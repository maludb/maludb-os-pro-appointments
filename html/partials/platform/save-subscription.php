<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireSuperAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Invalid security token.</div>';
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$isEdit = $id > 0;

$restaurantId = (int)($_POST['restaurant_id'] ?? 0);
$productId = (int)($_POST['product_id'] ?? 0);
$status = $_POST['status'] ?? 'active';
$startedAt = trim($_POST['started_at'] ?? '');
$expiresAt = trim($_POST['expires_at'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if ($restaurantId <= 0 || $productId <= 0 || $startedAt === '') {
    echo '<div class="alert alert-danger">Restaurant, product, and start date are required.</div>';
    exit;
}

if (!in_array($status, ['active', 'trialing', 'past_due', 'cancelled'])) {
    echo '<div class="alert alert-danger">Invalid status.</div>';
    exit;
}

$pdo = db();

// Verify restaurant and product exist
$check = $pdo->prepare("SELECT id FROM restaurants WHERE id = ?");
$check->execute([$restaurantId]);
if (!$check->fetch()) {
    echo '<div class="alert alert-danger">Restaurant not found.</div>';
    exit;
}

$check = $pdo->prepare("SELECT id FROM products WHERE id = ?");
$check->execute([$productId]);
if (!$check->fetch()) {
    echo '<div class="alert alert-danger">Product not found.</div>';
    exit;
}

$cancelledAt = null;
if ($status === 'cancelled') {
    $cancelledAt = date('Y-m-d H:i:s');
}

if ($isEdit) {
    $stmt = $pdo->prepare(
        "UPDATE subscriptions SET product_id = ?, status = ?, started_at = ?, expires_at = ?,
                cancelled_at = ?, notes = ?
         WHERE id = ?"
    );
    $stmt->execute([$productId, $status, $startedAt, $expiresAt ?: null, $cancelledAt, $notes ?: null, $id]);
} else {
    $stmt = $pdo->prepare(
        "INSERT INTO subscriptions (restaurant_id, product_id, status, started_at, expires_at, cancelled_at, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$restaurantId, $productId, $status, $startedAt, $expiresAt ?: null, $cancelledAt, $notes ?: null]);
}

header('HX-Trigger: closeModal');
include __DIR__ . '/subscriptions.php';
