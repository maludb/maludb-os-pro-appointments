-- ============================================================
-- Invoicing & Payment System — Database Migration
-- Run this SQL to set up billing tables and columns
-- ============================================================

-- 1. Add billing columns to restaurants table
-- (prepay_balance and billing_user columns already exist)
ALTER TABLE restaurants
  ADD COLUMN invoice_day_of_month TINYINT UNSIGNED NOT NULL DEFAULT 1
    COMMENT 'Day of month (1-28) when invoice is generated',
  ADD COLUMN invoice_description VARCHAR(255) NOT NULL DEFAULT 'Monthly Service Fee'
    COMMENT 'Single line item description on the invoice',
  ADD COLUMN monthly_price DECIMAL(10,2) NOT NULL DEFAULT 0.00
    COMMENT 'Monthly invoiced amount',
  ADD COLUMN affiliate_commission DECIMAL(5,2) NOT NULL DEFAULT 0.00
    COMMENT 'Affiliate commission percentage (e.g. 10.00 = 10%)';

-- 2. Create invoices table
CREATE TABLE invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    invoice_number VARCHAR(20) NOT NULL COMMENT 'e.g. INV-000001',
    line_description VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    affiliate_commission_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('unpaid','paid','overdue','void') NOT NULL DEFAULT 'unpaid',
    period_start DATE NOT NULL COMMENT 'Billing period start',
    period_end DATE NOT NULL COMMENT 'Billing period end',
    due_date DATE NOT NULL COMMENT '30 days from creation',
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_invoice_number (invoice_number),
    INDEX idx_restaurant_status (restaurant_id, status),
    INDEX idx_due_date (due_date),
    CONSTRAINT fk_invoices_restaurant FOREIGN KEY (restaurant_id)
        REFERENCES restaurants(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. Create invoice_payments table
CREATE TABLE invoice_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    restaurant_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('prepay','direct','manual') NOT NULL
        COMMENT 'prepay=from balance, direct=card/external, manual=admin adjustment',
    reference_note VARCHAR(255) NULL COMMENT 'Transaction ref or admin note',
    created_by INT UNSIGNED NULL COMMENT 'User who made the payment',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_invoice (invoice_id),
    INDEX idx_restaurant (restaurant_id),
    CONSTRAINT fk_ip_invoice FOREIGN KEY (invoice_id)
        REFERENCES invoices(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_ip_restaurant FOREIGN KEY (restaurant_id)
        REFERENCES restaurants(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Create prepay_transactions table
CREATE TABLE prepay_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL COMMENT 'Positive=deposit, Negative=applied to invoice',
    balance_after DECIMAL(10,2) NOT NULL COMMENT 'Running balance after this transaction',
    type ENUM('deposit','invoice_applied','refund','adjustment') NOT NULL,
    reference_note VARCHAR(255) NULL COMMENT 'e.g. Invoice #INV-000001 or payment ref',
    invoice_id INT UNSIGNED NULL COMMENT 'If applied to a specific invoice',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_restaurant (restaurant_id),
    INDEX idx_invoice (invoice_id),
    CONSTRAINT fk_pt_restaurant FOREIGN KEY (restaurant_id)
        REFERENCES restaurants(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. Nav permissions for billing pages
-- Billing nav visible to all location types for admin/manager roles
INSERT INTO nav_permissions (nav_item_id, label, user_role, restaurant_role, location_type, is_active)
VALUES
    ('nav-caption-billing', 'BILLING caption', NULL, 'admin', NULL, 1),
    ('nav-caption-billing', 'BILLING caption', NULL, 'manager', NULL, 1),
    ('nav-caption-billing', 'BILLING caption', NULL, 'owner', NULL, 1),
    ('nav-billing', 'Billing', NULL, 'admin', NULL, 1),
    ('nav-billing', 'Billing', NULL, 'manager', NULL, 1),
    ('nav-billing', 'Billing', NULL, 'owner', NULL, 1),
    ('nav-caption-billing', 'BILLING caption', 'super-admin', NULL, NULL, 1),
    ('nav-billing', 'Billing', 'super-admin', NULL, NULL, 1),
    ('nav-platform-billing', 'Platform Billing', 'super-admin', NULL, NULL, 1);
