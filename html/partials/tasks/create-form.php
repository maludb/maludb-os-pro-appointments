<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../models/Task.php';

check_auth();
$user = get_user();

// Get current team context
$teamId = $_SESSION['selected_team_id'] ?? null;

// Get assignable users for dropdown
$taskModel = new Task();
$assignableUsers = $taskModel->getAssignableUsers($user['id'], $teamId);
?>

<!-- Task Create Modal -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="createTaskModal" tabindex="-1" aria-labelledby="createTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" id="create-task-modal-dialog">
        <div class="modal-content" id="create-task-modal-content">
            <div class="modal-header" id="create-task-modal-header">
                <h5 class="modal-title" id="createTaskModalLabel">
                    <i class="feather-plus-circle me-2"></i>Create New Task
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="createTaskForm"
                  hx-post="/partials/tasks/create.php"
                  hx-target="#task-create-result"
                  hx-indicator="#create-task-loading">

                <div class="modal-body" id="create-task-modal-body">
                    <!-- Result Messages -->
                    <div id="task-create-result"></div>

                    <!-- Title (Required) -->
                    <div class="mb-3" id="create-task-title-group">
                        <label for="create-task-title" class="form-label">
                            Title <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control"
                               id="create-task-title"
                               name="title"
                               required
                               maxlength="255"
                               placeholder="Enter task title">
                        <div class="form-text">Maximum 255 characters</div>
                    </div>

                    <!-- Description -->
                    <div class="mb-3" id="create-task-description-group">
                        <label for="create-task-description" class="form-label">Description</label>
                        <textarea class="form-control"
                                  id="create-task-description"
                                  name="description"
                                  rows="4"
                                  maxlength="1000"
                                  placeholder="Enter task description (optional)"></textarea>
                        <div class="form-text">Maximum 1000 characters</div>
                    </div>

                    <div class="row" id="create-task-fields-row">
                        <!-- Status -->
                        <div class="col-md-6 mb-3" id="create-task-status-group">
                            <label for="create-task-status" class="form-label">Status</label>
                            <select class="form-select" id="create-task-status" name="status">
                                <option value="pending" selected>Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="review">Review</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>

                        <!-- Priority -->
                        <div class="col-md-6 mb-3" id="create-task-priority-group">
                            <label for="create-task-priority" class="form-label">Priority</label>
                            <select class="form-select" id="create-task-priority" name="priority">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                    </div>

                    <div class="row" id="create-task-assignment-row">
                        <!-- Assigned To -->
                        <div class="col-md-6 mb-3" id="create-task-assigned-group">
                            <label for="create-task-assigned" class="form-label">Assign To</label>
                            <select class="form-select" id="create-task-assigned" name="assigned_to">
                                <option value="<?php echo htmlspecialchars($user['id']); ?>" selected>
                                    Me (<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>)
                                </option>
                                <?php foreach ($assignableUsers as $assignUser): ?>
                                    <?php if ($assignUser['id'] != $user['id']): ?>
                                        <option value="<?php echo htmlspecialchars($assignUser['id']); ?>">
                                            <?php echo htmlspecialchars($assignUser['first_name'] . ' ' . $assignUser['last_name']); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Due Date -->
                        <div class="col-md-6 mb-3" id="create-task-due-date-group">
                            <label for="create-task-due-date" class="form-label">Due Date</label>
                            <input type="date"
                                   class="form-control"
                                   id="create-task-due-date"
                                   name="due_date"
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div class="row" id="create-task-meta-row">
                        <!-- Category -->
                        <div class="col-md-6 mb-3" id="create-task-category-group">
                            <label for="create-task-category" class="form-label">Category</label>
                            <input type="text"
                                   class="form-control"
                                   id="create-task-category"
                                   name="category"
                                   maxlength="100"
                                   placeholder="e.g., Development, Design, Testing">
                        </div>

                        <!-- Tags -->
                        <div class="col-md-6 mb-3" id="create-task-tags-group">
                            <label for="create-task-tags" class="form-label">Tags</label>
                            <input type="text"
                                   class="form-control"
                                   id="create-task-tags"
                                   name="tags"
                                   placeholder="e.g., urgent, bug, feature">
                            <div class="form-text">Separate tags with commas</div>
                        </div>
                    </div>

                    <!-- CSRF Token -->
                    <?php echo csrf_field(); ?>

                    <!-- Hidden team_id if applicable -->
                    <?php if ($teamId): ?>
                        <input type="hidden" name="team_id" value="<?php echo htmlspecialchars($teamId); ?>">
                    <?php endif; ?>
                </div>

                <div class="modal-footer" id="create-task-modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="btn-cancel-create-task">
                        <i class="feather-x-circle me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="btn-submit-create-task">
                        <span class="d-none" id="create-task-loading">
                            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        </span>
                        <i class="feather-check-circle me-2"></i>Create Task
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal will be shown automatically by HTMX afterSwap event in app.php -->
