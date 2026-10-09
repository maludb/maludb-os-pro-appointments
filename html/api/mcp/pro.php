<?php
/**
 * MCP (Model Context Protocol) Server — Professional Scheduling
 *
 * JSON-RPC 2.0 over HTTP. Exposes professional appointment tools
 * for Retell AI, Claude, or any MCP-compatible client.
 *
 * Auth: Bearer token = the business's 'mcp_api_key' setting; tools are limited to that business
 */

require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/api-auth.php';
require_once __DIR__ . '/pro-tools.php';

header('Content-Type: application/json');

// --- Logging ---
define('MCP_PRO_LOG_FILE', __DIR__ . '/../../../logs/mcp-pro.log');

function mcpProLog(string $message): void
{
    $ts = date('Y-m-d H:i:s');
    file_put_contents(MCP_PRO_LOG_FILE, "[{$ts}] {$message}\n", FILE_APPEND);
}

function mcpProLogJson(string $label, $data): void
{
    mcpProLog("{$label}: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

// --- Auth ---
function verifyMcpProAuth(): void
{
    $restaurantId = mcp_business_for_request();

    // Every request needs the MCP API key of an active business (Settings > Integrations).
    if ($restaurantId === null) {
        mcpProLog("AUTH FAILED: no key or key does not match a business");
        http_response_code(401);
        emitProJson([
            'jsonrpc' => '2.0',
            'error' => ['code' => -32000, 'message' => 'Unauthorized'],
            'id' => null,
        ]);
        exit;
    }

    $GLOBALS['mcp_authorized_restaurant_id'] = $restaurantId;
}

// --- JSON-RPC Handler ---
function jsonRpcProError(int $code, string $message, $id = null): array
{
    return [
        'jsonrpc' => '2.0',
        'error' => ['code' => $code, 'message' => $message],
        'id' => $id,
    ];
}

function jsonRpcProResult($result, $id): array
{
    return [
        'jsonrpc' => '2.0',
        'result' => $result,
        'id' => $id,
    ];
}

function emitProJson(array $payload): void
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
mcpProLog("=== REQUEST: {$_SERVER['REQUEST_METHOD']} {$_SERVER['REQUEST_URI']} ===");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mcpProLog("ERROR: Method not allowed — {$_SERVER['REQUEST_METHOD']}");
    http_response_code(405);
    emitProJson(jsonRpcProError(-32600, 'Only POST requests are accepted.'));
    exit;
}

verifyMcpProAuth();

$rawBody = file_get_contents('php://input');
mcpProLog("BODY: " . strlen((string)$rawBody) . " bytes");

$request = json_decode($rawBody, true);

if (!is_array($request) || !isset($request['method'])) {
    mcpProLog("ERROR: Invalid JSON-RPC request");
    emitProJson(jsonRpcProError(-32700, 'Parse error: invalid JSON-RPC request.'));
    exit;
}

$method = $request['method'];
$params = $request['params'] ?? [];
$id = $request['id'] ?? null;

mcpProLog("METHOD: {$method} | ID: " . json_encode($id));

switch ($method) {
    case 'initialize':
        $response = jsonRpcProResult([
            'protocolVersion' => '2024-11-05',
            'capabilities' => [
                'tools' => ['listChanged' => false],
            ],
            'serverInfo' => [
                'name' => 'ZozoCal Professional Scheduling',
                'version' => '1.0.0',
            ],
        ], $id);
        mcpProLog("RESPONSE: initialize OK");
        emitProJson($response);
        break;

    case 'notifications/initialized':
        mcpProLog("RESPONSE: notifications/initialized acknowledged");
        if ($id !== null) {
            emitProJson(jsonRpcProResult([], $id));
        }
        break;

    case 'tools/list':
        mcpProLog("RESPONSE: tools/list — returning " . count(getProToolDefinitions()) . " tools");
        emitProJson(jsonRpcProResult([
            'tools' => getProToolDefinitions(),
        ], $id));
        break;

    case 'tools/call':
        $toolName = $params['name'] ?? '';
        $toolArgs = $params['arguments'] ?? [];

        mcpProLog("TOOL CALL: {$toolName}");
        mcpProLogJson("TOOL ARGS", $toolArgs);

        if ($toolName === '') {
            mcpProLog("ERROR: Missing tool name");
            emitProJson(jsonRpcProError(-32602, 'Missing tool name.', $id));
            break;
        }

        try {
            $toolResult = executeProTool($toolName, $toolArgs);
            mcpProLogJson("TOOL RESULT", $toolResult);
            emitProJson(jsonRpcProResult($toolResult, $id));
        } catch (Throwable $e) {
            mcpProLog("EXCEPTION: " . $e->getMessage());
            mcpProLog($e->getTraceAsString());
            emitProJson(jsonRpcProError(-32603, 'Internal server error.', $id));
        }
        break;

    default:
        mcpProLog("ERROR: Unknown method — {$method}");
        emitProJson(jsonRpcProError(-32601, "Method not found: {$method}", $id));
        break;
}

mcpProLog("=== END ===");
