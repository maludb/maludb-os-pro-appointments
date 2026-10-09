<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireSuperAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="platform-save-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="platform-save-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$isEdit = $id > 0;

// Validate required fields
$name = trim($_POST['name'] ?? '');
$slug = trim($_POST['slug'] ?? '');
$timezone = trim($_POST['timezone'] ?? '');

if ($name === '' || $slug === '' || $timezone === '') {
    echo '<div class="alert alert-danger" id="platform-save-required-error">Name, slug, and timezone are required.</div>';
    exit;
}

// Validate slug format
if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
    echo '<div class="alert alert-danger" id="platform-save-slug-format-error">Slug must contain only lowercase letters, numbers, and hyphens.</div>';
    exit;
}

// Check slug uniqueness
$slugCheck = db()->prepare("SELECT id FROM restaurants WHERE slug = ? AND id != ?");
$slugCheck->execute([$slug, $id]);
if ($slugCheck->fetch()) {
    echo '<div class="alert alert-danger" id="platform-save-slug-unique-error">This slug is already in use. Please choose a different one.</div>';
    exit;
}

// Validate email if provided
$email = trim($_POST['email'] ?? '');
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo '<div class="alert alert-danger" id="platform-save-email-error">Please enter a valid email address.</div>';
    exit;
}

// Validate website URL if provided
$website = trim($_POST['website'] ?? '');
if ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
    echo '<div class="alert alert-danger" id="platform-save-url-error">Please enter a valid website URL.</div>';
    exit;
}

$phone = trim($_POST['phone'] ?? '');
$addressLine1 = trim($_POST['address_line1'] ?? '');
$city = trim($_POST['city'] ?? '');
$state = trim($_POST['state'] ?? '');
$postalCode = trim($_POST['postal_code'] ?? '');

$pdo = db();

if ($isEdit) {
    // Verify restaurant exists
    $check = $pdo->prepare("SELECT id FROM restaurants WHERE id = ?");
    $check->execute([$id]);
    if (!$check->fetch()) {
        echo '<div class="alert alert-danger" id="platform-save-notfound-error">Restaurant not found.</div>';
        exit;
    }

    $stmt = $pdo->prepare(
        "UPDATE restaurants SET
            name = ?, slug = ?, phone = ?, email = ?, address_line1 = ?,
            city = ?, state = ?, postal_code = ?, website = ?, timezone = ?
         WHERE id = ?"
    );
    $stmt->execute([
        $name, $slug, $phone ?: null, $email ?: null, $addressLine1 ?: null,
        $city ?: null, $state ?: null, $postalCode ?: null, $website ?: null, $timezone,
        $id
    ]);

    // Update session if editing current restaurant
    if (($id === (int)($_SESSION['current_restaurant_id'] ?? 0))) {
        $_SESSION['current_restaurant_name'] = $name;
    }

    header('HX-Trigger: closeModal');
    // Reload the restaurant dashboard
    $_GET['id'] = $id;
    include __DIR__ . '/restaurant-dashboard.php';
    exit;
}

// Create new restaurant
$pdo->beginTransaction();
try {
    // Insert restaurant
    $stmt = $pdo->prepare(
        "INSERT INTO restaurants (name, slug, phone, email, address_line1, city, state, postal_code, website, timezone, is_active, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())"
    );
    $stmt->execute([
        $name, $slug, $phone ?: null, $email ?: null, $addressLine1 ?: null,
        $city ?: null, $state ?: null, $postalCode ?: null, $website ?: null, $timezone
    ]);
    $restaurantId = (int)$pdo->lastInsertId();

    // Create default settings
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
            (?, 'cancellation_policy', 'Please cancel at least 2 hours before your reservation time.')"
    )->execute(array_fill(0, 15, $restaurantId));

    // Create default section
    $pdo->prepare(
        "INSERT INTO sections (restaurant_id, name, description, display_order, is_active) VALUES (?, 'Main Dining', 'Main dining area', 1, 1)"
    )->execute([$restaurantId]);

    // Create default turn time
    $pdo->prepare(
        "INSERT INTO turn_times (restaurant_id, party_size_min, party_size_max, service_period, duration_minutes, buffer_minutes)
         VALUES (?, 1, 4, 'all', 90, 15), (?, 5, 8, 'all', 105, 15), (?, 9, 12, 'all', 120, 15)"
    )->execute([$restaurantId, $restaurantId, $restaurantId]);

    // Create default operating hours (Tue-Sat Lunch & Dinner)
    $days = [2, 3, 4, 5, 6]; // Tue-Sat
    $hoursStmt = $pdo->prepare(
        "INSERT INTO operating_hours (restaurant_id, day_of_week, service_name, open_time, close_time, first_seating, last_seating, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1)"
    );
    foreach ($days as $day) {
        $hoursStmt->execute([$restaurantId, $day, 'Lunch', '11:00:00', '14:30:00', '11:00:00', '14:00:00']);
        $hoursStmt->execute([$restaurantId, $day, 'Dinner', '17:00:00', '22:00:00', '17:00:00', '21:00:00']);
    }
    // Sunday Brunch + Dinner
    $hoursStmt->execute([$restaurantId, 0, 'Brunch', '10:00:00', '14:30:00', '10:00:00', '14:00:00']);
    $hoursStmt->execute([$restaurantId, 0, 'Dinner', '17:00:00', '21:00:00', '17:00:00', '20:00:00']);

    // Assign creating user as admin
    $userId = currentUserId();
    $pdo->prepare(
        "INSERT INTO user_restaurants (user_id, restaurant_id, role, is_active) VALUES (?, ?, 'admin', 1)"
    )->execute([$userId, $restaurantId]);

    $pdo->commit();

    header('HX-Trigger: closeModal');
    // Reload the restaurants list
    include __DIR__ . '/restaurants.php';

} catch (Exception $e) {
    $pdo->rollBack();
    echo '<div class="alert alert-danger" id="platform-save-error">Error creating restaurant: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
