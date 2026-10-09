<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../models/Team.php';
require_once __DIR__ . '/../../../models/User.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

check_auth();
is_admin() or die('Access denied');

$user = get_user();
$csrf_token = generate_csrf_token();
$successMessage = null;
$errorMessage = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errorMessage = 'Invalid security token. Please try again.';
    } else {
        try {
            $teamId = isset($_POST['team_id']) ? (int)$_POST['team_id'] : null;
            $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : null;
            $role = isset($_POST['role']) ? trim($_POST['role']) : 'member';

            error_log("Add member attempt: team_id=$teamId, user_id=$userId, role=$role");

            // Validate inputs
            if (!$teamId || !$userId) {
                $errorMessage = 'Please select a team and a user.';
            } elseif (!in_array($role, ['member', 'lead', 'admin'])) {
                $errorMessage = 'Invalid role selected.';
            } else {
                // Add member to team
                $teamModel = new Team();
                $result = $teamModel->addMember($teamId, $userId, $role);

                error_log("Add member result: " . ($result ? "success" : "failed"));

                if ($result) {
                    $successMessage = 'Team member added successfully!';
                    // Clear form
                    $_POST = [];
                } else {
                    $errorMessage = 'Failed to add team member. They may already be a member of this team.';
                }
            }
        } catch (Exception $e) {
            error_log("Error adding team member: " . $e->getMessage());
            $errorMessage = 'An error occurred: ' . $e->getMessage();
        }
    }
}

// Get all teams for dropdown
$teamModel = new Team();
$teams = $teamModel->getAllTeams();
?>

<!-- Add Team Member Page Container -->
<div class="container-fluid" id="add-member-page">
    <!-- Page Header -->
    <div class="row mb-4" id="add-member-header">
        <div class="col-12">
            <div class="card bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body">
                    <h4 class="mb-1 text-dark">
                        <i class="feather-user-plus me-2"></i>Add Team Member
                    </h4>
                    <p class="mb-0 text-dark">Add a new member to a team</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <div class="row mb-4" id="add-member-messages">
        <div class="col-12">
            <?php if ($successMessage): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert" id="success-message">
                    <i class="feather-check-circle-fill me-2"></i><?php echo htmlspecialchars($successMessage); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($errorMessage): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert" id="error-message">
                    <i class="feather-alert-triangle-fill me-2"></i><?php echo htmlspecialchars($errorMessage); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Member Form -->
    <div class="row" id="add-member-form-container">
        <div class="col-lg-6 col-12">
            <div class="card" id="add-member-card">
                <div class="card-header bg-light" id="form-header">
                    <h5 class="card-title mb-0">Add Member Details</h5>
                </div>
                <div class="card-body" id="form-body">
                    <form id="add-member-form"
                          hx-post="/partials/team/add-member.php"
                          hx-target="#add-member-page"
                          hx-swap="innerHTML">
                        <?php echo csrf_field(); ?>

                        <!-- Team Selection -->
                        <div class="mb-3">
                            <label for="teamSelect" class="form-label">Team <span class="text-danger">*</span></label>
                            <select class="form-select" id="teamSelect" name="team_id" required
                                    hx-get="/partials/team/get-available-users.php?team_id={this.value}"
                                    hx-target="#userSelect"
                                    hx-trigger="change"
                                    hx-swap="innerHTML">
                                <option value="">-- Select a Team --</option>
                                <?php foreach ($teams as $t): ?>
                                    <option value="<?= $t['id'] ?>">
                                        <?= htmlspecialchars($t['name']) ?>
                                        (<?= $t['member_count'] ?> members)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- User Selection -->
                        <div class="mb-3">
                            <label for="userSelect" class="form-label">User <span class="text-danger">*</span></label>
                            <select class="form-select" id="userSelect" name="user_id" required>
                                <option value="">-- Select a Team First --</option>
                            </select>
                        </div>

                        <!-- Role Selection -->
                        <div class="mb-3">
                            <label for="roleSelect" class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" id="roleSelect" name="role" required>
                                <option value="member" selected>Member</option>
                                <option value="lead">Lead</option>
                                <option value="admin">Admin</option>
                            </select>
                            <small class="text-muted d-block mt-2">
                                <strong>Member:</strong> Standard team member<br>
                                <strong>Lead:</strong> Team lead with oversight<br>
                                <strong>Admin:</strong> Full administrative access
                            </small>
                        </div>

                        <!-- Buttons -->
                        <div class="d-flex gap-2 justify-content-end">
                            <a href="#"
                               hx-get="/partials/dashboard/index.php"
                               hx-target="#page-content"
                               class="btn btn-outline-secondary">
                                <i class="feather-x-circle me-1"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="feather-user-plus me-1"></i>Add Member
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Info Card -->
        <div class="col-lg-6 col-12">
            <div class="card" id="info-card">
                <div class="card-header bg-light" id="info-header">
                    <h5 class="card-title mb-0">
                        <i class="feather-info me-2"></i>Information
                    </h5>
                </div>
                <div class="card-body" id="info-body">
                    <div class="mb-4">
                        <h6 class="mb-2">Team Roles</h6>
                        <p class="text-muted small">
                            Each team member is assigned a role that determines their permissions and responsibilities within the team.
                        </p>
                        <ul class="list-unstyled">
                            <li class="mb-3">
                                <span class="badge bg-secondary mb-2">Member</span>
                                <p class="mb-0 text-muted small">Can view and work on team tasks, but cannot manage team settings.</p>
                            </li>
                            <li class="mb-3">
                                <span class="badge bg-warning text-dark mb-2">Lead</span>
                                <p class="mb-0 text-muted small">Team lead with project oversight and task assignment capabilities.</p>
                            </li>
                            <li>
                                <span class="badge bg-danger mb-2">Admin</span>
                                <p class="mb-0 text-muted small">Full administrative access including member management and team settings.</p>
                            </li>
                        </ul>
                    </div>

                    <div>
                        <h6 class="mb-2">Current Teams</h6>
                        <p class="text-muted small mb-3">
                            You have <?= count($teams) ?> team<?= count($teams) != 1 ? 's' : '' ?> in your organization.
                        </p>
                        <a href="#"
                           hx-get="/partials/teams/index.php"
                           hx-target="#page-content"
                           class="btn btn-sm btn-outline-primary">
                            <i class="feather-users me-1"></i>View All Teams
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
