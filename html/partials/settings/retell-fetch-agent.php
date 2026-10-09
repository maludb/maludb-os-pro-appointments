<?php
/**
 * Retell API: Fetch current agent + LLM config from Retell.
 * Returns JSON with all fields so the edit form can be populated.
 * Also updates the local database record with the fetched values.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../helpers/retell-api.php';

requireAuth();
if (!isAdmin() && !isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

header('Content-Type: application/json');

$restaurantId = currentRestaurantId();
$pdo = db();
$promptId = (int)($_GET['id'] ?? 0);

if ($promptId <= 0) {
    echo json_encode(['error' => 'Missing prompt ID']);
    exit;
}

// Fetch local record
$stmt = $pdo->prepare("SELECT * FROM restaurant_prompts WHERE id = ? AND restaurant_id = ?");
$stmt->execute([$promptId, $restaurantId]);
$prompt = $stmt->fetch();

if (!$prompt) {
    echo json_encode(['error' => 'Agent not found']);
    exit;
}

$agentId = $prompt['agent_id'] ?? '';
$llmId = $prompt['retell_llm_id'] ?? '';

if ($agentId === '') {
    echo json_encode(['error' => 'No Retell agent ID on this record']);
    exit;
}

// Fetch agent from Retell
$agentResult = retellGetAgent($agentId);
if (!$agentResult['success']) {
    echo json_encode(['error' => 'Failed to fetch agent: ' . $agentResult['error']]);
    exit;
}

$agent = $agentResult['data'];

// Extract agent-level fields
$fetched = [
    'agent_id' => $agent['agent_id'] ?? $agentId,
    'description' => $agent['agent_name'] ?? '',
    'voice_id' => $agent['voice_id'] ?? '',
    'voice_model' => $agent['voice_model'] ?? '',
    'language_code' => '',
    'webhook_url' => $agent['webhook_url'] ?? '',
];

// Map Retell locale back to short code
$langMap = [
    'en-US' => 'en', 'es-ES' => 'es', 'fr-FR' => 'fr', 'pt-BR' => 'pt',
    'de-DE' => 'de', 'it-IT' => 'it', 'zh-CN' => 'zh', 'ja-JP' => 'ja',
    'ko-KR' => 'ko', 'pl-PL' => 'pl', 'ro-RO' => 'ro', 'ar-SA' => 'ar',
    'hi-IN' => 'hi',
];
$retellLang = $agent['language'] ?? '';
$fetched['language_code'] = $langMap[$retellLang] ?? 'en';

// Get LLM ID from agent's response_engine if we don't have it
$responseLlmId = $agent['response_engine']['llm_id'] ?? '';
if ($llmId === '' && $responseLlmId !== '') {
    $llmId = $responseLlmId;
}
$fetched['retell_llm_id'] = $llmId;

// Fetch LLM from Retell
if ($llmId !== '') {
    $llmResult = retellGetLlm($llmId);
    if ($llmResult['success']) {
        $llm = $llmResult['data'];
        $fetched['model'] = $llm['model'] ?? 'gpt-4.1';
        $fetched['agent_prompt'] = $llm['general_prompt'] ?? '';
        $fetched['begin_message'] = $llm['begin_message'] ?? '';

        // Extract transfer_number and agent_swap_id from tools
        $tools = $llm['general_tools'] ?? [];
        foreach ($tools as $tool) {
            if (($tool['type'] ?? '') === 'transfer_call') {
                $fetched['transfer_number'] = $tool['transfer_destination']['number'] ?? '';
            }
            if (($tool['type'] ?? '') === 'agent_swap') {
                $fetched['agent_swap_id'] = $tool['agent_id'] ?? '';
            }
        }

        // Extract MCP config
        $mcps = $llm['mcps'] ?? [];
        if (!empty($mcps[0])) {
            $fetched['mcp_url'] = $mcps[0]['url'] ?? '';
            $fetched['mcp_headers'] = !empty($mcps[0]['headers']) ? json_encode($mcps[0]['headers']) : '';
        }

        // Extract default dynamic variables as custom variables
        $dynVars = $llm['default_dynamic_variables'] ?? [];
        $fetched['dynamic_variables'] = $dynVars;
    }
}

// Update local DB with fetched values
$langNameMap = [
    'en' => 'English', 'es' => 'Spanish', 'fr' => 'French', 'pt' => 'Portuguese',
    'de' => 'German', 'it' => 'Italian', 'zh' => 'Chinese', 'ja' => 'Japanese',
    'ko' => 'Korean', 'pl' => 'Polish', 'ro' => 'Romanian', 'ar' => 'Arabic',
    'hi' => 'Hindi',
];

$updStmt = $pdo->prepare(
    "UPDATE restaurant_prompts SET
        retell_llm_id = ?, description = ?, voice_id = ?, voice_model = ?,
        language_code = ?, language_name = ?, model = ?, agent_prompt = ?, begin_message = ?,
        transfer_number = ?, agent_swap_id = ?, webhook_url = ?,
        mcp_url = ?, mcp_headers = ?
     WHERE id = ? AND restaurant_id = ?"
);
$updStmt->execute([
    $fetched['retell_llm_id'] ?? null,
    $fetched['description'] ?: null,
    $fetched['voice_id'] ?: null,
    $fetched['voice_model'] ?: null,
    $fetched['language_code'] ?: 'en',
    $langNameMap[$fetched['language_code']] ?? 'English',
    $fetched['model'] ?? 'gpt-4.1',
    $fetched['agent_prompt'] ?? null,
    $fetched['begin_message'] ?? null,
    $fetched['transfer_number'] ?? null,
    $fetched['agent_swap_id'] ?? null,
    $fetched['webhook_url'] ?? null,
    $fetched['mcp_url'] ?? null,
    $fetched['mcp_headers'] ?? null,
    $promptId, $restaurantId,
]);

echo json_encode(['success' => true, 'data' => $fetched]);
