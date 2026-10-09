<?php
/**
 * Guest Save Handler — Multi-action POST endpoint
 * Actions: save, add_tag, remove_tag, update_pref, update_notes
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo '<div class="alert alert-danger">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
$pdo = db();
$action = $_POST['action'] ?? '';

// --- SAVE (create/update guest) ---
if ($action === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '') ?: null;
    $phone = trim($_POST['phone'] ?? '') ?: null;
    $addressLine1 = trim($_POST['address_line1'] ?? '') ?: null;
    $city = trim($_POST['city'] ?? '') ?: null;
    $state = trim($_POST['state'] ?? '') ?: null;
    $postalCode = trim($_POST['postal_code'] ?? '') ?: null;
    $tags = trim($_POST['tags'] ?? '') ?: null;
    $dietaryRestrictions = trim($_POST['dietary_restrictions'] ?? '') ?: null;
    $allergies = trim($_POST['allergies'] ?? '') ?: null;
    $seatingPreference = trim($_POST['seating_preference'] ?? '') ?: null;
    $notes = trim($_POST['notes'] ?? '') ?: null;
    $doNotCall  = isset($_POST['do_not_call'])  ? 1 : 0;
    $doNotEmail = isset($_POST['do_not_email']) ? 1 : 0;
    $doNotText  = isset($_POST['do_not_text'])  ? 1 : 0;

    if ($firstName === '' || $lastName === '') {
        echo '<div class="alert alert-danger">First and last name are required.</div>';
        exit;
    }

    if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo '<div class="alert alert-danger">Please enter a valid email address.</div>';
        exit;
    }

    if ($id > 0) {
        // Update
        $stmt = $pdo->prepare("SELECT id FROM guests WHERE id = ? AND restaurant_id = ?");
        $stmt->execute([$id, $restaurantId]);
        if (!$stmt->fetch()) {
            echo '<div class="alert alert-danger">Guest not found.</div>';
            exit;
        }

        $stmt = $pdo->prepare(
            "UPDATE guests SET first_name = ?, last_name = ?, email = ?, phone = ?,
             address_line1 = ?, city = ?, state = ?, postal_code = ?,
             tags = ?, dietary_restrictions = ?, allergies = ?, seating_preference = ?, notes = ?,
             do_not_call = ?, do_not_email = ?, do_not_text = ?
             WHERE id = ? AND restaurant_id = ?"
        );
        $stmt->execute([
            $firstName, $lastName, $email, $phone,
            $addressLine1, $city, $state, $postalCode,
            $tags, $dietaryRestrictions, $allergies, $seatingPreference, $notes,
            $doNotCall, $doNotEmail, $doNotText,
            $id, $restaurantId
        ]);
    } else {
        // Insert
        $stmt = $pdo->prepare(
            "INSERT INTO guests (restaurant_id, first_name, last_name, email, phone,
             address_line1, city, state, postal_code,
             tags, dietary_restrictions, allergies, seating_preference, notes,
             do_not_call, do_not_email, do_not_text,
             visit_count, noshow_count, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 1)"
        );
        $stmt->execute([
            $restaurantId, $firstName, $lastName, $email, $phone,
            $addressLine1, $city, $state, $postalCode,
            $tags, $dietaryRestrictions, $allergies, $seatingPreference, $notes,
            $doNotCall, $doNotEmail, $doNotText
        ]);
    }

    header('HX-Trigger: refreshGuestList');
    echo '<div class="alert alert-success">Guest saved successfully.</div>';
    echo '<script>document.getElementById("guest-modal-container").innerHTML="";</script>';
    exit;
}

// --- ADD TAG ---
if ($action === 'add_tag') {
    $id = (int)($_POST['id'] ?? 0);
    $newTag = strtolower(trim($_POST['guest-tag-add-select'] ?? ''));

    if ($id < 1 || $newTag === '') {
        echo '<div class="alert alert-warning">Select a tag to add.</div>';
        exit;
    }

    $stmt = $pdo->prepare("SELECT tags FROM guests WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$id, $restaurantId]);
    $guest = $stmt->fetch();
    if (!$guest) { echo '<div class="alert alert-danger">Guest not found.</div>'; exit; }

    $tags = $guest['tags'] ? array_map('trim', explode(',', $guest['tags'])) : [];
    if (!in_array($newTag, $tags)) {
        $tags[] = $newTag;
    }

    $stmt = $pdo->prepare("UPDATE guests SET tags = ? WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([implode(',', array_filter($tags)), $id, $restaurantId]);

    // Return refreshed detail
    header("HX-Redirect: ");
    $_GET['id'] = $id;
    include __DIR__ . '/detail.php';
    exit;
}

// --- REMOVE TAG ---
if ($action === 'remove_tag') {
    $id = (int)($_POST['id'] ?? 0);
    $removeTag = strtolower(trim($_POST['tag'] ?? ''));

    if ($id < 1 || $removeTag === '') { exit; }

    $stmt = $pdo->prepare("SELECT tags FROM guests WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$id, $restaurantId]);
    $guest = $stmt->fetch();
    if (!$guest) { exit; }

    $tags = $guest['tags'] ? array_map('trim', explode(',', $guest['tags'])) : [];
    $tags = array_filter($tags, function($t) use ($removeTag) { return $t !== $removeTag; });

    $stmt = $pdo->prepare("UPDATE guests SET tags = ? WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([implode(',', $tags) ?: null, $id, $restaurantId]);

    $_GET['id'] = $id;
    include __DIR__ . '/detail.php';
    exit;
}

// --- UPDATE PREFERENCES (inline) ---
if ($action === 'update_pref') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id < 1) { exit; }

    $fields = ['dietary_restrictions', 'allergies', 'seating_preference', 'favorite_server'];
    $updates = [];
    $params = [];

    foreach ($fields as $f) {
        if (isset($_POST[$f])) {
            $updates[] = "{$f} = ?";
            $params[] = trim($_POST[$f]) ?: null;
        }
    }

    if (empty($updates)) { exit; }

    $params[] = $id;
    $params[] = $restaurantId;
    $stmt = $pdo->prepare("UPDATE guests SET " . implode(', ', $updates) . " WHERE id = ? AND restaurant_id = ?");
    $stmt->execute($params);

    echo '<div class="alert alert-success alert-dismissible fade show py-1 px-2 small" id="guest-pref-saved"><button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>Saved</div>';
    exit;
}

// --- UPDATE OPT-OUT FLAGS (inline toggle) ---
if ($action === 'update_optout') {
    $id = (int)($_POST['id'] ?? 0);
    $field = $_POST['field'] ?? '';

    $allowed = ['do_not_call', 'do_not_email', 'do_not_text'];
    if ($id < 1 || !in_array($field, $allowed)) { exit; }

    // Checkbox: present = checked (1), absent = unchecked (0)
    $value = isset($_POST[$field]) ? 1 : 0;

    $stmt = $pdo->prepare("UPDATE guests SET {$field} = ? WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$value, $id, $restaurantId]);

    echo '<div class="alert alert-success alert-dismissible fade show py-1 px-2 small" id="guest-optout-saved"><button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>Saved</div>';
    exit;
}

// --- UPDATE NOTES (inline) ---
if ($action === 'update_notes') {
    $id = (int)($_POST['id'] ?? 0);
    $notes = trim($_POST['notes'] ?? '') ?: null;

    if ($id < 1) { exit; }

    $stmt = $pdo->prepare("UPDATE guests SET notes = ? WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$notes, $id, $restaurantId]);

    echo '<div class="alert alert-success alert-dismissible fade show py-1 px-2 small" id="guest-notes-saved"><button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>Saved</div>';
    exit;
}

echo '<div class="alert alert-danger">Unknown action.</div>';
