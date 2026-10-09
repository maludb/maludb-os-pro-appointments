<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/ui.php';
require_once __DIR__ . '/../../../helpers/date.php';
require_once __DIR__ . '/../../../models/Task.php';

check_auth();
$user = get_user();

// Get current team ID
$teamId = $_SESSION['selected_team_id'] ?? null;

// Redirect if no team selected
if (!$teamId) {
    echo '<div class="alert alert-warning">Please select a team to view team tasks.</div>';
    exit;
}

// Get parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = isset($_GET['per_page']) ? intval($_GET['per_page']) : 20;

// Validate per page (only allow specific values)
$validPerPage = [10, 20, 50, 100];
if (!in_array($perPage, $validPerPage)) {
    $perPage = 20;
}

// Get filters (no team_id needed - using searchTeamTasks instead)
$filters = [
    'query' => $_GET['q'] ?? '',
    'status' => $_GET['status'] ?? '',
    'priority' => $_GET['priority'] ?? '',
    'assignee' => $_GET['assignee'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

// Get sort parameters
$sort = [
    'column' => $_GET['sort'] ?? 'created_at',
    'direction' => $_GET['dir'] ?? 'DESC'
];

// Get team tasks (all tasks created by or assigned to team members)
$taskModel = new Task();
$result = $taskModel->searchTeamTasks($teamId, $filters, $sort, $page, $perPage);

$tasks = $result['tasks'];
$total = $result['total'];
$totalPages = $result['totalPages'];

// Build query string for pagination
$queryParams = [];
if (!empty($filters['query'])) $queryParams['q'] = $filters['query'];
if (!empty($filters['status'])) $queryParams['status'] = $filters['status'];
if (!empty($filters['priority'])) $queryParams['priority'] = $filters['priority'];
if (!empty($filters['assignee'])) $queryParams['assignee'] = $filters['assignee'];
if (!empty($filters['date_from'])) $queryParams['date_from'] = $filters['date_from'];
if (!empty($filters['date_to'])) $queryParams['date_to'] = $filters['date_to'];
if (!empty($sort['column'])) $queryParams['sort'] = $sort['column'];
if (!empty($sort['direction'])) $queryParams['dir'] = $sort['direction'];
if ($perPage != 20) $queryParams['per_page'] = $perPage;

$queryString = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';

// Function to build sort URL
function getSortUrl($column, $currentSort, $queryString) {
    $direction = 'ASC';
    $icon = 'feather-arrow-down-up';

    if ($currentSort['column'] === $column) {
        $direction = $currentSort['direction'] === 'ASC' ? 'DESC' : 'ASC';
        $icon = $currentSort['direction'] === 'ASC' ? 'feather-arrow-up' : 'feather-arrow-down';
    }

    return [
        'url' => "sort={$column}&dir={$direction}" . str_replace("&sort={$currentSort['column']}&dir={$currentSort['direction']}", '', $queryString),
        'icon' => $icon
    ];
}
?>

<div class="card stretch stretch-full" id="team-tasks-card">
    <!-- Card Header -->
    <div class="card-header">
        <h5 class="card-title">Team Tasks</h5>
        <div class="card-header-action">
            <div class="card-header-btn">
                <div data-bs-toggle="tooltip" title="Refresh">
                    <a href="javascript:void(0);"
                       class="avatar-text avatar-xs bg-warning"
                       hx-get="/partials/tasks/team-tasks-table.php?<?= $queryString ?>"
                       hx-target="#team-tasks-list-table"
                       hx-include="#team-tasks-search-input, #team-tasks-filters select, #team-tasks-filters input">
                    </a>
                </div>
            </div>
            <div class="dropdown">
                <a href="javascript:void(0);" class="avatar-text avatar-sm" data-bs-toggle="dropdown" data-bs-offset="25, 25">
                    <div data-bs-toggle="tooltip" title="Options">
                        <i class="feather-more-vertical"></i>
                    </div>
                </a>
                <div class="dropdown-menu dropdown-menu-end">
                    <a href="javascript:void(0);"
                       class="dropdown-item"
                       hx-get="/partials/tasks/create-form.php"
                       hx-target="#modal-container"
                       hx-swap="innerHTML">
                        <i class="feather-plus"></i>New Task
                    </a>
                    <a href="javascript:void(0);" class="dropdown-item"><i class="feather-filter"></i>Filters</a>
                    <div class="dropdown-divider"></div>
                    <div class="px-3 py-2">
                        <label class="fs-11 fw-medium text-muted text-uppercase">Per Page</label>
                        <select class="form-select form-select-sm mt-1"
                                name="per_page"
                                hx-get="/partials/tasks/team-tasks-table.php"
                                hx-trigger="change"
                                hx-target="#team-tasks-list-table"
                                hx-include="#team-tasks-search-input, #team-tasks-filters select, #team-tasks-filters input">
                            <option value="10" <?= $perPage == 10 ? 'selected' : '' ?>>10</option>
                            <option value="20" <?= $perPage == 20 ? 'selected' : '' ?>>20</option>
                            <option value="50" <?= $perPage == 50 ? 'selected' : '' ?>>50</option>
                            <option value="100" <?= $perPage == 100 ? 'selected' : '' ?>>100</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card Body -->
    <div class="card-body custom-card-action p-0">
        <?php if (empty($tasks)): ?>
            <!-- Empty State -->
            <div class="text-center py-5" id="team-tasks-empty-state">
                <i class="feather-inbox fs-1 text-muted mb-3 d-block"></i>
                <h5 class="text-muted">No team tasks found</h5>
                <p class="text-muted">
                    <?php if (array_filter($filters)): ?>
                        Try adjusting your search or filters to find what you're looking for.
                    <?php else: ?>
                        Get started by creating your first team task!
                    <?php endif; ?>
                </p>
                <button class="btn btn-primary mt-3"
                        hx-get="/partials/tasks/create-form.php"
                        hx-target="#modal-container"
                        hx-swap="innerHTML">
                    <i class="feather-plus me-2"></i>Create Team Task
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Task</th>
                            <th scope="col" class="w-25">Status</th>
                            <th scope="col">Priority</th>
                            <th scope="col">Assigned To</th>
                            <th scope="col">Due Date</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $task): ?>
                            <?php
                            $isOverdue = isOverdue($task['due_date'], $task['status']);
                            $priorityIcon = getPriorityIcon($task['priority']);
                            $priorityColor = getPriorityColor($task['priority']);
                            ?>
                            <tr id="team-task-row-<?= $task['id'] ?>">
                                <!-- Task Column -->
                                <td>
                                    <div class="hstack gap-3">
                                        <div class="avatar-text bg-soft-<?= $priorityColor ?> text-<?= $priorityColor ?>">
                                            <i class="<?= $priorityIcon ?>"></i>
                                        </div>
                                        <div>
                                            <a href="javascript:void(0);"
                                               class="d-block mb-1"
                                               hx-get="/partials/tasks/view.php?id=<?= $task['id'] ?>"
                                               hx-target="#page-content"
                                               hx-swap="innerHTML">
                                                <?= htmlspecialchars($task['title']) ?>
                                            </a>
                                            <div class="d-flex gap-3">
                                                <?php if (!empty($task['due_date'])): ?>
                                                    <a href="javascript:void(0);" class="hstack gap-1 fs-11 fw-normal text-muted">
                                                        <i class="feather-clock fs-10"></i>
                                                        <span><?= formatDate($task['due_date'], 'M j, g:i A') ?></span>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (!empty($task['creator_name'])): ?>
                                                    <span class="hstack gap-1 fs-11 fw-normal text-muted">
                                                        <i class="feather-user fs-10"></i>
                                                        <span>Created by <?= htmlspecialchars($task['creator_name']) ?></span>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Status Column with Progress -->
                                <td>
                                    <?php
                                    $statusProgress = [
                                        'pending' => 0,
                                        'in_progress' => 50,
                                        'completed' => 100
                                    ];
                                    $progress = $statusProgress[$task['status']] ?? 0;
                                    $statusColors = [
                                        'pending' => 'warning',
                                        'in_progress' => 'info',
                                        'completed' => 'success'
                                    ];
                                    $statusColor = $statusColors[$task['status']] ?? 'secondary';
                                    ?>
                                    <div class="fs-12 fw-medium mb-2"><?= ucfirst(str_replace('_', ' ', $task['status'])) ?></div>
                                    <div class="progress ht-3">
                                        <div class="progress-bar bg-<?= $statusColor ?>"
                                             role="progressbar"
                                             style="width: <?= $progress ?>%"></div>
                                    </div>
                                </td>

                                <!-- Priority Column -->
                                <td>
                                    <span class="badge bg-<?= $priorityColor ?>">
                                        <?= ucfirst($task['priority']) ?>
                                    </span>
                                </td>

                                <!-- Assigned To Column -->
                                <td>
                                    <?php if (!empty($task['assigned_to'])): ?>
                                        <div class="hstack gap-2">
                                            <div class="avatar-text avatar-sm bg-soft-primary text-primary">
                                                <?= strtoupper(substr($task['assignee_name'] ?? 'U', 0, 1)) ?>
                                            </div>
                                            <span><?= htmlspecialchars($task['assignee_name'] ?? 'Unknown') ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Unassigned</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Due Date Column -->
                                <td>
                                    <?php if (!empty($task['due_date'])): ?>
                                        <?= formatDate($task['due_date']) ?>
                                        <?php if ($isOverdue): ?>
                                            <div class="text-danger fs-11">
                                                <i class="feather-alert-triangle"></i>
                                                Overdue
                                            </div>
                                        <?php elseif ($task['status'] !== 'completed'): ?>
                                            <div class="text-muted fs-11">
                                                <?= getFriendlyDueDate($task['due_date'], $task['status']) ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">No due date</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Actions Column -->
                                <td>
                                    <div class="hstack gap-2 justify-content-end">
                                        <a href="javascript:void(0);"
                                           class="avatar-text avatar-md"
                                           data-bs-toggle="tooltip"
                                           title="View"
                                           hx-get="/partials/tasks/view.php?id=<?= $task['id'] ?>"
                                           hx-target="#page-content"
                                           hx-swap="innerHTML">
                                            <i class="feather-eye"></i>
                                        </a>
                                        <?php if ($task['status'] !== 'completed'): ?>
                                            <a href="javascript:void(0);"
                                               class="avatar-text avatar-md"
                                               data-bs-toggle="tooltip"
                                               title="Mark Complete"
                                               hx-post="/partials/tasks/complete.php?id=<?= $task['id'] ?>"
                                               hx-target="#team-task-row-<?= $task['id'] ?>"
                                               hx-swap="outerHTML">
                                                <i class="feather-check-circle"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="javascript:void(0);"
                                           class="avatar-text avatar-md"
                                           data-bs-toggle="tooltip"
                                           title="Edit"
                                           hx-get="/partials/tasks/edit-task.php?id=<?= $task['id'] ?>"
                                           hx-target="#page-content"
                                           hx-swap="innerHTML">
                                            <i class="feather-edit"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Card Footer with Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <ul class="list-unstyled d-flex align-items-center gap-2 mb-0 pagination-common-style">
                <!-- Previous -->
                <li>
                    <a href="javascript:void(0);"
                       class="<?= $page <= 1 ? 'disabled' : '' ?>"
                       <?php if ($page > 1): ?>
                       hx-get="/partials/tasks/team-tasks-table.php?page=<?= $page - 1 ?><?= $queryString ?>"
                       hx-target="#team-tasks-list-table"
                       hx-include="#team-tasks-search-input, #team-tasks-filters select, #team-tasks-filters input"
                       <?php endif; ?>>
                        <i class="feather-arrow-left"></i>
                    </a>
                </li>

                <!-- Page Numbers -->
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);

                if ($startPage > 1): ?>
                    <li>
                        <a href="javascript:void(0);"
                           hx-get="/partials/tasks/team-tasks-table.php?page=1<?= $queryString ?>"
                           hx-target="#team-tasks-list-table"
                           hx-include="#team-tasks-search-input, #team-tasks-filters select, #team-tasks-filters input">
                            1
                        </a>
                    </li>
                    <?php if ($startPage > 2): ?>
                        <li><a href="javascript:void(0);"><i class="feather-more-horizontal"></i></a></li>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <li>
                        <a href="javascript:void(0);"
                           class="<?= $i === $page ? 'active' : '' ?>"
                           hx-get="/partials/tasks/team-tasks-table.php?page=<?= $i ?><?= $queryString ?>"
                           hx-target="#team-tasks-list-table"
                           hx-include="#team-tasks-search-input, #team-tasks-filters select, #team-tasks-filters input">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                        <li><a href="javascript:void(0);"><i class="feather-more-horizontal"></i></a></li>
                    <?php endif; ?>
                    <li>
                        <a href="javascript:void(0);"
                           hx-get="/partials/tasks/team-tasks-table.php?page=<?= $totalPages ?><?= $queryString ?>"
                           hx-target="#team-tasks-list-table"
                           hx-include="#team-tasks-search-input, #team-tasks-filters select, #team-tasks-filters input">
                            <?= $totalPages ?>
                        </a>
                    </li>
                <?php endif; ?>

                <!-- Next -->
                <li>
                    <a href="javascript:void(0);"
                       class="<?= $page >= $totalPages ? 'disabled' : '' ?>"
                       <?php if ($page < $totalPages): ?>
                       hx-get="/partials/tasks/team-tasks-table.php?page=<?= $page + 1 ?><?= $queryString ?>"
                       hx-target="#team-tasks-list-table"
                       hx-include="#team-tasks-search-input, #team-tasks-filters select, #team-tasks-filters input"
                       <?php endif; ?>>
                        <i class="feather-arrow-right"></i>
                    </a>
                </li>
            </ul>
        </div>
    <?php endif; ?>
</div>
