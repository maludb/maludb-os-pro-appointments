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

$userId = (int)($_POST['user_id'] ?? 0);
if ($userId <= 0) {
    echo '<div class="alert alert-danger">Invalid user.</div>';
    exit;
}

// Prevent deactivating yourself
if ($userId === currentUserId()) {
    echo '<div class="alert alert-danger">You cannot deactivate your own account.</div>';
    exit;
}

$pdo = db();
$stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
$stmt->execute([$userId]);

include __DIR__ . '/users.php';
