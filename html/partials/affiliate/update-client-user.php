<?php
/**
 * Update Client User — save handler for affiliates editing a user in their prospect's company.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../helpers/validation.php';
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

$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$role = trim($_POST['role'] ?? '');

if ($firstName === '' || $lastName === '') {
    echo '<div class="alert alert-danger">First name and last name are required.</div>';
    exit;
}

if (!in_array($role, ['admin', 'manager', 'user'])) {
    echo '<div class="alert alert-danger">Please select a valid role.</div>';
    exit;
}

try {
    $pdo->beginTransaction();

    // Update user info
    $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ? WHERE id = ?");
    $stmt->execute([$firstName, $lastName, $phone ?: null, (int)$ur['user_id']]);

    // Update role
    $stmt = $pdo->prepare("UPDATE user_restaurants SET role = ? WHERE id = ?");
    $stmt->execute([$role, $urId]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    echo '<div class="alert alert-danger">An error occurred. Please try again.</div>';
    exit;
}

// Reload the users page
include __DIR__ . '/users.php';
