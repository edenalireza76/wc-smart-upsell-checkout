<?php
if (!defined('ABSPATH')) { exit; }

final class WC_SUC_Admin {
    private const NONCE = 'wc_suc_manage_complements';

    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'assets']);
        add_action('wp_ajax_wc_suc_product_list', [$this, 'product_list']);
        add_action('wp_ajax_wc_suc_save_links', [$this, 'save_links']);
    }

    public function assets($hook) {
        if ($hook !== 'woocommerce_page_wc-smart-upsell-checkout') { return; }
        wp_enqueue_style('woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css', [], WC_VERSION);
        wp_enqueue_script('wc-enhanced-select');
        wp_enqueue_style('wc-suc-admin', plugins_url('../assets/admin.css', __FILE__), [], WC_Smart_Upsell_Checkout::VERSION);
        wp_enqueue_script('wc-suc-admin', plugins_url('../assets/admin.js', __FILE__), ['jquery', 'wc-enhanced-select'], WC_Smart_Upsell_Checkout::VERSION, true);
        wp_localize_script('wc-suc-admin', 'WCSUCAdmin', [
            'url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE),
        ]);
    }

    public static function selector($id, $ids = [], $exclude = 0) {
        ?>
        <select id="<?php echo esc_attr($id); ?>" class="wc-product-search" multiple="multiple"
            data-sortable="true" data-placeholder="نام محصول یا شناسهٔ کالا…"
            data-action="woocommerce_json_search_products_and_variations"
            data-exclude="<?php echo esc_attr($exclude); ?>" aria-label="محصولات مکمل">
            <?php foreach ($ids as $linked_id) :
                $linked = wc_get_product($linked_id);
                if (!$linked) { continue; } ?>
                <option value="<?php echo esc_attr($linked_id); ?>" selected="selected"><?php echo esc_html($linked->get_formatted_name()); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public static function page() {
        ?>
        <section class="suc-panel" aria-labelledby="suc-manager-title">
            <div class="suc-panel-heading"><div><h2 id="suc-manager-title">مکمل‌های هر محصول</h2>
                <p>محصولاتی را انتخاب کنید که کنار هم کاربرد دارند. ارتباط با چند محصول سبد، اولویت نمایش را بیشتر می‌کند.</p></div>
                <span class="suc-tag">انتخاب دستی</span></div>
            <form id="suc-filters" class="suc-filters" novalidate>
                <div class="suc-search"><label for="suc-search">جست‌وجوی محصول</label><div class="suc-search-input"><input type="search" id="suc-search" placeholder="نام محصول یا SKU" autocomplete="off"><button type="button" id="suc-clear" class="button" aria-label="پاک کردن جست‌وجو" hidden>×</button></div></div>
                <div><label for="suc-category">دسته‌بندی</label><?php wp_dropdown_categories([
                    'taxonomy' => 'product_cat', 'name' => 'suc-category', 'id' => 'suc-category',
                    'show_option_all' => 'همهٔ دسته‌ها', 'hide_empty' => false, 'hierarchical' => true,
                ]); ?></div>
                <div><label for="suc-coverage">وضعیت مکمل</label><select id="suc-coverage"><option value="all">همهٔ محصولات</option><option value="empty">بدون مکمل</option></select></div>
                <button class="button" type="submit">نمایش محصولات</button>
            </form>
            <div class="suc-bulk">
                <div class="suc-bulk-heading"><strong>ویرایش گروهی</strong><span id="suc-selected">۰ محصول انتخاب شده</span></div>
                <div class="suc-bulk-controls">
                    <div><label for="suc-bulk-mode">نوع تغییر</label><select id="suc-bulk-mode"><option value="add">افزودن به مکمل‌های فعلی</option><option value="replace">جایگزینی همهٔ مکمل‌ها</option><option value="remove">حذف مکمل‌های انتخاب‌شده</option></select></div>
                    <div class="suc-bulk-products"><label for="suc-bulk-links">محصولات مکمل</label><?php self::selector('suc-bulk-links'); ?></div>
                    <button type="button" class="button" id="suc-bulk-apply" disabled>اعمال روی انتخاب‌ها</button>
                </div>
                <p class="suc-hint">انتخاب گروهی فقط برای محصولات همین صفحه است. در حالت جایگزینی، مکمل‌های قبلی حذف می‌شوند؛ انتخاب خالی همهٔ مکمل‌ها را پاک می‌کند.</p>
                <div id="suc-confirm" hidden><p id="suc-confirm-text"></p><button type="button" class="button button-primary" id="suc-confirm-yes">تأیید و اعمال تغییر</button> <button type="button" class="button" id="suc-confirm-no">انصراف</button></div>
                <p id="suc-bulk-status" role="status" aria-live="polite"></p>
            </div>
            <p id="suc-list-status" role="status" aria-live="polite"></p>
            <button type="button" class="button" id="suc-retry" hidden>تلاش دوباره</button>
            <div class="suc-table-scroll" tabindex="0" aria-label="جدول محصولات">
                <table class="widefat suc-table"><thead><tr><td class="check-column"><input type="checkbox" id="suc-select-all" aria-label="انتخاب همهٔ محصولات این صفحه"></td><th scope="col">محصول</th><th scope="col">محصولات مکمل</th><th scope="col">ذخیره</th></tr></thead><tbody id="suc-rows"></tbody></table>
            </div>
            <div class="suc-pagination"><button type="button" class="button" id="suc-prev" disabled>صفحهٔ قبل</button><span id="suc-page" aria-live="polite"></span><button type="button" class="button" id="suc-next" disabled>صفحهٔ بعد</button></div>
            <div id="suc-discard" hidden><p>تغییرات ذخیره‌نشدهٔ این صفحه کنار گذاشته شوند؟</p><button type="button" class="button" id="suc-discard-yes">کنار گذاشتن و ادامه</button> <button type="button" class="button" id="suc-discard-no">بازگشت به ویرایش</button></div>
        </section>
        <?php
    }

    private function authorize() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'اجازهٔ مدیریت محصولات را ندارید.'], 403);
        }
        if (!check_ajax_referer(self::NONCE, 'nonce', false)) {
            wp_send_json_error(['message' => 'نشست منقضی شده است. تغییرات را یادداشت و صفحه را تازه کنید.'], 403);
        }
    }

    public static function links($product) {
        return array_values(array_unique(array_filter(array_map('absint', (array) $product->get_meta(WC_Smart_Upsell_Checkout::COMPLEMENTS_META_KEY, true)))));
    }

    public static function revision($ids) { return hash('sha256', wp_json_encode($ids)); }

    public function product_list() {
        $this->authorize();
        $page = max(1, absint($_POST['page'] ?? 1));
        $search = sanitize_text_field(wp_unslash($_POST['search'] ?? ''));
        $args = [
            'post_type' => 'product', 'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'posts_per_page' => 20, 'paged' => $page, 'orderby' => 'ID', 'order' => 'DESC',
            'suc_admin_search' => $search,
        ];
        $category = absint($_POST['category'] ?? 0);
        if ($category) {
            $args['tax_query'] = [['taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => [$category]]];
        }
        if (($_POST['coverage'] ?? '') === 'empty') {
            $args['meta_query'] = ['relation' => 'OR',
                ['key' => WC_Smart_Upsell_Checkout::COMPLEMENTS_META_KEY, 'compare' => 'NOT EXISTS'],
                ['key' => WC_Smart_Upsell_Checkout::COMPLEMENTS_META_KEY, 'value' => 'a:0:{}'],
                ['key' => WC_Smart_Upsell_Checkout::COMPLEMENTS_META_KEY, 'value' => ''],
            ];
        }
        // Scope title/SKU search to this query and prepare all user input.
        $search_filter = static function ($where, $query) {
            global $wpdb;
            $term = $query->get('suc_admin_search');
            if ($term === '') { return $where; }
            if (!$term) { return $where; }
            $like = '%' . $wpdb->esc_like($term) . '%';
            return $where . $wpdb->prepare(
                " AND ({$wpdb->posts}.post_title LIKE %s OR EXISTS (SELECT 1 FROM {$wpdb->postmeta} suc_sku WHERE suc_sku.post_id = {$wpdb->posts}.ID AND suc_sku.meta_key = '_sku' AND suc_sku.meta_value LIKE %s))", $like, $like
            );
        };
        add_filter('posts_where', $search_filter, 10, 2);
        $query = new WP_Query($args);
        remove_filter('posts_where', $search_filter, 10);
        $pages = max(1, (int) $query->max_num_pages);
        if ($page > $pages) { $args['paged'] = $pages; add_filter('posts_where', $search_filter, 10, 2); $query = new WP_Query($args); remove_filter('posts_where', $search_filter, 10); $page = $pages; }
        ob_start();
        foreach ($query->posts as $post) {
            $product = wc_get_product($post->ID);
            if (!$product) { continue; }
            $ids = self::links($product);
            $editable = current_user_can('edit_post', $post->ID);
            ?>
            <tr data-id="<?php echo esc_attr($post->ID); ?>" data-revision="<?php echo esc_attr(self::revision($ids)); ?>">
                <th scope="row" class="check-column"><input type="checkbox" class="suc-select" aria-label="<?php echo esc_attr('انتخاب ' . $product->get_name()); ?>" <?php disabled(!$editable); ?>></th>
                <td><div class="suc-product"><?php echo wp_kses_post($product->get_image('thumbnail')); ?><div><a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>"><?php echo esc_html($product->get_name()); ?></a><span class="suc-sku"><?php echo esc_html($product->get_sku() ?: 'بدون SKU'); ?></span><span class="suc-hint"><?php echo esc_html(get_post_status_object($post->post_status)->label); ?></span></div></div></td>
                <td><?php if ($editable) { self::selector('suc-links-' . $post->ID, $ids, $post->ID); } else { echo esc_html('اجازهٔ ویرایش این محصول را ندارید.'); } ?></td>
                <td><button type="button" class="button suc-save" disabled>ذخیره</button><span class="suc-row-status" role="status" aria-live="polite"></span></td>
            </tr>
            <?php
        }
        if (!$query->posts) { echo '<tr><td colspan="4" class="suc-empty">محصولی پیدا نشد. عبارت جست‌وجو یا فیلترها را تغییر دهید.</td></tr>'; }
        wp_send_json_success(['html' => ob_get_clean(), 'page' => $page, 'pages' => $pages, 'total' => (int) $query->found_posts]);
    }

    public static function merge_links($product, $current, $input, $mode) {
        $ids = [];
        foreach ($input as $value) {
            if (!is_scalar($value)) { continue; }
            $id = absint($value);
            $candidate = $id ? wc_get_product($id) : false;
            if ($candidate && $id !== $product->get_id() && $candidate->get_parent_id() !== $product->get_id()) { $ids[] = $id; }
        }
        $ids = array_values(array_unique($ids));
        if ($mode === 'add') { $ids = array_merge($current, $ids); }
        if ($mode === 'remove') { $ids = array_diff($current, $ids); }
        return array_values(array_unique($ids));
    }

    public function save_links() {
        $this->authorize();
        $id = absint($_POST['product_id'] ?? 0);
        $product = wc_get_product($id);
        if (!$product || $product->get_parent_id() || !current_user_can('edit_post', $id)) {
            wp_send_json_error(['message' => 'محصول در دسترس نیست یا اجازهٔ ویرایش آن را ندارید.'], 403);
        }
        $mode = sanitize_key($_POST['mode'] ?? 'replace');
        if (!in_array($mode, ['add', 'replace', 'remove'], true) || !isset($_POST['revision']) || !isset($_POST['ids']) || !is_string($_POST['ids'])) {
            wp_send_json_error(['message' => 'درخواست نامعتبر است.'], 400);
        }
        $input = json_decode(wp_unslash($_POST['ids']), true);
        if (!is_array($input) || count($input) > 200) {
            wp_send_json_error(['message' => 'حداکثر ۲۰۰ مکمل در هر درخواست انتخاب کنید.'], 400);
        }
        $current = self::links($product);
        if (!hash_equals(self::revision($current), (string) $_POST['revision'])) {
            wp_send_json_error(['message' => 'مکمل‌های این محصول در جای دیگری تغییر کرده‌اند. برای دریافت نسخهٔ جدید، فهرست را دوباره بارگذاری کنید.'], 409);
        }
        $ids = self::merge_links($product, $current, $input, $mode);
        try {
            $product->update_meta_data(WC_Smart_Upsell_Checkout::COMPLEMENTS_META_KEY, $ids);
            $product->save();
        } catch (Exception $error) {
            wp_send_json_error(['message' => 'ذخیره انجام نشد. دوباره تلاش کنید.'], 500);
        }
        $options = [];
        foreach ($ids as $linked_id) {
            $linked = wc_get_product($linked_id);
            if ($linked) { $options[] = ['id' => $linked_id, 'text' => $linked->get_formatted_name()]; }
        }
        wp_send_json_success(['ids' => $ids, 'options' => $options, 'revision' => self::revision($ids)]);
    }
}
