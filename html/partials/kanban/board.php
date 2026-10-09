<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../models/Task.php';

check_auth();
$user = get_user();

// Get current team context
$teamId = $_SESSION['selected_team_id'] ?? null;

// Get filter parameters
$filters = [];
if (!empty($_GET['assignee'])) {
    $filters['assignee'] = $_GET['assignee'];
}
if (!empty($_GET['priority'])) {
    $filters['priority'] = $_GET['priority'];
}
if (!empty($_GET['category'])) {
    $filters['category'] = $_GET['category'];
}
if (isset($teamId)) {
    $filters['team_id'] = $teamId;
}

// Get tasks
$taskModel = new Task();
$result = $taskModel->search($user['id'], $filters, [], 1, 1000);
$tasks = $result['tasks'];

// Hide completed if requested
$hideCompleted = isset($_GET['hide_completed']) && $_GET['hide_completed'] == '1';

// Group tasks by status
$tasksByStatus = [
    'pending' => [],
    'in_progress' => [],
    'review' => [],
    'completed' => []
];

foreach ($tasks as $task) {
    $status = $task['status'];
    if ($hideCompleted && $status === 'completed') {
        continue;
    }
    if (isset($tasksByStatus[$status])) {
        $tasksByStatus[$status][] = $task;
    }
}

// Column configuration
$columns = [
    'pending' => [
        'title' => 'To Do',
        'icon' => 'bi-circle',
        'class' => 'kanban-column-pending'
    ],
    'in_progress' => [
        'title' => 'In Progress',
        'icon' => 'bi-arrow-repeat',
        'class' => 'kanban-column-in_progress'
    ],
    'review' => [
        'title' => 'Review',
        'icon' => 'bi-eye',
        'class' => 'kanban-column-review'
    ],
    'completed' => [
        'title' => 'Done',
        'icon' => 'bi-check-circle',
        'class' => 'kanban-column-completed'
    ]
];
?>

<!-- Kanban Board -->
<div class="row g-3" id="kanban-board">
    <?php foreach ($columns as $status => $column): ?>
        <?php
        $columnTasks = $tasksByStatus[$status];
        $taskCount = count($columnTasks);
        ?>
        <!-- Column: <?= htmlspecialchars($column['title']) ?> -->
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12" id="kanban-column-<?= $status ?>-wrapper">
            <div class="kanban-column <?= $column['class'] ?>" id="kanban-column-<?= $status ?>">
                <!-- Column Header -->
                <div class="kanban-column-header" id="kanban-column-<?= $status ?>-header">
                    <div>
                        <i class="bi <?= $column['icon'] ?> me-2"></i>
                        <span><?= htmlspecialchars($column['title']) ?></span>
                    </div>
                    <span class="badge bg-white text-dark" id="kanban-column-<?= $status ?>-count">
                        <?= $taskCount ?>
                    </span>
                </div>

                <!-- Column Cards -->
                <div class="kanban-column-cards"
                     id="kanban-column-<?= $status ?>-cards"
                     data-status="<?= $status ?>">
                    <?php if (empty($columnTasks)): ?>
                        <!-- Empty State -->
                        <div class="kanban-empty-state" id="kanban-column-<?= $status ?>-empty">
                            <i class="bi <?= $column['icon'] ?>"></i>
                            <p class="mb-0">No tasks</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($columnTasks as $task): ?>
                            <?php
                            // Include card template
                            $cardTask = $task;
                            include __DIR__ . '/card.php';
                            ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Initialize SortableJS -->
<script>
(function() {
    // Wait for DOM to be ready
    if (typeof Sortable === 'undefined') {
        console.error('SortableJS library not loaded!');
        return;
    }

    // Initialize sortable for each column
    const columns = document.querySelectorAll('.kanban-column-cards');

    columns.forEach(function(column) {
        new Sortable(column, {
            group: 'kanban',
            animation: 300,
            ghostClass: 'sortable-ghost',
            dragClass: 'sortable-drag',
            handle: '.kanban-card',
            onEnd: function(evt) {
                // Get task info
                const taskId = evt.item.getAttribute('data-task-id');
                const oldStatus = evt.from.getAttribute('data-status');
                const newStatus = evt.to.getAttribute('data-status');

                // Only update if status changed
                if (oldStatus !== newStatus) {
                    // Show loading indicator
                    evt.item.style.opacity = '0.5';

                    // Send update request
                    fetch('/partials/kanban/update-status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'task_id=' + encodeURIComponent(taskId) +
                              '&new_status=' + encodeURIComponent(newStatus) +
                              '&old_status=' + encodeURIComponent(oldStatus)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update card status attribute
                            evt.item.setAttribute('data-status', newStatus);
                            evt.item.style.opacity = '1';

                            // Update column counts
                            updateColumnCounts();

                            // Show success notification (optional)
                            console.log('Task status updated successfully');
                        } else {
                            // Revert on error
                            console.error('Failed to update task status:', data.error);
                            evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex]);
                            evt.item.style.opacity = '1';
                            alert('Failed to update task status: ' + (data.error || 'Unknown error'));
                        }
                    })
                    .catch(error => {
                        console.error('Error updating task status:', error);
                        // Revert on error
                        evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex]);
                        evt.item.style.opacity = '1';
                        alert('Error updating task status. Please try again.');
                    });
                }
            }
        });
    });

    // Function to update column counts
    function updateColumnCounts() {
        const statuses = ['pending', 'in_progress', 'review', 'completed'];

        statuses.forEach(function(status) {
            const column = document.getElementById('kanban-column-' + status + '-cards');
            if (column) {
                const count = column.querySelectorAll('.kanban-card').length;
                const countBadge = document.getElementById('kanban-column-' + status + '-count');
                if (countBadge) {
                    countBadge.textContent = count;
                }

                // Show/hide empty state
                const emptyState = document.getElementById('kanban-column-' + status + '-empty');
                if (emptyState) {
                    emptyState.style.display = count === 0 ? 'block' : 'none';
                }
            }
        });
    }
})();
</script>
