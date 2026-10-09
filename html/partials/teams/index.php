<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../models/Team.php';

check_auth();
is_admin() or die('Access denied');

$user = get_user();
$teamModel = new Team();
$teams = $teamModel->getAllTeams();
?>

<!-- Team Management Page Container -->
<div class="container-fluid" id="team-management-page">
    <!-- Page Header -->
    <div class="row mb-4" id="team-management-header">
        <div class="col-12">
            <div class="card bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-1 text-dark">
                                <i class="feather-users me-2"></i>Team Management
                            </h4>
                            <p class="mb-0 text-dark"><?= count($teams) ?> team<?= count($teams) != 1 ? 's' : '' ?> in your organization</p>
                        </div>
                        <a href="#"
                           hx-get="/partials/team/add-team.php"
                           hx-target="#page-content"
                           hx-swap="innerHTML"
                           class="btn btn-light"
                           id="btn-add-team">
                            <i class="feather-plus-circle me-1"></i>Add Team
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($teams)): ?>
        <!-- No Teams -->
        <div class="row" id="team-management-no-teams">
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="feather-users fs-1 text-muted mb-3 d-block"></i>
                        <h5 class="text-muted">No Teams</h5>
                        <p class="text-muted">No teams have been created yet.</p>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Teams Table -->
        <div class="row" id="team-management-content">
            <div class="col-12">
                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="teams-management-table">
                            <thead class="table-light">
                                <tr>
                                    <th id="teams-col-name">Team Name</th>
                                    <th id="teams-col-members" class="text-center">Members</th>
                                    <th id="teams-col-created" class="text-center">Created</th>
                                    <th id="teams-col-actions" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="teams-table-body">
                                <?php foreach ($teams as $team): ?>
                                    <tr id="team-row-<?= $team['id'] ?>">
                                        <td id="team-name-<?= $team['id'] ?>">
                                            <div class="d-flex align-items-center">
                                                <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2"
                                                     style="width: 40px; height: 40px; font-size: 16px; font-weight: bold;">
                                                    <?= strtoupper(substr($team['name'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <p class="mb-0 fw-semibold"><?= htmlspecialchars($team['name']) ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td id="team-members-<?= $team['id'] ?>" class="text-center">
                                            <span class="badge bg-info"><?= $team['member_count'] ?? 0 ?></span>
                                        </td>
                                        <td id="team-created-<?= $team['id'] ?>" class="text-center">
                                            <small class="text-muted"><?= date('M j, Y', strtotime($team['created_at'] ?? 'now')) ?></small>
                                        </td>
                                        <td id="team-actions-<?= $team['id'] ?>" class="text-center">
                                            <a href="#"
                                               hx-get="/partials/team/team-members.php?team_id=<?= $team['id'] ?>"
                                               hx-target="#page-content"
                                               hx-swap="innerHTML"
                                               class="btn btn-sm btn-outline-primary"
                                               title="View Members">
                                                <i class="feather-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
