<?php
/**
 * Sync a voice agent prompt to Retell API.
 * Creates or updates the Retell LLM + Agent from stored config.
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
    http_response_code(403);
    echo '<div class="alert alert-danger">Invalid CSRF token.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
$pdo = db();
$promptId = (int)($_POST['id'] ?? 0);

if ($promptId <= 0) {
    echo '<div class="alert alert-danger">Invalid prompt ID.</div>';
    exit;
}

// Fetch the prompt
$stmt = $pdo->prepare("SELECT * FROM restaurant_prompts WHERE id = ? AND restaurant_id = ?");
$stmt->execute([$promptId, $restaurantId]);
$prompt = $stmt->fetch();

if (!$prompt) {
    echo '<div class="alert alert-danger">Prompt not found.</div>';
    exit;
}

if (empty($prompt['agent_prompt'])) {
    echo '<div class="alert alert-danger">Cannot sync — agent prompt is empty. Edit the agent first.</div>';
    exit;
}

// Fetch details
$detStmt = $pdo->prepare("SELECT * FROM restaurant_prompt_details WHERE restaurant_prompt_id = ? AND is_active = 1");
$detStmt->execute([$promptId]);
$details = $detStmt->fetchAll();

$messages = [];
$success = true;

// Step 1: Create or update Retell LLM
$llmPayload = retellBuildLlmPayload($prompt, $details);
$llmId = $prompt['retell_llm_id'] ?? '';

if ($llmId !== '' && $llmId !== null) {
    $llmResult = retellUpdateLlm($llmId, $llmPayload);
    if ($llmResult['success']) {
        $messages[] = 'LLM updated.';
    } else {
        $success = false;
        $messages[] = 'LLM update failed: ' . $llmResult['error'];
    }
} else {
    $llmResult = retellCreateLlm($llmPayload);
    if ($llmResult['success'] && !empty($llmResult['data']['llm_id'])) {
        $llmId = $llmResult['data']['llm_id'];
        $pdo->prepare("UPDATE restaurant_prompts SET retell_llm_id = ? WHERE id = ?")->execute([$llmId, $promptId]);
        $messages[] = 'LLM created.';
    } else {
        $success = false;
        $messages[] = 'LLM creation failed: ' . ($llmResult['error'] ?? 'Unknown error');
    }
}

// Step 2: Create or update Agent
if ($llmId !== '' && $llmId !== null) {
    $agentPayload = retellBuildAgentPayload($prompt, $llmId);
    $agentId = $prompt['agent_id'] ?? '';

    if ($agentId !== '' && $agentId !== null) {
        $agentResult = retellUpdateAgent($agentId, $agentPayload);
        if ($agentResult['success']) {
            $messages[] = 'Agent updated.';
        } else {
            $success = false;
            $messages[] = 'Agent update failed: ' . $agentResult['error'];
        }
    } else {
        $agentResult = retellCreateAgent($agentPayload);
        if ($agentResult['success'] && !empty($agentResult['data']['agent_id'])) {
            $newAgentId = $agentResult['data']['agent_id'];
            $pdo->prepare("UPDATE restaurant_prompts SET agent_id = ? WHERE id = ?")->execute([$newAgentId, $promptId]);
            $messages[] = 'Agent created (' . $newAgentId . ').';
        } else {
            $success = false;
            $messages[] = 'Agent creation failed: ' . ($agentResult['error'] ?? 'Unknown error');
        }
    }
}

// Update sync flag
if ($success) {
    $pdo->prepare("UPDATE restaurant_prompts SET is_synced = 1 WHERE id = ?")->execute([$promptId]);
}

$detail = implode(' ', $messages);
if ($success) {
    echo '<div class="alert alert-success">Synced to Retell successfully. ' . htmlspecialchars($detail) . '</div>';
} else {
    echo '<div class="alert alert-warning">Sync had issues: ' . htmlspecialchars($detail) . '</div>';
}
