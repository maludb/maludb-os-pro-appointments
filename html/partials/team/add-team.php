<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../models/Team.php';
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
            $name = isset($_POST['team_name']) ? trim($_POST['team_name']) : '';
            $description = isset($_POST['team_description']) ? trim($_POST['team_description']) : null;

            error_log("Add team attempt: name=$name, created_by={$user['id']}");

            // Validate inputs
            if (empty($name)) {
                $errorMessage = 'Team name is required.';
            } elseif (strlen($name) < 2) {
                $errorMessage = 'Team name must be at least 2 characters long.';
            } elseif (strlen($name) > 100) {
                $errorMessage = 'Team name must not exceed 100 characters.';
            } else {
                // Create team
                $teamModel = new Team();
                $teamId = $teamModel->create($name, $description, $user['id']);

                error_log("Team creation result: " . ($teamId ? "success (ID: $teamId)" : "failed"));

                if ($teamId) {
                    $successMessage = 'Team created successfully!';
                    // Clear form
                    $_POST = [];
                } else {
                    $errorMessage = 'Failed to create team. Please try again.';
                }
            }
        } catch (Exception $e) {
            error_log("Error creating team: " . $e->getMessage());
            $errorMessage = 'An error occurred: ' . $e->getMessage();
        }
    }
}

$teamModel = new Team();
$teams = $teamModel->getAllTeams();
?>

<!-- Add Team Page Container -->
<div class="container-fluid" id="add-team-page">
    <!-- Page Header -->
    <div class="row mb-4" id="add-team-header">
        <div class="col-12">
            <div class="card bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body">
                    <h4 class="mb-1 text-dark">
                        <i class="feather-plus-circle me-2"></i>Create New Team
                    </h4>
                    <p class="mb-0 text-dark">Add a new team to your organization</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <div class="row mb-4" id="add-team-messages">
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

    <!-- Add Team Form -->
    <div class="row" id="add-team-form-container">
        <div class="col-lg-6 col-12">
            <div class="card" id="add-team-card">
                <div class="card-header bg-light" id="form-header">
                    <h5 class="card-title mb-0">Team Details</h5>
                </div>
                <div class="card-body" id="form-body">
                    <form id="add-team-form"
                          hx-post="/partials/team/add-team.php"
                          hx-target="#add-team-page"
                          hx-swap="innerHTML">
                        <?php echo csrf_field(); ?>

                        <!-- Team Name -->
                        <div class="mb-3">
                            <label for="teamName" class="form-label">Team Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="teamName" name="team_name"
                                   placeholder="e.g., Engineering, Marketing, Design" required
                                   value="<?= isset($_POST['team_name']) ? htmlspecialchars($_POST['team_name']) : '' ?>">
                            <small class="text-muted d-block mt-1">Team name must be unique and 2-100 characters long.</small>
                        </div>

                        <!-- Team Description -->
                        <div class="mb-3">
                            <label for="teamDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="teamDescription" name="team_description" rows="4"
                                      placeholder="Describe the purpose and focus of this team..."><?= isset($_POST['team_description']) ? htmlspecialchars($_POST['team_description']) : '' ?></textarea>
                            <small class="text-muted d-block mt-1">Optional: Brief description of the team's purpose.</small>
                        </div>

                        <!-- Buttons -->
                        <div class="d-flex gap-2 justify-content-end">
                            <a href="#"
                               hx-get="/partials/teams/index.php"
                               hx-target="#page-content"
                               class="btn btn-outline-secondary">
                                <i class="feather-x-circle me-1"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="feather-check-circle me-1"></i>Create Team
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
                        <i class="feather-info me-2"></i>About Teams
                    </h5>
                </div>
                <div class="card-body" id="info-body">
                    <div class="mb-4">
                        <h6 class="mb-2">What is a Team?</h6>
                        <p class="text-muted small">
                            Teams allow you to organize members and collaborate on projects. Each team can have its own tasks, goals, and team members.
                        </p>
                    </div>

                    <div class="mb-4">
                        <h6 class="mb-2">Team Management</h6>
                        <p class="text-muted small">
                            As the team creator, you become the team owner and can:
                        </p>
                        <ul class="text-muted small ms-3">
                            <li>Add and remove team members</li>
                            <li>Assign roles to members</li>
                            <li>Manage team settings</li>
                            <li>View team tasks and progress</li>
                        </ul>
                    </div>

                    <div>
                        <h6 class="mb-2">Current Teams</h6>
                        <p class="text-muted small mb-3">
                            Your organization has <?= count($teams) ?> team<?= count($teams) != 1 ? 's' : '' ?>.
                        </p>
                        <a href="#"
                           hx-get="/partials/teams/index.php"
                           hx-target="#page-content"
                           class="btn btn-sm btn-outline-primary">
                            <i class="feather-list me-1"></i>View All Teams
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
