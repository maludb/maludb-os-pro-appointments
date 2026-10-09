<?php
require_once '/var/www/helpers/auth.php';
require_once '/var/www/models/Organization.php';
require_once '/var/www/models/Invitation.php';
requireManager();

$orgModel = new Organization();
$invModel = new Invitation();
$orgId = (int)($_GET['id'] ?? 0);

$method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $_SERVER['REQUEST_METHOD']);

if ($method === 'DELETE' && isset($_GET['invite_id'])) {
    $invModel->delete((int)$_GET['invite_id']);
    exit;
}

$org = $orgId ? $orgModel->getById($orgId) : null;
if (!$org) {
    http_response_code(404);
    echo '<div class="container-fluid" id="org-view-notfound"><div class="alert alert-warning">Organization not found.</div></div>';
    exit;
}

$members = $orgModel->getMembers($orgId);
$invitations = $invModel->getByOrgId($orgId);
$pendingInvitations = array_filter($invitations, fn($i) => $i['status'] === 'pending');
?>

<div class="container-fluid" id="org-view-page">
  <div class="d-flex justify-content-between align-items-center mb-4" id="org-view-header">
    <div id="org-view-title-wrap">
      <h4 class="mb-1" id="org-view-title"><?= htmlspecialchars($org['name']) ?></h4>
      <p class="text-muted mb-0" id="org-view-subtitle"><?= htmlspecialchars($org['description'] ?? '') ?></p>
    </div>
    <div class="d-flex gap-2" id="org-view-actions">
      <a href="#" class="btn btn-outline-secondary" id="org-view-back-btn"
         hx-get="/partials/organizations/list.php" hx-target="#page-content">Back to list</a>
      <a href="#" class="btn btn-primary" id="org-view-invite-btn"
         hx-get="/partials/organizations/invite.php?org_id=<?= $orgId ?>" hx-target="#page-content">
        <i class="bi bi-plus-lg me-1"></i> Invite User
      </a>
    </div>
  </div>

  <div class="card mb-4" id="org-view-members-card">
    <div class="card-header" id="org-view-members-header">
      <h6 class="mb-0" id="org-view-members-title">Members (<?= count($members) ?>)</h6>
    </div>
    <div class="card-body p-0" id="org-view-members-body">
      <?php if (empty($members)): ?>
        <div class="p-4 text-center text-muted" id="org-view-members-empty">No members in this organization yet.</div>
      <?php else: ?>
        <div class="table-responsive" id="org-view-members-table-wrap">
          <table class="table table-hover mb-0" id="org-view-members-table">
            <thead id="org-view-members-thead">
              <tr>
                <th id="org-view-members-th-name">Name</th>
                <th id="org-view-members-th-email">Email</th>
                <th id="org-view-members-th-role">Role</th>
                <th id="org-view-members-th-joined">Joined</th>
              </tr>
            </thead>
            <tbody id="org-view-members-tbody">
              <?php foreach ($members as $member): ?>
                <tr id="org-view-member-row-<?= $member['id'] ?>">
                  <td id="org-view-member-name-<?= $member['id'] ?>"><?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?></td>
                  <td id="org-view-member-email-<?= $member['id'] ?>"><?= htmlspecialchars($member['email']) ?></td>
                  <td id="org-view-member-role-<?= $member['id'] ?>">
                    <span class="badge <?= $member['member_role'] === 'admin' ? 'bg-primary' : 'bg-secondary' ?>">
                      <?= htmlspecialchars(ucfirst($member['member_role'])) ?>
                    </span>
                  </td>
                  <td id="org-view-member-joined-<?= $member['id'] ?>"><?= htmlspecialchars(date('M j, Y', strtotime($member['joined_at']))) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card mb-4" id="org-view-invitations-card">
    <div class="card-header" id="org-view-invitations-header">
      <h6 class="mb-0" id="org-view-invitations-title">Pending Invitations (<?= count($pendingInvitations) ?>)</h6>
    </div>
    <div class="card-body p-0" id="org-view-invitations-body">
      <?php if (empty($pendingInvitations)): ?>
        <div class="p-4 text-center text-muted" id="org-view-invitations-empty">No pending invitations.</div>
      <?php else: ?>
        <div class="table-responsive" id="org-view-invitations-table-wrap">
          <table class="table table-hover mb-0" id="org-view-invitations-table">
            <thead id="org-view-invitations-thead">
              <tr>
                <th id="org-view-invitations-th-name">Name</th>
                <th id="org-view-invitations-th-email">Email</th>
                <th id="org-view-invitations-th-role">Role</th>
                <th id="org-view-invitations-th-sent">Sent</th>
                <th id="org-view-invitations-th-actions">Actions</th>
              </tr>
            </thead>
            <tbody id="org-view-invitations-tbody">
              <?php foreach ($pendingInvitations as $inv): ?>
                <tr id="org-view-invitation-row-<?= $inv['id'] ?>">
                  <td id="org-view-invitation-name-<?= $inv['id'] ?>"><?= htmlspecialchars($inv['name']) ?></td>
                  <td id="org-view-invitation-email-<?= $inv['id'] ?>"><?= htmlspecialchars($inv['email']) ?></td>
                  <td id="org-view-invitation-role-<?= $inv['id'] ?>">
                    <span class="badge bg-info"><?= htmlspecialchars(ucfirst($inv['role'])) ?></span>
                  </td>
                  <td id="org-view-invitation-sent-<?= $inv['id'] ?>"><?= htmlspecialchars(date('M j, Y', strtotime($inv['created_at']))) ?></td>
                  <td id="org-view-invitation-actions-<?= $inv['id'] ?>">
                    <button class="btn btn-sm btn-link text-danger p-1" id="org-view-invitation-delete-<?= $inv['id'] ?>"
                            hx-delete="/partials/organizations/view.php?id=<?= $orgId ?>&invite_id=<?= $inv['id'] ?>"
                            hx-confirm="Remove this invitation?"
                            hx-target="closest tr"
                            hx-swap="outerHTML">
                      <i class="bi bi-trash"></i>
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
</div>
