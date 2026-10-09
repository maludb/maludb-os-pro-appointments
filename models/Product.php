<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';

class Product
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll(int $orgId, ?string $category = null, ?string $search = null, int $limit = 12, int $offset = 0): array
    {
        $sql = "SELECT * FROM products WHERE org_id = :org_id";
        $params = [':org_id' => $orgId];

        if (!empty($category)) {
            $sql .= " AND product_category = :category";
            $params[':category'] = $category;
        }
        if (!empty($search)) {
            $sql .= " AND (name LIKE :search OR prompt LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        $sql .= " ORDER BY updated_at DESC, created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, $key === ':org_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countAll(int $orgId, ?string $category = null, ?string $search = null): int
    {
        $sql = "SELECT COUNT(*) as cnt FROM products WHERE org_id = :org_id";
        $params = [':org_id' => $orgId];

        if (!empty($category)) {
            $sql .= " AND product_category = :category";
            $params[':category'] = $category;
        }
        if (!empty($search)) {
            $sql .= " AND (name LIKE :search OR prompt LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, $key === ':org_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $row = $stmt->fetch();
        return (int)($row['cnt'] ?? 0);
    }

    public function getById(int $id, int $orgId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM products WHERE id = :id AND org_id = :org_id LIMIT 1");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':org_id', $orgId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("INSERT INTO products (
            ts, user_id, org_id, name, product_category, prompt, knowledgebase,
            features_json, pricing_info, competitive_notes, created_by
        ) VALUES (
            NOW(), :user_id, :org_id, :name, :product_category, :prompt, :knowledgebase,
            :features_json, :pricing_info, :competitive_notes, :created_by
        )");

        $stmt->execute([
            ':user_id' => currentUserId(),
            ':org_id' => currentOrgId(),
            ':name' => $data['name'],
            ':product_category' => $data['product_category'] ?? '',
            ':prompt' => $data['prompt'] ?? '',
            ':knowledgebase' => $data['knowledgebase'] ?? null,
            ':features_json' => $data['features_json'] ?? null,
            ':pricing_info' => $data['pricing_info'] ?? null,
            ':competitive_notes' => $data['competitive_notes'] ?? null,
            ':created_by' => currentUserId(),
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data, int $orgId): bool
    {
        $stmt = $this->db->prepare("UPDATE products SET
            name = :name,
            product_category = :product_category,
            prompt = :prompt,
            knowledgebase = :knowledgebase,
            features_json = :features_json,
            pricing_info = :pricing_info,
            competitive_notes = :competitive_notes
            WHERE id = :id AND org_id = :org_id");

        return $stmt->execute([
            ':name' => $data['name'],
            ':product_category' => $data['product_category'] ?? '',
            ':prompt' => $data['prompt'] ?? '',
            ':knowledgebase' => $data['knowledgebase'] ?? null,
            ':features_json' => $data['features_json'] ?? null,
            ':pricing_info' => $data['pricing_info'] ?? null,
            ':competitive_notes' => $data['competitive_notes'] ?? null,
            ':id' => $id,
            ':org_id' => $orgId,
        ]);
    }

    public function delete(int $id, int $orgId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM products WHERE id = :id AND org_id = :org_id");
        return $stmt->execute([
            ':id' => $id,
            ':org_id' => $orgId,
        ]);
    }

    public function getCategories(int $orgId): array
    {
        $stmt = $this->db->prepare("SELECT DISTINCT product_category FROM products WHERE org_id = :org_id AND product_category IS NOT NULL AND product_category <> '' ORDER BY product_category ASC");
        $stmt->bindValue(':org_id', $orgId, PDO::PARAM_INT);
        $stmt->execute();
        return array_column($stmt->fetchAll(), 'product_category');
    }
}
