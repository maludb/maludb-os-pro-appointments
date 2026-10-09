<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../config/database.php';

check_auth();
$user = get_user();

// Get agent ID from request
$agentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$agentId) {
    echo '<div class="alert alert-danger">Agent ID is required.</div>';
    exit;
}

$db = Database::getInstance()->getConnection();

// Get agent info
$stmt = $db->prepare("SELECT * FROM prospect_agents WHERE id = ?");
$stmt->execute([$agentId]);
$agent = $stmt->fetch();

if (!$agent) {
    echo '<div class="alert alert-danger">Agent not found.</div>';
    exit;
}

// Get all prospects for dropdown
$stmt = $db->prepare("SELECT id, name FROM prospects ORDER BY name ASC");
$stmt->execute();
$prospects = $stmt->fetchAll();
?>

<!-- Edit Agent Modal -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="editAgentModal" tabindex="-1" aria-labelledby="editAgentModalLabel">
    <div class="modal-dialog modal-lg" id="edit-agent-modal-dialog">
        <div class="modal-content" id="edit-agent-modal-content">
            <div class="modal-header" id="edit-agent-modal-header">
                <h5 class="modal-title" id="editAgentModalLabel">
                    <i class="feather-edit me-2"></i>Edit Agent
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="editAgentForm"
                  hx-post="/partials/agents/save-agent.php"
                  hx-target="#agent-edit-result"
                  hx-indicator="#edit-agent-loading">

                <input type="hidden" name="id" value="<?= $agent['id'] ?>">

                <div class="modal-body" id="edit-agent-modal-body">
                    <!-- Result Messages -->
                    <div id="agent-edit-result"></div>

                    <!-- Agent Name -->
                    <div class="mb-3" id="edit-agent-name-group">
                        <label for="edit-agent-name" class="form-label">
                            Agent Name <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control"
                               id="edit-agent-name"
                               name="agent_name"
                               required
                               maxlength="80"
                               value="<?= htmlspecialchars($agent['agent_name'] ?? '') ?>"
                               placeholder="Enter agent name">
                    </div>

                    <div class="row" id="edit-agent-row-1">
                        <!-- Prospect -->
                        <div class="col-md-6 mb-3" id="edit-agent-prospect-group">
                            <label for="edit-agent-prospect" class="form-label">Prospect</label>
                            <select class="form-select"
                                    id="edit-agent-prospect"
                                    name="prospect_id">
                                <option value="">Select a prospect</option>
                                <?php foreach ($prospects as $prospect): ?>
                                    <option value="<?= $prospect['id'] ?>" <?= $agent['prospect_id'] == $prospect['id'] ? 'selected' : '' ?>><?= htmlspecialchars($prospect['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Role -->
                        <div class="col-md-6 mb-3" id="edit-agent-role-group">
                            <label for="edit-agent-role" class="form-label">
                                Role <span class="text-danger">*</span>
                            </label>
                            <select class="form-select"
                                    id="edit-agent-role"
                                    name="agent_role"
                                    required>
                                <option value="">Select a role</option>
                                <option value="buyer" <?= $agent['agent_role'] === 'buyer' ? 'selected' : '' ?>>Buyer</option>
                                <option value="decision_maker" <?= $agent['agent_role'] === 'decision_maker' ? 'selected' : '' ?>>Decision Maker</option>
                                <option value="influencer" <?= $agent['agent_role'] === 'influencer' ? 'selected' : '' ?>>Influencer</option>
                                <option value="gatekeeper" <?= $agent['agent_role'] === 'gatekeeper' ? 'selected' : '' ?>>Gatekeeper</option>
                                <option value="end_user" <?= $agent['agent_role'] === 'end_user' ? 'selected' : '' ?>>End User</option>
                            </select>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="mb-3" id="edit-agent-description-group">
                        <label for="edit-agent-description" class="form-label">Description</label>
                        <input type="text"
                               class="form-control"
                               id="edit-agent-description"
                               name="agent_description"
                               maxlength="255"
                               value="<?= htmlspecialchars($agent['agent_description'] ?? '') ?>"
                               placeholder="Brief description of the agent">
                    </div>

                    <div class="row" id="edit-agent-row-2">
                        <!-- Temperament -->
                        <div class="col-md-4 mb-3" id="edit-agent-temperament-group">
                            <label for="edit-agent-temperament" class="form-label">
                                Temperament <span class="text-danger">*</span>
                            </label>
                            <select class="form-select"
                                    id="edit-agent-temperament"
                                    name="default_temprement"
                                    required>
                                <option value="">Select temperament</option>
                                <option value="friendly" <?= $agent['default_temprement'] === 'friendly' ? 'selected' : '' ?>>Friendly</option>
                                <option value="neutral" <?= $agent['default_temprement'] === 'neutral' ? 'selected' : '' ?>>Neutral</option>
                                <option value="skeptical" <?= $agent['default_temprement'] === 'skeptical' ? 'selected' : '' ?>>Skeptical</option>
                                <option value="hostile" <?= $agent['default_temprement'] === 'hostile' ? 'selected' : '' ?>>Hostile</option>
                                <option value="enthusiastic" <?= $agent['default_temprement'] === 'enthusiastic' ? 'selected' : '' ?>>Enthusiastic</option>
                            </select>
                        </div>

                        <!-- Product Knowledge -->
                        <div class="col-md-4 mb-3" id="edit-agent-knowledge-group">
                            <label for="edit-agent-knowledge" class="form-label">
                                Product Knowledge <span class="text-danger">*</span>
                            </label>
                            <input type="number"
                                   class="form-control"
                                   id="edit-agent-knowledge"
                                   name="product_knowledge"
                                   min="1"
                                   max="10"
                                   value="<?= htmlspecialchars($agent['product_knowledge']) ?>"
                                   required
                                   placeholder="1-10">
                            <small class="text-muted">Scale of 1-10</small>
                        </div>

                        <!-- Technical Level -->
                        <div class="col-md-4 mb-3" id="edit-agent-technical-group">
                            <label for="edit-agent-technical" class="form-label">
                                Technical Level <span class="text-danger">*</span>
                            </label>
                            <input type="number"
                                   class="form-control"
                                   id="edit-agent-technical"
                                   name="technical_level"
                                   min="1"
                                   max="10"
                                   value="<?= htmlspecialchars($agent['technical_level']) ?>"
                                   required
                                   placeholder="1-10">
                            <small class="text-muted">Scale of 1-10</small>
                        </div>
                    </div>

                    <!-- Agent Prompt -->
                    <div class="mb-3" id="edit-agent-prompt-group">
                        <label for="edit-agent-prompt" class="form-label">Agent Prompt</label>
                        <textarea class="form-control"
                                  id="edit-agent-prompt"
                                  name="agent_prompt"
                                  rows="5"
                                  placeholder="Enter the agent's system prompt"><?= htmlspecialchars($agent['agent_prompt'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="modal-footer" id="edit-agent-modal-footer">
                    <button type="button"
                            class="btn btn-secondary"
                            id="btn-cancel-edit-agent"
                            data-bs-dismiss="modal">
                        <i class="feather-x me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="btn-submit-edit-agent">
                        <span class="d-none" id="edit-agent-loading">
                            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        </span>
                        <i class="feather-save me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
