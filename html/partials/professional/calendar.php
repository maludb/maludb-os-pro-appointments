<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();
requireManager();

function professionalCalendarBuildUrl(array $currentParams, array $overrides = []) {
    $params = array_merge($currentParams, $overrides);

    foreach ($params as $key => $value) {
        if (($key === 'status' || $key === 'service_id') && ($value === '' || $value === 0 || $value === '0')) {
            unset($params[$key]);
        }

        if ($key === 'search' && trim((string)$value) === '') {
            unset($params[$key]);
        }
    }

    return '/partials/professional/calendar.php?' . http_build_query($params);
}

function professionalCalendarNormalizePhoneSearch(string $value): string {
    return preg_replace('/\D+/', '', $value) ?? '';
}

function professionalCalendarStatusColor(string $status): string {
    $map = [
        'pending' => 'warning',
        'confirmed' => 'primary',
        'completed' => 'success',
        'cancelled' => 'dark',
        'no_show' => 'danger',
    ];

    return $map[$status] ?? 'secondary';
}

function professionalCalendarStatusBorder(string $status): string {
    $map = [
        'pending' => '#f59f00',
        'confirmed' => '#0d6efd',
        'completed' => '#198754',
        'cancelled' => '#495057',
        'no_show' => '#dc3545',
    ];

    return $map[$status] ?? '#6c757d';
}

function professionalCalendarTransitions(string $status): array {
    $map = [
        'pending' => ['confirmed', 'cancelled', 'no_show'],
        'confirmed' => ['completed', 'cancelled', 'no_show'],
        'completed' => [],
        'cancelled' => [],
        'no_show' => [],
    ];

    return $map[$status] ?? [];
}

function professionalCalendarTransitionMeta(string $status): array {
    $map = [
        'confirmed' => ['label' => 'Confirm', 'icon' => 'feather-check', 'class' => 'primary'],
        'completed' => ['label' => 'Complete', 'icon' => 'feather-check-circle', 'class' => 'success'],
        'cancelled' => ['label' => 'Cancel', 'icon' => 'feather-x-circle', 'class' => 'dark'],
        'no_show' => ['label' => 'No Show', 'icon' => 'feather-user-x', 'class' => 'danger'],
    ];

    return $map[$status] ?? ['label' => ucfirst(str_replace('_', ' ', $status)), 'icon' => 'feather-arrow-right', 'class' => 'secondary'];
}

function professionalRenderAppointmentCard(array $appointment, string $context): string {
    $appointmentId = (int)$appointment['id'];
    $status = (string)$appointment['status'];
    $clientName = trim(($appointment['first_name'] ?? '') . ' ' . ($appointment['last_name'] ?? ''));
    $serviceName = $appointment['service_name'] ?: 'Service';
    $statusClass = professionalCalendarStatusColor($status);
    $serviceColor = $appointment['service_color'] ?: professionalCalendarStatusBorder($status);
    $allowedTransitions = professionalCalendarTransitions($status);
    $canEdit = in_array($status, ['pending', 'confirmed'], true);
    $compactActions = $context === 'week';
    $csrfToken = htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8');

    ob_start();
    ?>
    <div class="card h-100 shadow-sm" id="professional-appointment-card-<?php echo htmlspecialchars($context); ?>-<?php echo $appointmentId; ?>" style="border-left: 4px solid <?php echo htmlspecialchars($serviceColor); ?>;">
        <div class="card-body py-2 px-3" id="professional-appointment-card-body-<?php echo htmlspecialchars($context); ?>-<?php echo $appointmentId; ?>">
            <div class="d-flex justify-content-between align-items-start gap-2" id="professional-appointment-card-head-<?php echo htmlspecialchars($context); ?>-<?php echo $appointmentId; ?>">
                <div id="professional-appointment-card-copy-<?php echo htmlspecialchars($context); ?>-<?php echo $appointmentId; ?>">
                    <div class="fw-semibold" id="professional-appointment-card-client-<?php echo htmlspecialchars($context); ?>-<?php echo $appointmentId; ?>">
                        <?php echo htmlspecialchars($clientName ?: 'Client'); ?>
                    </div>
                    <div class="small text-muted" id="professional-appointment-card-service-<?php echo htmlspecialchars($context); ?>-<?php echo $appointmentId; ?>">
                        <?php echo htmlspecialchars($serviceName); ?>
                    </div>
                </div>
                <span class="badge bg-<?php echo htmlspecialchars($statusClass); ?>" id="professional-appointment-card-status-<?php echo htmlspecialchars($context); ?>-<?php echo $appointmentId; ?>">
                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $status))); ?>
                </span>
            </div>

            <div class="small text-muted mt-2" id="professional-appointment-card-time-<?php echo htmlspecialchars($context); ?>-<?php echo $appointmentId; ?>">
                <i class="feather-clock me-1"></i><?php echo htmlspecialchars(date('g:ia', strtotime($appointment['start_at']))); ?>
                <span class="ms-1">to <?php echo htmlspecialchars(date('g:ia', strtotime($appointment['end_at']))); ?></span>
            </div>

            <div class="small text-muted mt-1" id="professional-appointment-card-contact-<?php echo htmlspecialchars($context); ?>-<?php echo $appointmentId; ?>">
                <?php if (!empty($appointment['client_phone'])): ?>
                <span id="professional-appointment-card-phone-<?php echo htmlspecialchars($context); ?>-<?php echo $appointmentId; ?>">
                    <i class="feather-phone me-1"></i><?php echo htmlspecialchars($appointment['client_phone']); ?>
                </span>
                <?php elseif (!empty($appointment['client_email'])): ?>
                <span id="professional-appointment-card-email-<?php echo htmlspecialchars($context); ?>-<?php echo $appointmentId; ?>">
                    <i class="feather-mail me-1"></i><?php echo htmlspecialchars($appointment['client_email']); ?>
                </span>
                <?php else: ?>
                <span id="professional-appointment-card-contact-empty-<?php echo htmlspecialchars($context); ?>-<?php echo $appointmentId; ?>">No contact details</span>
                <?php endif; ?>
            </div>

            <?php if (!empty($appointment['confirmation_code'])): ?>
            <div class="small text-muted mt-1" id="professional-appointment-card-code-<?php echo htmlspecialchars($context); ?>-<?php echo $appointmentId; ?>">
                Code: <code><?php echo htmlspecialchars($appointment['confirmation_code']); ?></code>
            </div>
            <?php endif; ?>

            <?php if ($canEdit || !empty($allowedTransitions)): ?>
            <div class="d-flex gap-1 mt-3<?php echo $compactActions ? ' flex-nowrap' : ' flex-wrap'; ?>" id="professional-appointment-card-actions-<?php echo htmlspecialchars($context); ?>-<?php echo $appointmentId; ?>">
                <?php if ($canEdit): ?>
                <button type="button" class="btn btn-outline-primary btn-sm<?php echo $compactActions ? ' px-2' : ''; ?>"
                        id="professional-appointment-card-edit-<?php echo htmlspecialchars($context); ?>-<?php echo $appointmentId; ?>"
                        hx-get="/partials/professional/appointment-form.php?appointment_id=<?php echo $appointmentId; ?>"
                        hx-target="#professional-modal-container"
                        hx-swap="innerHTML"
                        aria-label="Edit"
                        title="Edit appointment">
                    <i class="feather-edit-2<?php echo $compactActions ? '' : ' me-1'; ?>"></i><?php if (!$compactActions): ?>Edit<?php endif; ?>
                </button>
                <?php endif; ?>

                <?php foreach ($allowedTransitions as $nextStatus): ?>
                    <?php $transitionMeta = professionalCalendarTransitionMeta($nextStatus); ?>
                <button type="button" class="btn btn-outline-<?php echo htmlspecialchars($transitionMeta['class']); ?> btn-sm<?php echo $compactActions ? ' px-2' : ''; ?>"
                        id="professional-appointment-card-action-<?php echo htmlspecialchars($context); ?>-<?php echo $appointmentId; ?>-<?php echo htmlspecialchars($nextStatus); ?>"
                        hx-post="/partials/professional/update-appointment-status.php"
                        hx-vals='{"appointment_id": <?php echo $appointmentId; ?>, "new_status": "<?php echo htmlspecialchars($nextStatus, ENT_QUOTES, 'UTF-8'); ?>", "csrf_token": "<?php echo $csrfToken; ?>"}'
                        hx-target="#professional-calendar-messages"
                        hx-swap="innerHTML"
                        aria-label="<?php echo htmlspecialchars($transitionMeta['label']); ?>"
                        title="<?php echo htmlspecialchars($transitionMeta['label']); ?>"
                        hx-confirm="Change appointment status to <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $nextStatus))); ?>?">
                    <i class="<?php echo htmlspecialchars($transitionMeta['icon']); ?><?php echo $compactActions ? '' : ' me-1'; ?>"></i><?php if (!$compactActions): ?><?php echo htmlspecialchars($transitionMeta['label']); ?><?php endif; ?>
                </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php

    return (string)ob_get_clean();
}

$restaurantId = currentRestaurantId();

if (!$restaurantId) {
    echo '<div class="alert alert-danger" id="professional-calendar-no-restaurant">No professional account is currently selected.</div>';
    exit;
}

$allowedViews = ['day', 'week', 'month', 'upcoming'];
$view = trim($_GET['view'] ?? 'week');
if (!in_array($view, $allowedViews, true)) {
    $view = 'week';
}

$date = trim($_GET['date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$filterStatus = trim($_GET['status'] ?? '');
$filterServiceId = (int)($_GET['service_id'] ?? 0);
$appointmentSearch = trim((string)($_GET['search'] ?? ''));
$normalizedAppointmentSearch = professionalCalendarNormalizePhoneSearch($appointmentSearch);

if ($appointmentSearch !== '') {
    $view = 'upcoming';
}

$baseDate = new DateTimeImmutable($date . ' 00:00:00');
$rangeStart = $baseDate;
$rangeEnd = $baseDate->setTime(23, 59, 59);
$viewLabel = '';
$prevDate = $date;
$nextDate = $date;
$calendarGridStart = null;
$calendarGridEnd = null;
$weekDates = [];

switch ($view) {
    case 'day':
        $viewLabel = $baseDate->format('l, F j, Y');
        $prevDate = $baseDate->modify('-1 day')->format('Y-m-d');
        $nextDate = $baseDate->modify('+1 day')->format('Y-m-d');
        break;

    case 'week':
        $rangeStart = $baseDate->modify('monday this week')->setTime(0, 0, 0);
        $rangeEnd = $rangeStart->modify('+6 days')->setTime(23, 59, 59);
        $viewLabel = $rangeStart->format('M j') . ' - ' . $rangeEnd->format('M j, Y');
        $prevDate = $rangeStart->modify('-7 days')->format('Y-m-d');
        $nextDate = $rangeStart->modify('+7 days')->format('Y-m-d');
        for ($dayIndex = 0; $dayIndex < 7; $dayIndex++) {
            $weekDates[] = $rangeStart->modify('+' . $dayIndex . ' days');
        }
        break;

    case 'month':
        $monthStart = $baseDate->modify('first day of this month')->setTime(0, 0, 0);
        $monthEnd = $baseDate->modify('last day of this month')->setTime(23, 59, 59);
        $calendarGridStart = $monthStart->modify('monday this week')->setTime(0, 0, 0);
        $calendarGridEnd = $monthEnd->modify('sunday this week')->setTime(23, 59, 59);
        $rangeStart = $calendarGridStart;
        $rangeEnd = $calendarGridEnd;
        $viewLabel = $monthStart->format('F Y');
        $prevDate = $monthStart->modify('-1 month')->format('Y-m-d');
        $nextDate = $monthStart->modify('+1 month')->format('Y-m-d');
        break;

    case 'upcoming':
        if ($appointmentSearch !== '') {
            $rangeStart = new DateTimeImmutable('now');
            $rangeEnd = $rangeStart;
            $viewLabel = 'Upcoming matches for "' . $appointmentSearch . '"';
        } else {
            $rangeStart = $baseDate->setTime(0, 0, 0);
            $rangeEnd = $rangeStart->setTime(23, 59, 59);
            $viewLabel = 'Upcoming on ' . $rangeStart->format('M j, Y');
        }
        $prevDate = $rangeStart->modify('-1 day')->format('Y-m-d');
        $nextDate = $rangeStart->modify('+1 day')->format('Y-m-d');
        break;
}

$servicesStmt = db()->prepare(
    "SELECT id, name, color, is_active
     FROM professional_services
     WHERE restaurant_id = ?
     ORDER BY is_active DESC, sort_order ASC, name ASC"
);
$servicesStmt->execute([$restaurantId]);
$services = $servicesStmt->fetchAll(PDO::FETCH_ASSOC);

$sql = "
    SELECT
        a.*,
        c.first_name,
        c.last_name,
        c.email AS client_email,
        c.phone AS client_phone,
        s.color AS service_color,
        s.is_active AS service_is_active
    FROM professional_appointments a
    JOIN professional_clients c ON c.id = a.client_id
    LEFT JOIN professional_services s ON s.id = a.service_id AND s.restaurant_id = a.restaurant_id
    WHERE a.restaurant_id = ?
";
$params = [$restaurantId];

if ($appointmentSearch !== '') {
    $sql .= " AND a.start_at >= ?";
    $params[] = $rangeStart->format('Y-m-d H:i:s');
} else {
    $sql .= " AND a.start_at >= ? AND a.start_at <= ?";
    $params[] = $rangeStart->format('Y-m-d H:i:s');
    $params[] = $rangeEnd->format('Y-m-d H:i:s');
}

if ($filterStatus !== '') {
    $sql .= " AND a.status = ?";
    $params[] = $filterStatus;
}

if ($filterServiceId > 0) {
    $sql .= " AND a.service_id = ?";
    $params[] = $filterServiceId;
}

if ($appointmentSearch !== '') {
    $searchClauses = ["c.last_name LIKE ?"];
    $params[] = '%' . $appointmentSearch . '%';

    if ($normalizedAppointmentSearch !== '') {
        $searchClauses[] = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(c.phone, ''), ' ', ''), '-', ''), '(', ''), ')', ''), '+', ''), '.', ''), '/', '') LIKE ?";
        $params[] = '%' . $normalizedAppointmentSearch . '%';
    }

    $sql .= " AND (" . implode(' OR ', $searchClauses) . ")";
}

$sql .= " ORDER BY a.start_at ASC, a.created_at ASC";

$appointmentsStmt = db()->prepare($sql);
$appointmentsStmt->execute($params);
$appointments = $appointmentsStmt->fetchAll(PDO::FETCH_ASSOC);

$stats = [
    'total' => count($appointments),
    'confirmed' => 0,
    'pending' => 0,
    'completed' => 0,
    'issues' => 0,
];

$appointmentsByDate = [];
$appointmentsByTime = [];

foreach ($appointments as $appointment) {
    $appointmentDateKey = date('Y-m-d', strtotime($appointment['start_at']));
    $appointmentTimeKey = date('H:i', strtotime($appointment['start_at']));

    $appointmentsByDate[$appointmentDateKey][] = $appointment;
    $appointmentsByTime[$appointmentTimeKey][] = $appointment;

    if ($appointment['status'] === 'confirmed') {
        $stats['confirmed']++;
    } elseif ($appointment['status'] === 'pending') {
        $stats['pending']++;
    } elseif ($appointment['status'] === 'completed') {
        $stats['completed']++;
    } elseif (in_array($appointment['status'], ['cancelled', 'no_show'], true)) {
        $stats['issues']++;
    }
}

$currentParams = [
    'view' => $view,
    'date' => $baseDate->format('Y-m-d'),
    'status' => $filterStatus,
    'service_id' => $filterServiceId > 0 ? $filterServiceId : '',
    'search' => $appointmentSearch,
];
$selfUrl = professionalCalendarBuildUrl($currentParams);
$todayUrl = professionalCalendarBuildUrl($currentParams, ['date' => date('Y-m-d')]);
$newAppointmentUrl = '/partials/professional/appointment-form.php?date=' . urlencode($baseDate->format('Y-m-d'));
?>
<div class="main-content" id="professional-calendar-main"
     hx-get="<?php echo htmlspecialchars($selfUrl); ?>"
     hx-trigger="refreshProfessionalCalendar from:body"
     hx-target="#professional-calendar-main"
     hx-swap="outerHTML">
    <div class="row" id="professional-calendar-row">
        <div class="col-12" id="professional-calendar-col">
            <div class="card mb-3" id="professional-calendar-header-card">
                <div class="card-body" id="professional-calendar-header-body">
                    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3" id="professional-calendar-header-flex">
                        <div id="professional-calendar-header-copy">
                            <h5 class="mb-1" id="professional-calendar-title">
                                <i class="feather-calendar me-2"></i>Professional Calendar
                            </h5>
                            <p class="text-muted mb-0" id="professional-calendar-subtitle"><?php echo htmlspecialchars($viewLabel); ?></p>
                        </div>
                        <div class="d-flex flex-wrap gap-2" id="professional-calendar-header-actions">
                            <button type="button" class="btn btn-primary btn-sm" id="professional-calendar-new-btn"
                                    hx-get="<?php echo htmlspecialchars($newAppointmentUrl); ?>"
                                    hx-target="#professional-modal-container"
                                    hx-swap="innerHTML">
                                <i class="feather-plus me-1"></i> New Appointment
                            </button>
                            <a href="#" class="btn btn-outline-secondary btn-sm" id="professional-calendar-dashboard-btn"
                               hx-get="/partials/professional/dashboard.php"
                               hx-target="#page-content">
                                <i class="feather-home me-1"></i> Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3" id="professional-calendar-toolbar-card">
                <div class="card-body py-3" id="professional-calendar-toolbar-body">
                    <div class="d-flex flex-column gap-3" id="professional-calendar-toolbar-stack">
                        <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between" id="professional-calendar-nav-row">
                            <div class="btn-group" id="professional-calendar-nav-buttons" role="group" aria-label="Calendar navigation">
                                <a href="#" class="btn btn-outline-secondary btn-sm" id="professional-calendar-prev-btn"
                                   hx-get="<?php echo htmlspecialchars(professionalCalendarBuildUrl($currentParams, ['date' => $prevDate])); ?>"
                                   hx-target="#professional-calendar-main"
                                   hx-swap="outerHTML">
                                    <i class="feather-chevron-left"></i>
                                </a>
                                <input type="date" class="form-control form-control-sm" id="professional-calendar-date-picker"
                                       name="date"
                                       value="<?php echo htmlspecialchars($baseDate->format('Y-m-d')); ?>"
                                       hx-get="/partials/professional/calendar.php"
                                       hx-include="#professional-calendar-view-input, #professional-calendar-filter-status, #professional-calendar-filter-service, #professional-calendar-search-input"
                                       hx-target="#professional-calendar-main"
                                       hx-swap="outerHTML"
                                       hx-trigger="change"
                                       style="width: 165px;">
                                <a href="#" class="btn btn-outline-secondary btn-sm" id="professional-calendar-next-btn"
                                   hx-get="<?php echo htmlspecialchars(professionalCalendarBuildUrl($currentParams, ['date' => $nextDate])); ?>"
                                   hx-target="#professional-calendar-main"
                                   hx-swap="outerHTML">
                                    <i class="feather-chevron-right"></i>
                                </a>
                                <a href="#" class="btn btn-outline-secondary btn-sm" id="professional-calendar-today-btn"
                                   hx-get="<?php echo htmlspecialchars($todayUrl); ?>"
                                   hx-target="#professional-calendar-main"
                                   hx-swap="outerHTML">Today</a>
                            </div>

                            <div class="btn-group" id="professional-calendar-view-switcher" role="group" aria-label="Calendar views">
                                <?php foreach (['day' => 'Day', 'week' => 'Week', 'month' => 'Month', 'upcoming' => 'Upcoming'] as $viewKey => $viewName): ?>
                                <a href="#" class="btn btn-sm <?php echo $view === $viewKey ? 'btn-primary' : 'btn-outline-primary'; ?>"
                                   id="professional-calendar-view-<?php echo htmlspecialchars($viewKey); ?>"
                                   hx-get="<?php echo htmlspecialchars(professionalCalendarBuildUrl($currentParams, ['view' => $viewKey])); ?>"
                                   hx-target="#professional-calendar-main"
                                   hx-swap="outerHTML">
                                    <?php echo htmlspecialchars($viewName); ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="d-flex flex-column flex-lg-row gap-2 align-items-lg-center justify-content-between" id="professional-calendar-search-row">
                            <div class="flex-grow-1" id="professional-calendar-search-wrap">
                                <div class="input-group input-group-sm" id="professional-calendar-search-group">
                                    <span class="input-group-text" id="professional-calendar-search-icon">
                                        <i class="feather-search"></i>
                                    </span>
                                    <input type="text"
                                           class="form-control"
                                           id="professional-calendar-search-input"
                                           name="search"
                                           placeholder="Search by last name or phone"
                                           value="<?php echo htmlspecialchars($appointmentSearch); ?>"
                                           hx-get="/partials/professional/calendar.php"
                                           hx-include="#professional-calendar-view-input, #professional-calendar-date-picker, #professional-calendar-filter-status, #professional-calendar-filter-service"
                                           hx-target="#professional-calendar-main"
                                           hx-swap="outerHTML"
                                           hx-trigger="keyup changed delay:300ms, search"
                                           autocomplete="off">
                                </div>
                                <div class="small text-muted mt-1" id="professional-calendar-search-help">
                                    Search shows future appointments across all upcoming dates and ignores the date picker.
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 align-items-center" id="professional-calendar-filter-row">
                            <input type="hidden" id="professional-calendar-view-input" name="view" value="<?php echo htmlspecialchars($view); ?>">
                            <span class="small text-muted" id="professional-calendar-filter-label">Filters:</span>
                            <select class="form-select form-select-sm d-inline-block w-auto" id="professional-calendar-filter-status" name="status"
                                    hx-get="/partials/professional/calendar.php"
                                    hx-include="#professional-calendar-view-input, #professional-calendar-date-picker, #professional-calendar-filter-service, #professional-calendar-search-input"
                                    hx-target="#professional-calendar-main"
                                    hx-swap="outerHTML"
                                    hx-trigger="change">
                                <option value="">All Statuses</option>
                                <?php foreach (['pending' => 'Pending', 'confirmed' => 'Confirmed', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'no_show' => 'No Show'] as $statusValue => $statusLabel): ?>
                                <option value="<?php echo htmlspecialchars($statusValue); ?>" <?php echo $filterStatus === $statusValue ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($statusLabel); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <select class="form-select form-select-sm d-inline-block w-auto" id="professional-calendar-filter-service" name="service_id"
                                    hx-get="/partials/professional/calendar.php"
                                    hx-include="#professional-calendar-view-input, #professional-calendar-date-picker, #professional-calendar-filter-status, #professional-calendar-search-input"
                                    hx-target="#professional-calendar-main"
                                    hx-swap="outerHTML"
                                    hx-trigger="change">
                                <option value="0">All Services</option>
                                <?php foreach ($services as $service): ?>
                                <option value="<?php echo (int)$service['id']; ?>" <?php echo $filterServiceId === (int)$service['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($service['name']); ?><?php echo ((int)$service['is_active'] !== 1) ? ' (Inactive)' : ''; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div id="professional-calendar-messages"></div>

            <div class="row g-3 mb-3" id="professional-calendar-stats-row">
                <div class="col-md-6 col-xl-3" id="professional-calendar-stat-total-col">
                    <div class="card h-100" id="professional-calendar-stat-total-card">
                        <div class="card-body py-3 text-center" id="professional-calendar-stat-total-body">
                            <h4 class="mb-1" id="professional-calendar-stat-total-value"><?php echo (int)$stats['total']; ?></h4>
                            <div class="text-muted small" id="professional-calendar-stat-total-label">Appointments</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3" id="professional-calendar-stat-confirmed-col">
                    <div class="card h-100" id="professional-calendar-stat-confirmed-card">
                        <div class="card-body py-3 text-center" id="professional-calendar-stat-confirmed-body">
                            <h4 class="mb-1 text-primary" id="professional-calendar-stat-confirmed-value"><?php echo (int)$stats['confirmed']; ?></h4>
                            <div class="text-muted small" id="professional-calendar-stat-confirmed-label">Confirmed</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3" id="professional-calendar-stat-pending-col">
                    <div class="card h-100" id="professional-calendar-stat-pending-card">
                        <div class="card-body py-3 text-center" id="professional-calendar-stat-pending-body">
                            <h4 class="mb-1 text-warning" id="professional-calendar-stat-pending-value"><?php echo (int)$stats['pending']; ?></h4>
                            <div class="text-muted small" id="professional-calendar-stat-pending-label">Pending</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3" id="professional-calendar-stat-issues-col">
                    <div class="card h-100" id="professional-calendar-stat-issues-card">
                        <div class="card-body py-3 text-center" id="professional-calendar-stat-issues-body">
                            <h4 class="mb-1 text-danger" id="professional-calendar-stat-issues-value"><?php echo (int)$stats['issues']; ?></h4>
                            <div class="text-muted small" id="professional-calendar-stat-issues-label">Cancelled / No Show</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card" id="professional-calendar-content-card">
                <div class="card-body" id="professional-calendar-content-body">
                    <?php if ($view === 'day'): ?>
                        <?php if (empty($appointmentsByTime)): ?>
                        <div class="text-center text-muted py-5" id="professional-calendar-empty-day">
                            <i class="feather-calendar d-block mb-3" style="font-size: 2rem;"></i>
                            <p class="mb-0" id="professional-calendar-empty-day-copy">No appointments match this day and filter selection.</p>
                        </div>
                        <?php else: ?>
                            <?php foreach ($appointmentsByTime as $timeKey => $timeAppointments): ?>
                            <div class="mb-4" id="professional-calendar-slot-<?php echo htmlspecialchars(str_replace(':', '', $timeKey)); ?>">
                                <h6 class="fw-bold border-bottom pb-2" id="professional-calendar-slot-title-<?php echo htmlspecialchars(str_replace(':', '', $timeKey)); ?>">
                                    <?php echo htmlspecialchars(date('g:ia', strtotime($timeKey))); ?>
                                    <span class="badge bg-secondary ms-2" id="professional-calendar-slot-count-<?php echo htmlspecialchars(str_replace(':', '', $timeKey)); ?>"><?php echo count($timeAppointments); ?></span>
                                </h6>
                                <div class="row g-3" id="professional-calendar-slot-cards-<?php echo htmlspecialchars(str_replace(':', '', $timeKey)); ?>">
                                    <?php foreach ($timeAppointments as $appointment): ?>
                                    <div class="col-md-6 col-xl-4" id="professional-calendar-slot-card-col-<?php echo (int)$appointment['id']; ?>">
                                        <?php echo professionalRenderAppointmentCard($appointment, 'day'); ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php elseif ($view === 'week'): ?>
                        <div class="row g-3" id="professional-calendar-week-row">
                            <?php foreach ($weekDates as $weekDate): ?>
                                <?php $weekDateKey = $weekDate->format('Y-m-d'); ?>
                            <div class="col-12 col-md-6 col-xl" id="professional-calendar-week-col-<?php echo htmlspecialchars($weekDateKey); ?>">
                                <div class="card h-100 border-0 bg-light" id="professional-calendar-week-card-<?php echo htmlspecialchars($weekDateKey); ?>">
                                    <div class="card-body" id="professional-calendar-week-body-<?php echo htmlspecialchars($weekDateKey); ?>">
                                        <div class="d-flex justify-content-between align-items-center mb-3" id="professional-calendar-week-head-<?php echo htmlspecialchars($weekDateKey); ?>">
                                            <div id="professional-calendar-week-head-copy-<?php echo htmlspecialchars($weekDateKey); ?>">
                                                <div class="fw-semibold" id="professional-calendar-week-day-<?php echo htmlspecialchars($weekDateKey); ?>"><?php echo htmlspecialchars($weekDate->format('D')); ?></div>
                                                <div class="small text-muted" id="professional-calendar-week-date-<?php echo htmlspecialchars($weekDateKey); ?>"><?php echo htmlspecialchars($weekDate->format('M j')); ?></div>
                                            </div>
                                            <button type="button" class="btn btn-outline-primary btn-sm" id="professional-calendar-week-add-<?php echo htmlspecialchars($weekDateKey); ?>"
                                                    hx-get="/partials/professional/appointment-form.php?date=<?php echo urlencode($weekDateKey); ?>"
                                                    hx-target="#professional-modal-container"
                                                    hx-swap="innerHTML">
                                                <i class="feather-plus"></i>
                                            </button>
                                        </div>

                                        <?php if (empty($appointmentsByDate[$weekDateKey])): ?>
                                        <div class="text-muted small py-4 text-center" id="professional-calendar-week-empty-<?php echo htmlspecialchars($weekDateKey); ?>">No appointments</div>
                                        <?php else: ?>
                                        <div class="d-grid gap-2" id="professional-calendar-week-list-<?php echo htmlspecialchars($weekDateKey); ?>">
                                            <?php foreach ($appointmentsByDate[$weekDateKey] as $appointment): ?>
                                            <div id="professional-calendar-week-item-<?php echo (int)$appointment['id']; ?>">
                                                <?php echo professionalRenderAppointmentCard($appointment, 'week'); ?>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($view === 'month'): ?>
                        <div class="table-responsive" id="professional-calendar-month-wrap">
                            <table class="table table-bordered align-top mb-0" id="professional-calendar-month-table">
                                <thead id="professional-calendar-month-head">
                                    <tr>
                                        <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekdayIndex => $weekdayLabel): ?>
                                        <th class="text-center bg-light" id="professional-calendar-month-header-<?php echo $weekdayIndex; ?>"><?php echo htmlspecialchars($weekdayLabel); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody id="professional-calendar-month-body">
                                    <?php
                                    $dayCursor = $calendarGridStart;
                                    while ($dayCursor <= $calendarGridEnd):
                                    ?>
                                    <tr id="professional-calendar-month-row-<?php echo htmlspecialchars($dayCursor->format('Ymd')); ?>">
                                        <?php for ($monthColumn = 0; $monthColumn < 7; $monthColumn++): ?>
                                            <?php $dayKey = $dayCursor->format('Y-m-d'); ?>
                                            <?php $isCurrentMonth = $dayCursor->format('m') === $baseDate->format('m'); ?>
                                        <td id="professional-calendar-month-cell-<?php echo htmlspecialchars($dayKey); ?>" class="<?php echo $isCurrentMonth ? '' : 'bg-light'; ?>" style="width: 14.28%; min-width: 150px; height: 140px;">
                                            <div class="d-flex justify-content-between align-items-center mb-2" id="professional-calendar-month-cell-head-<?php echo htmlspecialchars($dayKey); ?>">
                                                <a href="#" class="fw-semibold text-decoration-none" id="professional-calendar-month-cell-day-<?php echo htmlspecialchars($dayKey); ?>"
                                                   hx-get="<?php echo htmlspecialchars(professionalCalendarBuildUrl($currentParams, ['view' => 'day', 'date' => $dayKey])); ?>"
                                                   hx-target="#professional-calendar-main"
                                                   hx-swap="outerHTML">
                                                    <?php echo htmlspecialchars($dayCursor->format('j')); ?>
                                                </a>
                                                <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2" id="professional-calendar-month-cell-add-<?php echo htmlspecialchars($dayKey); ?>"
                                                        hx-get="/partials/professional/appointment-form.php?date=<?php echo urlencode($dayKey); ?>"
                                                        hx-target="#professional-modal-container"
                                                        hx-swap="innerHTML">+</button>
                                            </div>
                                            <div class="d-grid gap-1" id="professional-calendar-month-cell-list-<?php echo htmlspecialchars($dayKey); ?>">
                                                <?php if (!empty($appointmentsByDate[$dayKey])): ?>
                                                    <?php foreach (array_slice($appointmentsByDate[$dayKey], 0, 3) as $appointment): ?>
                                                    <button type="button" class="btn btn-sm btn-light text-start border"
                                                            id="professional-calendar-month-appointment-<?php echo (int)$appointment['id']; ?>"
                                                            hx-get="/partials/professional/appointment-form.php?appointment_id=<?php echo (int)$appointment['id']; ?>"
                                                            hx-target="#professional-modal-container"
                                                            hx-swap="innerHTML"
                                                            style="border-left: 3px solid <?php echo htmlspecialchars($appointment['service_color'] ?: professionalCalendarStatusBorder($appointment['status'])); ?> !important;">
                                                        <span class="d-block small fw-semibold"><?php echo htmlspecialchars(date('g:ia', strtotime($appointment['start_at']))); ?></span>
                                                        <span class="d-block small text-muted"><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></span>
                                                    </button>
                                                    <?php endforeach; ?>
                                                    <?php if (count($appointmentsByDate[$dayKey]) > 3): ?>
                                                    <a href="#" class="small text-muted text-decoration-none" id="professional-calendar-month-more-<?php echo htmlspecialchars($dayKey); ?>"
                                                       hx-get="<?php echo htmlspecialchars(professionalCalendarBuildUrl($currentParams, ['view' => 'day', 'date' => $dayKey])); ?>"
                                                       hx-target="#professional-calendar-main"
                                                       hx-swap="outerHTML">
                                                        +<?php echo count($appointmentsByDate[$dayKey]) - 3; ?> more
                                                    </a>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                            <?php $dayCursor = $dayCursor->modify('+1 day'); ?>
                                        <?php endfor; ?>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <?php if (empty($appointments)): ?>
                        <div class="text-center text-muted py-5" id="professional-calendar-empty-upcoming">
                            <i class="feather-clock d-block mb-3" style="font-size: 2rem;"></i>
                            <p class="mb-0" id="professional-calendar-empty-upcoming-copy">
                                <?php echo $appointmentSearch !== '' ? 'No upcoming appointments match this search and filter selection.' : 'No upcoming appointments match this filter selection.'; ?>
                            </p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive" id="professional-calendar-upcoming-wrap">
                            <table class="table align-middle mb-0" id="professional-calendar-upcoming-table">
                                <thead class="bg-light" id="professional-calendar-upcoming-head">
                                    <tr>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Client</th>
                                        <th>Service</th>
                                        <th>Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="professional-calendar-upcoming-body">
                                    <?php foreach ($appointments as $appointment): ?>
                                        <?php
                                        $appointmentId = (int)$appointment['id'];
                                        $statusClass = professionalCalendarStatusColor($appointment['status']);
                                        $canEdit = in_array($appointment['status'], ['pending', 'confirmed'], true);
                                        $allowedTransitions = professionalCalendarTransitions($appointment['status']);
                                        $serviceColor = $appointment['service_color'] ?: professionalCalendarStatusBorder($appointment['status']);
                                        ?>
                                    <tr id="professional-calendar-upcoming-row-<?php echo $appointmentId; ?>">
                                        <td id="professional-calendar-upcoming-date-<?php echo $appointmentId; ?>"><?php echo htmlspecialchars(date('D, M j, Y', strtotime($appointment['start_at']))); ?></td>
                                        <td id="professional-calendar-upcoming-time-<?php echo $appointmentId; ?>"><?php echo htmlspecialchars(date('g:ia', strtotime($appointment['start_at']))); ?></td>
                                        <td id="professional-calendar-upcoming-client-<?php echo $appointmentId; ?>">
                                            <div class="fw-semibold" id="professional-calendar-upcoming-client-name-<?php echo $appointmentId; ?>"><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></div>
                                            <div class="small text-muted" id="professional-calendar-upcoming-client-contact-<?php echo $appointmentId; ?>"><?php echo htmlspecialchars($appointment['client_phone'] ?: ($appointment['client_email'] ?: 'No contact')); ?></div>
                                        </td>
                                        <td id="professional-calendar-upcoming-service-<?php echo $appointmentId; ?>">
                                            <span class="badge text-bg-light border" id="professional-calendar-upcoming-service-badge-<?php echo $appointmentId; ?>" style="border-left: 3px solid <?php echo htmlspecialchars($serviceColor); ?> !important;">
                                                <?php echo htmlspecialchars($appointment['service_name']); ?>
                                            </span>
                                        </td>
                                        <td id="professional-calendar-upcoming-status-<?php echo $appointmentId; ?>">
                                            <span class="badge bg-<?php echo htmlspecialchars($statusClass); ?>" id="professional-calendar-upcoming-status-badge-<?php echo $appointmentId; ?>">
                                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $appointment['status']))); ?>
                                            </span>
                                        </td>
                                        <td class="text-end" id="professional-calendar-upcoming-actions-<?php echo $appointmentId; ?>">
                                            <div class="d-inline-flex flex-wrap justify-content-end gap-1" id="professional-calendar-upcoming-actions-wrap-<?php echo $appointmentId; ?>">
                                                <?php if ($canEdit): ?>
                                                <button type="button" class="btn btn-outline-primary btn-sm"
                                                        id="professional-calendar-upcoming-edit-<?php echo $appointmentId; ?>"
                                                        hx-get="/partials/professional/appointment-form.php?appointment_id=<?php echo $appointmentId; ?>"
                                                        hx-target="#professional-modal-container"
                                                        hx-swap="innerHTML">
                                                    <i class="feather-edit-2"></i>
                                                </button>
                                                <?php endif; ?>
                                                <?php foreach ($allowedTransitions as $nextStatus): ?>
                                                    <?php $transitionMeta = professionalCalendarTransitionMeta($nextStatus); ?>
                                                <button type="button" class="btn btn-outline-<?php echo htmlspecialchars($transitionMeta['class']); ?> btn-sm"
                                                        id="professional-calendar-upcoming-action-<?php echo $appointmentId; ?>-<?php echo htmlspecialchars($nextStatus); ?>"
                                                        hx-post="/partials/professional/update-appointment-status.php"
                                                        hx-vals='{"appointment_id": <?php echo $appointmentId; ?>, "new_status": "<?php echo htmlspecialchars($nextStatus, ENT_QUOTES, 'UTF-8'); ?>", "csrf_token": "<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>"}'
                                                        hx-target="#professional-calendar-messages"
                                                        hx-swap="innerHTML"
                                                        hx-confirm="Change appointment status to <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $nextStatus))); ?>?">
                                                    <i class="<?php echo htmlspecialchars($transitionMeta['icon']); ?>"></i>
                                                </button>
                                                <?php endforeach; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
