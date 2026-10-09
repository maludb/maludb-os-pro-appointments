<?php
require_once '../../helpers/csrf.php';
require_once '../../helpers/professional-booking.php';
require_once '../../helpers/professional-availability.php';

session_start();

$slug = strtolower(trim($_GET['professional'] ?? ''));
$profile = getProfessionalProfileByBookingSlug($slug);

if (!$profile) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Not Found</title><link rel="stylesheet" href="/assets/css/bootstrap.min.css"></head><body><div class="container py-5" id="professional-lookup-not-found-container"><div class="alert alert-danger" id="professional-lookup-not-found-alert">The requested appointment page could not be found.</div></div></body></html>';
    exit;
}

$displayName = $profile['display_name'] ?: $profile['business_name'];
$isSearch = isset($_GET['code']);

if ($isSearch) {
    $code = strtoupper(trim($_GET['code'] ?? ''));
    $lastName = trim($_GET['last_name'] ?? '');

    if ($code === '' || $lastName === '') {
        echo '<div class="alert alert-warning" id="professional-lookup-missing-fields">Enter both the confirmation code and the client last name.</div>';
        exit;
    }

    $appointment = professionalGetAppointmentContextByConfirmation($slug, $code, $lastName);
    if (!$appointment) {
        echo '<div class="alert alert-warning" id="professional-lookup-not-found-result">No appointment was found with that confirmation code and last name.</div>';
        exit;
    }

    $restriction = professionalGetSelfServiceRestriction($appointment);
    $statusClass = professionalSelfServiceStatusClass((string)$appointment['status']);
    $canManage = ($restriction === null);
    ?>
    <div class="card shadow-sm border-0" id="professional-lookup-result-card">
        <div class="card-body" id="professional-lookup-result-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3" id="professional-lookup-result-header">
                <div id="professional-lookup-result-copy">
                    <h5 class="mb-1" id="professional-lookup-result-title">Appointment Details</h5>
                    <p class="text-muted mb-0" id="professional-lookup-result-subtitle"><?php echo htmlspecialchars($displayName); ?></p>
                </div>
                <span class="badge bg-<?php echo htmlspecialchars($statusClass); ?>" id="professional-lookup-result-status">
                    <?php echo htmlspecialchars(professionalSelfServiceStatusLabel((string)$appointment['status'])); ?>
                </span>
            </div>

            <table class="table table-borderless mb-0" id="professional-lookup-result-table">
                <tr id="professional-lookup-row-code"><td class="text-muted" style="width: 165px;">Confirmation</td><td><code><?php echo htmlspecialchars($appointment['confirmation_code']); ?></code></td></tr>
                <tr id="professional-lookup-row-service"><td class="text-muted">Service</td><td><?php echo htmlspecialchars($appointment['service_name']); ?></td></tr>
                <tr id="professional-lookup-row-date"><td class="text-muted">Date</td><td><?php echo htmlspecialchars(date('l, F j, Y', strtotime($appointment['start_at']))); ?></td></tr>
                <tr id="professional-lookup-row-time"><td class="text-muted">Time</td><td><?php echo htmlspecialchars(date('g:ia', strtotime($appointment['start_at']))); ?></td></tr>
                <tr id="professional-lookup-row-client"><td class="text-muted">Client</td><td><?php echo htmlspecialchars(trim($appointment['first_name'] . ' ' . $appointment['last_name'])); ?></td></tr>
                <?php if (!empty($appointment['client_email'])): ?>
                <tr id="professional-lookup-row-email"><td class="text-muted">Email</td><td><?php echo htmlspecialchars($appointment['client_email']); ?></td></tr>
                <?php endif; ?>
                <?php if (!empty($appointment['client_phone'])): ?>
                <tr id="professional-lookup-row-phone"><td class="text-muted">Phone</td><td><?php echo htmlspecialchars($appointment['client_phone']); ?></td></tr>
                <?php endif; ?>
                <?php if (!empty($appointment['location_label']) || !empty($appointment['location_type'])): ?>
                <tr id="professional-lookup-row-location"><td class="text-muted">Location</td><td><?php echo htmlspecialchars($appointment['location_label'] ?: ucwords(str_replace('_', ' ', $appointment['location_type']))); ?></td></tr>
                <?php endif; ?>
            </table>

            <?php if ($restriction !== null): ?>
            <div class="alert alert-warning mt-3 mb-0" id="professional-lookup-restriction-alert">
                <?php echo htmlspecialchars($restriction['message']); ?>
            </div>
            <?php endif; ?>

            <?php if ($canManage): ?>
            <div class="d-flex flex-wrap gap-2 mt-3" id="professional-lookup-result-actions">
                <a href="/pro-booking/modify.php?professional=<?php echo urlencode($slug); ?>&code=<?php echo urlencode($appointment['confirmation_code']); ?>&last_name=<?php echo urlencode($appointment['last_name']); ?>"
                   class="btn btn-outline-primary" id="professional-lookup-modify-btn">Reschedule Appointment</a>
                <a href="/pro-booking/cancel.php?professional=<?php echo urlencode($slug); ?>&code=<?php echo urlencode($appointment['confirmation_code']); ?>&last_name=<?php echo urlencode($appointment['last_name']); ?>"
                   class="btn btn-outline-danger" id="professional-lookup-cancel-btn">Cancel Appointment</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointment — <?php echo htmlspecialchars($displayName); ?></title>
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
    <script src="https://unpkg.com/htmx.org@2.0.8"></script>
    <style>
        body { background: #f8f9fa; }
        .professional-lookup-hero { background: linear-gradient(135deg, #173f35, #2b6e5d); color: #fff; }
        .htmx-indicator { display: none; }
        .htmx-request .htmx-indicator { display: inline-block; }
    </style>
</head>
<body>
    <div class="professional-lookup-hero py-4 mb-4" id="professional-lookup-hero">
        <div class="container text-center" id="professional-lookup-hero-container">
            <h2 class="mb-1" id="professional-lookup-title"><?php echo htmlspecialchars($displayName); ?></h2>
            <p class="mb-0 opacity-75" id="professional-lookup-subtitle">Find and manage your appointment</p>
        </div>
    </div>

    <div class="container pb-5" id="professional-lookup-container">
        <div class="row justify-content-center" id="professional-lookup-row">
            <div class="col-lg-6 col-md-8" id="professional-lookup-col">
                <div class="card shadow-sm border-0 mb-3" id="professional-lookup-form-card">
                    <div class="card-body" id="professional-lookup-form-body">
                        <h5 class="card-title" id="professional-lookup-form-title">Look Up Your Appointment</h5>
                        <p class="text-muted" id="professional-lookup-form-copy">
                            Enter the confirmation code and the client's last name to view the appointment details.
                        </p>

                        <div class="mb-3" id="professional-lookup-code-wrap">
                            <label for="professional-lookup-code" class="form-label">Confirmation Code</label>
                            <input type="text" class="form-control" id="professional-lookup-code" name="code" placeholder="ABC12345" style="text-transform: uppercase;">
                        </div>
                        <div class="mb-3" id="professional-lookup-last-name-wrap">
                            <label for="professional-lookup-last-name" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="professional-lookup-last-name" name="last_name">
                        </div>
                        <button type="button"
                                class="btn btn-dark w-100"
                                id="professional-lookup-search-btn"
                                hx-get="/pro-booking/lookup.php?professional=<?php echo urlencode($slug); ?>"
                                hx-include="#professional-lookup-code, #professional-lookup-last-name"
                                hx-target="#professional-lookup-results"
                                hx-swap="innerHTML">
                            Find Appointment
                            <span class="htmx-indicator spinner-border spinner-border-sm ms-1"></span>
                        </button>
                    </div>
                </div>

                <div id="professional-lookup-results"></div>

                <div class="text-center mt-3" id="professional-lookup-footer-actions">
                    <a href="/pro-booking/index.php?professional=<?php echo urlencode($slug); ?>" class="btn btn-outline-secondary btn-sm" id="professional-lookup-new-booking-btn">
                        Book a New Appointment
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
