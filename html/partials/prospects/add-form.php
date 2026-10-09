<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../config/database.php';

check_auth();
$user = get_user();
?>

<!-- Add Prospect Modal -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="addProspectModal" tabindex="-1" aria-labelledby="addProspectModalLabel">
    <div class="modal-dialog modal-lg" id="add-prospect-modal-dialog">
        <div class="modal-content" id="add-prospect-modal-content">
            <div class="modal-header" id="add-prospect-modal-header">
                <h5 class="modal-title" id="addProspectModalLabel">
                    <i class="feather-target me-2"></i>Add Prospect
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="addProspectForm"
                  hx-post="/partials/prospects/save-prospect.php"
                  hx-target="#prospect-add-result"
                  hx-indicator="#add-prospect-loading">

                <div class="modal-body" id="add-prospect-modal-body">
                    <!-- Result Messages -->
                    <div id="prospect-add-result"></div>

                    <!-- Name (Required) -->
                    <div class="mb-3" id="add-prospect-name-group">
                        <label for="add-prospect-name" class="form-label">
                            Name <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control"
                               id="add-prospect-name"
                               name="name"
                               required
                               maxlength="100"
                               placeholder="Enter prospect name">
                    </div>

                    <!-- Business Type -->
                    <div class="mb-3" id="add-prospect-type-group">
                        <label for="add-prospect-type" class="form-label">Business Type</label>
                        <input type="text"
                               class="form-control"
                               id="add-prospect-type"
                               name="business_type"
                               maxlength="100"
                               placeholder="e.g., Technology, Healthcare, Retail">
                    </div>

                    <!-- Stub -->
                    <div class="mb-3" id="add-prospect-stub-group">
                        <label for="add-prospect-stub" class="form-label">Stub</label>
                        <input type="text"
                               class="form-control"
                               id="add-prospect-stub"
                               name="stub"
                               maxlength="80"
                               placeholder="Short identifier (e.g., acme-corp)">
                        <small class="text-muted">A short, URL-friendly identifier for the prospect</small>
                    </div>

                    <!-- Description -->
                    <div class="mb-3" id="add-prospect-description-group">
                        <label for="add-prospect-description" class="form-label">Description</label>
                        <textarea class="form-control"
                                  id="add-prospect-description"
                                  name="description"
                                  rows="3"
                                  placeholder="Enter prospect description"></textarea>
                    </div>

                    <!-- Agent ID -->
                    <div class="mb-3" id="add-prospect-agent-group">
                        <label for="add-prospect-agent" class="form-label">Agent ID</label>
                        <input type="text"
                               class="form-control"
                               id="add-prospect-agent"
                               name="agent_id"
                               maxlength="30"
                               placeholder="Optional agent ID">
                    </div>
                </div>

                <div class="modal-footer" id="add-prospect-modal-footer">
                    <button type="button"
                            class="btn btn-secondary"
                            id="btn-cancel-add-prospect"
                            data-bs-dismiss="modal">
                        <i class="feather-x me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="btn-submit-add-prospect">
                        <span class="d-none" id="add-prospect-loading">
                            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        </span>
                        <i class="feather-plus me-1"></i>Add Prospect
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
