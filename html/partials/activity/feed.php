<?php
/**
 * Activity Feed Polling Endpoint
 *
 * Returns activity items for polling updates (HTMX hx-trigger="every 30s")
 * Only returns new items since last update
 */

session_start();
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/date.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../models/Activity.php';

check_auth();
$user = get_user();

// Initialize model
$activityModel = new Activity();

// Get filter parameters
$actionType = $_GET['action_type'] ?? null;
$userId = $_GET['user_id'] ?? null;
$dateRange = $_GET['date_range'] ?? '7';
$page = max(1, (int)($_GET['page'] ?? 1));
$itemsPerPage = (int)($_GET['items_per_page'] ?? 10);

// Get team ID if in team context
$selectedTeamId = $_SESSION['selected_team_id'] ?? null;

// Build query
$offset = ($page - 1) * $itemsPerPage;

try {
    $db = Database::getInstance()->getConnection();

    $sql = "SELECT a.*, u.first_name, u.last_name, u.username,
                   t.title as task_title, t.id as task_id
            FROM activities a
            LEFT JOIN users u ON a.user_id = u.id
            LEFT JOIN tasks t ON a.target_type = 'task' AND a.target_id = t.id
            WHERE 1=1";

    $params = [];

    // Filter by action type
    if (!empty($actionType)) {
        $sql .= " AND a.action = :action_type";
        $params[':action_type'] = $actionType;
    }

    // Filter by user
    if (!empty($userId)) {
        $sql .= " AND a.user_id = :user_id";
        $params[':user_id'] = (int)$userId;
    }

    // Filter by date range
    if (!empty($dateRange)) {
        $sql .= " AND a.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)";
        $params[':days'] = (int)$dateRange;
    }

    // Filter by team if applicable
    if ($selectedTeamId) {
        $sql .= " AND EXISTS (
            SELECT 1 FROM tasks
            WHERE tasks.id = a.target_id
            AND tasks.team_id = :team_id
        )";
        $params[':team_id'] = $selectedTeamId;
    }

    // Add ordering and pagination
    $sql .= " ORDER BY a.created_at DESC LIMIT :limit OFFSET :offset";
    $params[':limit'] = $itemsPerPage;
    $params[':offset'] = $offset;

    // Execute query
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

} catch (Exception $e) {
    error_log("Error loading activity feed: " . $e->getMessage());
    $activities = [];
}

// Action type labels
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

<?php if (empty($activities)): ?>
  <div class="text-center py-5" id="no-activities">
    <i class="feather-inbox fs-1 text-muted mb-3 d-block"></i>
    <h5 class="text-muted">No Activities Found</h5>
    <p class="text-muted small">Try adjusting your filters</p>
  </div>
<?php else: ?>
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
<?php endif; ?>
