-- Channel Message Logs: SMS and Email
-- Voice calls already tracked in call_logs table.

-- SMS Message Log
CREATE TABLE IF NOT EXISTS sms_message_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NULL COMMENT 'FK to professional_clients',
    prospect_id INT UNSIGNED NULL COMMENT 'FK to prospects',
    phone_number_id INT UNSIGNED NULL COMMENT 'FK to restaurant_phone_numbers',
    direction ENUM('inbound','outbound') NOT NULL DEFAULT 'inbound',
    from_number VARCHAR(20) NOT NULL,
    to_number VARCHAR(20) NOT NULL,
    body TEXT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'received' COMMENT 'received, sent, failed',
    external_id VARCHAR(200) NULL COMMENT 'Provider message ID (e.g. Twilio SID)',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sml_restaurant (restaurant_id),
    INDEX idx_sml_phone (phone_number_id),
    INDEX idx_sml_direction (direction),
    INDEX idx_sml_created (created_at),
    INDEX idx_sml_from (from_number),
    INDEX idx_sml_to (to_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Email Message Log
CREATE TABLE IF NOT EXISTS email_message_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    direction ENUM('inbound','outbound') NOT NULL DEFAULT 'inbound',
    from_address VARCHAR(255) NOT NULL,
    to_address VARCHAR(255) NOT NULL,
    subject VARCHAR(500) NULL,
    body_text TEXT NULL,
    body_html TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'received' COMMENT 'received, sent, failed',
    external_id VARCHAR(200) NULL COMMENT 'Provider message ID',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_eml_restaurant (restaurant_id),
    INDEX idx_eml_direction (direction),
    INDEX idx_eml_created (created_at),
    INDEX idx_eml_from (from_address),
    INDEX idx_eml_to (to_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Nav permissions: SMS Messages, Voice Calls, Email Messages
-- Visible to any restaurant_role for all location_types

-- Restaurant mode
INSERT INTO nav_permissions (nav_item_id, label, user_role, restaurant_role, location_type, product_type) VALUES
('nav-sms-messages', 'SMS Messages', NULL, NULL, 'restaurant', NULL),
('nav-voice-calls', 'Voice Calls', NULL, NULL, 'restaurant', NULL),
('nav-email-messages', 'Email Messages', NULL, NULL, 'restaurant', NULL);

-- Professional mode
INSERT INTO nav_permissions (nav_item_id, label, user_role, restaurant_role, location_type, product_type) VALUES
('nav-sms-messages', 'SMS Messages', NULL, NULL, 'professional', NULL),
('nav-voice-calls', 'Voice Calls', NULL, NULL, 'professional', NULL),
('nav-email-messages', 'Email Messages', NULL, NULL, 'professional', NULL);

-- Affiliate mode
INSERT INTO nav_permissions (nav_item_id, label, user_role, restaurant_role, location_type, product_type) VALUES
('nav-sms-messages', 'SMS Messages', NULL, NULL, 'affiliate', NULL),
('nav-voice-calls', 'Voice Calls', NULL, NULL, 'affiliate', NULL),
('nav-email-messages', 'Email Messages', NULL, NULL, 'affiliate', NULL);
