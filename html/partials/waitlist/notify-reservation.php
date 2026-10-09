<?php
/**
 * POST handler: "Table Ready" notification for a reservation from the waitlist screen
 * Sends SMS if phone is on file, otherwise just shows a confirmation.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo '<div class="alert alert-danger">Invalid security token.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
$pdo = db();
$resId = (int)($_POST['reservation_id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT r.*, g.first_name, g.last_name, g.phone AS guest_phone
     FROM reservations r
     JOIN guests g ON r.guest_id = g.id
     WHERE r.id = ? AND r.restaurant_id = ? AND r.status IN ('pending', 'confirmed')"
);
$stmt->execute([$resId, $restaurantId]);
$res = $stmt->fetch();

if (!$res) {
    echo '<div class="alert alert-warning">Reservation not found or already seated.</div>';
    exit;
}

$phone = $res['guest_phone'] ?? '';
$name = $res['first_name'] . ' ' . $res['last_name'];

// Send SMS if phone is on file
$smsSent = false;
if ($phone !== '') {
    require_once __DIR__ . '/../../../helpers/notifications.php';

    // Use Twilio to send a simple "table ready" message
    $twilioSid = null;
    $stmt2 = $pdo->prepare("SELECT setting_value FROM settings WHERE restaurant_id = ? AND setting_key = 'twilio_account_sid'");
    $stmt2->execute([$restaurantId]);
    $twilioSid = ($stmt2->fetch())['setting_value'] ?? null;

    if ($twilioSid) {
        $stmt2 = $pdo->prepare("SELECT setting_value FROM settings WHERE restaurant_id = ? AND setting_key IN ('twilio_auth_token', 'twilio_phone_number')");
        $stmt2->execute([$restaurantId]);
        $settings = $stmt2->fetchAll(PDO::FETCH_KEY_PAIR);
        $authToken = $settings['twilio_auth_token'] ?? '';
        $fromNumber = $settings['twilio_phone_number'] ?? '';

        if ($authToken && $fromNumber) {
            try {
                $message = "Hi {$res['first_name']}, your table is ready! Please check in with the host.";
                $url = "https://api.twilio.com/2010-04-01/Accounts/{$twilioSid}/Messages.json";
                $data = ['To' => $phone, 'From' => $fromNumber, 'Body' => $message];
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                curl_setopt($ch, CURLOPT_USERPWD, "{$twilioSid}:{$authToken}");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                $result = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                $smsSent = ($httpCode >= 200 && $httpCode < 300);
            } catch (Exception $e) {
                $smsSent = false;
            }
        }
    }
}

// Log activity
$method = $phone !== '' ? ($smsSent ? 'SMS sent' : 'SMS failed') : 'called/announced';
$stmt = $pdo->prepare(
    "INSERT INTO activity_log (restaurant_id, user_id, action, entity_type, entity_id, description, ip_address)
     VALUES (?, ?, 'notify', 'reservation', ?, ?, ?)"
);
$desc = "Table ready for {$name} — {$method}";
$stmt->execute([$restaurantId, currentUserId(), $resId, $desc, $_SERVER['REMOTE_ADDR'] ?? null]);

header('HX-Trigger: refreshWaitlist');

if ($phone !== '' && $smsSent) {
    echo '<div class="alert alert-success alert-dismissible fade show">
            <i class="feather-check-circle me-1"></i> SMS sent to ' . htmlspecialchars($name) . ' at ' . htmlspecialchars($phone) . '.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
} elseif ($phone !== '') {
    echo '<div class="alert alert-warning alert-dismissible fade show">
            <i class="feather-alert-circle me-1"></i> Marked as called for ' . htmlspecialchars($name) . '. SMS could not be sent.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
} else {
    echo '<div class="alert alert-info alert-dismissible fade show">
            <i class="feather-info me-1"></i> Call guest ' . htmlspecialchars($name) . ' — no phone on file.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
}
