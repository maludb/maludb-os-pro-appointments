<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();
requireManager();

$restaurantId = currentRestaurantId();
$availabilityId = (int)($_GET['availability_id'] ?? 0);
$availability = null;
$isEdit = false;

if (!$restaurantId) {
    echo '<div class="alert alert-danger" id="professional-availability-form-no-restaurant">No professional account is currently selected.</div>';
    exit;
}

if ($availabilityId > 0) {
    $stmt = db()->prepare("SELECT * FROM professional_availability_rules WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$availabilityId, $restaurantId]);
    $availability = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$availability) {
        echo '<div class="alert alert-danger" id="professional-availability-form-not-found">Availability window not found.</div>';
        exit;
    }

    $isEdit = true;
}

$dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$locationTypes = [
    '' => 'Use Business Default',
    'in_person' => 'In Person',
    'phone' => 'Phone',
    'video' => 'Video',
    'onsite' => 'On Site',
    'custom' => 'Custom',
];

$startTimeValue = !empty($availability['start_time']) ? date('H:i', strtotime($availability['start_time'])) : '';
$endTimeValue = !empty($availability['end_time']) ? date('H:i', strtotime($availability['end_time'])) : '';
?>
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" id="professional-availability-modal">
    <div class="modal-dialog" id="professional-availability-modal-dialog">
        <div class="modal-content" id="professional-availability-modal-content">
            <div class="modal-header" id="professional-availability-modal-header">
                <h5 class="modal-title" id="professional-availability-modal-title">
                    <?php echo $isEdit ? 'Edit Availability Window' : 'Add Availability Window'; ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form hx-post="/partials/professional/save-availability.php"
                  hx-target="#professional-availability-form-feedback"
                  hx-swap="innerHTML"
                  id="professional-availability-form">
                <div class="modal-body" id="professional-availability-modal-body">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="availability_id" value="<?php echo (int)$availabilityId; ?>">

                    <div id="professional-availability-form-feedback"></div>

                    <div class="mb-3" id="professional-availability-weekday-wrap">
                        <label for="professional-availability-weekday" class="form-label fw-semibold">Day of Week <span class="text-danger">*</span></label>
                        <select class="form-select" id="professional-availability-weekday" name="weekday" required>
                            <?php for ($day = 0; $day <= 6; $day++): ?>
                            <option value="<?php echo $day; ?>"
                                <?php echo ((int)($availability['weekday'] ?? -1) === $day) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dayNames[$day]); ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="row g-3" id="professional-availability-times-row">
                        <div class="col-md-6" id="professional-availability-start-wrap">
                            <label for="professional-availability-start" class="form-label fw-semibold">Start Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="professional-availability-start" name="start_time"
                                   value="<?php echo htmlspecialchars($startTimeValue); ?>" required>
                        </div>
                        <div class="col-md-6" id="professional-availability-end-wrap">
                            <label for="professional-availability-end" class="form-label fw-semibold">End Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="professional-availability-end" name="end_time"
                                   value="<?php echo htmlspecialchars($endTimeValue); ?>" required>
                        </div>
                    </div>

                    <div class="row g-3 mt-1" id="professional-availability-location-row">
                        <div class="col-md-6" id="professional-availability-location-type-wrap">
                            <label for="professional-availability-location-type" class="form-label fw-semibold">Location Type</label>
                            <select class="form-select" id="professional-availability-location-type" name="location_type">
                                <?php foreach ($locationTypes as $locationTypeValue => $locationTypeLabel): ?>
                                <option value="<?php echo htmlspecialchars($locationTypeValue); ?>"
                                    <?php echo (($availability['location_type'] ?? '') === $locationTypeValue) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($locationTypeLabel); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6" id="professional-availability-location-label-wrap">
                            <label for="professional-availability-location-label" class="form-label fw-semibold">Location Label</label>
                            <input type="text" class="form-control" id="professional-availability-location-label" name="location_label"
                                   value="<?php echo htmlspecialchars($availability['location_label'] ?? ''); ?>"
                                   placeholder="Office, studio, phone line, meeting URL">
                        </div>
                    </div>

                    <div class="form-check mt-3" id="professional-availability-active-wrap">
                        <input type="checkbox" class="form-check-input" id="professional-availability-active" name="is_active" value="1"
                               <?php echo (($availability['is_active'] ?? 1) ? 'checked' : ''); ?>>
                        <label class="form-check-label" for="professional-availability-active">Active availability window</label>
                    </div>
                </div>
                <div class="modal-footer" id="professional-availability-modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="professional-availability-cancel-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="professional-availability-save-btn">
                        <i class="feather-save me-1"></i> <?php echo $isEdit ? 'Update' : 'Create'; ?> Availability
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
