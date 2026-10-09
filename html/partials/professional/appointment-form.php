<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../helpers/professional-availability.php';

requireAuth();
requireManager();

$restaurantId = currentRestaurantId();
$appointmentId = (int)($_GET['appointment_id'] ?? 0);
$requestedClientId = (int)($_GET['client_id'] ?? 0);
$appointment = null;
$requestedClient = null;
$isEdit = false;

if (!$restaurantId) {
    echo '<div class="alert alert-danger" id="professional-appointment-form-no-restaurant">No professional account is currently selected.</div>';
    exit;
}

if ($appointmentId > 0) {
    $appointmentStmt = db()->prepare(
        "SELECT
            a.*,
            c.first_name,
            c.last_name,
            c.email AS client_email,
            c.phone AS client_phone
         FROM professional_appointments a
         JOIN professional_clients c ON c.id = a.client_id
         WHERE a.id = ? AND a.restaurant_id = ?
         LIMIT 1"
    );
    $appointmentStmt->execute([$appointmentId, $restaurantId]);
    $appointment = $appointmentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        echo '<div class="alert alert-danger" id="professional-appointment-form-not-found">Appointment not found.</div>';
        exit;
    }

    $isEdit = true;
}

if (!$isEdit && $requestedClientId > 0) {
    $requestedClientStmt = db()->prepare(
        "SELECT id, first_name, last_name, email, phone
         FROM professional_clients
         WHERE id = ? AND restaurant_id = ?
         LIMIT 1"
    );
    $requestedClientStmt->execute([$requestedClientId, $restaurantId]);
    $requestedClient = $requestedClientStmt->fetch(PDO::FETCH_ASSOC);
}

$currentServiceId = (int)($appointment['service_id'] ?? 0);
$servicesStmt = db()->prepare(
    "SELECT *
     FROM professional_services
     WHERE restaurant_id = ?
       AND (is_active = 1 OR id = ?)
     ORDER BY is_active DESC, sort_order ASC, name ASC"
);
$servicesStmt->execute([$restaurantId, $currentServiceId]);
$services = $servicesStmt->fetchAll(PDO::FETCH_ASSOC);

$selectedDate = $appointment ? date('Y-m-d', strtotime($appointment['start_at'])) : trim($_GET['date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}

$selectedStartAt = $appointment['start_at'] ?? '';
$selectedStatus = $appointment['status'] ?? 'confirmed';
$selectedClientId = $appointment ? (int)$appointment['client_id'] : (int)($requestedClient['id'] ?? 0);
$selectedSource = $appointment['source'] ?? 'staff';
$selectedServiceId = $currentServiceId;
$selectedClientName = $appointment
    ? trim(($appointment['first_name'] ?? '') . ' ' . ($appointment['last_name'] ?? ''))
    : trim(($requestedClient['first_name'] ?? '') . ' ' . ($requestedClient['last_name'] ?? ''));

$statusOptions = [
    'pending' => 'Pending',
    'confirmed' => 'Confirmed',
];

if ($isEdit && !isset($statusOptions[$selectedStatus])) {
    $statusOptions = [$selectedStatus => ucfirst(str_replace('_', ' ', $selectedStatus))] + $statusOptions;
}
?>
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" id="professional-appointment-modal">
    <div class="modal-dialog modal-xl" id="professional-appointment-modal-dialog">
        <div class="modal-content" id="professional-appointment-modal-content">
            <div class="modal-header" id="professional-appointment-modal-header">
                <h5 class="modal-title" id="professional-appointment-modal-title">
                    <?php echo $isEdit ? 'Edit Appointment' : 'New Appointment'; ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="professional-appointment-form"
                  hx-post="/partials/professional/save-appointment.php"
                  hx-target="#professional-appointment-form-feedback"
                  hx-swap="innerHTML">
                <div class="modal-body" id="professional-appointment-modal-body">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="appointment_id" id="professional-appointment-id" value="<?php echo (int)$appointmentId; ?>">
                    <input type="hidden" name="start_at" id="professional-appointment-start-at" value="<?php echo htmlspecialchars($selectedStartAt); ?>">
                    <input type="hidden" name="client_id" id="professional-appointment-client-id" value="<?php echo $selectedClientId; ?>">
                    <input type="hidden" name="source" id="professional-appointment-source" value="<?php echo htmlspecialchars($selectedSource); ?>">

                    <div id="professional-appointment-form-feedback"></div>

                    <?php if (empty($services)): ?>
                    <div class="alert alert-warning" id="professional-appointment-no-services">
                        Add at least one active service before creating appointments.
                    </div>
                    <div class="d-flex gap-2" id="professional-appointment-no-services-actions">
                        <button type="button" class="btn btn-primary"
                                id="professional-appointment-open-services-btn"
                                hx-get="/partials/professional/services.php"
                                hx-target="#page-content"
                                data-bs-dismiss="modal">
                            Open Services
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="row g-4" id="professional-appointment-form-layout">
                        <div class="col-lg-7" id="professional-appointment-primary-col">
                            <div class="card border bg-light" id="professional-appointment-booking-card">
                                <div class="card-body" id="professional-appointment-booking-body">
                                    <h6 class="fw-bold mb-3" id="professional-appointment-booking-title">1. Service and Time</h6>

                                    <div class="row g-3" id="professional-appointment-booking-fields">
                                        <div class="col-md-7" id="professional-appointment-service-wrap">
                                            <label for="professional-appointment-service" class="form-label fw-semibold">Service <span class="text-danger">*</span></label>
                                            <select class="form-select" id="professional-appointment-service" name="service_id" required onchange="window.clearProfessionalAppointmentSlot && window.clearProfessionalAppointmentSlot();">
                                                <option value="">Choose a service</option>
                                                <?php foreach ($services as $service): ?>
                                                <option value="<?php echo (int)$service['id']; ?>" <?php echo $selectedServiceId === (int)$service['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($service['name']); ?><?php echo ((int)$service['is_active'] !== 1) ? ' (Inactive)' : ''; ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-5" id="professional-appointment-date-wrap">
                                            <label for="professional-appointment-date" class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" id="professional-appointment-date" name="appointment_date"
                                                   value="<?php echo htmlspecialchars($selectedDate); ?>"
                                                   onchange="window.clearProfessionalAppointmentSlot && window.clearProfessionalAppointmentSlot();"
                                                   required>
                                        </div>
                                    </div>

                                    <div class="d-flex flex-wrap gap-2 align-items-center mt-3" id="professional-appointment-slot-actions">
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="professional-appointment-load-slots-btn"
                                                hx-get="/partials/professional/check-slots.php"
                                                hx-include="#professional-appointment-service, #professional-appointment-date, #professional-appointment-id, #professional-appointment-start-at"
                                                hx-target="#professional-appointment-slot-results"
                                                hx-swap="innerHTML">
                                            <i class="feather-search me-1"></i> Load Available Times
                                        </button>
                                        <span class="badge bg-primary <?php echo $selectedStartAt ? '' : 'd-none'; ?>" id="professional-appointment-selected-slot">
                                            <?php echo $selectedStartAt ? htmlspecialchars(date('M j, Y g:ia', strtotime($selectedStartAt))) : ''; ?>
                                        </span>
                                    </div>

                                    <div class="mt-3" id="professional-appointment-slot-results">
                                        <p class="text-muted mb-0" id="professional-appointment-slot-placeholder">
                                            Choose a service and date, then load available times.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="card border bg-light mt-4" id="professional-appointment-client-card">
                                <div class="card-body" id="professional-appointment-client-body">
                                    <div class="d-flex justify-content-between align-items-center gap-2 mb-3" id="professional-appointment-client-title-row">
                                        <h6 class="fw-bold mb-0" id="professional-appointment-client-title">2. Client</h6>
                                        <a href="#" class="btn btn-outline-secondary btn-sm" id="professional-appointment-manage-clients-btn"
                                           hx-get="/partials/professional/clients.php"
                                           hx-target="#page-content"
                                           data-bs-dismiss="modal">
                                            Manage Clients
                                        </a>
                                    </div>

                                    <div class="mb-3" id="professional-appointment-client-search-wrap">
                                        <label for="professional-appointment-client-search" class="form-label fw-semibold">Search Existing Client</label>
                                        <input type="text" class="form-control" id="professional-appointment-client-search"
                                               name="client_query"
                                               placeholder="Type name, email, or phone"
                                               hx-get="/partials/professional/client-search.php"
                                               hx-trigger="keyup changed delay:250ms"
                                               hx-target="#professional-appointment-client-search-results"
                                               hx-swap="innerHTML"
                                               autocomplete="off">
                                        <div id="professional-appointment-client-search-results"></div>
                                    </div>

                                    <div class="alert alert-info <?php echo $selectedClientId > 0 ? '' : 'd-none'; ?>" id="professional-appointment-client-selected">
                                        <div class="d-flex justify-content-between align-items-center gap-2" id="professional-appointment-client-selected-row">
                                            <div id="professional-appointment-client-selected-copy">
                                                <strong id="professional-appointment-client-selected-name"><?php echo htmlspecialchars($selectedClientName ?: 'Selected client'); ?></strong>
                                                <div class="small mb-0" id="professional-appointment-client-selected-contact">
                                                    <?php
                                                    $selectedClientContact = $appointment['client_phone'] ?? ($requestedClient['phone'] ?? '');
                                                    if ($selectedClientContact === '') {
                                                        $selectedClientContact = $appointment['client_email'] ?? ($requestedClient['email'] ?? '');
                                                    }
                                                    echo htmlspecialchars($selectedClientContact);
                                                    ?>
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-outline-secondary btn-sm" id="professional-appointment-client-clear-btn"
                                                    onclick="window.clearProfessionalClientSelection && window.clearProfessionalClientSelection();">
                                                Clear
                                            </button>
                                        </div>
                                    </div>

                                    <div class="row g-3" id="professional-appointment-client-fields">
                                        <div class="col-md-6" id="professional-appointment-client-first-wrap">
                                            <label for="professional-appointment-client-first" class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="professional-appointment-client-first" name="first_name"
                                                   value="<?php echo htmlspecialchars($appointment['first_name'] ?? ($requestedClient['first_name'] ?? '')); ?>" required>
                                        </div>
                                        <div class="col-md-6" id="professional-appointment-client-last-wrap">
                                            <label for="professional-appointment-client-last" class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="professional-appointment-client-last" name="last_name"
                                                   value="<?php echo htmlspecialchars($appointment['last_name'] ?? ($requestedClient['last_name'] ?? '')); ?>" required>
                                        </div>
                                        <div class="col-md-6" id="professional-appointment-client-email-wrap">
                                            <label for="professional-appointment-client-email" class="form-label fw-semibold">Email</label>
                                            <input type="email" class="form-control" id="professional-appointment-client-email" name="email"
                                                   value="<?php echo htmlspecialchars($appointment['client_email'] ?? ($requestedClient['email'] ?? '')); ?>">
                                        </div>
                                        <div class="col-md-6" id="professional-appointment-client-phone-wrap">
                                            <label for="professional-appointment-client-phone" class="form-label fw-semibold">Phone</label>
                                            <input type="text" class="form-control" id="professional-appointment-client-phone" name="phone"
                                                   value="<?php echo htmlspecialchars($appointment['client_phone'] ?? ($requestedClient['phone'] ?? '')); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card border bg-light mt-4" id="professional-appointment-service-contact-card">
                                <div class="card-body" id="professional-appointment-service-contact-body">
                                    <h6 class="fw-bold mb-3" id="professional-appointment-service-contact-title">3. Service Contact &amp; Location</h6>

                                    <div class="row g-3" id="professional-appointment-service-contact-fields">
                                        <div class="col-md-6" id="professional-appointment-service-contact-name-wrap">
                                            <label for="professional-appointment-service-contact-name" class="form-label fw-semibold">Contact Name</label>
                                            <input type="text" class="form-control" id="professional-appointment-service-contact-name" name="service_contact_name"
                                                   value="<?php echo htmlspecialchars($appointment['service_contact_name'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6" id="professional-appointment-service-phone-wrap">
                                            <label for="professional-appointment-service-phone" class="form-label fw-semibold">Contact Phone</label>
                                            <input type="text" class="form-control" id="professional-appointment-service-phone" name="service_phone"
                                                   value="<?php echo htmlspecialchars($appointment['service_phone'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6" id="professional-appointment-service-contact-method-wrap">
                                            <label for="professional-appointment-service-contact-method" class="form-label fw-semibold">Contact Method</label>
                                            <select class="form-select" id="professional-appointment-service-contact-method" name="service_contact_method">
                                                <?php
                                                $contactMethods = ['' => 'Not specified', 'ph' => 'Phone', 'em' => 'Email', 'tx' => 'Text', 'ip' => 'In Person'];
                                                $currentMethod = $appointment['service_contact_method'] ?? '';
                                                foreach ($contactMethods as $mVal => $mLabel): ?>
                                                <option value="<?php echo $mVal; ?>" <?php echo $currentMethod === $mVal ? 'selected' : ''; ?>><?php echo $mLabel; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-12" id="professional-appointment-service-address-wrap">
                                            <label for="professional-appointment-service-address" class="form-label fw-semibold">Service Address</label>
                                            <input type="text" class="form-control" id="professional-appointment-service-address" name="service_address_1"
                                                   value="<?php echo htmlspecialchars($appointment['service_address_1'] ?? ''); ?>"
                                                   placeholder="Street address">
                                        </div>
                                        <div class="col-md-5" id="professional-appointment-service-city-wrap">
                                            <label for="professional-appointment-service-city" class="form-label fw-semibold">City</label>
                                            <input type="text" class="form-control" id="professional-appointment-service-city" name="service_city"
                                                   value="<?php echo htmlspecialchars($appointment['service_city'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-3" id="professional-appointment-service-state-wrap">
                                            <label for="professional-appointment-service-state" class="form-label fw-semibold">State</label>
                                            <input type="text" class="form-control" id="professional-appointment-service-state" name="service_state"
                                                   value="<?php echo htmlspecialchars($appointment['service_state'] ?? ''); ?>" maxlength="2">
                                        </div>
                                        <div class="col-md-4" id="professional-appointment-service-postal-wrap">
                                            <label for="professional-appointment-service-postal" class="form-label fw-semibold">Postal Code</label>
                                            <input type="text" class="form-control" id="professional-appointment-service-postal" name="service_postal_code"
                                                   value="<?php echo htmlspecialchars($appointment['service_postal_code'] ?? ''); ?>" maxlength="5">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-5" id="professional-appointment-secondary-col">
                            <div class="card border bg-light h-100" id="professional-appointment-details-card">
                                <div class="card-body" id="professional-appointment-details-body">
                                    <h6 class="fw-bold mb-3" id="professional-appointment-details-title">4. Appointment Details</h6>

                                    <div class="mb-3" id="professional-appointment-status-wrap">
                                        <label for="professional-appointment-status" class="form-label fw-semibold">Status</label>
                                        <select class="form-select" id="professional-appointment-status" name="status">
                                            <?php foreach ($statusOptions as $statusValue => $statusLabel): ?>
                                            <option value="<?php echo htmlspecialchars($statusValue); ?>" <?php echo $selectedStatus === $statusValue ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($statusLabel); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if ($isEdit && !in_array($selectedStatus, ['pending', 'confirmed'], true)): ?>
                                        <div class="form-text" id="professional-appointment-status-help">
                                            Quick status buttons in the calendar are the preferred way to manage completed, cancelled, and no-show appointments.
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="mb-3" id="professional-appointment-client-notes-wrap">
                                        <label for="professional-appointment-client-notes" class="form-label fw-semibold">Client Notes</label>
                                        <textarea class="form-control" id="professional-appointment-client-notes" name="client_notes" rows="4"><?php echo htmlspecialchars($appointment['client_notes'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="mb-3" id="professional-appointment-internal-notes-wrap">
                                        <label for="professional-appointment-internal-notes" class="form-label fw-semibold">Internal Notes</label>
                                        <textarea class="form-control" id="professional-appointment-internal-notes" name="internal_notes" rows="6"><?php echo htmlspecialchars($appointment['internal_notes'] ?? ''); ?></textarea>
                                    </div>

                                    <?php if ($isEdit): ?>
                                    <div class="small text-muted" id="professional-appointment-edit-meta">
                                        Created <?php echo htmlspecialchars(date('M j, Y g:ia', strtotime($appointment['created_at']))); ?>
                                        <?php if (!empty($appointment['confirmation_code'])): ?>
                                        <span class="ms-2">Code: <code><?php echo htmlspecialchars($appointment['confirmation_code']); ?></code></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="modal-footer" id="professional-appointment-modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="professional-appointment-cancel-btn">Cancel</button>
                    <?php if (!empty($services)): ?>
                    <button type="submit" class="btn btn-primary" id="professional-appointment-save-btn">
                        <i class="feather-save me-1"></i> <?php echo $isEdit ? 'Update' : 'Create'; ?> Appointment
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    window.selectProfessionalClient = function (button) {
        var clientIdField = document.getElementById('professional-appointment-client-id');
        var firstNameField = document.getElementById('professional-appointment-client-first');
        var lastNameField = document.getElementById('professional-appointment-client-last');
        var emailField = document.getElementById('professional-appointment-client-email');
        var phoneField = document.getElementById('professional-appointment-client-phone');
        var selectedBox = document.getElementById('professional-appointment-client-selected');
        var selectedName = document.getElementById('professional-appointment-client-selected-name');
        var selectedContact = document.getElementById('professional-appointment-client-selected-contact');
        var searchInput = document.getElementById('professional-appointment-client-search');
        var resultsWrap = document.getElementById('professional-appointment-client-search-results');

        if (!clientIdField || !firstNameField || !lastNameField || !selectedBox || !selectedName || !selectedContact) {
            return;
        }

        clientIdField.value = button.dataset.clientId || '';
        firstNameField.value = button.dataset.firstName || '';
        lastNameField.value = button.dataset.lastName || '';
        emailField.value = button.dataset.email || '';
        phoneField.value = button.dataset.phone || '';
        selectedName.textContent = (button.dataset.firstName || '') + ' ' + (button.dataset.lastName || '');
        selectedContact.textContent = button.dataset.phone || button.dataset.email || '';
        selectedBox.classList.remove('d-none');

        if (searchInput) {
            searchInput.value = '';
        }
        if (resultsWrap) {
            resultsWrap.innerHTML = '';
        }
    };

    window.clearProfessionalClientSelection = function () {
        var clientIdField = document.getElementById('professional-appointment-client-id');
        var selectedBox = document.getElementById('professional-appointment-client-selected');
        var selectedName = document.getElementById('professional-appointment-client-selected-name');
        var selectedContact = document.getElementById('professional-appointment-client-selected-contact');

        if (clientIdField) {
            clientIdField.value = '';
        }
        if (selectedName) {
            selectedName.textContent = '';
        }
        if (selectedContact) {
            selectedContact.textContent = '';
        }
        if (selectedBox) {
            selectedBox.classList.add('d-none');
        }
    };

    window.selectProfessionalAppointmentSlot = function (button) {
        var startAtField = document.getElementById('professional-appointment-start-at');
        var selectedBadge = document.getElementById('professional-appointment-selected-slot');

        if (!startAtField || !selectedBadge) {
            return;
        }

        document.querySelectorAll('#professional-appointment-slot-results .professional-slot-btn').forEach(function (slotButton) {
            slotButton.classList.remove('btn-primary');
            slotButton.classList.add('btn-outline-primary');
        });

        button.classList.remove('btn-outline-primary');
        button.classList.add('btn-primary');

        startAtField.value = button.dataset.startAt || '';
        selectedBadge.textContent = button.dataset.display || '';
        selectedBadge.classList.remove('d-none');
    };

    window.clearProfessionalAppointmentSlot = function () {
        var startAtField = document.getElementById('professional-appointment-start-at');
        var selectedBadge = document.getElementById('professional-appointment-selected-slot');
        var slotResults = document.getElementById('professional-appointment-slot-results');

        if (startAtField) {
            startAtField.value = '';
        }
        if (selectedBadge) {
            selectedBadge.textContent = '';
            selectedBadge.classList.add('d-none');
        }
        if (slotResults) {
            slotResults.innerHTML = '<p class="text-muted mb-0" id="professional-appointment-slot-placeholder-reset">Choose a service and date, then load available times.</p>';
        }
    };
})();
</script>
