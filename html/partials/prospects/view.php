<?php
require_once '/var/www/helpers/auth.php';
require_once '/var/www/models/Prospect.php';
requireAuth();

$orgId = currentOrgId();
$prospectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$prospectModel = new Prospect();
$prospect = $prospectId ? $prospectModel->getById($prospectId, $orgId) : null;

if (!$prospect) {
    http_response_code(404);
    echo '<div class="alert alert-danger" id="prospect-view-error">Prospect not found.</div>';
    exit;
}

$personalityColors = [
    'Busy & skeptical' => 'danger',
    'Analytical & detail-oriented' => 'info',
    'Friendly but indecisive' => 'success',
    'Aggressive negotiator' => 'warning',
    'Warm & relationship-driven' => 'primary',
    'Technical & no-nonsense' => 'secondary',
    'Budget-conscious & cautious' => 'dark',
];

$difficultyColors = [
    'beginner' => 'success',
    'intermediate' => 'warning',
    'advanced' => 'danger',
];

$personality = $prospect['prospect_personality'] ?? 'Busy & skeptical';
$personalityBadge = $personalityColors[$personality] ?? 'secondary';
$difficultyBadge = $difficultyColors[$prospect['difficulty_level'] ?? 'intermediate'] ?? 'secondary';
$callTypes = array_filter(explode(',', $prospect['call_types'] ?? ''), fn($v) => $v !== '');
$image = !empty($prospect['image']) ? htmlspecialchars($prospect['image']) : '/assets/images/default-prospect.png';
?>

<div class="container-fluid" id="prospect-view-page">
  <div class="d-flex justify-content-between align-items-center mb-4" id="prospect-view-header">
    <div class="d-flex align-items-center" id="prospect-view-header-left">
      <img src="<?= $image ?>" alt="<?= htmlspecialchars($prospect['name']) ?>" class="rounded me-3" width="72" height="72" id="prospect-view-avatar">
      <div id="prospect-view-title-wrap">
        <h3 class="mb-1" id="prospect-view-title"><?= htmlspecialchars($prospect['name']) ?></h3>
        <p class="text-muted mb-1" id="prospect-view-contact">
          <?= htmlspecialchars($prospect['prospect_name'] ?? '') ?> • <?= htmlspecialchars($prospect['prospect_role'] ?? '') ?>
        </p>
        <div class="d-flex align-items-center gap-2" id="prospect-view-badges">
          <span class="badge bg-<?= $personalityBadge ?>" id="prospect-view-personality"><?= htmlspecialchars($personality) ?></span>
          <?php if (!empty($prospect['industry'])): ?>
            <span class="badge bg-light text-dark" id="prospect-view-industry"><?= htmlspecialchars($prospect['industry']) ?></span>
          <?php endif; ?>
          <span class="badge bg-<?= $difficultyBadge ?>" id="prospect-view-difficulty">Difficulty: <?= ucfirst(htmlspecialchars($prospect['difficulty_level'] ?? 'intermediate')) ?></span>
        </div>
      </div>
    </div>
    <div class="d-flex gap-2" id="prospect-view-actions">
      <a href="#" class="btn btn-outline-secondary" id="prospect-view-back"
         hx-get="/partials/prospects/list.php" hx-target="#page-content">Back</a>
      <?php if (isManager()): ?>
        <a href="#" class="btn btn-outline-primary" id="prospect-view-edit"
           hx-get="/partials/prospects/form.php?id=<?= $prospectId ?>" hx-target="#page-content">
          <i class="bi bi-pencil me-1"></i>Edit
        </a>
      <?php endif; ?>
      <a href="#" class="btn btn-primary" id="prospect-view-practice"
         hx-get="/partials/sessions/setup.php?prospect_id=<?= $prospectId ?>" hx-target="#page-content">
        <i class="bi bi-telephone-outbound me-1"></i>Practice with this Prospect
      </a>
    </div>
  </div>

  <div class="row g-4 mb-4" id="prospect-view-info-row">
    <div class="col-lg-6" id="prospect-view-company-col">
      <div class="card h-100" id="prospect-view-company-card">
        <div class="card-header" id="prospect-view-company-header">
          <h6 class="mb-0" id="prospect-view-company-title">Company Info</h6>
        </div>
        <div class="card-body" id="prospect-view-company-body">
          <div class="row gy-2" id="prospect-view-company-grid">
            <div class="col-6" id="prospect-view-business-type"><strong>Business Type:</strong> <?= htmlspecialchars($prospect['business_type'] ?? '—') ?></div>
            <div class="col-6" id="prospect-view-company-size"><strong>Company Size:</strong> <?= htmlspecialchars($prospect['company_size'] ?? '—') ?></div>
            <div class="col-6" id="prospect-view-members"><strong>Members:</strong> <?= htmlspecialchars($prospect['members'] ?? 0) ?></div>
            <div class="col-6" id="prospect-view-call-types"><strong>Call Types:</strong>
              <?php if (!empty($callTypes)): ?>
                <?php foreach ($callTypes as $type): ?>
                  <span class="badge bg-light text-dark me-1" id="prospect-view-call-<?= htmlspecialchars($type) ?>"><?= ucwords(str_replace('_', ' ', $type)) ?></span>
                <?php endforeach; ?>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </div>
            <div class="col-12" id="prospect-view-description"><strong>Description:</strong><br><?= nl2br(htmlspecialchars($prospect['description'] ?? '—')) ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-6" id="prospect-view-context-col">
      <div class="card h-100" id="prospect-view-context-card">
        <div class="card-header" id="prospect-view-context-header">
          <h6 class="mb-0" id="prospect-view-context-title">Sales Context</h6>
        </div>
        <div class="card-body" id="prospect-view-context-body">
          <ul class="list-unstyled mb-0" id="prospect-view-context-list">
            <li class="mb-2" id="prospect-view-current-solution"><strong>Current Solution:</strong> <?= htmlspecialchars($prospect['current_solution'] ?? '—') ?></li>
            <li class="mb-2" id="prospect-view-tools"><strong>Tools Used:</strong> <?= htmlspecialchars($prospect['tools_used'] ?? '—') ?></li>
            <li class="mb-2" id="prospect-view-events"><strong>Recent Events:</strong> <?= htmlspecialchars($prospect['recent_events'] ?? '—') ?></li>
            <li class="mb-2" id="prospect-view-leads"><strong>Lead Volume:</strong> <?= htmlspecialchars($prospect['lead_volume'] ?? '—') ?></li>
            <li class="mb-2" id="prospect-view-usage"><strong>Usage Count:</strong> <?= (int)($prospect['usage_count'] ?? 0) ?> sessions</li>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4" id="prospect-view-persona-card">
    <div class="card-header" id="prospect-view-persona-header">
      <h6 class="mb-0" id="prospect-view-persona-title">Buyer Persona</h6>
    </div>
    <div class="card-body" id="prospect-view-persona-body">
      <div class="row gy-2" id="prospect-view-persona-grid">
        <div class="col-md-4" id="prospect-view-persona-name"><strong>Name:</strong> <?= htmlspecialchars($prospect['prospect_name'] ?? '') ?></div>
        <div class="col-md-4" id="prospect-view-persona-role"><strong>Role:</strong> <?= htmlspecialchars($prospect['prospect_role'] ?? '') ?></div>
        <div class="col-md-4" id="prospect-view-persona-talk"><strong>Talkativeness:</strong> <?= htmlspecialchars($prospect['prospect_talkativeness'] ?? '') ?></div>
        <div class="col-12" id="prospect-view-persona-style"><strong>Communication Style:</strong><br><?= nl2br(htmlspecialchars($prospect['prosepect_communication_style'] ?? '—')) ?></div>
      </div>
    </div>
  </div>

  <div class="card" id="prospect-view-agents-card">
    <div class="card-header d-flex justify-content-between align-items-center" id="prospect-view-agents-header">
      <h6 class="mb-0" id="prospect-view-agents-title">Contacts & Sub-Agents</h6>
      <?php if (isManager()): ?>
        <a href="#" class="btn btn-sm btn-outline-primary" id="prospect-view-add-agent"
           hx-get="/partials/prospects/agents.php?prospect_id=<?= $prospectId ?>&agent_id=0"
           hx-target="#prospect-agents-wrapper" hx-swap="outerHTML">Add Contact</a>
      <?php endif; ?>
    </div>
    <div class="card-body" id="prospect-view-agents-body">
      <div id="prospect-agents-wrapper"
           hx-get="/partials/prospects/agents.php?prospect_id=<?= $prospectId ?>"
           hx-trigger="load" hx-target="#prospect-agents-wrapper" hx-swap="outerHTML">
        <div class="text-center text-muted" id="prospect-agents-loading">
          <div class="spinner-border spinner-border-sm" role="status"></div>
          <span class="ms-2">Loading contacts...</span>
        </div>
      </div>
    </div>
  </div>
</div>
