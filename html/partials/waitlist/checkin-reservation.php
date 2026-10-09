<?php
/**
 * POST handler: Check in a reservation from the waitlist screen
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

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

if (!$resId || !$restaurantId) {
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit;
}

$pdo = db();

$stmt = $pdo->prepare("SELECT id, status, checked_in_at FROM reservations WHERE id = ? AND restaurant_id = ?");
$stmt->execute([$resId, $restaurantId]);
$res = $stmt->fetch();

if (!$res) {
    echo '<div class="alert alert-danger">Reservation not found.</div>';
    exit;
}

if (!in_array($res['status'], ['pending', 'confirmed'])) {
    echo '<div class="alert alert-warning">Reservation cannot be checked in (status: ' . htmlspecialchars($res['status']) . ').</div>';
    exit;
}

// Set checked_in_at and confirm if pending
$setClauses = ["checked_in_at = NOW()"];
if ($res['status'] === 'pending') {
    $setClauses[] = "status = 'confirmed'";
}

$stmt = $pdo->prepare("UPDATE reservations SET " . implode(', ', $setClauses) . " WHERE id = ? AND restaurant_id = ?");
$stmt->execute([$resId, $restaurantId]);

// Log activity
$stmt = $pdo->prepare(
    "INSERT INTO activity_log (restaurant_id, user_id, action, entity_type, entity_id, description, ip_address)
     VALUES (?, ?, 'check_in', 'reservation', ?, 'Guest checked in', ?)"
);
$stmt->execute([$restaurantId, currentUserId(), $resId, $_SERVER['REMOTE_ADDR'] ?? null]);

// Refresh waitlist
header('HX-Trigger: refreshWaitlist');
echo '<div class="alert alert-success alert-dismissible fade show" id="checkin-success">
        <i class="feather-check-circle me-1"></i> Guest checked in.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>';
