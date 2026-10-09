<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../config/database.php';

check_auth();
$user = get_user();

// Get research job ID from request
$jobId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$jobId) {
    echo '<div class="alert alert-danger">Research Job ID is required.</div>';
    exit;
}

$db = Database::getInstance()->getConnection();

// Get research job info
$stmt = $db->prepare("SELECT * FROM research_jobs WHERE id = ?");
$stmt->execute([$jobId]);
$job = $stmt->fetch();

if (!$job) {
    echo '<div class="alert alert-danger">Research Job not found.</div>';
    exit;
}

// Get project info for breadcrumb
$project = null;
if ($job['project_id']) {
    $stmt = $db->prepare("SELECT id, name FROM projects WHERE id = ?");
    $stmt->execute([$job['project_id']]);
    $project = $stmt->fetch();
}

// Get all iterations (unique iteration numbers from both tables)
$stmt = $db->prepare("
    SELECT DISTINCT iteration FROM (
        SELECT iteration FROM red_team_drafts WHERE job_id = ?
        UNION
        SELECT iteration FROM red_team_issues WHERE job_id = ?
    ) AS iterations ORDER BY iteration ASC
");
$stmt->execute([$jobId, $jobId]);
$iterations = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get drafts grouped by iteration
$stmt = $db->prepare("SELECT * FROM red_team_drafts WHERE job_id = ? ORDER BY iteration ASC");
$stmt->execute([$jobId]);
$allDrafts = $stmt->fetchAll();
$draftsByIteration = [];
foreach ($allDrafts as $draft) {
    $draftsByIteration[$draft['iteration']][] = $draft;
}

// Get issues grouped by iteration
$stmt = $db->prepare("SELECT * FROM red_team_issues WHERE job_id = ? ORDER BY iteration ASC, severity DESC");
$stmt->execute([$jobId]);
$allIssues = $stmt->fetchAll();
$issuesByIteration = [];
foreach ($allIssues as $issue) {
    $issuesByIteration[$issue['iteration']][] = $issue;
}

// Status classes for badges
$statusClasses = [
    'queued' => 'bg-secondary',
    'running' => 'bg-primary',
    'succeeded' => 'bg-success',
    'failed' => 'bg-danger',
    'cancelled' => 'bg-warning',
    'webhook_failed' => 'bg-danger'
];
?>

<!-- Research Job Dashboard Page Container -->
<div class="container-fluid" id="research-job-dashboard-page">
    <!-- Page Header -->
    <div class="row mb-4" id="research-job-dashboard-header">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center" id="research-job-dashboard-header-content">
                <div id="research-job-dashboard-header-title">
                    <nav aria-label="breadcrumb" id="research-job-breadcrumb">
                        <ol class="breadcrumb mb-1">
                            <?php if ($project): ?>
                                <li class="breadcrumb-item">
                                    <a href="#" hx-get="/partials/projects/index.php" hx-target="#page-content">Projects</a>
                                </li>
                                <li class="breadcrumb-item">
                                    <a href="#" hx-get="/partials/projects/view.php?id=<?= $project['id'] ?>" hx-target="#page-content"><?= htmlspecialchars($project['name']) ?></a>
                                </li>
                            <?php else: ?>
                                <li class="breadcrumb-item">
                                    <a href="#" hx-get="/partials/projects/index.php" hx-target="#page-content">Projects</a>
                                </li>
                            <?php endif; ?>
                            <li class="breadcrumb-item active">Research Job #<?= $job['id'] ?></li>
                        </ol>
                    </nav>
                    <h3 class="mb-0">Research Job #<?= $job['id'] ?></h3>
                </div>
                <div id="research-job-dashboard-header-actions">
                    <?php if ($project): ?>
                        <a href="#"
                           class="btn btn-outline-secondary"
                           id="btn-back-to-project"
                           hx-get="/partials/projects/view.php?id=<?= $project['id'] ?>"
                           hx-target="#page-content">
                            <i class="feather-arrow-left me-2"></i>Back to Project
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Research Job Info Section -->
    <div class="row mb-4" id="research-job-info-row">
        <div class="col-12">
            <div class="card" id="research-job-info-card">
                <div class="card-header" id="research-job-info-card-header">
                    <h5 class="card-title mb-0">
                        <i class="feather-search me-2"></i>Research Job Information
                    </h5>
                </div>
                <div class="card-body" id="research-job-info-card-body">
                    <div class="row" id="research-job-info-details">
                        <div class="col-md-6" id="research-job-info-col-left">
                            <table class="table table-borderless mb-0" id="research-job-info-table-left">
                                <tr id="research-job-info-id-row">
                                    <th class="text-muted" style="width: 180px;">ID</th>
                                    <td><?= htmlspecialchars($job['id']) ?></td>
                                </tr>
                                <tr id="research-job-info-session-row">
                                    <th class="text-muted">Session ID</th>
                                    <td><code><?= htmlspecialchars($job['session_id'] ?: '-') ?></code></td>
                                </tr>
                                <tr id="research-job-info-model-row">
                                    <th class="text-muted">Model</th>
                                    <td><?= htmlspecialchars($job['model_name'] ?: '-') ?></td>
                                </tr>
                                <tr id="research-job-info-status-row">
                                    <th class="text-muted">Status</th>
                                    <td>
                                        <span class="badge <?= $statusClasses[$job['status']] ?? 'bg-secondary' ?>">
                                            <?= htmlspecialchars($job['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr id="research-job-info-priority-row">
                                    <th class="text-muted">Priority</th>
                                    <td><?= htmlspecialchars($job['priority'] ?? '-') ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6" id="research-job-info-col-right">
                            <table class="table table-borderless mb-0" id="research-job-info-table-right">
                                <tr id="research-job-info-iterations-row">
                                    <th class="text-muted" style="width: 180px;">Max Iterations</th>
                                    <td><?= htmlspecialchars($job['max_iterations'] ?? '-') ?></td>
                                </tr>
                                <tr id="research-job-info-quality-row">
                                    <th class="text-muted">Quality Threshold</th>
                                    <td><?= htmlspecialchars($job['quality_threshold'] ?? '-') ?></td>
                                </tr>
                                <tr id="research-job-info-timeout-row">
                                    <th class="text-muted">Timeout (seconds)</th>
                                    <td><?= htmlspecialchars($job['timeout_seconds'] ?? '-') ?></td>
                                </tr>
                                <tr id="research-job-info-created-row">
                                    <th class="text-muted">Created</th>
                                    <td><?= $job['created_at'] ? date('M j, Y g:i A', strtotime($job['created_at'])) : '-' ?></td>
                                </tr>
                                <tr id="research-job-info-completed-row">
                                    <th class="text-muted">Completed</th>
                                    <td><?= $job['completed_at'] ? date('M j, Y g:i A', strtotime($job['completed_at'])) : '-' ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Query -->
                    <?php if ($job['query']): ?>
                        <div class="mt-3" id="research-job-query-section">
                            <h6 class="text-muted mb-2">Query</h6>
                            <div class="bg-light rounded p-3" id="research-job-query-content">
                                <p class="mb-0" style="white-space: pre-wrap;"><?= htmlspecialchars($job['query']) ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Error Message -->
                    <?php if ($job['error_message']): ?>
                        <div class="mt-3" id="research-job-error-section">
                            <h6 class="text-danger mb-2">Error Message</h6>
                            <div class="bg-danger bg-opacity-10 border border-danger rounded p-3" id="research-job-error-content">
                                <p class="mb-0 text-danger"><?= htmlspecialchars($job['error_message']) ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Iterations Section -->
    <div class="row mb-4" id="iterations-row">
        <div class="col-12">
            <div class="card" id="iterations-card">
                <div class="card-header d-flex justify-content-between align-items-center" id="iterations-card-header">
                    <h5 class="card-title mb-0">
                        <i class="feather-repeat me-2"></i>Iterations
                    </h5>
                    <span class="badge bg-secondary" id="iterations-count"><?= count($iterations) ?> iterations</span>
                </div>
                <div class="card-body" id="iterations-card-body">
                    <?php if (empty($iterations)): ?>
                        <div class="text-center py-4" id="iterations-empty">
                            <i class="feather-inbox text-muted" style="font-size: 48px;"></i>
                            <h5 class="mt-3 text-muted">No Iterations</h5>
                            <p class="text-muted">No iteration data found for this research job.</p>
                        </div>
                    <?php else: ?>
                        <div class="accordion" id="iterations-accordion">
                            <?php foreach ($iterations as $iteration): ?>
                                <?php
                                $drafts = $draftsByIteration[$iteration] ?? [];
                                $issues = $issuesByIteration[$iteration] ?? [];
                                ?>
                                <div class="accordion-item" id="iteration-item-<?= $iteration ?>">
                                    <h2 class="accordion-header" id="iteration-header-<?= $iteration ?>">
                                        <button class="accordion-button <?= $iteration > 1 ? 'collapsed' : '' ?>"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#iteration-collapse-<?= $iteration ?>"
                                                id="iteration-btn-<?= $iteration ?>">
                                            <span class="me-3"><strong>Iteration <?= $iteration ?></strong></span>
                                            <span class="badge bg-primary me-2"><?= count($drafts) ?> draft(s)</span>
                                            <span class="badge bg-warning text-dark"><?= count($issues) ?> issue(s)</span>
                                        </button>
                                    </h2>
                                    <div id="iteration-collapse-<?= $iteration ?>"
                                         class="accordion-collapse collapse <?= $iteration == 1 ? 'show' : '' ?>"
                                         data-bs-parent="#iterations-accordion">
                                        <div class="accordion-body" id="iteration-body-<?= $iteration ?>">

                                            <!-- Drafts for this iteration -->
                                            <div class="mb-4" id="iteration-<?= $iteration ?>-drafts">
                                                <h6 class="text-primary mb-3">
                                                    <i class="feather-file-text me-2"></i>Red Team Drafts
                                                </h6>
                                                <?php if (empty($drafts)): ?>
                                                    <p class="text-muted">No drafts for this iteration.</p>
                                                <?php else: ?>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm" id="drafts-table-iter-<?= $iteration ?>">
                                                            <thead>
                                                                <tr>
                                                                    <th>Quality Score</th>
                                                                    <th>Critiques</th>
                                                                    <th>Severity</th>
                                                                    <th>Duration</th>
                                                                    <th>Preview</th>
                                                                    <th class="text-end">Actions</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($drafts as $draft): ?>
                                                                    <tr id="draft-row-<?= $draft['id'] ?>">
                                                                        <td id="draft-quality-<?= $draft['id'] ?>">
                                                                            <span class="badge bg-info"><?= htmlspecialchars($draft['quality_score'] ?? '-') ?></span>
                                                                        </td>
                                                                        <td id="draft-critiques-<?= $draft['id'] ?>">
                                                                            <?= htmlspecialchars($draft['critique_count'] ?? '0') ?>
                                                                        </td>
                                                                        <td id="draft-severity-<?= $draft['id'] ?>">
                                                                            <?= htmlspecialchars($draft['severity'] ?? '-') ?>
                                                                        </td>
                                                                        <td id="draft-duration-<?= $draft['id'] ?>">
                                                                            <?= $draft['duration_ms'] ? number_format($draft['duration_ms']) . 'ms' : '-' ?>
                                                                        </td>
                                                                        <td id="draft-preview-<?= $draft['id'] ?>">
                                                                            <?php
                                                                            $preview = $draft['draft_text'] ?: '';
                                                                            $truncated = strlen($preview) > 60 ? substr($preview, 0, 60) . '...' : $preview;
                                                                            ?>
                                                                            <span class="text-muted small"><?= htmlspecialchars($truncated) ?></span>
                                                                        </td>
                                                                        <td class="text-end" id="draft-actions-<?= $draft['id'] ?>">
                                                                            <button class="btn btn-sm btn-outline-primary"
                                                                                    id="btn-view-draft-<?= $draft['id'] ?>"
                                                                                    hx-get="/partials/projects/view-draft-modal.php?id=<?= $draft['id'] ?>"
                                                                                    hx-target="#modal-container"
                                                                                    hx-swap="innerHTML">
                                                                                <i class="feather-eye me-1"></i>View
                                                                            </button>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Issues for this iteration -->
                                            <div id="iteration-<?= $iteration ?>-issues">
                                                <h6 class="text-warning mb-3">
                                                    <i class="feather-alert-triangle me-2"></i>Red Team Issues
                                                </h6>
                                                <?php if (empty($issues)): ?>
                                                    <p class="text-muted">No issues for this iteration.</p>
                                                <?php else: ?>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm" id="issues-table-iter-<?= $iteration ?>">
                                                            <thead>
                                                                <tr>
                                                                    <th style="width: 100px;">Severity</th>
                                                                    <th>Issue</th>
                                                                    <th style="width: 150px;">Created</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($issues as $issue): ?>
                                                                    <tr id="issue-row-<?= $issue['id'] ?>">
                                                                        <td id="issue-severity-<?= $issue['id'] ?>">
                                                                            <?php
                                                                            $sevClass = $issue['severity'] >= 7 ? 'bg-danger' : ($issue['severity'] >= 4 ? 'bg-warning text-dark' : 'bg-info');
                                                                            ?>
                                                                            <span class="badge <?= $sevClass ?>"><?= htmlspecialchars($issue['severity']) ?></span>
                                                                        </td>
                                                                        <td id="issue-text-<?= $issue['id'] ?>">
                                                                            <?= htmlspecialchars($issue['issue_text']) ?>
                                                                        </td>
                                                                        <td id="issue-created-<?= $issue['id'] ?>">
                                                                            <?= $issue['created_at'] ? date('M j H:i', strtotime($issue['created_at'])) : '-' ?>
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
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
