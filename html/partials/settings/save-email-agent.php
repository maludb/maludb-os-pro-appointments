<?php
/**
 * Save (create or update) an email agent prompt.
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

$id           = (int)($_POST['id'] ?? 0);
$agentId      = trim($_POST['agent_id'] ?? '');
$email        = trim($_POST['email_address'] ?? '');
$greeting     = trim($_POST['email_agent_greeting'] ?? '');
$description  = trim($_POST['description'] ?? '');

if ($agentId === '') {
    echo '<div class="alert alert-danger">Agent ID is required.</div>';
    exit;
}

// Collect variable details from form
$varNames  = $_POST['var_names'] ?? [];
$varValues = $_POST['var_values'] ?? [];
$detailIds = $_POST['detail_ids'] ?? [];

try {
    $pdo->beginTransaction();

    if ($id > 0) {
        // Update existing — verify ownership
        $stmt = $pdo->prepare(
            "UPDATE email_agent_prompts
             SET agent_id = ?, email_address = ?, email_agent_greeting = ?, description = ?
             WHERE id = ? AND restaurant_id = ?"
        );
        $stmt->execute([$agentId, $email ?: null, $greeting ?: null, $description ?: null, $id, $restaurantId]);
        $promptId = $id;
    } else {
        // Insert new
        $stmt = $pdo->prepare(
            "INSERT INTO email_agent_prompts (restaurant_id, agent_id, email_address, email_agent_greeting, description)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$restaurantId, $agentId, $email ?: null, $greeting ?: null, $description ?: null]);
        $promptId = (int)$pdo->lastInsertId();
    }

    // --- Save prompt details (variables) ---
    $stmt = $pdo->prepare("DELETE FROM email_agent_prompt_details WHERE email_agent_prompt_id = ?");
    $stmt->execute([$promptId]);

    $insertDetail = $pdo->prepare(
        "INSERT INTO email_agent_prompt_details (email_agent_prompt_id, variable_name, variable_value, is_active)
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
    echo '<div class="alert alert-success">Email agent ' . ($id > 0 ? 'updated' : 'created') . '.</div>';
} catch (PDOException $e) {
    $pdo->rollBack();
    if (str_contains($e->getMessage(), 'Duplicate entry')) {
        echo '<div class="alert alert-danger">An email agent with this ID already exists for this restaurant.</div>';
    } else {
        echo '<div class="alert alert-danger">Error saving email agent: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}
