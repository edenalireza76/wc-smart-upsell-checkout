<?php
/**
 * Plugin Name: WC Smart Upsell Checkout for WoodMart
 * Description: Generic, lightweight complementary-product recommendations for WooCommerce checkout. Uses purchase history first and product context as fallback, with native WooCommerce/WoodMart product cards.
 * Version: 6.0.0
 * Author: AliRezaMoghimian
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WC_Smart_Upsell_Checkout')) {

final class WC_Smart_Upsell_Checkout {

    const VERSION    = '6.0.0';
    const OPTION_KEY = 'wc_smart_upsell_settings';
    const NONCE      = 'wc_suc_nonce';

    private static $context_cache = [];
    private static $history_cache = null;

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
                'history'  => 'yes',
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
            'Smart Upsell Checkout',
            'Smart Upsell Checkout',
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

            'history' => (
                ($input['history'] ?? 'yes') === 'yes'
            )
                ? 'yes'
                : 'no',
        ];
    }

    public function settings_page() {

        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $settings = $this->settings();

        $key = esc_attr(self::OPTION_KEY);
        ?>

        <div class="wrap">

            <h1>Smart Upsell Checkout</h1>

            <p>
                پیشنهادها برای همه نوع محصول به‌صورت خودکار ساخته می‌شوند.
                ابتدا از خریدهای واقعی هم‌زمان استفاده می‌شود و در صورت نبود
                داده کافی، دسته‌بندی، ویژگی، تگ و عنوان محصول به‌عنوان fallback
                استفاده می‌شوند.
            </p>

            <form method="post" action="options.php">

                <?php
                settings_fields(
                    'wc_smart_upsell_settings_group'
                );
                ?>

                <table class="form-table">

                    <tr>

                        <th>فعال بودن</th>

                        <td>

                            <select
                                name="<?php echo $key; ?>[enabled]"
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

                        <th>عنوان</th>

                        <td>

                            <input
                                class="regular-text"
                                type="text"
                                name="<?php echo $key; ?>[title]"
                                value="<?php echo esc_attr($settings['title']); ?>"
                            >

                        </td>

                    </tr>

                    <tr>

                        <th>توضیح</th>

                        <td>

                            <input
                                class="regular-text"
                                type="text"
                                name="<?php echo $key; ?>[subtitle]"
                                value="<?php echo esc_attr($settings['subtitle']); ?>"
                            >

                        </td>

                    </tr>

                    <tr>

                        <th>تعداد محصولات</th>

                        <td>

                            <select
                                name="<?php echo $key; ?>[count]"
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

                    <tr>

                        <th>سابقه خرید</th>

                        <td>

                            <label>

                                <input
                                    type="checkbox"
                                    name="<?php echo $key; ?>[history]"
                                    value="yes"
                                    <?php checked(
                                        $settings['history'],
                                        'yes'
                                    ); ?>
                                >

                                استفاده از محصولات واقعاً هم‌خریدشده
                                در سفارش‌های قبلی

                            </label>

                        </td>

                    </tr>

                </table>

                <?php submit_button('ذخیره تنظیمات'); ?>

            </form>

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
            $limit,
            $settings['history'] === 'yes'
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

    private function recommend(
        $limit = 4,
        $use_history = true
    ) {

        $cart_ids = $this->cart_ids();
        $source_ids = $this->source_ids();

        if (!$source_ids) {
            return [];
        }

        sort($cart_ids);
        sort($source_ids);

        $cache_key =
            'wc_suc_v6_' .
            md5(
                wp_json_encode(
                    [
                        $source_ids,
                        $cart_ids,
                        $limit,
                        $use_history ? 1 : 0
                    ]
                )
            );

        $cached = get_transient(
            $cache_key
        );

        if (
            is_array($cached) &&
            $cached
        ) {

            $out = [];

            foreach ($cached as $id) {

                $product = wc_get_product(
                    $id
                );

                if (
                    $product &&
                    !in_array(
                        $product->get_id(),
                        $cart_ids,
                        true
                    ) &&
                    $product->exists() &&
                    $product->is_visible() &&
                    $product->is_in_stock() &&
                    $product->is_purchasable()
                ) {

                    $out[] = $product;
                }
            }

            if ($out) {
                return array_slice(
                    $out,
                    0,
                    $limit
                );
            }
        }

        $contexts = [];

        foreach ($source_ids as $id) {

            $contexts[$id] =
                $this->context($id);
        }

        $scores = [];

        /*
         * سیگنال اصلی:
         * محصولات واقعاً هم‌خریدشده در سفارش‌های قبلی.
         */
        if ($use_history) {

            $history =
                $this->co_purchase_matrix(
                    $source_ids
                );

            foreach (
                $history as $source_id => $items
            ) {

                foreach (
                    $items as $candidate_id => $count
                ) {

                    $candidate_id =
                        absint($candidate_id);

                    if (
                        !$candidate_id ||
                        in_array(
                            $candidate_id,
                            $cart_ids,
                            true
                        )
                    ) {
                        continue;
                    }

                    $scores[$candidate_id] =
                        ($scores[$candidate_id] ?? 0)
                        + 80
                        + min(
                            120,
                            $count * 15
                        );
                }
            }
        }

        /*
         * Cold Start:
         * دسته‌بندی + عنوان + ویژگی + تگ.
         */
        $candidate_ids = [];

        foreach ($contexts as $ctx) {

            $candidate_ids = array_merge(
                $candidate_ids,
                $this->cold_start_candidates(
                    $ctx
                )
            );
        }

        $candidate_ids =
            array_values(
                array_unique(
                    array_filter(
                        array_map(
                            'absint',
                            $candidate_ids
                        )
                    )
                )
            );

        foreach ($candidate_ids as $candidate_id) {

            if (
                in_array(
                    $candidate_id,
                    $cart_ids,
                    true
                )
            ) {
                continue;
            }

            $candidate =
                wc_get_product(
                    $candidate_id
                );

            if (
                !$candidate ||
                !$candidate->exists() ||
                !$candidate->is_visible() ||
                !$candidate->is_in_stock() ||
                !$candidate->is_purchasable()
            ) {
                continue;
            }

            $candidate_context =
                $this->context(
                    $candidate_id
                );

            $best = -9999;

            foreach (
                $contexts as $source_context
            ) {

                $score =
                    $this->complement_score(
                        $source_context,
                        $candidate_context
                    );

                if ($score > $best) {
                    $best = $score;
                }
            }

            if ($best < 20) {
                continue;
            }

            $scores[$candidate_id] =
                ($scores[$candidate_id] ?? 0)
                + $best;
        }

        if (!$scores) {
            return [];
        }

        arsort(
            $scores,
            SORT_NUMERIC
        );

        $result = [];
        $families = [];

        foreach (
            $scores as $id => $score
        ) {

            if (
                count($result) >= $limit
            ) {
                break;
            }

            $product =
                wc_get_product($id);

            if (!$product) {
                continue;
            }

            $ctx =
                $this->context($id);

            $family =
                $this->family_key($ctx);

            /*
             * جلوگیری از نمایش چند محصول
             * از یک خانواده مشابه.
             */
            if (
                $family !== '' &&
                isset($families[$family])
            ) {
                continue;
            }

            $result[] = $product;

            if ($family !== '') {
                $families[$family] = true;
            }
        }

        $ids = array_map(
            static function ($product) {
                return $product->get_id();
            },
            $result
        );

        if ($ids) {

            set_transient(
                $cache_key,
                $ids,
                2 * HOUR_IN_SECONDS
            );
        }

        return $result;
    }

    /**
     * ساخت ماتریس خرید مشترک.
     *
     * فقط تعداد محدودی سفارش اخیر بررسی می‌شود
     * تا فشار روی Checkout پایین بماند.
     */
    private function co_purchase_matrix($source_ids) {

        $source_ids =
            array_values(
                array_unique(
                    array_map(
                        'absint',
                        (array) $source_ids
                    )
                )
            );

        if (
            !$source_ids ||
            !function_exists('wc_get_orders')
        ) {
            return [];
        }

        $cache_key =
            'wc_suc_hist_' .
            md5(
                wp_json_encode(
                    $source_ids
                )
            );

        $cached =
            get_transient(
                $cache_key
            );

        if (is_array($cached)) {
            return $cached;
        }

        $matrix = [];

        foreach ($source_ids as $id) {
            $matrix[$id] = [];
        }

        /*
         * فقط 30 سفارش اخیر در یک سال گذشته.
         */
        $orders = wc_get_orders(
            [
                'status' => [
                    'wc-processing',
                    'wc-completed',
                    'wc-on-hold'
                ],

                'limit' => 30,

                'orderby' => 'date',

                'order' => 'DESC',

                'return' => 'objects',

                'date_created' =>
                    '>' .
                    (
                        time() -
                        YEAR_IN_SECONDS
                    ),
            ]
        );

        foreach (
            (array) $orders
            as $order
        ) {

            if (
                !$order instanceof WC_Order
            ) {
                continue;
            }

            $products_in_order = [];

            foreach (
                $order->get_items(
                    'line_item'
                ) as $item
            ) {

                $product_id =
                    absint(
                        $item->get_product_id()
                    );

                if ($product_id) {
                    $products_in_order[
                        $product_id
                    ] = true;
                }

                $variation_id =
                    absint(
                        $item->get_variation_id()
                    );

                if ($variation_id) {
                    $products_in_order[
                        $variation_id
                    ] = true;
                }
            }

            if (!$products_in_order) {
                continue;
            }

            $products_in_order =
                array_keys(
                    $products_in_order
                );

            foreach (
                $source_ids as $source_id
            ) {

                if (
                    !in_array(
                        $source_id,
                        $products_in_order,
                        true
                    )
                ) {
                    continue;
                }

                foreach (
                    $products_in_order
                    as $candidate_id
                ) {

                    if (
                        $candidate_id ===
                        $source_id
                    ) {
                        continue;
                    }

                    if (
                        in_array(
                            $candidate_id,
                            $source_ids,
                            true
                        )
                    ) {
                        continue;
                    }

                    $matrix[$source_id][
                        $candidate_id
                    ] =
                        (
                            $matrix[$source_id][
                                $candidate_id
                            ] ?? 0
                        ) + 1;
                }
            }
        }

        foreach (
            $matrix as &$items
        ) {

            arsort(
                $items,
                SORT_NUMERIC
            );

            $items =
                array_slice(
                    $items,
                    0,
                    30,
                    true
                );
        }

        unset($items);

        set_transient(
            $cache_key,
            $matrix,
            2 * HOUR_IN_SECONDS
        );

        return $matrix;
    }

    private function cold_start_candidates($ctx) {

        $ids = [];

        $cats =
            $ctx['categories'];

        if (!$cats) {
            return $ids;
        }

        $leaf =
            $ctx['leaf_categories'];

        $parents =
            $ctx['parent_categories'];

        $tax = [];

        if ($parents) {

            $tax[] = [
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => $parents,
                'operator' => 'IN',
            ];
        }

        if ($leaf) {

            $tax[] = [
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => $leaf,
                'operator' => 'IN',
            ];
        }

        if ($tax) {

            $tax_query =
                count($tax) > 1
                    ? array_merge(
                        ['relation' => 'OR'],
                        $tax
                    )
                    : $tax;

            $query =
                new WP_Query(
                    [
                        'post_type' => 'product',

                        'post_status' => 'publish',

                        'posts_per_page' => 70,

                        'fields' => 'ids',

                        'no_found_rows' => true,

                        'ignore_sticky_posts' => true,

                        'update_post_meta_cache' => false,

                        'update_post_term_cache' => false,

                        'tax_query' => $tax_query,
                    ]
                );

            if (!empty($query->posts)) {

                $ids =
                    $query->posts;
            }

            wp_reset_postdata();
        }

        /*
         * فقط چند Token معنادار.
         */
        $tokens =
            array_slice(
                $this->useful_tokens(
                    $ctx['tokens']
                ),
                0,
                3
            );

        foreach ($tokens as $token) {

            $query =
                new WP_Query(
                    [
                        'post_type' => 'product',

                        'post_status' => 'publish',

                        'posts_per_page' => 15,

                        'fields' => 'ids',

                        's' => $token,

                        'no_found_rows' => true,

                        'ignore_sticky_posts' => true,

                        'update_post_meta_cache' => false,

                        'update_post_term_cache' => false,
                    ]
                );

            if (!empty($query->posts)) {

                $ids =
                    array_merge(
                        $ids,
                        $query->posts
                    );
            }

            wp_reset_postdata();
        }

        return $ids;
    }

    private function complement_score($a, $b) {

        if (!$a || !$b) {
            return -1000;
        }

        $score = 0;

        $shared_cat =
            count(
                array_intersect(
                    $a['categories'],
                    $b['categories']
                )
            );

        $shared_parent =
            count(
                array_intersect(
                    $a['parent_categories'],
                    $b['parent_categories']
                )
            );

        $shared_attr =
            count(
                array_intersect(
                    $a['attributes'],
                    $b['attributes']
                )
            );

        $shared_tokens =
            count(
                array_intersect(
                    $a['tokens'],
                    $b['tokens']
                )
            );

        if ($shared_parent) {

            $score += min(
                30,
                $shared_parent * 15
            );
        }

        if ($shared_cat) {

            $score += min(
                16,
                $shared_cat * 8
            );
        }

        if ($shared_attr) {

            $score += min(
                24,
                $shared_attr * 6
            );
        }

        if ($shared_tokens) {

            $score += min(
                18,
                $shared_tokens * 3
            );
        }

        if (
            $a['leaf_categories'] &&
            $b['leaf_categories'] &&
            !array_intersect(
                $a['leaf_categories'],
                $b['leaf_categories']
            )
        ) {

            $score += 25;

        } elseif (
            array_intersect(
                $a['leaf_categories'],
                $b['leaf_categories']
            )
        ) {

            $score -= 18;
        }

        if (
            $this->same_family(
                $a,
                $b
            )
        ) {

            $score -= 65;

        } else {

            $score += 18;
        }

        if (
            $a['brand'] !== '' &&
            $b['brand'] !== '' &&
            $a['brand'] === $b['brand']
        ) {

            $score += 4;
        }

        return $score;
    }

    private function same_family($a, $b) {

        return
            !empty($a['leaf_categories']) &&
            !empty($b['leaf_categories']) &&
            (bool) array_intersect(
                $a['leaf_categories'],
                $b['leaf_categories']
            );
    }

    private function family_key($ctx) {

        if (
            !empty(
                $ctx['leaf_categories']
            )
        ) {

            $leaf =
                $ctx['leaf_categories'];

            sort($leaf);

            return
                'cat:' .
                implode(
                    ',',
                    array_slice(
                        $leaf,
                        0,
                        2
                    )
                );
        }

        return '';
    }

    private function context($id) {

        $id = absint($id);

        if (
            isset(
                self::$context_cache[$id]
            )
        ) {

            return self::$context_cache[$id];
        }

        $product =
            wc_get_product($id);

        if (!$product) {

            return
                self::$context_cache[$id] = [
                    'categories'        => [],
                    'leaf_categories'   => [],
                    'parent_categories' => [],
                    'attributes'        => [],
                    'tokens'            => [],
                    'brand'             => '',
                ];
        }

        $texts = [
            $product->get_name()
        ];

        $cats =
            wp_get_post_terms(
                $id,
                'product_cat',
                [
                    'fields' => 'all'
                ]
            );

        if (is_wp_error($cats)) {
            $cats = [];
        }

        $cat_ids = [];
        $leaf = [];
        $parents = [];

        foreach ($cats as $cat) {

            $cat_ids[] =
                (int) $cat->term_id;

            $texts[] =
                $cat->name;

            $children =
                get_term_children(
                    $cat->term_id,
                    'product_cat'
                );

            if (
                !is_wp_error($children) &&
                empty($children)
            ) {

                $leaf[] =
                    (int) $cat->term_id;
            }

            foreach (
                (array) get_ancestors(
                    $cat->term_id,
                    'product_cat'
                ) as $ancestor_id
            ) {

                $ancestor_id =
                    (int) $ancestor_id;

                $parents[] =
                    $ancestor_id;

                $cat_ids[] =
                    $ancestor_id;

                $term =
                    get_term(
                        $ancestor_id,
                        'product_cat'
                    );

                if (
                    $term &&
                    !is_wp_error($term)
                ) {

                    $texts[] =
                        $term->name;
                }
            }
        }

        $tags =
            wp_get_post_terms(
                $id,
                'product_tag',
                [
                    'fields' => 'names'
                ]
            );

        if (!is_wp_error($tags)) {

            $texts =
                array_merge(
                    $texts,
                    $tags
                );
        }

        $attrs = [];

        foreach (
            $product->get_attributes()
            as $attribute
        ) {

            if (
                !$attribute
                instanceof WC_Product_Attribute
            ) {
                continue;
            }

            if (
                $attribute->is_taxonomy()
            ) {

                $terms =
                    wp_get_post_terms(
                        $id,
                        $attribute->get_name(),
                        [
                            'fields' => 'names'
                        ]
                    );

                if (!is_wp_error($terms)) {

                    foreach (
                        $terms as $term_name
                    ) {

                        $attrs[] =
                            $term_name;

                        $texts[] =
                            $term_name;
                    }
                }

            } else {

                foreach (
                    (array)
                    $attribute->get_options()
                    as $value
                ) {

                    $attrs[] =
                        $value;

                    $texts[] =
                        $value;
                }
            }
        }

        if (!$leaf) {
            $leaf = $cat_ids;
        }

        $brand = '';

        foreach (
            [
                'pa_brand',
                'pa_brands',
                'brand'
            ] as $taxonomy
        ) {

            $names =
                wp_get_post_terms(
                    $id,
                    $taxonomy,
                    [
                        'fields' => 'names'
                    ]
                );

            if (
                !is_wp_error($names) &&
                $names
            ) {

                $brand =
                    $this->norm(
                        $names[0]
                    );

                break;
            }
        }

        if ($brand === '') {

            $brand =
                $this->norm(
                    $product->get_attribute(
                        'brand'
                    )
                );
        }

        $result = [

            'categories' =>
                array_values(
                    array_unique(
                        array_map(
                            'absint',
                            $cat_ids
                        )
                    )
                ),

            'leaf_categories' =>
                array_values(
                    array_unique(
                        array_map(
                            'absint',
                            $leaf
                        )
                    )
                ),

            'parent_categories' =>
                array_values(
                    array_unique(
                        array_map(
                            'absint',
                            $parents
                        )
                    )
                ),

            'attributes' =>
                $this->normalize(
                    $attrs
                ),

            'tokens' =>
                $this->tokens(
                    $this->norm(
                        implode(
                            ' ',
                            $texts
                        )
                    )
                ),

            'brand' => $brand,
        ];

        self::$context_cache[$id] =
            $result;

        return $result;
    }

    private function useful_tokens($tokens) {

        $out = [];

        foreach (
            (array) $tokens
            as $token
        ) {

            if (
                $this->strlen($token) >= 3
            ) {

                $out[] =
                    $token;
            }
        }

        return array_values(
            array_unique($out)
        );
    }

    private function tokens($text) {

        $text =
            preg_replace(
                '/[^\p{L}\p{N}]+/u',
                ' ',
                $text
            );

        $parts =
            preg_split(
                '/\s+/u',
                $text,
                -1,
                PREG_SPLIT_NO_EMPTY
            );

        $stop = [

            'برای',
            'با',
            'و',
            'از',
            'در',
            'به',
            'این',
            'آن',
            'یک',
            'های',
            'ها',
            'روی',
            'مخصوص',
            'مناسب',
            'قیمت',
            'خرید',
            'فروش',
            'اصل',
            'اصلی',
            'اورجینال',
            'شرکتی',
            'جدید',
            'جدیدترین',
            'محصول',
            'کالا',
            'مدل',
            'سایز',
            'رنگ',
            'عدد',
            'است',
            'را',
            'که',
            'یا',
            'هم',
            'بر',
            'تا',
            'هر'
        ];

        $out = [];

        foreach (
            (array) $parts
            as $part
        ) {

            if (
                in_array(
                    $part,
                    $stop,
                    true
                )
            ) {
                continue;
            }

            if (
                $this->strlen($part) < 2
            ) {
                continue;
            }

            $out[] =
                $part;
        }

        return array_values(
            array_unique($out)
        );
    }

    private function normalize($values) {

        $out = [];

        foreach (
            (array) $values
            as $value
        ) {

            $value =
                $this->norm($value);

            if ($value !== '') {

                $out[] =
                    $value;
            }
        }

        return array_values(
            array_unique($out)
        );
    }

    private function strlen($text) {

        return function_exists(
            'mb_strlen'
        )
            ? mb_strlen(
                $text,
                'UTF-8'
            )
            : strlen($text);
    }

    private function strtolower($text) {

        return function_exists(
            'mb_strtolower'
        )
            ? mb_strtolower(
                $text,
                'UTF-8'
            )
            : strtolower($text);
    }

    private function norm($text) {

        $text =
            wp_strip_all_tags(
                (string) $text
            );

        $text =
            str_replace(
                [
                    'ي',
                    'ى',
                    'ئ',
                    'ك',
                    'ة',
                    'ۀ',
                    'ؤ'
                ],
                [
                    'ی',
                    'ی',
                    'ی',
                    'ک',
                    'ه',
                    'ه',
                    'و'
                ],
                $text
            );

        $text =
            strtr(
                $text,
                [
                    '۰'=>'0',
                    '۱'=>'1',
                    '۲'=>'2',
                    '۳'=>'3',
                    '۴'=>'4',
                    '۵'=>'5',
                    '۶'=>'6',
                    '۷'=>'7',
                    '۸'=>'8',
                    '۹'=>'9',

                    '٠'=>'0',
                    '١'=>'1',
                    '٢'=>'2',
                    '٣'=>'3',
                    '٤'=>'4',
                    '٥'=>'5',
                    '٦'=>'6',
                    '٧'=>'7',
                    '٨'=>'8',
                    '٩'=>'9'
                ]
            );

        return trim(
            $this->strtolower(
                $text
            )
        );
    }
}

new WC_Smart_Upsell_Checkout();

}