<?php
/**
 * Save Prospect — create or update
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

$id = (int)($_POST['id'] ?? 0);
$isEdit = $id > 0;

$businessName = trim($_POST['business_name'] ?? '');
$businessType = trim($_POST['business_type'] ?? '');
$address = trim($_POST['address'] ?? '');
$city = trim($_POST['city'] ?? '');
$state = strtoupper(trim($_POST['state'] ?? ''));
$zip = trim($_POST['zip'] ?? '');
$website = trim($_POST['website'] ?? '');
$contactName = trim($_POST['contact_name'] ?? '');
$contactPhone = trim($_POST['contact_phone'] ?? '');
$contactEmail = trim($_POST['contact_email'] ?? '');
$status = $_POST['status'] ?? 'new';
$productInterest = $_POST['product_interest'] ?? 'restaurant';
$source = trim($_POST['source'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if ($businessName === '') {
    echo '<div class="alert alert-danger">Business name is required.</div>';
    exit;
}

$validStatuses = ['new', 'contacted', 'qualified', 'in_setup', 'client', 'lost'];
if (!in_array($status, $validStatuses)) $status = 'new';

$validProducts = ['restaurant', 'professional', 'systems_integrator'];
if (!in_array($productInterest, $validProducts)) $productInterest = 'restaurant';

if ($isEdit) {
    // Verify ownership
    $check = $pdo->prepare("SELECT id FROM prospects WHERE id = ? AND affiliate_id = ?");
    $check->execute([$id, $affiliateId]);
    if (!$check->fetch()) {
        echo '<div class="alert alert-danger">Prospect not found.</div>';
        exit;
    }

    $stmt = $pdo->prepare(
        "UPDATE prospects SET
            business_name = ?, business_type = ?, address = ?, city = ?, state = ?, zip = ?, website = ?,
            contact_name = ?, contact_phone = ?, contact_email = ?,
            status = ?, product_interest = ?, source = ?, notes = ?, updated_at = NOW()
         WHERE id = ? AND affiliate_id = ?"
    );
    $stmt->execute([
        $businessName, $businessType ?: null, $address ?: null, $city ?: null, $state ?: null, $zip ?: null, $website ?: null,
        $contactName ?: null, $contactPhone ?: null, $contactEmail ?: null,
        $status, $productInterest, $source ?: null, $notes ?: null,
        $id, $affiliateId
    ]);
} else {
    $stmt = $pdo->prepare(
        "INSERT INTO prospects (affiliate_id, restaurant_id, business_name, business_type, address, city, state, zip, website,
            contact_name, contact_phone, contact_email, status, product_interest, source, notes, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
    );
    $stmt->execute([
        $affiliateId, null,
        $businessName, $businessType ?: null, $address ?: null, $city ?: null, $state ?: null, $zip ?: null, $website ?: null,
        $contactName ?: null, $contactPhone ?: null, $contactEmail ?: null,
        $status, $productInterest, $source ?: null, $notes ?: null
    ]);
}

// Auto-create restaurant when saving as in_setup or client
if (in_array($status, ['in_setup', 'client'])) {
    $prospectId = $isEdit ? $id : (int)$pdo->lastInsertId();
    $pStmt = $pdo->prepare("SELECT * FROM prospects WHERE id = ?");
    $pStmt->execute([$prospectId]);
    $fullProspect = $pStmt->fetch();
    if ($fullProspect && empty($fullProspect['restaurant_id'])) {
        createRestaurantFromProspect($fullProspect, $userId);
    }
}

// Reload the appropriate list based on the prospect's status
header('HX-Trigger: closeModal');
$_GET['status'] = '';
if ($status === 'in_setup') $_GET['status'] = 'in_setup';
elseif ($status === 'client') $_GET['status'] = 'client';
include __DIR__ . '/prospects.php';
