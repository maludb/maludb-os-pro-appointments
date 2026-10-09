<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../config/database.php';

check_auth();
$user = get_user();

// Get agent ID from request
$agentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$agentId) {
    echo '<div class="alert alert-danger">Agent ID is required.</div>';
    exit;
}

$db = Database::getInstance()->getConnection();

// Get agent info with prospect name
$stmt = $db->prepare("
    SELECT a.*, p.name as prospect_name
    FROM prospect_agents a
    LEFT JOIN prospects p ON a.prospect_id = p.id
    WHERE a.id = ?
");
$stmt->execute([$agentId]);
$agent = $stmt->fetch();

if (!$agent) {
    echo '<div class="alert alert-danger">Agent not found.</div>';
    exit;
}
?>

<!-- Agent Dashboard Page Container -->
<div class="container-fluid pt-4 ps-4" id="agent-dashboard-page">
    <!-- Page Header -->
    <div class="row mb-4" id="agent-dashboard-header">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center" id="agent-dashboard-header-content">
                <div id="agent-dashboard-header-title">
                    <nav aria-label="breadcrumb" id="agent-breadcrumb">
                        <ol class="breadcrumb mb-1">
                            <li class="breadcrumb-item">
                                <a href="#" hx-get="/partials/agents/index.php" hx-target="#page-content">Agents</a>
                            </li>
                            <li class="breadcrumb-item active"><?= htmlspecialchars($agent['agent_name'] ?: 'Agent #' . $agent['id']) ?></li>
                        </ol>
                    </nav>
                    <h3 class="mb-0"><?= htmlspecialchars($agent['agent_name'] ?: 'Agent #' . $agent['id']) ?></h3>
                </div>
                <div id="agent-dashboard-header-actions">
                    <a href="#"
                       class="btn btn-outline-secondary"
                       id="btn-back-to-agents"
                       hx-get="/partials/agents/index.php"
                       hx-target="#page-content">
                        <i class="feather-arrow-left me-2"></i>Back to Agents
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Agent Info Section -->
    <div class="row mb-4" id="agent-info-row">
        <div class="col-12">
            <div class="card" id="agent-info-card">
                <div class="card-header d-flex justify-content-between align-items-center" id="agent-info-card-header">
                    <h5 class="card-title mb-0">
                        <i class="feather-info me-2"></i>Agent Information
                    </h5>
                    <button class="btn btn-sm btn-outline-primary"
                            id="btn-edit-agent"
                            hx-get="/partials/agents/edit-form.php?id=<?= $agent['id'] ?>"
                            hx-target="#modal-container"
                            hx-swap="innerHTML">
                        <i class="feather-edit-2 me-1"></i>Edit
                    </button>
                </div>
                <div class="card-body" id="agent-info-card-body">
                    <div class="row" id="agent-info-details">
                        <div class="col-md-6" id="agent-info-col-left">
                            <table class="table table-borderless mb-0" id="agent-info-table-left">
                                <tr id="agent-info-id-row">
                                    <th class="text-muted" style="width: 150px;">ID</th>
                                    <td><?= htmlspecialchars($agent['id']) ?></td>
                                </tr>
                                <tr id="agent-info-name-row">
                                    <th class="text-muted">Name</th>
                                    <td><?= htmlspecialchars($agent['agent_name'] ?: '-') ?></td>
                                </tr>
                                <tr id="agent-info-role-row">
                                    <th class="text-muted">Role</th>
                                    <td><?= htmlspecialchars($agent['agent_role']) ?></td>
                                </tr>
                                <tr id="agent-info-prospect-row">
                                    <th class="text-muted">Prospect</th>
                                    <td><?= htmlspecialchars($agent['prospect_name'] ?: '-') ?></td>
                                </tr>
                                <tr id="agent-info-desc-row">
                                    <th class="text-muted">Description</th>
                                    <td><?= htmlspecialchars($agent['agent_description'] ?: '-') ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6" id="agent-info-col-right">
                            <table class="table table-borderless mb-0" id="agent-info-table-right">
                                <tr id="agent-info-temperament-row">
                                    <th class="text-muted" style="width: 180px;">Temperament</th>
                                    <td><?= htmlspecialchars($agent['default_temprement']) ?></td>
                                </tr>
                                <tr id="agent-info-knowledge-row">
                                    <th class="text-muted">Product Knowledge</th>
                                    <td><?= htmlspecialchars($agent['product_knowledge']) ?></td>
                                </tr>
                                <tr id="agent-info-technical-row">
                                    <th class="text-muted">Technical Level</th>
                                    <td><?= htmlspecialchars($agent['technical_level']) ?></td>
                                </tr>
                                <tr id="agent-info-created-row">
                                    <th class="text-muted">Created</th>
                                    <td><?= date('M j, Y g:i A', strtotime($agent['created_at'])) ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Agent Prompt Section -->
    <div class="row mb-4" id="agent-prompt-row">
        <div class="col-12">
            <div class="card" id="agent-prompt-card">
                <div class="card-header" id="agent-prompt-card-header">
                    <h5 class="card-title mb-0">
                        <i class="feather-message-square me-2"></i>Agent Prompt
                    </h5>
                </div>
                <div class="card-body" id="agent-prompt-card-body">
                    <?php if (!empty($agent['agent_prompt'])): ?>
                        <div class="bg-light rounded p-3" id="agent-prompt-content">
                            <pre class="mb-0" style="white-space: pre-wrap; font-size: 0.9rem;"><?= htmlspecialchars($agent['agent_prompt']) ?></pre>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4" id="agent-prompt-empty">
                            <p class="text-muted mb-0">No prompt configured for this agent.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Placeholder for future sections -->
    <div class="row mb-4" id="agent-additional-row">
        <div class="col-12">
            <div class="card" id="agent-additional-card">
                <div class="card-header" id="agent-additional-card-header">
                    <h5 class="card-title mb-0">
                        <i class="feather-layers me-2"></i>Additional Details
                    </h5>
                </div>
                <div class="card-body" id="agent-additional-card-body">
                    <div class="text-center py-4" id="agent-additional-empty">
                        <p class="text-muted mb-0">Additional agent details will be added here.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
