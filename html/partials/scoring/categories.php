<?php
require_once '/var/www/helpers/auth.php';
require_once '/var/www/models/ScoringCategory.php';
require_once '/var/www/models/ScoringRubric.php';
requireManager();

$orgId = currentOrgId();
$catModel = new ScoringCategory();
$rubricModel = new ScoringRubric();

$action = $_POST['action'] ?? '';

if ($action === 'create_category') {
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $weight = (float)($_POST['weight'] ?? 1.0);
    if ($name !== '') {
        $existing = $catModel->getAll($orgId);
        $sort = count($existing);
        $catModel->create([
            'name' => $name,
            'description' => $desc,
            'weight' => $weight,
            'sort_order' => $sort,
        ]);
    }
} elseif ($action === 'update_category') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $weight = (float)($_POST['weight'] ?? 1.0);
    if ($id > 0 && $name !== '') {
        $catModel->update($id, [
            'name' => $name,
            'description' => $desc,
            'weight' => $weight,
        ], $orgId);
    }
} elseif ($action === 'delete_category') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        // delete rubrics first
        foreach ($rubricModel->getAllForCategory($id, $orgId) as $r) {
            $rubricModel->delete((int)$r['id'], $orgId);
        }
        $catModel->delete($id, $orgId);
    }
} elseif ($action === 'reorder') {
    $ids = $_POST['ids'] ?? '';
    if (is_string($ids)) {
        $ids = array_filter(explode(',', $ids), fn($v) => $v !== '');
    }
    if (is_array($ids)) {
        $catModel->reorder($ids, $orgId);
    }
} elseif ($action === 'create_rubric') {
    $catId = (int)($_POST['category_id'] ?? 0);
    $score = (int)($_POST['score_level'] ?? 5);
    $name = trim($_POST['name'] ?? '');
    $criteria = trim($_POST['criteria'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if ($catId > 0 && $name !== '') {
        $rubricModel->create([
            'category_id' => $catId,
            'score_level' => $score,
            'name' => $name,
            'criteria' => $criteria,
            'description' => $desc,
        ]);
    }
} elseif ($action === 'update_rubric') {
    $id = (int)($_POST['id'] ?? 0);
    $score = (int)($_POST['score_level'] ?? 5);
    $name = trim($_POST['name'] ?? '');
    $criteria = trim($_POST['criteria'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if ($id > 0 && $name !== '') {
        $rubricModel->update($id, [
            'score_level' => $score,
            'name' => $name,
            'criteria' => $criteria,
            'description' => $desc,
        ], $orgId);
    }
} elseif ($action === 'delete_rubric') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $rubricModel->delete($id, $orgId);
    }
}

$categories = $catModel->getAll($orgId);
if (empty($categories)) {
    // clone defaults and reload
    $catModel->cloneDefaults($orgId, currentUserId());
    $categories = $catModel->getAll($orgId);
}
$rubricsByCat = [];
foreach ($categories as $cat) {
    $rubricsByCat[$cat['id']] = $rubricModel->getAllForCategory((int)$cat['id'], $orgId);
}
?>

<div class="container-fluid" id="scoring-page">
  <div class="d-flex justify-content-between align-items-center mb-4" id="scoring-header">
    <div id="scoring-title-wrap">
      <h4 class="mb-1" id="scoring-title">Scoring Configuration</h4>
      <p class="text-muted mb-0" id="scoring-subtitle">Define how practice sessions are evaluated. Categories and weights determine the overall score.</p>
    </div>
    <div id="scoring-actions">
      <a href="#" class="btn btn-outline-secondary" id="scoring-clone-defaults"
         hx-post="/partials/scoring/categories.php" hx-vals='{"action":"clone_defaults"}' hx-target="#page-content" hx-swap="outerHTML">Reload</a>
    </div>
  </div>

  <div class="card mb-3" id="scoring-add-card">
    <div class="card-body" id="scoring-add-body">
      <form class="row g-3 align-items-end" id="scoring-add-form"
            hx-post="/partials/scoring/categories.php"
            hx-target="#page-content" hx-swap="outerHTML">
        <input type="hidden" name="action" value="create_category">
        <div class="col-md-4" id="scoring-add-name-col">
          <label class="form-label" for="scoring-add-name">Category Name</label>
          <input type="text" class="form-control" id="scoring-add-name" name="name" required>
        </div>
        <div class="col-md-4" id="scoring-add-desc-col">
          <label class="form-label" for="scoring-add-desc">Description</label>
          <input type="text" class="form-control" id="scoring-add-desc" name="description" placeholder="How this is evaluated">
        </div>
        <div class="col-md-2" id="scoring-add-weight-col">
          <label class="form-label" for="scoring-add-weight">Weight (x)</label>
          <input type="number" step="0.1" min="0.1" class="form-control" id="scoring-add-weight" name="weight" value="1.0">
        </div>
        <div class="col-md-2 d-grid" id="scoring-add-submit-col">
          <button type="submit" class="btn btn-primary" id="scoring-add-submit">Add Category</button>
        </div>
      </form>
    </div>
  </div>

  <div class="row g-3" id="scoring-category-row">
    <?php foreach ($categories as $idx => $cat): ?>
      <div class="col-12" id="scoring-cat-col-<?= $cat['id'] ?>">
        <div class="card" id="scoring-cat-card-<?= $cat['id'] ?>">
          <div class="card-header d-flex justify-content-between align-items-start" id="scoring-cat-header-<?= $cat['id'] ?>">
            <div id="scoring-cat-title-wrap-<?= $cat['id'] ?>">
              <h6 class="mb-1" id="scoring-cat-title-<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></h6>
              <p class="text-muted small mb-0" id="scoring-cat-desc-<?= $cat['id'] ?>"><?= htmlspecialchars($cat['description'] ?? '') ?></p>
            </div>
            <div class="d-flex gap-2 align-items-center" id="scoring-cat-actions-<?= $cat['id'] ?>">
              <span class="badge bg-light text-dark" id="scoring-cat-weight-<?= $cat['id'] ?>"><?= htmlspecialchars($cat['weight']) ?>x</span>
              <div class="btn-group btn-group-sm" role="group" id="scoring-cat-buttons-<?= $cat['id'] ?>">
                <button class="btn btn-outline-secondary" id="scoring-cat-edit-<?= $cat['id'] ?>"
                        hx-post="/partials/scoring/categories.php"
                        hx-vals='{"action":"update_category","id":"<?= $cat['id'] ?>","name":"<?= htmlspecialchars($cat['name'], ENT_QUOTES) ?>","description":"<?= htmlspecialchars($cat['description'] ?? '', ENT_QUOTES) ?>","weight":"<?= $cat['weight'] ?>"}'
                        hx-target="#page-content" hx-swap="outerHTML">Save</button>
                <button class="btn btn-outline-danger" id="scoring-cat-delete-<?= $cat['id'] ?>"
                        hx-post="/partials/scoring/categories.php"
                        hx-vals='{"action":"delete_category","id":"<?= $cat['id'] ?>"}'
                        hx-confirm="Delete this category?" hx-target="#page-content" hx-swap="outerHTML">Delete</button>
              </div>
            </div>
          </div>
          <div class="card-body" id="scoring-cat-body-<?= $cat['id'] ?>">
            <form class="row g-3 mb-3" id="scoring-cat-inline-form-<?= $cat['id'] ?>"
                  hx-post="/partials/scoring/categories.php" hx-target="#page-content" hx-swap="outerHTML">
              <input type="hidden" name="action" value="update_category">
              <input type="hidden" name="id" value="<?= $cat['id'] ?>">
              <div class="col-md-4" id="scoring-edit-name-col-<?= $cat['id'] ?>">
                <label class="form-label" for="edit-name-<?= $cat['id'] ?>">Name</label>
                <input type="text" class="form-control" id="edit-name-<?= $cat['id'] ?>" name="name" value="<?= htmlspecialchars($cat['name']) ?>" required>
              </div>
              <div class="col-md-5" id="scoring-edit-desc-col-<?= $cat['id'] ?>">
                <label class="form-label" for="edit-desc-<?= $cat['id'] ?>">Description</label>
                <input type="text" class="form-control" id="edit-desc-<?= $cat['id'] ?>" name="description" value="<?= htmlspecialchars($cat['description'] ?? '') ?>">
              </div>
              <div class="col-md-2" id="scoring-edit-weight-col-<?= $cat['id'] ?>">
                <label class="form-label" for="edit-weight-<?= $cat['id'] ?>">Weight</label>
                <input type="number" step="0.1" min="0.1" class="form-control" id="edit-weight-<?= $cat['id'] ?>" name="weight" value="<?= htmlspecialchars($cat['weight']) ?>">
              </div>
              <div class="col-md-1 d-grid" id="scoring-edit-submit-col-<?= $cat['id'] ?>">
                <button class="btn btn-primary mt-4" type="submit">Save</button>
              </div>
            </form>

            <div class="table-responsive" id="scoring-rubric-table-wrap-<?= $cat['id'] ?>">
              <table class="table table-sm align-middle" id="scoring-rubric-table-<?= $cat['id'] ?>">
                <thead>
                  <tr id="scoring-rubric-head-<?= $cat['id'] ?>">
                    <th>Score</th>
                    <th>Name</th>
                    <th>Criteria</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody id="scoring-rubric-body-<?= $cat['id'] ?>">
                  <?php if (empty($rubricsByCat[$cat['id']])): ?>
                    <tr id="scoring-rubric-empty-<?= $cat['id'] ?>">
                      <td colspan="4" class="text-muted">No criteria yet.</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($rubricsByCat[$cat['id']] as $rubric): ?>
                      <tr id="scoring-rubric-row-<?= $rubric['id'] ?>">
                        <td id="scoring-rubric-score-<?= $rubric['id'] ?>"><?= (int)$rubric['score_level'] ?></td>
                        <td id="scoring-rubric-name-<?= $rubric['id'] ?>"><?= htmlspecialchars($rubric['name']) ?></td>
                        <td id="scoring-rubric-criteria-<?= $rubric['id'] ?>"><?= htmlspecialchars($rubric['criteria'] ?? $rubric['description'] ?? '') ?></td>
                        <td class="text-end" id="scoring-rubric-actions-<?= $rubric['id'] ?>">
                          <div class="btn-group btn-group-sm" role="group">
                            <button class="btn btn-outline-secondary" id="scoring-rubric-edit-<?= $rubric['id'] ?>"
                                    hx-post="/partials/scoring/categories.php"
                                    hx-vals='{"action":"update_rubric","id":"<?= $rubric['id'] ?>","score_level":"<?= $rubric['score_level'] ?>","name":"<?= htmlspecialchars($rubric['name'], ENT_QUOTES) ?>","criteria":"<?= htmlspecialchars($rubric['criteria'] ?? '', ENT_QUOTES) ?>","description":"<?= htmlspecialchars($rubric['description'] ?? '', ENT_QUOTES) ?>"}'
                                    hx-target="#page-content" hx-swap="outerHTML">Save</button>
                            <button class="btn btn-outline-danger" id="scoring-rubric-delete-<?= $rubric['id'] ?>"
                                    hx-post="/partials/scoring/categories.php"
                                    hx-vals='{"action":"delete_rubric","id":"<?= $rubric['id'] ?>"}'
                                    hx-confirm="Delete this criteria?"
                                    hx-target="#page-content" hx-swap="outerHTML">Delete</button>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

            <form class="row g-2 align-items-end" id="scoring-rubric-add-form-<?= $cat['id'] ?>"
                  hx-post="/partials/scoring/categories.php" hx-target="#page-content" hx-swap="outerHTML">
              <input type="hidden" name="action" value="create_rubric">
              <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
              <div class="col-md-2" id="rubric-score-col-<?= $cat['id'] ?>">
                <label class="form-label" for="rubric-score-<?= $cat['id'] ?>">Score</label>
                <input type="number" class="form-control" id="rubric-score-<?= $cat['id'] ?>" name="score_level" min="1" max="10" value="5">
              </div>
              <div class="col-md-3" id="rubric-name-col-<?= $cat['id'] ?>">
                <label class="form-label" for="rubric-name-<?= $cat['id'] ?>">Name</label>
                <input type="text" class="form-control" id="rubric-name-<?= $cat['id'] ?>" name="name" required>
              </div>
              <div class="col-md-6" id="rubric-criteria-col-<?= $cat['id'] ?>">
                <label class="form-label" for="rubric-criteria-<?= $cat['id'] ?>">Criteria</label>
                <input type="text" class="form-control" id="rubric-criteria-<?= $cat['id'] ?>" name="criteria" placeholder="What this score means">
              </div>
              <div class="col-md-1 d-grid" id="rubric-add-btn-col-<?= $cat['id'] ?>">
                <button class="btn btn-outline-primary" type="submit">Add</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
