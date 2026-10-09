<?php
/**
 * Message logs (read-only)
 *
 * GET /api/v1/messages.php?channel=sms|voice|email          — List, newest first
 *     Filters: direction=inbound|outbound, search=text, client_id=N, page=N, per_page=N (max 100)
 * GET /api/v1/messages.php?channel=sms|voice|email&id=N     — One message, including call transcript
 *                                                             / email HTML body
 *
 * Mirrors html/partials/messages/{sms,voice,email}-log.php.
 */

require_once __DIR__ . '/_bootstrap.php';

api_require_method('GET');
$auth = api_authenticate();
$rid  = $auth['restaurant_id'];

$channels = [
    'sms'   => ['table' => 'sms_message_log',   'search' => ['from_number', 'to_number', 'body'],                    'omit' => []],
    'voice' => ['table' => 'call_logs',         'search' => ['from_number', 'to_number', 'call_summary'],            'omit' => ['transcript', 'metadata']],
    'email' => ['table' => 'email_message_log', 'search' => ['from_address', 'to_address', 'subject', 'body_text'], 'omit' => ['body_html']],
];

$channel = api_query('channel');
if (!isset($channels[$channel])) {
    api_error('channel must be sms, voice or email.', 'VALIDATION_ERROR');
}
$table = $channels[$channel]['table'];
$pdo = db();

// --- Single message ---
if (api_query('id') !== null) {
    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([(int)api_query('id'), $rid]);
    $message = $stmt->fetch();
    if (!$message) {
        api_error('Message not found.', 'NOT_FOUND', 404);
    }
    api_success(['message' => $message]);
}

// --- List ---
$where = "WHERE restaurant_id = ?";
$params = [$rid];

$direction = api_query('direction', '');
if ($direction === 'inbound' || $direction === 'outbound') {
    $where .= " AND direction = ?";
    $params[] = $direction;
}
if (api_query('client_id') !== null) {
    $where .= " AND client_id = ?";
    $params[] = (int)api_query('client_id');
}
$search = trim((string)api_query('search', ''));
if ($search !== '') {
    $where .= " AND (" . implode(' OR ', array_map(fn($c) => "{$c} ILIKE ?", $channels[$channel]['search'])) . ")";
    foreach ($channels[$channel]['search'] as $unused) {
        $params[] = "%{$search}%";
    }
}

$page = max(1, (int)api_query('page', 1));
$perPage = max(1, min(100, (int)api_query('per_page', 25)));
$offset = ($page - 1) * $perPage;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} {$where}");
$countStmt->execute($params);

$stmt = $pdo->prepare("SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}");
$stmt->execute($params);
$omit = array_flip($channels[$channel]['omit']);
$messages = array_map(fn($m) => array_diff_key($m, $omit), $stmt->fetchAll());

api_success([
    'channel'  => $channel,
    'messages' => $messages,
    'total'    => (int)$countStmt->fetchColumn(),
    'page'     => $page,
    'per_page' => $perPage,
]);
