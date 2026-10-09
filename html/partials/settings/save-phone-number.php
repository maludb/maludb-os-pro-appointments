<?php
/**
 * Save (create or update) a restaurant phone number.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();
if (!isAdmin() && !isSuperAdmin()) {
    http_response_code(403);
    exit('Forbidden');
}
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Invalid CSRF token.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
$pdo = db();

$id           = (int)($_POST['id'] ?? 0);
$phoneNumber  = trim($_POST['phone_number'] ?? '');
$label        = trim($_POST['label'] ?? '');
$languageCode = trim($_POST['language_code'] ?? 'en');
$languageName = trim($_POST['language_name'] ?? 'English');
$isPrimary    = isset($_POST['is_primary']) ? 1 : 0;

if ($phoneNumber === '') {
    echo '<div class="alert alert-danger">Phone number is required.</div>';
    exit;
}

try {
    $pdo->beginTransaction();

    // If marking as primary, clear other primary flags for this restaurant
    if ($isPrimary) {
        $clearStmt = $pdo->prepare(
            "UPDATE restaurant_phone_numbers SET is_primary = 0 WHERE restaurant_id = ?" . ($id > 0 ? " AND id != ?" : "")
        );
        $clearStmt->execute($id > 0 ? [$restaurantId, $id] : [$restaurantId]);
    }

    if ($id > 0) {
        // Update existing — verify ownership
        $stmt = $pdo->prepare(
            "UPDATE restaurant_phone_numbers
             SET phone_number = ?, label = ?, language_code = ?, language_name = ?, is_primary = ?
             WHERE id = ? AND restaurant_id = ?"
        );
        $stmt->execute([$phoneNumber, $label ?: null, $languageCode, $languageName, $isPrimary, $id, $restaurantId]);
    } else {
        // Insert new
        $stmt = $pdo->prepare(
            "INSERT INTO restaurant_phone_numbers (restaurant_id, phone_number, label, language_code, language_name, is_primary)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$restaurantId, $phoneNumber, $label ?: null, $languageCode, $languageName, $isPrimary]);
    }

    $pdo->commit();
    echo '<div class="alert alert-success">Phone number ' . ($id > 0 ? 'updated' : 'added') . '.</div>';
} catch (PDOException $e) {
    $pdo->rollBack();
    echo '<div class="alert alert-danger">Error saving phone number: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
