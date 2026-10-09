<?php
/**
 * Team Member Profile View
 *
 * Displays detailed profile information for a team member
 */

require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../models/Team.php';
require_once __DIR__ . '/../../../models/Task.php';

check_auth();
$currentUser = get_user();

// Get member ID from GET parameter
$memberId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$memberId) {
    echo '<div class="alert alert-danger" id="member-error">Invalid member ID</div>';
    exit;
}

try {
    // Get current team context
    $teamId = $_SESSION['selected_team_id'] ?? null;
    $teamModel = new Team();

    // Get all team members and find the requested one
    $teamMembers = $teamModel->getTeamMembers($teamId);
    $member = null;

    foreach ($teamMembers as $tm) {
        if ($tm['id'] == $memberId) {
            $member = $tm;
            break;
        }
    }

    if (!$member) {
        echo '<div class="alert alert-danger" id="member-not-found">Team member not found</div>';
        exit;
    }

    // Get member's tasks - get recent tasks assigned to this member in the team
    $taskModel = new Task();
    // Search for tasks assigned to this team member, filtered by team context
    $filters = [];
    if ($teamId) {
        $filters['team_id'] = $teamId;
    }
    $result = $taskModel->search($memberId, $filters, ['column' => 'created_at', 'direction' => 'DESC'], 1, 5);
    $memberTasks = $result['tasks'] ?? [];

    $completedTasks = count(array_filter($memberTasks, fn($t) => $t['status'] === 'completed'));
    $activeTasks = count(array_filter($memberTasks, fn($t) => in_array($t['status'], ['pending', 'in_progress', 'review'])));

} catch (Exception $e) {
    error_log("Error loading team member: " . $e->getMessage());
    echo '<div class="alert alert-danger" id="member-load-error">Failed to load team member profile</div>';
    exit;
}

// Determine initials for avatar
$initials = strtoupper(substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1));

?>

<!-- Team Member Profile Container -->
<div class="container-fluid" id="team-member-profile-container">

    <!-- Header Section with Background -->
    <div class="mx-n4 mb-4 p-4 bg-primary" id="member-header-bg">
        <div class="d-flex align-items-center flex-row">
            <!-- Avatar -->
            <div class="avatar bg-white text-primary rounded-circle d-flex align-items-center justify-content-center me-3"
                 style="width: 80px; height: 80px; font-size: 32px; font-weight: bold;">
                <?php echo htmlspecialchars($initials); ?>
            </div>

            <!-- Member Info -->
            <div class="ms-3 text-white">
                <h4 class="mb-1" id="member-full-name">
                    <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                    <?php if ($memberId == $currentUser['id']): ?>
                        <span class="badge bg-info ms-2">You</span>
                    <?php endif; ?>
                </h4>
                <div class="d-flex gap-2">
                    <span class="badge bg-<?php echo $member['team_role'] === 'admin' ? 'danger' : ($member['team_role'] === 'lead' ? 'warning' : 'secondary'); ?>"
                          id="member-role-badge">
                        <?php echo ucfirst(htmlspecialchars($member['team_role'])); ?> Role
                    </span>
                    <small class="text-white" id="member-joined">
                        Joined <?php echo date('M j, Y', strtotime($member['joined_at'])); ?>
                    </small>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="ms-auto">
                <?php if ($memberId != $currentUser['id']): ?>
                    <button class="btn btn-light"
                            id="btn-assign-task"
                            hx-get="/partials/tasks/create-form.php?assign_to=<?php echo $memberId; ?>"
                            hx-target="#modal-container"
                            hx-swap="innerHTML">
                        <i class="feather-plus-circle me-1"></i>Assign Task
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Content Section -->
    <div class="row gx-4" id="member-content-row">

        <!-- Left Column: Tasks and Info -->
        <div class="col-xl-4 col-sm-6" id="member-left-col">

            <!-- Contact Info Card -->
            <div class="card mb-4" id="member-contact-card">
                <div class="card-header bg-light" id="contact-header">
                    <h5 class="card-title mb-0">
                        <i class="feather-mail me-2"></i>Contact Information
                    </h5>
                </div>
                <div class="card-body" id="member-contact-body">
                    <div class="mb-3">
                        <small class="text-muted d-block">Email Address</small>
                        <a href="mailto:<?php echo htmlspecialchars($member['email']); ?>" id="member-email">
                            <?php echo htmlspecialchars($member['email']); ?>
                        </a>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block">Username</small>
                        <p class="mb-0" id="member-username">@<?php echo htmlspecialchars($member['username']); ?></p>
                    </div>
                    <div>
                        <small class="text-muted d-block">Member Since</small>
                        <p class="mb-0" id="member-joined-date">
                            <?php echo date('F j, Y', strtotime($member['joined_at'])); ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Tasks Summary Card -->
            <div class="card mb-4" id="member-tasks-summary-card">
                <div class="card-header bg-light" id="tasks-header">
                    <h5 class="card-title mb-0">
                        <i class="feather-check-circle me-2"></i>Task Summary
                    </h5>
                </div>
                <div class="card-body" id="member-tasks-body">
                    <div class="row g-3">
                        <div class="col-6" id="active-tasks-col">
                            <div class="icon-box sm bg-primary rounded-5 me-2 d-inline-flex align-items-center justify-content-center">
                                <i class="feather-clock text-white"></i>
                            </div>
                            <div class="d-inline-block">
                                <h4 class="m-0" id="active-tasks-count"><?php echo $activeTasks; ?></h4>
                                <p class="m-0 small text-muted">Active Tasks</p>
                            </div>
                        </div>
                        <div class="col-6" id="completed-tasks-col">
                            <div class="icon-box sm bg-success rounded-5 me-2 d-inline-flex align-items-center justify-content-center">
                                <i class="feather-check-circle text-white"></i>
                            </div>
                            <div class="d-inline-block">
                                <h4 class="m-0" id="completed-tasks-count"><?php echo $completedTasks; ?></h4>
                                <p class="m-0 small text-muted">Completed</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Column: Recent Tasks -->
        <div class="col-xl-8 col-sm-12" id="member-right-col">

            <!-- Recent Tasks Card -->
            <div class="card mb-4" id="member-recent-tasks-card">
                <div class="card-header bg-light" id="recent-tasks-header">
                    <h5 class="card-title mb-0">
                        <i class="feather-list-task me-2"></i>Recent Tasks
                    </h5>
                </div>
                <div class="card-body p-0" id="member-recent-tasks-body">
                    <?php if (empty($memberTasks)): ?>
                        <div class="p-4 text-center text-muted" id="no-tasks">
                            <i class="feather-inbox fs-3 mb-2 d-block"></i>
                            <p class="mb-0">No tasks assigned yet</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive" id="tasks-table-wrapper">
                            <table class="table table-hover mb-0" id="member-tasks-table">
                                <thead class="table-light">
                                    <tr id="tasks-table-header">
                                        <th id="col-title">Task Title</th>
                                        <th id="col-status">Status</th>
                                        <th id="col-priority">Priority</th>
                                        <th id="col-due">Due Date</th>
                                    </tr>
                                </thead>
                                <tbody id="tasks-table-body">
                                    <?php foreach ($memberTasks as $task): ?>
                                        <tr id="task-row-<?php echo $task['id']; ?>">
                                            <td id="task-title-<?php echo $task['id']; ?>">
                                                <a href="#"
                                                   hx-get="/partials/tasks/view.php?id=<?php echo $task['id']; ?>"
                                                   hx-target="#page-content"
                                                   class="text-decoration-none">
                                                    <?php echo htmlspecialchars(substr($task['title'], 0, 40)); ?>
                                                </a>
                                            </td>
                                            <td id="task-status-<?php echo $task['id']; ?>">
                                                <span class="badge bg-<?php
                                                    echo match($task['status']) {
                                                        'completed' => 'success',
                                                        'in_progress' => 'primary',
                                                        'review' => 'info',
                                                        'cancelled' => 'danger',
                                                        default => 'secondary'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($task['status']))); ?>
                                                </span>
                                            </td>
                                            <td id="task-priority-<?php echo $task['id']; ?>">
                                                <span class="badge bg-<?php
                                                    echo match($task['priority']) {
                                                        'critical' => 'danger',
                                                        'high' => 'warning',
                                                        'medium' => 'info',
                                                        'low' => 'success',
                                                        default => 'secondary'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst(htmlspecialchars($task['priority'])); ?>
                                                </span>
                                            </td>
                                            <td id="task-due-<?php echo $task['id']; ?>">
                                                <?php if ($task['due_date']): ?>
                                                    <small class="text-muted">
                                                        <?php echo date('M j, Y', strtotime($task['due_date'])); ?>
                                                    </small>
                                                <?php else: ?>
                                                    <small class="text-muted">—</small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<style>
.avatar {
    font-weight: bold;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.icon-box {
    width: 40px;
    height: 40px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.icon-box.sm {
    width: 36px;
    height: 36px;
    font-size: 16px;
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.02);
}

.badge {
    font-size: 0.75rem;
}
</style>
