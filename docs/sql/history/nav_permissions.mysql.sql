-- Step 1: Add location_type column to restaurants table
ALTER TABLE restaurants
ADD COLUMN location_type ENUM('restaurant','professional','affiliate') NOT NULL DEFAULT 'restaurant'
AFTER is_active;

-- Step 2: Create nav_permissions table
CREATE TABLE IF NOT EXISTS nav_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nav_item_id VARCHAR(100) NOT NULL COMMENT 'HTML id attribute of the sidenav <li> element',
    label VARCHAR(100) NULL COMMENT 'Human-readable label for this nav item',
    user_role VARCHAR(50) NULL COMMENT 'users.role: super-admin, affiliate, user, or NULL=any',
    restaurant_role VARCHAR(50) NULL COMMENT 'user_restaurants.role: admin, manager, user, or NULL=any',
    location_type VARCHAR(50) NULL COMMENT 'restaurants.location_type: restaurant, professional, affiliate, or NULL=any',
    product_type VARCHAR(50) NULL COMMENT 'users.product_type: restaurant, professional, affiliate, or NULL=any',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_nav_perm (nav_item_id, user_role, restaurant_role, location_type, product_type)
) ENGINE=InnoDB COMMENT='Maps sidenav items to role/type combinations that can see them';

-- Step 3: Populate nav_permissions
-- NULL in a column means "any value matches"

-- =============================================
-- ALWAYS VISIBLE (any user, any role, any mode)
-- =============================================
INSERT INTO nav_permissions (nav_item_id, label, user_role, restaurant_role, location_type, product_type) VALUES
('nav-caption-spacer', 'Spacer', NULL, NULL, NULL, NULL),
('nav-logout', 'Logout', NULL, NULL, NULL, NULL);

-- =============================================
-- PROFESSIONAL MODE items (product_type='professional')
-- Visible to any restaurant_role in professional mode
-- =============================================
-- SCHEDULING: Dashboard, Appointments, Services, Calendar
INSERT INTO nav_permissions (nav_item_id, label, user_role, restaurant_role, location_type, product_type) VALUES
('nav-caption-professional', 'SCHEDULING', NULL, NULL, 'professional', NULL),
('nav-professional-dashboard', 'Dashboard', NULL, NULL, 'professional', NULL),
('nav-professional-appointments', 'Appointments', NULL, NULL, 'professional', NULL),
('nav-professional-services', 'Services', NULL, NULL, 'professional', NULL),
('nav-professional-calendar', 'Calendar', NULL, NULL, 'professional', NULL),
-- BUSINESS: Clients, Availability, Time Off
('nav-caption-professional-business', 'BUSINESS caption', NULL, NULL, 'professional', NULL),
('nav-professional-clients', 'Clients', NULL, NULL, 'professional', NULL),
('nav-professional-availability', 'Availability', NULL, NULL, 'professional', NULL),
('nav-professional-time-off', 'Time Off', NULL, NULL, 'professional', NULL),
-- ADMIN: Settings, Reports
('nav-caption-professional-admin', 'ADMIN caption', NULL, NULL, 'professional', NULL),
('nav-professional-settings', 'Settings', NULL, NULL, 'professional', NULL),
('nav-professional-reports', 'Reports', NULL, NULL, 'professional', NULL);

-- =============================================
-- AFFILIATE MODE items (location_type='affiliate')
-- SCHEDULING: Dashboard, Appointments, Calendar
-- =============================================
INSERT INTO nav_permissions (nav_item_id, label, user_role, restaurant_role, location_type, product_type) VALUES
('nav-caption-professional', 'SCHEDULING', NULL, NULL, 'affiliate', NULL),
('nav-professional-dashboard', 'Dashboard', NULL, NULL, 'affiliate', NULL),
('nav-professional-appointments', 'Appointments', NULL, NULL, 'affiliate', NULL),
('nav-professional-calendar', 'Calendar', NULL, NULL, 'affiliate', NULL);

-- =============================================
-- RESTAURANT MODE: SCHEDULING section
-- Visible to any restaurant_role when location_type='restaurant'
-- =============================================
INSERT INTO nav_permissions (nav_item_id, label, user_role, restaurant_role, location_type, product_type) VALUES
('nav-caption-scheduling', 'SCHEDULING', NULL, NULL, 'restaurant', NULL),
('nav-dashboard', 'Dashboard', NULL, NULL, 'restaurant', NULL),
('nav-reservations', 'Reservations', NULL, NULL, 'restaurant', NULL),
('nav-floor-plan', 'Floor Plan', NULL, NULL, 'restaurant', NULL),
('nav-waitlist', 'Waitlist', NULL, NULL, 'restaurant', NULL),
('nav-events', 'Events', NULL, NULL, 'restaurant', NULL),
('nav-caption-guests', 'GUESTS caption', NULL, NULL, 'restaurant', NULL),
('nav-guests', 'Guest Directory', NULL, NULL, 'restaurant', NULL);

-- =============================================
-- MANAGEMENT section — Restaurant mode, manager+ role
-- =============================================
INSERT INTO nav_permissions (nav_item_id, label, user_role, restaurant_role, location_type, product_type) VALUES
('nav-caption-management', 'MANAGEMENT caption', NULL, 'admin', 'restaurant', NULL),
('nav-caption-management', 'MANAGEMENT caption', NULL, 'manager', 'restaurant', NULL),
('nav-table-setup', 'Table Setup', NULL, 'admin', 'restaurant', NULL),
('nav-table-setup', 'Table Setup', NULL, 'manager', 'restaurant', NULL),
('nav-sections', 'Sections', NULL, 'admin', 'restaurant', NULL),
('nav-sections', 'Sections', NULL, 'manager', 'restaurant', NULL),
('nav-hours', 'Operating Hours', NULL, 'admin', 'restaurant', NULL),
('nav-hours', 'Operating Hours', NULL, 'manager', 'restaurant', NULL),
('nav-turn-times', 'Turn Times', NULL, 'admin', 'restaurant', NULL),
('nav-turn-times', 'Turn Times', NULL, 'manager', 'restaurant', NULL),
('nav-special-dates', 'Special Dates', NULL, 'admin', 'restaurant', NULL),
('nav-special-dates', 'Special Dates', NULL, 'manager', 'restaurant', NULL),
('nav-reports', 'Reports', NULL, 'admin', 'restaurant', NULL),
('nav-reports', 'Reports', NULL, 'manager', 'restaurant', NULL);

-- =============================================
-- MANAGEMENT section — Affiliate mode, manager+ role
-- =============================================
INSERT INTO nav_permissions (nav_item_id, label, user_role, restaurant_role, location_type, product_type) VALUES
('nav-caption-management', 'PIPELINE caption', NULL, 'admin', 'affiliate', NULL),
('nav-caption-management', 'PIPELINE caption', NULL, 'manager', 'affiliate', NULL),
('nav-prospects', 'Prospects', NULL, 'admin', 'affiliate', NULL),
('nav-prospects', 'Prospects', NULL, 'manager', 'affiliate', NULL),
('nav-in-setup', 'In-Setup', NULL, 'admin', 'affiliate', NULL),
('nav-in-setup', 'In-Setup', NULL, 'manager', 'affiliate', NULL),
('nav-clients', 'Clients', NULL, 'admin', 'affiliate', NULL),
('nav-clients', 'Clients', NULL, 'manager', 'affiliate', NULL),
('nav-reports', 'Reports', NULL, 'admin', 'affiliate', NULL),
('nav-reports', 'Reports', NULL, 'manager', 'affiliate', NULL);

-- =============================================
-- SETTINGS section — admin role only
-- These apply to restaurant, professional, and affiliate modes
-- =============================================
INSERT INTO nav_permissions (nav_item_id, label, user_role, restaurant_role, location_type, product_type) VALUES
('nav-caption-settings', 'SETTINGS caption', NULL, 'admin', 'restaurant', NULL),
('nav-caption-settings', 'SETTINGS caption', NULL, 'admin', 'professional', NULL),
('nav-caption-settings', 'SETTINGS caption', NULL, 'admin', 'affiliate', NULL),
('nav-profile', 'Business Profile', NULL, 'admin', 'restaurant', NULL),
('nav-profile', 'Business Profile', NULL, 'admin', 'professional', NULL),
('nav-profile', 'Business Profile', NULL, 'admin', 'affiliate', NULL),
('nav-notifications', 'Notifications', NULL, 'admin', 'restaurant', NULL),
('nav-notifications', 'Notifications', NULL, 'admin', 'professional', NULL),
('nav-notifications', 'Notifications', NULL, 'admin', 'affiliate', NULL),
('nav-staff', 'Staff Users', NULL, 'admin', 'restaurant', NULL),
('nav-integrations', 'Integrations', NULL, 'admin', 'restaurant', NULL),
('nav-integrations', 'Integrations', NULL, 'admin', 'professional', NULL),
('nav-integrations', 'Integrations', NULL, 'admin', 'affiliate', NULL),
('nav-voice-prompts', 'Voice Prompts', NULL, 'admin', 'restaurant', NULL),
('nav-voice-prompts', 'Voice Prompts', NULL, 'admin', 'professional', NULL),
('nav-voice-prompts', 'Voice Prompts', NULL, 'admin', 'affiliate', NULL),
('nav-text-agents', 'Text Agents', NULL, 'admin', 'restaurant', NULL),
('nav-text-agents', 'Text Agents', NULL, 'admin', 'professional', NULL),
('nav-text-agents', 'Text Agents', NULL, 'admin', 'affiliate', NULL),
('nav-email-agents', 'Email Agents', NULL, 'admin', 'restaurant', NULL),
('nav-email-agents', 'Email Agents', NULL, 'admin', 'professional', NULL),
('nav-email-agents', 'Email Agents', NULL, 'admin', 'affiliate', NULL);

-- =============================================
-- PLATFORM section — super-admin only
-- =============================================
INSERT INTO nav_permissions (nav_item_id, label, user_role, restaurant_role, location_type, product_type) VALUES
('nav-caption-platform', 'PLATFORM caption', 'super-admin', NULL, NULL, NULL),
('nav-manage-restaurants', 'Manage Locations', 'super-admin', NULL, NULL, NULL),
('nav-platform-users', 'Users', 'super-admin', NULL, NULL, NULL),
('nav-products', 'Products & Pricing', 'super-admin', NULL, 'affiliate', NULL),
('nav-subscriptions', 'Subscriptions', 'super-admin', NULL, NULL, NULL),
('nav-account-options', 'Account Options', 'super-admin', NULL, NULL, NULL),
('nav-affiliates', 'Affiliates', 'super-admin', NULL, NULL, NULL);

-- =============================================
-- AFFILIATE section — affiliate users in affiliate mode
-- =============================================
INSERT INTO nav_permissions (nav_item_id, label, user_role, restaurant_role, location_type, product_type) VALUES
('nav-caption-affiliate', 'AFFILIATE caption', 'affiliate', NULL, 'affiliate', NULL),
('nav-affiliate-dashboard', 'My Referrals', 'affiliate', NULL, 'affiliate', NULL),
('nav-affiliate-products', 'Products', NULL, 'admin', 'affiliate', NULL),
('nav-affiliate-products', 'Products', NULL, 'manager', 'affiliate', NULL);

-- =============================================
-- Migration: Add label column to existing nav_permissions table
-- (Run this only if table already exists without the label column)
-- =============================================
-- ALTER TABLE nav_permissions ADD COLUMN label VARCHAR(100) NULL COMMENT 'Human-readable label for this nav item' AFTER nav_item_id;
