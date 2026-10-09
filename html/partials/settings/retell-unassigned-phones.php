<?php
/**
 * Retell API: Fetch phone numbers not assigned to any agent.
 * Returns JSON array for use in a dropdown.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/retell-api.php';

requireAuth();
if (!isAdmin() && !isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

header('Content-Type: application/json');

$result = retellListPhoneNumbers();

if (!$result['success']) {
    echo json_encode(['error' => $result['error'] ?? 'Failed to fetch phone numbers']);
    exit;
}

$phones = $result['data'] ?? [];
$unassigned = [];

foreach ($phones as $phone) {
    $inbound = $phone['inbound_agents'] ?? $phone['inbound_agent'] ?? [];
    $outbound = $phone['outbound_agents'] ?? $phone['outbound_agent'] ?? [];

    // Normalize: inbound_agent (deprecated) may be a string, inbound_agents is an array
    if (is_string($inbound)) {
        $inbound = $inbound !== '' ? [$inbound] : [];
    }
    if (is_string($outbound)) {
        $outbound = $outbound !== '' ? [$outbound] : [];
    }

    $hasAgent = !empty($inbound) || !empty($outbound);

    if (!$hasAgent) {
        $unassigned[] = [
            'phone_number' => $phone['phone_number'] ?? '',
            'nickname' => $phone['nickname'] ?? '',
        ];
    }
}

echo json_encode($unassigned);
