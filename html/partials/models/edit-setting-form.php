<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../config/database.php';

check_auth();
$user = get_user();

$settingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$settingId) {
    http_response_code(400);
    echo '<div class="alert alert-danger">Setting ID is required</div>';
    exit;
}

$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT * FROM model_settings WHERE id = ?");
$stmt->execute([$settingId]);
$setting = $stmt->fetch();

if (!$setting) {
    http_response_code(404);
    echo '<div class="alert alert-danger">Setting not found</div>';
    exit;
}
?>

<!-- Edit Setting Modal -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="editSettingModal" tabindex="-1" aria-labelledby="editSettingModalLabel">
    <div class="modal-dialog" id="edit-setting-modal-dialog">
        <div class="modal-content" id="edit-setting-modal-content">
            <div class="modal-header" id="edit-setting-modal-header">
                <h5 class="modal-title" id="editSettingModalLabel">
                    <i class="feather-edit-2 me-2"></i>Edit Setting
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="editSettingForm"
                  hx-post="/partials/models/save-setting.php"
                  hx-target="#setting-edit-result"
                  hx-indicator="#edit-setting-loading">

                <input type="hidden" name="id" value="<?= $setting['id'] ?>">
                <input type="hidden" name="model_id" value="<?= $setting['model_id'] ?>">

                <div class="modal-body" id="edit-setting-modal-body">
                    <!-- Result Messages -->
                    <div id="setting-edit-result"></div>

                    <!-- Setting Name (Required) -->
                    <div class="mb-3" id="edit-setting-name-group">
                        <label for="edit-setting-name" class="form-label">
                            Setting Name <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control"
                               id="edit-setting-name"
                               name="name"
                               required
                               maxlength="100"
                               value="<?= htmlspecialchars($setting['name']) ?>">
                    </div>

                    <!-- Value -->
                    <div class="mb-3" id="edit-setting-value-group">
                        <label for="edit-setting-value" class="form-label">Value</label>
                        <textarea class="form-control"
                                  id="edit-setting-value"
                                  name="value"
                                  rows="3"><?= htmlspecialchars($setting['value'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="modal-footer" id="edit-setting-modal-footer">
                    <button type="button"
                            class="btn btn-secondary"
                            id="btn-cancel-edit-setting"
                            data-bs-dismiss="modal">
                        <i class="feather-x me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="btn-submit-edit-setting">
                        <span class="d-none" id="edit-setting-loading">
                            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        </span>
                        <i class="feather-check me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
