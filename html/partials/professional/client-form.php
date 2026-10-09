<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();
requireManager();

$restaurantId = currentRestaurantId();
$clientId = (int)($_GET['client_id'] ?? 0);
$client = null;
$isEdit = false;

if (!$restaurantId) {
    echo '<div class="alert alert-danger" id="professional-client-form-no-restaurant">No professional account is currently selected.</div>';
    exit;
}

if ($clientId > 0) {
    $stmt = db()->prepare("SELECT * FROM professional_clients WHERE id = ? AND restaurant_id = ? LIMIT 1");
    $stmt->execute([$clientId, $restaurantId]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        echo '<div class="alert alert-danger" id="professional-client-form-not-found">Professional client not found.</div>';
        exit;
    }

    $isEdit = true;
}

$preferredContactOptions = [
    '' => 'Not Set',
    'email' => 'Email',
    'phone' => 'Phone',
    'sms' => 'SMS',
];
?>
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" id="professional-client-modal">
    <div class="modal-dialog modal-lg" id="professional-client-modal-dialog">
        <div class="modal-content" id="professional-client-modal-content">
            <div class="modal-header" id="professional-client-modal-header">
                <h5 class="modal-title" id="professional-client-modal-title">
                    <?php echo $isEdit ? 'Edit Client' : 'Add Client'; ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="professional-client-form"
                  hx-post="/partials/professional/save-client.php"
                  hx-target="#professional-client-form-feedback"
                  hx-swap="innerHTML">
                <div class="modal-body" id="professional-client-modal-body">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="client_id" value="<?php echo $clientId; ?>">

                    <div id="professional-client-form-feedback"></div>

                    <div class="row g-3" id="professional-client-form-row-1">
                        <div class="col-md-6" id="professional-client-first-wrap">
                            <label for="professional-client-first-name" class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="professional-client-first-name" name="first_name"
                                   value="<?php echo htmlspecialchars($client['first_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6" id="professional-client-last-wrap">
                            <label for="professional-client-last-name" class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="professional-client-last-name" name="last_name"
                                   value="<?php echo htmlspecialchars($client['last_name'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="row g-3 mt-1" id="professional-client-form-row-2">
                        <div class="col-md-6" id="professional-client-email-wrap">
                            <label for="professional-client-email" class="form-label fw-semibold">Email</label>
                            <input type="email" class="form-control" id="professional-client-email" name="email"
                                   value="<?php echo htmlspecialchars($client['email'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6" id="professional-client-phone-wrap">
                            <label for="professional-client-phone" class="form-label fw-semibold">Phone</label>
                            <input type="text" class="form-control" id="professional-client-phone" name="phone"
                                   value="<?php echo htmlspecialchars($client['phone'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="row g-3 mt-1" id="professional-client-form-row-3">
                        <div class="col-md-6" id="professional-client-birth-wrap">
                            <label for="professional-client-birth-date" class="form-label fw-semibold">Birth Date</label>
                            <input type="date" class="form-control" id="professional-client-birth-date" name="birth_date"
                                   value="<?php echo htmlspecialchars($client['birth_date'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6" id="professional-client-preferred-wrap">
                            <label for="professional-client-preferred-contact" class="form-label fw-semibold">Preferred Contact Method</label>
                            <select class="form-select" id="professional-client-preferred-contact" name="preferred_contact_method">
                                <?php foreach ($preferredContactOptions as $optionValue => $optionLabel): ?>
                                <option value="<?php echo htmlspecialchars($optionValue); ?>" <?php echo (($client['preferred_contact_method'] ?? '') === $optionValue) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($optionLabel); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <hr class="mt-3 mb-2">
                    <h6 class="fw-semibold" id="professional-client-address-heading">Service Address</h6>

                    <div class="mb-3" id="professional-client-address-line1-wrap">
                        <label for="professional-client-address-line1" class="form-label fw-semibold">Address</label>
                        <input type="text" class="form-control" id="professional-client-address-line1" name="service_address_line1"
                               value="<?php echo htmlspecialchars($client['service_address_line1'] ?? ''); ?>">
                    </div>

                    <div class="row g-3" id="professional-client-form-row-address">
                        <div class="col-md-5" id="professional-client-city-wrap">
                            <label for="professional-client-city" class="form-label fw-semibold">City</label>
                            <input type="text" class="form-control" id="professional-client-city" name="service_city"
                                   value="<?php echo htmlspecialchars($client['service_city'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4" id="professional-client-state-wrap">
                            <label for="professional-client-state" class="form-label fw-semibold">State</label>
                            <input type="text" class="form-control" id="professional-client-state" name="service_state"
                                   value="<?php echo htmlspecialchars($client['service_state'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3" id="professional-client-postal-wrap">
                            <label for="professional-client-postal-code" class="form-label fw-semibold">Postal Code</label>
                            <input type="text" class="form-control" id="professional-client-postal-code" name="service_postal_code"
                                   value="<?php echo htmlspecialchars($client['service_postal_code'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="mt-3" id="professional-client-last-service-wrap">
                        <label for="professional-client-last-service-date" class="form-label fw-semibold">Last Service Date</label>
                        <input type="date" class="form-control" id="professional-client-last-service-date" name="last_service_date"
                               value="<?php echo htmlspecialchars($client['last_service_date'] ?? ''); ?>">
                    </div>

                    <hr class="mt-3 mb-2">

                    <div class="form-check mt-3" id="professional-client-marketing-wrap">
                        <input class="form-check-input" type="checkbox" id="professional-client-marketing-opt-in" name="marketing_opt_in" value="1"
                               <?php echo !empty($client['marketing_opt_in']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="professional-client-marketing-opt-in">Client has opted in to marketing updates</label>
                    </div>

                    <div class="mt-3" id="professional-client-notes-wrap">
                        <label for="professional-client-notes" class="form-label fw-semibold">Client Notes</label>
                        <textarea class="form-control" id="professional-client-notes" name="notes" rows="3"><?php echo htmlspecialchars($client['notes'] ?? ''); ?></textarea>
                    </div>

                    <div class="mt-3" id="professional-client-internal-notes-wrap">
                        <label for="professional-client-internal-notes" class="form-label fw-semibold">Internal Notes</label>
                        <textarea class="form-control" id="professional-client-internal-notes" name="internal_notes" rows="4"><?php echo htmlspecialchars($client['internal_notes'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="modal-footer" id="professional-client-modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="professional-client-cancel-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="professional-client-save-btn">
                        <i class="feather-save me-1"></i> <?php echo $isEdit ? 'Update' : 'Create'; ?> Client
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
