<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../helpers/professional-booking.php';
require_once __DIR__ . '/../../../helpers/professional-notifications.php';

requireAuth();
requireManager();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="professional-update-appointment-status-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="professional-update-appointment-status-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
$appointmentId = (int)($_POST['appointment_id'] ?? 0);
$newStatus = trim($_POST['new_status'] ?? '');

if (!$restaurantId || $appointmentId <= 0) {
    echo '<div class="alert alert-danger" id="professional-update-appointment-status-invalid">Invalid request.</div>';
    exit;
}

$allowedTransitions = [
    'pending' => ['confirmed', 'cancelled', 'no_show'],
    'confirmed' => ['completed', 'cancelled', 'no_show'],
    'completed' => [],
    'cancelled' => [],
    'no_show' => [],
];

$pdo = db();
$appointmentStmt = $pdo->prepare("SELECT * FROM professional_appointments WHERE id = ? AND restaurant_id = ? LIMIT 1");
$appointmentStmt->execute([$appointmentId, $restaurantId]);
$appointment = $appointmentStmt->fetch(PDO::FETCH_ASSOC);

if (!$appointment) {
    echo '<div class="alert alert-danger" id="professional-update-appointment-status-not-found">Appointment not found.</div>';
    exit;
}

$nextStatuses = $allowedTransitions[$appointment['status']] ?? [];
if (!in_array($newStatus, $nextStatuses, true)) {
    echo '<div class="alert alert-danger" id="professional-update-appointment-status-transition-error">Invalid status transition.</div>';
    exit;
}

try {
    $pdo->beginTransaction();
    $previousStatus = (string)$appointment['status'];

    $cancelledAt = null;
    $completedAt = null;

    if ($newStatus === 'cancelled') {
        $cancelledAt = date('Y-m-d H:i:s');
    } elseif ($newStatus === 'completed') {
        $completedAt = date('Y-m-d H:i:s');
    }

    $updateStmt = $pdo->prepare(
        "UPDATE professional_appointments SET
            status = ?,
            cancelled_at = ?,
            completed_at = ?,
            updated_at = NOW()
         WHERE id = ? AND restaurant_id = ?"
    );
    $updateStmt->execute([
        $newStatus,
        $cancelledAt,
        $completedAt,
        $appointmentId,
        $restaurantId,
    ]);

    if ($newStatus === 'completed') {
        $clientStmt = $pdo->prepare(
            "UPDATE professional_clients SET
                last_appointment_at = CASE
                    WHEN last_appointment_at IS NULL OR last_appointment_at < ? THEN ?
                    ELSE last_appointment_at
                END,
                updated_at = NOW()
             WHERE id = ? AND restaurant_id = ?"
        );
        $clientStmt->execute([
            $appointment['start_at'],
            $appointment['start_at'],
            (int)$appointment['client_id'],
            $restaurantId,
        ]);
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo '<div class="alert alert-danger" id="professional-update-appointment-status-failure">Unable to update the appointment status.</div>';
    exit;
}

professionalLogAppointmentActivity(
    $restaurantId,
    currentUserId(),
    $appointmentId,
    'status_change',
    'Appointment status changed from ' . $previousStatus . ' to ' . $newStatus . '.',
    ['status' => $previousStatus],
    ['status' => $newStatus],
    $_SERVER['REMOTE_ADDR'] ?? null
);

if ($newStatus === 'confirmed') {
    professionalSendAppointmentConfirmationNotifications($appointmentId);
} elseif ($newStatus === 'cancelled') {
    professionalSendAppointmentCancellationNotifications($appointmentId);
}

header('HX-Trigger: refreshProfessionalCalendar');

echo '<div class="alert alert-success alert-dismissible fade show" id="professional-update-appointment-status-success">
        <i class="feather-check-circle me-1"></i> Appointment status updated to ' . htmlspecialchars(ucfirst(str_replace('_', ' ', $newStatus))) . '.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>';
