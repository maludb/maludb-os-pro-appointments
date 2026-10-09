<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../models/Task.php';

check_auth();
$user = get_user();

// Get task ID
$taskId = $_GET['id'] ?? null;

if (!$taskId) {
    http_response_code(400);
    echo '<div class="alert alert-danger">Task ID is required</div>';
    exit;
}

// Get task details
$taskModel = new Task();
$task = $taskModel->getTaskById($taskId);

if (!$task) {
    http_response_code(404);
    echo '<div class="alert alert-danger">Task not found</div>';
    exit;
}

// Authorization check
if ($task['user_id'] != $user['id'] && $task['created_by'] != $user['id'] && !is_admin()) {
    http_response_code(403);
    echo '<div class="alert alert-danger">You do not have permission to edit this task</div>';
    exit;
}

// Get current team context
$teamId = $_SESSION['selected_team_id'] ?? $task['team_id'];

// Get assignable users
$assignableUsers = $taskModel->getAssignableUsers($user['id'], $teamId);
?>

<!-- Task Edit Modal -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="editTaskModal" tabindex="-1" aria-labelledby="editTaskModalLabel">
    <div class="modal-dialog modal-lg" id="edit-task-modal-dialog">
        <div class="modal-content" id="edit-task-modal-content">
            <div class="modal-header" id="edit-task-modal-header">
                <h5 class="modal-title" id="editTaskModalLabel">
                    <i class="feather-edit me-2"></i>Edit Task
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="editTaskForm"
                  hx-post="/partials/tasks/update.php?id=<?php echo htmlspecialchars($taskId); ?>"
                  hx-target="#task-edit-result"
                  hx-indicator="#edit-task-loading">

                <div class="modal-body" id="edit-task-modal-body">
                    <!-- Result Messages -->
                    <div id="task-edit-result"></div>

                    <!-- Title (Required) -->
                    <div class="mb-3" id="edit-task-title-group">
                        <label for="edit-task-title" class="form-label">
                            Title <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control"
                               id="edit-task-title"
                               name="title"
                               required
                               maxlength="255"
                               value="<?php echo htmlspecialchars($task['title']); ?>">
                    </div>

                    <!-- Description -->
                    <div class="mb-3" id="edit-task-description-group">
                        <label for="edit-task-description" class="form-label">Description</label>
                        <textarea class="form-control"
                                  id="edit-task-description"
                                  name="description"
                                  rows="4"
                                  maxlength="1000"><?php echo htmlspecialchars($task['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="row" id="edit-task-fields-row">
                        <!-- Status -->
                        <div class="col-md-6 mb-3" id="edit-task-status-group">
                            <label for="edit-task-status" class="form-label">Status</label>
                            <select class="form-select" id="edit-task-status" name="status">
                                <option value="pending" <?php echo $task['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="in_progress" <?php echo $task['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="review" <?php echo $task['status'] === 'review' ? 'selected' : ''; ?>>Review</option>
                                <option value="completed" <?php echo $task['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $task['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>

                        <!-- Priority -->
                        <div class="col-md-6 mb-3" id="edit-task-priority-group">
                            <label for="edit-task-priority" class="form-label">Priority</label>
                            <select class="form-select" id="edit-task-priority" name="priority">
                                <option value="low" <?php echo $task['priority'] === 'low' ? 'selected' : ''; ?>>Low</option>
                                <option value="medium" <?php echo $task['priority'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="high" <?php echo $task['priority'] === 'high' ? 'selected' : ''; ?>>High</option>
                                <option value="critical" <?php echo $task['priority'] === 'critical' ? 'selected' : ''; ?>>Critical</option>
                            </select>
                        </div>
                    </div>

                    <div class="row" id="edit-task-assignment-row">
                        <!-- Assigned To -->
                        <div class="col-md-6 mb-3" id="edit-task-assigned-group">
                            <label for="edit-task-assigned" class="form-label">Assign To</label>
                            <select class="form-select" id="edit-task-assigned" name="assigned_to">
                                <option value="">Unassigned</option>
                                <?php foreach ($assignableUsers as $assignUser): ?>
                                    <option value="<?php echo htmlspecialchars($assignUser['id']); ?>"
                                            <?php echo $task['assigned_to'] == $assignUser['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($assignUser['first_name'] . ' ' . $assignUser['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Due Date -->
                        <div class="col-md-6 mb-3" id="edit-task-due-date-group">
                            <label for="edit-task-due-date" class="form-label">Due Date</label>
                            <input type="date"
                                   class="form-control"
                                   id="edit-task-due-date"
                                   name="due_date"
                                   value="<?php echo htmlspecialchars($task['due_date'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="row" id="edit-task-meta-row">
                        <!-- Category -->
                        <div class="col-md-6 mb-3" id="edit-task-category-group">
                            <label for="edit-task-category" class="form-label">Category</label>
                            <input type="text"
                                   class="form-control"
                                   id="edit-task-category"
                                   name="category"
                                   maxlength="100"
                                   value="<?php echo htmlspecialchars($task['category'] ?? ''); ?>">
                        </div>

                        <!-- Tags -->
                        <div class="col-md-6 mb-3" id="edit-task-tags-group">
                            <label for="edit-task-tags" class="form-label">Tags</label>
                            <input type="text"
                                   class="form-control"
                                   id="edit-task-tags"
                                   name="tags"
                                   value="<?php echo htmlspecialchars($task['tags'] ?? ''); ?>">
                        </div>
                    </div>

                    <!-- CSRF Token -->
                    <?php echo csrf_field(); ?>
                </div>

                <div class="modal-footer" id="edit-task-modal-footer">
                    <button type="button"
                            class="btn btn-secondary"
                            id="btn-cancel-edit-task"
                            hx-get="/partials/tasks/view.php?id=<?php echo $taskId; ?>"
                            hx-target="#modal-container"
                            hx-swap="innerHTML">
                        <i class="feather-x-circle me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="btn-submit-edit-task">
                        <span class="d-none" id="edit-task-loading">
                            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        </span>
                        <i class="feather-check-circle me-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal will be shown automatically by HTMX afterSwap event in app.php -->
