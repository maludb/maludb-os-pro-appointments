<?php
/**
 * Prospects List — shows prospects filtered by status
 * ?status=in_setup  → In-Setup pipeline
 * ?status=client    → Active clients
 * (no status)       → All prospects (new/contacted/qualified)
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../helpers/db.php';

requireAuth();

$pdo = db();
$restaurantId = currentRestaurantId();

// Verify current restaurant is an affiliate location
$rStmt = $pdo->prepare("SELECT location_type, affiliate_id FROM restaurants WHERE id = ?");
$rStmt->execute([$restaurantId]);
$restaurant = $rStmt->fetch();

if (!$restaurant || $restaurant['location_type'] !== 'affiliate') {
    echo '<div class="alert alert-warning m-4">This feature is only available for affiliate locations.</div>';
    exit;
}

$affiliateId = currentAffiliateId();

$statusFilter = $_GET['status'] ?? '';

// Determine which statuses to show and page title
if ($statusFilter === 'in_setup') {
    $whereClause = "AND p.status = 'in_setup'";
    $pageTitle = 'In-Setup';
    $pageIcon = 'feather-settings';
    $pageSubtitle = 'Prospects currently being set up';
} elseif ($statusFilter === 'client') {
    $whereClause = "AND p.status = 'client'";
    $pageTitle = 'Clients';
    $pageIcon = 'feather-check-circle';
    $pageSubtitle = 'Active clients';
} else {
    $whereClause = "AND p.status IN ('new', 'contacted', 'qualified')";
    $pageTitle = 'Prospects';
    $pageIcon = 'feather-user-plus';
    $pageSubtitle = 'Potential clients in your pipeline';
}

$stmt = $pdo->prepare(
    "SELECT p.* FROM prospects p
     WHERE p.affiliate_id = ? $whereClause
     ORDER BY p.updated_at DESC"
);
$stmt->execute([$affiliateId]);
$prospects = $stmt->fetchAll();

$statusColors = [
    'new' => 'primary',
    'contacted' => 'info',
    'qualified' => 'warning',
    'in_setup' => 'secondary',
    'client' => 'success',
    'lost' => 'danger',
];

$statusLabels = [
    'new' => 'New',
    'contacted' => 'Contacted',
    'qualified' => 'Qualified',
    'in_setup' => 'In-Setup',
    'client' => 'Client',
    'lost' => 'Lost',
];
?>

<div class="main-content p-4" id="prospects-page">
    <div class="d-flex justify-content-between align-items-center mb-4" id="prospects-header">
        <div id="prospects-title-wrap">
            <h4 class="mb-0" id="prospects-title"><i class="<?php echo $pageIcon; ?> me-2"></i><?php echo $pageTitle; ?></h4>
            <small class="text-muted" id="prospects-subtitle"><?php echo $pageSubtitle; ?> (<?php echo count($prospects); ?>)</small>
        </div>
        <button class="btn btn-primary" id="prospects-add-btn"
                hx-get="/partials/affiliate/prospect-form.php"
                hx-target="#modal-container">
            <i class="feather-plus me-1"></i> Add Prospect
        </button>
    </div>

    <div class="card border-0 shadow-sm" id="prospects-card">
        <div class="card-body p-0" id="prospects-card-body">
            <?php if (empty($prospects)): ?>
            <div class="text-center text-muted py-5" id="prospects-empty">
                <i class="<?php echo $pageIcon; ?> d-block mb-2" style="font-size:2rem;"></i>
                <p>No <?php echo strtolower($pageTitle); ?> found.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive" id="prospects-table-wrap">
                <table class="table table-hover mb-0" id="prospects-table">
                    <thead class="bg-light" id="prospects-thead">
                        <tr>
                            <th>Business Name</th>
                            <th>Contact</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Product</th>
                            <th>Status</th>
                            <th>Last Updated</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="prospects-tbody">
                        <?php foreach ($prospects as $p): ?>
                        <tr id="prospect-row-<?php echo (int)$p['id']; ?>" style="cursor:pointer;"
                            hx-get="/partials/affiliate/prospect-detail.php?id=<?php echo (int)$p['id']; ?>"
                            hx-target="#page-content">
                            <td>
                                <strong><?php echo htmlspecialchars($p['business_name']); ?></strong>
                                <?php if ($p['business_type']): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($p['business_type']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($p['contact_name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($p['contact_phone'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($p['contact_email'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($p['product_interest'] ?? 'restaurant')); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $statusColors[$p['status']] ?? 'secondary'; ?>">
                                    <?php echo $statusLabels[$p['status']] ?? ucfirst($p['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($p['updated_at'])); ?></td>
                            <td class="text-end" onclick="event.stopPropagation();">
                                <button class="btn btn-sm btn-outline-primary me-1"
                                        hx-get="/partials/affiliate/prospect-detail.php?id=<?php echo (int)$p['id']; ?>"
                                        hx-target="#page-content"
                                        title="Open Dashboard"
                                        id="prospect-edit-btn-<?php echo (int)$p['id']; ?>">
                                    <i class="feather-eye"></i>
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
