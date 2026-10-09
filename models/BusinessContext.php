<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';

class BusinessContext
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll(int $orgId): array
    {
        $sql = "SELECT bc.* FROM business_context bc
                INNER JOIN org_members om ON bc.user_id = om.user_id
                WHERE om.org_id = :org_id
                ORDER BY bc.updated_at DESC, bc.id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':org_id', $orgId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getById(int $id, int $orgId): ?array
    {
        $sql = "SELECT bc.* FROM business_context bc
                INNER JOIN org_members om ON bc.user_id = om.user_id
                WHERE bc.id = :id AND om.org_id = :org_id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':org_id', $orgId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("INSERT INTO business_context (user_id, section_type, name, prompt_section, prompt) VALUES (:user_id, :section_type, :name, :prompt_section, :prompt)");
        $stmt->execute([
            ':user_id' => currentUserId(),
            ':section_type' => $data['section_type'] ?? null,
            ':name' => $data['name'] ?? null,
            ':prompt_section' => $data['prompt_section'] ?? null,
            ':prompt' => $data['prompt'] ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data, int $orgId): bool
    {
        // Ensure belongs to org
        if (!$this->getById($id, $orgId)) {
            return false;
        }
        $stmt = $this->db->prepare("UPDATE business_context SET section_type = :section_type, name = :name, prompt_section = :prompt_section, prompt = :prompt WHERE id = :id");
        return $stmt->execute([
            ':section_type' => $data['section_type'] ?? null,
            ':name' => $data['name'] ?? null,
            ':prompt_section' => $data['prompt_section'] ?? null,
            ':prompt' => $data['prompt'] ?? null,
            ':id' => $id,
        ]);
    }

    public function delete(int $id, int $orgId): bool
    {
        if (!$this->getById($id, $orgId)) {
            return false;
        }
        $stmt = $this->db->prepare("DELETE FROM business_context WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function getBySection(string $sectionType, int $orgId): ?array
    {
        $sql = "SELECT bc.* FROM business_context bc
                INNER JOIN org_members om ON bc.user_id = om.user_id
                WHERE bc.section_type = :section_type AND om.org_id = :org_id
                ORDER BY bc.updated_at DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':section_type' => $sectionType,
            ':org_id' => $orgId,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function assemblePrompt(int $orgId): string
    {
        $contexts = $this->getAll($orgId);
        if (empty($contexts)) {
            return '';
        }
        $blocks = [];
        foreach ($contexts as $ctx) {
            $title = $ctx['name'] ?: ($ctx['section_type'] ?: 'Context');
            $blocks[] = strtoupper($title) . ":\n" . trim($ctx['prompt'] ?? '');
        }
        return implode("\n\n", $blocks);
    }
}
