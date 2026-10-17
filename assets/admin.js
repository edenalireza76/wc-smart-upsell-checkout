(function ($) {
    'use strict';
    var page = 1, pages = 1, loading = false, busy = false, sequence = 0;
    var request = null, retryPage = 1, timer, pendingNavigation = null, pendingBulk = null, composing = false;
    var filters = { search: '', category: '0', coverage: 'all' };
    var number = new Intl.NumberFormat('fa-IR');
    function selected() { return $('#suc-rows .suc-select:checked').closest('tr'); }
    function dirty() { return $('#suc-rows tr[data-dirty="1"]').length > 0; }
    function announce(text) { $('#suc-list-status').text(text); }
    function initSelects() { $(document.body).trigger('wc-enhanced-select-init'); }
    function controls() {
        var count = selected().length, boxes = $('#suc-rows .suc-select:not(:disabled)');
        $('#suc-selected').text(number.format(count) + ' محصول انتخاب شده');
        $('#suc-bulk-apply').prop('disabled', !count || busy || loading);
        $('#suc-select-all').prop('checked', count > 0 && count === boxes.length)
            .prop('indeterminate', count > 0 && count < boxes.length).prop('disabled', busy || loading || !boxes.length);
        $('#suc-prev').prop('disabled', busy || loading || page <= 1);
        $('#suc-next').prop('disabled', busy || loading || page >= pages);
        $('#suc-rows .suc-save').each(function () {
            $(this).prop('disabled', busy || loading || $(this).closest('tr').attr('data-dirty') !== '1');
        });
        $('#suc-filters :input, #suc-bulk-mode, #suc-bulk-links').prop('disabled', busy);
    }
    function errorMessage(xhr) {
        return xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
            ? xhr.responseJSON.data.message : 'ارتباط با سرور برقرار نشد. دوباره تلاش کنید.';
    }
    function navigate(callback) {
        if (busy) { return; }
        if (dirty()) {
            pendingNavigation = callback;
            $('#suc-discard').prop('hidden', false);
            $('#suc-discard-no').trigger('focus');
        } else { callback(); }
    }
    function writeURL() {
        var url = new URL(window.location.href);
        ['suc_search', 'suc_category', 'suc_coverage', 'suc_page'].forEach(function (key) { url.searchParams.delete(key); });
        if (filters.search) { url.searchParams.set('suc_search', filters.search); }
        if (filters.category !== '0') { url.searchParams.set('suc_category', filters.category); }
        if (filters.coverage !== 'all') { url.searchParams.set('suc_coverage', filters.coverage); }
        if (page > 1) { url.searchParams.set('suc_page', String(page)); }
        window.history.replaceState(null, '', url.toString());
    }
    function load(targetPage) {
        var ticket = ++sequence;
        retryPage = targetPage;
        pendingBulk = null;
        $('#suc-confirm').prop('hidden', true);
        if (request) { request.abort(); }
        loading = true;
        $('#suc-retry').prop('hidden', true);
        $('#suc-rows').attr('aria-busy', 'true');
        // Lock the old results while filters are being applied.
        $('#suc-rows select, #suc-rows input').prop('disabled', true);
        controls();
        announce('در حال دریافت محصولات…');
        request = $.ajax({ url: WCSUCAdmin.url, method: 'POST', timeout: 30000, data: {
            action: 'wc_suc_product_list', nonce: WCSUCAdmin.nonce, page: targetPage,
            search: filters.search, category: filters.category, coverage: filters.coverage
        }}).done(function (json) {
            if (ticket !== sequence) { return; }
            if (!json.success) { announce(json.data.message); $('#suc-retry').prop('hidden', false); return; }
            $('#suc-rows .wc-product-search.enhanced').each(function () { $(this).selectWoo('destroy'); });
            $('#suc-rows').html(json.data.html);
            page = json.data.page; pages = json.data.pages;
            initSelects();
            $('#suc-page').text('صفحهٔ ' + number.format(page) + ' از ' + number.format(pages));
            announce(number.format(json.data.total) + ' محصول');
            writeURL();
        }).fail(function (xhr, status) {
            if (ticket !== sequence || status === 'abort') { return; }
            announce(errorMessage(xhr)); $('#suc-retry').prop('hidden', false);
        }).always(function () {
            if (ticket !== sequence) { return; }
            loading = false; request = null;
            $('#suc-rows').attr('aria-busy', 'false');
            // Server-rendered disabled checkboxes belong to read-only rows.
            $('#suc-rows select').prop('disabled', false);
            $('#suc-rows tr').each(function () { if ($(this).find('select').length) { $(this).find('.suc-select').prop('disabled', false); } });
            controls();
        });
    }
    function saveRow(row, mode, ids) {
        var status = row.find('.suc-row-status');
        status.removeClass('suc-error').text('در حال ذخیره…');
        return $.ajax({ url: WCSUCAdmin.url, method: 'POST', timeout: 30000, data: {
            action: 'wc_suc_save_links', nonce: WCSUCAdmin.nonce,
            product_id: row.attr('data-id'), revision: row.attr('data-revision'),
            mode: mode, ids: JSON.stringify(ids)
        }}).then(function (json) {
            if (!json.success) { status.addClass('suc-error').text(json.data.message); return false; }
            var select = row.find('select');
            select.empty();
            json.data.options.forEach(function (option) { select.append(new Option(option.text, option.id, true, true)); });
            select.trigger('change');
            row.attr('data-revision', json.data.revision).removeAttr('data-dirty');
            status.text('ذخیره شد');
            return true;
        }, function (xhr) {
            status.addClass('suc-error').text(errorMessage(xhr));
            return false;
        });
    }
    function setBusy(value) {
        busy = value;
        $('#suc-rows select, #suc-rows .suc-select').prop('disabled', value);
        if (!value) {
            $('#suc-rows tr').each(function () { if (!$(this).find('select').length) { $(this).find('.suc-select').prop('disabled', true); } });
        }
        controls();
    }
    async function applyBulk(plan) {
        setBusy(true);
        var success = 0, failed = 0;
        for (var i = 0; i < plan.rows.length; i++) {
            $('#suc-bulk-status').text('در حال ذخیرهٔ ' + number.format(i + 1) + ' از ' + number.format(plan.rows.length));
            var row = $(plan.rows[i]);
            if (await saveRow(row, plan.mode, plan.ids)) {
                success++; row.find('.suc-select').prop('checked', false);
            } else { failed++; }
        }
        setBusy(false);
        $('#suc-bulk-status').text(number.format(success) + ' محصول ذخیره شد؛ ' + number.format(failed) + ' خطا. محصولات ناموفق انتخاب‌شده باقی مانده‌اند.');
    }
    function applyFilters() {
        clearTimeout(timer);
        var next = { search: $('#suc-search').val().trim(), category: $('#suc-category').val(), coverage: $('#suc-coverage').val() };
        navigate(function () { filters = next; load(1); });
    }
    $(function () {
        if (!$('#suc-rows').length) { return; }
        var params = new URL(window.location.href).searchParams;
        filters.search = params.get('suc_search') || '';
        filters.category = params.get('suc_category') || '0';
        filters.coverage = params.get('suc_coverage') === 'empty' ? 'empty' : 'all';
        $('#suc-search').val(filters.search);
        $('#suc-category').val(filters.category); if (!$('#suc-category').val()) { filters.category = '0'; $('#suc-category').val('0'); }
        $('#suc-coverage').val(filters.coverage);
        $('#suc-clear').prop('hidden', !filters.search);
        initSelects(); load(Math.max(1, parseInt(params.get('suc_page'), 10) || 1));
        $('#suc-filters').on('submit', function (event) { event.preventDefault(); applyFilters(); });
        $('#suc-category, #suc-coverage').on('change', applyFilters);
        $('#suc-search').on('compositionstart', function () { composing = true; clearTimeout(timer); })
            .on('compositionend', function () { composing = false; $(this).trigger('input'); })
            .on('input', function () { $('#suc-clear').prop('hidden', !this.value); clearTimeout(timer); if (!composing) { timer = setTimeout(applyFilters, 300); } });
        $('#suc-clear').on('click', function () { $('#suc-search').val('').trigger('focus'); $(this).prop('hidden', true); applyFilters(); });
        $('#suc-prev').on('click', function () { navigate(function () { load(page - 1); }); });
        $('#suc-next').on('click', function () { navigate(function () { load(page + 1); }); });
        $('#suc-retry').on('click', function () { navigate(function () { load(retryPage); }); });
        $('#suc-discard-yes').on('click', function () { $('#suc-discard').prop('hidden', true); if (pendingNavigation) { pendingNavigation(); pendingNavigation = null; } });
        $('#suc-discard-no').on('click', function () { pendingNavigation = null; $('#suc-discard').prop('hidden', true); $('#suc-rows select').first().trigger('focus'); });
        $('#suc-select-all').on('change', function () { $('#suc-rows .suc-select:not(:disabled)').prop('checked', this.checked); controls(); });
        $('#suc-rows').on('change', '.suc-select', controls).on('change', 'select', function () {
            var row = $(this).closest('tr'); row.attr('data-dirty', '1'); row.find('.suc-row-status').removeClass('suc-error').text('ذخیره نشده'); controls();
        }).on('click', '.suc-save', async function () {
            if (busy || loading) { return; }
            var row = $(this).closest('tr'), ids = row.find('select').val() || [];
            setBusy(true); await saveRow(row, 'replace', ids); setBusy(false);
        });
        $('#suc-bulk-apply').on('click', function () {
            if (busy || loading) { return; }
            var rows = selected(), mode = $('#suc-bulk-mode').val(), ids = $('#suc-bulk-links').val() || [];
            if (!rows.length) { return; }
            if (rows.filter('[data-dirty="1"]').length) { $('#suc-bulk-status').text('ابتدا تغییرات ذخیره‌نشدهٔ محصولات انتخاب‌شده را ذخیره کنید.'); return; }
            if (!ids.length && mode !== 'replace') { $('#suc-bulk-status').text('حداقل یک محصول مکمل انتخاب کنید.'); $('#suc-bulk-links').selectWoo('open'); return; }
            pendingBulk = { rows: rows.toArray(), mode: mode, ids: ids };
            if (mode === 'replace' || mode === 'remove') {
                $('#suc-confirm-text').text((mode === 'replace' ? 'مکمل‌های قبلی جایگزین شوند؟ ' : 'مکمل‌های انتخاب‌شده حذف شوند؟ ') + number.format(rows.length) + ' محصول تغییر می‌کند.');
                $('#suc-confirm').prop('hidden', false); $('#suc-confirm-no').trigger('focus');
            } else { applyBulk(pendingBulk); pendingBulk = null; }
        });
        $('#suc-confirm-yes').on('click', function () { $('#suc-confirm').prop('hidden', true); if (pendingBulk) { applyBulk(pendingBulk); pendingBulk = null; } });
        $('#suc-confirm-no').on('click', function () { pendingBulk = null; $('#suc-confirm').prop('hidden', true); $('#suc-bulk-apply').trigger('focus'); });
        $('#suc-bulk-mode, #suc-bulk-links, #suc-rows, #suc-select-all').on('change', function () { pendingBulk = null; $('#suc-confirm').prop('hidden', true); });
        window.addEventListener('beforeunload', function (event) { if (dirty() || busy) { event.preventDefault(); event.returnValue = ''; } });
    });
})(jQuery);
