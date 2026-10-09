<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../models/Task.php';
require_once __DIR__ . '/../../../models/Activity.php';

check_auth();
$user = get_user();

// Accept both DELETE and POST methods
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'DELETE' && $method !== 'POST') {
    http_response_code(405);
    exit;
}

// Get task ID and action
$taskId = $_GET['id'] ?? null;
$action = $_GET['action'] ?? 'archive'; // Default to archive for backward compatibility

if (!$taskId) {
    http_response_code(400);
    exit;
}

$taskModel = new Task();
$task = $taskModel->getTaskById($taskId);

if (!$task) {
    http_response_code(404);
    exit;
}

// Authorization check - only owner or creator can delete/archive
if ($task['user_id'] != $user['id'] && $task['created_by'] != $user['id'] && !is_admin()) {
    http_response_code(403);
    exit;
}

try {
    $activityModel = new Activity();

    if ($action === 'delete') {
        // Permanently delete task

        // Log activity before deletion
        $activityModel->logActivity(
            $user['id'],
            'task_deleted',
            'task',
            $taskId,
            'Deleted task: ' . $task['title']
        );

        // Delete task
        $result = $taskModel->delete($taskId, $user['id']);

        if (!$result) {
            throw new Exception('Failed to delete task');
        }

        // Trigger refresh event and close modal if open
        header('HX-Trigger: {"taskDeleted": {"id": ' . $taskId . '}, "refreshTaskList": true, "closeModal": true}');

    } else {
        // Archive task (for completed tasks)

        // Log activity before archiving
        $activityModel->logActivity(
            $user['id'],
            'task_archived',
            'task',
            $taskId,
            'Archived task: ' . $task['title']
        );

        // Archive task
        $result = $taskModel->archive($taskId, $user['id']);

        if (!$result) {
            throw new Exception('Failed to archive task');
        }

        // Trigger refresh event and close modal if open
        header('HX-Trigger: {"taskArchived": {"id": ' . $taskId . '}, "refreshTaskList": true, "closeModal": true}');
    }

    // Return empty content (removes the row via swap)
    http_response_code(200);
    echo '';

} catch (Exception $e) {
    $errorAction = ($action === 'delete') ? 'deleting' : 'archiving';
    error_log("Error $errorAction task: " . $e->getMessage());
    http_response_code(500);
    echo '<div class="alert alert-danger">Failed to ' . htmlspecialchars($errorAction) . ' task</div>';
}
?>
