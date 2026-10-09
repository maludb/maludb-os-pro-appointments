<?php
/**
 * Setup a new company (restaurant) from an in-setup prospect.
 * Creates restaurant + user_restaurants + default config, then marks prospect as client.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../helpers/db.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="setup-company-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="setup-company-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$prospectId = (int)($_POST['prospect_id'] ?? 0);
$userId = currentUserId();

if ($prospectId <= 0) {
    echo '<div class="alert alert-danger" id="setup-company-invalid-error">Invalid prospect.</div>';
    exit;
}

$pdo = db();

// Get affiliate id for current context (user or restaurant)
$affiliateId = currentAffiliateId();

if (!$affiliateId) {
    echo '<div class="alert alert-danger" id="setup-company-aff-error">Affiliate account not found.</div>';
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM prospects WHERE id = ? AND affiliate_id = ? AND status = 'in_setup'");
$stmt->execute([$prospectId, $affiliateId]);
$p = $stmt->fetch();

if (!$p) {
    echo '<div class="alert alert-danger" id="setup-company-notfound-error">Prospect not found or not in setup status.</div>';
    exit;
}

require_once __DIR__ . '/../../../helpers/prospect-restaurant.php';

// Use existing restaurant if already auto-created, otherwise create one
$restaurantId = !empty($p['restaurant_id']) ? (int)$p['restaurant_id'] : null;

if (!$restaurantId) {
    $restaurantId = createRestaurantFromProspect($p, $userId);
    if (!$restaurantId) {
        echo '<div class="alert alert-danger" id="setup-company-create-error">Error creating restaurant.</div>';
        exit;
    }
}

$pdo->beginTransaction();
try {

    // Create default settings (IGNORE to skip if already exist from a prior attempt)
    $pdo->prepare(
        "INSERT INTO settings (restaurant_id, setting_key, setting_value) VALUES
            (?, 'time_slot_interval', '30'),
            (?, 'default_turn_time', '90'),
            (?, 'buffer_time', '15'),
            (?, 'max_party_size', '12'),
            (?, 'max_online_party_size', '8'),
            (?, 'advance_booking_min_hours', '2'),
            (?, 'advance_booking_max_days', '30'),
            (?, 'same_day_cutoff_hours', '1'),
            (?, 'max_covers_per_slot', '0'),
            (?, 'online_table_hold_percent', '70'),
            (?, 'confirmation_email_enabled', '1'),
            (?, 'reminder_email_enabled', '1'),
            (?, 'reminder_hours_before', '24'),
            (?, 'cancellation_email_enabled', '1'),
            (?, 'cancellation_policy', 'Please cancel at least 2 hours before your reservation time.')
         ON CONFLICT (restaurant_id, setting_key) DO NOTHING"
    )->execute(array_fill(0, 15, $restaurantId));

    // Create default section (IGNORE to skip if already exists)
    $pdo->prepare(
        "INSERT INTO sections (restaurant_id, name, description, display_order, is_active) VALUES (?, 'Main Dining', 'Main dining area', 1, 1) ON CONFLICT (restaurant_id, name) DO NOTHING"
    )->execute([$restaurantId]);

    // Create default turn times (IGNORE to skip if already exist)
    $pdo->prepare(
        "INSERT INTO turn_times (restaurant_id, min_party_size, max_party_size, service_period, duration_minutes, buffer_minutes)
         VALUES (?, 1, 4, 'all', 90, 15), (?, 5, 8, 'all', 105, 15), (?, 9, 12, 'all', 120, 15)
         ON CONFLICT (restaurant_id, min_party_size, max_party_size, service_period) DO NOTHING"
    )->execute([$restaurantId, $restaurantId, $restaurantId]);

    // Create default operating hours (Tue-Sat Lunch & Dinner, Sun Brunch & Dinner)
    $hoursStmt = $pdo->prepare(
        "INSERT INTO operating_hours (restaurant_id, day_of_week, service_name, open_time, close_time, first_seating, last_seating, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1)
         ON CONFLICT (restaurant_id, day_of_week, service_name) DO NOTHING"
    );
    foreach ([2, 3, 4, 5, 6] as $day) {
        $hoursStmt->execute([$restaurantId, $day, 'Lunch', '11:00:00', '14:30:00', '11:00:00', '14:00:00']);
        $hoursStmt->execute([$restaurantId, $day, 'Dinner', '17:00:00', '22:00:00', '17:00:00', '21:00:00']);
    }
    $hoursStmt->execute([$restaurantId, 0, 'Brunch', '10:00:00', '14:30:00', '10:00:00', '14:00:00']);
    $hoursStmt->execute([$restaurantId, 0, 'Dinner', '17:00:00', '21:00:00', '17:00:00', '20:00:00']);

    // Update prospect status to client
    $pdo->prepare("UPDATE prospects SET status = 'client', updated_at = NOW() WHERE id = ?")->execute([$prospectId]);

    // Update restaurant status to active
    $pdo->prepare("UPDATE restaurants SET status = 'active' WHERE id = ?")->execute([$restaurantId]);

    // Log status change activity
    $pdo->prepare(
        "INSERT INTO prospect_activities (prospect_id, affiliate_id, activity_type, subject, body, created_at)
         VALUES (?, ?, 'status_change', 'Company Setup Complete', ?, NOW())"
    )->execute([
        $prospectId,
        $affiliateId,
        'Restaurant "' . $p['business_name'] . '" created and linked to your account. You can now switch to it using the company switcher in the top navbar.'
    ]);

    $pdo->commit();

    // Full page reload so the navbar re-renders with the new company in the switcher
    header('HX-Redirect: /app.php');

} catch (Exception $e) {
    $pdo->rollBack();
    echo '<div class="alert alert-danger" id="setup-company-error">Error setting up company: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
