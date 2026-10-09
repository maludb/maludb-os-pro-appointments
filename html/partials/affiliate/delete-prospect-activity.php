<?php
/**
 * Delete Prospect Activity
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

$activityId = (int)($_POST['activity_id'] ?? 0);

// Get the activity and verify ownership
$stmt = $pdo->prepare("SELECT prospect_id FROM prospect_activities WHERE id = ? AND affiliate_id = ?");
$stmt->execute([$activityId, $affiliateId]);
$activity = $stmt->fetch();

if (!$activity) {
    echo '<div class="alert alert-danger">Activity not found.</div>';
    exit;
}

$prospectId = (int)$activity['prospect_id'];

$pdo->prepare("DELETE FROM prospect_activities WHERE id = ? AND affiliate_id = ?")->execute([$activityId, $affiliateId]);

// Reload the dashboard
$_GET['id'] = $prospectId;
include __DIR__ . '/prospect-detail.php';
