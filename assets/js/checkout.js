/* global wc_checkout_params */
jQuery( function( $ ) {
    'use strict';

    // wc_checkout_params is required to continue, ensure the object exists
    if ( typeof wc_checkout_params === 'undefined' ) {
        return false;
    }

    $(document).ajaxComplete( function ( event, xhr, settings ) {
        if ( settings.url === wc_checkout_params.checkout_url ) {
            var data = xhr.responseText;

            // Parse
			try {
                var result = $.parseJSON( data );
			} catch ( e ) {
				return false;
            }

            // Check is response from payex checkout method
            if ( ! result.hasOwnProperty( 'is_payex_checkout' ) ) {
                return false;
            }

			var checkout_form = $( 'form.checkout' );

            // Add script code
            checkout_form.append('<script src="' + result.js_url + '"></script>');

            // Wait for script loading
            var timer = window.setInterval( function() {
                if ( typeof window.payex !== 'undefined' ) {
                    window.clearInterval( timer );

                    $.featherlight('<div id="payex-paymentmenu">&nbsp;</div>', {
                        variant: 'featherlight-payex',
                        persist: true,
                        closeOnClick: false,
                        closeOnEsc: false,
                        afterOpen: function () {
                            // Load PayEx Checkout frame
                            var config = {
                                container: 'payex-paymentmenu',
                                culture: WC_Gateway_PayEx_Checkout.culture,
                                onPaymentCreated: function () {
                                    console.log('onPaymentCreated');
                                },
                                onPaymentCompleted: function ( data ) {
                                    console.log('onPaymentCompleted');
                                    console.log(data);
                                    self.location.href = data.redirectUrl;
                                },
                                onPaymentFailed: function ( data ) {
                                    console.log('onPaymentFailed');
                                    console.log(data);
                                    //self.location.href = data.redirectUrl;
                                },
                                onError: function () {
                                    //
                                }
                            };
                            window.payex.hostedView.paymentMenu( config ).open();
                        },
                        afterClose: function () {
                            checkout_form.removeClass( 'processing' ).unblock();
                        }
                    });
                }
            }, 500 );
        }
    } );
});

