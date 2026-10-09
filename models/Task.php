<?php
/**
 * Task Model
 *
 * Handles all database operations related to tasks
 */

require_once __DIR__ . '/../config/database.php';

class Task {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get task statistics for a user
     *
     * @param int $userId User ID
     * @param int|null $teamId Optional team ID to filter by team
     * @return array Task counts by status
     */
    public function getTaskStats($userId, $teamId = null) {
        try {
            $sql = "SELECT
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                        SUM(CASE WHEN status = 'review' THEN 1 ELSE 0 END) as review,
                        SUM(CASE WHEN due_date < CURDATE() AND status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) as overdue
                    FROM tasks
                    WHERE (assigned_to = :assigned_user_id OR (user_id = :user_id AND assigned_to IS NULL))
                    AND status != 'archived'";

            if ($teamId !== null) {
                $sql .= " AND team_id = :team_id";
            }

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':assigned_user_id', $userId, PDO::PARAM_INT);

            if ($teamId !== null) {
                $stmt->bindValue(':team_id', $teamId, PDO::PARAM_INT);
            }

            $stmt->execute();
            return $stmt->fetch();

        } catch (PDOException $e) {
            error_log("Error getting task stats: " . $e->getMessage());
            return [
                'total' => 0,
                'pending' => 0,
                'in_progress' => 0,
                'completed' => 0,
                'review' => 0,
                'overdue' => 0
            ];
        }
    }

    /**
     * Get upcoming tasks (due in next N days)
     *
     * @param int $userId User ID
     * @param int $days Number of days to look ahead
     * @param int $limit Maximum number of tasks to return
     * @param int|null $teamId Optional team ID to filter
     * @return array Upcoming tasks
     */
    public function getUpcomingTasks($userId, $days = 7, $limit = 5, $teamId = null) {
        try {
            $sql = "SELECT t.*, u.first_name, u.last_name
                    FROM tasks t
                    LEFT JOIN users u ON t.assigned_to = u.id
                    WHERE (t.assigned_to = :assigned_user_id OR (t.user_id = :user_id AND t.assigned_to IS NULL))
                    AND t.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)
                    AND t.status NOT IN ('completed', 'cancelled', 'archived')";

            if ($teamId !== null) {
                $sql .= " AND t.team_id = :team_id";
            }

            $sql .= " ORDER BY t.due_date ASC, t.priority DESC LIMIT :limit";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':assigned_user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':days', $days, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

            if ($teamId !== null) {
                $stmt->bindValue(':team_id', $teamId, PDO::PARAM_INT);
            }

            $stmt->execute();
            return $stmt->fetchAll();

        } catch (PDOException $e) {
            error_log("Error getting upcoming tasks: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get recent tasks
     *
     * @param int $userId User ID
     * @param int $limit Maximum number of tasks
     * @param int|null $teamId Optional team ID to filter
     * @return array Recent tasks
     */
    public function getRecentTasks($userId, $limit = 10, $teamId = null) {
        try {
            $sql = "SELECT t.*, u.first_name, u.last_name
                    FROM tasks t
                    LEFT JOIN users u ON t.assigned_to = u.id
                    WHERE (t.assigned_to = :assigned_user_id OR (t.user_id = :user_id AND t.assigned_to IS NULL))
                    AND t.status != 'archived'";

            if ($teamId !== null) {
                $sql .= " AND t.team_id = :team_id";
            }

            $sql .= " ORDER BY t.created_at DESC LIMIT :limit";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':assigned_user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

            if ($teamId !== null) {
                $stmt->bindValue(':team_id', $teamId, PDO::PARAM_INT);
            }

            $stmt->execute();
            return $stmt->fetchAll();

        } catch (PDOException $e) {
            error_log("Error getting recent tasks: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get overdue tasks count
     *
     * @param int $userId User ID
     * @param int|null $teamId Optional team ID to filter
     * @return int Count of overdue tasks
     */
    public function getOverdueTasksCount($userId, $teamId = null) {
        try {
            $sql = "SELECT COUNT(*) as count
                    FROM tasks
                    WHERE (assigned_to = :user_id OR (user_id = :user_id AND assigned_to IS NULL))
                    AND due_date < CURDATE()
                    AND status NOT IN ('completed', 'cancelled')";

            if ($teamId !== null) {
                $sql .= " AND team_id = :team_id";
            }

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);

            if ($teamId !== null) {
                $stmt->bindValue(':team_id', $teamId, PDO::PARAM_INT);
            }

            $stmt->execute();
            $result = $stmt->fetch();
            return $result['count'] ?? 0;

        } catch (PDOException $e) {
            error_log("Error getting overdue tasks count: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get task by ID
     *
     * @param int $taskId Task ID
     * @return array|false Task data or false
     */
    public function getTaskById($taskId) {
        try {
            $sql = "SELECT t.*, u.first_name, u.last_name,
                           creator.first_name as creator_first_name,
                           creator.last_name as creator_last_name
                    FROM tasks t
                    LEFT JOIN users u ON t.assigned_to = u.id
                    LEFT JOIN users creator ON t.created_by = creator.id
                    WHERE t.id = :task_id";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':task_id', $taskId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch();

        } catch (PDOException $e) {
            error_log("Error getting task: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create new task
     *
     * @param array $data Task data
     * @return int|false Task ID or false
     */
    public function create($data) {
        try {
            $sql = "INSERT INTO tasks
                    (user_id, team_id, assigned_to, title, description, status,
                     priority, due_date, category, tags, created_by, project, created_at)
                    VALUES
                    (:user_id, :team_id, :assigned_to, :title, :description, :status,
                     :priority, :due_date, :category, :tags, :created_by, :project, NOW())";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user_id' => $data['user_id'],
                ':team_id' => $data['team_id'] ?? null,
                ':assigned_to' => $data['assigned_to'] ?? $data['user_id'],
                ':title' => $data['title'],
                ':description' => $data['description'] ?? null,
                ':status' => $data['status'] ?? 'pending',
                ':priority' => $data['priority'] ?? 'medium',
                ':due_date' => $data['due_date'] ?? null,
                ':category' => $data['category'] ?? null,
                ':tags' => $data['tags'] ?? null,
                ':created_by' => $data['created_by'] ?? $data['user_id'],
                ':project' => $data['project'] ?? null
            ]);

            return $this->db->lastInsertId();

        } catch (PDOException $e) {
            error_log("Error creating task: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update task
     *
     * @param int $taskId Task ID
     * @param array $data Task data to update
     * @return bool Success
     */
    public function update($taskId, $data) {
        try {
            $updates = [];
            $params = [':task_id' => $taskId];

            $allowedFields = ['title', 'description', 'status', 'priority', 'due_date',
                            'category', 'tags', 'assigned_to', 'team_id', 'project'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = :$field";
                    $params[":$field"] = $data[$field];
                }
            }

            if (empty($updates)) {
                return false;
            }

            // Add completed_at if status is completed
            if (isset($data['status']) && $data['status'] === 'completed') {
                $updates[] = "completed_at = NOW()";
            }

            $sql = "UPDATE tasks SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = :task_id";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);

        } catch (PDOException $e) {
            error_log("Error updating task: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Archive task (set status to archived)
     *
     * @param int $taskId Task ID
     * @param int $userId User ID (for authorization)
     * @return bool Success
     */
    public function archive($taskId, $userId) {
        try {
            $sql = "UPDATE tasks SET status = 'archived', updated_at = NOW()
                    WHERE id = :task_id AND (user_id = :user_id OR created_by = :created_by_user_id)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':task_id' => $taskId,
                ':user_id' => $userId,
                ':created_by_user_id' => $userId
            ]);

            return $stmt->rowCount() > 0;

        } catch (PDOException $e) {
            error_log("Error archiving task: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete task permanently
     *
     * @param int $taskId Task ID
     * @param int $userId User ID (for authorization)
     * @return bool Success
     */
    public function delete($taskId, $userId) {
        try {
            $sql = "DELETE FROM tasks WHERE id = :task_id AND (user_id = :user_id OR created_by = :created_by_user_id)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':task_id' => $taskId,
                ':user_id' => $userId,
                ':created_by_user_id' => $userId
            ]);

            return $stmt->rowCount() > 0;

        } catch (PDOException $e) {
            error_log("Error deleting task: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Search and filter tasks with pagination
     *
     * @param int $userId User ID
     * @param array $filters Filter parameters (query, status, priority, assignee, date_from, date_to, team_id)
     * @param array $sort Sort parameters (column, direction)
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return array Array with 'tasks' and 'total' keys
     */
    public function search($userId, $filters = [], $sort = [], $page = 1, $perPage = 20) {
        try {
            $where = ["(t.assigned_to = :assigned_user_id OR (t.user_id = :user_id AND t.assigned_to IS NULL))", "t.status != 'archived'"];
            $params = [':user_id' => $userId, ':assigned_user_id' => $userId];

            // Search query
            if (!empty($filters['query'])) {
                $where[] = "(t.title LIKE :query OR t.description LIKE :query)";
                $params[':query'] = '%' . $filters['query'] . '%';
            }

            // Status filter
            if (!empty($filters['status'])) {
                $where[] = "t.status = :status";
                $params[':status'] = $filters['status'];
            }

            // Priority filter
            if (!empty($filters['priority'])) {
                $where[] = "t.priority = :priority";
                $params[':priority'] = $filters['priority'];
            }

            // Assignee filter
            if (!empty($filters['assignee'])) {
                $where[] = "t.assigned_to = :assignee";
                $params[':assignee'] = $filters['assignee'];
            }

            // Date range filter
            if (!empty($filters['date_from'])) {
                $where[] = "t.due_date >= :date_from";
                $params[':date_from'] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $where[] = "t.due_date <= :date_to";
                $params[':date_to'] = $filters['date_to'];
            }

            // Team filter
            if (isset($filters['team_id']) && $filters['team_id'] !== null) {
                $where[] = "t.team_id = :team_id";
                $params[':team_id'] = $filters['team_id'];
            }

            // Category filter
            if (!empty($filters['category'])) {
                $where[] = "t.category = :category";
                $params[':category'] = $filters['category'];
            }

            // Overdue filter
            if (!empty($filters['overdue'])) {
                $where[] = "t.due_date < CURDATE() AND t.status NOT IN ('completed', 'cancelled')";
            }

            $whereClause = implode(' AND ', $where);

            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM tasks t WHERE " . $whereClause;
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];

            // Build sort clause
            $allowedColumns = ['title', 'status', 'priority', 'due_date', 'created_at', 'category'];
            $sortColumn = in_array($sort['column'] ?? '', $allowedColumns) ? $sort['column'] : 'created_at';
            $sortDirection = strtoupper($sort['direction'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
            $orderBy = "t.{$sortColumn} {$sortDirection}";

            // Get paginated results
            $offset = ($page - 1) * $perPage;
            $sql = "SELECT t.*,
                           u.first_name, u.last_name,
                           creator.first_name as creator_first_name,
                           creator.last_name as creator_last_name
                    FROM tasks t
                    LEFT JOIN users u ON t.assigned_to = u.id
                    LEFT JOIN users creator ON t.created_by = creator.id
                    WHERE " . $whereClause . "
                    ORDER BY " . $orderBy . "
                    LIMIT :limit OFFSET :offset";

            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();

            return [
                'tasks' => $stmt->fetchAll(),
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => ceil($total / $perPage)
            ];

        } catch (PDOException $e) {
            error_log("Error searching tasks: " . $e->getMessage());
            return [
                'tasks' => [],
                'total' => 0,
                'page' => 1,
                'perPage' => $perPage,
                'totalPages' => 0
            ];
        }
    }

    /**
     * Search and filter team tasks with pagination
     * Returns all tasks created by or assigned to any team member
     *
     * @param int $teamId Team ID
     * @param array $filters Filter parameters (query, status, priority, assignee, date_from, date_to)
     * @param array $sort Sort parameters (column, direction)
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return array Array with 'tasks' and 'total' keys
     */
    public function searchTeamTasks($teamId, $filters = [], $sort = [], $page = 1, $perPage = 20) {
        try {
            // Get all team member IDs
            $stmt = $this->db->prepare("SELECT user_id FROM team_members WHERE team_id = :team_id");
            $stmt->execute([':team_id' => $teamId]);
            $teamMemberIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($teamMemberIds)) {
                return [
                    'tasks' => [],
                    'total' => 0,
                    'page' => 1,
                    'perPage' => $perPage,
                    'totalPages' => 0
                ];
            }

            // Build WHERE clause for team tasks
            $placeholders = implode(',', array_fill(0, count($teamMemberIds), '?'));
            $where = ["(t.created_by IN ($placeholders) OR t.assigned_to IN ($placeholders))", "t.status != 'archived'"];
            $params = array_merge($teamMemberIds, $teamMemberIds);

            // Search query
            if (!empty($filters['query'])) {
                $where[] = "(t.title LIKE ? OR t.description LIKE ?)";
                $params[] = '%' . $filters['query'] . '%';
                $params[] = '%' . $filters['query'] . '%';
            }

            // Status filter
            if (!empty($filters['status'])) {
                $where[] = "t.status = ?";
                $params[] = $filters['status'];
            }

            // Priority filter
            if (!empty($filters['priority'])) {
                $where[] = "t.priority = ?";
                $params[] = $filters['priority'];
            }

            // Assignee filter
            if (!empty($filters['assignee'])) {
                $where[] = "t.assigned_to = ?";
                $params[] = $filters['assignee'];
            }

            // Date range filter
            if (!empty($filters['date_from'])) {
                $where[] = "t.due_date >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $where[] = "t.due_date <= ?";
                $params[] = $filters['date_to'];
            }

            // Category filter
            if (!empty($filters['category'])) {
                $where[] = "t.category = ?";
                $params[] = $filters['category'];
            }

            $whereClause = implode(' AND ', $where);

            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM tasks t WHERE " . $whereClause;
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];

            // Build sort clause
            $allowedColumns = ['title', 'status', 'priority', 'due_date', 'created_at', 'category'];
            $sortColumn = in_array($sort['column'] ?? '', $allowedColumns) ? $sort['column'] : 'created_at';
            $sortDirection = strtoupper($sort['direction'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
            $orderBy = "t.{$sortColumn} {$sortDirection}";

            // Get paginated results
            $offset = ($page - 1) * $perPage;
            $sql = "SELECT t.*,
                           u.first_name, u.last_name,
                           creator.first_name as creator_first_name,
                           creator.last_name as creator_last_name
                    FROM tasks t
                    LEFT JOIN users u ON t.assigned_to = u.id
                    LEFT JOIN users creator ON t.created_by = creator.id
                    WHERE " . $whereClause . "
                    ORDER BY " . $orderBy . "
                    LIMIT ? OFFSET ?";

            $stmt = $this->db->prepare($sql);
            $params[] = (int)$perPage;
            $params[] = (int)$offset;
            $stmt->execute($params);

            return [
                'tasks' => $stmt->fetchAll(),
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => ceil($total / $perPage)
            ];

        } catch (PDOException $e) {
            error_log("Error searching team tasks: " . $e->getMessage());
            return [
                'tasks' => [],
                'total' => 0,
                'page' => 1,
                'perPage' => $perPage,
                'totalPages' => 0
            ];
        }
    }

    /**
     * Update multiple tasks at once
     *
     * @param array $taskIds Array of task IDs
     * @param array $updates Fields to update
     * @param int $userId User ID for authorization
     * @return int Number of updated tasks
     */
    public function updateBulk($taskIds, $updates, $userId) {
        try {
            if (empty($taskIds) || empty($updates)) {
                return 0;
            }

            $updates_sql = [];
            $params = [];
            $allowedFields = ['status', 'priority', 'assigned_to', 'category'];

            foreach ($allowedFields as $field) {
                if (isset($updates[$field])) {
                    $updates_sql[] = "$field = :$field";
                    $params[":$field"] = $updates[$field];
                }
            }

            if (empty($updates_sql)) {
                return 0;
            }

            // Add completed_at if status is completed
            if (isset($updates['status']) && $updates['status'] === 'completed') {
                $updates_sql[] = "completed_at = NOW()";
            }

            $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
            $sql = "UPDATE tasks
                    SET " . implode(', ', $updates_sql) . ", updated_at = NOW()
                    WHERE id IN ($placeholders)
                    AND (user_id = :user_id OR created_by = :user_id)";

            $params[':user_id'] = $userId;

            $stmt = $this->db->prepare($sql);

            // Bind task IDs
            foreach ($taskIds as $index => $id) {
                $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
            }

            // Bind other params
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->execute();
            return $stmt->rowCount();

        } catch (PDOException $e) {
            error_log("Error bulk updating tasks: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Archive multiple tasks at once
     *
     * @param array $taskIds Array of task IDs
     * @param int $userId User ID for authorization
     * @return int Number of archived tasks
     */
    public function archiveBulk($taskIds, $userId) {
        try {
            if (empty($taskIds)) {
                return 0;
            }

            $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
            $sql = "UPDATE tasks SET status = 'archived', updated_at = NOW()
                    WHERE id IN ($placeholders)
                    AND (user_id = ? OR created_by = ?)";

            $stmt = $this->db->prepare($sql);

            // Bind task IDs
            foreach ($taskIds as $index => $id) {
                $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
            }

            // Bind user ID twice (for user_id and created_by)
            $stmt->bindValue(count($taskIds) + 1, $userId, PDO::PARAM_INT);
            $stmt->bindValue(count($taskIds) + 2, $userId, PDO::PARAM_INT);

            $stmt->execute();
            return $stmt->rowCount();

        } catch (PDOException $e) {
            error_log("Error bulk archiving tasks: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Delete multiple tasks permanently
     *
     * @param array $taskIds Array of task IDs
     * @param int $userId User ID for authorization
     * @return int Number of deleted tasks
     */
    public function deleteBulk($taskIds, $userId) {
        try {
            if (empty($taskIds)) {
                return 0;
            }

            $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
            $sql = "DELETE FROM tasks
                    WHERE id IN ($placeholders)
                    AND (user_id = ? OR created_by = ?)";

            $stmt = $this->db->prepare($sql);

            // Bind task IDs
            foreach ($taskIds as $index => $id) {
                $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
            }

            // Bind user ID twice (for user_id and created_by)
            $stmt->bindValue(count($taskIds) + 1, $userId, PDO::PARAM_INT);
            $stmt->bindValue(count($taskIds) + 2, $userId, PDO::PARAM_INT);

            $stmt->execute();
            return $stmt->rowCount();

        } catch (PDOException $e) {
            error_log("Error bulk deleting tasks: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get all users for assignee filter
     *
     * @param int $userId Current user ID
     * @param int|null $teamId Optional team ID to filter users
     * @return array List of users
     */
    public function getAssignableUsers($userId, $teamId = null) {
        try {
            if ($teamId !== null) {
                // Get team members
                $sql = "SELECT DISTINCT u.id, u.first_name, u.last_name
                        FROM users u
                        INNER JOIN team_members tm ON u.id = tm.user_id
                        WHERE tm.team_id = :team_id AND u.status = 'active'
                        ORDER BY u.first_name, u.last_name";

                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(':team_id', $teamId, PDO::PARAM_INT);
            } else {
                // Get all active users
                $sql = "SELECT id, first_name, last_name
                        FROM users
                        WHERE status = 'active'
                        ORDER BY first_name, last_name";

                $stmt = $this->db->prepare($sql);
            }

            $stmt->execute();
            return $stmt->fetchAll();

        } catch (PDOException $e) {
            error_log("Error getting assignable users: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get tasks for calendar view
     *
     * @param int $userId User ID
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @param array $filters Optional filters (team_id, assigned_to)
     * @return array Tasks with due dates in range
     */
    public function getTasksForCalendar($userId, $startDate, $endDate, $filters = []) {
        try {
            $where = ["(t.assigned_to = :assigned_user_id OR (t.user_id = :user_id AND t.assigned_to IS NULL))", "t.status != 'archived'"];
            $params = [':user_id' => $userId, ':assigned_user_id' => $userId];

            // Date range filter
            $where[] = "t.due_date BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $startDate;
            $params[':end_date'] = $endDate;

            // Team filter
            if (isset($filters['team_id']) && $filters['team_id'] !== null) {
                $where[] = "t.team_id = :team_id";
                $params[':team_id'] = $filters['team_id'];
            }

            // Assignee filter (can be array)
            if (!empty($filters['assigned_to'])) {
                if (is_array($filters['assigned_to'])) {
                    $placeholders = [];
                    foreach ($filters['assigned_to'] as $i => $assigneeId) {
                        $key = ":assignee_$i";
                        $placeholders[] = $key;
                        $params[$key] = $assigneeId;
                    }
                    $where[] = "t.assigned_to IN (" . implode(',', $placeholders) . ")";
                } else {
                    $where[] = "t.assigned_to = :assignee";
                    $params[':assignee'] = $filters['assigned_to'];
                }
            }

            $whereClause = implode(' AND ', $where);

            $sql = "SELECT t.*,
                           u.first_name, u.last_name
                    FROM tasks t
                    LEFT JOIN users u ON t.assigned_to = u.id
                    WHERE " . $whereClause . "
                    ORDER BY t.due_date ASC, t.priority DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll();

        } catch (PDOException $e) {
            error_log("Error getting tasks for calendar: " . $e->getMessage());
            return [];
        }
    }
}
