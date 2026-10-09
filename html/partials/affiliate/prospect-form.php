<?php
/**
 * Prospect Form — create or edit a prospect (modal)
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../helpers/db.php';

requireAuth();

$pdo = db();
$userId = currentUserId();
$id = (int)($_GET['id'] ?? 0);
$prospect = null;
$isEdit = false;

// Get affiliate id for current context (user or restaurant)
$affiliateId = currentAffiliateId();

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM prospects WHERE id = ? AND affiliate_id = ?");
    $stmt->execute([$id, $affiliateId]);
    $prospect = $stmt->fetch();
    if (!$prospect) {
        echo '<div class="alert alert-danger">Prospect not found.</div>';
        exit;
    }
    $isEdit = true;
}

$statusOptions = [
    'new' => 'New',
    'contacted' => 'Contacted',
    'qualified' => 'Qualified',
    'in_setup' => 'In-Setup',
    'client' => 'Client',
    'lost' => 'Lost',
];

$productOptions = [
    'restaurant' => 'Restaurant',
    'professional' => 'Professional',
    'systems_integrator' => 'Systems Integrator',
];
?>

<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" id="prospect-modal">
    <div class="modal-dialog modal-lg" id="prospect-modal-dialog">
        <div class="modal-content" id="prospect-modal-content">
            <div class="modal-header" id="prospect-modal-header">
                <h5 class="modal-title" id="prospect-modal-title">
                    <?php echo $isEdit ? 'Edit Prospect' : 'Add Prospect'; ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form hx-post="/partials/affiliate/save-prospect.php"
                  hx-target="#page-content"
                  id="prospect-form">
                <div class="modal-body" id="prospect-modal-body">
                    <?php echo csrf_field(); ?>
                    <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?php echo (int)$prospect['id']; ?>">
                    <?php endif; ?>

                    <div id="prospect-form-feedback"></div>

                    <!-- Business Info -->
                    <h6 class="fw-bold mb-3" id="prospect-form-business-heading">Business Information</h6>
                    <div class="row g-3 mb-3" id="prospect-form-business-fields">
                        <div class="col-md-6" id="prospect-form-business-name-wrap">
                            <label for="prospect-business-name" class="form-label fw-semibold">Business Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="prospect-business-name" name="business_name"
                                   value="<?php echo htmlspecialchars($prospect['business_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6" id="prospect-form-business-type-wrap">
                            <label for="prospect-business-type" class="form-label fw-semibold">Business Type</label>
                            <input type="text" class="form-control" id="prospect-business-type" name="business_type"
                                   value="<?php echo htmlspecialchars($prospect['business_type'] ?? ''); ?>"
                                   placeholder="e.g. Italian Restaurant, Salon, etc.">
                        </div>
                        <div class="col-md-8" id="prospect-form-address-wrap">
                            <label for="prospect-address" class="form-label fw-semibold">Address</label>
                            <input type="text" class="form-control" id="prospect-address" name="address"
                                   value="<?php echo htmlspecialchars($prospect['address'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4" id="prospect-form-city-wrap">
                            <label for="prospect-city" class="form-label fw-semibold">City</label>
                            <input type="text" class="form-control" id="prospect-city" name="city"
                                   value="<?php echo htmlspecialchars($prospect['city'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4" id="prospect-form-state-wrap">
                            <label for="prospect-state" class="form-label fw-semibold">State</label>
                            <input type="text" class="form-control" id="prospect-state" name="state"
                                   value="<?php echo htmlspecialchars($prospect['state'] ?? ''); ?>"
                                   maxlength="2" placeholder="TX">
                        </div>
                        <div class="col-md-4" id="prospect-form-zip-wrap">
                            <label for="prospect-zip" class="form-label fw-semibold">ZIP</label>
                            <input type="text" class="form-control" id="prospect-zip" name="zip"
                                   value="<?php echo htmlspecialchars($prospect['zip'] ?? ''); ?>"
                                   maxlength="10">
                        </div>
                        <div class="col-md-4" id="prospect-form-website-wrap">
                            <label for="prospect-website" class="form-label fw-semibold">Website</label>
                            <input type="url" class="form-control" id="prospect-website" name="website"
                                   value="<?php echo htmlspecialchars($prospect['website'] ?? ''); ?>"
                                   placeholder="https://...">
                        </div>
                    </div>

                    <!-- Contact Info -->
                    <h6 class="fw-bold mb-3" id="prospect-form-contact-heading">Contact Information</h6>
                    <div class="row g-3 mb-3" id="prospect-form-contact-fields">
                        <div class="col-md-4" id="prospect-form-contact-name-wrap">
                            <label for="prospect-contact-name" class="form-label fw-semibold">Contact Name</label>
                            <input type="text" class="form-control" id="prospect-contact-name" name="contact_name"
                                   value="<?php echo htmlspecialchars($prospect['contact_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4" id="prospect-form-contact-phone-wrap">
                            <label for="prospect-contact-phone" class="form-label fw-semibold">Phone</label>
                            <input type="tel" class="form-control" id="prospect-contact-phone" name="contact_phone"
                                   value="<?php echo htmlspecialchars($prospect['contact_phone'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4" id="prospect-form-contact-email-wrap">
                            <label for="prospect-contact-email" class="form-label fw-semibold">Email</label>
                            <input type="email" class="form-control" id="prospect-contact-email" name="contact_email"
                                   value="<?php echo htmlspecialchars($prospect['contact_email'] ?? ''); ?>">
                        </div>
                    </div>

                    <!-- Status & Product -->
                    <h6 class="fw-bold mb-3" id="prospect-form-status-heading">Status & Product</h6>
                    <div class="row g-3 mb-3" id="prospect-form-status-fields">
                        <div class="col-md-4" id="prospect-form-status-wrap">
                            <label for="prospect-status" class="form-label fw-semibold">Status</label>
                            <?php $currentStatus = $prospect['status'] ?? 'new'; ?>
                            <select class="form-select" id="prospect-status" name="status">
                                <?php foreach ($statusOptions as $val => $label): ?>
                                <option value="<?php echo $val; ?>" <?php echo $currentStatus === $val ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4" id="prospect-form-product-wrap">
                            <label for="prospect-product" class="form-label fw-semibold">Product Interest</label>
                            <?php $currentProduct = $prospect['product_interest'] ?? 'restaurant'; ?>
                            <select class="form-select" id="prospect-product" name="product_interest">
                                <?php foreach ($productOptions as $val => $label): ?>
                                <option value="<?php echo $val; ?>" <?php echo $currentProduct === $val ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4" id="prospect-form-source-wrap">
                            <label for="prospect-source" class="form-label fw-semibold">Lead Source</label>
                            <input type="text" class="form-control" id="prospect-source" name="source"
                                   value="<?php echo htmlspecialchars($prospect['source'] ?? ''); ?>"
                                   placeholder="e.g. Referral, Cold Call, Website">
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="mb-3" id="prospect-form-notes-wrap">
                        <label for="prospect-notes" class="form-label fw-semibold">Notes</label>
                        <textarea class="form-control" id="prospect-notes" name="notes" rows="3"><?php echo htmlspecialchars($prospect['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="modal-footer" id="prospect-modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="prospect-save-btn">
                        <i class="feather-save me-1"></i> <?php echo $isEdit ? 'Update' : 'Create'; ?> Prospect
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
