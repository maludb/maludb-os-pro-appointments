<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/professional-availability.php';

requireAuth();
requireManager();

$restaurantId = currentRestaurantId();
$serviceId = (int)($_GET['service_id'] ?? 0);
$date = trim($_GET['appointment_date'] ?? '');
$appointmentId = (int)($_GET['appointment_id'] ?? 0);
$selectedStartAt = trim($_GET['start_at'] ?? '');

if (!$restaurantId) {
    echo '<div class="alert alert-danger" id="professional-check-slots-no-restaurant">No professional account is currently selected.</div>';
    exit;
}

if ($serviceId <= 0 || $date === '') {
    echo '<div class="alert alert-info" id="professional-check-slots-missing-fields">Choose a service and date before loading available times.</div>';
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo '<div class="alert alert-danger" id="professional-check-slots-invalid-date">Please choose a valid appointment date.</div>';
    exit;
}

$service = getProfessionalService($restaurantId, $serviceId, ['allow_inactive' => $appointmentId > 0]);
if (!$service) {
    echo '<div class="alert alert-danger" id="professional-check-slots-service-not-found">The selected service is not available.</div>';
    exit;
}

$slots = getProfessionalAvailableSlots($restaurantId, $serviceId, $date, [
    'ignore_notice' => true,
    'ignore_horizon' => true,
    'exclude_appointment_id' => $appointmentId,
]);
?>
<div id="professional-check-slots-main">
    <div class="d-flex justify-content-between align-items-center mb-2" id="professional-check-slots-head">
        <div id="professional-check-slots-copy">
            <div class="fw-semibold" id="professional-check-slots-title">Available Times</div>
            <div class="small text-muted" id="professional-check-slots-subtitle">
                <?php echo htmlspecialchars($service['name']); ?> on <?php echo htmlspecialchars(date('l, M j, Y', strtotime($date))); ?>
            </div>
        </div>
        <span class="badge bg-secondary" id="professional-check-slots-count"><?php echo count($slots); ?> slots</span>
    </div>

    <?php if (empty($slots)): ?>
    <div class="alert alert-warning mb-0" id="professional-check-slots-empty">
        No bookable times were found for this service on the selected date.
    </div>
    <?php else: ?>
    <div class="d-flex flex-wrap gap-2" id="professional-check-slots-list">
        <?php foreach ($slots as $slot): ?>
            <?php
            $isSelected = ($selectedStartAt !== '' && $selectedStartAt === $slot['start_at']);
            $buttonClass = $isSelected ? 'btn-primary' : 'btn-outline-primary';
            $locationTitle = trim(($slot['location_type'] ?: '') . ' ' . ($slot['location_label'] ?: ''));
            ?>
        <button type="button"
                class="btn btn-sm professional-slot-btn <?php echo htmlspecialchars($buttonClass); ?>"
                id="professional-check-slots-btn-<?php echo htmlspecialchars(date('His', strtotime($slot['start_at']))); ?>"
                data-start-at="<?php echo htmlspecialchars($slot['start_at']); ?>"
                data-display="<?php echo htmlspecialchars(date('M j, Y g:ia', strtotime($slot['start_at']))); ?>"
                <?php echo $locationTitle !== '' ? 'title="' . htmlspecialchars(trim(ucwords(str_replace('_', ' ', $locationTitle)))) . '"' : ''; ?>
                onclick="window.selectProfessionalAppointmentSlot && window.selectProfessionalAppointmentSlot(this);">
            <?php echo htmlspecialchars($slot['time_display']); ?>
        </button>
        <?php endforeach; ?>
    </div>

    <div class="small text-muted mt-2" id="professional-check-slots-help">
        Only conflict-free times inside the provider availability windows are shown here.
    </div>
    <?php endif; ?>
</div>
