/* global wc_checkout_params */
jQuery(function ($) {
    'use strict';

    window.sb_helper = {
        init: function () {
            $(document.body).on('sb_payment_menu_instrument_selected', function (event, name, instrument) {
                console.log(name);

                if (instrument === 'Invoice') {
                    sb_helper.apply_fee();
                } else {
                    sb_helper.remove_fee();
                }
            });
        },

        apply_fee: function () {
            console.log('Apply invoice fee');
            sb_helper.block();

            return $.ajax({
                type: 'POST',
                url: WC_Gateway_Swedbank_Pay_Checkout.ajax_url,
                data: {
                    action: 'sb_invoice_apply_fee',
                    nonce: WC_Gateway_Swedbank_Pay_Checkout.nonce
                },
                dataType: 'json'
            }).done(function () {
                sb_helper.unblock();
                sb_helper.update_checkout();
            });
        },

        remove_fee: function () {
            console.log('Remove invoice fee');
            sb_helper.block();

            return $.ajax({
                type: 'POST',
                url: WC_Gateway_Swedbank_Pay_Checkout.ajax_url,
                data: {
                    action: 'sb_invoice_unset_fee',
                    nonce: WC_Gateway_Swedbank_Pay_Checkout.nonce
                },
                dataType: 'json'
            }).done(function () {
                sb_helper.unblock();
                sb_helper.update_checkout();
            });
        },

        update_checkout: function () {
            $(document.body).trigger('update_checkout');
            wc_sb_checkout.onUpdatedCheckout();
        },

        block: function () {
            let form_data = $("form.checkout, form#order_review, form#add_payment_method").data();
            if (1 !== form_data['blockUI.isBlocked']) {
                $("form.checkout, form#order_review, form#add_payment_method").block({
                    message: null,
                    overlayCSS: {
                        background: '#fff',
                        opacity: 0.6
                    }
                });
            }
        },

        unblock: function () {
            $("form.checkout, form#order_review, form#add_payment_method").unblock();
        },
    };

    $(document).ready(function () {
        window.sb_helper.init();
    });
});

