<?php
require_once '/var/www/helpers/auth.php';
require_once '/var/www/models/Product.php';
requireAuth();

$orgId = currentOrgId();
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$productModel = new Product();
$product = $productId ? $productModel->getById($productId, $orgId) : null;

if (!$product) {
    http_response_code(404);
    echo '<div class="alert alert-danger" id="product-view-error">Product not found.</div>';
    exit;
}

$features = json_decode($product['features_json'] ?? '[]', true);
if (!is_array($features)) {
    $features = [];
}
?>

<div class="container-fluid" id="product-view-page">
  <div class="d-flex justify-content-between align-items-center mb-4" id="product-view-header">
    <div id="product-view-title-wrap">
      <h3 class="mb-1" id="product-view-title"><?= htmlspecialchars($product['name']) ?></h3>
      <p class="text-muted mb-1" id="product-view-category">
        <?= !empty($product['product_category']) ? htmlspecialchars($product['product_category']) : 'Uncategorized' ?>
      </p>
    </div>
    <div class="d-flex gap-2" id="product-view-actions">
      <a href="#" class="btn btn-outline-secondary" id="product-view-back"
         hx-get="/partials/products/list.php" hx-target="#page-content">Back</a>
      <?php if (isManager()): ?>
        <a href="#" class="btn btn-outline-primary" id="product-view-edit"
           hx-get="/partials/products/form.php?id=<?= $productId ?>" hx-target="#page-content">
          <i class="bi bi-pencil me-1"></i>Edit
        </a>
      <?php endif; ?>
      <a href="#" class="btn btn-primary" id="product-view-practice"
         hx-get="/partials/sessions/setup.php?product_id=<?= $productId ?>" hx-target="#page-content">
        <i class="bi bi-telephone-outbound me-1"></i>Practice Selling This Product
      </a>
    </div>
  </div>

  <div class="card mb-4" id="product-view-prompt-card">
    <div class="card-header" id="product-view-prompt-header">
      <h6 class="mb-0" id="product-view-prompt-title">Product Description & AI Context</h6>
    </div>
    <div class="card-body" id="product-view-prompt-body">
      <p class="mb-0" id="product-view-prompt-text"><?= nl2br(htmlspecialchars($product['prompt'] ?? '')) ?></p>
    </div>
  </div>

  <div class="row g-4 mb-4" id="product-view-details-row">
    <div class="col-lg-6" id="product-view-features-col">
      <div class="card h-100" id="product-view-features-card">
        <div class="card-header" id="product-view-features-header">
          <h6 class="mb-0" id="product-view-features-title">Key Features</h6>
        </div>
        <div class="card-body" id="product-view-features-body">
          <?php if (empty($features)): ?>
            <p class="text-muted mb-0" id="product-view-features-empty">No features listed.</p>
          <?php else: ?>
            <ul class="mb-0" id="product-view-features-list">
              <?php foreach ($features as $idx => $feature): ?>
                <li id="product-view-feature-<?= $idx ?>"><?= htmlspecialchars($feature) ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-lg-6" id="product-view-pricing-col">
      <div class="card h-100" id="product-view-pricing-card">
        <div class="card-header" id="product-view-pricing-header">
          <h6 class="mb-0" id="product-view-pricing-title">Pricing Information</h6>
        </div>
        <div class="card-body" id="product-view-pricing-body">
          <p class="mb-0" id="product-view-pricing-text"><?= nl2br(htmlspecialchars($product['pricing_info'] ?? '—')) ?></p>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4" id="product-view-competitive-card">
    <div class="card-header" id="product-view-competitive-header">
      <h6 class="mb-0" id="product-view-competitive-title">Competitive Notes</h6>
    </div>
    <div class="card-body" id="product-view-competitive-body">
      <p class="mb-0" id="product-view-competitive-text"><?= nl2br(htmlspecialchars($product['competitive_notes'] ?? '—')) ?></p>
    </div>
  </div>

  <?php if (!empty($product['knowledgebase'])): ?>
  <div class="card" id="product-view-knowledge-card">
    <div class="card-body" id="product-view-knowledge-body">
      <strong>Knowledge Base:</strong>
      <a href="<?= htmlspecialchars($product['knowledgebase']) ?>" target="_blank" rel="noopener" id="product-view-knowledge-link">Open link</a>
    </div>
  </div>
  <?php endif; ?>
</div>
