<?php
/**
 * Professional Notification Helper
 *
 * Appointment-specific notification delivery for the
 * professional scheduling product.
 */

require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/professional-booking.php';

/**
 * Load the data needed to send one professional appointment notification.
 */
function professionalGetNotificationContext(int $appointmentId): ?array
{
    $appointment = professionalGetAppointmentContextById($appointmentId);
    if (!$appointment) {
        return null;
    }

    $businessName = trim((string)($appointment['display_name'] ?: $appointment['business_name'] ?: $appointment['restaurant_name']));
    $locationLabel = trim((string)($appointment['location_label'] ?: $appointment['default_location_label']));
    if ($locationLabel === '') {
        $locationLabel = ucwords(str_replace('_', ' ', (string)($appointment['location_type'] ?: $appointment['default_location_type'] ?: 'in_person')));
    }

    $timezoneName = $appointment['profile_timezone'] ?: ($appointment['restaurant_timezone'] ?: 'America/New_York');
    try {
        $timezone = new DateTimeZone($timezoneName);
    } catch (Throwable $exception) {
        $timezone = new DateTimeZone('America/New_York');
    }

    $startAt = professionalNormalizeDateTime($appointment['start_at'] ?? '', $timezone);
    $endAt = professionalNormalizeDateTime($appointment['end_at'] ?? '', $timezone);

    if ($startAt === null || $endAt === null) {
        return null;
    }

    return [
        '_row' => $appointment,
        'restaurant_id' => (int)$appointment['restaurant_id'],
        'appointment_id' => (int)$appointment['id'],
        'client_id' => (int)$appointment['client_id'],
        'client_first_name' => (string)$appointment['first_name'],
        'client_last_name' => (string)$appointment['last_name'],
        'client_name' => trim((string)$appointment['first_name'] . ' ' . (string)$appointment['last_name']),
        'client_email' => (string)($appointment['client_email'] ?? ''),
        'client_phone' => (string)($appointment['client_phone'] ?? ''),
        'business_name' => $businessName,
        'business_phone' => (string)($appointment['business_phone'] ?: $appointment['restaurant_phone'] ?: ''),
        'business_email' => (string)($appointment['business_email'] ?: $appointment['restaurant_email'] ?: ''),
        'service_name' => (string)$appointment['service_name'],
        'date' => $startAt->format('l, F j, Y'),
        'time' => $startAt->format('g:ia'),
        'end_time' => $endAt->format('g:ia'),
        'location' => $locationLabel,
        'status' => professionalSelfServiceStatusLabel((string)$appointment['status']),
        'confirmation_code' => (string)($appointment['confirmation_code'] ?? ''),
        'booking_slug' => (string)($appointment['booking_slug'] ?? ''),
        'client_notes' => (string)($appointment['client_notes'] ?? ''),
    ];
}

/**
 * Check one professional notification toggle.
 */
function professionalNotificationEnabled(int $restaurantId, string $settingKey, string $default = '0'): bool
{
    return getRestaurantSetting($restaurantId, $settingKey, $default) === '1';
}

/**
 * Send one professional appointment email notification.
 */
function professionalSendAppointmentEmail(int $appointmentId, string $type): bool
{
    $context = professionalGetNotificationContext($appointmentId);
    if (!$context) {
        return false;
    }

    if ($context['client_email'] === '') {
        return false;
    }

    $subject = '';
    $htmlBody = '';
    $textBody = '';

    switch ($type) {
        case 'confirmation':
            $subject = 'Appointment Confirmed — ' . $context['business_name'];
            $htmlBody = '<h2>Your appointment is confirmed</h2>'
                . '<p>Hi ' . htmlspecialchars($context['client_first_name']) . ',</p>'
                . '<p>Your appointment with <strong>' . htmlspecialchars($context['business_name']) . '</strong> is confirmed.</p>'
                . '<ul>'
                . '<li><strong>Service:</strong> ' . htmlspecialchars($context['service_name']) . '</li>'
                . '<li><strong>Date:</strong> ' . htmlspecialchars($context['date']) . '</li>'
                . '<li><strong>Time:</strong> ' . htmlspecialchars($context['time']) . '</li>'
                . '<li><strong>Location:</strong> ' . htmlspecialchars($context['location']) . '</li>'
                . '<li><strong>Confirmation Code:</strong> ' . htmlspecialchars($context['confirmation_code']) . '</li>'
                . '</ul>'
                . '<p>If you need to reschedule or cancel, use your confirmation code with the booking page.</p>'
                . '<p>' . htmlspecialchars($context['business_name']) . '</p>';
            break;

        case 'reminder':
            $subject = 'Appointment Reminder — ' . $context['business_name'];
            $htmlBody = '<h2>Appointment Reminder</h2>'
                . '<p>Hi ' . htmlspecialchars($context['client_first_name']) . ',</p>'
                . '<p>This is a reminder of your upcoming appointment with <strong>' . htmlspecialchars($context['business_name']) . '</strong>.</p>'
                . '<ul>'
                . '<li><strong>Service:</strong> ' . htmlspecialchars($context['service_name']) . '</li>'
                . '<li><strong>Date:</strong> ' . htmlspecialchars($context['date']) . '</li>'
                . '<li><strong>Time:</strong> ' . htmlspecialchars($context['time']) . '</li>'
                . '<li><strong>Location:</strong> ' . htmlspecialchars($context['location']) . '</li>'
                . '<li><strong>Confirmation Code:</strong> ' . htmlspecialchars($context['confirmation_code']) . '</li>'
                . '</ul>'
                . '<p>' . htmlspecialchars($context['business_name']) . '</p>';
            break;

        case 'cancellation':
            $subject = 'Appointment Cancelled — ' . $context['business_name'];
            $htmlBody = '<h2>Appointment Cancelled</h2>'
                . '<p>Hi ' . htmlspecialchars($context['client_first_name']) . ',</p>'
                . '<p>Your appointment with <strong>' . htmlspecialchars($context['business_name']) . '</strong> has been cancelled.</p>'
                . '<ul>'
                . '<li><strong>Service:</strong> ' . htmlspecialchars($context['service_name']) . '</li>'
                . '<li><strong>Date:</strong> ' . htmlspecialchars($context['date']) . '</li>'
                . '<li><strong>Time:</strong> ' . htmlspecialchars($context['time']) . '</li>'
                . '<li><strong>Confirmation Code:</strong> ' . htmlspecialchars($context['confirmation_code']) . '</li>'
                . '</ul>'
                . '<p>If you need a new appointment, please book again or contact ' . htmlspecialchars($context['business_name']) . '.</p>';
            break;

        default:
            return false;
    }

    $textBody = trim(strip_tags(str_replace(['</li>', '</p>', '</h2>'], ["\n", "\n", "\n"], $htmlBody)));

    return sendEmail(
        $context['restaurant_id'],
        $context['client_email'],
        $subject,
        $htmlBody,
        $textBody
    );
}

/**
 * Send one professional appointment SMS notification.
 */
function professionalSendAppointmentSms(int $appointmentId, string $type): bool
{
    $context = professionalGetNotificationContext($appointmentId);
    if (!$context) {
        return false;
    }

    if ($context['client_phone'] === '') {
        return false;
    }

    switch ($type) {
        case 'confirmation':
            $message = 'Hi ' . $context['client_first_name']
                . ', your appointment with ' . $context['business_name']
                . ' is confirmed for ' . $context['date'] . ' at ' . $context['time']
                . '. Code: ' . $context['confirmation_code'] . '.';
            break;

        case 'reminder':
            $message = 'Reminder: ' . $context['service_name']
                . ' with ' . $context['business_name']
                . ' is on ' . $context['date'] . ' at ' . $context['time']
                . '. Code: ' . $context['confirmation_code'] . '.';
            break;

        case 'cancellation':
            $message = 'Your appointment with ' . $context['business_name']
                . ' on ' . $context['date'] . ' at ' . $context['time']
                . ' has been cancelled. Code: ' . $context['confirmation_code'] . '.';
            break;

        default:
            return false;
    }

    $result = twilioSend($context['restaurant_id'], $context['client_phone'], $message);

    return (bool)($result['success'] ?? false);
}

/**
 * Send confirmation notifications for a professional appointment.
 */
function professionalSendAppointmentConfirmationNotifications(int $appointmentId): array
{
    $context = professionalGetNotificationContext($appointmentId);
    if (!$context) {
        return ['email' => false, 'sms' => false];
    }

    $restaurantId = (int)$context['restaurant_id'];

    return [
        'email' => professionalNotificationEnabled($restaurantId, 'notification_confirmation_email', '1')
            ? professionalSendAppointmentEmail($appointmentId, 'confirmation')
            : false,
        'sms' => professionalNotificationEnabled($restaurantId, 'notification_confirmation_sms', '0')
            ? professionalSendAppointmentSms($appointmentId, 'confirmation')
            : false,
    ];
}

/**
 * Send reminder notifications for a professional appointment.
 */
function professionalSendAppointmentReminderNotifications(int $appointmentId): array
{
    $context = professionalGetNotificationContext($appointmentId);
    if (!$context) {
        return ['email' => false, 'sms' => false];
    }

    $restaurantId = (int)$context['restaurant_id'];

    return [
        'email' => professionalNotificationEnabled($restaurantId, 'notification_reminder_email', '1')
            ? professionalSendAppointmentEmail($appointmentId, 'reminder')
            : false,
        'sms' => professionalNotificationEnabled($restaurantId, 'notification_reminder_sms', '0')
            ? professionalSendAppointmentSms($appointmentId, 'reminder')
            : false,
    ];
}

/**
 * Send cancellation notifications for a professional appointment.
 */
function professionalSendAppointmentCancellationNotifications(int $appointmentId): array
{
    $context = professionalGetNotificationContext($appointmentId);
    if (!$context) {
        return ['email' => false, 'sms' => false];
    }

    $restaurantId = (int)$context['restaurant_id'];

    return [
        'email' => professionalNotificationEnabled($restaurantId, 'notification_cancellation_email', '1')
            ? professionalSendAppointmentEmail($appointmentId, 'cancellation')
            : false,
        'sms' => professionalNotificationEnabled($restaurantId, 'notification_cancellation_sms', '0')
            ? professionalSendAppointmentSms($appointmentId, 'cancellation')
            : false,
    ];
}
