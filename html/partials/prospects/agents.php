<?php
require_once '/var/www/helpers/auth.php';
require_once '/var/www/models/Prospect.php';
requireAuth();

$orgId = currentOrgId();
$prospectId = (int)($_GET['prospect_id'] ?? $_POST['prospect_id'] ?? 0);
$prospectModel = new Prospect();
$prospect = $prospectId ? $prospectModel->getById($prospectId, $orgId) : null;

if (!$prospect) {
    http_response_code(404);
    echo '<div class="alert alert-danger" id="prospect-agents-error">Prospect not found.</div>';
    exit;
}

$method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $_SERVER['REQUEST_METHOD']);
$errors = [];
$editAgentId = 0;
$editAgent = null;

if ($method === 'POST') {
    requireManager();
    $editAgentId = (int)($_POST['agent_id'] ?? 0);
    $data = [
        'prospect_id' => $prospectId,
        'agent_name' => trim($_POST['agent_name'] ?? ''),
        'agent_description' => trim($_POST['agent_description'] ?? ''),
        'agent_prompt' => trim($_POST['agent_prompt'] ?? ''),
        'agent_role' => trim($_POST['agent_role'] ?? 'caller'),
        'default_temperament' => trim($_POST['default_temperament'] ?? 'friendly'),
        'product_knowledge' => max(1, min(10, (int)($_POST['product_knowledge'] ?? 5))),
        'technical_level' => max(1, min(10, (int)($_POST['technical_level'] ?? 5))),
    ];

    if ($data['agent_name'] === '') {
        $errors[] = 'Agent name is required';
    }

    if (empty($errors)) {
        if ($editAgentId > 0) {
            $prospectModel->updateAgent($editAgentId, $data);
        } else {
            $prospectModel->createAgent($data);
        }
        header('HX-Trigger: {"toast": {"message": "Contact saved", "type": "success"}}');
    } else {
        $editAgent = $data;
        $editAgent['id'] = $editAgentId;
    }
} elseif ($method === 'DELETE') {
    requireManager();
    $deleteId = (int)($_GET['agent_id'] ?? $_POST['agent_id'] ?? 0);
    if ($deleteId > 0) {
        $prospectModel->deleteAgent($deleteId);
        header('HX-Trigger: {"toast": {"message": "Contact deleted", "type": "info"}}');
    }
} else {
    if (isset($_GET['agent_id'])) {
        $editAgentId = (int)$_GET['agent_id'];
        if ($editAgentId > 0) {
            $editAgent = $prospectModel->getAgent($editAgentId);
        }
    }
}

$agents = $prospectModel->getAgents($prospectId);
?>

<div id="prospect-agents-wrapper">
  <?php if (isManager()): ?>
    <div class="card mb-3" id="prospect-agent-form-card">
      <div class="card-body" id="prospect-agent-form-body">
        <h6 class="mb-3" id="prospect-agent-form-title"><?= $editAgent ? 'Edit Contact' : 'Add Contact' ?></h6>
        <?php if (!empty($errors)): ?>
          <div class="alert alert-danger" id="prospect-agent-form-errors">
            <ul class="mb-0" id="prospect-agent-error-list">
              <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
        <form id="prospect-agent-form" hx-post="/partials/prospects/agents.php" hx-target="#prospect-agents-wrapper" hx-swap="outerHTML">
          <input type="hidden" name="prospect_id" value="<?= $prospectId ?>">
          <input type="hidden" name="agent_id" value="<?= $editAgent['id'] ?? 0 ?>">
          <div class="row g-3" id="prospect-agent-form-row">
            <div class="col-md-4" id="agent-name-col">
              <label class="form-label" for="agent-name">Name *</label>
              <input type="text" class="form-control" id="agent-name" name="agent_name" required value="<?= htmlspecialchars($editAgent['agent_name'] ?? '') ?>">
            </div>
            <div class="col-md-4" id="agent-role-col">
              <label class="form-label" for="agent-role">Role</label>
              <select class="form-select" id="agent-role" name="agent_role">
                <?php foreach (['caller' => 'Caller', 'gatekeeper' => 'Gatekeeper', 'decision-maker' => 'Decision Maker', 'influencer' => 'Influencer'] as $key => $label): ?>
                  <option value="<?= $key ?>" <?= ($editAgent['agent_role'] ?? 'caller') === $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4" id="agent-temperament-col">
              <label class="form-label" for="agent-temperament">Temperament</label>
              <select class="form-select" id="agent-temperament" name="default_temperament">
                <?php foreach (['friendly','neutral','hostile','skeptical'] as $temp): ?>
                  <option value="<?= $temp ?>" <?= ($editAgent['default_temperament'] ?? 'friendly') === $temp ? 'selected' : '' ?>><?= ucfirst($temp) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6" id="agent-description-col">
              <label class="form-label" for="agent-description">Description</label>
              <input type="text" class="form-control" id="agent-description" name="agent_description" value="<?= htmlspecialchars($editAgent['agent_description'] ?? '') ?>">
            </div>
            <div class="col-md-6" id="agent-prompt-col">
              <label class="form-label" for="agent-prompt">Prompt / Notes</label>
              <input type="text" class="form-control" id="agent-prompt" name="agent_prompt" value="<?= htmlspecialchars($editAgent['agent_prompt'] ?? '') ?>">
            </div>
            <div class="col-md-3" id="agent-product-knowledge-col">
              <label class="form-label" for="agent-product-knowledge">Product Knowledge (1-10)</label>
              <input type="number" class="form-control" id="agent-product-knowledge" name="product_knowledge" min="1" max="10" value="<?= htmlspecialchars($editAgent['product_knowledge'] ?? 5) ?>">
            </div>
            <div class="col-md-3" id="agent-technical-level-col">
              <label class="form-label" for="agent-technical-level">Technical Level (1-10)</label>
              <input type="number" class="form-control" id="agent-technical-level" name="technical_level" min="1" max="10" value="<?= htmlspecialchars($editAgent['technical_level'] ?? 5) ?>">
            </div>
            <div class="col-12" id="agent-form-buttons-col">
              <div class="d-flex justify-content-end gap-2" id="agent-form-buttons">
                <button type="reset" class="btn btn-light" id="agent-reset-btn" hx-get="/partials/prospects/agents.php?prospect_id=<?= $prospectId ?>" hx-target="#prospect-agents-wrapper" hx-swap="outerHTML">Reset</button>
                <button type="submit" class="btn btn-primary" id="agent-submit-btn"><?= $editAgent ? 'Save Contact' : 'Add Contact' ?></button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <div class="row g-3" id="prospect-agent-cards-row">
    <?php if (empty($agents)): ?>
      <div class="col-12" id="prospect-agent-empty-col">
        <div class="text-center text-muted" id="prospect-agent-empty-text">No contacts yet.</div>
      </div>
    <?php else: ?>
      <?php foreach ($agents as $agent): ?>
        <div class="col-md-6" id="prospect-agent-col-<?= $agent['id'] ?>">
          <div class="card h-100" id="prospect-agent-card-<?= $agent['id'] ?>">
            <div class="card-body" id="prospect-agent-card-body-<?= $agent['id'] ?>">
              <div class="d-flex justify-content-between align-items-start mb-2" id="prospect-agent-card-header-<?= $agent['id'] ?>">
                <div id="prospect-agent-card-title-wrap-<?= $agent['id'] ?>">
                  <h6 class="mb-1" id="prospect-agent-name-<?= $agent['id'] ?>"><?= htmlspecialchars($agent['agent_name']) ?></h6>
                  <p class="text-muted small mb-0" id="prospect-agent-role-<?= $agent['id'] ?>"><?= htmlspecialchars(ucwords(str_replace('-', ' ', $agent['agent_role'] ?? ''))) ?></p>
                </div>
                <span class="badge bg-light text-dark" id="prospect-agent-temp-<?= $agent['id'] ?>"><?= htmlspecialchars(ucfirst($agent['default_temperament'] ?? 'friendly')) ?></span>
              </div>
              <?php if (!empty($agent['agent_description'])): ?>
                <p class="mb-2" id="prospect-agent-desc-<?= $agent['id'] ?>"><?= htmlspecialchars($agent['agent_description']) ?></p>
              <?php endif; ?>
              <?php if (!empty($agent['agent_prompt'])): ?>
                <p class="text-muted small mb-2" id="prospect-agent-prompt-<?= $agent['id'] ?>"><?= htmlspecialchars($agent['agent_prompt']) ?></p>
              <?php endif; ?>
              <div class="d-flex gap-3" id="prospect-agent-metrics-<?= $agent['id'] ?>">
                <span class="text-muted small" id="prospect-agent-pk-<?= $agent['id'] ?>"><i class="bi bi-star me-1"></i>Product Knowledge: <?= (int)($agent['product_knowledge'] ?? 0) ?>/10</span>
                <span class="text-muted small" id="prospect-agent-tech-<?= $agent['id'] ?>"><i class="bi bi-cpu me-1"></i>Technical: <?= (int)($agent['technical_level'] ?? 0) ?>/10</span>
              </div>
            </div>
            <?php if (isManager()): ?>
              <div class="card-footer bg-white d-flex justify-content-end gap-2" id="prospect-agent-actions-<?= $agent['id'] ?>">
                <button class="btn btn-sm btn-outline-secondary" id="prospect-agent-edit-<?= $agent['id'] ?>"
                        hx-get="/partials/prospects/agents.php?prospect_id=<?= $prospectId ?>&agent_id=<?= $agent['id'] ?>"
                        hx-target="#prospect-agents-wrapper" hx-swap="outerHTML">Edit</button>
                <button class="btn btn-sm btn-outline-danger" id="prospect-agent-delete-<?= $agent['id'] ?>"
                        hx-delete="/partials/prospects/agents.php?prospect_id=<?= $prospectId ?>&agent_id=<?= $agent['id'] ?>"
                        hx-target="#prospect-agents-wrapper" hx-swap="outerHTML" hx-confirm="Delete this contact?">Delete</button>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
