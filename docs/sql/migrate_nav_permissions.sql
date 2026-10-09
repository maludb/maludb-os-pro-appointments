-- Migration: Move product_type values into location_type, rename RESERVATIONS to SCHEDULING
-- Run this against your live database

-- Step 1: Move product_type values to location_type where location_type is NULL
UPDATE nav_permissions SET location_type = product_type WHERE location_type IS NULL AND product_type IS NOT NULL;

-- Step 2: Clear product_type (no longer used)
UPDATE nav_permissions SET product_type = NULL WHERE product_type IS NOT NULL;

-- Step 3: Rename nav-caption-reservations to nav-caption-scheduling
UPDATE nav_permissions SET nav_item_id = 'nav-caption-scheduling', label = 'SCHEDULING' WHERE nav_item_id = 'nav-caption-reservations';

-- Step 4: Delete any rows that still have location_type=NULL (except always-visible items)
-- These would match ALL location types, causing duplicate nav items
DELETE FROM nav_permissions
WHERE location_type IS NULL
  AND nav_item_id NOT IN ('nav-caption-spacer', 'nav-logout')
  AND user_role IS NULL;

-- Step 5: Add affiliate SCHEDULING section (Dashboard, Appointments, Calendar)
-- Uses the same professional nav item IDs since they share the same partials
INSERT IGNORE INTO nav_permissions (nav_item_id, label, user_role, restaurant_role, location_type) VALUES
('nav-caption-professional', 'SCHEDULING', NULL, NULL, 'affiliate'),
('nav-professional-dashboard', 'Dashboard', NULL, NULL, 'affiliate'),
('nav-professional-appointments', 'Appointments', NULL, NULL, 'affiliate'),
('nav-professional-calendar', 'Calendar', NULL, NULL, 'affiliate');
