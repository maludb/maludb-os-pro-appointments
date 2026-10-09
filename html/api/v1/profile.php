<?php
/**
 * GET /api/v1/profile.php — Business profile, booking settings and notification settings
 * PUT /api/v1/profile.php — Update any of those fields, partial (admin)
 *
 * PUT validation mirrors html/partials/professional/save-settings.php.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../helpers/professional-availability.php';
require_once __DIR__ . '/../../../helpers/validation.php';

// Notification settings stored in the settings table, with the web form's defaults
const PROFILE_NOTIFICATION_SETTINGS = [
    'notification_from_email'         => '',
    'reminder_hours_before'           => '24',
    'notification_confirmation_email' => '0',
    'notification_confirmation_sms'   => '0',
    'notification_reminder_email'     => '0',
    'notification_reminder_sms'       => '0',
    'notification_cancellation_email' => '0',
    'notification_cancellation_sms'   => '0',
];

$auth = api_authenticate();
$rid  = $auth['restaurant_id'];

if (api_method() === 'GET') {
    $profile = getProfessionalProfile($rid);
    if (!$profile) {
        api_error('Professional profile not configured.', 'NOT_FOUND', 404);
    }
    api_success(['profile' => formatProfile($profile, loadNotificationSettings($rid))]);
} elseif (api_method() === 'PUT') {
    api_require_role($auth, 'admin');
    handleUpdateProfile($rid, $auth['user_id']);
} else {
    api_error('Method not allowed.', 'METHOD_NOT_ALLOWED', 405);
}

function loadNotificationSettings(int $rid): array {
    $keys = array_keys(PROFILE_NOTIFICATION_SETTINGS);
    $stmt = db()->prepare(
        "SELECT setting_key, setting_value FROM settings
         WHERE restaurant_id = ? AND setting_key IN (" . implode(',', array_fill(0, count($keys), '?')) . ")"
    );
    $stmt->execute(array_merge([$rid], $keys));
    return array_merge(PROFILE_NOTIFICATION_SETTINGS, $stmt->fetchAll(PDO::FETCH_KEY_PAIR));
}

function handleUpdateProfile(int $rid, int $userId): void {
    $pdo = db();
    $profile = getProfessionalProfile($rid) ?: [];
    $settings = loadNotificationSettings($rid);

    $current = [
        'business_name' => $profile['business_name'] ?? '',
        'display_name' => $profile['display_name'] ?? '',
        'business_email' => $profile['business_email'] ?? '',
        'business_phone' => $profile['business_phone'] ?? '',
        'timezone' => $profile['timezone'] ?? '',
        'booking_slug' => $profile['booking_slug'] ?? '',
        'default_location_type' => $profile['default_location_type'] ?? 'in_person',
        'default_location_label' => $profile['default_location_label'] ?? '',
        'booking_instructions' => $profile['booking_instructions'] ?? '',
        'cancellation_policy' => $profile['cancellation_policy'] ?? '',
        'cancellation_notice_hours' => $profile['cancellation_notice_hours'] ?? 24,
        'is_public_booking_enabled' => $profile['is_public_booking_enabled'] ?? 1,
    ] + $settings;
    $d = array_merge($current, array_intersect_key(api_json_body(), $current));

    $businessName = trim((string)$d['business_name']);
    $displayName = trim((string)$d['display_name']);
    $businessEmail = trim((string)$d['business_email']);
    $timezone = trim((string)$d['timezone']);
    $bookingSlug = strtolower(trim((string)$d['booking_slug']));
    $defaultLocationType = trim((string)$d['default_location_type']);
    $notificationFromEmail = trim((string)$d['notification_from_email']);

    if ($businessName === '') api_error('Business name is required.', 'VALIDATION_ERROR');
    if ($displayName === '') api_error('Display name is required.', 'VALIDATION_ERROR');
    if (!in_array($timezone, timezone_identifiers_list(), true)) {
        api_error('timezone must be a valid identifier such as America/Chicago.', 'VALIDATION_ERROR');
    }
    if (!preg_match('/^[a-z0-9\-]+$/', $bookingSlug)) {
        api_error('Booking slug must contain only lowercase letters, numbers, and hyphens.', 'VALIDATION_ERROR');
    }
    if (!in_array($defaultLocationType, ['in_person', 'phone', 'video', 'onsite', 'custom'], true)) {
        api_error('default_location_type must be one of in_person, phone, video, onsite, custom.', 'VALIDATION_ERROR');
    }
    if ($businessEmail !== '' && !validate_email($businessEmail)) {
        api_error('Please enter a valid business email address.', 'VALIDATION_ERROR');
    }
    if ($notificationFromEmail !== '' && !validate_email($notificationFromEmail)) {
        api_error('Please enter a valid notification from email address.', 'VALIDATION_ERROR');
    }

    $slugStmt = $pdo->prepare("SELECT id FROM professional_profiles WHERE booking_slug = ? AND restaurant_id != ? LIMIT 1");
    $slugStmt->execute([$bookingSlug, $rid]);
    if ($slugStmt->fetch()) {
        api_error('This booking slug is already in use. Please choose a different one.', 'DUPLICATE');
    }

    $values = [
        $businessName,
        $displayName,
        trim((string)$d['business_phone']) ?: null,
        $businessEmail ?: null,
        $timezone,
        $bookingSlug,
        max(0, min(168, (int)$d['cancellation_notice_hours'])),
        api_bool($d['is_public_booking_enabled']) ? 1 : 0,
        $defaultLocationType,
        trim((string)$d['default_location_label']) ?: null,
        trim((string)$d['booking_instructions']) ?: null,
        trim((string)$d['cancellation_policy']) ?: null,
    ];

    if ($profile) {
        $pdo->prepare(
            "UPDATE professional_profiles SET
                business_name = ?, display_name = ?, business_phone = ?, business_email = ?, timezone = ?,
                booking_slug = ?, cancellation_notice_hours = ?, is_public_booking_enabled = ?,
                default_location_type = ?, default_location_label = ?, booking_instructions = ?,
                cancellation_policy = ?, updated_at = NOW()
             WHERE restaurant_id = ?"
        )->execute(array_merge($values, [$rid]));
    } else {
        $pdo->prepare(
            "INSERT INTO professional_profiles (
                business_name, display_name, business_phone, business_email, timezone, booking_slug,
                cancellation_notice_hours, is_public_booking_enabled, default_location_type,
                default_location_label, booking_instructions, cancellation_policy,
                restaurant_id, owner_user_id, created_at, updated_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
        )->execute(array_merge($values, [$rid, $userId]));
    }

    $pdo->prepare("UPDATE restaurants SET timezone = ? WHERE id = ?")->execute([$timezone, $rid]);

    $settingsStmt = $pdo->prepare(
        "INSERT INTO settings (restaurant_id, setting_key, setting_value) VALUES (?, ?, ?)
         ON CONFLICT (restaurant_id, setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value"
    );
    foreach (array_keys(PROFILE_NOTIFICATION_SETTINGS) as $key) {
        if ($key === 'notification_from_email') {
            $value = $notificationFromEmail;
        } elseif ($key === 'reminder_hours_before') {
            $value = (string)max(1, min(168, (int)$d[$key]));
        } else {
            $value = api_bool($d[$key]) ? '1' : '0';
        }
        $settingsStmt->execute([$rid, $key, $value]);
    }

    // Re-read directly: getProfessionalProfile() caches the pre-update row for this request
    $stmt = $pdo->prepare("SELECT * FROM professional_profiles WHERE restaurant_id = ?");
    $stmt->execute([$rid]);
    api_success(['profile' => formatProfile($stmt->fetch(), loadNotificationSettings($rid))]);
}

function formatProfile(array $profile, array $settings): array {
    $formatted = [
        'id'                            => (int)$profile['id'],
        'business_name'                 => $profile['business_name'],
        'display_name'                  => $profile['display_name'],
        'business_phone'                => $profile['business_phone'],
        'business_email'                => $profile['business_email'],
        'timezone'                      => $profile['timezone'],
        'booking_slug'                  => $profile['booking_slug'],
        'slot_interval_minutes'         => (int)$profile['slot_interval_minutes'],
        'default_buffer_before_minutes' => (int)$profile['default_buffer_before_minutes'],
        'default_buffer_after_minutes'  => (int)$profile['default_buffer_after_minutes'],
        'minimum_booking_notice_hours'  => (int)$profile['minimum_booking_notice_hours'],
        'maximum_booking_horizon_days'  => (int)$profile['maximum_booking_horizon_days'],
        'default_location_type'         => $profile['default_location_type'],
        'default_location_label'        => $profile['default_location_label'],
        'booking_instructions'          => $profile['booking_instructions'],
        'cancellation_policy'           => $profile['cancellation_policy'],
        'cancellation_notice_hours'     => (int)$profile['cancellation_notice_hours'],
        'is_public_booking_enabled'     => (bool)$profile['is_public_booking_enabled'],
        'notification_from_email'       => $settings['notification_from_email'],
        'reminder_hours_before'         => (int)$settings['reminder_hours_before'],
    ];
    foreach ($settings as $key => $value) {
        if (!isset($formatted[$key])) {
            $formatted[$key] = $value === '1';
        }
    }
    return $formatted;
}
