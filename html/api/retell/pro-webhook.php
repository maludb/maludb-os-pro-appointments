<?php
/**
 * Retell Professional Webhook
 *
 * Single webhook endpoint handling all Retell events:
 *   - call_inbound: Look up restaurant/guest, return dynamic_variables
 *   - call_started: Log only
 *   - call_ended:   Save transcript, recording, summary to call_logs
 *   - call_analyzed: Save call analysis to call_logs
 */
require_once __DIR__ . '/../../../helpers/retell-auth.php';
require_once __DIR__ . '/../../../helpers/voice-api.php';

header('Content-Type: application/json');

// --- Logging helper ---
$logFile = __DIR__ . '/../../../logs/pro-webhook.log';

function wlog(string $msg) {
    global $logFile;
    $ts = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$ts}] {$msg}\n", FILE_APPEND);
}

// Agent ID from query string (e.g. ?id=agent_xxxx)
$queryAgentId = $_GET['id'] ?? '';

// Read raw body
$rawBody = file_get_contents('php://input');

$data = ($rawBody !== false && $rawBody !== '') ? json_decode($rawBody, true) : [];
if (!is_array($data)) {
    $data = [];
}

requireRetellSignature((string)$rawBody, $data);

// Determine the event type
$event = $data['event'] ?? 'call_inbound';
wlog("=== WEBHOOK EVENT: {$event} ===");
wlog("QUERY agent_id: " . ($queryAgentId ?: '(none)'));

// Extract call data — Retell nests differently per event type
$call = $data['call'] ?? $data['call_inbound'] ?? $data;
$fromNumber = $call['from_number'] ?? '';
$toNumber   = $call['to_number'] ?? '';
$callId     = $call['call_id'] ?? '';

wlog("PARSED — call_id: {$callId}, from_number: {$fromNumber}, to_number: {$toNumber}");

if ($toNumber === '') {
    $toNumber = '+18473930142';
    wlog("to_number empty — using default: {$toNumber}");
}

// --- Look up restaurant: prefer agent_id resolution, fall back to phone ---
$restaurant = null;

// If agent_id provided, resolve restaurant from restaurant_prompts first
if ($queryAgentId !== '') {
    $agentStmt = db()->prepare(
        "SELECT rp.restaurant_id FROM restaurant_prompts rp
         JOIN restaurants r ON r.id = rp.restaurant_id AND r.is_active = 1
         WHERE rp.agent_id = ?
         LIMIT 1"
    );
    $agentStmt->execute([$queryAgentId]);
    $agentRow = $agentStmt->fetch();
    if ($agentRow) {
        $restaurant = getRestaurant((int)$agentRow['restaurant_id']);
        wlog("RESTAURANT RESOLVED via agent_id — restaurant_id: {$agentRow['restaurant_id']}");
    }
}

// Fall back to phone lookup only if no agent_id was provided
if (!$restaurant && $queryAgentId === '' && $toNumber !== '') {
    $restaurant = getRestaurantByPhone($toNumber);
    if ($restaurant) {
        wlog("RESTAURANT RESOLVED via phone — to_number: {$toNumber}");
    }
}

if (!$restaurant && $queryAgentId !== '') {
    wlog("INVALID agent_id: {$queryAgentId} — no matching restaurant_prompts record");
}

$restaurantId = $restaurant ? (int)$restaurant['id'] : null;
if ($restaurantId) {
    applyRestaurantTimezone($restaurantId);
}

// =====================================================================
// EVENT: call_inbound — Return dynamic variables for the agent
// =====================================================================
if ($event === 'call_inbound' || $event === 'call_started') {

    if (!$restaurant) {
        wlog("RESTAURANT NOT FOUND for to_number: {$toNumber}");
        $emptyVars = [
            'business_name' => '',
            'business_slug' => '',
            'caller_known'  => 'false',
            'source'        => 'phone',
        ];
        $response = [
            'dynamic_variables' => $emptyVars,
            'call_inbound' => ['dynamic_variables' => $emptyVars],
        ];
        echo json_encode($response);
        exit;
    }

    wlog("RESTAURANT FOUND — id: {$restaurantId}, name: {$restaurant['name']}");
    $pdo = db();

    // --- Build dynamic variables ---
    $vars = [
        'business_name'  => $restaurant['name'],
        'business_slug'  => $restaurant['slug'],
        'business_phone' => $restaurant['phone'] ?? '',
        'business_email' => $restaurant['email'] ?? '',
        'time'             => date('g:i A'),
        'today'            => date('Y-m-d'),
        'tomorrow'         => date('Y-m-d', strtotime('+1 day')),
        'dow'              => date('l'),
        'monday'           => date('Y-m-d', strtotime(strtolower(date('l')) === 'monday' ? 'today' : 'next monday')),
        'tuesday'          => date('Y-m-d', strtotime(strtolower(date('l')) === 'tuesday' ? 'today' : 'next tuesday')),
        'wednesday'        => date('Y-m-d', strtotime(strtolower(date('l')) === 'wednesday' ? 'today' : 'next wednesday')),
        'thursday'         => date('Y-m-d', strtotime(strtolower(date('l')) === 'thursday' ? 'today' : 'next thursday')),
        'friday'           => date('Y-m-d', strtotime(strtolower(date('l')) === 'friday' ? 'today' : 'next friday')),
        'saturday'         => date('Y-m-d', strtotime(strtolower(date('l')) === 'saturday' ? 'today' : 'next saturday')),
        'sunday'           => date('Y-m-d', strtotime(strtolower(date('l')) === 'sunday' ? 'today' : 'next sunday')),
        'caller_known'     => 'false',
    ];

    // --- Add metadata fields to dynamic variables ---
    $vars['business_id']  = (string)$restaurantId;
    $vars['source']       = 'phone';
    $vars['from_number']  = $fromNumber;
    $vars['to_number']    = $toNumber;

    // --- Check if caller is a known client ---
    if ($fromNumber !== '') {
        $fromDigits = normalizePhone($fromNumber);
        $from10 = strlen($fromDigits) > 10 ? substr($fromDigits, -10) : $fromDigits;
        $fromE164 = '+1' . $from10;
        wlog("CLIENT LOOKUP — raw from_number: {$fromNumber}, digits: {$fromDigits}, 10-digit: {$from10}, E.164: {$fromE164}");
        wlog("CLIENT LOOKUP — searching restaurant_id: {$restaurantId}");

        $stmt = $pdo->prepare(
            "SELECT * FROM professional_clients
             WHERE restaurant_id = ? AND phone IS NOT NULL AND phone != ''
             ORDER BY last_appointment_at DESC"
        );
        $stmt->execute([$restaurantId]);
        $clients = $stmt->fetchAll();
        wlog("CLIENT LOOKUP — found " . count($clients) . " clients with phone numbers for restaurant_id {$restaurantId}");

        // Log all client phones for debugging
        foreach ($clients as $idx => $c) {
            $dbDigits = normalizePhone($c['phone']);
            $db10 = strlen($dbDigits) > 10 ? substr($dbDigits, -10) : $dbDigits;
            $dbE164 = '+1' . $db10;
            wlog("CLIENT LOOKUP — [{$idx}] id: {$c['id']}, name: {$c['first_name']} {$c['last_name']}, db_phone: {$c['phone']}, normalized: {$dbE164}, match: " . ($fromE164 === $dbE164 ? 'YES' : 'NO'));
        }

        $matchedClient = null;
        foreach ($clients as $c) {
            $cDigits = normalizePhone($c['phone']);
            $c10 = strlen($cDigits) > 10 ? substr($cDigits, -10) : $cDigits;
            $cE164 = '+1' . $c10;
            if ($fromE164 === $cE164) {
                $matchedClient = $c;
                wlog("CLIENT MATCHED — id: {$c['id']}, name: {$c['first_name']} {$c['last_name']}, phone: {$c['phone']} (normalized: {$cE164})");
                break;
            }
        }

        if (!$matchedClient) {
            wlog("CLIENT NOT MATCHED — no client matched for from_number {$fromNumber} (E.164: {$fromE164}) in restaurant_id {$restaurantId}");
            $vars['client_phone'] = $fromE164;
        }

        if ($matchedClient) {
            $vars['caller_known']              = 'true';
            $vars['client_name']               = trim($matchedClient['first_name'] . ' ' . $matchedClient['last_name']);
            $vars['client_first_name']         = $matchedClient['first_name'];
            $vars['client_last_name']          = $matchedClient['last_name'];
            $vars['client_phone']              = $matchedClient['phone'] ?? '';
            $vars['client_email']              = $matchedClient['email'] ?? '';
            $vars['client_notes']              = $matchedClient['notes'] ?? '';
            $vars['client_service_address']    = trim(($matchedClient['service_address_line1'] ?? '') . ', ' . ($matchedClient['service_city'] ?? '') . ', ' . ($matchedClient['service_state'] ?? '') . ' ' . ($matchedClient['service_postal_code'] ?? ''), ', ');
            $vars['client_service_address_line1']  = $matchedClient['service_address_line1'] ?? '';
            $vars['client_service_city']       = $matchedClient['service_city'] ?? '';
            $vars['client_service_state']      = $matchedClient['service_state'] ?? '';
            $vars['client_service_postal_code'] = $matchedClient['service_postal_code'] ?? '';
            $vars['client_last_service_date']  = $matchedClient['last_service_date'] ?? '';
            $vars['client_preferred_contact_method'] = $matchedClient['preferred_contact_method'] ?? '';
            $vars['client_last_appointment_date']    = $matchedClient['last_appointment_at'] ? date('Y-m-d', strtotime($matchedClient['last_appointment_at'])) : '';

            $vars['client_id'] = (string)$matchedClient['id'];

            // --- Check for next upcoming appointment ---
            $apptStmt = $pdo->prepare(
                "SELECT appointment_date, start_at, service_name, price, confirmation_code,
                        client_notes, service_contact_name, service_phone, service_contact_method,
                        service_address_1, service_city, service_state, service_postal_code
                 FROM professional_appointments
                 WHERE restaurant_id = ? AND client_id = ? AND appointment_date >= CURRENT_DATE
                   AND status IN ('pending','confirmed')
                 ORDER BY appointment_date ASC, start_at ASC
                 LIMIT 1"
            );
            $apptStmt->execute([$restaurantId, $matchedClient['id']]);
            $nextAppt = $apptStmt->fetch();

            if ($nextAppt) {
                $vars['has_appointment']              = 'true';
                $vars['appointment_date']             = $nextAppt['appointment_date'] ?? '';
                $vars['appointment_start_at']         = $nextAppt['start_at'] ? date('g:i A', strtotime($nextAppt['start_at'])) : '';
                $vars['appointment_service_name']     = $nextAppt['service_name'] ?? '';
                $vars['appointment_price']            = $nextAppt['price'] ?? '';
                $vars['appointment_confirmation_code'] = $nextAppt['confirmation_code'] ?? '';
                $vars['appointment_client_notes']     = $nextAppt['client_notes'] ?? '';
                $vars['appointment_service_contact_name']   = $nextAppt['service_contact_name'] ?? '';
                $vars['appointment_service_contact_phone']  = $nextAppt['service_phone'] ?? '';
                $vars['appointment_service_contact_method'] = $nextAppt['service_contact_method'] ?? '';
                $vars['appointment_service_address_1']      = $nextAppt['service_address_1'] ?? '';
                $vars['appointment_service_city']           = $nextAppt['service_city'] ?? '';
                $vars['appointment_service_state']          = $nextAppt['service_state'] ?? '';
                $vars['appointment_service_postal_code']    = $nextAppt['service_postal_code'] ?? '';
                wlog("NEXT APPOINTMENT FOUND — date: {$nextAppt['appointment_date']}, service: {$nextAppt['service_name']}, code: {$nextAppt['confirmation_code']}");
            } else {
                $vars['has_appointment'] = 'false';
                wlog("NO UPCOMING APPOINTMENT for client id: {$matchedClient['id']}");
            }
        }
    }

    // --- Load custom prompt sections from settings ---
    $promptKeys = ['voice_agent_greeting', 'voice_agent_specials', 'voice_agent_custom_info'];
    $placeholders = implode(',', array_fill(0, count($promptKeys), '?'));
    $stmt = $pdo->prepare(
        "SELECT setting_key, setting_value FROM settings
         WHERE restaurant_id = ? AND setting_key IN ({$placeholders})"
    );
    $stmt->execute(array_merge([$restaurantId], $promptKeys));
    $promptSettings = $stmt->fetchAll();

    foreach ($promptSettings as $ps) {
        $val = trim($ps['setting_value'] ?? '');
        if ($val !== '') {
            $vars[$ps['setting_key']] = $val;
        }
    }

    // --- Override with agent-specific prompt from restaurant_prompts ---
    $agentPrompt = null;
    if ($queryAgentId !== '') {
        $stmt = $pdo->prepare(
            "SELECT id, agent_id, phone_number, voice_agent_greeting, description,
                    language_code, language_name, is_primary_language
             FROM restaurant_prompts
             WHERE restaurant_id = ? AND agent_id = ?"
        );
        $stmt->execute([$restaurantId, $queryAgentId]);
        $agentPrompt = $stmt->fetch();
    }

    if (!$agentPrompt && $toNumber !== '') {
        $stmt = $pdo->prepare(
            "SELECT id, agent_id, phone_number, voice_agent_greeting, description,
                    language_code, language_name, is_primary_language
             FROM restaurant_prompts
             WHERE restaurant_id = ? AND phone_number = ?"
        );
        $stmt->execute([$restaurantId, $toNumber]);
        $agentPrompt = $stmt->fetch();
    }

    if ($agentPrompt) {
        if (!empty(trim($agentPrompt['voice_agent_greeting'] ?? ''))) {
            $vars['voice_agent_greeting'] = trim($agentPrompt['voice_agent_greeting']);
        }
        if (!empty($agentPrompt['phone_number'])) {
            $vars['agent_phone_number'] = $agentPrompt['phone_number'];
        }
        if (!empty($agentPrompt['description'])) {
            $vars['agent_description'] = $agentPrompt['description'];
        }
        $vars['agent_id'] = $agentPrompt['agent_id'];
        $vars['agent_language_code'] = $agentPrompt['language_code'] ?? 'en';
        $vars['agent_language_name'] = $agentPrompt['language_name'] ?? 'English';
        $vars['is_primary_language'] = $agentPrompt['is_primary_language'] ? 'true' : 'false';

        $detailStmt = $pdo->prepare(
            "SELECT variable_name, variable_value FROM restaurant_prompt_details
             WHERE restaurant_prompt_id = ? AND is_active = 1"
        );
        $detailStmt->execute([$agentPrompt['id']]);
        foreach ($detailStmt->fetchAll() as $d) {
            $vars[$d['variable_name']] = $d['variable_value'];
        }
    }

    // --- Log the inbound call start ---
    try {
        $desc = "Inbound call started from {$fromNumber} to {$toNumber}";
        if (isset($vars['client_name']) && $vars['client_name'] !== '') {
            $desc .= " (known client: {$vars['client_name']}" . ($vars['client_last_service_date'] ? ", last service: {$vars['client_last_service_date']}" : '') . ")";
        }
        $stmt = $pdo->prepare(
            "INSERT INTO activity_log (restaurant_id, user_id, action, entity_type, description, ip_address, created_at)
             VALUES (?, NULL, 'voice_call_started', 'voice_call', ?, ?, NOW())"
        );
        $stmt->execute([$restaurantId, $desc, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
    } catch (Exception $e) {
        wlog("ACTIVITY LOG ERROR: " . $e->getMessage());
    }

    // --- Return Retell response (both keys for call_inbound and call_started compatibility) ---
    $response = [
        'dynamic_variables' => $vars,
        'call_inbound' => [
            'dynamic_variables' => $vars,
        ],
    ];

    wlog("DYNAMIC VARIABLES: " . json_encode($vars, JSON_PRETTY_PRINT));
    wlog("=== END ===\n");

    echo json_encode($response);
    exit;
}

// =====================================================================
// EVENT: call_ended — Save call data to call_logs and activity_log
// =====================================================================
if ($event === 'call_ended') {

    $direction           = $call['direction'] ?? 'inbound';
    $status              = $call['call_status'] ?? ($call['status'] ?? 'ended');
    $durationMs          = $call['duration_ms'] ?? null;
    $disconnectionReason = $call['disconnection_reason'] ?? '';
    $transcript          = $call['transcript'] ?? '';
    $recordingUrl        = $call['recording_url'] ?? '';
    $publicLogUrl        = $call['public_log_url'] ?? '';
    $callSummary         = $call['call_analysis']['call_summary'] ?? ($call['call_summary'] ?? '');
    $startTimestamp      = $call['start_timestamp'] ?? null;
    $endTimestamp        = $call['end_timestamp'] ?? null;

    wlog("duration_ms: {$durationMs}, status: {$status}, recording: " . ($recordingUrl ?: '(none)'));

    $pdo = db();

    // --- Match guest by from_number ---
    $guestId = null;
    if ($fromNumber !== '' && $restaurantId) {
        $fromDigits = normalizePhone($fromNumber);
        $from10 = strlen($fromDigits) > 10 ? substr($fromDigits, -10) : $fromDigits;
        $fromE164 = '+1' . $from10;

        $stmt = $pdo->prepare(
            "SELECT id, phone FROM guests
             WHERE restaurant_id = ? AND phone IS NOT NULL AND phone != '' AND is_active = 1"
        );
        $stmt->execute([$restaurantId]);
        foreach ($stmt->fetchAll() as $g) {
            $gDigits = normalizePhone($g['phone']);
            $g10 = strlen($gDigits) > 10 ? substr($gDigits, -10) : $gDigits;
            $gE164 = '+1' . $g10;
            if ($fromE164 === $gE164) {
                $guestId = (int)$g['id'];
                wlog("GUEST MATCHED — id: {$guestId}");
                break;
            }
        }
    }

    // --- Build extra metadata ---
    $callMeta = [];
    if ($publicLogUrl !== '') {
        $callMeta['public_log_url'] = $publicLogUrl;
    }
    if ($startTimestamp !== null) {
        $callMeta['start_timestamp'] = $startTimestamp;
    }
    if ($endTimestamp !== null) {
        $callMeta['end_timestamp'] = $endTimestamp;
    }
    if (isset($call['call_analysis']) && is_array($call['call_analysis'])) {
        $callMeta['call_analysis'] = $call['call_analysis'];
    }

    // --- Timestamps ---
    $createdAt = $startTimestamp ? date('Y-m-d H:i:s', $startTimestamp / 1000) : date('Y-m-d H:i:s');
    $endedAt   = $endTimestamp   ? date('Y-m-d H:i:s', $endTimestamp / 1000)   : date('Y-m-d H:i:s');

    // --- Insert into call_logs ---
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO call_logs
             (restaurant_id, guest_id, retell_call_id, direction, from_number, to_number,
              status, duration_ms, disconnection_reason, transcript, recording_url,
              call_summary, metadata, created_at, ended_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $restaurantId,
            $guestId,
            $callId ?: null,
            $direction,
            $fromNumber,
            $toNumber,
            $status,
            $durationMs,
            $disconnectionReason ?: null,
            $transcript ?: null,
            $recordingUrl ?: null,
            $callSummary ?: null,
            !empty($callMeta) ? json_encode($callMeta) : null,
            $createdAt,
            $endedAt,
        ]);
        $insertedCallLogId = $pdo->lastInsertId();
        wlog("INSERTED call_logs row id: " . $insertedCallLogId);
    } catch (Exception $e) {
        $insertedCallLogId = null;
        wlog("ERROR inserting call_logs: " . $e->getMessage());
    }

    // --- Check if this is a voice message delivery call (outbound relay) ---
    if ($direction === 'outbound' && $callId !== '') {
        try {
            $vmStmt = $pdo->prepare(
                "SELECT id FROM voice_messages WHERE delivery_call_id = ? AND status = 'in_progress' LIMIT 1"
            );
            $vmStmt->execute([$callId]);
            $vmRow = $vmStmt->fetch();
            if ($vmRow) {
                $pdo->prepare(
                    "UPDATE voice_messages SET status = 'delivered', delivered_at = NOW(),
                            delivery_call_log_id = ? WHERE id = ?"
                )->execute([$insertedCallLogId, $vmRow['id']]);
                wlog("VOICE RELAY DELIVERED — voice_messages.id: {$vmRow['id']}, delivery_call_id: {$callId}");
            }
        } catch (Exception $e) {
            wlog("VOICE RELAY DELIVERY CHECK ERROR: " . $e->getMessage());
        }
    }

    // --- Audit entry in activity_log ---
    $durationSec = $durationMs !== null ? round($durationMs / 1000) : null;
    $durationDisplay = $durationSec !== null ? gmdate('H:i:s', $durationSec) : 'unknown';
    $desc = "Call ended — from: {$fromNumber}, to: {$toNumber}, duration: {$durationDisplay}, status: {$status}";
    if ($disconnectionReason !== '') {
        $desc .= ", reason: {$disconnectionReason}";
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO activity_log (restaurant_id, user_id, action, entity_type, description, ip_address, created_at)
             VALUES (?, NULL, 'voice_call_ended', 'voice_call', ?, ?, NOW())"
        );
        $stmt->execute([$restaurantId, $desc, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
    } catch (Exception $e) {
        wlog("ERROR inserting activity_log: " . $e->getMessage());
    }

    // --- Multi-language message relay ---
    // If the source agent's language differs from the business's primary language,
    // queue a voice_messages row for outbound delivery by the primary-language agent.
    if ($restaurantId && $direction === 'inbound') {
        try {
            // Check if multi-language is enabled for this business
            $optStmt = $pdo->prepare(
                "SELECT enabled FROM account_options
                 WHERE restaurant_id = ? AND option_key = 'multi_language_enabled' AND enabled = 1"
            );
            $optStmt->execute([$restaurantId]);
            $mlEnabled = $optStmt->fetch();

            if ($mlEnabled) {
                // Look up the source agent's language
                $sourcePrompt = null;
                if ($queryAgentId !== '') {
                    $spStmt = $pdo->prepare(
                        "SELECT id, language_code, language_name, is_primary_language
                         FROM restaurant_prompts WHERE restaurant_id = ? AND agent_id = ?"
                    );
                    $spStmt->execute([$restaurantId, $queryAgentId]);
                    $sourcePrompt = $spStmt->fetch();
                }

                if ($sourcePrompt && !$sourcePrompt['is_primary_language']) {
                    // Find the primary language agent
                    $tpStmt = $pdo->prepare(
                        "SELECT id, language_code, language_name, phone_number
                         FROM restaurant_prompts
                         WHERE restaurant_id = ? AND is_primary_language = 1
                         LIMIT 1"
                    );
                    $tpStmt->execute([$restaurantId]);
                    $targetPrompt = $tpStmt->fetch();

                    if ($targetPrompt && $targetPrompt['language_code'] !== $sourcePrompt['language_code']) {
                        // Get the message text: prefer call_summary, fall back to transcript
                        $messageText = $callSummary ?: $transcript;

                        if ($messageText !== '') {
                            // Determine recipient phone: business phone from restaurants or professional_profiles
                            $bpStmt = $pdo->prepare(
                                "SELECT pp.business_phone, r.phone
                                 FROM restaurants r
                                 LEFT JOIN professional_profiles pp ON pp.restaurant_id = r.id
                                 WHERE r.id = ?"
                            );
                            $bpStmt->execute([$restaurantId]);
                            $bpRow = $bpStmt->fetch();
                            $recipientPhone = $bpRow['business_phone'] ?: ($bpRow['phone'] ?: '');

                            if ($recipientPhone !== '') {
                                // Get the call_log id we just inserted
                                $callLogId = $pdo->lastInsertId();

                                $vmStmt = $pdo->prepare(
                                    "INSERT INTO voice_messages
                                     (restaurant_id, call_log_id, source_prompt_id, target_prompt_id,
                                      from_language, to_language, message_text, recipient_phone,
                                      recipient_type, status)
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'business_staff', 'pending')"
                                );
                                $vmStmt->execute([
                                    $restaurantId,
                                    $callLogId ?: null,
                                    $sourcePrompt['id'],
                                    $targetPrompt['id'],
                                    $sourcePrompt['language_code'],
                                    $targetPrompt['language_code'],
                                    $messageText,
                                    $recipientPhone,
                                ]);
                                wlog("MULTI-LANG RELAY QUEUED — from {$sourcePrompt['language_name']} to {$targetPrompt['language_name']}, recipient: {$recipientPhone}");

                                // Activity log for relay
                                $relayDesc = "Voice message relay queued: {$sourcePrompt['language_name']} → {$targetPrompt['language_name']} from caller {$fromNumber}";
                                $pdo->prepare(
                                    "INSERT INTO activity_log (restaurant_id, user_id, action, entity_type, description, ip_address, created_at)
                                     VALUES (?, NULL, 'voice_relay_queued', 'voice_message', ?, ?, NOW())"
                                )->execute([$restaurantId, $relayDesc, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
                            } else {
                                wlog("MULTI-LANG RELAY SKIP — no business phone configured for restaurant_id {$restaurantId}");
                            }
                        } else {
                            wlog("MULTI-LANG RELAY SKIP — no message text (empty summary and transcript)");
                        }
                    }
                }
            }
        } catch (Exception $e) {
            wlog("MULTI-LANG RELAY ERROR: " . $e->getMessage());
        }
    }

    wlog("=== END ===\n");
    echo json_encode(['success' => true]);
    exit;
}

// =====================================================================
// EVENT: call_started, call_analyzed, or unknown — acknowledge only
// =====================================================================
wlog("Event '{$event}' acknowledged — no action taken");
wlog("=== END ===\n");
echo json_encode(['success' => true]);
exit;
