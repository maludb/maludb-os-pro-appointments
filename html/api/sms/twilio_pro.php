<?php
/**
 * Twilio Inbound SMS Webhook — Professional
 *
 * Receives incoming SMS from Twilio, identifies the company from
 * restaurant_phone_numbers and the client from professional_clients,
 * then stores the message in sms_message_log.
 *
 * Twilio webhook URL: https://yourdomain.com/api/sms/twilio_pro.php
 * HTTP Method: POST
 */

require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/restaurant.php';

// --- Logging ---
define('SMS_PRO_LOG', __DIR__ . '/../../../logs/pro-webhook.log');

function proLog(string $message): void
{
    $ts = date('Y-m-d H:i:s');
    file_put_contents(SMS_PRO_LOG, "[{$ts}] {$message}\n", FILE_APPEND);
}

// --- Main ---
proLog("=== INBOUND SMS ===");

$fromNumber = $_POST['From'] ?? '';
$toNumber   = $_POST['To'] ?? '';
$body       = trim($_POST['Body'] ?? '');
$messageSid = $_POST['MessageSid'] ?? '';

proLog("FROM: {$fromNumber} | TO: {$toNumber} | SID: {$messageSid}");
proLog("BODY: {$body}");

if ($fromNumber === '' || $toNumber === '' || $body === '') {
    proLog("ERROR: Missing required fields");
    header('Content-Type: text/xml');
    echo '<Response></Response>';
    exit;
}

$pdo = db();

// --- Look up company by TO number via restaurant_phone_numbers ---
$restaurantId = null;
$phoneNumberId = null;
$toDigits = normalizePhone($toNumber);
$toDigits10 = strlen($toDigits) > 10 ? substr($toDigits, -10) : $toDigits;

$rpnStmt = $pdo->prepare(
    "SELECT rpn.id as phone_number_id, rpn.restaurant_id, rpn.phone_number, r.name as restaurant_name
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
        proLog("MATCHED company: {$rpn['restaurant_name']} (id={$restaurantId}) via phone_number_id={$phoneNumberId}");
        break;
    }
}

if (!$restaurantId) {
    proLog("ERROR: No company found for number {$toNumber}");
    header('Content-Type: text/xml');
    echo '<Response></Response>';
    exit;
}

// --- Identify client by FROM number via professional_clients ---
$clientId = null;
$fromDigits = normalizePhone($fromNumber);
$fromDigits10 = strlen($fromDigits) > 10 ? substr($fromDigits, -10) : $fromDigits;

$clientStmt = $pdo->prepare(
    "SELECT id, phone FROM professional_clients WHERE restaurant_id = ? AND phone IS NOT NULL AND phone != ''"
);
$clientStmt->execute([$restaurantId]);

foreach ($clientStmt->fetchAll() as $cl) {
    $clDigits = normalizePhone($cl['phone']);
    $clDigits10 = strlen($clDigits) > 10 ? substr($clDigits, -10) : $clDigits;
    if ($clDigits === $fromDigits || $clDigits10 === $fromDigits10) {
        $clientId = (int)$cl['id'];
        proLog("CLIENT MATCHED: id={$clientId}");
        break;
    }
}

// Fallback: check prospects table by contact_phone tied to this restaurant
$prospectId = null;
if (!$clientId) {
    $prospectStmt = $pdo->prepare(
        "SELECT id, contact_phone FROM prospects WHERE restaurant_id = ? AND contact_phone IS NOT NULL AND contact_phone != ''"
    );
    $prospectStmt->execute([$restaurantId]);

    foreach ($prospectStmt->fetchAll() as $pr) {
        $prDigits = normalizePhone($pr['contact_phone']);
        $prDigits10 = strlen($prDigits) > 10 ? substr($prDigits, -10) : $prDigits;
        if ($prDigits === $fromDigits || $prDigits10 === $fromDigits10) {
            $prospectId = (int)$pr['id'];
            proLog("PROSPECT MATCHED: id={$prospectId}");
            break;
        }
    }
}

if (!$clientId && !$prospectId) {
    proLog("SENDER: No matching professional_client or prospect for {$fromNumber}");
}

// --- Store inbound message ---
$logStmt = $pdo->prepare(
    "INSERT INTO sms_message_log (restaurant_id, client_id, prospect_id, phone_number_id, direction, from_number, to_number, body, status, external_id)
     VALUES (?, ?, ?, ?, 'inbound', ?, ?, ?, 'received', ?)"
);
$logStmt->execute([$restaurantId, $clientId, $prospectId, $phoneNumberId, $fromNumber, $toNumber, $body, $messageSid]);
proLog("STORED message id=" . $pdo->lastInsertId());

// Return empty TwiML — no auto-reply for now
header('Content-Type: text/xml');
echo '<Response></Response>';

proLog("=== END ===");
