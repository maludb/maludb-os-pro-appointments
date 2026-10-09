<?php
/**
 * Save Client User — handler for affiliates inviting a user to a prospect's company.
 * Mirrors the admin save-user.php flow but uses the prospect's restaurant_id.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../helpers/validation.php';
require_once __DIR__ . '/../../../helpers/db.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="save-client-user-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="save-client-user-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$pdo = db();
$userId = currentUserId();
$prospectId = (int)($_POST['prospect_id'] ?? 0);

// Get affiliate id for current context (user or restaurant)
$affiliateId = currentAffiliateId();

if (!$affiliateId || $prospectId <= 0) {
    echo '<div class="alert alert-danger" id="save-client-user-invalid-error">Invalid request.</div>';
    exit;
}

$stmt = $pdo->prepare("SELECT restaurant_id, business_name FROM prospects WHERE id = ? AND affiliate_id = ? AND status = 'in_setup'");
$stmt->execute([$prospectId, $affiliateId]);
$prospect = $stmt->fetch();

if (!$prospect || empty($prospect['restaurant_id'])) {
    echo '<div class="alert alert-danger" id="save-client-user-no-company">Company must be created before inviting users.</div>';
    exit;
}

$restaurantId = (int)$prospect['restaurant_id'];

// Validate fields
$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$role = trim($_POST['role'] ?? '');
$password = $_POST['password'] ?? '';

if ($firstName === '' || $lastName === '' || $email === '') {
    echo '<div class="alert alert-danger" id="save-client-user-required-error">First name, last name, and email are required.</div>';
    exit;
}

if (!validate_email($email)) {
    echo '<div class="alert alert-danger" id="save-client-user-email-error">Please enter a valid email address.</div>';
    exit;
}

if (!in_array($role, ['admin', 'manager', 'user'])) {
    echo '<div class="alert alert-danger" id="save-client-user-role-error">Please select a valid role.</div>';
    exit;
}

if ($password !== '') {
    $pwCheck = validate_password($password);
    if (!$pwCheck['valid']) {
        echo '<div class="alert alert-danger" id="save-client-user-pw-error">' . htmlspecialchars($pwCheck['message']) . '</div>';
        exit;
    }
}

try {
    $pdo->beginTransaction();

    // Check if user email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $existingUser = $stmt->fetch();

    if ($existingUser) {
        $newUserId = (int)$existingUser['id'];

        // Check if already linked to this restaurant
        $stmt = $pdo->prepare("SELECT id FROM user_restaurants WHERE user_id = ? AND restaurant_id = ?");
        $stmt->execute([$newUserId, $restaurantId]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            echo '<div class="alert alert-danger" id="save-client-user-exists-error">This user is already a member of this company.</div>';
            exit;
        }

        // Update user info if password provided
        if ($password !== '') {
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, password_hash = ? WHERE id = ?");
            $stmt->execute([$firstName, $lastName, $phone ?: null, password_hash($password, PASSWORD_DEFAULT), $newUserId]);
        }
    } else {
        // Create new user — if no password, mark as invited
        $hashValue = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : '!INVITED';
        $stmt = $pdo->prepare(
            "INSERT INTO users (first_name, last_name, email, phone, password_hash, is_platform_admin, is_active)
             VALUES (?, ?, ?, ?, ?, 0, 1)"
        );
        $stmt->execute([$firstName, $lastName, $email, $phone ?: null, $hashValue]);
        $newUserId = (int)$pdo->lastInsertId();
    }

    // Create user_restaurants entry
    $stmt = $pdo->prepare(
        "INSERT INTO user_restaurants (user_id, restaurant_id, role, is_active)
         VALUES (?, ?, ?, 1)"
    );
    $stmt->execute([$newUserId, $restaurantId, $role]);

    $pdo->commit();

    echo '<div class="alert alert-success alert-dismissible fade show" id="save-client-user-success">
            <i class="feather-check-circle me-1"></i> User invited successfully to ' . htmlspecialchars($prospect['business_name']) . '.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    echo '<script>document.getElementById("modal-container").innerHTML="";</script>';

} catch (Exception $e) {
    $pdo->rollBack();
    echo '<div class="alert alert-danger" id="save-client-user-error">An error occurred while saving. Please try again.</div>';
}
