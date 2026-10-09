<?php
/**
 * Set Billing User — mark a user as responsible for invoices/payments for a restaurant.
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
$restaurantId = (int)($_POST['restaurant_id'] ?? 0);
$targetUserId = (int)($_POST['user_id'] ?? 0);

// Get affiliate id for current context (user or restaurant)
$affiliateId = currentAffiliateId();

if (!$affiliateId || $restaurantId <= 0 || $targetUserId <= 0) {
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit;
}

// Verify affiliate owns this restaurant via prospects
$stmt = $pdo->prepare(
    "SELECT p.id FROM prospects p
     JOIN restaurants r ON r.id = p.restaurant_id
     WHERE p.affiliate_id = ? AND r.id = ?"
);
$stmt->execute([$affiliateId, $restaurantId]);
if (!$stmt->fetch()) {
    echo '<div class="alert alert-danger">Access denied.</div>';
    exit;
}

// Verify user is linked to this restaurant and active
$stmt = $pdo->prepare(
    "SELECT id FROM user_restaurants WHERE user_id = ? AND restaurant_id = ? AND is_active = 1"
);
$stmt->execute([$targetUserId, $restaurantId]);
if (!$stmt->fetch()) {
    echo '<div class="alert alert-danger">User is not an active member of this company.</div>';
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE restaurants SET billing_user = ? WHERE id = ?");
    $stmt->execute([$targetUserId, $restaurantId]);
} catch (Exception $e) {
    echo '<div class="alert alert-danger">An error occurred. Please try again.</div>';
    exit;
}

// Reload the users page
include __DIR__ . '/users.php';
