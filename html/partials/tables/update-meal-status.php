<?php
/**
 * POST handler: Update meal status or complete & open table
 */
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';
require_once '../../../helpers/meal-status.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Invalid security token.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
$resId = (int)($_POST['reservation_id'] ?? 0);
$tableId = (int)($_POST['table_id'] ?? 0);
$action = trim($_POST['action'] ?? '');
$newMealStatus = trim($_POST['meal_status'] ?? '');

if (!$resId || !$tableId) {
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit;
}

$pdo = db();

// Verify reservation
$stmt = $pdo->prepare("SELECT id, status, meal_status FROM reservations WHERE id = ? AND restaurant_id = ? AND status = 'seated'");
$stmt->execute([$resId, $restaurantId]);
$res = $stmt->fetch();

if (!$res) {
    echo '<div class="alert alert-danger">Reservation not found or not seated.</div>';
    exit;
}

// Complete & Open Table
if ($action === 'complete') {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "UPDATE reservations SET status = 'completed', meal_status = NULL, completed_at = NOW() WHERE id = ?"
        );
        $stmt->execute([$resId]);

        // Log activity
        $stmt = $pdo->prepare(
            "INSERT INTO activity_log (restaurant_id, user_id, action, entity_type, entity_id, description, ip_address)
             VALUES (?, ?, 'update', 'reservation', ?, 'Table completed and opened', ?)"
        );
        $stmt->execute([$restaurantId, currentUserId(), $resId, $_SERVER['REMOTE_ADDR'] ?? null]);

        $pdo->commit();

        // Reload floor plan
        include __DIR__ . '/floor-plan.php';
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        echo '<div class="alert alert-danger">An error occurred.</div>';
        exit;
    }
}

// Update meal status
$validStatuses = array_keys(getMealStatuses());
if (!in_array($newMealStatus, $validStatuses)) {
    echo '<div class="alert alert-danger">Invalid meal status.</div>';
    exit;
}

// Map meal status to its timestamp column
$timestampColumns = [
    'ordering'      => 'ordering_at',
    'eating'        => 'eating_at',
    'check_dropped' => 'check_dropped_at',
    'paid'          => 'paid_at',
];

$sql = "UPDATE reservations SET meal_status = ?";
$params = [$newMealStatus];

if (isset($timestampColumns[$newMealStatus])) {
    $sql .= ", {$timestampColumns[$newMealStatus]} = NOW()";
}

$sql .= " WHERE id = ?";
$params[] = $resId;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

// Re-render the modal with updated status
$_GET['table_id'] = $tableId;
include __DIR__ . '/table-status.php';
