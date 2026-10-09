<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';

class Organization {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll(?string $search = null, int $limit = 12, int $offset = 0): array {
        $sql = "SELECT * FROM orgs";
        $params = [];

        if (!empty($search)) {
            $sql .= " WHERE name LIKE :search OR description LIKE :search";
            $params[':search'] = '%' . $search . '%';
        }

        $sql .= " ORDER BY name ASC LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countAll(?string $search = null): int {
        $sql = "SELECT COUNT(*) as cnt FROM orgs";
        $params = [];

        if (!empty($search)) {
            $sql .= " WHERE name LIKE :search OR description LIKE :search";
            $params[':search'] = '%' . $search . '%';
        }

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->execute();
        $row = $stmt->fetch();
        return (int)($row['cnt'] ?? 0);
    }

    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM orgs WHERE id = :id LIMIT 1");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("INSERT INTO orgs (name, description, created_by) VALUES (:name, :description, :created_by)");
        $stmt->execute([
            ':name' => $data['name'],
            ':description' => $data['description'] ?? null,
            ':created_by' => currentUserId(),
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $stmt = $this->db->prepare("UPDATE orgs SET name = :name, description = :description WHERE id = :id");
        return $stmt->execute([
            ':name' => $data['name'],
            ':description' => $data['description'] ?? null,
            ':id' => $id,
        ]);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM orgs WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function getMembers(int $orgId): array {
        $stmt = $this->db->prepare("SELECT u.id, u.first_name, u.last_name, u.email, u.role, om.role as member_role, om.joined_at FROM users u JOIN org_members om ON om.user_id = u.id WHERE om.team_id = :org_id ORDER BY u.first_name ASC");
        $stmt->bindValue(':org_id', $orgId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
