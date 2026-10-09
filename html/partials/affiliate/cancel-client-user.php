<?php
/**
 * Cancel Client User — remove a user from a prospect's company (deactivate user_restaurants row).
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
    echo '<div class="alert alert-danger">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$pdo = db();
$userId = currentUserId();
$urId = (int)($_POST['ur_id'] ?? 0);

// Get affiliate id for current context (user or restaurant)
$affiliateId = currentAffiliateId();

if (!$affiliateId || $urId <= 0) {
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit;
}

// Verify affiliate owns this user_restaurants row via prospects
$stmt = $pdo->prepare(
    "SELECT ur.user_id, ur.restaurant_id
     FROM user_restaurants ur
     JOIN restaurants r ON r.id = ur.restaurant_id
     JOIN prospects p ON p.restaurant_id = r.id AND p.affiliate_id = ?
     WHERE ur.id = ?"
);
$stmt->execute([$affiliateId, $urId]);
$ur = $stmt->fetch();

if (!$ur) {
    echo '<div class="alert alert-danger">User not found or access denied.</div>';
    exit;
}

try {
    // Deactivate the user_restaurants link
    $stmt = $pdo->prepare("UPDATE user_restaurants SET is_active = 0 WHERE id = ?");
    $stmt->execute([$urId]);

    // If this user was the billing_user, clear it
    $stmt = $pdo->prepare("UPDATE restaurants SET billing_user = 0 WHERE id = ? AND billing_user = ?");
    $stmt->execute([(int)$ur['restaurant_id'], (int)$ur['user_id']]);
} catch (Exception $e) {
    echo '<div class="alert alert-danger">An error occurred. Please try again.</div>';
    exit;
}

// Reload the users page
include __DIR__ . '/users.php';
