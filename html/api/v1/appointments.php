<?php
/**
 * Appointments REST API
 *
 * GET    /api/v1/appointments.php                          — List appointments
 * GET    /api/v1/appointments.php?id=42                    — Single appointment by ID
 * GET    /api/v1/appointments.php?action=lookup&...        — Lookup by code or phone
 * POST   /api/v1/appointments.php                          — Book new appointment
 * PUT    /api/v1/appointments.php?code=ABCD1234            — Modify appointment
 * POST   /api/v1/appointments.php?action=confirm&code=X    — Confirm
 * POST   /api/v1/appointments.php?action=cancel&code=X     — Cancel
 * POST   /api/v1/appointments.php?action=complete&code=X   — Complete
 * POST   /api/v1/appointments.php?action=no-show&code=X    — No-show
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../helpers/professional-availability.php';
require_once __DIR__ . '/../../../helpers/professional-voice-api.php';
require_once __DIR__ . '/../../../helpers/professional-booking.php';

$auth   = api_authenticate();
$rid    = $auth['restaurant_id'];
$method = api_method();

// Resolve the booking slug for this restaurant
$profile = getProfessionalProfile($rid);
if (!$profile) {
    api_error('Professional profile not configured.', 'NOT_FOUND', 404);
}
$slug = $profile['booking_slug'];

// --- Route ---
if ($method === 'GET') {
    $action = api_query('action');
    if ($action === 'lookup') {
        handleLookup($slug);
    } elseif (api_query('id')) {
        handleGetById($rid, (int)api_query('id'));
    } else {
        handleList($rid, $profile);
    }
} elseif ($method === 'POST') {
    $action = api_query('action');
    if ($action === 'confirm') {
        handleConfirm($slug);
    } elseif ($action === 'cancel') {
        handleCancel($slug);
    } elseif ($action === 'complete') {
        handleComplete($rid);
    } elseif ($action === 'no-show') {
        handleNoShow($rid);
    } else {
        handleCreate($slug);
    }
} elseif ($method === 'PUT') {
    handleModify($slug);
} else {
    api_error('Method not allowed.', 'METHOD_NOT_ALLOWED', 405);
}

// ===================== Handlers =====================

function handleList(int $rid, array $profile): void {
    $tz = $profile['timezone'] ?? 'America/New_York';
    $today = (new DateTime('now', new DateTimeZone($tz)))->format('Y-m-d');

    $startDate = api_query('start_date', $today);
    $endDate   = api_query('end_date', date('Y-m-d', strtotime($startDate . ' +30 days')));
    $status    = api_query('status', 'all');
    $clientId  = api_query('client_id');
    $serviceId = api_query('service_id');
    $page      = max(1, (int)api_query('page', 1));
    $perPage   = min(100, max(1, (int)api_query('per_page', 50)));
    $offset    = ($page - 1) * $perPage;

    $pdo = db();
    $where  = ["a.restaurant_id = ?"];
    $params = [$rid];

    // Date range
    $where[]  = "a.appointment_date >= ?";
    $params[] = $startDate;
    $where[]  = "a.appointment_date <= ?";
    $params[] = $endDate;

    // Status filter
    if ($status === 'active') {
        $where[] = "a.status IN ('pending','confirmed')";
    } elseif ($status !== 'all') {
        $where[]  = "a.status = ?";
        $params[] = $status;
    }

    if ($clientId) {
        $where[]  = "a.client_id = ?";
        $params[] = (int)$clientId;
    }
    if ($serviceId) {
        $where[]  = "a.service_id = ?";
        $params[] = (int)$serviceId;
    }

    $whereSQL = implode(' AND ', $where);

    // Count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM professional_appointments a WHERE {$whereSQL}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // Fetch
    $sql = "SELECT a.*, c.first_name AS client_first_name, c.last_name AS client_last_name,
                   c.phone AS client_phone, c.email AS client_email
            FROM professional_appointments a
            LEFT JOIN professional_clients c ON c.id = a.client_id
            WHERE {$whereSQL}
            ORDER BY a.start_at ASC
            LIMIT {$perPage} OFFSET {$offset}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $appointments = array_map('formatAppointment', $rows);

    api_success([
        'appointments' => $appointments,
        'total'    => $total,
        'page'     => $page,
        'per_page' => $perPage,
    ]);
}

function handleGetById(int $rid, int $id): void {
    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT a.*, c.first_name AS client_first_name, c.last_name AS client_last_name,
                c.phone AS client_phone, c.email AS client_email
         FROM professional_appointments a
         LEFT JOIN professional_clients c ON c.id = a.client_id
         WHERE a.id = ? AND a.restaurant_id = ?
         LIMIT 1"
    );
    $stmt->execute([$id, $rid]);
    $row = $stmt->fetch();

    if (!$row) {
        api_error('Appointment not found.', 'NOT_FOUND', 404);
    }

    api_success(['appointment' => formatAppointment($row)]);
}

function handleLookup(string $slug): void {
    $code  = api_query('confirmation_code', '');
    $phone = api_query('client_phone', '');

    if ($code === '' && $phone === '') {
        api_error('Provide confirmation_code or client_phone.', 'VALIDATION_ERROR', 400);
    }

    $result = proVoiceLookupAppointment($slug, $code !== '' ? $code : $phone);

    if (!$result['success']) {
        api_error($result['error'] ?? 'Not found.', 'NOT_FOUND', 404);
    }

    api_success([
        'appointments' => $result['appointments'] ?? [],
        'total'        => $result['total_appointments'] ?? 0,
    ]);
}

function handleCreate(string $slug): void {
    $body = api_json_body();

    $required = ['service_id', 'date', 'time', 'client_name', 'client_phone'];
    foreach ($required as $f) {
        if (empty($body[$f])) {
            api_error("{$f} is required.", 'VALIDATION_ERROR', 400);
        }
    }

    $extras = [];
    foreach (['internal_notes', 'service_contact_name', 'service_phone', 'service_contact_method',
              'service_address_1', 'service_city', 'service_state', 'service_postal_code'] as $key) {
        if (isset($body[$key])) $extras[$key] = $body[$key];
    }

    $result = proVoiceBookAppointment(
        $slug,
        (int)$body['service_id'],
        $body['date'],
        $body['time'],
        $body['client_name'],
        $body['client_phone'],
        $body['client_email'] ?? '',
        $body['client_notes'] ?? '',
        $extras
    );

    if (!$result['success']) {
        $code = 400;
        $errCode = 'VALIDATION_ERROR';
        $msg = $result['error'] ?? 'Booking failed.';
        if (stripos($msg, 'conflict') !== false || stripos($msg, 'unavailable') !== false || stripos($msg, 'overlap') !== false) {
            $code = 409;
            $errCode = 'SLOT_UNAVAILABLE';
        }
        api_error($msg, $errCode, $code);
    }

    api_success($result, 201);
}

function handleModify(string $slug): void {
    $code = api_query('code', '');
    if ($code === '') {
        api_error('code query parameter is required.', 'VALIDATION_ERROR', 400);
    }

    $body = api_json_body();
    $required = ['service_id', 'date', 'time', 'client_name', 'client_phone'];
    foreach ($required as $f) {
        if (empty($body[$f])) {
            api_error("{$f} is required.", 'VALIDATION_ERROR', 400);
        }
    }

    $extras = [];
    foreach (['internal_notes', 'service_contact_name', 'service_phone', 'service_contact_method',
              'service_address_1', 'service_city', 'service_state', 'service_postal_code'] as $key) {
        if (isset($body[$key])) $extras[$key] = $body[$key];
    }

    $result = proVoiceModifyAppointment(
        $slug,
        $code,
        (int)$body['service_id'],
        $body['date'],
        $body['time'],
        $body['client_name'],
        $body['client_phone'],
        $body['client_email'] ?? '',
        $body['client_notes'] ?? '',
        $extras
    );

    if (!$result['success']) {
        $msg = $result['error'] ?? 'Modification failed.';
        if (stripos($msg, 'not found') !== false) {
            api_error($msg, 'NOT_FOUND', 404);
        } elseif (stripos($msg, 'conflict') !== false || stripos($msg, 'unavailable') !== false) {
            api_error($msg, 'SLOT_UNAVAILABLE', 409);
        } elseif (stripos($msg, 'cannot') !== false || stripos($msg, 'status') !== false) {
            api_error($msg, 'STATUS_INVALID', 422);
        }
        api_error($msg, 'VALIDATION_ERROR', 400);
    }

    api_success($result);
}

function handleConfirm(string $slug): void {
    $code = api_query('code', '');
    if ($code === '') {
        api_error('code query parameter is required.', 'VALIDATION_ERROR', 400);
    }

    $result = proVoiceConfirmAppointment($slug, $code);
    if (!$result['success']) {
        $msg = $result['error'] ?? 'Confirm failed.';
        if (stripos($msg, 'not found') !== false) {
            api_error($msg, 'NOT_FOUND', 404);
        }
        api_error($msg, 'STATUS_INVALID', 422);
    }
    api_success($result);
}

function handleCancel(string $slug): void {
    $code = api_query('code', '');
    if ($code === '') {
        api_error('code query parameter is required.', 'VALIDATION_ERROR', 400);
    }

    $result = proVoiceCancelAppointment($slug, $code);
    if (!$result['success']) {
        $msg = $result['error'] ?? 'Cancel failed.';
        if (stripos($msg, 'not found') !== false) {
            api_error($msg, 'NOT_FOUND', 404);
        } elseif (stripos($msg, 'notice') !== false || stripos($msg, 'window') !== false) {
            api_error($msg, 'CANCELLATION_WINDOW', 422);
        }
        api_error($msg, 'STATUS_INVALID', 422);
    }
    api_success($result);
}

function handleComplete(int $rid): void {
    $code = api_query('code', '');
    if ($code === '') {
        api_error('code query parameter is required.', 'VALIDATION_ERROR', 400);
    }

    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT * FROM professional_appointments
         WHERE confirmation_code = ? AND restaurant_id = ? LIMIT 1"
    );
    $stmt->execute([$code, $rid]);
    $apt = $stmt->fetch();

    if (!$apt) {
        api_error('Appointment not found.', 'NOT_FOUND', 404);
    }
    if ($apt['status'] !== 'confirmed') {
        api_error('Only confirmed appointments can be completed.', 'STATUS_INVALID', 422);
    }

    $stmt = $pdo->prepare(
        "UPDATE professional_appointments SET status = 'completed', completed_at = NOW(), updated_at = NOW()
         WHERE id = ?"
    );
    $stmt->execute([$apt['id']]);

    if (function_exists('professionalLogAppointmentActivity')) {
        professionalLogAppointmentActivity($rid, null, $apt['id'], 'appointment_completed', 'Marked completed via API.', $apt['status'], 'completed');
    }

    api_success([
        'confirmation_code' => $code,
        'message' => 'Appointment marked as completed.',
    ]);
}

function handleNoShow(int $rid): void {
    $code = api_query('code', '');
    if ($code === '') {
        api_error('code query parameter is required.', 'VALIDATION_ERROR', 400);
    }

    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT * FROM professional_appointments
         WHERE confirmation_code = ? AND restaurant_id = ? LIMIT 1"
    );
    $stmt->execute([$code, $rid]);
    $apt = $stmt->fetch();

    if (!$apt) {
        api_error('Appointment not found.', 'NOT_FOUND', 404);
    }
    if ($apt['status'] !== 'confirmed') {
        api_error('Only confirmed appointments can be marked as no-show.', 'STATUS_INVALID', 422);
    }

    $stmt = $pdo->prepare(
        "UPDATE professional_appointments SET status = 'no_show', updated_at = NOW()
         WHERE id = ?"
    );
    $stmt->execute([$apt['id']]);

    if (function_exists('professionalLogAppointmentActivity')) {
        professionalLogAppointmentActivity($rid, null, $apt['id'], 'appointment_no_show', 'Marked no-show via API.', $apt['status'], 'no_show');
    }

    api_success([
        'confirmation_code' => $code,
        'message' => 'Appointment marked as no-show.',
    ]);
}

// ===================== Formatting =====================

function formatAppointment(array $a): array {
    return [
        'id'                    => (int)$a['id'],
        'confirmation_code'     => $a['confirmation_code'],
        'status'                => $a['status'],
        'source'                => $a['source'] ?? 'api',
        'appointment_date'      => $a['appointment_date'],
        'start_at'              => $a['start_at'],
        'end_at'                => $a['end_at'],
        'service' => [
            'id'               => $a['service_id'] ? (int)$a['service_id'] : null,
            'name'             => $a['service_name'] ?? '',
            'duration_minutes' => (int)($a['duration_minutes'] ?? 0),
            'price'            => $a['price'] ?? null,
            'currency_code'    => $a['currency_code'] ?? 'USD',
        ],
        'client' => [
            'id'         => $a['client_id'] ? (int)$a['client_id'] : null,
            'first_name' => $a['client_first_name'] ?? '',
            'last_name'  => $a['client_last_name'] ?? '',
            'phone'      => $a['client_phone'] ?? '',
            'email'      => $a['client_email'] ?? '',
        ],
        'location_type'          => $a['location_type'] ?? null,
        'location_label'         => $a['location_label'] ?? null,
        'client_notes'           => $a['client_notes'] ?? '',
        'internal_notes'         => $a['internal_notes'] ?? '',
        'service_contact_name'   => $a['service_contact_name'] ?? '',
        'service_phone'          => $a['service_phone'] ?? '',
        'service_contact_method' => $a['service_contact_method'] ?? '',
        'service_address_1'      => $a['service_address_1'] ?? '',
        'service_city'           => $a['service_city'] ?? '',
        'service_state'          => $a['service_state'] ?? '',
        'service_postal_code'    => $a['service_postal_code'] ?? '',
        'buffer_before_minutes'  => (int)($a['buffer_before_minutes'] ?? 0),
        'buffer_after_minutes'   => (int)($a['buffer_after_minutes'] ?? 0),
        'created_at'             => $a['created_at'] ?? '',
    ];
}
