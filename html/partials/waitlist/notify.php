<?php
/**
 * Waitlist Notify — "Table Ready" button handler
 * Sends SMS if phone is on file, otherwise just marks as called.
 * Tracks notify_count and notified_at timestamp.
 * Allows up to 2 calls before suggesting no_response.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo '<div class="alert alert-danger">Invalid security token.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
$pdo = db();

$id = (int)($_POST['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT w.*, g.first_name, g.last_name, g.phone AS guest_phone
     FROM waitlist w LEFT JOIN guests g ON w.guest_id = g.id
     WHERE w.id = ? AND w.restaurant_id = ? AND w.status IN ('waiting', 'notified')"
);
$stmt->execute([$id, $restaurantId]);
$entry = $stmt->fetch();

if (!$entry) {
    echo '<div class="alert alert-warning">Waitlist entry not found or already processed.</div>';
    exit;
}

$phone = $entry['phone'] ?: ($entry['guest_phone'] ?? '');
$name = $entry['guest_id'] ? ($entry['first_name'] . ' ' . $entry['last_name']) : ($entry['guest_name'] ?: 'Unknown');
$currentCount = (int)($entry['notify_count'] ?? 0);
$newCount = $currentCount + 1;

// Update status, timestamp, and count
$stmt = $pdo->prepare(
    "UPDATE waitlist SET status = 'notified', notified_at = NOW(), notify_count = ? WHERE id = ?"
);
$stmt->execute([$newCount, $id]);

// Send SMS if phone is on file
$smsSent = false;
if ($phone !== '') {
    require_once __DIR__ . '/../../../helpers/notifications.php';
    $smsSent = sendWaitlistSMS($id);
}

// Log activity
$method = $phone !== '' ? ($smsSent ? 'SMS sent' : 'SMS failed') : 'called/announced';
$stmt = $pdo->prepare(
    "INSERT INTO activity_log (restaurant_id, user_id, action, entity_type, entity_id, description, ip_address)
     VALUES (?, ?, 'notify', 'waitlist', ?, ?, ?)"
);
$desc = "Table ready for {$name} — {$method} (call #{$newCount})";
$stmt->execute([$restaurantId, currentUserId(), $id, $desc, $_SERVER['REMOTE_ADDR'] ?? null]);

header('HX-Trigger: refreshWaitlist');

if ($phone !== '' && $smsSent) {
    echo '<div class="alert alert-success">SMS sent to ' . htmlspecialchars($name) . ' at ' . htmlspecialchars($phone) . ' (call #' . $newCount . ').</div>';
} elseif ($phone !== '') {
    echo '<div class="alert alert-warning">Marked as called (call #' . $newCount . '). SMS could not be sent — check Twilio settings.</div>';
} else {
    echo '<div class="alert alert-info">Marked as called for ' . htmlspecialchars($name) . ' (call #' . $newCount . '). No phone on file.</div>';
}
