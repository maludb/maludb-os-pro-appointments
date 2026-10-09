<?php
session_start();
require_once __DIR__ . '/../../../../helpers/auth.php';
require_once __DIR__ . '/../../../../models/Event.php';
require_once __DIR__ . '/../../../../models/Activity.php';

check_auth();
$user = get_user();

header('Content-Type: text/html; charset=utf-8');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '<div class="alert alert-danger" role="alert">Invalid request method.</div>';
    exit;
}

try {
    // Get parameters
    $eventId = $_POST['event_id'] ?? null;
    $status = $_POST['status'] ?? null;

    if (!$eventId || !$status) {
        echo '<div class="alert alert-danger" role="alert">Event ID and status are required.</div>';
        exit;
    }

    // Validate status
    $validStatuses = ['pending', 'accepted', 'declined', 'tentative'];
    if (!in_array($status, $validStatuses)) {
        echo '<div class="alert alert-danger" role="alert">Invalid response status.</div>';
        exit;
    }

    // Update response
    $eventModel = new Event();
    $success = $eventModel->updateAttendeeResponse($eventId, $user['id'], $status);

    if (!$success) {
        echo '<div class="alert alert-danger" role="alert">Failed to update response. Please try again.</div>';
        exit;
    }

    // Log activity
    $event = $eventModel->getEventById($eventId);
    if ($event) {
        $activityModel = new Activity();
        $activityModel->logActivity(
            $user['id'],
            'event_response_updated',
            'event',
            $eventId,
            "Updated response to '{$status}' for event: " . $event['title']
        );
    }

    // Refresh the event view modal
    include __DIR__ . '/../view.php';

    // Also refresh calendar
    echo '<script>
        if (window.calendarInstance) {
            window.calendarInstance.refetchEvents();
        }
    </script>';

} catch (Exception $e) {
    error_log("Error updating attendee response: " . $e->getMessage());
    echo '<div class="alert alert-danger" role="alert">An error occurred. Please try again.</div>';
}
