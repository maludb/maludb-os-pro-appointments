<?php
/**
 * GET /api/v1/availability.php?service_id=1&date=2026-04-01
 * Returns available time slots for a service on a given date.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../helpers/professional-availability.php';

api_require_method('GET');
$auth = api_authenticate();
$rid  = $auth['restaurant_id'];

$serviceId = api_query('service_id');
$date      = api_query('date');

if (!$serviceId || !$date) {
    api_error('service_id and date are required.', 'VALIDATION_ERROR', 400);
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    api_error('date must be YYYY-MM-DD format.', 'VALIDATION_ERROR', 400);
}

$service = getProfessionalService($rid, (int)$serviceId);
if (!$service) {
    api_error('Service not found.', 'NOT_FOUND', 404);
}

$slots = getProfessionalAvailableSlots($rid, (int)$serviceId, $date, [
    'include_unavailable' => true,
]);

$formatted = array_map(function ($s) {
    return [
        'time'               => substr($s['time'], 0, 5),
        'time_display'       => $s['time_display'],
        'start_at'           => $s['start_at'],
        'end_at'             => $s['end_at'],
        'is_available'       => $s['is_available'],
        'unavailable_reason' => $s['unavailable_reason'] ?? null,
        'location_type'      => $s['location_type'] ?? null,
        'location_label'     => $s['location_label'] ?? null,
    ];
}, $slots);

$profile = getProfessionalProfile($rid);
$dateObj = new DateTime($date, new DateTimeZone($profile['timezone'] ?? 'America/New_York'));

api_success([
    'service_name'    => $service['name'],
    'service_id'      => (int)$service['id'],
    'date'            => $date,
    'date_display'    => $dateObj->format('l, F j, Y'),
    'duration_minutes' => (int)$service['duration_minutes'],
    'slots'           => $formatted,
    'total_available'  => count(array_filter($formatted, fn($s) => $s['is_available'])),
]);
