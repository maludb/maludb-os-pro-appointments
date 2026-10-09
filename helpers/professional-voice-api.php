<?php
/**
 * Professional Voice / MCP API Service Layer
 *
 * Shared functions for the professional MCP server (pro.php).
 * No session dependency — professional identified by booking_slug.
 * All functions return ['success' => bool, ...data or 'error' => string].
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api-auth.php';
require_once __DIR__ . '/professional-availability.php';
require_once __DIR__ . '/professional-booking.php';
require_once __DIR__ . '/professional-notifications.php';

/**
 * Reuse the restaurant voiceFixDate() for relative date words.
 */
require_once __DIR__ . '/voice-api.php';

/**
 * Resolve a professional profile by booking slug, apply timezone.
 * Returns [profile, restaurantId] or an error array.
 */
function proVoiceResolveProfile(string $slug): array
{
    $profile = getProfessionalProfileByBookingSlug($slug);
    if (!$profile) {
        return ['success' => false, 'error' => 'Professional profile not found.'];
    }

    $restaurantId = (int)$profile['restaurant_id'];
    if (!mcp_business_allowed($restaurantId)) {
        return ['success' => false, 'error' => 'Professional profile not found.'];
    }

    // Apply timezone so date() calls use the professional's timezone
    try {
        date_default_timezone_set($profile['timezone']);
    } catch (Throwable $e) {
        // fall through
    }

    return ['success' => true, 'profile' => $profile, 'restaurant_id' => $restaurantId];
}

/**
 * Generate a unique confirmation code for a professional appointment.
 */
function proVoiceGenerateConfirmationCode(PDO $pdo, int $restaurantId): string
{
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

/**
 * Resolve a valid professional user id for API-created/updated appointments.
 */
function proVoiceResolveProfessionalUserId(
    PDO $pdo,
    int $restaurantId,
    array $profile = [],
    ?int $fallbackUserId = null
): int {
    $candidateIds = [];

    $ownerUserId = (int)($profile['owner_user_id'] ?? 0);
    if ($ownerUserId > 0) {
        $candidateIds[] = $ownerUserId;
    }

    $fallbackUserId = (int)($fallbackUserId ?? 0);
    if ($fallbackUserId > 0) {
        $candidateIds[] = $fallbackUserId;
    }

    foreach (array_unique($candidateIds) as $candidateId) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$candidateId]);
        if ((int)$stmt->fetchColumn() > 0) {
            return $candidateId;
        }
    }

    $stmt = $pdo->prepare(
        "SELECT ur.user_id
         FROM user_restaurants ur
         INNER JOIN users u ON u.id = ur.user_id
         WHERE ur.restaurant_id = ? AND ur.is_active = 1
         ORDER BY CASE WHEN ur.role = 'admin' THEN 0 ELSE 1 END, ur.user_id ASC
         LIMIT 1"
    );
    $stmt->execute([$restaurantId]);

    return (int)$stmt->fetchColumn();
}

/**
 * List active, public-bookable services for a professional.
 */
function proVoiceListServices(string $slug): array
{
    $resolved = proVoiceResolveProfile($slug);
    if (!$resolved['success']) {
        return $resolved;
    }

    $restaurantId = $resolved['restaurant_id'];
    $profile = $resolved['profile'];

    $stmt = db()->prepare(
        "SELECT id, name, description, duration_minutes, price, currency_code, location_type
         FROM professional_services
         WHERE restaurant_id = ? AND is_active = 1 AND is_public_bookable = 1
         ORDER BY sort_order ASC, name ASC"
    );
    $stmt->execute([$restaurantId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $services = [];
    foreach ($rows as $row) {
        $service = [
            'service_id' => (int)$row['id'],
            'name' => $row['name'],
            'duration_minutes' => (int)$row['duration_minutes'],
        ];
        if ($row['description']) {
            $service['description'] = $row['description'];
        }
        if ($row['price'] !== null) {
            $service['price'] = '$' . number_format((float)$row['price'], 2);
        }
        if ($row['location_type']) {
            $service['location_type'] = $row['location_type'];
        }
        $services[] = $service;
    }

    $message = count($services) > 0
        ? count($services) . ' service(s) available for booking.'
        : 'No services are currently available for booking.';

    return [
        'success' => true,
        'business_name' => $profile['business_name'] ?: $profile['display_name'],
        'services' => $services,
        'total_services' => count($services),
        'message' => $message,
    ];
}

/**
 * Check available appointment slots for a service on a date.
 */
function proVoiceCheckAvailability(string $slug, int $serviceId, string $date): array
{
    $date = voiceFixDate($date);

    $resolved = proVoiceResolveProfile($slug);
    if (!$resolved['success']) {
        return $resolved;
    }

    $restaurantId = $resolved['restaurant_id'];
    $profile = $resolved['profile'];

    $service = getProfessionalService($restaurantId, $serviceId, ['public_booking' => true]);
    if (!$service) {
        return ['success' => false, 'error' => 'Service not found or not available for booking.'];
    }

    // Validate date range
    $timezone = new DateTimeZone($profile['timezone']);
    $now = new DateTimeImmutable('now', $timezone);
    $today = $now->format('Y-m-d');

    if ($date < $today) {
        return ['success' => false, 'error' => 'Cannot book a date in the past.'];
    }

    $maxDays = (int)$profile['maximum_booking_horizon_days'];
    $maxDate = $now->modify("+{$maxDays} days")->format('Y-m-d');
    if ($date > $maxDate) {
        return ['success' => false, 'error' => "Appointments can only be booked up to {$maxDays} days in advance."];
    }

    $slots = getProfessionalAvailableSlots($restaurantId, $serviceId, $date, [
        'public_booking' => true,
    ]);

    $available = array_values(array_filter($slots, function ($s) {
        return $s['is_available'];
    }));

    $timeList = array_map(function ($s) {
        return $s['time_display'];
    }, $available);

    $message = count($available) > 0
        ? count($available) . ' time slot(s) available.'
        : 'No availability for this date.';

    return [
        'success' => true,
        'business_name' => $profile['business_name'] ?: $profile['display_name'],
        'service_name' => $service['name'],
        'service_id' => (int)$service['id'],
        'date' => $date,
        'date_display' => date('l, F j, Y', strtotime($date)),
        'duration_minutes' => (int)$service['duration_minutes'],
        'available_slots' => $available,
        'available_times' => $timeList,
        'total_available' => count($available),
        'message' => $message,
    ];
}

/**
 * Book a new professional appointment.
 */
function proVoiceBookAppointment(
    string $slug, int $serviceId, string $date, string $time,
    string $clientName, string $clientPhone,
    string $clientEmail = '', string $clientNotes = '',
    array $extras = []
): array {
    $date = voiceFixDate($date);

    $resolved = proVoiceResolveProfile($slug);
    if (!$resolved['success']) {
        return $resolved;
    }

    $restaurantId = $resolved['restaurant_id'];
    $profile = $resolved['profile'];
    $pdo = db();

    // Validate time format
    if (!preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $time)) {
        return ['success' => false, 'error' => 'Invalid time format. Use HH:MM.'];
    }
    if (strlen($time) <= 5) {
        $time .= ':00';
    }

    // Build start_at datetime
    $startAt = $date . ' ' . $time;

    // Validate the slot
    $validation = validateProfessionalSlot($restaurantId, $serviceId, $startAt, [
        'public_booking' => true,
    ]);

    if (!$validation['is_available']) {
        return ['success' => false, 'error' => $validation['message'] ?: 'This time slot is not available.'];
    }

    $service = $validation['service'];
    $slot = $validation['slot'];

    // Validate client name
    if (trim($clientName) === '') {
        return ['success' => false, 'error' => 'Client name is required.'];
    }
    $nameParts = explode(' ', trim($clientName), 2);
    $firstName = $nameParts[0];
    $lastName = $nameParts[1] ?? '';

    // Validate phone
    if (trim($clientPhone) === '') {
        return ['success' => false, 'error' => 'Phone number is required.'];
    }

    // Find or create client (match save-appointment.php: update existing client info)
    $existingClient = null;
    if ($clientEmail !== '') {
        $stmt = $pdo->prepare("SELECT id FROM professional_clients WHERE restaurant_id = ? AND email = ? LIMIT 1");
        $stmt->execute([$restaurantId, trim($clientEmail)]);
        $existingClient = $stmt->fetch();
    }
    if (!$existingClient) {
        $stmt = $pdo->prepare("SELECT id FROM professional_clients WHERE restaurant_id = ? AND phone = ? LIMIT 1");
        $stmt->execute([$restaurantId, trim($clientPhone)]);
        $existingClient = $stmt->fetch();
    }

    if ($existingClient) {
        $clientId = (int)$existingClient['id'];
        $stmt = $pdo->prepare(
            "UPDATE professional_clients SET first_name = ?, last_name = ?, email = ?, phone = ?, updated_at = NOW()
             WHERE id = ? AND restaurant_id = ?"
        );
        $stmt->execute([$firstName, $lastName, $clientEmail ?: null, trim($clientPhone), $clientId, $restaurantId]);
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO professional_clients (restaurant_id, first_name, last_name, phone, email, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())"
        );
        $stmt->execute([$restaurantId, $firstName, $lastName, trim($clientPhone), $clientEmail ?: null]);
        $clientId = (int)$pdo->lastInsertId();
    }

    // Generate confirmation code
    $confirmationCode = proVoiceGenerateConfirmationCode($pdo, $restaurantId);

    $professionalUserId = proVoiceResolveProfessionalUserId($pdo, $restaurantId, $profile);
    if ($professionalUserId <= 0) {
        return ['success' => false, 'error' => 'No professional user is configured for this business.'];
    }

    // Resolve location
    $locationType = $service['location_type'] ?: $profile['default_location_type'];
    $locationLabel = $service['location_label'] ?: $profile['default_location_label'];

    // Extract extra fields
    $internalNotes = trim($extras['internal_notes'] ?? '');
    $serviceContactName = trim($extras['service_contact_name'] ?? '');
    $servicePhone = trim($extras['service_phone'] ?? '');
    $serviceContactMethod = trim($extras['service_contact_method'] ?? '');
    $serviceAddress1 = trim($extras['service_address_1'] ?? '');
    $serviceCity = trim($extras['service_city'] ?? '');
    $serviceState = trim($extras['service_state'] ?? '');
    $servicePostalCode = substr(trim($extras['service_postal_code'] ?? ''), 0, 5);

    // Insert appointment
    $stmt = $pdo->prepare(
        "INSERT INTO professional_appointments (
            restaurant_id, professional_user_id, client_id, service_id,
            status, source, appointment_date, start_at, end_at,
            service_name, duration_minutes,
            buffer_before_minutes, buffer_after_minutes,
            price, currency_code, location_type, location_label,
            confirmation_code, client_notes, internal_notes,
            service_contact_name, service_phone, service_contact_method,
            service_address_1, service_city, service_state, service_postal_code,
            created_at, updated_at
         ) VALUES (?, ?, ?, ?, 'confirmed', 'api', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
    );
    $stmt->execute([
        $restaurantId,
        $professionalUserId,
        $clientId,
        (int)$service['id'],
        $date,
        $slot['start_at'],
        $slot['end_at'],
        $service['name'],
        (int)$service['duration_minutes'],
        (int)$service['effective_buffer_before_minutes'],
        (int)$service['effective_buffer_after_minutes'],
        $service['price'],
        $service['currency_code'] ?: 'USD',
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
    ]);
    $appointmentId = (int)$pdo->lastInsertId();

    // Update client's last_appointment_at
    try {
        $stmt = $pdo->prepare("UPDATE professional_clients SET last_appointment_at = ? WHERE id = ? AND restaurant_id = ?");
        $stmt->execute([$slot['start_at'], $clientId, $restaurantId]);
    } catch (Throwable $e) {}

    // Log activity
    try {
        professionalLogAppointmentActivity(
            $restaurantId, null, $appointmentId,
            'appointment_created',
            "API booking by {$firstName} {$lastName} for {$service['name']} on {$date} at {$time}",
            null, null,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
        );
    } catch (Throwable $e) {}

    // Send notifications
    try {
        professionalSendAppointmentConfirmationNotifications($appointmentId);
    } catch (Throwable $e) {}

    $timezone = new DateTimeZone($profile['timezone']);
    $startDt = new DateTimeImmutable($slot['start_at'], $timezone);

    return [
        'success' => true,
        'confirmation_code' => $confirmationCode,
        'business_name' => $profile['business_name'] ?: $profile['display_name'],
        'service_name' => $service['name'],
        'date' => $date,
        'date_display' => $startDt->format('l, F j, Y'),
        'time' => $startDt->format('H:i'),
        'time_display' => $startDt->format('g:ia'),
        'duration_minutes' => (int)$service['duration_minutes'],
        'client_name' => trim("{$firstName} {$lastName}"),
        'status' => 'confirmed',
        'message' => "Appointment confirmed! Confirmation code is {$confirmationCode}.",
    ];
}

/**
 * Look up appointments by confirmation code or client phone.
 */
function proVoiceLookupAppointment(string $slug, string $codeOrPhone): array
{
    $resolved = proVoiceResolveProfile($slug);
    if (!$resolved['success']) {
        return $resolved;
    }

    $restaurantId = $resolved['restaurant_id'];
    $profile = $resolved['profile'];
    $pdo = db();
    $timezone = new DateTimeZone($profile['timezone']);

    $appointments = [];

    // Try confirmation code first
    $stmt = $pdo->prepare(
        "SELECT a.*, c.first_name, c.last_name, c.phone AS client_phone, c.email AS client_email
         FROM professional_appointments a
         INNER JOIN professional_clients c ON c.id = a.client_id
         WHERE a.restaurant_id = ? AND a.confirmation_code = ?
         LIMIT 1"
    );
    $stmt->execute([$restaurantId, strtoupper(trim($codeOrPhone))]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $appointments[] = $row;
    }

    // If not found by code, try by phone (active appointments only)
    if (empty($appointments)) {
        $stmt = $pdo->prepare(
            "SELECT a.*, c.first_name, c.last_name, c.phone AS client_phone, c.email AS client_email
             FROM professional_appointments a
             INNER JOIN professional_clients c ON c.id = a.client_id
             WHERE a.restaurant_id = ? AND c.phone = ?
               AND a.status IN ('pending', 'confirmed')
             ORDER BY a.start_at ASC"
        );
        $stmt->execute([$restaurantId, trim($codeOrPhone)]);
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if (empty($appointments)) {
        return ['success' => false, 'error' => 'No appointment found with that confirmation code or phone number.'];
    }

    $formatted = [];
    foreach ($appointments as $a) {
        $startDt = new DateTimeImmutable($a['start_at'], $timezone);
        $formatted[] = [
            'confirmation_code' => $a['confirmation_code'],
            'client_name' => trim($a['first_name'] . ' ' . $a['last_name']),
            'phone' => $a['client_phone'] ?? '',
            'email' => $a['client_email'] ?? '',
            'service_name' => $a['service_name'],
            'date' => $a['appointment_date'],
            'date_display' => $startDt->format('l, F j, Y'),
            'time' => $startDt->format('H:i'),
            'time_display' => $startDt->format('g:ia'),
            'duration_minutes' => (int)$a['duration_minutes'],
            'status' => $a['status'],
            'location_type' => $a['location_type'] ?? '',
            'client_notes' => $a['client_notes'] ?? '',
            'internal_notes' => $a['internal_notes'] ?? '',
            'service_contact_name' => $a['service_contact_name'] ?? '',
            'service_phone' => $a['service_phone'] ?? '',
            'service_contact_method' => $a['service_contact_method'] ?? '',
            'service_address_1' => $a['service_address_1'] ?? '',
            'service_city' => $a['service_city'] ?? '',
            'service_state' => $a['service_state'] ?? '',
            'service_postal_code' => $a['service_postal_code'] ?? '',
            'can_cancel' => in_array($a['status'], ['pending', 'confirmed']),
            'can_confirm' => $a['status'] === 'pending',
        ];
    }

    $first = $appointments[0];
    $clientName = trim($first['first_name'] . ' ' . $first['last_name']);
    $firstStart = new DateTimeImmutable($first['start_at'], $timezone);

    if (count($appointments) === 1) {
        $message = "Appointment found for {$clientName}: {$first['service_name']} on "
            . $firstStart->format('l, F j') . " at " . $firstStart->format('g:ia')
            . ". Status: {$first['status']}.";
    } else {
        $message = count($appointments) . " appointments found for {$clientName}:";
        foreach ($appointments as $i => $a) {
            $num = $i + 1;
            $dt = new DateTimeImmutable($a['start_at'], $timezone);
            $message .= " ({$num}) {$a['confirmation_code']} — {$a['service_name']} on "
                . $dt->format('l, F j') . " at " . $dt->format('g:ia')
                . ", status: {$a['status']}.";
        }
    }

    return [
        'success' => true,
        'appointment' => $formatted[0],
        'appointments' => $formatted,
        'total_appointments' => count($formatted),
        'can_cancel' => $formatted[0]['can_cancel'],
        'can_confirm' => $formatted[0]['can_confirm'],
        'message' => $message,
    ];
}

/**
 * Cancel a professional appointment.
 */
function proVoiceCancelAppointment(string $slug, string $confirmationCode): array
{
    $resolved = proVoiceResolveProfile($slug);
    if (!$resolved['success']) {
        return $resolved;
    }

    $restaurantId = $resolved['restaurant_id'];
    $profile = $resolved['profile'];
    $pdo = db();
    $timezone = new DateTimeZone($profile['timezone']);

    $stmt = $pdo->prepare(
        "SELECT a.id, a.status, a.start_at, a.service_name, a.appointment_date,
                c.first_name, c.last_name
         FROM professional_appointments a
         INNER JOIN professional_clients c ON c.id = a.client_id
         WHERE a.restaurant_id = ? AND a.confirmation_code = ?
         LIMIT 1"
    );
    $stmt->execute([$restaurantId, strtoupper(trim($confirmationCode))]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        return ['success' => false, 'error' => 'No appointment found with that confirmation code.'];
    }

    if (!in_array($appointment['status'], ['pending', 'confirmed'])) {
        return ['success' => false, 'error' => "This appointment cannot be cancelled because it is already {$appointment['status']}."];
    }

    // Check cancellation notice window
    $noticeHours = (int)($profile['cancellation_notice_hours'] ?? 0);
    if ($noticeHours > 0) {
        $startAt = new DateTimeImmutable($appointment['start_at'], $timezone);
        $now = new DateTimeImmutable('now', $timezone);
        $deadline = $startAt->modify("-{$noticeHours} hours");
        if ($now >= $deadline) {
            return ['success' => false, 'error' => "Cancellations must be made at least {$noticeHours} hours before the appointment."];
        }
    }

    // Cancel
    $stmt = $pdo->prepare(
        "UPDATE professional_appointments SET status = 'cancelled', cancelled_at = NOW(), updated_at = NOW() WHERE id = ?"
    );
    $stmt->execute([$appointment['id']]);

    // Log activity
    try {
        professionalLogAppointmentActivity(
            $restaurantId, null, (int)$appointment['id'],
            'appointment_cancelled',
            "API cancellation for {$appointment['first_name']} {$appointment['last_name']} — {$appointment['service_name']}",
            null, null,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
        );
    } catch (Throwable $e) {}

    // Send cancellation notification
    try {
        professionalSendAppointmentCancellationNotifications((int)$appointment['id']);
    } catch (Throwable $e) {}

    $startDt = new DateTimeImmutable($appointment['start_at'], $timezone);

    return [
        'success' => true,
        'confirmation_code' => strtoupper(trim($confirmationCode)),
        'message' => "Appointment for {$appointment['first_name']} {$appointment['last_name']} ({$appointment['service_name']}) on "
            . $startDt->format('l, F j') . " at " . $startDt->format('g:ia')
            . " has been cancelled.",
    ];
}

/**
 * Confirm a pending professional appointment.
 */
function proVoiceConfirmAppointment(string $slug, string $confirmationCode): array
{
    $resolved = proVoiceResolveProfile($slug);
    if (!$resolved['success']) {
        return $resolved;
    }

    $restaurantId = $resolved['restaurant_id'];
    $profile = $resolved['profile'];
    $pdo = db();
    $timezone = new DateTimeZone($profile['timezone']);

    $stmt = $pdo->prepare(
        "SELECT a.id, a.status, a.start_at, a.service_name, a.appointment_date, a.duration_minutes,
                c.first_name, c.last_name
         FROM professional_appointments a
         INNER JOIN professional_clients c ON c.id = a.client_id
         WHERE a.restaurant_id = ? AND a.confirmation_code = ?
         LIMIT 1"
    );
    $stmt->execute([$restaurantId, strtoupper(trim($confirmationCode))]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        return ['success' => false, 'error' => 'No appointment found with that confirmation code.'];
    }

    if ($appointment['status'] !== 'pending') {
        if ($appointment['status'] === 'confirmed') {
            return ['success' => true, 'message' => 'This appointment is already confirmed.'];
        }
        return ['success' => false, 'error' => "This appointment cannot be confirmed because it is {$appointment['status']}."];
    }

    $stmt = $pdo->prepare("UPDATE professional_appointments SET status = 'confirmed', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$appointment['id']]);

    // Log activity
    try {
        professionalLogAppointmentActivity(
            $restaurantId, null, (int)$appointment['id'],
            'appointment_confirmed',
            "API confirmation for {$appointment['first_name']} {$appointment['last_name']} — {$appointment['service_name']}",
            null, null,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
        );
    } catch (Throwable $e) {}

    // Send confirmation notification
    try {
        professionalSendAppointmentConfirmationNotifications((int)$appointment['id']);
    } catch (Throwable $e) {}

    $startDt = new DateTimeImmutable($appointment['start_at'], $timezone);

    return [
        'success' => true,
        'confirmation_code' => strtoupper(trim($confirmationCode)),
        'message' => "Appointment confirmed for {$appointment['first_name']} {$appointment['last_name']} ({$appointment['service_name']}) on "
            . $startDt->format('l, F j') . " at " . $startDt->format('g:ia') . ".",
    ];
}

/**
 * Modify an existing appointment (in-place UPDATE, preserving confirmation code).
 */
function proVoiceModifyAppointment(
    string $slug, string $confirmationCode,
    int $serviceId, string $date, string $time,
    string $clientName, string $clientPhone,
    string $clientEmail = '', string $clientNotes = '',
    array $extras = []
): array {
    $date = voiceFixDate($date);

    $resolved = proVoiceResolveProfile($slug);
    if (!$resolved['success']) {
        return $resolved;
    }

    $restaurantId = $resolved['restaurant_id'];
    $profile = $resolved['profile'];
    $pdo = db();
    $timezone = new DateTimeZone($profile['timezone']);

    // Look up the existing appointment
    $stmt = $pdo->prepare(
        "SELECT a.*, c.first_name, c.last_name, c.phone AS client_phone, c.email AS client_email
         FROM professional_appointments a
         INNER JOIN professional_clients c ON c.id = a.client_id
         WHERE a.restaurant_id = ? AND a.confirmation_code = ?
         LIMIT 1"
    );
    $stmt->execute([$restaurantId, strtoupper(trim($confirmationCode))]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        return ['success' => false, 'error' => 'No appointment found with that confirmation code.'];
    }

    if (!in_array($existing['status'], ['pending', 'confirmed'])) {
        return ['success' => false, 'error' => "This appointment cannot be modified because it is {$existing['status']}."];
    }

    // Validate time format
    if (!preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $time)) {
        return ['success' => false, 'error' => 'Invalid time format. Use HH:MM.'];
    }
    if (strlen($time) <= 5) {
        $time .= ':00';
    }

    $startAt = $date . ' ' . $time;

    // Validate the slot (exclude this appointment from conflict check)
    $validation = validateProfessionalSlot($restaurantId, $serviceId, $startAt, [
        'public_booking' => true,
        'exclude_appointment_id' => (int)$existing['id'],
    ]);

    if (!$validation['is_available']) {
        return ['success' => false, 'error' => $validation['message'] ?: 'This time slot is not available.'];
    }

    $service = $validation['service'];
    $slot = $validation['slot'];

    // Validate client name
    if (trim($clientName) === '') {
        return ['success' => false, 'error' => 'Client name is required.'];
    }
    $nameParts = explode(' ', trim($clientName), 2);
    $firstName = $nameParts[0];
    $lastName = $nameParts[1] ?? '';

    if (trim($clientPhone) === '') {
        return ['success' => false, 'error' => 'Phone number is required.'];
    }

    // Update existing client info
    $clientId = (int)$existing['client_id'];
    $stmt = $pdo->prepare(
        "UPDATE professional_clients SET first_name = ?, last_name = ?, email = ?, phone = ?, updated_at = NOW()
         WHERE id = ? AND restaurant_id = ?"
    );
    $stmt->execute([$firstName, $lastName, $clientEmail ?: null, trim($clientPhone), $clientId, $restaurantId]);

    $professionalUserId = proVoiceResolveProfessionalUserId(
        $pdo,
        $restaurantId,
        $profile,
        (int)($existing['professional_user_id'] ?? 0)
    );
    if ($professionalUserId <= 0) {
        return ['success' => false, 'error' => 'No professional user is configured for this business.'];
    }

    // Resolve location
    $locationType = $slot['location_type'] ?? ($service['location_type'] ?: $profile['default_location_type']);
    $locationLabel = $slot['location_label'] ?? ($service['location_label'] ?: $profile['default_location_label']);

    // Extract extra fields
    $internalNotes = trim($extras['internal_notes'] ?? '');
    $serviceContactName = trim($extras['service_contact_name'] ?? '');
    $servicePhoneVal = trim($extras['service_phone'] ?? '');
    $serviceContactMethod = trim($extras['service_contact_method'] ?? '');
    $serviceAddress1 = trim($extras['service_address_1'] ?? '');
    $serviceCity = trim($extras['service_city'] ?? '');
    $serviceState = trim($extras['service_state'] ?? '');
    $servicePostalCode = substr(trim($extras['service_postal_code'] ?? ''), 0, 5);

    // In-place UPDATE (preserves confirmation code)
    $stmt = $pdo->prepare(
        "UPDATE professional_appointments SET
            professional_user_id = ?,
            client_id = ?,
            service_id = ?,
            status = ?,
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
            cancelled_at = NULL,
            completed_at = NULL,
            updated_at = NOW()
         WHERE id = ? AND restaurant_id = ?"
    );
    $stmt->execute([
        $professionalUserId,
        $clientId,
        (int)$service['id'],
        'confirmed',
        $date,
        $slot['start_at'],
        $slot['end_at'],
        $service['name'],
        (int)$service['duration_minutes'],
        (int)$service['effective_buffer_before_minutes'],
        (int)$service['effective_buffer_after_minutes'],
        $service['price'],
        $service['currency_code'] ?: 'USD',
        $locationType,
        $locationLabel,
        $clientNotes !== '' ? $clientNotes : null,
        $internalNotes !== '' ? $internalNotes : null,
        $serviceContactName !== '' ? $serviceContactName : null,
        $servicePhoneVal !== '' ? $servicePhoneVal : null,
        $serviceContactMethod !== '' ? $serviceContactMethod : null,
        $serviceAddress1 !== '' ? $serviceAddress1 : null,
        $serviceCity !== '' ? $serviceCity : null,
        $serviceState !== '' ? $serviceState : null,
        $servicePostalCode !== '' ? $servicePostalCode : null,
        (int)$existing['id'],
        $restaurantId,
    ]);

    // Update client's last_appointment_at
    try {
        $stmt = $pdo->prepare("UPDATE professional_clients SET last_appointment_at = ? WHERE id = ? AND restaurant_id = ?");
        $stmt->execute([$slot['start_at'], $clientId, $restaurantId]);
    } catch (Throwable $e) {}

    // Log activity
    try {
        professionalLogAppointmentActivity(
            $restaurantId, null, (int)$existing['id'],
            'appointment_modified',
            "API modification for {$firstName} {$lastName} — {$service['name']} on {$date} at {$time}",
            null, null,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
        );
    } catch (Throwable $e) {}

    // Send confirmation notification
    try {
        professionalSendAppointmentConfirmationNotifications((int)$existing['id']);
    } catch (Throwable $e) {}

    $startDt = new DateTimeImmutable($slot['start_at'], $timezone);
    $code = strtoupper(trim($confirmationCode));

    return [
        'success' => true,
        'confirmation_code' => $code,
        'business_name' => $profile['business_name'] ?: $profile['display_name'],
        'service_name' => $service['name'],
        'date' => $date,
        'date_display' => $startDt->format('l, F j, Y'),
        'time' => $startDt->format('H:i'),
        'time_display' => $startDt->format('g:ia'),
        'duration_minutes' => (int)$service['duration_minutes'],
        'client_name' => trim("{$firstName} {$lastName}"),
        'status' => 'confirmed',
        'message' => "Appointment modified. Confirmation code {$code} — {$service['name']} on "
            . $startDt->format('l, F j, Y') . " at " . $startDt->format('g:ia') . ".",
    ];
}

/**
 * Update client contact preferences.
 */
function proVoiceUpdateClientPreferences(string $slug, string $clientPhone, array $preferences): array
{
    $resolved = proVoiceResolveProfile($slug);
    if (!$resolved['success']) {
        return $resolved;
    }

    $restaurantId = $resolved['restaurant_id'];
    $pdo = db();

    $stmt = $pdo->prepare(
        "SELECT id, first_name, last_name, marketing_opt_in, preferred_contact_method
         FROM professional_clients
         WHERE restaurant_id = ? AND phone = ?
         LIMIT 1"
    );
    $stmt->execute([$restaurantId, trim($clientPhone)]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        return ['success' => false, 'error' => 'No client found with that phone number.'];
    }

    $updates = [];
    $params = [];
    $changed = [];

    if (isset($preferences['marketing_opt_in'])) {
        $val = $preferences['marketing_opt_in'] ? 1 : 0;
        $updates[] = "marketing_opt_in = ?";
        $params[] = $val;
        $changed[] = "marketing opt-in: " . ($val ? 'yes' : 'no');
    }

    if (isset($preferences['preferred_contact_method'])) {
        $allowed = ['email', 'phone', 'sms', ''];
        $method = (string)$preferences['preferred_contact_method'];
        if (in_array($method, $allowed, true)) {
            $updates[] = "preferred_contact_method = ?";
            $params[] = $method ?: null;
            $changed[] = "preferred contact: " . ($method ?: 'none');
        }
    }

    if (empty($updates)) {
        return ['success' => false, 'error' => 'Provide at least one preference: marketing_opt_in or preferred_contact_method.'];
    }

    $updates[] = "updated_at = NOW()";
    $params[] = $client['id'];
    $params[] = $restaurantId;
    $stmt = $pdo->prepare("UPDATE professional_clients SET " . implode(', ', $updates) . " WHERE id = ? AND restaurant_id = ?");
    $stmt->execute($params);

    $clientName = trim($client['first_name'] . ' ' . $client['last_name']);

    return [
        'success' => true,
        'client_name' => $clientName,
        'updated' => $changed,
        'message' => "Preferences updated for {$clientName}: " . implode(', ', $changed) . '.',
    ];
}
