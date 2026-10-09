<?php
/**
 * GET /api/v1/reports.php?start_date=2026-09-01&end_date=2026-09-30&group_by=week   (manager)
 *
 * The Reports screen's figures: totals by status, revenue, utilization, a per-period series,
 * per-service breakdown and top clients. Defaults to the last 30 days grouped by day.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../helpers/professional-reports.php';

api_require_method('GET');
$auth = api_authenticate();
api_require_role($auth, 'manager');

$r = professionalReportsBuild($auth['restaurant_id'], $_GET);
if ($r === null) {
    api_error('Professional profile not configured.', 'NOT_FOUND', 404);
}

api_success([
    'range' => [
        'start_date' => $r['startDate'],
        'end_date'   => $r['endDate'],
        'group_by'   => $r['groupBy'],
        'timezone'   => $r['timezone']->getName(),
    ],
    'summary' => [
        'total_appointments'      => $r['totalAppointments'],
        'pending'                 => $r['pendingAppointments'],
        'confirmed'               => $r['confirmedAppointments'],
        'completed'               => $r['completedAppointments'],
        'cancelled'               => $r['cancelledAppointments'],
        'no_show'                 => $r['noShowAppointments'],
        'issue_rate_percent'      => $r['issueRate'],
        'currency_code'           => $r['currencyCode'],
        'scheduled_revenue'       => round($r['scheduledRevenue'], 2),
        'completed_revenue'       => round($r['completedRevenue'], 2),
        'booked_minutes'          => $r['bookedMinutes'],
        'available_minutes'       => $r['totalAvailableMinutes'],
        'utilization_percent'     => $r['utilizationRate'],
    ],
    'periods' => array_values(array_map(fn($b) => [
        'label'     => $b['label'],
        'total'     => (int)$b['total'],
        'scheduled' => (int)$b['scheduled'],
        'completed' => (int)$b['completed'],
        'issues'    => (int)$b['issues'],
    ], $r['periodBuckets'])),
    'services' => array_map(fn($s) => [
        'service_name'    => $s['service_name'],
        'kept_count'      => (int)$s['kept_count'],
        'cancelled_count' => (int)$s['cancelled_count'],
        'no_show_count'   => (int)$s['no_show_count'],
        'booked_minutes'  => (int)$s['booked_minutes'],
        'revenue'         => round((float)$s['revenue'], 2),
    ], $r['serviceRows']),
    'top_clients' => array_map(fn($c) => [
        'client_id'     => (int)$c['id'],
        'name'          => trim($c['first_name'] . ' ' . $c['last_name']),
        'email'         => $c['email'],
        'phone'         => $c['phone'],
        'visit_count'   => (int)$c['visit_count'],
        'revenue'       => round((float)$c['revenue'], 2),
        'last_visit_at' => $c['last_visit_at'],
    ], $r['topClients']),
]);
