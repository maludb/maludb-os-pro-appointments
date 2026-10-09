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

$affiliateCode = trim($_POST['affiliate_code'] ?? '');
$commissionRate = (float)($_POST['commission_rate'] ?? 10);
$status = $_POST['status'] ?? 'active';
$payoutEmail = trim($_POST['payout_email'] ?? '');
$payoutMethod = $_POST['payout_method'] ?? 'paypal';
$notes = trim($_POST['notes'] ?? '');

// Company fields
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

$validCompanyTypes = ['', 'c-corp', 's-corp', 'llc', 'partnership', 'sole-proprietor', 'non-profit', 'other'];
if (!in_array($companyType, $validCompanyTypes)) {
    $companyType = '';
}

if ($affiliateCode === '') {
    echo '<div class="alert alert-danger">Affiliate code is required.</div>';
    exit;
}

if (!in_array($status, ['active', 'inactive', 'suspended'])) {
    echo '<div class="alert alert-danger">Invalid status.</div>';
    exit;
}

if (!in_array($payoutMethod, ['paypal', 'bank_transfer', 'check'])) {
    echo '<div class="alert alert-danger">Invalid payout method.</div>';
    exit;
}

$pdo = db();

// Check code uniqueness
$codeCheck = $pdo->prepare("SELECT id FROM affiliates WHERE affiliate_code = ? AND id != ?");
$codeCheck->execute([$affiliateCode, $id]);
if ($codeCheck->fetch()) {
    echo '<div class="alert alert-danger">This affiliate code is already in use.</div>';
    exit;
}

if ($isEdit) {
    $stmt = $pdo->prepare(
        "UPDATE affiliates SET affiliate_code = ?, commission_rate = ?, status = ?,
                payout_email = ?, payout_method = ?, notes = ?,
                company_name = ?, company_type = ?, tax_id = ?,
                business_address = ?, business_city = ?, business_state = ?, business_zip = ?,
                contact_name = ?, contact_phone = ?, contact_email = ?
         WHERE id = ?"
    );
    $stmt->execute([
        $affiliateCode, $commissionRate, $status, $payoutEmail ?: null, $payoutMethod, $notes ?: null,
        $companyName ?: null, $companyType ?: null, $taxId ?: null,
        $businessAddress ?: null, $businessCity ?: null, $businessState ?: null, $businessZip ?: null,
        $contactName ?: null, $contactPhone ?: null, $contactEmail ?: null,
        $id
    ]);
} else {
    // Determine user_id: existing user or create new
    $userId = (int)($_POST['user_id'] ?? 0);

    if ($userId <= 0) {
        // Create new user
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($firstName === '' || $lastName === '' || $email === '') {
            echo '<div class="alert alert-danger">First name, last name, and email are required for new users.</div>';
            exit;
        }

        if ($password === '') {
            echo '<div class="alert alert-danger">Password is required for new users.</div>';
            exit;
        }

        // Check email uniqueness
        $emailCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $emailCheck->execute([$email]);
        if ($emailCheck->fetch()) {
            echo '<div class="alert alert-danger">A user with this email already exists. Use the "Existing User" tab.</div>';
            exit;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO users (first_name, last_name, email, phone, password_hash, is_platform_admin, created_at)
             VALUES (?, ?, ?, ?, ?, 0, NOW())"
        );
        $stmt->execute([$firstName, $lastName, $email, $phone ?: null, password_hash($password, PASSWORD_DEFAULT)]);
        $userId = (int)$pdo->lastInsertId();
    }

    // Verify user exists and is not already an affiliate
    $userCheck = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $userCheck->execute([$userId]);
    if (!$userCheck->fetch()) {
        echo '<div class="alert alert-danger">User not found.</div>';
        exit;
    }

    $affCheck = $pdo->prepare("SELECT id FROM affiliates WHERE user_id = ?");
    $affCheck->execute([$userId]);
    if ($affCheck->fetch()) {
        echo '<div class="alert alert-danger">This user is already an affiliate.</div>';
        exit;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO affiliates (user_id, affiliate_code, commission_rate, status, payout_email, payout_method, notes,
                company_name, company_type, tax_id, business_address, business_city, business_state, business_zip,
                contact_name, contact_phone, contact_email)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $userId, $affiliateCode, $commissionRate, $status, $payoutEmail ?: null, $payoutMethod, $notes ?: null,
        $companyName ?: null, $companyType ?: null, $taxId ?: null,
        $businessAddress ?: null, $businessCity ?: null, $businessState ?: null, $businessZip ?: null,
        $contactName ?: null, $contactPhone ?: null, $contactEmail ?: null
    ]);

    // Create the affiliate's business in the restaurants table
    $userRow = $pdo->prepare("SELECT first_name, last_name, email, phone FROM users WHERE id = ?");
    $userRow->execute([$userId]);
    $userRow = $userRow->fetch();

    $bizName = $companyName !== '' ? $companyName : trim($userRow['first_name'] . ' ' . $userRow['last_name']);
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $bizName), '-'));
    if ($slug === '') {
        $slug = 'affiliate-' . $affiliateCode;
    }
    $baseSlug = $slug;
    $counter = 1;
    while (true) {
        $check = $pdo->prepare("SELECT id FROM restaurants WHERE slug = ?");
        $check->execute([$slug]);
        if (!$check->fetch()) break;
        $slug = $baseSlug . '-' . $counter++;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO restaurants (name, slug, phone, email, address_line1, city, state, postal_code, timezone, location_type, status, is_active, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'America/Chicago', 'affiliate', 'in-setup', 1, NOW())"
    );
    $stmt->execute([
        $bizName, $slug,
        $contactPhone ?: ($userRow['phone'] ?: null),
        $contactEmail ?: ($userRow['email'] ?: null),
        $businessAddress ?: null, $businessCity ?: null, $businessState ?: null, $businessZip ?: null
    ]);
    $restaurantId = (int)$pdo->lastInsertId();

    // Link the affiliate user as admin of the business
    $pdo->prepare(
        "INSERT INTO user_restaurants (user_id, restaurant_id, role, is_active) VALUES (?, ?, 'admin', 1)"
    )->execute([$userId, $restaurantId]);
}

header('HX-Retarget: #page-content');
header('HX-Reswap: innerHTML');
header('HX-Trigger: closeModal');
include __DIR__ . '/affiliates.php';
