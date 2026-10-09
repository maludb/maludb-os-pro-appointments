<?php
require_once '/var/www/helpers/auth.php';
require_once '/var/www/models/Session.php';
requireAuth();

$sessionId = (int)($_POST['session_id'] ?? 0);
$transcript = $_POST['transcript'] ?? '';
$durationMs = (int)($_POST['duration_ms'] ?? 0);

$sessionModel = new Session();
if ($sessionId > 0) {
    $sessionModel->update($sessionId, [
        'transcript' => $transcript,
        'duration_ms' => $durationMs,
    ]);
}

echo '<div class="alert alert-success">Session saved. Review coming soon.</div>';
?>
