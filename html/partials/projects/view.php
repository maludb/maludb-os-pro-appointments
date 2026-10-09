<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../config/database.php';

check_auth();
$user = get_user();

// Get project ID from request
$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$projectId) {
    echo '<div class="alert alert-danger">Project ID is required.</div>';
    exit;
}

$db = Database::getInstance()->getConnection();

// Get project info
$stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
$stmt->execute([$projectId, $user['id']]);
$project = $stmt->fetch();

if (!$project) {
    echo '<div class="alert alert-danger">Project not found.</div>';
    exit;
}

// Get research jobs for this project
$stmt = $db->prepare("SELECT * FROM research_jobs WHERE project_id = ? ORDER BY created_at DESC");
$stmt->execute([$projectId]);
$researchJobs = $stmt->fetchAll();

// Geography mapping
$geographyMap = [
    1 => 'US East',
    2 => 'US West',
    3 => 'Europe',
    4 => 'Asia Pacific',
    5 => 'Global'
];
?>

<!-- Project View Page Container -->
<div class="container-fluid" id="project-view-page">
    <!-- Page Header -->
    <div class="row mb-4" id="project-view-header">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center" id="project-view-header-content">
                <div id="project-view-header-title">
                    <nav aria-label="breadcrumb" id="project-breadcrumb">
                        <ol class="breadcrumb mb-1">
                            <li class="breadcrumb-item">
                                <a href="#" hx-get="/partials/projects/index.php" hx-target="#page-content">Projects</a>
                            </li>
                            <li class="breadcrumb-item active"><?= htmlspecialchars($project['name']) ?></li>
                        </ol>
                    </nav>
                    <h3 class="mb-0"><?= htmlspecialchars($project['name']) ?></h3>
                </div>
                <div id="project-view-header-actions">
                    <a href="#"
                       class="btn btn-outline-secondary"
                       id="btn-back-to-projects"
                       hx-get="/partials/projects/index.php"
                       hx-target="#page-content">
                        <i class="feather-arrow-left me-2"></i>Back to Projects
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Project Info Section -->
    <div class="row mb-4" id="project-info-row">
        <div class="col-12">
            <div class="card" id="project-info-card">
                <div class="card-header d-flex justify-content-between align-items-center" id="project-info-card-header">
                    <h5 class="card-title mb-0">
                        <i class="feather-folder me-2"></i>Project Information
                    </h5>
                    <button class="btn btn-sm btn-outline-primary"
                            id="btn-edit-project"
                            hx-get="/partials/projects/edit-form.php?id=<?= $project['id'] ?>"
                            hx-target="#modal-container"
                            hx-swap="innerHTML">
                        <i class="feather-edit-2 me-1"></i>Edit
                    </button>
                </div>
                <div class="card-body" id="project-info-card-body">
                    <div class="row" id="project-info-details">
                        <div class="col-md-6" id="project-info-col-left">
                            <table class="table table-borderless mb-0" id="project-info-table-left">
                                <tr id="project-info-id-row">
                                    <th class="text-muted" style="width: 180px;">ID</th>
                                    <td><?= htmlspecialchars($project['id']) ?></td>
                                </tr>
                                <tr id="project-info-name-row">
                                    <th class="text-muted">Name</th>
                                    <td><?= htmlspecialchars($project['name']) ?></td>
                                </tr>
                                <tr id="project-info-stub-row">
                                    <th class="text-muted">Stub</th>
                                    <td><code><?= htmlspecialchars($project['stub'] ?: '-') ?></code></td>
                                </tr>
                                <tr id="project-info-desc-row">
                                    <th class="text-muted">Description</th>
                                    <td><?= htmlspecialchars($project['description'] ?: '-') ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6" id="project-info-col-right">
                            <table class="table table-borderless mb-0" id="project-info-table-right">
                                <tr id="project-info-geography-row">
                                    <th class="text-muted" style="width: 180px;">Geography</th>
                                    <td><?= htmlspecialchars($geographyMap[$project['geography']] ?? '-') ?></td>
                                </tr>
                                <tr id="project-info-members-row">
                                    <th class="text-muted">Members</th>
                                    <td><?= htmlspecialchars($project['members'] ?: '0') ?></td>
                                </tr>
                                <tr id="project-info-spend-row">
                                    <th class="text-muted">Monthly Spend</th>
                                    <td>
                                        $<?= number_format($project['monthly_spend'] ?: 0) ?>
                                        <?php if ($project['monthly_spend_limit']): ?>
                                            / $<?= number_format($project['monthly_spend_limit']) ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr id="project-info-created-row">
                                    <th class="text-muted">Created</th>
                                    <td><?= date('M j, Y g:i A', strtotime($project['created_at'])) ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Research Jobs Section -->
    <div class="row mb-4" id="research-jobs-row">
        <div class="col-12">
            <div class="card" id="research-jobs-card">
                <div class="card-header d-flex justify-content-between align-items-center" id="research-jobs-card-header">
                    <h5 class="card-title mb-0">
                        <i class="feather-search me-2"></i>Research Jobs
                    </h5>
                    <span class="badge bg-secondary" id="research-jobs-count"><?= count($researchJobs) ?> jobs</span>
                </div>
                <div class="card-body" id="research-jobs-card-body">
                    <?php if (empty($researchJobs)): ?>
                        <div class="text-center py-4" id="research-jobs-empty">
                            <i class="feather-inbox text-muted" style="font-size: 48px;"></i>
                            <h5 class="mt-3 text-muted">No Research Jobs</h5>
                            <p class="text-muted">No research jobs have been created for this project yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive" id="research-jobs-table-container">
                            <table class="table table-hover" id="research-jobs-table">
                                <thead id="research-jobs-table-head">
                                    <tr>
                                        <th>ID</th>
                                        <th>Session</th>
                                        <th>Model</th>
                                        <th>Query</th>
                                        <th>Status</th>
                                        <th>Priority</th>
                                        <th>Created</th>
                                        <th>Completed</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="research-jobs-table-body">
                                    <?php foreach ($researchJobs as $job): ?>
                                        <tr id="research-job-row-<?= $job['id'] ?>">
                                            <td id="research-job-id-<?= $job['id'] ?>">
                                                <?= htmlspecialchars($job['id']) ?>
                                            </td>
                                            <td id="research-job-session-<?= $job['id'] ?>">
                                                <code><?= htmlspecialchars($job['session_id'] ?: '-') ?></code>
                                            </td>
                                            <td id="research-job-model-<?= $job['id'] ?>">
                                                <?= htmlspecialchars($job['model_name'] ?: '-') ?>
                                            </td>
                                            <td id="research-job-query-<?= $job['id'] ?>">
                                                <?php
                                                $query = $job['query'] ?: '-';
                                                $truncated = strlen($query) > 60 ? substr($query, 0, 60) . '...' : $query;
                                                ?>
                                                <span title="<?= htmlspecialchars($query) ?>"><?= htmlspecialchars($truncated) ?></span>
                                            </td>
                                            <td id="research-job-status-<?= $job['id'] ?>">
                                                <?php
                                                $statusClasses = [
                                                    'queued' => 'bg-secondary',
                                                    'running' => 'bg-primary',
                                                    'succeeded' => 'bg-success',
                                                    'failed' => 'bg-danger',
                                                    'cancelled' => 'bg-warning',
                                                    'webhook_failed' => 'bg-danger'
                                                ];
                                                $statusClass = $statusClasses[$job['status']] ?? 'bg-secondary';
                                                ?>
                                                <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($job['status']) ?></span>
                                            </td>
                                            <td id="research-job-priority-<?= $job['id'] ?>">
                                                <?= htmlspecialchars($job['priority'] ?? '-') ?>
                                            </td>
                                            <td id="research-job-created-<?= $job['id'] ?>">
                                                <?= $job['created_at'] ? date('M j, Y H:i', strtotime($job['created_at'])) : '-' ?>
                                            </td>
                                            <td id="research-job-completed-<?= $job['id'] ?>">
                                                <?= $job['completed_at'] ? date('M j, Y H:i', strtotime($job['completed_at'])) : '-' ?>
                                            </td>
                                            <td class="text-end" id="research-job-actions-<?= $job['id'] ?>">
                                                <a href="#"
                                                   class="btn btn-sm btn-outline-primary"
                                                   id="btn-view-research-job-<?= $job['id'] ?>"
                                                   hx-get="/partials/projects/research-job-dashboard.php?id=<?= $job['id'] ?>"
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
