<?php
/**
 * Delete an email agent prompt.
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
    echo '<div class="alert alert-danger">Invalid email agent ID.</div>';
    exit;
}

// Delete details first, then the prompt
$pdo->prepare("DELETE FROM email_agent_prompt_details WHERE email_agent_prompt_id = ?")->execute([$id]);

$stmt = $pdo->prepare("DELETE FROM email_agent_prompts WHERE id = ? AND restaurant_id = ?");
$stmt->execute([$id, $restaurantId]);

if ($stmt->rowCount() > 0) {
    echo '<div class="alert alert-success">Email agent deleted.</div>';
} else {
    echo '<div class="alert alert-warning">Email agent not found.</div>';
}
