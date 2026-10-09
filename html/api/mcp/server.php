<?php
/**
 * MCP (Model Context Protocol) Server — Streamable HTTP
 *
 * JSON-RPC 2.0 over HTTP. Exposes reservation tools for Retell AI,
 * Claude, or any MCP-compatible client.
 *
 * Auth: Bearer token in Authorization header (setting: 'mcp_api_key')
 */

require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/api-auth.php';
require_once __DIR__ . '/../../../helpers/availability.php';
require_once __DIR__ . '/server-tools.php';

header('Content-Type: application/json');

// --- Logging ---
define('MCP_LOG_FILE', __DIR__ . '/../../../logs/mcp-server.log');

function mcpLog(string $message): void
{
    $ts = date('Y-m-d H:i:s');
    file_put_contents(MCP_LOG_FILE, "[{$ts}] {$message}\n", FILE_APPEND);
}

function mcpLogJson(string $label, $data): void
{
    mcpLog("{$label}: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

// --- Auth ---
function verifyMcpAuth(): void
{
    // Every request needs the MCP API key of an active business (Settings > Integrations).
    if (mcp_business_for_request() === null) {
        mcpLog("AUTH FAILED: no key or key does not match a business");
        http_response_code(401);
        emitMcpJson([
            'jsonrpc' => '2.0',
            'error' => ['code' => -32000, 'message' => 'Unauthorized'],
            'id' => null,
        ]);
        exit;
    }
}

// --- JSON-RPC Handler ---
function jsonRpcError(int $code, string $message, $id = null): array
{
    return [
        'jsonrpc' => '2.0',
        'error' => ['code' => $code, 'message' => $message],
        'id' => $id,
    ];
}

function jsonRpcResult($result, $id): array
{
    return [
        'jsonrpc' => '2.0',
        'result' => $result,
        'id' => $id,
    ];
}

function emitMcpJson(array $payload): void
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
mcpLog("=== REQUEST: {$_SERVER['REQUEST_METHOD']} {$_SERVER['REQUEST_URI']} ===");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mcpLog("ERROR: Method not allowed — {$_SERVER['REQUEST_METHOD']}");
    http_response_code(405);
    emitMcpJson(jsonRpcError(-32600, 'Only POST requests are accepted.'));
    exit;
}

verifyMcpAuth();

$rawBody = file_get_contents('php://input');
mcpLog("BODY: " . strlen((string)$rawBody) . " bytes");

$request = json_decode($rawBody, true);

if (!is_array($request) || !isset($request['method'])) {
    mcpLog("ERROR: Invalid JSON-RPC request");
    emitMcpJson(jsonRpcError(-32700, 'Parse error: invalid JSON-RPC request.'));
    exit;
}

$method = $request['method'];
$params = $request['params'] ?? [];
$id = $request['id'] ?? null;

mcpLog("METHOD: {$method} | ID: " . json_encode($id));

switch ($method) {
    case 'initialize':
        $response = jsonRpcResult([
            'protocolVersion' => '2024-11-05',
            'capabilities' => [
                'tools' => ['listChanged' => false],
            ],
            'serverInfo' => [
                'name' => 'ZozoCal Restaurant Reservations',
                'version' => '1.0.0',
            ],
        ], $id);
        mcpLog("RESPONSE: initialize OK");
        emitMcpJson($response);
        break;

    case 'notifications/initialized':
        mcpLog("RESPONSE: notifications/initialized acknowledged");
        // Notification — no response needed, but send empty result if id present
        if ($id !== null) {
            emitMcpJson(jsonRpcResult([], $id));
        }
        break;

    case 'tools/list':
        mcpLog("RESPONSE: tools/list — returning " . count(getToolDefinitions()) . " tools");
        emitMcpJson(jsonRpcResult([
            'tools' => getToolDefinitions(),
        ], $id));
        break;

    case 'tools/call':
        $toolName = $params['name'] ?? '';
        $toolArgs = $params['arguments'] ?? [];

        mcpLog("TOOL CALL: {$toolName}");
        mcpLogJson("TOOL ARGS", $toolArgs);

        if ($toolName === '') {
            mcpLog("ERROR: Missing tool name");
            emitMcpJson(jsonRpcError(-32602, 'Missing tool name.', $id));
            break;
        }

        try {
            $toolResult = executeTool($toolName, $toolArgs);
            mcpLogJson("TOOL RESULT", $toolResult);
            emitMcpJson(jsonRpcResult($toolResult, $id));
        } catch (Throwable $e) {
            mcpLog("EXCEPTION: " . $e->getMessage());
            mcpLog($e->getTraceAsString());
            emitMcpJson(jsonRpcError(-32603, 'Internal server error.', $id));
        }
        break;

    default:
        mcpLog("ERROR: Unknown method — {$method}");
        emitMcpJson(jsonRpcError(-32601, "Method not found: {$method}", $id));
        break;
}

mcpLog("=== END ===");
