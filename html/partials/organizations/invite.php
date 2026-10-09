<?php
require_once '/var/www/helpers/auth.php';
require_once '/var/www/config/database.php';
require_once '/var/www/models/Invitation.php';
require_once '/var/www/models/Organization.php';
requireManager();

$invModel = new Invitation();
$orgModel = new Organization();
$db = Database::getInstance()->getConnection();
$rolesStmt = $db->query("SELECT role, description FROM roles ORDER BY id");
$roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);
$orgId = (int)($_GET['org_id'] ?? $_POST['org_id'] ?? 0);
$method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $_SERVER['REQUEST_METHOD']);
$errors = [];

$org = $orgId ? $orgModel->getById($orgId) : null;
if (!$org) {
    http_response_code(404);
    echo '<div class="container-fluid" id="invite-notfound"><div class="alert alert-warning">Organization not found.</div></div>';
    exit;
}

$values = ['name' => '', 'email' => '', 'role' => 'user'];

if ($method === 'POST') {
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'role' => trim($_POST['role'] ?? 'user'),
        'org_id' => $orgId,
        'invited_by' => currentUserId(),
    ];

    if ($data['name'] === '') {
        $errors[] = 'Name is required.';
    }
    if ($data['email'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }

    if (empty($errors)) {
        $invModel->create($data);
        header('HX-Trigger: {"toast": {"message": "Invitation sent", "type": "success"}}');
        header('HX-Location: {"path":"/partials/organizations/view.php?id=' . $orgId . '","target":"#page-content"}');
        exit;
    } else {
        $values = array_merge($values, $data);
    }
}
?>

<div class="container-fluid" id="invite-form-page">
  <div class="d-flex justify-content-between align-items-center mb-4" id="invite-form-header">
    <div id="invite-form-title-wrap">
      <h4 class="mb-1" id="invite-form-title">Invite User</h4>
      <p class="text-muted mb-0" id="invite-form-subtitle">Invite a user to <strong><?= htmlspecialchars($org['name']) ?></strong></p>
    </div>
    <div id="invite-form-actions">
      <a href="#" class="btn btn-outline-secondary" id="invite-form-cancel"
         hx-get="/partials/organizations/view.php?id=<?= $orgId ?>" hx-target="#page-content">Back to organization</a>
    </div>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger" id="invite-form-errors">
      <ul class="mb-0" id="invite-form-errors-list">
        <?php foreach ($errors as $error): ?>
          <li><?= htmlspecialchars($error) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form id="invite-form" hx-post="/partials/organizations/invite.php" hx-target="#page-content" hx-swap="outerHTML">
    <input type="hidden" name="org_id" value="<?= $orgId ?>">

    <div class="card mb-4" id="invite-main-card">
      <div class="card-header" id="invite-main-card-header">
        <h6 class="mb-0" id="invite-main-title">Invitee Details</h6>
      </div>
      <div class="card-body" id="invite-main-card-body">
        <div class="row g-3" id="invite-main-row">
          <div class="col-md-6" id="invite-name-col">
            <label class="form-label" for="invite-name">Name *</label>
            <input type="text" class="form-control" id="invite-name" name="name" required
                   value="<?= htmlspecialchars($values['name']) ?>">
          </div>
          <div class="col-md-6" id="invite-email-col">
            <label class="form-label" for="invite-email">Email *</label>
            <input type="email" class="form-control" id="invite-email" name="email" required
                   value="<?= htmlspecialchars($values['email']) ?>">
          </div>
          <div class="col-md-6" id="invite-role-col">
            <label class="form-label" for="invite-role">Role</label>
            <select class="form-select" id="invite-role" name="role">
              <?php foreach ($roles as $r): ?>
                <option value="<?= htmlspecialchars($r['role']) ?>" <?= $values['role'] === $r['role'] ? 'selected' : '' ?>><?= htmlspecialchars($r['description']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-end gap-2" id="invite-form-buttons">
      <a href="#" class="btn btn-light" id="invite-cancel-btn"
         hx-get="/partials/organizations/view.php?id=<?= $orgId ?>" hx-target="#page-content">Cancel</a>
      <button type="submit" class="btn btn-primary" id="invite-submit-btn">Send Invitation</button>
    </div>
  </form>
</div>
