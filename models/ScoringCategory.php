<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';

class ScoringCategory
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll(int $orgId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM scoring_categories WHERE org_id = :org_id ORDER BY sort_order ASC, id ASC");
        $stmt->bindValue(':org_id', $orgId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getById(int $id, int $orgId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM scoring_categories WHERE id = :id AND org_id = :org_id LIMIT 1");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':org_id', $orgId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("INSERT INTO scoring_categories (org_id, name, description, weight, sort_order, created_by) VALUES (:org_id, :name, :description, :weight, :sort_order, :created_by)");
        $stmt->execute([
            ':org_id' => currentOrgId(),
            ':name' => $data['name'],
            ':description' => $data['description'] ?? null,
            ':weight' => $data['weight'] ?? 1.0,
            ':sort_order' => $data['sort_order'] ?? 0,
            ':created_by' => currentUserId(),
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data, int $orgId): bool
    {
        $stmt = $this->db->prepare("UPDATE scoring_categories SET name = :name, description = :description, weight = :weight WHERE id = :id AND org_id = :org_id");
        return $stmt->execute([
            ':name' => $data['name'],
            ':description' => $data['description'] ?? null,
            ':weight' => $data['weight'] ?? 1.0,
            ':id' => $id,
            ':org_id' => $orgId,
        ]);
    }

    public function delete(int $id, int $orgId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM scoring_categories WHERE id = :id AND org_id = :org_id");
        return $stmt->execute([
            ':id' => $id,
            ':org_id' => $orgId,
        ]);
    }

    public function reorder(array $ids, int $orgId): bool
    {
        $this->db->beginTransaction();
        try {
            $order = 0;
            $stmt = $this->db->prepare("UPDATE scoring_categories SET sort_order = :sort_order WHERE id = :id AND org_id = :org_id");
            foreach ($ids as $id) {
                $stmt->execute([
                    ':sort_order' => $order++,
                    ':id' => (int)$id,
                    ':org_id' => $orgId,
                ]);
            }
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Reorder scoring categories failed: ' . $e->getMessage());
            return false;
        }
    }

    public function cloneDefaults(int $orgId, int $userId): void
    {
        $stmt = $this->db->prepare("SELECT * FROM scoring_categories WHERE org_id IS NULL ORDER BY sort_order ASC, id ASC");
        $stmt->execute();
        $defaults = $stmt->fetchAll();
        if (empty($defaults)) {
            return;
        }
        $insert = $this->db->prepare("INSERT INTO scoring_categories (org_id, name, description, weight, sort_order, created_by) VALUES (:org_id, :name, :description, :weight, :sort_order, :created_by)");
        foreach ($defaults as $cat) {
            $insert->execute([
                ':org_id' => $orgId,
                ':name' => $cat['name'],
                ':description' => $cat['description'],
                ':weight' => $cat['weight'],
                ':sort_order' => $cat['sort_order'],
                ':created_by' => $userId,
            ]);
        }
    }
}
