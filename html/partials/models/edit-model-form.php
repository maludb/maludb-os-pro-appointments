<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../config/database.php';

check_auth();
$user = get_user();

$modelId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$modelId) {
    http_response_code(400);
    echo '<div class="alert alert-danger">Model ID is required</div>';
    exit;
}

$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT * FROM models WHERE id = ?");
$stmt->execute([$modelId]);
$model = $stmt->fetch();

if (!$model) {
    http_response_code(404);
    echo '<div class="alert alert-danger">Model not found</div>';
    exit;
}
?>

<!-- Edit Model Modal -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="editModelModal" tabindex="-1" aria-labelledby="editModelModalLabel">
    <div class="modal-dialog" id="edit-model-modal-dialog">
        <div class="modal-content" id="edit-model-modal-content">
            <div class="modal-header" id="edit-model-modal-header">
                <h5 class="modal-title" id="editModelModalLabel">
                    <i class="feather-edit-2 me-2"></i>Edit Model
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="editModelForm"
                  hx-post="/partials/models/save-model.php"
                  hx-target="#model-edit-result"
                  hx-indicator="#edit-model-loading">

                <input type="hidden" name="id" value="<?= $model['id'] ?>">

                <div class="modal-body" id="edit-model-modal-body">
                    <!-- Result Messages -->
                    <div id="model-edit-result"></div>

                    <!-- Name (Required) -->
                    <div class="mb-3" id="edit-model-name-group">
                        <label for="edit-model-name" class="form-label">
                            Name <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control"
                               id="edit-model-name"
                               name="name"
                               required
                               maxlength="100"
                               value="<?= htmlspecialchars($model['name']) ?>">
                    </div>

                    <!-- Description -->
                    <div class="mb-3" id="edit-model-description-group">
                        <label for="edit-model-description" class="form-label">Description</label>
                        <textarea class="form-control"
                                  id="edit-model-description"
                                  name="description"
                                  rows="3"><?= htmlspecialchars($model['description'] ?? '') ?></textarea>
                    </div>

                    <div class="row" id="edit-model-settings-row">
                        <!-- Max Iterations -->
                        <div class="col-md-4 mb-3" id="edit-model-max-iterations-group">
                            <label for="edit-model-max-iterations" class="form-label">Max Iterations</label>
                            <input type="number"
                                   class="form-control"
                                   id="edit-model-max-iterations"
                                   name="max_iterations"
                                   min="1"
                                   value="<?= htmlspecialchars($model['max_iterations'] ?? '') ?>">
                        </div>

                        <!-- Max Concurrent Researchers -->
                        <div class="col-md-4 mb-3" id="edit-model-max-researchers-group">
                            <label for="edit-model-max-researchers" class="form-label">Max Researchers</label>
                            <input type="number"
                                   class="form-control"
                                   id="edit-model-max-researchers"
                                   name="max_concurrent_researchers"
                                   min="1"
                                   value="<?= htmlspecialchars($model['max_concurrent_researchers'] ?? '') ?>">
                        </div>

                        <!-- Quality Threshold -->
                        <div class="col-md-4 mb-3" id="edit-model-quality-threshold-group">
                            <label for="edit-model-quality-threshold" class="form-label">Quality Threshold</label>
                            <input type="number"
                                   class="form-control"
                                   id="edit-model-quality-threshold"
                                   name="quality_threshold"
                                   min="0"
                                   max="100"
                                   step="0.01"
                                   value="<?= htmlspecialchars($model['quality_threshold'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="modal-footer" id="edit-model-modal-footer">
                    <button type="button"
                            class="btn btn-secondary"
                            id="btn-cancel-edit-model"
                            data-bs-dismiss="modal">
                        <i class="feather-x me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="btn-submit-edit-model">
                        <span class="d-none" id="edit-model-loading">
                            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        </span>
                        <i class="feather-check me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
