<?php
/**
 * Save Prospect Product — add or update a product pitched to a prospect
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
$prospectId = (int)($_POST['prospect_id'] ?? 0);
$productId = (int)($_POST['product_id'] ?? 0);
$quotedPrice = (float)($_POST['quoted_price'] ?? 0);
$status = $_POST['status'] ?? 'pitched';
$notes = trim($_POST['notes'] ?? '');

$validStatuses = ['pitched', 'interested', 'accepted', 'declined', 'converted'];
if (!in_array($status, $validStatuses)) $status = 'pitched';

// Verify prospect ownership
$check = $pdo->prepare("SELECT id FROM prospects WHERE id = ? AND affiliate_id = ?");
$check->execute([$prospectId, $affiliateId]);
if (!$check->fetch()) {
    echo '<div class="alert alert-danger">Prospect not found.</div>';
    exit;
}

// Get product pricing
$prodStmt = $pdo->prepare("SELECT price, affiliate_price FROM products WHERE id = ? AND is_active = 1");
$prodStmt->execute([$productId]);
$product = $prodStmt->fetch();

if (!$product) {
    echo '<div class="alert alert-danger">Product not found.</div>';
    exit;
}

$recommendedPrice = (float)$product['price'];
$affiliatePrice = (float)$product['affiliate_price'];
if ($quotedPrice <= 0) $quotedPrice = $recommendedPrice;

if ($ppId > 0) {
    // Update existing
    $stmt = $pdo->prepare(
        "UPDATE prospect_products SET product_id = ?, recommended_price = ?, affiliate_price = ?,
                quoted_price = ?, status = ?, notes = ?, updated_at = NOW()
         WHERE id = ? AND affiliate_id = ?"
    );
    $stmt->execute([$productId, $recommendedPrice, $affiliatePrice, $quotedPrice, $status, $notes ?: null, $ppId, $affiliateId]);
} else {
    // Check for duplicate
    $dupCheck = $pdo->prepare("SELECT id FROM prospect_products WHERE prospect_id = ? AND product_id = ?");
    $dupCheck->execute([$prospectId, $productId]);
    if ($dupCheck->fetch()) {
        echo '<div class="alert alert-warning">This product is already added to the prospect.</div>';
        exit;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO prospect_products (prospect_id, affiliate_id, product_id, recommended_price, affiliate_price, quoted_price, status, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$prospectId, $affiliateId, $productId, $recommendedPrice, $affiliatePrice, $quotedPrice, $status, $notes ?: null]);
}

// Update prospect timestamp
$pdo->prepare("UPDATE prospects SET updated_at = NOW() WHERE id = ?")->execute([$prospectId]);

// Reload dashboard
$_GET['id'] = $prospectId;
include __DIR__ . '/prospect-detail.php';
