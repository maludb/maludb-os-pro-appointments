<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireSuperAdmin();

$pdo = db();
$id = (int)($_GET['id'] ?? 0);
$affiliate = null;
$user = null;
$isEdit = false;

if ($id > 0) {
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
    $isEdit = true;
}

// Get users who are not yet affiliates (for the new form)
$existingAffiliateUserIds = $pdo->query("SELECT user_id FROM affiliates")->fetchAll(PDO::FETCH_COLUMN);
$allUsers = $pdo->query("SELECT id, first_name, last_name, email FROM users ORDER BY last_name, first_name")->fetchAll();
?>

<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" id="platform-affiliate-modal">
    <div class="modal-dialog modal-lg" id="platform-affiliate-modal-dialog">
        <div class="modal-content" id="platform-affiliate-modal-content">
            <div class="modal-header" id="platform-affiliate-modal-header">
                <h5 class="modal-title" id="platform-affiliate-modal-title">
                    <?php echo $isEdit ? 'Edit Affiliate' : 'Add Affiliate'; ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form hx-post="/partials/platform/save-affiliate.php"
                  hx-target="#platform-affiliate-form-feedback"
                  id="platform-affiliate-form">
                <div class="modal-body" id="platform-affiliate-modal-body">
                    <?php echo csrf_field(); ?>
                    <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?php echo (int)$affiliate['id']; ?>">
                    <?php endif; ?>

                    <div id="platform-affiliate-form-feedback"></div>

                    <?php if (!$isEdit): ?>
                    <!-- New affiliate: choose existing user or create new -->
                    <ul class="nav nav-tabs mb-3" id="platform-affiliate-tabs">
                        <li class="nav-item" id="platform-affiliate-tab-existing-wrap">
                            <a class="nav-link active" data-bs-toggle="tab" href="#platform-affiliate-existing" id="platform-affiliate-tab-existing">Existing User</a>
                        </li>
                        <li class="nav-item" id="platform-affiliate-tab-new-wrap">
                            <a class="nav-link" data-bs-toggle="tab" href="#platform-affiliate-new" id="platform-affiliate-tab-new">New User</a>
                        </li>
                    </ul>
                    <div class="tab-content mb-3" id="platform-affiliate-tab-content">
                        <div class="tab-pane fade show active" id="platform-affiliate-existing">
                            <div class="mb-3" id="platform-affiliate-user-select-wrap">
                                <label for="platform-affiliate-user-select" class="form-label fw-semibold">Select User</label>
                                <select class="form-select" id="platform-affiliate-user-select" name="user_id">
                                    <option value="">Choose a user...</option>
                                    <?php foreach ($allUsers as $u): ?>
                                    <?php if (!in_array($u['id'], $existingAffiliateUserIds)): ?>
                                    <option value="<?php echo (int)$u['id']; ?>">
                                        <?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name'] . ' (' . $u['email'] . ')'); ?>
                                    </option>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="platform-affiliate-new">
                            <div class="row g-3" id="platform-affiliate-new-fields">
                                <div class="col-md-6" id="platform-affiliate-fname-wrap">
                                    <label for="platform-affiliate-fname" class="form-label fw-semibold">First Name</label>
                                    <input type="text" class="form-control" id="platform-affiliate-fname" name="first_name">
                                </div>
                                <div class="col-md-6" id="platform-affiliate-lname-wrap">
                                    <label for="platform-affiliate-lname" class="form-label fw-semibold">Last Name</label>
                                    <input type="text" class="form-control" id="platform-affiliate-lname" name="last_name">
                                </div>
                                <div class="col-md-6" id="platform-affiliate-email-wrap">
                                    <label for="platform-affiliate-email" class="form-label fw-semibold">Email</label>
                                    <input type="email" class="form-control" id="platform-affiliate-email" name="email">
                                </div>
                                <div class="col-md-6" id="platform-affiliate-phone-wrap">
                                    <label for="platform-affiliate-phone" class="form-label fw-semibold">Phone</label>
                                    <input type="tel" class="form-control" id="platform-affiliate-phone" name="phone">
                                </div>
                                <div class="col-md-6" id="platform-affiliate-password-wrap">
                                    <label for="platform-affiliate-password" class="form-label fw-semibold">Password</label>
                                    <input type="password" class="form-control" id="platform-affiliate-password" name="password">
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="mb-3" id="platform-affiliate-user-display">
                        <label class="form-label fw-semibold">User</label>
                        <p class="form-control-plaintext"><?php echo htmlspecialchars($affiliate['first_name'] . ' ' . $affiliate['last_name'] . ' (' . $affiliate['email'] . ')'); ?></p>
                    </div>
                    <?php endif; ?>

                    <div class="row g-3" id="platform-affiliate-form-fields">
                        <div class="col-md-4" id="platform-affiliate-code-wrap">
                            <label for="platform-affiliate-code" class="form-label fw-semibold">Affiliate Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="platform-affiliate-code" name="affiliate_code"
                                   value="<?php echo htmlspecialchars($affiliate['affiliate_code'] ?? strtoupper(bin2hex(random_bytes(4)))); ?>" required>
                            <small class="text-muted">Unique referral code</small>
                        </div>
                        <div class="col-md-4" id="platform-affiliate-commission-wrap">
                            <label for="platform-affiliate-commission" class="form-label fw-semibold">Commission Rate (%) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="platform-affiliate-commission" name="commission_rate"
                                       value="<?php echo htmlspecialchars($affiliate['commission_rate'] ?? '10.00'); ?>"
                                       step="0.01" min="0" max="100" required>
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="col-md-4" id="platform-affiliate-status-wrap">
                            <label for="platform-affiliate-status" class="form-label fw-semibold">Status</label>
                            <select class="form-select" id="platform-affiliate-status" name="status">
                                <option value="active" <?php echo ($affiliate['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($affiliate['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="suspended" <?php echo ($affiliate['status'] ?? '') === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="platform-affiliate-payout-email-wrap">
                            <label for="platform-affiliate-payout-email" class="form-label fw-semibold">Payout Email</label>
                            <input type="email" class="form-control" id="platform-affiliate-payout-email" name="payout_email"
                                   value="<?php echo htmlspecialchars($affiliate['payout_email'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6" id="platform-affiliate-payout-method-wrap">
                            <label for="platform-affiliate-payout-method" class="form-label fw-semibold">Payout Method</label>
                            <select class="form-select" id="platform-affiliate-payout-method" name="payout_method">
                                <option value="paypal" <?php echo ($affiliate['payout_method'] ?? 'paypal') === 'paypal' ? 'selected' : ''; ?>>PayPal</option>
                                <option value="bank_transfer" <?php echo ($affiliate['payout_method'] ?? '') === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                <option value="check" <?php echo ($affiliate['payout_method'] ?? '') === 'check' ? 'selected' : ''; ?>>Check</option>
                            </select>
                        </div>
                        <div class="col-12" id="platform-affiliate-notes-wrap">
                            <label for="platform-affiliate-notes" class="form-label fw-semibold">Notes</label>
                            <textarea class="form-control" id="platform-affiliate-notes" name="notes" rows="2"><?php echo htmlspecialchars($affiliate['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Company Information -->
                    <hr class="my-3">
                    <h6 class="fw-bold mb-3" id="platform-affiliate-company-heading">Company Information</h6>
                    <div class="row g-3" id="platform-affiliate-company-fields">
                        <div class="col-md-6" id="platform-affiliate-company-name-wrap">
                            <label for="platform-affiliate-company-name" class="form-label fw-semibold">Company Name</label>
                            <input type="text" class="form-control" id="platform-affiliate-company-name" name="company_name"
                                   value="<?php echo htmlspecialchars($affiliate['company_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6" id="platform-affiliate-company-type-wrap">
                            <label for="platform-affiliate-company-type" class="form-label fw-semibold">Company Type</label>
                            <?php $companyType = $affiliate['company_type'] ?? ''; ?>
                            <select class="form-select" id="platform-affiliate-company-type" name="company_type">
                                <option value="">Select...</option>
                                <option value="c-corp" <?php echo $companyType === 'c-corp' ? 'selected' : ''; ?>>C-Corp</option>
                                <option value="s-corp" <?php echo $companyType === 's-corp' ? 'selected' : ''; ?>>S-Corp</option>
                                <option value="llc" <?php echo $companyType === 'llc' ? 'selected' : ''; ?>>LLC</option>
                                <option value="partnership" <?php echo $companyType === 'partnership' ? 'selected' : ''; ?>>Partnership</option>
                                <option value="sole-proprietor" <?php echo $companyType === 'sole-proprietor' ? 'selected' : ''; ?>>Sole Proprietor</option>
                                <option value="non-profit" <?php echo $companyType === 'non-profit' ? 'selected' : ''; ?>>Non-Profit</option>
                                <option value="other" <?php echo $companyType === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-4" id="platform-affiliate-tax-id-wrap">
                            <label for="platform-affiliate-tax-id" class="form-label fw-semibold">FEIN / SSN</label>
                            <input type="text" class="form-control" id="platform-affiliate-tax-id" name="tax_id"
                                   value="<?php echo htmlspecialchars($affiliate['tax_id'] ?? ''); ?>"
                                   placeholder="XX-XXXXXXX">
                        </div>
                        <div class="col-md-8" id="platform-affiliate-business-address-wrap">
                            <label for="platform-affiliate-business-address" class="form-label fw-semibold">Business Address</label>
                            <input type="text" class="form-control" id="platform-affiliate-business-address" name="business_address"
                                   value="<?php echo htmlspecialchars($affiliate['business_address'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4" id="platform-affiliate-business-city-wrap">
                            <label for="platform-affiliate-business-city" class="form-label fw-semibold">City</label>
                            <input type="text" class="form-control" id="platform-affiliate-business-city" name="business_city"
                                   value="<?php echo htmlspecialchars($affiliate['business_city'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4" id="platform-affiliate-business-state-wrap">
                            <label for="platform-affiliate-business-state" class="form-label fw-semibold">State</label>
                            <input type="text" class="form-control" id="platform-affiliate-business-state" name="business_state"
                                   value="<?php echo htmlspecialchars($affiliate['business_state'] ?? ''); ?>"
                                   maxlength="2" placeholder="TX">
                        </div>
                        <div class="col-md-4" id="platform-affiliate-business-zip-wrap">
                            <label for="platform-affiliate-business-zip" class="form-label fw-semibold">ZIP Code</label>
                            <input type="text" class="form-control" id="platform-affiliate-business-zip" name="business_zip"
                                   value="<?php echo htmlspecialchars($affiliate['business_zip'] ?? ''); ?>"
                                   maxlength="10" placeholder="75001">
                        </div>
                        <div class="col-md-4" id="platform-affiliate-contact-name-wrap">
                            <label for="platform-affiliate-contact-name" class="form-label fw-semibold">Contact Name</label>
                            <input type="text" class="form-control" id="platform-affiliate-contact-name" name="contact_name"
                                   value="<?php echo htmlspecialchars($affiliate['contact_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4" id="platform-affiliate-contact-phone-wrap">
                            <label for="platform-affiliate-contact-phone" class="form-label fw-semibold">Contact Phone</label>
                            <input type="tel" class="form-control" id="platform-affiliate-contact-phone" name="contact_phone"
                                   value="<?php echo htmlspecialchars($affiliate['contact_phone'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4" id="platform-affiliate-contact-email-wrap">
                            <label for="platform-affiliate-contact-email" class="form-label fw-semibold">Contact Email</label>
                            <input type="email" class="form-control" id="platform-affiliate-contact-email" name="contact_email"
                                   value="<?php echo htmlspecialchars($affiliate['contact_email'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer" id="platform-affiliate-modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="platform-affiliate-save-btn">
                        <i class="feather-save me-1"></i> <?php echo $isEdit ? 'Update' : 'Create'; ?> Affiliate
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
