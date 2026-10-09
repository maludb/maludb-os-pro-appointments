<?php
/**
 * Delete Prospect Product
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../helpers/db.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Invalid security token.</div>';
    exit;
}

$pdo = db();
$userId = currentUserId();

// Get affiliate id for current context (user or restaurant)
$affiliateId = currentAffiliateId();

if (!$affiliateId) {
    echo '<div class="alert alert-danger">Affiliate account required.</div>';
    exit;
}

$ppId = (int)($_POST['pp_id'] ?? 0);

// Get the record and verify ownership
$stmt = $pdo->prepare("SELECT prospect_id FROM prospect_products WHERE id = ? AND affiliate_id = ?");
$stmt->execute([$ppId, $affiliateId]);
$record = $stmt->fetch();

if (!$record) {
    echo '<div class="alert alert-danger">Record not found.</div>';
    exit;
}

$prospectId = (int)$record['prospect_id'];

$pdo->prepare("DELETE FROM prospect_products WHERE id = ? AND affiliate_id = ?")->execute([$ppId, $affiliateId]);

// Reload dashboard
$_GET['id'] = $prospectId;
include __DIR__ . '/prospect-detail.php';
