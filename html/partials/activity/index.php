<?php
/**
 * Activity Feed Main Page
 *
 * Displays all recent activities with filtering, pagination, and real-time polling
 */

session_start();
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/date.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../models/Activity.php';
require_once __DIR__ . '/../../../models/User.php';

check_auth();
$user = get_user();

// Initialize models
$activityModel = new Activity();
$userModel = new User();

// Get filter parameters
$actionType = $_GET['action_type'] ?? null;
$userId = $_GET['user_id'] ?? null;
$dateRange = $_GET['date_range'] ?? '7'; // days
$page = max(1, (int)($_GET['page'] ?? 1));
$itemsPerPage = (int)($_GET['items_per_page'] ?? 10);

// Get team ID if in team context
$selectedTeamId = $_SESSION['selected_team_id'] ?? null;

// Build query
$offset = ($page - 1) * $itemsPerPage;

try {
    $baseSql = "SELECT a.*, u.first_name, u.last_name, u.username,
                       t.title as task_title, t.id as task_id
                FROM activities a
                LEFT JOIN users u ON a.user_id = u.id
                LEFT JOIN tasks t ON a.target_type = 'task' AND a.target_id = t.id
                WHERE 1=1";

    $params = [];

    // Filter by action type
    if (!empty($actionType)) {
        $baseSql .= " AND a.action = :action_type";
        $params[':action_type'] = $actionType;
    }

    // Filter by user
    if (!empty($userId)) {
        $baseSql .= " AND a.user_id = :user_id";
        $params[':user_id'] = (int)$userId;
    }

    // Filter by date range
    if (!empty($dateRange)) {
        $baseSql .= " AND a.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)";
        $params[':days'] = (int)$dateRange;
    }

    // Filter by team if applicable
    if ($selectedTeamId) {
        $baseSql .= " AND EXISTS (
            SELECT 1 FROM tasks
            WHERE tasks.id = a.target_id
            AND tasks.team_id = :team_id
        )";
        $params[':team_id'] = $selectedTeamId;
    }

    // Get total count
    $db = Database::getInstance()->getConnection();
    $countSql = "SELECT COUNT(*) as count FROM activities a WHERE 1=1";
    if (!empty($actionType)) {
        $countSql .= " AND a.action = :action_type";
    }
    if (!empty($userId)) {
        $countSql .= " AND a.user_id = :user_id";
    }
    if (!empty($dateRange)) {
        $countSql .= " AND a.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)";
    }
    if ($selectedTeamId) {
        $countSql .= " AND EXISTS (
            SELECT 1 FROM tasks
            WHERE tasks.id = a.target_id
            AND tasks.team_id = :team_id
        )";
    }

    $countStmt = $db->prepare($countSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalCount = $countStmt->fetch()['count'] ?? 0;
    $totalPages = ceil($totalCount / $itemsPerPage);

    // Get activities
    $sql = $baseSql . " ORDER BY a.created_at DESC LIMIT :limit OFFSET :offset";
    $params[':limit'] = $itemsPerPage;
    $params[':offset'] = $offset;

    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        if ($key === ':limit' || $key === ':offset') {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value);
        }
    }

    $stmt->execute();
    $activities = $stmt->fetchAll();

    // Add icon and color info
    foreach ($activities as &$activity) {
        $activity['icon'] = $activityModel->getActivityIcon($activity['action']);
        $activity['color'] = $activityModel->getActivityColor($activity['action']);
    }

    // Get list of users for filter dropdown
    $allUsers = $userModel->getAllUsers();

} catch (Exception $e) {
    error_log("Error loading activity feed: " . $e->getMessage());
    $activities = [];
    $totalCount = 0;
    $totalPages = 1;
    $allUsers = [];
}

// Get action type labels
$actionTypes = [
    'task_created' => 'Task Created',
    'task_updated' => 'Task Updated',
    'task_completed' => 'Task Completed',
    'task_assigned' => 'Task Assigned',
    'task_status_changed' => 'Status Changed',
    'task_deleted' => 'Task Deleted',
    'comment_added' => 'Comment Added'
];
?>

<div id="activity-feed-container">
  <!-- Activity Feed Header -->
  <div class="row mt-2 mx-2 mb-0">
    <div class="col-12">
      <div class="card bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="card-body">
          <h4 id="activity-feed-title" class="mb-1 text-dark"><i class="feather-activity me-2"></i>Activity Feed</h4>
          <p class="mb-0 text-dark" id="activity-count"><?php echo $totalCount; ?> activities</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Filters Section -->
  <div class="card mb-4 shadow-sm" id="activity-filters-card">
    <div class="card-body">
      <div class="row g-3">
        <!-- Action Type Filter -->
        <div class="col-md-4">
          <label for="filter-action-type" class="form-label">
            <i class="feather-filter me-2"></i>Action Type
          </label>
          <select class="form-select form-select-sm" id="filter-action-type"
                  hx-get="/partials/activity/index.php"
                  hx-target="#activity-feed-container"
                  hx-include="[name='action_type'], [name='user_id'], [name='date_range'], [name='items_per_page']"
                  hx-swap="outerHTML">
            <option value="">All Activities</option>
            <?php foreach ($actionTypes as $key => $label): ?>
            <option value="<?php echo $key; ?>" <?php echo $actionType === $key ? 'selected' : ''; ?>>
              <?php echo $label; ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- User Filter -->
        <div class="col-md-4">
          <label for="filter-user-id" class="form-label">
            <i class="feather-user me-2"></i>By User
          </label>
          <select class="form-select form-select-sm" id="filter-user-id"
                  hx-get="/partials/activity/index.php"
                  hx-target="#activity-feed-container"
                  hx-include="[name='action_type'], [name='user_id'], [name='date_range'], [name='items_per_page']"
                  hx-swap="outerHTML">
            <option value="">All Users</option>
            <?php foreach ($allUsers as $u): ?>
            <option value="<?php echo $u['id']; ?>" <?php echo $userId === (string)$u['id'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Date Range Filter -->
        <div class="col-md-4">
          <label for="filter-date-range" class="form-label">
            <i class="feather-calendar me-2"></i>Date Range
          </label>
          <select class="form-select form-select-sm" id="filter-date-range"
                  hx-get="/partials/activity/index.php"
                  hx-target="#activity-feed-container"
                  hx-include="[name='action_type'], [name='user_id'], [name='date_range'], [name='items_per_page']"
                  hx-swap="outerHTML">
            <option value="1" <?php echo $dateRange === '1' ? 'selected' : ''; ?>>Last 24 hours</option>
            <option value="7" <?php echo $dateRange === '7' ? 'selected' : ''; ?>>Last 7 days</option>
            <option value="30" <?php echo $dateRange === '30' ? 'selected' : ''; ?>>Last 30 days</option>
            <option value="90" <?php echo $dateRange === '90' ? 'selected' : ''; ?>>Last 90 days</option>
            <option value="">All Time</option>
          </select>
        </div>
      </div>

      <!-- Items Per Page -->
      <div class="row g-3 mt-1">
        <div class="col-md-4">
          <label for="items-per-page" class="form-label">
            <i class="feather-list me-2"></i>Items Per Page
          </label>
          <select class="form-select form-select-sm" id="items-per-page"
                  hx-get="/partials/activity/index.php"
                  hx-target="#activity-feed-container"
                  hx-include="[name='action_type'], [name='user_id'], [name='date_range'], [name='items_per_page']"
                  hx-swap="outerHTML">
            <option value="5" <?php echo $itemsPerPage === 5 ? 'selected' : ''; ?>>5</option>
            <option value="10" <?php echo $itemsPerPage === 10 ? 'selected' : ''; ?>>10</option>
            <option value="25" <?php echo $itemsPerPage === 25 ? 'selected' : ''; ?>>25</option>
            <option value="50" <?php echo $itemsPerPage === 50 ? 'selected' : ''; ?>>50</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  <!-- Activity Timeline Feed -->
  <div id="activity-timeline"
       hx-get="/partials/activity/feed.php?action_type=<?php echo urlencode($actionType ?? ''); ?>&user_id=<?php echo urlencode($userId ?? ''); ?>&date_range=<?php echo urlencode($dateRange ?? ''); ?>&items_per_page=<?php echo $itemsPerPage; ?>&page=<?php echo $page; ?>"
       hx-trigger="load, refreshActivityFeed from:body, every 30s"
       hx-swap="innerHTML"
       hx-target="#activity-items">
    <!-- Feed will load here -->
  </div>

  <!-- Activity Items Container -->
  <div id="activity-items">
    <?php if (empty($activities)): ?>
      <div class="text-center py-5" id="no-activities">
        <i class="feather-inbox fs-1 text-muted mb-3 d-block"></i>
        <h5 class="text-muted">No Activities Found</h5>
        <p class="text-muted small">Try adjusting your filters</p>
      </div>
    <?php else: ?>
      <!-- Timeline -->
      <div class="timeline" id="activity-items-list">
        <?php foreach ($activities as $activity): ?>
          <div class="timeline-item" id="activity-item-<?php echo $activity['id']; ?>">
            <!-- Timeline Node -->
            <div class="timeline-node bg-<?php echo $activity['color']; ?>" title="<?php echo htmlspecialchars($actionTypes[$activity['action']] ?? $activity['action']); ?>">
              <i class="bi <?php echo $activity['icon']; ?>"></i>
            </div>

            <!-- Timeline Content -->
            <div class="timeline-content card shadow-sm h-100">
              <div class="card-body">
                <!-- User Info -->
                <div class="d-flex align-items-center mb-2">
                  <div class="rounded-circle me-2 bg-light d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                    <i class="feather-user text-muted"></i>
                  </div>
                  <div>
                    <p class="mb-0 fw-bold">
                      <?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?>
                    </p>
                    <small class="text-muted">
                      <i class="feather-clock me-1"></i>
                      <?php echo timeAgo($activity['created_at']); ?>
                    </small>
                  </div>
                </div>

                <!-- Activity Description -->
                <div class="mb-2">
                  <p class="mb-1">
                    <span class="text-<?php echo $activity['color']; ?> fw-bold">
                      <?php echo htmlspecialchars($actionTypes[$activity['action']] ?? ucfirst(str_replace('_', ' ', $activity['action']))); ?>
                    </span>
                  </p>
                  <?php if ($activity['task_title']): ?>
                    <p class="mb-1">
                      <i class="feather-check-circle text-primary me-1"></i>
                      <a href="#"
                         hx-get="/partials/tasks/view.php?id=<?php echo $activity['task_id']; ?>"
                         hx-target="#page-content"
                         class="text-decoration-none">
                        <?php echo htmlspecialchars($activity['task_title']); ?>
                      </a>
                    </p>
                  <?php endif; ?>
                  <?php if ($activity['description']): ?>
                    <p class="text-muted small mb-0">
                      <?php echo htmlspecialchars($activity['description']); ?>
                    </p>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Pagination -->
      <nav aria-label="Activity feed pagination" id="activity-pagination" class="mt-4">
        <ul class="pagination justify-content-center">
          <!-- Previous -->
          <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
            <a class="page-link" href="#"
               <?php if ($page > 1): ?>
               hx-get="/partials/activity/index.php?page=<?php echo $page - 1; ?>&action_type=<?php echo urlencode($actionType ?? ''); ?>&user_id=<?php echo urlencode($userId ?? ''); ?>&date_range=<?php echo urlencode($dateRange ?? ''); ?>&items_per_page=<?php echo $itemsPerPage; ?>"
               hx-target="#activity-feed-container"
               hx-swap="outerHTML"
               <?php endif; ?>>
              <i class="feather-chevron-left"></i> Previous
            </a>
          </li>

          <!-- Page Numbers -->
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
            <a class="page-link" href="#"
               hx-get="/partials/activity/index.php?page=<?php echo $i; ?>&action_type=<?php echo urlencode($actionType ?? ''); ?>&user_id=<?php echo urlencode($userId ?? ''); ?>&date_range=<?php echo urlencode($dateRange ?? ''); ?>&items_per_page=<?php echo $itemsPerPage; ?>"
               hx-target="#activity-feed-container"
               hx-swap="outerHTML">
              <?php echo $i; ?>
            </a>
          </li>
          <?php endfor; ?>

          <!-- Next -->
          <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
            <a class="page-link" href="#"
               <?php if ($page < $totalPages): ?>
               hx-get="/partials/activity/index.php?page=<?php echo $page + 1; ?>&action_type=<?php echo urlencode($actionType ?? ''); ?>&user_id=<?php echo urlencode($userId ?? ''); ?>&date_range=<?php echo urlencode($dateRange ?? ''); ?>&items_per_page=<?php echo $itemsPerPage; ?>"
               hx-target="#activity-feed-container"
               hx-swap="outerHTML"
               <?php endif; ?>>
              Next <i class="feather-chevron-right"></i>
            </a>
          </li>
        </ul>
      </nav>
    <?php endif; ?>
  </div>
</div>

<!-- Activity Feed Styles -->
<style>
.timeline {
  position: relative;
  padding: 20px 0;
}

.timeline-item {
  display: flex;
  gap: 20px;
  margin-bottom: 30px;
  position: relative;
}

.timeline-item:not(:last-child)::before {
  content: '';
  position: absolute;
  left: 15px;
  top: 60px;
  width: 2px;
  height: calc(100% + 10px);
  background-color: var(--bs-border-color);
}

.timeline-node {
  min-width: 50px;
  width: 50px;
  height: 50px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 20px;
  flex-shrink: 0;
  position: relative;
  z-index: 1;
}

.timeline-content {
  flex-grow: 1;
  border: none;
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.timeline-content:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
}

.timeline-content .card-body {
  padding: 1.25rem;
}

.timeline-content a {
  color: var(--bs-primary);
  transition: color 0.2s ease;
}

.timeline-content a:hover {
  color: var(--bs-primary);
  text-decoration: underline;
}
</style>
