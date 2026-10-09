<?php
/**
 * Professional Dashboard
 *
 * Dashboard plus remaining placeholder views for professional mode.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/professional-availability.php';

requireAuth();

$businessName = $_SESSION['current_restaurant_name'] ?? 'Professional Business';
$todayLabel = date('l, F j, Y');
$view = $_GET['view'] ?? 'dashboard';
$restaurantId = currentRestaurantId();
$professionalProfile = $restaurantId ? getProfessionalProfile($restaurantId) : null;
$publicBookingUrl = (
    $professionalProfile
    && (int)($professionalProfile['id'] ?? 0) > 0
    && !empty($professionalProfile['booking_slug'])
) ? '/pro-booking/index.php?professional=' . urlencode($professionalProfile['booking_slug']) : '';

$viewConfig = [
    'dashboard' => [
        'title' => 'Professional Dashboard',
        'subtitle' => 'Your scheduling workspace is now active in professional mode.',
        'icon' => 'feather-home',
        'phase' => 'Step 2 shell ready',
    ],
    'calendar' => [
        'title' => 'Calendar',
        'subtitle' => 'Use the main calendar screen to manage day, week, month, and upcoming appointment views.',
        'icon' => 'feather-calendar',
        'phase' => 'Live in navigation',
    ],
    'appointments' => [
        'title' => 'Appointments',
        'subtitle' => 'Use the appointment calendar screen to create, edit, reschedule, and update appointments.',
        'icon' => 'feather-clock',
        'phase' => 'Live in navigation',
    ],
    'clients' => [
        'title' => 'Clients',
        'subtitle' => 'Use the client directory to manage contact data, notes, and appointment history.',
        'icon' => 'feather-users',
        'phase' => 'Live in navigation',
    ],
    'services' => [
        'title' => 'Services',
        'subtitle' => 'This area will manage bookable services, duration, price, and buffers.',
        'icon' => 'feather-briefcase',
        'phase' => 'Planned for services CRUD step',
    ],
    'availability' => [
        'title' => 'Availability',
        'subtitle' => 'This area will manage weekly working hours and scheduling rules.',
        'icon' => 'feather-clock',
        'phase' => 'Planned for availability step',
    ],
    'time_off' => [
        'title' => 'Time Off',
        'subtitle' => 'This area will manage vacations, holidays, and manual blocked time.',
        'icon' => 'feather-moon',
        'phase' => 'Planned for time-off step',
    ],
    'settings' => [
        'title' => 'Professional Settings',
        'subtitle' => 'This area will manage the business profile, booking slug, and booking rules.',
        'icon' => 'feather-settings',
        'phase' => 'Planned for professional profile/settings step',
    ],
    'reports' => [
        'title' => 'Reports',
        'subtitle' => 'Use the reports screen to review appointment volume, revenue, client activity, and utilization.',
        'icon' => 'feather-bar-chart-2',
        'phase' => 'Live in navigation',
    ],
];

if (!isset($viewConfig[$view])) {
    $view = 'dashboard';
}

$currentView = $viewConfig[$view];
$isDashboardView = ($view === 'dashboard');

// Check restaurant status for setup checklist
$currentRestaurantStatus = 'active';
if ($restaurantId) {
    $statusStmt = db()->prepare("SELECT status FROM restaurants WHERE id = ?");
    $statusStmt->execute([$restaurantId]);
    $statusRow = $statusStmt->fetch(PDO::FETCH_ASSOC);
    if ($statusRow && !empty($statusRow['status'])) {
        $currentRestaurantStatus = $statusRow['status'];
    }
}

$isInSetup = ($currentRestaurantStatus === 'in-setup');

// --- Billing cards: show if user is billing_user OR affiliate for this restaurant ---
$pdo = db();
$userId = currentUserId();
$showBillingCards = false;
$billingUser = 0;
$prepayBalance = 0.00;
$unpaidInvoiceCount = 0;
$unpaidInvoiceTotal = 0.00;
$totalInvoiceCount = 0;

try {
    $billingStmt = $pdo->prepare("SELECT billing_user, prepay_balance FROM restaurants WHERE id = ?");
    $billingStmt->execute([$restaurantId]);
    $billingRow = $billingStmt->fetch();
    if ($billingRow) {
        $billingUser = (int)$billingRow['billing_user'];
        $prepayBalance = (float)($billingRow['prepay_balance'] ?? 0);
    }
} catch (Exception $e) {
    $billingStmt = $pdo->prepare("SELECT billing_user FROM restaurants WHERE id = ?");
    $billingStmt->execute([$restaurantId]);
    $billingRow = $billingStmt->fetch();
    if ($billingRow) {
        $billingUser = (int)$billingRow['billing_user'];
    }
}

$isAffiliateForRestaurant = false;
$affCheckStmt = $pdo->prepare("SELECT id FROM affiliates WHERE user_id = ?");
$affCheckStmt->execute([$userId]);
$affRow = $affCheckStmt->fetch();
if ($affRow) {
    $affRestStmt = $pdo->prepare("SELECT id FROM restaurants WHERE id = ? AND affiliate_id = ?");
    $affRestStmt->execute([$restaurantId, $userId]);
    $isAffiliateForRestaurant = (bool)$affRestStmt->fetch();
}

if ($billingUser === $userId || $isAffiliateForRestaurant) {
    $showBillingCards = true;
    try {
        $invStmt = $pdo->prepare(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total
             FROM invoices WHERE restaurant_id = ? AND status IN ('unpaid', 'overdue')"
        );
        $invStmt->execute([$restaurantId]);
        $invRow = $invStmt->fetch();
        $unpaidInvoiceCount = (int)$invRow['cnt'];
        $unpaidInvoiceTotal = (float)$invRow['total'];

        $totalInvStmt = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE restaurant_id = ?");
        $totalInvStmt->execute([$restaurantId]);
        $totalInvoiceCount = (int)$totalInvStmt->fetchColumn();
    } catch (Exception $e) {
        // invoices table may not exist yet
    }
}

$billingUser_obj = get_user();
$lastLogin = $billingUser_obj['last_login_at'] ?? null;

// Build setup checklist when in-setup
$setupChecklist = [];
if ($isInSetup && $restaurantId) {
    $pdo = db();

    // 1. Setup Company Profile — check name, phone, email in restaurants
    $s = $pdo->prepare("SELECT COUNT(*) FROM restaurants WHERE id = ? AND name IS NOT NULL AND name != '' AND phone IS NOT NULL AND phone != '' AND email IS NOT NULL AND email != ''");
    $s->execute([$restaurantId]);
    $setupChecklist[] = ['label' => 'Setup Company Profile', 'done' => (int)$s->fetchColumn() > 0, 'icon' => 'feather-briefcase', 'link' => '/partials/professional/settings.php'];

    // 2. Setup Phone Numbers
    $s = $pdo->prepare("SELECT COUNT(*) FROM restaurant_phone_numbers WHERE restaurant_id = ? AND is_active = 1");
    $s->execute([$restaurantId]);
    $setupChecklist[] = ['label' => 'Setup Phone Numbers', 'done' => (int)$s->fetchColumn() > 0, 'icon' => 'feather-phone', 'link' => '/partials/settings/phone-numbers.php'];

    // 3. Setup Email
    $s = $pdo->prepare("SELECT COUNT(*) FROM email_agent_prompts WHERE restaurant_id = ? AND email_address IS NOT NULL AND email_address != ''");
    $s->execute([$restaurantId]);
    $setupChecklist[] = ['label' => 'Setup Email', 'done' => (int)$s->fetchColumn() > 0, 'icon' => 'feather-mail', 'link' => '/partials/settings/email-prompts.php'];

    // 4. Add Agents
    $s = $pdo->prepare("SELECT COUNT(*) FROM restaurant_prompts WHERE restaurant_id = ? AND agent_id IS NOT NULL AND agent_id != ''");
    $s->execute([$restaurantId]);
    $setupChecklist[] = ['label' => 'Add Agents', 'done' => (int)$s->fetchColumn() > 0, 'icon' => 'feather-cpu', 'link' => '/partials/settings/prompts.php'];

    // 5. Add Services
    $s = $pdo->prepare("SELECT COUNT(*) FROM professional_services WHERE restaurant_id = ? AND is_active = 1");
    $s->execute([$restaurantId]);
    $setupChecklist[] = ['label' => 'Add Services', 'done' => (int)$s->fetchColumn() > 0, 'icon' => 'feather-clipboard', 'link' => '/partials/professional/services.php'];

    // 6. Setup Availability
    $s = $pdo->prepare("SELECT COUNT(*) FROM professional_availability_rules WHERE restaurant_id = ? AND is_active = 1");
    $s->execute([$restaurantId]);
    $setupChecklist[] = ['label' => 'Setup Availability', 'done' => (int)$s->fetchColumn() > 0, 'icon' => 'feather-clock', 'link' => '/partials/professional/availability.php'];

    // 7. Invite Users (at least 2 users = creator + invited)
    $s = $pdo->prepare("SELECT COUNT(*) FROM user_restaurants WHERE restaurant_id = ? AND is_active = 1");
    $s->execute([$restaurantId]);
    $setupChecklist[] = ['label' => 'Invite Users', 'done' => (int)$s->fetchColumn() >= 2, 'icon' => 'feather-user-plus', 'link' => '/partials/settings/users.php'];
}
?>

<div id="professional-dashboard-main" class="p-4">
    <div id="professional-dashboard-header" class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div id="professional-dashboard-header-copy">
            <h4 class="mb-1" id="professional-dashboard-title">
                <i class="<?php echo htmlspecialchars($currentView['icon']); ?> me-2"></i><?php echo htmlspecialchars($currentView['title']); ?>
            </h4>
            <p class="text-muted mb-1" id="professional-dashboard-subtitle">
                <?php echo htmlspecialchars($businessName); ?> &middot; <?php echo htmlspecialchars($todayLabel); ?>
            </p>
            <small class="text-muted" id="professional-dashboard-phase"><?php echo htmlspecialchars($currentView['phase']); ?></small>
        </div>
        <div id="professional-dashboard-header-actions" class="d-flex flex-wrap gap-2">
            <a href="#" class="btn btn-primary" id="professional-dashboard-action-appointments"
               hx-get="/partials/professional/appointment-form.php?date=<?php echo urlencode(date('Y-m-d')); ?>"
               hx-target="#professional-modal-container"
               hx-swap="innerHTML">
                <i class="feather-plus me-1"></i> New Appointment
            </a>
            <a href="#" class="btn btn-outline-primary" id="professional-dashboard-action-clients"
               hx-get="/partials/professional/client-form.php"
               hx-target="#professional-modal-container"
               hx-swap="innerHTML">
                <i class="feather-user-plus me-1"></i> New Client
            </a>
            <a href="#" class="btn btn-outline-secondary" id="professional-dashboard-action-time-off"
               hx-get="/partials/professional/time-off.php"
               hx-target="#page-content">
                <i class="feather-slash me-1"></i> Block Time
            </a>
            <?php if ($publicBookingUrl !== ''): ?>
            <a href="<?php echo htmlspecialchars($publicBookingUrl); ?>" class="btn btn-outline-success" id="professional-dashboard-action-booking-page" target="_blank" rel="noopener">
                <i class="feather-external-link me-1"></i> View Booking Page
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($showBillingCards): ?>
    <!-- Billing Cards -->
    <div class="row g-3 mb-4" id="pro-dashboard-billing-row">
        <div class="col-sm-6 col-xl-3" id="pro-dashboard-billing-welcome">
            <div class="card border-0 shadow-sm h-100" id="pro-dashboard-billing-welcome-card">
                <div class="card-body d-flex align-items-center" id="pro-dashboard-billing-welcome-body">
                    <div class="avatar-md bg-primary bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center me-3" id="pro-dashboard-billing-welcome-icon">
                        <i class="feather-user text-primary" style="font-size:24px;"></i>
                    </div>
                    <div id="pro-dashboard-billing-welcome-text">
                        <h5 class="mb-1 fw-bold" id="pro-dashboard-billing-welcome-name">Welcome, <?php echo htmlspecialchars($billingUser_obj['first_name']); ?>!</h5>
                        <small class="text-muted" id="pro-dashboard-billing-welcome-login">
                            <?php if ($lastLogin): ?>
                                Last login: <?php echo date('M j, Y g:ia', strtotime($lastLogin)); ?>
                            <?php else: ?>
                                First visit
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3" id="pro-dashboard-billing-prepay">
            <div class="card border-0 shadow-sm h-100" id="pro-dashboard-billing-prepay-card">
                <div class="card-body d-flex align-items-center" id="pro-dashboard-billing-prepay-body">
                    <div class="avatar-md bg-success bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center me-3" id="pro-dashboard-billing-prepay-icon">
                        <i class="feather-dollar-sign text-success" style="font-size:24px;"></i>
                    </div>
                    <div id="pro-dashboard-billing-prepay-text">
                        <h3 class="mb-0 fw-bold" id="pro-dashboard-billing-prepay-amount">$<?php echo number_format($prepayBalance, 2); ?></h3>
                        <small class="text-muted" id="pro-dashboard-billing-prepay-label">Prepay Balance</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3" id="pro-dashboard-billing-unpaid">
            <div class="card border-0 shadow-sm h-100" id="pro-dashboard-billing-unpaid-card">
                <div class="card-body d-flex align-items-center" id="pro-dashboard-billing-unpaid-body">
                    <div class="avatar-md <?php echo $unpaidInvoiceCount > 0 ? 'bg-danger' : 'bg-info'; ?> bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center me-3" id="pro-dashboard-billing-unpaid-icon">
                        <i class="feather-alert-circle <?php echo $unpaidInvoiceCount > 0 ? 'text-danger' : 'text-info'; ?>" style="font-size:24px;"></i>
                    </div>
                    <div id="pro-dashboard-billing-unpaid-text">
                        <h3 class="mb-0 fw-bold" id="pro-dashboard-billing-unpaid-count"><?php echo $unpaidInvoiceCount; ?></h3>
                        <small class="text-muted" id="pro-dashboard-billing-unpaid-label">
                            Unpaid Invoice<?php echo $unpaidInvoiceCount !== 1 ? 's' : ''; ?>
                            <?php if ($unpaidInvoiceTotal > 0): ?>
                                ($<?php echo number_format($unpaidInvoiceTotal, 2); ?>)
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3" id="pro-dashboard-billing-invoices">
            <div class="card border-0 shadow-sm h-100" id="pro-dashboard-billing-invoices-card">
                <div class="card-body d-flex align-items-center" id="pro-dashboard-billing-invoices-body">
                    <div class="avatar-md bg-secondary bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center me-3" id="pro-dashboard-billing-invoices-icon">
                        <i class="feather-file-text text-secondary" style="font-size:24px;"></i>
                    </div>
                    <div id="pro-dashboard-billing-invoices-text">
                        <h3 class="mb-0 fw-bold" id="pro-dashboard-billing-invoices-count"><?php echo $totalInvoiceCount; ?></h3>
                        <small class="text-muted" id="pro-dashboard-billing-invoices-label">Invoice History</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($isDashboardView && $isInSetup): ?>
    <!-- Setup Checklist -->
    <div id="professional-dashboard-setup-checklist-row" class="row g-3 mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm" id="professional-dashboard-setup-card">
                <div class="card-body" id="professional-dashboard-setup-body">
                    <h5 class="mb-1" id="professional-dashboard-setup-title"><i class="feather-check-square me-2"></i>Setup Checklist</h5>
                    <p class="text-muted mb-4" id="professional-dashboard-setup-subtitle">Complete the following steps to get your company up and running.</p>
                    <div class="row g-3" id="professional-dashboard-setup-items">
                        <?php foreach ($setupChecklist as $ci => $item): ?>
                        <div class="col-md-6 col-xl-4" id="professional-dashboard-setup-item-<?php echo $ci; ?>">
                            <a href="#" class="text-decoration-none"
                               hx-get="<?php echo htmlspecialchars($item['link']); ?>"
                               hx-target="#page-content"
                               id="professional-dashboard-setup-link-<?php echo $ci; ?>">
                                <div class="d-flex align-items-center p-3 rounded border <?php echo $item['done'] ? 'border-success bg-success-subtle' : 'border-warning bg-warning-subtle'; ?>"
                                     id="professional-dashboard-setup-box-<?php echo $ci; ?>">
                                    <div class="me-3" id="professional-dashboard-setup-icon-<?php echo $ci; ?>">
                                        <?php if ($item['done']): ?>
                                        <i class="feather-check-circle text-success" style="font-size: 1.5rem;"></i>
                                        <?php else: ?>
                                        <i class="feather-circle text-warning" style="font-size: 1.5rem;"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div id="professional-dashboard-setup-label-<?php echo $ci; ?>">
                                        <h6 class="mb-0 <?php echo $item['done'] ? 'text-success' : 'text-dark'; ?>">
                                            <i class="<?php echo htmlspecialchars($item['icon']); ?> me-1"></i>
                                            <?php echo htmlspecialchars($item['label']); ?>
                                        </h6>
                                        <small class="<?php echo $item['done'] ? 'text-success' : 'text-muted'; ?>">
                                            <?php echo $item['done'] ? 'Complete' : 'Not started'; ?>
                                        </small>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php
                    $doneCount = count(array_filter($setupChecklist, fn($i) => $i['done']));
                    $totalCount = count($setupChecklist);
                    $pct = $totalCount > 0 ? round(($doneCount / $totalCount) * 100) : 0;
                    ?>
                    <div class="mt-4" id="professional-dashboard-setup-progress-wrap">
                        <div class="d-flex justify-content-between mb-1" id="professional-dashboard-setup-progress-labels">
                            <small class="fw-bold" id="professional-dashboard-setup-progress-text"><?php echo $doneCount; ?> of <?php echo $totalCount; ?> complete</small>
                            <small class="fw-bold" id="professional-dashboard-setup-progress-pct"><?php echo $pct; ?>%</small>
                        </div>
                        <div class="progress" style="height: 8px;" id="professional-dashboard-setup-progress-bar">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $pct; ?>%;" id="professional-dashboard-setup-progress-fill"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php elseif ($isDashboardView): ?>
    <div id="professional-dashboard-stats-row" class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3" id="professional-dashboard-stat-today-col">
            <div class="card border-0 shadow-sm h-100" id="professional-dashboard-stat-today-card">
                <div class="card-body" id="professional-dashboard-stat-today-body">
                    <h6 class="text-muted mb-2" id="professional-dashboard-stat-today-label">Today</h6>
                    <h3 class="mb-1" id="professional-dashboard-stat-today-value">0</h3>
                    <p class="mb-0 text-muted" id="professional-dashboard-stat-today-copy">Use the calendar to manage today's appointments.</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3" id="professional-dashboard-stat-upcoming-col">
            <div class="card border-0 shadow-sm h-100" id="professional-dashboard-stat-upcoming-card">
                <div class="card-body" id="professional-dashboard-stat-upcoming-body">
                    <h6 class="text-muted mb-2" id="professional-dashboard-stat-upcoming-label">Upcoming</h6>
                    <h3 class="mb-1" id="professional-dashboard-stat-upcoming-value">0</h3>
                    <p class="mb-0 text-muted" id="professional-dashboard-stat-upcoming-copy">The next client visits will be surfaced here.</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3" id="professional-dashboard-stat-availability-col">
            <div class="card border-0 shadow-sm h-100" id="professional-dashboard-stat-availability-card">
                <div class="card-body" id="professional-dashboard-stat-availability-body">
                    <h6 class="text-muted mb-2" id="professional-dashboard-stat-availability-label">Availability</h6>
                    <h3 class="mb-1" id="professional-dashboard-stat-availability-value">Active</h3>
                    <p class="mb-0 text-muted" id="professional-dashboard-stat-availability-copy">Availability rules, blocked time, and slot checks are now active.</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3" id="professional-dashboard-stat-build-col">
            <div class="card border-0 shadow-sm h-100" id="professional-dashboard-stat-build-card">
                <div class="card-body" id="professional-dashboard-stat-build-body">
                    <h6 class="text-muted mb-2" id="professional-dashboard-stat-build-label">Build Status</h6>
                    <h3 class="mb-1" id="professional-dashboard-stat-build-value">Complete</h3>
                    <p class="mb-0 text-muted" id="professional-dashboard-stat-build-copy">Professional mode now includes reporting for appointments, revenue, client activity, cancellations, and utilization.</p>
                </div>
            </div>
        </div>
    </div>

    <div id="professional-dashboard-content-row" class="row g-3">
        <div class="col-lg-8" id="professional-dashboard-overview-col">
            <div class="card border-0 shadow-sm h-100" id="professional-dashboard-overview-card">
                <div class="card-body" id="professional-dashboard-overview-body">
                    <h5 class="mb-3" id="professional-dashboard-overview-title">Professional mode is now active</h5>
                    <p class="text-muted mb-3" id="professional-dashboard-overview-copy">
                        <?php echo htmlspecialchars($currentView['subtitle']); ?>
                    </p>
                    <p class="mb-0 text-muted" id="professional-dashboard-overview-copy-secondary">
                        Professional mode now includes settings, services, availability, blocked time, appointment management, the client CRM, the public booking page, client self-service appointment changes, appointment notifications, and reporting.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-lg-4" id="professional-dashboard-links-col">
            <div class="card border-0 shadow-sm h-100" id="professional-dashboard-links-card">
                <div class="card-body" id="professional-dashboard-links-body">
                    <h5 class="mb-3" id="professional-dashboard-links-title">Build Sequence</h5>
                    <div id="professional-dashboard-links-wrap" class="d-grid gap-2">
                        <a href="#" class="btn btn-outline-primary text-start" id="professional-dashboard-link-settings"
                           hx-get="/partials/professional/settings.php"
                           hx-target="#page-content">Professional Settings</a>
                        <a href="#" class="btn btn-outline-primary text-start" id="professional-dashboard-link-services"
                           hx-get="/partials/professional/services.php"
                           hx-target="#page-content">Services</a>
                        <a href="#" class="btn btn-outline-primary text-start" id="professional-dashboard-link-availability"
                           hx-get="/partials/professional/availability.php"
                           hx-target="#page-content">Availability</a>
                        <a href="#" class="btn btn-outline-primary text-start" id="professional-dashboard-link-calendar"
                           hx-get="/partials/professional/calendar.php?view=week"
                           hx-target="#page-content">Calendar</a>
                        <a href="#" class="btn btn-outline-primary text-start" id="professional-dashboard-link-clients"
                           hx-get="/partials/professional/clients.php"
                           hx-target="#page-content">Clients</a>
                        <a href="#" class="btn btn-outline-primary text-start" id="professional-dashboard-link-reports"
                           hx-get="/partials/professional/reports.php"
                           hx-target="#page-content">Reports</a>
                        <?php if ($publicBookingUrl !== ''): ?>
                        <a href="<?php echo htmlspecialchars($publicBookingUrl); ?>" class="btn btn-outline-success text-start" id="professional-dashboard-link-booking-page" target="_blank" rel="noopener">Public Booking Page</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div id="professional-placeholder-row" class="row g-3">
        <div class="col-12" id="professional-placeholder-col">
            <div class="card border-0 shadow-sm" id="professional-placeholder-card">
                <div class="card-body" id="professional-placeholder-body">
                    <h5 class="mb-3" id="professional-placeholder-title"><?php echo htmlspecialchars($currentView['title']); ?></h5>
                    <p class="text-muted mb-3" id="professional-placeholder-copy">
                        <?php echo htmlspecialchars($currentView['subtitle']); ?>
                    </p>
                    <p class="mb-3 text-muted" id="professional-placeholder-copy-secondary">
                        This screen remains a placeholder while the remaining professional features are built, so professional users do not fall back into the restaurant interface.
                    </p>
                    <div id="professional-placeholder-actions" class="d-flex flex-wrap gap-2">
                        <a href="#" class="btn btn-primary" id="professional-placeholder-back-dashboard"
                           hx-get="/partials/professional/dashboard.php"
                           hx-target="#page-content">Back to Dashboard</a>
                        <a href="#" class="btn btn-outline-primary" id="professional-placeholder-next-settings"
                           hx-get="/partials/professional/settings.php"
                           hx-target="#page-content">Open Professional Settings</a>
                        <a href="#" class="btn btn-outline-primary" id="professional-placeholder-next-calendar"
                           hx-get="/partials/professional/calendar.php?view=week"
                           hx-target="#page-content">Open Calendar</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
