<?php
require_once '/var/www/helpers/auth.php';
require_once '/var/www/models/Prospect.php';
require_once '/var/www/models/Product.php';
requireAuth();

$orgId = currentOrgId();
$step = $_GET['step'] ?? '1';
$prospectId = (int)($_GET['prospect_id'] ?? 0);
$productId = (int)($_GET['product_id'] ?? 0);

$prospectModel = new Prospect();
$productModel = new Product();

function renderProspectCards($prospects, $selectedId) {
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
    foreach ($prospects as $p) {
        $pid = $p['id'];
        $selected = $pid == $selectedId ? 'border-primary' : 'border-light';
        $personality = $p['prospect_personality'] ?? 'Busy & skeptical';
        $personalityBadge = $personalityColors[$personality] ?? 'secondary';
        $difficultyBadge = $difficultyColors[$p['difficulty_level'] ?? 'intermediate'] ?? 'secondary';
        $img = !empty($p['image']) ? htmlspecialchars($p['image']) : '/assets/images/default-prospect.png';
        echo "<div class='col' id='setup-prospect-col-$pid'>
                <div class='card h-100 $selected' style='cursor:pointer;' onclick=\"selectProspect($pid)\" id='setup-prospect-card-$pid'>
                  <div class='card-body d-flex flex-column'>
                    <div class='d-flex align-items-start mb-2'>
                      <img src='$img' class='rounded me-2' width='52' height='52' alt=''>
                      <div>
                        <h6 class='mb-0'>" . htmlspecialchars($p['name']) . "</h6>
                        <p class='text-muted small mb-0'>" . htmlspecialchars($p['prospect_name'] ?? '') . " • " . htmlspecialchars($p['prospect_role'] ?? '') . "</p>
                      </div>
                    </div>
                    <div class='mb-2'>
                      <span class='badge bg-$personalityBadge me-1'>" . htmlspecialchars($personality) . "</span>
                      <span class='badge bg-$difficultyBadge'>" . ucfirst(htmlspecialchars($p['difficulty_level'] ?? '')) . "</span>
                    </div>
                    <p class='text-muted small flex-grow-1'>" . htmlspecialchars($p['description'] ?? '') . "</p>
                  </div>
                </div>
              </div>";
    }
}

function renderProductCards($products, $selectedId) {
    foreach ($products as $product) {
        $pid = $product['id'];
        $selected = $pid == $selectedId ? 'border-primary' : 'border-light';
        $summary = trim(mb_substr(strip_tags($product['prompt'] ?? ''), 0, 100));
        if (mb_strlen(strip_tags($product['prompt'] ?? '')) > 100) {
            $summary .= '…';
        }
        echo "<div class='col' id='setup-product-col-$pid'>
                <div class='card h-100 $selected' style='cursor:pointer;' onclick=\"selectProduct($pid)\" id='setup-product-card-$pid'>
                  <div class='card-body d-flex flex-column'>
                    <div class='d-flex justify-content-between align-items-start mb-2'>
                      <h6 class='mb-0'>" . htmlspecialchars($product['name']) . "</h6>
                      <span class='badge bg-light text-dark'>" . htmlspecialchars($product['product_category'] ?? 'Uncategorized') . "</span>
                    </div>
                    <p class='text-muted small flex-grow-1'>" . htmlspecialchars($summary ?: 'No description yet.') . "</p>
                  </div>
                </div>
              </div>";
    }
}
?>

<div class="container-fluid" id="session-setup">
  <?php if ($step === '1'): ?>
    <?php
      $prospects = $prospectModel->getAll($orgId, [], 50, 0);
    ?>
    <div class="d-flex justify-content-between align-items-center mb-4" id="session-step1-header">
      <div>
        <h4 class="mb-1">Select a Prospect</h4>
        <p class="text-muted mb-0">Choose who you'll be calling.</p>
      </div>
    </div>
    <div class="row row-cols-1 row-cols-md-3 g-3 mb-3" id="session-step1-grid">
      <?php if (empty($prospects)): ?>
        <div class="col"><div class="alert alert-info">No prospects available.</div></div>
      <?php else: renderProspectCards($prospects, $prospectId); endif; ?>
    </div>
    <form class="d-flex justify-content-end gap-2" id="session-step1-actions"
          hx-get="/partials/sessions/setup.php"
          hx-target="#page-content"
          hx-include="#session-step1-hidden">
      <input type="hidden" name="step" value="2" id="step1-next-step">
      <div id="session-step1-hidden"></div>
      <button type="submit" class="btn btn-primary" id="session-step1-next" disabled>Next</button>
    </form>
    <script>
      function selectProspect(id) {
        document.querySelectorAll('[id^=\"setup-prospect-card-\"]')?.forEach(card => card.classList.remove('border-primary'));
        const card = document.getElementById('setup-prospect-card-' + id);
        if (card) card.classList.add('border-primary');
        const hidden = document.getElementById('session-step1-hidden');
        hidden.innerHTML = `<input type="hidden" name="prospect_id" value="${id}">`;
        document.getElementById('session-step1-next').disabled = false;
      }
    </script>
  <?php elseif ($step === '2' && $prospectId): ?>
    <?php $products = $productModel->getAll($orgId, null, null, 50, 0); ?>
    <div class="d-flex justify-content-between align-items-center mb-4" id="session-step2-header">
      <div>
        <h4 class="mb-1">Select a Product</h4>
        <p class="text-muted mb-0">What are you selling?</p>
      </div>
    </div>
    <div class="row row-cols-1 row-cols-md-3 g-3 mb-3" id="session-step2-grid">
      <?php if (empty($products)): ?>
        <div class="col"><div class="alert alert-info">No products available.</div></div>
      <?php else: renderProductCards($products, $productId); endif; ?>
    </div>
    <form class="d-flex justify-content-between gap-2" id="session-step2-actions"
          hx-get="/partials/sessions/setup.php"
          hx-target="#page-content">
      <input type="hidden" name="step" value="1">
      <button type="submit" class="btn btn-outline-secondary">Back</button>
    </form>
    <form class="d-flex justify-content-end gap-2 mt-2" id="session-step2-nextform"
          hx-get="/partials/sessions/setup.php"
          hx-target="#page-content"
          hx-include="#session-step2-hidden">
      <input type="hidden" name="step" value="3">
      <input type="hidden" name="prospect_id" value="<?= $prospectId ?>">
      <div id="session-step2-hidden"></div>
      <button type="submit" class="btn btn-primary" id="session-step2-next" disabled>Next</button>
    </form>
    <script>
      function selectProduct(id) {
        document.querySelectorAll('[id^=\"setup-product-card-\"]')?.forEach(card => card.classList.remove('border-primary'));
        const card = document.getElementById('setup-product-card-' + id);
        if (card) card.classList.add('border-primary');
        const hidden = document.getElementById('session-step2-hidden');
        hidden.innerHTML = `<input type="hidden" name="product_id" value="${id}">`;
        document.getElementById('session-step2-next').disabled = false;
      }
    </script>
  <?php elseif ($step === '3' && $prospectId && $productId): ?>
    <?php
      $prospect = $prospectModel->getById($prospectId, $orgId);
      $product = $productModel->getById($productId, $orgId);
      $callTypes = [
        'cold_call' => "You're calling this prospect for the first time",
        'discovery' => 'Qualify the prospect and understand their needs',
        'demo' => 'Present the product to an interested prospect',
        'objection_handling' => 'The prospect has concerns to address',
        'closing' => 'Push for commitment and next steps',
        'renewal' => 'Retain and expand an existing customer',
      ];
    ?>
    <div class="d-flex justify-content-between align-items-center mb-4" id="session-step3-header">
      <div>
        <h4 class="mb-1">Configure Call</h4>
        <p class="text-muted mb-0">Review selections and choose call type.</p>
      </div>
    </div>
    <div class="row g-3 mb-4" id="session-step3-cards">
      <div class="col-md-6"><div class="card h-100"><div class="card-body"><h6>Prospect</h6><p class="mb-0"><?= htmlspecialchars($prospect['prospect_name'] ?? '') ?> (<?= htmlspecialchars($prospect['prospect_role'] ?? '') ?>) at <?= htmlspecialchars($prospect['name'] ?? '') ?></p></div></div></div>
      <div class="col-md-6"><div class="card h-100"><div class="card-body"><h6>Product</h6><p class="mb-0"><?= htmlspecialchars($product['name'] ?? '') ?> — <?= htmlspecialchars($product['product_category'] ?? '') ?></p></div></div></div>
    </div>
    <form id="session-step3-form" hx-post="/partials/sessions/call.php" hx-target="#page-content">
      <input type="hidden" name="prospect_id" value="<?= $prospectId ?>">
      <input type="hidden" name="product_id" value="<?= $productId ?>">
      <div class="card mb-4"><div class="card-body"><h6 class="mb-3">Call Type</h6>
        <div class="row g-2">
          <?php foreach ($callTypes as $key => $label): ?>
            <div class="col-md-4">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="call_type" id="calltype-<?= $key ?>" value="<?= $key ?>" <?= $key === 'cold_call' ? 'checked' : '' ?>>
                <label class="form-check-label" for="calltype-<?= $key ?>"><?= htmlspecialchars(ucwords(str_replace('_',' ', $key))) ?> <br><small class="text-muted"><?= htmlspecialchars($label) ?></small></label>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div></div>
      <div class="d-flex justify-content-between">
        <button type="button" class="btn btn-outline-secondary" hx-get="/partials/sessions/setup.php?step=2&prospect_id=<?= $prospectId ?>" hx-target="#page-content">Back</button>
        <button type="submit" class="btn btn-primary">Start Practice Call</button>
      </div>
    </form>
  <?php endif; ?>
</div>
