<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../models/Task.php';

check_auth();
$user = get_user();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-danger">Method not allowed</div>';
    exit;
}

// Get action and task IDs
$action = $_POST['action'] ?? '';
$taskIds = $_POST['task_ids'] ?? [];
$value = $_POST['value'] ?? '';

// Validate inputs
if (empty($action) || empty($taskIds)) {
    echo '<div class="alert alert-danger">Missing required parameters</div>';
    exit;
}

// Ensure task IDs are integers
$taskIds = array_map('intval', $taskIds);
$taskIds = array_filter($taskIds, function($id) { return $id > 0; });

if (empty($taskIds)) {
    echo '<div class="alert alert-danger">No valid task IDs provided</div>';
    exit;
}

$taskModel = new Task();
$count = 0;
$message = '';

try {
    switch ($action) {
        case 'complete':
            // Mark tasks as completed
            $updates = ['status' => 'completed'];
            $count = $taskModel->updateBulk($taskIds, $updates, $user['id']);
            $message = "Successfully marked {$count} task(s) as completed.";
            break;

        case 'status':
            // Change task status
            if (empty($value) || !in_array($value, ['pending', 'in_progress', 'review', 'completed', 'cancelled', 'archived'])) {
                echo '<div class="alert alert-danger">Invalid status value</div>';
                exit;
            }
            $updates = ['status' => $value];
            $count = $taskModel->updateBulk($taskIds, $updates, $user['id']);
            $message = "Successfully updated {$count} task(s) to " . ucfirst(str_replace('_', ' ', $value)) . ".";
            break;

        case 'priority':
            // Change task priority
            if (empty($value) || !in_array($value, ['low', 'medium', 'high', 'critical'])) {
                echo '<div class="alert alert-danger">Invalid priority value</div>';
                exit;
            }
            $updates = ['priority' => $value];
            $count = $taskModel->updateBulk($taskIds, $updates, $user['id']);
            $message = "Successfully updated {$count} task(s) to " . ucfirst($value) . " priority.";
            break;

        case 'delete':
            // Separate tasks by status - archive completed, delete others
            $completedIds = [];
            $otherIds = [];

            foreach ($taskIds as $id) {
                $task = $taskModel->getTaskById($id);
                if ($task && $task['status'] === 'completed') {
                    $completedIds[] = $id;
                } else if ($task) {
                    $otherIds[] = $id;
                }
            }

            $archiveCount = 0;
            $deleteCount = 0;

            if (!empty($completedIds)) {
                $archiveCount = $taskModel->archiveBulk($completedIds, $user['id']);
            }

            if (!empty($otherIds)) {
                $deleteCount = $taskModel->deleteBulk($otherIds, $user['id']);
            }

            $count = $archiveCount + $deleteCount;

            if ($archiveCount > 0 && $deleteCount > 0) {
                $message = "Successfully archived {$archiveCount} completed task(s) and deleted {$deleteCount} task(s).";
            } else if ($archiveCount > 0) {
                $message = "Successfully archived {$archiveCount} completed task(s).";
            } else {
                $message = "Successfully deleted {$deleteCount} task(s).";
            }
            break;

        default:
            echo '<div class="alert alert-danger">Invalid action</div>';
            exit;
    }

    if ($count > 0) {
        echo '<div class="alert alert-success">' . htmlspecialchars($message) . '</div>';

        // Trigger table refresh event
        header('HX-Trigger: taskUpdated');
    } else {
        echo '<div class="alert alert-warning">No tasks were updated. Make sure you have permission to modify these tasks.</div>';
    }

} catch (Exception $e) {
    error_log("Bulk action error: " . $e->getMessage());
    echo '<div class="alert alert-danger">An error occurred. Please try again.</div>';
}
