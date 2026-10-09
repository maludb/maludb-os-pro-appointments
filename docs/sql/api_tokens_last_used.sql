-- Track when each API token / agent key was last used (Settings > Integrations > Agent API Keys)
ALTER TABLE api_tokens ADD COLUMN IF NOT EXISTS last_used_at TIMESTAMP NULL;
