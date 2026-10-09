<?php
/**
 * Affiliate Settings — allows affiliates to view/edit their company & payout info
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../helpers/db.php';

requireAuth();

$pdo = db();
$userId = currentUserId();

// Get affiliate id for current context (user or restaurant)
$affiliateId = currentAffiliateId();

$stmt = $pdo->prepare(
    "SELECT a.*, u.first_name, u.last_name, u.email
     FROM affiliates a JOIN users u ON u.id = a.user_id
     WHERE a.id = ?"
);
$stmt->execute([$affiliateId]);
$affiliate = $stmt->fetch();

if (!$affiliate) {
    echo '<div class="alert alert-warning m-4">You do not have an affiliate account.</div>';
    exit;
}

$successMsg = $_SESSION['affiliate_settings_success'] ?? null;
if ($successMsg) { unset($_SESSION['affiliate_settings_success']); }
?>

<div class="main-content p-4" id="affiliate-settings-page">
    <div class="d-flex justify-content-between align-items-center mb-4" id="affiliate-settings-header">
        <div id="affiliate-settings-title-wrap">
            <h4 class="mb-0" id="affiliate-settings-title"><i class="feather-briefcase me-2"></i>Affiliate Settings</h4>
            <small class="text-muted" id="affiliate-settings-subtitle">
                Code: <code><?php echo htmlspecialchars($affiliate['affiliate_code']); ?></code>
                &middot; Commission: <?php echo number_format($affiliate['commission_rate'], 1); ?>%
                &middot; Status: <span class="badge bg-<?php echo $affiliate['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($affiliate['status']); ?></span>
            </small>
        </div>
    </div>

    <?php if ($successMsg): ?>
    <div class="alert alert-success alert-dismissible fade show" id="affiliate-settings-success">
        <?php echo htmlspecialchars($successMsg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm" id="affiliate-settings-card">
        <div class="card-body" id="affiliate-settings-card-body">
            <div id="affiliate-settings-messages"></div>

            <form hx-post="/partials/affiliate/save-settings.php"
                  hx-target="#affiliate-settings-messages"
                  hx-swap="innerHTML"
                  id="affiliate-settings-form">
                <?php echo csrf_field(); ?>

                <!-- Company Information -->
                <h6 class="fw-bold mb-3" id="affiliate-settings-company-heading">Company Information</h6>
                <div class="row g-3 mb-4" id="affiliate-settings-company-fields">
                    <div class="col-md-6" id="affiliate-settings-company-name-wrap">
                        <label for="affiliate-settings-company-name" class="form-label fw-semibold">Company Name</label>
                        <input type="text" class="form-control" id="affiliate-settings-company-name" name="company_name"
                               value="<?php echo htmlspecialchars($affiliate['company_name'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6" id="affiliate-settings-company-type-wrap">
                        <label for="affiliate-settings-company-type" class="form-label fw-semibold">Company Type</label>
                        <?php $companyType = $affiliate['company_type'] ?? ''; ?>
                        <select class="form-select" id="affiliate-settings-company-type" name="company_type">
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
                    <div class="col-md-4" id="affiliate-settings-tax-id-wrap">
                        <label for="affiliate-settings-tax-id" class="form-label fw-semibold">FEIN / SSN</label>
                        <input type="text" class="form-control" id="affiliate-settings-tax-id" name="tax_id"
                               value="<?php echo htmlspecialchars($affiliate['tax_id'] ?? ''); ?>"
                               placeholder="XX-XXXXXXX">
                    </div>
                    <div class="col-md-8" id="affiliate-settings-business-address-wrap">
                        <label for="affiliate-settings-business-address" class="form-label fw-semibold">Business Address</label>
                        <input type="text" class="form-control" id="affiliate-settings-business-address" name="business_address"
                               value="<?php echo htmlspecialchars($affiliate['business_address'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4" id="affiliate-settings-business-city-wrap">
                        <label for="affiliate-settings-business-city" class="form-label fw-semibold">City</label>
                        <input type="text" class="form-control" id="affiliate-settings-business-city" name="business_city"
                               value="<?php echo htmlspecialchars($affiliate['business_city'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4" id="affiliate-settings-business-state-wrap">
                        <label for="affiliate-settings-business-state" class="form-label fw-semibold">State</label>
                        <input type="text" class="form-control" id="affiliate-settings-business-state" name="business_state"
                               value="<?php echo htmlspecialchars($affiliate['business_state'] ?? ''); ?>"
                               maxlength="2" placeholder="TX">
                    </div>
                    <div class="col-md-4" id="affiliate-settings-business-zip-wrap">
                        <label for="affiliate-settings-business-zip" class="form-label fw-semibold">ZIP Code</label>
                        <input type="text" class="form-control" id="affiliate-settings-business-zip" name="business_zip"
                               value="<?php echo htmlspecialchars($affiliate['business_zip'] ?? ''); ?>"
                               maxlength="10" placeholder="75001">
                    </div>
                </div>

                <!-- Contact Information -->
                <h6 class="fw-bold mb-3" id="affiliate-settings-contact-heading">Contact Information</h6>
                <div class="row g-3 mb-4" id="affiliate-settings-contact-fields">
                    <div class="col-md-4" id="affiliate-settings-contact-name-wrap">
                        <label for="affiliate-settings-contact-name" class="form-label fw-semibold">Contact Name</label>
                        <input type="text" class="form-control" id="affiliate-settings-contact-name" name="contact_name"
                               value="<?php echo htmlspecialchars($affiliate['contact_name'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4" id="affiliate-settings-contact-phone-wrap">
                        <label for="affiliate-settings-contact-phone" class="form-label fw-semibold">Contact Phone</label>
                        <input type="tel" class="form-control" id="affiliate-settings-contact-phone" name="contact_phone"
                               value="<?php echo htmlspecialchars($affiliate['contact_phone'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4" id="affiliate-settings-contact-email-wrap">
                        <label for="affiliate-settings-contact-email" class="form-label fw-semibold">Contact Email</label>
                        <input type="email" class="form-control" id="affiliate-settings-contact-email" name="contact_email"
                               value="<?php echo htmlspecialchars($affiliate['contact_email'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Payout Information -->
                <h6 class="fw-bold mb-3" id="affiliate-settings-payout-heading">Payout Information</h6>
                <div class="row g-3 mb-4" id="affiliate-settings-payout-fields">
                    <div class="col-md-6" id="affiliate-settings-payout-email-wrap">
                        <label for="affiliate-settings-payout-email" class="form-label fw-semibold">Payout Email</label>
                        <input type="email" class="form-control" id="affiliate-settings-payout-email" name="payout_email"
                               value="<?php echo htmlspecialchars($affiliate['payout_email'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6" id="affiliate-settings-payout-method-wrap">
                        <label for="affiliate-settings-payout-method" class="form-label fw-semibold">Payout Method</label>
                        <select class="form-select" id="affiliate-settings-payout-method" name="payout_method">
                            <option value="paypal" <?php echo ($affiliate['payout_method'] ?? 'paypal') === 'paypal' ? 'selected' : ''; ?>>PayPal</option>
                            <option value="bank_transfer" <?php echo ($affiliate['payout_method'] ?? '') === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                            <option value="check" <?php echo ($affiliate['payout_method'] ?? '') === 'check' ? 'selected' : ''; ?>>Check</option>
                        </select>
                    </div>
                </div>

                <div id="affiliate-settings-submit-wrap">
                    <button type="submit" class="btn btn-primary" id="affiliate-settings-save-btn">
                        <i class="feather-save me-1"></i> Save Settings
                        <span class="htmx-indicator spinner-border spinner-border-sm ms-2"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
