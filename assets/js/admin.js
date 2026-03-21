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
            this.bindMetaboxQuickAdd();
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
            var $tabs = $('.lflow-settings-tabs a');
            if (!$tabs.length) return;

            $tabs.on('click', function (e) {
                e.preventDefault();
                var tab = $(this).data('tab');
                $tabs.removeClass('active');
                $(this).addClass('active');
                $('.lflow-settings-tab-pane').hide();
                $('#lflow-tab-' + tab).show();
                // Update URL hash without scroll
                history.replaceState(null, '', $(this).attr('href'));
            });

            // Activate from hash or first tab
            var hash = window.location.hash.replace('#', '') || $tabs.first().data('tab');
            $tabs.filter('[data-tab="' + hash + '"]').trigger('click');
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

        bindMetaboxQuickAdd: function () {
            // Toggle the quick-add form
            $(document).on('click', '#lflow-quick-add-toggle', function (e) {
                e.preventDefault();
                $('#lflow-quick-add-form').slideToggle(200);
            });

            // Submit quick-add form
            $(document).on('submit', '#lflow-quick-add-form form', function (e) {
                e.preventDefault();
                var $form   = $(this);
                var $btn    = $form.find('.lflow-quick-add-submit');
                var pid     = $form.find('[name="product_id"]').val();
                var type    = $form.find('[name="license_type"]').val();
                var rawVal  = $form.find('[name="license_value[key]"]').val();

                if (!rawVal) { alert('Veuillez entrer la valeur de la licence.'); return; }

                $btn.prop('disabled', true).text('Enregistrement…');

                $.post(lflow_admin.ajax_url, {
                    action:       'lflow_save_license',
                    nonce:        lflow_admin.nonce,
                    license_id:   0,
                    product_id:   pid,
                    variation_id: 0,
                    license_type: type,
                    'license_value[key]': rawVal,
                    license_status: 'available',
                    delivre_x_times: 1
                }, function (response) {
                    if (response.success) {
                        // Append new row to the table
                        var id = response.data.license_id;
                        var $tbody = $('#lflow-quick-licenses-tbody');
                        var shortKey = rawVal.length > 30 ? rawVal.substring(0, 28) + '…' : rawVal;
                        $tbody.prepend(
                            '<tr><td><a href="' + lflow_admin.edit_url + '&license_id=' + id + '">#' + id + '</a></td>' +
                            '<td><span class="lflow-status-badge lflow-status-available">Disponible</span></td>' +
                            '<td><code>' + $('<span>').text(shortKey).html() + '</code></td>' +
                            '<td>—</td></tr>'
                        );
                        // Update available count
                        var $count = $('#lflow-quick-available-count');
                        $count.text(parseInt($count.text() || 0) + 1);
                        // Reset field
                        $form.find('[name="license_value[key]"]').val('').focus();
                        $btn.prop('disabled', false).text('+ Ajouter');
                        LFLOW.showNotice('Licence #' + id + ' ajoutée.', 'success');
                    } else {
                        alert(response.data.message || lflow_admin.i18n.error);
                        $btn.prop('disabled', false).text('+ Ajouter');
                    }
                });
            });
        },

        // ── Utility ───────────────────────────────────────────────────────────

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
