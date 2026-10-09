<?php
require_once '/var/www/helpers/auth.php';
require_once '/var/www/models/BusinessContext.php';
requireManager();

$orgId = currentOrgId();
$ctxModel = new BusinessContext();
$method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $_SERVER['REQUEST_METHOD']);

if ($method === 'DELETE') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $ctxModel->delete($id, $orgId);
    }
    exit;
}

$contexts = $ctxModel->getAll($orgId);
$sectionBadges = [
    'Company Overview' => 'primary',
    'Value Propositions' => 'success',
    'Competitive Landscape' => 'warning',
    'Common Objections & Responses' => 'danger',
    'Sales Methodology' => 'info',
    'Compliance/Regulatory Notes' => 'secondary',
    'Custom' => 'dark'
];
?>

<div class="container-fluid" id="context-page">
  <div class="d-flex justify-content-between align-items-center mb-4" id="context-header">
    <div id="context-title-wrap">
      <h4 class="mb-1" id="context-title">Business Context</h4>
      <p class="text-muted mb-0" id="context-subtitle">These sections are injected into AI agent prompts to provide your company-specific context.</p>
    </div>
    <div id="context-actions">
      <a href="#" class="btn btn-primary" id="context-add-btn"
         hx-get="/partials/context/form.php" hx-target="#page-content">
        <i class="bi bi-plus-lg me-1"></i>Add Context Section
      </a>
    </div>
  </div>

  <div class="row g-3" id="context-card-row">
    <?php if (empty($contexts)): ?>
      <div class="col-12" id="context-empty-col">
        <div class="card h-100 text-center" id="context-empty-card">
          <div class="card-body" id="context-empty-body">
            <i class="bi bi-journal-text fs-1 text-muted" id="context-empty-icon"></i>
            <h5 class="mt-3" id="context-empty-title">No context added</h5>
            <p class="text-muted" id="context-empty-text">Add company context to improve AI prompts.</p>
            <a href="#" class="btn btn-primary" id="context-empty-cta"
               hx-get="/partials/context/form.php" hx-target="#page-content">Add Context Section</a>
          </div>
        </div>
      </div>
    <?php else: ?>
      <?php foreach ($contexts as $ctx):
        $badge = $sectionBadges[$ctx['section_type'] ?? ''] ?? 'light';
        $preview = trim(mb_substr(strip_tags($ctx['prompt'] ?? ''), 0, 120));
        if (mb_strlen(strip_tags($ctx['prompt'] ?? '')) > 120) {
            $preview .= '…';
        }
      ?>
        <div class="col-md-6" id="context-card-col-<?= $ctx['id'] ?>">
          <div class="card h-100" id="context-card-<?= $ctx['id'] ?>">
            <div class="card-body" id="context-card-body-<?= $ctx['id'] ?>">
              <div class="d-flex justify-content-between align-items-start mb-2" id="context-card-header-<?= $ctx['id'] ?>">
                <div id="context-card-title-wrap-<?= $ctx['id'] ?>">
                  <h6 class="mb-1" id="context-card-name-<?= $ctx['id'] ?>"><?= htmlspecialchars($ctx['name'] ?? 'Untitled') ?></h6>
                  <span class="badge bg-<?= $badge ?>" id="context-card-type-<?= $ctx['id'] ?>"><?= htmlspecialchars($ctx['section_type'] ?? 'Custom') ?></span>
                </div>
                <div class="btn-group btn-group-sm" role="group" id="context-card-actions-<?= $ctx['id'] ?>">
                  <button class="btn btn-outline-secondary" id="context-edit-btn-<?= $ctx['id'] ?>"
                          hx-get="/partials/context/form.php?id=<?= $ctx['id'] ?>" hx-target="#page-content">Edit</button>
                  <button class="btn btn-outline-danger" id="context-delete-btn-<?= $ctx['id'] ?>"
                          hx-delete="/partials/context/list.php?id=<?= $ctx['id'] ?>"
                          hx-confirm="Delete this context section?"
                          hx-target="closest .col-md-6" hx-swap="outerHTML">Delete</button>
                </div>
              </div>
              <p class="text-muted mb-0" id="context-card-preview-<?= $ctx['id'] ?>"><?= htmlspecialchars($preview ?: 'No content yet.') ?></p>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
