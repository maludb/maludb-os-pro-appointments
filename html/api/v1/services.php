<?php
/**
 * GET  /api/v1/services.php                        — List services (?include_inactive=1)
 * GET  /api/v1/services.php?id=1                   — Get single service
 * POST /api/v1/services.php                        — Create service            (manager)
 * PUT  /api/v1/services.php?id=1                   — Update service, partial   (manager)
 * POST /api/v1/services.php?action=activate&id=1   — Activate                  (manager)
 * POST /api/v1/services.php?action=deactivate&id=1 — Deactivate                (manager)
 *
 * Validation mirrors html/partials/professional/save-service.php.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../helpers/professional-availability.php';

$auth   = api_authenticate();
$rid    = $auth['restaurant_id'];
$method = api_method();

if ($method === 'GET') {
    handleGetServices($rid);
} elseif ($method === 'POST' && in_array(api_query('action'), ['activate', 'deactivate'], true)) {
    api_require_role($auth, 'manager');
    handleSetServiceActive($rid, (int)api_query('id'), api_query('action') === 'activate');
} elseif ($method === 'POST') {
    api_require_role($auth, 'manager');
    handleSaveService($rid, null);
} elseif ($method === 'PUT') {
    api_require_role($auth, 'manager');
    handleSaveService($rid, (int)api_query('id'));
} else {
    api_error('Method not allowed.', 'METHOD_NOT_ALLOWED', 405);
}

function handleGetServices(int $rid): void {
    $serviceId = api_query('id');

    // --- Single service ---
    if ($serviceId !== null) {
        $service = getProfessionalService($rid, (int)$serviceId, ['allow_inactive' => true]);
        if (!$service) {
            api_error('Service not found.', 'NOT_FOUND', 404);
        }
        api_success(['service' => formatService($service)]);
    }

    // --- List services ---
    $includeInactive = filter_var(api_query('include_inactive', false), FILTER_VALIDATE_BOOLEAN);

    $pdo = db();
    $sql = "SELECT * FROM professional_services WHERE restaurant_id = ?";
    $params = [$rid];
    if (!$includeInactive) {
        $sql .= " AND is_active = 1";
    }
    $sql .= " ORDER BY sort_order ASC, name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Attach effective buffers from profile
    $profile = getProfessionalProfile($rid);
    $services = array_map(function ($row) use ($profile) {
        $row['effective_buffer_before_minutes'] = ($row['buffer_before_minutes'] > 0)
            ? $row['buffer_before_minutes']
            : ($profile['default_buffer_before_minutes'] ?? 0);
        $row['effective_buffer_after_minutes'] = ($row['buffer_after_minutes'] > 0)
            ? $row['buffer_after_minutes']
            : ($profile['default_buffer_after_minutes'] ?? 0);
        return formatService($row);
    }, $rows);

    api_success([
        'services' => $services,
        'total'    => count($services),
    ]);
}

function handleSaveService(int $rid, ?int $serviceId): void {
    $pdo = db();
    $in = api_json_body();

    // Updates are partial: start from the stored service and apply the fields sent
    $current = [
        'name' => '', 'description' => '', 'duration_minutes' => 0, 'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0, 'price' => null, 'location_type' => '', 'location_label' => '',
        'color' => '#0d6efd', 'sort_order' => 0, 'is_active' => true, 'is_public_bookable' => true,
    ];
    if ($serviceId !== null) {
        $stmt = $pdo->prepare("SELECT * FROM professional_services WHERE id = ? AND restaurant_id = ?");
        $stmt->execute([$serviceId, $rid]);
        $row = $stmt->fetch();
        if (!$row) {
            api_error('Service not found.', 'NOT_FOUND', 404);
        }
        $current = array_intersect_key($row, $current) + $current;
    }
    $d = array_merge($current, array_intersect_key($in, $current));

    $name = trim((string)$d['name']);
    $description = trim((string)$d['description']);
    $duration = (int)$d['duration_minutes'];
    $bufferBefore = (int)$d['buffer_before_minutes'];
    $bufferAfter = (int)$d['buffer_after_minutes'];
    $locationType = trim((string)$d['location_type']);
    $locationLabel = trim((string)$d['location_label']);
    $color = trim((string)$d['color']);
    $sortOrder = (int)$d['sort_order'];
    $isActive = api_bool($d['is_active']) ? 1 : 0;
    $isPublic = api_bool($d['is_public_bookable']) ? 1 : 0;

    if ($name === '') api_error('Service name is required.', 'VALIDATION_ERROR');
    if ($duration < 5) api_error('Duration must be at least 5 minutes.', 'VALIDATION_ERROR');
    if ($bufferBefore < 0 || $bufferAfter < 0) api_error('Buffers cannot be negative.', 'VALIDATION_ERROR');
    if ($sortOrder < 0) api_error('Sort order cannot be negative.', 'VALIDATION_ERROR');
    if (!in_array($locationType, ['', 'in_person', 'phone', 'video', 'onsite', 'custom'], true)) {
        api_error('location_type must be one of in_person, phone, video, onsite, custom.', 'VALIDATION_ERROR');
    }
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        api_error('color must be a hex color such as #0d6efd.', 'VALIDATION_ERROR');
    }

    $price = null;
    if ($d['price'] !== null && $d['price'] !== '') {
        if (!is_numeric($d['price']) || (float)$d['price'] < 0) {
            api_error('Price must be a valid non-negative amount.', 'VALIDATION_ERROR');
        }
        $price = number_format((float)$d['price'], 2, '.', '');
    }

    $dup = $pdo->prepare("SELECT id FROM professional_services WHERE restaurant_id = ? AND name = ? AND id != ?");
    $dup->execute([$rid, $name, $serviceId ?? 0]);
    if ($dup->fetch()) {
        api_error('A service with this name already exists.', 'DUPLICATE');
    }

    $values = [
        $name, $description ?: null, $duration, $bufferBefore, $bufferAfter, $price,
        $locationType ?: null, $locationLabel ?: null, $color, $sortOrder, $isActive, $isPublic,
    ];

    if ($serviceId !== null) {
        $pdo->prepare(
            "UPDATE professional_services SET
                name = ?, description = ?, duration_minutes = ?, buffer_before_minutes = ?,
                buffer_after_minutes = ?, price = ?, currency_code = 'USD', location_type = ?,
                location_label = ?, color = ?, sort_order = ?, is_active = ?, is_public_bookable = ?,
                updated_at = NOW()
             WHERE id = ? AND restaurant_id = ?"
        )->execute(array_merge($values, [$serviceId, $rid]));
        $status = 200;
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO professional_services (
                restaurant_id, name, description, duration_minutes, buffer_before_minutes,
                buffer_after_minutes, price, currency_code, location_type, location_label, color,
                sort_order, is_active, is_public_bookable, created_at, updated_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, 'USD', ?, ?, ?, ?, ?, ?, NOW(), NOW())
             RETURNING id"
        );
        $stmt->execute(array_merge([$rid], $values));
        $serviceId = (int)$stmt->fetchColumn();
        $status = 201;
    }

    $service = getProfessionalService($rid, $serviceId, ['allow_inactive' => true]);
    api_success(['service' => formatService($service)], $status);
}

function handleSetServiceActive(int $rid, int $serviceId, bool $active): void {
    $stmt = db()->prepare(
        "UPDATE professional_services SET is_active = ?, updated_at = NOW() WHERE id = ? AND restaurant_id = ?"
    );
    $stmt->execute([$active ? 1 : 0, $serviceId, $rid]);
    if ($stmt->rowCount() === 0) {
        api_error('Service not found.', 'NOT_FOUND', 404);
    }
    api_success(['service' => formatService(getProfessionalService($rid, $serviceId, ['allow_inactive' => true]))]);
}

function formatService(array $s): array {
    return [
        'id'                => (int)$s['id'],
        'name'              => $s['name'],
        'description'       => $s['description'] ?? null,
        'duration_minutes'  => (int)$s['duration_minutes'],
        'buffer_before_minutes' => (int)($s['effective_buffer_before_minutes'] ?? $s['buffer_before_minutes']),
        'buffer_after_minutes'  => (int)($s['effective_buffer_after_minutes'] ?? $s['buffer_after_minutes']),
        'price'             => $s['price'],
        'currency_code'     => $s['currency_code'] ?? 'USD',
        'location_type'     => $s['location_type'] ?? null,
        'location_label'    => $s['location_label'] ?? null,
        'color'             => $s['color'] ?? null,
        'sort_order'        => (int)($s['sort_order'] ?? 0),
        'is_active'         => (bool)$s['is_active'],
        'is_public_bookable' => (bool)$s['is_public_bookable'],
    ];
}
