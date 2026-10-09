<?php
/**
 * POST handler: Update reservation (edit, reassign table, update notes)
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
$action = trim($_POST['action'] ?? '');

if (!$resId || !$restaurantId) {
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit;
}

$pdo = db();

// Verify reservation belongs to this restaurant
$stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ? AND restaurant_id = ?");
$stmt->execute([$resId, $restaurantId]);
$res = $stmt->fetch();

if (!$res) {
    echo '<div class="alert alert-danger">Reservation not found.</div>';
    exit;
}

// Handle inline updates (table reassign, notes)
if ($action === 'reassign_table') {
    $tableId = (int)($_POST['table_id'] ?? 0) ?: null;

    if ($tableId) {
        $stmt = $pdo->prepare("SELECT id FROM tables WHERE id = ? AND restaurant_id = ?");
        $stmt->execute([$tableId, $restaurantId]);
        if (!$stmt->fetch()) {
            echo '<div class="alert alert-danger">Invalid table.</div>';
            exit;
        }
    }

    $stmt = $pdo->prepare("UPDATE reservations SET table_id = ? WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$tableId, $resId, $restaurantId]);

    // Log
    $stmt = $pdo->prepare(
        "INSERT INTO activity_log (restaurant_id, user_id, action, entity_type, entity_id, description, ip_address)
         VALUES (?, ?, 'update', 'reservation', ?, ?, ?)"
    );
    $stmt->execute([$restaurantId, currentUserId(), $resId, "Table reassigned for reservation #" . $res['confirmation_code'], $_SERVER['REMOTE_ADDR'] ?? null]);

    echo '<div class="alert alert-success alert-dismissible fade show" id="update-table-success">
            <i class="feather-check-circle me-1"></i> Table updated.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    exit;
}

if ($action === 'update_requests') {
    $requests = trim($_POST['special_requests'] ?? '') ?: null;
    $stmt = $pdo->prepare("UPDATE reservations SET special_requests = ? WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$requests, $resId, $restaurantId]);

    echo '<div class="alert alert-success alert-dismissible fade show" id="update-requests-success">
            <i class="feather-check-circle me-1"></i> Special requests updated.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    exit;
}

if ($action === 'update_notes') {
    $notes = trim($_POST['internal_notes'] ?? '') ?: null;
    $stmt = $pdo->prepare("UPDATE reservations SET internal_notes = ? WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$notes, $resId, $restaurantId]);

    echo '<div class="alert alert-success alert-dismissible fade show" id="update-notes-success">
            <i class="feather-check-circle me-1"></i> Notes updated.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    exit;
}

// Handle full edit
if ($action !== 'edit') {
    echo '<div class="alert alert-danger">Invalid action.</div>';
    exit;
}

$date = trim($_POST['reservation_date'] ?? '');
$time = trim($_POST['reservation_time'] ?? '');
$partySize = (int)($_POST['party_size'] ?? 0);
$tableId = (int)($_POST['table_id'] ?? 0) ?: null;
$turnTime = trim($_POST['turn_time_minutes'] ?? '') !== '' ? (int)$_POST['turn_time_minutes'] : null;
$specialRequests = trim($_POST['special_requests'] ?? '') ?: null;
$internalNotes = trim($_POST['internal_notes'] ?? '') ?: null;

if ($date === '' || $time === '' || $partySize < 1) {
    echo '<div class="alert alert-danger">Date, time, and party size are required.</div>';
    exit;
}

// Validate table if provided
if ($tableId) {
    $stmt = $pdo->prepare("SELECT id FROM tables WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$tableId, $restaurantId]);
    if (!$stmt->fetch()) {
        $tableId = null;
    }
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "UPDATE reservations SET reservation_date = ?, reservation_time = ?, party_size = ?,
                table_id = ?, turn_time_minutes = ?, special_requests = ?, internal_notes = ?
         WHERE id = ? AND restaurant_id = ?"
    );
    $stmt->execute([$date, $time, $partySize, $tableId, $turnTime, $specialRequests, $internalNotes, $resId, $restaurantId]);

    // Log activity
    $description = "Edited reservation #" . $res['confirmation_code'] . ": date={$date}, time={$time}, party={$partySize}";
    $stmt = $pdo->prepare(
        "INSERT INTO activity_log (restaurant_id, user_id, action, entity_type, entity_id, description, old_value, new_value, ip_address)
         VALUES (?, ?, 'update', 'reservation', ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $restaurantId, currentUserId(), $resId, $description,
        json_encode(['date' => $res['reservation_date'], 'time' => $res['reservation_time'], 'party_size' => $res['party_size']]),
        json_encode(['date' => $date, 'time' => $time, 'party_size' => $partySize]),
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);

    $pdo->commit();

    // Reload detail page into #page-content via HTMX
    $_GET['id'] = $resId;
    include __DIR__ . '/detail.php';
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    echo '<div class="alert alert-danger">An error occurred while updating.</div>';
}
