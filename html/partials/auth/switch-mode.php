<?php
/**
 * Switch Product Mode — updates users.product_type and triggers full page reload
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$mode = $_POST['mode'] ?? '';
$validModes = ['restaurant', 'professional', 'affiliate'];

if (!in_array($mode, $validModes)) {
    http_response_code(400);
    echo 'Invalid mode.';
    exit;
}

$userId = currentUserId();
$pdo = db();

// Update product_type in the database
$stmt = $pdo->prepare("UPDATE users SET product_type = ? WHERE id = ?");
$stmt->execute([$mode, $userId]);

// Trigger full page reload via HX-Redirect
header('HX-Redirect: /app.php');
exit;
