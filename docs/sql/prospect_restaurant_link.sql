-- Add restaurant_id to prospects to track auto-created restaurant
ALTER TABLE prospects
ADD COLUMN restaurant_id INT UNSIGNED NULL AFTER affiliate_id,
ADD INDEX idx_prospect_restaurant (restaurant_id);
