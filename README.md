# WC Smart Upsell Checkout for WoodMart

Shows manually selected complementary products in a WooCommerce checkout popup, using the store's WooCommerce product card template.

## Configure recommendations

1. Edit a product in the standard WooCommerce product editor.
2. Open **Product data → Linked products** (محصولات مرتبط).
3. Select products in **محصولات مکمل**, arrange their order, and update the product.
4. Repeat for other products. Links are directional; selecting B for A does not select A for B.

For variable products, configure complements on the parent product. Individual variations can be selected as recommendations. Products already in the cart (including variants of a parent in the cart), hidden, unavailable or unpurchasable products are excluded.

Recommendations rank by the number of distinct cart products linking to them, then by the sum of their zero-based positions in those lists (lower first), then product ID for deterministic ties. Quantity does not increase relevance. The popup displays up to the configured limit, with no category or purchase-history fallback. Changes apply on the next recommendation request without a recommendation cache.

Existing purchase-history settings and recommendation caches are no longer used. Define complements before expecting recommendations after upgrading. Popup enablement and display count remain under **WooCommerce → Smart Upsell Checkout**.

## Local checks

```sh
php -l wc-smart-upsell-checkout.php
php tests/recommendations.php
```

These isolated rule tests do not replace integration checks in WordPress. Verify search, ordering, saving/reloading, clearing selections, and checkout product cards in a staging store with the actual WooCommerce/WoodMart versions. The block product editor is not integrated by this field.
