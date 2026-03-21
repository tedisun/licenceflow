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

        init: function () {
            this.bindProductChange();
            this.bindVariationChange();
            // Init field groups visibility based on current hidden input value
            var currentType = $('#lflow-license-type').val() || 'key';
            this.showFieldGroup(currentType);
        },

        // ── Product selection ─────────────────────────────────────────────────

        bindProductChange: function () {
            $(document).on('change', '#lflow-product-id', function () {
                var productId = $(this).val();

                // Reset variation
                $('#lflow-variation-id').html('<option value="0">' + (lflow_admin.i18n ? '— Toutes —' : '— All —') + '</option>');
                $('#lflow-variation-row').hide();

                if (!productId) {
                    return;
                }

                $.post(lflow_admin.ajax_url, {
                    action: 'lflow_get_variations',
                    nonce: lflow_admin.nonce,
                    product_id: productId
                }, function (response) {
                    if (!response.success) return;

                    var data = response.data;

                    // Populate variations dropdown
                    if (data.variations && Object.keys(data.variations).length > 0) {
                        var $select = $('#lflow-variation-id');
                        $.each(data.variations, function (vid, vname) {
                            $select.append('<option value="' + vid + '">' + vname + '</option>');
                        });
                        $('#lflow-variation-row').show();
                    }

                    // Update license type
                    var type = data.license_type || 'key';
                    LicenseForm.setLicenseType(type);
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
                    if (response.success && response.data.license_type) {
                        LicenseForm.setLicenseType(response.data.license_type);
                    }
                });
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
