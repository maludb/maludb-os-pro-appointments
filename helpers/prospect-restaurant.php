<?php
/**
 * Auto-create a restaurant from a prospect when status moves to in_setup or client.
 * Returns the restaurant_id or null on failure.
 */
require_once __DIR__ . '/db.php';

function createRestaurantFromProspect(array $prospect, int $affiliateUserId): ?int
{
    $pdo = db();

    // Skip if prospect already has a restaurant
    if (!empty($prospect['restaurant_id'])) {
        return (int)$prospect['restaurant_id'];
    }

    // Generate unique slug
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $prospect['business_name']), '-'));
    if ($slug === '') {
        $slug = 'location-' . $prospect['id'];
    }
    $baseSlug = $slug;
    $counter = 1;
    while (true) {
        $check = $pdo->prepare("SELECT id FROM restaurants WHERE slug = ?");
        $check->execute([$slug]);
        if (!$check->fetch()) break;
        $slug = $baseSlug . '-' . $counter++;
    }

    // Map product_interest to location_type
    $productInterest = $prospect['product_interest'] ?? 'restaurant';
    $locationTypeMap = [
        'restaurant'          => 'restaurant',
        'professional'        => 'professional',
        'systems_integrator'  => 'affiliate',
    ];
    $locationType = $locationTypeMap[$productInterest] ?? 'restaurant';

    $pdo->beginTransaction();
    try {
        // Create restaurant
        $stmt = $pdo->prepare(
            "INSERT INTO restaurants (name, slug, phone, email, address_line1, city, state, postal_code, website, timezone, location_type, status, affiliate_id, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'America/Chicago', ?, 'in-setup', ?, 1, NOW())"
        );
        $stmt->execute([
            $prospect['business_name'],
            $slug,
            $prospect['contact_phone'] ?: null,
            $prospect['contact_email'] ?: null,
            $prospect['address'] ?: null,
            $prospect['city'] ?: null,
            $prospect['state'] ?: null,
            $prospect['zip'] ?: null,
            $prospect['website'] ?: null,
            $locationType,
            (int)$prospect['affiliate_id'],
        ]);
        $restaurantId = (int)$pdo->lastInsertId();

        // Link affiliate user as admin
        $pdo->prepare(
            "INSERT INTO user_restaurants (user_id, restaurant_id, role, is_active) VALUES (?, ?, 'admin', 1)"
        )->execute([$affiliateUserId, $restaurantId]);

        // Link restaurant back to prospect
        $pdo->prepare("UPDATE prospects SET restaurant_id = ? WHERE id = ?")->execute([$restaurantId, $prospect['id']]);

        $pdo->commit();
        return $restaurantId;
    } catch (Exception $e) {
        $pdo->rollBack();
        return null;
    }
}
