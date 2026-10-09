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
    // Get event ID
    $eventId = $_GET['id'] ?? $_POST['id'] ?? null;

    if (!$eventId) {
        echo '<div class="alert alert-danger" role="alert">Event ID is required.</div>';
        exit;
    }

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

    // Get event to verify ownership
    $eventModel = new Event();
    $event = $eventModel->getEventById($eventId);

    if (!$event) {
        echo '<div class="alert alert-danger" role="alert">Event not found.</div>';
        exit;
    }

    // Check authorization (organizer or admin)
    $isOrganizer = $eventModel->isOrganizer($eventId, $user['id']);
    if (!$isOrganizer && !is_admin()) {
        echo '<div class="alert alert-danger" role="alert">You do not have permission to edit this event.</div>';
        exit;
    }

    // Prepare event data
    $eventData = [
        'title' => trim($_POST['title']),
        'description' => trim($_POST['description'] ?? ''),
        'location' => trim($_POST['location'] ?? ''),
        'start_datetime' => $_POST['start_datetime'],
        'end_datetime' => $_POST['end_datetime'],
        'all_day' => isset($_POST['all_day']) ? 1 : 0,
        'color' => $_POST['color'] ?? '#0d6efd',
        'type' => $_POST['type'] ?? 'event',
        'status' => $_POST['status'] ?? 'scheduled'
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

    // Update event
    $success = $eventModel->updateEvent($eventId, $eventData);

    if (!$success) {
        echo '<div class="alert alert-danger" role="alert">Failed to update event. Please try again.</div>';
        exit;
    }

    // Update attendees if changed
    if (isset($_POST['attendees']) && is_array($_POST['attendees'])) {
        // Get current attendees
        $currentAttendees = $eventModel->getEventAttendees($eventId);
        $currentAttendeeIds = array_column($currentAttendees, 'user_id');
        $newAttendeeIds = $_POST['attendees'];

        // Remove attendees no longer in list (except organizers)
        foreach ($currentAttendees as $attendee) {
            if (!in_array($attendee['user_id'], $newAttendeeIds) && !$attendee['is_organizer']) {
                $eventModel->removeAttendee($eventId, $attendee['user_id']);
            }
        }

        // Add new attendees
        foreach ($newAttendeeIds as $attendeeId) {
            if (!in_array($attendeeId, $currentAttendeeIds)) {
                $eventModel->addAttendee($eventId, $attendeeId, false);
            }
        }
    }

    // Log activity
    $activityModel = new Activity();
    $activityModel->logActivity(
        $user['id'],
        'event_updated',
        'event',
        $eventId,
        "Updated event: " . $eventData['title']
    );

    // Return success message with trigger to refresh calendar
    echo '<div class="alert alert-success" role="alert">Event updated successfully!</div>';

    // Close modal and refresh calendar
    echo '<script>
        setTimeout(function() {
            document.getElementById("editEventModal").remove();
            document.querySelector(".modal-backdrop").remove();
            if (window.calendarInstance) {
                window.calendarInstance.refetchEvents();
            }
        }, 1000);
    </script>';

} catch (Exception $e) {
    error_log("Error updating event: " . $e->getMessage());
    echo '<div class="alert alert-danger" role="alert">An error occurred while updating the event. Please try again.</div>';
}
