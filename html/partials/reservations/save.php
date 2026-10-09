<?php
/**
 * POST handler: Create a new reservation
 */
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';
require_once '../../../helpers/availability.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="save-res-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="save-res-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
if (!$restaurantId) {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="save-res-no-restaurant">No restaurant selected.</div>';
    exit;
}

// Collect inputs
$date = trim($_POST['reservation_date'] ?? '');
$time = trim($_POST['reservation_time'] ?? '');
$partySize = (int)($_POST['party_size'] ?? 0);
$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$guestId = (int)($_POST['guest_id'] ?? 0);
$tableId = (int)($_POST['table_id'] ?? 0) ?: null;
$source = trim($_POST['source'] ?? 'phone');
$specialRequests = trim($_POST['special_requests'] ?? '') ?: null;
$internalNotes = trim($_POST['internal_notes'] ?? '') ?: null;
$turnTimeOverride = (int)($_POST['turn_time'] ?? 0) ?: null;

// Validate required fields
if ($date === '') {
    echo '<div class="alert alert-danger" id="save-res-date-error">Date is required.</div>';
    exit;
}

if ($time === '') {
    echo '<div class="alert alert-danger" id="save-res-time-error">Please select a time slot.</div>';
    exit;
}

if ($partySize < 1) {
    echo '<div class="alert alert-danger" id="save-res-party-error">Party size must be at least 1.</div>';
    exit;
}

if ($firstName === '' || $lastName === '') {
    echo '<div class="alert alert-danger" id="save-res-name-error">Guest first and last name are required.</div>';
    exit;
}

if (!in_array($source, ['online', 'phone', 'walk_in', 'staff'])) {
    $source = 'phone';
}

$pdo = db();

try {
    $pdo->beginTransaction();

    // Find or create guest
    if ($guestId > 0) {
        // Verify guest belongs to this restaurant
        $stmt = $pdo->prepare("SELECT id FROM guests WHERE id = ? AND restaurant_id = ?");
        $stmt->execute([$guestId, $restaurantId]);
        if (!$stmt->fetch()) {
            $guestId = 0; // Reset if invalid
        }
    }

    if ($guestId <= 0) {
        // Try to match existing guest by email or phone within restaurant
        if ($email !== '') {
            $stmt = $pdo->prepare("SELECT id FROM guests WHERE restaurant_id = ? AND email = ? LIMIT 1");
            $stmt->execute([$restaurantId, $email]);
            $existing = $stmt->fetch();
            if ($existing) $guestId = (int)$existing['id'];
        }

        if ($guestId <= 0 && $phone !== '') {
            $stmt = $pdo->prepare("SELECT id FROM guests WHERE restaurant_id = ? AND phone = ? LIMIT 1");
            $stmt->execute([$restaurantId, $phone]);
            $existing = $stmt->fetch();
            if ($existing) $guestId = (int)$existing['id'];
        }

        if ($guestId <= 0) {
            // Create new guest
            $stmt = $pdo->prepare(
                "INSERT INTO guests (restaurant_id, first_name, last_name, email, phone, visit_count, noshow_count, is_active)
                 VALUES (?, ?, ?, ?, ?, 0, 0, 1)"
            );
            $stmt->execute([$restaurantId, $firstName, $lastName, $email ?: null, $phone ?: null]);
            $guestId = (int)$pdo->lastInsertId();
        } else {
            // Update existing guest info
            $stmt = $pdo->prepare("UPDATE guests SET first_name = ?, last_name = ?, phone = COALESCE(?, phone), email = COALESCE(?, email) WHERE id = ?");
            $stmt->execute([$firstName, $lastName, $phone ?: null, $email ?: null, $guestId]);
        }
    }

    // Auto-assign table if not manually selected
    if (!$tableId) {
        $tableId = findAvailableTable($restaurantId, $date, $time, $partySize);
    } else {
        // Validate table belongs to this restaurant
        $stmt = $pdo->prepare("SELECT id FROM tables WHERE id = ? AND restaurant_id = ?");
        $stmt->execute([$tableId, $restaurantId]);
        if (!$stmt->fetch()) {
            $tableId = null;
        }
    }

    // Generate confirmation code
    $confirmationCode = generateConfirmationCode($restaurantId, $date);

    // Staff-created reservations are auto-confirmed
    $status = 'confirmed';

    // Insert reservation
    $stmt = $pdo->prepare(
        "INSERT INTO reservations (restaurant_id, guest_id, table_id, reservation_date, reservation_time,
                party_size, status, confirmation_code, source, special_requests, turn_time_minutes,
                internal_notes, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $restaurantId, $guestId, $tableId, $date, $time,
        $partySize, $status, $confirmationCode, $source, $specialRequests, $turnTimeOverride,
        $internalNotes, currentUserId()
    ]);
    $reservationId = (int)$pdo->lastInsertId();

    // Log activity
    $stmt = $pdo->prepare(
        "INSERT INTO activity_log (restaurant_id, user_id, action, entity_type, entity_id, description, ip_address)
         VALUES (?, ?, 'create', 'reservation', ?, ?, ?)"
    );
    $description = "Created reservation #{$confirmationCode} for {$firstName} {$lastName}, party of {$partySize} on {$date} at " . date('g:ia', strtotime($time));
    $stmt->execute([$restaurantId, currentUserId(), $reservationId, $description, $_SERVER['REMOTE_ADDR'] ?? null]);

    $pdo->commit();

    // Get table name for display
    $tableName = 'Auto-assign (pending)';
    if ($tableId) {
        $stmt = $pdo->prepare("SELECT table_name FROM tables WHERE id = ?");
        $stmt->execute([$tableId]);
        $t = $stmt->fetch();
        if ($t) $tableName = $t['table_name'];
    }

    echo '<script>window.scrollTo({top:0,behavior:"smooth"});</script>';
    echo '<div class="alert alert-success" id="save-res-success">
            <h5 class="alert-heading"><i class="feather-check-circle me-1"></i> Reservation Created</h5>
            <p class="mb-1"><strong>Confirmation Code:</strong> <code>' . htmlspecialchars($confirmationCode) . '</code></p>
            <p class="mb-1"><strong>Guest:</strong> ' . htmlspecialchars($firstName . ' ' . $lastName) . '</p>
            <p class="mb-1"><strong>Date/Time:</strong> ' . date('l, M j, Y', strtotime($date)) . ' at ' . date('g:ia', strtotime($time)) . '</p>
            <p class="mb-1"><strong>Party Size:</strong> ' . $partySize . '</p>
            <p class="mb-1"><strong>Table:</strong> ' . htmlspecialchars($tableName) . '</p>
            <p class="mb-0"><strong>Status:</strong> <span class="badge bg-primary">Confirmed</span></p>
            <hr>
            <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-primary"
                    hx-get="/partials/reservations/create-form.php"
                    hx-target="#page-content"
                    hx-swap="innerHTML">
                <i class="feather-plus me-1"></i> New Reservation
            </button>
            <button class="btn btn-sm btn-outline-secondary"
                    hx-get="/partials/reservations/calendar.php"
                    hx-target="#page-content"
                    hx-swap="innerHTML">
                <i class="feather-calendar me-1"></i> View Calendar
            </button>
            </div>
          </div>';

} catch (Exception $e) {
    $pdo->rollBack();
    echo '<script>window.scrollTo({top:0,behavior:"smooth"});</script>';
    echo '<div class="alert alert-danger" id="save-res-error">An error occurred while creating the reservation. Please try again.</div>';
}
