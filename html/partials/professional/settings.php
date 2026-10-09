<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/availability.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();
requireAdmin();

$restaurantId = currentRestaurantId();
$user = get_user();

if (!$restaurantId) {
    echo '<div class="alert alert-danger" id="professional-settings-no-restaurant">No professional account is currently selected.</div>';
    exit;
}

$pdo = db();

$restaurantStmt = $pdo->prepare("SELECT id, name, phone, email, timezone, slug FROM restaurants WHERE id = ?");
$restaurantStmt->execute([$restaurantId]);
$restaurant = $restaurantStmt->fetch(PDO::FETCH_ASSOC);

if (!$restaurant) {
    echo '<div class="alert alert-danger" id="professional-settings-no-business">Professional business not found.</div>';
    exit;
}

$profileStmt = $pdo->prepare("SELECT * FROM professional_profiles WHERE restaurant_id = ?");
$profileStmt->execute([$restaurantId]);
$profile = $profileStmt->fetch(PDO::FETCH_ASSOC);

$policyStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE restaurant_id = ? AND setting_key = 'cancellation_policy' LIMIT 1");
$policyStmt->execute([$restaurantId]);
$defaultCancellationPolicy = (string)($policyStmt->fetchColumn() ?: '');

$timezones = [
    'America/New_York' => 'Eastern (New York)',
    'America/Chicago' => 'Central (Chicago)',
    'America/Denver' => 'Mountain (Denver)',
    'America/Phoenix' => 'Arizona (Phoenix)',
    'America/Los_Angeles' => 'Pacific (Los Angeles)',
    'America/Anchorage' => 'Alaska (Anchorage)',
    'Pacific/Honolulu' => 'Hawaii (Honolulu)',
];

$locationTypes = [
    'in_person' => 'In Person',
    'phone' => 'Phone',
    'video' => 'Video',
    'onsite' => 'On Site',
    'custom' => 'Custom',
];

$formData = [
    'business_name' => $profile['business_name'] ?? $restaurant['name'],
    'display_name' => $profile['display_name'] ?? $restaurant['name'],
    'business_phone' => $profile['business_phone'] ?? ($restaurant['phone'] ?? ''),
    'business_email' => $profile['business_email'] ?? ($restaurant['email'] ?? ($user['email'] ?? '')),
    'timezone' => $profile['timezone'] ?? ($restaurant['timezone'] ?? 'America/New_York'),
    'booking_slug' => $profile['booking_slug'] ?? ($restaurant['slug'] ?? ''),
    'default_location_type' => $profile['default_location_type'] ?? 'in_person',
    'default_location_label' => $profile['default_location_label'] ?? '',
    'booking_instructions' => $profile['booking_instructions'] ?? '',
    'cancellation_policy' => $profile['cancellation_policy'] ?? $defaultCancellationPolicy,
    'cancellation_notice_hours' => (int)($profile['cancellation_notice_hours'] ?? 24),
    'is_public_booking_enabled' => isset($profile['is_public_booking_enabled']) ? (int)$profile['is_public_booking_enabled'] : 1,
    'notification_from_email' => getRestaurantSetting($restaurantId, 'notification_from_email', $profile['business_email'] ?? ($restaurant['email'] ?? '')),
    'reminder_hours_before' => (int)getRestaurantSetting($restaurantId, 'reminder_hours_before', '24'),
    'notification_confirmation_email' => getRestaurantSetting($restaurantId, 'notification_confirmation_email', '1'),
    'notification_confirmation_sms' => getRestaurantSetting($restaurantId, 'notification_confirmation_sms', '0'),
    'notification_reminder_email' => getRestaurantSetting($restaurantId, 'notification_reminder_email', '1'),
    'notification_reminder_sms' => getRestaurantSetting($restaurantId, 'notification_reminder_sms', '0'),
    'notification_cancellation_email' => getRestaurantSetting($restaurantId, 'notification_cancellation_email', '1'),
    'notification_cancellation_sms' => getRestaurantSetting($restaurantId, 'notification_cancellation_sms', '0'),
];
$bookingPageUrl = (
    !empty($profile)
    && !empty($profile['id'])
    && !empty($profile['booking_slug'])
) ? '/pro-booking/index.php?professional=' . urlencode($profile['booking_slug']) : '';
?>
<div class="main-content" id="professional-settings-main">
    <div class="row" id="professional-settings-row">
        <div class="col-xxl-8 col-xl-10 col-12" id="professional-settings-col">
            <div class="card" id="professional-settings-card">
                <div class="card-header d-flex align-items-center justify-content-between" id="professional-settings-card-header">
                    <div id="professional-settings-card-header-copy">
                        <h5 class="card-title mb-1" id="professional-settings-card-title">
                            <i class="feather-settings me-2"></i>Professional Settings
                        </h5>
                        <p class="text-muted mb-0" id="professional-settings-card-subtitle">
                            Manage the business profile, booking slug, timezone, and booking rules for your professional scheduling workspace.
                        </p>
                    </div>
                    <div class="d-flex flex-wrap gap-2" id="professional-settings-card-header-actions">
                        <a href="#" class="btn btn-outline-primary btn-sm" id="professional-settings-back-dashboard"
                           hx-get="/partials/professional/dashboard.php"
                           hx-target="#page-content">
                            <i class="feather-arrow-left me-1"></i> Dashboard
                        </a>
                        <?php if ($bookingPageUrl !== ''): ?>
                        <a href="<?php echo htmlspecialchars($bookingPageUrl); ?>" class="btn btn-outline-success btn-sm" id="professional-settings-view-booking-page" target="_blank" rel="noopener">
                            <i class="feather-external-link me-1"></i> View Booking Page
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body" id="professional-settings-card-body">
                    <div id="professional-settings-messages"></div>

                    <form id="professional-settings-form"
                          hx-post="/partials/professional/save-settings.php"
                          hx-target="#professional-settings-messages"
                          hx-swap="innerHTML"
                          hx-on::after-request="window.scrollTo({top: 0, behavior: 'smooth'})">
                        <?php echo csrf_field(); ?>

                        <div class="row g-3" id="professional-settings-fields-row">
                            <div class="col-md-6" id="professional-settings-business-name-wrap">
                                <label for="professional-settings-business-name" class="form-label">Business Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="professional-settings-business-name" name="business_name"
                                       value="<?php echo htmlspecialchars($formData['business_name']); ?>" required>
                            </div>
                            <div class="col-md-6" id="professional-settings-display-name-wrap">
                                <label for="professional-settings-display-name" class="form-label">Display Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="professional-settings-display-name" name="display_name"
                                       value="<?php echo htmlspecialchars($formData['display_name']); ?>" required>
                            </div>

                            <div class="col-md-6" id="professional-settings-phone-wrap">
                                <label for="professional-settings-phone" class="form-label">Business Phone</label>
                                <input type="tel" class="form-control" id="professional-settings-phone" name="business_phone"
                                       value="<?php echo htmlspecialchars($formData['business_phone']); ?>">
                            </div>
                            <div class="col-md-6" id="professional-settings-email-wrap">
                                <label for="professional-settings-email" class="form-label">Business Email</label>
                                <input type="email" class="form-control" id="professional-settings-email" name="business_email"
                                       value="<?php echo htmlspecialchars($formData['business_email']); ?>">
                            </div>

                            <div class="col-md-6" id="professional-settings-timezone-wrap">
                                <label for="professional-settings-timezone" class="form-label">Timezone <span class="text-danger">*</span></label>
                                <select class="form-select" id="professional-settings-timezone" name="timezone" required>
                                    <?php foreach ($timezones as $timezoneValue => $timezoneLabel): ?>
                                    <option value="<?php echo htmlspecialchars($timezoneValue); ?>"
                                        <?php echo ($formData['timezone'] === $timezoneValue) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($timezoneLabel); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6" id="professional-settings-booking-slug-wrap">
                                <label for="professional-settings-booking-slug" class="form-label">Booking Slug <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="professional-settings-booking-slug" name="booking_slug"
                                       value="<?php echo htmlspecialchars($formData['booking_slug']); ?>"
                                       pattern="[a-z0-9\-]+"
                                       title="Lowercase letters, numbers, and hyphens only"
                                       oninput="this.value=this.value.toLowerCase().replace(/[^a-z0-9-]/g,'-').replace(/--+/g,'-')"
                                       required>
                                <div class="form-text" id="professional-settings-booking-slug-help">
                                    This slug is used for the public booking page URL.
                                </div>
                            </div>

                            <div class="col-md-6" id="professional-settings-location-type-wrap">
                                <label for="professional-settings-location-type" class="form-label">Default Location Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="professional-settings-location-type" name="default_location_type" required>
                                    <?php foreach ($locationTypes as $locationTypeValue => $locationTypeLabel): ?>
                                    <option value="<?php echo htmlspecialchars($locationTypeValue); ?>"
                                        <?php echo ($formData['default_location_type'] === $locationTypeValue) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($locationTypeLabel); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6" id="professional-settings-location-label-wrap">
                                <label for="professional-settings-location-label" class="form-label">Default Location Label</label>
                                <input type="text" class="form-control" id="professional-settings-location-label" name="default_location_label"
                                       value="<?php echo htmlspecialchars($formData['default_location_label']); ?>"
                                       placeholder="Office address, Zoom room, call instructions, etc.">
                            </div>

                            <div class="col-12" id="professional-settings-booking-instructions-wrap">
                                <label for="professional-settings-booking-instructions" class="form-label">Booking Instructions</label>
                                <textarea class="form-control" id="professional-settings-booking-instructions" name="booking_instructions"
                                          rows="4"><?php echo htmlspecialchars($formData['booking_instructions']); ?></textarea>
                            </div>

                            <div class="col-12" id="professional-settings-cancellation-policy-wrap">
                                <label for="professional-settings-cancellation-policy" class="form-label">Cancellation Policy</label>
                                <textarea class="form-control" id="professional-settings-cancellation-policy" name="cancellation_policy"
                                          rows="4"><?php echo htmlspecialchars($formData['cancellation_policy']); ?></textarea>
                            </div>

                            <div class="col-12" id="professional-settings-booking-controls-col">
                                <div class="border rounded p-3 bg-light" id="professional-settings-booking-controls-card">
                                    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3" id="professional-settings-booking-controls-header">
                                        <div id="professional-settings-booking-controls-copy">
                                            <h6 class="mb-1" id="professional-settings-booking-controls-title">Online Booking Controls</h6>
                                            <p class="text-muted mb-0" id="professional-settings-booking-controls-subtitle">
                                                Control whether clients can book online and how close to the appointment they can still change it themselves.
                                            </p>
                                        </div>
                                    </div>

                                    <div class="row g-3 mt-1" id="professional-settings-booking-controls-fields">
                                        <div class="col-md-6" id="professional-settings-public-booking-wrap">
                                            <div class="form-check form-switch mt-2" id="professional-settings-public-booking-toggle-wrap">
                                                <input class="form-check-input" type="checkbox" id="professional-settings-public-booking-enabled" name="is_public_booking_enabled" value="1"
                                                    <?php echo (int)$formData['is_public_booking_enabled'] === 1 ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="professional-settings-public-booking-enabled">
                                                    Allow clients to book online
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-6" id="professional-settings-cancellation-notice-wrap">
                                            <label for="professional-settings-cancellation-notice-hours" class="form-label">Cancellation / Reschedule Notice Hours</label>
                                            <input type="number" class="form-control" id="professional-settings-cancellation-notice-hours" name="cancellation_notice_hours"
                                                   min="0" max="168" value="<?php echo (int)$formData['cancellation_notice_hours']; ?>">
                                            <div class="form-text" id="professional-settings-cancellation-notice-help">
                                                Clients will be blocked from self-service changes inside this window.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12" id="professional-settings-notifications-col">
                                <div class="border rounded p-3" id="professional-settings-notifications-card">
                                    <div id="professional-settings-notifications-header">
                                        <h6 class="mb-1" id="professional-settings-notifications-title">Appointment Notifications</h6>
                                        <p class="text-muted mb-3" id="professional-settings-notifications-subtitle">
                                            Use the existing email and Twilio transport settings for confirmations, reminders, and cancellations.
                                        </p>
                                    </div>

                                    <div class="row g-3" id="professional-settings-notifications-fields">
                                        <div class="col-md-6" id="professional-settings-notification-from-email-wrap">
                                            <label for="professional-settings-notification-from-email" class="form-label">From Email Address</label>
                                            <input type="email" class="form-control" id="professional-settings-notification-from-email" name="notification_from_email"
                                                   value="<?php echo htmlspecialchars($formData['notification_from_email']); ?>"
                                                   placeholder="noreply@example.com">
                                        </div>
                                        <div class="col-md-6" id="professional-settings-reminder-hours-wrap">
                                            <label for="professional-settings-reminder-hours-before" class="form-label">Reminder Hours Before</label>
                                            <input type="number" class="form-control" id="professional-settings-reminder-hours-before" name="reminder_hours_before"
                                                   min="1" max="168" value="<?php echo (int)$formData['reminder_hours_before']; ?>">
                                        </div>

                                        <div class="col-md-4" id="professional-settings-notify-confirm-email-wrap">
                                            <div class="form-check form-switch" id="professional-settings-notify-confirm-email-toggle-wrap">
                                                <input class="form-check-input" type="checkbox" id="professional-settings-notify-confirm-email" name="notification_confirmation_email" value="1"
                                                    <?php echo $formData['notification_confirmation_email'] === '1' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="professional-settings-notify-confirm-email">Confirmation Email</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4" id="professional-settings-notify-confirm-sms-wrap">
                                            <div class="form-check form-switch" id="professional-settings-notify-confirm-sms-toggle-wrap">
                                                <input class="form-check-input" type="checkbox" id="professional-settings-notify-confirm-sms" name="notification_confirmation_sms" value="1"
                                                    <?php echo $formData['notification_confirmation_sms'] === '1' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="professional-settings-notify-confirm-sms">Confirmation SMS</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4" id="professional-settings-notify-reminder-email-wrap">
                                            <div class="form-check form-switch" id="professional-settings-notify-reminder-email-toggle-wrap">
                                                <input class="form-check-input" type="checkbox" id="professional-settings-notify-reminder-email" name="notification_reminder_email" value="1"
                                                    <?php echo $formData['notification_reminder_email'] === '1' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="professional-settings-notify-reminder-email">Reminder Email</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4" id="professional-settings-notify-reminder-sms-wrap">
                                            <div class="form-check form-switch" id="professional-settings-notify-reminder-sms-toggle-wrap">
                                                <input class="form-check-input" type="checkbox" id="professional-settings-notify-reminder-sms" name="notification_reminder_sms" value="1"
                                                    <?php echo $formData['notification_reminder_sms'] === '1' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="professional-settings-notify-reminder-sms">Reminder SMS</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4" id="professional-settings-notify-cancel-email-wrap">
                                            <div class="form-check form-switch" id="professional-settings-notify-cancel-email-toggle-wrap">
                                                <input class="form-check-input" type="checkbox" id="professional-settings-notify-cancel-email" name="notification_cancellation_email" value="1"
                                                    <?php echo $formData['notification_cancellation_email'] === '1' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="professional-settings-notify-cancel-email">Cancellation Email</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4" id="professional-settings-notify-cancel-sms-wrap">
                                            <div class="form-check form-switch" id="professional-settings-notify-cancel-sms-toggle-wrap">
                                                <input class="form-check-input" type="checkbox" id="professional-settings-notify-cancel-sms" name="notification_cancellation_sms" value="1"
                                                    <?php echo $formData['notification_cancellation_sms'] === '1' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="professional-settings-notify-cancel-sms">Cancellation SMS</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4" id="professional-settings-submit-wrap">
                            <button type="submit" class="btn btn-primary" id="professional-settings-save-btn">
                                <i class="feather-save me-1"></i> Save Settings
                                <span class="htmx-indicator spinner-border spinner-border-sm ms-2"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
