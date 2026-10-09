<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../helpers/validation.php';
require_once __DIR__ . '/../../../models/Task.php';
require_once __DIR__ . '/../../../models/Activity.php';

check_auth();
$user = get_user();

// Accept PUT or POST
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'PUT' && $method !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-danger" id="update-task-error">Method not allowed</div>';
    exit;
}

// Get task ID
$taskId = $_GET['id'] ?? $_POST['id'] ?? null;

if (!$taskId) {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="update-task-error">Task ID is required</div>';
    exit;
}

// Handle PUT method - parse php://input
if ($method === 'PUT') {
    parse_str(file_get_contents('php://input'), $_POST);
}

// Verify CSRF token
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="update-task-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

// Get existing task
$taskModel = new Task();
$task = $taskModel->getTaskById($taskId);

if (!$task) {
    http_response_code(404);
    echo '<div class="alert alert-danger" id="update-task-error">Task not found</div>';
    exit;
}

// Authorization check
if ($task['user_id'] != $user['id'] && $task['created_by'] != $user['id'] && !is_admin()) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="update-task-error">You do not have permission to edit this task</div>';
    exit;
}

// Validate inputs
$errors = [];

if (empty($_POST['title'])) {
    $errors[] = 'Title is required';
} elseif (strlen($_POST['title']) > 255) {
    $errors[] = 'Title must not exceed 255 characters';
}

if (!empty($_POST['description']) && strlen($_POST['description']) > 1000) {
    $errors[] = 'Description must not exceed 1000 characters';
}

if (!empty($_POST['category']) && strlen($_POST['category']) > 100) {
    $errors[] = 'Category must not exceed 100 characters';
}

// Validate status enum
$validStatuses = ['pending', 'in_progress', 'review', 'completed', 'cancelled'];
if (!empty($_POST['status']) && !in_array($_POST['status'], $validStatuses)) {
    $errors[] = 'Invalid status value';
}

// Validate priority enum
$validPriorities = ['low', 'medium', 'high', 'critical'];
if (!empty($_POST['priority']) && !in_array($_POST['priority'], $validPriorities)) {
    $errors[] = 'Invalid priority value';
}

// Validate due date format
if (!empty($_POST['due_date'])) {
    $date = DateTime::createFromFormat('Y-m-d', $_POST['due_date']);
    if (!$date || $date->format('Y-m-d') !== $_POST['due_date']) {
        $errors[] = 'Invalid due date format';
    }
}

// If there are validation errors, return them
if (!empty($errors)) {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="update-task-validation-error">';
    echo '<strong>Validation Error:</strong><ul class="mb-0">';
    foreach ($errors as $error) {
        echo '<li>' . htmlspecialchars($error) . '</li>';
    }
    echo '</ul></div>';
    exit;
}

// Build update data
$updateData = [
    'title' => trim($_POST['title']),
    'description' => !empty($_POST['description']) ? trim($_POST['description']) : null,
    'status' => $_POST['status'] ?? $task['status'],
    'priority' => $_POST['priority'] ?? $task['priority'],
    'due_date' => !empty($_POST['due_date']) ? $_POST['due_date'] : null,
    'category' => !empty($_POST['category']) ? trim($_POST['category']) : null,
    'tags' => !empty($_POST['tags']) ? trim($_POST['tags']) : null,
    'assigned_to' => !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null,
];

// Track changes for activity log
$changes = [];
if ($task['title'] !== $updateData['title']) $changes[] = 'title';
if ($task['description'] !== $updateData['description']) $changes[] = 'description';
if ($task['status'] !== $updateData['status']) $changes[] = 'status';
if ($task['priority'] !== $updateData['priority']) $changes[] = 'priority';
if ($task['due_date'] !== $updateData['due_date']) $changes[] = 'due date';
if ($task['category'] !== $updateData['category']) $changes[] = 'category';
if ($task['tags'] !== $updateData['tags']) $changes[] = 'tags';
if ($task['assigned_to'] != $updateData['assigned_to']) $changes[] = 'assignee';

try {
    // Update task
    $success = $taskModel->update($taskId, $updateData);

    if (!$success) {
        throw new Exception('Failed to update task');
    }

    // Log activity if there were changes
    if (!empty($changes)) {
        $activityModel = new Activity();
        $changeList = implode(', ', $changes);
        $activityModel->logActivity(
            $user['id'],
            'task_updated',
            'task',
            $taskId,
            'Updated task: ' . $updateData['title'] . ' (changed: ' . $changeList . ')'
        );
    }

    // Return success message and trigger events
    echo '<div class="alert alert-success" id="update-task-success">';
    echo '<i class="feather-check-circle me-2"></i>';
    echo '<strong>Success!</strong> Task updated successfully.';
    echo '</div>';

    // Trigger events to refresh task list
    header('HX-Trigger: {"taskUpdated": {"id": ' . $taskId . '}, "refreshTaskList": true}');

} catch (Exception $e) {
    error_log("Error updating task: " . $e->getMessage());
    http_response_code(500);
    echo '<div class="alert alert-danger" id="update-task-error">';
    echo '<i class="feather-alert-triangle me-2"></i>';
    echo '<strong>Error:</strong> Failed to update task. Please try again.';
    echo '</div>';
}
?>
