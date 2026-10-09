<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();
requireManager();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="professional-toggle-availability-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="professional-toggle-availability-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
$availabilityId = (int)($_POST['availability_id'] ?? 0);

if (!$restaurantId || !$availabilityId) {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="professional-toggle-availability-invalid">Invalid request.</div>';
    exit;
}

$pdo = db();
$stmt = $pdo->prepare(
    "SELECT id, weekday, start_time, end_time, is_active
     FROM professional_availability_rules
     WHERE id = ? AND restaurant_id = ?"
);
$stmt->execute([$availabilityId, $restaurantId]);
$rule = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rule) {
    echo '<div class="alert alert-danger" id="professional-toggle-availability-not-found">Availability window not found.</div>';
    exit;
}

$newStatus = ((int)$rule['is_active'] === 1) ? 0 : 1;

if ($newStatus === 1) {
    $overlapStmt = $pdo->prepare(
        "SELECT id
         FROM professional_availability_rules
         WHERE restaurant_id = ?
           AND weekday = ?
           AND is_active = 1
           AND id != ?
           AND NOT (end_time <= ? OR start_time >= ?)"
    );
    $overlapStmt->execute([
        $restaurantId,
        (int)$rule['weekday'],
        $availabilityId,
        $rule['start_time'],
        $rule['end_time'],
    ]);
    if ($overlapStmt->fetch()) {
        echo '<div class="alert alert-danger" id="professional-toggle-availability-overlap-error">This availability window overlaps another active window on the same day and cannot be activated.</div>';
        exit;
    }
}

$updateStmt = $pdo->prepare("UPDATE professional_availability_rules SET is_active = ?, updated_at = NOW() WHERE id = ? AND restaurant_id = ?");
$updateStmt->execute([$newStatus, $availabilityId, $restaurantId]);

header('HX-Trigger: refreshProfessionalAvailabilityList');

echo '<div class="alert alert-success alert-dismissible fade show" id="professional-toggle-availability-success">
        <i class="feather-check-circle me-1"></i> Availability window ' . ($newStatus === 1 ? 'activated' : 'deactivated') . ' successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>';
