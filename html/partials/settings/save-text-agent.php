<?php
/**
 * Save (create or update) a text agent prompt.
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

$id            = (int)($_POST['id'] ?? 0);
$phone         = trim($_POST['phone_number'] ?? '');
$description   = trim($_POST['description'] ?? '');
$customWebhook = trim($_POST['custom_webhook'] ?? '');
$systemPrompt  = trim($_POST['system_prompt'] ?? '');

if ($phone === '') {
    echo '<div class="alert alert-danger">Phone number is required.</div>';
    exit;
}

// Agent ID is automatically the phone number
$agentId = $phone;

// Collect variable details from form
$varNames  = $_POST['var_names'] ?? [];
$varValues = $_POST['var_values'] ?? [];
$detailIds = $_POST['detail_ids'] ?? [];

try {
    $pdo->beginTransaction();

    if ($id > 0) {
        // Update existing — verify ownership
        $stmt = $pdo->prepare(
            "UPDATE text_agent_prompts
             SET agent_id = ?, phone_number = ?, description = ?, system_prompt = ?, custom_webhook = ?
             WHERE id = ? AND restaurant_id = ?"
        );
        $stmt->execute([$agentId, $phone, $description ?: null, $systemPrompt ?: null, $customWebhook ?: null, $id, $restaurantId]);
        $promptId = $id;
    } else {
        // Insert new
        $stmt = $pdo->prepare(
            "INSERT INTO text_agent_prompts (restaurant_id, agent_id, phone_number, description, system_prompt, custom_webhook)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$restaurantId, $agentId, $phone, $description ?: null, $systemPrompt ?: null, $customWebhook ?: null]);
        $promptId = (int)$pdo->lastInsertId();
    }

    // --- Save prompt details (variables) ---
    // Remove existing details for this prompt, then re-insert
    $stmt = $pdo->prepare("DELETE FROM text_agent_prompt_details WHERE text_agent_prompt_id = ?");
    $stmt->execute([$promptId]);

    $insertDetail = $pdo->prepare(
        "INSERT INTO text_agent_prompt_details (text_agent_prompt_id, variable_name, variable_value, is_active)
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
    echo '<div class="alert alert-success">Text agent ' . ($id > 0 ? 'updated' : 'created') . '.</div>';
} catch (PDOException $e) {
    $pdo->rollBack();
    if (str_contains($e->getMessage(), 'Duplicate entry')) {
        echo '<div class="alert alert-danger">A text agent with this ID already exists for this restaurant.</div>';
    } else {
        echo '<div class="alert alert-danger">Error saving text agent: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}
