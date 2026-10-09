<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../config/database.php';

check_auth();
$user = get_user();
?>

<!-- Add Project Modal -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="addProjectModal" tabindex="-1" aria-labelledby="addProjectModalLabel">
    <div class="modal-dialog modal-lg" id="add-project-modal-dialog">
        <div class="modal-content" id="add-project-modal-content">
            <div class="modal-header" id="add-project-modal-header">
                <h5 class="modal-title" id="addProjectModalLabel">
                    <i class="feather-folder-plus me-2"></i>Add Project
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="addProjectForm"
                  hx-post="/partials/projects/save-project.php"
                  hx-target="#project-add-result"
                  hx-indicator="#add-project-loading">

                <div class="modal-body" id="add-project-modal-body">
                    <!-- Result Messages -->
                    <div id="project-add-result"></div>

                    <!-- Name (Required) -->
                    <div class="mb-3" id="add-project-name-group">
                        <label for="add-project-name" class="form-label">
                            Name <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control"
                               id="add-project-name"
                               name="name"
                               required
                               maxlength="100"
                               placeholder="Enter project name">
                    </div>

                    <!-- Stub -->
                    <div class="mb-3" id="add-project-stub-group">
                        <label for="add-project-stub" class="form-label">Stub</label>
                        <input type="text"
                               class="form-control"
                               id="add-project-stub"
                               name="stub"
                               maxlength="80"
                               placeholder="Short identifier (e.g., my-project)">
                        <small class="text-muted">A short, URL-friendly identifier for the project</small>
                    </div>

                    <!-- Description -->
                    <div class="mb-3" id="add-project-description-group">
                        <label for="add-project-description" class="form-label">Description</label>
                        <textarea class="form-control"
                                  id="add-project-description"
                                  name="description"
                                  rows="3"
                                  placeholder="Enter project description"></textarea>
                    </div>

                    <div class="row" id="add-project-settings-row">
                        <!-- Geography -->
                        <div class="col-md-4 mb-3" id="add-project-geography-group">
                            <label for="add-project-geography" class="form-label">Geography</label>
                            <select class="form-select"
                                    id="add-project-geography"
                                    name="geography">
                                <option value="">Select region</option>
                                <option value="1">US East</option>
                                <option value="2">US West</option>
                                <option value="3">Europe</option>
                                <option value="4">Asia Pacific</option>
                                <option value="5">Global</option>
                            </select>
                        </div>

                        <!-- Members -->
                        <div class="col-md-4 mb-3" id="add-project-members-group">
                            <label for="add-project-members" class="form-label">Members</label>
                            <input type="number"
                                   class="form-control"
                                   id="add-project-members"
                                   name="members"
                                   min="0"
                                   value="1"
                                   placeholder="Number of members">
                        </div>

                        <!-- Monthly Spend Limit -->
                        <div class="col-md-4 mb-3" id="add-project-spend-limit-group">
                            <label for="add-project-spend-limit" class="form-label">Monthly Spend Limit ($)</label>
                            <input type="number"
                                   class="form-control"
                                   id="add-project-spend-limit"
                                   name="monthly_spend_limit"
                                   min="0"
                                   placeholder="e.g., 1000">
                        </div>
                    </div>
                </div>

                <div class="modal-footer" id="add-project-modal-footer">
                    <button type="button"
                            class="btn btn-secondary"
                            id="btn-cancel-add-project"
                            data-bs-dismiss="modal">
                        <i class="feather-x me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="btn-submit-add-project">
                        <span class="d-none" id="add-project-loading">
                            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        </span>
                        <i class="feather-plus me-1"></i>Add Project
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
