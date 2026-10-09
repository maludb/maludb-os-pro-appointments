<?php
/**
 * Affiliate Products — read-only catalog of available products with features
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';

requireAuth();

$pdo = db();

// Load active products with their features
$products = $pdo->query(
    "SELECT * FROM products WHERE is_active = 1 ORDER BY sort_order, name"
)->fetchAll();

$productIds = array_column($products, 'id');
$features = [];
if ($productIds) {
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $fStmt = $pdo->prepare(
        "SELECT * FROM product_features WHERE product_id IN ($placeholders) ORDER BY sort_order, id"
    );
    $fStmt->execute($productIds);
    foreach ($fStmt->fetchAll() as $f) {
        $features[$f['product_id']][] = $f;
    }
}

$billingLabels = [
    'monthly' => '/mo',
    'yearly' => '/yr',
    'one_time' => ' one-time',
];
?>

<div class="main-content p-4" id="affiliate-products-page">
    <div class="d-flex justify-content-between align-items-center mb-4" id="affiliate-products-header">
        <div id="affiliate-products-title-wrap">
            <h4 class="mb-0 fw-bold" id="affiliate-products-title"><i class="feather-package me-2"></i>Products</h4>
            <small class="text-muted" id="affiliate-products-subtitle">Available products to pitch to your prospects</small>
        </div>
    </div>

    <?php if (empty($products)): ?>
    <div class="text-center text-muted py-5" id="affiliate-products-empty">
        <i class="feather-package d-block mb-2" style="font-size:2rem;"></i>
        <p>No products available at this time.</p>
    </div>
    <?php else: ?>
    <?php foreach ($products as $prod): ?>
    <div class="card border-0 shadow-sm mb-4" id="affiliate-product-card-<?php echo (int)$prod['id']; ?>">
        <div class="card-body" id="affiliate-product-body-<?php echo (int)$prod['id']; ?>">
            <div class="d-flex justify-content-between align-items-start mb-3" id="affiliate-product-top-<?php echo (int)$prod['id']; ?>">
                <div id="affiliate-product-info-<?php echo (int)$prod['id']; ?>">
                    <h5 class="fw-bold mb-1" id="affiliate-product-name-<?php echo (int)$prod['id']; ?>">
                        <?php echo htmlspecialchars($prod['name']); ?>
                    </h5>
                    <?php if ($prod['description']): ?>
                    <p class="text-muted mb-0" id="affiliate-product-desc-<?php echo (int)$prod['id']; ?>">
                        <?php echo htmlspecialchars($prod['description']); ?>
                    </p>
                    <?php endif; ?>
                </div>
                <div class="text-end flex-shrink-0 ms-3" id="affiliate-product-pricing-<?php echo (int)$prod['id']; ?>">
                    <div id="affiliate-product-price-<?php echo (int)$prod['id']; ?>">
                        <span class="fs-4 fw-bold text-primary">$<?php echo number_format($prod['price'], 2); ?></span>
                        <span class="text-muted"><?php echo $billingLabels[$prod['billing_interval']] ?? ''; ?></span>
                    </div>
                    <div id="affiliate-product-aff-price-<?php echo (int)$prod['id']; ?>">
                        <small class="text-success fw-semibold">Affiliate: $<?php echo number_format($prod['affiliate_price'], 2); ?><?php echo $billingLabels[$prod['billing_interval']] ?? ''; ?></small>
                    </div>
                </div>
            </div>

            <?php $prodFeatures = $features[$prod['id']] ?? []; ?>
            <?php if (!empty($prodFeatures)): ?>
            <hr class="my-3">
            <div class="row g-2" id="affiliate-product-features-<?php echo (int)$prod['id']; ?>">
                <?php foreach ($prodFeatures as $f): ?>
                <div class="col-md-6 col-lg-4" id="affiliate-feature-<?php echo (int)$f['id']; ?>">
                    <div class="d-flex align-items-center">
                        <i class="feather-check-circle text-success me-2" style="font-size:16px;"></i>
                        <span>
                            <?php echo htmlspecialchars($f['feature_label']); ?>
                            <?php if ($f['feature_value']): ?>
                            <small class="text-muted ms-1">(<?php echo htmlspecialchars($f['feature_value']); ?>)</small>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
