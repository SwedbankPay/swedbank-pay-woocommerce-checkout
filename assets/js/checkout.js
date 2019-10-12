/* global wc_checkout_params */
jQuery( function( $ ) {
    'use strict';

    /**
     * Object to handle PayEx checkout payment forms.
     */
    window.wc_payex_checkout = {
        js_url: null,
        xhr: false,

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

            $( document.body ).bind( 'update_checkout', this.onUpdateCheckout );
            $( document.body ).on( 'checkout_error', this.resetCheckout );
            $( document.body ).on( 'updated_checkout', this.onUpdatedCheckout );
            $( document.body ).on( 'blur', this.onUpdatedCheckout );

            $( document.body ).on( 'click', '#change-shipping-info', function () {
                // Hide "Change shipping info" button
                $( '#change-shipping-info' ).hide();

                // Show Address Fields
                wc_payex_checkout.showAddressFields();
            } );

            // Initialize Instant Checkout
            if ( wc_payex_checkout.isInstantCheckout() ) {
                wc_payex_checkout.initInstantCheckout();
            }
        },

        /**
         * Initialize Instant Checkout
         */
        initInstantCheckout: function () {
            console.log( 'Initialization of Instant Checkout...' );

         	//$( document.body ).bind( 'init_checkout', this.init_checkout );

            if ( wc_payex_checkout.isCheckinEnabled() ) {
                //wc_payex_checkout.hideAddressFields();
            }

            // Use saved consumerProfileRef
            let consumerProfileElm = $( '#payex-consumer-profile' );
            if ( consumerProfileElm.length > 0 ) {
                let reference = consumerProfileElm.data( 'reference' );
                console.log( 'Initiate consumerProfileRef', reference );
                wc_payex_checkout.initCheckout( reference );
            } else {
                // Initiate checkin

                wc_payex_checkout.initCheckIn();
            }
        },

        /**
         * Initialize CheckIn
         */
        initCheckIn: function() {
            if ( typeof payex === 'undefined') {
                return;
            }

            wc_payex_checkout.waitForLoading('payex.hostedView.consumer', function ( err ) {
                if ( err ) {
                    console.warn( err );
                    return;
                }

                // Init PayEx hostedView
                window.payex.hostedView.consumer( {
                    container: 'payex-checkin',
                    culture: WC_Gateway_PayEx_Checkout.culture,
                    style: WC_Gateway_PayEx_Checkout.checkInStyle ? JSON.parse( WC_Gateway_PayEx_Checkout.checkInStyle ) : null,
                    onConsumerIdentified: function( data ) {
                        console.log( 'hostedView: onConsumerIdentified' );
                        wc_payex_checkout.onConsumerIdentified( data );
                    },
                    onBillingDetailsAvailable: function( data ) {
                        wc_payex_checkout.onAddressDetailsAvailable( 'billing', data );
                    },
                    onShippingDetailsAvailable: function( data ) {
                        wc_payex_checkout.onAddressDetailsAvailable( 'shipping', data );
                    },
                    onError: function ( data ) {
                        wc_payex_checkout.logError( 'payex-checkin', data );
                        alert( data.details );
                        //wc_payex_checkout.onError( data.details );
                    }
                } ).open();
            });
        },

        initCheckout: function( reference ) {
            // Show "Change shipping info" button
            $( '#change-shipping-info' ).show();

            wc_payex_checkout.form.find( '.payex_customer_reference' ).remove();
            wc_payex_checkout.form.append( "<input type='hidden' class='payex_customer_reference' name='payex_customer_reference' value='" + reference + "'/>" );

            //wc_payex_checkout.form.submit();
            wc_payex_checkout.onSubmit();
        },

        isInstantCheckout() {
            return WC_Gateway_PayEx_Checkout.instant_checkout;
        },

        isCheckinEnabled() {
            return WC_Gateway_PayEx_Checkout.checkin;
        },

        isPaymentMethodChosen: function() {
            return $( '#payment_method_payex_checkout' ).is( ':checked' );
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
            console.log( 'Ready' );
        },

        onUpdateCheckout: function() {
            console.log( 'onUpdateCheckout' );

            if ( ! wc_payex_checkout.isInstantCheckout() ) {
                return false;
            }

            // @todo
        },

        onUpdatedCheckout: function() {
            console.log( 'onUpdatedCheckout' );

            if ( ! wc_payex_checkout.isInstantCheckout() ) {
                return false;
            }

            if ( wc_payex_checkout.form.is( '.processing' ) ) {
                return false;
            }

            if ( ! wc_payex_checkout.validateForm() ) {
                console.log( 'onUpdatedCheckout: Validation is failed' );
                return false;
            }

            if ( wc_payex_checkout.xhr ) {
                wc_payex_checkout.xhr.abort();
            }

            $( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
            wc_payex_checkout.form.addClass( 'processing' );
            wc_payex_checkout.block();

            wc_payex_checkout.xhr = wc_payex_checkout.updateOrder()
                .always( function ( response ) {
                    wc_payex_checkout.form.removeClass( 'processing' );
                    wc_payex_checkout.unblock();
                } )
                .fail( function( jqXHR, textStatus ) {
                    console.log( 'updateOrder error:' + textStatus );
                    wc_payex_checkout.onError( textStatus );
                } )
                .done( function ( response) {
                    console.log( response );
                    this.xhr = false;

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

                    // Refresh Payment Menu
                    wc_payex_checkout.js_url = response['js_url'];
                    wc_payex_checkout.refreshPaymentMenu();

                    // Load PayEx Checkout frame
                    //wc_payex_checkout.initPaymentJS( response['js_url'] )
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

                console.log( 'onSubmit' );

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
                        if ( response.result !== 'success' ) {
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
                        wc_payex_checkout.js_url = response['js_url'];
                        wc_payex_checkout.initPaymentJS( wc_payex_checkout.js_url );
                    } );

                return false;
            }

            return true;
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
                    if ( attempts >= 120 ) {
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
            console.log( 'Loading of ' + js );
            // Creates a new script tag
            let script = document.createElement( 'script' );

            // Set script tag params
            script.setAttribute( 'src', js );
            script.setAttribute( 'type', 'text/javascript' );
            script.setAttribute( 'async', '' );
            script.addEventListener( 'load', function () {
                console.log( 'Loaded: ' + js );
                callback();
            }, false );

            // Gets document head element
            let oHead = document.getElementsByTagName( 'head' )[0];
            if ( oHead ) {
                // Add script tag to head
                oHead.appendChild( script );
            }

            return script;
        },

        /**
         * Initiate Payment Javascript
         * @param url
         * @param callback
         */
        initPaymentJS: function ( url, callback ) {
            if ( typeof callback === 'undefined' ) {
                callback = function () {};
            }

            wc_payex_checkout.loadJs( url, function () {
                $( '#payment-payex-checkout iframe' ).remove();

                // Load PayEx Checkout frame
                if ( wc_payex_checkout.isInstantCheckout() ) {
                    $('#payment').hide();
                    wc_payex_checkout.initPaymentMenu('payment-payex-checkout' );
                } else {
                    $.featherlight( '<div id="payex-paymentmenu">&nbsp;</div>', {
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
                    } );
                }

                callback()
            } );
        },

        /**
         * Initiate Payment Menu.
         * Payment Javascript must be loaded.
         *
         * @param id
         */
        initPaymentMenu: function ( id ) {
            console.log( 'initPaymentMenu' );

            // Load PayEx Checkout frame
            this.paymentMenu = window.payex.hostedView.paymentMenu( {
                container: id,
                culture: WC_Gateway_PayEx_Checkout.culture,
                style: WC_Gateway_PayEx_Checkout.paymentMenuStyle ? JSON.parse( WC_Gateway_PayEx_Checkout.paymentMenuStyle ) : null,
                onPaymentMenuInstrumentSelected: function ( data ) {
                    console.log( 'onPaymentMenuInstrumentSelected' );
                    console.log( data );
                    wc_payex_checkout.onPaymentMenuInstrumentSelected( data.name, data.instrument );
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
                    wc_payex_checkout.logError( 'payment-menu-cancel', data );
                },
                onPaymentFailed: function ( data ) {
                    console.log( 'onPaymentFailed' );
                    console.log( data );
                    wc_payex_checkout.logError( 'payment-menu-failed', data );
                    //self.location.href = data.redirectUrl;
                },
                onError: function ( data ) {
                    wc_payex_checkout.logError( 'payment-menu-error', data );
                }
            } );

            this.paymentMenu.open();
        },

        /**
         * Refresh
         */
        refreshPaymentMenu: function() {
            console.log( 'refreshPaymentMenu' );
            if ( typeof this.paymentMenu !== 'undefined' ) {
                this.paymentMenu.refresh();
            } else {
                console.warn( 'refreshPaymentMenu: refresh workaround' );
                wc_payex_checkout.initPaymentJS( wc_payex_checkout.js_url )
            }
        },

        /**
         * Place Order
         * @return {JQueryPromise<any>}
         */
        placeOrder: function () {
            console.log( 'placeOrder' );
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
            } ).done( function ( response) {
                if (response.hasOwnProperty('result') && response.result === 'failure') {
                    wc_payex_checkout.logError('payex-place-order', response);
                }
            } );
        },

        /**
         * Update Order
         * @return {JQueryPromise<any>}
         */
        updateOrder: function () {
            console.log( 'updateOrder' );
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
            } ).done( function ( response) {
                if (response.hasOwnProperty('result') && response.result === 'failure') {
                    wc_payex_checkout.logError('payex-update-order', response);
                }
            } );
        },

        /**
         * On Consumer Identified
         * @param data
         * @returns {*}
         */
        onConsumerIdentified: function ( data ) {
            console.log( 'onConsumerIdentified', data );
            $( '#change-shipping-info' ).show();

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

                // Initiate Checkout
                wc_payex_checkout.initCheckout( data.consumerProfileRef );
            } );
        },

        /**
         * On Address Details Available
         * @param type
         * @param data
         * @returns {*}
         */
        onAddressDetailsAvailable: function( type, data ) {
            console.log( 'onAddressDetailsAvailable', type, data );

            $( '#change-shipping-info' ).show();

            return $.ajax( {
                type: 'POST',
                url: WC_Gateway_PayEx_Checkout.ajax_url,
                data: {
                    action: 'payex_checkout_get_address',
                    nonce: WC_Gateway_PayEx_Checkout.nonce,
                    type: type,
                    url: data.url
                },
                dataType: 'json'
            } ).always( function ( response ) {
                //
            } ).done( function ( response) {
                console.log(response);
                if (!response.success) {
                    wc_payex_checkout.logError('payex-address-details', response);
                    alert(response.data.message);
                    return;
                }

                // Process address
                let data = response.data;
                $.each(data, function (key, value) {
                    [type].forEach(function(section) {
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

        /**
         * On Payment Menu Instrument Selected
         * @param name
         * @param instrument
         */
        onPaymentMenuInstrumentSelected: function ( name, instrument ) {
            console.log( 'onPaymentMenuInstrumentSelected', name, instrument );
            // @todo
        },

        /**
         * Log Error
         * @param id
         * @param data
         * @returns {*}
         */
        logError: function ( id, data ) {
            console.warn( data );

            return $.ajax( {
                type: 'POST',
                url: WC_Gateway_PayEx_Checkout.ajax_url,
                data: {
                    action: 'payex_checkout_log_error',
                    nonce: WC_Gateway_PayEx_Checkout.nonce,
                    id: id,
                    data: JSON.stringify( data )
                },
                dataType: 'json'
            } );
        },

        onError: function ( data ) {
            console.log( 'onError', data );
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
        },
        hideAddressFields: function () {
            $( '.woocommerce-billing-fields__field-wrapper, .woocommerce-shipping-fields' ).hide();
        },
        showAddressFields: function () {
            $( '.woocommerce-billing-fields__field-wrapper, .woocommerce-shipping-fields' ).show();
        }
    };

    $(document).ready( function () {
        wc_payex_checkout.init( $( "form.checkout, form#order_review, form#add_payment_method" ) );
    } );
});

