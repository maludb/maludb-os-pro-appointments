<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireSuperAdmin();

$pdo = db();
$affiliateId = (int)($_GET['affiliate_id'] ?? 0);

if ($affiliateId <= 0) {
    echo '<div class="alert alert-danger">Invalid affiliate.</div>';
    exit;
}

// Get active referrals for this affiliate
$referrals = $pdo->prepare(
    "SELECT ar.id, r.name as restaurant_name
     FROM affiliate_referrals ar
     JOIN restaurants r ON r.id = ar.restaurant_id
     WHERE ar.affiliate_id = ? AND ar.status = 'active'
     ORDER BY r.name"
);
$referrals->execute([$affiliateId]);
$referrals = $referrals->fetchAll();
?>

<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" id="platform-commission-modal">
    <div class="modal-dialog" id="platform-commission-modal-dialog">
        <div class="modal-content" id="platform-commission-modal-content">
            <div class="modal-header" id="platform-commission-modal-header">
                <h5 class="modal-title" id="platform-commission-modal-title">Add Commission</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form hx-post="/partials/platform/save-affiliate-commission.php"
                  hx-target="#page-content"
                  id="platform-commission-form">
                <div class="modal-body" id="platform-commission-modal-body">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="affiliate_id" value="<?php echo $affiliateId; ?>">

                    <div class="row g-3" id="platform-commission-form-fields">
                        <div class="col-12" id="platform-commission-referral-wrap">
                            <label for="platform-commission-referral" class="form-label fw-semibold">Referral (Restaurant) <span class="text-danger">*</span></label>
                            <select class="form-select" id="platform-commission-referral" name="referral_id" required>
                                <option value="">Select referral...</option>
                                <?php foreach ($referrals as $r): ?>
                                <option value="<?php echo (int)$r['id']; ?>"><?php echo htmlspecialchars($r['restaurant_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6" id="platform-commission-amount-wrap">
                            <label for="platform-commission-amount" class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="platform-commission-amount" name="amount"
                                       step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6" id="platform-commission-status-wrap">
                            <label for="platform-commission-status" class="form-label fw-semibold">Status</label>
                            <select class="form-select" id="platform-commission-status" name="status">
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="platform-commission-period-start-wrap">
                            <label for="platform-commission-period-start" class="form-label fw-semibold">Period Start</label>
                            <input type="date" class="form-control" id="platform-commission-period-start" name="period_start">
                        </div>
                        <div class="col-md-6" id="platform-commission-period-end-wrap">
                            <label for="platform-commission-period-end" class="form-label fw-semibold">Period End</label>
                            <input type="date" class="form-control" id="platform-commission-period-end" name="period_end">
                        </div>
                        <div class="col-12" id="platform-commission-notes-wrap">
                            <label for="platform-commission-notes" class="form-label fw-semibold">Notes</label>
                            <textarea class="form-control" id="platform-commission-notes" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" id="platform-commission-modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="platform-commission-save-btn">
                        <i class="feather-save me-1"></i> Add Commission
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
