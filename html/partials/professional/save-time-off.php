<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();
requireManager();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="professional-save-time-off-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="professional-save-time-off-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();

if (!$restaurantId) {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="professional-save-time-off-no-restaurant">No professional account is currently selected.</div>';
    exit;
}

$action = trim($_POST['action'] ?? '');
$timeOffId = (int)($_POST['time_off_id'] ?? 0);
$pdo = db();

if ($action === 'delete' && $timeOffId > 0) {
    $stmt = $pdo->prepare("SELECT id FROM professional_time_off WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$timeOffId, $restaurantId]);
    if (!$stmt->fetch()) {
        echo '<div class="alert alert-danger" id="professional-save-time-off-delete-not-found">Time-off entry not found.</div>';
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM professional_time_off WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$timeOffId, $restaurantId]);

    header('HX-Trigger: refreshProfessionalTimeOffList');
    echo '<div class="alert alert-success alert-dismissible fade show" id="professional-save-time-off-delete-success">
            <i class="feather-check-circle me-1"></i> Time-off entry deleted.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    exit;
}

if ($action !== 'save') {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="professional-save-time-off-action-error">Invalid action.</div>';
    exit;
}

$isEdit = $timeOffId > 0;
$startDate = trim($_POST['start_date'] ?? '');
$endDate = trim($_POST['end_date'] ?? '');
$startTime = trim($_POST['start_time'] ?? '');
$endTime = trim($_POST['end_time'] ?? '');
$reason = trim($_POST['reason'] ?? '');
$notes = trim($_POST['notes'] ?? '');
$isAllDay = isset($_POST['is_all_day']) ? 1 : 0;

if ($startDate === '' || $endDate === '') {
    echo '<div class="alert alert-danger" id="professional-save-time-off-date-required">Start and end dates are required.</div>';
    exit;
}

if ($isAllDay === 1) {
    $startsAt = $startDate . ' 00:00:00';
    $endsAt = $endDate . ' 23:59:59';
} else {
    if ($startTime === '' || $endTime === '') {
        echo '<div class="alert alert-danger" id="professional-save-time-off-time-required">Start and end times are required unless the block is marked all day.</div>';
        exit;
    }
    $startsAt = $startDate . ' ' . $startTime . ':00';
    $endsAt = $endDate . ' ' . $endTime . ':00';
}

if (strtotime($startsAt) === false || strtotime($endsAt) === false) {
    echo '<div class="alert alert-danger" id="professional-save-time-off-datetime-invalid">Please provide valid dates and times.</div>';
    exit;
}

if (strtotime($startsAt) >= strtotime($endsAt)) {
    echo '<div class="alert alert-danger" id="professional-save-time-off-order-error">Start date/time must be before end date/time.</div>';
    exit;
}

if ($isEdit) {
    $checkStmt = $pdo->prepare("SELECT id FROM professional_time_off WHERE id = ? AND restaurant_id = ?");
    $checkStmt->execute([$timeOffId, $restaurantId]);
    if (!$checkStmt->fetch()) {
        echo '<div class="alert alert-danger" id="professional-save-time-off-not-found">Time-off entry not found.</div>';
        exit;
    }

    $stmt = $pdo->prepare(
        "UPDATE professional_time_off SET
            starts_at = ?,
            ends_at = ?,
            reason = ?,
            notes = ?,
            is_all_day = ?,
            updated_at = NOW()
         WHERE id = ? AND restaurant_id = ?"
    );
    $stmt->execute([
        $startsAt,
        $endsAt,
        $reason ?: null,
        $notes ?: null,
        $isAllDay,
        $timeOffId,
        $restaurantId,
    ]);
} else {
    $stmt = $pdo->prepare(
        "INSERT INTO professional_time_off (
            restaurant_id,
            starts_at,
            ends_at,
            reason,
            notes,
            is_all_day,
            created_at,
            updated_at
         ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())"
    );
    $stmt->execute([
        $restaurantId,
        $startsAt,
        $endsAt,
        $reason ?: null,
        $notes ?: null,
        $isAllDay,
    ]);
}

header('HX-Trigger-After-Swap: {"refreshProfessionalTimeOffList":true,"closeModal":true}');

echo '<div class="alert alert-success alert-dismissible fade show" id="professional-save-time-off-success">
        <i class="feather-check-circle me-1"></i> Time-off entry ' . ($isEdit ? 'updated' : 'created') . ' successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>';
