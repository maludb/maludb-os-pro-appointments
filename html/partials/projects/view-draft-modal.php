<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../config/database.php';

check_auth();
$user = get_user();

$draftId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$draftId) {
    http_response_code(400);
    echo '<div class="alert alert-danger">Draft ID is required</div>';
    exit;
}

$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT * FROM red_team_drafts WHERE id = ?");
$stmt->execute([$draftId]);
$draft = $stmt->fetch();

if (!$draft) {
    http_response_code(404);
    echo '<div class="alert alert-danger">Draft not found</div>';
    exit;
}
?>

<!-- View Draft Modal -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="viewDraftModal" tabindex="-1" aria-labelledby="viewDraftModalLabel">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" id="view-draft-modal-dialog">
        <div class="modal-content" id="view-draft-modal-content">
            <div class="modal-header" id="view-draft-modal-header">
                <h5 class="modal-title" id="viewDraftModalLabel">
                    <i class="feather-file-text me-2"></i>Draft - Iteration <?= htmlspecialchars($draft['iteration']) ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body" id="view-draft-modal-body">
                <!-- Draft Metadata -->
                <div class="row mb-4" id="draft-metadata-row">
                    <div class="col-md-3" id="draft-meta-quality">
                        <div class="card bg-light">
                            <div class="card-body text-center py-2">
                                <small class="text-muted d-block">Quality Score</small>
                                <span class="fs-4 fw-bold text-primary"><?= htmlspecialchars($draft['quality_score'] ?? '-') ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3" id="draft-meta-critiques">
                        <div class="card bg-light">
                            <div class="card-body text-center py-2">
                                <small class="text-muted d-block">Critiques</small>
                                <span class="fs-4 fw-bold"><?= htmlspecialchars($draft['critique_count'] ?? '0') ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3" id="draft-meta-severity">
                        <div class="card bg-light">
                            <div class="card-body text-center py-2">
                                <small class="text-muted d-block">Severity</small>
                                <span class="fs-4 fw-bold text-warning"><?= htmlspecialchars($draft['severity'] ?? '-') ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3" id="draft-meta-duration">
                        <div class="card bg-light">
                            <div class="card-body text-center py-2">
                                <small class="text-muted d-block">Duration</small>
                                <span class="fs-5 fw-bold"><?= $draft['duration_ms'] ? number_format($draft['duration_ms']) . 'ms' : '-' ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Draft Content -->
                <div id="draft-content-section">
                    <h6 class="text-muted mb-2">Draft Content</h6>
                    <div class="bg-light rounded p-3 border" id="draft-content-container" style="max-height: 60vh; overflow-y: auto;">
                        <pre class="mb-0" style="white-space: pre-wrap; font-size: 0.9rem; font-family: inherit;"><?= htmlspecialchars($draft['draft_text'] ?? '') ?></pre>
                    </div>
                </div>

                <!-- Created timestamp -->
                <div class="mt-3 text-end" id="draft-created-info">
                    <small class="text-muted">
                        Created: <?= $draft['created_at'] ? date('M j, Y g:i A', strtotime($draft['created_at'])) : '-' ?>
                    </small>
                </div>
            </div>

            <div class="modal-footer" id="view-draft-modal-footer">
                <button type="button"
                        class="btn btn-secondary"
                        id="btn-close-view-draft"
                        data-bs-dismiss="modal">
                    <i class="feather-x me-1"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>
