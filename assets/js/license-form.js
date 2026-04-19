/* global lflow_admin, jQuery */
(function ($) {
    'use strict';

    var LicenseForm = {

        typeIcons: {
            key:     '🔑 ',
            account: '👤 ',
            link:    '🔗 ',
            code:    '🎟️ '
        },

        _varXhr: null,

        init: function () {
            this.bindProductChange();
            this.bindVariationChange();
            this.bindTypeChange();
            var currentType = $('#lflow-license-type').val() || 'key';
            this.showFieldGroup(currentType);
        },

        // ── Product selection ─────────────────────────────────────────────────

        bindProductChange: function () {
            $(document).on('change', '#lflow-product-id', function () {
                var productId  = $(this).val();
                var $varSelect = $('#lflow-variation-id');
                var $varRow    = $('#lflow-variation-row');

                $varSelect.find('option:not(:first)').remove();
                $varSelect.val('0');
                $varRow.hide();

                if (LicenseForm._varXhr) { LicenseForm._varXhr.abort(); LicenseForm._varXhr = null; }
                if (!productId) { return; }

                LicenseForm._varXhr = $.post(lflow_admin.ajax_url, {
                    action: 'lflow_get_variations',
                    nonce: lflow_admin.nonce,
                    product_id: productId
                }, function (response) {
                    LicenseForm._varXhr = null;
                    if (!response.success) return;

                    var data = response.data;

                    $varSelect.find('option:not(:first)').remove();
                    if (data.variations && data.variations.length > 0) {
                        $.each(data.variations, function (i, v) {
                            $varSelect.append('<option value="' + v.id + '">' + v.label + '</option>');
                        });
                        $varRow.show();
                    }

                    var type = data.license_type || 'key';
                    LicenseForm.setLicenseType(type);

                    if (typeof data.default_valid !== 'undefined') {
                        $('#lflow-valid').val(data.default_valid);
                    }
                });
            });
        },

        // ── Variation selection (re-fetch type for that variation) ────────────

        bindVariationChange: function () {
            $(document).on('change', '#lflow-variation-id', function () {
                var productId   = $('#lflow-product-id').val();
                var variationId = $(this).val() || 0;
                if (!productId) return;

                // Re-fetch to get the variation-specific license type
                $.post(lflow_admin.ajax_url, {
                    action: 'lflow_get_variations',
                    nonce: lflow_admin.nonce,
                    product_id: productId,
                    variation_id: variationId
                }, function (response) {
                    if (!response.success) return;
                    var data = response.data;
                    if (data.license_type) {
                        LicenseForm.setLicenseType(data.license_type);
                    }
                    if (typeof data.default_valid !== 'undefined') {
                        $('#lflow-valid').val(data.default_valid);
                    }
                });
            });
        },

        // ── Manual type change ────────────────────────────────────────────────

        bindTypeChange: function () {
            $(document).on('change', '#lflow-license-type', function () {
                LicenseForm.showFieldGroup($(this).val());
            });
        },

        // ── License type ──────────────────────────────────────────────────────

        setLicenseType: function (type) {
            $('#lflow-license-type').val(type);
            this.showFieldGroup(type);
            this.updateTypeLabel(type);
        },

        showFieldGroup: function (type) {
            $('.lflow-license-field-group').removeClass('lflow-active');
            var $group = $('.lflow-license-field-group[data-type="' + type + '"]');
            if ($group.length) {
                $group.addClass('lflow-active');
            } else {
                // Fallback to key
                $('.lflow-license-field-group[data-type="key"]').addClass('lflow-active');
            }
        },

        updateTypeLabel: function (type) {
            var types = lflow_admin.license_types || {};
            var icons  = LicenseForm.typeIcons;
            var label  = types[type] || type;
            var icon   = icons[type] || '';
            $('#lflow-license-type-label').text(icon + label);
        }
    };

    $(function () {
        LicenseForm.init();
    });

}(jQuery));
