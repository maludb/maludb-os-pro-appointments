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

// Get restaurants not already referred by this affiliate
$existing = $pdo->prepare("SELECT restaurant_id FROM affiliate_referrals WHERE affiliate_id = ?");
$existing->execute([$affiliateId]);
$existingIds = $existing->fetchAll(PDO::FETCH_COLUMN);

$restaurants = $pdo->query("SELECT id, name FROM restaurants ORDER BY name")->fetchAll();
?>

<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" id="platform-referral-modal">
    <div class="modal-dialog" id="platform-referral-modal-dialog">
        <div class="modal-content" id="platform-referral-modal-content">
            <div class="modal-header" id="platform-referral-modal-header">
                <h5 class="modal-title" id="platform-referral-modal-title">Add Referral</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form hx-post="/partials/platform/save-affiliate-referral.php"
                  hx-target="#page-content"
                  id="platform-referral-form">
                <div class="modal-body" id="platform-referral-modal-body">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="affiliate_id" value="<?php echo $affiliateId; ?>">

                    <div class="mb-3" id="platform-referral-restaurant-wrap">
                        <label for="platform-referral-restaurant" class="form-label fw-semibold">Restaurant <span class="text-danger">*</span></label>
                        <select class="form-select" id="platform-referral-restaurant" name="restaurant_id" required>
                            <option value="">Select restaurant...</option>
                            <?php foreach ($restaurants as $r): ?>
                            <?php if (!in_array($r['id'], $existingIds)): ?>
                            <option value="<?php echo (int)$r['id']; ?>"><?php echo htmlspecialchars($r['name']); ?></option>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3" id="platform-referral-status-wrap">
                        <label for="platform-referral-status" class="form-label fw-semibold">Status</label>
                        <select class="form-select" id="platform-referral-status" name="status">
                            <option value="pending">Pending</option>
                            <option value="active">Active</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer" id="platform-referral-modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="platform-referral-save-btn">
                        <i class="feather-save me-1"></i> Add Referral
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
