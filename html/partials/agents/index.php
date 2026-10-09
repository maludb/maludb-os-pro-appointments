<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../config/database.php';

check_auth();
$user = get_user();

// Get all agents with prospect names
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("
    SELECT a.*, p.name as prospect_name
    FROM prospect_agents a
    LEFT JOIN prospects p ON a.prospect_id = p.id
    ORDER BY a.agent_name ASC
");
$stmt->execute();
$agents = $stmt->fetchAll();
?>

<!-- Agents List Page Container -->
<div class="container-fluid pt-4 ps-4" id="agents-list-page">
    <!-- Page Header -->
    <div class="row mb-4" id="agents-list-header">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center" id="agents-header-content">
                <div id="agents-header-title">
                    <h3 class="mb-1">Agents</h3>
                    <p class="text-muted mb-0">Manage your prospect agents and their configurations</p>
                </div>
                <div id="agents-header-actions">
                    <button class="btn btn-primary"
                            id="btn-add-agent"
                            hx-get="/partials/agents/add-form.php"
                            hx-target="#modal-container"
                            hx-swap="innerHTML">
                        <i class="feather-plus me-2"></i>Add Agent
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Agents Table -->
    <div class="row" id="agents-table-row">
        <div class="col-12">
            <div class="card" id="agents-table-card">
                <div class="card-body" id="agents-table-card-body">
                    <?php if (empty($agents)): ?>
                        <div class="text-center py-5" id="agents-empty-state">
                            <i class="feather-users text-muted" style="font-size: 48px;"></i>
                            <h5 class="mt-3 text-muted">No Agents Found</h5>
                            <p class="text-muted">Get started by adding your first agent.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive" id="agents-table-container">
                            <table class="table table-hover" id="agents-table">
                                <thead id="agents-table-head">
                                    <tr>
                                        <th>Name</th>
                                        <th>Role</th>
                                        <th>Prospect</th>
                                        <th>Temperament</th>
                                        <th>Product Knowledge</th>
                                        <th>Technical Level</th>
                                        <th>Created</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="agents-table-body">
                                    <?php foreach ($agents as $agent): ?>
                                        <tr id="agent-row-<?= $agent['id'] ?>">
                                            <td id="agent-name-<?= $agent['id'] ?>">
                                                <span class="fw-medium"><?= htmlspecialchars($agent['agent_name'] ?: '-') ?></span>
                                            </td>
                                            <td id="agent-role-<?= $agent['id'] ?>">
                                                <?= htmlspecialchars($agent['agent_role']) ?>
                                            </td>
                                            <td id="agent-prospect-<?= $agent['id'] ?>">
                                                <?= htmlspecialchars($agent['prospect_name'] ?: '-') ?>
                                            </td>
                                            <td id="agent-temperament-<?= $agent['id'] ?>">
                                                <?= htmlspecialchars($agent['default_temprement']) ?>
                                            </td>
                                            <td id="agent-knowledge-<?= $agent['id'] ?>">
                                                <?= htmlspecialchars($agent['product_knowledge']) ?>
                                            </td>
                                            <td id="agent-technical-<?= $agent['id'] ?>">
                                                <?= htmlspecialchars($agent['technical_level']) ?>
                                            </td>
                                            <td id="agent-created-<?= $agent['id'] ?>">
                                                <?= date('M j, Y', strtotime($agent['created_at'])) ?>
                                            </td>
                                            <td class="text-end" id="agent-actions-<?= $agent['id'] ?>">
                                                <a href="#"
                                                   class="btn btn-sm btn-outline-primary"
                                                   id="btn-edit-agent-<?= $agent['id'] ?>"
                                                   hx-get="/partials/agents/agent-dashboard.php?id=<?= $agent['id'] ?>"
                                                   hx-target="#page-content">
                                                    <i class="feather-edit-2 me-1"></i>Edit
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
