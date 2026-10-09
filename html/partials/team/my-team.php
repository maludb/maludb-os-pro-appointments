<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../models/Team.php';
require_once __DIR__ . '/../../../config/database.php';

check_auth();
$user = get_user();

$teamModel = new Team();
$userTeams = $teamModel->getUserTeams($user['id']);
$selectedTeam = null;
$teamMembers = [];

// Handle team selection via POST (from the select box)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['team_id'])) {
    $teamId = (int)$_POST['team_id'];
    error_log("Team selection POST received: team_id=$teamId, user_id={$user['id']}");

    // Verify user is member of this team
    if ($teamModel->isTeamMember($user['id'], $teamId)) {
        try {
            $db = Database::getInstance()->getConnection();
            $sql = "UPDATE users SET current_team = :team_id WHERE id = :user_id";
            $stmt = $db->prepare($sql);
            $result = $stmt->execute([
                ':team_id' => $teamId,
                ':user_id' => $user['id']
            ]);

            error_log("Team update executed: $result, rows affected: " . $stmt->rowCount());

            $_SESSION['selected_team_id'] = $teamId;
            $user['current_team'] = $teamId;
            $_SESSION['user'] = $user;

            error_log("Team selection complete: current_team set to $teamId");
        } catch (PDOException $e) {
            error_log("Error updating current team: " . $e->getMessage());
        }
    } else {
        error_log("User not a member of team $teamId");
    }
}

// Get the current team ID (prefer current_team from DB, fallback to session)
$teamId = $user['current_team'] ?? ($_SESSION['selected_team_id'] ?? null);

// If user has teams but none selected, auto-select the first one
if (!$teamId && !empty($userTeams)) {
    $teamId = $userTeams[0]['id'];
    try {
        $db = Database::getInstance()->getConnection();
        $sql = "UPDATE users SET current_team = :team_id WHERE id = :user_id";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':team_id' => $teamId,
            ':user_id' => $user['id']
        ]);
        $_SESSION['selected_team_id'] = $teamId;
    } catch (PDOException $e) {
        error_log("Error auto-selecting team: " . $e->getMessage());
    }
}

if ($teamId) {
    $selectedTeam = $teamModel->getTeamDetails($teamId);
    $teamMembers = $teamModel->getTeamMembers($teamId);
}
?>

<!-- My Team Page Container -->
<div class="container-fluid" id="my-team-page">
    <!-- Page Header -->
    <div class="row mt-2 mx-2 mb-0" id="my-team-header">
        <div class="col-12">
            <div class="card bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body">
                    <h4 class="mb-1 text-dark">
                        <i class="feather-users me-2"></i>My Team
                    </h4>
                    <?php if ($selectedTeam): ?>
                        <p class="mb-0 text-dark">
                            <?= htmlspecialchars($selectedTeam['name']) ?> - <?= count($teamMembers) ?> member<?= count($teamMembers) != 1 ? 's' : '' ?>
                        </p>
                    <?php else: ?>
                        <p class="mb-0 text-dark">View your team members</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Team Selector -->
    <?php if (!empty($userTeams)): ?>
        <div class="row mb-4" id="my-team-selector">
            <div class="col-12 col-md-6 col-lg-4">
                <form id="team-select-form"
                      hx-post="/partials/team/my-team.php"
                      hx-target="#page-content"
                      hx-swap="innerHTML">
                    <label for="teamSelector" class="form-label">Select a Team</label>
                    <select class="form-select" id="teamSelector" name="team_id"
                            hx-post="/partials/team/my-team.php"
                            hx-target="#page-content"
                            hx-swap="innerHTML"
                            hx-trigger="change">
                        <option value="">-- Select a Team --</option>
                        <?php foreach ($userTeams as $team): ?>
                            <option value="<?= $team['id'] ?>" <?= $team['id'] == $teamId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($team['name']) ?>
                                (<?= $team['member_count'] ?? 0 ?> members)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!$selectedTeam): ?>
        <!-- No Team Selected -->
        <div class="row" id="my-team-no-team">
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="feather-users fs-1 text-muted mb-3 d-block"></i>
                        <h5 class="text-muted">No Team Selected</h5>
                        <p class="text-muted">
                            Please select a team from the team switcher in the navigation bar to view team members.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Team Members Grid -->
        <div class="row g-4" id="my-team-members-grid">
            <?php if (empty($teamMembers)): ?>
                <!-- No Members -->
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="feather-user-x fs-1 text-muted mb-3 d-block"></i>
                            <h5 class="text-muted">No Team Members</h5>
                            <p class="text-muted">This team has no members yet.</p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($teamMembers as $member): ?>
                    <div class="col-xl-3 col-lg-4 col-md-6 col-12" id="team-member-card-<?= $member['id'] ?>">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3"
                                         style="width: 60px; height: 60px; font-size: 24px; font-weight: bold;">
                                        <?= strtoupper(substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1)) ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h5 class="mb-0">
                                            <?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?>
                                            <?php if ($member['id'] == $user['id']): ?>
                                                <span class="badge bg-info ms-1">You</span>
                                            <?php endif; ?>
                                        </h5>
                                        <p class="text-muted small mb-0">@<?= htmlspecialchars($member['username'] ?? '') ?></p>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="feather-mail me-2 text-muted"></i>
                                        <small class="text-muted"><?= htmlspecialchars($member['email']) ?></small>
                                    </div>
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="feather-shield-check me-2 text-muted"></i>
                                        <span class="badge bg-<?= $member['team_role'] === 'admin' ? 'danger' : ($member['team_role'] === 'lead' ? 'warning' : 'secondary') ?>">
                                            <?= ucfirst(htmlspecialchars($member['team_role'])) ?>
                                        </span>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <i class="feather-calendar-check me-2 text-muted"></i>
                                        <small class="text-muted">
                                            Joined <?= date('M j, Y', strtotime($member['joined_at'])) ?>
                                        </small>
                                    </div>
                                </div>

                                <div class="d-grid gap-2">
                                    <button class="btn btn-sm btn-primary"
                                            id="btn-view-member-<?= $member['id'] ?>"
                                            hx-get="/partials/team/view-member.php?id=<?= $member['id'] ?>"
                                            hx-target="#page-content"
                                            hx-swap="innerHTML">
                                        <i class="feather-eye me-1"></i>View Profile
                                    </button>
                                </div>

                                <?php if ($member['id'] != $user['id']): ?>
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-sm btn-outline-primary"
                                                id="btn-assign-task-to-<?= $member['id'] ?>"
                                                hx-get="/partials/tasks/create-form.php?assign_to=<?= $member['id'] ?>"
                                                hx-target="#modal-container"
                                                hx-swap="innerHTML">
                                            <i class="feather-plus-circle me-1"></i>Assign Task
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
