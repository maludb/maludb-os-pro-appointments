<?php
/**
 * GET /api/v1/calendar.php?start_date=2026-04-01&end_date=2026-04-30
 * Aggregate endpoint: appointments + time off + availability rules in one call.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../helpers/professional-availability.php';

api_require_method('GET');
$auth = api_authenticate();
$rid  = $auth['restaurant_id'];

$startDate = api_query('start_date');
$endDate   = api_query('end_date');

if (!$startDate || !$endDate) {
    api_error('start_date and end_date are required.', 'VALIDATION_ERROR', 400);
}

$profile = getProfessionalProfile($rid);
if (!$profile) {
    api_error('Professional profile not configured.', 'NOT_FOUND', 404);
}

$tz = $profile['timezone'] ?? 'America/New_York';

// --- Appointments ---
$pdo = db();
$stmt = $pdo->prepare(
    "SELECT a.*, c.first_name AS client_first_name, c.last_name AS client_last_name,
            c.phone AS client_phone
     FROM professional_appointments a
     LEFT JOIN professional_clients c ON c.id = a.client_id
     WHERE a.restaurant_id = ?
       AND a.appointment_date >= ?
       AND a.appointment_date <= ?
       AND a.status IN ('pending','confirmed','completed')
     ORDER BY a.start_at ASC"
);
$stmt->execute([$rid, $startDate, $endDate]);
$aptRows = $stmt->fetchAll();

$appointments = array_map(function ($a) {
    return [
        'id'                    => (int)$a['id'],
        'confirmation_code'     => $a['confirmation_code'],
        'status'                => $a['status'],
        'appointment_date'      => $a['appointment_date'],
        'start_at'              => $a['start_at'],
        'end_at'                => $a['end_at'],
        'buffer_before_minutes' => (int)($a['buffer_before_minutes'] ?? 0),
        'buffer_after_minutes'  => (int)($a['buffer_after_minutes'] ?? 0),
        'service' => [
            'id'    => $a['service_id'] ? (int)$a['service_id'] : null,
            'name'  => $a['service_name'] ?? '',
            'color' => null, // Will be filled below
        ],
        'client' => [
            'id'         => $a['client_id'] ? (int)$a['client_id'] : null,
            'first_name' => $a['client_first_name'] ?? '',
            'last_name'  => $a['client_last_name'] ?? '',
            'phone'      => $a['client_phone'] ?? '',
        ],
    ];
}, $aptRows);

// Attach service colors
$serviceIds = array_unique(array_filter(array_column($aptRows, 'service_id')));
$colorMap = [];
if (!empty($serviceIds)) {
    $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
    $stmt = $pdo->prepare("SELECT id, color FROM professional_services WHERE id IN ({$placeholders})");
    $stmt->execute(array_values($serviceIds));
    foreach ($stmt->fetchAll() as $s) {
        $colorMap[(int)$s['id']] = $s['color'];
    }
}
foreach ($appointments as &$apt) {
    $sid = $apt['service']['id'];
    $apt['service']['color'] = $sid ? ($colorMap[$sid] ?? null) : null;
}
unset($apt);

// --- Time Off ---
$rangeStart = $startDate . ' 00:00:00';
$rangeEnd   = $endDate . ' 23:59:59';
$blocks = getProfessionalTimeOffBlocks($rid, $rangeStart, $rangeEnd);

$timeOff = array_map(function ($b) {
    return [
        'id'         => (int)$b['id'],
        'starts_at'  => $b['starts_at'],
        'ends_at'    => $b['ends_at'],
        'reason'     => $b['reason'] ?? null,
        'is_all_day' => (bool)($b['is_all_day'] ?? false),
    ];
}, $blocks);

// --- Availability Rules ---
$dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$stmt = $pdo->prepare(
    "SELECT * FROM professional_availability_rules
     WHERE restaurant_id = ? AND is_active = 1
     ORDER BY weekday ASC, start_time ASC"
);
$stmt->execute([$rid]);
$ruleRows = $stmt->fetchAll();

$rules = array_map(function ($r) use ($dayNames) {
    return [
        'weekday'      => (int)$r['weekday'],
        'weekday_name' => $dayNames[(int)$r['weekday']] ?? '',
        'start_time'   => $r['start_time'],
        'end_time'     => $r['end_time'],
    ];
}, $ruleRows);

api_success([
    'timezone'           => $tz,
    'appointments'       => $appointments,
    'time_off'           => $timeOff,
    'availability_rules' => $rules,
]);
