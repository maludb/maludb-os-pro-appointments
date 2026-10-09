-- Fix: Clear incorrect restaurant_id on prospects where it was set to the
-- affiliate's own restaurant at creation time (bug in save-prospect.php).
-- Only clears prospects whose restaurant_id points to an affiliate-type restaurant,
-- meaning no real prospect restaurant was created for them.

UPDATE prospects p
JOIN restaurants r ON r.id = p.restaurant_id
SET p.restaurant_id = NULL
WHERE r.location_type = 'affiliate';
