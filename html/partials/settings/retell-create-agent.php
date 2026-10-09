<?php
/**
 * Retell API: Create a new agent.
 * 1. Creates a minimal LLM in Retell
 * 2. Creates an Agent in Retell linked to that LLM
 * 3. Creates the local restaurant_prompts record with the returned IDs
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../helpers/retell-api.php';

requireAuth();
if (!isAdmin() && !isSuperAdmin()) {
    http_response_code(403);
    exit('Forbidden');
}
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo '<div class="alert alert-danger">Invalid CSRF token.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
$pdo = db();

// Logging
$logFile = __DIR__ . '/../../../logs/retell-create-agent.log';
function clog(string $msg) {
    global $logFile;
    file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

$agentName = trim($_POST['agent_name'] ?? '');
if ($agentName === '') {
    echo '<div class="alert alert-danger">Agent name is required.</div>';
    exit;
}

clog('=== CREATE AGENT START === name: ' . $agentName);

// Step 1: Create a minimal Retell LLM
$llmPayload = [
    'model' => 'gpt-4.1',
    'general_prompt' => 'You are a helpful voice assistant.',
    'start_speaker' => 'agent',
    'general_tools' => [
        [
            'type' => 'end_call',
            'name' => 'end_call',
            'description' => 'End the call when the conversation is complete or the caller says goodbye.',
        ],
    ],
];

clog('LLM payload: ' . json_encode($llmPayload));
$llmResult = retellCreateLlm($llmPayload);
clog('LLM result: ' . json_encode($llmResult));

if (!$llmResult['success']) {
    echo '<div class="alert alert-danger">Failed to create Retell LLM: ' . htmlspecialchars($llmResult['error']) . '</div>';
    exit;
}

$llmId = $llmResult['data']['llm_id'] ?? '';
if ($llmId === '') {
    echo '<div class="alert alert-danger">Retell returned success but no llm_id.</div>';
    exit;
}
clog('LLM created: ' . $llmId);

// Step 2: Create Agent linked to that LLM
$agentPayload = [
    'response_engine' => [
        'type' => 'retell-llm',
        'llm_id' => $llmId,
    ],
    'voice_id' => 'retell-Cimo',
    'agent_name' => $agentName,
    'language' => 'en-US',
];

clog('Agent payload: ' . json_encode($agentPayload));
$agentResult = retellCreateAgent($agentPayload);
clog('Agent result: ' . json_encode($agentResult));

if (!$agentResult['success']) {
    // Clean up: delete the orphaned LLM
    retellDeleteLlm($llmId);
    echo '<div class="alert alert-danger">Failed to create Retell Agent: ' . htmlspecialchars($agentResult['error']) . '</div>';
    exit;
}

$agentId = $agentResult['data']['agent_id'] ?? '';
if ($agentId === '') {
    retellDeleteLlm($llmId);
    echo '<div class="alert alert-danger">Retell returned success but no agent_id.</div>';
    exit;
}

// Step 3: Set webhook URL on the agent
$webhookUrl = 'https://zozocal.com/api/retell/pro-webhook.php?id=' . $agentId;
clog('Setting webhook: ' . $webhookUrl);

$webhookResult = retellUpdateAgent($agentId, [
    'webhook_url' => $webhookUrl,
    'webhook_events' => ['call_started', 'call_ended', 'call_analyzed'],
]);
clog('Webhook result: ' . json_encode($webhookResult));

$postMessages = [];
if ($webhookResult['success']) {
    $postMessages[] = 'Webhook set.';
} else {
    $postMessages[] = 'Webhook failed: ' . $webhookResult['error'];
}

// Step 4: Add MCP server to the LLM
$mcpUrl = 'https://zozocal.com/api/mcp/pro.php';
clog('Setting MCP: ' . $mcpUrl);

$mcpResult = retellUpdateLlm($llmId, [
    'mcps' => [
        [
            'name' => 'professional_mcp',
            'url' => $mcpUrl,
        ],
    ],
]);
clog('MCP result: ' . json_encode($mcpResult));

if ($mcpResult['success']) {
    $postMessages[] = 'MCP server set.';

    // Step 5: Fetch available MCP tools and enable the ones we need
    $mcpToolsResult = retellApiCall('GET', '/get-mcp-tools/' . urlencode($llmId) . '?mcp_id=professional_mcp');
    clog('MCP tools result: ' . json_encode($mcpToolsResult));

    $enabledToolNames = [
        'list_services',
        'book_appointment',
        'lookup_appointment',
        'cancel_appointment',
        'confirm_appointment',
        'modify_appointment',
        'update_client_preferences',
    ];

    if ($mcpToolsResult['success'] && is_array($mcpToolsResult['data'])) {
        $mcpTools = [];
        foreach ($mcpToolsResult['data'] as $tool) {
            $toolName = $tool['name'] ?? '';
            if (in_array($toolName, $enabledToolNames)) {
                $mcpTools[] = [
                    'type' => 'mcp',
                    'mcp_id' => 'professional_mcp',
                    'name' => $toolName,
                    'description' => $tool['description'] ?? $toolName,
                    'input_schema' => $tool['inputSchema'] ?? $tool['input_schema'] ?? new \stdClass(),
                ];
            }
        }

        if (!empty($mcpTools)) {
            // Get existing tools and merge with MCP tools
            $existingLlm = retellGetLlm($llmId);
            $existingTools = $existingLlm['success'] ? ($existingLlm['data']['general_tools'] ?? []) : [];
            $allTools = array_merge($existingTools, $mcpTools);

            $toolsResult = retellUpdateLlm($llmId, ['general_tools' => $allTools]);
            clog('Enable MCP tools result: ' . json_encode($toolsResult));

            if ($toolsResult['success']) {
                $postMessages[] = count($mcpTools) . ' MCP tools enabled.';
            } else {
                $postMessages[] = 'MCP tools enable failed: ' . $toolsResult['error'];
            }
        } else {
            $postMessages[] = 'No matching MCP tools found to enable.';
        }
    } else {
        $postMessages[] = 'Could not fetch MCP tools: ' . ($mcpToolsResult['error'] ?? 'unknown');
    }
} else {
    $postMessages[] = 'MCP failed: ' . $mcpResult['error'];
}

// Step 6: Create local record
try {
    $stmt = $pdo->prepare(
        "INSERT INTO restaurant_prompts (restaurant_id, agent_id, retell_llm_id, description, language_code, language_name, model, webhook_url, mcp_url)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$restaurantId, $agentId, $llmId, $agentName, 'en', 'English', 'gpt-4.1', $webhookUrl, $mcpUrl]);
} catch (PDOException $e) {
    echo '<div class="alert alert-warning">Agent created in Retell but failed to save locally: ' . htmlspecialchars($e->getMessage())
        . '<br>Agent ID: <code>' . htmlspecialchars($agentId) . '</code> LLM ID: <code>' . htmlspecialchars($llmId) . '</code></div>';
    exit;
}

$postDetail = !empty($postMessages) ? '<br><small>' . htmlspecialchars(implode(' ', $postMessages)) . '</small>' : '';
echo '<div class="alert alert-success">Agent created.<br>'
    . 'Agent ID: <code>' . htmlspecialchars($agentId) . '</code><br>'
    . 'LLM ID: <code>' . htmlspecialchars($llmId) . '</code>'
    . $postDetail . '</div>';
