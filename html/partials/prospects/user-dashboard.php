<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../config/database.php';

check_auth();
$user = get_user();

// Get prospect ID from request
$prospectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$prospectId) {
    echo '<div class="alert alert-danger">Prospect ID is required.</div>';
    exit;
}

$db = Database::getInstance()->getConnection();

// Get prospect info
$stmt = $db->prepare("SELECT * FROM prospects WHERE id = ?");
$stmt->execute([$prospectId]);
$prospect = $stmt->fetch();

if (!$prospect) {
    echo '<div class="alert alert-danger">Prospect not found.</div>';
    exit;
}

// Get agents for this prospect
$stmt = $db->prepare("SELECT * FROM prospect_agents WHERE prospect_id = ? ORDER BY agent_name ASC");
$stmt->execute([$prospectId]);
$agents = $stmt->fetchAll();

// Define badge colors for agent cards
$badgeColors = [
    ['bg' => 'bg-primary-subtle', 'text' => 'text-primary'],
    ['bg' => 'bg-success-subtle', 'text' => 'text-success'],
    ['bg' => 'bg-warning-subtle', 'text' => 'text-warning'],
    ['bg' => 'bg-info-subtle', 'text' => 'text-info'],
    ['bg' => 'bg-danger-subtle', 'text' => 'text-danger'],
];

$imagePath = !empty($prospect['image']) ? htmlspecialchars($prospect['image']) : '/assets/images/default-prospect.png';
?>

<!-- User Prospect Dashboard Page Container -->
<div class="container-fluid pt-4 ps-4" id="user-prospect-dashboard-page">
    <!-- Page Header -->
    <div class="row mb-4" id="user-prospect-header">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center" id="user-prospect-header-content">
                <div id="user-prospect-header-title">
                    <nav aria-label="breadcrumb" id="user-prospect-breadcrumb">
                        <ol class="breadcrumb mb-1">
                            <li class="breadcrumb-item">
                                <a href="#" hx-get="/partials/dashboard/index.php" hx-target="#page-content">Home</a>
                            </li>
                            <li class="breadcrumb-item active"><?= htmlspecialchars($prospect['name']) ?></li>
                        </ol>
                    </nav>
                    <h3 class="mb-0"><?= htmlspecialchars($prospect['name']) ?></h3>
                    <?php if (!empty($prospect['business_type'])): ?>
                        <p class="text-muted mb-0"><?= htmlspecialchars($prospect['business_type']) ?></p>
                    <?php endif; ?>
                </div>
                <div id="user-prospect-header-actions">
                    <a href="#"
                       class="btn btn-outline-secondary"
                       id="btn-back-to-home"
                       hx-get="/partials/dashboard/index.php"
                       hx-target="#page-content">
                        <i class="feather-arrow-left me-2"></i>Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Prospect Info Card -->
    <div class="row mb-4" id="user-prospect-info-row">
        <div class="col-md-4" id="user-prospect-image-col">
            <div class="card" id="user-prospect-image-card">
                <div class="card-img-header-large" id="user-prospect-image-container">
                    <img src="<?= $imagePath ?>" alt="<?= htmlspecialchars($prospect['name']) ?>" class="card-img-top" id="user-prospect-image">
                </div>
            </div>
        </div>
        <div class="col-md-8" id="user-prospect-details-col">
            <div class="card h-100" id="user-prospect-details-card">
                <div class="card-header" id="user-prospect-details-header">
                    <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                        <h5 class="card-title mb-0">
                            <i class="feather-info me-2"></i>About <?= htmlspecialchars($prospect['name']) ?>
                        </h5>
                        <div class="card border-0 bg-transparent p-0 retell-voice-card retell-launch-card"
                             id="user-prospect-launch-agent"
                             data-retell-agent-id="agent_e2a5523391fcd589c7b81a6808">
                            <div class="card-body p-0 d-flex align-items-center">
                                <button type="button" class="btn btn-success btn-sm">
                                    <i class="feather-phone-call me-1"></i>Launch Agent
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body" id="user-prospect-details-body">
                    <?php if (!empty($prospect['description'])): ?>
                        <p class="mb-3" id="user-prospect-description"><?= htmlspecialchars($prospect['description']) ?></p>
                    <?php endif; ?>
                    <div class="row" id="user-prospect-meta">
                        <?php if (!empty($prospect['business_type'])): ?>
                        <div class="col-sm-6 mb-2" id="user-prospect-type-col">
                            <strong>Business Type:</strong><br>
                            <span class="text-muted"><?= htmlspecialchars($prospect['business_type']) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="col-sm-6 mb-2" id="user-prospect-agents-count-col">
                            <strong>Available Agents:</strong><br>
                            <span class="text-muted"><?= count($agents) ?> agent(s)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Agents Section -->
    <div class="row mb-4" id="user-prospect-agents-section">
        <div class="col-12">
            <h4 class="mb-3" id="user-prospect-agents-title">
                <i class="feather-users me-2"></i>Available Agents
            </h4>
        </div>
    </div>

    <?php if (empty($agents)): ?>
        <div class="row" id="user-prospect-no-agents-row">
            <div class="col-12">
                <div class="card" id="user-prospect-no-agents-card">
                    <div class="card-body text-center py-5" id="user-prospect-no-agents-body">
                        <i class="feather-user-x text-muted" style="font-size: 48px;"></i>
                        <h5 class="mt-3 text-muted">No Agents Available</h5>
                        <p class="text-muted">There are no agents configured for this prospect yet.</p>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row gx-4" id="user-prospect-agents-container">
            <?php foreach ($agents as $index => $agent):
                $colorIndex = $index % count($badgeColors);
                $badge = $badgeColors[$colorIndex];
                // Use the agent_id field from prospects table or a default
                $retellAgentId = !empty($agent['agent_id']) ? $agent['agent_id'] : (!empty($prospect['agent_id']) ? $prospect['agent_id'] : '');
            ?>
            <div class="col-xl-6 col-md-6 mb-3" id="agent-card-col-<?= $agent['id'] ?>">
                <div class="card stretch stretch-full retell-voice-card <?= !empty($retellAgentId) ? '' : 'disabled-card' ?>"
                     id="agent-card-<?= $agent['id'] ?>"
                     <?php if (!empty($retellAgentId)): ?>
                     data-retell-agent-id="<?= htmlspecialchars($retellAgentId) ?>"
                     style="cursor: pointer;"
                     <?php endif; ?>>
                    <div class="card-body" id="agent-card-body-<?= $agent['id'] ?>">
                        <div class="d-flex align-items-center mb-3" id="agent-card-header-<?= $agent['id'] ?>">
                            <div class="avatar avatar-lg bg-<?= str_replace('bg-', '', explode(' ', $badge['bg'])[0]) ?> rounded-circle me-3" id="agent-avatar-<?= $agent['id'] ?>">
                                <i class="feather-user <?= $badge['text'] ?>"></i>
                            </div>
                            <div id="agent-info-<?= $agent['id'] ?>">
                                <h5 class="card-title fw-bold mb-1" id="agent-name-<?= $agent['id'] ?>"><?= htmlspecialchars($agent['agent_name'] ?: 'Agent #' . $agent['id']) ?></h5>
                                <span class="badge bg-secondary" id="agent-role-<?= $agent['id'] ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $agent['agent_role']))) ?></span>
                            </div>
                        </div>
                        <?php if (!empty($agent['agent_description'])): ?>
                            <p class="card-text text-muted fs-13 mb-3" id="agent-desc-<?= $agent['id'] ?>"><?= htmlspecialchars($agent['agent_description']) ?></p>
                        <?php endif; ?>
                        <div class="row mb-3" id="agent-stats-<?= $agent['id'] ?>">
                            <div class="col-6" id="agent-temperament-col-<?= $agent['id'] ?>">
                                <small class="text-muted">Temperament</small><br>
                                <span class="fw-medium"><?= htmlspecialchars(ucfirst($agent['default_temprement'])) ?></span>
                            </div>
                            <div class="col-6" id="agent-knowledge-col-<?= $agent['id'] ?>">
                                <small class="text-muted">Knowledge Level</small><br>
                                <span class="fw-medium"><?= $agent['product_knowledge'] ?>/10</span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center justify-content-between" id="agent-card-footer-<?= $agent['id'] ?>">
                            <?php if (!empty($retellAgentId)): ?>
                                <span class="badge <?= $badge['bg'] ?> <?= $badge['text'] ?>"><i class="feather-phone me-1"></i> Start Call</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary"><i class="feather-phone-off me-1"></i> No Agent ID</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
/* Large Image Header */
.card-img-header-large {
    height: 250px;
    overflow: hidden;
    background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
    border-radius: 0.5rem;
}

.card-img-header-large img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Avatar styles */
.avatar {
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.avatar-lg {
    width: 60px;
    height: 60px;
    font-size: 1.5rem;
}

#user-prospect-details-header .retell-launch-card {
    transition: none;
}

#user-prospect-details-header .retell-launch-card:hover {
    transform: none;
    box-shadow: none;
}

#user-prospect-details-header .retell-launch-card .retell-call-indicator {
    margin-top: 6px;
}

/* Retell Voice Call Styles */
.retell-voice-card {
    transition: all 0.3s ease;
}

.retell-voice-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.retell-voice-card.disabled-card {
    opacity: 0.7;
    cursor: not-allowed !important;
}

.retell-voice-card.disabled-card:hover {
    transform: none;
}

.retell-call-connecting {
    border: 2px solid #ffc107 !important;
    animation: pulse-border 1.5s infinite;
}

.retell-call-active {
    border: 2px solid #28a745 !important;
    box-shadow: 0 0 15px rgba(40, 167, 69, 0.3) !important;
}

@keyframes pulse-border {
    0%, 100% { border-color: #ffc107; }
    50% { border-color: #ff9800; }
}

.retell-call-indicator {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 8px 12px;
    margin-top: 10px;
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.retell-call-connecting .retell-call-indicator {
    background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
}

.retell-end-call-btn {
    width: 100%;
}
</style>
