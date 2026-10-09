<?php
require_once '/var/www/helpers/auth.php';
require_once '/var/www/models/Prospect.php';
requireAuth();

$orgId = currentOrgId();
$prospectModel = new Prospect();

$method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $_SERVER['REQUEST_METHOD']);

if ($method === 'DELETE') {
    requireManager();
    $deleteId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
    if ($deleteId > 0) {
        $prospectModel->delete($deleteId, $orgId);
    }
    exit;
}

$filters = [
    'search' => trim($_GET['search'] ?? ''),
    'industry' => trim($_GET['industry'] ?? ''),
    'difficulty_level' => trim($_GET['difficulty_level'] ?? ''),
    'prospect_personality' => trim($_GET['prospect_personality'] ?? ''),
];

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 9;
$offset = ($page - 1) * $limit;

$prospects = $prospectModel->getAll($orgId, $filters, $limit, $offset);
$total = $prospectModel->countAll($orgId, $filters);
$totalPages = max(1, (int)ceil($total / $limit));

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

$industryOptions = [
    '' => 'All Industries',
    'Technology' => 'Technology',
    'Healthcare' => 'Healthcare',
    'Finance' => 'Finance',
    'Manufacturing' => 'Manufacturing',
    'Retail' => 'Retail',
    'Education' => 'Education',
    'Other' => 'Other',
];

$personalityOptions = array_keys($personalityColors);
?>

<div class="container-fluid" id="prospects-page">
  <div class="d-flex justify-content-between align-items-center mb-4" id="prospects-header">
    <div id="prospects-title-wrap">
      <h4 class="mb-1" id="prospects-title">Prospects</h4>
      <p class="text-muted mb-0" id="prospects-subtitle">Manage AI prospect personas for practice sessions.</p>
    </div>
    <?php if (isManager()): ?>
      <div id="prospects-header-actions">
        <a href="#" class="btn btn-primary" id="prospects-add-btn"
           hx-get="/partials/prospects/form.php" hx-target="#page-content">
          <i class="bi bi-plus-lg me-1"></i> Add Prospect
        </a>
      </div>
    <?php endif; ?>
  </div>

  <div class="card mb-4" id="prospects-filter-card">
    <div class="card-body" id="prospects-filter-card-body">
      <form class="row g-3 align-items-end" id="prospect-filter-form"
            hx-get="/partials/prospects/list.php"
            hx-target="#prospect-grid"
            hx-include="#prospect-filter-form">
        <div class="col-md-4" id="prospect-search-col">
          <label for="prospect-search" class="form-label">Search</label>
          <input type="text" class="form-control" id="prospect-search" name="search"
                 value="<?= htmlspecialchars($filters['search']) ?>"
                 placeholder="Search by company or contact name"
                 hx-trigger="keyup changed delay:500ms">
        </div>
        <div class="col-md-2" id="prospect-industry-col">
          <label for="prospect-industry" class="form-label">Industry</label>
          <select class="form-select" id="prospect-industry" name="industry" hx-trigger="change">
            <?php foreach ($industryOptions as $key => $label): ?>
              <option value="<?= htmlspecialchars($key) ?>" <?= $filters['industry'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2" id="prospect-difficulty-col">
          <label for="prospect-difficulty" class="form-label">Difficulty</label>
          <select class="form-select" id="prospect-difficulty" name="difficulty_level" hx-trigger="change">
            <option value="">All</option>
            <option value="beginner" <?= $filters['difficulty_level'] === 'beginner' ? 'selected' : '' ?>>Beginner</option>
            <option value="intermediate" <?= $filters['difficulty_level'] === 'intermediate' ? 'selected' : '' ?>>Intermediate</option>
            <option value="advanced" <?= $filters['difficulty_level'] === 'advanced' ? 'selected' : '' ?>>Advanced</option>
          </select>
        </div>
        <div class="col-md-3" id="prospect-personality-col">
          <label for="prospect-personality" class="form-label">Personality</label>
          <select class="form-select" id="prospect-personality" name="prospect_personality" hx-trigger="change">
            <option value="">All</option>
            <?php foreach ($personalityOptions as $personality): ?>
              <option value="<?= htmlspecialchars($personality) ?>" <?= $filters['prospect_personality'] === $personality ? 'selected' : '' ?>><?= htmlspecialchars($personality) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-1 d-grid" id="prospect-reset-col">
          <a href="#" class="btn btn-outline-secondary" id="prospect-reset-btn"
             hx-get="/partials/prospects/list.php" hx-target="#prospect-grid">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div id="prospect-grid">
    <div class="row row-cols-1 row-cols-md-3 g-4" id="prospect-card-row">
      <?php if (empty($prospects)): ?>
        <div class="col" id="prospect-empty-col">
          <div class="card h-100 text-center" id="prospect-empty-card">
            <div class="card-body" id="prospect-empty-body">
              <i class="bi bi-people fs-1 text-muted" id="prospect-empty-icon"></i>
              <h5 class="mt-3" id="prospect-empty-title">No prospects yet</h5>
              <p class="text-muted" id="prospect-empty-text">Create your first prospect persona to get started.</p>
              <?php if (isManager()): ?>
                <a href="#" class="btn btn-primary" id="prospect-empty-cta"
                   hx-get="/partials/prospects/form.php" hx-target="#page-content">Add Prospect</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php else: ?>
        <?php foreach ($prospects as $prospect):
          $personality = $prospect['prospect_personality'] ?? 'Busy & skeptical';
          $personalityBadge = $personalityColors[$personality] ?? 'secondary';
          $difficultyBadge = $difficultyColors[$prospect['difficulty_level'] ?? 'intermediate'] ?? 'secondary';
          $image = !empty($prospect['image']) ? htmlspecialchars($prospect['image']) : '/assets/images/default-prospect.png';
        ?>
          <div class="col" id="prospect-card-col-<?= $prospect['id'] ?>">
            <div class="card h-100" id="prospect-card-<?= $prospect['id'] ?>">
              <div class="card-body d-flex flex-column" id="prospect-card-body-<?= $prospect['id'] ?>">
                <div class="d-flex align-items-start mb-3" id="prospect-card-header-<?= $prospect['id'] ?>">
                  <img src="<?= $image ?>" alt="<?= htmlspecialchars($prospect['name']) ?>" class="rounded me-3" width="52" height="52" id="prospect-card-avatar-<?= $prospect['id'] ?>">
                  <div id="prospect-card-title-wrap-<?= $prospect['id'] ?>">
                    <h6 class="mb-1" id="prospect-card-title-<?= $prospect['id'] ?>"><?= htmlspecialchars($prospect['name']) ?></h6>
                    <p class="text-muted small mb-0" id="prospect-card-contact-<?= $prospect['id'] ?>">
                      <?= htmlspecialchars($prospect['prospect_name'] ?? 'Unknown Contact') ?> • <?= htmlspecialchars($prospect['prospect_role'] ?? 'Role N/A') ?>
                    </p>
                  </div>
                </div>
                <div class="mb-3" id="prospect-card-tags-<?= $prospect['id'] ?>">
                  <span class="badge bg-<?= $personalityBadge ?> me-1" id="prospect-card-personality-<?= $prospect['id'] ?>"><?= htmlspecialchars($personality) ?></span>
                  <?php if (!empty($prospect['industry'])): ?>
                    <span class="badge bg-light text-dark me-1" id="prospect-card-industry-<?= $prospect['id'] ?>"><?= htmlspecialchars($prospect['industry']) ?></span>
                  <?php endif; ?>
                  <span class="badge bg-<?= $difficultyBadge ?>" id="prospect-card-difficulty-<?= $prospect['id'] ?>">
                    <?= ucfirst(htmlspecialchars($prospect['difficulty_level'] ?? 'intermediate')) ?>
                  </span>
                </div>
                <?php if (!empty($prospect['description'])): ?>
                  <p class="text-muted small flex-grow-1" id="prospect-card-desc-<?= $prospect['id'] ?>"><?= htmlspecialchars($prospect['description']) ?></p>
                <?php else: ?>
                  <div class="flex-grow-1" id="prospect-card-desc-placeholder-<?= $prospect['id'] ?>"></div>
                <?php endif; ?>
                <div class="d-flex justify-content-between align-items-center mt-2" id="prospect-card-footer-<?= $prospect['id'] ?>">
                  <div class="text-muted small" id="prospect-card-usage-<?= $prospect['id'] ?>">
                    <i class="bi bi-activity me-1"></i>Used <?= (int)($prospect['usage_count'] ?? 0) ?>x
                  </div>
                  <div class="d-flex align-items-center" id="prospect-card-actions-<?= $prospect['id'] ?>">
                    <?php if (isManager()): ?>
                      <button class="btn btn-sm btn-link text-danger p-1 me-1" id="prospect-delete-btn-<?= $prospect['id'] ?>"
                              hx-delete="/partials/prospects/list.php?id=<?= $prospect['id'] ?>"
                              hx-confirm="Delete this prospect?"
                              hx-target="closest .card"
                              hx-swap="outerHTML">
                        <i class="bi bi-trash"></i>
                      </button>
                      <button class="btn btn-sm btn-link p-1 me-1" id="prospect-edit-btn-<?= $prospect['id'] ?>"
                              hx-get="/partials/prospects/form.php?id=<?= $prospect['id'] ?>"
                              hx-target="#page-content">
                        <i class="bi bi-pencil"></i>
                      </button>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-outline-primary" id="prospect-view-btn-<?= $prospect['id'] ?>"
                            hx-get="/partials/prospects/view.php?id=<?= $prospect['id'] ?>"
                            hx-target="#page-content">
                      View
                    </button>
                  </div>
                </div>
              </div>
              <a href="#" class="stretched-link" id="prospect-card-link-<?= $prospect['id'] ?>"
                 hx-get="/partials/prospects/view.php?id=<?= $prospect['id'] ?>"
                 hx-target="#page-content"></a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
      <div class="d-flex justify-content-between align-items-center mt-4" id="prospect-pagination">
        <div class="text-muted small" id="prospect-pagination-summary">
          Showing <?= count($prospects) ?> of <?= $total ?>
        </div>
        <div class="btn-group" role="group" aria-label="Pagination" id="prospect-pagination-buttons">
          <button class="btn btn-outline-secondary" id="prospect-page-prev"
                  <?= $page <= 1 ? 'disabled' : '' ?>
                  hx-get="/partials/prospects/list.php?page=<?= max(1, $page - 1) ?>"
                  hx-include="#prospect-filter-form"
                  hx-target="#prospect-grid">Prev</button>
          <span class="btn btn-outline-light text-dark" id="prospect-page-label">Page <?= $page ?> / <?= $totalPages ?></span>
          <button class="btn btn-outline-secondary" id="prospect-page-next"
                  <?= $page >= $totalPages ? 'disabled' : '' ?>
                  hx-get="/partials/prospects/list.php?page=<?= min($totalPages, $page + 1) ?>"
                  hx-include="#prospect-filter-form"
                  hx-target="#prospect-grid">Next</button>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
