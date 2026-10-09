<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';

class ScoringRubric
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAllForCategory(int $categoryId, int $orgId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM scoring_rubric WHERE category_id = :category_id AND org_id = :org_id ORDER BY score_level ASC, id ASC");
        $stmt->execute([
            ':category_id' => $categoryId,
            ':org_id' => $orgId,
        ]);
        return $stmt->fetchAll();
    }

    public function getById(int $id, int $orgId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM scoring_rubric WHERE id = :id AND org_id = :org_id LIMIT 1");
        $stmt->execute([
            ':id' => $id,
            ':org_id' => $orgId,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("INSERT INTO scoring_rubric (org_id, category_id, score_level, name, description, criteria, created_by) VALUES (:org_id, :category_id, :score_level, :name, :description, :criteria, :created_by)");
        $stmt->execute([
            ':org_id' => currentOrgId(),
            ':category_id' => $data['category_id'],
            ':score_level' => $data['score_level'] ?? 5,
            ':name' => $data['name'] ?? '',
            ':description' => $data['description'] ?? null,
            ':criteria' => $data['criteria'] ?? null,
            ':created_by' => currentUserId(),
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data, int $orgId): bool
    {
        $stmt = $this->db->prepare("UPDATE scoring_rubric SET score_level = :score_level, name = :name, description = :description, criteria = :criteria WHERE id = :id AND org_id = :org_id");
        return $stmt->execute([
            ':score_level' => $data['score_level'] ?? 5,
            ':name' => $data['name'] ?? '',
            ':description' => $data['description'] ?? null,
            ':criteria' => $data['criteria'] ?? null,
            ':id' => $id,
            ':org_id' => $orgId,
        ]);
    }

    public function delete(int $id, int $orgId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM scoring_rubric WHERE id = :id AND org_id = :org_id");
        return $stmt->execute([
            ':id' => $id,
            ':org_id' => $orgId,
        ]);
    }

    public function getAllForOrg(int $orgId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM scoring_rubric WHERE org_id = :org_id ORDER BY category_id ASC, score_level ASC");
        $stmt->bindValue(':org_id', $orgId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['category_id']][] = $row;
        }
        return $grouped;
    }
}
