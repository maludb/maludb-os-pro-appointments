<?php
/**
 * Clear Waitlist — marks all active waitlist entries as 'left'
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();
verify_csrf_token($_POST['csrf_token'] ?? '');
$restaurantId = currentRestaurantId();
$pdo = db();

$stmt = $pdo->prepare(
    "UPDATE waitlist SET status = 'left', updated_at = NOW()
     WHERE restaurant_id = ? AND status IN ('waiting', 'notified')"
);
$stmt->execute([$restaurantId]);
$cleared = $stmt->rowCount();

// Log activity
$stmt = $pdo->prepare(
    "INSERT INTO activity_log (restaurant_id, user_id, action, details)
     VALUES (?, ?, 'waitlist_cleared', ?)"
);
$stmt->execute([$restaurantId, currentUserId(), "Cleared $cleared waitlist entries"]);

echo '<div class="alert alert-success alert-dismissible fade show" id="waitlist-clear-success">';
echo "Cleared $cleared waitlist " . ($cleared === 1 ? 'entry' : 'entries') . '.';
echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
echo '</div>';

// Trigger waitlist refresh
echo '<script>document.body.dispatchEvent(new Event("refreshWaitlist"));</script>';
