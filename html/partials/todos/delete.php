<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('<div class="alert alert-danger">Invalid CSRF token.</div>');
}

$restaurantId = currentRestaurantId();
$userId = $_SESSION['user_id'] ?? 0;
$id = (int)($_POST['id'] ?? 0);

$pdo = db();

// Verify ownership then delete
$stmt = $pdo->prepare("DELETE FROM todos WHERE id = ? AND restaurant_id = ? AND user_id = ?");
$stmt->execute([$id, $restaurantId, $userId]);

// Return the updated list
require __DIR__ . '/list.php';
