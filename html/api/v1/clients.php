<?php
/**
 * GET  /api/v1/clients.php                             — List clients
 * GET  /api/v1/clients.php?id=10                       — Single client + appointments
 * PUT  /api/v1/clients.php?id=10&action=preferences    — Update preferences
 * POST /api/v1/clients.php                             — Create client          (manager)
 * PUT  /api/v1/clients.php?id=10                       — Update client, partial (manager)
 *
 * Create / update validation mirrors html/partials/professional/save-client.php.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../helpers/validation.php';

$auth   = api_authenticate();
$rid    = $auth['restaurant_id'];
$method = api_method();

if ($method === 'GET') {
    $id = api_query('id');
    if ($id) {
        handleGetClient($rid, (int)$id);
    } else {
        handleListClients($rid);
    }
} elseif ($method === 'PUT' && api_query('action') === 'preferences') {
    handleUpdatePreferences($rid, (int)api_query('id'));
} elseif ($method === 'POST') {
    api_require_role($auth, 'manager');
    handleSaveClient($rid, null);
} elseif ($method === 'PUT') {
    api_require_role($auth, 'manager');
    handleSaveClient($rid, (int)api_query('id'));
} else {
    api_error('Method not allowed.', 'METHOD_NOT_ALLOWED', 405);
}

function handleListClients(int $rid): void {
    $search  = trim(api_query('search', ''));
    $page    = max(1, (int)api_query('page', 1));
    $perPage = min(100, max(1, (int)api_query('per_page', 50)));
    $offset  = ($page - 1) * $perPage;

    $pdo = db();
    $where  = ["restaurant_id = ?"];
    $params = [$rid];

    if ($search !== '') {
        $like = "%{$search}%";
        $where[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
        $params = array_merge($params, [$like, $like, $like, $like]);
    }

    $whereSQL = implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM professional_clients WHERE {$whereSQL}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT * FROM professional_clients WHERE {$whereSQL}
         ORDER BY last_name ASC, first_name ASC
         LIMIT {$perPage} OFFSET {$offset}"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $clients = array_map('formatClient', $rows);

    api_success([
        'clients'  => $clients,
        'total'    => $total,
        'page'     => $page,
        'per_page' => $perPage,
    ]);
}

function handleGetClient(int $rid, int $id): void {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM professional_clients WHERE id = ? AND restaurant_id = ? LIMIT 1");
    $stmt->execute([$id, $rid]);
    $client = $stmt->fetch();

    if (!$client) {
        api_error('Client not found.', 'NOT_FOUND', 404);
    }

    // Upcoming appointments
    $stmt = $pdo->prepare(
        "SELECT * FROM professional_appointments
         WHERE client_id = ? AND restaurant_id = ? AND status IN ('pending','confirmed') AND start_at >= NOW()
         ORDER BY start_at ASC LIMIT 20"
    );
    $stmt->execute([$id, $rid]);
    $upcoming = $stmt->fetchAll();

    // Past appointments
    $stmt = $pdo->prepare(
        "SELECT * FROM professional_appointments
         WHERE client_id = ? AND restaurant_id = ? AND (status IN ('completed','cancelled','no_show') OR start_at < NOW())
         ORDER BY start_at DESC LIMIT 20"
    );
    $stmt->execute([$id, $rid]);
    $past = $stmt->fetchAll();

    api_success([
        'client'                => formatClient($client),
        'upcoming_appointments' => array_map('formatClientAppointment', $upcoming),
        'past_appointments'     => array_map('formatClientAppointment', $past),
    ]);
}

function handleUpdatePreferences(int $rid, int $id): void {
    if (!$id) {
        api_error('id query parameter is required.', 'VALIDATION_ERROR', 400);
    }

    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM professional_clients WHERE id = ? AND restaurant_id = ? LIMIT 1");
    $stmt->execute([$id, $rid]);
    $client = $stmt->fetch();

    if (!$client) {
        api_error('Client not found.', 'NOT_FOUND', 404);
    }

    $body    = api_json_body();
    $updates = [];
    $params  = [];
    $changed = [];

    if (isset($body['marketing_opt_in'])) {
        $updates[] = "marketing_opt_in = ?";
        $params[]  = $body['marketing_opt_in'] ? 1 : 0;
        $changed[] = 'marketing_opt_in';
    }
    if (isset($body['preferred_contact_method'])) {
        $val = $body['preferred_contact_method'];
        if (!in_array($val, ['email', 'phone', 'sms', ''], true)) {
            api_error('preferred_contact_method must be email, phone, sms, or empty.', 'VALIDATION_ERROR', 400);
        }
        $updates[] = "preferred_contact_method = ?";
        $params[]  = $val === '' ? null : $val;
        $changed[] = 'preferred_contact_method';
    }

    if (empty($updates)) {
        api_error('No preferences provided.', 'VALIDATION_ERROR', 400);
    }

    $params[] = $id;
    $pdo->prepare("UPDATE professional_clients SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);

    api_success([
        'updated' => $changed,
        'message' => 'Preferences updated.',
    ]);
}

function handleSaveClient(int $rid, ?int $clientId): void {
    $pdo = db();
    $fields = [
        'first_name', 'last_name', 'email', 'phone', 'birth_date', 'preferred_contact_method',
        'marketing_opt_in', 'notes', 'internal_notes', 'service_address_line1', 'service_city',
        'service_state', 'service_postal_code', 'last_service_date',
    ];

    $current = array_fill_keys($fields, '');
    $current['marketing_opt_in'] = false;
    if ($clientId !== null) {
        $stmt = $pdo->prepare("SELECT * FROM professional_clients WHERE id = ? AND restaurant_id = ?");
        $stmt->execute([$clientId, $rid]);
        $row = $stmt->fetch();
        if (!$row) {
            api_error('Client not found.', 'NOT_FOUND', 404);
        }
        $current = array_intersect_key($row, $current) + $current;
    }
    $d = array_merge($current, array_intersect_key(api_json_body(), $current));

    $v = [];
    foreach ($fields as $field) {
        $v[$field] = $field === 'marketing_opt_in' ? (api_bool($d[$field]) ? 1 : 0) : trim((string)$d[$field]);
    }

    if ($v['first_name'] === '' || $v['last_name'] === '') {
        api_error('first_name and last_name are required.', 'VALIDATION_ERROR');
    }
    if ($v['email'] !== '' && !validate_email($v['email'])) {
        api_error('Please enter a valid email address.', 'VALIDATION_ERROR');
    }
    foreach (['birth_date', 'last_service_date'] as $dateField) {
        if ($v[$dateField] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v[$dateField])) {
            api_error("{$dateField} must be YYYY-MM-DD.", 'VALIDATION_ERROR');
        }
    }
    if (!in_array($v['preferred_contact_method'], ['', 'email', 'phone', 'sms'], true)) {
        api_error('preferred_contact_method must be email, phone or sms.', 'VALIDATION_ERROR');
    }
    if ($v['email'] !== '') {
        $dup = $pdo->prepare("SELECT id FROM professional_clients WHERE restaurant_id = ? AND email = ? AND id != ? LIMIT 1");
        $dup->execute([$rid, $v['email'], $clientId ?? 0]);
        if ($dup->fetch()) {
            api_error('A client with this email already exists.', 'DUPLICATE');
        }
    }

    // Empty strings are stored as NULL, as the web form does
    $values = array_map(fn($x) => $x === '' ? null : $x, array_values($v));

    if ($clientId !== null) {
        $sets = implode(', ', array_map(fn($f) => "{$f} = ?", $fields));
        $pdo->prepare("UPDATE professional_clients SET {$sets}, updated_at = NOW() WHERE id = ? AND restaurant_id = ?")
            ->execute(array_merge($values, [$clientId, $rid]));
        $status = 200;
    } else {
        $placeholders = implode(', ', array_fill(0, count($fields), '?'));
        $stmt = $pdo->prepare(
            "INSERT INTO professional_clients (restaurant_id, " . implode(', ', $fields) . ", created_at, updated_at)
             VALUES (?, {$placeholders}, NOW(), NOW())
             RETURNING id"
        );
        $stmt->execute(array_merge([$rid], $values));
        $clientId = (int)$stmt->fetchColumn();
        $status = 201;
    }

    $stmt = $pdo->prepare("SELECT * FROM professional_clients WHERE id = ?");
    $stmt->execute([$clientId]);
    api_success(['client' => formatClient($stmt->fetch())], $status);
}

function formatClient(array $c): array {
    return [
        'id'                       => (int)$c['id'],
        'first_name'               => $c['first_name'],
        'last_name'                => $c['last_name'],
        'email'                    => $c['email'] ?? null,
        'phone'                    => $c['phone'] ?? null,
        'birth_date'               => $c['birth_date'] ?? null,
        'notes'                    => $c['notes'] ?? null,
        'internal_notes'           => $c['internal_notes'] ?? null,
        'last_service_date'        => $c['last_service_date'] ?? null,
        'service_address_line1'    => $c['service_address_line1'] ?? null,
        'service_city'             => $c['service_city'] ?? null,
        'service_state'            => $c['service_state'] ?? null,
        'service_postal_code'      => $c['service_postal_code'] ?? null,
        'preferred_contact_method' => $c['preferred_contact_method'] ?? null,
        'marketing_opt_in'         => (bool)($c['marketing_opt_in'] ?? false),
        'last_appointment_at'      => $c['last_appointment_at'] ?? null,
        'created_at'               => $c['created_at'] ?? '',
    ];
}

function formatClientAppointment(array $a): array {
    return [
        'id'                => (int)$a['id'],
        'confirmation_code' => $a['confirmation_code'],
        'status'            => $a['status'],
        'appointment_date'  => $a['appointment_date'],
        'start_at'          => $a['start_at'],
        'end_at'            => $a['end_at'],
        'service_name'      => $a['service_name'] ?? '',
        'duration_minutes'  => (int)($a['duration_minutes'] ?? 0),
        'price'             => $a['price'] ?? null,
    ];
}
