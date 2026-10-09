<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();
requireManager();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="save-hours-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="save-hours-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
if (!$restaurantId) {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="save-hours-no-restaurant">No restaurant selected.</div>';
    exit;
}

$action = trim($_POST['action'] ?? '');
$hourId = (int)($_POST['hour_id'] ?? 0);
$pdo = db();

// Handle toggle action
if ($action === 'toggle' && $hourId > 0) {
    $stmt = $pdo->prepare("SELECT is_active FROM operating_hours WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$hourId, $restaurantId]);
    $row = $stmt->fetch();

    if (!$row) {
        echo '<div class="alert alert-danger" id="save-hours-toggle-notfound">Service period not found.</div>';
        exit;
    }

    $newStatus = $row['is_active'] ? 0 : 1;
    $stmt = $pdo->prepare("UPDATE operating_hours SET is_active = ? WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$newStatus, $hourId, $restaurantId]);

    header('HX-Trigger: refreshHoursList');
    $label = $newStatus ? 'activated' : 'deactivated';
    echo '<div class="alert alert-success alert-dismissible fade show" id="save-hours-toggle-success">
            <i class="feather-check-circle me-1"></i> Service period ' . $label . '.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    exit;
}

// Handle delete action
if ($action === 'delete' && $hourId > 0) {
    $stmt = $pdo->prepare("SELECT id FROM operating_hours WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$hourId, $restaurantId]);
    if (!$stmt->fetch()) {
        echo '<div class="alert alert-danger" id="save-hours-delete-notfound">Service period not found.</div>';
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM operating_hours WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$hourId, $restaurantId]);

    header('HX-Trigger: refreshHoursList');
    echo '<div class="alert alert-success alert-dismissible fade show" id="save-hours-delete-success">
            <i class="feather-check-circle me-1"></i> Service period deleted.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    exit;
}

// Handle save (add/edit)
if ($action !== 'save') {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="save-hours-action-error">Invalid action.</div>';
    exit;
}

$isEdit = $hourId > 0;
$dayOfWeek = (int)($_POST['day_of_week'] ?? -1);
$serviceName = trim($_POST['service_name'] ?? '');
$openTime = trim($_POST['open_time'] ?? '');
$closeTime = trim($_POST['close_time'] ?? '');
$firstSeating = trim($_POST['first_seating'] ?? '');
$lastSeating = trim($_POST['last_seating'] ?? '');
$isActive = isset($_POST['is_active']) ? 1 : 0;

// Validate required fields
if ($dayOfWeek < 0 || $dayOfWeek > 6) {
    echo '<div class="alert alert-danger" id="save-hours-day-error">Please select a valid day.</div>';
    exit;
}

if ($serviceName === '') {
    echo '<div class="alert alert-danger" id="save-hours-service-error">Service name is required.</div>';
    exit;
}

if ($openTime === '' || $closeTime === '' || $firstSeating === '' || $lastSeating === '') {
    echo '<div class="alert alert-danger" id="save-hours-times-error">All time fields are required.</div>';
    exit;
}

// Validate time ordering
if ($openTime >= $closeTime) {
    echo '<div class="alert alert-danger" id="save-hours-oc-error">Open time must be before close time.</div>';
    exit;
}

if ($firstSeating < $openTime) {
    echo '<div class="alert alert-danger" id="save-hours-fs-error">First seating cannot be before open time.</div>';
    exit;
}

if ($lastSeating > $closeTime) {
    echo '<div class="alert alert-danger" id="save-hours-ls-error">Last seating cannot be after close time.</div>';
    exit;
}

if ($firstSeating >= $lastSeating) {
    echo '<div class="alert alert-danger" id="save-hours-fl-error">First seating must be before last seating.</div>';
    exit;
}

// Check uniqueness (day + service_name per restaurant)
$uniqueQuery = "SELECT id FROM operating_hours WHERE restaurant_id = ? AND day_of_week = ? AND service_name = ?";
$uniqueParams = [$restaurantId, $dayOfWeek, $serviceName];
if ($isEdit) {
    $uniqueQuery .= " AND id != ?";
    $uniqueParams[] = $hourId;
}
$stmt = $pdo->prepare($uniqueQuery);
$stmt->execute($uniqueParams);
if ($stmt->fetch()) {
    echo '<div class="alert alert-danger" id="save-hours-dup-error">This day already has a service period with that name.</div>';
    exit;
}

try {
    if ($isEdit) {
        $stmt = $pdo->prepare("SELECT id FROM operating_hours WHERE id = ? AND restaurant_id = ?");
        $stmt->execute([$hourId, $restaurantId]);
        if (!$stmt->fetch()) {
            echo '<div class="alert alert-danger" id="save-hours-notfound">Service period not found.</div>';
            exit;
        }

        $stmt = $pdo->prepare(
            "UPDATE operating_hours SET day_of_week = ?, service_name = ?, open_time = ?, close_time = ?,
                    first_seating = ?, last_seating = ?, is_active = ?
             WHERE id = ? AND restaurant_id = ?"
        );
        $stmt->execute([$dayOfWeek, $serviceName, $openTime, $closeTime, $firstSeating, $lastSeating, $isActive, $hourId, $restaurantId]);
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO operating_hours (restaurant_id, day_of_week, service_name, open_time, close_time, first_seating, last_seating, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$restaurantId, $dayOfWeek, $serviceName, $openTime, $closeTime, $firstSeating, $lastSeating, $isActive]);
    }

    header('HX-Trigger: refreshHoursList');

    echo '<div class="alert alert-success alert-dismissible fade show" id="save-hours-success">
            <i class="feather-check-circle me-1"></i> Service period ' . ($isEdit ? 'updated' : 'added') . ' successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    echo '<script>document.getElementById("hours-modal-container").innerHTML="";</script>';

} catch (Exception $e) {
    echo '<div class="alert alert-danger" id="save-hours-error">An error occurred while saving. Please try again.</div>';
}
