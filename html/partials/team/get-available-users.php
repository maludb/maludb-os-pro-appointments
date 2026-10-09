<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../models/Team.php';
require_once __DIR__ . '/../../../models/User.php';
require_once __DIR__ . '/../../../config/database.php';

check_auth();
is_admin() or die('Access denied');

$teamId = isset($_GET['team_id']) ? (int)$_GET['team_id'] : null;

if (!$teamId) {
    echo '<option value="">-- Select a User --</option>';
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Get all users who are NOT members of this team
    $sql = "SELECT u.id, u.first_name, u.last_name, u.email
            FROM users u
            WHERE u.id NOT IN (
                SELECT user_id FROM team_members WHERE team_id = :team_id
            )
            ORDER BY u.first_name ASC, u.last_name ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute([':team_id' => $teamId]);
    $availableUsers = $stmt->fetchAll();

    if (empty($availableUsers)) {
        echo '<option value="">-- All users are already members --</option>';
    } else {
        echo '<option value="">-- Select a User --</option>';
        foreach ($availableUsers as $u) {
            echo '<option value="' . $u['id'] . '">';
            echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) . ' (' . htmlspecialchars($u['email']) . ')';
            echo '</option>';
        }
    }
} catch (PDOException $e) {
    error_log("Error getting available users: " . $e->getMessage());
    echo '<option value="">-- Error loading users --</option>';
}
