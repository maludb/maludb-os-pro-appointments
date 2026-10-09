<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();
requireManager();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="professional-save-service-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="professional-save-service-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();

if (!$restaurantId) {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="professional-save-service-no-restaurant">No professional account is currently selected.</div>';
    exit;
}

$serviceId = (int)($_POST['service_id'] ?? 0);
$isEdit = $serviceId > 0;

$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$durationMinutes = (int)($_POST['duration_minutes'] ?? 0);
$bufferBeforeMinutes = (int)($_POST['buffer_before_minutes'] ?? 0);
$bufferAfterMinutes = (int)($_POST['buffer_after_minutes'] ?? 0);
$priceInput = trim($_POST['price'] ?? '');
$locationType = trim($_POST['location_type'] ?? '');
$locationLabel = trim($_POST['location_label'] ?? '');
$color = trim($_POST['color'] ?? '');
$sortOrder = (int)($_POST['sort_order'] ?? 0);
$isPublicBookable = isset($_POST['is_public_bookable']) ? 1 : 0;
$isActive = isset($_POST['is_active']) ? 1 : 0;

if ($name === '') {
    echo '<div class="alert alert-danger" id="professional-save-service-name-error">Service name is required.</div>';
    exit;
}

if ($durationMinutes < 5) {
    echo '<div class="alert alert-danger" id="professional-save-service-duration-error">Duration must be at least 5 minutes.</div>';
    exit;
}

if ($bufferBeforeMinutes < 0 || $bufferAfterMinutes < 0) {
    echo '<div class="alert alert-danger" id="professional-save-service-buffer-error">Buffers cannot be negative.</div>';
    exit;
}

if ($sortOrder < 0) {
    echo '<div class="alert alert-danger" id="professional-save-service-sort-order-error">Sort order cannot be negative.</div>';
    exit;
}

$allowedLocationTypes = ['', 'in_person', 'phone', 'video', 'onsite', 'custom'];
if (!in_array($locationType, $allowedLocationTypes, true)) {
    echo '<div class="alert alert-danger" id="professional-save-service-location-type-error">Please choose a valid location type.</div>';
    exit;
}

if ($color === '' || !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
    echo '<div class="alert alert-danger" id="professional-save-service-color-error">Please choose a valid service color.</div>';
    exit;
}

$price = null;
if ($priceInput !== '') {
    if (!is_numeric($priceInput) || (float)$priceInput < 0) {
        echo '<div class="alert alert-danger" id="professional-save-service-price-error">Price must be a valid non-negative amount.</div>';
        exit;
    }
    $price = number_format((float)$priceInput, 2, '.', '');
}

$pdo = db();

$duplicateQuery = "SELECT id FROM professional_services WHERE restaurant_id = ? AND name = ?";
$duplicateParams = [$restaurantId, $name];
if ($isEdit) {
    $duplicateQuery .= " AND id != ?";
    $duplicateParams[] = $serviceId;
}
$duplicateStmt = $pdo->prepare($duplicateQuery);
$duplicateStmt->execute($duplicateParams);
if ($duplicateStmt->fetch()) {
    echo '<div class="alert alert-danger" id="professional-save-service-duplicate-error">A service with this name already exists.</div>';
    exit;
}

if ($isEdit) {
    $checkStmt = $pdo->prepare("SELECT id FROM professional_services WHERE id = ? AND restaurant_id = ?");
    $checkStmt->execute([$serviceId, $restaurantId]);
    if (!$checkStmt->fetch()) {
        echo '<div class="alert alert-danger" id="professional-save-service-not-found">Service not found.</div>';
        exit;
    }

    $stmt = $pdo->prepare(
        "UPDATE professional_services SET
            name = ?,
            description = ?,
            duration_minutes = ?,
            buffer_before_minutes = ?,
            buffer_after_minutes = ?,
            price = ?,
            currency_code = 'USD',
            location_type = ?,
            location_label = ?,
            color = ?,
            sort_order = ?,
            is_active = ?,
            is_public_bookable = ?,
            updated_at = NOW()
         WHERE id = ? AND restaurant_id = ?"
    );

    $stmt->execute([
        $name,
        $description ?: null,
        $durationMinutes,
        $bufferBeforeMinutes,
        $bufferAfterMinutes,
        $price,
        $locationType ?: null,
        $locationLabel ?: null,
        $color,
        $sortOrder,
        $isActive,
        $isPublicBookable,
        $serviceId,
        $restaurantId,
    ]);
} else {
    $stmt = $pdo->prepare(
        "INSERT INTO professional_services (
            restaurant_id,
            name,
            description,
            duration_minutes,
            buffer_before_minutes,
            buffer_after_minutes,
            price,
            currency_code,
            location_type,
            location_label,
            color,
            sort_order,
            is_active,
            is_public_bookable,
            created_at,
            updated_at
         ) VALUES (?, ?, ?, ?, ?, ?, ?, 'USD', ?, ?, ?, ?, ?, ?, NOW(), NOW())"
    );

    $stmt->execute([
        $restaurantId,
        $name,
        $description ?: null,
        $durationMinutes,
        $bufferBeforeMinutes,
        $bufferAfterMinutes,
        $price,
        $locationType ?: null,
        $locationLabel ?: null,
        $color,
        $sortOrder,
        $isActive,
        $isPublicBookable,
    ]);
}

header('HX-Trigger-After-Swap: {"refreshProfessionalServicesList":true,"closeModal":true}');

echo '<div class="alert alert-success alert-dismissible fade show" id="professional-save-service-success">
        <i class="feather-check-circle me-1"></i> Service ' . ($isEdit ? 'updated' : 'created') . ' successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>';
