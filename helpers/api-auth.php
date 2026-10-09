<?php
/**
 * Shared request authentication for the REST API and MCP servers.
 */

require_once __DIR__ . '/db.php';

/**
 * Return the Bearer token from the Authorization header, or ''.
 * Under mod_php, $_SERVER['HTTP_AUTHORIZATION'] is often unset, so fall back to getallheaders().
 */
function request_bearer_token(): string {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($header === '' && function_exists('getallheaders')) {
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);
        $header = $headers['authorization'] ?? '';
    }
    return preg_match('/^Bearer\s+(.+)$/i', trim($header), $m) ? trim($m[1]) : '';
}

/**
 * Find the business whose Integrations "MCP API key" matches the request's Bearer token.
 * Returns the restaurant id, or null when there is no token or no match.
 */
function mcp_business_for_request(): ?int {
    $token = request_bearer_token();
    if ($token === '') return null;

    $rows = db()->query(
        "SELECT s.restaurant_id, s.setting_value
         FROM settings s
         JOIN restaurants r ON r.id = s.restaurant_id AND r.is_active = 1
         WHERE s.setting_key = 'mcp_api_key' AND s.setting_value != ''"
    )->fetchAll();

    foreach ($rows as $row) {
        if (hash_equals($row['setting_value'], $token)) {
            return (int)$row['restaurant_id'];
        }
    }
    return null;
}

/**
 * Tools running under an MCP key may only touch that key's business.
 * Returns true when no MCP key is in force (web app, webhooks) or the business matches.
 */
function mcp_business_allowed(int $restaurantId): bool {
    $allowed = $GLOBALS['mcp_authorized_restaurant_id'] ?? null;
    return $allowed === null || (int)$allowed === $restaurantId;
}
