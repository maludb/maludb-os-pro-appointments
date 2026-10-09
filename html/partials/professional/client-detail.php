<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();
requireManager();

$restaurantId = currentRestaurantId();
$clientId = (int)($_GET['id'] ?? 0);

if (!$restaurantId) {
    echo '<div class="alert alert-danger" id="professional-client-detail-no-restaurant">No professional account is currently selected.</div>';
    exit;
}

if ($clientId <= 0) {
    echo '<div class="alert alert-danger" id="professional-client-detail-invalid">Invalid professional client.</div>';
    exit;
}

$clientStmt = db()->prepare(
    "SELECT *
     FROM professional_clients
     WHERE id = ? AND restaurant_id = ?
     LIMIT 1"
);
$clientStmt->execute([$clientId, $restaurantId]);
$client = $clientStmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    echo '<div class="alert alert-danger" id="professional-client-detail-not-found">Professional client not found.</div>';
    exit;
}

$statsStmt = db()->prepare(
    "SELECT
        COUNT(*) AS total_appointments,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_appointments,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed_appointments,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_appointments,
        SUM(CASE WHEN status IN ('cancelled', 'no_show') THEN 1 ELSE 0 END) AS issue_appointments,
        MAX(start_at) AS last_appointment_at
     FROM professional_appointments
     WHERE restaurant_id = ? AND client_id = ?"
);
$statsStmt->execute([$restaurantId, $clientId]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [
    'total_appointments' => 0,
    'completed_appointments' => 0,
    'confirmed_appointments' => 0,
    'pending_appointments' => 0,
    'issue_appointments' => 0,
    'last_appointment_at' => null,
];

$historyStmt = db()->prepare(
    "SELECT
        a.*,
        s.color AS service_color
     FROM professional_appointments a
     LEFT JOIN professional_services s ON s.id = a.service_id AND s.restaurant_id = a.restaurant_id
     WHERE a.restaurant_id = ? AND a.client_id = ?
     ORDER BY a.start_at DESC, a.created_at DESC
     LIMIT 50"
);
$historyStmt->execute([$restaurantId, $clientId]);
$history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

$statusColors = [
    'pending' => 'warning',
    'confirmed' => 'primary',
    'completed' => 'success',
    'cancelled' => 'dark',
    'no_show' => 'danger',
];

// --- Communications History (all channels) ---
$comms = [];

// SMS messages
$smsStmt = db()->prepare(
    "SELECT 'sms' AS channel, direction, from_number AS contact_from, to_number AS contact_to,
            body AS summary, status, created_at
     FROM sms_message_log
     WHERE restaurant_id = ? AND client_id = ?
     ORDER BY created_at DESC LIMIT 50"
);
$smsStmt->execute([$restaurantId, $clientId]);
$comms = array_merge($comms, $smsStmt->fetchAll(PDO::FETCH_ASSOC));

// Voice calls
$voiceStmt = db()->prepare(
    "SELECT 'voice' AS channel, direction, from_number AS contact_from, to_number AS contact_to,
            COALESCE(call_summary, '') AS summary, status, created_at
     FROM call_logs
     WHERE restaurant_id = ? AND client_id = ?
     ORDER BY created_at DESC LIMIT 50"
);
$voiceStmt->execute([$restaurantId, $clientId]);
$comms = array_merge($comms, $voiceStmt->fetchAll(PDO::FETCH_ASSOC));

// Email messages
$emailStmt = db()->prepare(
    "SELECT 'email' AS channel, direction, from_address AS contact_from, to_address AS contact_to,
            COALESCE(subject, '') AS summary, status, created_at
     FROM email_message_log
     WHERE restaurant_id = ? AND client_id = ?
     ORDER BY created_at DESC LIMIT 50"
);
$emailStmt->execute([$restaurantId, $clientId]);
$comms = array_merge($comms, $emailStmt->fetchAll(PDO::FETCH_ASSOC));

// Sort all communications by date descending
usort($comms, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
$comms = array_slice($comms, 0, 50);

$channelIcons = ['sms' => 'feather-message-square', 'voice' => 'feather-phone-call', 'email' => 'feather-mail'];
$channelColors = ['sms' => 'primary', 'voice' => 'success', 'email' => 'info'];
?>
<div id="professional-client-detail-main"
     style="padding: 20px;"
     hx-get="/partials/professional/client-detail.php?id=<?php echo $clientId; ?>"
     hx-trigger="refreshProfessionalClientDetail from:body"
     hx-target="#professional-client-detail-main"
     hx-swap="outerHTML">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4" id="professional-client-detail-header">
        <div id="professional-client-detail-header-copy">
            <h4 class="mb-1" id="professional-client-detail-name"><?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?></h4>
            <p class="text-muted mb-0" id="professional-client-detail-subtitle">
                Client since <?php echo htmlspecialchars(date('M j, Y', strtotime($client['created_at']))); ?>
                <?php if (!empty($client['last_appointment_at'])): ?>
                <span class="ms-2">Last appointment <?php echo htmlspecialchars(date('M j, Y g:ia', strtotime($client['last_appointment_at']))); ?></span>
                <?php endif; ?>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2" id="professional-client-detail-actions">
            <button class="btn btn-primary" id="professional-client-detail-new-appointment-btn"
                    hx-get="/partials/professional/appointment-form.php?client_id=<?php echo $clientId; ?>"
                    hx-target="#professional-modal-container"
                    hx-swap="innerHTML">
                <i class="feather-plus me-1"></i> New Appointment
            </button>
            <button class="btn btn-outline-primary" id="professional-client-detail-edit-btn"
                    hx-get="/partials/professional/client-form.php?client_id=<?php echo $clientId; ?>"
                    hx-target="#professional-modal-container"
                    hx-swap="innerHTML">
                <i class="feather-edit-2 me-1"></i> Edit Client
            </button>
            <a href="#" class="btn btn-outline-secondary" id="professional-client-detail-back-btn"
               hx-get="/partials/professional/clients.php"
               hx-target="#page-content">
                <i class="feather-arrow-left me-1"></i> Back to Clients
            </a>
        </div>
    </div>

    <div id="professional-client-detail-messages"></div>

    <div class="row g-3 mb-4" id="professional-client-detail-stats-row">
        <div class="col-md-6 col-xl-3" id="professional-client-detail-stat-total-col">
            <div class="card h-100" id="professional-client-detail-stat-total-card">
                <div class="card-body py-3 text-center" id="professional-client-detail-stat-total-body">
                    <h4 class="mb-1"><?php echo (int)$stats['total_appointments']; ?></h4>
                    <div class="small text-muted" id="professional-client-detail-stat-total-label">Appointments</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3" id="professional-client-detail-stat-completed-col">
            <div class="card h-100" id="professional-client-detail-stat-completed-card">
                <div class="card-body py-3 text-center" id="professional-client-detail-stat-completed-body">
                    <h4 class="mb-1 text-success"><?php echo (int)$stats['completed_appointments']; ?></h4>
                    <div class="small text-muted" id="professional-client-detail-stat-completed-label">Completed</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3" id="professional-client-detail-stat-confirmed-col">
            <div class="card h-100" id="professional-client-detail-stat-confirmed-card">
                <div class="card-body py-3 text-center" id="professional-client-detail-stat-confirmed-body">
                    <h4 class="mb-1 text-primary"><?php echo (int)$stats['confirmed_appointments'] + (int)$stats['pending_appointments']; ?></h4>
                    <div class="small text-muted" id="professional-client-detail-stat-confirmed-label">Open Appointments</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3" id="professional-client-detail-stat-issues-col">
            <div class="card h-100" id="professional-client-detail-stat-issues-card">
                <div class="card-body py-3 text-center" id="professional-client-detail-stat-issues-body">
                    <h4 class="mb-1 text-danger"><?php echo (int)$stats['issue_appointments']; ?></h4>
                    <div class="small text-muted" id="professional-client-detail-stat-issues-label">Cancelled / No Show</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4" id="professional-client-detail-layout">
        <div class="col-lg-4" id="professional-client-detail-sidebar">
            <div class="card mb-3" id="professional-client-detail-contact-card">
                <div class="card-body" id="professional-client-detail-contact-body">
                    <h6 class="fw-bold mb-3" id="professional-client-detail-contact-title">Contact Information</h6>
                    <table class="table table-borderless table-sm mb-0" id="professional-client-detail-contact-table">
                        <tr id="professional-client-detail-email-row">
                            <td class="text-muted" style="width: 140px;">Email</td>
                            <td><?php echo htmlspecialchars($client['email'] ?: 'Not provided'); ?></td>
                        </tr>
                        <tr id="professional-client-detail-phone-row">
                            <td class="text-muted">Phone</td>
                            <td><?php echo htmlspecialchars($client['phone'] ?: 'Not provided'); ?></td>
                        </tr>
                        <tr id="professional-client-detail-preferred-row">
                            <td class="text-muted">Preferred Contact</td>
                            <td><?php echo htmlspecialchars($client['preferred_contact_method'] ? ucfirst($client['preferred_contact_method']) : 'Not set'); ?></td>
                        </tr>
                        <tr id="professional-client-detail-marketing-row">
                            <td class="text-muted">Marketing Opt-In</td>
                            <td>
                                <?php if ((int)$client['marketing_opt_in'] === 1): ?>
                                <span class="badge bg-success">Yes</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">No</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr id="professional-client-detail-birth-row">
                            <td class="text-muted">Birth Date</td>
                            <td><?php echo !empty($client['birth_date']) ? htmlspecialchars(date('M j, Y', strtotime($client['birth_date']))) : 'Not provided'; ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="card mb-3" id="professional-client-detail-address-card">
                <div class="card-body" id="professional-client-detail-address-body">
                    <h6 class="fw-bold mb-3" id="professional-client-detail-address-title">Service Address</h6>
                    <table class="table table-borderless table-sm mb-0" id="professional-client-detail-address-table">
                        <tr id="professional-client-detail-address-line1-row">
                            <td class="text-muted" style="width: 140px;">Address</td>
                            <td><?php echo htmlspecialchars($client['service_address_line1'] ?: 'Not provided'); ?></td>
                        </tr>
                        <tr id="professional-client-detail-city-row">
                            <td class="text-muted">City</td>
                            <td><?php echo htmlspecialchars($client['service_city'] ?: 'Not provided'); ?></td>
                        </tr>
                        <tr id="professional-client-detail-state-row">
                            <td class="text-muted">State</td>
                            <td><?php echo htmlspecialchars($client['service_state'] ?: 'Not provided'); ?></td>
                        </tr>
                        <tr id="professional-client-detail-postal-row">
                            <td class="text-muted">Postal Code</td>
                            <td><?php echo htmlspecialchars($client['service_postal_code'] ?: 'Not provided'); ?></td>
                        </tr>
                        <tr id="professional-client-detail-last-service-row">
                            <td class="text-muted">Last Service</td>
                            <td><?php echo !empty($client['last_service_date']) ? htmlspecialchars(date('M j, Y', strtotime($client['last_service_date']))) : 'Not provided'; ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="card mb-3" id="professional-client-detail-notes-card">
                <div class="card-body" id="professional-client-detail-notes-body">
                    <h6 class="fw-bold mb-2" id="professional-client-detail-notes-title">Client Notes</h6>
                    <p class="mb-0 text-muted" id="professional-client-detail-notes-copy">
                        <?php echo nl2br(htmlspecialchars($client['notes'] ?: 'No client-facing notes have been added.')); ?>
                    </p>
                </div>
            </div>

            <div class="card" id="professional-client-detail-internal-card">
                <div class="card-body" id="professional-client-detail-internal-body">
                    <h6 class="fw-bold mb-2" id="professional-client-detail-internal-title">Internal Notes</h6>
                    <p class="mb-0 text-muted" id="professional-client-detail-internal-copy">
                        <?php echo nl2br(htmlspecialchars($client['internal_notes'] ?: 'No internal notes have been added.')); ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-lg-8" id="professional-client-detail-content">
            <div class="card" id="professional-client-detail-history-card">
                <div class="card-body" id="professional-client-detail-history-body">
                    <h6 class="fw-bold mb-3" id="professional-client-detail-history-title">Appointment History</h6>
                    <?php if (empty($history)): ?>
                    <div class="text-muted" id="professional-client-detail-history-empty">No appointments have been recorded for this client yet.</div>
                    <?php else: ?>
                    <div class="table-responsive" id="professional-client-detail-history-wrap">
                        <table class="table table-hover align-middle mb-0" id="professional-client-detail-history-table">
                            <thead id="professional-client-detail-history-head">
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Service</th>
                                    <th>Status</th>
                                    <th>Code</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="professional-client-detail-history-body-rows">
                                <?php foreach ($history as $appointment): ?>
                                <tr id="professional-client-detail-history-row-<?php echo (int)$appointment['id']; ?>">
                                    <td id="professional-client-detail-history-date-<?php echo (int)$appointment['id']; ?>">
                                        <?php echo htmlspecialchars(date('M j, Y', strtotime($appointment['start_at']))); ?>
                                    </td>
                                    <td id="professional-client-detail-history-time-<?php echo (int)$appointment['id']; ?>">
                                        <?php echo htmlspecialchars(date('g:ia', strtotime($appointment['start_at']))); ?>
                                    </td>
                                    <td id="professional-client-detail-history-service-<?php echo (int)$appointment['id']; ?>">
                                        <span class="badge text-bg-light border" style="border-left: 3px solid <?php echo htmlspecialchars($appointment['service_color'] ?: '#6c757d'); ?> !important;">
                                            <?php echo htmlspecialchars($appointment['service_name']); ?>
                                        </span>
                                    </td>
                                    <td id="professional-client-detail-history-status-<?php echo (int)$appointment['id']; ?>">
                                        <span class="badge bg-<?php echo htmlspecialchars($statusColors[$appointment['status']] ?? 'secondary'); ?>">
                                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $appointment['status']))); ?>
                                        </span>
                                    </td>
                                    <td id="professional-client-detail-history-code-<?php echo (int)$appointment['id']; ?>">
                                        <?php echo !empty($appointment['confirmation_code']) ? '<code>' . htmlspecialchars($appointment['confirmation_code']) . '</code>' : '<span class="text-muted">-</span>'; ?>
                                    </td>
                                    <td class="text-end" id="professional-client-detail-history-actions-<?php echo (int)$appointment['id']; ?>">
                                        <button class="btn btn-outline-primary btn-sm"
                                                id="professional-client-detail-history-edit-btn-<?php echo (int)$appointment['id']; ?>"
                                                hx-get="/partials/professional/appointment-form.php?appointment_id=<?php echo (int)$appointment['id']; ?>"
                                                hx-target="#professional-modal-container"
                                                hx-swap="innerHTML">
                                            <i class="feather-edit-2"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Communications History -->
            <div class="card mt-4" id="professional-client-detail-comms-card">
                <div class="card-body" id="professional-client-detail-comms-body">
                    <h6 class="fw-bold mb-3" id="professional-client-detail-comms-title">
                        Communications History
                        <?php if (!empty($comms)): ?>
                        <span class="badge bg-secondary ms-1"><?php echo count($comms); ?></span>
                        <?php endif; ?>
                    </h6>
                    <?php if (empty($comms)): ?>
                    <div class="text-muted" id="professional-client-detail-comms-empty">No communications have been recorded for this client yet.</div>
                    <?php else: ?>
                    <div class="table-responsive" id="professional-client-detail-comms-wrap">
                        <table class="table table-hover align-middle mb-0" id="professional-client-detail-comms-table">
                            <thead id="professional-client-detail-comms-head">
                                <tr>
                                    <th id="professional-client-detail-comms-th-channel">Channel</th>
                                    <th id="professional-client-detail-comms-th-direction">Direction</th>
                                    <th id="professional-client-detail-comms-th-summary">Summary</th>
                                    <th id="professional-client-detail-comms-th-status">Status</th>
                                    <th id="professional-client-detail-comms-th-date">Date</th>
                                </tr>
                            </thead>
                            <tbody id="professional-client-detail-comms-rows">
                                <?php foreach ($comms as $ci => $comm): ?>
                                <tr id="professional-client-detail-comms-row-<?php echo $ci; ?>">
                                    <td id="professional-client-detail-comms-channel-<?php echo $ci; ?>">
                                        <span class="badge bg-<?php echo $channelColors[$comm['channel']] ?? 'secondary'; ?>">
                                            <i class="<?php echo $channelIcons[$comm['channel']] ?? 'feather-circle'; ?> me-1"></i>
                                            <?php echo ucfirst($comm['channel']); ?>
                                        </span>
                                    </td>
                                    <td id="professional-client-detail-comms-dir-<?php echo $ci; ?>">
                                        <?php if ($comm['direction'] === 'inbound'): ?>
                                        <span class="badge bg-success">Inbound</span>
                                        <?php else: ?>
                                        <span class="badge bg-info">Outbound</span>
                                        <?php endif; ?>
                                    </td>
                                    <td id="professional-client-detail-comms-summary-<?php echo $ci; ?>">
                                        <?php echo htmlspecialchars(mb_substr($comm['summary'], 0, 80)); ?><?php echo mb_strlen($comm['summary']) > 80 ? '...' : ''; ?>
                                    </td>
                                    <td id="professional-client-detail-comms-status-<?php echo $ci; ?>">
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($comm['status']); ?></span>
                                    </td>
                                    <td id="professional-client-detail-comms-date-<?php echo $ci; ?>" class="text-nowrap">
                                        <?php echo date('M j, g:ia', strtotime($comm['created_at'])); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
