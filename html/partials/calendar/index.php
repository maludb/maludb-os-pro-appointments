<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../models/Task.php';
require_once __DIR__ . '/../../../models/Team.php';

check_auth();
$user = get_user();

// Get current team context
$teamId = $_SESSION['selected_team_id'] ?? null;

// Get team members for filter
$taskModel = new Task();
$teamMembers = $taskModel->getAssignableUsers($user['id'], $teamId);
?>

<!-- Calendar Page Container -->
<div class="container-fluid" id="calendar-page">
    <!-- Page Header -->
    <div class="row mt-2 mx-2 mb-0" id="calendar-page-header">
        <div class="col-12">
            <div class="card bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div id="calendar-header-title">
                            <h4 class="mb-1 text-dark"><i class="feather-calendar me-2"></i>Calendar</h4>
                            <p class="mb-0 text-dark">View and manage tasks and events</p>
                        </div>
                        <div id="calendar-header-actions">
                            <button class="btn btn-light"
                                    id="btn-calendar-create-event"
                                    hx-get="/partials/calendar/create-form.php"
                                    hx-target="#modal-container"
                                    hx-swap="innerHTML">
                                <i class="feather-plus me-2"></i>Create Event
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Row -->
    <div class="row mb-3" id="calendar-filters-row">
        <div class="col-12">
            <div class="card" id="calendar-filters-card">
                <div class="card-body">
                    <div class="row g-3" id="calendar-filters-container">
                        <!-- Show Filter -->
                        <div class="col-lg-3 col-md-4 col-sm-6" id="calendar-show-filter-col">
                            <label class="form-label small text-muted" for="calendar-filter-show">
                                <i class="feather-eye me-1"></i>Show
                            </label>
                            <select name="show"
                                    id="calendar-filter-show"
                                    class="form-select">
                                <option value="both" selected>Tasks & Events</option>
                                <option value="tasks">Tasks Only</option>
                                <option value="events">Events Only</option>
                            </select>
                        </div>

                        <!-- Team Members Filter -->
                        <div class="col-lg-3 col-md-4 col-sm-6" id="calendar-members-filter-col">
                            <label class="form-label small text-muted" for="calendar-filter-members">
                                <i class="feather-users me-1"></i>Team Members
                            </label>
                            <select name="members"
                                    id="calendar-filter-members"
                                    class="form-select">
                                <option value="">All Members</option>
                                <?php foreach ($teamMembers as $member): ?>
                                    <option value="<?= $member['id'] ?>">
                                        <?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Event Type Filter -->
                        <div class="col-lg-3 col-md-4 col-sm-6" id="calendar-event-type-filter-col">
                            <label class="form-label small text-muted" for="calendar-filter-event-type">
                                <i class="feather-bookmark me-1"></i>Event Type
                            </label>
                            <select name="event_types"
                                    id="calendar-filter-event-type"
                                    class="form-select">
                                <option value="">All Types</option>
                                <option value="event">Event</option>
                                <option value="meeting">Meeting</option>
                                <option value="appointment">Appointment</option>
                                <option value="reminder">Reminder</option>
                            </select>
                        </div>

                        <!-- Color By Filter -->
                        <div class="col-lg-3 col-md-4 col-sm-6" id="calendar-color-filter-col">
                            <label class="form-label small text-muted" for="calendar-filter-color">
                                <i class="feather-droplet me-1"></i>Color By
                            </label>
                            <select name="color_by"
                                    id="calendar-filter-color"
                                    class="form-select">
                                <option value="priority" selected>Priority</option>
                                <option value="status">Status</option>
                                <option value="type">Event Type</option>
                            </select>
                        </div>

                        <!-- Apply Filters Button -->
                        <div class="col-12" id="calendar-filter-actions-col">
                            <button type="button"
                                    class="btn btn-sm btn-primary"
                                    id="btn-calendar-apply-filters"
                                    onclick="refreshCalendar()">
                                <i class="feather-filter me-1"></i>Apply Filters
                            </button>
                            <button type="button"
                                    class="btn btn-sm btn-outline-secondary"
                                    id="btn-calendar-clear-filters"
                                    onclick="clearCalendarFilters()">
                                <i class="feather-x-circle me-1"></i>Clear Filters
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Calendar Container -->
    <div class="row" id="calendar-view-row">
        <div class="col-12">
            <div class="card" id="calendar-view-card">
                <div class="card-body">
                    <div id="calendar-container"></div>
                    <div class="text-center py-5" id="calendar-loading-indicator">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="text-muted mt-2">Loading calendar...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Container -->
<div id="modal-container"></div>

<!-- FullCalendar CSS -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet" />

<!-- FullCalendar JS -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>

<script>
function initializeCalendar() {
    const calendarEl = document.getElementById('calendar-container');
    const loadingIndicator = document.getElementById('calendar-loading-indicator');

    if (!calendarEl) {
        console.error('Calendar container not found');
        return;
    }

    // Check if FullCalendar is loaded
    if (typeof FullCalendar === 'undefined') {
        console.log('Waiting for FullCalendar library to load...');
        // Retry after a short delay
        setTimeout(initializeCalendar, 100);
        return;
    }

    console.log('Initializing FullCalendar...');

    // Initialize FullCalendar
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        editable: true,
        droppable: true,
        height: 'auto',
        eventSources: [
            {
                url: '/partials/calendar/feed.php',
                method: 'GET',
                extraParams: function() {
                    return getFilterParams();
                },
                failure: function(error) {
                    console.error('Error loading events:', error);
                    alert('Failed to load calendar events. Please try again.');
                }
            }
        ],
        eventClick: function(info) {
            const eventType = info.event.extendedProps.type;
            const id = eventType === 'task' ? info.event.extendedProps.taskId : info.event.extendedProps.eventId;

            if (eventType === 'task') {
                // Open task details modal
                htmx.ajax('GET', '/partials/tasks/view.php?id=' + id, {
                    target: '#modal-container',
                    swap: 'innerHTML'
                });
            } else {
                // Open event details modal
                htmx.ajax('GET', '/partials/calendar/view.php?id=' + id, {
                    target: '#modal-container',
                    swap: 'innerHTML'
                });
            }
        },
        eventDrop: function(info) {
            const eventType = info.event.extendedProps.type;
            const id = eventType === 'task' ? info.event.extendedProps.taskId : info.event.extendedProps.eventId;
            const newDate = info.event.start.toISOString().split('T')[0];

            if (eventType === 'task') {
                // Update task due date
                htmx.ajax('POST', '/partials/calendar/update-task-date.php', {
                    values: {
                        task_id: id,
                        due_date: newDate
                    },
                    swap: 'none'
                }).then(() => {
                    calendar.refetchEvents();
                }).catch((error) => {
                    console.error('Error updating task:', error);
                    info.revert();
                    alert('Failed to update task date. Please try again.');
                });
            } else {
                // Event drag-drop not implemented yet
                info.revert();
                alert('Event rescheduling coming soon!');
            }
        },
        dateClick: function(info) {
            // Open create event form with pre-filled date
            htmx.ajax('GET', '/partials/calendar/create-form.php?date=' + info.dateStr, {
                target: '#modal-container',
                swap: 'innerHTML'
            });
        },
        loading: function(isLoading) {
            if (isLoading) {
                loadingIndicator.style.display = 'block';
            } else {
                loadingIndicator.style.display = 'none';
            }
        }
    });

    calendar.render();

    // Store calendar instance globally for refresh
    window.calendarInstance = calendar;

    // Hide loading indicator after initial render
    if (loadingIndicator) {
        loadingIndicator.style.display = 'none';
    }
}

// Start initialization
initializeCalendar();

// Get filter parameters
function getFilterParams() {
    const show = document.getElementById('calendar-filter-show').value;
    const colorBy = document.getElementById('calendar-filter-color').value;
    const members = document.getElementById('calendar-filter-members').value;
    const eventTypes = document.getElementById('calendar-filter-event-type').value;

    return {
        show: show,
        color_by: colorBy,
        members: members,
        event_types: eventTypes,
        team_id: <?= $teamId ?? 'null' ?>
    };
}

// Refresh calendar with current filters
function refreshCalendar() {
    if (window.calendarInstance) {
        window.calendarInstance.refetchEvents();
    }
}

// Clear all filters
function clearCalendarFilters() {
    document.getElementById('calendar-filter-show').value = 'both';
    document.getElementById('calendar-filter-color').value = 'priority';
    document.getElementById('calendar-filter-members').value = '';
    document.getElementById('calendar-filter-event-type').value = '';
    refreshCalendar();
}

// Listen for HTMX events to refresh calendar
document.body.addEventListener('eventCreated', function() {
    refreshCalendar();
});

document.body.addEventListener('eventUpdated', function() {
    refreshCalendar();
});

document.body.addEventListener('eventDeleted', function() {
    refreshCalendar();
});
</script>

<style>
/* Calendar custom styles */
#calendar-container {
    padding: 1rem;
}

.fc {
    /* FullCalendar responsive adjustments */
}

.fc-event {
    cursor: pointer;
    border-radius: 4px;
    padding: 2px 4px;
}

.fc-event:hover {
    opacity: 0.9;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

/* Loading indicator initially hidden */
#calendar-loading-indicator {
    display: none;
}
</style>
