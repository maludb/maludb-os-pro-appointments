<?php
require_once '/var/www/helpers/auth.php';
require_once '/var/www/models/Session.php';
require_once '/var/www/models/Prospect.php';
require_once '/var/www/models/Product.php';
requireAuth();

$orgId = currentOrgId();
$userId = currentUserId();
$sessionModel = new Session();
$prospectModel = new Prospect();
$productModel = new Product();

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$filters = [
    'search' => $_GET['search'] ?? '',
    'prospect_id' => $_GET['prospect_id'] ?? '',
    'product_id' => $_GET['product_id'] ?? '',
    'call_type' => $_GET['call_type'] ?? '',
    'score_min' => $_GET['score_min'] ?? '',
    'score_max' => $_GET['score_max'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'user_id' => $_GET['user_id'] ?? '',
];
$scope = $_GET['scope'] ?? (isManager() ? 'team' : 'mine');
if (!isManager()) $scope = 'mine';

if ($scope === 'team' && isManager()) {
    $sessions = $sessionModel->getByOrg($orgId, $filters, $limit, $offset);
    $totalCount = $sessionModel->countByOrg($orgId, $filters);
} else {
    $sessions = $sessionModel->getByUser($userId, $orgId, $filters, $limit, $offset);
    $totalCount = $sessionModel->countByUser($userId, $orgId, $filters);
}
$totalPages = max(1, (int)ceil($totalCount / $limit));

$prospects = $prospectModel->getAll($orgId, [], 500, 0);
$products = $productModel->getAll($orgId, null, null, 500, 0);

$isHx = isset($_SERVER['HTTP_HX_REQUEST']);

function scoreBadge($score) {
    if ($score === null || $score === '') return '<span class="badge bg-secondary">—</span>';
    $s = (float)$score;
    if ($s >= 7) return '<span class="badge bg-success">' . number_format($s,1) . '</span>';
    if ($s >= 5) return '<span class="badge bg-warning text-dark">' . number_format($s,1) . '</span>';
    return '<span class="badge bg-danger">' . number_format($s,1) . '</span>';
}
function durationFmt($ms) {
    $sec = (int)($ms/1000);
    $m = intdiv($sec, 60);
    $s = $sec % 60;
    return $m . 'm ' . $s . 's';
}

if ($isHx) {
    if (empty($sessions)) {
        echo '<tr><td colspan="8" class="text-center text-muted">No sessions match your filters.</td></tr>';
    } else {
        foreach ($sessions as $sess) {
            echo '<tr id="session-row-' . $sess['id'] . '">';
            echo '<td>' . htmlspecialchars(date('M j, Y g:i A', strtotime($sess['created_at']))) . '</td>';
            if ($scope === 'team' && isManager()) {
                echo '<td>' . htmlspecialchars(($sess['rep_first_name'] ?? '') . ' ' . ($sess['rep_last_name'] ?? '')) . '</td>';
            }
            echo '<td>' . htmlspecialchars($sess['prospect_company'] ?? '') . '<br><small class="text-muted">' . htmlspecialchars(($sess['prospect_name'] ?? '') . ' • ' . ($sess['prospect_role'] ?? '')) . '</small></td>';
            echo '<td>' . htmlspecialchars($sess['product_name'] ?? '') . '</td>';
            echo '<td><span class="badge bg-light text-dark">' . htmlspecialchars(str_replace('_',' ', $sess['call_type'] ?? '')) . '</span></td>';
            echo '<td>' . scoreBadge($sess['quality_score']) . '</td>';
            echo '<td>' . durationFmt($sess['duration_ms'] ?? 0) . '</td>';
            echo '<td class="text-end"><a href="#" class="btn btn-sm btn-outline-primary" hx-get="/partials/sessions/review.php?id=' . $sess['id'] . '" hx-target="#page-content">View</a></td>';
            echo '</tr>';
        }
    }
    return;
}
?>

<div class="container-fluid" id="session-history-page">
  <div class="d-flex justify-content-between align-items-center mb-3" id="session-history-header">
    <div>
      <h4 class="mb-1">Session History</h4>
      <p class="text-muted mb-0">Review past practice calls and scores.</p>
    </div>
    <?php if (isManager()): ?>
      <div class="btn-group" role="group" id="session-scope-toggle">
        <a href="#" class="btn btn-<?= $scope==='mine'?'primary':'outline-primary' ?>" hx-get="/partials/sessions/list.php?scope=mine" hx-target="#page-content">My Sessions</a>
        <a href="#" class="btn btn-<?= $scope==='team'?'primary':'outline-primary' ?>" hx-get="/partials/sessions/list.php?scope=team" hx-target="#page-content">Team Sessions</a>
      </div>
    <?php endif; ?>
  </div>

  <div class="card mb-3" id="session-filter-card">
    <div class="card-body">
      <form class="row g-2" id="session-filter-form" hx-get="/partials/sessions/list.php" hx-target="#session-list-body" hx-include="#session-filter-form" hx-push-url="true">
        <input type="hidden" name="scope" value="<?= $scope ?>">
        <div class="col-md-3"><input type="text" class="form-control" name="search" placeholder="Search" value="<?= htmlspecialchars($filters['search']) ?>" hx-trigger="keyup changed delay:500ms"></div>
        <div class="col-md-2">
          <select class="form-select" name="prospect_id" hx-trigger="change">
            <option value="">All Prospects</option>
            <?php foreach ($prospects as $p): ?>
              <option value="<?= $p['id'] ?>" <?= ($filters['prospect_id']==$p['id'])?'selected':'' ?>><?= htmlspecialchars($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <select class="form-select" name="product_id" hx-trigger="change">
            <option value="">All Products</option>
            <?php foreach ($products as $prod): ?>
              <option value="<?= $prod['id'] ?>" <?= ($filters['product_id']==$prod['id'])?'selected':'' ?>><?= htmlspecialchars($prod['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <select class="form-select" name="call_type" hx-trigger="change">
            <option value="">All Call Types</option>
            <?php foreach (['cold_call','discovery','demo','objection_handling','closing','renewal'] as $ct): ?>
              <option value="<?= $ct ?>" <?= ($filters['call_type']==$ct)?'selected':'' ?>><?= htmlspecialchars(str_replace('_',' ',$ct)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-1"><input type="number" class="form-control" name="score_min" placeholder="Min" value="<?= htmlspecialchars($filters['score_min']) ?>" hx-trigger="change"></div>
        <div class="col-md-1"><input type="number" class="form-control" name="score_max" placeholder="Max" value="<?= htmlspecialchars($filters['score_max']) ?>" hx-trigger="change"></div>
        <div class="col-md-2"><input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($filters['date_from']) ?>" hx-trigger="change"></div>
        <div class="col-md-2"><input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($filters['date_to']) ?>" hx-trigger="change"></div>
        <?php if (isManager()): ?>
        <div class="col-md-2">
          <input type="number" class="form-control" name="user_id" placeholder="User ID" value="<?= htmlspecialchars($filters['user_id']) ?>" hx-trigger="change">
        </div>
        <?php endif; ?>
        <div class="col-md-2 d-grid"><a href="#" class="btn btn-outline-secondary" hx-get="/partials/sessions/list.php?scope=<?= $scope ?>" hx-target="#page-content">Clear Filters</a></div>
      </form>
    </div>
  </div>

  <div class="d-flex justify-content-between mb-2" id="session-summary">
    <div>Showing <?= count($sessions) ?> of <?= $totalCount ?> sessions</div>
  </div>

  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th>Date</th>
          <?php if ($scope === 'team' && isManager()): ?><th>Rep</th><?php endif; ?>
          <th>Prospect</th>
          <th>Product</th>
          <th>Call Type</th>
          <th>Score</th>
          <th>Duration</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody id="session-list-body">
        <?php if (empty($sessions)): ?>
          <tr><td colspan="8" class="text-center text-muted">No practice sessions yet.</td></tr>
        <?php else: ?>
          <?php foreach ($sessions as $sess): ?>
            <tr id="session-row-<?= $sess['id'] ?>">
              <td><?= htmlspecialchars(date('M j, Y g:i A', strtotime($sess['created_at']))) ?></td>
              <?php if ($scope === 'team' && isManager()): ?>
                <td><?= htmlspecialchars(($sess['rep_first_name'] ?? '') . ' ' . ($sess['rep_last_name'] ?? '')) ?></td>
              <?php endif; ?>
              <td><?= htmlspecialchars($sess['prospect_company'] ?? '') ?><br><small class="text-muted"><?= htmlspecialchars(($sess['prospect_name'] ?? '') . ' • ' . ($sess['prospect_role'] ?? '')) ?></small></td>
              <td><?= htmlspecialchars($sess['product_name'] ?? '') ?></td>
              <td><span class="badge bg-light text-dark"><?= htmlspecialchars(str_replace('_',' ', $sess['call_type'] ?? '')) ?></span></td>
              <td><?= scoreBadge($sess['quality_score']) ?></td>
              <td><?= durationFmt($sess['duration_ms'] ?? 0) ?></td>
              <td class="text-end"><a href="#" class="btn btn-sm btn-outline-primary" hx-get="/partials/sessions/review.php?id=<?= $sess['id'] ?>" hx-target="#page-content">View</a></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
    <nav aria-label="Page navigation" class="mt-3">
      <ul class="pagination justify-content-end mb-0">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <li class="page-item <?= $i === $page ? 'active' : '' ?>">
            <a class="page-link" href="#" hx-get="/partials/sessions/list.php?page=<?= $i ?>&scope=<?= $scope ?>" hx-target="#page-content"><?= $i ?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
  <?php endif; ?>
</div>
