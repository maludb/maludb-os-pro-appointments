<?php
require_once '../../helpers/csrf.php';
require_once '../../helpers/professional-booking.php';
require_once '../../helpers/professional-availability.php';
require_once '../../helpers/professional-notifications.php';

session_start();

$slug = strtolower(trim($_GET['professional'] ?? $_POST['professional'] ?? ''));
$code = strtoupper(trim($_GET['code'] ?? $_POST['code'] ?? ''));
$lastName = trim($_GET['last_name'] ?? $_POST['last_name'] ?? '');

if ($slug === '' || $code === '' || $lastName === '') {
    http_response_code(400);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Invalid Request</title><link rel="stylesheet" href="/assets/css/bootstrap.min.css"></head><body><div class="container py-5" id="professional-modify-invalid-container"><div class="alert alert-danger" id="professional-modify-invalid-alert">Missing required appointment lookup details.</div></div></body></html>';
    exit;
}

$appointment = professionalGetAppointmentContextByConfirmation($slug, $code, $lastName);
if (!$appointment) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Not Found</title><link rel="stylesheet" href="/assets/css/bootstrap.min.css"></head><body><div class="container py-5" id="professional-modify-not-found-container"><div class="alert alert-danger" id="professional-modify-not-found-alert">The appointment could not be found.</div></div></body></html>';
    exit;
}

$restaurantId = (int)$appointment['restaurant_id'];
$profile = getProfessionalProfile($restaurantId);
$service = getProfessionalService($restaurantId, (int)$appointment['service_id'], ['allow_inactive' => true]);
$displayName = trim((string)($appointment['display_name'] ?: $appointment['business_name'] ?: $appointment['restaurant_name']));
$restriction = professionalGetSelfServiceRestriction($appointment);
$message = '';

if ($profile) {
    $timezone = new DateTimeZone($profile['timezone']);
    $now = new DateTimeImmutable('now', $timezone);
    $minDate = $now->modify('+' . (int)$profile['minimum_booking_notice_hours'] . ' hours')->format('Y-m-d');
    $maxDate = $now->setTime(23, 59, 59)->modify('+' . (int)$profile['maximum_booking_horizon_days'] . ' days')->format('Y-m-d');
    if ($maxDate < $minDate) {
        $maxDate = $minDate;
    }
} else {
    $minDate = date('Y-m-d');
    $maxDate = date('Y-m-d', strtotime('+90 days'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'modify') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = '<div class="alert alert-danger" id="professional-modify-csrf-error">Invalid security token. Please refresh and try again.</div>';
    } elseif ($restriction !== null) {
        $message = '<div class="alert alert-warning" id="professional-modify-restricted-error">' . htmlspecialchars($restriction['message']) . '</div>';
    } elseif (!$service) {
        $message = '<div class="alert alert-warning" id="professional-modify-service-error">Online rescheduling is not available for this service right now. Please contact the business directly.</div>';
    } else {
        $newStartAt = trim($_POST['new_start_at'] ?? '');

        if ($newStartAt === '') {
            $message = '<div class="alert alert-danger" id="professional-modify-start-required">Choose a new appointment time first.</div>';
        } else {
            $slotValidation = validateProfessionalSlot($restaurantId, (int)$appointment['service_id'], $newStartAt, [
                'allow_inactive' => true,
                'exclude_appointment_id' => (int)$appointment['id'],
            ]);

            if (!$slotValidation['is_available']) {
                $message = '<div class="alert alert-danger" id="professional-modify-slot-error">' . htmlspecialchars($slotValidation['message']) . '</div>';
            } else {
                $slot = $slotValidation['slot'];
                $serviceData = $slotValidation['service'];
                $timezoneName = $profile['timezone'] ?? ($appointment['profile_timezone'] ?: 'America/New_York');
                $slotTimezone = new DateTimeZone($timezoneName);
                $startAtObject = professionalNormalizeDateTime($slot['start_at'], $slotTimezone);
                $endAtObject = professionalNormalizeDateTime($slot['end_at'], $slotTimezone);

                if ($startAtObject === null || $endAtObject === null) {
                    $message = '<div class="alert alert-danger" id="professional-modify-datetime-error">The selected appointment time is invalid.</div>';
                } else {
                    $pdo = db();
                    $oldValue = [
                        'start_at' => $appointment['start_at'],
                        'end_at' => $appointment['end_at'],
                        'appointment_date' => $appointment['appointment_date'],
                    ];
                    $newValue = [
                        'start_at' => $startAtObject->format('Y-m-d H:i:s'),
                        'end_at' => $endAtObject->format('Y-m-d H:i:s'),
                        'appointment_date' => $startAtObject->format('Y-m-d'),
                    ];

                    try {
                        $pdo->beginTransaction();

                        $updateStmt = $pdo->prepare(
                            "UPDATE professional_appointments SET
                                appointment_date = ?,
                                start_at = ?,
                                end_at = ?,
                                service_name = ?,
                                duration_minutes = ?,
                                buffer_before_minutes = ?,
                                buffer_after_minutes = ?,
                                price = ?,
                                currency_code = ?,
                                location_type = ?,
                                location_label = ?,
                                updated_at = NOW()
                             WHERE id = ? AND restaurant_id = ?"
                        );
                        $updateStmt->execute([
                            $startAtObject->format('Y-m-d'),
                            $startAtObject->format('Y-m-d H:i:s'),
                            $endAtObject->format('Y-m-d H:i:s'),
                            $serviceData['name'],
                            (int)$serviceData['duration_minutes'],
                            (int)$serviceData['effective_buffer_before_minutes'],
                            (int)$serviceData['effective_buffer_after_minutes'],
                            $serviceData['price'],
                            $serviceData['currency_code'],
                            $slot['location_type'] ?: null,
                            $slot['location_label'] ?: null,
                            (int)$appointment['id'],
                            $restaurantId,
                        ]);

                        $pdo->commit();

                        professionalLogAppointmentActivity(
                            $restaurantId,
                            null,
                            (int)$appointment['id'],
                            'self_service_reschedule',
                            'Client rescheduled appointment #' . $appointment['confirmation_code'],
                            $oldValue,
                            $newValue,
                            $_SERVER['REMOTE_ADDR'] ?? null
                        );

                        professionalSendAppointmentConfirmationNotifications((int)$appointment['id']);

                        $appointment = professionalGetAppointmentContextByConfirmation($slug, $code, $lastName) ?: $appointment;
                        $restriction = professionalGetSelfServiceRestriction($appointment);
                        $message = '<div class="alert alert-success" id="professional-modify-success">Your appointment has been updated successfully.</div>';
                    } catch (Throwable $exception) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }

                        $message = '<div class="alert alert-danger" id="professional-modify-save-error">Unable to update the appointment right now. Please try again.</div>';
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reschedule Appointment — <?php echo htmlspecialchars($displayName); ?></title>
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
    <script src="https://unpkg.com/htmx.org@2.0.8"></script>
    <style>
        body { background: #f8f9fa; }
        .professional-modify-hero { background: linear-gradient(135deg, #173f35, #2b6e5d); color: #fff; }
        .professional-modify-slot-btn.selected { background-color: #173f35 !important; border-color: #173f35 !important; color: #fff !important; }
        .htmx-indicator { display: none; }
        .htmx-request .htmx-indicator { display: inline-block; }
    </style>
</head>
<body>
    <div class="professional-modify-hero py-4 mb-4" id="professional-modify-hero">
        <div class="container text-center" id="professional-modify-hero-container">
            <h2 class="mb-1" id="professional-modify-title"><?php echo htmlspecialchars($displayName); ?></h2>
            <p class="mb-0 opacity-75" id="professional-modify-subtitle">Reschedule your appointment</p>
        </div>
    </div>

    <div class="container pb-5" id="professional-modify-container">
        <div class="row justify-content-center" id="professional-modify-row">
            <div class="col-lg-8 col-md-10" id="professional-modify-col">
                <?php echo $message; ?>

                <div class="card shadow-sm border-0 mb-3" id="professional-modify-current-card">
                    <div class="card-body" id="professional-modify-current-body">
                        <h5 class="card-title mb-3" id="professional-modify-current-title">Current Appointment</h5>
                        <div class="row g-3" id="professional-modify-current-fields">
                            <div class="col-md-6" id="professional-modify-current-service-col">
                                <div class="text-muted small" id="professional-modify-current-service-label">Service</div>
                                <div id="professional-modify-current-service-value"><?php echo htmlspecialchars($appointment['service_name']); ?></div>
                            </div>
                            <div class="col-md-6" id="professional-modify-current-code-col">
                                <div class="text-muted small" id="professional-modify-current-code-label">Confirmation Code</div>
                                <div id="professional-modify-current-code-value"><code><?php echo htmlspecialchars($appointment['confirmation_code']); ?></code></div>
                            </div>
                            <div class="col-md-6" id="professional-modify-current-date-col">
                                <div class="text-muted small" id="professional-modify-current-date-label">Current Date</div>
                                <div id="professional-modify-current-date-value"><?php echo htmlspecialchars(date('l, F j, Y', strtotime($appointment['start_at']))); ?></div>
                            </div>
                            <div class="col-md-6" id="professional-modify-current-time-col">
                                <div class="text-muted small" id="professional-modify-current-time-label">Current Time</div>
                                <div id="professional-modify-current-time-value"><?php echo htmlspecialchars(date('g:ia', strtotime($appointment['start_at']))); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($restriction !== null): ?>
                <div class="alert alert-warning" id="professional-modify-restriction-alert">
                    <?php echo htmlspecialchars($restriction['message']); ?>
                </div>
                <?php elseif (!$service): ?>
                <div class="alert alert-warning" id="professional-modify-service-alert">
                    Online rescheduling is not available for this service right now. Please contact the business directly.
                </div>
                <?php else: ?>
                <div class="card shadow-sm border-0 mb-3" id="professional-modify-form-card">
                    <div class="card-body" id="professional-modify-form-body">
                        <h5 class="card-title mb-3" id="professional-modify-form-title">Choose a New Time</h5>

                        <div class="row g-3 mb-3" id="professional-modify-check-row">
                            <div class="col-md-6" id="professional-modify-date-wrap">
                                <label for="professional-modify-date" class="form-label">New Date</label>
                                <input type="date" class="form-control" id="professional-modify-date" name="date"
                                       value="<?php echo htmlspecialchars($appointment['appointment_date']); ?>"
                                       min="<?php echo htmlspecialchars($minDate); ?>"
                                       max="<?php echo htmlspecialchars($maxDate); ?>">
                            </div>
                            <div class="col-md-6 d-flex align-items-end" id="professional-modify-check-wrap">
                                <button type="button"
                                        class="btn btn-outline-dark w-100"
                                        id="professional-modify-check-btn"
                                        onclick="resetProfessionalModifySelection()"
                                        hx-get="/pro-booking/check-modify-slots.php?professional=<?php echo urlencode($slug); ?>&code=<?php echo urlencode($code); ?>&last_name=<?php echo urlencode($lastName); ?>"
                                        hx-include="#professional-modify-date"
                                        hx-target="#professional-modify-slots"
                                        hx-swap="innerHTML">
                                    Check Availability
                                    <span class="htmx-indicator spinner-border spinner-border-sm ms-1"></span>
                                </button>
                            </div>
                        </div>

                        <div id="professional-modify-slots"></div>

                        <form method="POST" id="professional-modify-submit-form">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="professional" value="<?php echo htmlspecialchars($slug); ?>">
                            <input type="hidden" name="code" value="<?php echo htmlspecialchars($code); ?>">
                            <input type="hidden" name="last_name" value="<?php echo htmlspecialchars($lastName); ?>">
                            <input type="hidden" name="action" value="modify">
                            <input type="hidden" name="new_start_at" id="professional-modify-new-start-at" value="">

                            <div class="alert alert-light border mt-3 d-none" id="professional-modify-selection-summary">
                                <strong id="professional-modify-selection-summary-date">Date</strong> at
                                <strong id="professional-modify-selection-summary-time">Time</strong>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100 mt-3" id="professional-modify-submit-btn" disabled>
                                Confirm New Appointment Time
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <div class="text-center" id="professional-modify-footer-actions">
                    <a href="/pro-booking/lookup.php?professional=<?php echo urlencode($slug); ?>" class="btn btn-outline-secondary btn-sm" id="professional-modify-back-btn">
                        Back to Appointment Lookup
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
    function resetProfessionalModifySelection() {
        document.querySelectorAll('.professional-modify-slot-btn').forEach(function (button) {
            button.classList.remove('selected');
        });

        document.getElementById('professional-modify-new-start-at').value = '';
        document.getElementById('professional-modify-submit-btn').disabled = true;
        document.getElementById('professional-modify-selection-summary').classList.add('d-none');
    }

    function selectProfessionalModifySlot(button) {
        document.querySelectorAll('.professional-modify-slot-btn').forEach(function (item) {
            item.classList.remove('selected');
        });

        button.classList.add('selected');
        document.getElementById('professional-modify-new-start-at').value = button.dataset.startAt || '';
        document.getElementById('professional-modify-submit-btn').disabled = false;
        document.getElementById('professional-modify-selection-summary-date').textContent = document.getElementById('professional-modify-date').value;
        document.getElementById('professional-modify-selection-summary-time').textContent = button.dataset.timeDisplay || '';
        document.getElementById('professional-modify-selection-summary').classList.remove('d-none');
    }
    </script>
</body>
</html>
