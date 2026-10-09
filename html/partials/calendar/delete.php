<?php
session_start();
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../models/Event.php';
require_once __DIR__ . '/../../../models/Activity.php';

check_auth();
$user = get_user();

header('Content-Type: text/html; charset=utf-8');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '<div class="alert alert-danger" role="alert">Invalid request method.</div>';
    exit;
}

try {
    // Get event ID
    $eventId = $_GET['id'] ?? $_POST['id'] ?? null;

    if (!$eventId) {
        echo '<div class="alert alert-danger" role="alert">Event ID is required.</div>';
        exit;
    }

    // Get event details
    $eventModel = new Event();
    $event = $eventModel->getEventById($eventId);

    if (!$event) {
        echo '<div class="alert alert-danger" role="alert">Event not found.</div>';
        exit;
    }

    // Check authorization (organizer or admin)
    $isOrganizer = $eventModel->isOrganizer($eventId, $user['id']);
    if (!$isOrganizer && !is_admin()) {
        echo '<div class="alert alert-danger" role="alert">You do not have permission to delete this event.</div>';
        exit;
    }

    // Log activity before deletion
    $activityModel = new Activity();
    $activityModel->logActivity(
        $user['id'],
        'event_deleted',
        'event',
        $eventId,
        "Deleted event: " . $event['title']
    );

    // Delete event
    $success = $eventModel->deleteEvent($eventId);

    if (!$success) {
        echo '<div class="alert alert-danger" role="alert">Failed to delete event. Please try again.</div>';
        exit;
    }

    // Return success and close modal + refresh calendar
    echo '<div class="alert alert-success" role="alert">Event deleted successfully!</div>';
    echo '<script>
        setTimeout(function() {
            const modal = document.getElementById("viewEventModal") || document.getElementById("editEventModal");
            const backdrop = document.querySelector(".modal-backdrop");
            if (modal) modal.remove();
            if (backdrop) backdrop.remove();
            if (window.calendarInstance) {
                window.calendarInstance.refetchEvents();
            }
        }, 500);
    </script>';

} catch (Exception $e) {
    error_log("Error deleting event: " . $e->getMessage());
    echo '<div class="alert alert-danger" role="alert">An error occurred while deleting the event. Please try again.</div>';
}
