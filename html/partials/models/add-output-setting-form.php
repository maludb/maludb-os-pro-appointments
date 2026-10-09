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

<!-- Add Output Setting Modal -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="addOutputSettingModal" tabindex="-1" aria-labelledby="addOutputSettingModalLabel">
    <div class="modal-dialog modal-lg" id="add-output-setting-modal-dialog">
        <div class="modal-content" id="add-output-setting-modal-content">
            <div class="modal-header" id="add-output-setting-modal-header">
                <h5 class="modal-title" id="addOutputSettingModalLabel">
                    <i class="feather-plus me-2"></i>Add Output Setting
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="addOutputSettingForm"
                  hx-post="/partials/models/save-output-setting.php"
                  hx-target="#output-setting-add-result"
                  hx-indicator="#add-output-setting-loading">

                <input type="hidden" name="model_id" value="<?= $modelId ?>">

                <div class="modal-body" id="add-output-setting-modal-body">
                    <!-- Result Messages -->
                    <div id="output-setting-add-result"></div>

                    <p class="text-muted mb-3" id="add-output-setting-model-info">
                        Adding output setting to: <strong><?= htmlspecialchars($model['name']) ?></strong>
                    </p>

                    <!-- Format (Required) -->
                    <div class="mb-3" id="add-output-setting-format-group">
                        <label for="add-output-setting-format" class="form-label">
                            Format <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control"
                               id="add-output-setting-format"
                               name="format"
                               required
                               maxlength="10"
                               placeholder="e.g., JSON, XML, TEXT">
                    </div>

                    <!-- Value -->
                    <div class="mb-3" id="add-output-setting-value-group">
                        <label for="add-output-setting-value" class="form-label">Value</label>
                        <textarea class="form-control font-monospace"
                                  id="add-output-setting-value"
                                  name="value"
                                  rows="12"
                                  placeholder="Enter the output format template or schema"></textarea>
                    </div>
                </div>

                <div class="modal-footer" id="add-output-setting-modal-footer">
                    <button type="button"
                            class="btn btn-secondary"
                            id="btn-cancel-add-output-setting"
                            data-bs-dismiss="modal">
                        <i class="feather-x me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="btn-submit-add-output-setting">
                        <span class="d-none" id="add-output-setting-loading">
                            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        </span>
                        <i class="feather-plus me-1"></i>Add Output Setting
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
