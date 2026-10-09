<?php
/**
 * Delete a voice agent prompt.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();
if (!isAdmin() && !isSuperAdmin()) {
    http_response_code(403);
    exit('Forbidden');
}
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Invalid CSRF token.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
$pdo = db();
$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    echo '<div class="alert alert-danger">Invalid prompt ID.</div>';
    exit;
}

// Delete details first, then the prompt
$pdo->prepare("DELETE FROM restaurant_prompt_details WHERE restaurant_prompt_id = ?")->execute([$id]);

$stmt = $pdo->prepare("DELETE FROM restaurant_prompts WHERE id = ? AND restaurant_id = ?");
$stmt->execute([$id, $restaurantId]);

if ($stmt->rowCount() > 0) {
    echo '<div class="alert alert-success">Voice agent prompt deleted.</div>';
} else {
    echo '<div class="alert alert-warning">Prompt not found.</div>';
}
