<?php
/**
 * Recurring weekly availability windows
 *
 * GET    /api/v1/availability-rules.php                    — List active windows (?include_inactive=1)
 * POST   /api/v1/availability-rules.php                    — Create window             (manager)
 * PUT    /api/v1/availability-rules.php?id=1               — Update window, partial    (manager)
 *                                                             (send is_active to activate / deactivate)
 * DELETE /api/v1/availability-rules.php?id=1               — Delete window             (manager)
 *
 * weekday: 0 = Sunday … 6 = Saturday. Times are 24-hour HH:MM.
 * Validation mirrors html/partials/professional/save-availability.php.
 */

require_once __DIR__ . '/_bootstrap.php';

$auth   = api_authenticate();
$rid    = $auth['restaurant_id'];
$method = api_method();

if ($method === 'GET') {
    handleListRules($rid);
} elseif ($method === 'POST') {
    api_require_role($auth, 'manager');
    handleSaveRule($rid, null);
} elseif ($method === 'PUT') {
    api_require_role($auth, 'manager');
    handleSaveRule($rid, (int)api_query('id'));
} elseif ($method === 'DELETE') {
    api_require_role($auth, 'manager');
    $stmt = db()->prepare("DELETE FROM professional_availability_rules WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([(int)api_query('id'), $rid]);
    if ($stmt->rowCount() === 0) {
        api_error('Availability window not found.', 'NOT_FOUND', 404);
    }
    api_success(['deleted' => true]);
} else {
    api_error('Method not allowed.', 'METHOD_NOT_ALLOWED', 405);
}

function handleListRules(int $rid): void {
    $includeInactive = api_bool(api_query('include_inactive', false));
    $stmt = db()->prepare(
        "SELECT * FROM professional_availability_rules
         WHERE restaurant_id = ?" . ($includeInactive ? '' : ' AND is_active = 1') . "
         ORDER BY weekday ASC, start_time ASC"
    );
    $stmt->execute([$rid]);
    api_success(['rules' => array_map('formatRule', $stmt->fetchAll())]);
}

function handleSaveRule(int $rid, ?int $ruleId): void {
    $pdo = db();

    $current = ['weekday' => -1, 'start_time' => '', 'end_time' => '', 'location_type' => '', 'location_label' => '', 'is_active' => true];
    if ($ruleId !== null) {
        $stmt = $pdo->prepare("SELECT * FROM professional_availability_rules WHERE id = ? AND restaurant_id = ?");
        $stmt->execute([$ruleId, $rid]);
        $row = $stmt->fetch();
        if (!$row) {
            api_error('Availability window not found.', 'NOT_FOUND', 404);
        }
        $current = array_intersect_key($row, $current) + $current;
    }
    $d = array_merge($current, array_intersect_key(api_json_body(), $current));

    $weekday = (int)$d['weekday'];
    $startTime = substr(trim((string)$d['start_time']), 0, 5);
    $endTime = substr(trim((string)$d['end_time']), 0, 5);
    $locationType = trim((string)$d['location_type']);
    $locationLabel = trim((string)$d['location_label']);
    $isActive = api_bool($d['is_active']) ? 1 : 0;

    if ($weekday < 0 || $weekday > 6) {
        api_error('weekday must be 0 (Sunday) to 6 (Saturday).', 'VALIDATION_ERROR');
    }
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $startTime) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $endTime)) {
        api_error('start_time and end_time are required as 24-hour HH:MM.', 'VALIDATION_ERROR');
    }
    if ($startTime >= $endTime) {
        api_error('Start time must be before end time.', 'VALIDATION_ERROR');
    }
    if (!in_array($locationType, ['', 'in_person', 'phone', 'video', 'onsite', 'custom'], true)) {
        api_error('location_type must be one of in_person, phone, video, onsite, custom.', 'VALIDATION_ERROR');
    }

    if ($isActive === 1) {
        $overlap = $pdo->prepare(
            "SELECT id FROM professional_availability_rules
             WHERE restaurant_id = ? AND weekday = ? AND is_active = 1 AND id != ?
               AND NOT (end_time <= ? OR start_time >= ?)"
        );
        $overlap->execute([$rid, $weekday, $ruleId ?? 0, $startTime, $endTime]);
        if ($overlap->fetch()) {
            api_error('This time window overlaps another active availability window on the same day.', 'OVERLAP', 409);
        }
    }

    $values = [$weekday, $startTime, $endTime, $locationType ?: null, $locationLabel ?: null, $isActive];

    if ($ruleId !== null) {
        $pdo->prepare(
            "UPDATE professional_availability_rules SET
                weekday = ?, start_time = ?, end_time = ?, location_type = ?, location_label = ?,
                is_active = ?, updated_at = NOW()
             WHERE id = ? AND restaurant_id = ?"
        )->execute(array_merge($values, [$ruleId, $rid]));
        $status = 200;
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO professional_availability_rules (
                restaurant_id, weekday, start_time, end_time, location_type, location_label,
                is_active, created_at, updated_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
             RETURNING id"
        );
        $stmt->execute(array_merge([$rid], $values));
        $ruleId = (int)$stmt->fetchColumn();
        $status = 201;
    }

    $stmt = $pdo->prepare("SELECT * FROM professional_availability_rules WHERE id = ?");
    $stmt->execute([$ruleId]);
    api_success(['rule' => formatRule($stmt->fetch())], $status);
}

function formatRule(array $r): array {
    $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    return [
        'id'             => (int)$r['id'],
        'weekday'        => (int)$r['weekday'],
        'weekday_name'   => $dayNames[(int)$r['weekday']] ?? '',
        'start_time'     => $r['start_time'],
        'end_time'       => $r['end_time'],
        'location_type'  => $r['location_type'] ?? null,
        'location_label' => $r['location_label'] ?? null,
        'is_active'      => (bool)$r['is_active'],
    ];
}
