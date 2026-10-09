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
$businessType = trim($_POST['business_type'] ?? '');
$stub = trim($_POST['stub'] ?? '');
$description = trim($_POST['description'] ?? '');
$members = isset($_POST['members']) && $_POST['members'] !== '' ? (int)$_POST['members'] : 1;
$monthlySpendLimit = isset($_POST['monthly_spend_limit']) && $_POST['monthly_spend_limit'] !== '' ? (int)$_POST['monthly_spend_limit'] : 0;
$agentId = trim($_POST['agent_id'] ?? '');

// Validate required fields
if (empty($name)) {
    echo '<div class="alert alert-danger">Prospect name is required</div>';
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
        // Update existing prospect
        $stmt = $db->prepare("SELECT id FROM prospects WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            echo '<div class="alert alert-danger">Prospect not found</div>';
            exit;
        }

        $stmt = $db->prepare("
            UPDATE prospects SET
                name = ?, business_type = ?, stub = ?, description = ?,
                members = ?, monthly_spend_limit = ?, agent_id = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $name,
            $businessType ?: null,
            $stub,
            $description ?: null,
            $members,
            $monthlySpendLimit,
            $agentId ?: null,
            $id
        ]);

        $prospectId = $id;
        $message = 'Prospect updated successfully';
        $modalId = 'editProspectModal';
        $redirectUrl = "/partials/prospects/prospect-dashboard.php?id=" . $prospectId;

    } else {
        // Insert new prospect
        $stmt = $db->prepare("
            INSERT INTO prospects (
                ts, user_id, org_id, name, business_type, stub, description,
                members, monthly_spend_limit, monthly_spend,
                created_by, created_at, updated_at, image, agent_id
            ) VALUES (
                NOW(), ?, ?, ?, ?, ?, ?,
                ?, ?, 0,
                ?, NOW(), NOW(), '', ?
            )
        ");

        $stmt->execute([
            $user['id'],
            $user['org_id'] ?? null,
            $name,
            $businessType ?: null,
            $stub,
            $description ?: null,
            $members,
            $monthlySpendLimit,
            $user['id'],
            $agentId ?: null
        ]);

        $prospectId = $db->lastInsertId();
        $message = 'Prospect created successfully';
        $modalId = 'addProspectModal';
        $redirectUrl = "/partials/prospects/index.php";
    }

    // Return success with reload trigger
    header('HX-Trigger: {"prospectSaved": {"id": ' . $prospectId . '}}');
    echo '<div class="alert alert-success">' . htmlspecialchars($message) . '</div>';
    echo '<script>
        setTimeout(function() {
            var modal = bootstrap.Modal.getInstance(document.getElementById("' . $modalId . '"));
            if (modal) modal.hide();
            htmx.ajax("GET", "' . $redirectUrl . '", {target: "#page-content"});
        }, 1000);
    </script>';

} catch (PDOException $e) {
    error_log("Error saving prospect: " . $e->getMessage());
    echo '<div class="alert alert-danger">An error occurred while saving the prospect</div>';
}
