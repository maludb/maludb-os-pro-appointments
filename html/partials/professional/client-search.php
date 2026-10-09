<?php
require_once __DIR__ . '/../../../helpers/auth.php';

requireAuth();
requireManager();

$restaurantId = currentRestaurantId();
$query = trim($_GET['client_query'] ?? '');

if (!$restaurantId) {
    echo '<div class="alert alert-danger mt-2" id="professional-client-search-no-restaurant">No professional account is currently selected.</div>';
    exit;
}

if ($query === '' || strlen($query) < 2) {
    echo '<div id="professional-client-search-empty"></div>';
    exit;
}

$likeQuery = '%' . $query . '%';
$stmt = db()->prepare(
    "SELECT id, first_name, last_name, email, phone, last_appointment_at
     FROM professional_clients
     WHERE restaurant_id = ?
       AND (
            first_name LIKE ?
            OR last_name LIKE ?
            OR CONCAT(first_name, ' ', last_name) LIKE ?
            OR email LIKE ?
            OR phone LIKE ?
       )
     ORDER BY last_appointment_at DESC, last_name ASC, first_name ASC
     LIMIT 8"
);
$stmt->execute([$restaurantId, $likeQuery, $likeQuery, $likeQuery, $likeQuery, $likeQuery]);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="mt-2" id="professional-client-search-main">
    <?php if (empty($clients)): ?>
    <div class="small text-muted" id="professional-client-search-no-results">
        No existing client matched this search. Fill out the name fields below to create a new client with this appointment.
    </div>
    <?php else: ?>
    <div class="list-group" id="professional-client-search-results-list">
        <?php foreach ($clients as $client): ?>
        <button type="button"
                class="list-group-item list-group-item-action"
                id="professional-client-search-result-<?php echo (int)$client['id']; ?>"
                data-client-id="<?php echo (int)$client['id']; ?>"
                data-first-name="<?php echo htmlspecialchars($client['first_name']); ?>"
                data-last-name="<?php echo htmlspecialchars($client['last_name']); ?>"
                data-email="<?php echo htmlspecialchars($client['email'] ?? ''); ?>"
                data-phone="<?php echo htmlspecialchars($client['phone'] ?? ''); ?>"
                onclick="window.selectProfessionalClient && window.selectProfessionalClient(this);">
            <div class="d-flex justify-content-between align-items-start" id="professional-client-search-result-row-<?php echo (int)$client['id']; ?>">
                <div id="professional-client-search-result-copy-<?php echo (int)$client['id']; ?>">
                    <div class="fw-semibold" id="professional-client-search-result-name-<?php echo (int)$client['id']; ?>">
                        <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?>
                    </div>
                    <div class="small text-muted" id="professional-client-search-result-contact-<?php echo (int)$client['id']; ?>">
                        <?php
                        $contactSummary = $client['phone'] ?: ($client['email'] ?: 'No contact details');
                        echo htmlspecialchars($contactSummary);
                        ?>
                    </div>
                </div>
                <div class="small text-muted text-end" id="professional-client-search-result-meta-<?php echo (int)$client['id']; ?>">
                    <?php echo !empty($client['last_appointment_at']) ? htmlspecialchars(date('M j, Y', strtotime($client['last_appointment_at']))) : 'Newer client'; ?>
                </div>
            </div>
        </button>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
