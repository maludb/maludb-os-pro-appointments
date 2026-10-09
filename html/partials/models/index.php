<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../config/database.php';

check_auth();
$user = get_user();

// Get all models
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT id, name, description, created_at FROM models ORDER BY name ASC");
$stmt->execute();
$models = $stmt->fetchAll();
?>

<!-- Models List Page Container -->
<div class="container-fluid" id="models-list-page">
    <!-- Page Header -->
    <div class="row mb-4" id="models-list-header">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center" id="models-header-content">
                <div id="models-header-title">
                    <h3 class="mb-1">Models</h3>
                    <p class="text-muted mb-0">Manage your AI models and configurations</p>
                </div>
                <div id="models-header-actions">
                    <button class="btn btn-primary"
                            id="btn-add-model"
                            hx-get="/partials/models/add-form.php"
                            hx-target="#modal-container"
                            hx-swap="innerHTML">
                        <i class="feather-plus me-2"></i>Add Model
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Models Table -->
    <div class="row" id="models-table-row">
        <div class="col-12">
            <div class="card" id="models-table-card">
                <div class="card-body" id="models-table-card-body">
                    <?php if (empty($models)): ?>
                        <div class="text-center py-5" id="models-empty-state">
                            <i class="feather-cpu text-muted" style="font-size: 48px;"></i>
                            <h5 class="mt-3 text-muted">No Models Found</h5>
                            <p class="text-muted">Get started by adding your first model.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive" id="models-table-container">
                            <table class="table table-hover" id="models-table">
                                <thead id="models-table-head">
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Created</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="models-table-body">
                                    <?php foreach ($models as $model): ?>
                                        <tr id="model-row-<?= $model['id'] ?>">
                                            <td id="model-id-<?= $model['id'] ?>"><?= htmlspecialchars($model['id']) ?></td>
                                            <td id="model-name-<?= $model['id'] ?>">
                                                <span class="fw-medium"><?= htmlspecialchars($model['name']) ?></span>
                                            </td>
                                            <td id="model-desc-<?= $model['id'] ?>">
                                                <?= htmlspecialchars($model['description'] ?: '-') ?>
                                            </td>
                                            <td id="model-created-<?= $model['id'] ?>">
                                                <?= date('M j, Y', strtotime($model['created_at'])) ?>
                                            </td>
                                            <td class="text-end" id="model-actions-<?= $model['id'] ?>">
                                                <a href="#"
                                                   class="btn btn-sm btn-outline-primary"
                                                   id="btn-edit-model-<?= $model['id'] ?>"
                                                   hx-get="/partials/models/model-dashboard.php?id=<?= $model['id'] ?>"
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
