<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../config/database.php';

check_auth();
$user = get_user();

$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$projectId) {
    http_response_code(400);
    echo '<div class="alert alert-danger">Project ID is required</div>';
    exit;
}

$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
$stmt->execute([$projectId, $user['id']]);
$project = $stmt->fetch();

if (!$project) {
    http_response_code(404);
    echo '<div class="alert alert-danger">Project not found</div>';
    exit;
}
?>

<!-- Edit Project Modal -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="editProjectModal" tabindex="-1" aria-labelledby="editProjectModalLabel">
    <div class="modal-dialog modal-lg" id="edit-project-modal-dialog">
        <div class="modal-content" id="edit-project-modal-content">
            <div class="modal-header" id="edit-project-modal-header">
                <h5 class="modal-title" id="editProjectModalLabel">
                    <i class="feather-edit-2 me-2"></i>Edit Project
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="editProjectForm"
                  hx-post="/partials/projects/save-project.php"
                  hx-target="#project-edit-result"
                  hx-indicator="#edit-project-loading">

                <input type="hidden" name="id" value="<?= $project['id'] ?>">

                <div class="modal-body" id="edit-project-modal-body">
                    <!-- Result Messages -->
                    <div id="project-edit-result"></div>

                    <!-- Name (Required) -->
                    <div class="mb-3" id="edit-project-name-group">
                        <label for="edit-project-name" class="form-label">
                            Name <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control"
                               id="edit-project-name"
                               name="name"
                               required
                               maxlength="100"
                               value="<?= htmlspecialchars($project['name']) ?>">
                    </div>

                    <!-- Stub -->
                    <div class="mb-3" id="edit-project-stub-group">
                        <label for="edit-project-stub" class="form-label">Stub</label>
                        <input type="text"
                               class="form-control"
                               id="edit-project-stub"
                               name="stub"
                               maxlength="80"
                               value="<?= htmlspecialchars($project['stub'] ?? '') ?>">
                        <small class="text-muted">A short, URL-friendly identifier for the project</small>
                    </div>

                    <!-- Description -->
                    <div class="mb-3" id="edit-project-description-group">
                        <label for="edit-project-description" class="form-label">Description</label>
                        <textarea class="form-control"
                                  id="edit-project-description"
                                  name="description"
                                  rows="3"><?= htmlspecialchars($project['description'] ?? '') ?></textarea>
                    </div>

                    <div class="row" id="edit-project-settings-row">
                        <!-- Geography -->
                        <div class="col-md-4 mb-3" id="edit-project-geography-group">
                            <label for="edit-project-geography" class="form-label">Geography</label>
                            <select class="form-select"
                                    id="edit-project-geography"
                                    name="geography">
                                <option value="">Select region</option>
                                <option value="1" <?= $project['geography'] == 1 ? 'selected' : '' ?>>US East</option>
                                <option value="2" <?= $project['geography'] == 2 ? 'selected' : '' ?>>US West</option>
                                <option value="3" <?= $project['geography'] == 3 ? 'selected' : '' ?>>Europe</option>
                                <option value="4" <?= $project['geography'] == 4 ? 'selected' : '' ?>>Asia Pacific</option>
                                <option value="5" <?= $project['geography'] == 5 ? 'selected' : '' ?>>Global</option>
                            </select>
                        </div>

                        <!-- Members -->
                        <div class="col-md-4 mb-3" id="edit-project-members-group">
                            <label for="edit-project-members" class="form-label">Members</label>
                            <input type="number"
                                   class="form-control"
                                   id="edit-project-members"
                                   name="members"
                                   min="0"
                                   value="<?= htmlspecialchars($project['members'] ?? '1') ?>">
                        </div>

                        <!-- Monthly Spend Limit -->
                        <div class="col-md-4 mb-3" id="edit-project-spend-limit-group">
                            <label for="edit-project-spend-limit" class="form-label">Monthly Spend Limit ($)</label>
                            <input type="number"
                                   class="form-control"
                                   id="edit-project-spend-limit"
                                   name="monthly_spend_limit"
                                   min="0"
                                   value="<?= htmlspecialchars($project['monthly_spend_limit'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="modal-footer" id="edit-project-modal-footer">
                    <button type="button"
                            class="btn btn-secondary"
                            id="btn-cancel-edit-project"
                            data-bs-dismiss="modal">
                        <i class="feather-x me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="btn-submit-edit-project">
                        <span class="d-none" id="edit-project-loading">
                            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        </span>
                        <i class="feather-check me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
