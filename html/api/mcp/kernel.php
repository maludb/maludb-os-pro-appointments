<?php
/**
 * MCP server for the Business OS kernel alone — /api/mcp/kernel.php (JSON-RPC 2.0 over HTTP).
 *
 * The kernel calls as itself with its 60-second kernel token (Authorization: Bearer kernel.…) and sees one tool,
 * app_roles — the roles this application offers and the rights each gives (os.app-roles/1) — plus whatever
 * maludb-os.json shares[] names (none yet). Nothing else answers here: the receptionist's tools stay on
 * /api/mcp/pro.php and /api/mcp/sms.php under a business's own MCP key, and people and agents never reach this file.
 * Answers 404 while OS_ENABLED is off.
 */
require_once __DIR__ . '/../../../helpers/os.php';
require_once __DIR__ . '/../../../helpers/api-auth.php';

header('Content-Type: application/json');

function kernelMcpEmit(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
function kernelMcpError(int $code, string $message, $id = null): void
{
    kernelMcpEmit(['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]]);
}

if (!os_enabled()) {
    http_response_code(404);
    kernelMcpError(-32000, 'Not found.');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    kernelMcpError(-32600, 'POST a JSON-RPC 2.0 request.');
    exit;
}
if (!os_verify_kernel_token(request_bearer_token())) {
    http_response_code(401);
    header('WWW-Authenticate: Bearer');
    kernelMcpError(-32000, 'Unauthorized: this server answers the operating system\'s kernel token only.');
    exit;
}

$request = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($request) || ($request['jsonrpc'] ?? '') !== '2.0' || empty($request['method'])) {
    http_response_code(400);
    kernelMcpError(-32600, 'Invalid Request');
    exit;
}
$method = (string)$request['method'];
$params = is_array($request['params'] ?? null) ? $request['params'] : [];
$id = $request['id'] ?? null;
if (!array_key_exists('id', $request)) {            // a notification: nothing to answer
    http_response_code(202);
    exit;
}

$appRolesTool = ['name' => 'app_roles', 'description' => 'The roles this application offers and the rights each gives (os.app-roles/1).',
    'inputSchema' => ['type' => 'object', 'properties' => new stdClass()],
    'annotations' => ['title' => 'Roles and rights', 'readOnlyHint' => true, 'openWorldHint' => false]];

switch ($method) {
    case 'initialize':
        kernelMcpEmit(['jsonrpc' => '2.0', 'id' => $id, 'result' => [
            'protocolVersion' => in_array($params['protocolVersion'] ?? '', ['2025-06-18', '2025-03-26', '2024-11-05'], true) ? $params['protocolVersion'] : '2025-06-18',
            'capabilities' => ['tools' => ['listChanged' => false]],
            'serverInfo' => ['name' => 'Pro Appointments (for the kernel)', 'version' => '1.0.0'],
        ]]);
        break;
    case 'ping':
        kernelMcpEmit(['jsonrpc' => '2.0', 'id' => $id, 'result' => new stdClass()]);
        break;
    case 'tools/list':
        kernelMcpEmit(['jsonrpc' => '2.0', 'id' => $id, 'result' => ['tools' => [$appRolesTool]]]);
        break;
    case 'tools/call':
        if ((string)($params['name'] ?? '') !== 'app_roles') {
            kernelMcpError(-32602, 'The kernel may call app_roles only.', $id);
            break;
        }
        $doc = app_roles_document();
        kernelMcpEmit(['jsonrpc' => '2.0', 'id' => $id, 'result' => ['isError' => false, 'structuredContent' => $doc,
            'content' => [['type' => 'text', 'text' => json_encode($doc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]]]]);
        break;
    default:
        kernelMcpError(-32601, "Method not found: {$method}", $id);
}
