<?php
require_once '/var/www/helpers/auth.php';
require_once '/var/www/models/Product.php';
requireAuth();

$orgId = currentOrgId();
$productModel = new Product();
$method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $_SERVER['REQUEST_METHOD']);

if ($method === 'DELETE') {
    requireManager();
    $deleteId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
    if ($deleteId > 0) {
        $productModel->delete($deleteId, $orgId);
    }
    exit;
}

$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 9;
$offset = ($page - 1) * $limit;

$products = $productModel->getAll($orgId, $category ?: null, $search ?: null, $limit, $offset);
$total = $productModel->countAll($orgId, $category ?: null, $search ?: null);
$totalPages = max(1, (int)ceil($total / $limit));
$categories = $productModel->getCategories($orgId);
?>

<div class="container-fluid" id="products-page">
  <div class="d-flex justify-content-between align-items-center mb-4" id="products-header">
    <div id="products-title-wrap">
      <h4 class="mb-1" id="products-title">Products</h4>
      <p class="text-muted mb-0" id="products-subtitle">Configure offerings reps will practice selling.</p>
    </div>
    <?php if (isManager()): ?>
      <div id="products-header-actions">
        <a href="#" class="btn btn-primary" id="products-add-btn"
           hx-get="/partials/products/form.php" hx-target="#page-content">
          <i class="bi bi-plus-lg me-1"></i> Add Product
        </a>
      </div>
    <?php endif; ?>
  </div>

  <div class="card mb-4" id="products-filter-card">
    <div class="card-body" id="products-filter-body">
      <form class="row g-3 align-items-end" id="product-filter-form"
            hx-get="/partials/products/list.php"
            hx-target="#product-grid"
            hx-include="#product-filter-form">
        <div class="col-md-6" id="product-search-col">
          <label class="form-label" for="product-search">Search</label>
          <input type="text" class="form-control" id="product-search" name="search"
                 value="<?= htmlspecialchars($search) ?>"
                 placeholder="Search by name or description"
                 hx-trigger="keyup changed delay:500ms">
        </div>
        <div class="col-md-4" id="product-category-col">
          <label class="form-label" for="product-category">Category</label>
          <select class="form-select" id="product-category" name="category" hx-trigger="change">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 d-grid" id="product-reset-col">
          <a href="#" class="btn btn-outline-secondary" id="product-reset-btn"
             hx-get="/partials/products/list.php" hx-target="#product-grid">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div id="product-grid">
    <div class="row row-cols-1 row-cols-md-3 g-4" id="product-card-row">
      <?php if (empty($products)): ?>
        <div class="col" id="product-empty-col">
          <div class="card h-100 text-center" id="product-empty-card">
            <div class="card-body" id="product-empty-body">
              <i class="bi bi-box-seam fs-1 text-muted" id="product-empty-icon"></i>
              <h5 class="mt-3" id="product-empty-title">No products configured</h5>
              <p class="text-muted" id="product-empty-text">Add your first product to enable practice sessions.</p>
              <?php if (isManager()): ?>
                <a href="#" class="btn btn-primary" id="product-empty-cta"
                   hx-get="/partials/products/form.php" hx-target="#page-content">Add Product</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php else: ?>
        <?php foreach ($products as $product):
          $summary = trim(mb_substr(strip_tags($product['prompt'] ?? ''), 0, 100));
          if (mb_strlen(strip_tags($product['prompt'] ?? '')) > 100) {
              $summary .= '…';
          }
        ?>
          <div class="col" id="product-card-col-<?= $product['id'] ?>">
            <div class="card h-100" id="product-card-<?= $product['id'] ?>">
              <div class="card-body d-flex flex-column" id="product-card-body-<?= $product['id'] ?>">
                <div class="d-flex justify-content-between align-items-start mb-2" id="product-card-header-<?= $product['id'] ?>">
                  <h6 class="mb-0" id="product-card-title-<?= $product['id'] ?>"><?= htmlspecialchars($product['name']) ?></h6>
                  <?php if (!empty($product['product_category'])): ?>
                    <span class="badge bg-light text-dark" id="product-card-category-<?= $product['id'] ?>"><?= htmlspecialchars($product['product_category']) ?></span>
                  <?php endif; ?>
                </div>
                <p class="text-muted small flex-grow-1" id="product-card-summary-<?= $product['id'] ?>"><?= htmlspecialchars($summary ?: 'No description yet.') ?></p>
                <div class="d-flex justify-content-between align-items-center" id="product-card-actions-row-<?= $product['id'] ?>">
                  <div class="text-muted small" id="product-card-updated-<?= $product['id'] ?>">Updated <?= htmlspecialchars(date('M j, Y', strtotime($product['updated_at'] ?? $product['created_at'] ?? 'now'))) ?></div>
                  <div class="d-flex gap-2" id="product-card-actions-<?= $product['id'] ?>">
                    <?php if (isManager()): ?>
                      <button class="btn btn-sm btn-link text-danger p-1" id="product-delete-btn-<?= $product['id'] ?>"
                              hx-delete="/partials/products/list.php?id=<?= $product['id'] ?>"
                              hx-confirm="Delete this product?"
                              hx-target="closest .card"
                              hx-swap="outerHTML">
                        <i class="bi bi-trash"></i>
                      </button>
                      <button class="btn btn-sm btn-link p-1" id="product-edit-btn-<?= $product['id'] ?>"
                              hx-get="/partials/products/form.php?id=<?= $product['id'] ?>"
                              hx-target="#page-content">
                        <i class="bi bi-pencil"></i>
                      </button>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-outline-primary" id="product-view-btn-<?= $product['id'] ?>"
                            hx-get="/partials/products/view.php?id=<?= $product['id'] ?>"
                            hx-target="#page-content">View</button>
                  </div>
                </div>
              </div>
              <a href="#" class="stretched-link" id="product-card-link-<?= $product['id'] ?>"
                 hx-get="/partials/products/view.php?id=<?= $product['id'] ?>"
                 hx-target="#page-content"></a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
      <div class="d-flex justify-content-between align-items-center mt-4" id="product-pagination">
        <div class="text-muted small" id="product-pagination-summary">Showing <?= count($products) ?> of <?= $total ?></div>
        <div class="btn-group" role="group" aria-label="Pagination" id="product-pagination-buttons">
          <button class="btn btn-outline-secondary" id="product-page-prev"
                  <?= $page <= 1 ? 'disabled' : '' ?>
                  hx-get="/partials/products/list.php?page=<?= max(1, $page - 1) ?>"
                  hx-include="#product-filter-form"
                  hx-target="#product-grid">Prev</button>
          <span class="btn btn-outline-light text-dark" id="product-page-label">Page <?= $page ?> / <?= $totalPages ?></span>
          <button class="btn btn-outline-secondary" id="product-page-next"
                  <?= $page >= $totalPages ? 'disabled' : '' ?>
                  hx-get="/partials/products/list.php?page=<?= min($totalPages, $page + 1) ?>"
                  hx-include="#product-filter-form"
                  hx-target="#product-grid">Next</button>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
