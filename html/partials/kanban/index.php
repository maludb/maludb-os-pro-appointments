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

<!-- Kanban Board Page Container -->
<div class="container-fluid" id="kanban-board-page">
    <!-- Page Header -->
    <div class="row mt-2 mx-2 mb-0" id="kanban-page-header">
        <div class="col-12">
            <div class="card bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div id="kanban-header-title">
                            <h4 class="mb-1 text-dark"><i class="feather-trello me-2"></i>Kanban Board</h4>
                            <p class="mb-0 text-dark">Drag and drop tasks to update their status</p>
                        </div>
                        <div id="kanban-header-actions">
                            <button class="btn btn-light"
                                    id="btn-kanban-create-task"
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

    <!-- Filters Row -->
    <div class="row mb-3" id="kanban-filters-row">
        <div class="col-12">
            <div class="card" id="kanban-filters-card">
                <div class="card-body">
                    <div class="row g-3" id="kanban-filters-container">
                        <!-- Assignee Filter -->
                        <div class="col-lg-3 col-md-4 col-sm-6" id="kanban-assignee-filter-col">
                            <label class="form-label small text-muted" for="kanban-filter-assignee">
                                <i class="feather-user me-1"></i>Assignee
                            </label>
                            <select name="assignee"
                                    id="kanban-filter-assignee"
                                    class="form-select"
                                    hx-get="/partials/kanban/board.php"
                                    hx-trigger="change"
                                    hx-target="#kanban-board-container"
                                    hx-include="#kanban-filters-container select, #kanban-filters-container input"
                                    hx-indicator="#kanban-loading-indicator">
                                <option value="">All Assignees</option>
                                <?php foreach ($assignableUsers as $assignee): ?>
                                    <option value="<?= $assignee['id'] ?>">
                                        <?= htmlspecialchars($assignee['first_name'] . ' ' . $assignee['last_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Priority Filter -->
                        <div class="col-lg-3 col-md-4 col-sm-6" id="kanban-priority-filter-col">
                            <label class="form-label small text-muted" for="kanban-filter-priority">
                                <i class="feather-flag me-1"></i>Priority
                            </label>
                            <select name="priority"
                                    id="kanban-filter-priority"
                                    class="form-select"
                                    hx-get="/partials/kanban/board.php"
                                    hx-trigger="change"
                                    hx-target="#kanban-board-container"
                                    hx-include="#kanban-filters-container select, #kanban-filters-container input"
                                    hx-indicator="#kanban-loading-indicator">
                                <option value="">All Priorities</option>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>

                        <!-- Category Filter -->
                        <div class="col-lg-3 col-md-4 col-sm-6" id="kanban-category-filter-col">
                            <label class="form-label small text-muted" for="kanban-filter-category">
                                <i class="feather-tag me-1"></i>Category
                            </label>
                            <input type="text"
                                   name="category"
                                   id="kanban-filter-category"
                                   class="form-control"
                                   placeholder="Filter by category..."
                                   hx-get="/partials/kanban/board.php"
                                   hx-trigger="keyup changed delay:500ms"
                                   hx-target="#kanban-board-container"
                                   hx-include="#kanban-filters-container select, #kanban-filters-container input"
                                   hx-indicator="#kanban-loading-indicator">
                        </div>

                        <!-- Hide Completed Toggle -->
                        <div class="col-lg-3 col-md-4 col-sm-6 d-flex align-items-end" id="kanban-toggle-completed-col">
                            <div class="form-check form-switch">
                                <input class="form-check-input"
                                       type="checkbox"
                                       id="kanban-hide-completed"
                                       name="hide_completed"
                                       value="1"
                                       hx-get="/partials/kanban/board.php"
                                       hx-trigger="change"
                                       hx-target="#kanban-board-container"
                                       hx-include="#kanban-filters-container select, #kanban-filters-container input"
                                       hx-indicator="#kanban-loading-indicator">
                                <label class="form-check-label" for="kanban-hide-completed">
                                    Hide Completed
                                </label>
                            </div>
                        </div>

                        <!-- Clear Filters Button -->
                        <div class="col-12" id="kanban-clear-filters-col">
                            <button type="button"
                                    id="btn-kanban-clear-filters"
                                    class="btn btn-sm btn-outline-secondary"
                                    onclick="document.getElementById('kanban-filter-assignee').value='';
                                             document.getElementById('kanban-filter-priority').value='';
                                             document.getElementById('kanban-filter-category').value='';
                                             document.getElementById('kanban-hide-completed').checked=false;
                                             htmx.trigger('#kanban-filter-assignee', 'change');">
                                <i class="feather-x-circle me-2"></i>Clear Filters
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Indicator -->
    <div class="text-center py-3 d-none" id="kanban-loading-indicator">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2 text-muted">Loading board...</p>
    </div>

    <!-- Kanban Board Container (loaded via HTMX) -->
    <div id="kanban-board-container"
         hx-get="/partials/kanban/board.php"
         hx-trigger="load"
         hx-indicator="#kanban-loading-indicator">
        <!-- Board will be loaded here -->
    </div>
</div>

<!-- Include SortableJS from CDN -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<!-- Kanban Board Styles -->
<style>
    /* Kanban Column Styling */
    .kanban-column {
        background-color: #f8f9fa;
        border-radius: 8px;
        padding: 0;
        min-height: 500px;
    }

    .kanban-column-header {
        padding: 12px 16px;
        border-radius: 8px 8px 0 0;
        font-weight: 600;
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: #fff;
    }

    .kanban-column-pending .kanban-column-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .kanban-column-in_progress .kanban-column-header {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }

    .kanban-column-review .kanban-column-header {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }

    .kanban-column-completed .kanban-column-header {
        background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    }

    .kanban-column-cards {
        padding: 12px;
        min-height: 400px;
    }

    /* Kanban Card Styling */
    .kanban-card {
        background: #fff;
        border-radius: 8px;
        padding: 12px;
        margin-bottom: 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        cursor: move;
        transition: all 0.3s ease;
        border-left: 4px solid #ddd;
    }

    .kanban-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transform: translateY(-2px);
    }

    .kanban-card[data-priority="critical"] {
        border-left-color: #dc3545;
    }

    .kanban-card[data-priority="high"] {
        border-left-color: #fd7e14;
    }

    .kanban-card[data-priority="medium"] {
        border-left-color: #ffc107;
    }

    .kanban-card[data-priority="low"] {
        border-left-color: #28a745;
    }

    .kanban-card-title {
        font-weight: 600;
        font-size: 14px;
        margin-bottom: 8px;
        color: #2c3e50;
        cursor: pointer;
    }

    .kanban-card-title:hover {
        color: #667eea;
    }

    .kanban-card-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        font-size: 12px;
        color: #6c757d;
    }

    .kanban-card-actions {
        display: flex;
        gap: 4px;
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px solid #e9ecef;
    }

    .kanban-card-actions .btn {
        font-size: 11px;
        padding: 2px 8px;
    }

    /* Priority Badge */
    .priority-badge {
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 4px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .priority-critical {
        background-color: #dc3545;
        color: #fff;
    }

    .priority-high {
        background-color: #fd7e14;
        color: #fff;
    }

    .priority-medium {
        background-color: #ffc107;
        color: #000;
    }

    .priority-low {
        background-color: #28a745;
        color: #fff;
    }

    /* Assignee Avatar */
    .assignee-avatar {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background-color: #667eea;
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        font-weight: 600;
    }

    /* Drag and Drop Styling */
    .sortable-ghost {
        opacity: 0.4;
        background: #e9ecef;
    }

    .sortable-drag {
        opacity: 1;
        transform: rotate(3deg);
    }

    /* Empty State */
    .kanban-empty-state {
        padding: 40px 20px;
        text-align: center;
        color: #adb5bd;
    }

    .kanban-empty-state i {
        font-size: 48px;
        margin-bottom: 16px;
        opacity: 0.5;
    }

    /* Responsive */
    @media (max-width: 991px) {
        .kanban-column {
            min-height: 300px;
        }
    }

    @media (max-width: 767px) {
        .kanban-column {
            min-height: 200px;
            margin-bottom: 16px;
        }
    }
</style>

<!-- HTMX Loading Indicator Handling -->
<script>
document.body.addEventListener('htmx:beforeRequest', function(evt) {
    if (evt.detail.target.id === 'kanban-board-container') {
        document.getElementById('kanban-loading-indicator').classList.remove('d-none');
    }
});

document.body.addEventListener('htmx:afterRequest', function(evt) {
    if (evt.detail.target.id === 'kanban-board-container') {
        document.getElementById('kanban-loading-indicator').classList.add('d-none');
    }
});

// Refresh board after task actions
document.body.addEventListener('taskCreated', function() {
    htmx.trigger('#kanban-board-container', 'refresh');
});

document.body.addEventListener('taskUpdated', function() {
    htmx.trigger('#kanban-board-container', 'refresh');
});

document.body.addEventListener('taskDeleted', function() {
    htmx.trigger('#kanban-board-container', 'refresh');
});
</script>
