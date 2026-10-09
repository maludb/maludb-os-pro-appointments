<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireSuperAdmin();

$pdo = db();

$stmt = $pdo->query(
    "SELECT a.*, u.first_name, u.last_name, u.email,
            (SELECT r.name FROM user_restaurants ur JOIN restaurants r ON r.id = ur.restaurant_id WHERE ur.user_id = a.user_id AND r.location_type = 'affiliate' LIMIT 1) as business_name,
            (SELECT COUNT(*) FROM affiliate_referrals ar WHERE ar.affiliate_id = a.id) as referral_count,
            (SELECT COUNT(*) FROM affiliate_referrals ar WHERE ar.affiliate_id = a.id AND ar.status = 'active') as active_referrals,
            (SELECT COALESCE(SUM(ac.amount), 0) FROM affiliate_commissions ac WHERE ac.affiliate_id = a.id AND ac.status = 'paid') as total_paid,
            (SELECT COALESCE(SUM(ac.amount), 0) FROM affiliate_commissions ac WHERE ac.affiliate_id = a.id AND ac.status = 'pending') as total_pending
     FROM affiliates a
     JOIN users u ON u.id = a.user_id
     ORDER BY u.last_name, u.first_name"
);
$affiliates = $stmt->fetchAll();
?>

<div class="main-content" id="platform-affiliates-page">
    <div class="row" id="platform-affiliates-header">
        <div class="col-12 d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0" id="platform-affiliates-title">
                <i class="feather-users me-2"></i>Affiliates
            </h4>
            <button class="btn btn-primary"
                    hx-get="/partials/platform/affiliate-form.php"
                    hx-target="#modal-container"
                    id="platform-add-affiliate-btn">
                <i class="feather-plus me-1"></i> Add Affiliate
            </button>
        </div>
    </div>

    <div class="row" id="platform-affiliates-content">
        <div class="col-12">
            <div class="card border-0 shadow-sm" id="platform-affiliates-card">
                <div class="card-body p-0" id="platform-affiliates-card-body">
                    <?php if (empty($affiliates)): ?>
                    <div class="text-center text-muted py-5" id="platform-no-affiliates">
                        <i class="feather-users fs-1 mb-3 d-block"></i>
                        <p>No affiliates yet. Add your first affiliate to get started.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive" id="platform-affiliates-table-wrap">
                        <table class="table table-hover mb-0" id="platform-affiliates-table">
                            <thead class="bg-light" id="platform-affiliates-thead">
                                <tr>
                                    <th>Name</th>
                                    <th>Business</th>
                                    <th>Code</th>
                                    <th>Commission</th>
                                    <th>Referrals</th>
                                    <th>Pending</th>
                                    <th>Total Paid</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="platform-affiliates-tbody">
                                <?php foreach ($affiliates as $a): ?>
                                <tr id="platform-affiliate-row-<?php echo (int)$a['id']; ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars($a['first_name'] . ' ' . $a['last_name']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($a['email']); ?></small>
                                    </td>
                                    <td id="platform-affiliate-business-<?php echo (int)$a['id']; ?>"><?php echo htmlspecialchars($a['business_name'] ?? '-'); ?></td>
                                    <td><code><?php echo htmlspecialchars($a['affiliate_code']); ?></code></td>
                                    <td><?php echo number_format($a['commission_rate'], 1); ?>%</td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo (int)$a['active_referrals']; ?></span>
                                        <small class="text-muted">/ <?php echo (int)$a['referral_count']; ?></small>
                                    </td>
                                    <td><span class="text-warning fw-semibold">$<?php echo number_format($a['total_pending'], 2); ?></span></td>
                                    <td><span class="text-success fw-semibold">$<?php echo number_format($a['total_paid'], 2); ?></span></td>
                                    <td>
                                        <?php
                                        $statusColors = ['active' => 'success', 'inactive' => 'secondary', 'suspended' => 'danger'];
                                        $color = $statusColors[$a['status']] ?? 'dark';
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst($a['status']); ?></span>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-info me-1"
                                                hx-get="/partials/platform/affiliate-detail.php?id=<?php echo (int)$a['id']; ?>"
                                                hx-target="#page-content"
                                                title="View Details"
                                                id="platform-view-affiliate-btn-<?php echo (int)$a['id']; ?>">
                                            <i class="feather-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-primary me-1"
                                                hx-get="/partials/platform/affiliate-form.php?id=<?php echo (int)$a['id']; ?>"
                                                hx-target="#modal-container"
                                                title="Edit"
                                                id="platform-edit-affiliate-btn-<?php echo (int)$a['id']; ?>">
                                            <i class="feather-edit-2"></i>
                                        </button>
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
