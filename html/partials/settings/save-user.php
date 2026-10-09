<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';
require_once '../../../helpers/validation.php';

requireAuth();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="save-user-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="save-user-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
if (!$restaurantId) {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="save-user-no-restaurant">No restaurant selected.</div>';
    exit;
}

$userId = (int)($_POST['user_id'] ?? 0);
$isEdit = $userId > 0;

// Validate required fields
$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$role = trim($_POST['role'] ?? '');
$password = $_POST['password'] ?? '';

if ($firstName === '' || $lastName === '' || $email === '') {
    echo '<div class="alert alert-danger" id="save-user-required-error">First name, last name, and email are required.</div>';
    exit;
}

if (!validate_email($email)) {
    echo '<div class="alert alert-danger" id="save-user-email-error">Please enter a valid email address.</div>';
    exit;
}

if (!in_array($role, ['admin', 'manager', 'user'])) {
    echo '<div class="alert alert-danger" id="save-user-role-error">Please select a valid role.</div>';
    exit;
}

// Password is optional for new users — they can accept invitation later
// If no password, store a marker so they must set one via Accept Invitation

if ($password !== '') {
    $pwCheck = validate_password($password);
    if (!$pwCheck['valid']) {
        echo '<div class="alert alert-danger" id="save-user-pw-error">' . htmlspecialchars($pwCheck['message']) . '</div>';
        exit;
    }
}

$pdo = db();

try {
    $pdo->beginTransaction();

    if ($isEdit) {
        // Verify user belongs to this restaurant
        $stmt = $pdo->prepare("SELECT user_id FROM user_restaurants WHERE user_id = ? AND restaurant_id = ?");
        $stmt->execute([$userId, $restaurantId]);
        if (!$stmt->fetch()) {
            $pdo->rollBack();
            echo '<div class="alert alert-danger" id="save-user-notfound">User not found in this restaurant.</div>';
            exit;
        }

        // Update user record
        if ($password !== '') {
            // Only reset passwords for accounts that belong to this business alone
            $stmt = $pdo->prepare(
                "SELECT 1 FROM users u
                 WHERE u.id = ? AND (u.role = 'super-admin'
                    OR EXISTS (SELECT 1 FROM user_restaurants ur WHERE ur.user_id = u.id AND ur.restaurant_id != ?))"
            );
            $stmt->execute([$userId, $restaurantId]);
            if ($stmt->fetch()) {
                $pdo->rollBack();
                echo '<div class="alert alert-danger" id="save-user-pw-shared-error">This person also has access to another business, so only they can change their password.</div>';
                exit;
            }
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, password_hash = ? WHERE id = ?");
            $stmt->execute([$firstName, $lastName, $phone ?: null, password_hash($password, PASSWORD_DEFAULT), $userId]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ? WHERE id = ?");
            $stmt->execute([$firstName, $lastName, $phone ?: null, $userId]);
        }

        // Update role in user_restaurants
        $stmt = $pdo->prepare("UPDATE user_restaurants SET role = ? WHERE user_id = ? AND restaurant_id = ?");
        $stmt->execute([$role, $userId, $restaurantId]);

    } else {
        // Check if user email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existingUser = $stmt->fetch();

        if ($existingUser) {
            $userId = $existingUser['id'];

            // Check if already linked to this restaurant
            $stmt = $pdo->prepare("SELECT id FROM user_restaurants WHERE user_id = ? AND restaurant_id = ?");
            $stmt->execute([$userId, $restaurantId]);
            if ($stmt->fetch()) {
                $pdo->rollBack();
                echo '<div class="alert alert-danger" id="save-user-exists-error">This user is already a member of this restaurant.</div>';
                exit;
            }

            // Update user info if password provided
            // Existing accounts keep their own password; an admin here must not be able to change it
        } else {
            // Create new user — if no password, mark as invited
            $hashValue = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : '!INVITED';
            $stmt = $pdo->prepare(
                "INSERT INTO users (first_name, last_name, email, phone, password_hash, is_platform_admin, is_active)
                 VALUES (?, ?, ?, ?, ?, 0, 1)"
            );
            $stmt->execute([$firstName, $lastName, $email, $phone ?: null, $hashValue]);
            $userId = $pdo->lastInsertId();
        }

        // Create user_restaurants entry
        $stmt = $pdo->prepare(
            "INSERT INTO user_restaurants (user_id, restaurant_id, role, is_active)
             VALUES (?, ?, ?, 1)"
        );
        $stmt->execute([$userId, $restaurantId, $role]);
    }

    $pdo->commit();

    // Trigger a refresh of the user list (must be before output)
    header('HX-Trigger: refreshUserList');

    echo '<div class="alert alert-success alert-dismissible fade show" id="save-user-success">
            <i class="feather-check-circle me-1"></i> Staff member ' . ($isEdit ? 'updated' : 'added') . ' successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    echo '<script>document.getElementById("users-modal-container").innerHTML="";</script>';

} catch (Exception $e) {
    $pdo->rollBack();
    echo '<div class="alert alert-danger" id="save-user-error">An error occurred while saving. Please try again.</div>';
}
