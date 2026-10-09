<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../config/database.php';

check_auth();
$user = get_user();

$promptId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$promptId) {
    http_response_code(400);
    echo '<div class="alert alert-danger">Prompt ID is required</div>';
    exit;
}

$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT * FROM model_prompts WHERE id = ?");
$stmt->execute([$promptId]);
$prompt = $stmt->fetch();

if (!$prompt) {
    http_response_code(404);
    echo '<div class="alert alert-danger">Prompt not found</div>';
    exit;
}
?>

<!-- Edit Model Prompt Modal -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="editModelPromptModal" tabindex="-1" aria-labelledby="editModelPromptModalLabel">
    <div class="modal-dialog modal-xl" id="edit-model-prompt-modal-dialog">
        <div class="modal-content" id="edit-model-prompt-modal-content">
            <div class="modal-header" id="edit-model-prompt-modal-header">
                <h5 class="modal-title" id="editModelPromptModalLabel">
                    <i class="feather-edit-2 me-2"></i>Edit Prompt
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="editModelPromptForm"
                  hx-post="/partials/models/save-model-prompt.php"
                  hx-target="#model-prompt-edit-result"
                  hx-indicator="#edit-model-prompt-loading">

                <input type="hidden" name="id" value="<?= $prompt['id'] ?>">
                <input type="hidden" name="model_id" value="<?= $prompt['model_id'] ?>">

                <div class="modal-body" id="edit-model-prompt-modal-body">
                    <!-- Result Messages -->
                    <div id="model-prompt-edit-result"></div>

                    <!-- Prompt Name (Required) -->
                    <div class="mb-3" id="edit-model-prompt-name-group">
                        <label for="edit-model-prompt-name" class="form-label">
                            Prompt Name <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control"
                               id="edit-model-prompt-name"
                               name="name"
                               required
                               maxlength="200"
                               value="<?= htmlspecialchars($prompt['name']) ?>">
                        <small class="text-muted">A unique identifier for this prompt (e.g., lead_researcher_prompt)</small>
                    </div>

                    <!-- Prompt Value -->
                    <div class="mb-3" id="edit-model-prompt-value-group">
                        <label for="edit-model-prompt-value" class="form-label">Prompt Content</label>
                        <textarea class="form-control font-monospace"
                                  id="edit-model-prompt-value"
                                  name="value"
                                  rows="20"
                                  style="font-size: 0.85rem;"><?= htmlspecialchars($prompt['value'] ?? '') ?></textarea>
                        <small class="text-muted">Use placeholders like {variable_name} for dynamic content</small>
                    </div>
                </div>

                <div class="modal-footer" id="edit-model-prompt-modal-footer">
                    <button type="button"
                            class="btn btn-secondary"
                            id="btn-cancel-edit-model-prompt"
                            data-bs-dismiss="modal">
                        <i class="feather-x me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="btn-submit-edit-model-prompt">
                        <span class="d-none" id="edit-model-prompt-loading">
                            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        </span>
                        <i class="feather-check me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
