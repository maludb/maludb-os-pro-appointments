<?php
require_once __DIR__ . '/../config/database.php';

class Plan {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll(): array {
        $stmt = $this->db->query("SELECT * FROM plans ORDER BY sort_order ASC");
        return $stmt->fetchAll();
    }

    public function getActivePlans(): array {
        $stmt = $this->db->query("SELECT * FROM plans WHERE is_active = 1 ORDER BY sort_order ASC");
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM plans WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $plan = $stmt->fetch();
        return $plan ?: null;
    }

    public function getBySlug(string $slug): ?array {
        $stmt = $this->db->prepare("SELECT * FROM plans WHERE slug = :slug");
        $stmt->execute(['slug' => $slug]);
        $plan = $stmt->fetch();
        return $plan ?: null;
    }

    public function getFeatures(int $planId): array {
        $plan = $this->getById($planId);
        if (!$plan || empty($plan['features'])) {
            return [];
        }
        return json_decode($plan['features'], true) ?: [];
    }

    public function hasFeature(int $planId, string $feature): bool {
        $features = $this->getFeatures($planId);
        return !empty($features[$feature]);
    }
}
