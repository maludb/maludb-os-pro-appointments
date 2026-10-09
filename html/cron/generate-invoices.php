<?php
/**
 * Cron Job: Generate Invoices
 *
 * Runs daily. Creates invoices for restaurants whose invoice_day_of_month matches today.
 * Auto-applies prepay balance when available.
 *
 * Crontab: 0 2 * * * php /var/www/html/cron/generate-invoices.php >> /var/www/logs/cron-invoices.log 2>&1
 */

require_once __DIR__ . '/../../config/database.php';

$pdo = Database::getInstance()->getConnection();
$today = (int)date('j'); // Day of month (1-31)
$now = date('Y-m-d H:i:s');

echo "[$now] Invoice generation started for day $today\n";

// Find restaurants due for invoicing today
$stmt = $pdo->prepare(
    "SELECT id, name, invoice_day_of_month, invoice_description, monthly_price,
            affiliate_commission, prepay_balance, affiliate_id
     FROM restaurants
     WHERE invoice_day_of_month = ?
       AND monthly_price > 0
       AND is_active = 1"
);
$stmt->execute([$today]);
$restaurants = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($restaurants)) {
    echo "[$now] No restaurants due for invoicing today.\n";
    exit(0);
}

// Get next invoice number
$numStmt = $pdo->query("SELECT MAX(id) FROM invoices");
$maxId = (int)$numStmt->fetchColumn();

$created = 0;

foreach ($restaurants as $r) {
    $restaurantId = (int)$r['id'];
    $amount = (float)$r['monthly_price'];
    $description = $r['invoice_description'];
    $commissionPct = (float)$r['affiliate_commission'];
    $prepayBalance = (float)$r['prepay_balance'];

    // Calculate billing period (previous month's billing day to today)
    $periodEnd = date('Y-m-d');
    $periodStart = date('Y-m-d', strtotime('-1 month'));
    $dueDate = date('Y-m-d', strtotime('+30 days'));

    // Calculate affiliate commission amount
    $commissionAmount = ($commissionPct > 0 && $r['affiliate_id'])
        ? round($amount * ($commissionPct / 100), 2)
        : 0.00;

    // Generate invoice number
    $maxId++;
    $invoiceNumber = 'INV-' . str_pad($maxId, 6, '0', STR_PAD_LEFT);

    try {
        $pdo->beginTransaction();

        // Create the invoice
        $insStmt = $pdo->prepare(
            "INSERT INTO invoices (restaurant_id, invoice_number, line_description, amount,
                                   affiliate_commission_amount, status, period_start, period_end, due_date)
             VALUES (?, ?, ?, ?, ?, 'unpaid', ?, ?, ?)"
        );
        $insStmt->execute([
            $restaurantId, $invoiceNumber, $description, $amount,
            $commissionAmount, $periodStart, $periodEnd, $dueDate
        ]);
        $invoiceId = (int)$pdo->lastInsertId();

        // Auto-apply prepay balance if available
        if ($prepayBalance > 0) {
            $applyAmount = min($prepayBalance, $amount);
            $newBalance = round($prepayBalance - $applyAmount, 2);

            // Create payment record
            $payStmt = $pdo->prepare(
                "INSERT INTO invoice_payments (invoice_id, restaurant_id, amount, payment_method, reference_note)
                 VALUES (?, ?, ?, 'prepay', ?)"
            );
            $payStmt->execute([$invoiceId, $restaurantId, $applyAmount, "Auto-applied from prepay balance"]);

            // Create prepay transaction
            $ptStmt = $pdo->prepare(
                "INSERT INTO prepay_transactions (restaurant_id, amount, balance_after, type, reference_note, invoice_id)
                 VALUES (?, ?, ?, 'invoice_applied', ?, ?)"
            );
            $ptStmt->execute([$restaurantId, -$applyAmount, $newBalance, "Applied to $invoiceNumber", $invoiceId]);

            // Update restaurant prepay balance
            $updStmt = $pdo->prepare("UPDATE restaurants SET prepay_balance = ? WHERE id = ?");
            $updStmt->execute([$newBalance, $restaurantId]);

            // If fully paid, mark invoice as paid
            if ($applyAmount >= $amount) {
                $paidStmt = $pdo->prepare(
                    "UPDATE invoices SET status = 'paid', paid_at = NOW() WHERE id = ?"
                );
                $paidStmt->execute([$invoiceId]);
                echo "[$now] Invoice $invoiceNumber for '{$r['name']}' — \${$amount} — PAID via prepay\n";
            } else {
                echo "[$now] Invoice $invoiceNumber for '{$r['name']}' — \${$amount} — \${$applyAmount} applied from prepay, remaining: \$" . round($amount - $applyAmount, 2) . "\n";
            }
        } else {
            echo "[$now] Invoice $invoiceNumber for '{$r['name']}' — \${$amount} — unpaid\n";
        }

        $pdo->commit();
        $created++;
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "[$now] ERROR creating invoice for restaurant {$restaurantId}: " . $e->getMessage() . "\n";
    }
}

echo "[$now] Invoice generation complete. Created $created invoice(s).\n";
