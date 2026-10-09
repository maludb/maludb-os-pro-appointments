<?php
require_once '/var/www/helpers/auth.php';
requireAuth();
?>
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h4 class="mb-1">Welcome back, <?= htmlspecialchars($_SESSION['user']['first_name'] ?? 'Rep') ?>!</h4>
      <p class="text-muted mb-0">Ready to sharpen your sales skills?</p>
    </div>
    <a href="#" hx-get="/partials/sessions/setup.php" hx-target="#page-content" class="btn btn-primary">
      <i class="bi bi-telephone-outbound me-1"></i> Start Practice Call
    </a>
  </div>
  <div class="row g-4">
    <div class="col-md-3"><div class="card h-100"><div class="card-body text-center"><i class="bi bi-telephone text-primary fs-3"></i><h3 class="mt-2 mb-0" id="stat-total-sessions">--</h3><small class="text-muted">Total Sessions</small></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body text-center"><i class="bi bi-star text-warning fs-3"></i><h3 class="mt-2 mb-0" id="stat-avg-score">--</h3><small class="text-muted">Avg Score</small></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body text-center"><i class="bi bi-graph-up-arrow text-success fs-3"></i><h3 class="mt-2 mb-0" id="stat-best-score">--</h3><small class="text-muted">Best Score</small></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body text-center"><i class="bi bi-fire text-danger fs-3"></i><h3 class="mt-2 mb-0" id="stat-streak">--</h3><small class="text-muted">Day Streak</small></div></div></div>
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Sessions</h6>
          <a href="#" hx-get="/partials/sessions/list.php" hx-target="#page-content" class="btn btn-sm btn-outline-primary">View All</a>
        </div>
        <div class="card-body" id="recent-sessions-widget" hx-get="/partials/dashboard/widgets/recent-sessions.php" hx-trigger="load" hx-swap="innerHTML">
          <div class="text-center py-4"><div class="spinner-border spinner-border-sm" role="status"></div><p class="text-muted mt-2 mb-0">Loading sessions...</p></div>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header"><h6 class="mb-0"><i class="bi bi-trophy me-2"></i>Leaderboard</h6></div>
        <div class="card-body" id="leaderboard-widget" hx-get="/partials/dashboard/widgets/leaderboard.php" hx-trigger="load" hx-swap="innerHTML">
          <div class="text-center py-4"><div class="spinner-border spinner-border-sm" role="status"></div></div>
        </div>
      </div>
    </div>
    <div class="col-12">
      <div class="card">
        <div class="card-header"><h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Score Trends</h6></div>
        <div class="card-body" id="score-trends-widget" hx-get="/partials/dashboard/widgets/score-trends.php" hx-trigger="load" hx-swap="innerHTML">
          <canvas id="score-trends-chart" height="100"></canvas>
          <p class="text-muted text-center mt-2" id="chart-placeholder">Practice sessions will appear here as you complete them.</p>
        </div>
      </div>
    </div>
  </div>
</div>
