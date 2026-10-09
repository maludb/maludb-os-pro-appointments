-- Add client_id and prospect_id columns to sms_message_log
-- Run this on existing databases that already have the sms_message_log table.

ALTER TABLE sms_message_log
    ADD COLUMN client_id INT UNSIGNED NULL COMMENT 'FK to professional_clients' AFTER restaurant_id,
    ADD COLUMN prospect_id INT UNSIGNED NULL COMMENT 'FK to prospects' AFTER client_id,
    ADD INDEX idx_sml_client (client_id),
    ADD INDEX idx_sml_prospect (prospect_id);
