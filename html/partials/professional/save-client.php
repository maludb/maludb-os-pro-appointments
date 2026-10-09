<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../helpers/validation.php';

requireAuth();
requireManager();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="professional-save-client-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="professional-save-client-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();

if (!$restaurantId) {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="professional-save-client-no-restaurant">No professional account is currently selected.</div>';
    exit;
}

$clientId = (int)($_POST['client_id'] ?? 0);
$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$birthDate = trim($_POST['birth_date'] ?? '');
$preferredContactMethod = trim($_POST['preferred_contact_method'] ?? '');
$marketingOptIn = isset($_POST['marketing_opt_in']) ? 1 : 0;
$notes = trim($_POST['notes'] ?? '');
$internalNotes = trim($_POST['internal_notes'] ?? '');
$serviceAddressLine1 = trim($_POST['service_address_line1'] ?? '');
$serviceCity = trim($_POST['service_city'] ?? '');
$serviceState = trim($_POST['service_state'] ?? '');
$servicePostalCode = trim($_POST['service_postal_code'] ?? '');
$lastServiceDate = trim($_POST['last_service_date'] ?? '');

if ($firstName === '' || $lastName === '') {
    echo '<div class="alert alert-danger" id="professional-save-client-name-error">First and last name are required.</div>';
    exit;
}

if ($email !== '' && !validate_email($email)) {
    echo '<div class="alert alert-danger" id="professional-save-client-email-error">Please enter a valid email address.</div>';
    exit;
}

if ($birthDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate)) {
    echo '<div class="alert alert-danger" id="professional-save-client-birth-error">Please enter a valid birth date.</div>';
    exit;
}

if ($lastServiceDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $lastServiceDate)) {
    echo '<div class="alert alert-danger" id="professional-save-client-service-date-error">Please enter a valid last service date.</div>';
    exit;
}

$allowedPreferredContactMethods = ['', 'email', 'phone', 'sms'];
if (!in_array($preferredContactMethod, $allowedPreferredContactMethods, true)) {
    echo '<div class="alert alert-danger" id="professional-save-client-contact-method-error">Please choose a valid preferred contact method.</div>';
    exit;
}

$pdo = db();
$isEdit = $clientId > 0;

if ($isEdit) {
    $clientCheckStmt = $pdo->prepare("SELECT id FROM professional_clients WHERE id = ? AND restaurant_id = ? LIMIT 1");
    $clientCheckStmt->execute([$clientId, $restaurantId]);
    if (!$clientCheckStmt->fetch()) {
        echo '<div class="alert alert-danger" id="professional-save-client-not-found">Professional client not found.</div>';
        exit;
    }
}

if ($email !== '') {
    $emailQuery = "SELECT id FROM professional_clients WHERE restaurant_id = ? AND email = ?";
    $emailParams = [$restaurantId, $email];
    if ($isEdit) {
        $emailQuery .= " AND id != ?";
        $emailParams[] = $clientId;
    }
    $emailQuery .= " LIMIT 1";
    $emailStmt = $pdo->prepare($emailQuery);
    $emailStmt->execute($emailParams);
    if ($emailStmt->fetch()) {
        echo '<div class="alert alert-danger" id="professional-save-client-email-duplicate">Another professional client already uses this email address.</div>';
        exit;
    }
}

if ($isEdit) {
    $updateStmt = $pdo->prepare(
        "UPDATE professional_clients SET
            first_name = ?,
            last_name = ?,
            email = ?,
            phone = ?,
            birth_date = ?,
            preferred_contact_method = ?,
            marketing_opt_in = ?,
            notes = ?,
            internal_notes = ?,
            service_address_line1 = ?,
            service_city = ?,
            service_state = ?,
            service_postal_code = ?,
            last_service_date = ?,
            updated_at = NOW()
         WHERE id = ? AND restaurant_id = ?"
    );
    $updateStmt->execute([
        $firstName,
        $lastName,
        $email !== '' ? $email : null,
        $phone !== '' ? $phone : null,
        $birthDate !== '' ? $birthDate : null,
        $preferredContactMethod !== '' ? $preferredContactMethod : null,
        $marketingOptIn,
        $notes !== '' ? $notes : null,
        $internalNotes !== '' ? $internalNotes : null,
        $serviceAddressLine1 !== '' ? $serviceAddressLine1 : null,
        $serviceCity !== '' ? $serviceCity : null,
        $serviceState !== '' ? $serviceState : null,
        $servicePostalCode !== '' ? $servicePostalCode : null,
        $lastServiceDate !== '' ? $lastServiceDate : null,
        $clientId,
        $restaurantId,
    ]);
} else {
    $insertStmt = $pdo->prepare(
        "INSERT INTO professional_clients (
            restaurant_id,
            first_name,
            last_name,
            email,
            phone,
            birth_date,
            preferred_contact_method,
            marketing_opt_in,
            notes,
            internal_notes,
            service_address_line1,
            service_city,
            service_state,
            service_postal_code,
            last_service_date,
            created_at,
            updated_at
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
    );
    $insertStmt->execute([
        $restaurantId,
        $firstName,
        $lastName,
        $email !== '' ? $email : null,
        $phone !== '' ? $phone : null,
        $birthDate !== '' ? $birthDate : null,
        $preferredContactMethod !== '' ? $preferredContactMethod : null,
        $marketingOptIn,
        $notes !== '' ? $notes : null,
        $internalNotes !== '' ? $internalNotes : null,
        $serviceAddressLine1 !== '' ? $serviceAddressLine1 : null,
        $serviceCity !== '' ? $serviceCity : null,
        $serviceState !== '' ? $serviceState : null,
        $servicePostalCode !== '' ? $servicePostalCode : null,
        $lastServiceDate !== '' ? $lastServiceDate : null,
    ]);
}

header('HX-Trigger-After-Swap: {"refreshProfessionalClientsList":true,"refreshProfessionalClientDetail":true,"closeModal":true}');

echo '<div class="alert alert-success alert-dismissible fade show" id="professional-save-client-success">
        <i class="feather-check-circle me-1"></i> Professional client ' . ($isEdit ? 'updated' : 'created') . ' successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>';
