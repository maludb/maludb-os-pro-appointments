<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../helpers/validation.php';

requireAuth();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="professional-save-settings-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="professional-save-settings-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
$userId = currentUserId();

if (!$restaurantId) {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="professional-save-settings-no-restaurant">No professional account is currently selected.</div>';
    exit;
}

$businessName = trim($_POST['business_name'] ?? '');
$displayName = trim($_POST['display_name'] ?? '');
$businessEmail = trim($_POST['business_email'] ?? '');
$businessPhone = trim($_POST['business_phone'] ?? '');
$timezone = trim($_POST['timezone'] ?? '');
$bookingSlug = strtolower(trim($_POST['booking_slug'] ?? ''));
$defaultLocationType = trim($_POST['default_location_type'] ?? '');
$defaultLocationLabel = trim($_POST['default_location_label'] ?? '');
$bookingInstructions = trim($_POST['booking_instructions'] ?? '');
$cancellationPolicy = trim($_POST['cancellation_policy'] ?? '');
$cancellationNoticeHours = max(0, min(168, (int)($_POST['cancellation_notice_hours'] ?? 24)));
$isPublicBookingEnabled = isset($_POST['is_public_booking_enabled']) ? 1 : 0;
$notificationFromEmail = trim($_POST['notification_from_email'] ?? '');
$reminderHoursBefore = max(1, min(168, (int)($_POST['reminder_hours_before'] ?? 24)));
$notificationConfirmationEmail = isset($_POST['notification_confirmation_email']) ? '1' : '0';
$notificationConfirmationSms = isset($_POST['notification_confirmation_sms']) ? '1' : '0';
$notificationReminderEmail = isset($_POST['notification_reminder_email']) ? '1' : '0';
$notificationReminderSms = isset($_POST['notification_reminder_sms']) ? '1' : '0';
$notificationCancellationEmail = isset($_POST['notification_cancellation_email']) ? '1' : '0';
$notificationCancellationSms = isset($_POST['notification_cancellation_sms']) ? '1' : '0';

if ($businessName === '') {
    echo '<div class="alert alert-danger" id="professional-save-settings-business-name-error">Business name is required.</div>';
    exit;
}

if ($displayName === '') {
    echo '<div class="alert alert-danger" id="professional-save-settings-display-name-error">Display name is required.</div>';
    exit;
}

if ($timezone === '' || !in_array($timezone, timezone_identifiers_list(), true)) {
    echo '<div class="alert alert-danger" id="professional-save-settings-timezone-error">Please choose a valid timezone.</div>';
    exit;
}

if ($bookingSlug === '') {
    echo '<div class="alert alert-danger" id="professional-save-settings-booking-slug-required">Booking slug is required.</div>';
    exit;
}

if (!preg_match('/^[a-z0-9\-]+$/', $bookingSlug)) {
    echo '<div class="alert alert-danger" id="professional-save-settings-booking-slug-format">Booking slug must contain only lowercase letters, numbers, and hyphens.</div>';
    exit;
}

$allowedLocationTypes = ['in_person', 'phone', 'video', 'onsite', 'custom'];
if (!in_array($defaultLocationType, $allowedLocationTypes, true)) {
    echo '<div class="alert alert-danger" id="professional-save-settings-location-type-error">Please choose a valid default location type.</div>';
    exit;
}

if ($businessEmail !== '' && !validate_email($businessEmail)) {
    echo '<div class="alert alert-danger" id="professional-save-settings-email-error">Please enter a valid business email address.</div>';
    exit;
}

if ($notificationFromEmail !== '' && !validate_email($notificationFromEmail)) {
    echo '<div class="alert alert-danger" id="professional-save-settings-notification-email-error">Please enter a valid notification from email address.</div>';
    exit;
}

$pdo = db();

$slugStmt = $pdo->prepare(
    "SELECT id FROM professional_profiles
     WHERE booking_slug = ? AND restaurant_id != ?
     LIMIT 1"
);
$slugStmt->execute([$bookingSlug, $restaurantId]);
if ($slugStmt->fetch()) {
    echo '<div class="alert alert-danger" id="professional-save-settings-booking-slug-unique">This booking slug is already in use. Please choose a different one.</div>';
    exit;
}

$profileStmt = $pdo->prepare("SELECT id, owner_user_id FROM professional_profiles WHERE restaurant_id = ? LIMIT 1");
$profileStmt->execute([$restaurantId]);
$existingProfile = $profileStmt->fetch(PDO::FETCH_ASSOC);

if ($existingProfile) {
    $stmt = $pdo->prepare(
        "UPDATE professional_profiles SET
            business_name = ?,
            display_name = ?,
            business_phone = ?,
            business_email = ?,
            timezone = ?,
            booking_slug = ?,
            cancellation_notice_hours = ?,
            is_public_booking_enabled = ?,
            default_location_type = ?,
            default_location_label = ?,
            booking_instructions = ?,
            cancellation_policy = ?,
            updated_at = NOW()
         WHERE restaurant_id = ?"
    );

    $stmt->execute([
        $businessName,
        $displayName,
        $businessPhone ?: null,
        $businessEmail ?: null,
        $timezone,
        $bookingSlug,
        $cancellationNoticeHours,
        $isPublicBookingEnabled,
        $defaultLocationType,
        $defaultLocationLabel ?: null,
        $bookingInstructions ?: null,
        $cancellationPolicy ?: null,
        $restaurantId,
    ]);
} else {
    $stmt = $pdo->prepare(
        "INSERT INTO professional_profiles (
            restaurant_id,
            owner_user_id,
            business_name,
            display_name,
            business_phone,
            business_email,
            timezone,
            booking_slug,
            cancellation_notice_hours,
            is_public_booking_enabled,
            default_location_type,
            default_location_label,
            booking_instructions,
            cancellation_policy,
            created_at,
            updated_at
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
    );

    $stmt->execute([
        $restaurantId,
        $userId,
        $businessName,
        $displayName,
        $businessPhone ?: null,
        $businessEmail ?: null,
        $timezone,
        $bookingSlug,
        $cancellationNoticeHours,
        $isPublicBookingEnabled,
        $defaultLocationType,
        $defaultLocationLabel ?: null,
        $bookingInstructions ?: null,
        $cancellationPolicy ?: null,
    ]);
}

$timezoneUpdateStmt = $pdo->prepare("UPDATE restaurants SET timezone = ? WHERE id = ?");
$timezoneUpdateStmt->execute([$timezone, $restaurantId]);

$settingsToSave = [
    'notification_from_email' => $notificationFromEmail,
    'reminder_hours_before' => (string)$reminderHoursBefore,
    'notification_confirmation_email' => $notificationConfirmationEmail,
    'notification_confirmation_sms' => $notificationConfirmationSms,
    'notification_reminder_email' => $notificationReminderEmail,
    'notification_reminder_sms' => $notificationReminderSms,
    'notification_cancellation_email' => $notificationCancellationEmail,
    'notification_cancellation_sms' => $notificationCancellationSms,
];

$settingsStmt = $pdo->prepare(
    "INSERT INTO settings (restaurant_id, setting_key, setting_value)
     VALUES (?, ?, ?)
     ON CONFLICT (restaurant_id, setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value"
);

foreach ($settingsToSave as $settingKey => $settingValue) {
    $settingsStmt->execute([$restaurantId, $settingKey, $settingValue]);
}

echo '<div class="alert alert-success alert-dismissible fade show" id="professional-save-settings-success">
        <i class="feather-check-circle me-1"></i> Professional settings updated successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>';
