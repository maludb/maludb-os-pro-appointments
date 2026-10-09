<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../config/database.php';

check_auth();
$user = get_user();

// Get all prospects
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT id, name, business_type, description, members, monthly_spend_limit, monthly_spend, created_at FROM prospects ORDER BY name ASC");
$stmt->execute();
$prospects = $stmt->fetchAll();
?>

<!-- Prospects List Page Container -->
<div class="container-fluid pt-4 ps-4" id="prospects-list-page">
    <!-- Page Header -->
    <div class="row mb-4" id="prospects-list-header">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center" id="prospects-header-content">
                <div id="prospects-header-title">
                    <h3 class="mb-1">Prospects</h3>
                    <p class="text-muted mb-0">Manage your prospects and their configurations</p>
                </div>
                <div id="prospects-header-actions">
                    <button class="btn btn-primary"
                            id="btn-add-prospect"
                            hx-get="/partials/prospects/add-form.php"
                            hx-target="#modal-container"
                            hx-swap="innerHTML">
                        <i class="feather-plus me-2"></i>Add Prospect
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Prospects Table -->
    <div class="row" id="prospects-table-row">
        <div class="col-12">
            <div class="card" id="prospects-table-card">
                <div class="card-body" id="prospects-table-card-body">
                    <?php if (empty($prospects)): ?>
                        <div class="text-center py-5" id="prospects-empty-state">
                            <i class="feather-target text-muted" style="font-size: 48px;"></i>
                            <h5 class="mt-3 text-muted">No Prospects Found</h5>
                            <p class="text-muted">Get started by adding your first prospect.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive" id="prospects-table-container">
                            <table class="table table-hover" id="prospects-table">
                                <thead id="prospects-table-head">
                                    <tr>
                                        <th>Name</th>
                                        <th>Business Type</th>
                                        <th>Members</th>
                                        <th>Monthly Spend</th>
                                        <th>Spend Limit</th>
                                        <th>Created</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="prospects-table-body">
                                    <?php foreach ($prospects as $prospect): ?>
                                        <tr id="prospect-row-<?= $prospect['id'] ?>">
                                            <td id="prospect-name-<?= $prospect['id'] ?>">
                                                <span class="fw-medium"><?= htmlspecialchars($prospect['name']) ?></span>
                                            </td>
                                            <td id="prospect-type-<?= $prospect['id'] ?>">
                                                <?= htmlspecialchars($prospect['business_type'] ?: '-') ?>
                                            </td>
                                            <td id="prospect-members-<?= $prospect['id'] ?>">
                                                <?= htmlspecialchars($prospect['members']) ?>
                                            </td>
                                            <td id="prospect-spend-<?= $prospect['id'] ?>">
                                                $<?= number_format($prospect['monthly_spend']) ?>
                                            </td>
                                            <td id="prospect-limit-<?= $prospect['id'] ?>">
                                                $<?= number_format($prospect['monthly_spend_limit']) ?>
                                            </td>
                                            <td id="prospect-created-<?= $prospect['id'] ?>">
                                                <?= date('M j, Y', strtotime($prospect['created_at'])) ?>
                                            </td>
                                            <td class="text-end" id="prospect-actions-<?= $prospect['id'] ?>">
                                                <a href="#"
                                                   class="btn btn-sm btn-outline-primary"
                                                   id="btn-edit-prospect-<?= $prospect['id'] ?>"
                                                   hx-get="/partials/prospects/prospect-dashboard.php?id=<?= $prospect['id'] ?>"
                                                   hx-target="#page-content">
                                                    <i class="feather-edit-2 me-1"></i>Edit
                                                </a>
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
    </div>
</div>
