/* global lflow_admin, jQuery */
(function ($) {
    'use strict';

    var LFLOW = {

        init: function () {
            this.bindBulkActions();
            this.bindDeleteLinks();
            this.bindSettingsTabs();
            this.bindMetaboxTabs();
            this.bindRegenerateApiKey();
            this.bindSyncStock();
            this.bindCopyKey();
            this.bindHelpToggles();
            this.bindMetaboxQuickAdd();
            this.bindFilterVariations();
            this.bindLiveSearch();
            this.bindTxtImport();
            this.initSearchableSelects();
        },

        // ── Bulk actions ──────────────────────────────────────────────────────

        bindBulkActions: function () {
            $(document).on('click', '#lflow-bulk-apply', function (e) {
                e.preventDefault();

                var action = $('#lflow-bulk-action').val();
                if (!action) return;

                var ids = [];
                $('input[name="license_ids[]"]:checked').each(function () {
                    ids.push($(this).val());
                });

                if (ids.length === 0) {
                    alert(lflow_admin.i18n.no_selection);
                    return;
                }

                if (!confirm(lflow_admin.i18n.confirm_bulk)) return;

                var $btn = $(this);
                $btn.prop('disabled', true).text(lflow_admin.i18n.saving);

                $.post(lflow_admin.ajax_url, {
                    action: 'lflow_bulk_action',
                    nonce: lflow_admin.nonce,
                    bulk_action: action,
                    license_ids: ids
                }, function (response) {
                    if (response.success) {
                        LFLOW.showNotice(response.data.message, 'success');
                        setTimeout(function () { location.reload(); }, 800);
                    } else {
                        LFLOW.showNotice(response.data.message || lflow_admin.i18n.error, 'error');
                        $btn.prop('disabled', false).text('Appliquer');
                    }
                });
            });

            // Select all checkbox
            $(document).on('change', '#lflow-select-all', function () {
                $('input[name="license_ids[]"]').prop('checked', this.checked);
            });
        },

        // ── Delete links ──────────────────────────────────────────────────────

        bindDeleteLinks: function () {
            $(document).on('click', '.lflow-delete-license', function (e) {
                e.preventDefault();

                if (!confirm(lflow_admin.i18n.confirm_delete)) return;

                var $link = $(this);
                var id = $link.data('id');

                $.post(lflow_admin.ajax_url, {
                    action: 'lflow_delete_license',
                    nonce: lflow_admin.nonce,
                    license_id: id
                }, function (response) {
                    if (response.success) {
                        $link.closest('tr').fadeOut(300, function () { $(this).remove(); });
                    } else {
                        alert(response.data.message || lflow_admin.i18n.error);
                    }
                });
            });
        },

        // ── Settings tabs ─────────────────────────────────────────────────────

        bindSettingsTabs: function () {
            var $nav = $('.lflow-settings-tabs');
            if (!$nav.length) return;

            var $tabs = $nav.find('a');

            function activateTab(slug) {
                $tabs.removeClass('active');
                $tabs.filter('[data-tab="' + slug + '"]').addClass('active');
                $('.lflow-settings-tab-pane').hide();
                $('#lflow-tab-' + slug).show();
                var href = $tabs.filter('[data-tab="' + slug + '"]').attr('href');
                if (href) { history.replaceState(null, '', href); }
            }

            $tabs.on('click', function (e) {
                e.preventDefault();
                activateTab($(this).data('tab'));
            });

            var activeTab = $nav.data('active-tab') || $tabs.first().data('tab');
            activateTab(activeTab);
        },

        // ── Metabox tabs ──────────────────────────────────────────────────────

        bindMetaboxTabs: function () {
            $(document).on('click', '.lflow-metabox-tabs a', function (e) {
                e.preventDefault();
                var tab = $(this).data('tab');
                var $box = $(this).closest('.lflow-metabox-inner');
                $box.find('.lflow-metabox-tabs a').removeClass('active');
                $(this).addClass('active');
                $box.find('.lflow-metabox-tab-content').removeClass('active');
                $box.find('#lflow-metabox-' + tab).addClass('active');
            });
        },

        // ── Regenerate API key ────────────────────────────────────────────────

        bindRegenerateApiKey: function () {
            $(document).on('click', '#lflow-regen-api-key', function (e) {
                e.preventDefault();
                if (!confirm('Régénérer la clé API ? Les outils qui utilisent l\'ancienne clé devront être reconfigurés.')) return;

                var $btn = $(this);
                $btn.prop('disabled', true);

                $.post(lflow_admin.ajax_url, {
                    action: 'lflow_regenerate_api_key',
                    nonce: lflow_admin.nonce
                }, function (response) {
                    if (response.success) {
                        $('#lflow-api-key-value').val(response.data.api_key);
                        LFLOW.showNotice('Clé API régénérée.', 'success');
                    } else {
                        alert(lflow_admin.i18n.error);
                    }
                    $btn.prop('disabled', false);
                });
            });
        },

        // ── Sync stock ────────────────────────────────────────────────────────

        bindSyncStock: function () {
            $(document).on('click', '.lflow-sync-stock-btn', function (e) {
                e.preventDefault();
                var $btn = $(this);
                var pid = $btn.data('product-id');
                var vid = $btn.data('variation-id') || 0;

                $btn.prop('disabled', true).text('Sync…');

                $.post(lflow_admin.ajax_url, {
                    action: 'lflow_sync_stock',
                    nonce: lflow_admin.nonce,
                    product_id: pid,
                    variation_id: vid
                }, function (response) {
                    if (response.success) {
                        LFLOW.showNotice(response.data.message, 'success');
                    } else {
                        alert(response.data.message || lflow_admin.i18n.error);
                    }
                    $btn.prop('disabled', false).text('Sync stock');
                });
            });
        },

        // ── Copy key ──────────────────────────────────────────────────────────

        bindCopyKey: function () {
            $(document).on('click', '.lflow-copy-key', function (e) {
                e.preventDefault();
                var text = $(this).data('key');
                navigator.clipboard.writeText(text).then(function () {
                    var $btn = $(e.currentTarget);
                    $btn.text('✓');
                    setTimeout(function () { $btn.text('⧉'); }, 1500);
                }).catch(function () {
                    var $tmp = $('<textarea>').val(text).appendTo('body').select();
                    document.execCommand('copy');
                    $tmp.remove();
                    var $btn = $(e.currentTarget);
                    $btn.text('✓');
                    setTimeout(function () { $btn.text('⧉'); }, 1500);
                });
            });
        },

        // ── Help toggles ──────────────────────────────────────────────────────

        bindHelpToggles: function () {
            $(document).on('click', '.lflow-help-btn', function (e) {
                e.preventDefault();
                var $btn  = $(this);
                var $text = $btn.next('.lflow-help-text');
                var open  = $text.hasClass('visible');
                $('.lflow-help-text.visible').removeClass('visible');
                $('.lflow-help-btn.active').removeClass('active');
                if (!open) {
                    $text.addClass('visible');
                    $btn.addClass('active');
                }
            });
        },

        // ── Metabox quick-add ─────────────────────────────────────────────────
        //
        // The quick-add block is a <div>, NOT a <form> — the product edit page
        // already wraps everything in a WP <form>. Nested forms are invalid HTML
        // and cause the outer WP form to submit instead. The button uses
        // type="button"; everything is sent via AJAX.

        bindMetaboxQuickAdd: function () {

            // Toggle visibility
            $(document).on('click', '#lflow-quick-add-toggle', function (e) {
                e.preventDefault();
                $('#lflow-quick-add-form').slideToggle(200, function () {
                    if ($('#lflow-quick-add-form').is(':visible')) {
                        LFLOW._qaFocusPrimary();
                    }
                });
            });

            // Variation change: reload license type + default_valid, swap fields
            $(document).on('change', '#lflow-qa-variation', function () {
                var $wrap = $(this).closest('#lflow-quick-add-form');
                var pid   = $wrap.find('[name="qa_product_id"]').val();
                var vid   = $(this).val() || 0;
                if (!pid) return;
                $.post(lflow_admin.ajax_url, {
                    action:       'lflow_get_variations',
                    nonce:        lflow_admin.nonce,
                    product_id:   pid,
                    variation_id: vid
                }, function (response) {
                    if (!response.success) return;
                    if (response.data.license_type) {
                        $wrap.find('#lflow-qa-type').val(response.data.license_type);
                        LFLOW._qaShowFields(response.data.license_type, $wrap);
                    }
                    if (typeof response.data.default_valid !== 'undefined') {
                        $wrap.find('#lflow-qa-valid').val(response.data.default_valid);
                    }
                });
            });

            // Click handler on the "Ajouter" button (type="button", not submit)
            $(document).on('click', '.lflow-quick-add-submit', function (e) {
                e.preventDefault();
                var $btn    = $(this);
                var $wrap   = $btn.closest('#lflow-quick-add-form');
                var pid     = $wrap.find('[name="qa_product_id"]').val();
                var type    = $wrap.find('#lflow-qa-type').val() || 'key';
                var varId   = $wrap.find('[name="variation_id"]').val() || 0;
                var delivre = parseInt($wrap.find('[name="delivre_x_times"]').val()) || 1;
                var valid   = parseInt($wrap.find('[name="valid"]').val()) || 0;

                var postData = {
                    action:          'lflow_save_license',
                    nonce:           lflow_admin.nonce,
                    license_id:      0,
                    product_id:      pid,
                    variation_id:    varId,
                    license_type:    type,
                    license_status:  'available',
                    delivre_x_times: delivre,
                    valid:           valid
                };

                var displayVal = '';

                if (type === 'key' || type === 'code') {
                    var rawVal = $.trim($wrap.find('[name="license_value[key]"]').val());
                    if (!rawVal) { $wrap.find('[name="license_value[key]"]').focus(); return; }
                    postData['license_value[key]'] = rawVal;
                    displayVal = rawVal.indexOf('||') !== -1 ? $.trim(rawVal.split('||')[0]) : rawVal;

                } else if (type === 'account') {
                    var user = $.trim($wrap.find('[name="license_value[username]"]').val());
                    var pass = $.trim($wrap.find('[name="license_value[password]"]').val());
                    if (!user) { $wrap.find('[name="license_value[username]"]').focus(); return; }
                    postData['license_value[username]'] = user;
                    postData['license_value[password]'] = pass;
                    displayVal = user;

                } else if (type === 'link') {
                    var url   = $.trim($wrap.find('[name="license_value[url]"]').val());
                    var label = $.trim($wrap.find('[name="license_value[label]"]').val());
                    if (!url) { $wrap.find('[name="license_value[url]"]').focus(); return; }
                    postData['license_value[url]']   = url;
                    postData['license_value[label]'] = label;
                    displayVal = label || url;
                }

                var shortKey = displayVal.length > 30 ? displayVal.substring(0, 28) + '…' : displayVal;
                $btn.prop('disabled', true).text('…');

                $.post(lflow_admin.ajax_url, postData, function (response) {
                    $btn.prop('disabled', false).text('+ Ajouter');
                    if (response.success) {
                        var id     = response.data.license_id;
                        var $tbody = $('#lflow-quick-licenses-tbody');
                        $tbody.find('#lflow-no-licenses-row').remove();
                        $tbody.prepend(
                            '<tr>' +
                            '<td><a href="' + lflow_admin.edit_url + '&license_id=' + id + '">#' + id + '</a></td>' +
                            '<td><span class="lflow-status-badge lflow-status-available">Disponible</span></td>' +
                            '<td><code>' + $('<span>').text(shortKey).html() + '</code></td>' +
                            '<td>—</td>' +
                            '</tr>'
                        );
                        var $count = $('#lflow-quick-available-count');
                        $count.text(parseInt($count.text() || 0) + 1);
                        $wrap.find('[name="license_value[key]"], [name="license_value[username]"], ' +
                                   '[name="license_value[password]"], [name="license_value[url]"], ' +
                                   '[name="license_value[label]"]').val('');
                        LFLOW._qaFocusPrimary();
                        LFLOW.showNotice('Licence #' + id + ' ajoutée.', 'success');
                    } else {
                        alert(response.data.message || lflow_admin.i18n.error);
                    }
                });
            });
        },

        // Focus the primary input based on current type
        _qaFocusPrimary: function () {
            var type = $('#lflow-qa-type').val() || 'key';
            if (type === 'account') {
                $('#lflow-qa-username').focus();
            } else if (type === 'link') {
                $('#lflow-qa-url').focus();
            } else {
                $('#lflow-qa-value').focus();
            }
        },

        // Show the right field rows when the license type changes
        _qaShowFields: function (type, $wrap) {
            $wrap = $wrap || $('#lflow-quick-add-form');
            $wrap.find('.lflow-qa-field').hide();
            $wrap.find('.lflow-qa-field-' + type).show();
        },

        // ── Import TXT modal ──────────────────────────────────────────────────

        bindTxtImport: function () {
            var self = this;

            $(document).on('click', '#lflow-txt-import-btn', function (e) {
                e.preventDefault();
                $('#lflow-txt-import-modal').show();
                var $sel = $('#lflow-txt-import-product');
                if (!$sel.data('lflow-ss')) { self._buildSearchableSelect($sel); }
            });

            $(document).on('click', '.lflow-txt-import-close, #lflow-txt-import-backdrop', function () {
                $('#lflow-txt-import-modal').hide();
            });

            // Product change: load variations + detect license type
            $(document).on('change', '#lflow-txt-import-product', function () {
                var pid     = $(this).val();
                var $varRow = $('#lflow-txt-import-var-row');
                var $varSel = $('#lflow-txt-import-variation');
                $varSel.find('option:not(:first)').remove().end().val('0');

                if (!pid || pid === '0') { $varRow.hide(); $('#lflow-txt-import-type').val('key'); return; }

                $.post(lflow_admin.ajax_url, {
                    action: 'lflow_get_variations', nonce: lflow_admin.nonce, product_id: pid
                }, function (response) {
                    if (!response.success) return;
                    if (response.data.variations && response.data.variations.length) {
                        response.data.variations.forEach(function (v) {
                            $varSel.append('<option value="' + v.id + '">' + v.label + '</option>');
                        });
                        $varRow.show();
                    } else {
                        $varRow.hide();
                    }
                    if (response.data.license_type) { $('#lflow-txt-import-type').val(response.data.license_type); }
                });
            });

            // File → populate textarea
            $(document).on('change', '#lflow-txt-import-file', function () {
                var file = this.files && this.files[0];
                if (!file) return;
                var reader = new FileReader();
                reader.onload = function (ev) { $('#lflow-txt-import-lines').val(ev.target.result).trigger('input'); };
                reader.readAsText(file);
            });

            // Live line count
            $(document).on('input', '#lflow-txt-import-lines', function () {
                var n = $(this).val().split('\n').filter(function (l) { return l.trim() !== ''; }).length;
                $('#lflow-txt-import-count').text(n > 0 ? n + ' ligne(s) détectée(s)' : '');
            });

            // Submit
            $(document).on('click', '#lflow-txt-import-submit', function () {
                var pid     = $('#lflow-txt-import-product').val();
                var vid     = $('#lflow-txt-import-variation').val() || 0;
                var type    = $('#lflow-txt-import-type').val() || 'key';
                var delivre = parseInt($('#lflow-txt-import-delivre').val()) || 1;
                var valid   = parseInt($('#lflow-txt-import-valid').val()) || 0;
                var lines   = $('#lflow-txt-import-lines').val();

                if (!pid || pid === '0') { alert('Veuillez sélectionner un produit.'); return; }
                if (!lines.trim())       { alert('Veuillez saisir ou charger des licences.'); return; }

                var $btn    = $(this).prop('disabled', true).text('Import…');
                var $status = $('#lflow-txt-import-status').text('Envoi en cours…');

                $.post(lflow_admin.ajax_url, {
                    action: 'lflow_import_txt', nonce: lflow_admin.nonce,
                    product_id: pid, variation_id: vid, license_type: type,
                    delivre_x_times: delivre, valid: valid, license_keys: lines
                }, function (response) {
                    $btn.prop('disabled', false).text('Importer');
                    $status.text('');
                    if (response.success) {
                        $('#lflow-txt-import-modal').hide();
                        $('#lflow-txt-import-lines').val('');
                        LFLOW.showNotice(response.data.message, 'success');
                        if (typeof LFLOW._loadTable === 'function') { LFLOW._loadTable(); }
                    } else {
                        alert(response.data.message || lflow_admin.i18n.error);
                    }
                }).fail(function () {
                    $btn.prop('disabled', false).text('Importer');
                    $status.text('');
                    alert(lflow_admin.i18n.error);
                });
            });
        },

        // ── Filter: dynamic variations (no auto-fetch) ────────────────────────

        bindFilterVariations: function () {
            var $product = $('#lflow-filter-product');
            if (!$product.length) return;

            // When product changes: populate variations, but do NOT fetch the table.
            // The user must click "Filtrer" to apply.
            $product.on('change', function () {
                var pid = $(this).val();
                var $variation = $('#lflow-filter-variation');
                $variation.find('option:not(:first)').remove();
                $variation.val('0');

                if (!pid || pid === '0') {
                    $variation.prop('disabled', true);
                    return;
                }

                $.post(lflow_admin.ajax_url, {
                    action: 'lflow_get_variations',
                    nonce: lflow_admin.nonce,
                    product_id: pid
                }, function (response) {
                    if (response.success && response.data.variations && response.data.variations.length) {
                        response.data.variations.forEach(function (v) {
                            $variation.append('<option value="' + v.id + '">' + v.label + '</option>');
                        });
                        $variation.prop('disabled', false);
                    } else {
                        $variation.prop('disabled', true);
                    }
                });
            });
        },

        // ── Live search / AJAX filter ─────────────────────────────────────────
        //
        // Only auto-fetches on text search (debounced).
        // Product / variation / type / status selects update their value in the
        // DOM but do NOT trigger a fetch — the user must click "Filtrer".

        bindLiveSearch: function () {
            var $form = $('#lflow-licenses-form');
            if (!$form.length) return;

            var debounce    = null;
            var currentOrderby = 'license_id';
            var currentOrder   = 'DESC';
            var currentPaged   = 1;

            // Read all current filter values from the DOM at the moment of loading.
            function getParams(extra) {
                return $.extend({
                    action:         'lflow_list_licenses',
                    nonce:          lflow_admin.nonce,
                    s:              $('#lflow-filter-s').val()          || '',
                    product_id:     $('#lflow-filter-product').val()    || '0',
                    variation_id:   $('#lflow-filter-variation').val()  || '0',
                    license_type:   $('#lflow-filter-type').val()       || '',
                    license_status: $('#lflow-filter-status').val()     || '',
                    orderby:        currentOrderby,
                    order:          currentOrder,
                    paged:          currentPaged
                }, extra || {});
            }

            function load(extra) {
                var params = getParams(extra);
                currentOrderby = params.orderby;
                currentOrder   = params.order;
                currentPaged   = params.paged;

                var $container = $('#lflow-table-container');
                $container.addClass('lflow-loading');

                $.post(lflow_admin.ajax_url, params, function (response) {
                    $container.removeClass('lflow-loading');
                    if (response.success) {
                        $container.html(response.data.html);
                    }
                });
            }

            // Expose load so other modules (TXT import) can refresh the table
            LFLOW._loadTable = load;

            // Text search — debounce 350 ms; instant reload when field is cleared.
            // Also binds 'search' to catch the browser's native ✕ clear button on
            // <input type="search"> which fires 'search' but not always 'input'.
            $(document).on('input search', '#lflow-filter-s', function () {
                currentPaged = 1;
                clearTimeout(debounce);
                var delay = $(this).val() === '' ? 0 : 350;
                debounce = setTimeout(load, delay);
            });

            // "Filtrer" button / form submit
            $form.on('submit', function (e) {
                e.preventDefault();
                currentPaged = 1;
                load();
            });

            // Reset button
            $(document).on('click', '.lflow-filter-reset', function (e) {
                e.preventDefault();
                currentOrderby = 'license_id';
                currentOrder   = 'DESC';
                currentPaged   = 1;

                $('#lflow-filter-s').val('');
                // Reset the product searchable select (fires change → clears variations)
                $('#lflow-filter-product').val('0').trigger('change').trigger('lflow:ss:sync');
                $('#lflow-filter-variation').val('0').prop('disabled', true);
                $('#lflow-filter-type').val('');
                $('#lflow-filter-status').val('');

                load();
            });

            // Intercept pagination, column-sort and status-tab links inside the table
            $(document).on('click',
                '#lflow-table-container .tablenav-pages a, ' +
                '#lflow-table-container .subsubsub a, ' +
                '#lflow-table-container th.sortable a, ' +
                '#lflow-table-container th.sorted a',
                function (e) {
                    e.preventDefault();
                    var params = LFLOW.parseQueryString($(this).attr('href') || '');
                    load({
                        paged:          params.paged          || 1,
                        orderby:        params.orderby        || currentOrderby,
                        order:          params.order          || currentOrder,
                        license_status: (params.license_status !== undefined)
                                            ? params.license_status
                                            : $('#lflow-filter-status').val() || ''
                    });
                }
            );
        },

        // ── Searchable select ─────────────────────────────────────────────────
        //
        // Transforms any <select class="lflow-product-select"> into a searchable
        // custom dropdown. The original <select> stays hidden and keeps its name
        // so form submission continues to work normally.

        initSearchableSelects: function () {
            var self = this;

            $('.lflow-product-select').each(function () {
                self._buildSearchableSelect($(this));
            });

            // Close any open panel when clicking outside
            $(document).on('click', function (e) {
                if (!$(e.target).closest('.lflow-ss-wrap').length) {
                    self._closeAll();
                }
            });
        },

        _buildSearchableSelect: function ($select) {
            if ($select.data('lflow-ss')) return; // already initialised
            $select.data('lflow-ss', true);

            // Collect options
            var options = [];
            $select.find('option').each(function () {
                options.push({ value: $(this).val(), label: $(this).text().trim() });
            });

            var $wrap = $('<div class="lflow-ss-wrap"></div>');
            var $trigger = $(
                '<div class="lflow-ss-trigger" tabindex="0" role="combobox" aria-haspopup="listbox">' +
                    '<span class="lflow-ss-display"></span>' +
                    '<span class="lflow-ss-arrow"></span>' +
                '</div>'
            );
            var $panel = $(
                '<div class="lflow-ss-panel" role="listbox">' +
                    '<div class="lflow-ss-search-wrap">' +
                        '<input type="text" class="lflow-ss-search" placeholder="Rechercher…" autocomplete="off" spellcheck="false">' +
                    '</div>' +
                    '<ul class="lflow-ss-list"></ul>' +
                '</div>'
            );

            // Transfer min-width from the original select to the trigger
            var origMinWidth = $select[0].style.minWidth;
            if (origMinWidth) {
                $trigger.css('min-width', origMinWidth);
                $panel.css('min-width', origMinWidth);
            }

            // Insert the wrapper right after the select, then hide the select
            $select.after($wrap);
            $wrap.append($select).append($trigger).append($panel);
            $select.addClass('lflow-ss-hidden');

            // ── Helpers ───────────────────────────────────────────────────────

            function getSelectedLabel() {
                var val = $select.val();
                for (var i = 0; i < options.length; i++) {
                    if (String(options[i].value) === String(val)) {
                        return options[i].label;
                    }
                }
                return options.length ? options[0].label : '';
            }

            function refreshDisplay() {
                $trigger.find('.lflow-ss-display').text(getSelectedLabel());
            }

            function buildList(search) {
                var $list = $panel.find('.lflow-ss-list');
                $list.empty();
                var term  = (search || '').toLowerCase();
                var curVal = String($select.val());
                var count = 0;

                options.forEach(function (opt) {
                    if (term && opt.label.toLowerCase().indexOf(term) === -1) return;
                    var $li = $('<li></li>')
                        .attr('data-value', opt.value)
                        .text(opt.label)
                        .attr('role', 'option');
                    if (String(opt.value) === curVal) {
                        $li.addClass('lflow-ss-selected');
                    }
                    $list.append($li);
                    count++;
                });

                if (count === 0) {
                    $list.append('<li class="lflow-ss-no-results">Aucun résultat</li>');
                }
            }

            function openPanel() {
                LFLOW._closeAll();
                buildList('');
                $panel.find('.lflow-ss-search').val('');
                $wrap.addClass('lflow-ss-open');
                $panel.show();

                // Scroll selected item into view
                var $sel = $panel.find('.lflow-ss-selected');
                if ($sel.length) {
                    var list = $panel.find('.lflow-ss-list')[0];
                    list.scrollTop = Math.max(0, $sel[0].offsetTop - 60);
                }

                $panel.find('.lflow-ss-search').focus();
            }

            function closePanel() {
                $wrap.removeClass('lflow-ss-open');
                $panel.hide();
            }

            function selectOption(value, label) {
                $select.val(value).trigger('change'); // propagate to existing handlers
                refreshDisplay();
                closePanel();
            }

            // ── Events ────────────────────────────────────────────────────────

            $trigger.on('click', function (e) {
                e.stopPropagation();
                $wrap.hasClass('lflow-ss-open') ? closePanel() : openPanel();
            });

            $trigger.on('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openPanel(); }
                if (e.key === 'Escape')                  { closePanel(); }
            });

            $panel.on('input', '.lflow-ss-search', function () {
                buildList($(this).val());
            });

            $panel.on('keydown', '.lflow-ss-search', function (e) {
                if (e.key === 'Escape') { closePanel(); $trigger.focus(); }
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    $panel.find('.lflow-ss-list li:not(.lflow-ss-no-results)').first().focus();
                }
            });

            $panel.on('click', '.lflow-ss-list li:not(.lflow-ss-no-results)', function () {
                selectOption($(this).attr('data-value'), $(this).text());
            });

            $panel.on('keydown', '.lflow-ss-list li', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    selectOption($(this).attr('data-value'), $(this).text());
                }
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    $(this).next('li').focus();
                }
                if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    var $prev = $(this).prev('li');
                    if ($prev.length) { $prev.focus(); } else { $panel.find('.lflow-ss-search').focus(); }
                }
                if (e.key === 'Escape') { closePanel(); $trigger.focus(); }
            });

            // External sync: when something sets $select.val() programmatically,
            // fire  $select.trigger('lflow:ss:sync')  to update the display label.
            $select.on('lflow:ss:sync', function () {
                refreshDisplay();
            });

            // ── Init display ──────────────────────────────────────────────────
            refreshDisplay();
        },

        _closeAll: function () {
            $('.lflow-ss-wrap.lflow-ss-open').removeClass('lflow-ss-open')
                .find('.lflow-ss-panel').hide();
        },

        // ── Utility ───────────────────────────────────────────────────────────

        parseQueryString: function (url) {
            var result = {};
            var qs = url.indexOf('?') !== -1 ? url.split('?')[1] : '';
            if (!qs) return result;
            qs.split('&').forEach(function (part) {
                var pair = part.split('=');
                result[decodeURIComponent(pair[0])] = decodeURIComponent(pair[1] || '');
            });
            return result;
        },

        showNotice: function (message, type) {
            var $n = $('#lflow-inline-notice');
            if (!$n.length) {
                var $target = $('.lflow-wrap').length ? $('.lflow-wrap') : $('#lflow-product-metabox .inside');
                $n = $('<div id="lflow-inline-notice" class="lflow-notice-inline"></div>').prependTo( $target.length ? $target : 'body' );
            }
            $n.removeClass('success error').addClass(type).text(message).show();
            setTimeout(function () { $n.fadeOut(); }, 4000);
        }
    };

    $(function () { LFLOW.init(); });

}(jQuery));
