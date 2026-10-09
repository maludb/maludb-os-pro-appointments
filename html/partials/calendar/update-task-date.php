<?php
session_start();
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../models/Task.php';
require_once __DIR__ . '/../../../models/Activity.php';

check_auth();
$user = get_user();

header('Content-Type: application/json');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    // Get parameters
    $taskId = $_POST['task_id'] ?? null;
    $dueDate = $_POST['due_date'] ?? null;

    if (!$taskId || !$dueDate) {
        echo json_encode(['success' => false, 'error' => 'Task ID and due date are required']);
        exit;
    }

    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
        echo json_encode(['success' => false, 'error' => 'Invalid date format']);
        exit;
    }

    // Get task to verify ownership
    $taskModel = new Task();
    $task = $taskModel->getTaskById($taskId);

    if (!$task) {
        echo json_encode(['success' => false, 'error' => 'Task not found']);
        exit;
    }

    // Check authorization (owner, creator, or assigned user)
    if ($task['user_id'] != $user['id'] && $task['assigned_to'] != $user['id'] && $task['created_by'] != $user['id']) {
        echo json_encode(['success' => false, 'error' => 'You do not have permission to update this task']);
        exit;
    }

    // Update task due date
    $success = $taskModel->update($taskId, ['due_date' => $dueDate]);

    if (!$success) {
        echo json_encode(['success' => false, 'error' => 'Failed to update task due date']);
        exit;
    }

    // Log activity
    $activityModel = new Activity();
    $activityModel->logActivity(
        $user['id'],
        'task_updated',
        'task',
        $taskId,
        "Updated due date to " . $dueDate . " for task: " . $task['title']
    );

    echo json_encode(['success' => true, 'message' => 'Task due date updated successfully']);

} catch (Exception $e) {
    error_log("Error updating task date: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred while updating the task']);
}
