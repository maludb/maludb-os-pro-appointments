<?php
/**
 * Become an Affiliate — creates an affiliate record for the current user
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';

requireAuth();

$pdo = db();
$userId = currentUserId();

// Check if already an affiliate
$stmt = $pdo->prepare("SELECT id FROM affiliates WHERE user_id = ?");
$stmt->execute([$userId]);
if ($stmt->fetch()) {
    // Already an affiliate, just reload dashboard
    include __DIR__ . '/../dashboard/index.php';
    exit;
}

// Generate unique affiliate code
$code = strtoupper(bin2hex(random_bytes(4)));
$codeCheck = $pdo->prepare("SELECT id FROM affiliates WHERE affiliate_code = ?");
$codeCheck->execute([$code]);
while ($codeCheck->fetch()) {
    $code = strtoupper(bin2hex(random_bytes(4)));
    $codeCheck->execute([$code]);
}

// Get user info for defaults
$userStmt = $pdo->prepare("SELECT first_name, last_name, email, company_name FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$user = $userStmt->fetch();

// Create affiliate record
$stmt = $pdo->prepare(
    "INSERT INTO affiliates (user_id, affiliate_code, commission_rate, status, payout_email, payout_method, company_name, contact_name, contact_email)
     VALUES (?, ?, 10.00, 'active', ?, 'paypal', ?, ?, ?)"
);
$stmt->execute([
    $userId,
    $code,
    $user['email'],
    $user['company_name'] ?? null,
    $user['first_name'] . ' ' . $user['last_name'],
    $user['email']
]);

// Update user role to affiliate if currently 'user'
$pdo->prepare("UPDATE users SET is_affiliate = 1 WHERE id = ? AND is_affiliate = 0")->execute([$userId]);

// Update session
$_SESSION['is_affiliate'] = true;
$affId = (int)$pdo->lastInsertId();
$_SESSION['affiliate_id'] = $affId;

// Redirect to affiliate settings so they can fill in their details
include __DIR__ . '/settings.php';
