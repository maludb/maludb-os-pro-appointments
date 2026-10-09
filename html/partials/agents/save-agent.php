<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../config/database.php';

check_auth();
$user = get_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-danger">Method not allowed</div>';
    exit;
}

// Get form data
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$agentName = trim($_POST['agent_name'] ?? '');
$prospectId = isset($_POST['prospect_id']) && $_POST['prospect_id'] !== '' ? (int)$_POST['prospect_id'] : null;
$agentRole = trim($_POST['agent_role'] ?? '');
$agentDescription = trim($_POST['agent_description'] ?? '');
$defaultTemprement = trim($_POST['default_temprement'] ?? '');
$productKnowledge = isset($_POST['product_knowledge']) ? (int)$_POST['product_knowledge'] : 5;
$technicalLevel = isset($_POST['technical_level']) ? (int)$_POST['technical_level'] : 5;
$agentPrompt = trim($_POST['agent_prompt'] ?? '');
$voiceName = trim($_POST['voice_name'] ?? '');
$llm = trim($_POST['llm'] ?? '');

// Validate required fields
if (empty($agentName)) {
    echo '<div class="alert alert-danger">Agent name is required</div>';
    exit;
}

if (empty($agentRole)) {
    echo '<div class="alert alert-danger">Agent role is required</div>';
    exit;
}

if (empty($defaultTemprement)) {
    echo '<div class="alert alert-danger">Temperament is required</div>';
    exit;
}

// Validate ranges
$productKnowledge = max(1, min(10, $productKnowledge));
$technicalLevel = max(1, min(10, $technicalLevel));

try {
    $db = Database::getInstance()->getConnection();

    if ($id > 0) {
        // Update existing agent
        $stmt = $db->prepare("SELECT id FROM prospect_agents WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            echo '<div class="alert alert-danger">Agent not found</div>';
            exit;
        }

        $stmt = $db->prepare("
            UPDATE prospect_agents SET
                agent_name = ?, prospect_id = ?, agent_role = ?,
                agent_description = ?, default_temprement = ?,
                product_knowledge = ?, technical_level = ?,
                agent_prompt = ?, updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $agentName,
            $prospectId,
            $agentRole,
            $agentDescription ?: null,
            $defaultTemprement,
            $productKnowledge,
            $technicalLevel,
            $agentPrompt ?: null,
            $id
        ]);

        $agentId = $id;
        $message = 'Agent updated successfully';
        $modalId = 'editAgentModal';
        $redirectUrl = "/partials/agents/agent-dashboard.php?id=" . $agentId;

    } else {
        // Insert new agent
        $stmt = $db->prepare("
            INSERT INTO prospect_agents (
                ts, prospect_id, agent_name, agent_description,
                agent_prompt, agent_role, default_temprement,
                product_knowledge, technical_level,
                created_by, created_at, updated_at
            ) VALUES (
                NOW(), ?, ?, ?,
                ?, ?, ?,
                ?, ?,
                ?, NOW(), NOW()
            )
        ");

        $stmt->execute([
            $prospectId,
            $agentName,
            $agentDescription ?: null,
            $agentPrompt ?: null,
            $agentRole,
            $defaultTemprement,
            $productKnowledge,
            $technicalLevel,
            $user['id']
        ]);

        $agentId = $db->lastInsertId();
        $message = 'Agent created successfully';
        $modalId = 'addAgentModal';
        $redirectUrl = "/partials/agents/index.php";
    }

    // Return success with reload trigger
    header('HX-Trigger: {"agentSaved": {"id": ' . $agentId . '}}');
    echo '<div class="alert alert-success">' . htmlspecialchars($message) . '</div>';
    echo '<script>
        setTimeout(function() {
            var modal = bootstrap.Modal.getInstance(document.getElementById("' . $modalId . '"));
            if (modal) modal.hide();
            htmx.ajax("GET", "' . $redirectUrl . '", {target: "#page-content"});
        }, 1000);
    </script>';

} catch (PDOException $e) {
    error_log("Error saving agent: " . $e->getMessage());
    echo '<div class="alert alert-danger">An error occurred while saving the agent</div>';
}
