<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../models/Task.php';
require_once __DIR__ . '/../../../models/Event.php';

check_auth();
$user = get_user();

// Set JSON header
header('Content-Type: application/json');

try {
    // Get parameters
    $start = $_GET['start'] ?? null;
    $end = $_GET['end'] ?? null;
    $show = $_GET['show'] ?? 'both'; // 'tasks', 'events', 'both'
    $colorBy = $_GET['color_by'] ?? 'priority';
    $teamId = $_GET['team_id'] ?? $_SESSION['selected_team_id'] ?? null;

    // Parse member and event type filters
    $memberIds = !empty($_GET['members']) ? explode(',', $_GET['members']) : [];
    $eventTypes = !empty($_GET['event_types']) ? explode(',', $_GET['event_types']) : [];

    if (!$start || !$end) {
        echo json_encode(['error' => 'Missing date range']);
        exit;
    }

    $events = [];

    // Get tasks if requested
    if ($show === 'tasks' || $show === 'both') {
        $taskModel = new Task();

        // Build task filters
        $filters = [
            'start_date' => $start,
            'end_date' => $end,
            'team_id' => $teamId
        ];

        if (!empty($memberIds)) {
            $filters['assigned_to'] = $memberIds;
        }

        // Get tasks with due dates in range
        $tasks = $taskModel->getTasksForCalendar($user['id'], $start, $end, $filters);

        foreach ($tasks as $task) {
            if (!$task['due_date']) continue;

            $color = getTaskColor($task, $colorBy);

            $events[] = [
                'id' => 'task-' . $task['id'],
                'title' => $task['title'],
                'start' => $task['due_date'],
                'allDay' => true,
                'backgroundColor' => $color,
                'borderColor' => $color,
                'extendedProps' => [
                    'type' => 'task',
                    'taskId' => $task['id'],
                    'priority' => $task['priority'],
                    'status' => $task['status'],
                    'assignee' => $task['first_name'] ? $task['first_name'] . ' ' . $task['last_name'] : 'Unassigned'
                ]
            ];
        }
    }

    // Get events if requested
    if ($show === 'events' || $show === 'both') {
        if ($teamId) {
            $eventModel = new Event();

            // Build event filters
            $eventFilters = [];
            if (!empty($eventTypes)) {
                $eventFilters['types'] = $eventTypes;
            }
            if (!empty($memberIds)) {
                $eventFilters['user_ids'] = $memberIds;
            }

            $calendarEvents = $eventModel->getEvents($teamId, $start, $end, $eventFilters);

            foreach ($calendarEvents as $event) {
                $color = getEventColor($event, $colorBy);

                $events[] = [
                    'id' => 'event-' . $event['id'],
                    'title' => $event['title'],
                    'start' => $event['start_datetime'],
                    'end' => $event['end_datetime'],
                    'allDay' => (bool)$event['all_day'],
                    'backgroundColor' => $color,
                    'borderColor' => $color,
                    'extendedProps' => [
                        'type' => 'event',
                        'eventId' => $event['id'],
                        'eventType' => $event['type'],
                        'location' => $event['location'],
                        'description' => $event['description']
                    ]
                ];
            }
        }
    }

    echo json_encode($events);

} catch (Exception $e) {
    error_log("Calendar feed error: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to load calendar data']);
}

/**
 * Get task color based on color mode
 */
function getTaskColor($task, $colorBy) {
    if ($colorBy === 'status') {
        return getStatusColor($task['status']);
    } else {
        return getPriorityColor($task['priority']);
    }
}

/**
 * Get event color based on color mode
 */
function getEventColor($event, $colorBy) {
    if ($colorBy === 'type') {
        return getEventTypeColor($event['type']);
    } elseif (!empty($event['color'])) {
        return $event['color'];
    } else {
        return '#0d6efd'; // Default blue
    }
}

/**
 * Get color by priority
 */
function getPriorityColor($priority) {
    $colors = [
        'low' => '#28a745',      // Green
        'medium' => '#ffc107',   // Yellow
        'high' => '#fd7e14',     // Orange
        'critical' => '#dc3545'  // Red
    ];
    return $colors[$priority] ?? '#6c757d'; // Default gray
}

/**
 * Get color by status
 */
function getStatusColor($status) {
    $colors = [
        'pending' => '#6c757d',     // Gray
        'in_progress' => '#0dcaf0', // Cyan
        'review' => '#6f42c1',      // Purple
        'completed' => '#28a745',   // Green
        'cancelled' => '#dc3545'    // Red
    ];
    return $colors[$status] ?? '#6c757d'; // Default gray
}

/**
 * Get color by event type
 */
function getEventTypeColor($type) {
    $colors = [
        'event' => '#0d6efd',       // Blue
        'meeting' => '#198754',     // Green
        'appointment' => '#0dcaf0', // Cyan
        'reminder' => '#ffc107'     // Yellow
    ];
    return $colors[$type] ?? '#0d6efd'; // Default blue
}
