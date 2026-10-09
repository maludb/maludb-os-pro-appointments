<?php
/**
 * Cron-compatible Professional Reminder Script
 *
 * Usage:
 *   php /var/www/helpers/send-professional-reminders.php
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/restaurant.php';
require_once __DIR__ . '/availability.php';
require_once __DIR__ . '/professional-booking.php';
require_once __DIR__ . '/professional-notifications.php';

$pdo = db();
$profilesStmt = $pdo->query(
    "SELECT
        pp.restaurant_id,
        COALESCE(NULLIF(pp.display_name, ''), NULLIF(pp.business_name, ''), r.name) AS business_name
     FROM professional_profiles pp
     INNER JOIN restaurants r ON r.id = pp.restaurant_id
     WHERE r.is_active = 1
     ORDER BY pp.restaurant_id ASC"
);
$profiles = $profilesStmt->fetchAll(PDO::FETCH_ASSOC);

$totalSent = 0;
$totalFailed = 0;

foreach ($profiles as $profile) {
    $restaurantId = (int)$profile['restaurant_id'];
    applyRestaurantTimezone($restaurantId);

    $emailEnabled = getRestaurantSetting($restaurantId, 'notification_reminder_email', '1') === '1';
    $smsEnabled = getRestaurantSetting($restaurantId, 'notification_reminder_sms', '0') === '1';

    if (!$emailEnabled && !$smsEnabled) {
        continue;
    }

    $hoursBefore = max(1, (int)getRestaurantSetting($restaurantId, 'reminder_hours_before', '24'));

    $appointmentsStmt = $pdo->prepare(
        "SELECT a.id, a.confirmation_code, a.start_at, a.updated_at
         FROM professional_appointments a
         WHERE a.restaurant_id = ?
           AND a.status IN ('pending', 'confirmed')
           AND TIMESTAMPDIFF(HOUR, NOW(), a.start_at) BETWEEN 0 AND ?
           AND NOT EXISTS (
               SELECT 1
               FROM activity_log al
               WHERE al.restaurant_id = a.restaurant_id
                 AND al.entity_type = 'professional_appointment'
                 AND al.entity_id = a.id
                 AND al.action = 'reminder_sent'
                 AND al.created_at >= a.updated_at
           )
         ORDER BY a.start_at ASC"
    );
    $appointmentsStmt->execute([$restaurantId, $hoursBefore]);
    $appointments = $appointmentsStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($appointments as $appointment) {
        $appointmentId = (int)$appointment['id'];
        $results = professionalSendAppointmentReminderNotifications($appointmentId);
        $sent = !empty($results['email']) || !empty($results['sms']);

        if ($sent) {
            $totalSent++;

            professionalLogAppointmentActivity(
                $restaurantId,
                null,
                $appointmentId,
                'reminder_sent',
                'Reminder sent for appointment #' . $appointment['confirmation_code'] . '.',
                null,
                $results
            );

            echo date('Y-m-d H:i:s') . " [OK] Reminder sent for professional appointment #{$appointment['confirmation_code']} at {$profile['business_name']}\n";
        } else {
            $totalFailed++;
            echo date('Y-m-d H:i:s') . " [FAIL] Could not send reminder for professional appointment #{$appointment['confirmation_code']} at {$profile['business_name']}\n";
        }
    }
}

echo date('Y-m-d H:i:s') . " Professional reminder run complete. Sent: {$totalSent}, Failed: {$totalFailed}\n";
