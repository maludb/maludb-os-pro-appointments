<?php
require_once '/var/www/helpers/auth.php';
requireManager();
?>
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h4 class="mb-1">Team Dashboard</h4>
      <p class="text-muted mb-0"><?= htmlspecialchars($_SESSION['user']['org_name'] ?? '') ?> — Sales Coaching Overview</p>
    </div>
    <div class="d-flex gap-2">
      <a href="#" hx-get="/partials/sessions/setup.php" hx-target="#page-content" class="btn btn-outline-primary">
        <i class="bi bi-telephone-outbound me-1"></i> Practice Call
      </a>
      <a href="#" hx-get="/partials/prospects/list.php" hx-target="#page-content" class="btn btn-primary">
        <i class="bi bi-plus me-1"></i> New Prospect
      </a>
    </div>
  </div>
  <div class="row g-4">
    <div class="col-md-3"><div class="card h-100"><div class="card-body text-center"><i class="bi bi-people text-primary fs-3"></i><h3 class="mt-2 mb-0" id="stat-team-size">--</h3><small class="text-muted">Team Members</small></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body text-center"><i class="bi bi-telephone text-info fs-3"></i><h3 class="mt-2 mb-0" id="stat-team-sessions">--</h3><small class="text-muted">Sessions This Week</small></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body text-center"><i class="bi bi-star text-warning fs-3"></i><h3 class="mt-2 mb-0" id="stat-team-avg">--</h3><small class="text-muted">Team Avg Score</small></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body text-center"><i class="bi bi-graph-up-arrow text-success fs-3"></i><h3 class="mt-2 mb-0" id="stat-improvement">--</h3><small class="text-muted">Improvement %</small></div></div></div>
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="mb-0"><i class="bi bi-person-lines-fill me-2"></i>Rep Performance</h6>
          <a href="#" hx-get="/partials/analytics/team.php" hx-target="#page-content" class="btn btn-sm btn-outline-primary">Full Analytics</a>
        </div>
        <div class="card-body" id="rep-performance-widget" hx-get="/partials/dashboard/widgets/practice-stats.php" hx-trigger="load" hx-swap="innerHTML">
          <div class="text-center py-4"><div class="spinner-border spinner-border-sm" role="status"></div></div>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header"><h6 class="mb-0"><i class="bi bi-lightning me-2"></i>Quick Actions</h6></div>
        <div class="card-body d-grid gap-2">
          <a href="#" hx-get="/partials/prospects/list.php" hx-target="#page-content" class="btn btn-outline-primary text-start"><i class="bi bi-person-plus me-2"></i>Add Prospect</a>
          <a href="#" hx-get="/partials/products/list.php" hx-target="#page-content" class="btn btn-outline-primary text-start"><i class="bi bi-box-seam me-2"></i>Add Product</a>
          <a href="#" hx-get="/partials/scoring/categories.php" hx-target="#page-content" class="btn btn-outline-primary text-start"><i class="bi bi-clipboard-check me-2"></i>Edit Scoring Rubric</a>
          <a href="#" hx-get="/partials/team/members.php" hx-target="#page-content" class="btn btn-outline-primary text-start"><i class="bi bi-envelope-plus me-2"></i>Invite Team Member</a>
        </div>
      </div>
    </div>
    <div class="col-12">
      <div class="card">
        <div class="card-header"><h6 class="mb-0"><i class="bi bi-activity me-2"></i>Recent Team Activity</h6></div>
        <div class="card-body" id="team-activity-widget" hx-get="/partials/dashboard/widgets/recent-sessions.php?scope=team" hx-trigger="load" hx-swap="innerHTML">
          <div class="text-center py-4"><div class="spinner-border spinner-border-sm" role="status"></div></div>
        </div>
      </div>
    </div>
  </div>
</div>
