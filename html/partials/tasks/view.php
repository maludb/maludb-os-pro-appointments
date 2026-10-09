<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/ui.php';
require_once __DIR__ . '/../../../helpers/date.php';
require_once __DIR__ . '/../../../models/Task.php';
require_once __DIR__ . '/../../../models/Activity.php';

check_auth();
$user = get_user();

// Get task ID from query parameter
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

// Authorization check - user must own or be assigned to task
if ($task['user_id'] != $user['id'] && $task['assigned_to'] != $user['id'] && !is_admin()) {
    http_response_code(403);
    echo '<div class="alert alert-danger">You do not have permission to view this task</div>';
    exit;
}

// Get task activities
$activityModel = new Activity();
$activities = $activityModel->getTaskActivities($taskId, 20);

// Determine if task is overdue
$isOverdue = isOverdue($task['due_date'], $task['status']);
$isCompleted = $task['status'] === 'completed';
?>

<!-- Task View Modal -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="viewTaskModal" tabindex="-1" aria-labelledby="viewTaskModalLabel">
    <div class="modal-dialog modal-xl" id="view-task-modal-dialog">
        <div class="modal-content" id="view-task-modal-content">
            <div class="modal-header" id="view-task-modal-header">
                <h5 class="modal-title" id="viewTaskModalLabel">
                    <i class="feather-file-text me-2"></i>Task Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body" id="view-task-modal-body">
                <div class="row" id="view-task-content-row">
                    <!-- Left Column: Task Details -->
                    <div class="col-lg-8" id="view-task-details-col">
                        <!-- Task Title and Status -->
                        <div class="card mb-3" id="view-task-title-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3" id="view-task-title-header">
                                    <h4 class="mb-0" id="view-task-title">
                                        <?php echo htmlspecialchars($task['title']); ?>
                                        <?php if ($isOverdue): ?>
                                            <span class="badge bg-danger ms-2">Overdue</span>
                                        <?php endif; ?>
                                    </h4>
                                    <div id="view-task-status-badges">
                                        <?php echo getStatusBadge($task['status']); ?>
                                        <?php echo getPriorityBadge($task['priority']); ?>
                                    </div>
                                </div>

                                <!-- Description -->
                                <?php if ($task['description']): ?>
                                    <div class="mb-3" id="view-task-description-section">
                                        <h6 class="text-muted mb-2">Description</h6>
                                        <p class="mb-0" id="view-task-description">
                                            <?php echo nl2br(htmlspecialchars($task['description'])); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>

                                <!-- Task Metadata -->
                                <div class="row mt-3" id="view-task-metadata-row">
                                    <!-- Created By -->
                                    <div class="col-md-6 mb-2" id="view-task-created-by-col">
                                        <small class="text-muted">Created By:</small><br>
                                        <strong id="view-task-created-by">
                                            <?php echo htmlspecialchars($task['creator_first_name'] . ' ' . $task['creator_last_name']); ?>
                                        </strong>
                                    </div>

                                    <!-- Assigned To -->
                                    <div class="col-md-6 mb-2" id="view-task-assigned-to-col">
                                        <small class="text-muted">Assigned To:</small><br>
                                        <strong id="view-task-assigned-to">
                                            <?php if ($task['assigned_to']): ?>
                                                <?php echo htmlspecialchars($task['first_name'] . ' ' . $task['last_name']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Unassigned</span>
                                            <?php endif; ?>
                                        </strong>
                                    </div>

                                    <!-- Due Date -->
                                    <div class="col-md-6 mb-2" id="view-task-due-date-col">
                                        <small class="text-muted">Due Date:</small><br>
                                        <strong id="view-task-due-date">
                                            <?php if ($task['due_date']): ?>
                                                <?php echo formatDate($task['due_date']); ?>
                                                <?php if ($isOverdue): ?>
                                                    <span class="text-danger">(<?php echo getFriendlyDueDate($task['due_date'], $task['status']); ?>)</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">No due date</span>
                                            <?php endif; ?>
                                        </strong>
                                    </div>

                                    <!-- Category -->
                                    <?php if ($task['category']): ?>
                                        <div class="col-md-6 mb-2" id="view-task-category-col">
                                            <small class="text-muted">Category:</small><br>
                                            <span class="badge bg-secondary" id="view-task-category">
                                                <?php echo htmlspecialchars($task['category']); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Tags -->
                                    <?php if ($task['tags']): ?>
                                        <div class="col-md-12 mb-2" id="view-task-tags-col">
                                            <small class="text-muted">Tags:</small><br>
                                            <div id="view-task-tags">
                                                <?php
                                                $tags = explode(',', $task['tags']);
                                                foreach ($tags as $tag):
                                                    $tag = trim($tag);
                                                    if ($tag):
                                                ?>
                                                    <span class="badge bg-light text-dark me-1"><?php echo htmlspecialchars($tag); ?></span>
                                                <?php
                                                    endif;
                                                endforeach;
                                                ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Created At -->
                                    <div class="col-md-6 mb-2" id="view-task-created-at-col">
                                        <small class="text-muted">Created:</small><br>
                                        <span id="view-task-created-at"><?php echo formatDateTime($task['created_at']); ?></span>
                                    </div>

                                    <!-- Completed At -->
                                    <?php if ($task['completed_at']): ?>
                                        <div class="col-md-6 mb-2" id="view-task-completed-at-col">
                                            <small class="text-muted">Completed:</small><br>
                                            <span id="view-task-completed-at"><?php echo formatDateTime($task['completed_at']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="card" id="view-task-actions-card">
                            <div class="card-body">
                                <h6 class="card-title mb-3">Quick Actions</h6>
                                <div class="btn-group" role="group" id="view-task-actions-group">
                                    <!-- Edit Button -->
                                    <button type="button"
                                            class="btn btn-outline-primary"
                                            id="btn-edit-task"
                                            hx-get="/partials/tasks/edit-task.php?id=<?php echo $taskId; ?>"
                                            hx-target="#page-content"
                                            hx-swap="innerHTML">
                                        <i class="feather-edit me-1"></i>Edit
                                    </button>

                                    <!-- Complete/Uncomplete Toggle -->
                                    <?php if (!$isCompleted): ?>
                                        <button type="button"
                                                class="btn btn-outline-success"
                                                id="btn-complete-task"
                                                hx-patch="/partials/tasks/complete.php?id=<?php echo $taskId; ?>"
                                                hx-target="#view-task-modal-content"
                                                hx-swap="outerHTML">
                                            <i class="feather-check-circle me-1"></i>Mark Complete
                                        </button>
                                    <?php else: ?>
                                        <button type="button"
                                                class="btn btn-outline-warning"
                                                id="btn-reopen-task"
                                                hx-patch="/partials/tasks/complete.php?id=<?php echo $taskId; ?>"
                                                hx-target="#view-task-modal-content"
                                                hx-swap="outerHTML">
                                            <i class="feather-rotate-ccw me-1"></i>Reopen
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($isCompleted): ?>
                                        <!-- Archive Button for Completed Tasks -->
                                        <button type="button"
                                                class="btn btn-outline-warning"
                                                id="btn-archive-task"
                                                hx-post="/partials/tasks/delete.php?id=<?php echo $taskId; ?>&action=archive"
                                                hx-confirm="Are you sure you want to archive this completed task?"
                                                hx-target="body"
                                                hx-swap="none">
                                            <i class="feather-archive me-1"></i>Archive
                                        </button>
                                    <?php else: ?>
                                        <!-- Delete Button for Other Tasks -->
                                        <button type="button"
                                                class="btn btn-outline-danger"
                                                id="btn-delete-task"
                                                hx-delete="/partials/tasks/delete.php?id=<?php echo $taskId; ?>&action=delete"
                                                hx-confirm="Are you sure you want to permanently delete this task? This action cannot be undone."
                                                hx-target="body"
                                                hx-swap="none">
                                            <i class="feather-trash-2 me-1"></i>Delete
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Activity Timeline -->
                    <div class="col-lg-4" id="view-task-activity-col">
                        <div class="card" id="view-task-activity-card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="feather-clock-history me-2"></i>Activity Timeline</h6>
                            </div>
                            <div class="card-body" id="view-task-activity-body" style="max-height: 500px; overflow-y: auto;">
                                <?php if (count($activities) > 0): ?>
                                    <div class="timeline" id="task-activity-timeline">
                                        <?php foreach ($activities as $activity): ?>
                                            <div class="timeline-item mb-3" id="activity-<?php echo $activity['id']; ?>">
                                                <div class="d-flex">
                                                    <div class="flex-shrink-0 me-3">
                                                        <div class="rounded-circle bg-<?php echo $activity['color']; ?> text-white d-flex align-items-center justify-content-center"
                                                             style="width: 36px; height: 36px;">
                                                            <i class="bi bi-<?php echo $activity['icon']; ?>"></i>
                                                        </div>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <div class="mb-1">
                                                            <small class="text-muted"><?php echo timeAgo($activity['created_at']); ?></small>
                                                        </div>
                                                        <p class="mb-0">
                                                            <strong><?php echo htmlspecialchars($activity['user_name']); ?></strong>
                                                            <?php echo htmlspecialchars($activity['description']); ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-4" id="view-task-no-activity">
                                        <i class="feather-clock-history fs-1 d-block mb-2"></i>
                                        <p>No activity yet</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer" id="view-task-modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="btn-close-view-task">
                    Close
                </button>
                <button type="button"
                        class="btn btn-primary"
                        id="btn-edit-task-footer"
                        hx-get="/partials/tasks/edit-task.php?id=<?php echo $taskId; ?>"
                        hx-target="#page-content"
                        hx-swap="innerHTML"
                        data-bs-dismiss="modal">
                    <i class="feather-edit me-1"></i>Edit Task
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal will be shown automatically by HTMX afterSwap event in app.php -->
