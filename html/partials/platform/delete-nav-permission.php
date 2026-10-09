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

$permissionId = (int)($_POST['permission_id'] ?? 0);
if ($permissionId <= 0) {
    echo '<div class="alert alert-danger">Invalid permission.</div>';
    exit;
}

$pdo = db();
$stmt = $pdo->prepare("DELETE FROM nav_permissions WHERE id = ?");
$stmt->execute([$permissionId]);

include __DIR__ . '/nav-permissions.php';
