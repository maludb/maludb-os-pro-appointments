<?php
/**
 * Retell AI Request Authentication
 *
 * Verifies the X-Retell-Signature header (HMAC SHA-256 keyed with the Retell API key that owns the agent).
 * Each business's key is its own ('retell_api_key' in settings); a business without one uses the
 * server-wide RETELL_DEFAULT_API_KEY — never another business's.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/availability.php'; // getRestaurantSetting
require_once __DIR__ . '/restaurant.php';   // getRestaurantByPhone
require_once __DIR__ . '/../config/app.php';

/**
 * Verify the Retell signature on an incoming request.
 * Retell sends "v=<unix ms>,d=<hex>" where d = HMAC-SHA256(raw body . timestamp) keyed with the API key,
 * and the timestamp must be within five minutes (the scheme of Retell's own SDKs).
 */
function verifyRetellSignature(string $rawBody, string $signature, string $apiKey): bool
{
    if ($apiKey === '' || !preg_match('/^v=(\d+),d=([0-9a-f]+)$/', $signature, $m)) {
        return false;
    }
    if (abs((int)(microtime(true) * 1000) - (int)$m[1]) > 5 * 60 * 1000) {
        return false;
    }
    return hash_equals(hash_hmac('sha256', $rawBody . $m[1], $apiKey), $m[2]);
}

/**
 * The business a Retell request is about: the restaurant_slug argument of a custom function, else the agent
 * (call.agent_id, or ?id= on the webhook URL) through restaurant_prompts, else the number called.
 */
function retellRequestRestaurantId(array $data): ?int
{
    $slug = (string)($data['args']['restaurant_slug'] ?? '');
    if ($slug !== '') {
        $stmt = db()->prepare("SELECT id FROM restaurants WHERE slug = ?");
        $stmt->execute([$slug]);
        $id = $stmt->fetchColumn();
        if ($id) return (int)$id;
    }
    $call = $data['call'] ?? $data['call_inbound'] ?? $data;
    $agentId = (string)($call['agent_id'] ?? $_GET['id'] ?? '');
    if ($agentId !== '') {
        $stmt = db()->prepare("SELECT restaurant_id FROM restaurant_prompts WHERE agent_id = ? LIMIT 1");
        $stmt->execute([$agentId]);
        $id = $stmt->fetchColumn();
        if ($id) return (int)$id;
    }
    $to = (string)($call['to_number'] ?? '');
    if ($to !== '') {
        $r = getRestaurantByPhone($to);
        if ($r) return (int)$r['id'];
    }
    return null;
}

/**
 * Stop with 401 unless the request carries a valid Retell signature, made with the key of the business
 * it is about (or the server-wide key). Every endpoint Retell calls goes through here.
 */
function requireRetellSignature(string $rawBody, array $data): void
{
    $signature = $_SERVER['HTTP_X_RETELL_SIGNATURE'] ?? '';
    $keys = array_unique(array_filter([
        getRetellApiKey(retellRequestRestaurantId($data) ?? 0),
        (string)app_config('RETELL_DEFAULT_API_KEY', ''),
    ]));
    foreach ($keys as $key) {
        if (verifyRetellSignature($rawBody, $signature, $key)) return;
    }
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

/** Choose (with an id) or read (without) the business whose key the Retell API helpers use. */
function retellUseRestaurant(?int $restaurantId = null): ?int
{
    static $current = null;
    if ($restaurantId !== null) $current = $restaurantId;
    return $current;
}

/**
 * Get the Retell API key: the business's own key, else the server-wide RETELL_DEFAULT_API_KEY.
 * With no business given, the one chosen by retellUseRestaurant(), else the signed-in session's current
 * business, else only the server-wide key. Another business's key is never used.
 *
 * @param int|null $restaurantId The business the call is for (0 = the server-wide key only)
 */
function getRetellApiKey(?int $restaurantId = null): string
{
    $pdo = db();
    $restaurantId ??= retellUseRestaurant();
    if ($restaurantId === null && session_status() === PHP_SESSION_ACTIVE) {
        $restaurantId = (int)($_SESSION['current_restaurant_id'] ?? 0) ?: null;
    }

    // 1. The business's own key
    if ($restaurantId !== null && $restaurantId > 0) {
        $stmt = $pdo->prepare(
            "SELECT setting_value FROM settings WHERE setting_key = 'retell_api_key' AND restaurant_id = ? AND setting_value != '' LIMIT 1"
        );
        $stmt->execute([$restaurantId]);
        $row = $stmt->fetch();
        if ($row) {
            return $row['setting_value'];
        }
    }

    // 2. Server-wide default (the environment, config/.env or config/local.php)
    return (string)app_config('RETELL_DEFAULT_API_KEY', '');
}

/**
 * Parse a Retell Custom Function request.
 * Returns the decoded body or sends a 401/400 error response.
 */
function parseRetellRequest(): array
{
    // Logging
    $logFile = __DIR__ . '/../logs/retell-custom-functions.log';
    $ts = date('Y-m-d H:i:s');
    $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
    file_put_contents($logFile, "[{$ts}] === REQUEST: {$uri} ===\n", FILE_APPEND);

    // Read raw body
    $rawBody = file_get_contents('php://input');
    file_put_contents($logFile, "[{$ts}] BODY: " . strlen((string)$rawBody) . " bytes\n", FILE_APPEND);

    if ($rawBody === false || $rawBody === '') {
        file_put_contents($logFile, "[{$ts}] ERROR: Empty body\n\n", FILE_APPEND);
        http_response_code(400);
        echo json_encode(['error' => 'Empty request body']);
        exit;
    }

    // Parse JSON
    $data = json_decode($rawBody, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    requireRetellSignature($rawBody, $data);

    return $data;
}

/**
 * Extract args from Retell request body.
 * Retell sends: { "name": "function_name", "args": { ... }, "call": { ... } }
 */
function getRetellArgs(array $data): array
{
    return $data['args'] ?? [];
}

/**
 * Send a JSON response for Retell consumption.
 * Retell converts the response to a string for the LLM.
 */
function retellResponse(array $data): void
{
    $logFile = __DIR__ . '/../logs/retell-custom-functions.log';
    $ts = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$ts}] RESPONSE: success=" . json_encode($data['success'] ?? null) . "\n\n", FILE_APPEND);

    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
