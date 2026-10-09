-- Plans and Subscriptions Schema
-- Date: 2026-02-24

-- Plans table
CREATE TABLE IF NOT EXISTS `plans` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `price_monthly` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_yearly` decimal(10,2) NOT NULL DEFAULT 0.00,
  `max_users` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `max_coaching_minutes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `features` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default plans
INSERT INTO `plans` (`id`, `name`, `slug`, `description`, `price_monthly`, `price_yearly`, `max_users`, `max_coaching_minutes`, `features`, `is_active`, `sort_order`) VALUES
(1, 'Free', 'free', 'Get started with basic AI sales coaching', 0.00, 0.00, 1, 30, '{"practice_sessions": true, "session_history": true, "coaching_calendar": false, "team_management": false, "team_analytics": false, "ai_agents": false, "scoring_rubrics": false, "business_context": false, "prospects": false, "products": false}', 1, 1),
(2, 'Pro', 'pro', 'Full-featured AI coaching for individual sellers', 55.00, 550.00, 1, 180, '{"practice_sessions": true, "session_history": true, "coaching_calendar": true, "team_management": false, "team_analytics": false, "ai_agents": true, "scoring_rubrics": true, "business_context": true, "prospects": true, "products": true}', 1, 2),
(3, 'Team', 'team', 'Team coaching with management and analytics', 99.00, 990.00, 25, 300, '{"practice_sessions": true, "session_history": true, "coaching_calendar": true, "team_management": true, "team_analytics": true, "ai_agents": true, "scoring_rubrics": true, "business_context": true, "prospects": true, "products": true}', 1, 3);

-- Subscriptions table
CREATE TABLE IF NOT EXISTS `subscriptions` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `org_id` int(10) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `plan_id` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `status` enum('active','canceled','past_due','trialing') NOT NULL DEFAULT 'active',
  `billing_cycle` enum('monthly','yearly') NOT NULL DEFAULT 'monthly',
  `current_period_start` date DEFAULT NULL,
  `current_period_end` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_subscriptions_user` (`user_id`),
  KEY `idx_subscriptions_org` (`org_id`),
  KEY `idx_subscriptions_plan` (`plan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add plan_id to users table
ALTER TABLE `users` ADD COLUMN `plan_id` int(10) UNSIGNED NOT NULL DEFAULT 1 AFTER `org_id`;
