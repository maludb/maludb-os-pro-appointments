<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../helpers/professional-notifications.php';
require_once __DIR__ . '/../../../helpers/validation.php';
require_once __DIR__ . '/../../../helpers/professional-availability.php';

requireAuth();
requireManager();

function generateProfessionalAppointmentCode(PDO $pdo, int $restaurantId): string {
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
    echo '<div class="alert alert-warning" id="professional-save-appointment-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="professional-save-appointment-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
$userId = currentUserId();

if (!$restaurantId) {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="professional-save-appointment-no-restaurant">No professional account is currently selected.</div>';
    exit;
}

$appointmentId = (int)($_POST['appointment_id'] ?? 0);
$serviceId = (int)($_POST['service_id'] ?? 0);
$clientId = (int)($_POST['client_id'] ?? 0);
$startAtInput = trim($_POST['start_at'] ?? '');
$status = trim($_POST['status'] ?? 'confirmed');
$source = trim($_POST['source'] ?? 'staff');
$clientNotes = trim($_POST['client_notes'] ?? '');
$internalNotes = trim($_POST['internal_notes'] ?? '');
$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$serviceContactName = trim($_POST['service_contact_name'] ?? '');
$servicePhone = trim($_POST['service_phone'] ?? '');
$serviceContactMethod = trim($_POST['service_contact_method'] ?? '');
$serviceAddress1 = trim($_POST['service_address_1'] ?? '');
$serviceCity = trim($_POST['service_city'] ?? '');
$serviceState = trim($_POST['service_state'] ?? '');
$servicePostalCode = substr(trim($_POST['service_postal_code'] ?? ''), 0, 5);

$allowedStatuses = ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'];
$allowedSources = ['staff', 'public_booking', 'imported', 'api'];

if ($serviceId <= 0) {
    echo '<div class="alert alert-danger" id="professional-save-appointment-service-error">Please select a service.</div>';
    exit;
}

if ($startAtInput === '') {
    echo '<div class="alert alert-danger" id="professional-save-appointment-start-error">Please choose an available appointment time.</div>';
    exit;
}

if ($firstName === '' || $lastName === '') {
    echo '<div class="alert alert-danger" id="professional-save-appointment-client-name-error">Client first and last name are required.</div>';
    exit;
}

if ($email !== '' && !validate_email($email)) {
    echo '<div class="alert alert-danger" id="professional-save-appointment-email-error">Please provide a valid client email address.</div>';
    exit;
}

if (!in_array($status, $allowedStatuses, true)) {
    echo '<div class="alert alert-danger" id="professional-save-appointment-status-error">Please choose a valid appointment status.</div>';
    exit;
}

if (!in_array($source, $allowedSources, true)) {
    $source = 'staff';
}

$pdo = db();
$isEdit = $appointmentId > 0;
$existingAppointment = null;

if ($isEdit) {
    $existingStmt = $pdo->prepare("SELECT * FROM professional_appointments WHERE id = ? AND restaurant_id = ? LIMIT 1");
    $existingStmt->execute([$appointmentId, $restaurantId]);
    $existingAppointment = $existingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$existingAppointment) {
        echo '<div class="alert alert-danger" id="professional-save-appointment-not-found">Appointment not found.</div>';
        exit;
    }
}

$profile = getProfessionalProfile($restaurantId);
if (!$profile) {
    echo '<div class="alert alert-danger" id="professional-save-appointment-profile-error">Professional scheduling settings are not configured yet.</div>';
    exit;
}

$service = getProfessionalService($restaurantId, $serviceId, ['allow_inactive' => $isEdit]);
if (!$service) {
    echo '<div class="alert alert-danger" id="professional-save-appointment-service-not-found">The selected service is not available.</div>';
    exit;
}

$timezone = new DateTimeZone($profile['timezone']);
$startAtObject = professionalNormalizeDateTime($startAtInput, $timezone);
if ($startAtObject === null) {
    echo '<div class="alert alert-danger" id="professional-save-appointment-start-invalid">Please choose a valid appointment time.</div>';
    exit;
}

$needsSlotValidation = !in_array($status, ['cancelled', 'no_show'], true);
if (
    $isEdit
    && $existingAppointment
    && $existingAppointment['start_at'] === $startAtObject->format('Y-m-d H:i:s')
    && (int)$existingAppointment['service_id'] === $serviceId
) {
    $needsSlotValidation = false;
}

$slotValidation = null;
if ($needsSlotValidation) {
    $slotValidation = validateProfessionalSlot($restaurantId, $serviceId, $startAtObject->format('Y-m-d H:i:s'), [
        'ignore_notice' => true,
        'ignore_horizon' => true,
        'exclude_appointment_id' => $appointmentId,
        'allow_inactive' => $isEdit,
    ]);

    if (!$slotValidation['is_available']) {
        echo '<div class="alert alert-danger" id="professional-save-appointment-slot-error">' . htmlspecialchars($slotValidation['message']) . '</div>';
        exit;
    }
}

$durationMinutes = (int)$service['duration_minutes'];
$bufferBeforeMinutes = (int)$service['effective_buffer_before_minutes'];
$bufferAfterMinutes = (int)$service['effective_buffer_after_minutes'];
$endAtObject = $startAtObject->modify('+' . $durationMinutes . ' minutes');

$locationType = $slotValidation['slot']['location_type'] ?? ($service['location_type'] ?: $profile['default_location_type']);
$locationLabel = $slotValidation['slot']['location_label'] ?? ($service['location_label'] ?: $profile['default_location_label']);
$appointmentDate = $startAtObject->format('Y-m-d');
$professionalUserId = (int)($profile['owner_user_id'] ?: $userId);

try {
    $pdo->beginTransaction();
    $savedAppointmentId = $appointmentId;

    $client = null;

    if ($clientId > 0) {
        $clientStmt = $pdo->prepare("SELECT * FROM professional_clients WHERE id = ? AND restaurant_id = ? LIMIT 1");
        $clientStmt->execute([$clientId, $restaurantId]);
        $client = $clientStmt->fetch(PDO::FETCH_ASSOC);

        if (!$client) {
            throw new RuntimeException('The selected client record was not found.');
        }
    } elseif ($email !== '') {
        $clientStmt = $pdo->prepare("SELECT * FROM professional_clients WHERE restaurant_id = ? AND email = ? LIMIT 1");
        $clientStmt->execute([$restaurantId, $email]);
        $client = $clientStmt->fetch(PDO::FETCH_ASSOC);
    } elseif ($phone !== '') {
        $clientStmt = $pdo->prepare("SELECT * FROM professional_clients WHERE restaurant_id = ? AND phone = ? LIMIT 1");
        $clientStmt->execute([$restaurantId, $phone]);
        $client = $clientStmt->fetch(PDO::FETCH_ASSOC);
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
            $email !== '' ? $email : null,
            $phone !== '' ? $phone : null,
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
            $email !== '' ? $email : null,
            $phone !== '' ? $phone : null,
        ]);
        $clientId = (int)$pdo->lastInsertId();
    }

    if (!in_array($status, ['cancelled'], true)) {
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
    }

    $confirmationCode = $existingAppointment['confirmation_code'] ?? null;
    if ($confirmationCode === null || $confirmationCode === '') {
        $confirmationCode = generateProfessionalAppointmentCode($pdo, $restaurantId);
    }

    $cancelledAt = null;
    $completedAt = null;

    if ($isEdit) {
        $cancelledAt = $existingAppointment['cancelled_at'] ?: null;
        $completedAt = $existingAppointment['completed_at'] ?: null;
    }

    if ($status === 'cancelled') {
        $cancelledAt = $cancelledAt ?: date('Y-m-d H:i:s');
        $completedAt = null;
    } elseif ($status === 'completed') {
        $completedAt = $completedAt ?: date('Y-m-d H:i:s');
        $cancelledAt = null;
    } else {
        $cancelledAt = null;
        $completedAt = null;
    }

    if ($isEdit) {
        $updateStmt = $pdo->prepare(
            "UPDATE professional_appointments SET
                professional_user_id = ?,
                client_id = ?,
                service_id = ?,
                status = ?,
                source = ?,
                appointment_date = ?,
                start_at = ?,
                end_at = ?,
                service_name = ?,
                duration_minutes = ?,
                buffer_before_minutes = ?,
                buffer_after_minutes = ?,
                price = ?,
                currency_code = ?,
                location_type = ?,
                location_label = ?,
                client_notes = ?,
                internal_notes = ?,
                service_contact_name = ?,
                service_phone = ?,
                service_contact_method = ?,
                service_address_1 = ?,
                service_city = ?,
                service_state = ?,
                service_postal_code = ?,
                cancelled_at = ?,
                completed_at = ?,
                updated_at = NOW()
             WHERE id = ? AND restaurant_id = ?"
        );
        $updateStmt->execute([
            $professionalUserId,
            $clientId,
            $serviceId,
            $status,
            $existingAppointment['source'] ?: $source,
            $appointmentDate,
            $startAtObject->format('Y-m-d H:i:s'),
            $endAtObject->format('Y-m-d H:i:s'),
            $service['name'],
            $durationMinutes,
            $bufferBeforeMinutes,
            $bufferAfterMinutes,
            $service['price'],
            $service['currency_code'],
            $locationType,
            $locationLabel,
            $clientNotes !== '' ? $clientNotes : null,
            $internalNotes !== '' ? $internalNotes : null,
            $serviceContactName !== '' ? $serviceContactName : null,
            $servicePhone !== '' ? $servicePhone : null,
            $serviceContactMethod !== '' ? $serviceContactMethod : null,
            $serviceAddress1 !== '' ? $serviceAddress1 : null,
            $serviceCity !== '' ? $serviceCity : null,
            $serviceState !== '' ? $serviceState : null,
            $servicePostalCode !== '' ? $servicePostalCode : null,
            $cancelledAt,
            $completedAt,
            $appointmentId,
            $restaurantId,
        ]);
    } else {
        $insertStmt = $pdo->prepare(
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
                service_contact_name,
                service_phone,
                service_contact_method,
                service_address_1,
                service_city,
                service_state,
                service_postal_code,
                cancelled_at,
                completed_at,
                created_at,
                updated_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
        );
        $insertStmt->execute([
            $restaurantId,
            $professionalUserId,
            $clientId,
            $serviceId,
            $status,
            $source,
            $appointmentDate,
            $startAtObject->format('Y-m-d H:i:s'),
            $endAtObject->format('Y-m-d H:i:s'),
            $service['name'],
            $durationMinutes,
            $bufferBeforeMinutes,
            $bufferAfterMinutes,
            $service['price'],
            $service['currency_code'],
            $locationType,
            $locationLabel,
            $confirmationCode,
            $clientNotes !== '' ? $clientNotes : null,
            $internalNotes !== '' ? $internalNotes : null,
            $serviceContactName !== '' ? $serviceContactName : null,
            $servicePhone !== '' ? $servicePhone : null,
            $serviceContactMethod !== '' ? $serviceContactMethod : null,
            $serviceAddress1 !== '' ? $serviceAddress1 : null,
            $serviceCity !== '' ? $serviceCity : null,
            $serviceState !== '' ? $serviceState : null,
            $servicePostalCode !== '' ? $servicePostalCode : null,
            $cancelledAt,
            $completedAt,
        ]);

        $savedAppointmentId = (int)$pdo->lastInsertId();
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo '<div class="alert alert-danger" id="professional-save-appointment-failure">Unable to save the appointment. Please review the selected client and time, then try again.</div>';
    exit;
}

if ($savedAppointmentId > 0) {
    if ($isEdit) {
        $previousStatus = (string)($existingAppointment['status'] ?? '');

        if ($previousStatus !== 'confirmed' && $status === 'confirmed') {
            professionalSendAppointmentConfirmationNotifications($savedAppointmentId);
        } elseif ($previousStatus !== 'cancelled' && $status === 'cancelled') {
            professionalSendAppointmentCancellationNotifications($savedAppointmentId);
        }
    } elseif ($status === 'confirmed') {
        professionalSendAppointmentConfirmationNotifications($savedAppointmentId);
    }
}

header('HX-Trigger-After-Swap: {"refreshProfessionalCalendar":true,"closeModal":true}');

echo '<div class="alert alert-success alert-dismissible fade show" id="professional-save-appointment-success">
        <i class="feather-check-circle me-1"></i> Appointment ' . ($isEdit ? 'updated' : 'created') . ' successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>';
