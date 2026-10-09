<?php
/**
 * Update Prospect Status — quick status change without full edit
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/prospect-restaurant.php';

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
$newStatus = $_POST['status'] ?? '';

$validStatuses = ['new', 'contacted', 'qualified', 'in_setup', 'client', 'lost'];
if (!in_array($newStatus, $validStatuses)) {
    echo '<div class="alert alert-danger">Invalid status.</div>';
    exit;
}

// Verify ownership and get current status
$check = $pdo->prepare("SELECT id, status FROM prospects WHERE id = ? AND affiliate_id = ?");
$check->execute([$prospectId, $affiliateId]);
$prospect = $check->fetch();

if (!$prospect) {
    echo '<div class="alert alert-danger">Prospect not found.</div>';
    exit;
}

$oldStatus = $prospect['status'];

// Update status
$stmt = $pdo->prepare("UPDATE prospects SET status = ?, updated_at = NOW() WHERE id = ? AND affiliate_id = ?");
$stmt->execute([$newStatus, $prospectId, $affiliateId]);

// Log status change activity
$statusLabels = [
    'new' => 'New', 'contacted' => 'Contacted', 'qualified' => 'Qualified',
    'in_setup' => 'In-Setup', 'client' => 'Client', 'lost' => 'Lost',
];
$oldLabel = $statusLabels[$oldStatus] ?? ucfirst($oldStatus);
$newLabel = $statusLabels[$newStatus] ?? ucfirst($newStatus);

$actStmt = $pdo->prepare(
    "INSERT INTO prospect_activities (prospect_id, affiliate_id, activity_type, subject, body, created_at)
     VALUES (?, ?, 'status_change', ?, NULL, NOW())"
);
$actStmt->execute([$prospectId, $affiliateId, "Status changed from {$oldLabel} to {$newLabel}"]);

// Auto-create restaurant when moving to in_setup or client
if (in_array($newStatus, ['in_setup', 'client'])) {
    // Re-fetch full prospect record for the helper
    $pStmt = $pdo->prepare("SELECT * FROM prospects WHERE id = ?");
    $pStmt->execute([$prospectId]);
    $fullProspect = $pStmt->fetch();
    if ($fullProspect && empty($fullProspect['restaurant_id'])) {
        $newRestId = createRestaurantFromProspect($fullProspect, $userId);
        if ($newRestId) {
            $pdo->prepare(
                "INSERT INTO prospect_activities (prospect_id, affiliate_id, activity_type, subject, body, created_at)
                 VALUES (?, ?, 'status_change', 'Restaurant Auto-Created', ?, NOW())"
            )->execute([
                $prospectId, $affiliateId,
                'Restaurant "' . $fullProspect['business_name'] . '" (ID: ' . $newRestId . ') created and linked to your account.'
            ]);
        }
    }
}

// Sync restaurant status when prospect moves to in_setup or client
$prospectToRestaurantStatus = [
    'in_setup' => 'in-setup',
    'client' => 'active',
];
if (isset($prospectToRestaurantStatus[$newStatus])) {
    $pStmt = $pdo->prepare("SELECT restaurant_id FROM prospects WHERE id = ?");
    $pStmt->execute([$prospectId]);
    $pRow = $pStmt->fetch();
    if ($pRow && !empty($pRow['restaurant_id'])) {
        $pdo->prepare("UPDATE restaurants SET status = ? WHERE id = ?")->execute([
            $prospectToRestaurantStatus[$newStatus],
            $pRow['restaurant_id']
        ]);
    }
}

// If a restaurant was just created, full page reload so the topnav location switcher updates
if (in_array($newStatus, ['in_setup', 'client']) && isset($newRestId) && $newRestId) {
    header('HX-Redirect: /app.php');
    exit;
}

// Reload the prospect detail
$_GET['id'] = $prospectId;
include __DIR__ . '/prospect-detail.php';
