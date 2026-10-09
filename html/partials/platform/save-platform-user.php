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

$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? 'user';

if ($firstName === '' || $lastName === '' || $email === '') {
    echo '<div class="alert alert-danger">First name, last name, and email are required.</div>';
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo '<div class="alert alert-danger">Please enter a valid email address.</div>';
    exit;
}

if (!in_array($role, ['user', 'affiliate', 'super-admin'])) {
    echo '<div class="alert alert-danger">Invalid platform role.</div>';
    exit;
}

if (!$isEdit && $password === '') {
    echo '<div class="alert alert-danger">Password is required for new users.</div>';
    exit;
}

$pdo = db();

// Check email uniqueness
$emailCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
$emailCheck->execute([$email, $id]);
if ($emailCheck->fetch()) {
    echo '<div class="alert alert-danger">A user with this email already exists.</div>';
    exit;
}

if ($isEdit) {
    // Update user
    if ($password !== '') {
        $stmt = $pdo->prepare(
            "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?,
                    password_hash = ?, role = ?, is_platform_admin = ?
             WHERE id = ?"
        );
        $stmt->execute([
            $firstName, $lastName, $email, $phone ?: null,
            password_hash($password, PASSWORD_DEFAULT),
            $role, ($role === 'super-admin') ? 1 : 0,
            $id
        ]);
    } else {
        $stmt = $pdo->prepare(
            "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?,
                    role = ?, is_platform_admin = ?
             WHERE id = ?"
        );
        $stmt->execute([
            $firstName, $lastName, $email, $phone ?: null,
            $role, ($role === 'super-admin') ? 1 : 0,
            $id
        ]);
    }

    // Handle restaurant assignment if provided
    $assignRestaurantId = (int)($_POST['assign_restaurant_id'] ?? 0);
    $assignRole = $_POST['assign_role'] ?? '';
    if ($assignRestaurantId > 0 && in_array($assignRole, ['user', 'manager', 'admin'])) {
        $stmt = $pdo->prepare(
            "INSERT INTO user_restaurants (user_id, restaurant_id, role, is_active)
             VALUES (?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE role = VALUES(role), is_active = 1"
        );
        $stmt->execute([$id, $assignRestaurantId, $assignRole]);
    }
} else {
    // Create new user
    $stmt = $pdo->prepare(
        "INSERT INTO users (first_name, last_name, email, phone, password_hash, role, is_platform_admin, is_active, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())"
    );
    $stmt->execute([
        $firstName, $lastName, $email, $phone ?: null,
        password_hash($password, PASSWORD_DEFAULT),
        $role, ($role === 'super-admin') ? 1 : 0
    ]);
}

header('HX-Trigger: closeModal');
include __DIR__ . '/users.php';
