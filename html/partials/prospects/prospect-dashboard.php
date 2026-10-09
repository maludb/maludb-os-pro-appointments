<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../config/database.php';

check_auth();
$user = get_user();

// Get prospect ID from request
$prospectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$prospectId) {
    echo '<div class="alert alert-danger">Prospect ID is required.</div>';
    exit;
}

$db = Database::getInstance()->getConnection();

// Get prospect info
$stmt = $db->prepare("SELECT * FROM prospects WHERE id = ?");
$stmt->execute([$prospectId]);
$prospect = $stmt->fetch();

if (!$prospect) {
    echo '<div class="alert alert-danger">Prospect not found.</div>';
    exit;
}
?>

<!-- Prospect Dashboard Page Container -->
<div class="container-fluid pt-4 ps-4" id="prospect-dashboard-page">
    <!-- Page Header -->
    <div class="row mb-4" id="prospect-dashboard-header">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center" id="prospect-dashboard-header-content">
                <div id="prospect-dashboard-header-title">
                    <nav aria-label="breadcrumb" id="prospect-breadcrumb">
                        <ol class="breadcrumb mb-1">
                            <li class="breadcrumb-item">
                                <a href="#" hx-get="/partials/prospects/index.php" hx-target="#page-content">Prospects</a>
                            </li>
                            <li class="breadcrumb-item active"><?= htmlspecialchars($prospect['name']) ?></li>
                        </ol>
                    </nav>
                    <h3 class="mb-0"><?= htmlspecialchars($prospect['name']) ?></h3>
                </div>
                <div id="prospect-dashboard-header-actions">
                    <a href="#"
                       class="btn btn-outline-secondary"
                       id="btn-back-to-prospects"
                       hx-get="/partials/prospects/index.php"
                       hx-target="#page-content">
                        <i class="feather-arrow-left me-2"></i>Back to Prospects
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Prospect Info Section -->
    <div class="row mb-4" id="prospect-info-row">
        <div class="col-12">
            <div class="card" id="prospect-info-card">
                <div class="card-header d-flex justify-content-between align-items-center" id="prospect-info-card-header">
                    <h5 class="card-title mb-0">
                        <i class="feather-info me-2"></i>Prospect Information
                    </h5>
                    <button class="btn btn-sm btn-outline-primary"
                            id="btn-edit-prospect"
                            hx-get="/partials/prospects/edit-form.php?id=<?= $prospect['id'] ?>"
                            hx-target="#modal-container"
                            hx-swap="innerHTML">
                        <i class="feather-edit-2 me-1"></i>Edit
                    </button>
                </div>
                <div class="card-body" id="prospect-info-card-body">
                    <div class="row" id="prospect-info-details">
                        <div class="col-md-6" id="prospect-info-col-left">
                            <table class="table table-borderless mb-0" id="prospect-info-table-left">
                                <tr id="prospect-info-id-row">
                                    <th class="text-muted" style="width: 150px;">ID</th>
                                    <td><?= htmlspecialchars($prospect['id']) ?></td>
                                </tr>
                                <tr id="prospect-info-name-row">
                                    <th class="text-muted">Name</th>
                                    <td><?= htmlspecialchars($prospect['name']) ?></td>
                                </tr>
                                <tr id="prospect-info-type-row">
                                    <th class="text-muted">Business Type</th>
                                    <td><?= htmlspecialchars($prospect['business_type'] ?: '-') ?></td>
                                </tr>
                                <tr id="prospect-info-stub-row">
                                    <th class="text-muted">Stub</th>
                                    <td><?= htmlspecialchars($prospect['stub'] ?: '-') ?></td>
                                </tr>
                                <tr id="prospect-info-desc-row">
                                    <th class="text-muted">Description</th>
                                    <td><?= htmlspecialchars($prospect['description'] ?: '-') ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6" id="prospect-info-col-right">
                            <table class="table table-borderless mb-0" id="prospect-info-table-right">
                                <tr id="prospect-info-members-row">
                                    <th class="text-muted" style="width: 180px;">Members</th>
                                    <td><?= htmlspecialchars($prospect['members']) ?></td>
                                </tr>
                                <tr id="prospect-info-spend-row">
                                    <th class="text-muted">Monthly Spend</th>
                                    <td>$<?= number_format($prospect['monthly_spend']) ?></td>
                                </tr>
                                <tr id="prospect-info-limit-row">
                                    <th class="text-muted">Monthly Spend Limit</th>
                                    <td>$<?= number_format($prospect['monthly_spend_limit']) ?></td>
                                </tr>
                                <tr id="prospect-info-agent-row">
                                    <th class="text-muted">Agent ID</th>
                                    <td><?= htmlspecialchars($prospect['agent_id'] ?: '-') ?></td>
                                </tr>
                                <tr id="prospect-info-created-row">
                                    <th class="text-muted">Created</th>
                                    <td><?= date('M j, Y g:i A', strtotime($prospect['created_at'])) ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Placeholder for future sections -->
    <div class="row mb-4" id="prospect-additional-row">
        <div class="col-12">
            <div class="card" id="prospect-additional-card">
                <div class="card-header" id="prospect-additional-card-header">
                    <h5 class="card-title mb-0">
                        <i class="feather-layers me-2"></i>Additional Details
                    </h5>
                </div>
                <div class="card-body" id="prospect-additional-card-body">
                    <div class="text-center py-4" id="prospect-additional-empty">
                        <p class="text-muted mb-0">Additional prospect details will be added here.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
