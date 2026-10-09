<?php
/**
 * Event Model
 *
 * Handles all database operations related to calendar events
 */

require_once __DIR__ . '/../config/database.php';

class Event {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get events for a date range
     *
     * @param int $teamId Team ID
     * @param string $startDate Start date (Y-m-d H:i:s)
     * @param string $endDate End date (Y-m-d H:i:s)
     * @param array $filters Optional filters (types, user_ids)
     * @return array Events
     */
    public function getEvents($teamId, $startDate, $endDate, $filters = []) {
        try {
            $sql = "SELECT e.*,
                           u.first_name as creator_first_name,
                           u.last_name as creator_last_name
                    FROM events e
                    LEFT JOIN users u ON e.created_by = u.id
                    WHERE e.team_id = :team_id
                    AND e.status != 'cancelled'
                    AND (
                        (e.start_datetime BETWEEN :start_date AND :end_date)
                        OR (e.end_datetime BETWEEN :start_date AND :end_date)
                        OR (e.start_datetime <= :start_date AND e.end_datetime >= :end_date)
                    )";

            // Filter by event types
            if (!empty($filters['types'])) {
                $placeholders = [];
                foreach ($filters['types'] as $i => $type) {
                    $placeholders[] = ":type_$i";
                }
                $sql .= " AND e.type IN (" . implode(',', $placeholders) . ")";
            }

            // Filter by attendees (user_ids)
            if (!empty($filters['user_ids'])) {
                $userPlaceholders = [];
                foreach ($filters['user_ids'] as $i => $userId) {
                    $userPlaceholders[] = ":user_id_$i";
                }
                $sql .= " AND e.id IN (
                    SELECT DISTINCT ea.event_id
                    FROM event_attendees ea
                    WHERE ea.user_id IN (" . implode(',', $userPlaceholders) . ")
                )";
            }

            $sql .= " ORDER BY e.start_datetime ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':team_id', $teamId, PDO::PARAM_INT);
            $stmt->bindValue(':start_date', $startDate);
            $stmt->bindValue(':end_date', $endDate);

            // Bind type filters
            if (!empty($filters['types'])) {
                foreach ($filters['types'] as $i => $type) {
                    $stmt->bindValue(":type_$i", $type);
                }
            }

            // Bind user_id filters
            if (!empty($filters['user_ids'])) {
                foreach ($filters['user_ids'] as $i => $userId) {
                    $stmt->bindValue(":user_id_$i", $userId, PDO::PARAM_INT);
                }
            }

            $stmt->execute();
            return $stmt->fetchAll();

        } catch (PDOException $e) {
            error_log("Error getting events: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get event by ID
     *
     * @param int $eventId Event ID
     * @return array|false Event data or false
     */
    public function getEventById($eventId) {
        try {
            $sql = "SELECT e.*,
                           u.first_name as creator_first_name,
                           u.last_name as creator_last_name
                    FROM events e
                    LEFT JOIN users u ON e.created_by = u.id
                    WHERE e.id = :event_id";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':event_id', $eventId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch();

        } catch (PDOException $e) {
            error_log("Error getting event by ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a new event
     *
     * @param array $data Event data
     * @return int|false Event ID or false
     */
    public function createEvent($data) {
        try {
            $sql = "INSERT INTO events (
                        team_id, created_by, title, description, location,
                        start_datetime, end_datetime, all_day, color, type, status
                    ) VALUES (
                        :team_id, :created_by, :title, :description, :location,
                        :start_datetime, :end_datetime, :all_day, :color, :type, :status
                    )";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':team_id', $data['team_id'], PDO::PARAM_INT);
            $stmt->bindValue(':created_by', $data['created_by'], PDO::PARAM_INT);
            $stmt->bindValue(':title', $data['title']);
            $stmt->bindValue(':description', $data['description'] ?? '');
            $stmt->bindValue(':location', $data['location'] ?? '');
            $stmt->bindValue(':start_datetime', $data['start_datetime']);
            $stmt->bindValue(':end_datetime', $data['end_datetime']);
            $stmt->bindValue(':all_day', $data['all_day'] ?? 0, PDO::PARAM_INT);
            $stmt->bindValue(':color', $data['color'] ?? '#0d6efd');
            $stmt->bindValue(':type', $data['type'] ?? 'event');
            $stmt->bindValue(':status', $data['status'] ?? 'scheduled');

            $stmt->execute();
            return $this->db->lastInsertId();

        } catch (PDOException $e) {
            error_log("Error creating event: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update an event
     *
     * @param int $eventId Event ID
     * @param array $data Event data
     * @return bool Success
     */
    public function updateEvent($eventId, $data) {
        try {
            $sql = "UPDATE events SET
                        title = :title,
                        description = :description,
                        location = :location,
                        start_datetime = :start_datetime,
                        end_datetime = :end_datetime,
                        all_day = :all_day,
                        color = :color,
                        type = :type,
                        status = :status,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :event_id";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':event_id', $eventId, PDO::PARAM_INT);
            $stmt->bindValue(':title', $data['title']);
            $stmt->bindValue(':description', $data['description'] ?? '');
            $stmt->bindValue(':location', $data['location'] ?? '');
            $stmt->bindValue(':start_datetime', $data['start_datetime']);
            $stmt->bindValue(':end_datetime', $data['end_datetime']);
            $stmt->bindValue(':all_day', $data['all_day'] ?? 0, PDO::PARAM_INT);
            $stmt->bindValue(':color', $data['color'] ?? '#0d6efd');
            $stmt->bindValue(':type', $data['type'] ?? 'event');
            $stmt->bindValue(':status', $data['status'] ?? 'scheduled');

            return $stmt->execute();

        } catch (PDOException $e) {
            error_log("Error updating event: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete an event
     *
     * @param int $eventId Event ID
     * @return bool Success
     */
    public function deleteEvent($eventId) {
        try {
            // First delete all attendees
            $stmt = $this->db->prepare("DELETE FROM event_attendees WHERE event_id = :event_id");
            $stmt->bindValue(':event_id', $eventId, PDO::PARAM_INT);
            $stmt->execute();

            // Then delete the event
            $stmt = $this->db->prepare("DELETE FROM events WHERE id = :event_id");
            $stmt->bindValue(':event_id', $eventId, PDO::PARAM_INT);
            return $stmt->execute();

        } catch (PDOException $e) {
            error_log("Error deleting event: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get attendees for an event
     *
     * @param int $eventId Event ID
     * @return array Attendees
     */
    public function getEventAttendees($eventId) {
        try {
            $sql = "SELECT ea.*,
                           u.first_name, u.last_name, u.email
                    FROM event_attendees ea
                    JOIN users u ON ea.user_id = u.id
                    WHERE ea.event_id = :event_id
                    ORDER BY ea.is_organizer DESC, u.first_name ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':event_id', $eventId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();

        } catch (PDOException $e) {
            error_log("Error getting event attendees: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Add an attendee to an event
     *
     * @param int $eventId Event ID
     * @param int $userId User ID
     * @param bool $isOrganizer Is organizer flag
     * @return bool Success
     */
    public function addAttendee($eventId, $userId, $isOrganizer = false) {
        try {
            // Check if attendee already exists
            $stmt = $this->db->prepare("SELECT id FROM event_attendees WHERE event_id = :event_id AND user_id = :user_id");
            $stmt->bindValue(':event_id', $eventId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->fetch()) {
                return true; // Already exists
            }

            $sql = "INSERT INTO event_attendees (event_id, user_id, is_organizer, response_status)
                    VALUES (:event_id, :user_id, :is_organizer, :response_status)";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':event_id', $eventId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':is_organizer', $isOrganizer ? 1 : 0, PDO::PARAM_INT);
            $stmt->bindValue(':response_status', $isOrganizer ? 'accepted' : 'pending');

            return $stmt->execute();

        } catch (PDOException $e) {
            error_log("Error adding attendee: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update attendee response status
     *
     * @param int $eventId Event ID
     * @param int $userId User ID
     * @param string $status Response status (pending, accepted, declined, tentative)
     * @return bool Success
     */
    public function updateAttendeeResponse($eventId, $userId, $status) {
        try {
            $sql = "UPDATE event_attendees SET
                        response_status = :status,
                        responded_at = CURRENT_TIMESTAMP
                    WHERE event_id = :event_id AND user_id = :user_id";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':event_id', $eventId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':status', $status);

            return $stmt->execute();

        } catch (PDOException $e) {
            error_log("Error updating attendee response: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove an attendee from an event
     *
     * @param int $eventId Event ID
     * @param int $userId User ID
     * @return bool Success
     */
    public function removeAttendee($eventId, $userId) {
        try {
            $stmt = $this->db->prepare("DELETE FROM event_attendees WHERE event_id = :event_id AND user_id = :user_id");
            $stmt->bindValue(':event_id', $eventId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);

            return $stmt->execute();

        } catch (PDOException $e) {
            error_log("Error removing attendee: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if user is event organizer
     *
     * @param int $eventId Event ID
     * @param int $userId User ID
     * @return bool Is organizer
     */
    public function isOrganizer($eventId, $userId) {
        try {
            $stmt = $this->db->prepare("SELECT is_organizer FROM event_attendees WHERE event_id = :event_id AND user_id = :user_id");
            $stmt->bindValue(':event_id', $eventId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch();
            return $result && $result['is_organizer'] == 1;

        } catch (PDOException $e) {
            error_log("Error checking organizer: " . $e->getMessage());
            return false;
        }
    }
}
