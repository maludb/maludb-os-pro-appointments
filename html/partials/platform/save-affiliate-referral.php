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

$affiliateId = (int)($_POST['affiliate_id'] ?? 0);
$restaurantId = (int)($_POST['restaurant_id'] ?? 0);
$status = $_POST['status'] ?? 'pending';

if ($affiliateId <= 0 || $restaurantId <= 0) {
    echo '<div class="alert alert-danger">Affiliate and restaurant are required.</div>';
    exit;
}

if (!in_array($status, ['pending', 'active', 'cancelled'])) {
    echo '<div class="alert alert-danger">Invalid status.</div>';
    exit;
}

$pdo = db();

$activatedAt = ($status === 'active') ? date('Y-m-d H:i:s') : null;

$stmt = $pdo->prepare(
    "INSERT INTO affiliate_referrals (affiliate_id, restaurant_id, status, activated_at)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE status = VALUES(status), activated_at = VALUES(activated_at)"
);
$stmt->execute([$affiliateId, $restaurantId, $status, $activatedAt]);

header('HX-Trigger: closeModal');
$_GET['id'] = $affiliateId;
include __DIR__ . '/affiliate-detail.php';
