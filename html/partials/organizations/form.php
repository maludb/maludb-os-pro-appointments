<?php
require_once '/var/www/helpers/auth.php';
require_once '/var/www/models/Organization.php';
requireManager();

$orgModel = new Organization();
$errors = [];
$orgId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $_SERVER['REQUEST_METHOD']);

$emptyValues = ['name' => '', 'description' => ''];

$org = null;
if ($orgId > 0) {
    $org = $orgModel->getById($orgId);
}
$values = $org ?? $emptyValues;

if ($method === 'POST') {
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
    ];

    if ($data['name'] === '') {
        $errors[] = 'Organization name is required.';
    }

    if (empty($errors)) {
        if ($orgId > 0) {
            $orgModel->update($orgId, $data);
        } else {
            $orgId = $orgModel->create($data);
        }
        header('HX-Trigger: {"toast": {"message": "Organization saved", "type": "success"}}');
        header('HX-Location: {"path":"/partials/organizations/list.php","target":"#page-content"}');
        exit;
    } else {
        $values = array_merge($values, $data);
    }
}

$isEdit = $orgId > 0;
?>

<div class="container-fluid" id="org-form-page">
  <div class="d-flex justify-content-between align-items-center mb-4" id="org-form-header">
    <div id="org-form-title-wrap">
      <h4 class="mb-1" id="org-form-title"><?= $isEdit ? 'Edit Organization' : 'Add Organization' ?></h4>
      <p class="text-muted mb-0" id="org-form-subtitle">Manage organization details.</p>
    </div>
    <div id="org-form-actions">
      <a href="#" class="btn btn-outline-secondary" id="org-form-cancel"
         hx-get="/partials/organizations/list.php" hx-target="#page-content">Back to list</a>
    </div>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger" id="org-form-errors">
      <ul class="mb-0" id="org-form-errors-list">
        <?php foreach ($errors as $error): ?>
          <li><?= htmlspecialchars($error) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form id="org-form" hx-post="/partials/organizations/form.php" hx-target="#page-content" hx-swap="outerHTML">
    <input type="hidden" name="id" value="<?= $orgId ?>">

    <div class="card mb-4" id="org-main-card">
      <div class="card-header" id="org-main-card-header">
        <h6 class="mb-0" id="org-main-title">Organization Details</h6>
      </div>
      <div class="card-body" id="org-main-card-body">
        <div class="row g-3" id="org-main-row">
          <div class="col-12" id="org-name-col">
            <label class="form-label" for="org-name">Organization Name *</label>
            <input type="text" class="form-control" id="org-name" name="name" required
                   value="<?= htmlspecialchars($values['name']) ?>">
          </div>
          <div class="col-12" id="org-description-col">
            <label class="form-label" for="org-description">Description</label>
            <textarea class="form-control" id="org-description" name="description" rows="3"><?= htmlspecialchars($values['description'] ?? '') ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-end gap-2" id="org-form-buttons">
      <a href="#" class="btn btn-light" id="org-cancel-btn"
         hx-get="/partials/organizations/list.php" hx-target="#page-content">Cancel</a>
      <button type="submit" class="btn btn-primary" id="org-submit-btn"><?= $isEdit ? 'Save Changes' : 'Create Organization' ?></button>
    </div>
  </form>
</div>
