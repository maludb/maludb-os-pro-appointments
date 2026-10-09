<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();
requireManager();

$restaurantId = currentRestaurantId();

if (!$restaurantId) {
    echo '<div class="alert alert-danger" id="professional-services-no-restaurant">No professional account is currently selected.</div>';
    exit;
}

$stmt = db()->prepare(
    "SELECT *
     FROM professional_services
     WHERE restaurant_id = ?
     ORDER BY sort_order ASC, name ASC"
);
$stmt->execute([$restaurantId]);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="main-content" id="professional-services-main"
     hx-get="/partials/professional/services.php"
     hx-trigger="refreshProfessionalServicesList from:body"
     hx-target="#professional-services-main"
     hx-swap="outerHTML">
    <div class="row" id="professional-services-row">
        <div class="col-12" id="professional-services-col">
            <div class="card" id="professional-services-card">
                <div class="card-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3" id="professional-services-card-header">
                    <div id="professional-services-card-header-copy">
                        <h5 class="card-title mb-1" id="professional-services-card-title">
                            <i class="feather-briefcase me-2"></i>Services
                        </h5>
                        <p class="text-muted mb-0" id="professional-services-card-subtitle">
                            Manage the services clients can book in your professional scheduling workspace.
                        </p>
                    </div>
                    <button class="btn btn-primary btn-sm" id="professional-services-add-btn"
                            hx-get="/partials/professional/service-form.php"
                            hx-target="#professional-modal-container"
                            hx-swap="innerHTML">
                        <i class="feather-plus me-1"></i> Add Service
                    </button>
                </div>
                <div class="card-body" id="professional-services-card-body">
                    <div id="professional-services-messages"></div>

                    <?php if (empty($services)): ?>
                    <div class="text-center text-muted py-5" id="professional-services-empty">
                        <i class="feather-briefcase d-block mb-3" style="font-size:2rem;"></i>
                        <p class="mb-3" id="professional-services-empty-copy">No services yet. Add your first service to start building the booking catalog.</p>
                        <button class="btn btn-outline-primary" id="professional-services-empty-add-btn"
                                hx-get="/partials/professional/service-form.php"
                                hx-target="#professional-modal-container"
                                hx-swap="innerHTML">
                            <i class="feather-plus me-1"></i> Add Service
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive" id="professional-services-table-wrap">
                        <table class="table table-hover align-middle mb-0" id="professional-services-table">
                            <thead class="bg-light" id="professional-services-thead">
                                <tr>
                                    <th>Service</th>
                                    <th>Duration</th>
                                    <th>Buffers</th>
                                    <th>Price</th>
                                    <th>Location</th>
                                    <th>Public</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="professional-services-tbody">
                                <?php foreach ($services as $service): ?>
                                <tr id="professional-service-row-<?php echo (int)$service['id']; ?>">
                                    <td id="professional-service-name-cell-<?php echo (int)$service['id']; ?>">
                                        <div class="d-flex align-items-start gap-2" id="professional-service-name-wrap-<?php echo (int)$service['id']; ?>">
                                            <span class="rounded-circle mt-1 flex-shrink-0" id="professional-service-color-<?php echo (int)$service['id']; ?>"
                                                  style="width:12px;height:12px;background-color: <?php echo htmlspecialchars($service['color'] ?: '#0d6efd'); ?>;"></span>
                                            <div id="professional-service-name-copy-<?php echo (int)$service['id']; ?>">
                                                <strong><?php echo htmlspecialchars($service['name']); ?></strong>
                                                <?php if (!empty($service['description'])): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars(mb_strimwidth($service['description'], 0, 80, '...')); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td id="professional-service-duration-<?php echo (int)$service['id']; ?>">
                                        <?php echo (int)$service['duration_minutes']; ?> min
                                    </td>
                                    <td id="professional-service-buffers-<?php echo (int)$service['id']; ?>">
                                        <?php echo (int)$service['buffer_before_minutes']; ?> before
                                        <br><small class="text-muted"><?php echo (int)$service['buffer_after_minutes']; ?> after</small>
                                    </td>
                                    <td id="professional-service-price-<?php echo (int)$service['id']; ?>">
                                        <?php if ($service['price'] !== null): ?>
                                        $<?php echo number_format((float)$service['price'], 2); ?>
                                        <?php else: ?>
                                        <span class="text-muted">Not set</span>
                                        <?php endif; ?>
                                    </td>
                                    <td id="professional-service-location-<?php echo (int)$service['id']; ?>">
                                        <?php if (!empty($service['location_type'])): ?>
                                        <span><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $service['location_type']))); ?></span>
                                        <?php if (!empty($service['location_label'])): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($service['location_label']); ?></small>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        <span class="text-muted">Business default</span>
                                        <?php endif; ?>
                                    </td>
                                    <td id="professional-service-public-<?php echo (int)$service['id']; ?>">
                                        <?php if ((int)$service['is_public_bookable'] === 1): ?>
                                        <span class="badge bg-success">Public</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Private</span>
                                        <?php endif; ?>
                                    </td>
                                    <td id="professional-service-status-<?php echo (int)$service['id']; ?>">
                                        <?php if ((int)$service['is_active'] === 1): ?>
                                        <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end" id="professional-service-actions-<?php echo (int)$service['id']; ?>">
                                        <button class="btn btn-outline-primary btn-sm me-1"
                                                id="professional-service-edit-btn-<?php echo (int)$service['id']; ?>"
                                                hx-get="/partials/professional/service-form.php?service_id=<?php echo (int)$service['id']; ?>"
                                                hx-target="#professional-modal-container"
                                                hx-swap="innerHTML"
                                                title="Edit">
                                            <i class="feather-edit-2"></i>
                                        </button>
                                        <button class="btn btn-outline-<?php echo ((int)$service['is_active'] === 1) ? 'warning' : 'success'; ?> btn-sm"
                                                id="professional-service-toggle-btn-<?php echo (int)$service['id']; ?>"
                                                hx-post="/partials/professional/toggle-service.php"
                                                hx-vals='{"service_id": <?php echo (int)$service['id']; ?>, "csrf_token": "<?php echo htmlspecialchars(generate_csrf_token()); ?>"}'
                                                hx-target="#professional-services-messages"
                                                hx-swap="innerHTML"
                                                hx-confirm="Are you sure you want to <?php echo ((int)$service['is_active'] === 1) ? 'deactivate' : 'activate'; ?> this service?"
                                                title="<?php echo ((int)$service['is_active'] === 1) ? 'Deactivate' : 'Activate'; ?>">
                                            <i class="feather-<?php echo ((int)$service['is_active'] === 1) ? 'eye-off' : 'eye'; ?>"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
