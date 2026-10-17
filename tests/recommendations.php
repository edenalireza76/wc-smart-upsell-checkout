<?php
// Isolated business-rule tests; no WordPress installation required.
define('ABSPATH', __DIR__);
function add_action(...$args) {}
function absint($value) { return abs((int) $value); }
function wp_unslash($value) { return $value; }
function wc_get_product($id) { return $GLOBALS['products'][$id] ?? false; }
function WC() { return $GLOBALS['wc']; }
class WC_Product {
    public $id, $links, $parent = 0, $stock = true, $visible = true, $purchasable = true;
    function __construct($id, $links = []) { $this->id = $id; $this->links = $links; }
    function get_formatted_name() { return 'Product ' . $this->id; }
    function get_id() { return $this->id; }
    function get_parent_id() { return $this->parent; }
    function get_meta(...$args) { return $this->links; }
    function update_meta_data($key, $value) { $this->links = $value; }
    function save() { return $this->id; }
    function exists() { return true; }
    function is_visible() { return $this->visible; }
    function is_in_stock() { return $this->stock; }
    function is_purchasable() { return $this->purchasable; }
}
require dirname(__DIR__) . '/wc-smart-upsell-checkout.php';
$plugin = new WC_Smart_Upsell_Checkout();
$method = new ReflectionMethod($plugin, 'recommend');
if (PHP_VERSION_ID < 80100) { $method->setAccessible(true); }
$GLOBALS['wc'] = (object) ['cart' => new class {
    public $items = [];
    function get_cart() { return $this->items; }
}];
function expect($actual, $expected, $label) {
    if ($actual !== $expected) { throw new RuntimeException($label . ': ' . json_encode($actual)); }
    echo "PASS: $label\n";
}
function recommend_ids($limit = 8) {
    global $method, $plugin;
    return array_map(function ($p) { return $p->get_id(); }, $method->invoke($plugin, $limit));
}
foreach (range(1, 12) as $id) { $GLOBALS['products'][$id] = new WC_Product($id); }
$p = $GLOBALS['products'];
$GLOBALS['wc']->cart->items = [['product_id' => 1], ['product_id' => 2], ['product_id' => 1]];
$p[1]->links = [3, 4, 5, 3];
$p[2]->links = [4, 6];
expect(recommend_ids(), [4, 3, 6, 5], 'Shared complements rank first; duplicates do not inflate relevance');
expect(recommend_ids(2), [4, 3], 'Display limit');
$p[4]->stock = false;
$p[3]->visible = false;
$p[6]->purchasable = false;
expect(recommend_ids(), [5], 'Unavailable products excluded before applying limit');
$p[1]->links = [1, 2, 7];
$p[2]->links = [];
$p[7]->parent = 1;
expect(recommend_ids(), [], 'Cart items and their variations excluded');
$p[1]->links = [];
expect(recommend_ids(), [], 'No manual complements means no fallback suggestions');
$p[1]->links = [8];
expect(recommend_ids(), [8], 'Metadata changes take effect immediately');
$GLOBALS['wc']->cart->items = [['product_id' => 8]];
expect(recommend_ids(), [], 'Relations are directional');
$_POST = ['wc_suc_complements_present' => '1', 'wc_suc_complement_ids' => ['8', '8', '1', '7', '999', ['9'], '9']];
$plugin->save_complements($p[1]);
expect($p[1]->links, [8, 9], 'Save sanitizes, deduplicates and preserves selection order');
$_POST = [];
$plugin->save_complements($p[1]);
expect($p[1]->links, [8, 9], 'Unrelated save flows preserve links');
$_POST = ['wc_suc_complements_present' => '1'];
$plugin->save_complements($p[1]);
expect($p[1]->links, [], 'Removing all selections clears links');
$GLOBALS['wc']->cart->items = [];
expect(recommend_ids(), [], 'Empty cart');
