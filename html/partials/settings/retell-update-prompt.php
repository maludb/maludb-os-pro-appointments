<?php
/**
 * Retell API: Update the agent prompt and begin message on the Retell LLM.
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
$promptId = (int)($_POST['id'] ?? 0);
$agentPrompt = trim($_POST['agent_prompt'] ?? '');
$beginMessage = trim($_POST['begin_message'] ?? '');

if ($promptId <= 0) {
    echo '<div class="alert alert-danger">No agent selected.</div>';
    exit;
}

if ($agentPrompt === '') {
    echo '<div class="alert alert-danger">Agent prompt cannot be empty.</div>';
    exit;
}

// Fetch local record
$stmt = $pdo->prepare("SELECT * FROM restaurant_prompts WHERE id = ? AND restaurant_id = ?");
$stmt->execute([$promptId, $restaurantId]);
$prompt = $stmt->fetch();

if (!$prompt) {
    echo '<div class="alert alert-danger">Agent not found.</div>';
    exit;
}

$llmId = $prompt['retell_llm_id'] ?? '';
if ($llmId === '') {
    echo '<div class="alert alert-danger">No Retell LLM ID. Create the agent first.</div>';
    exit;
}

// Update the LLM prompt in Retell
$payload = [
    'general_prompt' => $agentPrompt,
];
if ($beginMessage !== '') {
    $payload['begin_message'] = $beginMessage;
}

$result = retellUpdateLlm($llmId, $payload);

if (!$result['success']) {
    echo '<div class="alert alert-danger">Failed to update prompt in Retell: ' . htmlspecialchars($result['error']) . '</div>';
    exit;
}

// Update local record
$pdo->prepare("UPDATE restaurant_prompts SET agent_prompt = ?, begin_message = ? WHERE id = ?")
    ->execute([$agentPrompt, $beginMessage ?: null, $promptId]);

echo '<div class="alert alert-success">Prompt updated in Retell.</div>';
