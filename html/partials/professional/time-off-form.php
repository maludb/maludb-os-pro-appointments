<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();
requireManager();

$restaurantId = currentRestaurantId();
$timeOffId = (int)($_GET['time_off_id'] ?? 0);
$timeOff = null;
$isEdit = false;

if (!$restaurantId) {
    echo '<div class="alert alert-danger" id="professional-time-off-form-no-restaurant">No professional account is currently selected.</div>';
    exit;
}

if ($timeOffId > 0) {
    $stmt = db()->prepare("SELECT * FROM professional_time_off WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$timeOffId, $restaurantId]);
    $timeOff = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$timeOff) {
        echo '<div class="alert alert-danger" id="professional-time-off-form-not-found">Time-off entry not found.</div>';
        exit;
    }

    $isEdit = true;
}

$startsAt = $timeOff['starts_at'] ?? '';
$endsAt = $timeOff['ends_at'] ?? '';
$startDate = $startsAt ? date('Y-m-d', strtotime($startsAt)) : '';
$startTime = $startsAt ? date('H:i', strtotime($startsAt)) : '';
$endDate = $endsAt ? date('Y-m-d', strtotime($endsAt)) : '';
$endTime = $endsAt ? date('H:i', strtotime($endsAt)) : '';
?>
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" id="professional-time-off-modal">
    <div class="modal-dialog modal-lg" id="professional-time-off-modal-dialog">
        <div class="modal-content" id="professional-time-off-modal-content">
            <div class="modal-header" id="professional-time-off-modal-header">
                <h5 class="modal-title" id="professional-time-off-modal-title">
                    <?php echo $isEdit ? 'Edit Time Off' : 'Add Time Off'; ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form hx-post="/partials/professional/save-time-off.php"
                  hx-target="#professional-time-off-form-feedback"
                  hx-swap="innerHTML"
                  id="professional-time-off-form">
                <div class="modal-body" id="professional-time-off-modal-body">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="time_off_id" value="<?php echo (int)$timeOffId; ?>">

                    <div id="professional-time-off-form-feedback"></div>

                    <div class="row g-3" id="professional-time-off-dates-row">
                        <div class="col-md-6" id="professional-time-off-start-date-wrap">
                            <label for="professional-time-off-start-date" class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="professional-time-off-start-date" name="start_date"
                                   value="<?php echo htmlspecialchars($startDate); ?>" required>
                        </div>
                        <div class="col-md-6" id="professional-time-off-end-date-wrap">
                            <label for="professional-time-off-end-date" class="form-label fw-semibold">End Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="professional-time-off-end-date" name="end_date"
                                   value="<?php echo htmlspecialchars($endDate); ?>" required>
                        </div>
                    </div>

                    <div class="row g-3 mt-1" id="professional-time-off-times-row">
                        <div class="col-md-6" id="professional-time-off-start-time-wrap">
                            <label for="professional-time-off-start-time" class="form-label fw-semibold">Start Time</label>
                            <input type="time" class="form-control" id="professional-time-off-start-time" name="start_time"
                                   value="<?php echo htmlspecialchars($startTime); ?>">
                        </div>
                        <div class="col-md-6" id="professional-time-off-end-time-wrap">
                            <label for="professional-time-off-end-time" class="form-label fw-semibold">End Time</label>
                            <input type="time" class="form-control" id="professional-time-off-end-time" name="end_time"
                                   value="<?php echo htmlspecialchars($endTime); ?>">
                        </div>
                    </div>

                    <div class="form-check mt-3" id="professional-time-off-all-day-wrap">
                        <input type="checkbox" class="form-check-input" id="professional-time-off-all-day" name="is_all_day" value="1"
                               <?php echo (($timeOff['is_all_day'] ?? 0) ? 'checked' : ''); ?>>
                        <label class="form-check-label" for="professional-time-off-all-day">All-day block</label>
                        <div class="form-text" id="professional-time-off-all-day-help">If checked, time inputs are ignored and the block covers the full selected dates.</div>
                    </div>

                    <div class="mt-3" id="professional-time-off-reason-wrap">
                        <label for="professional-time-off-reason" class="form-label fw-semibold">Reason</label>
                        <input type="text" class="form-control" id="professional-time-off-reason" name="reason"
                               value="<?php echo htmlspecialchars($timeOff['reason'] ?? ''); ?>"
                               placeholder="Vacation, holiday, meeting, personal time">
                    </div>

                    <div class="mt-3" id="professional-time-off-notes-wrap">
                        <label for="professional-time-off-notes" class="form-label fw-semibold">Notes</label>
                        <textarea class="form-control" id="professional-time-off-notes" name="notes" rows="3"><?php echo htmlspecialchars($timeOff['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="modal-footer" id="professional-time-off-modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="professional-time-off-cancel-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="professional-time-off-save-btn">
                        <i class="feather-save me-1"></i> <?php echo $isEdit ? 'Update' : 'Create'; ?> Time Off
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
