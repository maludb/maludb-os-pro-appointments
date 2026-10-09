<?php
/**
 * SMS Professional Context Webhook
 *
 * Called by the SMS agent to fetch company info, client info, and
 * conversation history — mirroring /api/retell/pro-webhook.php for voice.
 *
 * Auth: Authorization: Bearer <the business's mcp_api_key>.
 * Expects POST with: to_number, from_number
 * Returns JSON with: business, client, sms_history
 */

require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/restaurant.php';

header('Content-Type: application/json');

$logFile = __DIR__ . '/../../../logs/sms-pro-webhook.log';

function wlog(string $msg) {
    global $logFile;
    $ts = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$ts}] {$msg}\n", FILE_APPEND);
}

$toNumber   = trim($_POST['to_number'] ?? $_GET['to_number'] ?? '');
$fromNumber = trim($_POST['from_number'] ?? $_GET['from_number'] ?? '');

wlog("=== SMS CONTEXT REQUEST === to: {$toNumber}");

if ($toNumber === '' || $fromNumber === '') {
    wlog("ERROR: Missing to_number or from_number");
    echo json_encode(['error' => 'to_number and from_number are required']);
    exit;
}

$pdo = db();

// --- Look up company by TO number via restaurant_phone_numbers ---
$restaurantId = null;
$phoneNumberId = null;
$toDigits = normalizePhone($toNumber);
$toDigits10 = strlen($toDigits) > 10 ? substr($toDigits, -10) : $toDigits;

$rpnStmt = $pdo->prepare(
    "SELECT rpn.id as phone_number_id, rpn.restaurant_id, rpn.phone_number
     FROM restaurant_phone_numbers rpn
     JOIN restaurants r ON rpn.restaurant_id = r.id
     WHERE rpn.is_active = 1
     ORDER BY rpn.id"
);
$rpnStmt->execute();

foreach ($rpnStmt->fetchAll() as $rpn) {
    $rpnDigits = normalizePhone($rpn['phone_number'] ?? '');
    $rpnDigits10 = strlen($rpnDigits) > 10 ? substr($rpnDigits, -10) : $rpnDigits;
    if ($rpnDigits === $toDigits || $rpnDigits10 === $toDigits10) {
        $restaurantId = (int)$rpn['restaurant_id'];
        $phoneNumberId = (int)$rpn['phone_number_id'];
        wlog("MATCHED company restaurant_id={$restaurantId} via phone_number_id={$phoneNumberId}");
        break;
    }
}

if (!$restaurantId) {
    wlog("ERROR: No company found for number {$toNumber}");
    echo json_encode(['error' => 'No company found for this number']);
    exit;
}

// Only the SMS agent, which holds this business's MCP key (Settings → Integrations) as its Bearer token:
// the answer names a client and carries their messages.
require_once __DIR__ . '/../../../helpers/api-auth.php';
if (mcp_business_for_request() !== $restaurantId) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// --- Fetch company info from restaurants ---
$restStmt = $pdo->prepare(
    "SELECT id, name, slug, phone, email, address_line1, city, state, postal_code, website, timezone
     FROM restaurants WHERE id = ?"
);
$restStmt->execute([$restaurantId]);
$restaurant = $restStmt->fetch(PDO::FETCH_ASSOC);

$business = [
    'id'        => (int)$restaurant['id'],
    'name'      => $restaurant['name'],
    'slug'      => $restaurant['slug'],
    'phone'     => $restaurant['phone'] ?? '',
    'email'     => $restaurant['email'] ?? '',
    'address'   => trim(implode(', ', array_filter([
        $restaurant['address_line1'] ?? '',
        $restaurant['city'] ?? '',
        $restaurant['state'] ?? '',
        $restaurant['postal_code'] ?? '',
    ]))),
    'website'   => $restaurant['website'] ?? '',
    'timezone'  => $restaurant['timezone'] ?? 'America/Chicago',
    'today'     => date('Y-m-d'),
    'time'      => date('g:i A'),
    'day_of_week' => date('l'),
];

wlog("BUSINESS: {$business['name']}");

// --- Identify client by FROM number via professional_clients ---
$client = null;
$fromDigits = normalizePhone($fromNumber);
$fromDigits10 = strlen($fromDigits) > 10 ? substr($fromDigits, -10) : $fromDigits;

$clientStmt = $pdo->prepare(
    "SELECT * FROM professional_clients WHERE restaurant_id = ? AND phone IS NOT NULL AND phone != ''"
);
$clientStmt->execute([$restaurantId]);

foreach ($clientStmt->fetchAll() as $cl) {
    $clDigits = normalizePhone($cl['phone']);
    $clDigits10 = strlen($clDigits) > 10 ? substr($clDigits, -10) : $clDigits;
    if ($clDigits === $fromDigits || $clDigits10 === $fromDigits10) {
        $client = [
            'id'             => (int)$cl['id'],
            'first_name'     => $cl['first_name'] ?? '',
            'last_name'      => $cl['last_name'] ?? '',
            'name'           => trim(($cl['first_name'] ?? '') . ' ' . ($cl['last_name'] ?? '')),
            'phone'          => $cl['phone'] ?? '',
            'email'          => $cl['email'] ?? '',
            'notes'          => $cl['notes'] ?? '',
            'service_address' => trim(implode(', ', array_filter([
                $cl['service_address_line1'] ?? '',
                $cl['service_city'] ?? '',
                $cl['service_state'] ?? '',
                $cl['service_postal_code'] ?? '',
            ]))),
            'preferred_contact_method' => $cl['preferred_contact_method'] ?? '',
            'last_appointment_at'      => $cl['last_appointment_at'] ?? '',
        ];
        wlog("CLIENT MATCHED: id={$client['id']}");

        // --- Next upcoming appointment ---
        $apptStmt = $pdo->prepare(
            "SELECT appointment_date, start_at, service_name, price, confirmation_code,
                    client_notes, service_contact_name, service_phone
             FROM professional_appointments
             WHERE restaurant_id = ? AND client_id = ? AND appointment_date >= CURRENT_DATE
               AND status IN ('pending','confirmed')
             ORDER BY appointment_date ASC, start_at ASC
             LIMIT 1"
        );
        $apptStmt->execute([$restaurantId, $cl['id']]);
        $nextAppt = $apptStmt->fetch(PDO::FETCH_ASSOC);

        if ($nextAppt) {
            $client['next_appointment'] = [
                'date'              => $nextAppt['appointment_date'],
                'time'              => $nextAppt['start_at'] ? date('g:i A', strtotime($nextAppt['start_at'])) : '',
                'service'           => $nextAppt['service_name'] ?? '',
                'price'             => $nextAppt['price'] ?? '',
                'confirmation_code' => $nextAppt['confirmation_code'] ?? '',
                'notes'             => $nextAppt['client_notes'] ?? '',
            ];
            wlog("NEXT APPOINTMENT: {$nextAppt['appointment_date']} — {$nextAppt['service_name']}");
        }

        break;
    }
}

if (!$client) {
    wlog("CLIENT: No matching professional_client");
}

// --- Fetch SMS conversation history for this from/to pair ---
$historyStmt = $pdo->prepare(
    "SELECT direction, from_number, to_number, body, status, created_at
     FROM sms_message_log
     WHERE restaurant_id = ?
       AND ((from_number = ? AND to_number = ?) OR (from_number = ? AND to_number = ?))
     ORDER BY created_at ASC
     LIMIT 50"
);
$historyStmt->execute([$restaurantId, $fromNumber, $toNumber, $toNumber, $fromNumber]);
$smsHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

wlog("SMS HISTORY: " . count($smsHistory) . " messages");

// --- Build response ---
$response = [
    'business'    => $business,
    'client'      => $client,
    'sms_history' => $smsHistory,
];

wlog("=== END ===");

echo json_encode($response);
