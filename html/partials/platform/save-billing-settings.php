<?php
/**
 * Platform Admin — Save Billing Settings
 *
 * Saves billing configuration for a restaurant.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireSuperAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger m-4">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$pdo = db();

$restaurantId = (int)($_POST['restaurant_id'] ?? 0);
$invoiceDayOfMonth = (int)($_POST['invoice_day_of_month'] ?? 1);
$invoiceDescription = trim($_POST['invoice_description'] ?? '');
$monthlyPrice = (float)($_POST['monthly_price'] ?? 0);
$affiliateCommission = (float)($_POST['affiliate_commission'] ?? 0);

// Validate
if ($restaurantId <= 0) {
    echo '<div class="alert alert-danger m-4">Invalid restaurant.</div>';
    exit;
}

if ($invoiceDayOfMonth < 1 || $invoiceDayOfMonth > 28) {
    echo '<div class="alert alert-danger m-4">Invoice day must be between 1 and 28.</div>';
    exit;
}

if ($invoiceDescription === '') {
    echo '<div class="alert alert-danger m-4">Invoice description is required.</div>';
    exit;
}

if ($affiliateCommission < 0 || $affiliateCommission > 100) {
    echo '<div class="alert alert-danger m-4">Commission must be between 0 and 100.</div>';
    exit;
}

try {
    $stmt = $pdo->prepare(
        "UPDATE restaurants
         SET invoice_day_of_month = ?,
             invoice_description = ?,
             monthly_price = ?,
             affiliate_commission = ?
         WHERE id = ?"
    );
    $stmt->execute([$invoiceDayOfMonth, $invoiceDescription, $monthlyPrice, $affiliateCommission, $restaurantId]);
} catch (Exception $e) {
    echo '<div class="alert alert-danger m-4">An error occurred. Please try again.</div>';
    exit;
}

// Reload the billing settings list
include __DIR__ . '/billing-settings.php';
