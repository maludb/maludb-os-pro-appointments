<?php
/**
 * Fetch active prompt templates for dropdown.
 * Returns JSON array.
 */
require_once __DIR__ . '/../../../helpers/auth.php';

requireAuth();
header('Content-Type: application/json');

$pdo = db();
$stmt = $pdo->prepare("SELECT id, name, description, agent_prompt, begin_message FROM prompt_templates WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
$stmt->execute();
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
