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

            // Switch to a tab pane without page reload
            function activateTab(slug) {
                $tabs.removeClass('active');
                $tabs.filter('[data-tab="' + slug + '"]').addClass('active');
                $('.lflow-settings-tab-pane').hide();
                $('#lflow-tab-' + slug).show();
                // Keep the URL in sync so a save redirects back to the right tab
                var href = $tabs.filter('[data-tab="' + slug + '"]').attr('href');
                if (href) { history.replaceState(null, '', href); }
            }

            $tabs.on('click', function (e) {
                e.preventDefault();
                activateTab($(this).data('tab'));
            });

            // Activate the tab matching the current ?tab= URL param
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
                    // Fallback for older browsers
                    var $tmp = $('<textarea>').val(text).appendTo('body').select();
                    document.execCommand('copy');
                    $tmp.remove();
                    var $btn = $(e.currentTarget);
                    $btn.text('✓');
                    setTimeout(function () { $btn.text('⧉'); }, 1500);
                });
            });
        },

        // ── Metabox quick-add ─────────────────────────────────────────────────

        // ── Help toggles ──────────────────────────────────────────────────────

        bindHelpToggles: function () {
            $(document).on('click', '.lflow-help-btn', function (e) {
                e.preventDefault();
                var $btn  = $(this);
                var $text = $btn.next('.lflow-help-text');
                var open  = $text.hasClass('visible');
                // Close all other open helps
                $('.lflow-help-text.visible').removeClass('visible');
                $('.lflow-help-btn.active').removeClass('active');
                if (!open) {
                    $text.addClass('visible');
                    $btn.addClass('active');
                }
            });
        },

        bindMetaboxQuickAdd: function () {
            // Toggle the quick-add form
            $(document).on('click', '#lflow-quick-add-toggle', function (e) {
                e.preventDefault();
                $('#lflow-quick-add-form').slideToggle(200, function () {
                    if ($('#lflow-quick-add-form').is(':visible')) {
                        $('#lflow-qa-value').focus();
                    }
                });
            });

            // When variation changes: update license type + default_valid
            $(document).on('change', '#lflow-qa-variation', function () {
                var pid = $('#lflow-quick-add-form [name="product_id"]').val();
                var vid = $(this).val() || 0;
                if (!pid) return;
                $.post(lflow_admin.ajax_url, {
                    action: 'lflow_get_variations',
                    nonce: lflow_admin.nonce,
                    product_id: pid,
                    variation_id: vid
                }, function (response) {
                    if (!response.success) return;
                    if (response.data.license_type) {
                        $('#lflow-qa-type').val(response.data.license_type);
                    }
                    if (typeof response.data.default_valid !== 'undefined') {
                        $('#lflow-qa-valid').val(response.data.default_valid);
                    }
                });
            });

            // Submit quick-add form
            $(document).on('submit', '#lflow-quick-add-form form', function (e) {
                e.preventDefault();
                var $form   = $(this);
                var $btn    = $form.find('.lflow-quick-add-submit');
                var pid     = $form.find('[name="product_id"]').val();
                var type    = $form.find('#lflow-qa-type').val() || 'key';
                var rawVal  = $.trim($form.find('[name="license_value[key]"]').val());
                var varId   = $form.find('[name="variation_id"]').val() || 0;
                var delivre = parseInt($form.find('[name="delivre_x_times"]').val()) || 1;
                var valid   = parseInt($form.find('[name="valid"]').val()) || 0;

                if (!rawVal) {
                    $form.find('[name="license_value[key]"]').focus();
                    return;
                }

                // Parse || for display only (server handles the actual split)
                var displayVal = rawVal.indexOf('||') !== -1 ? $.trim(rawVal.split('||')[0]) : rawVal;
                var shortKey   = displayVal.length > 30 ? displayVal.substring(0, 28) + '…' : displayVal;

                $btn.prop('disabled', true).text('…');

                $.post(lflow_admin.ajax_url, {
                    action:              'lflow_save_license',
                    nonce:               lflow_admin.nonce,
                    license_id:          0,
                    product_id:          pid,
                    variation_id:        varId,
                    license_type:        type,
                    'license_value[key]': rawVal,
                    license_status:      'available',
                    delivre_x_times:     delivre,
                    valid:               valid
                }, function (response) {
                    $btn.prop('disabled', false).text('+ Ajouter');
                    if (response.success) {
                        var id     = response.data.license_id;
                        var $tbody = $('#lflow-quick-licenses-tbody');
                        // Remove "no licenses" placeholder row if present
                        $tbody.find('#lflow-no-licenses-row').remove();
                        $tbody.prepend(
                            '<tr>' +
                            '<td><a href="' + lflow_admin.edit_url + '&license_id=' + id + '">#' + id + '</a></td>' +
                            '<td><span class="lflow-status-badge lflow-status-available">Disponible</span></td>' +
                            '<td><code>' + $('<span>').text(shortKey).html() + '</code></td>' +
                            '<td>—</td>' +
                            '</tr>'
                        );
                        // Increment available count
                        var $count = $('#lflow-quick-available-count');
                        $count.text(parseInt($count.text() || 0) + 1);
                        // Reset value field only, keep other fields for chaining
                        $form.find('[name="license_value[key]"]').val('').focus();
                        LFLOW.showNotice('Licence #' + id + ' ajoutée.', 'success');
                    } else {
                        alert(response.data.message || lflow_admin.i18n.error);
                    }
                });
            });
        },

        // ── Filter: dynamic variations ────────────────────────────────────

        bindFilterVariations: function () {
            var $product = $('#lflow-filter-product');
            if (!$product.length) return;

            $product.on('change', function () {
                var pid = $(this).val();
                var $variation = $('#lflow-filter-variation');
                $variation.find('option:not(:first)').remove();

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

        bindLiveSearch: function () {
            var $form = $('#lflow-licenses-form');
            if (!$form.length) return;

            var debounce = null;

            // Snapshot current filter state from the rendered form
            var filters = {
                s:              $('#lflow-filter-s').val()        || '',
                product_id:     $('#lflow-filter-product').val()  || '0',
                variation_id:   $('#lflow-filter-variation').val()|| '0',
                license_type:   $('#lflow-filter-type').val()     || '',
                license_status: $('#lflow-filter-status').val()   || '',
                orderby:        'license_id',
                order:          'DESC',
                paged:          1
            };

            function load(extra) {
                if (extra) { $.extend(filters, extra); }
                var $container = $('#lflow-table-container');
                $container.addClass('lflow-loading');

                $.post(lflow_admin.ajax_url, $.extend(
                    { action: 'lflow_list_licenses', nonce: lflow_admin.nonce },
                    filters
                ), function (response) {
                    $container.removeClass('lflow-loading');
                    if (response.success) {
                        $container.html(response.data.html);
                    }
                });
            }

            // Text search — debounce 350 ms
            $(document).on('input', '#lflow-filter-s', function () {
                filters.s     = $(this).val();
                filters.paged = 1;
                clearTimeout(debounce);
                debounce = setTimeout(load, 350);
            });

            // Selects — immediate
            $(document).on('change', '#lflow-filter-product', function () {
                filters.product_id   = $(this).val();
                filters.variation_id = '0';
                filters.paged        = 1;
                load();
            });
            $(document).on('change', '#lflow-filter-variation', function () {
                filters.variation_id = $(this).val();
                filters.paged        = 1;
                load();
            });
            $(document).on('change', '#lflow-filter-type', function () {
                filters.license_type = $(this).val();
                filters.paged        = 1;
                load();
            });
            $(document).on('change', '#lflow-filter-status', function () {
                filters.license_status = $(this).val();
                filters.paged          = 1;
                load();
            });

            // Prevent classic form submit
            $form.on('submit', function (e) {
                e.preventDefault();
                load();
            });

            // Reset button
            $(document).on('click', '.lflow-filter-reset', function (e) {
                e.preventDefault();
                filters = { s: '', product_id: '0', variation_id: '0', license_type: '', license_status: '', orderby: 'license_id', order: 'DESC', paged: 1 };
                $('#lflow-filter-s').val('');
                $('#lflow-filter-product').val('0');
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
                        orderby:        params.orderby        || filters.orderby,
                        order:          params.order          || filters.order,
                        license_status: (params.license_status !== undefined)
                                            ? params.license_status
                                            : filters.license_status
                    });
                }
            );
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
