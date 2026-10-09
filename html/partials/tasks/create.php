<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../helpers/validation.php';
require_once __DIR__ . '/../../../models/Task.php';
require_once __DIR__ . '/../../../models/Activity.php';

check_auth();
$user = get_user();

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-danger" id="create-task-error">Method not allowed</div>';
    exit;
}

// Verify CSRF token
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="create-task-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

// Validate required fields
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
    echo '<div class="alert alert-danger" id="create-task-validation-error">';
    echo '<strong>Validation Error:</strong><ul class="mb-0">';
    foreach ($errors as $error) {
        echo '<li>' . htmlspecialchars($error) . '</li>';
    }
    echo '</ul></div>';
    exit;
}

// Prepare task data
$taskData = [
    'user_id' => $user['id'],
    'created_by' => $user['id'],
    'title' => trim($_POST['title']),
    'description' => !empty($_POST['description']) ? trim($_POST['description']) : null,
    'status' => $_POST['status'] ?? 'pending',
    'priority' => $_POST['priority'] ?? 'medium',
    'due_date' => !empty($_POST['due_date']) ? $_POST['due_date'] : null,
    'category' => !empty($_POST['category']) ? trim($_POST['category']) : null,
    'tags' => !empty($_POST['tags']) ? trim($_POST['tags']) : null,
    'assigned_to' => !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : $user['id'],
    'team_id' => !empty($_POST['team_id']) ? intval($_POST['team_id']) : null,
];

try {
    // Create task
    $taskModel = new Task();
    $taskId = $taskModel->create($taskData);

    if (!$taskId) {
        throw new Exception('Failed to create task');
    }

    // Log activity
    $activityModel = new Activity();
    $activityModel->logActivity(
        $user['id'],
        'task_created',
        'task',
        $taskId,
        'Created task: ' . $taskData['title']
    );

    // Return success message
    echo '<div class="alert alert-success" id="create-task-success">';
    echo '<i class="feather-check-circle me-2"></i>';
    echo '<strong>Success!</strong> Task created successfully.';
    echo '</div>';

    // Trigger events to refresh task list and close modal
    header('HX-Trigger: {"taskCreated": {"id": ' . $taskId . '}, "closeModal": true, "refreshTaskList": true}');

} catch (Exception $e) {
    error_log("Error creating task: " . $e->getMessage());
    http_response_code(500);
    echo '<div class="alert alert-danger" id="create-task-error">';
    echo '<i class="feather-alert-triangle me-2"></i>';
    echo '<strong>Error:</strong> Failed to create task. Please try again.';
    echo '</div>';
}
?>
