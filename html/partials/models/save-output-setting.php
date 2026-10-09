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
$format = strtoupper(trim($_POST['format'] ?? ''));
$value = trim($_POST['value'] ?? '');

// Validate required fields
if (!$modelId) {
    echo '<div class="alert alert-danger">Model ID is required</div>';
    exit;
}

if (empty($format)) {
    echo '<div class="alert alert-danger">Format is required</div>';
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
        // Update existing output setting
        $stmt = $db->prepare("UPDATE model_output_settings SET format = ?, value = ?, updated_at = NOW() WHERE id = ? AND model_id = ?");
        $stmt->execute([$format, $value, $id, $modelId]);
        $message = 'Output setting updated successfully';
        $modalId = 'editOutputSettingModal';
    } else {
        // Insert new output setting
        $stmt = $db->prepare("INSERT INTO model_output_settings (ts, model_id, format, value, created_by, created_at, updated_at) VALUES (NOW(), ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$modelId, $format, $value, $user['id']]);
        $message = 'Output setting added successfully';
        $modalId = 'addOutputSettingModal';
    }

    // Return success with reload trigger
    header('HX-Trigger: {"outputSettingUpdated": {"model_id": ' . $modelId . '}}');
    echo '<div class="alert alert-success">' . htmlspecialchars($message) . '</div>';
    echo '<script>
        setTimeout(function() {
            var modal = bootstrap.Modal.getInstance(document.getElementById("' . $modalId . '"));
            if (modal) modal.hide();
            htmx.ajax("GET", "/partials/models/model-dashboard.php?id=' . $modelId . '", {target: "#page-content"});
        }, 1000);
    </script>';

} catch (PDOException $e) {
    error_log("Error saving output setting: " . $e->getMessage());
    echo '<div class="alert alert-danger">An error occurred while saving</div>';
}
