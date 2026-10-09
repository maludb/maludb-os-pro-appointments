<?php
require_once '/var/www/helpers/auth.php';
require_once '/var/www/config/database.php';
require_once '/var/www/models/Team.php';
requireManager();

$teamModel = new Team();
$teamId = (int)($_GET['id'] ?? 0);
$method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $_SERVER['REQUEST_METHOD']);

// Handle removing a member
if ($method === 'DELETE' && isset($_GET['user_id'])) {
    $teamModel->removeMember($teamId, (int)$_GET['user_id']);
    exit;
}

// Handle adding a member
if ($method === 'POST') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $role = trim($_POST['role'] ?? 'member');
    if ($userId > 0 && $teamId > 0) {
        $teamModel->addMember($teamId, $userId, $role);
    }
    header('HX-Location: {"path":"/partials/team/view.php?id=' . $teamId . '","target":"#page-content"}');
    exit;
}

$team = $teamId ? $teamModel->getById($teamId) : null;
if (!$team) {
    http_response_code(404);
    echo '<div class="container-fluid" id="team-view-notfound"><div class="alert alert-warning">Team not found.</div></div>';
    exit;
}

$members = $teamModel->getTeamMembers($teamId);

// Get available users (not already in team)
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT u.id, u.first_name, u.last_name, u.email FROM users u WHERE u.status = 'active' AND u.id NOT IN (SELECT user_id FROM team_members WHERE team_id = :team_id) ORDER BY u.first_name ASC, u.last_name ASC");
$stmt->bindValue(':team_id', $teamId, PDO::PARAM_INT);
$stmt->execute();
$availableUsers = $stmt->fetchAll();
?>

<div class="container-fluid" id="team-view-page">
  <div class="d-flex justify-content-between align-items-center mb-4" id="team-view-header">
    <div id="team-view-title-wrap">
      <h4 class="mb-1" id="team-view-title"><?= htmlspecialchars($team['name']) ?></h4>
      <p class="text-muted mb-0" id="team-view-subtitle">
        <?= htmlspecialchars($team['description'] ?? '') ?>
        <?php if (!empty($team['org_name'])): ?>
          <span class="badge bg-light text-dark ms-1" id="team-view-org-badge"><?= htmlspecialchars($team['org_name']) ?></span>
        <?php endif; ?>
      </p>
    </div>
    <div class="d-flex gap-2" id="team-view-actions">
      <a href="#" class="btn btn-outline-secondary" id="team-view-back-btn"
         hx-get="/partials/team/members.php" hx-target="#page-content">Back to list</a>
      <a href="#" class="btn btn-outline-primary" id="team-view-edit-btn"
         hx-get="/partials/team/form.php?id=<?= $teamId ?>" hx-target="#page-content">
        <i class="bi bi-pencil me-1"></i> Edit Team
      </a>
    </div>
  </div>

  <!-- Members Table -->
  <div class="card mb-4" id="team-view-members-card">
    <div class="card-header d-flex justify-content-between align-items-center" id="team-view-members-header">
      <h6 class="mb-0" id="team-view-members-title">Members (<?= count($members) ?>)</h6>
    </div>
    <div class="card-body p-0" id="team-view-members-body">
      <?php if (empty($members)): ?>
        <div class="p-4 text-center text-muted" id="team-view-members-empty">No members in this team yet. Add one below.</div>
      <?php else: ?>
        <div class="table-responsive" id="team-view-members-table-wrap">
          <table class="table table-hover mb-0" id="team-view-members-table">
            <thead id="team-view-members-thead">
              <tr>
                <th id="team-view-th-name">Name</th>
                <th id="team-view-th-email">Email</th>
                <th id="team-view-th-role">Role</th>
                <th id="team-view-th-joined">Joined</th>
                <th id="team-view-th-actions">Actions</th>
              </tr>
            </thead>
            <tbody id="team-view-members-tbody">
              <?php foreach ($members as $member): ?>
                <tr id="team-view-member-row-<?= $member['id'] ?>">
                  <td id="team-view-member-name-<?= $member['id'] ?>"><?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?></td>
                  <td id="team-view-member-email-<?= $member['id'] ?>"><?= htmlspecialchars($member['email']) ?></td>
                  <td id="team-view-member-role-<?= $member['id'] ?>">
                    <span class="badge <?= $member['team_role'] === 'admin' ? 'bg-primary' : ($member['team_role'] === 'lead' ? 'bg-warning text-dark' : 'bg-secondary') ?>">
                      <?= htmlspecialchars(ucfirst($member['team_role'])) ?>
                    </span>
                  </td>
                  <td id="team-view-member-joined-<?= $member['id'] ?>"><?= htmlspecialchars(date('M j, Y', strtotime($member['joined_at']))) ?></td>
                  <td id="team-view-member-actions-<?= $member['id'] ?>">
                    <button class="btn btn-sm btn-link text-danger p-1" id="team-view-member-remove-<?= $member['id'] ?>"
                            hx-delete="/partials/team/view.php?id=<?= $teamId ?>&user_id=<?= $member['id'] ?>"
                            hx-confirm="Remove this member from the team?"
                            hx-target="closest tr"
                            hx-swap="outerHTML">
                      <i class="bi bi-x-circle"></i>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Add Member Form -->
  <div class="card mb-4" id="team-view-add-member-card">
    <div class="card-header" id="team-view-add-member-header">
      <h6 class="mb-0" id="team-view-add-member-title">Add Member</h6>
    </div>
    <div class="card-body" id="team-view-add-member-body">
      <?php if (empty($availableUsers)): ?>
        <p class="text-muted mb-0" id="team-view-no-users">All users are already members of this team.</p>
      <?php else: ?>
        <form id="team-add-member-form" hx-post="/partials/team/view.php?id=<?= $teamId ?>" hx-target="#page-content" hx-swap="outerHTML">
          <div class="row g-3 align-items-end" id="team-add-member-row">
            <div class="col-md-5" id="team-add-member-user-col">
              <label class="form-label" for="team-add-member-user">User</label>
              <select class="form-select" id="team-add-member-user" name="user_id" required>
                <option value="">Select a user</option>
                <?php foreach ($availableUsers as $u): ?>
                  <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?> (<?= htmlspecialchars($u['email']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4" id="team-add-member-role-col">
              <label class="form-label" for="team-add-member-role">Role</label>
              <select class="form-select" id="team-add-member-role" name="role">
                <option value="member" selected>Member</option>
                <option value="lead">Lead</option>
                <option value="admin">Admin</option>
              </select>
            </div>
            <div class="col-md-3" id="team-add-member-submit-col">
              <button type="submit" class="btn btn-primary w-100" id="team-add-member-btn">
                <i class="bi bi-plus-lg me-1"></i> Add Member
              </button>
            </div>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
