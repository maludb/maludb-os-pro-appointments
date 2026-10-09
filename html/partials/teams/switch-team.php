<?php
/**
 * Team Switcher Partial
 *
 * Handles team switching for the user
 */

session_start();
require_once '../../../helpers/auth.php';
require_once '../../../models/Team.php';

check_auth();
$user = get_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get team ID from request
    $teamId = isset($_POST['team_id']) ? (int)$_POST['team_id'] : null;

    if (!$teamId) {
        http_response_code(400);
        echo '<div class="alert alert-danger">Invalid team selection</div>';
        exit;
    }

    // Verify user is a member of the selected team
    $teamModel = new Team();
    if (!$teamModel->isTeamMember($user['id'], $teamId)) {
        http_response_code(403);
        echo '<div class="alert alert-danger">You are not a member of this team</div>';
        exit;
    }

    // Get team details
    $teamDetails = $teamModel->getTeamDetails($teamId);
    if (!$teamDetails) {
        http_response_code(404);
        echo '<div class="alert alert-danger">Team not found</div>';
        exit;
    }

    // Update session with selected team
    $_SESSION['selected_team_id'] = $teamId;

    // Send HX-Trigger event to refresh the dashboard
    header('HX-Trigger: ' . json_encode([
        'teamSwitched' => [
            'teamId' => $teamId,
            'teamName' => $teamDetails['name']
        ]
    ]));

    // Return success message (optional)
    echo '<div class="alert alert-success">Switched to team: ' . htmlspecialchars($teamDetails['name']) . '</div>';
    exit;
}

// If GET request, show error
http_response_code(405);
echo '<div class="alert alert-danger">Method not allowed</div>';
