<?php
/**
 * Edit Task - Full Page View
 * Displays task edit form as a full page instead of modal
 */

require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../models/Task.php';

check_auth();
$user = get_user();

// Get task ID
$taskId = $_GET['id'] ?? null;

if (!$taskId) {
    http_response_code(400);
    ?>
    <div class="container-fluid" id="edit-task-error-container">
        <div class="alert alert-danger" role="alert">
            <i class="feather-alert-triangle me-2"></i>
            <strong>Error:</strong> Task ID is required
        </div>
    </div>
    <?php
    exit;
}

// Get task details
$taskModel = new Task();
$task = $taskModel->getTaskById($taskId);

if (!$task) {
    http_response_code(404);
    ?>
    <div class="container-fluid" id="edit-task-not-found-container">
        <div class="alert alert-danger" role="alert">
            <i class="feather-alert-triangle me-2"></i>
            <strong>Error:</strong> Task not found
        </div>
    </div>
    <?php
    exit;
}

// Authorization check
if ($task['user_id'] != $user['id'] && $task['created_by'] != $user['id'] && !is_admin()) {
    http_response_code(403);
    ?>
    <div class="container-fluid" id="edit-task-unauthorized-container">
        <div class="alert alert-danger" role="alert">
            <i class="feather-alert-triangle me-2"></i>
            <strong>Access Denied:</strong> You do not have permission to edit this task
        </div>
    </div>
    <?php
    exit;
}

// Get current team context
$teamId = $_SESSION['selected_team_id'] ?? $task['team_id'];

// Get assignable users
$assignableUsers = $taskModel->getAssignableUsers($user['id'], $teamId);
?>

<!-- Edit Task Page Container -->
<div class="container-fluid" id="edit-task-page-container">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Edit Task &nbsp; </h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="#"
                       hx-get="/partials/dashboard/index.php"
                       hx-target="#page-content">
                        Home
                    </a>
                </li>
                <li class="breadcrumb-item">
                    <a href="#"
                       hx-get="/partials/tasks/my-tasks.php"
                       hx-target="#page-content">
                        Tasks
                    </a>
                </li>
                <li class="breadcrumb-item">Edit Task</li>
            </ul>
        </div>
    </div>

    <!-- Edit Form Card -->
    <div class="row" id="edit-task-form-row my-4">
        <div class="col-lg-8 col-md-10 mx-auto my-4">
            <div class="card" id="edit-task-form-card">
                <div class="card-header bg-light text-white" id="edit-task-form-card-header">
                    <h5 class="mb-0">
                        <i class="feather-edit me-2"></i>Task Details
                    </h5>
                </div>
                <div class="card-body" id="edit-task-form-card-body">

                    <!-- Result Messages -->
                    <div id="edit-task-page-result"></div>

                    <!-- Edit Form -->
                    <form id="editTaskPageForm"
                          hx-post="/partials/tasks/update.php?id=<?= htmlspecialchars($taskId) ?>"
                          hx-target="#edit-task-page-result"
                          hx-indicator="#edit-task-page-loading">

                        <!-- Title (Required) -->
                        <div class="mb-3" id="edit-task-page-title-group">
                            <label for="edit-task-page-title" class="form-label">
                                <i class="feather-file-text me-1"></i>
                                Title <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   class="form-control form-control-lg"
                                   id="edit-task-page-title"
                                   name="title"
                                   required
                                   maxlength="255"
                                   value="<?= htmlspecialchars($task['title']) ?>"
                                   autofocus>
                            <div class="form-text">Give your task a clear, descriptive title</div>
                        </div>

                        <!-- Description -->
                        <div class="mb-4" id="edit-task-page-description-group">
                            <label for="edit-task-page-description" class="form-label">
                                <i class="feather-align-left me-1"></i>
                                Description
                            </label>
                            <textarea class="form-control"
                                      id="edit-task-page-description"
                                      name="description"
                                      rows="6"
                                      maxlength="1000"><?= htmlspecialchars($task['description'] ?? '') ?></textarea>
                            <div class="form-text">Provide additional details about this task</div>
                        </div>

                        <!-- Status and Priority Row -->
                        <div class="row mb-3" id="edit-task-page-status-priority-row">
                            <!-- Status -->
                            <div class="col-md-6 mb-3" id="edit-task-page-status-group">
                                <label for="edit-task-page-status" class="form-label">
                                    <i class="feather-flag me-1"></i>
                                    Status
                                </label>
                                <select class="form-select" id="edit-task-page-status" name="status">
                                    <option value="pending" <?= $task['status'] === 'pending' ? 'selected' : '' ?>>
                                        📋 Pending
                                    </option>
                                    <option value="in_progress" <?= $task['status'] === 'in_progress' ? 'selected' : '' ?>>
                                        🔄 In Progress
                                    </option>
                                    <option value="review" <?= $task['status'] === 'review' ? 'selected' : '' ?>>
                                        👀 Review
                                    </option>
                                    <option value="completed" <?= $task['status'] === 'completed' ? 'selected' : '' ?>>
                                        ✅ Completed
                                    </option>
                                    <option value="cancelled" <?= $task['status'] === 'cancelled' ? 'selected' : '' ?>>
                                        ❌ Cancelled
                                    </option>
                                </select>
                            </div>

                            <!-- Priority -->
                            <div class="col-md-6 mb-3" id="edit-task-page-priority-group">
                                <label for="edit-task-page-priority" class="form-label">
                                    <i class="feather-alert-triangle me-1"></i>
                                    Priority
                                </label>
                                <select class="form-select" id="edit-task-page-priority" name="priority">
                                    <option value="low" <?= $task['priority'] === 'low' ? 'selected' : '' ?>>
                                        🟢 Low
                                    </option>
                                    <option value="medium" <?= $task['priority'] === 'medium' ? 'selected' : '' ?>>
                                        🟡 Medium
                                    </option>
                                    <option value="high" <?= $task['priority'] === 'high' ? 'selected' : '' ?>>
                                        🟠 High
                                    </option>
                                    <option value="critical" <?= $task['priority'] === 'critical' ? 'selected' : '' ?>>
                                        🔴 Critical
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Assignee and Due Date Row -->
                        <div class="row mb-3" id="edit-task-page-assignment-row">
                            <!-- Assigned To -->
                            <div class="col-md-6 mb-3" id="edit-task-page-assigned-group">
                                <label for="edit-task-page-assigned" class="form-label">
                                    <i class="feather-user me-1"></i>
                                    Assign To
                                </label>
                                <select class="form-select" id="edit-task-page-assigned" name="assigned_to">
                                    <option value="">Unassigned</option>
                                    <?php foreach ($assignableUsers as $assignUser): ?>
                                        <option value="<?= htmlspecialchars($assignUser['id']) ?>"
                                                <?= $task['assigned_to'] == $assignUser['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($assignUser['first_name'] . ' ' . $assignUser['last_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Due Date -->
                            <div class="col-md-6 mb-3" id="edit-task-page-due-date-group">
                                <label for="edit-task-page-due-date" class="form-label">
                                    <i class="feather-calendar me-1"></i>
                                    Due Date
                                </label>
                                <input type="date"
                                       class="form-control"
                                       id="edit-task-page-due-date"
                                       name="due_date"
                                       value="<?= htmlspecialchars($task['due_date'] ?? '') ?>">
                            </div>
                        </div>

                        <!-- Category and Tags Row -->
                        <div class="row mb-4" id="edit-task-page-meta-row">
                            <!-- Category -->
                            <div class="col-md-6 mb-3" id="edit-task-page-category-group">
                                <label for="edit-task-page-category" class="form-label">
                                    <i class="feather-tag me-1"></i>
                                    Category
                                </label>
                                <input type="text"
                                       class="form-control"
                                       id="edit-task-page-category"
                                       name="category"
                                       maxlength="100"
                                       placeholder="e.g., Development, Design, Marketing"
                                       value="<?= htmlspecialchars($task['category'] ?? '') ?>">
                            </div>

                            <!-- Tags -->
                            <div class="col-md-6 mb-3" id="edit-task-page-tags-group">
                                <label for="edit-task-page-tags" class="form-label">
                                    <i class="feather-tags me-1"></i>
                                    Tags
                                </label>
                                <input type="text"
                                       class="form-control"
                                       id="edit-task-page-tags"
                                       name="tags"
                                       placeholder="Comma-separated tags"
                                       value="<?= htmlspecialchars($task['tags'] ?? '') ?>">
                                <div class="form-text">Separate tags with commas</div>
                            </div>
                        </div>

                        <!-- CSRF Token -->
                        <?= csrf_field() ?>

                        <!-- Form Actions -->
                        <div class="d-flex gap-2 justify-content-end border-top pt-3" id="edit-task-page-form-actions">
                            <button type="button"
                                    class="btn btn-outline-secondary"
                                    id="btn-cancel-edit-task-page-bottom"
                                    hx-get="/partials/tasks/my-tasks.php"
                                    hx-target="#page-content"
                                    hx-swap="innerHTML">
                                <i class="feather-arrow-left me-2"></i>Back to Tasks
                            </button>
                            <button type="submit"
                                    class="btn btn-primary btn-lg"
                                    id="btn-submit-edit-task-page">
                                <span class="d-none" id="edit-task-page-loading">
                                    <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                    Saving...
                                </span>
                                <span id="edit-task-page-submit-text">
                                    <i class="feather-check-circle me-2"></i>Save Changes
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Task Info Card -->
            <div class="card mt-3" id="edit-task-info-card">
                <div class="card-body" id="edit-task-info-card-body">
                    <h6 class="card-title mb-3">
                        <i class="feather-info me-2"></i>Task Information
                    </h6>
                    <div class="row small text-muted" id="edit-task-info-details">
                        <div class="col-md-6 mb-2">
                            <strong>Created:</strong>
                            <?= date('M j, Y g:i A', strtotime($task['created_at'])) ?>
                        </div>
                        <div class="col-md-6 mb-2">
                            <strong>Created By:</strong>
                            <?= htmlspecialchars($task['creator_first_name'] . ' ' . $task['creator_last_name']) ?>
                        </div>
                        <?php if (!empty($task['updated_at']) && $task['updated_at'] != $task['created_at']): ?>
                            <div class="col-md-6 mb-2">
                                <strong>Last Updated:</strong>
                                <?= date('M j, Y g:i A', strtotime($task['updated_at'])) ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($task['status'] === 'completed' && !empty($task['completed_at'])): ?>
                            <div class="col-md-6 mb-2">
                                <strong>Completed:</strong>
                                <?= date('M j, Y g:i A', strtotime($task['completed_at'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- HTMX Response Handlers -->
<script>
document.body.addEventListener('htmx:afterRequest', function(evt) {
    if (evt.detail.elt.id === 'editTaskPageForm') {
        // Check if request was successful
        if (evt.detail.successful) {
            // Show the submit button text again
            document.getElementById('edit-task-page-loading').classList.add('d-none');
            document.getElementById('edit-task-page-submit-text').classList.remove('d-none');

            // Check if the response contains success alert
            var resultDiv = document.getElementById('edit-task-page-result');
            if (resultDiv && resultDiv.innerHTML.includes('alert-success')) {
                // Wait a moment to show the success message, then redirect
                setTimeout(function() {
                    htmx.ajax('GET', '/partials/tasks/my-tasks.php', {
                        target: '#page-content',
                        swap: 'innerHTML'
                    });
                }, 1000);
            } else {
                // If there's an error, scroll to show it
                resultDiv.scrollIntoView({ behavior: 'smooth' });
            }
        }
    }
});

document.body.addEventListener('htmx:beforeRequest', function(evt) {
    if (evt.detail.elt.id === 'editTaskPageForm') {
        // Hide submit button text, show loading
        document.getElementById('edit-task-page-loading').classList.remove('d-none');
        document.getElementById('edit-task-page-submit-text').classList.add('d-none');
    }
});
</script>

<style>
/* Edit Task Page Styles */
#edit-task-page-container {
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

#edit-task-form-card {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

#edit-task-info-card {
    background-color: #f8f9fa;
}

.form-label {
    font-weight: 600;
    color: #495057;
}

.form-control:focus,
.form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
}
</style>
