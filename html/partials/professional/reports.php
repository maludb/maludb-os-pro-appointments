<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/professional-availability.php';

requireAuth();
requireManager();

$restaurantId = currentRestaurantId();

if (!$restaurantId) {
    echo '<div class="alert alert-danger" id="professional-reports-no-restaurant">No professional account is currently selected.</div>';
    exit;
}

$professionalProfile = getProfessionalProfile($restaurantId);
$businessName = trim((string)($professionalProfile['business_name'] ?? ''));
if ($businessName === '') {
    $businessName = $_SESSION['current_restaurant_name'] ?? 'Professional Business';
}

$today = date('Y-m-d');
$defaultStartDate = date('Y-m-d', strtotime('-29 days'));
$startDate = trim((string)($_GET['start_date'] ?? $defaultStartDate));
$endDate = trim((string)($_GET['end_date'] ?? $today));
$groupBy = trim((string)($_GET['group_by'] ?? 'day'));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    $startDate = $defaultStartDate;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $endDate = $today;
}

if ($endDate < $startDate) {
    $tempDate = $startDate;
    $startDate = $endDate;
    $endDate = $tempDate;
}

if (!in_array($groupBy, ['day', 'week', 'month'], true)) {
    $groupBy = 'day';
}
?>

<div id="professional-reports-main" class="p-4">
    <div id="professional-reports-header" class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div id="professional-reports-header-copy">
            <h4 class="mb-1" id="professional-reports-title">
                <i class="feather-bar-chart-2 me-2"></i>Reports
            </h4>
            <p class="text-muted mb-1" id="professional-reports-subtitle">
                <?php echo htmlspecialchars($businessName); ?> scheduling performance
            </p>
            <small class="text-muted" id="professional-reports-range-summary">
                Review appointments, revenue, client activity, and utilization for the selected date range.
            </small>
        </div>
        <div id="professional-reports-header-actions" class="d-flex flex-wrap gap-2">
            <a href="#" class="btn btn-outline-primary" id="professional-reports-open-calendar"
               hx-get="/partials/professional/calendar.php?view=upcoming"
               hx-target="#page-content">
                <i class="feather-calendar me-1"></i> Open Calendar
            </a>
            <a href="#" class="btn btn-outline-secondary" id="professional-reports-open-dashboard"
               hx-get="/partials/professional/dashboard.php"
               hx-target="#page-content">
                <i class="feather-home me-1"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <div id="professional-reports-filter-card" class="card border-0 shadow-sm mb-4">
        <div id="professional-reports-filter-body" class="card-body">
            <div id="professional-reports-filter-row" class="row g-3 align-items-end">
                <div id="professional-reports-start-col" class="col-md-4 col-xl-3">
                    <label for="professional-reports-start-date" class="form-label">Start date</label>
                    <input
                        type="date"
                        class="form-control"
                        id="professional-reports-start-date"
                        name="start_date"
                        value="<?php echo htmlspecialchars($startDate); ?>"
                    >
                </div>
                <div id="professional-reports-end-col" class="col-md-4 col-xl-3">
                    <label for="professional-reports-end-date" class="form-label">End date</label>
                    <input
                        type="date"
                        class="form-control"
                        id="professional-reports-end-date"
                        name="end_date"
                        value="<?php echo htmlspecialchars($endDate); ?>"
                    >
                </div>
                <div id="professional-reports-group-col" class="col-md-4 col-xl-2">
                    <label for="professional-reports-group-by" class="form-label">Group by</label>
                    <select class="form-select" id="professional-reports-group-by" name="group_by">
                        <option value="day" <?php echo $groupBy === 'day' ? 'selected' : ''; ?>>Day</option>
                        <option value="week" <?php echo $groupBy === 'week' ? 'selected' : ''; ?>>Week</option>
                        <option value="month" <?php echo $groupBy === 'month' ? 'selected' : ''; ?>>Month</option>
                    </select>
                </div>
                <div id="professional-reports-actions-col" class="col-xl-4">
                    <div id="professional-reports-actions-wrap" class="d-flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="btn btn-primary"
                            id="professional-reports-apply-button"
                            hx-get="/partials/professional/reports.php"
                            hx-include="#professional-reports-start-date, #professional-reports-end-date, #professional-reports-group-by"
                            hx-target="#page-content"
                        >
                            Apply
                        </button>
                        <a
                            href="#"
                            class="btn btn-outline-secondary"
                            id="professional-reports-last-30-button"
                            hx-get="/partials/professional/reports.php?start_date=<?php echo urlencode($defaultStartDate); ?>&end_date=<?php echo urlencode($today); ?>&group_by=day"
                            hx-target="#page-content"
                        >
                            Last 30 Days
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div
        id="professional-reports-data"
        hx-get="/partials/professional/reports-data.php?start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>&group_by=<?php echo urlencode($groupBy); ?>"
        hx-trigger="load"
        hx-swap="innerHTML"
    >
        <div id="professional-reports-loading" class="card border-0 shadow-sm">
            <div id="professional-reports-loading-body" class="card-body text-center py-5">
                <div class="spinner-border text-primary" id="professional-reports-loading-spinner" role="status"></div>
                <p class="text-muted mt-3 mb-0" id="professional-reports-loading-copy">Loading professional reports...</p>
            </div>
        </div>
    </div>
</div>
