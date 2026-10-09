<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();
requireManager();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="professional-save-availability-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="professional-save-availability-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();

if (!$restaurantId) {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="professional-save-availability-no-restaurant">No professional account is currently selected.</div>';
    exit;
}

$action = trim($_POST['action'] ?? '');
$availabilityId = (int)($_POST['availability_id'] ?? 0);
$pdo = db();

if ($action === 'delete' && $availabilityId > 0) {
    $stmt = $pdo->prepare("SELECT id FROM professional_availability_rules WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$availabilityId, $restaurantId]);
    if (!$stmt->fetch()) {
        echo '<div class="alert alert-danger" id="professional-save-availability-delete-not-found">Availability window not found.</div>';
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM professional_availability_rules WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$availabilityId, $restaurantId]);

    header('HX-Trigger: refreshProfessionalAvailabilityList');
    echo '<div class="alert alert-success alert-dismissible fade show" id="professional-save-availability-delete-success">
            <i class="feather-check-circle me-1"></i> Availability window deleted.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    exit;
}

if ($action !== 'save') {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="professional-save-availability-action-error">Invalid action.</div>';
    exit;
}

$isEdit = $availabilityId > 0;
$weekday = (int)($_POST['weekday'] ?? -1);
$startTime = trim($_POST['start_time'] ?? '');
$endTime = trim($_POST['end_time'] ?? '');
$locationType = trim($_POST['location_type'] ?? '');
$locationLabel = trim($_POST['location_label'] ?? '');
$isActive = isset($_POST['is_active']) ? 1 : 0;

if ($weekday < 0 || $weekday > 6) {
    echo '<div class="alert alert-danger" id="professional-save-availability-weekday-error">Please choose a valid day of the week.</div>';
    exit;
}

if ($startTime === '' || $endTime === '') {
    echo '<div class="alert alert-danger" id="professional-save-availability-time-required">Start and end time are required.</div>';
    exit;
}

if ($startTime >= $endTime) {
    echo '<div class="alert alert-danger" id="professional-save-availability-time-order">Start time must be before end time.</div>';
    exit;
}

$allowedLocationTypes = ['', 'in_person', 'phone', 'video', 'onsite', 'custom'];
if (!in_array($locationType, $allowedLocationTypes, true)) {
    echo '<div class="alert alert-danger" id="professional-save-availability-location-type-error">Please choose a valid location type.</div>';
    exit;
}

if ($isActive === 1) {
    $overlapQuery = "
        SELECT id
        FROM professional_availability_rules
        WHERE restaurant_id = ?
          AND weekday = ?
          AND is_active = 1
          AND NOT (end_time <= ? OR start_time >= ?)
    ";
    $overlapParams = [$restaurantId, $weekday, $startTime, $endTime];

    if ($isEdit) {
        $overlapQuery .= " AND id != ?";
        $overlapParams[] = $availabilityId;
    }

    $overlapStmt = $pdo->prepare($overlapQuery);
    $overlapStmt->execute($overlapParams);
    if ($overlapStmt->fetch()) {
        echo '<div class="alert alert-danger" id="professional-save-availability-overlap-error">This time window overlaps another active availability window on the same day.</div>';
        exit;
    }
}

if ($isEdit) {
    $checkStmt = $pdo->prepare("SELECT id FROM professional_availability_rules WHERE id = ? AND restaurant_id = ?");
    $checkStmt->execute([$availabilityId, $restaurantId]);
    if (!$checkStmt->fetch()) {
        echo '<div class="alert alert-danger" id="professional-save-availability-not-found">Availability window not found.</div>';
        exit;
    }

    $stmt = $pdo->prepare(
        "UPDATE professional_availability_rules SET
            weekday = ?,
            start_time = ?,
            end_time = ?,
            location_type = ?,
            location_label = ?,
            is_active = ?,
            updated_at = NOW()
         WHERE id = ? AND restaurant_id = ?"
    );

    $stmt->execute([
        $weekday,
        $startTime,
        $endTime,
        $locationType ?: null,
        $locationLabel ?: null,
        $isActive,
        $availabilityId,
        $restaurantId,
    ]);
} else {
    $stmt = $pdo->prepare(
        "INSERT INTO professional_availability_rules (
            restaurant_id,
            weekday,
            start_time,
            end_time,
            location_type,
            location_label,
            is_active,
            created_at,
            updated_at
         ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
    );

    $stmt->execute([
        $restaurantId,
        $weekday,
        $startTime,
        $endTime,
        $locationType ?: null,
        $locationLabel ?: null,
        $isActive,
    ]);
}

header('HX-Trigger-After-Swap: {"refreshProfessionalAvailabilityList":true,"closeModal":true}');

echo '<div class="alert alert-success alert-dismissible fade show" id="professional-save-availability-success">
        <i class="feather-check-circle me-1"></i> Availability window ' . ($isEdit ? 'updated' : 'created') . ' successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>';
