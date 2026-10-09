<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();
requireManager();

$restaurantId = currentRestaurantId();

if (!$restaurantId) {
    echo '<div class="alert alert-danger" id="professional-time-off-no-restaurant">No professional account is currently selected.</div>';
    exit;
}

$stmt = db()->prepare(
    "SELECT *
     FROM professional_time_off
     WHERE restaurant_id = ?
     ORDER BY starts_at ASC, ends_at ASC"
);
$stmt->execute([$restaurantId]);
$timeOffEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="main-content" id="professional-time-off-main"
     hx-get="/partials/professional/time-off.php"
     hx-trigger="refreshProfessionalTimeOffList from:body"
     hx-target="#professional-time-off-main"
     hx-swap="outerHTML">
    <div class="row" id="professional-time-off-row">
        <div class="col-12" id="professional-time-off-col">
            <div class="card" id="professional-time-off-card">
                <div class="card-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3" id="professional-time-off-card-header">
                    <div id="professional-time-off-card-header-copy">
                        <h5 class="card-title mb-1" id="professional-time-off-card-title">
                            <i class="feather-moon me-2"></i>Time Off
                        </h5>
                        <p class="text-muted mb-0" id="professional-time-off-card-subtitle">
                            Manage vacations, holidays, and blocked time ranges.
                        </p>
                    </div>
                    <button class="btn btn-primary btn-sm" id="professional-time-off-add-btn"
                            hx-get="/partials/professional/time-off-form.php"
                            hx-target="#professional-modal-container"
                            hx-swap="innerHTML">
                        <i class="feather-plus me-1"></i> Add Time Off
                    </button>
                </div>
                <div class="card-body" id="professional-time-off-card-body">
                    <div id="professional-time-off-messages"></div>

                    <?php if (empty($timeOffEntries)): ?>
                    <div class="text-center text-muted py-5" id="professional-time-off-empty">
                        <i class="feather-moon d-block mb-3" style="font-size:2rem;"></i>
                        <p class="mb-3" id="professional-time-off-empty-copy">No blocked time has been added yet.</p>
                        <button class="btn btn-outline-primary" id="professional-time-off-empty-add-btn"
                                hx-get="/partials/professional/time-off-form.php"
                                hx-target="#professional-modal-container"
                                hx-swap="innerHTML">
                            <i class="feather-plus me-1"></i> Add Time Off
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive" id="professional-time-off-table-wrap">
                        <table class="table table-hover align-middle mb-0" id="professional-time-off-table">
                            <thead class="bg-light" id="professional-time-off-thead">
                                <tr>
                                    <th>Starts</th>
                                    <th>Ends</th>
                                    <th>Type</th>
                                    <th>Reason</th>
                                    <th>Notes</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="professional-time-off-tbody">
                                <?php foreach ($timeOffEntries as $entry): ?>
                                <tr id="professional-time-off-row-<?php echo (int)$entry['id']; ?>">
                                    <td id="professional-time-off-start-<?php echo (int)$entry['id']; ?>">
                                        <?php echo date('M j, Y g:ia', strtotime($entry['starts_at'])); ?>
                                    </td>
                                    <td id="professional-time-off-end-<?php echo (int)$entry['id']; ?>">
                                        <?php echo date('M j, Y g:ia', strtotime($entry['ends_at'])); ?>
                                    </td>
                                    <td id="professional-time-off-type-<?php echo (int)$entry['id']; ?>">
                                        <?php if ((int)$entry['is_all_day'] === 1): ?>
                                        <span class="badge bg-info text-dark">All Day</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Timed Block</span>
                                        <?php endif; ?>
                                    </td>
                                    <td id="professional-time-off-reason-<?php echo (int)$entry['id']; ?>">
                                        <?php echo htmlspecialchars($entry['reason'] ?: 'Blocked Time'); ?>
                                    </td>
                                    <td id="professional-time-off-notes-<?php echo (int)$entry['id']; ?>">
                                        <?php if (!empty($entry['notes'])): ?>
                                        <?php echo htmlspecialchars(mb_strimwidth($entry['notes'], 0, 90, '...')); ?>
                                        <?php else: ?>
                                        <span class="text-muted">No notes</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end" id="professional-time-off-actions-<?php echo (int)$entry['id']; ?>">
                                        <button class="btn btn-outline-primary btn-sm me-1"
                                                id="professional-time-off-edit-btn-<?php echo (int)$entry['id']; ?>"
                                                hx-get="/partials/professional/time-off-form.php?time_off_id=<?php echo (int)$entry['id']; ?>"
                                                hx-target="#professional-modal-container"
                                                hx-swap="innerHTML"
                                                title="Edit">
                                            <i class="feather-edit-2"></i>
                                        </button>
                                        <button class="btn btn-outline-danger btn-sm"
                                                id="professional-time-off-delete-btn-<?php echo (int)$entry['id']; ?>"
                                                hx-post="/partials/professional/save-time-off.php"
                                                hx-vals='{"action":"delete","time_off_id": <?php echo (int)$entry['id']; ?>, "csrf_token": "<?php echo htmlspecialchars(generate_csrf_token()); ?>"}'
                                                hx-target="#professional-time-off-messages"
                                                hx-swap="innerHTML"
                                                hx-confirm="Delete this blocked time entry?"
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
            </div>
        </div>
    </div>
</div>
