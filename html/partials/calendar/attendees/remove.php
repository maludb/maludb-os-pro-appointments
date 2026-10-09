<?php
session_start();
require_once __DIR__ . '/../../../../helpers/auth.php';
require_once __DIR__ . '/../../../../models/Event.php';
require_once __DIR__ . '/../../../../models/Activity.php';

check_auth();
$user = get_user();

header('Content-Type: application/json');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    // Get parameters
    $eventId = $_POST['event_id'] ?? $_GET['event_id'] ?? null;
    $userId = $_POST['user_id'] ?? $_GET['user_id'] ?? null;

    if (!$eventId || !$userId) {
        echo json_encode(['success' => false, 'error' => 'Event ID and user ID are required']);
        exit;
    }

    // Check if current user is organizer
    $eventModel = new Event();
    $isOrganizer = $eventModel->isOrganizer($eventId, $user['id']);

    if (!$isOrganizer && !is_admin() && $userId != $user['id']) {
        echo json_encode(['success' => false, 'error' => 'Only organizers can remove attendees']);
        exit;
    }

    // Remove attendee
    $success = $eventModel->removeAttendee($eventId, $userId);

    if (!$success) {
        echo json_encode(['success' => false, 'error' => 'Failed to remove attendee']);
        exit;
    }

    // Log activity
    $event = $eventModel->getEventById($eventId);
    if ($event) {
        $activityModel = new Activity();
        $activityModel->logActivity(
            $user['id'],
            'event_attendee_removed',
            'event',
            $eventId,
            "Removed attendee from event: " . $event['title']
        );
    }

    echo json_encode(['success' => true, 'message' => 'Attendee removed successfully']);

} catch (Exception $e) {
    error_log("Error removing attendee: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred']);
}
