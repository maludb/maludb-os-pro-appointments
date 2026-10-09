<?php
/**
 * Staff members of this business (admin)
 *
 * GET  /api/v1/staff.php           — List staff
 * POST /api/v1/staff.php           — Add staff: first_name, last_name, email, phone, role
 * PUT  /api/v1/staff.php?id=5      — Change role / is_active (and name / phone for accounts that
 *                                    belong to this business only)
 *
 * The API never sets passwords. A new person is created as an invitation and sets their own
 * password with "Accept Invitation" on the registration page, as with the web Add Staff form.
 * Mirrors html/partials/settings/save-user.php and toggle-user.php.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../helpers/validation.php';

$auth   = api_authenticate();
$rid    = $auth['restaurant_id'];
$method = api_method();
api_require_role($auth, 'admin');

if ($method === 'GET') {
    $stmt = db()->prepare(
        "SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.last_login_at,
                ur.role, ur.is_active, (u.password_hash = '!INVITED') AS invitation_pending
         FROM user_restaurants ur
         JOIN users u ON u.id = ur.user_id
         WHERE ur.restaurant_id = ?
         ORDER BY ur.role ASC, u.last_name ASC"
    );
    $stmt->execute([$rid]);
    api_success(['staff' => array_map('formatStaff', $stmt->fetchAll())]);
} elseif ($method === 'POST') {
    handleAddStaff($rid);
} elseif ($method === 'PUT') {
    handleUpdateStaff($rid, (int)api_query('id'), $auth['user_id']);
} else {
    api_error('Method not allowed.', 'METHOD_NOT_ALLOWED', 405);
}

function handleAddStaff(int $rid): void {
    $in = api_json_body();
    $firstName = trim((string)($in['first_name'] ?? ''));
    $lastName = trim((string)($in['last_name'] ?? ''));
    $email = trim((string)($in['email'] ?? ''));
    $phone = trim((string)($in['phone'] ?? ''));
    $role = trim((string)($in['role'] ?? ''));

    if ($firstName === '' || $lastName === '' || $email === '') {
        api_error('First name, last name, and email are required.', 'VALIDATION_ERROR');
    }
    if (!validate_email($email)) {
        api_error('Please enter a valid email address.', 'VALIDATION_ERROR');
    }
    if (!in_array($role, ['admin', 'manager', 'user'], true)) {
        api_error('role must be admin, manager or user.', 'VALIDATION_ERROR');
    }

    $pdo = db();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $userId = $stmt->fetchColumn();

    if ($userId) {
        // Existing account: link it to this business without changing the account itself
        $stmt = $pdo->prepare("SELECT 1 FROM user_restaurants WHERE user_id = ? AND restaurant_id = ?");
        $stmt->execute([$userId, $rid]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            api_error('This person is already a member of this business.', 'DUPLICATE');
        }
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO users (first_name, last_name, email, phone, password_hash, is_platform_admin, is_active)
             VALUES (?, ?, ?, ?, '!INVITED', 0, 1)
             RETURNING id"
        );
        $stmt->execute([$firstName, $lastName, $email, $phone ?: null]);
        $userId = $stmt->fetchColumn();
    }

    $pdo->prepare("INSERT INTO user_restaurants (user_id, restaurant_id, role, is_active) VALUES (?, ?, ?, 1)")
        ->execute([$userId, $rid, $role]);
    $pdo->commit();

    api_success(['staff' => loadStaff($rid, (int)$userId)], 201);
}

function handleUpdateStaff(int $rid, int $userId, int $selfId): void {
    $pdo = db();
    $member = loadStaff($rid, $userId);
    if (!$member) {
        api_error('Staff member not found.', 'NOT_FOUND', 404);
    }
    $in = api_json_body();

    if ($userId === $selfId && (isset($in['role']) || isset($in['is_active']))) {
        api_error('You cannot change your own role or deactivate yourself.', 'FORBIDDEN', 403);
    }

    $role = trim((string)($in['role'] ?? $member['role']));
    if (!in_array($role, ['admin', 'manager', 'user'], true)) {
        api_error('role must be admin, manager or user.', 'VALIDATION_ERROR');
    }
    $isActive = array_key_exists('is_active', $in) ? (api_bool($in['is_active']) ? 1 : 0) : (int)$member['is_active'];

    $pdo->prepare("UPDATE user_restaurants SET role = ?, is_active = ? WHERE user_id = ? AND restaurant_id = ?")
        ->execute([$role, $isActive, $userId, $rid]);

    // Name and phone live on the account, so only change them when it belongs to this business alone
    $profileFields = array_intersect_key($in, ['first_name' => 1, 'last_name' => 1, 'phone' => 1]);
    if ($profileFields) {
        $stmt = $pdo->prepare(
            "SELECT 1 FROM users u
             WHERE u.id = ? AND (u.role = 'super-admin'
                OR EXISTS (SELECT 1 FROM user_restaurants ur WHERE ur.user_id = u.id AND ur.restaurant_id != ?))"
        );
        $stmt->execute([$userId, $rid]);
        if ($stmt->fetch()) {
            api_error('This person also has access to another business, so only they can change their name or phone.', 'FORBIDDEN', 403);
        }
        $firstName = trim((string)($profileFields['first_name'] ?? $member['first_name']));
        $lastName = trim((string)($profileFields['last_name'] ?? $member['last_name']));
        if ($firstName === '' || $lastName === '') {
            api_error('First and last name cannot be empty.', 'VALIDATION_ERROR');
        }
        $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ? WHERE id = ?")
            ->execute([$firstName, $lastName, trim((string)($profileFields['phone'] ?? $member['phone'])) ?: null, $userId]);
    }

    api_success(['staff' => loadStaff($rid, $userId)]);
}

function loadStaff(int $rid, int $userId): ?array {
    $stmt = db()->prepare(
        "SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.last_login_at,
                ur.role, ur.is_active, (u.password_hash = '!INVITED') AS invitation_pending
         FROM user_restaurants ur
         JOIN users u ON u.id = ur.user_id
         WHERE ur.restaurant_id = ? AND ur.user_id = ?"
    );
    $stmt->execute([$rid, $userId]);
    $row = $stmt->fetch();
    return $row ? formatStaff($row) : null;
}

function formatStaff(array $u): array {
    return [
        'id'                 => (int)$u['id'],
        'first_name'         => $u['first_name'],
        'last_name'          => $u['last_name'],
        'email'              => $u['email'],
        'phone'              => $u['phone'],
        'role'               => $u['role'],
        'is_active'          => (bool)$u['is_active'],
        'invitation_pending' => (bool)$u['invitation_pending'],
        'last_login_at'      => $u['last_login_at'],
    ];
}
