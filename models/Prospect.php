<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';

class Prospect
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll(int $orgId, array $filters = [], int $limit = 12, int $offset = 0): array
    {
        $sql = "SELECT * FROM prospects WHERE org_id = :org_id";
        $params = ['org_id' => $orgId];

        if (!empty($filters['search'])) {
            $sql .= " AND (name LIKE :search OR prospect_name LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['industry'])) {
            $sql .= " AND industry = :industry";
            $params['industry'] = $filters['industry'];
        }
        if (!empty($filters['difficulty_level'])) {
            $sql .= " AND difficulty_level = :difficulty";
            $params['difficulty'] = $filters['difficulty_level'];
        }
        if (!empty($filters['prospect_personality'])) {
            $sql .= " AND prospect_personality = :personality";
            $params['personality'] = $filters['prospect_personality'];
        }

        $sql .= " ORDER BY updated_at DESC, created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $paramType = ($key === 'org_id') ? PDO::PARAM_INT : PDO::PARAM_STR;
            if ($key === 'industry' || $key === 'difficulty' || $key === 'personality' || $key === 'search') {
                $paramType = PDO::PARAM_STR;
            }
            $stmt->bindValue(':' . $key, $value, $paramType);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countAll(int $orgId, array $filters = []): int
    {
        $sql = "SELECT COUNT(*) as cnt FROM prospects WHERE org_id = :org_id";
        $params = ['org_id' => $orgId];

        if (!empty($filters['search'])) {
            $sql .= " AND (name LIKE :search OR prospect_name LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['industry'])) {
            $sql .= " AND industry = :industry";
            $params['industry'] = $filters['industry'];
        }
        if (!empty($filters['difficulty_level'])) {
            $sql .= " AND difficulty_level = :difficulty";
            $params['difficulty'] = $filters['difficulty_level'];
        }
        if (!empty($filters['prospect_personality'])) {
            $sql .= " AND prospect_personality = :personality";
            $params['personality'] = $filters['prospect_personality'];
        }

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $paramType = ($key === 'org_id') ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue(':' . $key, $value, $paramType);
        }
        $stmt->execute();
        $row = $stmt->fetch();
        return (int)($row['cnt'] ?? 0);
    }

    public function getById(int $id, int $orgId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM prospects WHERE id = :id AND org_id = :org_id LIMIT 1");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':org_id', $orgId, PDO::PARAM_INT);
        $stmt->execute();
        $prospect = $stmt->fetch();
        return $prospect ?: null;
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO prospects (
                    ts, user_id, org_id, name, business_type, stub, description, members,
                    monthly_spend_limit, monthly_spend, created_by, image, agent_id,
                    prospect_name, prospect_role, prospect_personality, prospect_talkativeness,
                    prosepect_communication_style, industry, company_size, current_solution,
                    tools_used, recent_events, lead_volume, difficulty_level, call_types
                ) VALUES (
                    NOW(), :user_id, :org_id, :name, :business_type, :stub, :description, :members,
                    :monthly_spend_limit, :monthly_spend, :created_by, :image, :agent_id,
                    :prospect_name, :prospect_role, :prospect_personality, :prospect_talkativeness,
                    :prosepect_communication_style, :industry, :company_size, :current_solution,
                    :tools_used, :recent_events, :lead_volume, :difficulty_level, :call_types
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => currentUserId(),
            ':org_id' => currentOrgId(),
            ':name' => $data['name'],
            ':business_type' => $data['business_type'] ?? null,
            ':stub' => $data['stub'] ?? null,
            ':description' => $data['description'] ?? null,
            ':members' => $data['members'] ?? 0,
            ':monthly_spend_limit' => $data['monthly_spend_limit'] ?? 0,
            ':monthly_spend' => $data['monthly_spend'] ?? 0,
            ':created_by' => currentUserId(),
            ':image' => $data['image'] ?? 'https://images.skilliks.ai/pending.php',
            ':agent_id' => $data['agent_id'] ?? null,
            ':prospect_name' => $data['prospect_name'] ?? null,
            ':prospect_role' => $data['prospect_role'] ?? null,
            ':prospect_personality' => $data['prospect_personality'] ?? 'Busy & skeptical',
            ':prospect_talkativeness' => $data['prospect_talkativeness'] ?? 'Low',
            ':prosepect_communication_style' => $data['prosepect_communication_style'] ?? null,
            ':industry' => $data['industry'] ?? null,
            ':company_size' => $data['company_size'] ?? null,
            ':current_solution' => $data['current_solution'] ?? null,
            ':tools_used' => $data['tools_used'] ?? null,
            ':recent_events' => $data['recent_events'] ?? null,
            ':lead_volume' => $data['lead_volume'] ?? null,
            ':difficulty_level' => $data['difficulty_level'] ?? 'intermediate',
            ':call_types' => $data['call_types'] ?? 'cold_call,discovery,demo,objection_handling,closing,renewal',
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data, int $orgId): bool
    {
        $sql = "UPDATE prospects SET
                    name = :name,
                    business_type = :business_type,
                    stub = :stub,
                    description = :description,
                    members = :members,
                    monthly_spend_limit = :monthly_spend_limit,
                    monthly_spend = :monthly_spend,
                    image = :image,
                    agent_id = :agent_id,
                    prospect_name = :prospect_name,
                    prospect_role = :prospect_role,
                    prospect_personality = :prospect_personality,
                    prospect_talkativeness = :prospect_talkativeness,
                    prosepect_communication_style = :prosepect_communication_style,
                    industry = :industry,
                    company_size = :company_size,
                    current_solution = :current_solution,
                    tools_used = :tools_used,
                    recent_events = :recent_events,
                    lead_volume = :lead_volume,
                    difficulty_level = :difficulty_level,
                    call_types = :call_types
                WHERE id = :id AND org_id = :org_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':name' => $data['name'],
            ':business_type' => $data['business_type'] ?? null,
            ':stub' => $data['stub'] ?? null,
            ':description' => $data['description'] ?? null,
            ':members' => $data['members'] ?? 0,
            ':monthly_spend_limit' => $data['monthly_spend_limit'] ?? 0,
            ':monthly_spend' => $data['monthly_spend'] ?? 0,
            ':image' => $data['image'] ?? 'https://images.skilliks.ai/pending.php',
            ':agent_id' => $data['agent_id'] ?? null,
            ':prospect_name' => $data['prospect_name'] ?? null,
            ':prospect_role' => $data['prospect_role'] ?? null,
            ':prospect_personality' => $data['prospect_personality'] ?? 'Busy & skeptical',
            ':prospect_talkativeness' => $data['prospect_talkativeness'] ?? 'Low',
            ':prosepect_communication_style' => $data['prosepect_communication_style'] ?? null,
            ':industry' => $data['industry'] ?? null,
            ':company_size' => $data['company_size'] ?? null,
            ':current_solution' => $data['current_solution'] ?? null,
            ':tools_used' => $data['tools_used'] ?? null,
            ':recent_events' => $data['recent_events'] ?? null,
            ':lead_volume' => $data['lead_volume'] ?? null,
            ':difficulty_level' => $data['difficulty_level'] ?? 'intermediate',
            ':call_types' => $data['call_types'] ?? 'cold_call,discovery,demo,objection_handling,closing,renewal',
            ':id' => $id,
            ':org_id' => $orgId
        ]);
    }

    public function delete(int $id, int $orgId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM prospects WHERE id = :id AND org_id = :org_id");
        return $stmt->execute([
            ':id' => $id,
            ':org_id' => $orgId
        ]);
    }

    public function incrementUsage(int $id): void
    {
        $stmt = $this->db->prepare("UPDATE prospects SET usage_count = usage_count + 1 WHERE id = :id AND org_id = :org_id");
        $stmt->execute([
            ':id' => $id,
            ':org_id' => currentOrgId()
        ]);
    }

    public function getAgents(int $prospectId): array
    {
        $stmt = $this->db->prepare("SELECT pa.*, pa.default_temprement AS default_temperament
            FROM prospect_agents pa
            INNER JOIN prospects p ON pa.prospect_id = p.id
            WHERE pa.prospect_id = :prospect_id AND p.org_id = :org_id
            ORDER BY pa.agent_name ASC");
        $stmt->execute([
            ':prospect_id' => $prospectId,
            ':org_id' => currentOrgId()
        ]);
        return $stmt->fetchAll();
    }

    public function getAgent(int $agentId): ?array
    {
        $stmt = $this->db->prepare("SELECT pa.*, pa.default_temprement AS default_temperament, p.org_id
            FROM prospect_agents pa
            INNER JOIN prospects p ON pa.prospect_id = p.id
            WHERE pa.id = :agent_id AND p.org_id = :org_id LIMIT 1");
        $stmt->execute([
            ':agent_id' => $agentId,
            ':org_id' => currentOrgId()
        ]);
        $agent = $stmt->fetch();
        return $agent ?: null;
    }

    public function createAgent(array $data): int
    {
        $stmt = $this->db->prepare("INSERT INTO prospect_agents (
            ts, prospect_id, agent_name, agent_description, agent_prompt, agent_role,
            default_temprement, product_knowledge, technical_level, created_by
        ) VALUES (
            NOW(), :prospect_id, :agent_name, :agent_description, :agent_prompt, :agent_role,
            :default_temprement, :product_knowledge, :technical_level, :created_by
        )");

        $stmt->execute([
            ':prospect_id' => $data['prospect_id'],
            ':agent_name' => $data['agent_name'],
            ':agent_description' => $data['agent_description'] ?? null,
            ':agent_prompt' => $data['agent_prompt'] ?? null,
            ':agent_role' => $data['agent_role'] ?? 'caller',
            ':default_temprement' => $data['default_temperament'] ?? 'friendly',
            ':product_knowledge' => $data['product_knowledge'] ?? 5,
            ':technical_level' => $data['technical_level'] ?? 5,
            ':created_by' => currentUserId()
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function updateAgent(int $id, array $data): bool
    {
        $agent = $this->getAgent($id);
        if (!$agent) {
            return false;
        }

        $stmt = $this->db->prepare("UPDATE prospect_agents SET
            agent_name = :agent_name,
            agent_description = :agent_description,
            agent_prompt = :agent_prompt,
            agent_role = :agent_role,
            default_temprement = :default_temprement,
            product_knowledge = :product_knowledge,
            technical_level = :technical_level
            WHERE id = :id");

        return $stmt->execute([
            ':agent_name' => $data['agent_name'],
            ':agent_description' => $data['agent_description'] ?? null,
            ':agent_prompt' => $data['agent_prompt'] ?? null,
            ':agent_role' => $data['agent_role'] ?? 'caller',
            ':default_temprement' => $data['default_temperament'] ?? 'friendly',
            ':product_knowledge' => $data['product_knowledge'] ?? 5,
            ':technical_level' => $data['technical_level'] ?? 5,
            ':id' => $id
        ]);
    }

    public function deleteAgent(int $id): bool
    {
        $agent = $this->getAgent($id);
        if (!$agent) {
            return false;
        }
        $stmt = $this->db->prepare("DELETE FROM prospect_agents WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
