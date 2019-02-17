/* global wc_checkout_params */
jQuery( function( $ ) {
    'use strict';

    /**
     * Object to handle PayEx checkout payment forms.
     */
    var wc_payex_checkout = {
        /**
         * Initialize e handlers and UI state.
         */
        init: function( form ) {
            this.form         = form;
            this.form_submit  = false;

            $( this.form )
            // We need to bind directly to the click (and not checkout_place_order_payex_checkout) to avoid popup blockers
            // especially on mobile devices (like on Chrome for iOS) from blocking payex_checkout(payment_id, {}, 'open'); from opening a tab
                .on( 'click', '#place_order', this.onSubmit )

                // WooCommerce lets us return a false on checkout_place_order_{gateway} to keep the form from submitting
                .on( 'submit checkout_place_order_payex_checkout' );

            $( document.body ).on( 'checkout_error', this.resetCheckout );
            $( document.body ).on( 'updated_checkout', this.onUpdatedCheckout );
            $( document.body ).on( 'blur', this.onUpdatedCheckout );

            if ( WC_Gateway_PayEx_Checkout.instant_checkout ) {
                if ( $('#payex-consumer-profile').length > 0 ) {
                    let reference = $('#payex-consumer-profile').data( 'reference' );
                    wc_payex_checkout.onTokenCreated( reference );
                } else {
                    wc_payex_checkout.waitForLoading('payex.hostedView.consumer', function ( err ) {
                        if ( err ) {
                            console.warn( err );
                            return;
                        }

                        // Init PayEx hostedView
                        window.payex.hostedView.consumer( {
                            container: 'payex-checkin',
                            culture: WC_Gateway_PayEx_Checkout.culture,
                            onConsumerIdentified: function( data ) {
                                wc_payex_checkout.onConsumerIdentified( data );
                            },
                            onShippingDetailsAvailable: function( data ) {
                                wc_payex_checkout.onShippingDetailsAvailable( data );
                            },
                            onError: function ( data ) {
                                console.warn( data );
                                alert( data.details );
                                //wc_payex_checkout.onError( data.details );
                            }
                        } ).open();
                    });
                }
            }
        },

        isPaymentMethodChosen: function() {
            return $( '#payment_method_payex_checkout' ).is( ':checked' );
        },

        hasToken: function() {
            return 0 < $( 'input.payex_customer_reference' ).length;
        },

        block: function() {
            let form_data = wc_payex_checkout.form.data();
            if ( 1 !== form_data['blockUI.isBlocked'] ) {
                wc_payex_checkout.form.block({
                    message: null,
                    overlayCSS: {
                        background: '#fff',
                        opacity: 0.6
                    }
                });
            }
        },

        unblock: function() {
            wc_payex_checkout.form.unblock();
        },

        onClose: function() {
            wc_payex_checkout.unblock();
        },

        onReady: function() {
            console.log('Ready');
        },

        onUpdatedCheckout: function() {
            if ( wc_payex_checkout.form.is( '.processing' ) ) {
                return false;
            }

            $( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
            wc_payex_checkout.form.addClass( 'processing' );
            wc_payex_checkout.block();
            wc_payex_checkout.updateOrder()
                .always( function ( response ) {
                    wc_payex_checkout.form.removeClass( 'processing' );
                    wc_payex_checkout.unblock();
                } )
                .fail( function( jqXHR, textStatus ) {
                    wc_payex_checkout.onError( textStatus );
                } )
                .done( function ( response) {
                    console.log( response );
                    if (response.result !== 'success') {
                        // Reload page
                        if ( true === response.result.reload ) {
                            window.location.reload();
                            return;
                        }

                        // Trigger update in case we need a fresh nonce
                        if ( true === response.result.refresh ) {
                            $( document.body ).trigger( 'update_checkout' );
                        }

                        wc_payex_checkout.onError( response.messages );
                        //wc_payex_checkout.form.submit();
                        return;
                    }

                    wc_payex_checkout.loadJs( response['js_url'], function () {
                        $('#payex-checkout iframe').remove();

                        // Load PayEx Checkout frame
                        if ( WC_Gateway_PayEx_Checkout.instant_checkout ) {
                            $('#payment').hide();
                            wc_payex_checkout.initPaymentMenu('payex-checkout' );
                        }
                    } );
                } );
        },

        onSubmit: function( e ) {
            if ( wc_payex_checkout.form_submit ) {
                return true;
            }

            if ( wc_payex_checkout.isPaymentMethodChosen() ) {
                if ( typeof e !== 'undefined' ) {
                    e.preventDefault();
                }

                if ( ! wc_payex_checkout.validateForm() ) {
                    return false;
                }

                console.log('onSubmit');

                if ( wc_payex_checkout.form.is( '.processing' ) ) {
                    return false;
                }

                $( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
                wc_payex_checkout.form.addClass( 'processing' );
                wc_payex_checkout.block();
                wc_payex_checkout.placeOrder()
                    .always( function ( response ) {
                        wc_payex_checkout.form.removeClass( 'processing' );
                        wc_payex_checkout.unblock();
                    } )
                    .fail( function( jqXHR, textStatus ) {
                        wc_payex_checkout.onError( textStatus );
                    } )
                    .done( function ( response) {
                        console.log( response );
                        if (response.result !== 'success') {
                            // Reload page
                            if ( true === response.result.reload ) {
                                window.location.reload();
                                return;
                            }

                            // Trigger update in case we need a fresh nonce
                            if ( true === response.result.refresh ) {
                                $( document.body ).trigger( 'update_checkout' );
                            }

                            wc_payex_checkout.onError( response.messages );
                            return;
                        }

                        // Load PayEx Checkout frame
                        wc_payex_checkout.loadJs( response['js_url'], function () {
                            $('#payex-checkout iframe').remove();

                            if ( WC_Gateway_PayEx_Checkout.instant_checkout ) {
                                $('#payment').hide();
                                wc_payex_checkout.initPaymentMenu('payex-checkout' );
                            } else {
                                $.featherlight('<div id="payex-paymentmenu">&nbsp;</div>', {
                                    variant: 'featherlight-payex',
                                    persist: true,
                                    closeOnClick: false,
                                    closeOnEsc: false,
                                    afterOpen: function () {
                                        wc_payex_checkout.initPaymentMenu('payex-paymentmenu' );
                                    },
                                    afterClose: function () {
                                        wc_payex_checkout.form.removeClass( 'processing' ).unblock();
                                    }
                                });
                            }
                        } );
                    } );

                return false;
            }

            return true;
        },

        onTokenCreated: function( data ) {
            console.log( 'onTokenCreated', data );

            wc_payex_checkout.form.append( "<input type='hidden' class='payex_customer_reference' name='payex_customer_reference' value='" + data + "'/>" );
            //wc_payex_checkout.form.submit();
            wc_payex_checkout.onSubmit();
        },

        resetCheckout: function() {
            wc_payex_checkout.form_submit = false;
        },

        validateForm: function () {
            var $required_inputs,
                validated = true;

            // check to see if we need to validate shipping address
            if ( $( '#ship-to-different-address-checkbox' ).is( ':checked' ) ) {
                $required_inputs = $( '.woocommerce-billing-fields .validate-required, .woocommerce-shipping-fields .validate-required' ).find('input, select').not( $( '#account_password, #account_username' ) );
            } else {
                $required_inputs = $( '.woocommerce-billing-fields .validate-required' ).find('input, select').not( $( '#account_password, #account_username' ) );
            }

            if ( $required_inputs.length ) {
                $required_inputs.each( function() {
                    var $this = $( this ),
                        $parent           = $this.closest( '.form-row' ),
                        validate_required = $parent.is( '.validate-required' ),
                        validate_email    = $parent.is( '.validate-email' );

                    if ( validate_required ) {
                        if ( 'checkbox' === $this.attr( 'type' ) && ! $this.is( ':checked' ) ) {
                            validated = false;
                        } else if ( $this.val() === '' ) {
                            validated = false;
                        }
                    }

                    if ( validate_email ) {
                        if ( $this.val() ) {
                            /* https://stackoverflow.com/questions/2855865/jquery-validate-e-mail-address-regex */
                            var pattern = new RegExp(/^((([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*)|((\x22)((((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(([\x01-\x08\x0b\x0c\x0e-\x1f\x7f]|\x21|[\x23-\x5b]|[\x5d-\x7e]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(\\([\x01-\x09\x0b\x0c\x0d-\x7f]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))))*(((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(\x22)))@((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?$/i);
                            if ( ! pattern.test( $this.val()  ) ) {
                                validated = false;
                            }
                        }
                    }
                });
            }

            return validated;
        },

        waitForLoading: function ( var_string, callback ) {
            try {
                var obj = eval( var_string );
            } catch (e) {
                callback( e );
                return;
            }

            let attempts = 0;
            let timer = window.setInterval( function() {
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
            let script = document.createElement( 'script' );

            // Set script tag params
            script.setAttribute( 'src', js );
            script.setAttribute( 'type', 'text/javascript' );
            script.setAttribute( 'async', '' );
            script.addEventListener( 'load', callback, false );

            // Gets document head element
            let oHead = document.getElementsByTagName( 'head' )[0];
            if ( oHead ) {
                // Add script tag to head
                oHead.appendChild( script );
            }

            return script;
        },

        initPaymentMenu: function ( id ) {
            // Load PayEx Checkout frame
            window.payex.hostedView.paymentMenu( {
                container: id,
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
        },

        placeOrder: function () {
            let fields = $('.woocommerce-checkout').serialize();

            return $.ajax( {
                type: 'POST',
                url: WC_Gateway_PayEx_Checkout.ajax_url,
                data: {
                    action: 'payex_place_order',
                    nonce: WC_Gateway_PayEx_Checkout.nonce,
                    data: fields
                },
                dataType: 'json'
            } );
        },

        updateOrder: function () {
            let fields = $('.woocommerce-checkout').serialize();

            return $.ajax( {
                type: 'POST',
                url: WC_Gateway_PayEx_Checkout.ajax_url,
                data: {
                    action: 'payex_update_order',
                    nonce: WC_Gateway_PayEx_Checkout.nonce,
                    data: fields
                },
                dataType: 'json'
            } );
        },

        onConsumerIdentified: function ( data ) {
            console.log( 'onConsumerIdentified', data );

            return $.ajax( {
                type: 'POST',
                url: WC_Gateway_PayEx_Checkout.ajax_url,
                data: {
                    action: 'payex_checkout_customer_profile',
                    nonce: WC_Gateway_PayEx_Checkout.nonce,
                    consumerProfileRef: data.consumerProfileRef
                },
                dataType: 'json'
            } ).always( function ( response ) {
                //
            } ).done( function ( response) {
                console.log(response);
                if (!response.success) {
                    alert(response.data.message);
                    return;
                }

                // Create checkout field
                wc_payex_checkout.onTokenCreated( data.consumerProfileRef );
            } );
        },

        onShippingDetailsAvailable: function( data ) {
            console.log( 'onShippingDetailsAvailable', data );

            return $.ajax( {
                type: 'POST',
                url: WC_Gateway_PayEx_Checkout.ajax_url,
                data: {
                    action: 'payex_checkout_get_address',
                    nonce: WC_Gateway_PayEx_Checkout.nonce,
                    url: data.url
                },
                dataType: 'json'
            } ).always( function ( response ) {
                //
            } ).done( function ( response) {
                console.log(response);
                if (!response.success) {
                    alert(response.data.message);
                    return;
                }

                // Process Billing
                let data = response.data;
                $.each(data, function (key, value) {
                    ['billing', 'shipping'].forEach(function(section) {
                        let el = $('input[name="' + section + '_' + key + '"]');
                        if (el.length === 0) {
                            return;
                        }

                        el.prop('readonly', false);
                        el.closest('.form-row').removeClass('payex-locked');
                        el.val(value).change();

                        if (key === 'country' || key === 'state') {
                            let el1 = $('#' + section + '_' + key);
                            if (typeof window.Select2 !== 'undefined') {
                                el1.select2('val', value);
                            } else if (typeof $.fn.chosen !== 'undefined') {
                                // Chosen
                                el1.val(value).trigger('chosen:updated');
                                //el1.chosen().change();
                            } else {
                                el1.change();
                            }
                        }
                    });
                });

                $( document.body ).trigger( 'update_checkout' );
            } );
        },

        onError: function ( data ) {
            //wc_payex_checkout.submit_error( '<div class="woocommerce-error">' + data + '</div>' );
        },
        submit_error: function( error_message ) {
            $( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
            wc_payex_checkout.form.prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + error_message + '</div>' );
            wc_payex_checkout.form.removeClass( 'processing' ).unblock();
            wc_payex_checkout.form.find( '.input-text, select, input:checkbox' ).trigger( 'validate' ).blur();
            wc_payex_checkout.scroll_to_notices();
            $( document.body ).trigger( 'checkout_error' );
        },
        scroll_to_notices: function() {
            let scrollElement = $( '.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout' );
            if ( ! scrollElement.length ) {
                scrollElement = $( '.form.checkout' );
            }

            $.scroll_to_notices( scrollElement );
        }
    };

    $(document).ready(function () {
        wc_payex_checkout.init( $( "form.checkout, form#order_review, form#add_payment_method" ) );
    });
});

