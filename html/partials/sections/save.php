<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();
requireManager();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="save-section-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="save-section-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
if (!$restaurantId) {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="save-section-no-restaurant">No restaurant selected.</div>';
    exit;
}

$sectionId = (int)($_POST['section_id'] ?? 0);
$isEdit = $sectionId > 0;

$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$displayOrder = (int)($_POST['display_order'] ?? 0);
$openTime = trim($_POST['open_time'] ?? '') ?: null;
$closeTime = trim($_POST['close_time'] ?? '') ?: null;
$isActive = isset($_POST['is_active']) ? 1 : 0;

if ($name === '') {
    echo '<div class="alert alert-danger" id="save-section-name-error">Section name is required.</div>';
    exit;
}

// Validate hours: if one is set, both must be set
if (($openTime && !$closeTime) || (!$openTime && $closeTime)) {
    echo '<div class="alert alert-danger" id="save-section-hours-error">Both open and close times must be set, or leave both blank.</div>';
    exit;
}

if ($openTime && $closeTime && $openTime >= $closeTime) {
    echo '<div class="alert alert-danger" id="save-section-hours-order-error">Open time must be before close time.</div>';
    exit;
}

$pdo = db();

// Check unique name within restaurant (excluding current record on edit)
$uniqueQuery = "SELECT id FROM sections WHERE restaurant_id = ? AND name = ?";
$uniqueParams = [$restaurantId, $name];
if ($isEdit) {
    $uniqueQuery .= " AND id != ?";
    $uniqueParams[] = $sectionId;
}
$stmt = $pdo->prepare($uniqueQuery);
$stmt->execute($uniqueParams);
if ($stmt->fetch()) {
    echo '<div class="alert alert-danger" id="save-section-dup-error">A section with this name already exists.</div>';
    exit;
}

try {
    if ($isEdit) {
        // Verify section belongs to this restaurant
        $stmt = $pdo->prepare("SELECT id FROM sections WHERE id = ? AND restaurant_id = ?");
        $stmt->execute([$sectionId, $restaurantId]);
        if (!$stmt->fetch()) {
            echo '<div class="alert alert-danger" id="save-section-notfound">Section not found.</div>';
            exit;
        }

        $stmt = $pdo->prepare(
            "UPDATE sections SET name = ?, description = ?, display_order = ?, open_time = ?, close_time = ?, is_active = ?
             WHERE id = ? AND restaurant_id = ?"
        );
        $stmt->execute([$name, $description ?: null, $displayOrder, $openTime, $closeTime, $isActive, $sectionId, $restaurantId]);
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO sections (restaurant_id, name, description, display_order, open_time, close_time, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$restaurantId, $name, $description ?: null, $displayOrder, $openTime, $closeTime, $isActive]);
    }

    header('HX-Trigger: refreshSectionList');

    echo '<div class="alert alert-success alert-dismissible fade show" id="save-section-success">
            <i class="feather-check-circle me-1"></i> Section ' . ($isEdit ? 'updated' : 'added') . ' successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    echo '<script>document.getElementById("sections-modal-container").innerHTML="";</script>';

} catch (Exception $e) {
    echo '<div class="alert alert-danger" id="save-section-error">An error occurred while saving. Please try again.</div>';
}
