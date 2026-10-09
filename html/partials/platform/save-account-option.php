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

$restaurantId = (int)($_POST['restaurant_id'] ?? 0);
$optionKey = trim($_POST['option_key'] ?? '');
$optionValue = trim($_POST['option_value'] ?? '1');
$enabled = isset($_POST['enabled']) ? 1 : 0;
$notes = trim($_POST['notes'] ?? '');

if ($restaurantId <= 0 || $optionKey === '') {
    echo '<div class="alert alert-danger">Restaurant and option key are required.</div>';
    exit;
}

$pdo = db();

if ($isEdit) {
    $stmt = $pdo->prepare(
        "UPDATE account_options SET option_value = ?, enabled = ?, notes = ? WHERE id = ?"
    );
    $stmt->execute([$optionValue, $enabled, $notes ?: null, $id]);
} else {
    // Use INSERT ... ON CONFLICT for the unique constraint
    $stmt = $pdo->prepare(
        "INSERT INTO account_options (restaurant_id, option_key, option_value, enabled, notes)
         VALUES (?, ?, ?, ?, ?)
         ON CONFLICT (restaurant_id, option_key) DO UPDATE SET option_value = EXCLUDED.option_value, enabled = EXCLUDED.enabled, notes = EXCLUDED.notes"
    );
    $stmt->execute([$restaurantId, $optionKey, $optionValue, $enabled, $notes ?: null]);
}

header('HX-Trigger: closeModal');
include __DIR__ . '/account-options.php';
