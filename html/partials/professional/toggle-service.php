<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();
requireManager();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="professional-toggle-service-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="professional-toggle-service-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
$serviceId = (int)($_POST['service_id'] ?? 0);

if (!$restaurantId || !$serviceId) {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="professional-toggle-service-invalid">Invalid request.</div>';
    exit;
}

$pdo = db();
$stmt = $pdo->prepare("SELECT is_active FROM professional_services WHERE id = ? AND restaurant_id = ?");
$stmt->execute([$serviceId, $restaurantId]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
    echo '<div class="alert alert-danger" id="professional-toggle-service-not-found">Service not found.</div>';
    exit;
}

$newStatus = ((int)$service['is_active'] === 1) ? 0 : 1;
$updateStmt = $pdo->prepare("UPDATE professional_services SET is_active = ?, updated_at = NOW() WHERE id = ? AND restaurant_id = ?");
$updateStmt->execute([$newStatus, $serviceId, $restaurantId]);

header('HX-Trigger: refreshProfessionalServicesList');

echo '<div class="alert alert-success alert-dismissible fade show" id="professional-toggle-service-success">
        <i class="feather-check-circle me-1"></i> Service ' . ($newStatus === 1 ? 'activated' : 'deactivated') . ' successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>';
