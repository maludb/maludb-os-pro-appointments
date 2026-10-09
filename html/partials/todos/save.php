<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('<div class="alert alert-danger">Invalid CSRF token. Please refresh and try again.</div>');
}

$restaurantId = currentRestaurantId();
$userId = $_SESSION['user_id'] ?? 0;

if (!$restaurantId) {
    echo '<div class="alert alert-danger">No account selected.</div>';
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$dueDate = trim($_POST['due_date'] ?? '') ?: null;
$priority = trim($_POST['priority'] ?? 'medium');

// Validate
if ($title === '') {
    echo '<div class="alert alert-danger">Title is required.</div>';
    exit;
}

if (!in_array($priority, ['low', 'medium', 'high'])) {
    $priority = 'medium';
}

if ($dueDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
    $dueDate = null;
}

$pdo = db();

if ($id > 0) {
    // Update — verify ownership
    $check = $pdo->prepare("SELECT id FROM todos WHERE id = ? AND restaurant_id = ? AND user_id = ?");
    $check->execute([$id, $restaurantId, $userId]);
    if (!$check->fetch()) {
        echo '<div class="alert alert-danger">Todo not found.</div>';
        exit;
    }

    $stmt = $pdo->prepare(
        "UPDATE todos SET title = ?, description = ?, due_date = ?, priority = ?, updated_at = NOW() WHERE id = ?"
    );
    $stmt->execute([$title, $description ?: null, $dueDate, $priority, $id]);
} else {
    // Create
    $stmt = $pdo->prepare(
        "INSERT INTO todos (restaurant_id, user_id, title, description, due_date, priority)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$restaurantId, $userId, $title, $description ?: null, $dueDate, $priority]);
}

// Return the updated list
header('HX-Trigger: todoSaved');
require __DIR__ . '/list.php';
