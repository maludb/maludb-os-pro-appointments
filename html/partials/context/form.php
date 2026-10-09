<?php
require_once '/var/www/helpers/auth.php';
require_once '/var/www/models/BusinessContext.php';
requireManager();

$orgId = currentOrgId();
$ctxModel = new BusinessContext();
$method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $_SERVER['REQUEST_METHOD']);
$errors = [];
$contextId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$sectionTypes = [
    'Company Overview',
    'Value Propositions',
    'Competitive Landscape',
    'Common Objections & Responses',
    'Sales Methodology',
    'Compliance/Regulatory Notes',
    'Custom'
];

$context = null;
if ($contextId > 0) {
    $context = $ctxModel->getById($contextId, $orgId);
}

$values = $context ?? [
    'section_type' => 'Company Overview',
    'name' => '',
    'prompt_section' => '',
    'prompt' => ''
];

if ($method === 'POST') {
    $data = [
        'section_type' => trim($_POST['section_type'] ?? ''),
        'name' => trim($_POST['name'] ?? ''),
        'prompt_section' => trim($_POST['prompt_section'] ?? ''),
        'prompt' => trim($_POST['prompt'] ?? ''),
    ];

    if ($data['name'] === '') {
        $errors[] = 'Name is required';
    }
    if ($data['prompt'] === '') {
        $errors[] = 'Prompt content is required';
    }
    if ($data['prompt_section'] === '') {
        $data['prompt_section'] = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $data['name']));
    }

    if (empty($errors)) {
        if ($contextId > 0) {
            $ctxModel->update($contextId, $data, $orgId);
        } else {
            $contextId = $ctxModel->create($data);
        }
        header('HX-Trigger: {"toast": {"message": "Context saved", "type": "success"}}');
        header('HX-Redirect: /partials/context/list.php');
        exit;
    } else {
        $values = array_merge($values, $data);
    }
}

$isEdit = $contextId > 0;
?>

<div class="container-fluid" id="context-form-page">
  <div class="d-flex justify-content-between align-items-center mb-4" id="context-form-header">
    <div id="context-form-title-wrap">
      <h4 class="mb-1" id="context-form-title"><?= $isEdit ? 'Edit Context Section' : 'Add Context Section' ?></h4>
      <p class="text-muted mb-0" id="context-form-subtitle">Write the content you want injected into AI prompts.</p>
    </div>
    <div id="context-form-actions">
      <a href="#" class="btn btn-outline-secondary" id="context-form-cancel"
         hx-get="/partials/context/list.php" hx-target="#page-content">Back to list</a>
    </div>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger" id="context-form-errors">
      <ul class="mb-0" id="context-form-errors-list">
        <?php foreach ($errors as $err): ?>
          <li><?= htmlspecialchars($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form id="context-form" hx-post="/partials/context/form.php" hx-target="#page-content" hx-swap="outerHTML">
    <input type="hidden" name="id" value="<?= $contextId ?>">
    <div class="card mb-4" id="context-form-card">
      <div class="card-body" id="context-form-body">
        <div class="row g-3" id="context-form-row">
          <div class="col-md-4" id="context-type-col">
            <label class="form-label" for="context-section-type">Section Type</label>
            <select class="form-select" id="context-section-type" name="section_type">
              <?php foreach ($sectionTypes as $type): ?>
                <option value="<?= htmlspecialchars($type) ?>" <?= $values['section_type'] === $type ? 'selected' : '' ?>><?= htmlspecialchars($type) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4" id="context-name-col">
            <label class="form-label" for="context-name">Name</label>
            <input type="text" class="form-control" id="context-name" name="name" required value="<?= htmlspecialchars($values['name']) ?>">
          </div>
          <div class="col-md-4" id="context-prompt-section-col">
            <label class="form-label" for="context-prompt-section">Prompt Section Identifier</label>
            <input type="text" class="form-control" id="context-prompt-section" name="prompt_section" value="<?= htmlspecialchars($values['prompt_section']) ?>">
          </div>
          <div class="col-12" id="context-prompt-col">
            <label class="form-label" for="context-prompt">Content</label>
            <textarea class="form-control" id="context-prompt" name="prompt" rows="6" required><?= htmlspecialchars($values['prompt']) ?></textarea>
            <small class="text-muted" id="context-prompt-help">Write the content as you would want the AI to know it. This text is injected directly into the AI's system prompt.</small>
          </div>
        </div>
      </div>
    </div>
    <div class="d-flex justify-content-end gap-2" id="context-form-buttons">
      <a href="#" class="btn btn-light" id="context-cancel-btn" hx-get="/partials/context/list.php" hx-target="#page-content">Cancel</a>
      <button type="submit" class="btn btn-primary" id="context-submit-btn"><?= $isEdit ? 'Save Changes' : 'Create Section' ?></button>
    </div>
  </form>
</div>
