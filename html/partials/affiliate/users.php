<?php
/**
 * Affiliate Users — list all users invited to companies managed by this affiliate.
 * Shows invitation status, role, and billing_user designation.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../helpers/db.php';

requireAuth();

$pdo = db();
$userId = currentUserId();

// Get affiliate id for current context (user or restaurant)
$affiliateId = currentAffiliateId();

if (!$affiliateId) {
    echo '<div class="alert alert-danger m-4" id="aff-users-no-affiliate">Not an affiliate.</div>';
    exit;
}

// Load all users linked to restaurants owned by this affiliate's prospects
$stmt = $pdo->prepare(
    "SELECT u.id AS user_id, u.first_name, u.last_name, u.email, u.phone,
            u.password_hash, u.is_active AS user_active, u.created_at AS user_created_at,
            ur.id AS ur_id, ur.role, ur.is_active AS ur_active, ur.created_at AS linked_at,
            r.id AS restaurant_id, r.name AS restaurant_name, r.billing_user,
            p.id AS prospect_id, p.business_name, p.status AS prospect_status
     FROM prospects p
     JOIN restaurants r ON r.id = p.restaurant_id
     JOIN user_restaurants ur ON ur.restaurant_id = r.id
     JOIN users u ON u.id = ur.user_id
     WHERE p.affiliate_id = ?
     ORDER BY r.name, u.last_name, u.first_name"
);
$stmt->execute([$affiliateId]);
$rows = $stmt->fetchAll();

// Group by restaurant
$grouped = [];
foreach ($rows as $row) {
    $rid = (int)$row['restaurant_id'];
    if (!isset($grouped[$rid])) {
        $grouped[$rid] = [
            'restaurant_id' => $rid,
            'restaurant_name' => $row['restaurant_name'],
            'business_name' => $row['business_name'],
            'prospect_id' => (int)$row['prospect_id'],
            'prospect_status' => $row['prospect_status'],
            'billing_user' => (int)$row['billing_user'],
            'users' => [],
        ];
    }
    $grouped[$rid]['users'][] = $row;
}

$csrfToken = generate_csrf_token();

$roleLabels = ['admin' => 'Admin', 'manager' => 'Manager', 'user' => 'User', 'owner' => 'Owner', 'host' => 'Host'];
$roleColors = ['admin' => 'danger', 'manager' => 'warning', 'user' => 'info', 'owner' => 'dark', 'host' => 'secondary'];
?>

<div class="main-content p-4" id="aff-users-page">
    <div class="d-flex justify-content-between align-items-center mb-4" id="aff-users-header">
        <div id="aff-users-title-area">
            <h4 class="mb-0 fw-bold" id="aff-users-title"><i class="feather-users me-2"></i>Users</h4>
            <small class="text-muted" id="aff-users-subtitle">All users invited to your companies</small>
        </div>
    </div>

    <?php if (empty($grouped)): ?>
    <div class="card border-0 shadow-sm" id="aff-users-empty-card">
        <div class="card-body text-center py-5" id="aff-users-empty-body">
            <i class="feather-users d-block mb-3" style="font-size:2.5rem;" id="aff-users-empty-icon"></i>
            <h5 class="text-muted" id="aff-users-empty-text">No users found</h5>
            <p class="text-muted mb-0" id="aff-users-empty-hint">Users will appear here once you invite them to your prospect companies.</p>
        </div>
    </div>
    <?php else: ?>

    <?php foreach ($grouped as $rid => $company): ?>
    <div class="card border-0 shadow-sm mb-4" id="aff-users-company-<?php echo $rid; ?>">
        <div class="card-header bg-white d-flex justify-content-between align-items-center" id="aff-users-company-header-<?php echo $rid; ?>">
            <div id="aff-users-company-title-<?php echo $rid; ?>">
                <h6 class="mb-0 fw-semibold">
                    <i class="feather-briefcase me-2"></i><?php echo htmlspecialchars($company['restaurant_name']); ?>
                </h6>
                <small class="text-muted"><?php echo count($company['users']); ?> user<?php echo count($company['users']) !== 1 ? 's' : ''; ?></small>
            </div>
            <div class="d-flex align-items-center gap-2" id="aff-users-company-actions-<?php echo $rid; ?>">
                <?php if (!empty($company['prospect_id'])): ?>
                <button class="btn btn-sm btn-outline-primary"
                        hx-get="/partials/affiliate/invite-client-user-form.php?prospect_id=<?php echo $company['prospect_id']; ?>"
                        hx-target="#modal-container"
                        id="aff-users-invite-btn-<?php echo $rid; ?>">
                    <i class="feather-user-plus me-1"></i> Invite User
                </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body p-0" id="aff-users-company-body-<?php echo $rid; ?>">
            <div class="table-responsive" id="aff-users-table-wrap-<?php echo $rid; ?>">
                <table class="table table-hover mb-0" id="aff-users-table-<?php echo $rid; ?>">
                    <thead class="bg-light" id="aff-users-thead-<?php echo $rid; ?>">
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Billing</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="aff-users-tbody-<?php echo $rid; ?>">
                        <?php foreach ($company['users'] as $u): ?>
                        <?php
                            $isInvited = ($u['password_hash'] === '!INVITED');
                            $isBillingUser = ((int)$company['billing_user'] === (int)$u['user_id']);
                            $isActive = (int)$u['ur_active'];
                        ?>
                        <tr id="aff-users-row-<?php echo (int)$u['ur_id']; ?>">
                            <td id="aff-users-name-<?php echo (int)$u['ur_id']; ?>">
                                <strong><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></strong>
                            </td>
                            <td id="aff-users-email-<?php echo (int)$u['ur_id']; ?>">
                                <?php echo htmlspecialchars($u['email']); ?>
                            </td>
                            <td id="aff-users-phone-<?php echo (int)$u['ur_id']; ?>">
                                <?php echo htmlspecialchars($u['phone'] ?? ''); ?>
                            </td>
                            <td id="aff-users-role-<?php echo (int)$u['ur_id']; ?>">
                                <span class="badge bg-<?php echo $roleColors[$u['role']] ?? 'secondary'; ?>">
                                    <?php echo $roleLabels[$u['role']] ?? ucfirst($u['role']); ?>
                                </span>
                            </td>
                            <td id="aff-users-status-<?php echo (int)$u['ur_id']; ?>">
                                <?php if (!$isActive): ?>
                                    <span class="badge bg-danger">Removed</span>
                                <?php elseif ($isInvited): ?>
                                    <span class="badge bg-warning text-dark">Pending Invite</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Active</span>
                                <?php endif; ?>
                            </td>
                            <td id="aff-users-billing-<?php echo (int)$u['ur_id']; ?>">
                                <?php if ($isBillingUser): ?>
                                    <span class="badge bg-dark"><i class="feather-credit-card me-1"></i>Billing</span>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-secondary border-0 p-0 px-1"
                                            hx-post="/partials/affiliate/set-billing-user.php"
                                            hx-vals='{"restaurant_id":<?php echo $rid; ?>,"user_id":<?php echo (int)$u['user_id']; ?>,"csrf_token":"<?php echo $csrfToken; ?>"}'
                                            hx-target="#page-content"
                                            hx-confirm="Set <?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?> as the billing contact for <?php echo htmlspecialchars($company['restaurant_name']); ?>?"
                                            title="Set as billing contact"
                                            id="aff-users-set-billing-<?php echo (int)$u['ur_id']; ?>">
                                        <i class="feather-credit-card" style="font-size:14px;"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td class="text-end" id="aff-users-actions-<?php echo (int)$u['ur_id']; ?>">
                                <div class="d-flex justify-content-end gap-1" id="aff-users-action-btns-<?php echo (int)$u['ur_id']; ?>">
                                    <button class="btn btn-sm btn-outline-primary border-0 p-0 px-1"
                                            hx-get="/partials/affiliate/edit-client-user-form.php?ur_id=<?php echo (int)$u['ur_id']; ?>"
                                            hx-target="#modal-container"
                                            title="Edit"
                                            id="aff-users-edit-<?php echo (int)$u['ur_id']; ?>">
                                        <i class="feather-edit-2" style="font-size:14px;"></i>
                                    </button>
                                    <?php if ($isActive): ?>
                                    <button class="btn btn-sm btn-outline-danger border-0 p-0 px-1"
                                            hx-post="/partials/affiliate/cancel-client-user.php"
                                            hx-vals='{"ur_id":<?php echo (int)$u['ur_id']; ?>,"csrf_token":"<?php echo $csrfToken; ?>"}'
                                            hx-target="#page-content"
                                            hx-confirm="Remove <?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?> from <?php echo htmlspecialchars($company['restaurant_name']); ?>?"
                                            title="Remove user"
                                            id="aff-users-cancel-<?php echo (int)$u['ur_id']; ?>">
                                        <i class="feather-x-circle" style="font-size:14px;"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>
</div>
