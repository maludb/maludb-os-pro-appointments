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

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$modelId = isset($_POST['model_id']) ? (int)$_POST['model_id'] : 0;
$name = trim($_POST['name'] ?? '');
$value = trim($_POST['value'] ?? '');

// Validate required fields
if (!$id) {
    echo '<div class="alert alert-danger">Prompt ID is required</div>';
    exit;
}

if (!$modelId) {
    echo '<div class="alert alert-danger">Model ID is required</div>';
    exit;
}

if (empty($name)) {
    echo '<div class="alert alert-danger">Prompt name is required</div>';
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Verify prompt exists
    $stmt = $db->prepare("SELECT id FROM model_prompts WHERE id = ? AND model_id = ?");
    $stmt->execute([$id, $modelId]);
    if (!$stmt->fetch()) {
        echo '<div class="alert alert-danger">Prompt not found</div>';
        exit;
    }

    // Update prompt
    $stmt = $db->prepare("UPDATE model_prompts SET name = ?, value = ?, updated_at = NOW() WHERE id = ? AND model_id = ?");
    $stmt->execute([$name, $value, $id, $modelId]);

    // Return success with reload trigger
    header('HX-Trigger: {"promptUpdated": {"id": ' . $id . ', "model_id": ' . $modelId . '}}');
    echo '<div class="alert alert-success">Prompt updated successfully</div>';
    echo '<script>
        setTimeout(function() {
            var modal = bootstrap.Modal.getInstance(document.getElementById("editModelPromptModal"));
            if (modal) modal.hide();
            htmx.ajax("GET", "/partials/models/model-dashboard.php?id=' . $modelId . '", {target: "#page-content"});
        }, 1000);
    </script>';

} catch (PDOException $e) {
    error_log("Error updating prompt: " . $e->getMessage());
    echo '<div class="alert alert-danger">An error occurred while saving</div>';
}
