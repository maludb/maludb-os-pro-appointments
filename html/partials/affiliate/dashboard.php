<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();

if (!isAffiliate()) {
    echo '<div class="alert alert-danger" id="affiliate-access-denied">Access denied. Affiliate role required.</div>';
    exit;
}

$pdo = db();
$affiliateId = currentAffiliateId();

// Get affiliate info
$stmt = $pdo->prepare(
    "SELECT a.*, u.first_name, u.last_name, u.email
     FROM affiliates a JOIN users u ON u.id = a.user_id WHERE a.id = ?"
);
$stmt->execute([$affiliateId]);
$affiliate = $stmt->fetch();

if (!$affiliate) {
    echo '<div class="alert alert-danger" id="affiliate-not-found">Affiliate profile not found.</div>';
    exit;
}

// Get referrals
$referrals = $pdo->prepare(
    "SELECT ar.*, r.name as restaurant_name,
            (SELECT p.name FROM subscriptions s JOIN products p ON p.id = s.product_id
             WHERE s.restaurant_id = ar.restaurant_id AND s.status = 'active' LIMIT 1) as product_name
     FROM affiliate_referrals ar
     JOIN restaurants r ON r.id = ar.restaurant_id
     WHERE ar.affiliate_id = ?
     ORDER BY ar.referred_at DESC"
);
$referrals->execute([$affiliateId]);
$referrals = $referrals->fetchAll();

// Get commissions
$commissions = $pdo->prepare(
    "SELECT ac.*, r.name as restaurant_name
     FROM affiliate_commissions ac
     JOIN affiliate_referrals ar ON ar.id = ac.referral_id
     JOIN restaurants r ON r.id = ar.restaurant_id
     WHERE ac.affiliate_id = ?
     ORDER BY ac.created_at DESC
     LIMIT 20"
);
$commissions->execute([$affiliateId]);
$commissions = $commissions->fetchAll();

// Stats
$totalPaid = 0; $totalPending = 0; $activeReferrals = 0;
foreach ($commissions as $c) {
    if ($c['status'] === 'paid') $totalPaid += $c['amount'];
    if ($c['status'] === 'pending' || $c['status'] === 'approved') $totalPending += $c['amount'];
}
foreach ($referrals as $r) {
    if ($r['status'] === 'active') $activeReferrals++;
}
?>

<div class="main-content" id="affiliate-dashboard-page">
    <div class="row mb-4" id="affiliate-dashboard-header">
        <div class="col-12">
            <h4 class="fw-bold mb-1" id="affiliate-dashboard-title">
                <i class="feather-trending-up me-2"></i>Affiliate Dashboard
            </h4>
            <p class="text-muted mb-0" id="affiliate-dashboard-subtitle">
                Code: <code><?php echo htmlspecialchars($affiliate['affiliate_code']); ?></code>
                &middot; Commission: <?php echo number_format($affiliate['commission_rate'], 1); ?>%
            </p>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4" id="affiliate-dashboard-stats">
        <div class="col-md-4" id="affiliate-stat-referrals-wrap">
            <div class="card border-0 shadow-sm" id="affiliate-stat-referrals-card">
                <div class="card-body text-center" id="affiliate-stat-referrals-body">
                    <h6 class="text-muted mb-1">Active Referrals</h6>
                    <h3 class="fw-bold"><?php echo $activeReferrals; ?></h3>
                    <small class="text-muted"><?php echo count($referrals); ?> total</small>
                </div>
            </div>
        </div>
        <div class="col-md-4" id="affiliate-stat-pending-wrap">
            <div class="card border-0 shadow-sm" id="affiliate-stat-pending-card">
                <div class="card-body text-center" id="affiliate-stat-pending-body">
                    <h6 class="text-muted mb-1">Pending Earnings</h6>
                    <h3 class="fw-bold text-warning">$<?php echo number_format($totalPending, 2); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4" id="affiliate-stat-paid-wrap">
            <div class="card border-0 shadow-sm" id="affiliate-stat-paid-card">
                <div class="card-body text-center" id="affiliate-stat-paid-body">
                    <h6 class="text-muted mb-1">Total Earned</h6>
                    <h3 class="fw-bold text-success">$<?php echo number_format($totalPaid, 2); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Referrals -->
    <div class="row mb-4" id="affiliate-referrals-section">
        <div class="col-12">
            <div class="card border-0 shadow-sm" id="affiliate-referrals-card">
                <div class="card-header bg-white" id="affiliate-referrals-header">
                    <h5 class="mb-0 fw-semibold" id="affiliate-referrals-title">My Referrals</h5>
                </div>
                <div class="card-body p-0" id="affiliate-referrals-body">
                    <?php if (empty($referrals)): ?>
                    <div class="text-center text-muted py-4" id="affiliate-no-referrals">
                        <p>No referrals yet. Share your affiliate code to start earning!</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive" id="affiliate-referrals-table-wrap">
                        <table class="table table-hover mb-0" id="affiliate-referrals-table">
                            <thead class="bg-light" id="affiliate-referrals-thead">
                                <tr>
                                    <th>Restaurant</th>
                                    <th>Product</th>
                                    <th>Status</th>
                                    <th>Referred</th>
                                </tr>
                            </thead>
                            <tbody id="affiliate-referrals-tbody">
                                <?php foreach ($referrals as $r): ?>
                                <tr id="affiliate-referral-row-<?php echo (int)$r['id']; ?>">
                                    <td><strong><?php echo htmlspecialchars($r['restaurant_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($r['product_name'] ?? '-'); ?></td>
                                    <td>
                                        <?php
                                        $refColors = ['pending' => 'warning', 'active' => 'success', 'cancelled' => 'secondary'];
                                        ?>
                                        <span class="badge bg-<?php echo $refColors[$r['status']] ?? 'dark'; ?>"><?php echo ucfirst($r['status']); ?></span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($r['referred_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Commission history -->
    <div class="row" id="affiliate-commissions-section">
        <div class="col-12">
            <div class="card border-0 shadow-sm" id="affiliate-commissions-card">
                <div class="card-header bg-white" id="affiliate-commissions-header">
                    <h5 class="mb-0 fw-semibold" id="affiliate-commissions-title">Commission History</h5>
                </div>
                <div class="card-body p-0" id="affiliate-commissions-body">
                    <?php if (empty($commissions)): ?>
                    <div class="text-center text-muted py-4" id="affiliate-no-commissions">
                        <p>No commissions yet.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive" id="affiliate-commissions-table-wrap">
                        <table class="table table-hover mb-0" id="affiliate-commissions-table">
                            <thead class="bg-light" id="affiliate-commissions-thead">
                                <tr>
                                    <th>Restaurant</th>
                                    <th>Amount</th>
                                    <th>Period</th>
                                    <th>Status</th>
                                    <th>Paid</th>
                                </tr>
                            </thead>
                            <tbody id="affiliate-commissions-tbody">
                                <?php foreach ($commissions as $c): ?>
                                <tr id="affiliate-commission-row-<?php echo (int)$c['id']; ?>">
                                    <td><?php echo htmlspecialchars($c['restaurant_name']); ?></td>
                                    <td><strong>$<?php echo number_format($c['amount'], 2); ?></strong></td>
                                    <td>
                                        <?php if ($c['period_start'] && $c['period_end']): ?>
                                        <?php echo date('M j', strtotime($c['period_start'])); ?> - <?php echo date('M j, Y', strtotime($c['period_end'])); ?>
                                        <?php else: ?>
                                        -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $comColors = ['pending' => 'warning', 'approved' => 'info', 'paid' => 'success', 'rejected' => 'danger'];
                                        ?>
                                        <span class="badge bg-<?php echo $comColors[$c['status']] ?? 'dark'; ?>"><?php echo ucfirst($c['status']); ?></span>
                                    </td>
                                    <td><?php echo $c['paid_at'] ? date('M j, Y', strtotime($c['paid_at'])) : '-'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
