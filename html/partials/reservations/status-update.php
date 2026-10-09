<?php
/**
 * POST handler: Update reservation status
 * Validates transitions, sets timestamps, updates guest stats
 */
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Invalid security token.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
$resId = (int)($_POST['reservation_id'] ?? 0);
$newStatus = trim($_POST['new_status'] ?? '');

if (!$resId || !$restaurantId) {
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit;
}

$pdo = db();

// Fetch reservation
$stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ? AND restaurant_id = ?");
$stmt->execute([$resId, $restaurantId]);
$res = $stmt->fetch();

if (!$res) {
    echo '<div class="alert alert-danger">Reservation not found.</div>';
    exit;
}

// Validate status transition
$transitions = [
    'pending' => ['confirmed', 'cancelled'],
    'confirmed' => ['seated', 'cancelled', 'no_show'],
    'seated' => ['completed'],
];

$allowed = $transitions[$res['status']] ?? [];
if (!in_array($newStatus, $allowed)) {
    echo '<div class="alert alert-danger">Invalid status transition from ' . htmlspecialchars($res['status']) . ' to ' . htmlspecialchars($newStatus) . '.</div>';
    exit;
}

try {
    $pdo->beginTransaction();

    // Build update
    $setClauses = ["status = ?"];
    $params = [$newStatus];

    if ($newStatus === 'seated') {
        $setClauses[] = "seated_at = NOW()";
        $setClauses[] = "meal_status = 'seated'";
    } elseif ($newStatus === 'completed') {
        $setClauses[] = "completed_at = NOW()";
        $setClauses[] = "meal_status = NULL";
    } elseif ($newStatus === 'cancelled') {
        $setClauses[] = "cancelled_at = NOW()";
        $setClauses[] = "meal_status = NULL";
    }

    $params[] = $resId;
    $params[] = $restaurantId;

    $stmt = $pdo->prepare("UPDATE reservations SET " . implode(', ', $setClauses) . " WHERE id = ? AND restaurant_id = ?");
    $stmt->execute($params);

    // Update guest stats
    if ($newStatus === 'no_show') {
        $stmt = $pdo->prepare("UPDATE guests SET noshow_count = noshow_count + 1 WHERE id = ?");
        $stmt->execute([$res['guest_id']]);
    } elseif ($newStatus === 'completed') {
        $stmt = $pdo->prepare("UPDATE guests SET visit_count = visit_count + 1, last_visit_at = ? WHERE id = ?");
        $stmt->execute([$res['reservation_date'], $res['guest_id']]);
    }

    // Log activity
    $description = "Status changed from " . $res['status'] . " to " . $newStatus . " for reservation #" . $res['confirmation_code'];
    $stmt = $pdo->prepare(
        "INSERT INTO activity_log (restaurant_id, user_id, action, entity_type, entity_id, description, old_value, new_value, ip_address)
         VALUES (?, ?, 'status_change', 'reservation', ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $restaurantId, currentUserId(), $resId, $description,
        json_encode(['status' => $res['status']]),
        json_encode(['status' => $newStatus]),
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);

    $pdo->commit();

    // Send notification emails
    require_once __DIR__ . '/../../../helpers/notifications.php';
    if ($newStatus === 'cancelled') {
        sendCancellationEmail($resId);
    } elseif ($newStatus === 'confirmed') {
        sendConfirmationEmail($resId);
        sendConfirmationSMS($resId);
    }

    // If called from waitlist, show success message and trigger refresh
    $fromWaitlist = ($_POST['from_waitlist'] ?? '') === '1';
    if ($fromWaitlist) {
        header('HX-Trigger: refreshWaitlist');
        echo '<div class="alert alert-success alert-dismissible fade show">
                <i class="feather-check-circle me-1"></i> Reservation seated.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>';
        exit;
    }

    // Reload the detail page via HTMX retarget (stay in #page-content)
    $_GET['id'] = $resId;
    include __DIR__ . '/detail.php';
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    echo '<div class="alert alert-danger">An error occurred while updating status.</div>';
}
