<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../models/Session.php';

requireAuth();

header('Content-Type: application/json');

$start = $_GET['start'] ?? null;
$end = $_GET['end'] ?? null;

if (!$start || !$end) {
    echo json_encode([]);
    exit;
}

$sessionModel = new Session();
$sessions = $sessionModel->getForCalendar(currentUserId(), currentOrgId(), $start, $end);

$events = [];
foreach ($sessions as $s) {
    $isComplete = ($s['status'] === 'complete');
    $title = ($s['prospect_name'] ?: $s['prospect_company'] ?: 'Session');
    if ($s['product_name']) {
        $title .= ' - ' . $s['product_name'];
    }
    $callType = ucfirst(str_replace('_', ' ', $s['call_type'] ?? ''));
    if ($callType) {
        $title .= ' (' . $callType . ')';
    }

    $events[] = [
        'id' => 'session-' . $s['id'],
        'title' => $title,
        'start' => $s['call_date'],
        'allDay' => false,
        'backgroundColor' => $isComplete ? '#198754' : '#0d6efd',
        'borderColor' => $isComplete ? '#198754' : '#0d6efd',
        'extendedProps' => [
            'sessionId' => $s['id'],
            'status' => $s['status'],
            'score' => $s['quality_score'],
        ],
    ];
}

echo json_encode($events);
