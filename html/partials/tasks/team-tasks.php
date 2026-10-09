<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../models/Task.php';
require_once __DIR__ . '/../../../models/Team.php';

check_auth();
$user = get_user();

// Get current team context
$teamId = $_SESSION['selected_team_id'] ?? null;
$teamModel = new Team();
$selectedTeam = null;

if ($teamId) {
    $selectedTeam = $teamModel->getTeamDetails($teamId);
}

// Get assignable users for filter (team members)
$taskModel = new Task();
$assignableUsers = $taskModel->getAssignableUsers($user['id'], $teamId);
?>

<!-- Team Tasks Page Container -->
<div class="container-fluid" id="team-tasks-page">
    <!-- Page Header -->
    <div class="row mt-4 mx-0 mb-0" id="team-tasks-header">
        <div class="col-12">
            <div class="card bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-1 text-dark">Team Tasks</h4>
                            <?php if ($selectedTeam): ?>
                                <p class="mb-0 text-dark">
                                    <i class="feather-users me-1"></i>
                                    <?= htmlspecialchars($selectedTeam['name']) ?> Team Tasks
                                </p>
                            <?php else: ?>
                                <p class="mb-0 text-dark">All team tasks</p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <button class="btn btn-light"
                                    id="btn-create-team-task"
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

    <?php if (!$selectedTeam): ?>
        <!-- No Team Selected -->
        <div class="row" id="team-tasks-no-team">
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="feather-users fs-1 text-muted mb-3 d-block"></i>
                        <h5 class="text-muted">No Team Selected</h5>
                        <p class="text-muted">
                            Please select a team from the team switcher in the navigation bar to view team tasks.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Search and Filters Row -->
        <div class="row mb-3" id="team-tasks-search-filter-row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row g-3">
                            <!-- Search Input -->
                            <div class="col-lg-4 col-md-6" id="team-tasks-search-col">
                                <label class="form-label small text-muted">Search</label>
                                <input type="search"
                                       id="team-tasks-search-input"
                                       name="q"
                                       class="form-control"
                                       placeholder="Search by title or description..."
                                       hx-get="/partials/tasks/team-tasks-table.php"
                                       hx-trigger="keyup changed delay:500ms"
                                       hx-target="#team-tasks-list-table"
                                       hx-include="#team-tasks-filters select, #team-tasks-filters input"
                                       hx-indicator="#team-tasks-loading-indicator">
                            </div>

                            <!-- Status Filter -->
                            <div class="col-lg-2 col-md-3 col-sm-6" id="team-tasks-status-filter-col">
                                <label class="form-label small text-muted">Status</label>
                                <select name="status"
                                        id="team-filter-status"
                                        class="form-select"
                                        hx-get="/partials/tasks/team-tasks-table.php"
                                        hx-trigger="change"
                                        hx-target="#team-tasks-list-table"
                                        hx-include="#team-tasks-search-input, #team-tasks-filters select, #team-tasks-filters input"
                                        hx-indicator="#team-tasks-loading-indicator">
                                    <option value="">All Statuses</option>
                                    <option value="pending">Pending</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="review">Review</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>

                            <!-- Priority Filter -->
                            <div class="col-lg-2 col-md-3 col-sm-6" id="team-tasks-priority-filter-col">
                                <label class="form-label small text-muted">Priority</label>
                                <select name="priority"
                                        id="team-filter-priority"
                                        class="form-select"
                                        hx-get="/partials/tasks/team-tasks-table.php"
                                        hx-trigger="change"
                                        hx-target="#team-tasks-list-table"
                                        hx-include="#team-tasks-search-input, #team-tasks-filters select, #team-tasks-filters input"
                                        hx-indicator="#team-tasks-loading-indicator">
                                    <option value="">All Priorities</option>
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>

                            <!-- Assignee Filter -->
                            <div class="col-lg-2 col-md-6" id="team-tasks-assignee-filter-col">
                                <label class="form-label small text-muted">Assignee</label>
                                <select name="assignee"
                                        id="team-filter-assignee"
                                        class="form-select"
                                        hx-get="/partials/tasks/team-tasks-table.php"
                                        hx-trigger="change"
                                        hx-target="#team-tasks-list-table"
                                        hx-include="#team-tasks-search-input, #team-tasks-filters select, #team-tasks-filters input"
                                        hx-indicator="#team-tasks-loading-indicator">
                                    <option value="">All Team Members</option>
                                    <?php foreach ($assignableUsers as $assignee): ?>
                                        <option value="<?= $assignee['id'] ?>">
                                            <?= htmlspecialchars($assignee['first_name'] . ' ' . $assignee['last_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Clear Filters Button -->
                            <div class="col-lg-2 col-md-6 d-flex align-items-end" id="team-tasks-clear-filter-col">
                                <button type="button"
                                        id="btn-clear-team-filters"
                                        class="btn btn-outline-secondary w-100"
                                        onclick="document.getElementById('team-tasks-search-input').value='';
                                                 document.getElementById('team-filter-status').value='';
                                                 document.getElementById('team-filter-priority').value='';
                                                 document.getElementById('team-filter-assignee').value='';
                                                 document.getElementById('team-filter-date-from').value='';
                                                 document.getElementById('team-filter-date-to').value='';
                                                 htmx.trigger('#team-tasks-search-input', 'keyup');">
                                    <i class="feather-x-circle me-2"></i>Clear Filters
                                </button>
                            </div>
                        </div>

                        <!-- Advanced Filters (Collapsible) -->
                        <div class="row mt-2" id="team-tasks-filters">
                            <div class="col-12">
                                <div class="collapse" id="teamTasksAdvancedFilters">
                                    <div class="row g-3 mt-1">
                                        <!-- Date From -->
                                        <div class="col-lg-3 col-md-6" id="team-tasks-date-from-col">
                                            <label class="form-label small text-muted">Due Date From</label>
                                            <input type="date"
                                                   id="team-filter-date-from"
                                                   name="date_from"
                                                   class="form-control"
                                                   hx-get="/partials/tasks/team-tasks-table.php"
                                                   hx-trigger="change"
                                                   hx-target="#team-tasks-list-table"
                                                   hx-include="#team-tasks-search-input, #team-tasks-filters select, #team-tasks-filters input"
                                                   hx-indicator="#team-tasks-loading-indicator">
                                        </div>

                                        <!-- Date To -->
                                        <div class="col-lg-3 col-md-6" id="team-tasks-date-to-col">
                                            <label class="form-label small text-muted">Due Date To</label>
                                            <input type="date"
                                                   id="team-filter-date-to"
                                                   name="date_to"
                                                   class="form-control"
                                                   hx-get="/partials/tasks/team-tasks-table.php"
                                                   hx-trigger="change"
                                                   hx-target="#team-tasks-list-table"
                                                   hx-include="#team-tasks-search-input, #team-tasks-filters select, #team-tasks-filters input"
                                                   hx-indicator="#team-tasks-loading-indicator">
                                        </div>
                                    </div>
                                </div>
                                <div class="text-center mt-2">
                                    <button class="btn btn-link btn-sm"
                                            type="button"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#teamTasksAdvancedFilters"
                                            id="btn-toggle-team-advanced-filters">
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
        <div class="row" id="team-tasks-table-row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <!-- Loading Indicator -->
                        <div class="text-center py-3 d-none" id="team-tasks-loading-indicator">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>

                        <!-- Task Table (loaded via HTMX) -->
                        <div id="team-tasks-list-table"
                             hx-get="/partials/tasks/team-tasks-table.php"
                             hx-trigger="load, refreshTaskList from:body"
                             hx-include="#team-tasks-search-input, #team-tasks-filters select, #team-tasks-filters input"
                             hx-indicator="#team-tasks-loading-indicator">
                            <!-- Table content will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- HTMX loading indicator -->
<script>
document.body.addEventListener('htmx:beforeRequest', function(evt) {
    if (evt.detail.target.id === 'team-tasks-list-table') {
        document.getElementById('team-tasks-loading-indicator').classList.remove('d-none');
    }
});

document.body.addEventListener('htmx:afterRequest', function(evt) {
    if (evt.detail.target.id === 'team-tasks-list-table') {
        document.getElementById('team-tasks-loading-indicator').classList.add('d-none');
    }
});
</script>
