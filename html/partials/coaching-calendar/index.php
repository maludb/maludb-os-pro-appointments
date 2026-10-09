<?php
require_once __DIR__ . '/../../../helpers/auth.php';
requireAuth();
?>

<div class="container-fluid" id="coaching-calendar-page">
    <!-- Page Header -->
    <div class="row mt-2 mx-2 mb-3" id="coaching-calendar-header">
        <div class="col-12">
            <div class="card bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body" id="coaching-calendar-header-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div id="coaching-calendar-header-title">
                            <h4 class="mb-1 text-dark"><i class="feather-calendar me-2"></i>Coaching Calendar</h4>
                            <p class="mb-0 text-dark">View past and upcoming coaching sessions</p>
                        </div>
                        <div id="coaching-calendar-header-actions">
                            <button class="btn btn-light" id="btn-schedule-session"
                                    hx-get="/partials/coaching-calendar/schedule.php"
                                    hx-target="#modal-container"
                                    hx-swap="innerHTML">
                                <i class="feather-plus me-2"></i>Schedule Session
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Legend -->
    <div class="row mx-2 mb-3" id="coaching-calendar-legend-row">
        <div class="col-12">
            <div class="d-flex gap-3 align-items-center" id="coaching-calendar-legend">
                <span class="d-flex align-items-center gap-1"><span class="badge bg-success">&nbsp;&nbsp;</span> Completed</span>
                <span class="d-flex align-items-center gap-1"><span class="badge bg-primary">&nbsp;&nbsp;</span> Upcoming</span>
            </div>
        </div>
    </div>

    <!-- Calendar Container -->
    <div class="row" id="coaching-calendar-view-row">
        <div class="col-12">
            <div class="card" id="coaching-calendar-view-card">
                <div class="card-body" id="coaching-calendar-view-body">
                    <div id="coaching-calendar-container"></div>
                    <div class="text-center py-5" id="coaching-calendar-loading">
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

<!-- FullCalendar CSS & JS -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>

<script>
function initCoachingCalendar() {
    var calendarEl = document.getElementById('coaching-calendar-container');
    var loadingEl = document.getElementById('coaching-calendar-loading');

    if (!calendarEl) return;

    if (typeof FullCalendar === 'undefined') {
        setTimeout(initCoachingCalendar, 100);
        return;
    }

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        height: 'auto',
        eventSources: [{
            url: '/partials/coaching-calendar/feed.php',
            method: 'GET'
        }],
        eventClick: function(info) {
            var sessionId = info.event.extendedProps.sessionId;
            var status = info.event.extendedProps.status;

            if (status === 'complete') {
                // Navigate to session review page
                htmx.ajax('GET', '/partials/sessions/detail.php?id=' + sessionId, {
                    target: '#page-content',
                    swap: 'innerHTML'
                });
            } else {
                // Show upcoming session detail modal
                htmx.ajax('GET', '/partials/coaching-calendar/view-session.php?id=' + sessionId, {
                    target: '#modal-container',
                    swap: 'innerHTML'
                });
            }
        },
        loading: function(isLoading) {
            if (loadingEl) {
                loadingEl.style.display = isLoading ? 'block' : 'none';
            }
        }
    });

    calendar.render();
    window.coachingCalendarInstance = calendar;

    if (loadingEl) loadingEl.style.display = 'none';
}

initCoachingCalendar();

// Refresh calendar when a session is scheduled
document.body.addEventListener('sessionScheduled', function() {
    if (window.coachingCalendarInstance) {
        window.coachingCalendarInstance.refetchEvents();
    }
});
</script>

<style>
#coaching-calendar-container { padding: 1rem; }
#coaching-calendar-loading { display: none; }
.fc-event { cursor: pointer; border-radius: 4px; padding: 2px 4px; }
.fc-event:hover { opacity: 0.9; transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
</style>
