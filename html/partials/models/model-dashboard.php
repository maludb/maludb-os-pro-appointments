<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../config/database.php';

check_auth();
$user = get_user();

// Get model ID from request
$modelId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$modelId) {
    echo '<div class="alert alert-danger">Model ID is required.</div>';
    exit;
}

$db = Database::getInstance()->getConnection();

// Get model info
$stmt = $db->prepare("SELECT * FROM models WHERE id = ?");
$stmt->execute([$modelId]);
$model = $stmt->fetch();

if (!$model) {
    echo '<div class="alert alert-danger">Model not found.</div>';
    exit;
}

// Get model settings
$stmt = $db->prepare("SELECT * FROM model_settings WHERE model_id = ? ORDER BY name ASC");
$stmt->execute([$modelId]);
$settings = $stmt->fetchAll();

// Get model prompts
$stmt = $db->prepare("SELECT * FROM model_prompts WHERE model_id = ? ORDER BY name ASC");
$stmt->execute([$modelId]);
$prompts = $stmt->fetchAll();

// Get model output settings
$stmt = $db->prepare("SELECT * FROM model_output_settings WHERE model_id = ? ORDER BY format ASC");
$stmt->execute([$modelId]);
$outputSettings = $stmt->fetchAll();
?>

<!-- Model Dashboard Page Container -->
<div class="container-fluid" id="model-dashboard-page">
    <!-- Page Header -->
    <div class="row mb-4" id="model-dashboard-header">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center" id="model-dashboard-header-content">
                <div id="model-dashboard-header-title">
                    <nav aria-label="breadcrumb" id="model-breadcrumb">
                        <ol class="breadcrumb mb-1">
                            <li class="breadcrumb-item">
                                <a href="#" hx-get="/partials/models/index.php" hx-target="#page-content">Models</a>
                            </li>
                            <li class="breadcrumb-item active"><?= htmlspecialchars($model['name']) ?></li>
                        </ol>
                    </nav>
                    <h3 class="mb-0"><?= htmlspecialchars($model['name']) ?></h3>
                </div>
                <div id="model-dashboard-header-actions">
                    <a href="#"
                       class="btn btn-outline-secondary"
                       id="btn-back-to-models"
                       hx-get="/partials/models/index.php"
                       hx-target="#page-content">
                        <i class="feather-arrow-left me-2"></i>Back to Models
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Model Info Section -->
    <div class="row mb-4" id="model-info-row">
        <div class="col-12">
            <div class="card" id="model-info-card">
                <div class="card-header d-flex justify-content-between align-items-center" id="model-info-card-header">
                    <h5 class="card-title mb-0">
                        <i class="feather-info me-2"></i>Model Information
                    </h5>
                    <button class="btn btn-sm btn-outline-primary"
                            id="btn-edit-model"
                            hx-get="/partials/models/edit-model-form.php?id=<?= $model['id'] ?>"
                            hx-target="#modal-container"
                            hx-swap="innerHTML">
                        <i class="feather-edit-2 me-1"></i>Edit
                    </button>
                </div>
                <div class="card-body" id="model-info-card-body">
                    <div class="row" id="model-info-details">
                        <div class="col-md-6" id="model-info-col-left">
                            <table class="table table-borderless mb-0" id="model-info-table-left">
                                <tr id="model-info-id-row">
                                    <th class="text-muted" style="width: 150px;">ID</th>
                                    <td><?= htmlspecialchars($model['id']) ?></td>
                                </tr>
                                <tr id="model-info-name-row">
                                    <th class="text-muted">Name</th>
                                    <td><?= htmlspecialchars($model['name']) ?></td>
                                </tr>
                                <tr id="model-info-desc-row">
                                    <th class="text-muted">Description</th>
                                    <td><?= htmlspecialchars($model['description'] ?: '-') ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6" id="model-info-col-right">
                            <table class="table table-borderless mb-0" id="model-info-table-right">
                                <tr id="model-info-max-iterations-row">
                                    <th class="text-muted" style="width: 200px;">Max Iterations</th>
                                    <td><?= htmlspecialchars($model['max_iterations'] ?? '-') ?></td>
                                </tr>
                                <tr id="model-info-max-researchers-row">
                                    <th class="text-muted">Max Concurrent Researchers</th>
                                    <td><?= htmlspecialchars($model['max_concurrent_researchers'] ?? '-') ?></td>
                                </tr>
                                <tr id="model-info-quality-threshold-row">
                                    <th class="text-muted">Quality Threshold</th>
                                    <td><?= htmlspecialchars($model['quality_threshold'] ?? '-') ?></td>
                                </tr>
                                <tr id="model-info-created-row">
                                    <th class="text-muted">Created</th>
                                    <td><?= date('M j, Y g:i A', strtotime($model['created_at'])) ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Model Settings Section -->
    <div class="row mb-4" id="model-settings-row">
        <div class="col-12">
            <div class="card" id="model-settings-card">
                <div class="card-header d-flex justify-content-between align-items-center" id="model-settings-card-header">
                    <h5 class="card-title mb-0">
                        <i class="feather-settings me-2"></i>Model Settings
                    </h5>
                    <button class="btn btn-sm btn-primary"
                            id="btn-add-setting"
                            hx-get="/partials/models/add-setting-form.php?model_id=<?= $model['id'] ?>"
                            hx-target="#modal-container"
                            hx-swap="innerHTML">
                        <i class="feather-plus me-1"></i>Add Setting
                    </button>
                </div>
                <div class="card-body" id="model-settings-card-body">
                    <?php if (empty($settings)): ?>
                        <div class="text-center py-4" id="model-settings-empty">
                            <p class="text-muted mb-0">No settings configured for this model.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive" id="model-settings-table-container">
                            <table class="table table-hover" id="model-settings-table">
                                <thead id="model-settings-table-head">
                                    <tr>
                                        <th>Setting Name</th>
                                        <th>Value</th>
                                        <th>Updated</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="model-settings-table-body">
                                    <?php foreach ($settings as $setting): ?>
                                        <tr id="setting-row-<?= $setting['id'] ?>">
                                            <td id="setting-name-<?= $setting['id'] ?>">
                                                <code><?= htmlspecialchars($setting['name']) ?></code>
                                            </td>
                                            <td id="setting-value-<?= $setting['id'] ?>">
                                                <?php
                                                // Mask sensitive values (API keys)
                                                $value = $setting['value'];
                                                if (stripos($setting['name'], 'key') !== false ||
                                                    stripos($setting['name'], 'secret') !== false ||
                                                    stripos($setting['name'], 'token') !== false) {
                                                    $value = substr($value, 0, 8) . '...' . substr($value, -4);
                                                }
                                                ?>
                                                <span class="text-muted"><?= htmlspecialchars($value) ?></span>
                                            </td>
                                            <td id="setting-updated-<?= $setting['id'] ?>">
                                                <?= date('M j, Y', strtotime($setting['updated_at'])) ?>
                                            </td>
                                            <td class="text-end" id="setting-actions-<?= $setting['id'] ?>">
                                                <button class="btn btn-sm btn-outline-primary"
                                                        id="btn-edit-setting-<?= $setting['id'] ?>"
                                                        hx-get="/partials/models/edit-setting-form.php?id=<?= $setting['id'] ?>"
                                                        hx-target="#modal-container"
                                                        hx-swap="innerHTML">
                                                    <i class="feather-edit-2 me-1"></i>Edit
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
    </div>

    <!-- Model Prompts Section -->
    <div class="row mb-4" id="model-prompts-row">
        <div class="col-12">
            <div class="card" id="model-prompts-card">
                <div class="card-header d-flex justify-content-between align-items-center" id="model-prompts-card-header">
                    <h5 class="card-title mb-0">
                        <i class="feather-message-square me-2"></i>Model Prompts
                    </h5>
                    <span class="badge bg-secondary" id="model-prompts-count"><?= count($prompts) ?> prompts</span>
                </div>
                <div class="card-body" id="model-prompts-card-body">
                    <?php if (empty($prompts)): ?>
                        <div class="text-center py-4" id="model-prompts-empty">
                            <p class="text-muted mb-0">No prompts configured for this model.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive" id="model-prompts-table-container">
                            <table class="table table-hover" id="model-prompts-table">
                                <thead id="model-prompts-table-head">
                                    <tr>
                                        <th>Prompt Name</th>
                                        <th>Preview</th>
                                        <th>Updated</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="model-prompts-table-body">
                                    <?php foreach ($prompts as $prompt): ?>
                                        <tr id="prompt-row-<?= $prompt['id'] ?>">
                                            <td id="prompt-name-<?= $prompt['id'] ?>">
                                                <code><?= htmlspecialchars($prompt['name']) ?></code>
                                            </td>
                                            <td id="prompt-preview-<?= $prompt['id'] ?>">
                                                <?php
                                                $preview = $prompt['value'] ?: '';
                                                $truncated = strlen($preview) > 80 ? substr($preview, 0, 80) . '...' : $preview;
                                                ?>
                                                <span class="text-muted" title="<?= htmlspecialchars(substr($preview, 0, 500)) ?>"><?= htmlspecialchars($truncated) ?></span>
                                            </td>
                                            <td id="prompt-updated-<?= $prompt['id'] ?>">
                                                <?= date('M j, Y', strtotime($prompt['updated_at'])) ?>
                                            </td>
                                            <td class="text-end" id="prompt-actions-<?= $prompt['id'] ?>">
                                                <button class="btn btn-sm btn-outline-primary"
                                                        id="btn-edit-prompt-<?= $prompt['id'] ?>"
                                                        hx-get="/partials/models/edit-model-prompt.php?id=<?= $prompt['id'] ?>"
                                                        hx-target="#modal-container"
                                                        hx-swap="innerHTML">
                                                    <i class="feather-edit-2 me-1"></i>Edit
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
    </div>

    <!-- Model Output Settings Section -->
    <div class="row mb-4" id="model-output-settings-row">
        <div class="col-12">
            <div class="card" id="model-output-settings-card">
                <div class="card-header d-flex justify-content-between align-items-center" id="model-output-settings-card-header">
                    <h5 class="card-title mb-0">
                        <i class="feather-file-text me-2"></i>Output Settings
                    </h5>
                    <button class="btn btn-sm btn-primary"
                            id="btn-add-output-setting"
                            hx-get="/partials/models/add-output-setting-form.php?model_id=<?= $model['id'] ?>"
                            hx-target="#modal-container"
                            hx-swap="innerHTML">
                        <i class="feather-plus me-1"></i>Add Output Setting
                    </button>
                </div>
                <div class="card-body" id="model-output-settings-card-body">
                    <?php if (empty($outputSettings)): ?>
                        <div class="text-center py-4" id="model-output-settings-empty">
                            <p class="text-muted mb-0">No output settings configured for this model.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($outputSettings as $output): ?>
                            <div class="mb-4" id="output-setting-<?= $output['id'] ?>">
                                <div class="d-flex justify-content-between align-items-center mb-2" id="output-setting-header-<?= $output['id'] ?>">
                                    <h6 class="mb-0" id="output-setting-format-<?= $output['id'] ?>">
                                        <span class="badge bg-primary"><?= htmlspecialchars($output['format']) ?></span>
                                    </h6>
                                    <div id="output-setting-actions-<?= $output['id'] ?>">
                                        <small class="text-muted me-3" id="output-setting-updated-<?= $output['id'] ?>">
                                            Updated: <?= date('M j, Y', strtotime($output['updated_at'])) ?>
                                        </small>
                                        <button class="btn btn-sm btn-outline-primary"
                                                id="btn-edit-output-setting-<?= $output['id'] ?>"
                                                hx-get="/partials/models/edit-output-setting-form.php?id=<?= $output['id'] ?>"
                                                hx-target="#modal-container"
                                                hx-swap="innerHTML">
                                            <i class="feather-edit-2 me-1"></i>Edit
                                        </button>
                                    </div>
                                </div>
                                <div class="bg-light rounded p-3" id="output-setting-value-<?= $output['id'] ?>">
                                    <pre class="mb-0" style="white-space: pre-wrap; font-size: 0.85rem;"><code><?= htmlspecialchars($output['value']) ?></code></pre>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
