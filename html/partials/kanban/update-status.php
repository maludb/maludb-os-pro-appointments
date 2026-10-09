<?php
/**
 * Kanban Status Update Endpoint
 * Handles drag-and-drop status updates for tasks
 */

session_start();
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../models/Task.php';
require_once __DIR__ . '/../../../models/Activity.php';

// Set JSON response header
header('Content-Type: application/json');

// Check authentication
check_auth();
$user = get_user();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get and validate parameters
$taskId = filter_input(INPUT_POST, 'task_id', FILTER_VALIDATE_INT);
$newStatus = isset($_POST['new_status']) ? trim(strip_tags($_POST['new_status'])) : null;
$oldStatus = isset($_POST['old_status']) ? trim(strip_tags($_POST['old_status'])) : null;

if (!$taskId || !$newStatus) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

// Validate status value
$validStatuses = ['pending', 'in_progress', 'review', 'completed', 'cancelled'];
if (!in_array($newStatus, $validStatuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid status value']);
    exit;
}

try {
    $taskModel = new Task();
    $activityModel = new Activity();

    // Get task to verify ownership/assignment
    $task = $taskModel->getTaskById($taskId);

    if (!$task) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Task not found']);
        exit;
    }

    // Check authorization - user must own the task or be assigned to it
    if ($task['user_id'] != $user['id'] && $task['assigned_to'] != $user['id'] && $task['created_by'] != $user['id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    // Update task status
    $updateData = ['status' => $newStatus];

    // If marking as completed, set completed_at
    if ($newStatus === 'completed') {
        $updateData['completed_at'] = date('Y-m-d H:i:s');
    }

    $success = $taskModel->update($taskId, $updateData);

    if ($success) {
        // Log activity
        $statusLabels = [
            'pending' => 'To Do',
            'in_progress' => 'In Progress',
            'review' => 'Review',
            'completed' => 'Done',
            'cancelled' => 'Cancelled'
        ];

        $oldStatusLabel = $statusLabels[$oldStatus] ?? $oldStatus;
        $newStatusLabel = $statusLabels[$newStatus] ?? $newStatus;

        $description = "Changed status from '{$oldStatusLabel}' to '{$newStatusLabel}' for task: " . htmlspecialchars($task['title']);

        $activityModel->logActivity(
            $user['id'],
            'task_status_changed',
            'task',
            $taskId,
            $description
        );

        // Return success
        echo json_encode([
            'success' => true,
            'task_id' => $taskId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'message' => 'Task status updated successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update task status']);
    }

} catch (Exception $e) {
    error_log("Error updating task status: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
