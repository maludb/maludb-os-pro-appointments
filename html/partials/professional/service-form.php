<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();
requireManager();

$restaurantId = currentRestaurantId();
$serviceId = (int)($_GET['service_id'] ?? 0);
$service = null;
$isEdit = false;

if (!$restaurantId) {
    echo '<div class="alert alert-danger" id="professional-service-form-no-restaurant">No professional account is currently selected.</div>';
    exit;
}

if ($serviceId > 0) {
    $stmt = db()->prepare("SELECT * FROM professional_services WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$serviceId, $restaurantId]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$service) {
        echo '<div class="alert alert-danger" id="professional-service-form-not-found">Service not found.</div>';
        exit;
    }

    $isEdit = true;
}

$locationTypes = [
    '' => 'Use Business Default',
    'in_person' => 'In Person',
    'phone' => 'Phone',
    'video' => 'Video',
    'onsite' => 'On Site',
    'custom' => 'Custom',
];

$formValues = [
    'name' => $service['name'] ?? '',
    'description' => $service['description'] ?? '',
    'duration_minutes' => (int)($service['duration_minutes'] ?? 60),
    'buffer_before_minutes' => (int)($service['buffer_before_minutes'] ?? 0),
    'buffer_after_minutes' => (int)($service['buffer_after_minutes'] ?? 0),
    'price' => isset($service['price']) ? number_format((float)$service['price'], 2, '.', '') : '',
    'location_type' => $service['location_type'] ?? '',
    'location_label' => $service['location_label'] ?? '',
    'color' => $service['color'] ?? '#0d6efd',
    'sort_order' => (int)($service['sort_order'] ?? 0),
    'is_public_bookable' => isset($service['is_public_bookable']) ? (int)$service['is_public_bookable'] : 1,
    'is_active' => isset($service['is_active']) ? (int)$service['is_active'] : 1,
];
?>
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" id="professional-service-modal">
    <div class="modal-dialog modal-lg" id="professional-service-modal-dialog">
        <div class="modal-content" id="professional-service-modal-content">
            <div class="modal-header" id="professional-service-modal-header">
                <h5 class="modal-title" id="professional-service-modal-title">
                    <?php echo $isEdit ? 'Edit Service' : 'Add Service'; ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form hx-post="/partials/professional/save-service.php"
                  hx-target="#professional-service-form-feedback"
                  hx-swap="innerHTML"
                  id="professional-service-form">
                <div class="modal-body" id="professional-service-modal-body">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="service_id" value="<?php echo (int)$serviceId; ?>">

                    <div id="professional-service-form-feedback"></div>

                    <div class="row g-3" id="professional-service-form-fields">
                        <div class="col-md-8" id="professional-service-name-wrap">
                            <label for="professional-service-name" class="form-label fw-semibold">Service Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="professional-service-name" name="name"
                                   value="<?php echo htmlspecialchars($formValues['name']); ?>" required>
                        </div>
                        <div class="col-md-4" id="professional-service-color-wrap">
                            <label for="professional-service-color" class="form-label fw-semibold">Color <span class="text-danger">*</span></label>
                            <input type="color" class="form-control form-control-color w-100" id="professional-service-color" name="color"
                                   value="<?php echo htmlspecialchars($formValues['color']); ?>" required>
                        </div>

                        <div class="col-12" id="professional-service-description-wrap">
                            <label for="professional-service-description" class="form-label fw-semibold">Description</label>
                            <textarea class="form-control" id="professional-service-description" name="description" rows="3"><?php echo htmlspecialchars($formValues['description']); ?></textarea>
                        </div>

                        <div class="col-md-4" id="professional-service-duration-wrap">
                            <label for="professional-service-duration" class="form-label fw-semibold">Duration (minutes) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="professional-service-duration" name="duration_minutes"
                                   value="<?php echo (int)$formValues['duration_minutes']; ?>" min="5" step="5" required>
                        </div>
                        <div class="col-md-4" id="professional-service-buffer-before-wrap">
                            <label for="professional-service-buffer-before" class="form-label fw-semibold">Buffer Before</label>
                            <input type="number" class="form-control" id="professional-service-buffer-before" name="buffer_before_minutes"
                                   value="<?php echo (int)$formValues['buffer_before_minutes']; ?>" min="0" step="5">
                        </div>
                        <div class="col-md-4" id="professional-service-buffer-after-wrap">
                            <label for="professional-service-buffer-after" class="form-label fw-semibold">Buffer After</label>
                            <input type="number" class="form-control" id="professional-service-buffer-after" name="buffer_after_minutes"
                                   value="<?php echo (int)$formValues['buffer_after_minutes']; ?>" min="0" step="5">
                        </div>

                        <div class="col-md-4" id="professional-service-price-wrap">
                            <label for="professional-service-price" class="form-label fw-semibold">Price</label>
                            <div class="input-group" id="professional-service-price-group">
                                <span class="input-group-text" id="professional-service-price-addon">$</span>
                                <input type="number" class="form-control" id="professional-service-price" name="price"
                                       value="<?php echo htmlspecialchars($formValues['price']); ?>" min="0" step="0.01">
                            </div>
                        </div>
                        <div class="col-md-4" id="professional-service-location-type-wrap">
                            <label for="professional-service-location-type" class="form-label fw-semibold">Location Type</label>
                            <select class="form-select" id="professional-service-location-type" name="location_type">
                                <?php foreach ($locationTypes as $locationTypeValue => $locationTypeLabel): ?>
                                <option value="<?php echo htmlspecialchars($locationTypeValue); ?>"
                                    <?php echo ($formValues['location_type'] === $locationTypeValue) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($locationTypeLabel); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4" id="professional-service-sort-order-wrap">
                            <label for="professional-service-sort-order" class="form-label fw-semibold">Sort Order</label>
                            <input type="number" class="form-control" id="professional-service-sort-order" name="sort_order"
                                   value="<?php echo (int)$formValues['sort_order']; ?>" min="0" step="1">
                        </div>

                        <div class="col-12" id="professional-service-location-label-wrap">
                            <label for="professional-service-location-label" class="form-label fw-semibold">Location Label</label>
                            <input type="text" class="form-control" id="professional-service-location-label" name="location_label"
                                   value="<?php echo htmlspecialchars($formValues['location_label']); ?>"
                                   placeholder="Office address, Zoom room, or booking instructions">
                        </div>

                        <div class="col-md-6" id="professional-service-public-wrap">
                            <div class="form-check mt-2" id="professional-service-public-check-wrap">
                                <input type="checkbox" class="form-check-input" id="professional-service-public" name="is_public_bookable" value="1"
                                       <?php echo ($formValues['is_public_bookable'] === 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="professional-service-public">Available for public booking</label>
                            </div>
                        </div>
                        <div class="col-md-6" id="professional-service-active-wrap">
                            <div class="form-check mt-2" id="professional-service-active-check-wrap">
                                <input type="checkbox" class="form-check-input" id="professional-service-active" name="is_active" value="1"
                                       <?php echo ($formValues['is_active'] === 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="professional-service-active">Active service</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" id="professional-service-modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="professional-service-cancel-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="professional-service-save-btn">
                        <i class="feather-save me-1"></i> <?php echo $isEdit ? 'Update' : 'Create'; ?> Service
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
