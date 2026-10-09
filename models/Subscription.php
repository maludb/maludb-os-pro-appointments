<?php
require_once __DIR__ . '/../config/database.php';

class Subscription {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO subscriptions (org_id, user_id, plan_id, status, billing_cycle, current_period_start, current_period_end)
            VALUES (:org_id, :user_id, :plan_id, :status, :billing_cycle, :period_start, :period_end)
        ");
        $stmt->execute([
            'org_id' => $data['org_id'] ?? null,
            'user_id' => $data['user_id'],
            'plan_id' => $data['plan_id'] ?? 1,
            'status' => $data['status'] ?? 'active',
            'billing_cycle' => $data['billing_cycle'] ?? 'monthly',
            'period_start' => $data['current_period_start'] ?? date('Y-m-d'),
            'period_end' => $data['current_period_end'] ?? date('Y-m-d', strtotime('+1 month')),
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function getByUserId(int $userId): ?array {
        $stmt = $this->db->prepare("
            SELECT s.*, p.name as plan_name, p.slug as plan_slug, p.max_coaching_minutes, p.max_users, p.features
            FROM subscriptions s
            JOIN plans p ON s.plan_id = p.id
            WHERE s.user_id = :user_id AND s.status = 'active'
            ORDER BY s.created_at DESC LIMIT 1
        ");
        $stmt->execute(['user_id' => $userId]);
        $sub = $stmt->fetch();
        return $sub ?: null;
    }

    public function getByOrgId(int $orgId): ?array {
        $stmt = $this->db->prepare("
            SELECT s.*, p.name as plan_name, p.slug as plan_slug, p.max_coaching_minutes, p.max_users, p.features
            FROM subscriptions s
            JOIN plans p ON s.plan_id = p.id
            WHERE s.org_id = :org_id AND s.status = 'active'
            ORDER BY s.created_at DESC LIMIT 1
        ");
        $stmt->execute(['org_id' => $orgId]);
        $sub = $stmt->fetch();
        return $sub ?: null;
    }

    public function update(int $id, array $data): bool {
        $fields = [];
        $params = ['id' => $id];
        foreach (['plan_id', 'status', 'billing_cycle', 'current_period_start', 'current_period_end'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }
        if (empty($fields)) return false;
        $sql = "UPDATE subscriptions SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function cancel(int $id): bool {
        return $this->update($id, ['status' => 'canceled']);
    }

    public function changePlan(int $userId, int $newPlanId, ?int $orgId = null): bool {
        $this->db->beginTransaction();
        try {
            // Cancel existing active subscription
            $stmt = $this->db->prepare("UPDATE subscriptions SET status = 'canceled' WHERE user_id = :user_id AND status = 'active'");
            $stmt->execute(['user_id' => $userId]);

            // Create new subscription
            $this->create([
                'user_id' => $userId,
                'org_id' => $orgId,
                'plan_id' => $newPlanId,
                'status' => 'active',
                'billing_cycle' => 'monthly',
                'current_period_start' => date('Y-m-d'),
                'current_period_end' => date('Y-m-d', strtotime('+1 month')),
            ]);

            // Update user's plan_id
            $stmt = $this->db->prepare("UPDATE users SET plan_id = :plan_id WHERE id = :user_id");
            $stmt->execute(['plan_id' => $newPlanId, 'user_id' => $userId]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Plan change error: " . $e->getMessage());
            return false;
        }
    }
}
