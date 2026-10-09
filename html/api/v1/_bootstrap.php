<?php
/**
 * REST API v1 Bootstrap
 * Shared by all /api/v1/ endpoints.
 * Provides: JSON helpers, token auth, error responses, request parsing.
 */

require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/restaurant.php';
require_once __DIR__ . '/../../../helpers/api-auth.php';
require_once __DIR__ . '/../../../helpers/os.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// --- JSON helpers ---

function api_success(array $data = [], int $status = 200): never {
    http_response_code($status);
    echo json_encode(array_merge(['success' => true], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

function api_error(string $message, string $code, int $status = 400): never {
    http_response_code($status);
    echo json_encode([
        'success' => false,
        'error'   => $message,
        'code'    => $code,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// --- Request helpers ---

function api_method(): string {
    return $_SERVER['REQUEST_METHOD'];
}

function api_require_method(string ...$allowed): void {
    if (!in_array(api_method(), $allowed, true)) {
        api_error('Method not allowed.', 'METHOD_NOT_ALLOWED', 405);
    }
}

function api_json_body(): array {
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function api_query(string $key, $default = null) {
    return $_GET[$key] ?? $default;
}

/**
 * Require at least the given business role (user < manager < admin), matching the web app's
 * requireManager() / requireAdmin() checks.
 */
function api_require_role(array $auth, string $minimum): void {
    $rank = ['user' => 1, 'manager' => 2, 'admin' => 3];
    if (($rank[$auth['restaurant_role']] ?? 0) < $rank[$minimum]) {
        api_error("This action requires the {$minimum} role.", 'FORBIDDEN', 403);
    }
}

/**
 * Read a boolean from JSON input (true/false, 1/0, "true"/"false", "yes"/"no").
 */
function api_bool($value): bool {
    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
}

function api_path_segments(): array {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    // Strip /api/v1/ prefix
    $path = preg_replace('#^/api/v1/?#', '', $path);
    $path = trim($path, '/');
    return $path !== '' ? explode('/', $path) : [];
}

// --- Token auth ---

function api_authenticate(): array {
    $token = request_bearer_token();
    if ($token === '') {
        api_error('Missing or invalid Authorization header.', 'AUTH_REQUIRED', 401);
    }

    // Under the operating system a key works only for a user linked to the kernel, in a business the kernel
    // still grants them — so a deactivation or revocation there ends the key here.
    $os = os_enabled();
    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT t.id AS token_id, t.user_id, t.restaurant_id, t.expires_at, t.last_used_at,
                u.id, u.email, u.first_name, u.last_name, u.role AS platform_role, u.is_active,
                ur.role AS restaurant_role
         FROM api_tokens t
         JOIN users u ON u.id = t.user_id
         JOIN user_restaurants ur ON ur.user_id = t.user_id AND ur.restaurant_id = t.restaurant_id AND ur.is_active = 1"
        . ($os ? " AND ur.source = 'os' AND EXISTS (SELECT 1 FROM restaurants r WHERE r.id = ur.restaurant_id AND r.is_active = 1 AND r.os_scope_id IS NOT NULL)" : "") . "
         WHERE t.token_hash = ? AND t.expires_at > NOW()"
        . ($os ? " AND u.os_member_id IS NOT NULL" : "") . "
         LIMIT 1"
    );
    $stmt->execute([hash('sha256', $token)]);
    $row = $stmt->fetch();

    if (!$row || !$row['is_active']) {
        api_error('Invalid or expired token.', 'AUTH_REQUIRED', 401);
    }

    // Record use for the Agent API Keys list (at most once a minute)
    if (!$row['last_used_at'] || strtotime($row['last_used_at']) < time() - 60) {
        $pdo->prepare("UPDATE api_tokens SET last_used_at = NOW() WHERE id = ?")->execute([$row['token_id']]);
    }

    return [
        'user_id'         => (int)$row['user_id'],
        'email'           => $row['email'],
        'first_name'      => $row['first_name'],
        'last_name'       => $row['last_name'],
        'platform_role'   => $row['platform_role'],
        'restaurant_id'   => (int)$row['restaurant_id'],
        'restaurant_role' => $row['restaurant_role'] ?? 'user',
    ];
}

// --- Rate limiting (simple, per-token) ---

function api_rate_limit(string $scope, int $maxPerMinute = 60): void {
    // Lightweight: uses APCu if available, otherwise skip
    if (!function_exists('apcu_fetch')) return;

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = "api_rl:{$scope}:{$ip}:" . floor(time() / 60);
    $count = apcu_fetch($key);
    if ($count === false) {
        apcu_store($key, 1, 120);
    } elseif ($count >= $maxPerMinute) {
        api_error('Rate limit exceeded. Try again shortly.', 'RATE_LIMITED', 429);
    } else {
        apcu_inc($key);
    }
}
