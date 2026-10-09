<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../config/database.php';

check_auth();
$user = get_user();

// Get all prospects for dropdown
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT id, name FROM prospects ORDER BY name ASC");
$stmt->execute();
$prospects = $stmt->fetchAll();
?>

<!-- Add Agent Modal -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="addAgentModal" tabindex="-1" aria-labelledby="addAgentModalLabel">
    <div class="modal-dialog modal-lg" id="add-agent-modal-dialog">
        <div class="modal-content" id="add-agent-modal-content">
            <div class="modal-header" id="add-agent-modal-header">
                <h5 class="modal-title" id="addAgentModalLabel">
                    <i class="feather-user-plus me-2"></i>Add Agent
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="addAgentForm"
                  hx-post="/partials/agents/save-agent.php"
                  hx-target="#agent-add-result"
                  hx-indicator="#add-agent-loading">

                <div class="modal-body" id="add-agent-modal-body">
                    <!-- Result Messages -->
                    <div id="agent-add-result"></div>

                    <!-- Agent Name -->
                    <div class="mb-3" id="add-agent-name-group">
                        <label for="add-agent-name" class="form-label">
                            Agent Name <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control"
                               id="add-agent-name"
                               name="agent_name"
                               required
                               maxlength="80"
                               placeholder="Enter agent name">
                    </div>

                    <div class="row" id="add-agent-row-1">
                        <!-- Prospect -->
                        <div class="col-md-6 mb-3" id="add-agent-prospect-group">
                            <label for="add-agent-prospect" class="form-label">Prospect</label>
                            <select class="form-select"
                                    id="add-agent-prospect"
                                    name="prospect_id">
                                <option value="">Select a prospect</option>
                                <?php foreach ($prospects as $prospect): ?>
                                    <option value="<?= $prospect['id'] ?>"><?= htmlspecialchars($prospect['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Role -->
                        <div class="col-md-6 mb-3" id="add-agent-role-group">
                            <label for="add-agent-role" class="form-label">
                                Role <span class="text-danger">*</span>
                            </label>
                            <select class="form-select"
                                    id="add-agent-role"
                                    name="agent_role"
                                    required>
                                <option value="">Select a role</option>
                                <option value="buyer">Buyer</option>
                                <option value="decision_maker">Decision Maker</option>
                                <option value="influencer">Influencer</option>
                                <option value="gatekeeper">Gatekeeper</option>
                                <option value="end_user">End User</option>
                            </select>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="mb-3" id="add-agent-description-group">
                        <label for="add-agent-description" class="form-label">Description</label>
                        <input type="text"
                               class="form-control"
                               id="add-agent-description"
                               name="agent_description"
                               maxlength="255"
                               placeholder="Brief description of the agent">
                    </div>

                    <div class="row" id="add-agent-row-voice-llm">
                        <!-- Voice Name -->
                        <div class="col-md-6 mb-3" id="add-agent-voice-group">
                            <label for="add-agent-voice" class="form-label">Voice Name</label>
                            <input type="text"
                                   class="form-control"
                                   id="add-agent-voice"
                                   name="voice_name"
                                   maxlength="80"
                                   placeholder="Enter voice name">
                        </div>

                        <!-- LLM -->
                        <div class="col-md-6 mb-3" id="add-agent-llm-group">
                            <label for="add-agent-llm" class="form-label">LLM</label>
                            <input type="text"
                                   class="form-control"
                                   id="add-agent-llm"
                                   name="llm"
                                   maxlength="80"
                                   placeholder="Enter LLM model">
                        </div>
                    </div>

                    <div class="row" id="add-agent-row-2">
                        <!-- Temperament -->
                        <div class="col-md-4 mb-3" id="add-agent-temperament-group">
                            <label for="add-agent-temperament" class="form-label">
                                Temperament <span class="text-danger">*</span>
                            </label>
                            <select class="form-select"
                                    id="add-agent-temperament"
                                    name="default_temprement"
                                    required>
                                <option value="">Select temperament</option>
                                <option value="friendly">Friendly</option>
                                <option value="neutral">Neutral</option>
                                <option value="skeptical">Skeptical</option>
                                <option value="hostile">Hostile</option>
                                <option value="enthusiastic">Enthusiastic</option>
                            </select>
                        </div>

                        <!-- Product Knowledge -->
                        <div class="col-md-4 mb-3" id="add-agent-knowledge-group">
                            <label for="add-agent-knowledge" class="form-label">
                                Product Knowledge <span class="text-danger">*</span>
                            </label>
                            <input type="number"
                                   class="form-control"
                                   id="add-agent-knowledge"
                                   name="product_knowledge"
                                   min="1"
                                   max="10"
                                   value="5"
                                   required
                                   placeholder="1-10">
                            <small class="text-muted">Scale of 1-10</small>
                        </div>

                        <!-- Technical Level -->
                        <div class="col-md-4 mb-3" id="add-agent-technical-group">
                            <label for="add-agent-technical" class="form-label">
                                Technical Level <span class="text-danger">*</span>
                            </label>
                            <input type="number"
                                   class="form-control"
                                   id="add-agent-technical"
                                   name="technical_level"
                                   min="1"
                                   max="10"
                                   value="5"
                                   required
                                   placeholder="1-10">
                            <small class="text-muted">Scale of 1-10</small>
                        </div>
                    </div>

                    <!-- Agent Prompt -->
                    <div class="mb-3" id="add-agent-prompt-group">
                        <label for="add-agent-prompt" class="form-label">Agent Prompt</label>
                        <textarea class="form-control"
                                  id="add-agent-prompt"
                                  name="agent_prompt"
                                  rows="5"
                                  placeholder="Enter the agent's system prompt"></textarea>
                    </div>
                </div>

                <div class="modal-footer" id="add-agent-modal-footer">
                    <button type="button"
                            class="btn btn-secondary"
                            id="btn-cancel-add-agent"
                            data-bs-dismiss="modal">
                        <i class="feather-x me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="btn-submit-add-agent">
                        <span class="d-none" id="add-agent-loading">
                            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        </span>
                        <i class="feather-plus me-1"></i>Add Agent
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
