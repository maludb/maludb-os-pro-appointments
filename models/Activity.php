<?php
/**
 * Activity Model
 *
 * Handles all database operations related to user activities
 */

require_once __DIR__ . '/../config/database.php';

class Activity {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get recent activities for a user
     *
     * @param int $userId User ID
     * @param int $limit Maximum number of activities
     * @param int|null $teamId Optional team ID to filter
     * @return array Recent activities
     */
    public function getRecentActivities($userId, $limit = 10, $teamId = null) {
        try {
            $sql = "SELECT a.*, u.first_name, u.last_name, u.username,
                           t.title as task_title
                    FROM activities a
                    LEFT JOIN users u ON a.user_id = u.id
                    LEFT JOIN tasks t ON a.target_type = 'task' AND a.target_id = t.id
                    WHERE a.user_id = :user_id";

            if ($teamId !== null) {
                $sql .= " AND EXISTS (
                    SELECT 1 FROM tasks
                    WHERE tasks.id = a.target_id
                    AND tasks.team_id = :team_id
                )";
            }

            $sql .= " ORDER BY a.created_at DESC LIMIT :limit";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

            if ($teamId !== null) {
                $stmt->bindValue(':team_id', $teamId, PDO::PARAM_INT);
            }

            $stmt->execute();
            return $stmt->fetchAll();

        } catch (PDOException $e) {
            error_log("Error getting recent activities: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get team activities
     *
     * @param int $teamId Team ID
     * @param int $limit Maximum number of activities
     * @return array Team activities
     */
    public function getTeamActivities($teamId, $limit = 10) {
        try {
            $sql = "SELECT a.*, u.first_name, u.last_name, u.username,
                           t.title as task_title
                    FROM activities a
                    LEFT JOIN users u ON a.user_id = u.id
                    LEFT JOIN tasks t ON a.target_type = 'task' AND a.target_id = t.id
                    WHERE a.target_type = 'task'
                    AND EXISTS (
                        SELECT 1 FROM tasks
                        WHERE tasks.id = a.target_id
                        AND tasks.team_id = :team_id
                    )
                    ORDER BY a.created_at DESC
                    LIMIT :limit";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':team_id', $teamId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();

        } catch (PDOException $e) {
            error_log("Error getting team activities: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get activities for a specific task
     *
     * @param int $taskId Task ID
     * @param int $limit Maximum number of activities
     * @return array Task activities with formatted data
     */
    public function getTaskActivities($taskId, $limit = 20) {
        try {
            $sql = "SELECT a.*,
                           u.first_name, u.last_name, u.username,
                           CONCAT(u.first_name, ' ', u.last_name) as user_name
                    FROM activities a
                    LEFT JOIN users u ON a.user_id = u.id
                    WHERE a.target_type = 'task'
                    AND a.target_id = :task_id
                    ORDER BY a.created_at DESC
                    LIMIT :limit";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':task_id', $taskId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $activities = $stmt->fetchAll();

            // Add icon and color to each activity
            foreach ($activities as &$activity) {
                $activity['icon'] = $this->getActivityIcon($activity['action']);
                $activity['color'] = $this->getActivityColor($activity['action']);
            }

            return $activities;

        } catch (PDOException $e) {
            error_log("Error getting task activities: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all recent activities (for dashboard feed)
     *
     * @param int $userId User ID
     * @param int $limit Maximum number of activities
     * @param int|null $teamId Optional team ID to filter
     * @return array All recent activities visible to user
     */
    public function getAllRecentActivities($userId, $limit = 10, $teamId = null) {
        try {
            $sql = "SELECT a.*, u.first_name, u.last_name, u.username,
                           t.title as task_title
                    FROM activities a
                    LEFT JOIN users u ON a.user_id = u.id
                    LEFT JOIN tasks t ON a.target_type = 'task' AND a.target_id = t.id
                    WHERE (
                        a.user_id = :user_id
                        OR EXISTS (
                            SELECT 1 FROM tasks
                            WHERE tasks.id = a.target_id
                            AND (tasks.user_id = :user_id2 OR tasks.assigned_to = :user_id3)
                        )
                    )";

            if ($teamId !== null) {
                $sql .= " AND EXISTS (
                    SELECT 1 FROM tasks
                    WHERE tasks.id = a.target_id
                    AND tasks.team_id = :team_id
                )";
            }

            $sql .= " ORDER BY a.created_at DESC LIMIT :limit";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id2', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id3', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

            if ($teamId !== null) {
                $stmt->bindValue(':team_id', $teamId, PDO::PARAM_INT);
            }

            $stmt->execute();
            return $stmt->fetchAll();

        } catch (PDOException $e) {
            error_log("Error getting all recent activities: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Log new activity
     *
     * @param int $userId User ID performing the action
     * @param string $action Action name (e.g., 'task_created', 'task_completed')
     * @param string $targetType Type of target (e.g., 'task', 'project')
     * @param int $targetId ID of the target
     * @param string|null $description Optional description
     * @return bool Success
     */
    public function logActivity($userId, $action, $targetType, $targetId, $description = null) {
        try {
            $sql = "INSERT INTO activities
                    (user_id, action, target_type, target_id, description, created_at)
                    VALUES
                    (:user_id, :action, :target_type, :target_id, :description, NOW())";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':user_id' => $userId,
                ':action' => $action,
                ':target_type' => $targetType,
                ':target_id' => $targetId,
                ':description' => $description
            ]);

        } catch (PDOException $e) {
            error_log("Error logging activity: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Format activity for display
     *
     * @param array $activity Activity record
     * @return string Formatted activity message
     */
    public function formatActivity($activity) {
        $userName = htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']);
        $taskTitle = htmlspecialchars($activity['task_title'] ?? 'Unknown Task');

        switch ($activity['action']) {
            case 'task_created':
                return "$userName created task: $taskTitle";
            case 'task_completed':
                return "$userName completed task: $taskTitle";
            case 'task_updated':
                return "$userName updated task: $taskTitle";
            case 'task_assigned':
                return "$userName was assigned to task: $taskTitle";
            case 'task_status_changed':
                return "$userName changed status of task: $taskTitle";
            case 'task_deleted':
                return "$userName deleted task: $taskTitle";
            case 'comment_added':
                return "$userName commented on task: $taskTitle";
            default:
                return $activity['description'] ? htmlspecialchars($activity['description']) : "$userName performed an action";
        }
    }

    /**
     * Get activity icon based on action type
     *
     * @param string $action Action type
     * @return string Bootstrap icon class
     */
    public function getActivityIcon($action) {
        switch ($action) {
            case 'task_created':
                return 'bi-plus-circle';
            case 'task_completed':
                return 'bi-check-circle';
            case 'task_updated':
                return 'bi-pencil-square';
            case 'task_assigned':
                return 'bi-person-check';
            case 'task_status_changed':
                return 'bi-arrow-repeat';
            case 'task_deleted':
                return 'bi-trash';
            case 'comment_added':
                return 'bi-chat-left-text';
            default:
                return 'bi-activity';
        }
    }

    /**
     * Get activity color based on action type
     *
     * @param string $action Action type
     * @return string Bootstrap color class
     */
    public function getActivityColor($action) {
        switch ($action) {
            case 'task_created':
                return 'primary';
            case 'task_completed':
                return 'success';
            case 'task_updated':
                return 'info';
            case 'task_assigned':
                return 'warning';
            case 'task_status_changed':
                return 'info';
            case 'task_deleted':
                return 'danger';
            case 'comment_added':
                return 'secondary';
            default:
                return 'dark';
        }
    }

    /**
     * Delete old activities (cleanup)
     *
     * @param int $daysOld Number of days old
     * @return bool Success
     */
    public function deleteOldActivities($daysOld = 90) {
        try {
            $sql = "DELETE FROM activities
                    WHERE created_at < NOW() - INTERVAL '1 day' * :days";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':days', $daysOld, PDO::PARAM_INT);

            return $stmt->execute();

        } catch (PDOException $e) {
            error_log("Error deleting old activities: " . $e->getMessage());
            return false;
        }
    }
}
