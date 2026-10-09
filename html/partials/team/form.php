<?php
require_once '/var/www/helpers/auth.php';
require_once '/var/www/config/database.php';
require_once '/var/www/models/Team.php';
requireManager();

$teamModel = new Team();
$method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $_SERVER['REQUEST_METHOD']);
$errors = [];
$teamId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$superAdmin = isSuperAdmin();

$orgs = [];
if ($superAdmin) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT id, name FROM orgs ORDER BY name ASC");
    $orgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$emptyValues = ['name' => '', 'description' => '', 'org_id' => currentOrgId()];

$team = null;
if ($teamId > 0) {
    $team = $teamModel->getById($teamId);
}
$values = $team ?? $emptyValues;

if ($method === 'POST') {
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'org_id' => $superAdmin ? (int)($_POST['org_id'] ?? 0) : (int)currentOrgId(),
    ];

    if ($data['name'] === '') {
        $errors[] = 'Team name is required.';
    }
    if ($superAdmin && $data['org_id'] <= 0) {
        $errors[] = 'Please select an organization.';
    }

    if (empty($errors)) {
        if ($teamId > 0) {
            $teamModel->update($teamId, ['name' => $data['name'], 'description' => $data['description']]);
        } else {
            $teamId = $teamModel->createWithOrg($data['name'], $data['description'], $data['org_id'], currentUserId());
        }
        header('HX-Trigger: {"toast": {"message": "Team saved", "type": "success"}}');
        header('HX-Location: {"path":"/partials/team/members.php","target":"#page-content"}');
        exit;
    } else {
        $values = array_merge($values, $data);
    }
}

$isEdit = $teamId > 0 && $team;
?>

<div class="container-fluid" id="team-form-page">
  <div class="d-flex justify-content-between align-items-center mb-4" id="team-form-header">
    <div id="team-form-title-wrap">
      <h4 class="mb-1" id="team-form-title"><?= $isEdit ? 'Edit Team' : 'Create New Team' ?></h4>
      <p class="text-muted mb-0" id="team-form-subtitle">Manage team details.</p>
    </div>
    <div id="team-form-actions">
      <a href="#" class="btn btn-outline-secondary" id="team-form-cancel"
         hx-get="/partials/team/members.php" hx-target="#page-content">Back to list</a>
    </div>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger" id="team-form-errors">
      <ul class="mb-0" id="team-form-errors-list">
        <?php foreach ($errors as $error): ?>
          <li><?= htmlspecialchars($error) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form id="team-form" hx-post="/partials/team/form.php" hx-target="#page-content" hx-swap="outerHTML">
    <input type="hidden" name="id" value="<?= $teamId ?>">

    <div class="card mb-4" id="team-main-card">
      <div class="card-header" id="team-main-card-header">
        <h6 class="mb-0" id="team-main-title">Team Details</h6>
      </div>
      <div class="card-body" id="team-main-card-body">
        <div class="row g-3" id="team-main-row">
          <div class="col-md-6" id="team-name-col">
            <label class="form-label" for="team-name">Team Name *</label>
            <input type="text" class="form-control" id="team-name" name="name" required
                   value="<?= htmlspecialchars($values['name']) ?>">
          </div>
          <?php if ($superAdmin): ?>
            <div class="col-md-6" id="team-org-col">
              <label class="form-label" for="team-org">Organization *</label>
              <select class="form-select" id="team-org" name="org_id" required>
                <option value="">Select an organization</option>
                <?php foreach ($orgs as $org): ?>
                  <option value="<?= $org['id'] ?>" <?= ((int)($values['org_id'] ?? 0)) === (int)$org['id'] ? 'selected' : '' ?>><?= htmlspecialchars($org['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php else: ?>
            <input type="hidden" name="org_id" value="<?= (int)currentOrgId() ?>">
          <?php endif; ?>
          <div class="col-12" id="team-description-col">
            <label class="form-label" for="team-description">Description</label>
            <textarea class="form-control" id="team-description" name="description" rows="3"><?= htmlspecialchars($values['description'] ?? '') ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-end gap-2" id="team-form-buttons">
      <a href="#" class="btn btn-light" id="team-cancel-btn"
         hx-get="/partials/team/members.php" hx-target="#page-content">Cancel</a>
      <button type="submit" class="btn btn-primary" id="team-submit-btn"><?= $isEdit ? 'Save Changes' : 'Create Team' ?></button>
    </div>
  </form>
</div>
