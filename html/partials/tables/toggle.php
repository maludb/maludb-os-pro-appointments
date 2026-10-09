<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();
requireManager();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="toggle-table-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="toggle-table-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
$tableId = (int)($_POST['table_id'] ?? 0);

if (!$restaurantId || !$tableId) {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="toggle-table-invalid">Invalid request.</div>';
    exit;
}

$pdo = db();

$stmt = $pdo->prepare("SELECT is_active FROM tables WHERE id = ? AND restaurant_id = ?");
$stmt->execute([$tableId, $restaurantId]);
$table = $stmt->fetch();

if (!$table) {
    echo '<div class="alert alert-danger" id="toggle-table-notfound">Table not found.</div>';
    exit;
}

$newStatus = $table['is_active'] ? 0 : 1;
$stmt = $pdo->prepare("UPDATE tables SET is_active = ? WHERE id = ? AND restaurant_id = ?");
$stmt->execute([$newStatus, $tableId, $restaurantId]);

$statusLabel = $newStatus ? 'activated' : 'deactivated';

header('HX-Trigger: refreshTableList');

echo '<div class="alert alert-success alert-dismissible fade show" id="toggle-table-success">
        <i class="feather-check-circle me-1"></i> Table ' . $statusLabel . ' successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>';
