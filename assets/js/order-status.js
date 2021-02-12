jQuery( function( $ ) {
    'use strict';

    window.wc_sb_checkout_order_status = {
        xhr: false,
        attempts: 0,

        /**
         * Initialize the checking
         */
        init: function() {
            this.checkPayment( function ( err, data ) {
                var status_elm = $( '#order-status-checking' ),
                    success_elm = $( '#order-success' ),
                    failed_elm = $( '#order-failed' );

                switch ( data.state ) {
                    case 'paid':
                        status_elm.hide();
                        success_elm.show();
                        break;
                    case 'failed':
                    case 'aborted':
                        status_elm.hide();
                        failed_elm.append("<p>" + data.message + "</p>");
                        failed_elm.show();
                        break;
                    default:
                        window.wc_sb_checkout_order_status.attempts++;

                        if ( window.wc_sb_checkout_order_status.attempts > 6) {
                            return;
                        }

                        setTimeout(function () {
                            window.wc_sb_checkout_order_status.init();
                        }, 10000);
                }
            } );
        },

        /**
         * Check payment
         * @return {JQueryPromise<any>}
         */
        checkPayment: function ( callback ) {
            $( '.woocommerce-order' ).block( {
                message: WC_Gateway_Swedbank_Pay_Checkout_Order_Status.check_message,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            } );

            return $.ajax( {
                type: 'POST',
                url: WC_Gateway_Swedbank_Pay_Checkout_Order_Status.ajax_url,
                data: {
                    action: 'swedbank_checkout_check_payment',
                    nonce: WC_Gateway_Swedbank_Pay_Checkout_Order_Status.nonce,
                    order_id: WC_Gateway_Swedbank_Pay_Checkout_Order_Status.order_id,
                    order_key: WC_Gateway_Swedbank_Pay_Checkout_Order_Status.order_key,
                },
                dataType: 'json'
            } ).always( function() {
                $( '.woocommerce-order' ).unblock();
            } ).done( function ( response ) {
                callback( null, response.data );
            } );
        },
    };

    $(document).ready( function () {
        window.wc_sb_checkout_order_status.init();
    } );
} );
