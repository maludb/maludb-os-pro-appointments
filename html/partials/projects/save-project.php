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
$name = trim($_POST['name'] ?? '');
$stub = trim($_POST['stub'] ?? '');
$description = trim($_POST['description'] ?? '');
$geography = isset($_POST['geography']) && $_POST['geography'] !== '' ? (int)$_POST['geography'] : null;
$members = isset($_POST['members']) && $_POST['members'] !== '' ? (int)$_POST['members'] : 1;
$monthlySpendLimit = isset($_POST['monthly_spend_limit']) && $_POST['monthly_spend_limit'] !== '' ? (int)$_POST['monthly_spend_limit'] : null;

// Validate required fields
if (empty($name)) {
    echo '<div class="alert alert-danger">Project name is required</div>';
    exit;
}

// Generate stub from name if not provided
if (empty($stub)) {
    $stub = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
    $stub = trim($stub, '-');
}

try {
    $db = Database::getInstance()->getConnection();

    if ($id > 0) {
        // Update existing project
        // Verify ownership
        $stmt = $db->prepare("SELECT id FROM projects WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user['id']]);
        if (!$stmt->fetch()) {
            echo '<div class="alert alert-danger">Project not found</div>';
            exit;
        }

        $stmt = $db->prepare("
            UPDATE projects SET
                name = ?, stub = ?, description = ?,
                geography = ?, members = ?, monthly_spend_limit = ?,
                updated_at = NOW()
            WHERE id = ? AND user_id = ?
        ");

        $stmt->execute([
            $name,
            $stub,
            $description,
            $geography,
            $members,
            $monthlySpendLimit,
            $id,
            $user['id']
        ]);

        $projectId = $id;
        $message = 'Project updated successfully';
        $modalId = 'editProjectModal';
        $redirectUrl = "/partials/projects/view.php?id=" . $projectId;

    } else {
        // Insert new project
        $stmt = $db->prepare("
            INSERT INTO projects (
                ts, user_id, org_id, name, stub, description,
                geography, members, monthly_spend_limit, monthly_spend,
                created_by, created_at, updated_at
            ) VALUES (
                NOW(), ?, ?, ?, ?, ?,
                ?, ?, ?, 0,
                ?, NOW(), NOW()
            )
        ");

        $stmt->execute([
            $user['id'],
            $user['org_id'] ?? null,
            $name,
            $stub,
            $description,
            $geography,
            $members,
            $monthlySpendLimit,
            $user['id']
        ]);

        $projectId = $db->lastInsertId();
        $message = 'Project created successfully';
        $modalId = 'addProjectModal';
        $redirectUrl = "/partials/projects/index.php";
    }

    // Return success with reload trigger
    header('HX-Trigger: {"projectSaved": {"id": ' . $projectId . '}}');
    echo '<div class="alert alert-success">' . htmlspecialchars($message) . '</div>';
    echo '<script>
        setTimeout(function() {
            var modal = bootstrap.Modal.getInstance(document.getElementById("' . $modalId . '"));
            if (modal) modal.hide();
            htmx.ajax("GET", "' . $redirectUrl . '", {target: "#page-content"});
        }, 1000);
    </script>';

} catch (PDOException $e) {
    error_log("Error saving project: " . $e->getMessage());
    echo '<div class="alert alert-danger">An error occurred while saving the project</div>';
}
