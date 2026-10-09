<?php
/**
 * SMS MCP Tool Definitions & Executor
 *
 * Adapts the professional MCP tools for SMS context:
 * - Company identified by to_number (via restaurant_phone_numbers), not slug
 * - Client identified by from_number (via professional_clients)
 * - Only known clients can book appointments
 */

require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/restaurant.php';
require_once __DIR__ . '/../../../helpers/professional-voice-api.php';

/**
 * Resolve company + client from phone numbers.
 * Returns ['success' => true, 'slug' => ..., 'client' => ..., 'restaurant_id' => ...]
 * or ['success' => false, 'error' => '...']
 */
function smsResolveContext(string $toNumber, string $fromNumber): array
{
    $pdo = db();

    // --- Look up company by TO number via restaurant_phone_numbers ---
    $toDigits = normalizePhone($toNumber);
    $toDigits10 = strlen($toDigits) > 10 ? substr($toDigits, -10) : $toDigits;

    $rpnStmt = $pdo->prepare(
        "SELECT rpn.restaurant_id, rpn.phone_number
         FROM restaurant_phone_numbers rpn
         JOIN restaurants r ON rpn.restaurant_id = r.id AND r.is_active = 1
         WHERE rpn.is_active = 1
         ORDER BY rpn.id"
    );
    $rpnStmt->execute();

    $restaurantId = null;
    foreach ($rpnStmt->fetchAll() as $rpn) {
        $rpnDigits = normalizePhone($rpn['phone_number'] ?? '');
        $rpnDigits10 = strlen($rpnDigits) > 10 ? substr($rpnDigits, -10) : $rpnDigits;
        if ($rpnDigits === $toDigits || $rpnDigits10 === $toDigits10) {
            $restaurantId = (int)$rpn['restaurant_id'];
            break;
        }
    }

    if (!$restaurantId || !mcp_business_allowed($restaurantId)) {
        return ['success' => false, 'error' => 'No company found for this phone number.'];
    }

    // --- Get professional profile to find booking slug ---
    $profile = getProfessionalProfile($restaurantId);
    if (!$profile) {
        return ['success' => false, 'error' => 'No professional profile found for this company.'];
    }

    $slug = $profile['booking_slug'] ?: $profile['restaurant_slug'];
    if ($slug === '') {
        return ['success' => false, 'error' => 'No booking slug configured for this company.'];
    }

    // --- Identify client by FROM number ---
    $client = null;
    $fromDigits = normalizePhone($fromNumber);
    $fromDigits10 = strlen($fromDigits) > 10 ? substr($fromDigits, -10) : $fromDigits;

    $clientStmt = $pdo->prepare(
        "SELECT id, first_name, last_name, phone, email,
                service_address_line1, service_city, service_state, service_postal_code,
                preferred_contact_method, notes
         FROM professional_clients
         WHERE restaurant_id = ? AND phone IS NOT NULL AND phone != ''"
    );
    $clientStmt->execute([$restaurantId]);

    foreach ($clientStmt->fetchAll() as $cl) {
        $clDigits = normalizePhone($cl['phone']);
        $clDigits10 = strlen($clDigits) > 10 ? substr($clDigits, -10) : $clDigits;
        if ($clDigits === $fromDigits || $clDigits10 === $fromDigits10) {
            $client = $cl;
            break;
        }
    }

    return [
        'success' => true,
        'slug' => $slug,
        'restaurant_id' => $restaurantId,
        'client' => $client,
    ];
}

/**
 * Tool definitions for SMS MCP — uses to_number/from_number instead of booking_slug.
 */
function getSmsToolDefinitions(): array
{
    $phoneProps = [
        'to_number' => ['type' => 'string', 'description' => 'The receiving phone number (identifies the company)'],
        'from_number' => ['type' => 'string', 'description' => 'The sender phone number (identifies the client)'],
    ];

    return [
        [
            'name' => 'list_services',
            'description' => 'List all available services that this client can book.',
            'inputSchema' => [
                'type' => 'object',
                'required' => ['to_number', 'from_number'],
                'properties' => $phoneProps,
            ],
        ],
        [
            'name' => 'check_availability',
            'description' => 'Check available appointment time slots for a specific service and date.',
            'inputSchema' => [
                'type' => 'object',
                'required' => ['to_number', 'from_number', 'service_id', 'date'],
                'properties' => array_merge($phoneProps, [
                    'service_id' => ['type' => 'integer', 'description' => 'Service ID (from list_services)'],
                    'date' => ['type' => 'string', 'description' => 'Appointment date. Use "today", "tomorrow", or a day name like "monday". Also accepts YYYY-MM-DD.'],
                ]),
            ],
        ],
        [
            'name' => 'book_appointment',
            'description' => 'Book a new appointment. Only works for known clients. Client info is auto-filled from the client record.',
            'inputSchema' => [
                'type' => 'object',
                'required' => ['to_number', 'from_number', 'service_id', 'date', 'time'],
                'properties' => array_merge($phoneProps, [
                    'service_id' => ['type' => 'integer', 'description' => 'Service ID to book'],
                    'date' => ['type' => 'string', 'description' => 'Appointment date. Use "today", "tomorrow", or a day name. Also accepts YYYY-MM-DD.'],
                    'time' => ['type' => 'string', 'description' => 'Appointment time in HH:MM format (24-hour)'],
                    'client_notes' => ['type' => 'string', 'description' => 'Notes or special requests from the client (optional)'],
                    'internal_notes' => ['type' => 'string', 'description' => 'Internal staff notes (optional)'],
                ]),
            ],
        ],
        [
            'name' => 'lookup_appointment',
            'description' => 'Look up existing appointments by confirmation code, or return all active appointments for this client.',
            'inputSchema' => [
                'type' => 'object',
                'required' => ['to_number', 'from_number'],
                'properties' => array_merge($phoneProps, [
                    'confirmation_code' => ['type' => 'string', 'description' => 'The 8-character confirmation code (optional — if omitted, searches by client phone)'],
                ]),
            ],
        ],
        [
            'name' => 'cancel_appointment',
            'description' => 'Cancel an existing appointment. Only pending or confirmed appointments can be cancelled.',
            'inputSchema' => [
                'type' => 'object',
                'required' => ['to_number', 'from_number', 'confirmation_code'],
                'properties' => array_merge($phoneProps, [
                    'confirmation_code' => ['type' => 'string', 'description' => 'The confirmation code of the appointment to cancel'],
                ]),
            ],
        ],
        [
            'name' => 'confirm_appointment',
            'description' => 'Confirm a pending appointment.',
            'inputSchema' => [
                'type' => 'object',
                'required' => ['to_number', 'from_number', 'confirmation_code'],
                'properties' => array_merge($phoneProps, [
                    'confirmation_code' => ['type' => 'string', 'description' => 'The confirmation code of the appointment to confirm'],
                ]),
            ],
        ],
        [
            'name' => 'modify_appointment',
            'description' => 'Modify an existing appointment. Cancels the old one and books a new one.',
            'inputSchema' => [
                'type' => 'object',
                'required' => ['to_number', 'from_number', 'confirmation_code', 'service_id', 'date', 'time'],
                'properties' => array_merge($phoneProps, [
                    'confirmation_code' => ['type' => 'string', 'description' => 'Confirmation code of the existing appointment'],
                    'service_id' => ['type' => 'integer', 'description' => 'Service ID for the new appointment'],
                    'date' => ['type' => 'string', 'description' => 'New appointment date. Use "today", "tomorrow", or a day name. Also accepts YYYY-MM-DD.'],
                    'time' => ['type' => 'string', 'description' => 'New appointment time in HH:MM format (24-hour)'],
                    'client_notes' => ['type' => 'string', 'description' => 'Notes or special requests (optional)'],
                    'internal_notes' => ['type' => 'string', 'description' => 'Internal staff notes (optional)'],
                ]),
            ],
        ],
        [
            'name' => 'update_client_preferences',
            'description' => 'Update this client\'s contact preferences such as marketing opt-in or preferred contact method.',
            'inputSchema' => [
                'type' => 'object',
                'required' => ['to_number', 'from_number'],
                'properties' => array_merge($phoneProps, [
                    'marketing_opt_in' => ['type' => 'boolean', 'description' => 'Set to true to opt in to marketing, false to opt out'],
                    'preferred_contact_method' => ['type' => 'string', 'description' => 'Preferred contact method: "email", "phone", "sms", or empty string to clear'],
                ]),
            ],
        ],
    ];
}

/**
 * Execute an SMS tool. Resolves phones → slug/client, enforces known-client gate, delegates to proVoice* functions.
 */
function executeSmsTool(string $toolName, array $args): array
{
    $toNumber = $args['to_number'] ?? '';
    $fromNumber = $args['from_number'] ?? '';

    if ($toNumber === '' || $fromNumber === '') {
        return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Missing required parameters: to_number and from_number.']]];
    }

    // Resolve company and client from phone numbers
    $ctx = smsResolveContext($toNumber, $fromNumber);
    if (!$ctx['success']) {
        return ['isError' => true, 'content' => [['type' => 'text', 'text' => $ctx['error']]]];
    }

    $slug = $ctx['slug'];
    $client = $ctx['client'];

    // --- Known-client gate for booking/modify ---
    $clientRequiredTools = ['book_appointment', 'modify_appointment', 'cancel_appointment', 'confirm_appointment', 'update_client_preferences'];
    if (in_array($toolName, $clientRequiredTools) && !$client) {
        return [
            'isError' => true,
            'content' => [['type' => 'text', 'text' => 'This phone number is not associated with a known client. Only existing clients can perform this action via SMS.']],
        ];
    }

    // Build client name and phone from the matched record
    $clientName = $client ? trim(($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? '')) : '';
    $clientPhone = $client ? ($client['phone'] ?? $fromNumber) : $fromNumber;
    $clientEmail = $client ? ($client['email'] ?? '') : '';

    switch ($toolName) {
        case 'list_services':
            $result = proVoiceListServices($slug);
            break;

        case 'check_availability':
            $serviceId = (int)($args['service_id'] ?? 0);
            $date = $args['date'] ?? '';
            if ($serviceId === 0 || $date === '') {
                return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Missing required parameters: service_id, date.']]];
            }
            $result = proVoiceCheckAvailability($slug, $serviceId, $date);
            break;

        case 'book_appointment':
            $serviceId = (int)($args['service_id'] ?? 0);
            $date = $args['date'] ?? '';
            $time = $args['time'] ?? '';
            if ($serviceId === 0 || $date === '' || $time === '') {
                return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Missing required parameters: service_id, date, time.']]];
            }
            $extras = [
                'internal_notes' => $args['internal_notes'] ?? '',
                'service_contact_name' => $client['first_name'] ?? '',
                'service_phone' => $clientPhone,
                'service_contact_method' => 'tx',
                'service_address_1' => $client['service_address_line1'] ?? '',
                'service_city' => $client['service_city'] ?? '',
                'service_state' => $client['service_state'] ?? '',
                'service_postal_code' => $client['service_postal_code'] ?? '',
            ];
            $result = proVoiceBookAppointment($slug, $serviceId, $date, $time, $clientName, $clientPhone, $clientEmail, $args['client_notes'] ?? '', $extras);
            break;

        case 'lookup_appointment':
            $codeOrPhone = $args['confirmation_code'] ?? $clientPhone;
            $result = proVoiceLookupAppointment($slug, $codeOrPhone);
            break;

        case 'cancel_appointment':
            $code = $args['confirmation_code'] ?? '';
            if ($code === '') {
                return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Missing required parameter: confirmation_code.']]];
            }
            $result = proVoiceCancelAppointment($slug, $code);
            break;

        case 'confirm_appointment':
            $code = $args['confirmation_code'] ?? '';
            if ($code === '') {
                return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Missing required parameter: confirmation_code.']]];
            }
            $result = proVoiceConfirmAppointment($slug, $code);
            break;

        case 'modify_appointment':
            $code = $args['confirmation_code'] ?? '';
            $serviceId = (int)($args['service_id'] ?? 0);
            $date = $args['date'] ?? '';
            $time = $args['time'] ?? '';
            if ($code === '' || $serviceId === 0 || $date === '' || $time === '') {
                return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Missing required parameters: confirmation_code, service_id, date, time.']]];
            }
            $extras = [
                'internal_notes' => $args['internal_notes'] ?? '',
                'service_contact_name' => $client['first_name'] ?? '',
                'service_phone' => $clientPhone,
                'service_contact_method' => 'tx',
                'service_address_1' => $client['service_address_line1'] ?? '',
                'service_city' => $client['service_city'] ?? '',
                'service_state' => $client['service_state'] ?? '',
                'service_postal_code' => $client['service_postal_code'] ?? '',
            ];
            $result = proVoiceModifyAppointment($slug, $code, $serviceId, $date, $time, $clientName, $clientPhone, $clientEmail, $args['client_notes'] ?? '', $extras);
            break;

        case 'update_client_preferences':
            $prefs = [];
            if (isset($args['marketing_opt_in'])) {
                $prefs['marketing_opt_in'] = (bool)$args['marketing_opt_in'];
            }
            if (isset($args['preferred_contact_method'])) {
                $prefs['preferred_contact_method'] = (string)$args['preferred_contact_method'];
            }
            if (empty($prefs)) {
                return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Provide at least one preference: marketing_opt_in or preferred_contact_method.']]];
            }
            $result = proVoiceUpdateClientPreferences($slug, $clientPhone, $prefs);
            break;

        default:
            return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Unknown tool: {$toolName}"]]];
    }

    $isError = !($result['success'] ?? false);
    $text = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

    return [
        'isError' => $isError,
        'content' => [['type' => 'text', 'text' => $text]],
    ];
}
