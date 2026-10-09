<?php
require_once '/var/www/helpers/auth.php';
require_once '/var/www/models/Agent.php';
requireManager();

$orgId = currentOrgId();
$agentModel = new Agent();

$method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $_SERVER['REQUEST_METHOD']);
if ($method === 'DELETE') {
    $deleteId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
    if ($deleteId > 0) {
        $agentModel->delete($deleteId, $orgId);
    }
    exit;
}

$agents = $agentModel->getAll($orgId);
?>

<div class="container-fluid" id="agents-page">
  <div class="d-flex justify-content-between align-items-center mb-4" id="agents-header">
    <div id="agents-title-wrap">
      <h4 class="mb-1" id="agents-title">AI Agents</h4>
      <p class="text-muted mb-0" id="agents-subtitle">Configure Retell voice agents for practice calls.</p>
    </div>
    <div id="agents-header-actions">
      <a href="#" class="btn btn-primary" id="agents-add-btn"
         hx-get="/partials/agents/form.php" hx-target="#page-content">
        <i class="bi bi-plus-lg me-1"></i>Add Agent
      </a>
    </div>
  </div>

  <div class="row row-cols-1 row-cols-md-3 g-4" id="agents-card-row">
    <?php if (empty($agents)): ?>
      <div class="col" id="agents-empty-col">
        <div class="card h-100 text-center" id="agents-empty-card">
          <div class="card-body" id="agents-empty-body">
            <i class="bi bi-robot fs-1 text-muted" id="agents-empty-icon"></i>
            <h5 class="mt-3" id="agents-empty-title">No agents configured</h5>
            <p class="text-muted" id="agents-empty-text">Add a Retell AI agent to power practice calls.</p>
            <a href="#" class="btn btn-primary" id="agents-empty-cta"
               hx-get="/partials/agents/form.php" hx-target="#page-content">Add Agent</a>
          </div>
        </div>
      </div>
    <?php else: ?>
      <?php foreach ($agents as $agent):
        $retellId = $agent['retell_agent_id'] ?? '';
        $retellShort = $retellId ? substr($retellId, 0, 10) . (strlen($retellId) > 10 ? '…' : '') : 'Not linked';
      ?>
        <div class="col" id="agent-card-col-<?= $agent['id'] ?>">
          <div class="card h-100" id="agent-card-<?= $agent['id'] ?>">
            <div class="card-body d-flex flex-column" id="agent-card-body-<?= $agent['id'] ?>">
              <div class="d-flex align-items-start mb-2" id="agent-card-header-<?= $agent['id'] ?>">
                <div class="me-3" id="agent-avatar-wrap-<?= $agent['id'] ?>">
                  <img src="<?= htmlspecialchars($agent['image'] ?? '/assets/images/default-prospect.png') ?>" alt="<?= htmlspecialchars($agent['name']) ?>" class="rounded" width="48" height="48">
                </div>
                <div id="agent-title-wrap-<?= $agent['id'] ?>">
                  <h6 class="mb-0" id="agent-name-<?= $agent['id'] ?>"><?= htmlspecialchars($agent['name']) ?></h6>
                  <p class="text-muted small mb-0" id="agent-desc-<?= $agent['id'] ?>"><?= htmlspecialchars($agent['description'] ?? 'No description') ?></p>
                </div>
              </div>
              <div class="mb-2" id="agent-meta-<?= $agent['id'] ?>">
                <?php if (!empty($agent['voice_name'])): ?>
                  <span class="badge bg-light text-dark me-1" id="agent-voice-<?= $agent['id'] ?>"><?= htmlspecialchars($agent['voice_name']) ?></span>
                <?php endif; ?>
                <?php if (!empty($agent['llm'])): ?>
                  <span class="badge bg-info text-white" id="agent-llm-<?= $agent['id'] ?>"><?= htmlspecialchars($agent['llm']) ?></span>
                <?php endif; ?>
              </div>
              <p class="text-muted small" id="agent-retell-<?= $agent['id'] ?>">Retell ID: <?= htmlspecialchars($retellShort) ?></p>
              <div class="mt-auto d-flex justify-content-between align-items-center" id="agent-actions-row-<?= $agent['id'] ?>">
                <span class="text-muted small" id="agent-updated-<?= $agent['id'] ?>">Updated <?= htmlspecialchars(date('M j, Y', strtotime($agent['updated_at'] ?? $agent['created_at'] ?? 'now'))) ?></span>
                <div class="btn-group btn-group-sm" role="group" id="agent-actions-<?= $agent['id'] ?>">
                  <button class="btn btn-outline-secondary" id="agent-edit-btn-<?= $agent['id'] ?>"
                          hx-get="/partials/agents/form.php?id=<?= $agent['id'] ?>" hx-target="#page-content">Edit</button>
                  <button class="btn btn-outline-danger" id="agent-delete-btn-<?= $agent['id'] ?>"
                          hx-delete="/partials/agents/list.php?id=<?= $agent['id'] ?>"
                          hx-confirm="Delete this agent?"
                          hx-target="closest .col" hx-swap="outerHTML">Delete</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
