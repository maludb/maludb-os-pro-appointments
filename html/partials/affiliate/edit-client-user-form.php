<?php
/**
 * Edit Client User — modal form for affiliates to edit a user linked to their prospect's company.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../helpers/db.php';

requireAuth();

$pdo = db();
$userId = currentUserId();
$urId = (int)($_GET['ur_id'] ?? 0);

// Get affiliate id for current context (user or restaurant)
$affiliateId = currentAffiliateId();

if (!$affiliateId || $urId <= 0) {
    echo '<div class="alert alert-danger" id="edit-client-user-error">Invalid request.</div>';
    exit;
}

// Load user_restaurants + user + verify affiliate owns this restaurant via prospects
$stmt = $pdo->prepare(
    "SELECT u.id AS user_id, u.first_name, u.last_name, u.email, u.phone,
            ur.id AS ur_id, ur.role, ur.restaurant_id,
            r.name AS restaurant_name,
            p.id AS prospect_id, p.business_name
     FROM user_restaurants ur
     JOIN users u ON u.id = ur.user_id
     JOIN restaurants r ON r.id = ur.restaurant_id
     JOIN prospects p ON p.restaurant_id = r.id AND p.affiliate_id = ?
     WHERE ur.id = ?"
);
$stmt->execute([$affiliateId, $urId]);
$row = $stmt->fetch();

if (!$row) {
    echo '<div class="alert alert-danger" id="edit-client-user-not-found">User not found or access denied.</div>';
    exit;
}

$csrfToken = generate_csrf_token();
?>
<div class="modal fade show d-block" tabindex="-1" id="edit-client-user-modal" style="background-color: rgba(0,0,0,0.5);">
    <div class="modal-dialog" id="edit-client-user-dialog">
        <div class="modal-content" id="edit-client-user-content">
            <div class="modal-header" id="edit-client-user-header">
                <h5 class="modal-title" id="edit-client-user-title">
                    <i class="feather-edit-2 me-2"></i>Edit User
                </h5>
                <button type="button" class="btn-close" id="edit-client-user-close-btn"
                        onclick="document.getElementById('modal-container').innerHTML=''"></button>
            </div>
            <div class="modal-body" id="edit-client-user-body">
                <p class="text-muted mb-3" id="edit-client-user-info">
                    Editing user for <strong><?php echo htmlspecialchars($row['restaurant_name']); ?></strong>
                </p>

                <div id="edit-client-user-messages"></div>

                <form id="edit-client-user-form"
                      hx-post="/partials/affiliate/update-client-user.php"
                      hx-target="#page-content"
                      hx-swap="innerHTML">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="ur_id" value="<?php echo $urId; ?>">

                    <div class="row" id="edit-client-user-name-row">
                        <div class="col-md-6 mb-3" id="edit-client-user-fname-group">
                            <label for="edit-client-user-fname" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit-client-user-fname" name="first_name"
                                   value="<?php echo htmlspecialchars($row['first_name']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3" id="edit-client-user-lname-group">
                            <label for="edit-client-user-lname" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit-client-user-lname" name="last_name"
                                   value="<?php echo htmlspecialchars($row['last_name']); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3" id="edit-client-user-email-group">
                        <label for="edit-client-user-email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="edit-client-user-email"
                               value="<?php echo htmlspecialchars($row['email']); ?>" disabled>
                        <div class="form-text" id="edit-client-user-email-help">Email cannot be changed.</div>
                    </div>

                    <div class="mb-3" id="edit-client-user-phone-group">
                        <label for="edit-client-user-phone" class="form-label">Phone</label>
                        <input type="tel" class="form-control" id="edit-client-user-phone" name="phone"
                               value="<?php echo htmlspecialchars($row['phone'] ?? ''); ?>">
                    </div>

                    <div class="mb-3" id="edit-client-user-role-group">
                        <label for="edit-client-user-role" class="form-label">Role <span class="text-danger">*</span></label>
                        <select class="form-select" id="edit-client-user-role" name="role" required>
                            <option value="admin" <?php echo $row['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="manager" <?php echo $row['role'] === 'manager' ? 'selected' : ''; ?>>Manager</option>
                            <option value="user" <?php echo $row['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                        </select>
                    </div>

                    <div class="d-flex justify-content-end gap-2" id="edit-client-user-buttons">
                        <button type="button" class="btn btn-secondary" id="edit-client-user-cancel-btn"
                                onclick="document.getElementById('modal-container').innerHTML=''">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="edit-client-user-submit-btn">
                            <i class="feather-save me-1"></i> Save Changes
                            <span class="htmx-indicator spinner-border spinner-border-sm ms-2"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
