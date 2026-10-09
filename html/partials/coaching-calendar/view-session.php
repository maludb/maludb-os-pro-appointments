<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../models/Session.php';

requireAuth();

$sessionId = (int)($_GET['id'] ?? 0);
$sessionModel = new Session();
$session = $sessionModel->getById($sessionId, currentOrgId());

if (!$session) {
    echo '<div class="alert alert-danger">Session not found.</div>';
    exit;
}

if ($session['user_id'] != currentUserId() && !isManager()) {
    echo '<div class="alert alert-danger">Access denied.</div>';
    exit;
}

$callType = ucfirst(str_replace('_', ' ', $session['call_type'] ?? 'Cold Call'));
$callDate = $session['call_date'] ? date('M j, Y g:i A', strtotime($session['call_date'])) : 'Not scheduled';
$isPlanned = ($session['status'] ?? '') === 'planned';
$prospectName = $session['prospect_name'] ?? '';
$productName = $session['product_name'] ?? '';
$prospectId = (int)($session['prospect_id'] ?? 0);
$productId = (int)($session['product_id'] ?? 0);
?>

<div class="modal fade show" id="viewSessionModal" tabindex="-1" aria-labelledby="viewSessionModalLabel" style="display:block;" aria-modal="true">
    <div class="modal-dialog" id="view-session-dialog">
        <div class="modal-content" id="view-session-content">
            <div class="modal-header" id="view-session-header">
                <h5 class="modal-title" id="viewSessionModalLabel">
                    <i class="feather-calendar me-2"></i><?= $isPlanned ? 'Upcoming' : 'Past' ?> Coaching Session
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="view-session-body">
                <div class="mb-3" id="view-session-status-section">
                    <span class="badge bg-<?= $isPlanned ? 'primary' : 'success' ?> fs-6">
                        <?= $isPlanned ? 'Planned' : 'Completed' ?>
                    </span>
                </div>

                <div class="mb-3" id="view-session-date-section">
                    <h6><i class="feather-clock me-2"></i>Scheduled Date</h6>
                    <p class="mb-0"><?= htmlspecialchars($callDate) ?></p>
                </div>

                <div class="mb-3" id="view-session-type-section">
                    <h6><i class="feather-phone me-2"></i>Call Type</h6>
                    <p class="mb-0"><span class="badge bg-light text-dark"><?= htmlspecialchars($callType) ?></span></p>
                </div>

                <?php if ($prospectName): ?>
                <div class="mb-3" id="view-session-prospect-section">
                    <h6><i class="feather-user me-2"></i>Prospect</h6>
                    <p class="mb-0"><?= htmlspecialchars($prospectName) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($productName): ?>
                <div class="mb-3" id="view-session-product-section">
                    <h6><i class="feather-box me-2"></i>Product</h6>
                    <p class="mb-0"><?= htmlspecialchars($productName) ?></p>
                </div>
                <?php endif; ?>

                <?php if (!$isPlanned && $session['quality_score']): ?>
                <div class="mb-3" id="view-session-score-section">
                    <h6><i class="feather-award me-2"></i>Score</h6>
                    <p class="mb-0"><?= number_format((float)$session['quality_score'], 1) ?> / 10</p>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer" id="view-session-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <?php if ($isPlanned): ?>
                    <a href="#" class="btn btn-primary" id="btn-start-session-call"
                       hx-get="/partials/sessions/setup.php?step=3&prospect_id=<?= $prospectId ?>&product_id=<?= $productId ?>"
                       hx-target="#page-content"
                       onclick="closeViewSessionModal()">
                        <i class="feather-phone-outgoing me-1"></i>Start Call
                    </a>
                <?php else: ?>
                    <a href="#" class="btn btn-primary" id="btn-view-session-review"
                       hx-get="/partials/sessions/detail.php?id=<?= $sessionId ?>"
                       hx-target="#page-content"
                       onclick="closeViewSessionModal()">
                        <i class="feather-eye me-1"></i>View Review
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal-backdrop fade show"></div>

<script>
document.getElementById('viewSessionModal').classList.add('show');

function closeViewSessionModal() {
    var modal = document.getElementById('viewSessionModal');
    var backdrop = document.querySelector('.modal-backdrop');
    if (modal) modal.remove();
    if (backdrop) backdrop.remove();
    document.getElementById('modal-container').innerHTML = '';
}

document.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function(btn) {
    btn.addEventListener('click', closeViewSessionModal);
});
</script>
