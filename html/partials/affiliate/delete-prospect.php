<?php
/**
 * Delete Prospect
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
$id = (int)($_POST['id'] ?? 0);

// Get affiliate id for current context (user or restaurant)
$affiliateId = currentAffiliateId();

if (!$affiliateId || $id <= 0) {
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit;
}

$stmt = $pdo->prepare("DELETE FROM prospects WHERE id = ? AND affiliate_id = ?");
$stmt->execute([$id, $affiliateId]);

// Return to prospects list
$_GET['status'] = '';
include __DIR__ . '/prospects.php';
