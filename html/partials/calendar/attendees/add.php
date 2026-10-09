<?php
session_start();
require_once __DIR__ . '/../../../../helpers/auth.php';
require_once __DIR__ . '/../../../../models/Event.php';
require_once __DIR__ . '/../../../../models/Activity.php';

check_auth();
$user = get_user();

header('Content-Type: application/json');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    // Get parameters
    $eventId = $_POST['event_id'] ?? null;
    $userId = $_POST['user_id'] ?? null;

    if (!$eventId || !$userId) {
        echo json_encode(['success' => false, 'error' => 'Event ID and user ID are required']);
        exit;
    }

    // Check if current user is organizer
    $eventModel = new Event();
    $isOrganizer = $eventModel->isOrganizer($eventId, $user['id']);

    if (!$isOrganizer && !is_admin()) {
        echo json_encode(['success' => false, 'error' => 'Only organizers can add attendees']);
        exit;
    }

    // Add attendee
    $success = $eventModel->addAttendee($eventId, $userId, false);

    if (!$success) {
        echo json_encode(['success' => false, 'error' => 'Failed to add attendee']);
        exit;
    }

    // Log activity
    $event = $eventModel->getEventById($eventId);
    if ($event) {
        $activityModel = new Activity();
        $activityModel->logActivity(
            $user['id'],
            'event_attendee_added',
            'event',
            $eventId,
            "Added attendee to event: " . $event['title']
        );
    }

    echo json_encode(['success' => true, 'message' => 'Attendee added successfully']);

} catch (Exception $e) {
    error_log("Error adding attendee: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred']);
}
