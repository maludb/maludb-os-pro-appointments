<?php
/**
 * Save Prospect Activity — add a note, call, email, or meeting log
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

$prospectId = (int)($_POST['prospect_id'] ?? 0);
$activityType = $_POST['activity_type'] ?? 'note';
$subject = trim($_POST['subject'] ?? '');
$body = trim($_POST['body'] ?? '');

// Validate prospect ownership
$check = $pdo->prepare("SELECT id FROM prospects WHERE id = ? AND affiliate_id = ?");
$check->execute([$prospectId, $affiliateId]);
if (!$check->fetch()) {
    echo '<div class="alert alert-danger">Prospect not found.</div>';
    exit;
}

$validTypes = ['note', 'call', 'email', 'meeting', 'status_change'];
if (!in_array($activityType, $validTypes)) $activityType = 'note';

if ($body === '' && $subject === '') {
    echo '<div class="alert alert-warning">Please enter a subject or details.</div>';
    exit;
}

$stmt = $pdo->prepare(
    "INSERT INTO prospect_activities (prospect_id, affiliate_id, activity_type, subject, body, created_at)
     VALUES (?, ?, ?, ?, ?, NOW())"
);
$stmt->execute([$prospectId, $affiliateId, $activityType, $subject ?: null, $body ?: null]);

// Update prospect's updated_at timestamp
$pdo->prepare("UPDATE prospects SET updated_at = NOW() WHERE id = ?")->execute([$prospectId]);

// Reload the dashboard
$_GET['id'] = $prospectId;
include __DIR__ . '/prospect-detail.php';
