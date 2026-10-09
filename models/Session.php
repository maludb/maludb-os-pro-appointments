<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';

class Session
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("INSERT INTO user_agent_session (user_id, org_id, session_id, prospect_id, product_id, call_type, transcript, quality_score, duration_ms, created_at, updated_at) VALUES (:user_id, :org_id, :session_id, :prospect_id, :product_id, :call_type, :transcript, :quality_score, :duration_ms, NOW(), NOW())");
        $stmt->execute([
            ':user_id' => $data['user_id'],
            ':org_id' => $data['org_id'],
            ':session_id' => $data['session_id'] ?? bin2hex(random_bytes(6)),
            ':prospect_id' => $data['prospect_id'] ?? null,
            ':product_id' => $data['product_id'] ?? null,
            ':call_type' => $data['call_type'] ?? 'cold_call',
            ':transcript' => $data['transcript'] ?? '',
            ':quality_score' => $data['quality_score'] ?? null,
            ':duration_ms' => $data['duration_ms'] ?? 0,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];
        foreach ($data as $key => $val) {
            $fields[] = "$key = :$key";
            $params[":$key"] = $val;
        }
        if (empty($fields)) return false;
        $sql = "UPDATE user_agent_session SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function getById(int $id, int $orgId): ?array
    {
        $sql = "SELECT s.*, p.name AS prospect_name, pr.name AS product_name
                FROM user_agent_session s
                LEFT JOIN prospects p ON s.prospect_id = p.id
                LEFT JOIN products pr ON s.product_id = pr.id
                WHERE s.id = :id AND s.org_id = :org_id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id, ':org_id' => $orgId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getByUser(int $userId, int $orgId, array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT s.*, p.name AS prospect_company, p.prospect_name, p.prospect_role,
                       pr.name AS product_name, pr.product_category
                FROM user_agent_session s
                LEFT JOIN prospects p ON s.prospect_id = p.id
                LEFT JOIN products pr ON s.product_id = pr.id
                WHERE s.user_id = :user_id AND s.org_id = :org_id";
        $params = [':user_id' => $userId, ':org_id' => $orgId];
        $this->applyFilters($sql, $params, $filters, false);
        $sql .= " ORDER BY s.created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        $this->bindParams($stmt, $params, $limit, $offset);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getByOrg(int $orgId, array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT s.*, p.name AS prospect_company, p.prospect_name, p.prospect_role,
                       pr.name AS product_name, pr.product_category,
                       u.first_name AS rep_first_name, u.last_name AS rep_last_name
                FROM user_agent_session s
                LEFT JOIN prospects p ON s.prospect_id = p.id
                LEFT JOIN products pr ON s.product_id = pr.id
                LEFT JOIN users u ON s.user_id = u.id
                WHERE s.org_id = :org_id";
        $params = [':org_id' => $orgId];
        $this->applyFilters($sql, $params, $filters, true);
        $sql .= " ORDER BY s.created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        $this->bindParams($stmt, $params, $limit, $offset);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countByUser(int $userId, int $orgId, array $filters = []): int
    {
        $sql = "SELECT COUNT(*) as cnt
                FROM user_agent_session s
                LEFT JOIN prospects p ON s.prospect_id = p.id
                LEFT JOIN products pr ON s.product_id = pr.id
                WHERE s.user_id = :user_id AND s.org_id = :org_id";
        $params = [':user_id' => $userId, ':org_id' => $orgId];
        $this->applyFilters($sql, $params, $filters, false);
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $row = $stmt->fetch();
        return (int)($row['cnt'] ?? 0);
    }

    public function countByOrg(int $orgId, array $filters = []): int
    {
        $sql = "SELECT COUNT(*) as cnt
                FROM user_agent_session s
                LEFT JOIN prospects p ON s.prospect_id = p.id
                LEFT JOIN products pr ON s.product_id = pr.id
                LEFT JOIN users u ON s.user_id = u.id
                WHERE s.org_id = :org_id";
        $params = [':org_id' => $orgId];
        $this->applyFilters($sql, $params, $filters, true);
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $row = $stmt->fetch();
        return (int)($row['cnt'] ?? 0);
    }

    public function getUserStats(int $userId, int $orgId): array
    {
        $sql = "SELECT
                    COUNT(*) AS total,
                    AVG(quality_score) AS avg_score,
                    MAX(quality_score) AS best_score,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS sessions_week
                FROM user_agent_session
                WHERE user_id = :user_id AND org_id = :org_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':org_id' => $orgId]);
        return $stmt->fetch() ?: ['total' => 0, 'avg_score' => 0, 'best_score' => 0, 'sessions_week' => 0];
    }

    public function getTeamStats(int $orgId): array
    {
        $sql = "SELECT COUNT(*) AS total_sessions, AVG(quality_score) AS avg_score, MAX(quality_score) AS best_score FROM user_agent_session WHERE org_id = :org_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':org_id' => $orgId]);
        return $stmt->fetch() ?: ['total_sessions' => 0, 'avg_score' => 0, 'best_score' => 0];
    }

    public function getScoreTrends(int $userId, int $orgId, int $days = 30): array
    {
        $sql = "SELECT DATE(created_at) as day, AVG(quality_score) as avg_score
                FROM user_agent_session
                WHERE user_id = :user_id AND org_id = :org_id AND created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                GROUP BY DATE(created_at)
                ORDER BY DATE(created_at) ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':org_id', $orgId, PDO::PARAM_INT);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getLeaderboard(int $orgId, int $limit = 10): array
    {
        $sql = "SELECT u.id, u.first_name, u.last_name, AVG(s.quality_score) as avg_score, COUNT(*) as sessions
                FROM user_agent_session s
                JOIN users u ON s.user_id = u.id
                WHERE s.org_id = :org_id AND s.quality_score IS NOT NULL
                GROUP BY u.id, u.first_name, u.last_name
                ORDER BY avg_score DESC, sessions DESC
                LIMIT :limit";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':org_id', $orgId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getForCalendar(int $userId, int $orgId, string $start, string $end): array
    {
        $sql = "SELECT s.id, s.call_date, s.status, s.call_type, s.quality_score,
                       p.name AS prospect_company, p.prospect_name,
                       pr.name AS product_name
                FROM user_agent_session s
                LEFT JOIN prospects p ON s.prospect_id = p.id
                LEFT JOIN products pr ON s.product_id = pr.id
                WHERE s.user_id = :user_id AND s.org_id = :org_id
                  AND s.call_date IS NOT NULL
                  AND s.call_date >= :start AND s.call_date <= :end
                ORDER BY s.call_date ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':org_id' => $orgId,
            ':start' => $start,
            ':end' => $end,
        ]);
        return $stmt->fetchAll();
    }

    public function createPlanned(array $data): int
    {
        $stmt = $this->db->prepare("INSERT INTO user_agent_session (user_id, org_id, session_id, prospect_id, product_id, call_type, call_date, status, transcript, duration_ms, created_at, updated_at) VALUES (:user_id, :org_id, :session_id, :prospect_id, :product_id, :call_type, :call_date, 'planned', '[]', 0, NOW(), NOW())");
        $stmt->execute([
            ':user_id' => $data['user_id'],
            ':org_id' => $data['org_id'],
            ':session_id' => bin2hex(random_bytes(6)),
            ':prospect_id' => $data['prospect_id'] ?: null,
            ':product_id' => $data['product_id'] ?: null,
            ':call_type' => $data['call_type'] ?? 'cold_call',
            ':call_date' => $data['call_date'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    private function applyFilters(string &$sql, array &$params, array $filters, bool $allowUserFilter): void
    {
        $search = trim($filters['search'] ?? '');
        if ($search !== '') {
            $sql .= " AND (s.transcript LIKE :search_like OR p.name LIKE :search_like OR pr.name LIKE :search_like)";
            $params[':search_like'] = '%' . $search . '%';
        }
        if (!empty($filters['prospect_id'])) {
            $sql .= " AND s.prospect_id = :prospect_id";
            $params[':prospect_id'] = (int)$filters['prospect_id'];
        }
        if (!empty($filters['product_id'])) {
            $sql .= " AND s.product_id = :product_id";
            $params[':product_id'] = (int)$filters['product_id'];
        }
        if (!empty($filters['call_type'])) {
            $sql .= " AND s.call_type = :call_type";
            $params[':call_type'] = $filters['call_type'];
        }
        if ($filters['score_min'] !== '' && $filters['score_min'] !== null) {
            $sql .= " AND s.quality_score >= :score_min";
            $params[':score_min'] = (float)$filters['score_min'];
        }
        if ($filters['score_max'] !== '' && $filters['score_max'] !== null) {
            $sql .= " AND s.quality_score <= :score_max";
            $params[':score_max'] = (float)$filters['score_max'];
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND s.created_at >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND s.created_at <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }
        if ($allowUserFilter && !empty($filters['user_id'])) {
            $sql .= " AND s.user_id = :user_filter";
            $params[':user_filter'] = (int)$filters['user_id'];
        }
    }

    private function bindParams($stmt, array $params, int $limit, int $offset): void
    {
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    }
}
