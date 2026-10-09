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
$referralId = (int)($_POST['referral_id'] ?? 0);
$amount = (float)($_POST['amount'] ?? 0);
$status = $_POST['status'] ?? 'pending';
$periodStart = trim($_POST['period_start'] ?? '');
$periodEnd = trim($_POST['period_end'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if ($affiliateId <= 0 || $referralId <= 0 || $amount <= 0) {
    echo '<div class="alert alert-danger">Affiliate, referral, and amount are required.</div>';
    exit;
}

if (!in_array($status, ['pending', 'approved', 'paid', 'rejected'])) {
    echo '<div class="alert alert-danger">Invalid status.</div>';
    exit;
}

$pdo = db();

// Verify referral belongs to affiliate
$check = $pdo->prepare("SELECT id FROM affiliate_referrals WHERE id = ? AND affiliate_id = ?");
$check->execute([$referralId, $affiliateId]);
if (!$check->fetch()) {
    echo '<div class="alert alert-danger">Invalid referral for this affiliate.</div>';
    exit;
}

// Find active subscription for this referral's restaurant
$subCheck = $pdo->prepare(
    "SELECT s.id FROM subscriptions s
     JOIN affiliate_referrals ar ON ar.restaurant_id = s.restaurant_id
     WHERE ar.id = ? AND s.status IN ('active','trialing')
     LIMIT 1"
);
$subCheck->execute([$referralId]);
$sub = $subCheck->fetch();
$subscriptionId = $sub ? $sub['id'] : null;

$stmt = $pdo->prepare(
    "INSERT INTO affiliate_commissions (affiliate_id, referral_id, subscription_id, amount, status, period_start, period_end, notes)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);
$stmt->execute([
    $affiliateId, $referralId, $subscriptionId, $amount, $status,
    $periodStart ?: null, $periodEnd ?: null, $notes ?: null
]);

header('HX-Trigger: closeModal');
$_GET['id'] = $affiliateId;
include __DIR__ . '/affiliate-detail.php';
