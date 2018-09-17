jQuery(function ($) {
    'use strict';
    $(document).ready(function () {

        var wc_payex_onepage = {
            init: function() {
                wc_payex_onepage.waitForLoading( 'payex.hostedView.consumer', function ( err ) {
                    payex.hostedView.consumer( {
                        container: 'payex-checkout',
                        onConsumerIdentified: function( data ) {
                            wc_payex_onepage.onConsumerIdentified ( data );
                        }
                    } ).open();
                })
            },

            onConsumerIdentified: function ( data ) {
                console.log( data );

                // Place Order
                var xhr = $.ajax( {
                    type: 'POST',
                    url: WC_Gateway_PayEx_Checkout.action_payex_place_order,
                    data: {
                        nonce: WC_Gateway_PayEx_Checkout.nonce,
                        consumerProfileRef: data.consumerProfileRef
                    },
                    dataType: 'json'
                } ).always( function ( response ) {
                    //
                } ).done( function ( response) {
                    console.log( response );
                    if (!response.success) {
                        alert(response.data);
                        return;
                    }

                    wc_payex_onepage.loadJs( response.data.js_url, function () {
                        // Load PayEx Checkout frame
                        window.payex.hostedView.paymentMenu( {
                            container: 'payex-checkout1',
                            culture: WC_Gateway_PayEx_Checkout.culture,
                            onPaymentCreated: function () {
                                console.log( 'onPaymentCreated' );
                            },
                            onPaymentCompleted: function ( data ) {
                                console.log( 'onPaymentCompleted' );
                                console.log( data );
                                self.location.href = data.redirectUrl;
                            },
                            onPaymentFailed: function ( data ) {
                                console.log( 'onPaymentFailed' );
                                console.log( data );
                                //self.location.href = data.redirectUrl;
                            },
                            onError: function () {
                                //
                            }
                        } ).open();
                    } );
                } );
            },

            /**
             * Wait for Object Loading
             * @param var_string
             * @param callback
             */
            waitForLoading: function ( var_string, callback ) {
                var obj = eval( var_string );
                var attempts = 0;
                var timer = window.setInterval( function() {
                    if ( typeof obj !== 'undefined' ) {
                        window.clearInterval( timer );
                        callback( null, obj );
                    } else {
                        attempts++;
                        if (attempts >= 120) {
                            window.clearInterval( timer );
                            callback( 'Timeout' );
                        }
                    }
                }, 500 );
            },

            /**
             * Load JS
             * @param js
             * @param callback
             */
            loadJs: function ( js, callback ) {
                // Creates a new script tag
                var script = document.createElement( 'script' );

                // Set script tag params
                script.setAttribute( 'src', js );
                script.setAttribute( 'type', 'text/javascript' );
                script.setAttribute( 'async', true );

                if ( typeof( script.addEventListener ) !== 'undefined' ) {
                    /* FF, Chrome, Safari, Opera */
                    script.addEventListener( 'load', callback, false );
                } else {
                    /* MS IE 8+ way */
                    function loadJS_handleIeState() {
                        if ( script.readyState == 'loaded' ) {
                            callback();
                        }
                    }

                    var ret = script.attachEvent( 'onreadystatechange', loadJS_handleIeState );
                }

                // Gets document head element
                var oHead = document.getElementsByTagName( 'head' )[0];
                if ( oHead ) {
                    // Add script tag to head
                    oHead.appendChild( script );
                }
                return script;
            }
        };

        window.wc_payex_onepage = wc_payex_onepage;

    });
});