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
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$maxIterations = isset($_POST['max_iterations']) && $_POST['max_iterations'] !== '' ? (int)$_POST['max_iterations'] : null;
$maxConcurrentResearchers = isset($_POST['max_concurrent_researchers']) && $_POST['max_concurrent_researchers'] !== '' ? (int)$_POST['max_concurrent_researchers'] : null;
$qualityThreshold = isset($_POST['quality_threshold']) && $_POST['quality_threshold'] !== '' ? (float)$_POST['quality_threshold'] : null;

// Validate required fields
if (!$id) {
    echo '<div class="alert alert-danger">Model ID is required</div>';
    exit;
}

if (empty($name)) {
    echo '<div class="alert alert-danger">Name is required</div>';
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Verify model exists
    $stmt = $db->prepare("SELECT id FROM models WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        echo '<div class="alert alert-danger">Model not found</div>';
        exit;
    }

    // Update model
    $stmt = $db->prepare("UPDATE models SET name = ?, description = ?, max_iterations = ?, max_concurrent_researchers = ?, quality_threshold = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$name, $description, $maxIterations, $maxConcurrentResearchers, $qualityThreshold, $id]);

    // Return success with reload trigger
    header('HX-Trigger: {"modelUpdated": {"id": ' . $id . '}}');
    echo '<div class="alert alert-success">Model updated successfully</div>';
    echo '<script>
        setTimeout(function() {
            var modal = bootstrap.Modal.getInstance(document.getElementById("editModelModal"));
            if (modal) modal.hide();
            htmx.ajax("GET", "/partials/models/model-dashboard.php?id=' . $id . '", {target: "#page-content"});
        }, 1000);
    </script>';

} catch (PDOException $e) {
    error_log("Error updating model: " . $e->getMessage());
    echo '<div class="alert alert-danger">An error occurred while saving</div>';
}
