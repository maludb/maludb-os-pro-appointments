<?php
require_once '../../helpers/professional-booking.php';
require_once '../../helpers/professional-availability.php';

$slug = strtolower(trim($_GET['professional'] ?? ''));
$code = strtoupper(trim($_GET['code'] ?? ''));
$lastName = trim($_GET['last_name'] ?? '');
$date = trim($_GET['date'] ?? '');

if ($slug === '' || $code === '' || $lastName === '') {
    echo '<div class="alert alert-danger" id="professional-modify-slots-missing-lookup">The appointment lookup details are missing.</div>';
    exit;
}

$appointment = professionalGetAppointmentContextByConfirmation($slug, $code, $lastName);
if (!$appointment) {
    echo '<div class="alert alert-danger" id="professional-modify-slots-not-found">The appointment could not be found.</div>';
    exit;
}

$restriction = professionalGetSelfServiceRestriction($appointment);
if ($restriction !== null) {
    echo '<div class="alert alert-warning" id="professional-modify-slots-restricted">' . htmlspecialchars($restriction['message']) . '</div>';
    exit;
}

if ($date === '') {
    echo '<div class="alert alert-warning" id="professional-modify-slots-missing-date">Choose a new date first.</div>';
    exit;
}

$slots = getProfessionalAvailableSlots((int)$appointment['restaurant_id'], (int)$appointment['service_id'], $date, [
    'allow_inactive' => true,
    'exclude_appointment_id' => (int)$appointment['id'],
]);

if (empty($slots)) {
    echo '<div class="alert alert-info" id="professional-modify-slots-empty">No available times were found for that date.</div>';
    exit;
}
?>
<div id="professional-modify-slots-results">
    <p class="text-muted small mb-2" id="professional-modify-slots-summary">
        <?php echo htmlspecialchars(date('l, F j, Y', strtotime($date))); ?> for <?php echo htmlspecialchars($appointment['service_name']); ?>
    </p>
    <div class="d-flex flex-wrap gap-2" id="professional-modify-slots-grid">
        <?php foreach ($slots as $index => $slot): ?>
        <button type="button"
                class="btn btn-outline-dark professional-modify-slot-btn"
                id="professional-modify-slot-<?php echo $index; ?>"
                data-start-at="<?php echo htmlspecialchars($slot['start_at']); ?>"
                data-time-display="<?php echo htmlspecialchars($slot['time_display']); ?>"
                onclick="selectProfessionalModifySlot(this)">
            <?php echo htmlspecialchars($slot['time_display']); ?>
        </button>
        <?php endforeach; ?>
    </div>
</div>
