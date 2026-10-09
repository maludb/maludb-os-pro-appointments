<?php
/**
 * Event Save Handler — Create, update, cancel events
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo '<div class="alert alert-danger">Invalid security token.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
$pdo = db();
$action = $_POST['action'] ?? '';

// --- SAVE (create/update) ---
if ($action === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $eventDate = trim($_POST['event_date'] ?? '');
    $startTime = trim($_POST['start_time'] ?? '');
    $endTime = trim($_POST['end_time'] ?? '') ?: null;
    $maxCapacity = (int)($_POST['max_capacity'] ?? 0);
    $priceRaw = trim($_POST['price_per_person'] ?? '');
    $pricePerPerson = $priceRaw !== '' ? (float)$priceRaw : null;
    $status = trim($_POST['status'] ?? 'draft');
    $isPublic = isset($_POST['is_public']) ? 1 : 0;
    $description = trim($_POST['description'] ?? '') ?: null;
    $notes = trim($_POST['notes'] ?? '') ?: null;

    // Validate
    if ($name === '' || $eventDate === '' || $startTime === '') {
        echo '<div class="alert alert-danger">Name, date, and start time are required.</div>';
        exit;
    }

    if ($maxCapacity < 1) {
        echo '<div class="alert alert-danger">Max capacity must be at least 1.</div>';
        exit;
    }

    $validStatuses = ['draft', 'published', 'cancelled', 'completed'];
    if (!in_array($status, $validStatuses)) {
        $status = 'draft';
    }

    // Normalize times to HH:MM:SS
    if (strlen($startTime) === 5) $startTime .= ':00';
    if ($endTime !== null && strlen($endTime) === 5) $endTime .= ':00';

    if ($id > 0) {
        // Update
        $stmt = $pdo->prepare("SELECT id FROM events WHERE id = ? AND restaurant_id = ?");
        $stmt->execute([$id, $restaurantId]);
        if (!$stmt->fetch()) {
            echo '<div class="alert alert-danger">Event not found.</div>';
            exit;
        }

        $stmt = $pdo->prepare(
            "UPDATE events SET name = ?, description = ?, event_date = ?, start_time = ?,
             end_time = ?, max_capacity = ?, price_per_person = ?, status = ?,
             is_public = ?, notes = ?
             WHERE id = ? AND restaurant_id = ?"
        );
        $stmt->execute([
            $name, $description, $eventDate, $startTime,
            $endTime, $maxCapacity, $pricePerPerson, $status,
            $isPublic, $notes,
            $id, $restaurantId
        ]);
    } else {
        // Insert
        $stmt = $pdo->prepare(
            "INSERT INTO events (restaurant_id, name, description, event_date, start_time,
             end_time, max_capacity, price_per_person, status, is_public, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $restaurantId, $name, $description, $eventDate, $startTime,
            $endTime, $maxCapacity, $pricePerPerson, $status, $isPublic, $notes
        ]);
        $id = (int)$pdo->lastInsertId();
    }

    // Log activity
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO activity_log (restaurant_id, user_id, action, entity_type, entity_id, description, ip_address)
             VALUES (?, ?, 'update', 'event', ?, ?, ?)"
        );
        $stmt->execute([$restaurantId, currentUserId(), $id, "Event saved: {$name} on {$eventDate}", $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Exception $e) {}

    // Redirect to event detail
    header("HX-Redirect: ");
    $_GET['id'] = $id;
    include __DIR__ . '/detail.php';
    exit;
}

// --- CANCEL ---
if ($action === 'cancel') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id < 1) { exit; }

    $stmt = $pdo->prepare("UPDATE events SET status = 'cancelled' WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$id, $restaurantId]);

    header("HX-Redirect: ");
    $_GET['id'] = $id;
    include __DIR__ . '/detail.php';
    exit;
}

echo '<div class="alert alert-danger">Unknown action.</div>';
