-- Prospect Products: track products pitched to prospects with pricing
CREATE TABLE IF NOT EXISTS prospect_products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    prospect_id INT UNSIGNED NOT NULL,
    affiliate_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    recommended_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    affiliate_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    quoted_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status VARCHAR(50) NOT NULL DEFAULT 'pitched',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_prospect (prospect_id),
    INDEX idx_affiliate (affiliate_id),
    INDEX idx_product (product_id),
    UNIQUE KEY uq_prospect_product (prospect_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
