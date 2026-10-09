<?php
require_once '../../helpers/db.php';
require_once '../../helpers/csrf.php';
require_once '../../helpers/professional-availability.php';

session_start();

function professionalPublicFormatPrice($price, $currencyCode) {
    if ($price === null || $price === '') {
        return '';
    }

    return ($currencyCode ?: 'USD') . ' ' . number_format((float)$price, 2);
}

$slug = strtolower(trim($_GET['professional'] ?? ''));
$profile = getProfessionalProfileByBookingSlug($slug);

if (!$profile) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Booking Not Found</title><link rel="stylesheet" href="/assets/css/bootstrap.min.css"></head><body><div class="container py-5" id="professional-public-not-found-container"><div class="alert alert-danger" id="professional-public-not-found-alert">The requested booking page could not be found.</div></div></body></html>';
    exit;
}

$restaurantId = (int)$profile['restaurant_id'];
$timezone = new DateTimeZone($profile['timezone']);
$now = new DateTimeImmutable('now', $timezone);
$minDate = $now->modify('+' . (int)$profile['minimum_booking_notice_hours'] . ' hours')->format('Y-m-d');
$maxDate = $now
    ->setTime(23, 59, 59)
    ->modify('+' . (int)$profile['maximum_booking_horizon_days'] . ' days')
    ->format('Y-m-d');

if ($maxDate < $minDate) {
    $maxDate = $minDate;
}

$servicesStmt = db()->prepare(
    "SELECT id, name, description, duration_minutes, price, currency_code, location_type, location_label
     FROM professional_services
     WHERE restaurant_id = ?
       AND is_active = 1
       AND is_public_bookable = 1
     ORDER BY sort_order ASC, name ASC"
);
$servicesStmt->execute([$restaurantId]);
$services = $servicesStmt->fetchAll(PDO::FETCH_ASSOC);

$displayName = $profile['display_name'] ?: $profile['business_name'];
$publicBookingEnabled = ((int)$profile['is_public_booking_enabled'] === 1);
$hasBookableServices = !empty($services);
$canAcceptPublicBookings = $publicBookingEnabled && $hasBookableServices;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($displayName); ?> Booking</title>
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
    <script src="https://unpkg.com/htmx.org@2.0.8"></script>
    <style>
        body { background: linear-gradient(180deg, #f7f4ee 0%, #ffffff 100%); }
        .professional-public-booking-hero { background: linear-gradient(135deg, #173f35, #2b6e5d); color: #fff; }
        .professional-public-slot-btn.selected { background-color: #173f35 !important; border-color: #173f35 !important; color: #fff !important; }
        .htmx-indicator { display: none; }
        .htmx-request .htmx-indicator { display: inline-block; }
    </style>
</head>
<body>
    <div class="professional-public-booking-hero py-5 mb-4" id="professional-public-booking-hero">
        <div class="container" id="professional-public-booking-hero-container">
            <div class="row justify-content-center" id="professional-public-booking-hero-row">
                <div class="col-lg-8 text-center" id="professional-public-booking-hero-col">
                    <h1 class="mb-2" id="professional-public-booking-title"><?php echo htmlspecialchars($displayName); ?></h1>
                    <p class="mb-2 opacity-75" id="professional-public-booking-subtitle">
                        Book a professional service online.
                    </p>
                    <?php if (!empty($profile['business_phone']) || !empty($profile['business_email'])): ?>
                    <p class="mb-0 small opacity-75" id="professional-public-booking-contact">
                        <?php
                        $contactParts = [];
                        if (!empty($profile['business_phone'])) {
                            $contactParts[] = htmlspecialchars($profile['business_phone']);
                        }
                        if (!empty($profile['business_email'])) {
                            $contactParts[] = htmlspecialchars($profile['business_email']);
                        }
                        echo implode(' | ', $contactParts);
                        ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="container pb-5" id="professional-public-booking-container">
        <div class="row justify-content-center g-4" id="professional-public-booking-main-row">
            <div class="col-xl-8 col-lg-9" id="professional-public-booking-main-col">
                <?php if (!$publicBookingEnabled): ?>
                <div class="alert alert-warning" id="professional-public-booking-disabled-alert">
                    Online booking is not currently enabled for this business. Please contact the office directly to schedule.
                </div>
                <?php elseif (!$hasBookableServices): ?>
                <div class="alert alert-warning" id="professional-public-booking-no-services-alert">
                    No services are currently available for online booking. Please contact the office directly to schedule.
                </div>
                <?php endif; ?>

                <div class="card shadow-sm border-0 mb-3" id="professional-public-booking-step1-card">
                    <div class="card-body" id="professional-public-booking-step1-body">
                        <h5 class="card-title mb-3" id="professional-public-booking-step1-title">
                            <span class="badge text-bg-dark me-2">1</span>Select a Service and Date
                        </h5>
                        <div class="row g-3" id="professional-public-booking-step1-row">
                            <div class="col-md-7" id="professional-public-booking-service-col">
                                <label for="professional-public-booking-service" class="form-label">Service</label>
                                <select class="form-select" id="professional-public-booking-service" name="service_id" <?php echo $canAcceptPublicBookings ? '' : 'disabled'; ?>>
                                    <option value="">Choose a service</option>
                                    <?php foreach ($services as $service): ?>
                                    <?php
                                    $serviceLabelParts = [
                                        $service['name'],
                                        ((int)$service['duration_minutes']) . ' min',
                                    ];
                                    $priceLabel = professionalPublicFormatPrice($service['price'], $service['currency_code']);
                                    if ($priceLabel !== '') {
                                        $serviceLabelParts[] = $priceLabel;
                                    }
                                    ?>
                                    <option value="<?php echo (int)$service['id']; ?>">
                                        <?php echo htmlspecialchars(implode(' | ', $serviceLabelParts)); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3" id="professional-public-booking-date-col">
                                <label for="professional-public-booking-date" class="form-label">Date</label>
                                <input type="date"
                                       class="form-control"
                                       id="professional-public-booking-date"
                                       name="date"
                                       value="<?php echo htmlspecialchars($minDate); ?>"
                                       min="<?php echo htmlspecialchars($minDate); ?>"
                                       max="<?php echo htmlspecialchars($maxDate); ?>"
                                       <?php echo $canAcceptPublicBookings ? '' : 'disabled'; ?>>
                            </div>
                            <div class="col-md-2 d-flex align-items-end" id="professional-public-booking-check-col">
                                <button type="button"
                                        class="btn btn-dark w-100"
                                        id="professional-public-booking-check-btn"
                                        onclick="resetProfessionalPublicSelection()"
                                        hx-get="/pro-booking/check-slots.php?professional=<?php echo urlencode($slug); ?>"
                                        hx-include="#professional-public-booking-service, #professional-public-booking-date"
                                        hx-target="#professional-public-booking-slots"
                                        hx-swap="innerHTML"
                                        <?php echo $canAcceptPublicBookings ? '' : 'disabled'; ?>>
                                    Check
                                    <span class="htmx-indicator spinner-border spinner-border-sm ms-1"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-3" id="professional-public-booking-step2-card">
                    <div class="card-body" id="professional-public-booking-step2-body">
                        <h5 class="card-title mb-3" id="professional-public-booking-step2-title">
                            <span class="badge text-bg-dark me-2">2</span>Choose a Time
                        </h5>
                        <div id="professional-public-booking-slots">
                            <p class="text-muted mb-0" id="professional-public-booking-slots-placeholder">
                                Choose a service and date, then check availability to see open appointment times.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-3" id="professional-public-booking-step3-card" style="display: none;">
                    <div class="card-body" id="professional-public-booking-step3-body">
                        <h5 class="card-title mb-3" id="professional-public-booking-step3-title">
                            <span class="badge text-bg-dark me-2">3</span>Your Details
                        </h5>

                        <form id="professional-public-booking-form"
                              hx-post="/pro-booking/confirm.php"
                              hx-target="#professional-public-booking-confirmation"
                              hx-swap="innerHTML">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="professional" value="<?php echo htmlspecialchars($slug); ?>">
                            <input type="hidden" name="service_id" id="professional-public-booking-form-service-id" value="">
                            <input type="hidden" name="start_at" id="professional-public-booking-form-start-at" value="">

                            <div class="alert alert-light border" id="professional-public-booking-summary">
                                <div id="professional-public-booking-summary-service-wrap">
                                    <strong id="professional-public-booking-summary-service">Service</strong>
                                </div>
                                <div id="professional-public-booking-summary-time-wrap">
                                    <span id="professional-public-booking-summary-date">Date</span> at
                                    <span id="professional-public-booking-summary-time">Time</span>
                                </div>
                            </div>

                            <div class="row g-3" id="professional-public-booking-client-row-one">
                                <div class="col-md-6" id="professional-public-booking-first-name-col">
                                    <label for="professional-public-booking-first-name" class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="professional-public-booking-first-name" name="first_name" required>
                                </div>
                                <div class="col-md-6" id="professional-public-booking-last-name-col">
                                    <label for="professional-public-booking-last-name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="professional-public-booking-last-name" name="last_name" required>
                                </div>
                            </div>

                            <div class="row g-3 mt-0" id="professional-public-booking-client-row-two">
                                <div class="col-md-6" id="professional-public-booking-phone-col">
                                    <label for="professional-public-booking-phone" class="form-label">Phone <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" id="professional-public-booking-phone" name="phone" required>
                                </div>
                                <div class="col-md-6" id="professional-public-booking-email-col">
                                    <label for="professional-public-booking-email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="professional-public-booking-email" name="email" required>
                                </div>
                            </div>

                            <div class="mt-3" id="professional-public-booking-notes-wrap">
                                <label for="professional-public-booking-notes" class="form-label">Notes for Your Appointment</label>
                                <textarea class="form-control" id="professional-public-booking-notes" name="client_notes" rows="3" placeholder="Anything the provider should know before the appointment."></textarea>
                            </div>

                            <div class="mt-4" id="professional-public-booking-submit-wrap">
                                <button type="submit" class="btn btn-success btn-lg w-100" id="professional-public-booking-submit-btn">
                                    Confirm Appointment
                                    <span class="htmx-indicator spinner-border spinner-border-sm ms-1"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div id="professional-public-booking-confirmation"></div>
            </div>

            <div class="col-xl-4 col-lg-5" id="professional-public-booking-sidebar-col">
                <div class="card shadow-sm border-0 mb-3" id="professional-public-booking-sidebar-card">
                    <div class="card-body" id="professional-public-booking-sidebar-body">
                        <h5 class="mb-3" id="professional-public-booking-sidebar-title">Before You Book</h5>
                        <?php if (!empty($profile['default_location_label']) || !empty($profile['default_location_type'])): ?>
                        <div id="professional-public-booking-location-wrap">
                            <h6 class="mb-1" id="professional-public-booking-location-title">Default Location</h6>
                            <p class="text-muted" id="professional-public-booking-location-copy">
                                <?php echo htmlspecialchars(trim(($profile['default_location_label'] ?: '') !== '' ? $profile['default_location_label'] : ucwords(str_replace('_', ' ', $profile['default_location_type'])))); ?>
                            </p>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($profile['booking_instructions'])): ?>
                        <div id="professional-public-booking-instructions-wrap">
                            <h6 class="mb-1" id="professional-public-booking-instructions-title">Booking Instructions</h6>
                            <p class="text-muted mb-3" id="professional-public-booking-instructions-copy"><?php echo nl2br(htmlspecialchars($profile['booking_instructions'])); ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($profile['cancellation_policy'])): ?>
                        <div id="professional-public-booking-policy-wrap">
                            <h6 class="mb-1" id="professional-public-booking-policy-title">Cancellation Policy</h6>
                            <p class="text-muted mb-0" id="professional-public-booking-policy-copy"><?php echo nl2br(htmlspecialchars($profile['cancellation_policy'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card shadow-sm border-0" id="professional-public-booking-manage-card">
                    <div class="card-body" id="professional-public-booking-manage-body">
                        <h6 class="mb-2" id="professional-public-booking-manage-title">Already Booked?</h6>
                        <p class="text-muted mb-3" id="professional-public-booking-manage-copy">
                            Look up your appointment to reschedule or cancel it online.
                        </p>
                        <a href="/pro-booking/lookup.php?professional=<?php echo urlencode($slug); ?>" class="btn btn-outline-dark w-100" id="professional-public-booking-manage-btn">
                            Manage Existing Appointment
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function resetProfessionalPublicSelection() {
        document.querySelectorAll('.professional-public-slot-btn').forEach(function (button) {
            button.classList.remove('selected');
        });

        document.getElementById('professional-public-booking-form-service-id').value = '';
        document.getElementById('professional-public-booking-form-start-at').value = '';
        document.getElementById('professional-public-booking-step3-card').style.display = 'none';
        document.getElementById('professional-public-booking-confirmation').innerHTML = '';
    }

    function selectProfessionalPublicSlot(button) {
        document.querySelectorAll('.professional-public-slot-btn').forEach(function (item) {
            item.classList.remove('selected');
        });

        button.classList.add('selected');

        var dateValue = button.dataset.date || '';
        var timeValue = button.dataset.timeDisplay || '';
        var serviceId = button.dataset.serviceId || '';
        var serviceName = button.dataset.serviceName || '';
        var startAtValue = button.dataset.startAt || '';

        document.getElementById('professional-public-booking-form-service-id').value = serviceId;
        document.getElementById('professional-public-booking-form-start-at').value = startAtValue;
        document.getElementById('professional-public-booking-summary-service').textContent = serviceName;
        document.getElementById('professional-public-booking-summary-date').textContent = dateValue;
        document.getElementById('professional-public-booking-summary-time').textContent = timeValue;
        document.getElementById('professional-public-booking-step3-card').style.display = 'block';
        document.getElementById('professional-public-booking-step3-card').scrollIntoView({behavior: 'smooth', block: 'start'});
    }

    document.getElementById('professional-public-booking-service').addEventListener('change', resetProfessionalPublicSelection);
    document.getElementById('professional-public-booking-date').addEventListener('change', resetProfessionalPublicSelection);
    </script>
</body>
</html>
