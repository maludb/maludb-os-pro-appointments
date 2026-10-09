<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();
requireManager();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="save-table-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="save-table-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
if (!$restaurantId) {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="save-table-no-restaurant">No restaurant selected.</div>';
    exit;
}

$tableId = (int)($_POST['table_id'] ?? 0);
$isEdit = $tableId > 0;

$tableName = trim($_POST['table_name'] ?? '');
$sectionId = (int)($_POST['section_id'] ?? 0);
$minSeats = (int)($_POST['min_seats'] ?? 1);
$maxSeats = (int)($_POST['max_seats'] ?? 2);
$status = trim($_POST['status'] ?? 'available');
$sortOrder = (int)($_POST['sort_order'] ?? 0);
$notes = trim($_POST['notes'] ?? '');
$isActive = isset($_POST['is_active']) ? 1 : 0;

// Validate required fields
if ($tableName === '') {
    echo '<div class="alert alert-danger" id="save-table-name-error">Table name is required.</div>';
    exit;
}

if ($sectionId <= 0) {
    echo '<div class="alert alert-danger" id="save-table-section-error">Please select a section.</div>';
    exit;
}

if ($minSeats < 1 || $maxSeats < 1) {
    echo '<div class="alert alert-danger" id="save-table-seats-min-error">Seats must be at least 1.</div>';
    exit;
}

if ($minSeats > $maxSeats) {
    echo '<div class="alert alert-danger" id="save-table-seats-range-error">Min seats cannot exceed max seats.</div>';
    exit;
}

if (!in_array($status, ['available', 'occupied', 'reserved', 'blocked'])) {
    echo '<div class="alert alert-danger" id="save-table-status-error">Invalid status.</div>';
    exit;
}

$pdo = db();

// Validate section belongs to this restaurant
$stmt = $pdo->prepare("SELECT id FROM sections WHERE id = ? AND restaurant_id = ?");
$stmt->execute([$sectionId, $restaurantId]);
if (!$stmt->fetch()) {
    echo '<div class="alert alert-danger" id="save-table-section-invalid">Invalid section.</div>';
    exit;
}

// Check unique table name within restaurant
$uniqueQuery = "SELECT id FROM tables WHERE restaurant_id = ? AND table_name = ?";
$uniqueParams = [$restaurantId, $tableName];
if ($isEdit) {
    $uniqueQuery .= " AND id != ?";
    $uniqueParams[] = $tableId;
}
$stmt = $pdo->prepare($uniqueQuery);
$stmt->execute($uniqueParams);
if ($stmt->fetch()) {
    echo '<div class="alert alert-danger" id="save-table-dup-error">A table with this name already exists.</div>';
    exit;
}

try {
    if ($isEdit) {
        // Verify table belongs to this restaurant
        $stmt = $pdo->prepare("SELECT id FROM tables WHERE id = ? AND restaurant_id = ?");
        $stmt->execute([$tableId, $restaurantId]);
        if (!$stmt->fetch()) {
            echo '<div class="alert alert-danger" id="save-table-notfound">Table not found.</div>';
            exit;
        }

        $stmt = $pdo->prepare(
            "UPDATE tables SET table_name = ?, section_id = ?, min_seats = ?, max_seats = ?,
                    status = ?, sort_order = ?, notes = ?, is_active = ?
             WHERE id = ? AND restaurant_id = ?"
        );
        $stmt->execute([$tableName, $sectionId, $minSeats, $maxSeats, $status, $sortOrder, $notes ?: null, $isActive, $tableId, $restaurantId]);
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO tables (restaurant_id, table_name, section_id, min_seats, max_seats, status, sort_order, notes, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$restaurantId, $tableName, $sectionId, $minSeats, $maxSeats, $status, $sortOrder, $notes ?: null, $isActive]);
    }

    header('HX-Trigger: refreshTableList');

    echo '<div class="alert alert-success alert-dismissible fade show" id="save-table-success">
            <i class="feather-check-circle me-1"></i> Table ' . ($isEdit ? 'updated' : 'added') . ' successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    echo '<script>document.getElementById("tables-modal-container").innerHTML="";</script>';

} catch (Exception $e) {
    echo '<div class="alert alert-danger" id="save-table-error">An error occurred while saving. Please try again.</div>';
}
