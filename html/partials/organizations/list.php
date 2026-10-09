<?php
require_once '/var/www/helpers/auth.php';
require_once '/var/www/models/Organization.php';
requireManager();

$orgModel = new Organization();
$method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $_SERVER['REQUEST_METHOD']);

if ($method === 'DELETE') {
    $deleteId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
    if ($deleteId > 0) {
        $orgModel->delete($deleteId);
    }
    exit;
}

$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

$orgs = $orgModel->getAll($search ?: null, $limit, $offset);
$total = $orgModel->countAll($search ?: null);
$totalPages = max(1, (int)ceil($total / $limit));
?>

<div class="container-fluid" id="orgs-page">
  <div class="d-flex justify-content-between align-items-center mb-4" id="orgs-header">
    <div id="orgs-title-wrap">
      <h4 class="mb-1" id="orgs-title">Organizations</h4>
      <p class="text-muted mb-0" id="orgs-subtitle">Manage organizations in the system.</p>
    </div>
    <div id="orgs-header-actions">
      <a href="#" class="btn btn-primary" id="orgs-add-btn"
         hx-get="/partials/organizations/form.php" hx-target="#page-content">
        <i class="bi bi-plus-lg me-1"></i> Add Organization
      </a>
    </div>
  </div>

  <div class="card mb-4" id="orgs-filter-card">
    <div class="card-body" id="orgs-filter-body">
      <form class="row g-3 align-items-end" id="org-filter-form"
            hx-get="/partials/organizations/list.php"
            hx-target="#org-grid"
            hx-include="#org-filter-form">
        <div class="col-md-10" id="org-search-col">
          <label class="form-label" for="org-search">Search</label>
          <input type="text" class="form-control" id="org-search" name="search"
                 value="<?= htmlspecialchars($search) ?>"
                 placeholder="Search by name or description"
                 hx-trigger="keyup changed delay:500ms">
        </div>
        <div class="col-md-2 d-grid" id="org-reset-col">
          <a href="#" class="btn btn-outline-secondary" id="org-reset-btn"
             hx-get="/partials/organizations/list.php" hx-target="#org-grid">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div id="org-grid">
    <div class="row row-cols-1 row-cols-md-3 g-4" id="org-card-row">
      <?php if (empty($orgs)): ?>
        <div class="col" id="org-empty-col">
          <div class="card h-100 text-center" id="org-empty-card">
            <div class="card-body" id="org-empty-body">
              <i class="bi bi-building fs-1 text-muted" id="org-empty-icon"></i>
              <h5 class="mt-3" id="org-empty-title">No organizations found</h5>
              <p class="text-muted" id="org-empty-text">Add your first organization to get started.</p>
              <a href="#" class="btn btn-primary" id="org-empty-cta"
                 hx-get="/partials/organizations/form.php" hx-target="#page-content">Add Organization</a>
            </div>
          </div>
        </div>
      <?php else: ?>
        <?php foreach ($orgs as $org):
          $desc = trim(mb_substr(strip_tags($org['description'] ?? ''), 0, 100));
          if (mb_strlen(strip_tags($org['description'] ?? '')) > 100) {
              $desc .= '...';
          }
        ?>
          <div class="col" id="org-card-col-<?= $org['id'] ?>">
            <div class="card h-100" id="org-card-<?= $org['id'] ?>">
              <div class="card-body d-flex flex-column" id="org-card-body-<?= $org['id'] ?>">
                <h6 class="mb-2" id="org-card-title-<?= $org['id'] ?>">
                  <a href="#" class="text-decoration-none text-dark" id="org-card-title-link-<?= $org['id'] ?>"
                     hx-get="/partials/organizations/view.php?id=<?= $org['id'] ?>" hx-target="#page-content"><?= htmlspecialchars($org['name']) ?></a>
                </h6>
                <p class="text-muted small flex-grow-1" id="org-card-desc-<?= $org['id'] ?>"><?= htmlspecialchars($desc ?: 'No description.') ?></p>
                <div class="d-flex justify-content-between align-items-center" id="org-card-actions-row-<?= $org['id'] ?>">
                  <div class="text-muted small" id="org-card-updated-<?= $org['id'] ?>">
                    Updated <?= htmlspecialchars(date('M j, Y', strtotime($org['updated_at'] ?? $org['created_at'] ?? 'now'))) ?>
                  </div>
                  <div class="d-flex gap-2" id="org-card-actions-<?= $org['id'] ?>">
                    <button class="btn btn-sm btn-link text-danger p-1" id="org-delete-btn-<?= $org['id'] ?>"
                            hx-delete="/partials/organizations/list.php?id=<?= $org['id'] ?>"
                            hx-confirm="Delete this organization?"
                            hx-target="closest .card"
                            hx-swap="outerHTML">
                      <i class="bi bi-trash"></i>
                    </button>
                    <button class="btn btn-sm btn-link p-1" id="org-edit-btn-<?= $org['id'] ?>"
                            hx-get="/partials/organizations/form.php?id=<?= $org['id'] ?>"
                            hx-target="#page-content">
                      <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-primary" id="org-view-btn-<?= $org['id'] ?>"
                            hx-get="/partials/organizations/view.php?id=<?= $org['id'] ?>"
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
      <div class="d-flex justify-content-between align-items-center mt-4" id="org-pagination">
        <div class="text-muted small" id="org-pagination-summary">Showing <?= count($orgs) ?> of <?= $total ?></div>
        <div class="btn-group" role="group" aria-label="Pagination" id="org-pagination-buttons">
          <button class="btn btn-outline-secondary" id="org-page-prev"
                  <?= $page <= 1 ? 'disabled' : '' ?>
                  hx-get="/partials/organizations/list.php?page=<?= max(1, $page - 1) ?>"
                  hx-include="#org-filter-form"
                  hx-target="#org-grid">Prev</button>
          <span class="btn btn-outline-light text-dark" id="org-page-label">Page <?= $page ?> / <?= $totalPages ?></span>
          <button class="btn btn-outline-secondary" id="org-page-next"
                  <?= $page >= $totalPages ? 'disabled' : '' ?>
                  hx-get="/partials/organizations/list.php?page=<?= min($totalPages, $page + 1) ?>"
                  hx-include="#org-filter-form"
                  hx-target="#org-grid">Next</button>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
