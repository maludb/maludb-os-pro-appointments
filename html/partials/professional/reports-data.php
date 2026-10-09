<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/professional-reports.php';

requireAuth();
requireManager();

$restaurantId = currentRestaurantId();

if (!$restaurantId) {
    echo '<div class="alert alert-danger" id="professional-reports-data-no-restaurant">No professional account is currently selected.</div>';
    exit;
}

$report = professionalReportsBuild($restaurantId, $_GET);
if ($report === null) {
    echo '<div class="alert alert-warning" id="professional-reports-data-no-profile">Professional settings are not configured yet.</div>';
    exit;
}
extract($report);
?>

<div id="professional-reports-data-wrap">
    <div id="professional-reports-range-card" class="card border-0 shadow-sm mb-4">
        <div id="professional-reports-range-body" class="card-body d-flex flex-column flex-lg-row justify-content-between gap-3">
            <div id="professional-reports-range-copy">
                <h6 class="mb-1" id="professional-reports-range-title">Selected Range</h6>
                <p class="text-muted mb-0" id="professional-reports-range-text">
                    <?php echo htmlspecialchars($rangeLabel); ?> grouped by <?php echo htmlspecialchars(strtolower($groupByLabel)); ?>.
                </p>
            </div>
            <div id="professional-reports-range-meta" class="text-lg-end">
                <div id="professional-reports-range-meta-booked" class="small text-muted">
                    Booked time: <?php echo htmlspecialchars(professionalReportsFormatMinutes($bookedMinutes)); ?>
                </div>
                <div id="professional-reports-range-meta-available" class="small text-muted">
                    Available time: <?php echo htmlspecialchars(professionalReportsFormatMinutes($totalAvailableMinutes)); ?>
                </div>
            </div>
        </div>
    </div>

    <div id="professional-reports-summary-row" class="row g-3 mb-4">
        <div id="professional-reports-summary-appointments-col" class="col-md-6 col-xl-3">
            <div id="professional-reports-summary-appointments-card" class="card border-0 shadow-sm h-100">
                <div id="professional-reports-summary-appointments-body" class="card-body">
                    <h6 class="text-muted mb-2" id="professional-reports-summary-appointments-label">Appointments</h6>
                    <h3 class="mb-1" id="professional-reports-summary-appointments-value"><?php echo $totalAppointments; ?></h3>
                    <p class="mb-0 text-muted" id="professional-reports-summary-appointments-copy">
                        <?php echo $completedAppointments; ?> completed, <?php echo $pendingAppointments + $confirmedAppointments; ?> scheduled
                    </p>
                </div>
            </div>
        </div>
        <div id="professional-reports-summary-revenue-col" class="col-md-6 col-xl-3">
            <div id="professional-reports-summary-revenue-card" class="card border-0 shadow-sm h-100">
                <div id="professional-reports-summary-revenue-body" class="card-body">
                    <h6 class="text-muted mb-2" id="professional-reports-summary-revenue-label">Scheduled Revenue</h6>
                    <h3 class="mb-1" id="professional-reports-summary-revenue-value"><?php echo htmlspecialchars(professionalReportsFormatMoney($scheduledRevenue, $currencyCode)); ?></h3>
                    <p class="mb-0 text-muted" id="professional-reports-summary-revenue-copy">
                        Completed revenue: <?php echo htmlspecialchars(professionalReportsFormatMoney($completedRevenue, $currencyCode)); ?>
                    </p>
                </div>
            </div>
        </div>
        <div id="professional-reports-summary-issues-col" class="col-md-6 col-xl-3">
            <div id="professional-reports-summary-issues-card" class="card border-0 shadow-sm h-100">
                <div id="professional-reports-summary-issues-body" class="card-body">
                    <h6 class="text-muted mb-2" id="professional-reports-summary-issues-label">Cancellations / No-Show</h6>
                    <h3 class="mb-1" id="professional-reports-summary-issues-value"><?php echo $issueAppointments; ?></h3>
                    <p class="mb-0 text-muted" id="professional-reports-summary-issues-copy">
                        <?php echo number_format($issueRate, 1); ?>% of appointments in range
                    </p>
                </div>
            </div>
        </div>
        <div id="professional-reports-summary-utilization-col" class="col-md-6 col-xl-3">
            <div id="professional-reports-summary-utilization-card" class="card border-0 shadow-sm h-100">
                <div id="professional-reports-summary-utilization-body" class="card-body">
                    <h6 class="text-muted mb-2" id="professional-reports-summary-utilization-label">Utilization</h6>
                    <h3 class="mb-1" id="professional-reports-summary-utilization-value">
                        <?php echo $totalAvailableMinutes > 0 ? number_format($utilizationRate, 1) . '%' : 'N/A'; ?>
                    </h3>
                    <p class="mb-0 text-muted" id="professional-reports-summary-utilization-copy">
                        <?php echo htmlspecialchars(professionalReportsFormatMinutes($bookedMinutes)); ?> booked of <?php echo htmlspecialchars(professionalReportsFormatMinutes($totalAvailableMinutes)); ?> capacity
                    </p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($totalAvailableMinutes <= 0): ?>
    <div id="professional-reports-no-availability-alert" class="alert alert-warning">
        Availability is not configured for the selected range, so utilization is shown as unavailable.
    </div>
    <?php endif; ?>

    <div id="professional-reports-chart-row" class="row g-3 mb-4">
        <div id="professional-reports-chart-volume-col" class="col-lg-8">
            <div id="professional-reports-chart-volume-card" class="card border-0 shadow-sm h-100">
                <div id="professional-reports-chart-volume-header" class="card-header bg-transparent">
                    <h6 class="mb-0" id="professional-reports-chart-volume-title">Appointments by <?php echo htmlspecialchars(strtolower($groupByLabel)); ?></h6>
                </div>
                <div id="professional-reports-chart-volume-body" class="card-body">
                    <div id="professional-reports-volume-chart"></div>
                </div>
            </div>
        </div>
        <div id="professional-reports-chart-service-col" class="col-lg-4">
            <div id="professional-reports-chart-service-card" class="card border-0 shadow-sm h-100">
                <div id="professional-reports-chart-service-header" class="card-header bg-transparent">
                    <h6 class="mb-0" id="professional-reports-chart-service-title">Revenue by Service</h6>
                </div>
                <div id="professional-reports-chart-service-body" class="card-body">
                    <?php if (!empty($serviceChartLabels)): ?>
                    <div id="professional-reports-service-chart"></div>
                    <?php else: ?>
                    <div id="professional-reports-service-chart-empty" class="text-muted small">
                        No paid appointment revenue is available for this range yet.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div id="professional-reports-table-row-top" class="row g-3 mb-4">
        <div id="professional-reports-services-col" class="col-xl-7">
            <div id="professional-reports-services-card" class="card border-0 shadow-sm h-100">
                <div id="professional-reports-services-header" class="card-header bg-transparent">
                    <h6 class="mb-0" id="professional-reports-services-title">Service Performance</h6>
                </div>
                <div id="professional-reports-services-body" class="card-body p-0">
                    <div id="professional-reports-services-table-wrap" class="table-responsive">
                        <table class="table table-hover mb-0" id="professional-reports-services-table">
                            <thead id="professional-reports-services-head">
                                <tr id="professional-reports-services-head-row">
                                    <th>Service</th>
                                    <th class="text-end">Appointments</th>
                                    <th class="text-end">Revenue</th>
                                    <th class="text-end">Booked Time</th>
                                    <th class="text-end">Issues</th>
                                </tr>
                            </thead>
                            <tbody id="professional-reports-services-table-body">
                                <?php if (empty($serviceRows)): ?>
                                <tr id="professional-reports-services-empty-row">
                                    <td colspan="5" class="text-center text-muted py-4">No appointment activity in this date range.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($serviceRows as $serviceIndex => $serviceRow): ?>
                                <?php
                                $serviceIssues = (int)$serviceRow['cancelled_count'] + (int)$serviceRow['no_show_count'];
                                ?>
                                <tr id="professional-reports-service-row-<?php echo $serviceIndex; ?>">
                                    <td id="professional-reports-service-name-<?php echo $serviceIndex; ?>">
                                        <?php echo htmlspecialchars((string)$serviceRow['service_name']); ?>
                                    </td>
                                    <td class="text-end" id="professional-reports-service-appointments-<?php echo $serviceIndex; ?>">
                                        <?php echo (int)$serviceRow['kept_count']; ?>
                                    </td>
                                    <td class="text-end" id="professional-reports-service-revenue-<?php echo $serviceIndex; ?>">
                                        <?php echo htmlspecialchars(professionalReportsFormatMoney((float)$serviceRow['revenue'], $currencyCode)); ?>
                                    </td>
                                    <td class="text-end" id="professional-reports-service-booked-<?php echo $serviceIndex; ?>">
                                        <?php echo htmlspecialchars(professionalReportsFormatMinutes((int)$serviceRow['booked_minutes'])); ?>
                                    </td>
                                    <td class="text-end" id="professional-reports-service-issues-<?php echo $serviceIndex; ?>">
                                        <?php echo $serviceIssues; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div id="professional-reports-clients-col" class="col-xl-5">
            <div id="professional-reports-clients-card" class="card border-0 shadow-sm h-100">
                <div id="professional-reports-clients-header" class="card-header bg-transparent">
                    <h6 class="mb-0" id="professional-reports-clients-title">Top Clients</h6>
                </div>
                <div id="professional-reports-clients-body" class="card-body p-0">
                    <div id="professional-reports-clients-table-wrap" class="table-responsive">
                        <table class="table table-hover mb-0" id="professional-reports-clients-table">
                            <thead id="professional-reports-clients-head">
                                <tr id="professional-reports-clients-head-row">
                                    <th>Client</th>
                                    <th class="text-end">Visits</th>
                                    <th class="text-end">Revenue</th>
                                </tr>
                            </thead>
                            <tbody id="professional-reports-clients-table-body">
                                <?php if (empty($topClients)): ?>
                                <tr id="professional-reports-clients-empty-row">
                                    <td colspan="3" class="text-center text-muted py-4">No client activity in this date range.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($topClients as $clientIndex => $clientRow): ?>
                                <?php
                                $clientName = trim((string)$clientRow['first_name'] . ' ' . (string)$clientRow['last_name']);
                                if ($clientName === '') {
                                    $clientName = 'Client #' . (int)$clientRow['id'];
                                }
                                ?>
                                <tr id="professional-reports-client-row-<?php echo (int)$clientRow['id']; ?>">
                                    <td id="professional-reports-client-name-<?php echo (int)$clientRow['id']; ?>">
                                        <div id="professional-reports-client-name-wrap-<?php echo (int)$clientRow['id']; ?>" class="fw-semibold">
                                            <?php echo htmlspecialchars($clientName); ?>
                                        </div>
                                        <div id="professional-reports-client-meta-<?php echo (int)$clientRow['id']; ?>" class="small text-muted">
                                            <?php
                                            $contactValue = trim((string)($clientRow['email'] ?: $clientRow['phone']));
                                            echo htmlspecialchars($contactValue !== '' ? $contactValue : 'No contact details');
                                            ?>
                                        </div>
                                    </td>
                                    <td class="text-end" id="professional-reports-client-visits-<?php echo (int)$clientRow['id']; ?>">
                                        <?php echo (int)$clientRow['visit_count']; ?>
                                    </td>
                                    <td class="text-end" id="professional-reports-client-revenue-<?php echo (int)$clientRow['id']; ?>">
                                        <?php echo htmlspecialchars(professionalReportsFormatMoney((float)$clientRow['revenue'], $currencyCode)); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="professional-reports-table-row-bottom" class="row g-3">
        <div id="professional-reports-outcomes-col" class="col-lg-6">
            <div id="professional-reports-outcomes-card" class="card border-0 shadow-sm h-100">
                <div id="professional-reports-outcomes-header" class="card-header bg-transparent">
                    <h6 class="mb-0" id="professional-reports-outcomes-title">Appointment Outcomes</h6>
                </div>
                <div id="professional-reports-outcomes-body" class="card-body p-0">
                    <div id="professional-reports-outcomes-table-wrap" class="table-responsive">
                        <table class="table table-hover mb-0" id="professional-reports-outcomes-table">
                            <thead id="professional-reports-outcomes-head">
                                <tr id="professional-reports-outcomes-head-row">
                                    <th>Status</th>
                                    <th class="text-end">Count</th>
                                    <th class="text-end">Share</th>
                                </tr>
                            </thead>
                            <tbody id="professional-reports-outcomes-table-body">
                                <?php foreach ($outcomeRows as $outcomeIndex => $outcomeRow): ?>
                                <tr id="professional-reports-outcome-row-<?php echo $outcomeIndex; ?>">
                                    <td id="professional-reports-outcome-name-<?php echo $outcomeIndex; ?>">
                                        <?php echo htmlspecialchars(professionalReportsStatusLabel((string)$outcomeRow['status'])); ?>
                                    </td>
                                    <td class="text-end" id="professional-reports-outcome-count-<?php echo $outcomeIndex; ?>">
                                        <?php echo (int)$outcomeRow['count']; ?>
                                    </td>
                                    <td class="text-end" id="professional-reports-outcome-share-<?php echo $outcomeIndex; ?>">
                                        <?php echo number_format(professionalReportsPercent((int)$outcomeRow['count'], $totalAppointments), 1); ?>%
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div id="professional-reports-utilization-col" class="col-lg-6">
            <div id="professional-reports-utilization-card" class="card border-0 shadow-sm h-100">
                <div id="professional-reports-utilization-header" class="card-header bg-transparent">
                    <h6 class="mb-0" id="professional-reports-utilization-title">Service Utilization</h6>
                </div>
                <div id="professional-reports-utilization-body" class="card-body">
                    <?php if (empty($serviceRows)): ?>
                    <div id="professional-reports-utilization-empty" class="text-muted">No utilization data is available yet.</div>
                    <?php else: ?>
                    <div id="professional-reports-utilization-list" class="d-grid gap-3">
                        <?php foreach ($serviceRows as $utilizationIndex => $serviceRow): ?>
                        <?php
                        $serviceBookedMinutes = (int)$serviceRow['booked_minutes'];
                        $serviceUtilizationRate = $totalAvailableMinutes > 0
                            ? min(100, round(($serviceBookedMinutes / $totalAvailableMinutes) * 100, 1))
                            : 0.0;
                        ?>
                        <div id="professional-reports-utilization-item-<?php echo $utilizationIndex; ?>">
                            <div id="professional-reports-utilization-copy-<?php echo $utilizationIndex; ?>" class="d-flex justify-content-between align-items-center mb-1">
                                <span id="professional-reports-utilization-name-<?php echo $utilizationIndex; ?>" class="fw-semibold">
                                    <?php echo htmlspecialchars((string)$serviceRow['service_name']); ?>
                                </span>
                                <span id="professional-reports-utilization-value-<?php echo $utilizationIndex; ?>" class="text-muted small">
                                    <?php echo htmlspecialchars(professionalReportsFormatMinutes($serviceBookedMinutes)); ?>
                                    /
                                    <?php echo htmlspecialchars(professionalReportsFormatMinutes($totalAvailableMinutes)); ?>
                                    (<?php echo number_format($serviceUtilizationRate, 1); ?>%)
                                </span>
                            </div>
                            <div id="professional-reports-utilization-progress-<?php echo $utilizationIndex; ?>" class="progress" style="height: 8px;">
                                <div
                                    id="professional-reports-utilization-progress-bar-<?php echo $utilizationIndex; ?>"
                                    class="progress-bar bg-primary"
                                    role="progressbar"
                                    style="width: <?php echo max(0, min(100, $serviceUtilizationRate)); ?>%;"
                                    aria-valuenow="<?php echo max(0, min(100, $serviceUtilizationRate)); ?>"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                ></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    if (typeof ApexCharts === 'undefined') {
        return;
    }

    var volumeChartElement = document.querySelector('#professional-reports-volume-chart');
    if (volumeChartElement) {
        volumeChartElement.innerHTML = '';
        new ApexCharts(volumeChartElement, {
            chart: {
                type: 'bar',
                height: 320,
                stacked: true,
                toolbar: { show: false }
            },
            series: [
                { name: 'Scheduled', data: <?php echo json_encode($periodScheduledSeries); ?> },
                { name: 'Issues', data: <?php echo json_encode($periodIssueSeries); ?> }
            ],
            colors: ['#233882', '#ba1654'],
            xaxis: {
                categories: <?php echo json_encode($periodLabels); ?>,
                labels: { rotate: -35, style: { fontSize: '10px' } }
            },
            yaxis: {
                title: { text: 'Appointments' }
            },
            legend: { position: 'top' },
            plotOptions: {
                bar: {
                    borderRadius: 4,
                    columnWidth: '55%'
                }
            },
            dataLabels: { enabled: false },
            stroke: { show: false },
            grid: { borderColor: '#eef0f6' }
        }).render();
    }

    var serviceChartElement = document.querySelector('#professional-reports-service-chart');
    if (serviceChartElement) {
        serviceChartElement.innerHTML = '';
        new ApexCharts(serviceChartElement, {
            chart: {
                type: 'bar',
                height: 320,
                toolbar: { show: false }
            },
            series: [
                { name: 'Revenue', data: <?php echo json_encode($serviceChartRevenue); ?> }
            ],
            colors: ['#497a0b'],
            xaxis: {
                categories: <?php echo json_encode($serviceChartLabels); ?>,
                labels: {
                    formatter: function(value) {
                        return value;
                    }
                }
            },
            yaxis: {
                labels: {
                    formatter: function(value) {
                        return value.toFixed(0);
                    }
                }
            },
            plotOptions: {
                bar: {
                    horizontal: true,
                    borderRadius: 4,
                    barHeight: '55%'
                }
            },
            dataLabels: {
                enabled: true,
                formatter: function(value) {
                    return '<?php echo $currencyCode === 'USD' ? '$' : addslashes($currencyCode . ' '); ?>' + Number(value).toFixed(0);
                }
            },
            tooltip: {
                y: {
                    formatter: function(value) {
                        return '<?php echo $currencyCode === 'USD' ? '$' : addslashes($currencyCode . ' '); ?>' + Number(value).toFixed(2);
                    }
                }
            },
            grid: { borderColor: '#eef0f6' }
        }).render();
    }
})();
</script>
