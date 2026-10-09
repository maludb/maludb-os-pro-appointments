<?php
require_once '/var/www/helpers/auth.php';
require_once '/var/www/models/Team.php';
requireManager();

$teamModel = new Team();
$method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $_SERVER['REQUEST_METHOD']);

if ($method === 'DELETE') {
    $deleteId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
    if ($deleteId > 0) {
        $teamModel->deleteById($deleteId);
    }
    exit;
}

$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

$superAdmin = isSuperAdmin();

if ($superAdmin) {
    $teams = $teamModel->getAllFiltered($search ?: null, $limit, $offset);
    $total = $teamModel->countAll($search ?: null);
} else {
    $orgId = currentOrgId();
    $teams = $teamModel->getByOrgId($orgId, $search ?: null, $limit, $offset);
    $total = $teamModel->countByOrgId($orgId, $search ?: null);
}

$totalPages = max(1, (int)ceil($total / $limit));
?>

<div class="container-fluid" id="teams-page">
  <div class="d-flex justify-content-between align-items-center mb-4" id="teams-header">
    <div id="teams-title-wrap">
      <h4 class="mb-1" id="teams-title">Teams</h4>
      <p class="text-muted mb-0" id="teams-subtitle"><?= $superAdmin ? 'All teams across all organizations.' : 'Teams in your organization.' ?></p>
    </div>
    <div id="teams-header-actions">
      <a href="#" class="btn btn-primary" id="teams-add-btn"
         hx-get="/partials/team/form.php" hx-target="#page-content">
        <i class="bi bi-plus-lg me-1"></i> Create New Team
      </a>
    </div>
  </div>

  <div class="card mb-4" id="teams-filter-card">
    <div class="card-body" id="teams-filter-body">
      <form class="row g-3 align-items-end" id="team-filter-form"
            hx-get="/partials/team/members.php"
            hx-target="#team-grid"
            hx-include="#team-filter-form">
        <div class="col-md-10" id="team-search-col">
          <label class="form-label" for="team-search">Search</label>
          <input type="text" class="form-control" id="team-search" name="search"
                 value="<?= htmlspecialchars($search) ?>"
                 placeholder="Search by team name or description"
                 hx-trigger="keyup changed delay:500ms">
        </div>
        <div class="col-md-2 d-grid" id="team-reset-col">
          <a href="#" class="btn btn-outline-secondary" id="team-reset-btn"
             hx-get="/partials/team/members.php" hx-target="#team-grid">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div id="team-grid">
    <div class="row row-cols-1 row-cols-md-3 g-4" id="team-card-row">
      <?php if (empty($teams)): ?>
        <div class="col" id="team-empty-col">
          <div class="card h-100 text-center" id="team-empty-card">
            <div class="card-body" id="team-empty-body">
              <i class="bi bi-people fs-1 text-muted" id="team-empty-icon"></i>
              <h5 class="mt-3" id="team-empty-title">No teams found</h5>
              <p class="text-muted" id="team-empty-text">Create your first team to get started.</p>
              <a href="#" class="btn btn-primary" id="team-empty-cta"
                 hx-get="/partials/team/form.php" hx-target="#page-content">Create New Team</a>
            </div>
          </div>
        </div>
      <?php else: ?>
        <?php foreach ($teams as $team):
          $desc = trim(mb_substr(strip_tags($team['description'] ?? ''), 0, 100));
          if (mb_strlen(strip_tags($team['description'] ?? '')) > 100) { $desc .= '...'; }
        ?>
          <div class="col" id="team-card-col-<?= $team['id'] ?>">
            <div class="card h-100" id="team-card-<?= $team['id'] ?>">
              <div class="card-body d-flex flex-column" id="team-card-body-<?= $team['id'] ?>">
                <h6 class="mb-1" id="team-card-title-<?= $team['id'] ?>"><?= htmlspecialchars($team['name']) ?></h6>
                <?php if ($superAdmin && !empty($team['org_name'])): ?>
                  <span class="badge bg-light text-dark mb-2" id="team-card-org-<?= $team['id'] ?>"><?= htmlspecialchars($team['org_name']) ?></span>
                <?php endif; ?>
                <p class="text-muted small flex-grow-1" id="team-card-desc-<?= $team['id'] ?>"><?= htmlspecialchars($desc ?: 'No description.') ?></p>
                <div class="d-flex justify-content-between align-items-center" id="team-card-footer-<?= $team['id'] ?>">
                  <div class="text-muted small" id="team-card-meta-<?= $team['id'] ?>">
                    <?= (int)($team['member_count'] ?? 0) ?> member<?= ($team['member_count'] ?? 0) != 1 ? 's' : '' ?>
                  </div>
                  <div class="d-flex gap-2" id="team-card-actions-<?= $team['id'] ?>">
                    <button class="btn btn-sm btn-link text-danger p-1" id="team-delete-btn-<?= $team['id'] ?>"
                            hx-delete="/partials/team/members.php?id=<?= $team['id'] ?>"
                            hx-confirm="Delete this team?"
                            hx-target="closest .card"
                            hx-swap="outerHTML">
                      <i class="bi bi-trash"></i>
                    </button>
                    <button class="btn btn-sm btn-link p-1" id="team-edit-btn-<?= $team['id'] ?>"
                            hx-get="/partials/team/form.php?id=<?= $team['id'] ?>"
                            hx-target="#page-content">
                      <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-primary" id="team-view-btn-<?= $team['id'] ?>"
                            hx-get="/partials/team/view.php?id=<?= $team['id'] ?>"
                            hx-target="#page-content">View</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
      <div class="d-flex justify-content-between align-items-center mt-4" id="team-pagination">
        <div class="text-muted small" id="team-pagination-summary">Showing <?= count($teams) ?> of <?= $total ?></div>
        <div class="btn-group" role="group" aria-label="Pagination" id="team-pagination-buttons">
          <button class="btn btn-outline-secondary" id="team-page-prev"
                  <?= $page <= 1 ? 'disabled' : '' ?>
                  hx-get="/partials/team/members.php?page=<?= max(1, $page - 1) ?>"
                  hx-include="#team-filter-form"
                  hx-target="#team-grid">Prev</button>
          <span class="btn btn-outline-light text-dark" id="team-page-label">Page <?= $page ?> / <?= $totalPages ?></span>
          <button class="btn btn-outline-secondary" id="team-page-next"
                  <?= $page >= $totalPages ? 'disabled' : '' ?>
                  hx-get="/partials/team/members.php?page=<?= min($totalPages, $page + 1) ?>"
                  hx-include="#team-filter-form"
                  hx-target="#team-grid">Next</button>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
