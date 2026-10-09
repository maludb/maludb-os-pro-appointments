<?php
/**
 * Professional reports: appointment, revenue and utilization figures for a date range.
 * Used by html/partials/professional/reports-data.php (web) and html/api/v1/reports.php (REST).
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/professional-availability.php';

function professionalReportsParseDate(string $value, string $fallback): string {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : $fallback;
}

function professionalReportsNormalizeGroupBy(string $value): string {
    return in_array($value, ['day', 'week', 'month'], true) ? $value : 'day';
}

function professionalReportsFormatMoney(float $amount, string $currencyCode): string {
    $currencyCode = strtoupper(trim($currencyCode));
    if ($currencyCode === '' || $currencyCode === 'USD') {
        return '$' . number_format($amount, 2);
    }

    return $currencyCode . ' ' . number_format($amount, 2);
}

function professionalReportsFormatMinutes(int $minutes): string {
    $minutes = max(0, $minutes);
    $hours = intdiv($minutes, 60);
    $remainingMinutes = $minutes % 60;

    if ($hours > 0 && $remainingMinutes > 0) {
        return $hours . 'h ' . $remainingMinutes . 'm';
    }

    if ($hours > 0) {
        return $hours . 'h';
    }

    return $remainingMinutes . 'm';
}

function professionalReportsStatusLabel(string $status): string {
    return ucwords(str_replace('_', ' ', $status));
}

function professionalReportsPercent(int $count, int $total): float {
    if ($total <= 0) {
        return 0.0;
    }

    return round(($count / $total) * 100, 1);
}

function professionalReportsPeriodAnchor(DateTimeImmutable $date, string $groupBy): DateTimeImmutable {
    if ($groupBy === 'week') {
        return $date->modify('monday this week')->setTime(0, 0, 0);
    }

    if ($groupBy === 'month') {
        return $date->modify('first day of this month')->setTime(0, 0, 0);
    }

    return $date->setTime(0, 0, 0);
}

function professionalReportsPeriodKey(DateTimeImmutable $date, string $groupBy): string {
    return professionalReportsPeriodAnchor($date, $groupBy)->format('Y-m-d');
}

function professionalReportsPeriodLabel(DateTimeImmutable $anchor, string $groupBy): string {
    if ($groupBy === 'week') {
        return 'Week of ' . $anchor->format('M j');
    }

    if ($groupBy === 'month') {
        return $anchor->format('M Y');
    }

    return $anchor->format('M j');
}

function professionalReportsBuildBuckets(DateTimeImmutable $startDate, DateTimeImmutable $endDate, string $groupBy): array {
    $buckets = [];
    $cursor = professionalReportsPeriodAnchor($startDate, $groupBy);
    $lastBucket = professionalReportsPeriodAnchor($endDate, $groupBy);

    while ($cursor <= $lastBucket) {
        $bucketKey = $cursor->format('Y-m-d');
        $buckets[$bucketKey] = [
            'label' => professionalReportsPeriodLabel($cursor, $groupBy),
            'scheduled' => 0,
            'issues' => 0,
            'completed' => 0,
            'total' => 0,
        ];

        if ($groupBy === 'week') {
            $cursor = $cursor->modify('+1 week');
        } elseif ($groupBy === 'month') {
            $cursor = $cursor->modify('+1 month');
        } else {
            $cursor = $cursor->modify('+1 day');
        }
    }

    return $buckets;
}

function professionalReportsIntervalMinutes(DateTimeInterface $start, DateTimeInterface $end): int {
    $seconds = $end->getTimestamp() - $start->getTimestamp();
    return max(0, (int) floor($seconds / 60));
}

function professionalReportsMergeRanges(array $ranges): array {
    if (empty($ranges)) {
        return [];
    }

    usort($ranges, function ($left, $right) {
        return $left['start'] <=> $right['start'];
    });

    $merged = [$ranges[0]];

    foreach ($ranges as $rangeIndex => $range) {
        if ($rangeIndex === 0) {
            continue;
        }

        $lastIndex = count($merged) - 1;
        if ($range['start'] <= $merged[$lastIndex]['end']) {
            $merged[$lastIndex]['end'] = max($merged[$lastIndex]['end'], $range['end']);
            continue;
        }

        $merged[] = $range;
    }

    return $merged;
}

function professionalReportsCalculateAvailableMinutes(int $restaurantId, DateTimeImmutable $startDate, DateTimeImmutable $endDate, DateTimeZone $timezone): int {
    $totalMinutes = 0;
    $cursor = $startDate;

    while ($cursor <= $endDate) {
        $dateString = $cursor->format('Y-m-d');
        $windows = getProfessionalAvailabilityWindowsForDate($restaurantId, $dateString);

        if (!empty($windows)) {
            $dayStart = $cursor->setTime(0, 0, 0);
            $dayEnd = $dayStart->modify('+1 day');
            $timeOffBlocks = getProfessionalTimeOffBlocks(
                $restaurantId,
                $dayStart->format('Y-m-d H:i:s'),
                $dayEnd->format('Y-m-d H:i:s')
            );

            foreach ($windows as $windowIndex => $window) {
                $windowStart = $window['window_start'];
                $windowEnd = $window['window_end'];
                $windowMinutes = professionalReportsIntervalMinutes($windowStart, $windowEnd);

                if ($windowMinutes <= 0) {
                    continue;
                }

                $blockedRanges = [];

                foreach ($timeOffBlocks as $block) {
                    $overlapStart = max($windowStart->getTimestamp(), $block['start_at_dt']->getTimestamp());
                    $overlapEnd = min($windowEnd->getTimestamp(), $block['end_at_dt']->getTimestamp());

                    if ($overlapStart >= $overlapEnd) {
                        continue;
                    }

                    $blockedRanges[] = [
                        'start' => $overlapStart,
                        'end' => $overlapEnd,
                        'window_index' => $windowIndex,
                    ];
                }

                $blockedMinutes = 0;
                foreach (professionalReportsMergeRanges($blockedRanges) as $range) {
                    $blockedMinutes += max(0, (int) floor(($range['end'] - $range['start']) / 60));
                }

                $totalMinutes += max(0, $windowMinutes - $blockedMinutes);
            }
        }

        $cursor = $cursor->modify('+1 day')->setTimezone($timezone);
    }

    return $totalMinutes;
}

/**
 * Build the report for a business. $query takes start_date, end_date (YYYY-MM-DD) and
 * group_by (day / week / month), each optional. Returns null when the business has no
 * professional profile; otherwise every figure the Reports screen shows, keyed by name.
 */
function professionalReportsBuild(int $restaurantId, array $query): ?array {
    $today = date('Y-m-d');
    $defaultStartDate = date('Y-m-d', strtotime('-29 days'));
    $startDate = professionalReportsParseDate((string)($query['start_date'] ?? $defaultStartDate), $defaultStartDate);
    $endDate = professionalReportsParseDate((string)($query['end_date'] ?? $today), $today);
    $groupBy = professionalReportsNormalizeGroupBy((string)($query['group_by'] ?? 'day'));

    if ($endDate < $startDate) {
        $tempDate = $startDate;
        $startDate = $endDate;
        $endDate = $tempDate;
    }

    $professionalProfile = getProfessionalProfile($restaurantId);
    if (!$professionalProfile) {
        return null;
    }

    $timezone = new DateTimeZone($professionalProfile['timezone']);
    $startDateObject = new DateTimeImmutable($startDate . ' 00:00:00', $timezone);
    $endDateObject = new DateTimeImmutable($endDate . ' 00:00:00', $timezone);
    $pdo = db();

    $metricsStmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS total_appointments,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_appointments,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed_appointments,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_appointments,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_appointments,
            SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) AS no_show_appointments,
            SUM(CASE WHEN status NOT IN ('cancelled', 'no_show') THEN COALESCE(price, 0) ELSE 0 END) AS scheduled_revenue,
            SUM(CASE WHEN status = 'completed' THEN COALESCE(price, 0) ELSE 0 END) AS completed_revenue,
            SUM(CASE WHEN status NOT IN ('cancelled', 'no_show') THEN duration_minutes ELSE 0 END) AS booked_minutes
         FROM professional_appointments
         WHERE restaurant_id = ?
           AND appointment_date BETWEEN ? AND ?"
    );
    $metricsStmt->execute([$restaurantId, $startDate, $endDate]);
    $metrics = $metricsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $currencyStmt = $pdo->prepare(
        "SELECT currency_code
         FROM professional_appointments
         WHERE restaurant_id = ?
           AND appointment_date BETWEEN ? AND ?
           AND currency_code IS NOT NULL
           AND currency_code != ''
         ORDER BY id DESC
         LIMIT 1"
    );
    $currencyStmt->execute([$restaurantId, $startDate, $endDate]);
    $currencyCode = strtoupper((string)$currencyStmt->fetchColumn());
    if ($currencyCode === '') {
        $currencyCode = 'USD';
    }

    $totalAppointments = (int)($metrics['total_appointments'] ?? 0);
    $pendingAppointments = (int)($metrics['pending_appointments'] ?? 0);
    $confirmedAppointments = (int)($metrics['confirmed_appointments'] ?? 0);
    $completedAppointments = (int)($metrics['completed_appointments'] ?? 0);
    $cancelledAppointments = (int)($metrics['cancelled_appointments'] ?? 0);
    $noShowAppointments = (int)($metrics['no_show_appointments'] ?? 0);
    $scheduledRevenue = (float)($metrics['scheduled_revenue'] ?? 0);
    $completedRevenue = (float)($metrics['completed_revenue'] ?? 0);
    $bookedMinutes = (int)($metrics['booked_minutes'] ?? 0);
    $issueAppointments = $cancelledAppointments + $noShowAppointments;
    $issueRate = professionalReportsPercent($issueAppointments, $totalAppointments);

    $totalAvailableMinutes = professionalReportsCalculateAvailableMinutes(
        $restaurantId,
        $startDateObject,
        $endDateObject,
        $timezone
    );
    $utilizationRate = $totalAvailableMinutes > 0
        ? round(($bookedMinutes / $totalAvailableMinutes) * 100, 1)
        : 0.0;

    $periodBuckets = professionalReportsBuildBuckets($startDateObject, $endDateObject, $groupBy);

    $periodStmt = $pdo->prepare(
        "SELECT appointment_date, status, COUNT(*) AS appointment_count
         FROM professional_appointments
         WHERE restaurant_id = ?
           AND appointment_date BETWEEN ? AND ?
         GROUP BY appointment_date, status
         ORDER BY appointment_date ASC"
    );
    $periodStmt->execute([$restaurantId, $startDate, $endDate]);
    $periodRows = $periodStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($periodRows as $row) {
        $appointmentDate = new DateTimeImmutable($row['appointment_date'] . ' 00:00:00', $timezone);
        $bucketKey = professionalReportsPeriodKey($appointmentDate, $groupBy);

        if (!isset($periodBuckets[$bucketKey])) {
            continue;
        }

        $count = (int)$row['appointment_count'];
        $status = (string)$row['status'];
        $periodBuckets[$bucketKey]['total'] += $count;

        if (in_array($status, ['cancelled', 'no_show'], true)) {
            $periodBuckets[$bucketKey]['issues'] += $count;
        } else {
            $periodBuckets[$bucketKey]['scheduled'] += $count;
        }

        if ($status === 'completed') {
            $periodBuckets[$bucketKey]['completed'] += $count;
        }
    }

    $periodLabels = [];
    $periodScheduledSeries = [];
    $periodIssueSeries = [];

    foreach ($periodBuckets as $bucket) {
        $periodLabels[] = $bucket['label'];
        $periodScheduledSeries[] = (int)$bucket['scheduled'];
        $periodIssueSeries[] = (int)$bucket['issues'];
    }

    $serviceStmt = $pdo->prepare(
        "SELECT
            COALESCE(NULLIF(service_name, ''), 'Service') AS service_name,
            SUM(CASE WHEN status NOT IN ('cancelled', 'no_show') THEN 1 ELSE 0 END) AS kept_count,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count,
            SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) AS no_show_count,
            SUM(CASE WHEN status NOT IN ('cancelled', 'no_show') THEN duration_minutes ELSE 0 END) AS booked_minutes,
            SUM(CASE WHEN status NOT IN ('cancelled', 'no_show') THEN COALESCE(price, 0) ELSE 0 END) AS revenue
         FROM professional_appointments
         WHERE restaurant_id = ?
           AND appointment_date BETWEEN ? AND ?
         GROUP BY COALESCE(NULLIF(service_name, ''), 'Service')
         ORDER BY revenue DESC, booked_minutes DESC, service_name ASC"
    );
    $serviceStmt->execute([$restaurantId, $startDate, $endDate]);
    $serviceRows = $serviceStmt->fetchAll(PDO::FETCH_ASSOC);

    $serviceChartLabels = [];
    $serviceChartRevenue = [];
    foreach ($serviceRows as $serviceRow) {
        $revenueValue = (float)($serviceRow['revenue'] ?? 0);
        if ($revenueValue <= 0) {
            continue;
        }

        $serviceChartLabels[] = (string)$serviceRow['service_name'];
        $serviceChartRevenue[] = round($revenueValue, 2);

        if (count($serviceChartLabels) >= 6) {
            break;
        }
    }

    $topClientsStmt = $pdo->prepare(
        "SELECT
            c.id,
            c.first_name,
            c.last_name,
            c.email,
            c.phone,
            SUM(CASE WHEN a.status NOT IN ('cancelled', 'no_show') THEN 1 ELSE 0 END) AS visit_count,
            SUM(CASE WHEN a.status NOT IN ('cancelled', 'no_show') THEN COALESCE(a.price, 0) ELSE 0 END) AS revenue,
            MAX(a.start_at) AS last_visit_at
         FROM professional_clients c
         INNER JOIN professional_appointments a
            ON a.client_id = c.id
           AND a.restaurant_id = c.restaurant_id
         WHERE c.restaurant_id = ?
           AND a.appointment_date BETWEEN ? AND ?
         GROUP BY c.id, c.first_name, c.last_name, c.email, c.phone
         HAVING SUM(CASE WHEN a.status NOT IN ('cancelled', 'no_show') THEN 1 ELSE 0 END) > 0
         ORDER BY visit_count DESC, revenue DESC, last_visit_at DESC
         LIMIT 10"
    );
    $topClientsStmt->execute([$restaurantId, $startDate, $endDate]);
    $topClients = $topClientsStmt->fetchAll(PDO::FETCH_ASSOC);

    $outcomeRows = [
        ['status' => 'pending', 'count' => $pendingAppointments],
        ['status' => 'confirmed', 'count' => $confirmedAppointments],
        ['status' => 'completed', 'count' => $completedAppointments],
        ['status' => 'cancelled', 'count' => $cancelledAppointments],
        ['status' => 'no_show', 'count' => $noShowAppointments],
    ];

    $groupByLabel = ucfirst($groupBy);
    $rangeLabel = $startDateObject->format('M j, Y') . ' - ' . $endDateObject->format('M j, Y');

    return get_defined_vars();
}
