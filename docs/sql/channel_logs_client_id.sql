-- Add client_id to all channel message log tables
-- Links communications to professional_clients for unified history.

-- SMS Message Log
ALTER TABLE sms_message_log
  ADD COLUMN client_id INT UNSIGNED NULL COMMENT 'FK to professional_clients' AFTER restaurant_id;
ALTER TABLE sms_message_log
  ADD INDEX idx_sml_client (client_id);

-- Email Message Log
ALTER TABLE email_message_log
  ADD COLUMN client_id INT UNSIGNED NULL COMMENT 'FK to professional_clients' AFTER restaurant_id;
ALTER TABLE email_message_log
  ADD INDEX idx_eml_client (client_id);

-- Call Logs (voice)
ALTER TABLE call_logs
  ADD COLUMN client_id INT UNSIGNED NULL COMMENT 'FK to professional_clients' AFTER guest_id;
ALTER TABLE call_logs
  ADD INDEX idx_cl_client (client_id);
