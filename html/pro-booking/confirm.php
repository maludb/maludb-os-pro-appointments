<?php
require_once '../../helpers/db.php';
require_once '../../helpers/csrf.php';
require_once '../../helpers/professional-booking.php';
require_once '../../helpers/professional-notifications.php';
require_once '../../helpers/validation.php';
require_once '../../helpers/professional-availability.php';

session_start();

function generateProfessionalPublicConfirmationCode(PDO $pdo, int $restaurantId): string {
    for ($attempt = 0; $attempt < 20; $attempt++) {
        $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM professional_appointments WHERE restaurant_id = ? AND confirmation_code = ?"
        );
        $stmt->execute([$restaurantId, $code]);

        if ((int)$stmt->fetchColumn() === 0) {
            return $code;
        }
    }

    return strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-danger" id="professional-public-confirm-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="professional-public-confirm-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$slug = strtolower(trim($_POST['professional'] ?? ''));
$serviceId = (int)($_POST['service_id'] ?? 0);
$startAtInput = trim($_POST['start_at'] ?? '');
$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$clientNotes = trim($_POST['client_notes'] ?? '');

if ($slug === '') {
    echo '<div class="alert alert-danger" id="professional-public-confirm-slug-error">Booking page not specified.</div>';
    exit;
}

$profile = getProfessionalProfileByBookingSlug($slug);
if (!$profile) {
    echo '<div class="alert alert-danger" id="professional-public-confirm-profile-error">The requested booking page could not be found.</div>';
    exit;
}

if ((int)$profile['is_public_booking_enabled'] !== 1) {
    echo '<div class="alert alert-danger" id="professional-public-confirm-disabled-error">Online booking is not currently enabled for this business.</div>';
    exit;
}

if ($serviceId <= 0) {
    echo '<div class="alert alert-danger" id="professional-public-confirm-service-error">Please choose a service.</div>';
    exit;
}

if ($startAtInput === '') {
    echo '<div class="alert alert-danger" id="professional-public-confirm-time-error">Please choose an available appointment time.</div>';
    exit;
}

if ($firstName === '' || $lastName === '') {
    echo '<div class="alert alert-danger" id="professional-public-confirm-name-error">First and last name are required.</div>';
    exit;
}

if ($phone === '' || $email === '') {
    echo '<div class="alert alert-danger" id="professional-public-confirm-contact-error">Phone and email are required for online booking.</div>';
    exit;
}

if (!validate_email($email)) {
    echo '<div class="alert alert-danger" id="professional-public-confirm-email-error">Please enter a valid email address.</div>';
    exit;
}

$restaurantId = (int)$profile['restaurant_id'];
$professionalUserId = (int)($profile['owner_user_id'] ?? 0);

if ($professionalUserId <= 0) {
    echo '<div class="alert alert-danger" id="professional-public-confirm-owner-error">This booking profile is not configured correctly yet.</div>';
    exit;
}

$slotValidation = validateProfessionalSlot($restaurantId, $serviceId, $startAtInput, [
    'public_booking' => true,
]);

if (!$slotValidation['is_available']) {
    echo '<div class="alert alert-danger" id="professional-public-confirm-slot-error">' . htmlspecialchars($slotValidation['message']) . '</div>';
    exit;
}

$service = $slotValidation['service'];
$slot = $slotValidation['slot'];
$timezone = new DateTimeZone($profile['timezone']);
$startAtObject = professionalNormalizeDateTime($slot['start_at'], $timezone);
$endAtObject = professionalNormalizeDateTime($slot['end_at'], $timezone);

if ($startAtObject === null || $endAtObject === null) {
    echo '<div class="alert alert-danger" id="professional-public-confirm-datetime-error">The selected appointment time is invalid.</div>';
    exit;
}

$pdo = db();

try {
    $pdo->beginTransaction();

    $client = null;
    $clientId = 0;

    $clientByEmailStmt = $pdo->prepare(
        "SELECT * FROM professional_clients WHERE restaurant_id = ? AND email = ? LIMIT 1"
    );
    $clientByEmailStmt->execute([$restaurantId, $email]);
    $client = $clientByEmailStmt->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        $clientByPhoneStmt = $pdo->prepare(
            "SELECT * FROM professional_clients WHERE restaurant_id = ? AND phone = ? LIMIT 1"
        );
        $clientByPhoneStmt->execute([$restaurantId, $phone]);
        $client = $clientByPhoneStmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($client) {
        $clientId = (int)$client['id'];
        $clientUpdateStmt = $pdo->prepare(
            "UPDATE professional_clients SET
                first_name = ?,
                last_name = ?,
                email = ?,
                phone = ?,
                updated_at = NOW()
             WHERE id = ? AND restaurant_id = ?"
        );
        $clientUpdateStmt->execute([
            $firstName,
            $lastName,
            $email,
            $phone,
            $clientId,
            $restaurantId,
        ]);
    } else {
        $clientInsertStmt = $pdo->prepare(
            "INSERT INTO professional_clients (
                restaurant_id,
                first_name,
                last_name,
                email,
                phone,
                created_at,
                updated_at
             ) VALUES (?, ?, ?, ?, ?, NOW(), NOW())"
        );
        $clientInsertStmt->execute([
            $restaurantId,
            $firstName,
            $lastName,
            $email,
            $phone,
        ]);
        $clientId = (int)$pdo->lastInsertId();
    }

    $clientDateValue = $startAtObject->format('Y-m-d H:i:s');
    $clientLastAppointmentStmt = $pdo->prepare(
        "UPDATE professional_clients SET
            last_appointment_at = CASE
                WHEN last_appointment_at IS NULL OR last_appointment_at < ? THEN ?
                ELSE last_appointment_at
            END,
            updated_at = NOW()
         WHERE id = ? AND restaurant_id = ?"
    );
    $clientLastAppointmentStmt->execute([$clientDateValue, $clientDateValue, $clientId, $restaurantId]);

    $confirmationCode = generateProfessionalPublicConfirmationCode($pdo, $restaurantId);

    $appointmentInsertStmt = $pdo->prepare(
        "INSERT INTO professional_appointments (
            restaurant_id,
            professional_user_id,
            client_id,
            service_id,
            status,
            source,
            appointment_date,
            start_at,
            end_at,
            service_name,
            duration_minutes,
            buffer_before_minutes,
            buffer_after_minutes,
            price,
            currency_code,
            location_type,
            location_label,
            confirmation_code,
            client_notes,
            internal_notes,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, 'confirmed', 'public_booking', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NOW(), NOW())"
    );
    $appointmentInsertStmt->execute([
        $restaurantId,
        $professionalUserId,
        $clientId,
        (int)$service['id'],
        $startAtObject->format('Y-m-d'),
        $startAtObject->format('Y-m-d H:i:s'),
        $endAtObject->format('Y-m-d H:i:s'),
        $service['name'],
        (int)$service['duration_minutes'],
        (int)$service['effective_buffer_before_minutes'],
        (int)$service['effective_buffer_after_minutes'],
        $service['price'],
        $service['currency_code'],
        $slot['location_type'] ?: null,
        $slot['location_label'] ?: null,
        $confirmationCode,
        $clientNotes !== '' ? $clientNotes : null,
    ]);

    $appointmentId = (int)$pdo->lastInsertId();

    $pdo->commit();

    professionalLogAppointmentActivity(
        $restaurantId,
        null,
        $appointmentId,
        'public_booking_create',
        'Client booked appointment #' . $confirmationCode . ' online.',
        null,
        [
            'service_id' => (int)$service['id'],
            'start_at' => $startAtObject->format('Y-m-d H:i:s'),
            'source' => 'public_booking',
        ],
        $_SERVER['REMOTE_ADDR'] ?? null
    );

    professionalSendAppointmentConfirmationNotifications($appointmentId);

    $displayName = $profile['display_name'] ?: $profile['business_name'];
    ?>
    <div class="card border-success shadow-sm" id="professional-public-confirm-success-card">
        <div class="card-body text-center" id="professional-public-confirm-success-body">
            <div class="mb-3" id="professional-public-confirm-success-icon">
                <span style="font-size: 3rem; color: #198754;">&#10003;</span>
            </div>
            <h4 class="text-success mb-2" id="professional-public-confirm-success-title">Appointment Confirmed</h4>
            <p class="mb-1" id="professional-public-confirm-success-code">
                Confirmation code: <strong><code><?php echo htmlspecialchars($confirmationCode); ?></code></strong>
            </p>
            <p class="text-muted small mb-4" id="professional-public-confirm-success-note">
                Save this code. You can now use it with the client last name to reschedule or cancel online.
            </p>

            <div class="card bg-light border-0 d-inline-block text-start p-3 mb-3" id="professional-public-confirm-success-details">
                <p class="mb-1" id="professional-public-confirm-success-detail-business"><strong>Business:</strong> <?php echo htmlspecialchars($displayName); ?></p>
                <p class="mb-1" id="professional-public-confirm-success-detail-service"><strong>Service:</strong> <?php echo htmlspecialchars($service['name']); ?></p>
                <p class="mb-1" id="professional-public-confirm-success-detail-date"><strong>Date:</strong> <?php echo htmlspecialchars($startAtObject->format('l, F j, Y')); ?></p>
                <p class="mb-1" id="professional-public-confirm-success-detail-time"><strong>Time:</strong> <?php echo htmlspecialchars($startAtObject->format('g:ia')); ?></p>
                <p class="mb-1" id="professional-public-confirm-success-detail-client"><strong>Client:</strong> <?php echo htmlspecialchars($firstName . ' ' . $lastName); ?></p>
                <?php if (!empty($slot['location_label']) || !empty($slot['location_type'])): ?>
                <p class="mb-1" id="professional-public-confirm-success-detail-location"><strong>Location:</strong> <?php echo htmlspecialchars($slot['location_label'] ?: ucwords(str_replace('_', ' ', $slot['location_type']))); ?></p>
                <?php endif; ?>
                <p class="mb-0" id="professional-public-confirm-success-detail-status"><strong>Status:</strong> <span class="badge text-bg-success">Confirmed</span></p>
            </div>

            <div id="professional-public-confirm-success-actions">
                <a href="/pro-booking/index.php?professional=<?php echo urlencode($slug); ?>" class="btn btn-outline-dark" id="professional-public-confirm-success-book-again">
                    Book Another Appointment
                </a>
                <a href="/pro-booking/lookup.php?professional=<?php echo urlencode($slug); ?>" class="btn btn-outline-secondary ms-2" id="professional-public-confirm-success-manage">
                    Manage Appointment
                </a>
            </div>
        </div>
    </div>
    <script>
    document.getElementById('professional-public-booking-step1-card').style.display = 'none';
    document.getElementById('professional-public-booking-step2-card').style.display = 'none';
    document.getElementById('professional-public-booking-step3-card').style.display = 'none';
    </script>
    <?php
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo '<div class="alert alert-danger" id="professional-public-confirm-save-error">An error occurred while creating your appointment. Please try again.</div>';
}
