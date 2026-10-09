<?php
session_start();
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../models/Event.php';
require_once __DIR__ . '/../../../models/Activity.php';

check_auth();
$user = get_user();

header('Content-Type: text/html; charset=utf-8');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '<div class="alert alert-danger" role="alert">Invalid request method.</div>';
    exit;
}

// Verify CSRF token
if (!csrf_verify()) {
    echo '<div class="alert alert-danger" role="alert">Invalid security token. Please try again.</div>';
    exit;
}

try {
    // Validate required fields
    if (empty($_POST['title'])) {
        echo '<div class="alert alert-danger" role="alert">Event title is required.</div>';
        exit;
    }

    if (empty($_POST['start_datetime'])) {
        echo '<div class="alert alert-danger" role="alert">Start date/time is required.</div>';
        exit;
    }

    if (empty($_POST['end_datetime'])) {
        echo '<div class="alert alert-danger" role="alert">End date/time is required.</div>';
        exit;
    }

    // Get team ID
    $teamId = $_POST['team_id'] ?? $_SESSION['selected_team_id'] ?? null;

    if (!$teamId) {
        echo '<div class="alert alert-danger" role="alert">No team selected. Please select a team first.</div>';
        exit;
    }

    // Prepare event data
    $eventData = [
        'team_id' => $teamId,
        'created_by' => $user['id'],
        'title' => trim($_POST['title']),
        'description' => trim($_POST['description'] ?? ''),
        'location' => trim($_POST['location'] ?? ''),
        'start_datetime' => $_POST['start_datetime'],
        'end_datetime' => $_POST['end_datetime'],
        'all_day' => isset($_POST['all_day']) ? 1 : 0,
        'color' => $_POST['color'] ?? '#0d6efd',
        'type' => $_POST['type'] ?? 'event',
        'status' => 'scheduled'
    ];

    // Validate dates
    $start = strtotime($eventData['start_datetime']);
    $end = strtotime($eventData['end_datetime']);

    if ($start === false || $end === false) {
        echo '<div class="alert alert-danger" role="alert">Invalid date/time format.</div>';
        exit;
    }

    if ($end <= $start) {
        echo '<div class="alert alert-danger" role="alert">End date/time must be after start date/time.</div>';
        exit;
    }

    // Create event
    $eventModel = new Event();
    $eventId = $eventModel->createEvent($eventData);

    if (!$eventId) {
        echo '<div class="alert alert-danger" role="alert">Failed to create event. Please try again.</div>';
        exit;
    }

    // Add attendees
    $attendees = $_POST['attendees'] ?? [];
    if (!empty($attendees) && is_array($attendees)) {
        foreach ($attendees as $attendeeId) {
            // Creator is organizer
            $isOrganizer = ($attendeeId == $user['id']);
            $eventModel->addAttendee($eventId, $attendeeId, $isOrganizer);
        }
    } else {
        // At minimum, add creator as organizer
        $eventModel->addAttendee($eventId, $user['id'], true);
    }

    // Log activity
    $activityModel = new Activity();
    $activityModel->logActivity(
        $user['id'],
        'event_created',
        'event',
        $eventId,
        "Created event: " . $eventData['title']
    );

    // Return success message with trigger to refresh calendar
    echo '<div class="alert alert-success" role="alert">Event created successfully!</div>';

    // Close modal and refresh calendar
    echo '<script>
        setTimeout(function() {
            document.getElementById("createEventModal").remove();
            document.querySelector(".modal-backdrop").remove();
            if (window.calendarInstance) {
                window.calendarInstance.refetchEvents();
            }
        }, 1000);
    </script>';

} catch (Exception $e) {
    error_log("Error creating event: " . $e->getMessage());
    echo '<div class="alert alert-danger" role="alert">An error occurred while creating the event. Please try again.</div>';
}
