<?php
require_once '/var/www/helpers/auth.php';
require_once '/var/www/models/Session.php';
require_once '/var/www/models/Prospect.php';
require_once '/var/www/models/Product.php';
require_once '/var/www/models/ScoringCategory.php';
requireAuth();

$sessionId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$sessionModel = new Session();
$session = $sessionModel->getById($sessionId, currentOrgId());

if (!$session) {
    echo '<div class="alert alert-danger">Session not found.</div>';
    return;
}
if ($session['user_id'] != currentUserId() && !isManager()) {
    echo '<div class="alert alert-danger">Access denied.</div>';
    return;
}

$prospectModel = new Prospect();
$productModel = new Product();
$catModel = new ScoringCategory();

$prospect = $prospectModel->getById((int)$session['prospect_id'], currentOrgId());
$product = $productModel->getById((int)$session['product_id'], currentOrgId());
$categories = $catModel->getAll(currentOrgId());
$categoryScores = json_decode($session['scoring_data'] ?? '{}', true) ?: [];
$strengths = json_decode($session['strengths'] ?? '[]', true) ?: [];
$improvements = json_decode($session['improvements'] ?? '[]', true) ?: [];
$score = (float)($session['quality_score'] ?? 0);
$scoreColor = $score >= 7 ? 'success' : ($score >= 5 ? 'warning' : 'danger');
$callType = ucfirst(str_replace('_',' ', $session['call_type'] ?? 'cold_call'));
$transcript = json_decode($session['transcript'] ?? '[]', true);
$managerNotes = $session['manager_notes'] ?? '';

if (($_POST['action'] ?? '') === 'add_note' && isManager()) {
    $note = trim($_POST['manager_notes'] ?? '');
    $sessionModel->update($sessionId, ['manager_notes' => $note]);
    $managerNotes = $note;
}
?>

<div class="container-fluid" id="session-review-page">
  <div class="d-flex justify-content-between align-items-center mb-4" id="session-review-header">
    <div>
      <h4 class="mb-1">Session Review</h4>
      <p class="text-muted mb-0">Call type: <span class="badge bg-light text-dark"><?= htmlspecialchars($callType) ?></span> • Duration: <?= (int)(($session['duration_ms'] ?? 0)/1000) ?>s</p>
    </div>
    <div class="d-flex gap-2">
      <a href="#" class="btn btn-outline-secondary" hx-get="/partials/sessions/setup.php" hx-target="#page-content">Try Different Prospect</a>
      <?php if ($prospect && $product): ?>
      <a href="#" class="btn btn-primary" hx-get="/partials/sessions/setup.php?step=3&prospect_id=<?= $prospect['id'] ?>&product_id=<?= $product['id'] ?>" hx-target="#page-content">Practice Again</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="row g-4 mb-4" id="session-review-toprow">
    <div class="col-md-4">
      <div class="card h-100 text-center" id="session-score-card">
        <div class="card-body">
          <div class="display-4 text-<?= $scoreColor ?>" id="session-score-value"><?= $score ? number_format($score,1) : '—' ?></div>
          <p class="text-muted mb-1">Overall Score /10</p>
          <p class="mb-0" id="session-call-summary"><?= htmlspecialchars($session['call_summary'] ?? 'Scoring pending or using mock scoring.') ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-8">
      <div class="card h-100" id="session-meta-card">
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6"><strong>Prospect:</strong> <?= htmlspecialchars($prospect['prospect_name'] ?? '') ?> (<?= htmlspecialchars($prospect['prospect_role'] ?? '') ?>) at <?= htmlspecialchars($prospect['name'] ?? '') ?></div>
            <div class="col-md-6"><strong>Product:</strong> <?= htmlspecialchars($product['name'] ?? '') ?> (<?= htmlspecialchars($product['product_category'] ?? '') ?>)</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4" id="session-categories-card">
    <div class="card-header"><h6 class="mb-0">Category Scores</h6></div>
    <div class="card-body" id="session-categories-body">
      <?php if (empty($categories)): ?>
        <p class="text-muted">No categories configured.</p>
      <?php else: ?>
        <?php foreach ($categories as $cat):
          $cScore = $categoryScores[$cat['id']]['score'] ?? null;
          $feedback = $categoryScores[$cat['id']]['feedback'] ?? '';
          $pct = $cScore ? min(100, max(0, ($cScore/10)*100)) : 0;
        ?>
          <div class="mb-3" id="category-row-<?= $cat['id'] ?>">
            <div class="d-flex justify-content-between"><span><strong><?= htmlspecialchars($cat['name']) ?></strong> <small class="text-muted">(<?= $cat['weight'] ?>x)</small></span><span><?= $cScore ? number_format($cScore,1) : '—' ?>/10</span></div>
            <div class="progress" style="height:10px;"><div class="progress-bar" role="progressbar" style="width: <?= $pct ?>%;"></div></div>
            <?php if ($feedback): ?><p class="text-muted small mb-0 mt-1"><?= htmlspecialchars($feedback) ?></p><?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="row g-4 mb-4" id="session-strengths-improvements">
    <div class="col-md-6">
      <div class="card h-100" id="session-strengths-card">
        <div class="card-header"><h6 class="mb-0">Strengths</h6></div>
        <div class="card-body">
          <?php if (empty($strengths)): ?><p class="text-muted mb-0">No strengths recorded.</p><?php else: ?>
            <ul class="list-unstyled mb-0">
              <?php foreach ($strengths as $s): ?><li class="mb-1"><i class="bi bi-check-circle text-success me-2"></i><?= htmlspecialchars($s) ?></li><?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card h-100" id="session-improvements-card">
        <div class="card-header"><h6 class="mb-0">Improvements</h6></div>
        <div class="card-body">
          <?php if (empty($improvements)): ?><p class="text-muted mb-0">No improvements recorded.</p><?php else: ?>
            <ul class="list-unstyled mb-0">
              <?php foreach ($improvements as $i): ?><li class="mb-1"><i class="bi bi-arrow-up-circle text-warning me-2"></i><?= htmlspecialchars($i) ?></li><?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4" id="session-transcript-card">
    <div class="card-header"><h6 class="mb-0">Transcript</h6></div>
    <div class="card-body" style="max-height:400px; overflow-y:auto;">
      <?php if (empty($transcript)): ?>
        <p class="text-muted mb-0">Transcript not available.</p>
      <?php else: ?>
        <?php foreach ($transcript as $entry):
          $role = ($entry['role'] ?? '') === 'agent' ? 'Prospect' : 'You';
          $roleClass = ($entry['role'] ?? '') === 'agent' ? 'text-danger' : 'text-primary';
        ?>
          <p class="mb-1"><strong class="<?= $roleClass ?>"><?= htmlspecialchars($role) ?>:</strong> <?= htmlspecialchars($entry['content'] ?? '') ?></p>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php if (isManager()): ?>
    <div class="card" id="session-notes-card">
      <div class="card-header"><h6 class="mb-0">Manager Notes</h6></div>
      <div class="card-body">
        <form hx-post="/partials/sessions/review.php" hx-target="#session-notes-card">
          <input type="hidden" name="action" value="add_note">
          <input type="hidden" name="id" value="<?= $sessionId ?>">
          <textarea class="form-control mb-2" name="manager_notes" rows="3" placeholder="Add coaching notes"><?= htmlspecialchars($managerNotes) ?></textarea>
          <button class="btn btn-primary btn-sm" type="submit">Save Notes</button>
        </form>
      </div>
    </div>
  <?php endif; ?>
</div>
