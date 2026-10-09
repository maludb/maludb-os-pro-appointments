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
$status = trim($_POST['status'] ?? '');

if (!in_array($status, ['pending', 'in_progress', 'completed'])) {
    echo '<div class="alert alert-danger">Invalid status.</div>';
    exit;
}

$pdo = db();

// Verify ownership
$check = $pdo->prepare("SELECT id FROM todos WHERE id = ? AND restaurant_id = ? AND user_id = ?");
$check->execute([$id, $restaurantId, $userId]);
if (!$check->fetch()) {
    echo '<div class="alert alert-danger">Todo not found.</div>';
    exit;
}

$completedAt = $status === 'completed' ? date('Y-m-d H:i:s') : null;

$stmt = $pdo->prepare("UPDATE todos SET status = ?, completed_at = ?, updated_at = NOW() WHERE id = ?");
$stmt->execute([$status, $completedAt, $id]);

// Return the updated list
require __DIR__ . '/list.php';
