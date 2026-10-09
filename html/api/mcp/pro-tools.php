<?php
/**
 * Professional MCP Tool Definitions & Executor
 * Shared by MCP server and SMS agent
 */

require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/professional-voice-api.php';

if (!function_exists('encodeProToolPayload')) {
    function encodeProToolPayload(array $payload): string
    {
        $json = json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($json !== false) {
            return $json;
        }

        return json_encode(
            [
                'success' => false,
                'error' => 'Unable to encode tool result as JSON.',
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }
}

if (!function_exists('getProToolDefinitions')) {
    function getProToolDefinitions(): array
    {
        return [
            [
                'name' => 'list_services',
                'description' => 'List all available services that clients can book with this professional.',
                'inputSchema' => [
                    'type' => 'object',
                    'required' => ['booking_slug'],
                    'properties' => [
                        'booking_slug' => ['type' => 'string', 'description' => 'Professional booking identifier slug (e.g. "jane-doe-coaching")'],
                    ],
                ],
            ],
            [
                'name' => 'check_availability',
                'description' => 'Check available appointment time slots for a specific service and date.',
                'inputSchema' => [
                    'type' => 'object',
                    'required' => ['booking_slug', 'service_id', 'date'],
                    'properties' => [
                        'booking_slug' => ['type' => 'string', 'description' => 'Professional booking identifier slug'],
                        'service_id' => ['type' => 'integer', 'description' => 'Service ID (from list_services)'],
                        'date' => ['type' => 'string', 'description' => 'Appointment date. Use "today", "tomorrow", or a day name like "monday", "tuesday", etc. Also accepts YYYY-MM-DD format.'],
                    ],
                ],
            ],
            [
                'name' => 'book_appointment',
                'description' => 'Book a new appointment with a professional. Returns a confirmation code.',
                'inputSchema' => [
                    'type' => 'object',
                    'required' => ['booking_slug', 'service_id', 'date', 'time', 'client_name', 'client_phone'],
                    'properties' => [
                        'booking_slug' => ['type' => 'string', 'description' => 'Professional booking identifier slug'],
                        'service_id' => ['type' => 'integer', 'description' => 'Service ID to book'],
                        'date' => ['type' => 'string', 'description' => 'Appointment date. Use "today", "tomorrow", or a day name like "monday", "tuesday", etc. Also accepts YYYY-MM-DD format.'],
                        'time' => ['type' => 'string', 'description' => 'Appointment time in HH:MM format (24-hour)'],
                        'client_name' => ['type' => 'string', 'description' => 'Full name of the client (first and last)'],
                        'client_phone' => ['type' => 'string', 'description' => 'Client phone number'],
                        'client_email' => ['type' => 'string', 'description' => 'Client email address (optional)'],
                        'client_notes' => ['type' => 'string', 'description' => 'Notes or special requests from the client (optional)'],
                        'internal_notes' => ['type' => 'string', 'description' => 'Internal staff notes (optional)'],
                        'service_contact_name' => ['type' => 'string', 'description' => 'Contact name for the service location (optional)'],
                        'service_phone' => ['type' => 'string', 'description' => 'Contact phone for the service location (optional)'],
                        'service_contact_method' => ['type' => 'string', 'description' => 'Preferred contact method: ph (phone), em (email), tx (text), ip (in person) (optional)'],
                        'service_address_1' => ['type' => 'string', 'description' => 'Service street address (optional)'],
                        'service_city' => ['type' => 'string', 'description' => 'Service city (optional)'],
                        'service_state' => ['type' => 'string', 'description' => 'Service state abbreviation (optional)'],
                        'service_postal_code' => ['type' => 'string', 'description' => 'Service postal/ZIP code (optional)'],
                    ],
                ],
            ],
            [
                'name' => 'lookup_appointment',
                'description' => 'Look up existing appointments by confirmation code or client phone number. When searching by phone, returns ALL active appointments for that client.',
                'inputSchema' => [
                    'type' => 'object',
                    'required' => ['booking_slug'],
                    'properties' => [
                        'booking_slug' => ['type' => 'string', 'description' => 'Professional booking identifier slug'],
                        'confirmation_code' => ['type' => 'string', 'description' => 'The 8-character appointment confirmation code'],
                        'client_phone' => ['type' => 'string', 'description' => 'Client phone number to search by (used if no confirmation code)'],
                    ],
                ],
            ],
            [
                'name' => 'cancel_appointment',
                'description' => 'Cancel an existing appointment. Only pending or confirmed appointments can be cancelled. Subject to cancellation notice window.',
                'inputSchema' => [
                    'type' => 'object',
                    'required' => ['booking_slug', 'confirmation_code'],
                    'properties' => [
                        'booking_slug' => ['type' => 'string', 'description' => 'Professional booking identifier slug'],
                        'confirmation_code' => ['type' => 'string', 'description' => 'The confirmation code of the appointment to cancel'],
                    ],
                ],
            ],
            [
                'name' => 'confirm_appointment',
                'description' => 'Confirm a pending appointment.',
                'inputSchema' => [
                    'type' => 'object',
                    'required' => ['booking_slug', 'confirmation_code'],
                    'properties' => [
                        'booking_slug' => ['type' => 'string', 'description' => 'Professional booking identifier slug'],
                        'confirmation_code' => ['type' => 'string', 'description' => 'The confirmation code of the appointment to confirm'],
                    ],
                ],
            ],
            [
                'name' => 'modify_appointment',
                'description' => 'Modify an existing appointment. Cancels the old appointment and books a new one. Always use this instead of calling cancel + book_appointment separately.',
                'inputSchema' => [
                    'type' => 'object',
                    'required' => ['booking_slug', 'confirmation_code', 'service_id', 'date', 'time', 'client_name', 'client_phone'],
                    'properties' => [
                        'booking_slug' => ['type' => 'string', 'description' => 'Professional booking identifier slug'],
                        'confirmation_code' => ['type' => 'string', 'description' => 'The confirmation code of the existing appointment to modify'],
                        'service_id' => ['type' => 'integer', 'description' => 'Service ID for the new appointment'],
                        'date' => ['type' => 'string', 'description' => 'New appointment date. Use "today", "tomorrow", or a day name. Also accepts YYYY-MM-DD.'],
                        'time' => ['type' => 'string', 'description' => 'New appointment time in HH:MM format (24-hour)'],
                        'client_name' => ['type' => 'string', 'description' => 'Full name of the client'],
                        'client_phone' => ['type' => 'string', 'description' => 'Client phone number'],
                        'client_email' => ['type' => 'string', 'description' => 'Client email address (optional)'],
                        'client_notes' => ['type' => 'string', 'description' => 'Notes or special requests (optional)'],
                        'internal_notes' => ['type' => 'string', 'description' => 'Internal staff notes (optional)'],
                        'service_contact_name' => ['type' => 'string', 'description' => 'Contact name for the service location (optional)'],
                        'service_phone' => ['type' => 'string', 'description' => 'Contact phone for the service location (optional)'],
                        'service_contact_method' => ['type' => 'string', 'description' => 'Preferred contact method: ph (phone), em (email), tx (text), ip (in person) (optional)'],
                        'service_address_1' => ['type' => 'string', 'description' => 'Service street address (optional)'],
                        'service_city' => ['type' => 'string', 'description' => 'Service city (optional)'],
                        'service_state' => ['type' => 'string', 'description' => 'Service state abbreviation (optional)'],
                        'service_postal_code' => ['type' => 'string', 'description' => 'Service postal/ZIP code (optional)'],
                    ],
                ],
            ],
            [
                'name' => 'update_client_preferences',
                'description' => 'Update a client\'s contact preferences such as marketing opt-in or preferred contact method.',
                'inputSchema' => [
                    'type' => 'object',
                    'required' => ['booking_slug', 'client_phone'],
                    'properties' => [
                        'booking_slug' => ['type' => 'string', 'description' => 'Professional booking identifier slug'],
                        'client_phone' => ['type' => 'string', 'description' => 'Client phone number to identify the client'],
                        'marketing_opt_in' => ['type' => 'boolean', 'description' => 'Set to true to opt in to marketing, false to opt out'],
                        'preferred_contact_method' => ['type' => 'string', 'description' => 'Preferred contact method: "email", "phone", "sms", or empty string to clear'],
                    ],
                ],
            ],
            [
                'name' => 'list_todos',
                'description' => 'List todo items for the professional. Can filter by status or priority.',
                'inputSchema' => [
                    'type' => 'object',
                    'required' => ['booking_slug'],
                    'properties' => [
                        'booking_slug' => ['type' => 'string', 'description' => 'Professional booking identifier slug'],
                        'status' => ['type' => 'string', 'description' => 'Filter by status: pending, in_progress, or completed (optional)'],
                        'priority' => ['type' => 'string', 'description' => 'Filter by priority: low, medium, or high (optional)'],
                    ],
                ],
            ],
            [
                'name' => 'create_todo',
                'description' => 'Create a new todo item for the professional.',
                'inputSchema' => [
                    'type' => 'object',
                    'required' => ['booking_slug', 'title'],
                    'properties' => [
                        'booking_slug' => ['type' => 'string', 'description' => 'Professional booking identifier slug'],
                        'title' => ['type' => 'string', 'description' => 'Title of the todo item'],
                        'description' => ['type' => 'string', 'description' => 'Optional description or details'],
                        'due_date' => ['type' => 'string', 'description' => 'Due date in YYYY-MM-DD format (optional)'],
                        'priority' => ['type' => 'string', 'description' => 'Priority: low, medium, or high (default: medium)'],
                    ],
                ],
            ],
            [
                'name' => 'update_todo',
                'description' => 'Update an existing todo item. Can change title, description, due date, priority, or status.',
                'inputSchema' => [
                    'type' => 'object',
                    'required' => ['booking_slug', 'todo_id'],
                    'properties' => [
                        'booking_slug' => ['type' => 'string', 'description' => 'Professional booking identifier slug'],
                        'todo_id' => ['type' => 'integer', 'description' => 'ID of the todo to update'],
                        'title' => ['type' => 'string', 'description' => 'New title (optional)'],
                        'description' => ['type' => 'string', 'description' => 'New description (optional)'],
                        'due_date' => ['type' => 'string', 'description' => 'New due date in YYYY-MM-DD format (optional)'],
                        'priority' => ['type' => 'string', 'description' => 'New priority: low, medium, or high (optional)'],
                        'status' => ['type' => 'string', 'description' => 'New status: pending, in_progress, or completed (optional)'],
                    ],
                ],
            ],
            [
                'name' => 'complete_todo',
                'description' => 'Mark a todo item as completed.',
                'inputSchema' => [
                    'type' => 'object',
                    'required' => ['booking_slug', 'todo_id'],
                    'properties' => [
                        'booking_slug' => ['type' => 'string', 'description' => 'Professional booking identifier slug'],
                        'todo_id' => ['type' => 'integer', 'description' => 'ID of the todo to complete'],
                    ],
                ],
            ],
            [
                'name' => 'get_good_news',
                'description' => 'Get the latest positive news articles for this business. Returns active good news stories that can be shared with callers.',
                'inputSchema' => [
                    'type' => 'object',
                    'required' => ['booking_slug'],
                    'properties' => [
                        'booking_slug' => ['type' => 'string', 'description' => 'Professional booking identifier slug'],
                        'category' => ['type' => 'string', 'description' => 'Filter by category (optional)'],
                        'limit' => ['type' => 'integer', 'description' => 'Max number of articles to return (default 5, max 20)'],
                    ],
                ],
            ],
        ];
    }
}

if (!function_exists('executeProTool')) {
    function executeProTool(string $toolName, array $args): array
    {
        switch ($toolName) {
            case 'list_services':
                $slug = $args['booking_slug'] ?? '';
                if ($slug === '') {
                    return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Missing required parameter: booking_slug.']]];
                }
                $result = proVoiceListServices($slug);
                break;

            case 'check_availability':
                $slug = $args['booking_slug'] ?? '';
                $serviceId = (int)($args['service_id'] ?? 0);
                $date = $args['date'] ?? '';
                if ($slug === '' || $serviceId === 0 || $date === '') {
                    return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Missing required parameters: booking_slug, service_id, date.']]];
                }
                $result = proVoiceCheckAvailability($slug, $serviceId, $date);
                break;

            case 'book_appointment':
                $slug = $args['booking_slug'] ?? '';
                $serviceId = (int)($args['service_id'] ?? 0);
                $date = $args['date'] ?? '';
                $time = $args['time'] ?? '';
                $clientName = $args['client_name'] ?? '';
                $clientPhone = $args['client_phone'] ?? '';
                $clientEmail = $args['client_email'] ?? '';
                $clientNotes = $args['client_notes'] ?? '';
                if ($slug === '' || $serviceId === 0 || $date === '' || $time === '' || $clientName === '' || $clientPhone === '') {
                    return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Missing required parameters.']]];
                }
                $extras = [
                    'internal_notes' => $args['internal_notes'] ?? '',
                    'service_contact_name' => $args['service_contact_name'] ?? '',
                    'service_phone' => $args['service_phone'] ?? '',
                    'service_contact_method' => $args['service_contact_method'] ?? '',
                    'service_address_1' => $args['service_address_1'] ?? '',
                    'service_city' => $args['service_city'] ?? '',
                    'service_state' => $args['service_state'] ?? '',
                    'service_postal_code' => $args['service_postal_code'] ?? '',
                ];
                $result = proVoiceBookAppointment($slug, $serviceId, $date, $time, $clientName, $clientPhone, $clientEmail, $clientNotes, $extras);
                break;

            case 'lookup_appointment':
                $slug = $args['booking_slug'] ?? '';
                $codeOrPhone = $args['confirmation_code'] ?? $args['client_phone'] ?? '';
                if ($slug === '' || $codeOrPhone === '') {
                    return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Missing booking_slug and either confirmation_code or client_phone.']]];
                }
                $result = proVoiceLookupAppointment($slug, $codeOrPhone);
                break;

            case 'cancel_appointment':
                $slug = $args['booking_slug'] ?? '';
                $code = $args['confirmation_code'] ?? '';
                if ($slug === '' || $code === '') {
                    return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Missing booking_slug or confirmation_code.']]];
                }
                $result = proVoiceCancelAppointment($slug, $code);
                break;

            case 'confirm_appointment':
                $slug = $args['booking_slug'] ?? '';
                $code = $args['confirmation_code'] ?? '';
                if ($slug === '' || $code === '') {
                    return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Missing booking_slug or confirmation_code.']]];
                }
                $result = proVoiceConfirmAppointment($slug, $code);
                break;

            case 'modify_appointment':
                $slug = $args['booking_slug'] ?? '';
                $code = $args['confirmation_code'] ?? '';
                $serviceId = (int)($args['service_id'] ?? 0);
                $date = $args['date'] ?? '';
                $time = $args['time'] ?? '';
                $clientName = $args['client_name'] ?? '';
                $clientPhone = $args['client_phone'] ?? '';
                $clientEmail = $args['client_email'] ?? '';
                $clientNotes = $args['client_notes'] ?? '';
                if ($slug === '' || $code === '' || $serviceId === 0 || $date === '' || $time === '' || $clientName === '' || $clientPhone === '') {
                    return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Missing required parameters.']]];
                }
                $extras = [
                    'internal_notes' => $args['internal_notes'] ?? '',
                    'service_contact_name' => $args['service_contact_name'] ?? '',
                    'service_phone' => $args['service_phone'] ?? '',
                    'service_contact_method' => $args['service_contact_method'] ?? '',
                    'service_address_1' => $args['service_address_1'] ?? '',
                    'service_city' => $args['service_city'] ?? '',
                    'service_state' => $args['service_state'] ?? '',
                    'service_postal_code' => $args['service_postal_code'] ?? '',
                ];
                $result = proVoiceModifyAppointment($slug, $code, $serviceId, $date, $time, $clientName, $clientPhone, $clientEmail, $clientNotes, $extras);
                break;

            case 'update_client_preferences':
                $slug = $args['booking_slug'] ?? '';
                $phone = $args['client_phone'] ?? '';
                if ($slug === '' || $phone === '') {
                    return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Missing booking_slug or client_phone.']]];
                }
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
                $result = proVoiceUpdateClientPreferences($slug, $phone, $prefs);
                break;

            case 'list_todos':
                $slug = $args['booking_slug'] ?? '';
                if ($slug === '') {
                    return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Missing required parameter: booking_slug.']]];
                }
                $result = proMcpListTodos($slug, $args);
                break;

            case 'create_todo':
                $slug = $args['booking_slug'] ?? '';
                $title = trim($args['title'] ?? '');
                if ($slug === '' || $title === '') {
                    return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Missing required parameters: booking_slug, title.']]];
                }
                $result = proMcpCreateTodo($slug, $args);
                break;

            case 'update_todo':
                $slug = $args['booking_slug'] ?? '';
                $todoId = (int)($args['todo_id'] ?? 0);
                if ($slug === '' || $todoId === 0) {
                    return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Missing required parameters: booking_slug, todo_id.']]];
                }
                $result = proMcpUpdateTodo($slug, $args);
                break;

            case 'complete_todo':
                $slug = $args['booking_slug'] ?? '';
                $todoId = (int)($args['todo_id'] ?? 0);
                if ($slug === '' || $todoId === 0) {
                    return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Missing required parameters: booking_slug, todo_id.']]];
                }
                $result = proMcpCompleteTodo($slug, $args);
                break;

            case 'get_good_news':
                $slug = $args['booking_slug'] ?? '';
                if ($slug === '') {
                    return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Missing required parameter: booking_slug.']]];
                }
                $result = proMcpGetGoodNews($slug, $args);
                break;

            default:
                return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Unknown tool: {$toolName}"]]];
        }

        $isError = !($result['success'] ?? false);
        $text = encodeProToolPayload($result);

        return [
            'isError' => $isError,
            'content' => [['type' => 'text', 'text' => $text]],
        ];
    }
}

// ── Todo MCP Handlers ──────────────────────────────────────

if (!function_exists('proMcpResolveTodoContext')) {
    function proMcpResolveTodoContext(string $slug): array
    {
        $resolved = proVoiceResolveProfile($slug);
        if (!$resolved['success']) {
            return $resolved;
        }
        return [
            'success' => true,
            'restaurant_id' => $resolved['restaurant_id'],
            'user_id' => (int)$resolved['profile']['owner_user_id'],
        ];
    }
}

if (!function_exists('proMcpListTodos')) {
    function proMcpListTodos(string $slug, array $args): array
    {
        $ctx = proMcpResolveTodoContext($slug);
        if (!$ctx['success']) return $ctx;

        $where = ['restaurant_id = ?', 'user_id = ?'];
        $params = [$ctx['restaurant_id'], $ctx['user_id']];

        $status = $args['status'] ?? '';
        if ($status !== '' && in_array($status, ['pending', 'in_progress', 'completed'])) {
            $where[] = 'status = ?';
            $params[] = $status;
        }

        $priority = $args['priority'] ?? '';
        if ($priority !== '' && in_array($priority, ['low', 'medium', 'high'])) {
            $where[] = 'priority = ?';
            $params[] = $priority;
        }

        $whereClause = implode(' AND ', $where);
        $stmt = db()->prepare("SELECT id, title, description, due_date, priority, status, completed_at, created_at FROM todos WHERE {$whereClause} ORDER BY status != 'completed', COALESCE(due_date, '9999-12-31') ASC, FIELD(priority, 'high', 'medium', 'low')");
        $stmt->execute($params);
        $todos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['success' => true, 'todos' => $todos, 'count' => count($todos)];
    }
}

if (!function_exists('proMcpCreateTodo')) {
    function proMcpCreateTodo(string $slug, array $args): array
    {
        $ctx = proMcpResolveTodoContext($slug);
        if (!$ctx['success']) return $ctx;

        $title = trim($args['title'] ?? '');
        $description = trim($args['description'] ?? '') ?: null;
        $dueDate = ($args['due_date'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $args['due_date']) ? $args['due_date'] : null;
        $priority = in_array($args['priority'] ?? '', ['low', 'medium', 'high']) ? $args['priority'] : 'medium';

        $pdo = db();
        $stmt = $pdo->prepare("INSERT INTO todos (restaurant_id, user_id, title, description, due_date, priority) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$ctx['restaurant_id'], $ctx['user_id'], $title, $description, $dueDate, $priority]);

        $newId = (int)$pdo->lastInsertId();

        return ['success' => true, 'message' => 'Todo created.', 'todo' => [
            'id' => $newId, 'title' => $title, 'description' => $description,
            'due_date' => $dueDate, 'priority' => $priority, 'status' => 'pending',
        ]];
    }
}

if (!function_exists('proMcpUpdateTodo')) {
    function proMcpUpdateTodo(string $slug, array $args): array
    {
        $ctx = proMcpResolveTodoContext($slug);
        if (!$ctx['success']) return $ctx;

        $todoId = (int)($args['todo_id'] ?? 0);
        $pdo = db();

        $existing = $pdo->prepare("SELECT * FROM todos WHERE id = ? AND restaurant_id = ? AND user_id = ?");
        $existing->execute([$todoId, $ctx['restaurant_id'], $ctx['user_id']]);
        $todo = $existing->fetch(PDO::FETCH_ASSOC);

        if (!$todo) {
            return ['success' => false, 'error' => 'Todo not found.'];
        }

        $title = isset($args['title']) ? trim($args['title']) : $todo['title'];
        $description = array_key_exists('description', $args) ? (trim($args['description']) ?: null) : $todo['description'];
        $dueDate = array_key_exists('due_date', $args)
            ? (($args['due_date'] && preg_match('/^\d{4}-\d{2}-\d{2}$/', $args['due_date'])) ? $args['due_date'] : null)
            : $todo['due_date'];
        $priority = isset($args['priority']) && in_array($args['priority'], ['low', 'medium', 'high']) ? $args['priority'] : $todo['priority'];
        $status = isset($args['status']) && in_array($args['status'], ['pending', 'in_progress', 'completed']) ? $args['status'] : $todo['status'];

        $completedAt = $todo['completed_at'];
        if ($status === 'completed' && $todo['status'] !== 'completed') {
            $completedAt = date('Y-m-d H:i:s');
        } elseif ($status !== 'completed') {
            $completedAt = null;
        }

        $stmt = $pdo->prepare("UPDATE todos SET title = ?, description = ?, due_date = ?, priority = ?, status = ?, completed_at = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$title, $description, $dueDate, $priority, $status, $completedAt, $todoId]);

        return ['success' => true, 'message' => 'Todo updated.', 'todo' => [
            'id' => $todoId, 'title' => $title, 'description' => $description,
            'due_date' => $dueDate, 'priority' => $priority, 'status' => $status,
        ]];
    }
}

if (!function_exists('proMcpCompleteTodo')) {
    function proMcpCompleteTodo(string $slug, array $args): array
    {
        $ctx = proMcpResolveTodoContext($slug);
        if (!$ctx['success']) return $ctx;

        $todoId = (int)($args['todo_id'] ?? 0);
        $pdo = db();

        $existing = $pdo->prepare("SELECT id, title, status FROM todos WHERE id = ? AND restaurant_id = ? AND user_id = ?");
        $existing->execute([$todoId, $ctx['restaurant_id'], $ctx['user_id']]);
        $todo = $existing->fetch(PDO::FETCH_ASSOC);

        if (!$todo) {
            return ['success' => false, 'error' => 'Todo not found.'];
        }

        if ($todo['status'] === 'completed') {
            return ['success' => true, 'message' => 'Todo is already completed.', 'todo_id' => $todoId, 'title' => $todo['title']];
        }

        $stmt = $pdo->prepare("UPDATE todos SET status = 'completed', completed_at = NOW(), updated_at = NOW() WHERE id = ?");
        $stmt->execute([$todoId]);

        return ['success' => true, 'message' => 'Todo marked as completed.', 'todo_id' => $todoId, 'title' => $todo['title']];
    }
}

// ── Good News MCP Handler ─────────────────────────────────────

if (!function_exists('proMcpGetGoodNews')) {
    function proMcpGetGoodNews(string $slug, array $args): array
    {
        $ctx = proMcpResolveTodoContext($slug);
        if (!$ctx['success']) return $ctx;

        $pdo = db();
        $limit = min(max((int)($args['limit'] ?? 5), 1), 20);
        $category = trim($args['category'] ?? '');

        $where = ['restaurant_id = ?', 'is_active = 1'];
        $params = [$ctx['restaurant_id']];

        if ($category !== '') {
            $where[] = 'category = ?';
            $params[] = $category;
        }

        $params[] = $limit;
        $whereClause = implode(' AND ', $where);

        $stmt = $pdo->prepare(
            "SELECT id, title, summary, source_name, source_url, image_url, category, published_date, fetched_at
             FROM good_news
             WHERE {$whereClause}
             ORDER BY fetched_at DESC, published_date DESC
             LIMIT ?"
        );
        $stmt->execute($params);
        $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($articles)) {
            return ['success' => true, 'message' => 'No good news articles found.', 'articles' => [], 'count' => 0];
        }

        return ['success' => true, 'articles' => $articles, 'count' => count($articles)];
    }
}
