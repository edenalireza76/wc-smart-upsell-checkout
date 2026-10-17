<?php
require __DIR__ . '/recommendations.php';
require dirname(__DIR__) . '/includes/class-wc-suc-admin.php';
function wp_json_encode($value) { return json_encode($value); }
function sanitize_key($value) { return preg_replace('/[^a-z_]/', '', $value); }
function current_user_can($capability, ...$args) { return $GLOBALS['allowed'] && ($capability !== 'edit_post' || $GLOBALS['editable']); }
function check_ajax_referer(...$args) { return $GLOBALS['valid_nonce']; }
class AjaxResult extends Exception {
    public $success, $payload, $status;
    function __construct($success, $payload, $status) { $this->success = $success; $this->payload = $payload; $this->status = $status; }
}
function wp_send_json_error($payload, $status = 200) { throw new AjaxResult(false, $payload, $status); }
function wp_send_json_success($payload) { throw new AjaxResult(true, $payload, 200); }
function call_save($overrides = []) {
    global $p;
    $_POST = array_merge(['product_id' => 1, 'mode' => 'replace', 'ids' => '[]', 'revision' => WC_SUC_Admin::revision(WC_SUC_Admin::links($p[1]))], $overrides);
    try { (new WC_SUC_Admin())->save_links(); } catch (AjaxResult $result) { return $result; }
    throw new RuntimeException('Expected AJAX response');
}
$GLOBALS['allowed'] = $GLOBALS['editable'] = $GLOBALS['valid_nonce'] = true;
$p[1]->links = [8, 9];
expect(WC_SUC_Admin::merge_links($p[1], [8, 9], [9, 10, 1, 7, 999], 'add'), [8, 9, 10], 'Bulk add preserves previous links and excludes self, variants and invalid IDs');
expect(WC_SUC_Admin::merge_links($p[1], [8, 9], [9], 'remove'), [8], 'Bulk remove preserves unrelated links');
expect(WC_SUC_Admin::merge_links($p[1], [8, 9], [], 'replace'), [], 'Bulk empty replacement clears complements');
expect(WC_SUC_Admin::merge_links($p[1], [8, 9], [10, 8, 10], 'replace'), [10, 8], 'Replacement preserves ordered unique IDs');
$GLOBALS['allowed'] = false;
expect(call_save()->status, 403, 'Admin permission required');
$GLOBALS['allowed'] = true; $GLOBALS['valid_nonce'] = false;
expect(call_save()->status, 403, 'Valid nonce required');
$GLOBALS['valid_nonce'] = true; $GLOBALS['editable'] = false;
expect(call_save()->status, 403, 'Per-product edit permission required');
$GLOBALS['editable'] = true;
expect(call_save(['revision' => 'old'])->status, 409, 'Stale edits rejected');
expect($p[1]->links, [8, 9], 'Conflict leaves current metadata unchanged');
expect(call_save(['ids' => 'invalid'])->status, 400, 'Malformed input rejected');
expect(call_save(['mode' => 'unknown'])->status, 400, 'Unknown bulk operation rejected');
expect(call_save(['ids' => json_encode(range(1, 201))])->status, 400, 'Request size bounded');
$result = call_save();
expect($result->success, true, 'AJAX save succeeds');
expect($p[1]->links, [], 'AJAX clear persisted');
expect($result->payload['revision'], WC_SUC_Admin::revision([]), 'Save returns current revision');
$result = call_save(['mode' => 'add', 'ids' => '[8,9,8,1,999]']);
expect($p[1]->links, [8, 9], 'AJAX add persists valid unique links');
expect(array_column($result->payload['options'], 'id'), [8, 9], 'AJAX returns saved selector options');
$result = call_save(['mode' => 'remove', 'ids' => '[8]']);
expect($p[1]->links, [9], 'AJAX remove persists remaining links');
