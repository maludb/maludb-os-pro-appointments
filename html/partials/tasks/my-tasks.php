<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../models/Task.php';

check_auth();
$user = get_user();

// Get current team context (if any)
$teamId = $_SESSION['selected_team_id'] ?? null;

// Get URL parameters for filtering
$statusParam = $_GET['status'] ?? '';
$overdueParam = $_GET['overdue'] ?? '';

// Determine page title and description based on parameters
$pageTitle = 'My Tasks';
$pageDescription = 'Tasks assigned to you or created by you';

if ($overdueParam == '1') {
    $pageTitle = 'My Overdue Tasks';
    $pageDescription = 'Tasks that are past their due date';
} elseif (!empty($statusParam)) {
    $pageTitle = 'My ' . ucfirst(str_replace('_', ' ', $statusParam)) . ' Tasks';
    $pageDescription = 'Tasks with ' . str_replace('_', ' ', $statusParam) . ' status';
}

// Get assignable users for filter
$taskModel = new Task();
$assignableUsers = $taskModel->getAssignableUsers($user['id'], $teamId);
?>

<!-- My Tasks Page Container -->
<div class="container-fluid" id="my-tasks-page">
    <!-- Page Header -->
    <div class="row mt-4 mx-0 mb-0" id="my-tasks-header">
        <div class="col-12">
            <div class="card bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-1 text-dark"><?= htmlspecialchars($pageTitle) ?></h4>
                            <p class="mb-0 text-dark"><?= htmlspecialchars($pageDescription) ?></p>
                        </div>
                        <div>
                            <button class="btn btn-light"
                                    id="btn-create-my-task"
                                    hx-get="/partials/tasks/create-form.php"
                                    hx-target="#modal-container"
                                    hx-swap="innerHTML">
                                <i class="feather-plus me-2"></i>Create Task
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filters Row -->
    <div class="row mb-3" id="my-tasks-search-filter-row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row g-3">
                        <!-- Search Input -->
                        <div class="col-lg-4 col-md-6" id="my-tasks-search-col">
                            <label class="form-label small text-muted">Search</label>
                            <input type="search"
                                   id="my-tasks-search-input"
                                   name="q"
                                   class="form-control"
                                   placeholder="Search by title or description..."
                                   hx-get="/partials/tasks/my-tasks-table.php"
                                   hx-trigger="keyup changed delay:500ms"
                                   hx-target="#my-tasks-list-table"
                                   hx-include="#my-tasks-filters select, #my-tasks-filters input, #overdue-filter-input"
                                   hx-indicator="#my-tasks-loading-indicator">
                            <!-- Hidden input for overdue filter -->
                            <input type="hidden" id="overdue-filter-input" name="overdue" value="<?= htmlspecialchars($overdueParam) ?>">
                        </div>

                        <!-- Status Filter -->
                        <div class="col-lg-2 col-md-3 col-sm-6" id="my-tasks-status-filter-col">
                            <label class="form-label small text-muted">Status</label>
                            <select name="status"
                                    id="my-filter-status"
                                    class="form-select"
                                    hx-get="/partials/tasks/my-tasks-table.php"
                                    hx-trigger="change"
                                    hx-target="#my-tasks-list-table"
                                    hx-include="#my-tasks-search-input, #my-tasks-filters select, #my-tasks-filters input, #overdue-filter-input"
                                    hx-indicator="#my-tasks-loading-indicator">
                                <option value="">All Statuses</option>
                                <option value="pending" <?= $statusParam === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="in_progress" <?= $statusParam === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                <option value="review" <?= $statusParam === 'review' ? 'selected' : '' ?>>Review</option>
                                <option value="completed" <?= $statusParam === 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="cancelled" <?= $statusParam === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>

                        <!-- Priority Filter -->
                        <div class="col-lg-2 col-md-3 col-sm-6" id="my-tasks-priority-filter-col">
                            <label class="form-label small text-muted">Priority</label>
                            <select name="priority"
                                    id="my-filter-priority"
                                    class="form-select"
                                    hx-get="/partials/tasks/my-tasks-table.php"
                                    hx-trigger="change"
                                    hx-target="#my-tasks-list-table"
                                    hx-include="#my-tasks-search-input, #my-tasks-filters select, #my-tasks-filters input, #overdue-filter-input"
                                    hx-indicator="#my-tasks-loading-indicator">
                                <option value="">All Priorities</option>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>

                        <!-- Clear Filters Button -->
                        <div class="col-lg-2 col-md-6 d-flex align-items-end" id="my-tasks-clear-filter-col">
                            <button type="button"
                                    id="btn-clear-my-filters"
                                    class="btn btn-outline-secondary w-100"
                                    onclick="document.getElementById('my-tasks-search-input').value='';
                                             document.getElementById('my-filter-status').value='';
                                             document.getElementById('my-filter-priority').value='';
                                             document.getElementById('my-filter-date-from').value='';
                                             document.getElementById('my-filter-date-to').value='';
                                             document.getElementById('overdue-filter-input').value='';
                                             htmx.trigger('#my-tasks-search-input', 'keyup');">
                                <i class="feather-x-circle me-2"></i>Clear Filters
                            </button>
                        </div>
                    </div>

                    <!-- Advanced Filters (Collapsible) -->
                    <div class="row mt-2" id="my-tasks-filters">
                        <div class="col-12">
                            <div class="collapse" id="myTasksAdvancedFilters">
                                <div class="row g-3 mt-1">
                                    <!-- Date From -->
                                    <div class="col-lg-3 col-md-6" id="my-tasks-date-from-col">
                                        <label class="form-label small text-muted">Due Date From</label>
                                        <input type="date"
                                               id="my-filter-date-from"
                                               name="date_from"
                                               class="form-control"
                                               hx-get="/partials/tasks/my-tasks-table.php"
                                               hx-trigger="change"
                                               hx-target="#my-tasks-list-table"
                                               hx-include="#my-tasks-search-input, #my-tasks-filters select, #my-tasks-filters input, #overdue-filter-input"
                                               hx-indicator="#my-tasks-loading-indicator">
                                    </div>

                                    <!-- Date To -->
                                    <div class="col-lg-3 col-md-6" id="my-tasks-date-to-col">
                                        <label class="form-label small text-muted">Due Date To</label>
                                        <input type="date"
                                               id="my-filter-date-to"
                                               name="date_to"
                                               class="form-control"
                                               hx-get="/partials/tasks/my-tasks-table.php"
                                               hx-trigger="change"
                                               hx-target="#my-tasks-list-table"
                                               hx-include="#my-tasks-search-input, #my-tasks-filters select, #my-tasks-filters input, #overdue-filter-input"
                                               hx-indicator="#my-tasks-loading-indicator">
                                    </div>
                                </div>
                            </div>
                            <div class="text-center mt-2">
                                <button class="btn btn-link btn-sm"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#myTasksAdvancedFilters"
                                        id="btn-toggle-my-advanced-filters">
                                    <i class="feather-chevron-down me-1"></i>Advanced Filters
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Task Table Container -->
    <div class="row" id="my-tasks-table-row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <!-- Loading Indicator -->
                    <div class="text-center py-3 d-none" id="my-tasks-loading-indicator">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>

                    <!-- Task Table (loaded via HTMX) -->
                    <div id="my-tasks-list-table"
                         hx-get="/partials/tasks/my-tasks-table.php?status=<?= htmlspecialchars($statusParam) ?>&overdue=<?= htmlspecialchars($overdueParam) ?>"
                         hx-trigger="load, refreshTaskList from:body"
                         hx-include="#my-tasks-search-input, #my-tasks-filters select, #my-tasks-filters input, #overdue-filter-input"
                         hx-indicator="#my-tasks-loading-indicator">
                        <!-- Table content will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- HTMX loading indicator -->
<script>
document.body.addEventListener('htmx:beforeRequest', function(evt) {
    if (evt.detail.target.id === 'my-tasks-list-table') {
        document.getElementById('my-tasks-loading-indicator').classList.remove('d-none');
    }
});

document.body.addEventListener('htmx:afterRequest', function(evt) {
    if (evt.detail.target.id === 'my-tasks-list-table') {
        document.getElementById('my-tasks-loading-indicator').classList.add('d-none');
    }
});
</script>
