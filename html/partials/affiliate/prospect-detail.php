<?php
/**
 * Prospect CRM Dashboard — full view with activity timeline, notes, communications
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../helpers/db.php';

requireAuth();

$pdo = db();
$userId = currentUserId();
$id = (int)($_GET['id'] ?? 0);

// Get affiliate id for current context (user or restaurant)
$affiliateId = currentAffiliateId();

if ($id <= 0 || !$affiliateId) {
    echo '<div class="alert alert-danger m-4">Invalid prospect.</div>';
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM prospects WHERE id = ? AND affiliate_id = ?");
$stmt->execute([$id, $affiliateId]);
$p = $stmt->fetch();

if (!$p) {
    echo '<div class="alert alert-danger m-4">Prospect not found.</div>';
    exit;
}

// Load activities
$actStmt = $pdo->prepare(
    "SELECT * FROM prospect_activities WHERE prospect_id = ? AND affiliate_id = ? ORDER BY created_at DESC"
);
$actStmt->execute([$id, $affiliateId]);
$activities = $actStmt->fetchAll();

// Count activities by type
$activityCounts = ['note' => 0, 'call' => 0, 'email' => 0, 'meeting' => 0, 'status_change' => 0];
foreach ($activities as $a) {
    if (isset($activityCounts[$a['activity_type']])) {
        $activityCounts[$a['activity_type']]++;
    }
}

// Load prospect products with product names
$ppStmt = $pdo->prepare(
    "SELECT pp.*, pr.name AS product_name, pr.billing_interval
     FROM prospect_products pp
     JOIN products pr ON pr.id = pp.product_id
     WHERE pp.prospect_id = ? AND pp.affiliate_id = ?
     ORDER BY pp.created_at DESC"
);
$ppStmt->execute([$id, $affiliateId]);
$prospectProducts = $ppStmt->fetchAll();

// Load active products for the add-product dropdown
$prodListStmt = $pdo->query("SELECT id, name, price, affiliate_price, billing_interval FROM products WHERE is_active = 1 ORDER BY sort_order");
$allProducts = $prodListStmt->fetchAll();

$ppStatusColors = [
    'pitched' => 'primary', 'interested' => 'info', 'accepted' => 'success',
    'declined' => 'danger', 'converted' => 'dark',
];
$ppStatusLabels = [
    'pitched' => 'Pitched', 'interested' => 'Interested', 'accepted' => 'Accepted',
    'declined' => 'Declined', 'converted' => 'Converted',
];

$statusColors = [
    'new' => 'primary', 'contacted' => 'info', 'qualified' => 'warning',
    'in_setup' => 'secondary', 'client' => 'success', 'lost' => 'danger',
];
$statusLabels = [
    'new' => 'New', 'contacted' => 'Contacted', 'qualified' => 'Qualified',
    'in_setup' => 'In-Setup', 'client' => 'Client', 'lost' => 'Lost',
];

$activityIcons = [
    'note' => 'feather-file-text', 'call' => 'feather-phone', 'email' => 'feather-mail',
    'meeting' => 'feather-users', 'status_change' => 'feather-refresh-cw',
];
$activityColors = [
    'note' => 'primary', 'call' => 'success', 'email' => 'info',
    'meeting' => 'warning', 'status_change' => 'secondary',
];
$activityLabels = [
    'note' => 'Note', 'call' => 'Call', 'email' => 'Email',
    'meeting' => 'Meeting', 'status_change' => 'Status Change',
];

// Determine back link based on status
$backStatus = '';
$backLabel = 'Prospects';
if ($p['status'] === 'in_setup') { $backStatus = '?status=in_setup'; $backLabel = 'In-Setup'; }
elseif ($p['status'] === 'client') { $backStatus = '?status=client'; $backLabel = 'Clients'; }

$csrfToken = generate_csrf_token();
$daysSinceCreated = (int)((time() - strtotime($p['created_at'])) / 86400);
?>

<div class="main-content p-4" id="prospect-dashboard-page">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4" id="prospect-dash-header">
        <div class="d-flex align-items-center" id="prospect-dash-title-area">
            <button class="btn btn-sm btn-outline-secondary me-3"
                    hx-get="/partials/affiliate/prospects.php<?php echo $backStatus; ?>"
                    hx-target="#page-content"
                    id="prospect-dash-back-btn">
                <i class="feather-arrow-left"></i>
            </button>
            <div id="prospect-dash-title-wrap">
                <h4 class="mb-0 fw-bold" id="prospect-dash-name"><?php echo htmlspecialchars($p['business_name']); ?></h4>
                <small class="text-muted" id="prospect-dash-subtitle">
                    <?php echo htmlspecialchars($p['business_type'] ?? ''); ?>
                    <?php if ($p['business_type'] && $p['city']): ?> &middot; <?php endif; ?>
                    <?php echo htmlspecialchars(implode(', ', array_filter([$p['city'], $p['state']]))); ?>
                </small>
            </div>
        </div>
        <div class="d-flex gap-2 align-items-center" id="prospect-dash-actions">
            <span class="badge bg-<?php echo $statusColors[$p['status']] ?? 'secondary'; ?> fs-6" id="prospect-dash-status-badge">
                <?php echo $statusLabels[$p['status']] ?? ucfirst($p['status']); ?>
            </span>
            <?php if (in_array($p['status'], ['new', 'contacted', 'qualified'])): ?>
            <button class="btn btn-secondary"
                    hx-post="/partials/affiliate/update-prospect-status.php"
                    hx-vals='{"prospect_id": <?php echo (int)$p['id']; ?>, "status": "in_setup", "csrf_token": "<?php echo $csrfToken; ?>"}'
                    hx-target="#page-content"
                    hx-confirm="Move &quot;<?php echo htmlspecialchars($p['business_name']); ?>&quot; to In-Setup?"
                    id="prospect-dash-in-setup-btn">
                <i class="feather-arrow-right-circle me-1"></i> Move to In-Setup
            </button>
            <?php endif; ?>
            <?php if ($p['status'] === 'in_setup'): ?>
            <button class="btn btn-success"
                    hx-post="/partials/affiliate/setup-prospect-company.php"
                    hx-vals='{"prospect_id": <?php echo (int)$p['id']; ?>, "csrf_token": "<?php echo $csrfToken; ?>"}'
                    hx-target="#page-content"
                    hx-confirm="Set up a new company for &quot;<?php echo htmlspecialchars($p['business_name']); ?>&quot;? This will create a company account you can switch to."
                    id="prospect-dash-setup-btn">
                <i class="feather-settings me-1"></i> Company Setup
            </button>
            <?php if (!empty($p['restaurant_id'])): ?>
            <button class="btn btn-outline-info"
                    hx-post="/partials/auth/switch-restaurant.php"
                    hx-vals='{"restaurant_id": <?php echo (int)$p['restaurant_id']; ?>}'
                    hx-target="#page-content"
                    id="prospect-dash-switch-btn">
                <i class="feather-repeat me-1"></i> Switch to Location
            </button>
            <button class="btn btn-info"
                    hx-get="/partials/affiliate/invite-client-user-form.php?prospect_id=<?php echo (int)$p['id']; ?>"
                    hx-target="#modal-container"
                    id="prospect-dash-invite-user-btn">
                <i class="feather-user-plus me-1"></i> Invite Client User
            </button>
            <?php endif; ?>
            <?php endif; ?>
            <button class="btn btn-outline-primary"
                    hx-get="/partials/affiliate/prospect-form.php?id=<?php echo (int)$p['id']; ?>"
                    hx-target="#modal-container"
                    id="prospect-dash-edit-btn">
                <i class="feather-edit-2 me-1"></i> Edit
            </button>
            <button class="btn btn-outline-danger"
                    hx-post="/partials/affiliate/delete-prospect.php"
                    hx-vals='{"id": <?php echo (int)$p['id']; ?>, "csrf_token": "<?php echo $csrfToken; ?>"}'
                    hx-target="#page-content"
                    hx-confirm="Delete this prospect? This cannot be undone."
                    id="prospect-dash-delete-btn">
                <i class="feather-trash-2 me-1"></i> Delete
            </button>
        </div>
    </div>

    <!-- Quick Stats Row -->
    <div class="row g-3 mb-4" id="prospect-dash-stats-row">
        <div class="col-6 col-md-3" id="prospect-dash-stat-days">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="card-body py-1" id="prospect-dash-stat-days-body">
                    <h3 class="mb-0 fw-bold text-primary"><?php echo $daysSinceCreated; ?></h3>
                    <small class="text-muted">Days in Pipeline</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3" id="prospect-dash-stat-activities">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="card-body py-1" id="prospect-dash-stat-activities-body">
                    <h3 class="mb-0 fw-bold text-info"><?php echo count($activities); ?></h3>
                    <small class="text-muted">Total Activities</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3" id="prospect-dash-stat-calls">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="card-body py-1" id="prospect-dash-stat-calls-body">
                    <h3 class="mb-0 fw-bold text-success"><?php echo $activityCounts['call']; ?></h3>
                    <small class="text-muted">Calls Logged</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3" id="prospect-dash-stat-products">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="card-body py-1" id="prospect-dash-stat-products-body">
                    <h3 class="mb-0 fw-bold text-warning"><?php echo count($prospectProducts); ?></h3>
                    <small class="text-muted">Products Pitched</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4" id="prospect-dash-content">
        <!-- Left Column: Activity Timeline -->
        <div class="col-lg-8" id="prospect-dash-left">

            <!-- Add Activity Form -->
            <div class="card border-0 shadow-sm mb-4" id="prospect-dash-add-activity-card">
                <div class="card-header bg-white" id="prospect-dash-add-activity-header">
                    <h6 class="mb-0 fw-semibold"><i class="feather-plus-circle me-2"></i>Log Activity</h6>
                </div>
                <div class="card-body" id="prospect-dash-add-activity-body">
                    <form hx-post="/partials/affiliate/save-prospect-activity.php"
                          hx-target="#page-content"
                          id="prospect-dash-activity-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="prospect_id" value="<?php echo (int)$p['id']; ?>">

                        <div class="row g-3" id="prospect-dash-activity-fields">
                            <div class="col-md-4" id="prospect-dash-activity-type-wrap">
                                <select class="form-select" name="activity_type" id="prospect-dash-activity-type">
                                    <option value="note">Note</option>
                                    <option value="call">Phone Call</option>
                                    <option value="email">Email</option>
                                    <option value="meeting">Meeting</option>
                                </select>
                            </div>
                            <div class="col-md-8" id="prospect-dash-activity-subject-wrap">
                                <input type="text" class="form-control" name="subject" placeholder="Subject (optional)" id="prospect-dash-activity-subject">
                            </div>
                            <div class="col-12" id="prospect-dash-activity-body-wrap">
                                <textarea class="form-control" name="body" rows="3" placeholder="Details..." id="prospect-dash-activity-body"></textarea>
                            </div>
                            <div class="col-12 text-end" id="prospect-dash-activity-submit-wrap">
                                <button type="submit" class="btn btn-primary" id="prospect-dash-activity-submit">
                                    <i class="feather-save me-1"></i> Save Activity
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Products Pitched -->
            <div class="card border-0 shadow-sm mb-4" id="prospect-dash-products-card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center" id="prospect-dash-products-header">
                    <h6 class="mb-0 fw-semibold"><i class="feather-package me-2"></i>Products Pitched</h6>
                    <span class="badge bg-light text-dark" id="prospect-dash-products-count"><?php echo count($prospectProducts); ?> products</span>
                </div>
                <div class="card-body" id="prospect-dash-products-body">
                    <!-- Add Product Form -->
                    <form hx-post="/partials/affiliate/save-prospect-product.php"
                          hx-target="#page-content"
                          class="mb-3"
                          id="prospect-dash-add-product-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="prospect_id" value="<?php echo (int)$p['id']; ?>">
                        <input type="hidden" name="pp_id" value="0">

                        <div class="row g-2" id="prospect-dash-add-product-fields">
                            <div class="col-md-5" id="prospect-dash-add-product-select-wrap">
                                <select class="form-select form-select-sm" name="product_id" required
                                        id="prospect-dash-add-product-select"
                                        onchange="updateQuotedPrice(this)">
                                    <option value="">Select a product...</option>
                                    <?php foreach ($allProducts as $prod): ?>
                                    <option value="<?php echo (int)$prod['id']; ?>"
                                            data-price="<?php echo $prod['price']; ?>"
                                            data-affiliate-price="<?php echo $prod['affiliate_price']; ?>">
                                        <?php echo htmlspecialchars($prod['name']); ?> ($<?php echo number_format($prod['price'], 2); ?>/<?php echo $prod['billing_interval']; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3" id="prospect-dash-add-product-price-wrap">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                                           name="quoted_price" placeholder="Quoted price"
                                           id="prospect-dash-add-product-price">
                                </div>
                            </div>
                            <div class="col-md-2" id="prospect-dash-add-product-status-wrap">
                                <select class="form-select form-select-sm" name="status" id="prospect-dash-add-product-status">
                                    <option value="pitched">Pitched</option>
                                    <option value="interested">Interested</option>
                                    <option value="accepted">Accepted</option>
                                    <option value="declined">Declined</option>
                                    <option value="converted">Converted</option>
                                </select>
                            </div>
                            <div class="col-md-2" id="prospect-dash-add-product-submit-wrap">
                                <button type="submit" class="btn btn-primary btn-sm w-100" id="prospect-dash-add-product-submit">
                                    <i class="feather-plus"></i> Add
                                </button>
                            </div>
                        </div>
                    </form>

                    <?php if (empty($prospectProducts)): ?>
                    <div class="text-center text-muted py-3" id="prospect-dash-products-empty">
                        <i class="feather-package d-block mb-2" style="font-size:1.5rem;"></i>
                        <p class="mb-0">No products pitched yet.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive" id="prospect-dash-products-table-wrap">
                        <table class="table table-sm table-hover mb-0" id="prospect-dash-products-table">
                            <thead class="bg-light" id="prospect-dash-products-thead">
                                <tr>
                                    <th>Product</th>
                                    <th class="text-end">Recommended</th>
                                    <th class="text-end">Affiliate</th>
                                    <th class="text-end">Quoted</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="prospect-dash-products-tbody">
                                <?php foreach ($prospectProducts as $pp): ?>
                                <tr id="prospect-product-row-<?php echo (int)$pp['id']; ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars($pp['product_name']); ?></strong>
                                        <br><small class="text-muted"><?php echo ucfirst($pp['billing_interval']); ?></small>
                                    </td>
                                    <td class="text-end">$<?php echo number_format($pp['recommended_price'], 2); ?></td>
                                    <td class="text-end">$<?php echo number_format($pp['affiliate_price'], 2); ?></td>
                                    <td class="text-end fw-semibold">$<?php echo number_format($pp['quoted_price'], 2); ?></td>
                                    <td>
                                        <select class="form-select form-select-sm border-0 p-0 ps-1"
                                                style="width:auto; background-color:transparent;"
                                                hx-post="/partials/affiliate/save-prospect-product.php"
                                                hx-target="#page-content"
                                                hx-vals='{"csrf_token":"<?php echo $csrfToken; ?>","pp_id":<?php echo (int)$pp['id']; ?>,"prospect_id":<?php echo (int)$p['id']; ?>,"product_id":<?php echo (int)$pp['product_id']; ?>,"quoted_price":"<?php echo $pp['quoted_price']; ?>"}'
                                                name="status"
                                                id="prospect-product-status-<?php echo (int)$pp['id']; ?>">
                                            <?php foreach ($ppStatusLabels as $val => $label): ?>
                                            <option value="<?php echo $val; ?>" <?php echo $pp['status'] === $val ? 'selected' : ''; ?>>
                                                <?php echo $label; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-danger border-0 p-0 px-1"
                                                hx-post="/partials/affiliate/delete-prospect-product.php"
                                                hx-vals='{"pp_id": <?php echo (int)$pp['id']; ?>, "csrf_token": "<?php echo $csrfToken; ?>"}'
                                                hx-target="#page-content"
                                                hx-confirm="Remove this product?"
                                                title="Remove"
                                                id="prospect-product-del-<?php echo (int)$pp['id']; ?>">
                                            <i class="feather-x" style="font-size:14px;"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot id="prospect-dash-products-tfoot">
                                <tr class="bg-light fw-semibold">
                                    <td>Totals</td>
                                    <td class="text-end">$<?php echo number_format(array_sum(array_column($prospectProducts, 'recommended_price')), 2); ?></td>
                                    <td class="text-end">$<?php echo number_format(array_sum(array_column($prospectProducts, 'affiliate_price')), 2); ?></td>
                                    <td class="text-end">$<?php echo number_format(array_sum(array_column($prospectProducts, 'quoted_price')), 2); ?></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Activity Timeline -->
            <div class="card border-0 shadow-sm mb-4" id="prospect-dash-timeline-card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center" id="prospect-dash-timeline-header">
                    <h6 class="mb-0 fw-semibold"><i class="feather-activity me-2"></i>Activity Timeline</h6>
                    <span class="badge bg-light text-dark" id="prospect-dash-timeline-count"><?php echo count($activities); ?> entries</span>
                </div>
                <div class="card-body" id="prospect-dash-timeline-body">
                    <?php if (empty($activities)): ?>
                    <div class="text-center text-muted py-4" id="prospect-dash-timeline-empty">
                        <i class="feather-inbox d-block mb-2" style="font-size:2rem;"></i>
                        <p class="mb-0">No activities yet. Log your first interaction above.</p>
                    </div>
                    <?php else: ?>
                    <div class="activity-feed" id="prospect-dash-activity-list">
                        <?php foreach ($activities as $a): ?>
                        <div class="d-flex mb-4" id="prospect-dash-activity-<?php echo (int)$a['id']; ?>">
                            <div class="me-3 flex-shrink-0" id="prospect-dash-activity-icon-<?php echo (int)$a['id']; ?>">
                                <div class="rounded-circle d-flex align-items-center justify-content-center bg-<?php echo $activityColors[$a['activity_type']] ?? 'secondary'; ?>-subtle text-<?php echo $activityColors[$a['activity_type']] ?? 'secondary'; ?>"
                                     style="width:40px;height:40px;">
                                    <i class="<?php echo $activityIcons[$a['activity_type']] ?? 'feather-file-text'; ?>"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1" id="prospect-dash-activity-content-<?php echo (int)$a['id']; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <span class="badge bg-<?php echo $activityColors[$a['activity_type']] ?? 'secondary'; ?>-subtle text-<?php echo $activityColors[$a['activity_type']] ?? 'secondary'; ?> me-2">
                                            <?php echo $activityLabels[$a['activity_type']] ?? 'Note'; ?>
                                        </span>
                                        <?php if ($a['subject']): ?>
                                        <strong><?php echo htmlspecialchars($a['subject']); ?></strong>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <small class="text-muted"><?php echo date('M j, Y g:ia', strtotime($a['created_at'])); ?></small>
                                        <button class="btn btn-sm btn-outline-danger border-0 p-0 px-1"
                                                hx-post="/partials/affiliate/delete-prospect-activity.php"
                                                hx-vals='{"activity_id": <?php echo (int)$a['id']; ?>, "csrf_token": "<?php echo $csrfToken; ?>"}'
                                                hx-target="#page-content"
                                                hx-confirm="Delete this activity?"
                                                title="Delete"
                                                id="prospect-dash-activity-del-<?php echo (int)$a['id']; ?>">
                                            <i class="feather-x" style="font-size:14px;"></i>
                                        </button>
                                    </div>
                                </div>
                                <?php if ($a['body']): ?>
                                <p class="mb-0 mt-1" style="white-space:pre-wrap;"><?php echo htmlspecialchars($a['body']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Legacy Notes (from prospects.notes field) -->
            <?php if ($p['notes']): ?>
            <div class="card border-0 shadow-sm mb-4" id="prospect-dash-legacy-notes-card">
                <div class="card-header bg-white" id="prospect-dash-legacy-notes-header">
                    <h6 class="mb-0 fw-semibold"><i class="feather-bookmark me-2"></i>Prospect Notes</h6>
                </div>
                <div class="card-body" id="prospect-dash-legacy-notes-body">
                    <p class="mb-0" style="white-space:pre-wrap;"><?php echo htmlspecialchars($p['notes']); ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Column: Info Cards -->
        <div class="col-lg-4" id="prospect-dash-right">

            <!-- Business Info -->
            <div class="card border-0 shadow-sm mb-4" id="prospect-dash-business-card">
                <div class="card-header bg-white" id="prospect-dash-business-header">
                    <h6 class="mb-0 fw-semibold"><i class="feather-briefcase me-2"></i>Business Info</h6>
                </div>
                <div class="card-body" id="prospect-dash-business-body">
                    <div class="mb-3" id="prospect-dash-biz-name">
                        <small class="text-muted d-block">Business Name</small>
                        <strong><?php echo htmlspecialchars($p['business_name']); ?></strong>
                    </div>
                    <?php if ($p['business_type']): ?>
                    <div class="mb-3" id="prospect-dash-biz-type">
                        <small class="text-muted d-block">Type</small>
                        <strong><?php echo htmlspecialchars($p['business_type']); ?></strong>
                    </div>
                    <?php endif; ?>
                    <?php if ($p['address']): ?>
                    <div class="mb-3" id="prospect-dash-biz-address">
                        <small class="text-muted d-block">Address</small>
                        <strong><?php
                            echo htmlspecialchars($p['address']);
                            $csz = implode(', ', array_filter([$p['city'], $p['state']]));
                            if ($p['zip']) $csz .= ' ' . $p['zip'];
                            if ($csz) echo '<br>' . htmlspecialchars($csz);
                        ?></strong>
                    </div>
                    <?php endif; ?>
                    <?php if ($p['website']): ?>
                    <div class="mb-3" id="prospect-dash-biz-website">
                        <small class="text-muted d-block">Website</small>
                        <strong><?php echo htmlspecialchars($p['website']); ?></strong>
                    </div>
                    <?php endif; ?>
                    <div class="mb-3" id="prospect-dash-biz-product">
                        <small class="text-muted d-block">Product Interest</small>
                        <strong><?php echo htmlspecialchars(ucfirst($p['product_interest'] ?? 'restaurant')); ?></strong>
                    </div>
                    <?php if ($p['source']): ?>
                    <div class="mb-0" id="prospect-dash-biz-source">
                        <small class="text-muted d-block">Lead Source</small>
                        <strong><?php echo htmlspecialchars($p['source']); ?></strong>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Contact Info -->
            <div class="card border-0 shadow-sm mb-4" id="prospect-dash-contact-card">
                <div class="card-header bg-white" id="prospect-dash-contact-header">
                    <h6 class="mb-0 fw-semibold"><i class="feather-user me-2"></i>Contact</h6>
                </div>
                <div class="card-body" id="prospect-dash-contact-body">
                    <?php if ($p['contact_name']): ?>
                    <div class="mb-3" id="prospect-dash-cname">
                        <small class="text-muted d-block">Name</small>
                        <strong><?php echo htmlspecialchars($p['contact_name']); ?></strong>
                    </div>
                    <?php endif; ?>
                    <?php if ($p['contact_phone']): ?>
                    <div class="mb-3" id="prospect-dash-cphone">
                        <small class="text-muted d-block">Phone</small>
                        <strong><?php echo htmlspecialchars($p['contact_phone']); ?></strong>
                    </div>
                    <?php endif; ?>
                    <?php if ($p['contact_email']): ?>
                    <div class="mb-0" id="prospect-dash-cemail">
                        <small class="text-muted d-block">Email</small>
                        <strong><?php echo htmlspecialchars($p['contact_email']); ?></strong>
                    </div>
                    <?php endif; ?>
                    <?php if (!$p['contact_name'] && !$p['contact_phone'] && !$p['contact_email']): ?>
                    <p class="text-muted mb-0">No contact information.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Timeline -->
            <div class="card border-0 shadow-sm mb-4" id="prospect-dash-dates-card">
                <div class="card-header bg-white" id="prospect-dash-dates-header">
                    <h6 class="mb-0 fw-semibold"><i class="feather-clock me-2"></i>Timeline</h6>
                </div>
                <div class="card-body" id="prospect-dash-dates-body">
                    <div class="mb-2" id="prospect-dash-created">
                        <small class="text-muted d-block">Created</small>
                        <strong><?php echo date('M j, Y g:ia', strtotime($p['created_at'])); ?></strong>
                    </div>
                    <div class="mb-0" id="prospect-dash-updated">
                        <small class="text-muted d-block">Last Updated</small>
                        <strong><?php echo date('M j, Y g:ia', strtotime($p['updated_at'])); ?></strong>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function updateQuotedPrice(sel) {
    var opt = sel.options[sel.selectedIndex];
    var price = opt.getAttribute('data-price') || '';
    document.getElementById('prospect-dash-add-product-price').value = price;
}
</script>
