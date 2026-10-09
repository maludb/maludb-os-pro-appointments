<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';
require_once '../../../helpers/validation.php';

requireAuth();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="save-profile-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="save-profile-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
if (!$restaurantId) {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="save-profile-no-restaurant">No restaurant selected.</div>';
    exit;
}

// Validate required fields
$name = trim($_POST['name'] ?? '');
$timezone = trim($_POST['timezone'] ?? '');

if ($name === '') {
    echo '<div class="alert alert-danger" id="save-profile-name-error">Restaurant name is required.</div>';
    exit;
}

if ($timezone === '') {
    echo '<div class="alert alert-danger" id="save-profile-tz-error">Timezone is required.</div>';
    exit;
}

// Validate email if provided
$email = trim($_POST['email'] ?? '');
if ($email !== '' && !validate_email($email)) {
    echo '<div class="alert alert-danger" id="save-profile-email-error">Please enter a valid email address.</div>';
    exit;
}

// Validate website URL if provided
$website = trim($_POST['website'] ?? '');
if ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
    echo '<div class="alert alert-danger" id="save-profile-url-error">Please enter a valid website URL.</div>';
    exit;
}

// Sanitize inputs
$phone = trim($_POST['phone'] ?? '');
$addressLine1 = trim($_POST['address_line1'] ?? '');
$city = trim($_POST['city'] ?? '');
$state = trim($_POST['state'] ?? '');
$postalCode = trim($_POST['postal_code'] ?? '');

// Update restaurant record scoped to current restaurant_id
$stmt = db()->prepare(
    "UPDATE restaurants SET
        name = ?, phone = ?, email = ?, address_line1 = ?,
        city = ?, state = ?, postal_code = ?, website = ?, timezone = ?
     WHERE id = ?"
);

$stmt->execute([
    $name, $phone ?: null, $email ?: null, $addressLine1 ?: null,
    $city ?: null, $state ?: null, $postalCode ?: null, $website ?: null, $timezone,
    $restaurantId
]);

// Update session with new restaurant name
$_SESSION['current_restaurant_name'] = $name;

echo '<div class="alert alert-success alert-dismissible fade show" id="save-profile-success">
        <i class="feather-check-circle me-1"></i> Restaurant profile updated successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>';
