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
if (!$modelId) {
    echo '<div class="alert alert-danger">Model ID is required</div>';
    exit;
}

if (empty($name)) {
    echo '<div class="alert alert-danger">Setting name is required</div>';
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Verify model exists
    $stmt = $db->prepare("SELECT id FROM models WHERE id = ?");
    $stmt->execute([$modelId]);
    if (!$stmt->fetch()) {
        echo '<div class="alert alert-danger">Model not found</div>';
        exit;
    }

    if ($id > 0) {
        // Update existing setting
        $stmt = $db->prepare("UPDATE model_settings SET name = ?, value = ?, updated_at = NOW() WHERE id = ? AND model_id = ?");
        $stmt->execute([$name, $value, $id, $modelId]);
        $message = 'Setting updated successfully';
        $modalId = 'editSettingModal';
    } else {
        // Insert new setting
        $stmt = $db->prepare("INSERT INTO model_settings (ts, model_id, name, value, created_by, created_at, updated_at) VALUES (NOW(), ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$modelId, $name, $value, $user['id']]);
        $message = 'Setting added successfully';
        $modalId = 'addSettingModal';
    }

    // Return success with reload trigger
    header('HX-Trigger: {"settingUpdated": {"model_id": ' . $modelId . '}}');
    echo '<div class="alert alert-success">' . htmlspecialchars($message) . '</div>';
    echo '<script>
        setTimeout(function() {
            var modal = bootstrap.Modal.getInstance(document.getElementById("' . $modalId . '"));
            if (modal) modal.hide();
            htmx.ajax("GET", "/partials/models/model-dashboard.php?id=' . $modelId . '", {target: "#page-content"});
        }, 1000);
    </script>';

} catch (PDOException $e) {
    error_log("Error saving setting: " . $e->getMessage());
    echo '<div class="alert alert-danger">An error occurred while saving</div>';
}
