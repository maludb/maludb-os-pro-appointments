<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../models/Session.php';
require_once __DIR__ . '/../../../models/Prospect.php';
require_once __DIR__ . '/../../../models/Product.php';

requireAuth();

$orgId = currentOrgId();
$userId = currentUserId();
$errors = [];
$success = false;

$method = strtoupper($_SERVER['REQUEST_METHOD']);

if ($method === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    }

    $prospectId = (int)($_POST['prospect_id'] ?? 0);
    $productId = (int)($_POST['product_id'] ?? 0);
    $callType = $_POST['call_type'] ?? 'cold_call';
    $callDate = trim($_POST['call_date'] ?? '');

    if (empty($callDate)) {
        $errors[] = 'Please select a date and time.';
    }

    $validTypes = ['cold_call', 'discovery', 'demo', 'objection_handling', 'closing', 'renewal'];
    if (!in_array($callType, $validTypes)) {
        $errors[] = 'Invalid call type.';
    }

    if (empty($errors)) {
        $sessionModel = new Session();
        $sessionModel->createPlanned([
            'user_id' => $userId,
            'org_id' => $orgId,
            'prospect_id' => $prospectId,
            'product_id' => $productId,
            'call_type' => $callType,
            'call_date' => $callDate,
        ]);
        header('HX-Trigger: sessionScheduled');
        echo '<div class="alert alert-success">Session scheduled successfully.</div>';
        echo '<script>setTimeout(function(){ var m = bootstrap.Modal.getInstance(document.getElementById("scheduleSessionModal")); if(m) m.hide(); document.getElementById("modal-container").innerHTML=""; }, 1000);</script>';
        exit;
    }
}

// Load prospects and products for dropdowns
$prospectModel = new Prospect();
$productModel = new Product();
$prospects = $prospectModel->getAll($orgId);
$products = $productModel->getAll($orgId);

$preDate = $_GET['date'] ?? '';
?>

<div class="modal fade show" id="scheduleSessionModal" tabindex="-1" aria-labelledby="scheduleSessionModalLabel" style="display:block;" aria-modal="true">
    <div class="modal-dialog" id="schedule-session-dialog">
        <div class="modal-content" id="schedule-session-content">
            <div class="modal-header" id="schedule-session-header">
                <h5 class="modal-title" id="scheduleSessionModalLabel">
                    <i class="feather-calendar me-2"></i>Schedule Coaching Session
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form hx-post="/partials/coaching-calendar/schedule.php" hx-target="#schedule-session-form-body" hx-swap="innerHTML">
                <div class="modal-body" id="schedule-session-form-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger" id="schedule-session-errors">
                            <?php foreach ($errors as $e): ?>
                                <div><?= htmlspecialchars($e) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?= csrf_field() ?>

                    <div class="mb-3" id="schedule-session-date-group">
                        <label for="schedule-call-date" class="form-label">Date & Time <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" id="schedule-call-date" name="call_date" value="<?= htmlspecialchars($preDate) ?>" required>
                    </div>

                    <div class="mb-3" id="schedule-session-prospect-group">
                        <label for="schedule-prospect" class="form-label">Prospect</label>
                        <select class="form-select" id="schedule-prospect" name="prospect_id">
                            <option value="">-- Select Prospect --</option>
                            <?php foreach ($prospects as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['prospect_name'] ?: $p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3" id="schedule-session-product-group">
                        <label for="schedule-product" class="form-label">Product</label>
                        <select class="form-select" id="schedule-product" name="product_id">
                            <option value="">-- Select Product --</option>
                            <?php foreach ($products as $pr): ?>
                                <option value="<?= $pr['id'] ?>"><?= htmlspecialchars($pr['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3" id="schedule-session-calltype-group">
                        <label for="schedule-call-type" class="form-label">Call Type</label>
                        <select class="form-select" id="schedule-call-type" name="call_type">
                            <option value="cold_call">Cold Call</option>
                            <option value="discovery">Discovery</option>
                            <option value="demo">Demo</option>
                            <option value="objection_handling">Objection Handling</option>
                            <option value="closing">Closing</option>
                            <option value="renewal">Renewal</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer" id="schedule-session-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btn-schedule-submit">
                        <i class="feather-check me-1"></i>Schedule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal-backdrop fade show"></div>

<script>
document.getElementById('scheduleSessionModal').classList.add('show');
document.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var modal = document.getElementById('scheduleSessionModal');
        var backdrop = document.querySelector('.modal-backdrop');
        if (modal) modal.remove();
        if (backdrop) backdrop.remove();
        document.getElementById('modal-container').innerHTML = '';
    });
});
</script>
