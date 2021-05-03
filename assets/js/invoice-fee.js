/* global wc_checkout_params */
jQuery(function ($) {
    'use strict';

    window.sb_invoice_fee = {
        init: function () {
            $(document.body).on('sb_payment_menu_instrument_selected', function (event, name, instrument) {
                console.log(name);

                sb_invoice_fee.block();

                var xhr;
                if (instrument === 'Invoice') {
                    xhr = sb_invoice_fee.apply_fee( true );
                } else {
                    xhr = sb_invoice_fee.remove_fee( true );
                }

                xhr.done( function () {
                    sb_invoice_fee.unblock();
                } );
            });
        },

        apply_fee: function ( update_checkout ) {
            console.log('Apply invoice fee');

            return $.ajax({
                type: 'POST',
                url: WC_Gateway_Swedbank_Pay_Checkout_Invoice.ajax_url,
                data: {
                    action: 'sb_invoice_apply_fee',
                    nonce: WC_Gateway_Swedbank_Pay_Checkout_Invoice.nonce
                },
                dataType: 'json'
            }).done(function () {
                // Update checkout
                if ( update_checkout ) {
                    sb_invoice_fee.update_checkout();
                }
            });
        },

        remove_fee: function ( update_checkout ) {
            console.log('Remove invoice fee');

            return $.ajax({
                type: 'POST',
                url: WC_Gateway_Swedbank_Pay_Checkout_Invoice.ajax_url,
                data: {
                    action: 'sb_invoice_unset_fee',
                    nonce: WC_Gateway_Swedbank_Pay_Checkout_Invoice.nonce
                },
                dataType: 'json'
            }).done(function () {
                // Update checkout
                if ( update_checkout ) {
                    sb_invoice_fee.update_checkout();
                }
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
        window.sb_invoice_fee.init();
    });
});

