/* global wc_checkout_params */
jQuery( function( $ ) {
    'use strict';

    window.wc_sb_payment_url = {
        js_url: null,
        payment_url: WC_Sb_Payment_Url.payment_url,
        paymentMenu: null,
        isPaymentMenuLoaded: false,
        xhr: false,
        isLocked: false,

        /**
         * Initiate Payment Javascript
         * @param url
         * @param callback
         */
        initPaymentJS: function ( url, callback ) {
            if ( typeof callback === 'undefined' ) {
                callback = function () {};
            }

            // Destroy
            if ( window.hasOwnProperty( 'payex' ) && window.payex.hasOwnProperty( 'hostedView' ) ) {
                if ( typeof window.payex.hostedView.paymentMenu !== 'undefined' ) {
                    window.payex.hostedView.paymentMenu().close();
                }

                $( '#payment-swedbank-pay-checkout iframe' ).remove();
                //delete window.payex.hostedView;
            }

            // Destroy JS
            $( "script[src*='px.paymentmenu.client']" ).remove();

            // Load JS
            wc_sb_common.loadJs( url, function () {
                $( '#payment-swedbank-pay-checkout iframe' ).remove();

                // Initiate the payment menu in "Instant Checkout"
                $( '#payment' ).hide();
                wc_sb_payment_url.initPaymentMenu( 'payment-swedbank-pay-checkout' );

                callback()
            } );
        },

        /**
         * Initiate Payment Menu.
         * Payment Javascript must be loaded.
         *
         * @param id
         * @param callback
         */
        initPaymentMenu: function ( id, callback ) {
            console.log( 'initPaymentMenu' );

            if ( typeof callback === 'undefined' ) {
                callback = function () {};
            }

            // Load SwedBank Pay Checkout frame
            this.paymentMenu = window.payex.hostedView.paymentMenu( {
                container: id,
                culture: WC_Sb_Payment_Url.culture,
                style: WC_Sb_Payment_Url.paymentMenuStyle ? JSON.parse( WC_Sb_Payment_Url.paymentMenuStyle ) : null,
                onApplicationConfigured: function( data ) {
                    console.log( 'onApplicationConfigured' );
                    console.log( data );
                    wc_sb_payment_url.isPaymentMenuLoaded = true;
                    callback( null );
                },
                onPaymentMenuInstrumentSelected: function ( data ) {
                    console.log( 'onPaymentMenuInstrumentSelected' );
                    console.log( data );
                    //wc_sb_checkout.onPaymentMenuInstrumentSelected( data.name, data.instrument );
                },
                onPaymentCreated: function () {
                    console.log( 'onPaymentCreated' );
                },
                onPaymentCompleted: function ( data ) {
                    console.log( 'onPaymentCompleted' );
                    console.log( data );
                    self.location.href = data.redirectUrl;
                },
                onPaymentCanceled: function ( data ) {
                    console.log( 'onPaymentCanceled' );
                    console.log( data );
                    wc_sb_common.logError( 'payment-menu-cancel', data );
                },
                onPaymentFailed: function ( data ) {
                    console.log( 'onPaymentFailed' );
                    console.log( data );
                    wc_sb_common.logError( 'payment-menu-failed', data );
                    //self.location.href = data.redirectUrl;
                },
                onError: function ( data ) {
                    wc_sb_common.logError( 'payment-menu-error', data );
                    callback( data );
                }
            } );

            this.paymentMenu.open();
        },
    }

    $(document).ready( function () {
        wc_sb_payment_url.initPaymentJS( WC_Sb_Payment_Url.payment_url, function () {
            console.log( 'Payment url has been loaded.' );
        } );
    } );
});
