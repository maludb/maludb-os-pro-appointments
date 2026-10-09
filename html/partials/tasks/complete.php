<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../models/Task.php';
require_once __DIR__ . '/../../../models/Activity.php';

check_auth();
$user = get_user();

// Accept PATCH or POST methods
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'PATCH' && $method !== 'POST') {
    http_response_code(405);
    exit;
}

// Get task ID
$taskId = $_GET['id'] ?? null;

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

// Authorization check
if ($task['user_id'] != $user['id'] && $task['assigned_to'] != $user['id'] && !is_admin()) {
    http_response_code(403);
    exit;
}

// Toggle completion status
$isCurrentlyCompleted = ($task['status'] === 'completed');
$newStatus = $isCurrentlyCompleted ? 'in_progress' : 'completed';
$action = $isCurrentlyCompleted ? 'task_reopened' : 'task_completed';
$actionDesc = $isCurrentlyCompleted ? 'Reopened task' : 'Completed task';

try {
    // Update task status
    $result = $taskModel->update($taskId, ['status' => $newStatus]);

    if (!$result) {
        throw new Exception('Failed to update task status');
    }

    // Log activity
    $activityModel = new Activity();
    $activityModel->logActivity(
        $user['id'],
        $action,
        'task',
        $taskId,
        $actionDesc . ': ' . $task['title']
    );

    // Trigger refresh event
    header('HX-Trigger: {"taskStatusChanged": {"id": ' . $taskId . ', "status": "' . $newStatus . '"}, "refreshTaskList": true}');

    // Check if we're in a modal context (from view.php)
    // If so, reload the view modal, otherwise just return empty for table row refresh
    if (isset($_GET['from_modal']) || strpos($_SERVER['HTTP_REFERER'] ?? '', 'view.php') !== false) {
        // Reload the entire view modal to reflect the change
        include __DIR__ . '/view.php';
    } else {
        // For table context, trigger a list refresh
        http_response_code(204); // No content - let HX-Trigger handle refresh
    }

} catch (Exception $e) {
    error_log("Error toggling task completion: " . $e->getMessage());
    http_response_code(500);
    exit;
}
?>
