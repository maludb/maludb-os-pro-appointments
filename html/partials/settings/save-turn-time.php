<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();
requireManager();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="save-tt-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="save-tt-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
if (!$restaurantId) {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="save-tt-no-restaurant">No restaurant selected.</div>';
    exit;
}

$action = trim($_POST['action'] ?? '');
$ttId = (int)($_POST['tt_id'] ?? 0);
$pdo = db();

// Handle delete
if ($action === 'delete' && $ttId > 0) {
    $stmt = $pdo->prepare("SELECT id FROM turn_times WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$ttId, $restaurantId]);
    if (!$stmt->fetch()) {
        echo '<div class="alert alert-danger" id="save-tt-delete-notfound">Turn time not found.</div>';
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM turn_times WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$ttId, $restaurantId]);

    header('HX-Trigger: refreshTurnTimes');
    echo '<div class="alert alert-success alert-dismissible fade show" id="save-tt-delete-success">
            <i class="feather-check-circle me-1"></i> Turn time deleted.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    exit;
}

// Handle save
if ($action !== 'save') {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="save-tt-action-error">Invalid action.</div>';
    exit;
}

$isEdit = $ttId > 0;
$minParty = (int)($_POST['min_party_size'] ?? 0);
$maxParty = (int)($_POST['max_party_size'] ?? 0);
$servicePeriod = trim($_POST['service_period'] ?? '');
$duration = (int)($_POST['duration_minutes'] ?? 0);
$buffer = (int)($_POST['buffer_minutes'] ?? 0);

if ($minParty < 1 || $maxParty < 1) {
    echo '<div class="alert alert-danger" id="save-tt-size-error">Party sizes must be at least 1.</div>';
    exit;
}

if ($minParty > $maxParty) {
    echo '<div class="alert alert-danger" id="save-tt-range-error">Min party size cannot exceed max party size.</div>';
    exit;
}

if ($servicePeriod === '') {
    echo '<div class="alert alert-danger" id="save-tt-service-error">Service period is required.</div>';
    exit;
}

if ($duration < 15) {
    echo '<div class="alert alert-danger" id="save-tt-duration-error">Duration must be at least 15 minutes.</div>';
    exit;
}

if ($buffer < 0) {
    echo '<div class="alert alert-danger" id="save-tt-buffer-error">Buffer cannot be negative.</div>';
    exit;
}

// Check uniqueness
$uniqueQuery = "SELECT id FROM turn_times WHERE restaurant_id = ? AND min_party_size = ? AND max_party_size = ? AND service_period = ?";
$uniqueParams = [$restaurantId, $minParty, $maxParty, $servicePeriod];
if ($isEdit) {
    $uniqueQuery .= " AND id != ?";
    $uniqueParams[] = $ttId;
}
$stmt = $pdo->prepare($uniqueQuery);
$stmt->execute($uniqueParams);
if ($stmt->fetch()) {
    echo '<div class="alert alert-danger" id="save-tt-dup-error">A turn time rule with this party size range and service period already exists.</div>';
    exit;
}

try {
    if ($isEdit) {
        $stmt = $pdo->prepare("SELECT id FROM turn_times WHERE id = ? AND restaurant_id = ?");
        $stmt->execute([$ttId, $restaurantId]);
        if (!$stmt->fetch()) {
            echo '<div class="alert alert-danger" id="save-tt-notfound">Turn time not found.</div>';
            exit;
        }

        $stmt = $pdo->prepare(
            "UPDATE turn_times SET min_party_size = ?, max_party_size = ?, service_period = ?, duration_minutes = ?, buffer_minutes = ?
             WHERE id = ? AND restaurant_id = ?"
        );
        $stmt->execute([$minParty, $maxParty, $servicePeriod, $duration, $buffer, $ttId, $restaurantId]);
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO turn_times (restaurant_id, min_party_size, max_party_size, service_period, duration_minutes, buffer_minutes)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$restaurantId, $minParty, $maxParty, $servicePeriod, $duration, $buffer]);
    }

    header('HX-Trigger: refreshTurnTimes');

    echo '<div class="alert alert-success alert-dismissible fade show" id="save-tt-success">
            <i class="feather-check-circle me-1"></i> Turn time ' . ($isEdit ? 'updated' : 'added') . ' successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    echo '<script>document.getElementById("turn-times-modal-container").innerHTML="";</script>';

} catch (Exception $e) {
    echo '<div class="alert alert-danger" id="save-tt-error">An error occurred while saving. Please try again.</div>';
}
