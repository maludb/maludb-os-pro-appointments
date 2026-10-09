<?php
/**
 * Save Affiliate Settings — handles the affiliate's own company/payout info updates
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
    echo '<div class="alert alert-danger">Invalid security token. Please try again.</div>';
    exit;
}

$pdo = db();
$userId = currentUserId();

// Get affiliate id for current context (user or restaurant)
$affiliateId = currentAffiliateId();

if (!$affiliateId) {
    echo '<div class="alert alert-danger">Affiliate record not found.</div>';
    exit;
}

$companyName = trim($_POST['company_name'] ?? '');
$companyType = $_POST['company_type'] ?? '';
$taxId = trim($_POST['tax_id'] ?? '');
$businessAddress = trim($_POST['business_address'] ?? '');
$businessCity = trim($_POST['business_city'] ?? '');
$businessState = strtoupper(trim($_POST['business_state'] ?? ''));
$businessZip = trim($_POST['business_zip'] ?? '');
$contactName = trim($_POST['contact_name'] ?? '');
$contactPhone = trim($_POST['contact_phone'] ?? '');
$contactEmail = trim($_POST['contact_email'] ?? '');
$payoutEmail = trim($_POST['payout_email'] ?? '');
$payoutMethod = $_POST['payout_method'] ?? 'paypal';

$validCompanyTypes = ['', 'c-corp', 's-corp', 'llc', 'partnership', 'sole-proprietor', 'non-profit', 'other'];
if (!in_array($companyType, $validCompanyTypes)) {
    $companyType = '';
}

if (!in_array($payoutMethod, ['paypal', 'bank_transfer', 'check'])) {
    $payoutMethod = 'paypal';
}

$stmt = $pdo->prepare(
    "UPDATE affiliates SET
        company_name = ?, company_type = ?, tax_id = ?,
        business_address = ?, business_city = ?, business_state = ?, business_zip = ?,
        contact_name = ?, contact_phone = ?, contact_email = ?,
        payout_email = ?, payout_method = ?
     WHERE id = ?"
);
$stmt->execute([
    $companyName ?: null, $companyType ?: null, $taxId ?: null,
    $businessAddress ?: null, $businessCity ?: null, $businessState ?: null, $businessZip ?: null,
    $contactName ?: null, $contactPhone ?: null, $contactEmail ?: null,
    $payoutEmail ?: null, $payoutMethod,
    $affiliateId
]);

echo '<div class="alert alert-success alert-dismissible fade show"><i class="feather-check me-1"></i> Affiliate settings saved successfully.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
