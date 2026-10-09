-- 001-salescoach-schema.sql
-- SalesCoach Pro schema migration (Prompt 01)
-- Idempotent: safe to re-run

-- Preserve FK checks
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS=0;

-- Step 1: Drop unused tables (after disabling FKs)
DROP TABLE IF EXISTS `webhook_deliveries`;
DROP TABLE IF EXISTS `research_jobs`;
DROP TABLE IF EXISTS `api_keys`;
DROP TABLE IF EXISTS `event_attendees`;
DROP TABLE IF EXISTS `events`;
DROP TABLE IF EXISTS `tasks`;

-- Step 2: Modify user_agent_session (add missing columns/keys if absent)
ALTER TABLE `user_agent_session`
  DROP COLUMN IF EXISTS `job_id`,
  ADD COLUMN IF NOT EXISTS `user_id` int(10) UNSIGNED NOT NULL AFTER `id`,
  ADD COLUMN IF NOT EXISTS `org_id` int(11) DEFAULT NULL AFTER `user_id`,
  ADD COLUMN IF NOT EXISTS `scored_at` timestamp NULL DEFAULT NULL AFTER `created_at`,
  ADD COLUMN IF NOT EXISTS `manager_notes` text DEFAULT NULL AFTER `scored_at`,
  ADD COLUMN IF NOT EXISTS `scoring_data` longtext DEFAULT NULL COMMENT 'JSON: category-level scores' AFTER `manager_notes`,
  ADD COLUMN IF NOT EXISTS `call_summary` text DEFAULT NULL COMMENT 'AI-generated call summary' AFTER `scoring_data`,
  ADD COLUMN IF NOT EXISTS `strengths` text DEFAULT NULL COMMENT 'JSON array of identified strengths' AFTER `call_summary`,
  ADD COLUMN IF NOT EXISTS `improvements` text DEFAULT NULL COMMENT 'JSON array of improvement areas' AFTER `strengths`,
  ADD INDEX IF NOT EXISTS `idx_user_id` (`user_id`),
  ADD INDEX IF NOT EXISTS `idx_org_id` (`org_id`),
  ADD INDEX IF NOT EXISTS `idx_prospect_id` (`prospect_id`),
  ADD INDEX IF NOT EXISTS `idx_product_id` (`product_id`),
  ADD INDEX IF NOT EXISTS `idx_created_at` (`created_at`);

-- Step 3: Modify scoring_categories
ALTER TABLE `scoring_categories`
  ADD COLUMN IF NOT EXISTS `org_id` int(11) DEFAULT NULL AFTER `id`,
  ADD COLUMN IF NOT EXISTS `weight` decimal(3,2) NOT NULL DEFAULT 1.00 COMMENT 'Weight for overall score calculation' AFTER `description`,
  ADD COLUMN IF NOT EXISTS `sort_order` int(11) NOT NULL DEFAULT 0 AFTER `weight`,
  ADD INDEX IF NOT EXISTS `idx_org_id` (`org_id`);

-- Step 4: Modify scoring_rubric
ALTER TABLE `scoring_rubric`
  ADD COLUMN IF NOT EXISTS `org_id` int(11) DEFAULT NULL AFTER `id`,
  ADD COLUMN IF NOT EXISTS `category_id` int(11) DEFAULT NULL AFTER `org_id`,
  ADD COLUMN IF NOT EXISTS `score_level` int(11) NOT NULL DEFAULT 5 COMMENT 'Score value this criteria describes (1-10)' AFTER `category_id`,
  ADD COLUMN IF NOT EXISTS `criteria` text DEFAULT NULL COMMENT 'What this score level means' AFTER `score_level`,
  ADD INDEX IF NOT EXISTS `idx_org_id` (`org_id`),
  ADD INDEX IF NOT EXISTS `idx_category_id` (`category_id`);

-- Step 5: Modify prospects
ALTER TABLE `prospects`
  ADD COLUMN IF NOT EXISTS `difficulty_level` enum('beginner','intermediate','advanced') NOT NULL DEFAULT 'intermediate' AFTER `lead_volume`,
  ADD COLUMN IF NOT EXISTS `call_types` varchar(255) DEFAULT 'cold_call,discovery,demo,objection_handling,closing,renewal' COMMENT 'Comma-separated applicable call types' AFTER `difficulty_level`,
  ADD COLUMN IF NOT EXISTS `usage_count` int(11) NOT NULL DEFAULT 0 AFTER `call_types`,
  ADD INDEX IF NOT EXISTS `idx_org_id` (`org_id`),
  ADD INDEX IF NOT EXISTS `idx_difficulty` (`difficulty_level`);

-- Step 6: Modify products
ALTER TABLE `products`
  ADD COLUMN IF NOT EXISTS `features_json` longtext DEFAULT NULL COMMENT 'JSON array of product features' AFTER `knowledgebase`,
  ADD COLUMN IF NOT EXISTS `pricing_info` text DEFAULT NULL AFTER `features_json`,
  ADD COLUMN IF NOT EXISTS `competitive_notes` text DEFAULT NULL AFTER `pricing_info`;

-- Step 7: users role enum remains as-is (user=rep, admin=manager, super_admin)

-- Step 8: Insert default scoring categories if missing
INSERT INTO `scoring_categories` (`name`, `description`, `weight`, `sort_order`, `created_by`)
SELECT name, description, weight, sort_order, created_by
FROM (
  SELECT 'Opening & Rapport' AS name, 'How well the rep opens the call and builds initial rapport' AS description, 1.00 AS weight, 1 AS sort_order, 1 AS created_by
  UNION ALL SELECT 'Discovery & Needs Assessment', 'Quality of discovery questions and understanding of prospect needs', 1.50, 2, 1
  UNION ALL SELECT 'Value Proposition', 'How effectively the rep communicates product value aligned to needs', 1.25, 3, 1
  UNION ALL SELECT 'Objection Handling', 'Ability to acknowledge, address, and overcome objections', 1.50, 4, 1
  UNION ALL SELECT 'Product Knowledge', 'Accuracy and depth of product/service knowledge demonstrated', 1.00, 5, 1
  UNION ALL SELECT 'Active Listening', 'Evidence of listening, paraphrasing, and responding to what was said', 1.25, 6, 1
  UNION ALL SELECT 'Closing & Next Steps', 'Effectiveness of closing attempts and establishing clear next steps', 1.25, 7, 1
  UNION ALL SELECT 'Professionalism & Communication', 'Overall communication quality, tone, pace, and professionalism', 0.75, 8, 1
) AS defaults
WHERE NOT EXISTS (
  SELECT 1 FROM `scoring_categories` sc
  WHERE sc.name = defaults.name
);

-- Restore FK checks
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
