<?php
require_once '../../helpers/professional-availability.php';

$slug = strtolower(trim($_GET['professional'] ?? ''));
$serviceId = (int)($_GET['service_id'] ?? 0);
$date = trim($_GET['date'] ?? '');

if ($slug === '') {
    echo '<div class="alert alert-danger" id="professional-public-slots-missing-slug">Booking page not specified.</div>';
    exit;
}

$profile = getProfessionalProfileByBookingSlug($slug);
if (!$profile) {
    echo '<div class="alert alert-danger" id="professional-public-slots-not-found">The requested booking page could not be found.</div>';
    exit;
}

if ((int)$profile['is_public_booking_enabled'] !== 1) {
    echo '<div class="alert alert-warning" id="professional-public-slots-disabled">Online booking is not currently enabled for this business.</div>';
    exit;
}

if ($serviceId <= 0 || $date === '') {
    echo '<div class="alert alert-warning" id="professional-public-slots-missing-fields">Choose a service and date first.</div>';
    exit;
}

$restaurantId = (int)$profile['restaurant_id'];
$service = getProfessionalService($restaurantId, $serviceId, ['public_booking' => true]);

if (!$service) {
    echo '<div class="alert alert-warning" id="professional-public-slots-service-invalid">The selected service is not available for online booking.</div>';
    exit;
}

$timezone = new DateTimeZone($profile['timezone']);
$dateObject = professionalNormalizeDate($date, $timezone);

if ($dateObject === null) {
    echo '<div class="alert alert-warning" id="professional-public-slots-date-invalid">Choose a valid booking date.</div>';
    exit;
}

$slots = getProfessionalAvailableSlots($restaurantId, $serviceId, $dateObject->format('Y-m-d'), [
    'public_booking' => true,
]);

if (empty($slots)) {
    echo '<div class="alert alert-info" id="professional-public-slots-empty">No online appointments are available for this service on ' . htmlspecialchars($dateObject->format('F j, Y')) . '.</div>';
    exit;
}
?>
<div id="professional-public-slots-results">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3" id="professional-public-slots-header">
        <div id="professional-public-slots-header-copy">
            <p class="text-muted mb-1" id="professional-public-slots-date-copy"><?php echo htmlspecialchars($dateObject->format('l, F j, Y')); ?></p>
            <h6 class="mb-0" id="professional-public-slots-service-title"><?php echo htmlspecialchars($service['name']); ?></h6>
        </div>
        <div class="text-muted small" id="professional-public-slots-meta">
            <?php echo (int)$service['duration_minutes']; ?> min
            <?php if (!empty($service['location_label']) || !empty($service['location_type'])): ?>
            | <?php echo htmlspecialchars($service['location_label'] ?: ucwords(str_replace('_', ' ', $service['location_type']))); ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2" id="professional-public-slots-grid">
        <?php foreach ($slots as $index => $slot): ?>
        <button type="button"
                class="btn btn-outline-dark professional-public-slot-btn"
                id="professional-public-slot-<?php echo (int)$service['id']; ?>-<?php echo $index; ?>"
                data-service-id="<?php echo (int)$service['id']; ?>"
                data-service-name="<?php echo htmlspecialchars($service['name']); ?>"
                data-date="<?php echo htmlspecialchars($dateObject->format('l, F j, Y')); ?>"
                data-time-display="<?php echo htmlspecialchars($slot['time_display']); ?>"
                data-start-at="<?php echo htmlspecialchars($slot['start_at']); ?>"
                onclick="selectProfessionalPublicSlot(this)">
            <?php echo htmlspecialchars($slot['time_display']); ?>
        </button>
        <?php endforeach; ?>
    </div>
</div>
