<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();
requireManager();

$restaurantId = currentRestaurantId();

if (!$restaurantId) {
    echo '<div class="alert alert-danger" id="professional-availability-no-restaurant">No professional account is currently selected.</div>';
    exit;
}

$dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

$stmt = db()->prepare(
    "SELECT *
     FROM professional_availability_rules
     WHERE restaurant_id = ?
     ORDER BY weekday ASC, start_time ASC, end_time ASC"
);
$stmt->execute([$restaurantId]);
$rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

$rulesByDay = [];
for ($day = 0; $day <= 6; $day++) {
    $rulesByDay[$day] = [];
}

foreach ($rules as $rule) {
    $rulesByDay[(int)$rule['weekday']][] = $rule;
}
?>
<div class="main-content" id="professional-availability-main"
     hx-get="/partials/professional/availability.php"
     hx-trigger="refreshProfessionalAvailabilityList from:body"
     hx-target="#professional-availability-main"
     hx-swap="outerHTML">
    <div class="row" id="professional-availability-row">
        <div class="col-12" id="professional-availability-col">
            <div class="card" id="professional-availability-card">
                <div class="card-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3" id="professional-availability-card-header">
                    <div id="professional-availability-card-header-copy">
                        <h5 class="card-title mb-1" id="professional-availability-card-title">
                            <i class="feather-clock me-2"></i>Availability
                        </h5>
                        <p class="text-muted mb-0" id="professional-availability-card-subtitle">
                            Manage recurring weekly working windows for professional scheduling.
                        </p>
                    </div>
                    <button class="btn btn-primary btn-sm" id="professional-availability-add-btn"
                            hx-get="/partials/professional/availability-form.php"
                            hx-target="#professional-modal-container"
                            hx-swap="innerHTML">
                        <i class="feather-plus me-1"></i> Add Availability Window
                    </button>
                </div>
                <div class="card-body" id="professional-availability-card-body">
                    <div id="professional-availability-messages"></div>

                    <?php for ($day = 0; $day <= 6; $day++): ?>
                    <div class="mb-4" id="professional-availability-day-<?php echo $day; ?>">
                        <h6 class="fw-bold border-bottom pb-2" id="professional-availability-day-title-<?php echo $day; ?>">
                            <?php echo htmlspecialchars($dayNames[$day]); ?>
                            <?php if (empty($rulesByDay[$day])): ?>
                            <span class="badge bg-secondary ms-2" id="professional-availability-day-badge-<?php echo $day; ?>">No windows</span>
                            <?php endif; ?>
                        </h6>

                        <?php if (!empty($rulesByDay[$day])): ?>
                        <div class="table-responsive" id="professional-availability-table-wrap-<?php echo $day; ?>">
                            <table class="table table-sm table-hover align-middle mb-0" id="professional-availability-table-<?php echo $day; ?>">
                                <thead id="professional-availability-thead-<?php echo $day; ?>">
                                    <tr>
                                        <th>Start</th>
                                        <th>End</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="professional-availability-tbody-<?php echo $day; ?>">
                                    <?php foreach ($rulesByDay[$day] as $rule): ?>
                                    <tr id="professional-availability-rule-row-<?php echo (int)$rule['id']; ?>">
                                        <td id="professional-availability-start-<?php echo (int)$rule['id']; ?>">
                                            <?php echo date('g:ia', strtotime($rule['start_time'])); ?>
                                        </td>
                                        <td id="professional-availability-end-<?php echo (int)$rule['id']; ?>">
                                            <?php echo date('g:ia', strtotime($rule['end_time'])); ?>
                                        </td>
                                        <td id="professional-availability-location-<?php echo (int)$rule['id']; ?>">
                                            <?php if (!empty($rule['location_type'])): ?>
                                            <span><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $rule['location_type']))); ?></span>
                                            <?php if (!empty($rule['location_label'])): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($rule['location_label']); ?></small>
                                            <?php endif; ?>
                                            <?php else: ?>
                                            <span class="text-muted">Business default</span>
                                            <?php endif; ?>
                                        </td>
                                        <td id="professional-availability-status-<?php echo (int)$rule['id']; ?>">
                                            <?php if ((int)$rule['is_active'] === 1): ?>
                                            <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end" id="professional-availability-actions-<?php echo (int)$rule['id']; ?>">
                                            <button class="btn btn-outline-primary btn-sm me-1"
                                                    id="professional-availability-edit-btn-<?php echo (int)$rule['id']; ?>"
                                                    hx-get="/partials/professional/availability-form.php?availability_id=<?php echo (int)$rule['id']; ?>"
                                                    hx-target="#professional-modal-container"
                                                    hx-swap="innerHTML"
                                                    title="Edit">
                                                <i class="feather-edit-2"></i>
                                            </button>
                                            <button class="btn btn-outline-<?php echo ((int)$rule['is_active'] === 1) ? 'warning' : 'success'; ?> btn-sm me-1"
                                                    id="professional-availability-toggle-btn-<?php echo (int)$rule['id']; ?>"
                                                    hx-post="/partials/professional/toggle-availability.php"
                                                    hx-vals='{"availability_id": <?php echo (int)$rule['id']; ?>, "csrf_token": "<?php echo htmlspecialchars(generate_csrf_token()); ?>"}'
                                                    hx-target="#professional-availability-messages"
                                                    hx-swap="innerHTML"
                                                    hx-confirm="Are you sure you want to <?php echo ((int)$rule['is_active'] === 1) ? 'deactivate' : 'activate'; ?> this availability window?"
                                                    title="<?php echo ((int)$rule['is_active'] === 1) ? 'Deactivate' : 'Activate'; ?>">
                                                <i class="feather-<?php echo ((int)$rule['is_active'] === 1) ? 'eye-off' : 'eye'; ?>"></i>
                                            </button>
                                            <button class="btn btn-outline-danger btn-sm"
                                                    id="professional-availability-delete-btn-<?php echo (int)$rule['id']; ?>"
                                                    hx-post="/partials/professional/save-availability.php"
                                                    hx-vals='{"action":"delete","availability_id": <?php echo (int)$rule['id']; ?>, "csrf_token": "<?php echo htmlspecialchars(generate_csrf_token()); ?>"}'
                                                    hx-target="#professional-availability-messages"
                                                    hx-swap="innerHTML"
                                                    hx-confirm="Delete this availability window?"
                                                    title="Delete">
                                                <i class="feather-trash-2"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>
</div>
