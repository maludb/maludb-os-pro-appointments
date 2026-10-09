<?php
require_once '../../helpers/csrf.php';
require_once '../../helpers/professional-booking.php';
require_once '../../helpers/professional-notifications.php';

session_start();

$slug = strtolower(trim($_GET['professional'] ?? $_POST['professional'] ?? ''));
$code = strtoupper(trim($_GET['code'] ?? $_POST['code'] ?? ''));
$lastName = trim($_GET['last_name'] ?? $_POST['last_name'] ?? '');

if ($slug === '' || $code === '' || $lastName === '') {
    http_response_code(400);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Invalid Request</title><link rel="stylesheet" href="/assets/css/bootstrap.min.css"></head><body><div class="container py-5" id="professional-cancel-invalid-container"><div class="alert alert-danger" id="professional-cancel-invalid-alert">Missing required appointment lookup details.</div></div></body></html>';
    exit;
}

$appointment = professionalGetAppointmentContextByConfirmation($slug, $code, $lastName);
if (!$appointment) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Not Found</title><link rel="stylesheet" href="/assets/css/bootstrap.min.css"></head><body><div class="container py-5" id="professional-cancel-not-found-container"><div class="alert alert-danger" id="professional-cancel-not-found-alert">The appointment could not be found.</div></div></body></html>';
    exit;
}

$displayName = trim((string)($appointment['display_name'] ?: $appointment['business_name'] ?: $appointment['restaurant_name']));
$restriction = professionalGetSelfServiceRestriction($appointment);
$cancelled = false;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = '<div class="alert alert-danger" id="professional-cancel-csrf-error">Invalid security token. Please refresh and try again.</div>';
    } elseif ($restriction !== null) {
        $message = '<div class="alert alert-warning" id="professional-cancel-restriction-error">' . htmlspecialchars($restriction['message']) . '</div>';
    } else {
        $pdo = db();

        try {
            $pdo->beginTransaction();

            $updateStmt = $pdo->prepare(
                "UPDATE professional_appointments SET
                    status = 'cancelled',
                    cancelled_at = NOW(),
                    completed_at = NULL,
                    updated_at = NOW()
                 WHERE id = ? AND restaurant_id = ?"
            );
            $updateStmt->execute([
                (int)$appointment['id'],
                (int)$appointment['restaurant_id'],
            ]);

            $pdo->commit();

            professionalLogAppointmentActivity(
                (int)$appointment['restaurant_id'],
                null,
                (int)$appointment['id'],
                'self_service_cancel',
                'Client cancelled appointment #' . $appointment['confirmation_code'],
                ['status' => $appointment['status']],
                ['status' => 'cancelled'],
                $_SERVER['REMOTE_ADDR'] ?? null
            );

            professionalSendAppointmentCancellationNotifications((int)$appointment['id']);

            $appointment = professionalGetAppointmentContextByConfirmation($slug, $code, $lastName) ?: $appointment;
            $cancelled = true;
            $message = '<div class="alert alert-success" id="professional-cancel-success-message">Your appointment has been cancelled.</div>';
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $message = '<div class="alert alert-danger" id="professional-cancel-save-error">Unable to cancel the appointment right now. Please try again.</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancel Appointment — <?php echo htmlspecialchars($displayName); ?></title>
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
    <style>
        body { background: #f8f9fa; }
        .professional-cancel-hero { background: linear-gradient(135deg, #173f35, #2b6e5d); color: #fff; }
    </style>
</head>
<body>
    <div class="professional-cancel-hero py-4 mb-4" id="professional-cancel-hero">
        <div class="container text-center" id="professional-cancel-hero-container">
            <h2 class="mb-1" id="professional-cancel-title"><?php echo htmlspecialchars($displayName); ?></h2>
            <p class="mb-0 opacity-75" id="professional-cancel-subtitle">Cancel your appointment</p>
        </div>
    </div>

    <div class="container pb-5" id="professional-cancel-container">
        <div class="row justify-content-center" id="professional-cancel-row">
            <div class="col-lg-6 col-md-8" id="professional-cancel-col">
                <?php echo $message; ?>

                <?php if ($cancelled): ?>
                <div class="card border-danger shadow-sm mb-3" id="professional-cancel-success-card">
                    <div class="card-body text-center" id="professional-cancel-success-body">
                        <div class="mb-3" id="professional-cancel-success-icon" style="font-size: 3rem; color: #dc3545;">&#10007;</div>
                        <h4 class="text-danger" id="professional-cancel-success-title">Appointment Cancelled</h4>
                        <p class="text-muted mb-3" id="professional-cancel-success-copy">
                            Your appointment on <?php echo htmlspecialchars(date('l, F j, Y', strtotime($appointment['start_at']))); ?>
                            at <?php echo htmlspecialchars(date('g:ia', strtotime($appointment['start_at']))); ?> has been cancelled.
                        </p>
                        <a href="/pro-booking/index.php?professional=<?php echo urlencode($slug); ?>" class="btn btn-dark" id="professional-cancel-rebook-btn">
                            Book a New Appointment
                        </a>
                    </div>
                </div>
                <?php else: ?>
                <div class="card shadow-sm border-0 mb-3" id="professional-cancel-details-card">
                    <div class="card-body" id="professional-cancel-details-body">
                        <h5 class="card-title mb-3" id="professional-cancel-details-title">Appointment Details</h5>
                        <table class="table table-borderless mb-0" id="professional-cancel-details-table">
                            <tr id="professional-cancel-row-code"><td class="text-muted" style="width: 150px;">Confirmation</td><td><code><?php echo htmlspecialchars($appointment['confirmation_code']); ?></code></td></tr>
                            <tr id="professional-cancel-row-service"><td class="text-muted">Service</td><td><?php echo htmlspecialchars($appointment['service_name']); ?></td></tr>
                            <tr id="professional-cancel-row-date"><td class="text-muted">Date</td><td><?php echo htmlspecialchars(date('l, F j, Y', strtotime($appointment['start_at']))); ?></td></tr>
                            <tr id="professional-cancel-row-time"><td class="text-muted">Time</td><td><?php echo htmlspecialchars(date('g:ia', strtotime($appointment['start_at']))); ?></td></tr>
                            <tr id="professional-cancel-row-client"><td class="text-muted">Client</td><td><?php echo htmlspecialchars(trim($appointment['first_name'] . ' ' . $appointment['last_name'])); ?></td></tr>
                        </table>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-3" id="professional-cancel-policy-card">
                    <div class="card-body" id="professional-cancel-policy-body">
                        <h6 class="fw-bold" id="professional-cancel-policy-title">Cancellation Policy</h6>
                        <p class="text-muted mb-0" id="professional-cancel-policy-copy">
                            <?php echo htmlspecialchars($appointment['cancellation_policy'] ?: 'Please contact the business directly if you need to cancel inside the self-service window.'); ?>
                        </p>
                    </div>
                </div>

                <?php if ($restriction !== null): ?>
                <div class="alert alert-warning" id="professional-cancel-restriction-alert">
                    <?php echo htmlspecialchars($restriction['message']); ?>
                </div>
                <?php else: ?>
                <form method="POST" id="professional-cancel-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="professional" value="<?php echo htmlspecialchars($slug); ?>">
                    <input type="hidden" name="code" value="<?php echo htmlspecialchars($code); ?>">
                    <input type="hidden" name="last_name" value="<?php echo htmlspecialchars($lastName); ?>">
                    <input type="hidden" name="action" value="cancel">

                    <button type="submit" class="btn btn-danger btn-lg w-100 mb-3" id="professional-cancel-confirm-btn"
                            onclick="return confirm('Are you sure you want to cancel this appointment?');">
                        Confirm Cancellation
                    </button>
                </form>
                <?php endif; ?>
                <?php endif; ?>

                <div class="text-center" id="professional-cancel-footer-actions">
                    <a href="/pro-booking/lookup.php?professional=<?php echo urlencode($slug); ?>" class="btn btn-outline-secondary btn-sm" id="professional-cancel-back-btn">
                        Back to Appointment Lookup
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
