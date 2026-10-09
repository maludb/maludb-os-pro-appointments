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

$commissionId = (int)($_POST['commission_id'] ?? 0);
$status = $_POST['status'] ?? '';
$affiliateId = (int)($_POST['affiliate_id'] ?? 0);

if ($commissionId <= 0 || $affiliateId <= 0) {
    echo '<div class="alert alert-danger">Invalid parameters.</div>';
    exit;
}

if (!in_array($status, ['pending', 'approved', 'paid', 'rejected'])) {
    echo '<div class="alert alert-danger">Invalid status.</div>';
    exit;
}

$pdo = db();

$paidAt = ($status === 'paid') ? date('Y-m-d H:i:s') : null;

$stmt = $pdo->prepare("UPDATE affiliate_commissions SET status = ?, paid_at = COALESCE(?, paid_at) WHERE id = ? AND affiliate_id = ?");
$stmt->execute([$status, $paidAt, $commissionId, $affiliateId]);

$_GET['id'] = $affiliateId;
include __DIR__ . '/affiliate-detail.php';
