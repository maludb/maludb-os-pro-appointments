<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../models/Task.php';

check_auth();
$user = get_user();

// Get current team context
$teamId = $_SESSION['selected_team_id'] ?? null;

// Get team members for attendees
$taskModel = new Task();
$teamMembers = $taskModel->getAssignableUsers($user['id'], $teamId);

// Get pre-filled date if provided
$prefilledDate = $_GET['date'] ?? date('Y-m-d');
?>

<!-- Event Create Modal -->
<div class="modal fade show" id="createEventModal" tabindex="-1" aria-labelledby="createEventModalLabel" style="display: block;" aria-modal="true">
    <div class="modal-dialog modal-lg" id="create-event-modal-dialog">
        <div class="modal-content" id="create-event-modal-content">
            <div class="modal-header" id="create-event-modal-header">
                <h5 class="modal-title" id="createEventModalLabel">
                    <i class="feather-calendar-plus me-2"></i>Create New Event
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="createEventForm"
                  hx-post="/partials/calendar/create.php"
                  hx-target="#event-create-result"
                  hx-indicator="#create-event-loading">

                <div class="modal-body" id="create-event-modal-body">
                    <!-- Result Messages -->
                    <div id="event-create-result"></div>

                    <!-- Title (Required) -->
                    <div class="mb-3" id="create-event-title-group">
                        <label for="create-event-title" class="form-label">
                            Title <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control"
                               id="create-event-title"
                               name="title"
                               required
                               maxlength="255"
                               placeholder="Enter event title">
                    </div>

                    <!-- Description -->
                    <div class="mb-3" id="create-event-description-group">
                        <label for="create-event-description" class="form-label">Description</label>
                        <textarea class="form-control"
                                  id="create-event-description"
                                  name="description"
                                  rows="3"
                                  placeholder="Enter event description (optional)"></textarea>
                    </div>

                    <!-- Location -->
                    <div class="mb-3" id="create-event-location-group">
                        <label for="create-event-location" class="form-label">Location</label>
                        <input type="text"
                               class="form-control"
                               id="create-event-location"
                               name="location"
                               maxlength="255"
                               placeholder="e.g., Conference Room A, Zoom, etc.">
                    </div>

                    <div class="row" id="create-event-datetime-row">
                        <!-- Start DateTime -->
                        <div class="col-md-6 mb-3" id="create-event-start-group">
                            <label for="create-event-start" class="form-label">
                                Start Date & Time <span class="text-danger">*</span>
                            </label>
                            <input type="datetime-local"
                                   class="form-control"
                                   id="create-event-start"
                                   name="start_datetime"
                                   required
                                   value="<?php echo htmlspecialchars($prefilledDate . 'T09:00'); ?>">
                        </div>

                        <!-- End DateTime -->
                        <div class="col-md-6 mb-3" id="create-event-end-group">
                            <label for="create-event-end" class="form-label">
                                End Date & Time <span class="text-danger">*</span>
                            </label>
                            <input type="datetime-local"
                                   class="form-control"
                                   id="create-event-end"
                                   name="end_datetime"
                                   required
                                   value="<?php echo htmlspecialchars($prefilledDate . 'T10:00'); ?>">
                        </div>
                    </div>

                    <!-- All Day Checkbox -->
                    <div class="mb-3 form-check" id="create-event-allday-group">
                        <input type="checkbox"
                               class="form-check-input"
                               id="create-event-allday"
                               name="all_day"
                               value="1"
                               onchange="toggleTimeFields(this)">
                        <label class="form-check-label" for="create-event-allday">
                            All Day Event
                        </label>
                    </div>

                    <div class="row" id="create-event-meta-row">
                        <!-- Event Type -->
                        <div class="col-md-6 mb-3" id="create-event-type-group">
                            <label for="create-event-type" class="form-label">Event Type</label>
                            <select class="form-select" id="create-event-type" name="type">
                                <option value="event" selected>Event</option>
                                <option value="meeting">Meeting</option>
                                <option value="appointment">Appointment</option>
                                <option value="reminder">Reminder</option>
                            </select>
                        </div>

                        <!-- Color -->
                        <div class="col-md-6 mb-3" id="create-event-color-group">
                            <label for="create-event-color" class="form-label">Color</label>
                            <select class="form-select" id="create-event-color" name="color">
                                <option value="#0d6efd" selected>Blue</option>
                                <option value="#198754">Green</option>
                                <option value="#0dcaf0">Cyan</option>
                                <option value="#ffc107">Yellow</option>
                                <option value="#fd7e14">Orange</option>
                                <option value="#dc3545">Red</option>
                                <option value="#6f42c1">Purple</option>
                            </select>
                            <div class="mt-2" id="color-preview" style="width: 50px; height: 30px; background-color: #0d6efd; border-radius: 4px;"></div>
                        </div>
                    </div>

                    <!-- Attendees -->
                    <div class="mb-3" id="create-event-attendees-group">
                        <label for="create-event-attendees" class="form-label">Attendees</label>
                        <select class="form-select"
                                id="create-event-attendees"
                                name="attendees[]"
                                multiple
                                size="5">
                            <?php foreach ($teamMembers as $member): ?>
                                <option value="<?= $member['id'] ?>" <?= $member['id'] == $user['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?>
                                    <?= $member['id'] == $user['id'] ? '(You)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Hold Ctrl/Cmd to select multiple attendees</div>
                    </div>

                    <!-- CSRF Token -->
                    <?php echo csrf_field(); ?>

                    <!-- Hidden team_id if applicable -->
                    <?php if ($teamId): ?>
                        <input type="hidden" name="team_id" value="<?= htmlspecialchars($teamId) ?>">
                    <?php endif; ?>
                </div>

                <div class="modal-footer" id="create-event-modal-footer">
                    <!-- Loading Indicator -->
                    <div id="create-event-loading" class="htmx-indicator">
                        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        Creating event...
                    </div>

                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btn-create-event-submit">
                        <i class="feather-check-lg me-2"></i>Create Event
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
document.getElementById('createEventModal').classList.add('show');

// Color preview update
document.getElementById('create-event-color').addEventListener('change', function() {
    document.getElementById('color-preview').style.backgroundColor = this.value;
});

// Toggle time fields for all-day events
function toggleTimeFields(checkbox) {
    const startInput = document.getElementById('create-event-start');
    const endInput = document.getElementById('create-event-end');

    if (checkbox.checked) {
        // Convert to date-only inputs
        startInput.type = 'date';
        endInput.type = 'date';
    } else {
        // Convert back to datetime inputs
        startInput.type = 'datetime-local';
        endInput.type = 'datetime-local';
    }
}

// Close modal handler
document.querySelectorAll('[data-bs-dismiss="modal"]').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('createEventModal').remove();
        document.querySelector('.modal-backdrop').remove();
    });
});
</script>

<style>
.modal.show {
    background: rgba(0, 0, 0, 0.5);
}
</style>
