<?php
/**
 * Save (create or update) a voice agent prompt locally.
 * Does NOT call the Retell API — use the individual action buttons for that.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

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

// --- Collect form fields ---
$id               = (int)($_POST['id'] ?? 0);
$phone            = trim($_POST['phone_number'] ?? '');
$greeting         = trim($_POST['voice_agent_greeting'] ?? '');
$description      = trim($_POST['description'] ?? '');
$languageCode     = trim($_POST['language_code'] ?? 'en');
$languageName     = trim($_POST['language_name'] ?? 'English');
$isPrimaryLang    = isset($_POST['is_primary_language']) ? 1 : 0;

// Retell config fields
$model            = trim($_POST['model'] ?? 'gpt-4.1');
$voiceId          = trim($_POST['voice_id'] ?? '');
$voiceModel       = trim($_POST['voice_model'] ?? '');
$agentPrompt      = trim($_POST['agent_prompt'] ?? '');
$beginMessage     = trim($_POST['begin_message'] ?? '');
$transferNumber   = trim($_POST['transfer_number'] ?? '');
$agentSwapId      = trim($_POST['agent_swap_id'] ?? '');
$webhookUrl       = trim($_POST['webhook_url'] ?? '');
$inboundWebhookUrl = trim($_POST['inbound_webhook_url'] ?? '');
$mcpUrl           = trim($_POST['mcp_url'] ?? '');
$mcpHeaders       = trim($_POST['mcp_headers'] ?? '');

// Collect variable details from form
$varNames  = $_POST['var_names'] ?? [];
$varValues = $_POST['var_values'] ?? [];
$detailIds = $_POST['detail_ids'] ?? [];

try {
    $pdo->beginTransaction();

    // If marking as primary, clear other primary flags for this restaurant first
    if ($isPrimaryLang) {
        $clearStmt = $pdo->prepare(
            "UPDATE restaurant_prompts SET is_primary_language = 0 WHERE restaurant_id = ?" . ($id > 0 ? " AND id != ?" : "")
        );
        $clearStmt->execute($id > 0 ? [$restaurantId, $id] : [$restaurantId]);
    }

    if ($id <= 0) {
        echo '<div class="alert alert-danger">Use the "Create Agent" button to create a new agent.</div>';
        exit;
    }

    // Update existing — verify ownership, never overwrite agent_id or retell_llm_id
    $stmt = $pdo->prepare(
        "UPDATE restaurant_prompts
         SET phone_number = ?, voice_agent_greeting = ?, description = ?,
             language_code = ?, language_name = ?, is_primary_language = ?,
             model = ?, voice_id = ?, voice_model = ?, agent_prompt = ?, begin_message = ?,
             transfer_number = ?, agent_swap_id = ?, webhook_url = ?, inbound_webhook_url = ?,
             mcp_url = ?, mcp_headers = ?
         WHERE id = ? AND restaurant_id = ?"
    );
    $stmt->execute([
        $phone ?: null, $greeting ?: null, $description ?: null,
        $languageCode, $languageName, $isPrimaryLang,
        $model, $voiceId ?: null, $voiceModel ?: null, $agentPrompt ?: null, $beginMessage ?: null,
        $transferNumber ?: null, $agentSwapId ?: null, $webhookUrl ?: null, $inboundWebhookUrl ?: null,
        $mcpUrl ?: null, $mcpHeaders ?: null,
        $id, $restaurantId
    ]);
    $promptId = $id;

    // --- Save prompt details (variables) ---
    $stmt = $pdo->prepare("DELETE FROM restaurant_prompt_details WHERE restaurant_prompt_id = ?");
    $stmt->execute([$promptId]);

    $insertDetail = $pdo->prepare(
        "INSERT INTO restaurant_prompt_details (restaurant_prompt_id, variable_name, variable_value, is_active)
         VALUES (?, ?, ?, 1)"
    );
    for ($i = 0; $i < count($varNames); $i++) {
        $vName  = trim($varNames[$i] ?? '');
        $vValue = trim($varValues[$i] ?? '');
        if ($vName !== '') {
            $insertDetail->execute([$promptId, $vName, $vValue]);
        }
    }

    $pdo->commit();

    echo '<div class="alert alert-success">Voice agent saved. Use the Retell API action buttons on the card to push changes to Retell.</div>';

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (str_contains($e->getMessage(), 'Duplicate entry')) {
        echo '<div class="alert alert-danger">An agent with this ID already exists for this restaurant.</div>';
    } else {
        echo '<div class="alert alert-danger">Error saving: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}
