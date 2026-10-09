<?php
/**
 * Notification Model
 *
 * Handles all database operations related to notifications
 */

require_once __DIR__ . '/../config/database.php';

class Notification {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get unread notification count for user
     *
     * @param int $userId User ID
     * @return int Unread count
     */
    public function getUnreadCount($userId) {
        try {
            $sql = "SELECT COUNT(*) as count
                    FROM notifications
                    WHERE user_id = :user_id AND read_at IS NULL";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch();
            return $result['count'] ?? 0;

        } catch (PDOException $e) {
            error_log("Error getting unread notification count: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get recent notifications for user
     *
     * @param int $userId User ID
     * @param int $limit Maximum number of notifications
     * @param bool $unreadOnly Show only unread notifications
     * @return array Notifications
     */
    public function getRecentNotifications($userId, $limit = 10, $unreadOnly = false) {
        try {
            $sql = "SELECT n.*, u.first_name, u.last_name
                    FROM notifications n
                    LEFT JOIN users u ON n.from_user_id = u.id
                    WHERE n.user_id = :user_id";

            if ($unreadOnly) {
                $sql .= " AND n.read_at IS NULL";
            }

            $sql .= " ORDER BY n.created_at DESC LIMIT :limit";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();

        } catch (PDOException $e) {
            error_log("Error getting recent notifications: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Create new notification
     *
     * @param int $userId User ID to notify
     * @param string $type Notification type
     * @param string $message Notification message
     * @param int|null $fromUserId User ID who triggered notification
     * @param string|null $link Optional link
     * @return bool Success
     */
    public function create($userId, $type, $message, $fromUserId = null, $link = null) {
        try {
            $sql = "INSERT INTO notifications
                    (user_id, from_user_id, type, message, link, created_at)
                    VALUES
                    (:user_id, :from_user_id, :type, :message, :link, NOW())";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':user_id' => $userId,
                ':from_user_id' => $fromUserId,
                ':type' => $type,
                ':message' => $message,
                ':link' => $link
            ]);

        } catch (PDOException $e) {
            error_log("Error creating notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark notification as read
     *
     * @param int $notificationId Notification ID
     * @param int $userId User ID (for authorization)
     * @return bool Success
     */
    public function markAsRead($notificationId, $userId) {
        try {
            $sql = "UPDATE notifications
                    SET read_at = NOW()
                    WHERE id = :notification_id AND user_id = :user_id AND read_at IS NULL";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':notification_id' => $notificationId,
                ':user_id' => $userId
            ]);

        } catch (PDOException $e) {
            error_log("Error marking notification as read: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark all notifications as read for user
     *
     * @param int $userId User ID
     * @return bool Success
     */
    public function markAllAsRead($userId) {
        try {
            $sql = "UPDATE notifications
                    SET read_at = NOW()
                    WHERE user_id = :user_id AND read_at IS NULL";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);

            return $stmt->execute();

        } catch (PDOException $e) {
            error_log("Error marking all notifications as read: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete notification
     *
     * @param int $notificationId Notification ID
     * @param int $userId User ID (for authorization)
     * @return bool Success
     */
    public function delete($notificationId, $userId) {
        try {
            $sql = "DELETE FROM notifications
                    WHERE id = :notification_id AND user_id = :user_id";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':notification_id' => $notificationId,
                ':user_id' => $userId
            ]);

        } catch (PDOException $e) {
            error_log("Error deleting notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete old notifications (cleanup)
     *
     * @param int $daysOld Number of days old
     * @return bool Success
     */
    public function deleteOldNotifications($daysOld = 30) {
        try {
            $sql = "DELETE FROM notifications
                    WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
                    AND read_at IS NOT NULL";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':days', $daysOld, PDO::PARAM_INT);

            return $stmt->execute();

        } catch (PDOException $e) {
            error_log("Error deleting old notifications: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get notification icon based on type
     *
     * @param string $type Notification type
     * @return string Bootstrap icon class
     */
    public function getNotificationIcon($type) {
        switch ($type) {
            case 'task_assigned':
                return 'bi-person-check';
            case 'task_completed':
                return 'bi-check-circle';
            case 'task_comment':
                return 'bi-chat-left-text';
            case 'task_mention':
                return 'bi-at';
            case 'task_due_soon':
                return 'bi-clock';
            case 'task_overdue':
                return 'bi-exclamation-triangle';
            case 'team_invite':
                return 'bi-people';
            default:
                return 'bi-bell';
        }
    }

    /**
     * Get notification color based on type
     *
     * @param string $type Notification type
     * @return string Bootstrap color class
     */
    public function getNotificationColor($type) {
        switch ($type) {
            case 'task_assigned':
                return 'primary';
            case 'task_completed':
                return 'success';
            case 'task_comment':
                return 'info';
            case 'task_mention':
                return 'warning';
            case 'task_due_soon':
                return 'warning';
            case 'task_overdue':
                return 'danger';
            case 'team_invite':
                return 'primary';
            default:
                return 'secondary';
        }
    }
}
