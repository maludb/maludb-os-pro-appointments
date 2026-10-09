<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';

class Invitation {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getByOrgId(int $orgId): array {
        $stmt = $this->db->prepare("SELECT * FROM invitations WHERE org_id = :org_id ORDER BY created_at DESC");
        $stmt->bindValue(':org_id', $orgId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getPendingByEmail(string $email): ?array {
        $stmt = $this->db->prepare("SELECT * FROM invitations WHERE email = :email AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("INSERT INTO invitations (org_id, name, email, role, invited_by) VALUES (:org_id, :name, :email, :role, :invited_by)");
        $stmt->execute([
            ':org_id' => $data['org_id'],
            ':name' => $data['name'],
            ':email' => $data['email'],
            ':role' => $data['role'] ?? 'member',
            ':invited_by' => $data['invited_by'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function markAccepted(int $id): bool {
        $stmt = $this->db->prepare("UPDATE invitations SET status = 'accepted' WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM invitations WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
