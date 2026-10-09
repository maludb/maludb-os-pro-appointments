<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();
requireManager();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="save-sd-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="save-sd-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
if (!$restaurantId) {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="save-sd-no-restaurant">No restaurant selected.</div>';
    exit;
}

$action = trim($_POST['action'] ?? '');
$sdId = (int)($_POST['sd_id'] ?? 0);
$pdo = db();

// Handle delete
if ($action === 'delete' && $sdId > 0) {
    $stmt = $pdo->prepare("SELECT id FROM special_dates WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$sdId, $restaurantId]);
    if (!$stmt->fetch()) {
        echo '<div class="alert alert-danger" id="save-sd-delete-notfound">Special date not found.</div>';
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM special_dates WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$sdId, $restaurantId]);

    header('HX-Trigger: refreshSpecialDates');
    echo '<div class="alert alert-success alert-dismissible fade show" id="save-sd-delete-success">
            <i class="feather-check-circle me-1"></i> Special date deleted.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    exit;
}

// Handle save
if ($action !== 'save') {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="save-sd-action-error">Invalid action.</div>';
    exit;
}

$isEdit = $sdId > 0;
$specialDate = trim($_POST['special_date'] ?? '');
$dateType = trim($_POST['date_type'] ?? '');
$label = trim($_POST['label'] ?? '');
$isClosed = isset($_POST['is_closed']) ? 1 : 0;
$customOpen = trim($_POST['custom_open_time'] ?? '') ?: null;
$customClose = trim($_POST['custom_close_time'] ?? '') ?: null;
$customFirst = trim($_POST['custom_first_seating'] ?? '') ?: null;
$customLast = trim($_POST['custom_last_seating'] ?? '') ?: null;
$maxCovers = trim($_POST['max_covers'] ?? '') !== '' ? (int)$_POST['max_covers'] : null;
$outboundVoiceAgent = trim($_POST['outbound_voice_agent'] ?? '') ?: null;
$notes = trim($_POST['notes'] ?? '') ?: null;

// Validate required fields
if ($specialDate === '') {
    echo '<div class="alert alert-danger" id="save-sd-date-error">Date is required.</div>';
    exit;
}

if (!in_array($dateType, ['holiday', 'blackout', 'event', 'modified_hours'])) {
    echo '<div class="alert alert-danger" id="save-sd-type-error">Please select a valid type.</div>';
    exit;
}

if ($label === '') {
    echo '<div class="alert alert-danger" id="save-sd-label-error">Label is required.</div>';
    exit;
}

// Validate custom hours if partially provided
$customHours = [$customOpen, $customClose, $customFirst, $customLast];
$hasAny = count(array_filter($customHours)) > 0;
$hasAll = count(array_filter($customHours)) === 4;

if ($hasAny && !$hasAll) {
    echo '<div class="alert alert-danger" id="save-sd-hours-error">If setting custom hours, all four time fields are required.</div>';
    exit;
}

if ($hasAll) {
    if ($customOpen >= $customClose) {
        echo '<div class="alert alert-danger" id="save-sd-oc-error">Open time must be before close time.</div>';
        exit;
    }
    if ($customFirst < $customOpen || $customLast > $customClose) {
        echo '<div class="alert alert-danger" id="save-sd-seating-error">Seating times must be within open/close times.</div>';
        exit;
    }
}

// Check uniqueness (date + type per restaurant)
$uniqueQuery = "SELECT id FROM special_dates WHERE restaurant_id = ? AND special_date = ? AND date_type = ?";
$uniqueParams = [$restaurantId, $specialDate, $dateType];
if ($isEdit) {
    $uniqueQuery .= " AND id != ?";
    $uniqueParams[] = $sdId;
}
$stmt = $pdo->prepare($uniqueQuery);
$stmt->execute($uniqueParams);
if ($stmt->fetch()) {
    echo '<div class="alert alert-danger" id="save-sd-dup-error">A special date with this date and type already exists.</div>';
    exit;
}

try {
    if ($isEdit) {
        $stmt = $pdo->prepare("SELECT id FROM special_dates WHERE id = ? AND restaurant_id = ?");
        $stmt->execute([$sdId, $restaurantId]);
        if (!$stmt->fetch()) {
            echo '<div class="alert alert-danger" id="save-sd-notfound">Special date not found.</div>';
            exit;
        }

        $stmt = $pdo->prepare(
            "UPDATE special_dates SET special_date = ?, date_type = ?, label = ?, is_closed = ?,
                    custom_open_time = ?, custom_close_time = ?, custom_first_seating = ?, custom_last_seating = ?,
                    max_covers = ?, outbound_voice_agent = ?, notes = ?
             WHERE id = ? AND restaurant_id = ?"
        );
        $stmt->execute([$specialDate, $dateType, $label, $isClosed, $customOpen, $customClose, $customFirst, $customLast, $maxCovers, $outboundVoiceAgent, $notes, $sdId, $restaurantId]);
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO special_dates (restaurant_id, special_date, date_type, label, is_closed,
                    custom_open_time, custom_close_time, custom_first_seating, custom_last_seating, max_covers, outbound_voice_agent, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$restaurantId, $specialDate, $dateType, $label, $isClosed, $customOpen, $customClose, $customFirst, $customLast, $maxCovers, $outboundVoiceAgent, $notes]);
    }

    header('HX-Trigger: refreshSpecialDates');

    echo '<div class="alert alert-success alert-dismissible fade show" id="save-sd-success">
            <i class="feather-check-circle me-1"></i> Special date ' . ($isEdit ? 'updated' : 'added') . ' successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    echo '<script>document.getElementById("special-dates-modal-container").innerHTML="";</script>';

} catch (Exception $e) {
    echo '<div class="alert alert-danger" id="save-sd-error">An error occurred while saving. Please try again.</div>';
}
