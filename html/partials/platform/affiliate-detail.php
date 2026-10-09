<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireSuperAdmin();

$pdo = db();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    echo '<div class="alert alert-danger">Invalid affiliate.</div>';
    exit;
}

$stmt = $pdo->prepare(
    "SELECT a.*, u.first_name, u.last_name, u.email, u.phone
     FROM affiliates a JOIN users u ON u.id = a.user_id WHERE a.id = ?"
);
$stmt->execute([$id]);
$affiliate = $stmt->fetch();
if (!$affiliate) {
    echo '<div class="alert alert-danger">Affiliate not found.</div>';
    exit;
}

// Get referrals
$referrals = $pdo->prepare(
    "SELECT ar.*, r.name as restaurant_name, r.slug as restaurant_slug,
            (SELECT p.name FROM subscriptions s JOIN products p ON p.id = s.product_id
             WHERE s.restaurant_id = ar.restaurant_id AND s.status = 'active' LIMIT 1) as product_name
     FROM affiliate_referrals ar
     JOIN restaurants r ON r.id = ar.restaurant_id
     WHERE ar.affiliate_id = ?
     ORDER BY ar.referred_at DESC"
);
$referrals->execute([$id]);
$referrals = $referrals->fetchAll();

// Get commissions
$commissions = $pdo->prepare(
    "SELECT ac.*, r.name as restaurant_name
     FROM affiliate_commissions ac
     JOIN affiliate_referrals ar ON ar.id = ac.referral_id
     JOIN restaurants r ON r.id = ar.restaurant_id
     WHERE ac.affiliate_id = ?
     ORDER BY ac.created_at DESC"
);
$commissions->execute([$id]);
$commissions = $commissions->fetchAll();

// Stats
$totalPaid = 0; $totalPending = 0; $totalApproved = 0;
foreach ($commissions as $c) {
    if ($c['status'] === 'paid') $totalPaid += $c['amount'];
    if ($c['status'] === 'pending') $totalPending += $c['amount'];
    if ($c['status'] === 'approved') $totalApproved += $c['amount'];
}

// Restaurants available for referral assignment
$restaurants = $pdo->query("SELECT id, name FROM restaurants ORDER BY name")->fetchAll();
?>

<div class="main-content" id="platform-affiliate-detail-page">
    <!-- Back button and header -->
    <div class="row mb-4" id="platform-affiliate-detail-header">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div id="platform-affiliate-detail-title-area">
                <button class="btn btn-sm btn-outline-secondary me-2"
                        hx-get="/partials/platform/affiliates.php"
                        hx-target="#page-content"
                        id="platform-affiliate-back-btn">
                    <i class="feather-arrow-left"></i>
                </button>
                <h4 class="fw-bold mb-0 d-inline" id="platform-affiliate-detail-name">
                    <?php echo htmlspecialchars($affiliate['first_name'] . ' ' . $affiliate['last_name']); ?>
                </h4>
                <span class="ms-2">
                    <?php
                    $statusColors = ['active' => 'success', 'inactive' => 'secondary', 'suspended' => 'danger'];
                    $color = $statusColors[$affiliate['status']] ?? 'dark';
                    ?>
                    <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst($affiliate['status']); ?></span>
                </span>
            </div>
            <button class="btn btn-outline-primary"
                    hx-get="/partials/platform/affiliate-form.php?id=<?php echo (int)$affiliate['id']; ?>"
                    hx-target="#modal-container"
                    id="platform-affiliate-detail-edit-btn">
                <i class="feather-edit-2 me-1"></i> Edit
            </button>
        </div>
    </div>

    <!-- Stats cards -->
    <div class="row g-3 mb-4" id="platform-affiliate-stats-row">
        <div class="col-md-3" id="platform-affiliate-stat-code">
            <div class="card border-0 shadow-sm" id="platform-affiliate-stat-code-card">
                <div class="card-body text-center" id="platform-affiliate-stat-code-body">
                    <h6 class="text-muted mb-1">Affiliate Code</h6>
                    <h4 class="fw-bold"><code><?php echo htmlspecialchars($affiliate['affiliate_code']); ?></code></h4>
                    <small class="text-muted"><?php echo number_format($affiliate['commission_rate'], 1); ?>% commission</small>
                </div>
            </div>
        </div>
        <div class="col-md-3" id="platform-affiliate-stat-referrals">
            <div class="card border-0 shadow-sm" id="platform-affiliate-stat-referrals-card">
                <div class="card-body text-center" id="platform-affiliate-stat-referrals-body">
                    <h6 class="text-muted mb-1">Referrals</h6>
                    <h4 class="fw-bold"><?php echo count($referrals); ?></h4>
                    <small class="text-muted">total</small>
                </div>
            </div>
        </div>
        <div class="col-md-3" id="platform-affiliate-stat-pending">
            <div class="card border-0 shadow-sm" id="platform-affiliate-stat-pending-card">
                <div class="card-body text-center" id="platform-affiliate-stat-pending-body">
                    <h6 class="text-muted mb-1">Pending + Approved</h6>
                    <h4 class="fw-bold text-warning">$<?php echo number_format($totalPending + $totalApproved, 2); ?></h4>
                    <small class="text-muted">$<?php echo number_format($totalPending, 2); ?> pending / $<?php echo number_format($totalApproved, 2); ?> approved</small>
                </div>
            </div>
        </div>
        <div class="col-md-3" id="platform-affiliate-stat-paid">
            <div class="card border-0 shadow-sm" id="platform-affiliate-stat-paid-card">
                <div class="card-body text-center" id="platform-affiliate-stat-paid-body">
                    <h6 class="text-muted mb-1">Total Paid</h6>
                    <h4 class="fw-bold text-success">$<?php echo number_format($totalPaid, 2); ?></h4>
                    <small class="text-muted">payout: <?php echo htmlspecialchars($affiliate['payout_method'] ?? 'paypal'); ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Company Information -->
    <?php if ($affiliate['company_name'] || $affiliate['tax_id'] || $affiliate['business_address']): ?>
    <div class="row mb-4" id="platform-affiliate-company-section">
        <div class="col-12">
            <div class="card border-0 shadow-sm" id="platform-affiliate-company-card">
                <div class="card-header bg-white" id="platform-affiliate-company-header">
                    <h5 class="mb-0 fw-semibold" id="platform-affiliate-company-title">Company Information</h5>
                </div>
                <div class="card-body" id="platform-affiliate-company-body">
                    <div class="row g-3">
                        <?php if ($affiliate['company_name']): ?>
                        <div class="col-md-4" id="platform-affiliate-detail-company-name">
                            <small class="text-muted d-block">Company Name</small>
                            <strong><?php echo htmlspecialchars($affiliate['company_name']); ?></strong>
                        </div>
                        <?php endif; ?>
                        <?php if ($affiliate['company_type']): ?>
                        <div class="col-md-4" id="platform-affiliate-detail-company-type">
                            <small class="text-muted d-block">Company Type</small>
                            <strong><?php echo htmlspecialchars(strtoupper($affiliate['company_type'])); ?></strong>
                        </div>
                        <?php endif; ?>
                        <?php if ($affiliate['tax_id']): ?>
                        <div class="col-md-4" id="platform-affiliate-detail-tax-id">
                            <small class="text-muted d-block">FEIN / SSN</small>
                            <strong><?php
                                $tid = $affiliate['tax_id'];
                                echo strlen($tid) > 4 ? str_repeat('*', strlen($tid) - 4) . substr($tid, -4) : htmlspecialchars($tid);
                            ?></strong>
                        </div>
                        <?php endif; ?>
                        <?php if ($affiliate['business_address']): ?>
                        <div class="col-md-6" id="platform-affiliate-detail-address">
                            <small class="text-muted d-block">Business Address</small>
                            <strong><?php
                                $addr = htmlspecialchars($affiliate['business_address']);
                                $cityStateZip = array_filter([
                                    $affiliate['business_city'],
                                    $affiliate['business_state'],
                                    $affiliate['business_zip']
                                ]);
                                if ($cityStateZip) $addr .= '<br>' . htmlspecialchars(implode(', ', $cityStateZip));
                                echo $addr;
                            ?></strong>
                        </div>
                        <?php endif; ?>
                        <?php if ($affiliate['contact_name']): ?>
                        <div class="col-md-3" id="platform-affiliate-detail-contact-name">
                            <small class="text-muted d-block">Contact Name</small>
                            <strong><?php echo htmlspecialchars($affiliate['contact_name']); ?></strong>
                        </div>
                        <?php endif; ?>
                        <?php if ($affiliate['contact_phone']): ?>
                        <div class="col-md-3" id="platform-affiliate-detail-contact-phone">
                            <small class="text-muted d-block">Contact Phone</small>
                            <strong><?php echo htmlspecialchars($affiliate['contact_phone']); ?></strong>
                        </div>
                        <?php endif; ?>
                        <?php if ($affiliate['contact_email']): ?>
                        <div class="col-md-3" id="platform-affiliate-detail-contact-email">
                            <small class="text-muted d-block">Contact Email</small>
                            <strong><?php echo htmlspecialchars($affiliate['contact_email']); ?></strong>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Referrals section -->
    <div class="row mb-4" id="platform-affiliate-referrals-section">
        <div class="col-12">
            <div class="card border-0 shadow-sm" id="platform-affiliate-referrals-card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center" id="platform-affiliate-referrals-header">
                    <h5 class="mb-0 fw-semibold" id="platform-affiliate-referrals-title">Referrals</h5>
                    <button class="btn btn-sm btn-primary"
                            hx-get="/partials/platform/affiliate-referral-form.php?affiliate_id=<?php echo (int)$affiliate['id']; ?>"
                            hx-target="#modal-container"
                            id="platform-add-referral-btn">
                        <i class="feather-plus me-1"></i> Add Referral
                    </button>
                </div>
                <div class="card-body p-0" id="platform-affiliate-referrals-body">
                    <?php if (empty($referrals)): ?>
                    <div class="text-center text-muted py-4" id="platform-no-referrals">
                        <p>No referrals yet.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive" id="platform-referrals-table-wrap">
                        <table class="table table-hover mb-0" id="platform-referrals-table">
                            <thead class="bg-light" id="platform-referrals-thead">
                                <tr>
                                    <th>Restaurant</th>
                                    <th>Product</th>
                                    <th>Status</th>
                                    <th>Referred</th>
                                    <th>Activated</th>
                                </tr>
                            </thead>
                            <tbody id="platform-referrals-tbody">
                                <?php foreach ($referrals as $r): ?>
                                <tr id="platform-referral-row-<?php echo (int)$r['id']; ?>">
                                    <td><strong><?php echo htmlspecialchars($r['restaurant_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($r['product_name'] ?? '-'); ?></td>
                                    <td>
                                        <?php
                                        $refColors = ['pending' => 'warning', 'active' => 'success', 'cancelled' => 'secondary'];
                                        ?>
                                        <span class="badge bg-<?php echo $refColors[$r['status']] ?? 'dark'; ?>"><?php echo ucfirst($r['status']); ?></span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($r['referred_at'])); ?></td>
                                    <td><?php echo $r['activated_at'] ? date('M j, Y', strtotime($r['activated_at'])) : '-'; ?></td>
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

    <!-- Commissions section -->
    <div class="row" id="platform-affiliate-commissions-section">
        <div class="col-12">
            <div class="card border-0 shadow-sm" id="platform-affiliate-commissions-card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center" id="platform-affiliate-commissions-header">
                    <h5 class="mb-0 fw-semibold" id="platform-affiliate-commissions-title">Commissions</h5>
                    <button class="btn btn-sm btn-primary"
                            hx-get="/partials/platform/affiliate-commission-form.php?affiliate_id=<?php echo (int)$affiliate['id']; ?>"
                            hx-target="#modal-container"
                            id="platform-add-commission-btn">
                        <i class="feather-plus me-1"></i> Add Commission
                    </button>
                </div>
                <div class="card-body p-0" id="platform-affiliate-commissions-body">
                    <?php if (empty($commissions)): ?>
                    <div class="text-center text-muted py-4" id="platform-no-commissions">
                        <p>No commissions yet.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive" id="platform-commissions-table-wrap">
                        <table class="table table-hover mb-0" id="platform-commissions-table">
                            <thead class="bg-light" id="platform-commissions-thead">
                                <tr>
                                    <th>Restaurant</th>
                                    <th>Amount</th>
                                    <th>Period</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Paid</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="platform-commissions-tbody">
                                <?php foreach ($commissions as $c): ?>
                                <tr id="platform-commission-row-<?php echo (int)$c['id']; ?>">
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
                                    <td><?php echo date('M j, Y', strtotime($c['created_at'])); ?></td>
                                    <td><?php echo $c['paid_at'] ? date('M j, Y', strtotime($c['paid_at'])) : '-'; ?></td>
                                    <td class="text-end">
                                        <?php if ($c['status'] === 'pending'): ?>
                                        <button class="btn btn-sm btn-outline-info me-1"
                                                hx-post="/partials/platform/update-commission-status.php"
                                                hx-vals='{"commission_id": <?php echo (int)$c['id']; ?>, "status": "approved", "affiliate_id": <?php echo (int)$affiliate['id']; ?>, "csrf_token": "<?php echo generate_csrf_token(); ?>"}'
                                                hx-target="#page-content"
                                                title="Approve"
                                                id="platform-approve-commission-btn-<?php echo (int)$c['id']; ?>">
                                            <i class="feather-check"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger"
                                                hx-post="/partials/platform/update-commission-status.php"
                                                hx-vals='{"commission_id": <?php echo (int)$c['id']; ?>, "status": "rejected", "affiliate_id": <?php echo (int)$affiliate['id']; ?>, "csrf_token": "<?php echo generate_csrf_token(); ?>"}'
                                                hx-target="#page-content"
                                                hx-confirm="Reject this commission?"
                                                title="Reject"
                                                id="platform-reject-commission-btn-<?php echo (int)$c['id']; ?>">
                                            <i class="feather-x"></i>
                                        </button>
                                        <?php elseif ($c['status'] === 'approved'): ?>
                                        <button class="btn btn-sm btn-outline-success"
                                                hx-post="/partials/platform/update-commission-status.php"
                                                hx-vals='{"commission_id": <?php echo (int)$c['id']; ?>, "status": "paid", "affiliate_id": <?php echo (int)$affiliate['id']; ?>, "csrf_token": "<?php echo generate_csrf_token(); ?>"}'
                                                hx-target="#page-content"
                                                hx-confirm="Mark as paid?"
                                                title="Mark Paid"
                                                id="platform-pay-commission-btn-<?php echo (int)$c['id']; ?>">
                                            <i class="feather-dollar-sign"></i> Pay
                                        </button>
                                        <?php endif; ?>
                                    </td>
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
