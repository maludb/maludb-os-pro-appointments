<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../config/database.php';

check_auth();
$user = get_user();

$modelId = isset($_GET['model_id']) ? (int)$_GET['model_id'] : 0;

if (!$modelId) {
    http_response_code(400);
    echo '<div class="alert alert-danger">Model ID is required</div>';
    exit;
}

// Verify model exists
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT id, name FROM models WHERE id = ?");
$stmt->execute([$modelId]);
$model = $stmt->fetch();

if (!$model) {
    http_response_code(404);
    echo '<div class="alert alert-danger">Model not found</div>';
    exit;
}
?>

<!-- Add Setting Modal -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="addSettingModal" tabindex="-1" aria-labelledby="addSettingModalLabel">
    <div class="modal-dialog" id="add-setting-modal-dialog">
        <div class="modal-content" id="add-setting-modal-content">
            <div class="modal-header" id="add-setting-modal-header">
                <h5 class="modal-title" id="addSettingModalLabel">
                    <i class="feather-plus me-2"></i>Add Setting
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="addSettingForm"
                  hx-post="/partials/models/save-setting.php"
                  hx-target="#setting-add-result"
                  hx-indicator="#add-setting-loading">

                <input type="hidden" name="model_id" value="<?= $modelId ?>">

                <div class="modal-body" id="add-setting-modal-body">
                    <!-- Result Messages -->
                    <div id="setting-add-result"></div>

                    <p class="text-muted mb-3" id="add-setting-model-info">
                        Adding setting to: <strong><?= htmlspecialchars($model['name']) ?></strong>
                    </p>

                    <!-- Setting Name (Required) -->
                    <div class="mb-3" id="add-setting-name-group">
                        <label for="add-setting-name" class="form-label">
                            Setting Name <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control"
                               id="add-setting-name"
                               name="name"
                               required
                               maxlength="100"
                               placeholder="e.g., api_key, max_tokens">
                    </div>

                    <!-- Value -->
                    <div class="mb-3" id="add-setting-value-group">
                        <label for="add-setting-value" class="form-label">Value</label>
                        <textarea class="form-control"
                                  id="add-setting-value"
                                  name="value"
                                  rows="3"
                                  placeholder="Enter the setting value"></textarea>
                    </div>
                </div>

                <div class="modal-footer" id="add-setting-modal-footer">
                    <button type="button"
                            class="btn btn-secondary"
                            id="btn-cancel-add-setting"
                            data-bs-dismiss="modal">
                        <i class="feather-x me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="btn-submit-add-setting">
                        <span class="d-none" id="add-setting-loading">
                            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        </span>
                        <i class="feather-plus me-1"></i>Add Setting
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
