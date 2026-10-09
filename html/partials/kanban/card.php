<?php
/**
 * Kanban Card Template
 * This file is included from board.php with $cardTask variable containing task data
 */

// Ensure we have task data
if (!isset($cardTask)) {
    return;
}

$task = $cardTask;
$taskId = $task['id'];
$priority = $task['priority'] ?? 'medium';
$status = $task['status'] ?? 'pending';

// Format due date
$dueDate = null;
$dueDateClass = '';
$dueDateText = '';
if (!empty($task['due_date'])) {
    $dueDate = new DateTime($task['due_date']);
    $now = new DateTime();
    $dueDateText = $dueDate->format('M j');

    if ($dueDate < $now && $status !== 'completed') {
        $dueDateClass = 'text-danger';
        $dueDateText .= ' (Overdue)';
    }
}

// Get assignee initials
$assigneeInitials = '';
$assigneeName = '';
if (!empty($task['first_name']) && !empty($task['last_name'])) {
    $assigneeInitials = strtoupper(substr($task['first_name'], 0, 1) . substr($task['last_name'], 0, 1));
    $assigneeName = htmlspecialchars($task['first_name'] . ' ' . $task['last_name']);
}

// Parse tags
$tags = [];
if (!empty($task['tags'])) {
    $tags = explode(',', $task['tags']);
}
?>

<!-- Kanban Card -->
<div class="kanban-card"
     id="kanban-card-<?= $taskId ?>"
     data-task-id="<?= $taskId ?>"
     data-status="<?= htmlspecialchars($status) ?>"
     data-priority="<?= htmlspecialchars($priority) ?>">

    <!-- Card Title -->
    <div class="kanban-card-title"
         id="kanban-card-<?= $taskId ?>-title"
         hx-get="/partials/tasks/view.php?id=<?= $taskId ?>"
         hx-target="#modal-container"
         hx-swap="innerHTML">
        <?= htmlspecialchars($task['title']) ?>
    </div>

    <!-- Card Meta -->
    <div class="kanban-card-meta" id="kanban-card-<?= $taskId ?>-meta">
        <!-- Priority Badge -->
        <span class="priority-badge priority-<?= htmlspecialchars($priority) ?>"
              id="kanban-card-<?= $taskId ?>-priority">
            <?= htmlspecialchars(ucfirst($priority)) ?>
        </span>

        <!-- Due Date -->
        <?php if ($dueDate): ?>
            <span class="<?= $dueDateClass ?>" id="kanban-card-<?= $taskId ?>-due-date">
                <i class="feather-calendar"></i>
                <?= $dueDateText ?>
            </span>
        <?php endif; ?>

        <!-- Category -->
        <?php if (!empty($task['category'])): ?>
            <span class="text-muted" id="kanban-card-<?= $taskId ?>-category">
                <i class="feather-tag"></i>
                <?= htmlspecialchars($task['category']) ?>
            </span>
        <?php endif; ?>

        <!-- Assignee -->
        <?php if ($assigneeInitials): ?>
            <span class="assignee-avatar"
                  id="kanban-card-<?= $taskId ?>-assignee"
                  title="<?= $assigneeName ?>"
                  data-bs-toggle="tooltip">
                <?= $assigneeInitials ?>
            </span>
        <?php endif; ?>
    </div>

    <!-- Tags -->
    <?php if (!empty($tags)): ?>
        <div class="mt-2" id="kanban-card-<?= $taskId ?>-tags">
            <?php foreach ($tags as $tag): ?>
                <span class="badge bg-secondary me-1" style="font-size: 10px;">
                    <?= htmlspecialchars(trim($tag)) ?>
                </span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Card Actions -->
    <div class="kanban-card-actions" id="kanban-card-<?= $taskId ?>-actions">
        <!-- View Button -->
        <button class="btn btn-sm btn-outline-primary"
                id="kanban-card-<?= $taskId ?>-btn-view"
                hx-get="/partials/tasks/view.php?id=<?= $taskId ?>"
                hx-target="#modal-container"
                hx-swap="innerHTML"
                title="View Details"
                data-bs-toggle="tooltip">
            <i class="feather-eye"></i>
        </button>

        <!-- Edit Button -->
        <button class="btn btn-sm btn-outline-secondary"
                id="kanban-card-<?= $taskId ?>-btn-edit"
                hx-get="/partials/tasks/edit-task.php?id=<?= $taskId ?>"
                hx-target="#page-content"
                hx-swap="innerHTML"
                title="Edit Task"
                data-bs-toggle="tooltip">
            <i class="feather-edit"></i>
        </button>

        <?php if ($status === 'completed'): ?>
            <!-- Archive Button for Completed Tasks -->
            <button class="btn btn-sm btn-outline-warning"
                    id="kanban-card-<?= $taskId ?>-btn-archive"
                    hx-post="/partials/tasks/delete.php?id=<?= $taskId ?>&action=archive"
                    hx-confirm="Are you sure you want to archive this completed task?"
                    hx-target="#kanban-card-<?= $taskId ?>"
                    hx-swap="outerHTML swap:0.3s"
                    title="Archive Task"
                    data-bs-toggle="tooltip">
                <i class="feather-archive"></i>
            </button>
        <?php else: ?>
            <!-- Delete Button for Other Tasks -->
            <button class="btn btn-sm btn-outline-danger"
                    id="kanban-card-<?= $taskId ?>-btn-delete"
                    hx-delete="/partials/tasks/delete.php?id=<?= $taskId ?>&action=delete"
                    hx-confirm="Are you sure you want to delete this task?"
                    hx-target="#kanban-card-<?= $taskId ?>"
                    hx-swap="outerHTML swap:0.3s"
                    title="Delete Task"
                    data-bs-toggle="tooltip">
                <i class="feather-trash-2"></i>
            </button>
        <?php endif; ?>
    </div>
</div>
