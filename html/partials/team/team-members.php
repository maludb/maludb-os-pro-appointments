<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../models/Team.php';

check_auth();
$currentUser = get_user();

// Get team ID from GET parameter
$teamId = isset($_GET['team_id']) ? (int)$_GET['team_id'] : null;

if (!$teamId) {
    echo '<div class="alert alert-danger" id="team-error">Invalid team ID</div>';
    exit;
}

try {
    $teamModel = new Team();

    // Get team details
    $team = $teamModel->getTeamDetails($teamId);

    if (!$team) {
        echo '<div class="alert alert-danger" id="team-not-found">Team not found</div>';
        exit;
    }

    // Get team members
    $teamMembers = $teamModel->getTeamMembers($teamId);

} catch (Exception $e) {
    error_log("Error getting team details: " . $e->getMessage());
    echo '<div class="alert alert-danger" id="team-error">Error loading team members</div>';
    exit;
}
?>

<!-- Team Members Page Container -->
<div class="container-fluid" id="team-members-page">
    <!-- Page Header -->
    <div class="row mb-4" id="team-members-header">
        <div class="col-12">
            <div class="card bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar bg-light text-primary rounded-circle d-flex align-items-center justify-content-center"
                             style="width: 50px; height: 50px; font-size: 24px; font-weight: bold;">
                            <?= strtoupper(substr($team['name'], 0, 1)) ?>
                        </div>
                        <div>
                            <h4 class="mb-1 text-dark"><?= htmlspecialchars($team['name']) ?></h4>
                            <p class="mb-0 text-dark"><?= count($teamMembers) ?> member<?= count($teamMembers) != 1 ? 's' : '' ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Back Button -->
    <div class="row mb-4" id="team-members-nav">
        <div class="col-12">
            <a href="#"
               hx-get="/partials/teams/index.php"
               hx-target="#page-content"
               hx-swap="innerHTML"
               class="btn btn-outline-secondary">
                <i class="feather-arrow-left me-1"></i>Back to Teams
            </a>
        </div>
    </div>

    <?php if (empty($teamMembers)): ?>
        <!-- No Members -->
        <div class="row" id="team-members-empty">
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="feather-users fs-1 text-muted mb-3 d-block"></i>
                        <h5 class="text-muted">No Team Members</h5>
                        <p class="text-muted">This team has no members yet.</p>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Team Members Grid -->
        <div class="row g-4" id="team-members-grid">
            <?php foreach ($teamMembers as $member): ?>
                <div class="col-xl-3 col-lg-4 col-md-6 col-12" id="team-member-card-<?= $member['id'] ?>">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3"
                                     style="width: 50px; height: 50px; font-size: 20px; font-weight: bold;">
                                    <?= strtoupper(substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1)) ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-0">
                                        <?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?>
                                        <?php if ($member['id'] == $currentUser['id']): ?>
                                            <span class="badge bg-info ms-1">You</span>
                                        <?php endif; ?>
                                    </h5>
                                    <p class="text-muted small mb-0">@<?= htmlspecialchars($member['username']) ?></p>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="feather-mail me-2 text-muted"></i>
                                    <small class="text-muted"><?= htmlspecialchars($member['email']) ?></small>
                                </div>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="feather-shield-check me-2 text-muted"></i>
                                    <span class="badge bg-<?= $member['team_role'] === 'admin' ? 'danger' : 'secondary' ?>">
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

                            <button class="btn btn-sm btn-primary w-100"
                                    id="btn-view-member-<?= $member['id'] ?>"
                                    hx-get="/partials/team/view-member.php?id=<?= $member['id'] ?>"
                                    hx-target="#page-content"
                                    hx-swap="innerHTML">
                                <i class="feather-eye me-1"></i>View Profile
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
