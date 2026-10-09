<?php
require_once '/var/www/helpers/auth.php';
require_once '/var/www/models/Product.php';
requireManager();

$orgId = currentOrgId();
$productModel = new Product();
$categories = $productModel->getCategories($orgId);

$method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $_SERVER['REQUEST_METHOD']);
$errors = [];
$productId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$emptyValues = [
    'name' => '',
    'product_category' => '',
    'prompt' => '',
    'knowledgebase' => '',
    'features_json' => json_encode([]),
    'pricing_info' => '',
    'competitive_notes' => '',
];

$product = null;
if ($productId > 0) {
    $product = $productModel->getById($productId, $orgId);
}
$values = $product ?? $emptyValues;
$features = json_decode($values['features_json'] ?? '[]', true);
if (!is_array($features)) {
    $features = [];
}

if ($method === 'POST') {
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'product_category' => trim($_POST['product_category'] ?? ''),
        'prompt' => trim($_POST['prompt'] ?? ''),
        'knowledgebase' => trim($_POST['knowledgebase'] ?? ''),
        'pricing_info' => trim($_POST['pricing_info'] ?? ''),
        'competitive_notes' => trim($_POST['competitive_notes'] ?? ''),
    ];

    $featureInputs = $_POST['features'] ?? [];
    $featureArray = array_values(array_filter(array_map('trim', $featureInputs), fn($f) => $f !== ''));
    $data['features_json'] = json_encode($featureArray);

    if ($data['name'] === '') {
        $errors[] = 'Product name is required.';
    }
    if (mb_strlen($data['prompt']) < 50) {
        $errors[] = 'Product description/context must be at least 50 characters.';
    }

    if (empty($errors)) {
        if ($productId > 0) {
            $productModel->update($productId, $data, $orgId);
        } else {
            $productId = $productModel->create($data);
        }
        header('HX-Trigger: {"toast": {"message": "Product saved", "type": "success"}}');
        header('HX-Redirect: /partials/products/list.php');
        exit;
    } else {
        $values = array_merge($values, $data);
        $features = $featureArray;
    }
}

$isEdit = $productId > 0;
?>

<div class="container-fluid" id="product-form-page">
  <div class="d-flex justify-content-between align-items-center mb-4" id="product-form-header">
    <div id="product-form-title-wrap">
      <h4 class="mb-1" id="product-form-title"><?= $isEdit ? 'Edit Product' : 'Add Product' ?></h4>
      <p class="text-muted mb-0" id="product-form-subtitle">Define product context for practice calls.</p>
    </div>
    <div id="product-form-actions">
      <a href="#" class="btn btn-outline-secondary" id="product-form-cancel"
         hx-get="/partials/products/list.php" hx-target="#page-content">Back to list</a>
    </div>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger" id="product-form-errors">
      <ul class="mb-0" id="product-form-errors-list">
        <?php foreach ($errors as $error): ?>
          <li><?= htmlspecialchars($error) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form id="product-form" hx-post="/partials/products/form.php" hx-target="#page-content" hx-swap="outerHTML">
    <input type="hidden" name="id" value="<?= $productId ?>">

    <div class="card mb-4" id="product-main-card">
      <div class="card-header" id="product-main-card-header">
        <h6 class="mb-0" id="product-main-title">Product Details</h6>
      </div>
      <div class="card-body" id="product-main-card-body">
        <div class="row g-3" id="product-main-row">
          <div class="col-md-6" id="product-name-col">
            <label class="form-label" for="product-name">Product Name *</label>
            <input type="text" class="form-control" id="product-name" name="name" required value="<?= htmlspecialchars($values['name']) ?>">
          </div>
          <div class="col-md-6" id="product-category-input-col">
            <label class="form-label" for="product-category-input">Category</label>
            <input list="product-category-options" class="form-control" id="product-category-input" name="product_category" value="<?= htmlspecialchars($values['product_category']) ?>">
            <datalist id="product-category-options">
              <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat) ?>"></option>
              <?php endforeach; ?>
            </datalist>
          </div>
          <div class="col-12" id="product-prompt-col">
            <label class="form-label" for="product-prompt">Product Description & AI Context *</label>
            <textarea class="form-control" id="product-prompt" name="prompt" rows="5" required><?= htmlspecialchars($values['prompt']) ?></textarea>
            <small class="text-muted" id="product-prompt-help">Describe the product in detail: features, benefits, pricing tiers, ideal customers, common objections and responses. The AI prospect will use this to ask realistic questions.</small>
          </div>
          <div class="col-md-6" id="product-knowledgebase-col">
            <label class="form-label" for="product-knowledgebase">Knowledge Base URL</label>
            <input type="url" class="form-control" id="product-knowledgebase" name="knowledgebase" value="<?= htmlspecialchars($values['knowledgebase']) ?>">
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-4" id="product-features-card">
      <div class="card-header" id="product-features-header">
        <h6 class="mb-0" id="product-features-title">Key Features</h6>
      </div>
      <div class="card-body" id="product-features-body">
        <div id="feature-list">
          <?php if (empty($features)): ?>
            <div class="row g-2 align-items-center mb-2 feature-row" id="feature-row-0">
              <div class="col-11" id="feature-input-col-0">
                <input type="text" class="form-control" name="features[]" placeholder="Feature description">
              </div>
              <div class="col-1 text-end" id="feature-remove-col-0">
                <button type="button" class="btn btn-link text-danger p-0" onclick="removeFeatureRow(this)" aria-label="Remove feature"><i class="bi bi-x-circle"></i></button>
              </div>
            </div>
          <?php else: ?>
            <?php foreach ($features as $idx => $feature): ?>
              <div class="row g-2 align-items-center mb-2 feature-row" id="feature-row-<?= $idx ?>">
                <div class="col-11" id="feature-input-col-<?= $idx ?>">
                  <input type="text" class="form-control" name="features[]" value="<?= htmlspecialchars($feature) ?>">
                </div>
                <div class="col-1 text-end" id="feature-remove-col-<?= $idx ?>">
                  <button type="button" class="btn btn-link text-danger p-0" onclick="removeFeatureRow(this)" aria-label="Remove feature"><i class="bi bi-x-circle"></i></button>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <button type="button" class="btn btn-outline-primary btn-sm" id="add-feature-btn" onclick="addFeatureRow()">
          <i class="bi bi-plus-lg"></i> Add Feature
        </button>
      </div>
    </div>

    <div class="card mb-4" id="product-pricing-card">
      <div class="card-header" id="product-pricing-header">
        <h6 class="mb-0" id="product-pricing-title">Pricing & Competitive Notes</h6>
      </div>
      <div class="card-body" id="product-pricing-body">
        <div class="row g-3" id="product-pricing-row">
          <div class="col-md-6" id="product-pricing-info-col">
            <label class="form-label" for="product-pricing-info">Pricing Information</label>
            <textarea class="form-control" id="product-pricing-info" name="pricing_info" rows="3"><?= htmlspecialchars($values['pricing_info']) ?></textarea>
          </div>
          <div class="col-md-6" id="product-competitive-notes-col">
            <label class="form-label" for="product-competitive-notes">Competitive Notes</label>
            <textarea class="form-control" id="product-competitive-notes" name="competitive_notes" rows="3"><?= htmlspecialchars($values['competitive_notes']) ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-end gap-2" id="product-form-buttons">
      <a href="#" class="btn btn-light" id="product-cancel-btn" hx-get="/partials/products/list.php" hx-target="#page-content">Cancel</a>
      <button type="submit" class="btn btn-primary" id="product-submit-btn"><?= $isEdit ? 'Save Changes' : 'Create Product' ?></button>
    </div>
  </form>
</div>

<script>
function addFeatureRow() {
  const list = document.getElementById('feature-list');
  const idx = list.querySelectorAll('.feature-row').length;
  const row = document.createElement('div');
  row.className = 'row g-2 align-items-center mb-2 feature-row';
  row.id = `feature-row-${idx}`;
  row.innerHTML = `
    <div class="col-11" id="feature-input-col-${idx}">
      <input type="text" class="form-control" name="features[]" placeholder="Feature description">
    </div>
    <div class="col-1 text-end" id="feature-remove-col-${idx}">
      <button type="button" class="btn btn-link text-danger p-0" onclick="removeFeatureRow(this)" aria-label="Remove feature"><i class="bi bi-x-circle"></i></button>
    </div>`;
  list.appendChild(row);
}
function removeFeatureRow(btn) {
  const row = btn.closest('.feature-row');
  if (row && document.querySelectorAll('.feature-row').length > 1) {
    row.remove();
  }
}
</script>
