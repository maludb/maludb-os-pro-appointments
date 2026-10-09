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

$optionId = (int)($_POST['option_id'] ?? 0);
if ($optionId <= 0) {
    echo '<div class="alert alert-danger">Invalid option.</div>';
    exit;
}

$pdo = db();
$stmt = $pdo->prepare("DELETE FROM account_options WHERE id = ?");
$stmt->execute([$optionId]);

include __DIR__ . '/account-options.php';
