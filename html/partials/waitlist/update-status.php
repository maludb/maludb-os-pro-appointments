<?php
/**
 * Waitlist Status Update — seat, left, no_response
 * GET with action=seat_form returns seating modal
 * POST updates status
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();
$restaurantId = currentRestaurantId();
$pdo = db();

// --- SEAT FORM (GET) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'seat_form') {
    $id = (int)($_GET['id'] ?? 0);

    $stmt = $pdo->prepare(
        "SELECT w.*, g.first_name, g.last_name, g.email
         FROM waitlist w LEFT JOIN guests g ON w.guest_id = g.id
         WHERE w.id = ? AND w.restaurant_id = ? AND w.status IN ('waiting', 'notified')"
    );
    $stmt->execute([$id, $restaurantId]);
    $entry = $stmt->fetch();

    if (!$entry) {
        echo '<div class="alert alert-warning">Waitlist entry not found or already processed.</div>';
        exit;
    }

    $name = $entry['guest_id'] ? ($entry['first_name'] . ' ' . $entry['last_name']) : ($entry['guest_name'] ?: 'Unknown');

    // Get open tables that fit the party (exclude tables with active seated/confirmed reservations today)
    $stmt = $pdo->prepare(
        "SELECT t.id, t.table_name, t.min_seats, t.max_seats, s.name AS section_name
         FROM tables t
         JOIN sections s ON t.section_id = s.id
         WHERE t.restaurant_id = ? AND t.is_active = 1
           AND t.max_seats >= ?
           AND t.id NOT IN (
               SELECT table_id FROM reservations
               WHERE restaurant_id = ? AND reservation_date = CURDATE()
                 AND status IN ('confirmed', 'seated') AND table_id IS NOT NULL
           )
         ORDER BY t.min_seats ASC"
    );
    $stmt->execute([$restaurantId, $entry['party_size'], $restaurantId]);
    $tables = $stmt->fetchAll();

    echo '<div class="modal show d-block" tabindex="-1" id="waitlist-seat-modal" style="background:rgba(0,0,0,0.5);">
        <div class="modal-dialog" id="waitlist-seat-dialog">
            <div class="modal-content" id="waitlist-seat-content">
                <div class="modal-header" id="waitlist-seat-header">
                    <h5 class="modal-title" id="waitlist-seat-title">Seat Guest</h5>
                    <button type="button" class="btn-close" id="waitlist-seat-close"
                            onclick="document.getElementById(\'waitlist-modal-container\').innerHTML=\'\';"></button>
                </div>
                <form hx-post="/partials/waitlist/update-status.php" hx-target="#waitlist-messages" hx-swap="innerHTML" id="waitlist-seat-form">
                    <div class="modal-body" id="waitlist-seat-body">
                        ' . csrf_field() . '
                        <input type="hidden" name="id" value="' . $id . '">
                        <input type="hidden" name="status" value="seated">

                        <div id="waitlist-seat-messages"></div>

                        <p id="waitlist-seat-info"><strong>' . htmlspecialchars($name) . '</strong> — Party of ' . (int)$entry['party_size'] . '</p>';

    if (empty($tables)) {
        echo '<div class="alert alert-warning" id="waitlist-seat-no-tables">No open tables fit this party size. You can still seat them by selecting any open table below.</div>';
        // Show all open tables regardless of size
        $stmt = $pdo->prepare(
            "SELECT t.id, t.table_name, t.min_seats, t.max_seats, s.name AS section_name
             FROM tables t JOIN sections s ON t.section_id = s.id
             WHERE t.restaurant_id = ? AND t.is_active = 1
               AND t.id NOT IN (
                   SELECT table_id FROM reservations
                   WHERE restaurant_id = ? AND reservation_date = CURDATE()
                     AND status IN ('confirmed', 'seated') AND table_id IS NOT NULL
               )
             ORDER BY t.min_seats ASC"
        );
        $stmt->execute([$restaurantId, $restaurantId]);
        $tables = $stmt->fetchAll();
    }

    if (!empty($tables)) {
        echo '<div class="mb-3" id="waitlist-seat-table-group">
                <label for="waitlist-seat-table" class="form-label">Select Table <span class="text-danger">*</span></label>
                <select class="form-select" id="waitlist-seat-table" name="table_id" required>
                    <option value="">Choose a table...</option>';
        foreach ($tables as $t) {
            echo '<option value="' . $t['id'] . '">'
                . htmlspecialchars($t['table_name']) . ' (' . $t['min_seats'] . '-' . $t['max_seats'] . ' seats) — '
                . htmlspecialchars($t['section_name']) . '</option>';
        }
        echo '</select></div>';
    } else {
        echo '<div class="alert alert-danger" id="waitlist-seat-none">No tables are currently available.</div>';
    }

    echo '      </div>
                <div class="modal-footer" id="waitlist-seat-footer">
                    <button type="button" class="btn btn-secondary" id="waitlist-seat-cancel-btn"
                            onclick="document.getElementById(\'waitlist-modal-container\').innerHTML=\'\';">Cancel</button>
                    <button type="submit" class="btn btn-success" id="waitlist-seat-confirm-btn"' . (empty($tables) ? ' disabled' : '') . '>Seat Guest</button>
                </div>
            </form>
        </div>
    </div>
</div>';
    exit;
}

// --- POST: Update Status ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo '<div class="alert alert-danger">Invalid security token.</div>';
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$newStatus = $_POST['status'] ?? '';

if (!in_array($newStatus, ['seated', 'left', 'no_response'])) {
    echo '<div class="alert alert-danger">Invalid status.</div>';
    exit;
}

$stmt = $pdo->prepare(
    "SELECT w.*, g.first_name, g.last_name, g.email, g.phone AS guest_phone
     FROM waitlist w LEFT JOIN guests g ON w.guest_id = g.id
     WHERE w.id = ? AND w.restaurant_id = ? AND w.status IN ('waiting', 'notified')"
);
$stmt->execute([$id, $restaurantId]);
$entry = $stmt->fetch();

if (!$entry) {
    echo '<div class="alert alert-warning">Waitlist entry not found or already processed.</div>';
    exit;
}

$name = $entry['guest_id'] ? ($entry['first_name'] . ' ' . $entry['last_name']) : ($entry['guest_name'] ?: 'Unknown');

try {
    $pdo->beginTransaction();

    if ($newStatus === 'seated') {
        $tableId = (int)($_POST['table_id'] ?? 0);
        if ($tableId < 1) {
            $pdo->rollBack();
            echo '<div class="alert alert-danger">Please select a table.</div>';
            exit;
        }

        // Validate table belongs to restaurant and is available
        $stmt = $pdo->prepare("SELECT id, table_name FROM tables WHERE id = ? AND restaurant_id = ? AND is_active = 1");
        $stmt->execute([$tableId, $restaurantId]);
        $table = $stmt->fetch();
        if (!$table) {
            $pdo->rollBack();
            echo '<div class="alert alert-danger">Table not found or unavailable.</div>';
            exit;
        }

        // Find or create guest if needed
        $guestId = $entry['guest_id'];
        if (!$guestId) {
            $nameParts = explode(' ', $entry['guest_name'], 2);
            $fName = $nameParts[0] ?? 'Walk';
            $lName = $nameParts[1] ?? 'In';
            $phone = $entry['phone'] ?: null;

            $stmt = $pdo->prepare(
                "INSERT INTO guests (restaurant_id, first_name, last_name, phone, visit_count, noshow_count, is_active)
                 VALUES (?, ?, ?, ?, 0, 0, 1)"
            );
            $stmt->execute([$restaurantId, $fName, $lName, $phone]);
            $guestId = (int)$pdo->lastInsertId();
        }

        // Create reservation (walk_in, seated)
        $confirmCode = generateConfirmationCode($restaurantId, date('Y-m-d'));
        $stmt = $pdo->prepare(
            "INSERT INTO reservations (restaurant_id, guest_id, table_id, reservation_date, reservation_time,
             party_size, status, confirmation_code, source, created_by, seated_at)
             VALUES (?, ?, ?, CURDATE(), CURTIME(), ?, 'seated', ?, 'walk_in', ?, NOW())"
        );
        $stmt->execute([$restaurantId, $guestId, $tableId, $entry['party_size'], $confirmCode, currentUserId()]);
        $resId = (int)$pdo->lastInsertId();

        // Update table status
        $stmt = $pdo->prepare("UPDATE tables SET status = 'occupied' WHERE id = ?");
        $stmt->execute([$tableId]);

        // Update waitlist entry
        $stmt = $pdo->prepare(
            "UPDATE waitlist SET status = 'seated', seated_at = NOW(), reservation_id = ? WHERE id = ?"
        );
        $stmt->execute([$resId, $id]);

        $desc = "Seated {$name} (party of {$entry['party_size']}) from waitlist at {$table['table_name']}";
    } else {
        // left or no_response
        $stmt = $pdo->prepare("UPDATE waitlist SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $id]);

        $statusLabel = str_replace('_', ' ', $newStatus);
        $desc = "Waitlist: {$name} marked as {$statusLabel}";
    }

    // Log activity
    $stmt = $pdo->prepare(
        "INSERT INTO activity_log (restaurant_id, user_id, action, entity_type, entity_id, description, ip_address)
         VALUES (?, ?, 'status_change', 'waitlist', ?, ?, ?)"
    );
    $stmt->execute([$restaurantId, currentUserId(), $id, $desc, $_SERVER['REMOTE_ADDR'] ?? null]);

    $pdo->commit();

    header('HX-Trigger: refreshWaitlist');

    if ($newStatus === 'seated') {
        echo '<div class="alert alert-success">' . htmlspecialchars($name) . ' has been seated.</div>';
        echo '<script>document.getElementById("waitlist-modal-container").innerHTML="";</script>';
    } else {
        $label = $newStatus === 'left' ? 'left' : 'no response';
        echo '<div class="alert alert-info">' . htmlspecialchars($name) . ' marked as ' . $label . '.</div>';
    }

} catch (Exception $e) {
    $pdo->rollBack();
    echo '<div class="alert alert-danger">An error occurred. Please try again.</div>';
}
