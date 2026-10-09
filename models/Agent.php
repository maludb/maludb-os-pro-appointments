<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';

class Agent
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll(int $orgId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM agents WHERE org_id = :org_id ORDER BY updated_at DESC, created_at DESC");
        $stmt->bindValue(':org_id', $orgId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getById(int $id, int $orgId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM agents WHERE id = :id AND org_id = :org_id LIMIT 1");
        $stmt->execute([
            ':id' => $id,
            ':org_id' => $orgId,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("INSERT INTO agents (ts, org_id, name, retell_agent_id, description, image, voice_name, llm, created_by) VALUES (NOW(), :org_id, :name, :retell_agent_id, :description, :image, :voice_name, :llm, :created_by)");
        $stmt->execute([
            ':org_id' => currentOrgId(),
            ':name' => $data['name'],
            ':retell_agent_id' => $data['retell_agent_id'] ?? null,
            ':description' => $data['description'] ?? null,
            ':image' => $data['image'] ?? 'https://images.skilliks.ai/pending.php',
            ':voice_name' => $data['voice_name'] ?? null,
            ':llm' => $data['llm'] ?? null,
            ':created_by' => currentUserId(),
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data, int $orgId): bool
    {
        $stmt = $this->db->prepare("UPDATE agents SET name = :name, retell_agent_id = :retell_agent_id, description = :description, image = :image, voice_name = :voice_name, llm = :llm WHERE id = :id AND org_id = :org_id");
        return $stmt->execute([
            ':name' => $data['name'],
            ':retell_agent_id' => $data['retell_agent_id'] ?? null,
            ':description' => $data['description'] ?? null,
            ':image' => $data['image'] ?? 'https://images.skilliks.ai/pending.php',
            ':voice_name' => $data['voice_name'] ?? null,
            ':llm' => $data['llm'] ?? null,
            ':id' => $id,
            ':org_id' => $orgId,
        ]);
    }

    public function delete(int $id, int $orgId): bool
    {
        // clean prompts/settings
        $this->db->prepare("DELETE FROM model_prompts WHERE model_id = :id")->execute([':id' => $id]);
        $this->db->prepare("DELETE FROM model_settings WHERE model_id = :id")->execute([':id' => $id]);
        $this->db->prepare("DELETE FROM model_output_settings WHERE model_id = :id")->execute([':id' => $id]);
        $stmt = $this->db->prepare("DELETE FROM agents WHERE id = :id AND org_id = :org_id");
        return $stmt->execute([
            ':id' => $id,
            ':org_id' => $orgId,
        ]);
    }

    public function getPrompts(int $agentId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM model_prompts WHERE model_id = :model_id ORDER BY id ASC");
        $stmt->execute([':model_id' => $agentId]);
        return $stmt->fetchAll();
    }

    public function getPrompt(int $promptId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM model_prompts WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $promptId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createPrompt(array $data): int
    {
        $stmt = $this->db->prepare("INSERT INTO model_prompts (ts, model_id, name, value, created_by) VALUES (NOW(), :model_id, :name, :value, :created_by)");
        $stmt->execute([
            ':model_id' => $data['model_id'],
            ':name' => $data['name'],
            ':value' => $data['value'] ?? null,
            ':created_by' => currentUserId(),
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updatePrompt(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("UPDATE model_prompts SET name = :name, value = :value WHERE id = :id");
        return $stmt->execute([
            ':name' => $data['name'],
            ':value' => $data['value'] ?? null,
            ':id' => $id,
        ]);
    }

    public function deletePrompt(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM model_prompts WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function getSettings(int $agentId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM model_settings WHERE model_id = :model_id ORDER BY name ASC");
        $stmt->execute([':model_id' => $agentId]);
        return $stmt->fetchAll();
    }

    public function saveSetting(int $agentId, string $name, string $value, int $userId): void
    {
        $stmt = $this->db->prepare("SELECT id FROM model_settings WHERE model_id = :model_id AND name = :name LIMIT 1");
        $stmt->execute([':model_id' => $agentId, ':name' => $name]);
        $row = $stmt->fetch();
        if ($row) {
            $upd = $this->db->prepare("UPDATE model_settings SET value = :value WHERE id = :id");
            $upd->execute([':value' => $value, ':id' => $row['id']]);
        } else {
            $ins = $this->db->prepare("INSERT INTO model_settings (ts, model_id, name, value, created_by) VALUES (NOW(), :model_id, :name, :value, :created_by)");
            $ins->execute([
                ':model_id' => $agentId,
                ':name' => $name,
                ':value' => $value,
                ':created_by' => $userId,
            ]);
        }
    }

    public function getOutputSettings(int $agentId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM model_output_settings WHERE model_id = :model_id ORDER BY format ASC");
        $stmt->execute([':model_id' => $agentId]);
        return $stmt->fetchAll();
    }

    public function saveOutputSetting(int $agentId, string $format, string $value, int $userId): void
    {
        $stmt = $this->db->prepare("SELECT id FROM model_output_settings WHERE model_id = :model_id AND format = :format LIMIT 1");
        $stmt->execute([':model_id' => $agentId, ':format' => $format]);
        $row = $stmt->fetch();
        if ($row) {
            $upd = $this->db->prepare("UPDATE model_output_settings SET value = :value WHERE id = :id");
            $upd->execute([':value' => $value, ':id' => $row['id']]);
        } else {
            $ins = $this->db->prepare("INSERT INTO model_output_settings (ts, model_id, format, value, created_by) VALUES (NOW(), :model_id, :format, :value, :created_by)");
            $ins->execute([
                ':model_id' => $agentId,
                ':format' => $format,
                ':value' => $value,
                ':created_by' => $userId,
            ]);
        }
    }
}
