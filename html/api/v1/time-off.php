<?php
/**
 * Time off (blocked time)
 *
 * GET    /api/v1/time-off.php?start_date=2026-04-01&end_date=2026-04-30 — List blocks in a date range
 * POST   /api/v1/time-off.php            — Create block           (manager)
 * PUT    /api/v1/time-off.php?id=1       — Update block, partial  (manager)
 * DELETE /api/v1/time-off.php?id=1       — Delete block           (manager)
 *
 * Body: start_date, end_date (YYYY-MM-DD), is_all_day (default true), start_time / end_time
 * (HH:MM, required when not all day), reason, notes.
 * Validation mirrors html/partials/professional/save-time-off.php.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../helpers/professional-availability.php';

$auth   = api_authenticate();
$rid    = $auth['restaurant_id'];
$method = api_method();

if ($method === 'GET') {
    handleListTimeOff($rid);
} elseif ($method === 'POST') {
    api_require_role($auth, 'manager');
    handleSaveTimeOff($rid, null);
} elseif ($method === 'PUT') {
    api_require_role($auth, 'manager');
    handleSaveTimeOff($rid, (int)api_query('id'));
} elseif ($method === 'DELETE') {
    api_require_role($auth, 'manager');
    $stmt = db()->prepare("DELETE FROM professional_time_off WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([(int)api_query('id'), $rid]);
    if ($stmt->rowCount() === 0) {
        api_error('Time-off entry not found.', 'NOT_FOUND', 404);
    }
    api_success(['deleted' => true]);
} else {
    api_error('Method not allowed.', 'METHOD_NOT_ALLOWED', 405);
}

function handleListTimeOff(int $rid): void {
    $startDate = api_query('start_date');
    $endDate   = api_query('end_date');

    if (!$startDate || !$endDate) {
        api_error('start_date and end_date are required.', 'VALIDATION_ERROR', 400);
    }

    $blocks = getProfessionalTimeOffBlocks($rid, $startDate . ' 00:00:00', $endDate . ' 23:59:59');
    api_success(['time_off' => array_map('formatTimeOff', $blocks)]);
}

function handleSaveTimeOff(int $rid, ?int $timeOffId): void {
    $pdo = db();

    $current = ['start_date' => '', 'end_date' => '', 'start_time' => '', 'end_time' => '', 'is_all_day' => true, 'reason' => '', 'notes' => ''];
    if ($timeOffId !== null) {
        $stmt = $pdo->prepare("SELECT * FROM professional_time_off WHERE id = ? AND restaurant_id = ?");
        $stmt->execute([$timeOffId, $rid]);
        $row = $stmt->fetch();
        if (!$row) {
            api_error('Time-off entry not found.', 'NOT_FOUND', 404);
        }
        $current = [
            'start_date' => substr($row['starts_at'], 0, 10), 'end_date' => substr($row['ends_at'], 0, 10),
            'start_time' => substr($row['starts_at'], 11, 5), 'end_time' => substr($row['ends_at'], 11, 5),
            'is_all_day' => (bool)$row['is_all_day'], 'reason' => (string)$row['reason'], 'notes' => (string)$row['notes'],
        ];
    }
    $d = array_merge($current, array_intersect_key(api_json_body(), $current));

    $startDate = trim((string)$d['start_date']);
    $endDate = trim((string)$d['end_date']);
    $isAllDay = api_bool($d['is_all_day']) ? 1 : 0;

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        api_error('start_date and end_date are required as YYYY-MM-DD.', 'VALIDATION_ERROR');
    }

    if ($isAllDay === 1) {
        $startsAt = $startDate . ' 00:00:00';
        $endsAt = $endDate . ' 23:59:59';
    } else {
        $startTime = substr(trim((string)$d['start_time']), 0, 5);
        $endTime = substr(trim((string)$d['end_time']), 0, 5);
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $startTime) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $endTime)) {
            api_error('start_time and end_time (24-hour HH:MM) are required when is_all_day is false.', 'VALIDATION_ERROR');
        }
        $startsAt = $startDate . ' ' . $startTime . ':00';
        $endsAt = $endDate . ' ' . $endTime . ':00';
    }

    if (strtotime($startsAt) === false || strtotime($endsAt) === false) {
        api_error('Invalid date or time.', 'VALIDATION_ERROR');
    }
    if (strtotime($startsAt) >= strtotime($endsAt)) {
        api_error('The start must be before the end.', 'VALIDATION_ERROR');
    }

    $values = [$startsAt, $endsAt, trim((string)$d['reason']) ?: null, trim((string)$d['notes']) ?: null, $isAllDay];

    if ($timeOffId !== null) {
        $pdo->prepare(
            "UPDATE professional_time_off SET starts_at = ?, ends_at = ?, reason = ?, notes = ?, is_all_day = ?, updated_at = NOW()
             WHERE id = ? AND restaurant_id = ?"
        )->execute(array_merge($values, [$timeOffId, $rid]));
        $status = 200;
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO professional_time_off (restaurant_id, starts_at, ends_at, reason, notes, is_all_day, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
             RETURNING id"
        );
        $stmt->execute(array_merge([$rid], $values));
        $timeOffId = (int)$stmt->fetchColumn();
        $status = 201;
    }

    $stmt = $pdo->prepare("SELECT * FROM professional_time_off WHERE id = ?");
    $stmt->execute([$timeOffId]);
    api_success(['time_off' => formatTimeOff($stmt->fetch())], $status);
}

function formatTimeOff(array $b): array {
    return [
        'id'         => (int)$b['id'],
        'starts_at'  => $b['starts_at'],
        'ends_at'    => $b['ends_at'],
        'reason'     => $b['reason'] ?? null,
        'notes'      => $b['notes'] ?? null,
        'is_all_day' => (bool)($b['is_all_day'] ?? false),
    ];
}
