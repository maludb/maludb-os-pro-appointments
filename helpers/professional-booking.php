<?php
/**
 * Professional Booking Helper
 *
 * Shared public self-service lookup rules and lightweight
 * appointment activity logging for the professional product.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/professional-availability.php';

/**
 * Load one professional appointment context by id.
 */
function professionalGetAppointmentContextById(int $appointmentId, ?int $restaurantId = null): ?array
{
    if ($appointmentId <= 0) {
        return null;
    }

    $sql = "
        SELECT
            a.*,
            c.first_name,
            c.last_name,
            c.email AS client_email,
            c.phone AS client_phone,
            c.preferred_contact_method,
            pp.display_name,
            pp.business_name,
            pp.business_phone,
            pp.business_email,
            pp.booking_slug,
            pp.timezone AS profile_timezone,
            pp.cancellation_policy,
            pp.cancellation_notice_hours,
            pp.default_location_type,
            pp.default_location_label,
            r.name AS restaurant_name,
            r.phone AS restaurant_phone,
            r.email AS restaurant_email,
            r.timezone AS restaurant_timezone
        FROM professional_appointments a
        INNER JOIN professional_clients c ON c.id = a.client_id
        INNER JOIN restaurants r ON r.id = a.restaurant_id
        LEFT JOIN professional_profiles pp ON pp.restaurant_id = a.restaurant_id
        WHERE a.id = ?
    ";
    $params = [$appointmentId];

    if ($restaurantId !== null && $restaurantId > 0) {
        $sql .= " AND a.restaurant_id = ?";
        $params[] = $restaurantId;
    }

    $sql .= " LIMIT 1";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    return $appointment ?: null;
}

/**
 * Load one professional appointment context for public self-service.
 */
function professionalGetAppointmentContextByConfirmation(string $bookingSlug, string $confirmationCode, string $lastName): ?array
{
    $bookingSlug = strtolower(trim($bookingSlug));
    $confirmationCode = strtoupper(trim($confirmationCode));
    $lastName = trim($lastName);

    if ($bookingSlug === '' || $confirmationCode === '' || $lastName === '') {
        return null;
    }

    $stmt = db()->prepare(
        "SELECT
            a.*,
            c.first_name,
            c.last_name,
            c.email AS client_email,
            c.phone AS client_phone,
            c.preferred_contact_method,
            pp.display_name,
            pp.business_name,
            pp.business_phone,
            pp.business_email,
            pp.booking_slug,
            pp.timezone AS profile_timezone,
            pp.cancellation_policy,
            pp.cancellation_notice_hours,
            pp.default_location_type,
            pp.default_location_label,
            r.name AS restaurant_name,
            r.phone AS restaurant_phone,
            r.email AS restaurant_email,
            r.timezone AS restaurant_timezone
         FROM professional_profiles pp
         INNER JOIN restaurants r ON r.id = pp.restaurant_id
         INNER JOIN professional_appointments a ON a.restaurant_id = pp.restaurant_id
         INNER JOIN professional_clients c ON c.id = a.client_id
         WHERE pp.booking_slug = ?
           AND r.is_active = 1
           AND a.confirmation_code = ?
           AND c.last_name = ?
         LIMIT 1"
    );
    $stmt->execute([$bookingSlug, $confirmationCode, $lastName]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    return $appointment ?: null;
}

/**
 * Convert a professional appointment status into a badge class.
 */
function professionalSelfServiceStatusClass(string $status): string
{
    $map = [
        'pending' => 'warning',
        'confirmed' => 'primary',
        'completed' => 'success',
        'cancelled' => 'dark',
        'no_show' => 'danger',
    ];

    return $map[$status] ?? 'secondary';
}

/**
 * Format a professional appointment status for display.
 */
function professionalSelfServiceStatusLabel(string $status): string
{
    return ucfirst(str_replace('_', ' ', $status));
}

/**
 * Check whether an appointment can still be managed by the client.
 */
function professionalCanSelfServiceManageAppointment(array $appointment): bool
{
    return in_array((string)($appointment['status'] ?? ''), ['pending', 'confirmed'], true);
}

/**
 * Return a self-service restriction, if any.
 */
function professionalGetSelfServiceRestriction(array $appointment, ?DateTimeImmutable $now = null): ?array
{
    if (!professionalCanSelfServiceManageAppointment($appointment)) {
        return [
            'reason' => 'status_locked',
            'message' => 'This appointment can no longer be changed online.',
        ];
    }

    $timezoneName = $appointment['profile_timezone'] ?: ($appointment['restaurant_timezone'] ?: 'America/New_York');
    try {
        $timezone = new DateTimeZone($timezoneName);
    } catch (Throwable $exception) {
        $timezone = new DateTimeZone('America/New_York');
    }

    $startAt = professionalNormalizeDateTime($appointment['start_at'] ?? '', $timezone);
    if ($startAt === null) {
        return [
            'reason' => 'invalid_start',
            'message' => 'This appointment cannot be changed online right now.',
        ];
    }

    if ($now === null) {
        $now = new DateTimeImmutable('now', $timezone);
    }

    if ($startAt <= $now) {
        return [
            'reason' => 'appointment_started',
            'message' => 'This appointment is already in progress or has passed.',
        ];
    }

    $noticeHours = max(0, (int)($appointment['cancellation_notice_hours'] ?? 0));
    if ($noticeHours > 0) {
        $deadline = $startAt->modify('-' . $noticeHours . ' hours');
        if ($now >= $deadline) {
            return [
                'reason' => 'inside_notice_window',
                'message' => 'Online changes are closed inside the ' . $noticeHours . '-hour cancellation window. Please contact the business directly.',
            ];
        }
    }

    return null;
}

/**
 * Insert a lightweight audit entry for professional appointments.
 */
function professionalLogAppointmentActivity(
    int $restaurantId,
    ?int $userId,
    int $appointmentId,
    string $action,
    string $description,
    $oldValue = null,
    $newValue = null,
    ?string $ipAddress = null
): void {
    if ($restaurantId <= 0 || $appointmentId <= 0 || $action === '') {
        return;
    }

    $oldValueJson = null;
    $newValueJson = null;

    if ($oldValue !== null) {
        $oldValueJson = is_string($oldValue) ? $oldValue : json_encode($oldValue);
    }

    if ($newValue !== null) {
        $newValueJson = is_string($newValue) ? $newValue : json_encode($newValue);
    }

    try {
        $stmt = db()->prepare(
            "INSERT INTO activity_log (
                restaurant_id,
                user_id,
                action,
                entity_type,
                entity_id,
                description,
                old_value,
                new_value,
                ip_address
             ) VALUES (?, ?, ?, 'professional_appointment', ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $restaurantId,
            $userId,
            $action,
            $appointmentId,
            $description,
            $oldValueJson,
            $newValueJson,
            $ipAddress,
        ]);
    } catch (Throwable $exception) {
        // Do not interrupt the booking flow if the audit insert fails.
    }
}
