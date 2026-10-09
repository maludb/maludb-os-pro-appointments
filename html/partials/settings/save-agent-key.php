<?php
/**
 * Create or revoke an Agent API key, then re-render the key list.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$newAgentKey = null;
$agentKeyError = null;

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $agentKeyError = 'Invalid security token. Please refresh and try again.';
} else {
    $restaurantId = currentRestaurantId();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $agentKeyError = 'Please give the key a name.';
        } else {
            $newAgentKey = bin2hex(random_bytes(32));
            db()->prepare(
                "INSERT INTO api_tokens (user_id, restaurant_id, token_hash, device_name, expires_at)
                 VALUES (?, ?, ?, ?, NOW() + INTERVAL '1 year')"
            )->execute([get_user_id(), $restaurantId, hash('sha256', $newAgentKey), mb_substr($name, 0, 100)]);
        }
    } elseif ($action === 'revoke') {
        db()->prepare("DELETE FROM api_tokens WHERE id = ? AND restaurant_id = ?")
            ->execute([(int)($_POST['id'] ?? 0), $restaurantId]);
    } else {
        $agentKeyError = 'Invalid action.';
    }
}

include __DIR__ . '/agent-keys.php';
