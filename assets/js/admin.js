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

        // ── Utility ───────────────────────────────────────────────────────────

        showNotice: function (message, type) {
            var $n = $('#lflow-inline-notice');
            if (!$n.length) {
                $n = $('<div id="lflow-inline-notice" class="lflow-notice-inline"></div>').appendTo('.lflow-wrap');
            }
            $n.removeClass('success error').addClass(type).text(message).show();
            setTimeout(function () { $n.fadeOut(); }, 4000);
        }
    };

    $(function () { LFLOW.init(); });

}(jQuery));
