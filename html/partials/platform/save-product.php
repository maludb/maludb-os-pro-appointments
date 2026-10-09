<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireSuperAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="platform-save-product-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="platform-save-product-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$isEdit = $id > 0;

$name = trim($_POST['name'] ?? '');
$slug = trim($_POST['slug'] ?? '');
$description = trim($_POST['description'] ?? '');
$price = (float)($_POST['price'] ?? 0);
$affiliatePrice = (float)($_POST['affiliate_price'] ?? 0);
$billingInterval = $_POST['billing_interval'] ?? 'monthly';
$sortOrder = (int)($_POST['sort_order'] ?? 0);
$isActive = isset($_POST['is_active']) ? 1 : 0;

if ($name === '' || $slug === '') {
    echo '<div class="alert alert-danger" id="platform-save-product-required-error">Name and slug are required.</div>';
    exit;
}

if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
    echo '<div class="alert alert-danger" id="platform-save-product-slug-error">Slug must contain only lowercase letters, numbers, and hyphens.</div>';
    exit;
}

if (!in_array($billingInterval, ['monthly', 'yearly', 'one_time'])) {
    echo '<div class="alert alert-danger" id="platform-save-product-billing-error">Invalid billing interval.</div>';
    exit;
}

$pdo = db();

// Check slug uniqueness
$slugCheck = $pdo->prepare("SELECT id FROM products WHERE slug = ? AND id != ?");
$slugCheck->execute([$slug, $id]);
if ($slugCheck->fetch()) {
    echo '<div class="alert alert-danger" id="platform-save-product-slug-unique-error">This slug is already in use.</div>';
    exit;
}

if ($isEdit) {
    $check = $pdo->prepare("SELECT id FROM products WHERE id = ?");
    $check->execute([$id]);
    if (!$check->fetch()) {
        echo '<div class="alert alert-danger" id="platform-save-product-notfound-error">Product not found.</div>';
        exit;
    }

    $stmt = $pdo->prepare(
        "UPDATE products SET name = ?, slug = ?, description = ?, price = ?, affiliate_price = ?,
                billing_interval = ?, sort_order = ?, is_active = ?
         WHERE id = ?"
    );
    $stmt->execute([$name, $slug, $description ?: null, $price, $affiliatePrice, $billingInterval, $sortOrder, $isActive, $id]);
} else {
    $stmt = $pdo->prepare(
        "INSERT INTO products (name, slug, description, price, affiliate_price, billing_interval, sort_order, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$name, $slug, $description ?: null, $price, $affiliatePrice, $billingInterval, $sortOrder, $isActive]);
}

header('HX-Trigger: closeModal');
include __DIR__ . '/products.php';
