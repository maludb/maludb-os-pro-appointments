<?php
/**
 * Waitlist Save — Add guest to waitlist
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../helpers/availability.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo '<div class="alert alert-danger">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
$pdo = db();

$guestId = (int)($_POST['guest_id'] ?? 0) ?: null;
$guestName = trim($_POST['guest_name'] ?? '');
$partySize = (int)($_POST['party_size'] ?? 0);
$phone = trim($_POST['phone'] ?? '') ?: null;
$quotedWait = isset($_POST['quoted_wait_minutes']) && $_POST['quoted_wait_minutes'] !== '' ? (int)$_POST['quoted_wait_minutes'] : null;
$seatingPref = trim($_POST['seating_preference'] ?? '') ?: null;
$notes = trim($_POST['notes'] ?? '') ?: null;

if ($guestName === '' || $partySize < 1) {
    echo '<div class="alert alert-danger">Guest name and party size are required.</div>';
    exit;
}

if ($quotedWait === null || $quotedWait < 0) {
    echo '<div class="alert alert-danger">Please enter the quoted wait time.</div>';
    exit;
}

// Calculate queue position (max current + 1)
$stmt = $pdo->prepare(
    "SELECT COALESCE(MAX(queue_position), 0) + 1 AS next_pos
     FROM waitlist WHERE restaurant_id = ? AND status IN ('waiting', 'notified')"
);
$stmt->execute([$restaurantId]);
$nextPos = (int)$stmt->fetchColumn();

$estimatedWait = $quotedWait;

$stmt = $pdo->prepare(
    "INSERT INTO waitlist (restaurant_id, guest_id, guest_name, party_size, phone,
     estimated_wait_minutes, status, queue_position, seating_preference, notes, added_by)
     VALUES (?, ?, ?, ?, ?, ?, 'waiting', ?, ?, ?, ?)"
);
$stmt->execute([
    $restaurantId, $guestId, $guestName, $partySize, $phone,
    $estimatedWait, $nextPos, $seatingPref, $notes, currentUserId()
]);

// Log activity
$stmt = $pdo->prepare(
    "INSERT INTO activity_log (restaurant_id, user_id, action, entity_type, entity_id, description, ip_address)
     VALUES (?, ?, 'create', 'waitlist', ?, ?, ?)"
);
$desc = "Added {$guestName} (party of {$partySize}) to waitlist at position #{$nextPos}";
$stmt->execute([$restaurantId, currentUserId(), $pdo->lastInsertId(), $desc, $_SERVER['REMOTE_ADDR'] ?? null]);

header('HX-Trigger: refreshWaitlist');
echo '<div class="alert alert-success">Added to waitlist at position #' . $nextPos . ' (~' . $estimatedWait . ' min wait).</div>';
echo '<script>document.getElementById("waitlist-modal-container").innerHTML="";</script>';
