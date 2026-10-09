<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../config/database.php';

check_auth();
$user = get_user();

// Get projects for the logged in user
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT id, name, stub, description, members, monthly_spend_limit, monthly_spend, created_at FROM projects WHERE user_id = ? ORDER BY name ASC");
$stmt->execute([$user['id']]);
$projects = $stmt->fetchAll();
?>

<!-- Projects List Page Container -->
<div class="container-fluid" id="projects-list-page">
    <!-- Page Header -->
    <div class="row mb-4" id="projects-list-header">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center" id="projects-header-content">
                <div id="projects-header-title">
                    <h3 class="mb-1">Projects</h3>
                    <p class="text-muted mb-0">Manage your projects</p>
                </div>
                <div id="projects-header-actions">
                    <button class="btn btn-primary"
                            id="btn-add-project"
                            hx-get="/partials/projects/add-form.php"
                            hx-target="#modal-container"
                            hx-swap="innerHTML">
                        <i class="feather-plus me-2"></i>Add Project
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Projects Table -->
    <div class="row" id="projects-table-row">
        <div class="col-12">
            <div class="card" id="projects-table-card">
                <div class="card-body" id="projects-table-card-body">
                    <?php if (empty($projects)): ?>
                        <div class="text-center py-5" id="projects-empty-state">
                            <i class="feather-folder text-muted" style="font-size: 48px;"></i>
                            <h5 class="mt-3 text-muted">No Projects Found</h5>
                            <p class="text-muted">Get started by creating your first project.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive" id="projects-table-container">
                            <table class="table table-hover" id="projects-table">
                                <thead id="projects-table-head">
                                    <tr>
                                        <th>Name</th>
                                        <th>Stub</th>
                                        <th>Description</th>
                                        <th>Members</th>
                                        <th>Monthly Spend</th>
                                        <th>Created</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="projects-table-body">
                                    <?php foreach ($projects as $project): ?>
                                        <tr id="project-row-<?= $project['id'] ?>">
                                            <td id="project-name-<?= $project['id'] ?>">
                                                <span class="fw-medium"><?= htmlspecialchars($project['name']) ?></span>
                                            </td>
                                            <td id="project-stub-<?= $project['id'] ?>">
                                                <code><?= htmlspecialchars($project['stub'] ?: '-') ?></code>
                                            </td>
                                            <td id="project-desc-<?= $project['id'] ?>">
                                                <?= htmlspecialchars($project['description'] ? substr($project['description'], 0, 50) . (strlen($project['description']) > 50 ? '...' : '') : '-') ?>
                                            </td>
                                            <td id="project-members-<?= $project['id'] ?>">
                                                <?= htmlspecialchars($project['members'] ?: '0') ?>
                                            </td>
                                            <td id="project-spend-<?= $project['id'] ?>">
                                                <?php
                                                $spend = $project['monthly_spend'] ?: 0;
                                                $limit = $project['monthly_spend_limit'] ?: 0;
                                                if ($limit > 0) {
                                                    $percentage = round(($spend / $limit) * 100);
                                                    $barColor = $percentage > 80 ? 'bg-danger' : ($percentage > 50 ? 'bg-warning' : 'bg-success');
                                                    echo '<div class="d-flex align-items-center">';
                                                    echo '<span class="me-2">$' . number_format($spend) . ' / $' . number_format($limit) . '</span>';
                                                    echo '</div>';
                                                } else {
                                                    echo '$' . number_format($spend);
                                                }
                                                ?>
                                            </td>
                                            <td id="project-created-<?= $project['id'] ?>">
                                                <?= date('M j, Y', strtotime($project['created_at'])) ?>
                                            </td>
                                            <td class="text-end" id="project-actions-<?= $project['id'] ?>">
                                                <a href="#"
                                                   class="btn btn-sm btn-outline-primary"
                                                   id="btn-view-project-<?= $project['id'] ?>"
                                                   hx-get="/partials/projects/view.php?id=<?= $project['id'] ?>"
                                                   hx-target="#page-content">
                                                    <i class="feather-eye me-1"></i>View
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
