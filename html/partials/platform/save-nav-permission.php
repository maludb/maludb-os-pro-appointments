<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireSuperAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Invalid security token.</div>';
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$isEdit = $id > 0;

$navItemId = trim($_POST['nav_item_id'] ?? '');
$label = trim($_POST['label'] ?? '') ?: null;
$userRole = trim($_POST['user_role'] ?? '') ?: null;
$restaurantRole = trim($_POST['restaurant_role'] ?? '') ?: null;
$locationType = trim($_POST['location_type'] ?? '') ?: null;
$isActive = isset($_POST['is_active']) ? 1 : 0;

if ($navItemId === '') {
    echo '<div class="alert alert-danger">Nav Item ID is required.</div>';
    exit;
}

$pdo = db();

if ($isEdit) {
    $stmt = $pdo->prepare(
        "UPDATE nav_permissions SET nav_item_id = ?, label = ?, user_role = ?, restaurant_role = ?, location_type = ?, is_active = ? WHERE id = ?"
    );
    $stmt->execute([$navItemId, $label, $userRole, $restaurantRole, $locationType, $isActive, $id]);
} else {
    $stmt = $pdo->prepare(
        "INSERT INTO nav_permissions (nav_item_id, label, user_role, restaurant_role, location_type, is_active)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$navItemId, $label, $userRole, $restaurantRole, $locationType, $isActive]);
}

header('HX-Trigger: closeModal');
include __DIR__ . '/nav-permissions.php';
