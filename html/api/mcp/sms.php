<?php
/**
 * MCP (Model Context Protocol) Server — SMS Scheduling
 *
 * JSON-RPC 2.0 over HTTP. Exposes appointment tools for SMS agents.
 * Company identified by phone number, only known clients can book.
 *
 * Auth: Bearer token = the business's 'mcp_api_key' setting; tools are limited to that business
 */

require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/api-auth.php';
require_once __DIR__ . '/sms-tools.php';

header('Content-Type: application/json');

// --- Logging ---
define('MCP_SMS_LOG_FILE', __DIR__ . '/../../../logs/mcp-sms.log');

function mcpSmsLog(string $message): void
{
    $ts = date('Y-m-d H:i:s');
    file_put_contents(MCP_SMS_LOG_FILE, "[{$ts}] {$message}\n", FILE_APPEND);
}

function mcpSmsLogJson(string $label, $data): void
{
    mcpSmsLog("{$label}: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

// --- Auth ---
function verifySmsAuth(): void
{
    $restaurantId = mcp_business_for_request();

    // Every request needs the MCP API key of an active business (Settings > Integrations).
    if ($restaurantId === null) {
        mcpSmsLog("AUTH FAILED: no key or key does not match a business");
        http_response_code(401);
        emitSmsJson([
            'jsonrpc' => '2.0',
            'error' => ['code' => -32000, 'message' => 'Unauthorized'],
            'id' => null,
        ]);
        exit;
    }

    $GLOBALS['mcp_authorized_restaurant_id'] = $restaurantId;
}

// --- JSON-RPC helpers ---
function jsonRpcSmsError(int $code, string $message, $id = null): array
{
    return [
        'jsonrpc' => '2.0',
        'error' => ['code' => $code, 'message' => $message],
        'id' => $id,
    ];
}

function jsonRpcSmsResult($result, $id): array
{
    return [
        'jsonrpc' => '2.0',
        'result' => $result,
        'id' => $id,
    ];
}

function emitSmsJson(array $payload): void
{
    $json = json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    if ($json === false) {
        http_response_code(500);
        echo '{"jsonrpc":"2.0","error":{"code":-32603,"message":"Internal JSON encoding error."},"id":null}';
        return;
    }

    echo $json;
}

// --- Main ---
mcpSmsLog("=== REQUEST: {$_SERVER['REQUEST_METHOD']} {$_SERVER['REQUEST_URI']} ===");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mcpSmsLog("ERROR: Method not allowed — {$_SERVER['REQUEST_METHOD']}");
    http_response_code(405);
    emitSmsJson(jsonRpcSmsError(-32600, 'Only POST requests are accepted.'));
    exit;
}

verifySmsAuth();

$rawBody = file_get_contents('php://input');
mcpSmsLog("RAW BODY: " . ($rawBody ?: '(empty)'));

$request = json_decode($rawBody, true);

if (!is_array($request) || !isset($request['method'])) {
    mcpSmsLog("ERROR: Invalid JSON-RPC request");
    emitSmsJson(jsonRpcSmsError(-32700, 'Parse error: invalid JSON-RPC request.'));
    exit;
}

$method = $request['method'];
$params = $request['params'] ?? [];
$id = $request['id'] ?? null;

mcpSmsLog("METHOD: {$method} | ID: " . json_encode($id));

switch ($method) {
    case 'initialize':
        $response = jsonRpcSmsResult([
            'protocolVersion' => '2024-11-05',
            'capabilities' => [
                'tools' => ['listChanged' => false],
            ],
            'serverInfo' => [
                'name' => 'ZozoCal SMS Scheduling',
                'version' => '1.0.0',
            ],
        ], $id);
        mcpSmsLog("RESPONSE: initialize OK");
        emitSmsJson($response);
        break;

    case 'notifications/initialized':
        mcpSmsLog("RESPONSE: notifications/initialized acknowledged");
        if ($id !== null) {
            emitSmsJson(jsonRpcSmsResult([], $id));
        }
        break;

    case 'tools/list':
        mcpSmsLog("RESPONSE: tools/list — returning " . count(getSmsToolDefinitions()) . " tools");
        emitSmsJson(jsonRpcSmsResult([
            'tools' => getSmsToolDefinitions(),
        ], $id));
        break;

    case 'tools/call':
        $toolName = $params['name'] ?? '';
        $toolArgs = $params['arguments'] ?? [];

        mcpSmsLog("TOOL CALL: {$toolName}");
        mcpSmsLogJson("TOOL ARGS", $toolArgs);

        if ($toolName === '') {
            mcpSmsLog("ERROR: Missing tool name");
            emitSmsJson(jsonRpcSmsError(-32602, 'Missing tool name.', $id));
            break;
        }

        try {
            $toolResult = executeSmsTool($toolName, $toolArgs);
            mcpSmsLogJson("TOOL RESULT", $toolResult);
            emitSmsJson(jsonRpcSmsResult($toolResult, $id));
        } catch (Throwable $e) {
            mcpSmsLog("EXCEPTION: " . $e->getMessage());
            mcpSmsLog($e->getTraceAsString());
            emitSmsJson(jsonRpcSmsError(-32603, 'Internal server error.', $id));
        }
        break;

    default:
        mcpSmsLog("ERROR: Unknown method — {$method}");
        emitSmsJson(jsonRpcSmsError(-32601, "Method not found: {$method}", $id));
        break;
}

mcpSmsLog("=== END ===");
