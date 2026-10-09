<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();
requireManager();

$restaurantId = currentRestaurantId();

if (!$restaurantId) {
    echo '<div class="alert alert-danger" id="professional-clients-no-restaurant">No professional account is currently selected.</div>';
    exit;
}

$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$summaryStmt = db()->prepare(
    "SELECT
        COUNT(*) AS total_clients,
        SUM(CASE WHEN email IS NOT NULL AND email != '' THEN 1 ELSE 0 END) AS email_clients,
        SUM(CASE WHEN phone IS NOT NULL AND phone != '' THEN 1 ELSE 0 END) AS phone_clients,
        SUM(CASE WHEN marketing_opt_in = 1 THEN 1 ELSE 0 END) AS marketing_clients
     FROM professional_clients
     WHERE restaurant_id = ?"
);
$summaryStmt->execute([$restaurantId]);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [
    'total_clients' => 0,
    'email_clients' => 0,
    'phone_clients' => 0,
    'marketing_clients' => 0,
];

$where = ['c.restaurant_id = ?'];
$params = [$restaurantId];

if ($search !== '') {
    $where[] = "(c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR CONCAT(c.first_name, ' ', c.last_name) LIKE ?)";
    $searchLike = '%' . $search . '%';
    $params = array_merge($params, [$searchLike, $searchLike, $searchLike, $searchLike, $searchLike]);
}

$whereClause = implode(' AND ', $where);

$countStmt = db()->prepare("SELECT COUNT(*) FROM professional_clients c WHERE {$whereClause}");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$listSql = "
    SELECT
        c.*,
        COALESCE(ap.total_appointments, 0) AS total_appointments,
        COALESCE(ap.completed_appointments, 0) AS completed_appointments,
        COALESCE(ap.issue_appointments, 0) AS issue_appointments,
        ap.last_appointment_at
    FROM professional_clients c
    LEFT JOIN (
        SELECT
            client_id,
            COUNT(*) AS total_appointments,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_appointments,
            SUM(CASE WHEN status IN ('cancelled', 'no_show') THEN 1 ELSE 0 END) AS issue_appointments,
            MAX(start_at) AS last_appointment_at
        FROM professional_appointments
        WHERE restaurant_id = ?
        GROUP BY client_id
    ) ap ON ap.client_id = c.id
    WHERE {$whereClause}
    ORDER BY COALESCE(c.last_appointment_at, ap.last_appointment_at) DESC, c.last_name ASC, c.first_name ASC
    LIMIT {$perPage} OFFSET {$offset}
";

$listParams = array_merge([$restaurantId], $params);
$listStmt = db()->prepare($listSql);
$listStmt->execute($listParams);
$clients = $listStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="main-content" id="professional-clients-main"
     hx-get="/partials/professional/clients.php"
     hx-trigger="refreshProfessionalClientsList from:body"
     hx-target="#professional-clients-main"
     hx-swap="outerHTML">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4" id="professional-clients-header">
        <div id="professional-clients-header-copy">
            <h4 class="mb-1" id="professional-clients-title">Client Directory</h4>
            <p class="text-muted mb-0" id="professional-clients-subtitle">Search, review, and update professional clients and their appointment history.</p>
        </div>
        <div class="d-flex flex-wrap gap-2" id="professional-clients-header-actions">
            <button class="btn btn-primary" id="professional-clients-add-btn"
                    hx-get="/partials/professional/client-form.php"
                    hx-target="#professional-modal-container"
                    hx-swap="innerHTML">
                <i class="feather-plus me-1"></i> Add Client
            </button>
            <a href="#" class="btn btn-outline-secondary" id="professional-clients-calendar-btn"
               hx-get="/partials/professional/calendar.php?view=upcoming"
               hx-target="#page-content">
                <i class="feather-calendar me-1"></i> Appointments
            </a>
        </div>
    </div>

    <div id="professional-clients-messages"></div>

    <div class="row g-3 mb-4" id="professional-clients-stats-row">
        <div class="col-md-6 col-xl-3" id="professional-clients-stat-total-col">
            <div class="card h-100" id="professional-clients-stat-total-card">
                <div class="card-body py-3 text-center" id="professional-clients-stat-total-body">
                    <h4 class="mb-1" id="professional-clients-stat-total-value"><?php echo (int)$summary['total_clients']; ?></h4>
                    <div class="small text-muted" id="professional-clients-stat-total-label">Total Clients</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3" id="professional-clients-stat-email-col">
            <div class="card h-100" id="professional-clients-stat-email-card">
                <div class="card-body py-3 text-center" id="professional-clients-stat-email-body">
                    <h4 class="mb-1 text-primary" id="professional-clients-stat-email-value"><?php echo (int)$summary['email_clients']; ?></h4>
                    <div class="small text-muted" id="professional-clients-stat-email-label">With Email</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3" id="professional-clients-stat-phone-col">
            <div class="card h-100" id="professional-clients-stat-phone-card">
                <div class="card-body py-3 text-center" id="professional-clients-stat-phone-body">
                    <h4 class="mb-1 text-success" id="professional-clients-stat-phone-value"><?php echo (int)$summary['phone_clients']; ?></h4>
                    <div class="small text-muted" id="professional-clients-stat-phone-label">With Phone</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3" id="professional-clients-stat-marketing-col">
            <div class="card h-100" id="professional-clients-stat-marketing-card">
                <div class="card-body py-3 text-center" id="professional-clients-stat-marketing-body">
                    <h4 class="mb-1 text-warning" id="professional-clients-stat-marketing-value"><?php echo (int)$summary['marketing_clients']; ?></h4>
                    <div class="small text-muted" id="professional-clients-stat-marketing-label">Marketing Opt-In</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3" id="professional-clients-filter-card">
        <div class="card-body" id="professional-clients-filter-body">
            <div class="row g-3 align-items-center" id="professional-clients-filter-row">
                <div class="col-md-8" id="professional-clients-search-wrap">
                    <input type="text" class="form-control" id="professional-clients-search" name="search"
                           placeholder="Search by name, email, or phone"
                           value="<?php echo htmlspecialchars($search); ?>"
                           hx-get="/partials/professional/clients.php"
                           hx-trigger="keyup changed delay:250ms"
                           hx-target="#professional-clients-main"
                           hx-swap="outerHTML">
                </div>
                <div class="col-md-4 text-md-end" id="professional-clients-count-wrap">
                    <span class="text-muted" id="professional-clients-count">
                        <?php echo $totalRows; ?> client<?php echo $totalRows === 1 ? '' : 's'; ?> found
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="card" id="professional-clients-table-card">
        <div class="card-body p-0" id="professional-clients-table-body">
            <?php if (empty($clients)): ?>
            <div class="text-center text-muted py-5" id="professional-clients-empty">
                <i class="feather-users d-block mb-3" style="font-size: 2rem;"></i>
                <p class="mb-0" id="professional-clients-empty-copy">No professional clients match the current search.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive" id="professional-clients-table-wrap">
                <table class="table table-hover align-middle mb-0" id="professional-clients-table">
                    <thead id="professional-clients-thead">
                        <tr>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Preferred Contact</th>
                            <th class="text-center">Appointments</th>
                            <th>Last Appointment</th>
                            <th class="text-center">Marketing</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="professional-clients-tbody">
                        <?php foreach ($clients as $client): ?>
                        <tr id="professional-client-row-<?php echo (int)$client['id']; ?>">
                            <td id="professional-client-name-<?php echo (int)$client['id']; ?>">
                                <a href="#" class="text-decoration-none fw-semibold"
                                   hx-get="/partials/professional/client-detail.php?id=<?php echo (int)$client['id']; ?>"
                                   hx-target="#page-content">
                                    <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?>
                                </a>
                                <?php if (!empty($client['birth_date'])): ?>
                                <div class="small text-muted" id="professional-client-birth-<?php echo (int)$client['id']; ?>">
                                    DOB: <?php echo htmlspecialchars(date('M j, Y', strtotime($client['birth_date']))); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td id="professional-client-contact-<?php echo (int)$client['id']; ?>">
                                <div id="professional-client-contact-email-<?php echo (int)$client['id']; ?>">
                                    <?php echo htmlspecialchars($client['email'] ?: 'No email'); ?>
                                </div>
                                <div class="small text-muted" id="professional-client-contact-phone-<?php echo (int)$client['id']; ?>">
                                    <?php echo htmlspecialchars($client['phone'] ?: 'No phone'); ?>
                                </div>
                            </td>
                            <td id="professional-client-preferred-<?php echo (int)$client['id']; ?>">
                                <?php echo htmlspecialchars($client['preferred_contact_method'] ? ucfirst($client['preferred_contact_method']) : 'Not set'); ?>
                            </td>
                            <td class="text-center" id="professional-client-appointments-<?php echo (int)$client['id']; ?>">
                                <div class="fw-semibold" id="professional-client-appointments-total-<?php echo (int)$client['id']; ?>"><?php echo (int)$client['total_appointments']; ?></div>
                                <div class="small text-muted" id="professional-client-appointments-meta-<?php echo (int)$client['id']; ?>">
                                    <?php echo (int)$client['completed_appointments']; ?> done
                                    <?php if ((int)$client['issue_appointments'] > 0): ?>
                                    <span class="text-danger">/ <?php echo (int)$client['issue_appointments']; ?> issues</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td id="professional-client-last-appointment-<?php echo (int)$client['id']; ?>">
                                <?php
                                $lastAppointmentAt = $client['last_appointment_at'] ?: null;
                                echo $lastAppointmentAt ? htmlspecialchars(date('M j, Y g:ia', strtotime($lastAppointmentAt))) : '<span class="text-muted">No appointments</span>';
                                ?>
                            </td>
                            <td class="text-center" id="professional-client-marketing-<?php echo (int)$client['id']; ?>">
                                <?php if ((int)$client['marketing_opt_in'] === 1): ?>
                                <span class="badge bg-success">Opted In</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">No</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end" id="professional-client-actions-<?php echo (int)$client['id']; ?>">
                                <div class="d-inline-flex gap-1" id="professional-client-actions-wrap-<?php echo (int)$client['id']; ?>">
                                    <button class="btn btn-outline-primary btn-sm" id="professional-client-view-btn-<?php echo (int)$client['id']; ?>"
                                            hx-get="/partials/professional/client-detail.php?id=<?php echo (int)$client['id']; ?>"
                                            hx-target="#page-content"
                                            title="View client">
                                        <i class="feather-eye"></i>
                                    </button>
                                    <button class="btn btn-outline-secondary btn-sm" id="professional-client-edit-btn-<?php echo (int)$client['id']; ?>"
                                            hx-get="/partials/professional/client-form.php?client_id=<?php echo (int)$client['id']; ?>"
                                            hx-target="#professional-modal-container"
                                            hx-swap="innerHTML"
                                            title="Edit client">
                                        <i class="feather-edit-2"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="card-footer" id="professional-clients-pagination">
                <nav id="professional-clients-pagination-nav">
                    <ul class="pagination pagination-sm justify-content-center mb-0" id="professional-clients-pagination-list">
                        <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
                        <li class="page-item <?php echo $pageNumber === $page ? 'active' : ''; ?>" id="professional-clients-page-<?php echo $pageNumber; ?>">
                            <a href="#" class="page-link"
                               hx-get="/partials/professional/clients.php?page=<?php echo $pageNumber; ?>&search=<?php echo urlencode($search); ?>"
                               hx-target="#professional-clients-main"
                               hx-swap="outerHTML">
                                <?php echo $pageNumber; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
