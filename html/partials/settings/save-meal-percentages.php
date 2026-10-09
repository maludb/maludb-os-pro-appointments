<?php
/**
 * POST handler: Save meal status percentages per restaurant
 * Each status stored as its own row: meal_pct_seated, meal_pct_ordering, etc.
 */
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';
require_once '../../../helpers/meal-status.php';

requireAuth();
requireManager();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Invalid security token.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
$statuses = array_keys(getDefaultMealPercentages());
$meta = getMealStatusMeta();
$pdo = db();

foreach ($statuses as $status) {
    $val = max(0, min(100, (int)($_POST['pct_' . $status] ?? 0)));
    $settingKey = 'meal_pct_' . $status;

    $stmt = $pdo->prepare(
        "SELECT id FROM settings WHERE restaurant_id = ? AND setting_key = ? LIMIT 1"
    );
    $stmt->execute([$restaurantId, $settingKey]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $pdo->prepare("UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([(string)$val, $existing['id']]);
    } else {
        $label = $meta[$status]['label'] ?? $status;
        $stmt = $pdo->prepare(
            "INSERT INTO settings (restaurant_id, setting_key, setting_value, category, description, created_at, updated_at)
             VALUES (?, ?, ?, 'operations', ?, NOW(), NOW())"
        );
        $stmt->execute([$restaurantId, $settingKey, (string)$val, "Meal progress % for {$label} status"]);
    }
}

echo '<div class="alert alert-success alert-dismissible fade show" id="meal-pct-success">
        <i class="feather-check-circle me-1"></i> Meal progress estimates saved.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>';
