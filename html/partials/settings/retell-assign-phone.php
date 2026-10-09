<?php
/**
 * Retell API: Assign a phone number to an agent.
 * Updates the phone number in Retell to set inbound_agents and outbound_agents.
 * Also updates the local restaurant_prompts record.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../helpers/retell-api.php';

requireAuth();
if (!isAdmin() && !isSuperAdmin()) {
    http_response_code(403);
    exit('Forbidden');
}
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo '<div class="alert alert-danger">Invalid CSRF token.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
$pdo = db();
$promptId = (int)($_POST['id'] ?? 0);
$phoneNumber = trim($_POST['phone_number'] ?? '');

if ($promptId <= 0 || $phoneNumber === '') {
    echo '<div class="alert alert-danger">Missing prompt ID or phone number.</div>';
    exit;
}

// Fetch the local record
$stmt = $pdo->prepare("SELECT * FROM restaurant_prompts WHERE id = ? AND restaurant_id = ?");
$stmt->execute([$promptId, $restaurantId]);
$prompt = $stmt->fetch();

if (!$prompt) {
    echo '<div class="alert alert-danger">Agent not found.</div>';
    exit;
}

$agentId = $prompt['agent_id'] ?? '';
if ($agentId === '') {
    echo '<div class="alert alert-danger">This agent has no Retell agent ID. Create the agent first.</div>';
    exit;
}

// Build the inbound webhook URL from the agent's webhook_url
$webhookUrl = $prompt['webhook_url'] ?? '';
if ($webhookUrl === '') {
    $webhookUrl = 'https://zozocal.com/api/retell/pro-webhook.php?id=' . $agentId;
}

// Assign phone number to this agent in Retell with inbound webhook
$result = retellUpdatePhoneNumber($phoneNumber, [
    'inbound_agents' => [
        ['agent_id' => $agentId, 'weight' => 1],
    ],
    'inbound_webhook_url' => $webhookUrl,
]);

if (!$result['success']) {
    echo '<div class="alert alert-danger">Failed to assign phone number in Retell: ' . htmlspecialchars($result['error']) . '</div>';
    exit;
}

// Update local record
$pdo->prepare("UPDATE restaurant_prompts SET phone_number = ?, inbound_webhook_url = ? WHERE id = ?")
    ->execute([$phoneNumber, $webhookUrl, $promptId]);

echo '<div class="alert alert-success">Phone number <code>' . htmlspecialchars($phoneNumber) . '</code> assigned to agent <code>' . htmlspecialchars($agentId) . '</code> with inbound webhook.</div>';
