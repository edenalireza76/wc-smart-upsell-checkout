<?php
/**
 * Plugin Name: WC Smart Upsell Checkout for WoodMart
 * Description: Manually linked complementary-product recommendations for WooCommerce checkout, with native WooCommerce/WoodMart product cards.
 * Version: 6.2.0
 * Author: AliRezaMoghimian
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WC_Smart_Upsell_Checkout')) {

final class WC_Smart_Upsell_Checkout {

    const VERSION    = '6.2.0';
    const OPTION_KEY = 'wc_smart_upsell_settings';
    const NONCE      = 'wc_suc_nonce';

    const COMPLEMENTS_META_KEY = '_wc_suc_complement_ids';

    public function __construct() {

        add_action(
            'before_woocommerce_init',
            [$this, 'declare_block_compatibility']
        );

        add_action(
            'plugins_loaded',
            [$this, 'init']
        );
    }

    public function declare_block_compatibility() {

        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {

            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'cart_checkout_blocks',
                __FILE__,
                true
            );
        }
    }

    public function init() {

        if (!class_exists('WooCommerce')) {
            return;
        }

        add_action(
            'wp_enqueue_scripts',
            [$this, 'assets']
        );

        add_shortcode(
            'wc_smart_upsell_checkout',
            [$this, 'shortcode']
        );

        add_action(
            'wp_ajax_wc_suc_recommendations',
            [$this, 'ajax_recommendations']
        );

        add_action(
            'wp_ajax_nopriv_wc_suc_recommendations',
            [$this, 'ajax_recommendations']
        );

        require_once __DIR__ . '/includes/class-wc-suc-admin.php';
        new WC_SUC_Admin();

        add_filter('option_page_capability_wc_smart_upsell_settings_group', static function () { return 'manage_woocommerce'; });

        add_action('woocommerce_product_options_related', [$this, 'complement_field']);
        add_action('woocommerce_admin_process_product_object', [$this, 'save_complements']);

        add_action(
            'admin_menu',
            [$this, 'admin_menu']
        );

        add_action(
            'admin_init',
            [$this, 'register_settings']
        );
    }

    private function settings() {

        return wp_parse_args(
            (array) get_option(self::OPTION_KEY, []),
            [
                'enabled'  => 'yes',
                'title'    => 'پیشنهادهای تکمیلی برای خرید شما',
                'subtitle' => 'برای تکمیل خریدتان، این محصولات هم ممکن است به کارتان بیاید.',
                'count'    => 4,
            ]
        );
    }

    public function assets() {

        if (
            is_admin() ||
            !function_exists('is_checkout') ||
            !is_checkout()
        ) {
            return;
        }

        if (
            function_exists('is_order_received_page') &&
            is_order_received_page()
        ) {
            return;
        }

        $settings = $this->settings();

        if ($settings['enabled'] !== 'yes') {
            return;
        }

        wp_enqueue_style(
            'wc-suc',
            plugins_url(
                'assets/wc-suc.min.css',
                __FILE__
            ),
            [],
            self::VERSION
        );

        wp_enqueue_script(
            'wc-suc',
            plugins_url(
                'assets/wc-suc.min.js',
                __FILE__
            ),
            [],
            self::VERSION,
            true
        );

        if (function_exists('wp_script_add_data')) {

            wp_script_add_data(
                'wc-suc',
                'strategy',
                'defer'
            );
        }

        wp_localize_script(
            'wc-suc',
            'WCSUC',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce(self::NONCE),
                'count'   => max(
                    1,
                    min(
                        8,
                        absint($settings['count'])
                    )
                ),
                'delay'   => 650,
                'title'   => $settings['title'],
                'subtitle' => $settings['subtitle'],
            ]
        );
    }

    /**
     * Optional manual shortcode fallback.
     */
    public function shortcode() {

        return '<span class="wc-suc-shortcode-anchor" data-wc-suc-anchor="1" aria-hidden="true"></span>';
    }

    public function admin_menu() {

        add_submenu_page(
            'woocommerce',
            'مدیریت محصولات مکمل',
            'محصولات مکمل',
            'manage_woocommerce',
            'wc-smart-upsell-checkout',
            [$this, 'settings_page']
        );
    }

    public function register_settings() {

        register_setting(
            'wc_smart_upsell_settings_group',
            self::OPTION_KEY,
            [
                'sanitize_callback' => [
                    $this,
                    'sanitize_settings'
                ]
            ]
        );
    }

    public function sanitize_settings($input) {

        $input = is_array($input)
            ? $input
            : [];

        $count = max(
            1,
            min(
                8,
                absint($input['count'] ?? 4)
            )
        );

        return [
            'enabled' => (
                ($input['enabled'] ?? 'no') === 'yes'
            )
                ? 'yes'
                : 'no',

            'title' => sanitize_text_field(
                $input['title']
                ?? 'پیشنهادهای تکمیلی برای خرید شما'
            ),

            'subtitle' => sanitize_text_field(
                $input['subtitle']
                ?? 'برای تکمیل خریدتان، این محصولات هم ممکن است به کارتان بیاید.'
            ),

            'count' => $count,

        ];
    }

    public function settings_page() {

        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $settings = $this->settings();

        $key = esc_attr(self::OPTION_KEY);
        ?>

        <div class="wrap suc-admin">

            <h1>مدیریت محصولات مکمل</h1>
            <p class="suc-intro">مکمل‌های مناسب را برای هر محصول انتخاب کنید تا پیشنهادهای تسویه‌حساب دقیق‌تر باشند.</p>
            <?php settings_errors(); WC_SUC_Admin::page(); ?>
            <details class="suc-panel suc-settings">
            <summary>تنظیمات نمایش در تسویه‌حساب</summary>

            <form method="post" action="options.php" novalidate>

                <?php
                settings_fields(
                    'wc_smart_upsell_settings_group'
                );
                ?>

                <table class="form-table">

                    <tr>

                        <th><label for="suc-enabled">فعال بودن</label></th>

                        <td>

                            <select
                                id="suc-enabled" name="<?php echo $key; ?>[enabled]"
                            >

                                <option
                                    value="yes"
                                    <?php selected(
                                        $settings['enabled'],
                                        'yes'
                                    ); ?>
                                >
                                    بله
                                </option>

                                <option
                                    value="no"
                                    <?php selected(
                                        $settings['enabled'],
                                        'no'
                                    ); ?>
                                >
                                    خیر
                                </option>

                            </select>

                        </td>

                    </tr>

                    <tr>

                        <th><label for="suc-title">عنوان</label></th>

                        <td>

                            <input
                                class="regular-text"
                                type="text"
                                id="suc-title" name="<?php echo $key; ?>[title]"
                                value="<?php echo esc_attr($settings['title']); ?>"
                            >

                        </td>

                    </tr>

                    <tr>

                        <th><label for="suc-subtitle">توضیح</label></th>

                        <td>

                            <input
                                class="regular-text"
                                type="text"
                                id="suc-subtitle" name="<?php echo $key; ?>[subtitle]"
                                value="<?php echo esc_attr($settings['subtitle']); ?>"
                            >

                        </td>

                    </tr>

                    <tr>

                        <th><label for="suc-count">تعداد محصولات</label></th>

                        <td>

                            <select
                                id="suc-count" name="<?php echo $key; ?>[count]"
                            >

                                <?php
                                for ($i = 1; $i <= 8; $i++) :
                                ?>

                                    <option
                                        value="<?php echo $i; ?>"
                                        <?php selected(
                                            (int) $settings['count'],
                                            $i
                                        ); ?>
                                    >
                                        <?php echo $i; ?>
                                    </option>

                                <?php endfor; ?>

                            </select>

                        </td>

                    </tr>



                </table>

                <?php submit_button('ذخیره تنظیمات'); ?>

            </form>
            </details>

        </div>

        <?php
    }

    public function ajax_recommendations() {

        if (
            !check_ajax_referer(
                self::NONCE,
                'nonce',
                false
            )
        ) {

            wp_send_json_error(
                [
                    'message' => 'invalid_nonce'
                ],
                403
            );
        }

        if (
            !function_exists('WC') ||
            !WC()->cart ||
            WC()->cart->is_empty()
        ) {

            wp_send_json_error(
                [
                    'message' => 'empty_cart'
                ],
                200
            );
        }

        $settings = $this->settings();

        if ($settings['enabled'] !== 'yes') {

            wp_send_json_error(
                [
                    'message' => 'disabled'
                ],
                200
            );
        }

        $limit = max(
            1,
            min(
                8,
                absint($settings['count'])
            )
        );

        $products = $this->recommend(
            $limit
        );

        if (!$products) {

            wp_send_json_error(
                [
                    'message' => 'no_recommendations'
                ],
                200
            );
        }

        $html = $this->render_products($products);

        if ($html === '') {

            wp_send_json_error(
                [
                    'message' => 'no_renderable_products'
                ],
                200
            );
        }

        wp_send_json_success(
            [
                'html' => $html
            ]
        );
    }

    private function render_products($products) {

        $valid = [];

        foreach ($products as $product) {

            if (!$product instanceof WC_Product) {
                continue;
            }

            if (
                !$product->exists() ||
                !$product->is_visible() ||
                !$product->is_in_stock() ||
                !$product->is_purchasable()
            ) {
                continue;
            }

            $valid[] = $product;
        }

        if (!$valid) {
            return '';
        }

        $columns = min(
            4,
            count($valid)
        );

        $old_post = $GLOBALS['post'] ?? null;
        $old_product = $GLOBALS['product'] ?? null;

        ob_start();

        if (function_exists('wc_set_loop_prop')) {

            wc_set_loop_prop(
                'columns',
                $columns
            );

            wc_set_loop_prop(
                'name',
                'wc-smart-upsell'
            );
        }

        echo '<ul class="products columns-' .
            esc_attr($columns) .
            '">';

        foreach ($valid as $product) {

            $post = get_post(
                $product->get_id()
            );

            if (!$post) {
                continue;
            }

            $GLOBALS['product'] = $product;
            $GLOBALS['post'] = $post;

            setup_postdata($post);

            /*
             * مهم:
             * کارت اصلی WooCommerce/WoodMart استفاده می‌شود.
             */
            wc_get_template_part(
                'content',
                'product'
            );
        }

        echo '</ul>';

        wp_reset_postdata();

        $GLOBALS['post'] = $old_post;
        $GLOBALS['product'] = $old_product;

        $html = trim(
            ob_get_clean()
        );

        return preg_match(
            '/<li\b[^>]*class=[^>]*product/i',
            $html
        )
            ? $html
            : '';
    }

    private function cart_ids() {

        $ids = [];

        if (
            !function_exists('WC') ||
            !WC()->cart
        ) {
            return $ids;
        }

        foreach (
            WC()->cart->get_cart()
            as $item
        ) {

            foreach (
                ['product_id', 'variation_id']
                as $key
            ) {

                $id = absint(
                    $item[$key] ?? 0
                );

                if ($id) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(
            array_unique($ids)
        );
    }

    private function source_ids() {

        $ids = [];

        if (
            !function_exists('WC') ||
            !WC()->cart
        ) {
            return $ids;
        }

        foreach (
            WC()->cart->get_cart()
            as $item
        ) {

            $id = absint(
                $item['product_id'] ?? 0
            );

            if ($id) {
                $ids[] = $id;
            }
        }

        return array_values(
            array_unique($ids)
        );
    }

    /**
     * Uses WooCommerce's existing searchable linked-product control.
     * Relations are directional and saved with the product's normal update action.
     */
    public function complement_field() {
        global $product_object;

        if (!$product_object instanceof WC_Product) {
            return;
        }

        $ids = (array) $product_object->get_meta(self::COMPLEMENTS_META_KEY, true);
        ?>
        <p class="form-field">
            <label for="wc_suc_complement_ids">محصولات مکمل</label>
            <select class="wc-product-search" multiple="multiple"
                id="wc_suc_complement_ids" name="wc_suc_complement_ids[]"
                style="width: 50%;"
                data-sortable="true"
                data-placeholder="جست‌وجوی محصول مکمل…"
                data-action="woocommerce_json_search_products_and_variations"
                data-exclude="<?php echo esc_attr($product_object->get_id()); ?>"
                aria-describedby="wc-suc-complements-help">
                <?php foreach ($ids as $id) :
                    $product = wc_get_product(absint($id));
                    if (!$product) {
                        continue;
                    }
                    ?>
                    <option value="<?php echo esc_attr($product->get_id()); ?>" selected="selected"><?php echo esc_html($product->get_formatted_name()); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="wc_suc_complements_present" value="1">
            <span class="description" id="wc-suc-complements-help">محصولاتی را انتخاب کنید که همراه این محصول کاربرد دارند، نه مدل‌های جایگزین آن. ترتیب انتخاب، اولویت نمایش در امتیاز برابر است. رابطه یک‌طرفه است.</span>
        </p>
        <?php
    }

    public function save_complements($product) {
        // WooCommerce verifies the product edit nonce and permissions before this hook.
        // Skip other save flows that do not include this field (for example quick edit).
        if (!isset($_POST['wc_suc_complements_present'])) {
            return;
        }

        $input = isset($_POST['wc_suc_complement_ids']) && is_array($_POST['wc_suc_complement_ids'])
            ? wp_unslash($_POST['wc_suc_complement_ids'])
            : [];
        $ids = [];

        foreach ($input as $value) {
            if (!is_scalar($value)) {
                continue;
            }
            $id = absint($value);
            $candidate = $id ? wc_get_product($id) : false;
            if (!$candidate || $id === $product->get_id() || $candidate->get_parent_id() === $product->get_id()) {
                continue;
            }
            $ids[] = $id;
        }

        $product->update_meta_data(self::COMPLEMENTS_META_KEY, array_values(array_unique($ids)));
    }

    private function recommend($limit = 4) {
        $cart_ids = $this->cart_ids();
        $candidates = [];

        foreach ($this->source_ids() as $source_id) {
            $source = wc_get_product($source_id);
            if (!$source) {
                continue;
            }

            $ids = array_values(array_unique(array_filter(array_map(
                'absint', (array) $source->get_meta(self::COMPLEMENTS_META_KEY, true)
            ))));

            foreach ($ids as $position => $id) {
                if (!isset($candidates[$id])) {
                    $candidates[$id] = ['connections' => 0, 'position' => 0];
                }
                $candidates[$id]['connections']++;
                $candidates[$id]['position'] += $position;
            }
        }

        uksort($candidates, static function ($a, $b) use ($candidates) {
            $first = $candidates[$a];
            $second = $candidates[$b];
            return ($second['connections'] <=> $first['connections'])
                ?: ($first['position'] <=> $second['position'])
                ?: ($a <=> $b);
        });

        $result = [];
        foreach ($candidates as $id => $rank) {
            $product = wc_get_product($id);
            if (!$product || !$product->exists() || !$product->is_visible()
                || !$product->is_in_stock() || !$product->is_purchasable()
                || in_array($id, $cart_ids, true)
                || ($product->get_parent_id() && in_array($product->get_parent_id(), $cart_ids, true))) {
                continue;
            }
            $result[] = $product;
            if (count($result) >= $limit) {
                break;
            }
        }

        // Read current product metadata each time so edits take effect immediately.
        return $result;
    }
}

new WC_Smart_Upsell_Checkout();

}
