<?php
/**
 * Event Booking Save — Create a reservation linked to an event
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

$eventId = (int)($_POST['event_id'] ?? 0);
$guestId = (int)($_POST['guest_id'] ?? 0);
$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$phone = trim($_POST['phone'] ?? '') ?: null;
$email = trim($_POST['email'] ?? '') ?: null;
$partySize = (int)($_POST['party_size'] ?? 0);
$specialRequests = trim($_POST['special_requests'] ?? '') ?: null;

// Validate event
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND restaurant_id = ?");
$stmt->execute([$eventId, $restaurantId]);
$event = $stmt->fetch();

if (!$event) {
    echo '<div class="alert alert-danger">Event not found.</div>';
    exit;
}

if (!in_array($event['status'], ['draft', 'published'])) {
    echo '<div class="alert alert-danger">This event is not accepting bookings.</div>';
    exit;
}

if ($partySize < 1) {
    echo '<div class="alert alert-danger">Party size must be at least 1.</div>';
    exit;
}

if ($firstName === '' || $lastName === '') {
    echo '<div class="alert alert-danger">Guest first and last name are required.</div>';
    exit;
}

// Check capacity
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(party_size), 0) FROM reservations
     WHERE event_id = ? AND restaurant_id = ? AND status NOT IN ('cancelled', 'no_show')"
);
$stmt->execute([$eventId, $restaurantId]);
$booked = (int)$stmt->fetchColumn();
$spotsLeft = max(0, (int)$event['max_capacity'] - $booked);

if ($partySize > $spotsLeft) {
    echo '<div class="alert alert-danger">Not enough spots available. Only ' . $spotsLeft . ' remaining.</div>';
    exit;
}

try {
    $pdo->beginTransaction();

    // Find or create guest
    if ($guestId > 0) {
        $stmt = $pdo->prepare("SELECT id FROM guests WHERE id = ? AND restaurant_id = ?");
        $stmt->execute([$guestId, $restaurantId]);
        if (!$stmt->fetch()) {
            $guestId = 0;
        }
    }

    if ($guestId <= 0) {
        // Match by email or phone
        if ($email !== null) {
            $stmt = $pdo->prepare("SELECT id FROM guests WHERE restaurant_id = ? AND email = ? LIMIT 1");
            $stmt->execute([$restaurantId, $email]);
            $existing = $stmt->fetch();
            if ($existing) $guestId = (int)$existing['id'];
        }
        if ($guestId <= 0 && $phone !== null) {
            $stmt = $pdo->prepare("SELECT id FROM guests WHERE restaurant_id = ? AND phone = ? LIMIT 1");
            $stmt->execute([$restaurantId, $phone]);
            $existing = $stmt->fetch();
            if ($existing) $guestId = (int)$existing['id'];
        }
        if ($guestId <= 0) {
            $stmt = $pdo->prepare(
                "INSERT INTO guests (restaurant_id, first_name, last_name, email, phone, visit_count, noshow_count, is_active)
                 VALUES (?, ?, ?, ?, ?, 0, 0, 1)"
            );
            $stmt->execute([$restaurantId, $firstName, $lastName, $email, $phone]);
            $guestId = (int)$pdo->lastInsertId();
        } else {
            $stmt = $pdo->prepare("UPDATE guests SET first_name = ?, last_name = ?, phone = COALESCE(?, phone), email = COALESCE(?, email) WHERE id = ?");
            $stmt->execute([$firstName, $lastName, $phone, $email, $guestId]);
        }
    }

    // Generate confirmation code
    $confirmationCode = generateConfirmationCode($restaurantId, $event['event_date']);

    // Insert reservation linked to event
    $stmt = $pdo->prepare(
        "INSERT INTO reservations (restaurant_id, guest_id, event_id, reservation_date, reservation_time,
                party_size, status, confirmation_code, source, special_requests, created_by)
         VALUES (?, ?, ?, ?, ?, ?, 'confirmed', ?, 'staff', ?, ?)"
    );
    $stmt->execute([
        $restaurantId, $guestId, $eventId,
        $event['event_date'], $event['start_time'],
        $partySize, $confirmationCode, $specialRequests, currentUserId()
    ]);
    $reservationId = (int)$pdo->lastInsertId();

    // Log activity
    $stmt = $pdo->prepare(
        "INSERT INTO activity_log (restaurant_id, user_id, action, entity_type, entity_id, description, ip_address)
         VALUES (?, ?, 'create', 'reservation', ?, ?, ?)"
    );
    $desc = "Event booking: {$firstName} {$lastName} (party of {$partySize}) for {$event['name']} on {$event['event_date']}";
    $stmt->execute([$restaurantId, currentUserId(), $reservationId, $desc, $_SERVER['REMOTE_ADDR'] ?? null]);

    $pdo->commit();

    // Reload event detail
    header("HX-Retarget: #page-content");
    header("HX-Reswap: innerHTML");
    $_GET['id'] = $eventId;
    include __DIR__ . '/detail.php';
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    echo '<div class="alert alert-danger">An error occurred. Please try again.</div>';
}
