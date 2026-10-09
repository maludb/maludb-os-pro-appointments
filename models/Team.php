<?php
/**
 * Team Model
 *
 * Handles all database operations related to teams
 */

require_once __DIR__ . '/../config/database.php';

class Team {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get all teams for a user
     *
     * @param int $userId User ID
     * @return array User's teams
     */
    public function getUserTeams($userId) {
        try {
            $sql = "SELECT t.*, tm.role as member_role,
                           (SELECT COUNT(*) FROM team_members WHERE team_id = t.id) as member_count
                    FROM teams t
                    INNER JOIN team_members tm ON t.id = tm.team_id
                    WHERE tm.user_id = :user_id
                    ORDER BY t.name ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();

        } catch (PDOException $e) {
            error_log("Error getting user teams: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all teams in the system
     *
     * @return array All teams with member counts
     */
    public function getAllTeams() {
        try {
            $sql = "SELECT t.*,
                           (SELECT COUNT(*) FROM team_members WHERE team_id = t.id) as member_count
                    FROM teams t
                    ORDER BY t.name ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll();

        } catch (PDOException $e) {
            error_log("Error getting all teams: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get team details
     *
     * @param int $teamId Team ID
     * @return array|false Team details or false
     */
    public function getTeamDetails($teamId) {
        try {
            $sql = "SELECT t.*,
                           (SELECT COUNT(*) FROM team_members WHERE team_id = t.id) as member_count,
                           (SELECT COUNT(*) FROM tasks WHERE team_id = t.id) as task_count
                    FROM teams t
                    WHERE t.id = :team_id";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':team_id', $teamId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch();

        } catch (PDOException $e) {
            error_log("Error getting team details: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get team members
     *
     * @param int $teamId Team ID
     * @return array Team members
     */
    public function getTeamMembers($teamId) {
        try {
            $sql = "SELECT u.id, u.first_name, u.last_name, u.email, u.username,
                           tm.role as team_role, tm.joined_at
                    FROM team_members tm
                    INNER JOIN users u ON tm.user_id = u.id
                    WHERE tm.team_id = :team_id
                    ORDER BY tm.role ASC, u.first_name ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':team_id', $teamId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();

        } catch (PDOException $e) {
            error_log("Error getting team members: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if user is member of team
     *
     * @param int $userId User ID
     * @param int $teamId Team ID
     * @return bool True if member
     */
    public function isTeamMember($userId, $teamId) {
        try {
            $sql = "SELECT COUNT(*) as count
                    FROM team_members
                    WHERE user_id = :user_id AND team_id = :team_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':team_id' => $teamId
            ]);

            $result = $stmt->fetch();
            return $result['count'] > 0;

        } catch (PDOException $e) {
            error_log("Error checking team membership: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user's role in team
     *
     * @param int $userId User ID
     * @param int $teamId Team ID
     * @return string|false Role or false
     */
    public function getUserTeamRole($userId, $teamId) {
        try {
            $sql = "SELECT role FROM team_members
                    WHERE user_id = :user_id AND team_id = :team_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':team_id' => $teamId
            ]);

            $result = $stmt->fetch();
            return $result ? $result['role'] : false;

        } catch (PDOException $e) {
            error_log("Error getting user team role: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create new team
     *
     * @param string $name Team name
     * @param string|null $description Team description
     * @param int $creatorId Creator user ID
     * @return int|false Team ID or false
     */
    public function create($name, $description, $creatorId) {
        try {
            $this->db->beginTransaction();

            // Create team
            $sql = "INSERT INTO teams (name, description, created_by, created_at, updated_at)
                    VALUES (:name, :description, :created_by, NOW(), NOW())";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':created_by' => $creatorId
            ]);

            $teamId = $this->db->lastInsertId();

            // Add creator as admin
            $sql = "INSERT INTO team_members (team_id, user_id, role, joined_at)
                    VALUES (:team_id, :user_id, 'admin', NOW())";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':team_id' => $teamId,
                ':user_id' => $creatorId
            ]);

            $this->db->commit();
            return $teamId;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error creating team: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update team
     *
     * @param int $teamId Team ID
     * @param array $data Team data to update
     * @return bool Success
     */
    public function update($teamId, $data) {
        try {
            $updates = [];
            $params = [':team_id' => $teamId];

            if (isset($data['name'])) {
                $updates[] = "name = :name";
                $params[':name'] = $data['name'];
            }

            if (isset($data['description'])) {
                $updates[] = "description = :description";
                $params[':description'] = $data['description'];
            }

            if (empty($updates)) {
                return false;
            }

            $sql = "UPDATE teams SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = :team_id";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);

        } catch (PDOException $e) {
            error_log("Error updating team: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add member to team
     *
     * @param int $teamId Team ID
     * @param int $userId User ID
     * @param string $role Role (member, admin, owner)
     * @return bool Success
     */
    public function addMember($teamId, $userId, $role = 'member') {
        try {
            $sql = "INSERT INTO team_members (team_id, user_id, role, joined_at)
                    VALUES (:team_id, :user_id, :role, NOW())";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':team_id' => $teamId,
                ':user_id' => $userId,
                ':role' => $role
            ]);

        } catch (PDOException $e) {
            error_log("Error adding team member: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove member from team
     *
     * @param int $teamId Team ID
     * @param int $userId User ID
     * @return bool Success
     */
    public function removeMember($teamId, $userId) {
        try {
            $sql = "DELETE FROM team_members
                    WHERE team_id = :team_id AND user_id = :user_id";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':team_id' => $teamId,
                ':user_id' => $userId
            ]);

        } catch (PDOException $e) {
            error_log("Error removing team member: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete team
     *
     * @param int $teamId Team ID
     * @param int $userId User ID (must be owner)
     * @return bool Success
     */
    public function delete($teamId, $userId) {
        try {
            // Verify user is owner
            $role = $this->getUserTeamRole($userId, $teamId);
            if ($role !== 'owner') {
                return false;
            }

            $this->db->beginTransaction();

            // Delete team members
            $sql = "DELETE FROM team_members WHERE team_id = :team_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':team_id' => $teamId]);

            // Delete team
            $sql = "DELETE FROM teams WHERE id = :team_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':team_id' => $teamId]);

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error deleting team: " . $e->getMessage());
            return false;
        }
    }

    public function getByOrgId(int $orgId, ?string $search = null, int $limit = 12, int $offset = 0): array {
        $sql = "SELECT t.*, o.name as org_name, (SELECT COUNT(*) FROM team_members WHERE team_id = t.id) as member_count FROM teams t LEFT JOIN orgs o ON t.org_id = o.id WHERE t.org_id = :org_id";
        $params = [':org_id' => $orgId];
        if (!empty($search)) {
            $sql .= " AND (t.name LIKE :search OR t.description LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        $sql .= " ORDER BY t.name ASC LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, $k === ':org_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countByOrgId(int $orgId, ?string $search = null): int {
        $sql = "SELECT COUNT(*) as cnt FROM teams WHERE org_id = :org_id";
        $params = [':org_id' => $orgId];
        if (!empty($search)) {
            $sql .= " AND (name LIKE :search OR description LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, $k === ':org_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return (int)($stmt->fetch()['cnt'] ?? 0);
    }

    public function getAllFiltered(?string $search = null, int $limit = 12, int $offset = 0): array {
        $sql = "SELECT t.*, o.name as org_name, (SELECT COUNT(*) FROM team_members WHERE team_id = t.id) as member_count FROM teams t LEFT JOIN orgs o ON t.org_id = o.id";
        $params = [];
        if (!empty($search)) {
            $sql .= " WHERE (t.name LIKE :search OR t.description LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        $sql .= " ORDER BY t.name ASC LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countAll(?string $search = null): int {
        $sql = "SELECT COUNT(*) as cnt FROM teams";
        $params = [];
        if (!empty($search)) {
            $sql .= " WHERE (name LIKE :search OR description LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stmt->execute();
        return (int)($stmt->fetch()['cnt'] ?? 0);
    }

    public function createWithOrg(string $name, ?string $description, int $orgId, int $creatorId): int {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("INSERT INTO teams (name, description, org_id, created_by, created_at, updated_at) VALUES (:name, :description, :org_id, :created_by, NOW(), NOW())");
            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':org_id' => $orgId,
                ':created_by' => $creatorId,
            ]);
            $teamId = (int)$this->db->lastInsertId();

            $stmt = $this->db->prepare("INSERT INTO team_members (team_id, user_id, role, joined_at) VALUES (:team_id, :user_id, 'admin', NOW())");
            $stmt->execute([':team_id' => $teamId, ':user_id' => $creatorId]);

            $this->db->commit();
            return $teamId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function deleteById(int $teamId): bool {
        try {
            $this->db->beginTransaction();
            $this->db->prepare("DELETE FROM team_members WHERE team_id = :id")->execute([':id' => $teamId]);
            $this->db->prepare("DELETE FROM teams WHERE id = :id")->execute([':id' => $teamId]);
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error deleting team: " . $e->getMessage());
            return false;
        }
    }

    public function getById(int $teamId): ?array {
        $stmt = $this->db->prepare("SELECT t.*, o.name as org_name FROM teams t LEFT JOIN orgs o ON t.org_id = o.id WHERE t.id = :id LIMIT 1");
        $stmt->bindValue(':id', $teamId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
