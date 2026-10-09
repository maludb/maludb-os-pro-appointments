<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../models/Task.php';
require_once __DIR__ . '/../../../helpers/ui.php';
require_once __DIR__ . '/../../../config/database.php';

check_auth();
$user = get_user();

// Get selected team from session
$selectedTeamId = $_SESSION['selected_team_id'] ?? null;

// Initialize model
$taskModel = new Task();

// Get tab from GET parameter (default to my-archived)
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'my-archived';

// Get my archived tasks
$myArchivedTasks = [];
try {
    $db = Database::getInstance()->getConnection();

    // Get all archived tasks where user is involved
    $sql = "SELECT t.*,
                   u.first_name as assignee_first_name, u.last_name as assignee_last_name,
                   creator.first_name as creator_first_name, creator.last_name as creator_last_name
            FROM tasks t
            LEFT JOIN users u ON t.assigned_to = u.id
            LEFT JOIN users creator ON t.created_by = creator.id
            WHERE t.status = 'archived'
            AND (t.created_by = ? OR t.assigned_to = ? OR t.user_id = ?)
            ORDER BY t.updated_at DESC";

    $stmt = $db->prepare($sql);
    $userId = $user['id'];
    $stmt->execute([$userId, $userId, $userId]);
    $myArchivedTasks = $stmt->fetchAll();
    error_log("My archived tasks found: " . count($myArchivedTasks) . " for user " . $userId);
} catch (PDOException $e) {
    error_log("Error getting my archived tasks: " . $e->getMessage());
}

// Get team archived tasks
$teamArchivedTasks = [];
if ($selectedTeamId) {
    try {
        $db = Database::getInstance()->getConnection();

        $sql = "SELECT t.*,
                       u.first_name as assignee_first_name, u.last_name as assignee_last_name,
                       creator.first_name as creator_first_name, creator.last_name as creator_last_name
                FROM tasks t
                LEFT JOIN users u ON t.assigned_to = u.id
                LEFT JOIN users creator ON t.created_by = creator.id
                WHERE t.team_id = :team_id
                AND t.status = 'archived'
                ORDER BY t.updated_at DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute([':team_id' => $selectedTeamId]);
        $teamArchivedTasks = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting team archived tasks: " . $e->getMessage());
    }
}
?>

<!-- Archived Tasks Page Container -->
<div class="container-fluid" id="archived-tasks-page">
    <!-- Page Header -->
    <div class="row mt-2 mx-2 mb-0" id="archived-header">
        <div class="col-12">
            <div class="card bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body">
                    <h4 class="mb-1 text-dark">
                        <i class="feather-archive me-2"></i>Archived Tasks
                    </h4>
                    <p class="mb-0 text-dark">View and manage archived tasks</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="row mb-4" id="archived-tabs-container">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-0">
                    <ul class="nav nav-tabs" id="archivedTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?= $activeTab === 'my-archived' ? 'active' : '' ?>"
                               id="my-archived-tab"
                               data-bs-toggle="tab"
                               href="#my-archived-content"
                               role="tab">
                                <i class="feather-user-check me-2"></i>My Archived Tasks
                                <span class="badge bg-secondary ms-2"><?= count($myArchivedTasks) ?></span>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?= $activeTab === 'team-archived' ? 'active' : '' ?>"
                               id="team-archived-tab"
                               data-bs-toggle="tab"
                               href="#team-archived-content"
                               role="tab">
                                <i class="feather-users-check me-2"></i>Team Archived Tasks
                                <span class="badge bg-secondary ms-2"><?= count($teamArchivedTasks) ?></span>
                            </a>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content" id="archivedTabContent">
                        <!-- My Archived Tasks Tab -->
                        <div class="tab-pane fade <?= $activeTab === 'my-archived' ? 'show active' : '' ?>"
                             id="my-archived-content"
                             role="tabpanel">
                            <div class="p-4" id="my-archived-body">
                                <?php if (empty($myArchivedTasks)): ?>
                                    <div class="text-center py-5" id="my-archived-empty">
                                        <i class="feather-inbox fs-1 text-muted mb-3 d-block"></i>
                                        <h5 class="text-muted">No Archived Tasks</h5>
                                        <p class="text-muted">You don't have any archived tasks yet.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive" id="my-archived-table-wrapper">
                                        <table class="table table-hover mb-0" id="my-archived-tasks-table">
                                            <thead class="table-light">
                                                <tr>
                                                    <th id="col-title">Task Title</th>
                                                    <th id="col-status">Status</th>
                                                    <th id="col-priority">Priority</th>
                                                    <th id="col-assigned">Assigned To</th>
                                                    <th id="col-archived-date">Archived Date</th>
                                                    <th id="col-actions">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="my-archived-table-body">
                                                <?php foreach ($myArchivedTasks as $task): ?>
                                                    <tr id="task-row-<?= $task['id'] ?>">
                                                        <td id="task-title-<?= $task['id'] ?>">
                                                            <a href="#"
                                                               hx-get="/partials/tasks/view.php?id=<?= $task['id'] ?>"
                                                               hx-target="#page-content"
                                                               class="text-decoration-none">
                                                                <?= htmlspecialchars(substr($task['title'], 0, 50)) ?>
                                                            </a>
                                                        </td>
                                                        <td id="task-status-<?= $task['id'] ?>">
                                                            <span class="badge bg-secondary">
                                                                <?= ucfirst(htmlspecialchars($task['status'])) ?>
                                                            </span>
                                                        </td>
                                                        <td id="task-priority-<?= $task['id'] ?>">
                                                            <?php
                                                            $priorityColors = [
                                                                'critical' => 'danger',
                                                                'high' => 'warning',
                                                                'medium' => 'info',
                                                                'low' => 'success',
                                                            ];
                                                            $priorityColor = $priorityColors[$task['priority']] ?? 'secondary';
                                                            ?>
                                                            <span class="badge bg-<?= $priorityColor ?>">
                                                                <?= ucfirst(htmlspecialchars($task['priority'])) ?>
                                                            </span>
                                                        </td>
                                                        <td id="task-assigned-<?= $task['id'] ?>">
                                                            <?php if (!empty($task['assignee_first_name']) && !empty($task['assignee_last_name'])): ?>
                                                                <?= htmlspecialchars($task['assignee_first_name'] . ' ' . $task['assignee_last_name']) ?>
                                                            <?php else: ?>
                                                                <span class="text-muted">Unassigned</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td id="task-archived-<?= $task['id'] ?>">
                                                            <small class="text-muted">
                                                                <?= date('M j, Y', strtotime($task['updated_at'] ?? 'now')) ?>
                                                            </small>
                                                        </td>
                                                        <td id="task-actions-<?= $task['id'] ?>">
                                                            <button class="btn btn-sm btn-outline-primary"
                                                                    hx-get="/partials/tasks/view.php?id=<?= $task['id'] ?>"
                                                                    hx-target="#modal-container"
                                                                    hx-swap="innerHTML"
                                                                    title="View Task">
                                                                <i class="feather-eye"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Team Archived Tasks Tab -->
                        <div class="tab-pane fade <?= $activeTab === 'team-archived' ? 'show active' : '' ?>"
                             id="team-archived-content"
                             role="tabpanel">
                            <div class="p-4" id="team-archived-body">
                                <?php if (!$selectedTeamId): ?>
                                    <div class="text-center py-5" id="team-archived-no-team">
                                        <i class="feather-users fs-1 text-muted mb-3 d-block"></i>
                                        <h5 class="text-muted">No Team Selected</h5>
                                        <p class="text-muted">Please select a team to view team archived tasks.</p>
                                    </div>
                                <?php elseif (empty($teamArchivedTasks)): ?>
                                    <div class="text-center py-5" id="team-archived-empty">
                                        <i class="feather-inbox fs-1 text-muted mb-3 d-block"></i>
                                        <h5 class="text-muted">No Archived Tasks</h5>
                                        <p class="text-muted">This team doesn't have any archived tasks yet.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive" id="team-archived-table-wrapper">
                                        <table class="table table-hover mb-0" id="team-archived-tasks-table">
                                            <thead class="table-light">
                                                <tr>
                                                    <th id="col-title">Task Title</th>
                                                    <th id="col-status">Status</th>
                                                    <th id="col-priority">Priority</th>
                                                    <th id="col-assigned">Assigned To</th>
                                                    <th id="col-created-by">Created By</th>
                                                    <th id="col-archived-date">Archived Date</th>
                                                    <th id="col-actions">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="team-archived-table-body">
                                                <?php foreach ($teamArchivedTasks as $task): ?>
                                                    <tr id="task-row-<?= $task['id'] ?>">
                                                        <td id="task-title-<?= $task['id'] ?>">
                                                            <a href="#"
                                                               hx-get="/partials/tasks/view.php?id=<?= $task['id'] ?>"
                                                               hx-target="#page-content"
                                                               class="text-decoration-none">
                                                                <?= htmlspecialchars(substr($task['title'], 0, 50)) ?>
                                                            </a>
                                                        </td>
                                                        <td id="task-status-<?= $task['id'] ?>">
                                                            <span class="badge bg-secondary">
                                                                <?= ucfirst(htmlspecialchars($task['status'])) ?>
                                                            </span>
                                                        </td>
                                                        <td id="task-priority-<?= $task['id'] ?>">
                                                            <?php
                                                            $priorityColors = [
                                                                'critical' => 'danger',
                                                                'high' => 'warning',
                                                                'medium' => 'info',
                                                                'low' => 'success',
                                                            ];
                                                            $priorityColor = $priorityColors[$task['priority']] ?? 'secondary';
                                                            ?>
                                                            <span class="badge bg-<?= $priorityColor ?>">
                                                                <?= ucfirst(htmlspecialchars($task['priority'])) ?>
                                                            </span>
                                                        </td>
                                                        <td id="task-assigned-<?= $task['id'] ?>">
                                                            <?php if (!empty($task['assignee_first_name']) && !empty($task['assignee_last_name'])): ?>
                                                                <?= htmlspecialchars($task['assignee_first_name'] . ' ' . $task['assignee_last_name']) ?>
                                                            <?php else: ?>
                                                                <span class="text-muted">Unassigned</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td id="task-created-<?= $task['id'] ?>">
                                                            <?php if (!empty($task['creator_first_name']) && !empty($task['creator_last_name'])): ?>
                                                                <?= htmlspecialchars($task['creator_first_name'] . ' ' . $task['creator_last_name']) ?>
                                                            <?php else: ?>
                                                                <span class="text-muted">Unknown</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td id="task-archived-<?= $task['id'] ?>">
                                                            <small class="text-muted">
                                                                <?= date('M j, Y', strtotime($task['updated_at'] ?? 'now')) ?>
                                                            </small>
                                                        </td>
                                                        <td id="task-actions-<?= $task['id'] ?>">
                                                            <button class="btn btn-sm btn-outline-primary"
                                                                    hx-get="/partials/tasks/view.php?id=<?= $task['id'] ?>"
                                                                    hx-target="#modal-container"
                                                                    hx-swap="innerHTML"
                                                                    title="View Task">
                                                                <i class="feather-eye"></i>
                                                            </button>
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
        </div>
    </div>
</div>
