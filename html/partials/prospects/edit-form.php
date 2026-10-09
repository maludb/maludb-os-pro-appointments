<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../config/database.php';

check_auth();
$user = get_user();

// Get prospect ID from request
$prospectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$prospectId) {
    echo '<div class="alert alert-danger">Prospect ID is required.</div>';
    exit;
}

$db = Database::getInstance()->getConnection();

// Get prospect info
$stmt = $db->prepare("SELECT * FROM prospects WHERE id = ?");
$stmt->execute([$prospectId]);
$prospect = $stmt->fetch();

if (!$prospect) {
    echo '<div class="alert alert-danger">Prospect not found.</div>';
    exit;
}
?>

<!-- Edit Prospect Modal -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="editProspectModal" tabindex="-1" aria-labelledby="editProspectModalLabel">
    <div class="modal-dialog modal-lg" id="edit-prospect-modal-dialog">
        <div class="modal-content" id="edit-prospect-modal-content">
            <div class="modal-header" id="edit-prospect-modal-header">
                <h5 class="modal-title" id="editProspectModalLabel">
                    <i class="feather-edit me-2"></i>Edit Prospect
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="editProspectForm"
                  hx-post="/partials/prospects/save-prospect.php"
                  hx-target="#prospect-edit-result"
                  hx-indicator="#edit-prospect-loading">

                <input type="hidden" name="id" value="<?= $prospect['id'] ?>">

                <div class="modal-body" id="edit-prospect-modal-body">
                    <!-- Result Messages -->
                    <div id="prospect-edit-result"></div>

                    <!-- Name (Required) -->
                    <div class="mb-3" id="edit-prospect-name-group">
                        <label for="edit-prospect-name" class="form-label">
                            Name <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control"
                               id="edit-prospect-name"
                               name="name"
                               required
                               maxlength="100"
                               value="<?= htmlspecialchars($prospect['name']) ?>"
                               placeholder="Enter prospect name">
                    </div>

                    <!-- Business Type -->
                    <div class="mb-3" id="edit-prospect-type-group">
                        <label for="edit-prospect-type" class="form-label">Business Type</label>
                        <input type="text"
                               class="form-control"
                               id="edit-prospect-type"
                               name="business_type"
                               maxlength="100"
                               value="<?= htmlspecialchars($prospect['business_type'] ?? '') ?>"
                               placeholder="e.g., Technology, Healthcare, Retail">
                    </div>

                    <!-- Stub -->
                    <div class="mb-3" id="edit-prospect-stub-group">
                        <label for="edit-prospect-stub" class="form-label">Stub</label>
                        <input type="text"
                               class="form-control"
                               id="edit-prospect-stub"
                               name="stub"
                               maxlength="80"
                               value="<?= htmlspecialchars($prospect['stub'] ?? '') ?>"
                               placeholder="Short identifier (e.g., acme-corp)">
                        <small class="text-muted">A short, URL-friendly identifier for the prospect</small>
                    </div>

                    <!-- Description -->
                    <div class="mb-3" id="edit-prospect-description-group">
                        <label for="edit-prospect-description" class="form-label">Description</label>
                        <textarea class="form-control"
                                  id="edit-prospect-description"
                                  name="description"
                                  rows="3"
                                  placeholder="Enter prospect description"><?= htmlspecialchars($prospect['description'] ?? '') ?></textarea>
                    </div>

                    <div class="row" id="edit-prospect-settings-row">
                        <!-- Members -->
                        <div class="col-md-4 mb-3" id="edit-prospect-members-group">
                            <label for="edit-prospect-members" class="form-label">Members</label>
                            <input type="number"
                                   class="form-control"
                                   id="edit-prospect-members"
                                   name="members"
                                   min="0"
                                   value="<?= htmlspecialchars($prospect['members']) ?>"
                                   placeholder="Number of members">
                        </div>

                        <!-- Monthly Spend Limit -->
                        <div class="col-md-4 mb-3" id="edit-prospect-spend-limit-group">
                            <label for="edit-prospect-spend-limit" class="form-label">Monthly Spend Limit ($)</label>
                            <input type="number"
                                   class="form-control"
                                   id="edit-prospect-spend-limit"
                                   name="monthly_spend_limit"
                                   min="0"
                                   value="<?= htmlspecialchars($prospect['monthly_spend_limit']) ?>"
                                   placeholder="e.g., 1000">
                        </div>

                        <!-- Agent ID -->
                        <div class="col-md-4 mb-3" id="edit-prospect-agent-group">
                            <label for="edit-prospect-agent" class="form-label">Agent ID</label>
                            <input type="text"
                                   class="form-control"
                                   id="edit-prospect-agent"
                                   name="agent_id"
                                   maxlength="30"
                                   value="<?= htmlspecialchars($prospect['agent_id'] ?? '') ?>"
                                   placeholder="Optional agent ID">
                        </div>
                    </div>
                </div>

                <div class="modal-footer" id="edit-prospect-modal-footer">
                    <button type="button"
                            class="btn btn-secondary"
                            id="btn-cancel-edit-prospect"
                            data-bs-dismiss="modal">
                        <i class="feather-x me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="btn-submit-edit-prospect">
                        <span class="d-none" id="edit-prospect-loading">
                            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        </span>
                        <i class="feather-save me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
