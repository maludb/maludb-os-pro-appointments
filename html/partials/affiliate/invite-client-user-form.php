<?php
/**
 * Invite Client User — modal form for affiliates to add a user to a prospect's company.
 * Mirrors the admin user-form.php flow but uses the prospect's restaurant_id.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../helpers/db.php';

requireAuth();

$pdo = db();
$userId = currentUserId();
$prospectId = (int)($_GET['prospect_id'] ?? 0);

// Get affiliate id for current context (user or restaurant)
$affiliateId = currentAffiliateId();

if (!$affiliateId || $prospectId <= 0) {
    echo '<div class="alert alert-danger" id="invite-client-error">Invalid request.</div>';
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM prospects WHERE id = ? AND affiliate_id = ? AND status = 'in_setup'");
$stmt->execute([$prospectId, $affiliateId]);
$p = $stmt->fetch();

if (!$p || empty($p['restaurant_id'])) {
    echo '<div class="alert alert-danger" id="invite-client-no-company">Company must be created before inviting users.</div>';
    exit;
}

$csrfToken = generate_csrf_token();
?>
<div class="modal fade show d-block" tabindex="-1" id="invite-client-user-modal" style="background-color: rgba(0,0,0,0.5);">
    <div class="modal-dialog" id="invite-client-user-dialog">
        <div class="modal-content" id="invite-client-user-content">
            <div class="modal-header" id="invite-client-user-header">
                <h5 class="modal-title" id="invite-client-user-title">
                    <i class="feather-user-plus me-2"></i>Invite Client User
                </h5>
                <button type="button" class="btn-close" id="invite-client-user-close-btn"
                        onclick="document.getElementById('modal-container').innerHTML=''"></button>
            </div>
            <div class="modal-body" id="invite-client-user-body">
                <p class="text-muted mb-3" id="invite-client-user-info">
                    Add a user to <strong><?php echo htmlspecialchars($p['business_name']); ?></strong>.
                    If no password is set, the user can set their own via Accept Invitation.
                </p>

                <div id="invite-client-user-messages"></div>

                <form id="invite-client-user-form"
                      hx-post="/partials/affiliate/save-client-user.php"
                      hx-target="#invite-client-user-messages"
                      hx-swap="innerHTML">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="prospect_id" value="<?php echo $prospectId; ?>">

                    <div class="row" id="invite-client-user-name-row">
                        <div class="col-md-6 mb-3" id="invite-client-user-fname-group">
                            <label for="invite-client-user-fname" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="invite-client-user-fname" name="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3" id="invite-client-user-lname-group">
                            <label for="invite-client-user-lname" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="invite-client-user-lname" name="last_name" required>
                        </div>
                    </div>

                    <div class="mb-3" id="invite-client-user-email-group">
                        <label for="invite-client-user-email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="invite-client-user-email" name="email" required>
                        <div class="form-text" id="invite-client-user-email-help">If this email already exists, the user will be linked to this company.</div>
                    </div>

                    <div class="mb-3" id="invite-client-user-phone-group">
                        <label for="invite-client-user-phone" class="form-label">Phone</label>
                        <input type="tel" class="form-control" id="invite-client-user-phone" name="phone">
                    </div>

                    <div class="mb-3" id="invite-client-user-role-group">
                        <label for="invite-client-user-role" class="form-label">Role <span class="text-danger">*</span></label>
                        <select class="form-select" id="invite-client-user-role" name="role" required>
                            <option value="">Select Role...</option>
                            <option value="admin">Admin</option>
                            <option value="manager">Manager</option>
                            <option value="user">User</option>
                        </select>
                    </div>

                    <div class="mb-3" id="invite-client-user-password-group">
                        <label for="invite-client-user-password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="invite-client-user-password" name="password" minlength="8">
                        <div class="form-text" id="invite-client-user-password-help">Leave blank to let user set their own password via Accept Invitation.</div>
                    </div>

                    <div class="d-flex justify-content-end gap-2" id="invite-client-user-buttons">
                        <button type="button" class="btn btn-secondary" id="invite-client-user-cancel-btn"
                                onclick="document.getElementById('modal-container').innerHTML=''">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="invite-client-user-submit-btn">
                            <i class="feather-send me-1"></i> Invite User
                            <span class="htmx-indicator spinner-border spinner-border-sm ms-2"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
