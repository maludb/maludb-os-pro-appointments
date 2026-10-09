<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../models/Event.php';

check_auth();
$user = get_user();

// Get event ID
$eventId = $_GET['id'] ?? null;

if (!$eventId) {
    echo '<div class="alert alert-danger" role="alert">Event not found.</div>';
    exit;
}

// Get event details
$eventModel = new Event();
$event = $eventModel->getEventById($eventId);

if (!$event) {
    echo '<div class="alert alert-danger" role="alert">Event not found.</div>';
    exit;
}

// Get attendees
$attendees = $eventModel->getEventAttendees($eventId);

// Check if user is organizer
$isOrganizer = $eventModel->isOrganizer($eventId, $user['id']);

// Format dates
$startDate = new DateTime($event['start_datetime']);
$endDate = new DateTime($event['end_datetime']);
$allDay = (bool)$event['all_day'];

$dateFormat = $allDay ? 'M j, Y' : 'M j, Y g:i A';
$startFormatted = $startDate->format($dateFormat);
$endFormatted = $endDate->format($dateFormat);

// Get user's response status
$userResponse = null;
foreach ($attendees as $attendee) {
    if ($attendee['user_id'] == $user['id']) {
        $userResponse = $attendee['response_status'];
        break;
    }
}
?>

<!-- Event View Modal -->
<div class="modal fade show" id="viewEventModal" tabindex="-1" aria-labelledby="viewEventModalLabel" style="display: block;" aria-modal="true">
    <div class="modal-dialog modal-lg" id="view-event-modal-dialog">
        <div class="modal-content" id="view-event-modal-content">
            <div class="modal-header" id="view-event-modal-header">
                <h5 class="modal-title" id="viewEventModalLabel">
                    <i class="feather-calendar me-2"></i>Event Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body" id="view-event-modal-body">
                <!-- Event Title -->
                <div class="mb-3" id="view-event-title-section">
                    <h4><?= htmlspecialchars($event['title']) ?></h4>
                    <span class="badge" style="background-color: <?= htmlspecialchars($event['color']) ?>">
                        <?= ucfirst(htmlspecialchars($event['type'])) ?>
                    </span>
                    <span class="badge bg-secondary ms-1"><?= ucfirst(htmlspecialchars($event['status'])) ?></span>
                </div>

                <!-- Date/Time -->
                <div class="mb-3" id="view-event-datetime-section">
                    <h6><i class="feather-clock me-2"></i>When</h6>
                    <p class="mb-1">
                        <strong>Start:</strong> <?= htmlspecialchars($startFormatted) ?>
                    </p>
                    <p class="mb-1">
                        <strong>End:</strong> <?= htmlspecialchars($endFormatted) ?>
                    </p>
                    <?php if ($allDay): ?>
                        <span class="badge bg-info">All Day Event</span>
                    <?php endif; ?>
                </div>

                <!-- Location -->
                <?php if (!empty($event['location'])): ?>
                <div class="mb-3" id="view-event-location-section">
                    <h6><i class="feather-map-pin me-2"></i>Location</h6>
                    <p><?= htmlspecialchars($event['location']) ?></p>
                </div>
                <?php endif; ?>

                <!-- Description -->
                <?php if (!empty($event['description'])): ?>
                <div class="mb-3" id="view-event-description-section">
                    <h6><i class="feather-file-text me-2"></i>Description</h6>
                    <p><?= nl2br(htmlspecialchars($event['description'])) ?></p>
                </div>
                <?php endif; ?>

                <!-- Organizer -->
                <div class="mb-3" id="view-event-organizer-section">
                    <h6><i class="feather-user me-2"></i>Organized By</h6>
                    <p><?= htmlspecialchars($event['creator_first_name'] . ' ' . $event['creator_last_name']) ?></p>
                </div>

                <!-- Attendees -->
                <?php if (!empty($attendees)): ?>
                <div class="mb-3" id="view-event-attendees-section">
                    <h6><i class="feather-users me-2"></i>Attendees (<?= count($attendees) ?>)</h6>
                    <div class="list-group" id="view-event-attendees-list">
                        <?php foreach ($attendees as $attendee): ?>
                            <div class="list-group-item" id="attendee-<?= $attendee['id'] ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($attendee['first_name'] . ' ' . $attendee['last_name']) ?></strong>
                                        <?php if ($attendee['is_organizer']): ?>
                                            <span class="badge bg-primary ms-1">Organizer</span>
                                        <?php endif; ?>
                                        <?php if ($attendee['user_id'] == $user['id']): ?>
                                            <span class="badge bg-info ms-1">You</span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <?php
                                        $statusBadges = [
                                            'pending' => 'bg-secondary',
                                            'accepted' => 'bg-success',
                                            'declined' => 'bg-danger',
                                            'tentative' => 'bg-warning'
                                        ];
                                        $badgeClass = $statusBadges[$attendee['response_status']] ?? 'bg-secondary';
                                        ?>
                                        <span class="badge <?= $badgeClass ?>">
                                            <?= ucfirst(htmlspecialchars($attendee['response_status'])) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- User Response Actions -->
                <?php if ($userResponse !== null && !$isOrganizer): ?>
                <div class="mb-3" id="view-event-response-section">
                    <h6><i class="feather-thumbs-up me-2"></i>Your Response</h6>
                    <div class="btn-group" role="group" id="view-event-response-buttons">
                        <button type="button"
                                class="btn btn-sm <?= $userResponse === 'accepted' ? 'btn-success' : 'btn-outline-success' ?>"
                                hx-post="/partials/calendar/attendees/update-response.php"
                                hx-vals='{"event_id": "<?= $eventId ?>", "status": "accepted"}'
                                hx-target="#view-event-modal-content"
                                hx-swap="outerHTML">
                            Accept
                        </button>
                        <button type="button"
                                class="btn btn-sm <?= $userResponse === 'tentative' ? 'btn-warning' : 'btn-outline-warning' ?>"
                                hx-post="/partials/calendar/attendees/update-response.php"
                                hx-vals='{"event_id": "<?= $eventId ?>", "status": "tentative"}'
                                hx-target="#view-event-modal-content"
                                hx-swap="outerHTML">
                            Tentative
                        </button>
                        <button type="button"
                                class="btn btn-sm <?= $userResponse === 'declined' ? 'btn-danger' : 'btn-outline-danger' ?>"
                                hx-post="/partials/calendar/attendees/update-response.php"
                                hx-vals='{"event_id": "<?= $eventId ?>", "status": "declined"}'
                                hx-target="#view-event-modal-content"
                                hx-swap="outerHTML">
                            Decline
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="modal-footer" id="view-event-modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>

                <?php if ($isOrganizer || is_admin()): ?>
                    <button type="button"
                            class="btn btn-primary"
                            hx-get="/partials/calendar/edit-form.php?id=<?= $eventId ?>"
                            hx-target="#modal-container"
                            hx-swap="innerHTML">
                        <i class="feather-edit me-1"></i>Edit
                    </button>
                    <button type="button"
                            class="btn btn-danger"
                            hx-delete="/partials/calendar/delete.php?id=<?= $eventId ?>"
                            hx-confirm="Are you sure you want to delete this event?"
                            hx-target="#modal-container"
                            hx-swap="innerHTML">
                        <i class="feather-trash-2 me-1"></i>Delete
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Backdrop -->
<div class="modal-backdrop fade show"></div>

<script>
// Show modal
document.getElementById('viewEventModal').classList.add('show');

// Close modal handler
document.querySelectorAll('[data-bs-dismiss="modal"]').forEach(btn => {
    btn.addEventListener('click', function() {
        const modal = document.getElementById('viewEventModal');
        const backdrop = document.querySelector('.modal-backdrop');
        if (modal) modal.remove();
        if (backdrop) backdrop.remove();
    });
});
</script>

<style>
.modal.show {
    background: rgba(0, 0, 0, 0.5);
}
</style>
