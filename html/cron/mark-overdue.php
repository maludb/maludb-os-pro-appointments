<?php
/**
 * Cron Job: Mark Overdue Invoices
 *
 * Runs daily. Marks unpaid invoices past their due date as 'overdue'.
 *
 * Crontab: 0 3 * * * php /var/www/html/cron/mark-overdue.php >> /var/www/logs/cron-invoices.log 2>&1
 */

require_once __DIR__ . '/../../config/database.php';

$pdo = Database::getInstance()->getConnection();
$now = date('Y-m-d H:i:s');
$today = date('Y-m-d');

echo "[$now] Checking for overdue invoices...\n";

$stmt = $pdo->prepare(
    "UPDATE invoices SET status = 'overdue', updated_at = NOW()
     WHERE status = 'unpaid' AND due_date < ?"
);
$stmt->execute([$today]);
$count = $stmt->rowCount();

echo "[$now] Marked $count invoice(s) as overdue.\n";
