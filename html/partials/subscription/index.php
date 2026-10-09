<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../models/Plan.php';
require_once __DIR__ . '/../../../models/Subscription.php';

requireAuth();

$userId = currentUserId();
$planModel = new Plan();
$subModel = new Subscription();

$subscription = $subModel->getByUserId($userId);
$allPlans = $planModel->getActivePlans();
$currentPlanId = (int)($_SESSION['user']['plan_id'] ?? 1);
$currentPlan = $planModel->getById($currentPlanId);

// Handle plan change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_plan') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo '<div class="alert alert-danger" id="sub-error-csrf">Invalid security token.</div>';
        exit;
    }
    $newPlanId = (int)($_POST['new_plan_id'] ?? 0);
    $newPlan = $planModel->getById($newPlanId);
    if (!$newPlan) {
        echo '<div class="alert alert-danger" id="sub-error-plan">Invalid plan selected.</div>';
        exit;
    }

    $orgId = currentOrgId();
    if ($subModel->changePlan($userId, $newPlanId, $orgId)) {
        $_SESSION['user']['plan_id'] = $newPlanId;
        echo '<div class="alert alert-success" id="sub-success-change">Plan changed to ' . htmlspecialchars($newPlan['name']) . ' successfully!</div>';
        // Refresh the page content
        header('HX-Trigger: planChanged');
    } else {
        echo '<div class="alert alert-danger" id="sub-error-change">Failed to change plan. Please try again.</div>';
    }
    exit;
}

$features = $currentPlan ? json_decode($currentPlan['features'] ?? '{}', true) : [];
$csrf_token = generate_csrf_token();
?>

<div class="main-content" id="subscription-page">
    <div class="row" id="subscription-header-row">
        <div class="col-12">
            <div class="d-flex align-items-center justify-content-between mb-4" id="subscription-title-bar">
                <h4 class="fw-bold m-0" id="subscription-page-title">
                    <i class="feather-credit-card me-2"></i>Subscription
                </h4>
            </div>
        </div>
    </div>

    <!-- Current Plan Card -->
    <div class="row mb-4" id="current-plan-row">
        <div class="col-lg-8" id="current-plan-col">
            <div class="card" id="current-plan-card">
                <div class="card-body" id="current-plan-body">
                    <div class="d-flex justify-content-between align-items-start" id="current-plan-header">
                        <div id="current-plan-info">
                            <h5 class="card-title fw-bold" id="current-plan-name">
                                <?= htmlspecialchars($currentPlan['name'] ?? 'Free') ?> Plan
                            </h5>
                            <p class="text-muted mb-2" id="current-plan-desc"><?= htmlspecialchars($currentPlan['description'] ?? '') ?></p>
                        </div>
                        <div class="text-end" id="current-plan-price-display">
                            <span class="fs-1 fw-bold" style="color: #667eea;" id="current-plan-price-amount">$<?= number_format($currentPlan['price_monthly'] ?? 0, 0) ?></span>
                            <span class="text-muted">/mo</span>
                        </div>
                    </div>
                    <hr>
                    <div class="row" id="current-plan-limits-row">
                        <div class="col-md-4 mb-3" id="coaching-limit-col">
                            <div class="d-flex align-items-center" id="coaching-limit-display">
                                <i class="feather-clock fs-4 me-2" style="color: #667eea;"></i>
                                <div>
                                    <small class="text-muted d-block">Coaching Time</small>
                                    <strong><?php
                                        $mins = (int)($currentPlan['max_coaching_minutes'] ?? 30);
                                        if ($mins >= 60) {
                                            echo ($mins / 60) . ' hours';
                                        } else {
                                            echo $mins . ' min';
                                        }
                                    ?> / month</strong>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3" id="users-limit-col">
                            <div class="d-flex align-items-center" id="users-limit-display">
                                <i class="feather-users fs-4 me-2" style="color: #667eea;"></i>
                                <div>
                                    <small class="text-muted d-block">Team Members</small>
                                    <strong><?= (int)($currentPlan['max_users'] ?? 1) ?> user<?= ($currentPlan['max_users'] ?? 1) > 1 ? 's' : '' ?></strong>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3" id="status-col">
                            <div class="d-flex align-items-center" id="status-display">
                                <i class="feather-check-circle fs-4 me-2" style="color: #4CAF50;"></i>
                                <div>
                                    <small class="text-muted d-block">Status</small>
                                    <strong class="text-success"><?= ucfirst($subscription['status'] ?? 'active') ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if ($subscription): ?>
                    <div class="mt-2 text-muted" id="period-info" style="font-size: 13px;">
                        <i class="feather-calendar me-1"></i>
                        Current period: <?= date('M j, Y', strtotime($subscription['current_period_start'])) ?> &ndash; <?= date('M j, Y', strtotime($subscription['current_period_end'])) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Features List -->
        <div class="col-lg-4" id="features-col">
            <div class="card" id="features-card">
                <div class="card-body" id="features-body">
                    <h6 class="card-title fw-bold mb-3" id="features-title">Your Features</h6>
                    <ul class="list-unstyled mb-0" id="features-list">
                        <?php
                        $featureLabels = [
                            'practice_sessions' => 'Practice Sessions',
                            'session_history' => 'Session History',
                            'coaching_calendar' => 'Coaching Calendar',
                            'ai_agents' => 'AI Agents',
                            'scoring_rubrics' => 'Scoring Rubrics',
                            'prospects' => 'Prospect Management',
                            'products' => 'Product Knowledge',
                            'business_context' => 'Business Context',
                            'team_management' => 'Team Management',
                            'team_analytics' => 'Team Analytics',
                        ];
                        foreach ($featureLabels as $key => $label):
                            $enabled = !empty($features[$key]);
                        ?>
                        <li class="d-flex align-items-center mb-2 <?= $enabled ? '' : 'text-muted' ?>" id="feature-<?= $key ?>">
                            <i class="feather-<?= $enabled ? 'check text-success' : 'x text-muted' ?> me-2"></i>
                            <?= $label ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Plan Options -->
    <div class="row" id="plan-options-row">
        <div class="col-12">
            <h5 class="fw-bold mb-3" id="plan-options-title">
                <?= $currentPlanId === 1 ? 'Upgrade Your Plan' : 'Change Plan' ?>
            </h5>
        </div>
        <div id="plan-change-messages"></div>
        <?php foreach ($allPlans as $plan): ?>
        <div class="col-md-4 mb-3" id="plan-option-<?= $plan['slug'] ?>-col">
            <div class="card h-100 <?= ($plan['id'] == $currentPlanId) ? 'border-primary' : '' ?>" id="plan-option-<?= $plan['slug'] ?>-card" style="<?= ($plan['id'] == $currentPlanId) ? 'border: 2px solid #667eea;' : '' ?>">
                <div class="card-body text-center" id="plan-option-<?= $plan['slug'] ?>-body">
                    <?php if ($plan['slug'] === 'pro'): ?>
                    <span class="badge mb-2" style="background: linear-gradient(135deg, #667eea, #764ba2);" id="pro-badge">MOST POPULAR</span><br>
                    <?php endif; ?>
                    <h5 class="fw-bold" id="plan-option-<?= $plan['slug'] ?>-name"><?= htmlspecialchars($plan['name']) ?></h5>
                    <p class="text-muted" style="font-size: 13px;" id="plan-option-<?= $plan['slug'] ?>-desc"><?= htmlspecialchars($plan['description']) ?></p>
                    <div class="my-3" id="plan-option-<?= $plan['slug'] ?>-price">
                        <span class="fs-1 fw-bold" <?= ($plan['slug'] === 'pro') ? 'style="color:#667eea;"' : '' ?>><?= $plan['price_monthly'] > 0 ? '$' . number_format($plan['price_monthly'], 0) : 'Free' ?></span>
                        <?php if ($plan['price_monthly'] > 0): ?><span class="text-muted">/mo</span><?php endif; ?>
                    </div>
                    <ul class="list-unstyled text-start mb-3" style="font-size: 13px;" id="plan-option-<?= $plan['slug'] ?>-features">
                        <li class="mb-1"><i class="feather-check text-success me-1"></i> <?php $m = (int)$plan['max_coaching_minutes']; echo $m >= 60 ? ($m / 60) . ' hrs' : $m . ' min'; ?> coaching / month</li>
                        <li class="mb-1"><i class="feather-check text-success me-1"></i> <?= (int)$plan['max_users'] ?> user<?= $plan['max_users'] > 1 ? 's' : '' ?></li>
                        <?php
                        $pf = json_decode($plan['features'] ?? '{}', true);
                        if (!empty($pf['ai_agents'])) echo '<li class="mb-1"><i class="feather-check text-success me-1"></i> AI Agents &amp; Scoring</li>';
                        if (!empty($pf['team_management'])) echo '<li class="mb-1"><i class="feather-check text-success me-1"></i> Team Management</li>';
                        if (!empty($pf['team_analytics'])) echo '<li class="mb-1"><i class="feather-check text-success me-1"></i> Team Analytics</li>';
                        ?>
                    </ul>
                    <?php if ($plan['id'] == $currentPlanId): ?>
                        <button class="btn btn-outline-secondary w-100" disabled id="plan-btn-<?= $plan['slug'] ?>-current">Current Plan</button>
                    <?php else: ?>
                        <button class="btn <?= ($plan['price_monthly'] > $currentPlan['price_monthly']) ? 'btn-primary' : 'btn-outline-primary' ?> w-100"
                                id="plan-btn-<?= $plan['slug'] ?>-change"
                                hx-post="/partials/subscription/index.php"
                                hx-target="#plan-change-messages"
                                hx-swap="innerHTML"
                                hx-vals='{"action":"change_plan","new_plan_id":"<?= $plan['id'] ?>","csrf_token":"<?= $csrf_token ?>"}'
                                hx-confirm="Switch to the <?= htmlspecialchars($plan['name']) ?> plan?">
                            <?= ($plan['price_monthly'] > $currentPlan['price_monthly']) ? 'Upgrade' : 'Switch' ?> to <?= htmlspecialchars($plan['name']) ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
