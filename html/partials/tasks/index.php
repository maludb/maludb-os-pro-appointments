<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../models/Task.php';

check_auth();
$user = get_user();

// Get current team context
$teamId = $_SESSION['selected_team_id'] ?? null;

// Get assignable users for filter
$taskModel = new Task();
$assignableUsers = $taskModel->getAssignableUsers($user['id'], $teamId);
?>

<!-- Task List Page Container -->
<div class="container-fluid" id="task-list-page">
    <!-- Page Header -->
    <div class="row mb-4" id="task-list-header">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="mb-1">Task List</h3>
                    <p class="text-muted mb-0">Manage and track all your tasks</p>
                </div>
                <div>
                    <button class="btn btn-primary"
                            id="btn-create-task"
                            hx-get="/partials/tasks/create-form.php"
                            hx-target="#modal-container"
                            hx-swap="innerHTML">
                        <i class="feather-plus-lg me-2"></i>Create Task
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filters Row -->
    <div class="row mb-3" id="task-search-filter-row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row g-3">
                        <!-- Search Input -->
                        <div class="col-lg-4 col-md-6" id="task-search-col">
                            <label class="form-label small text-muted">Search</label>
                            <input type="search"
                                   id="task-search-input"
                                   name="q"
                                   class="form-control"
                                   placeholder="Search by title or description..."
                                   hx-get="/partials/tasks/table.php"
                                   hx-trigger="keyup changed delay:500ms"
                                   hx-target="#task-list-table"
                                   hx-include="#task-filters select, #task-filters input"
                                   hx-indicator="#task-loading-indicator">
                        </div>

                        <!-- Status Filter -->
                        <div class="col-lg-2 col-md-3 col-sm-6" id="task-status-filter-col">
                            <label class="form-label small text-muted">Status</label>
                            <select name="status"
                                    id="filter-status"
                                    class="form-select"
                                    hx-get="/partials/tasks/table.php"
                                    hx-trigger="change"
                                    hx-target="#task-list-table"
                                    hx-include="#task-search-input, #task-filters select, #task-filters input"
                                    hx-indicator="#task-loading-indicator">
                                <option value="">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="review">Review</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>

                        <!-- Priority Filter -->
                        <div class="col-lg-2 col-md-3 col-sm-6" id="task-priority-filter-col">
                            <label class="form-label small text-muted">Priority</label>
                            <select name="priority"
                                    id="filter-priority"
                                    class="form-select"
                                    hx-get="/partials/tasks/table.php"
                                    hx-trigger="change"
                                    hx-target="#task-list-table"
                                    hx-include="#task-search-input, #task-filters select, #task-filters input"
                                    hx-indicator="#task-loading-indicator">
                                <option value="">All Priorities</option>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>

                        <!-- Assignee Filter -->
                        <div class="col-lg-2 col-md-6" id="task-assignee-filter-col">
                            <label class="form-label small text-muted">Assignee</label>
                            <select name="assignee"
                                    id="filter-assignee"
                                    class="form-select"
                                    hx-get="/partials/tasks/table.php"
                                    hx-trigger="change"
                                    hx-target="#task-list-table"
                                    hx-include="#task-search-input, #task-filters select, #task-filters input"
                                    hx-indicator="#task-loading-indicator">
                                <option value="">All Assignees</option>
                                <?php foreach ($assignableUsers as $assignee): ?>
                                    <option value="<?= $assignee['id'] ?>">
                                        <?= htmlspecialchars($assignee['first_name'] . ' ' . $assignee['last_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Clear Filters Button -->
                        <div class="col-lg-2 col-md-6 d-flex align-items-end" id="task-clear-filter-col">
                            <button type="button"
                                    id="btn-clear-filters"
                                    class="btn btn-outline-secondary w-100"
                                    onclick="document.getElementById('task-search-input').value='';
                                             document.getElementById('filter-status').value='';
                                             document.getElementById('filter-priority').value='';
                                             document.getElementById('filter-assignee').value='';
                                             document.getElementById('filter-date-from').value='';
                                             document.getElementById('filter-date-to').value='';
                                             htmx.trigger('#task-search-input', 'keyup');">
                                <i class="feather-x-circle me-2"></i>Clear Filters
                            </button>
                        </div>
                    </div>

                    <!-- Advanced Filters (Collapsible) -->
                    <div class="row mt-2" id="task-filters">
                        <div class="col-12">
                            <div class="collapse" id="advancedFilters">
                                <div class="row g-3 mt-1">
                                    <!-- Date From -->
                                    <div class="col-lg-3 col-md-6" id="task-date-from-col">
                                        <label class="form-label small text-muted">Due Date From</label>
                                        <input type="date"
                                               id="filter-date-from"
                                               name="date_from"
                                               class="form-control"
                                               hx-get="/partials/tasks/table.php"
                                               hx-trigger="change"
                                               hx-target="#task-list-table"
                                               hx-include="#task-search-input, #task-filters select, #task-filters input"
                                               hx-indicator="#task-loading-indicator">
                                    </div>

                                    <!-- Date To -->
                                    <div class="col-lg-3 col-md-6" id="task-date-to-col">
                                        <label class="form-label small text-muted">Due Date To</label>
                                        <input type="date"
                                               id="filter-date-to"
                                               name="date_to"
                                               class="form-control"
                                               hx-get="/partials/tasks/table.php"
                                               hx-trigger="change"
                                               hx-target="#task-list-table"
                                               hx-include="#task-search-input, #task-filters select, #task-filters input"
                                               hx-indicator="#task-loading-indicator">
                                    </div>
                                </div>
                            </div>
                            <div class="text-center mt-2">
                                <button class="btn btn-link btn-sm"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#advancedFilters"
                                        id="btn-toggle-advanced-filters">
                                    <i class="feather-chevron-down me-1"></i>Advanced Filters
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Actions Bar (Hidden by default) -->
    <div class="row mb-3 d-none" id="task-bulk-actions-row">
        <div class="col-12">
            <div class="card bg-light border-primary">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span id="bulk-selected-count" class="fw-bold">0</span> task(s) selected
                        </div>
                        <div class="btn-group" role="group">
                            <button type="button"
                                    class="btn btn-sm btn-success"
                                    id="btn-bulk-complete"
                                    onclick="bulkAction('complete')">
                                <i class="feather-check-circle me-1"></i>Mark Complete
                            </button>
                            <button type="button"
                                    class="btn btn-sm btn-warning"
                                    id="btn-bulk-status"
                                    data-bs-toggle="dropdown">
                                <i class="feather-edit me-1"></i>Change Status
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" onclick="bulkAction('status', 'pending')">Pending</a></li>
                                <li><a class="dropdown-item" href="#" onclick="bulkAction('status', 'in_progress')">In Progress</a></li>
                                <li><a class="dropdown-item" href="#" onclick="bulkAction('status', 'review')">Review</a></li>
                                <li><a class="dropdown-item" href="#" onclick="bulkAction('status', 'completed')">Completed</a></li>
                                <li><a class="dropdown-item" href="#" onclick="bulkAction('status', 'cancelled')">Cancelled</a></li>
                            </ul>
                            <button type="button"
                                    class="btn btn-sm btn-info"
                                    id="btn-bulk-priority"
                                    data-bs-toggle="dropdown">
                                <i class="feather-flag me-1"></i>Change Priority
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" onclick="bulkAction('priority', 'low')">Low</a></li>
                                <li><a class="dropdown-item" href="#" onclick="bulkAction('priority', 'medium')">Medium</a></li>
                                <li><a class="dropdown-item" href="#" onclick="bulkAction('priority', 'high')">High</a></li>
                                <li><a class="dropdown-item" href="#" onclick="bulkAction('priority', 'critical')">Critical</a></li>
                            </ul>
                            <button type="button"
                                    class="btn btn-sm btn-danger"
                                    id="btn-bulk-delete"
                                    onclick="bulkAction('delete')">
                                <i class="feather-trash-2 me-1"></i>Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Task Table Container -->
    <div class="row" id="task-table-row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <!-- Loading Indicator -->
                    <div class="text-center py-3 d-none" id="task-loading-indicator">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>

                    <!-- Task Table (loaded via HTMX) -->
                    <div id="task-list-table"
                         hx-get="/partials/tasks/table.php"
                         hx-trigger="load"
                         hx-include="#task-search-input, #task-filters select, #task-filters input"
                         hx-indicator="#task-loading-indicator">
                        <!-- Table content will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Action JavaScript -->
<script>
// Track selected tasks
let selectedTasks = new Set();

// Handle checkbox clicks
document.addEventListener('htmx:afterSwap', function(evt) {
    if (evt.detail.target.id === 'task-list-table') {
        // Reattach checkbox handlers
        updateBulkSelection();

        // Restore selected state
        selectedTasks.forEach(taskId => {
            const checkbox = document.querySelector(`input[name="task_ids[]"][value="${taskId}"]`);
            if (checkbox) {
                checkbox.checked = true;
            }
        });
    }
});

// Update bulk selection UI
function updateBulkSelection() {
    const checkboxes = document.querySelectorAll('input[name="task_ids[]"]');
    const selectAllCheckbox = document.getElementById('select-all-tasks');

    checkboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            if (this.checked) {
                selectedTasks.add(this.value);
            } else {
                selectedTasks.delete(this.value);
            }
            updateBulkUI();
        });
    });

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            checkboxes.forEach(cb => {
                cb.checked = this.checked;
                if (this.checked) {
                    selectedTasks.add(cb.value);
                } else {
                    selectedTasks.delete(cb.value);
                }
            });
            updateBulkUI();
        });
    }
}

// Update bulk action bar visibility
function updateBulkUI() {
    const count = selectedTasks.size;
    const bulkBar = document.getElementById('task-bulk-actions-row');
    const countEl = document.getElementById('bulk-selected-count');

    if (count > 0) {
        bulkBar.classList.remove('d-none');
        countEl.textContent = count;
    } else {
        bulkBar.classList.add('d-none');
    }
}

// Perform bulk action
function bulkAction(action, value = null) {
    if (selectedTasks.size === 0) {
        alert('Please select at least one task');
        return;
    }

    if (action === 'delete' && !confirm(`Are you sure you want to delete ${selectedTasks.size} task(s)?`)) {
        return;
    }

    const formData = new FormData();
    formData.append('action', action);
    if (value) {
        formData.append('value', value);
    }
    selectedTasks.forEach(id => formData.append('task_ids[]', id));

    fetch('/partials/tasks/bulk-action.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        // Clear selection
        selectedTasks.clear();
        updateBulkUI();

        // Reload table
        htmx.trigger('#task-search-input', 'keyup');

        // Show notification
        if (html.includes('alert-success')) {
            // Success - handled by response
        }
    })
    .catch(error => {
        console.error('Bulk action error:', error);
        alert('An error occurred. Please try again.');
    });

    return false;
}

// HTMX loading indicator
document.body.addEventListener('htmx:beforeRequest', function(evt) {
    if (evt.detail.target.id === 'task-list-table') {
        document.getElementById('task-loading-indicator').classList.remove('d-none');
    }
});

document.body.addEventListener('htmx:afterRequest', function(evt) {
    if (evt.detail.target.id === 'task-list-table') {
        document.getElementById('task-loading-indicator').classList.add('d-none');
    }
});
</script>
