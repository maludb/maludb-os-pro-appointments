<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../config/database.php';

check_auth();
$user = get_user();

$outputSettingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$outputSettingId) {
    http_response_code(400);
    echo '<div class="alert alert-danger">Output Setting ID is required</div>';
    exit;
}

$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT * FROM model_output_settings WHERE id = ?");
$stmt->execute([$outputSettingId]);
$outputSetting = $stmt->fetch();

if (!$outputSetting) {
    http_response_code(404);
    echo '<div class="alert alert-danger">Output Setting not found</div>';
    exit;
}
?>

<!-- Edit Output Setting Modal -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="editOutputSettingModal" tabindex="-1" aria-labelledby="editOutputSettingModalLabel">
    <div class="modal-dialog modal-lg" id="edit-output-setting-modal-dialog">
        <div class="modal-content" id="edit-output-setting-modal-content">
            <div class="modal-header" id="edit-output-setting-modal-header">
                <h5 class="modal-title" id="editOutputSettingModalLabel">
                    <i class="feather-edit-2 me-2"></i>Edit Output Setting
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="editOutputSettingForm"
                  hx-post="/partials/models/save-output-setting.php"
                  hx-target="#output-setting-edit-result"
                  hx-indicator="#edit-output-setting-loading">

                <input type="hidden" name="id" value="<?= $outputSetting['id'] ?>">
                <input type="hidden" name="model_id" value="<?= $outputSetting['model_id'] ?>">

                <div class="modal-body" id="edit-output-setting-modal-body">
                    <!-- Result Messages -->
                    <div id="output-setting-edit-result"></div>

                    <!-- Format (Required) -->
                    <div class="mb-3" id="edit-output-setting-format-group">
                        <label for="edit-output-setting-format" class="form-label">
                            Format <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control"
                               id="edit-output-setting-format"
                               name="format"
                               required
                               maxlength="10"
                               value="<?= htmlspecialchars($outputSetting['format']) ?>">
                    </div>

                    <!-- Value -->
                    <div class="mb-3" id="edit-output-setting-value-group">
                        <label for="edit-output-setting-value" class="form-label">Value</label>
                        <textarea class="form-control font-monospace"
                                  id="edit-output-setting-value"
                                  name="value"
                                  rows="12"><?= htmlspecialchars($outputSetting['value'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="modal-footer" id="edit-output-setting-modal-footer">
                    <button type="button"
                            class="btn btn-secondary"
                            id="btn-cancel-edit-output-setting"
                            data-bs-dismiss="modal">
                        <i class="feather-x me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="btn-submit-edit-output-setting">
                        <span class="d-none" id="edit-output-setting-loading">
                            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        </span>
                        <i class="feather-check me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
