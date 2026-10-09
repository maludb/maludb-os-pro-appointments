<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../models/Event.php';
require_once __DIR__ . '/../../../models/Task.php';

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

// Check authorization
$isOrganizer = $eventModel->isOrganizer($eventId, $user['id']);
if (!$isOrganizer && !is_admin()) {
    echo '<div class="alert alert-danger" role="alert">You do not have permission to edit this event.</div>';
    exit;
}

// Get current team context
$teamId = $_SESSION['selected_team_id'] ?? null;

// Get team members for attendees
$taskModel = new Task();
$teamMembers = $taskModel->getAssignableUsers($user['id'], $teamId);

// Get current attendees
$attendees = $eventModel->getEventAttendees($eventId);
$attendeeIds = array_column($attendees, 'user_id');

// Format datetime for input
$startDateTime = date('Y-m-d\TH:i', strtotime($event['start_datetime']));
$endDateTime = date('Y-m-d\TH:i', strtotime($event['end_datetime']));
?>

<!-- Event Edit Modal -->
<div class="modal fade show" id="editEventModal" tabindex="-1" aria-labelledby="editEventModalLabel" style="display: block;" aria-modal="true">
    <div class="modal-dialog modal-lg" id="edit-event-modal-dialog">
        <div class="modal-content" id="edit-event-modal-content">
            <div class="modal-header" id="edit-event-modal-header">
                <h5 class="modal-title" id="editEventModalLabel">
                    <i class="feather-edit me-2"></i>Edit Event
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="editEventForm"
                  hx-post="/partials/calendar/update.php?id=<?= $eventId ?>"
                  hx-target="#event-edit-result"
                  hx-indicator="#edit-event-loading">

                <div class="modal-body" id="edit-event-modal-body">
                    <!-- Result Messages -->
                    <div id="event-edit-result"></div>

                    <!-- Title (Required) -->
                    <div class="mb-3" id="edit-event-title-group">
                        <label for="edit-event-title" class="form-label">
                            Title <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control"
                               id="edit-event-title"
                               name="title"
                               required
                               maxlength="255"
                               value="<?= htmlspecialchars($event['title']) ?>"
                               placeholder="Enter event title">
                    </div>

                    <!-- Description -->
                    <div class="mb-3" id="edit-event-description-group">
                        <label for="edit-event-description" class="form-label">Description</label>
                        <textarea class="form-control"
                                  id="edit-event-description"
                                  name="description"
                                  rows="3"
                                  placeholder="Enter event description (optional)"><?= htmlspecialchars($event['description']) ?></textarea>
                    </div>

                    <!-- Location -->
                    <div class="mb-3" id="edit-event-location-group">
                        <label for="edit-event-location" class="form-label">Location</label>
                        <input type="text"
                               class="form-control"
                               id="edit-event-location"
                               name="location"
                               maxlength="255"
                               value="<?= htmlspecialchars($event['location']) ?>"
                               placeholder="e.g., Conference Room A, Zoom, etc.">
                    </div>

                    <div class="row" id="edit-event-datetime-row">
                        <!-- Start DateTime -->
                        <div class="col-md-6 mb-3" id="edit-event-start-group">
                            <label for="edit-event-start" class="form-label">
                                Start Date & Time <span class="text-danger">*</span>
                            </label>
                            <input type="<?= $event['all_day'] ? 'date' : 'datetime-local' ?>"
                                   class="form-control"
                                   id="edit-event-start"
                                   name="start_datetime"
                                   required
                                   value="<?= $event['all_day'] ? date('Y-m-d', strtotime($event['start_datetime'])) : $startDateTime ?>">
                        </div>

                        <!-- End DateTime -->
                        <div class="col-md-6 mb-3" id="edit-event-end-group">
                            <label for="edit-event-end" class="form-label">
                                End Date & Time <span class="text-danger">*</span>
                            </label>
                            <input type="<?= $event['all_day'] ? 'date' : 'datetime-local' ?>"
                                   class="form-control"
                                   id="edit-event-end"
                                   name="end_datetime"
                                   required
                                   value="<?= $event['all_day'] ? date('Y-m-d', strtotime($event['end_datetime'])) : $endDateTime ?>">
                        </div>
                    </div>

                    <!-- All Day Checkbox -->
                    <div class="mb-3 form-check" id="edit-event-allday-group">
                        <input type="checkbox"
                               class="form-check-input"
                               id="edit-event-allday"
                               name="all_day"
                               value="1"
                               <?= $event['all_day'] ? 'checked' : '' ?>
                               onchange="toggleEditTimeFields(this)">
                        <label class="form-check-label" for="edit-event-allday">
                            All Day Event
                        </label>
                    </div>

                    <div class="row" id="edit-event-meta-row">
                        <!-- Event Type -->
                        <div class="col-md-6 mb-3" id="edit-event-type-group">
                            <label for="edit-event-type" class="form-label">Event Type</label>
                            <select class="form-select" id="edit-event-type" name="type">
                                <option value="event" <?= $event['type'] === 'event' ? 'selected' : '' ?>>Event</option>
                                <option value="meeting" <?= $event['type'] === 'meeting' ? 'selected' : '' ?>>Meeting</option>
                                <option value="appointment" <?= $event['type'] === 'appointment' ? 'selected' : '' ?>>Appointment</option>
                                <option value="reminder" <?= $event['type'] === 'reminder' ? 'selected' : '' ?>>Reminder</option>
                            </select>
                        </div>

                        <!-- Color -->
                        <div class="col-md-6 mb-3" id="edit-event-color-group">
                            <label for="edit-event-color" class="form-label">Color</label>
                            <select class="form-select" id="edit-event-color" name="color">
                                <option value="#0d6efd" <?= $event['color'] === '#0d6efd' ? 'selected' : '' ?>>Blue</option>
                                <option value="#198754" <?= $event['color'] === '#198754' ? 'selected' : '' ?>>Green</option>
                                <option value="#0dcaf0" <?= $event['color'] === '#0dcaf0' ? 'selected' : '' ?>>Cyan</option>
                                <option value="#ffc107" <?= $event['color'] === '#ffc107' ? 'selected' : '' ?>>Yellow</option>
                                <option value="#fd7e14" <?= $event['color'] === '#fd7e14' ? 'selected' : '' ?>>Orange</option>
                                <option value="#dc3545" <?= $event['color'] === '#dc3545' ? 'selected' : '' ?>>Red</option>
                                <option value="#6f42c1" <?= $event['color'] === '#6f42c1' ? 'selected' : '' ?>>Purple</option>
                            </select>
                            <div class="mt-2" id="color-preview-edit" style="width: 50px; height: 30px; background-color: <?= htmlspecialchars($event['color']) ?>; border-radius: 4px;"></div>
                        </div>

                        <!-- Status -->
                        <div class="col-md-6 mb-3" id="edit-event-status-group">
                            <label for="edit-event-status" class="form-label">Status</label>
                            <select class="form-select" id="edit-event-status" name="status">
                                <option value="scheduled" <?= $event['status'] === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                                <option value="cancelled" <?= $event['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                <option value="completed" <?= $event['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                            </select>
                        </div>
                    </div>

                    <!-- Attendees -->
                    <div class="mb-3" id="edit-event-attendees-group">
                        <label for="edit-event-attendees" class="form-label">Attendees</label>
                        <select class="form-select"
                                id="edit-event-attendees"
                                name="attendees[]"
                                multiple
                                size="5">
                            <?php foreach ($teamMembers as $member): ?>
                                <option value="<?= $member['id'] ?>" <?= in_array($member['id'], $attendeeIds) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?>
                                    <?= $member['id'] == $user['id'] ? '(You)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Hold Ctrl/Cmd to select multiple attendees</div>
                    </div>

                    <!-- CSRF Token -->
                    <?php echo csrf_field(); ?>
                </div>

                <div class="modal-footer" id="edit-event-modal-footer">
                    <!-- Loading Indicator -->
                    <div id="edit-event-loading" class="htmx-indicator">
                        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        Updating event...
                    </div>

                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btn-edit-event-submit">
                        <i class="feather-check-lg me-2"></i>Update Event
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Backdrop -->
<div class="modal-backdrop fade show"></div>

<script>
// Show modal
document.getElementById('editEventModal').classList.add('show');

// Color preview update
document.getElementById('edit-event-color').addEventListener('change', function() {
    document.getElementById('color-preview-edit').style.backgroundColor = this.value;
});

// Toggle time fields for all-day events
function toggleEditTimeFields(checkbox) {
    const startInput = document.getElementById('edit-event-start');
    const endInput = document.getElementById('edit-event-end');

    if (checkbox.checked) {
        // Convert to date-only inputs
        const startDate = startInput.value.split('T')[0];
        const endDate = endInput.value.split('T')[0];
        startInput.type = 'date';
        endInput.type = 'date';
        startInput.value = startDate;
        endInput.value = endDate;
    } else {
        // Convert back to datetime inputs
        startInput.type = 'datetime-local';
        endInput.type = 'datetime-local';
    }
}

// Close modal handler
document.querySelectorAll('[data-bs-dismiss="modal"]').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('editEventModal').remove();
        document.querySelector('.modal-backdrop').remove();
    });
});
</script>

<style>
.modal.show {
    background: rgba(0, 0, 0, 0.5);
}
</style>
