/* global wc_checkout_params */
jQuery( function( $ ) {
    'use strict';

    /**
     * Object to handle Swedbank Pay checkout payment forms.
     */
    window.wc_sb_checkout = {
        js_url: null,
        paymentMenu: null,
        isPaymentMenuLoaded: false,
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
                wc_sb_checkout.showAddressFields();
            } );

            $( document.body ).on( 'change', '#checkin_country', function () {
                wc_sb_checkout.loadCheckIn( $(this).val() );
            } );

            // Initialize Instant Checkout
            if ( wc_sb_checkout.isInstantCheckout() ) {
                wc_sb_checkout.initInstantCheckout();
            }
        },

        /**
         * Initialize Instant Checkout
         */
        initInstantCheckout: function () {
            console.log( 'Initialization of Instant Checkout...' );

            if ( wc_sb_checkout.isCheckinEnabled() ) {
                wc_sb_checkout.hideAddressFields();
            }

            // Use saved consumerProfileRef
            let consumerProfileElm = $( '#swedbank-pay-consumer-profile' );
            if ( consumerProfileElm.length > 0 ) {
                let reference = consumerProfileElm.data( 'reference' );
                console.log( 'Initiate consumerProfileRef', reference );
                //wc_sb_checkout.initCheckout( reference );
                wc_sb_checkout.initCheckIn();
            } else {
                // Initiate checkin

                wc_sb_checkout.initCheckIn();
            }
        },

        /**
         * Load CheckIn
         * @param country
         * @returns {*}
         */
        loadCheckIn: function( country ) {
            return $.ajax( {
                type: 'POST',
                url: WC_Gateway_Swedbank_Pay_Checkout.ajax_url,
                data: {
                    action: 'swedbank_pay_checkin',
                    nonce: WC_Gateway_Swedbank_Pay_Checkout.nonce,
                    country: country
                },
                dataType: 'json'
            } ).done( function ( data ) {
                if ( ! data.success ) {
                    wc_sb_checkout.logError( 'sb-checkin-loader', data );
                    alert( data.details );
                    return;
                }

                // Destroy
                if ( window.hasOwnProperty( 'payex' ) && window.payex.hasOwnProperty( 'hostedView' ) ) {
                    if ( typeof window.payex.hostedView.consumer !== 'undefined' ) {
                        window.payex.hostedView.consumer().close();
                    }
                }

                // Destroy JS
                $( "script[src*='px.consumer.client']" ).remove();
                $( '#swedbank-pay-checkin iframe' ).remove();
                wc_sb_checkout.loadJs( data.data, function () {
                    wc_sb_checkout.initCheckIn();
                } );
            } );
        },

        /**
         * Initialize CheckIn
         */
        initCheckIn: function() {
            if ( typeof payex === 'undefined') {
                return;
            }

            wc_sb_checkout.waitForLoading('payex.hostedView.consumer', function ( err ) {
                if ( err ) {
                    console.warn( err );
                    return;
                }

                // Init PayEx hostedView
                window.payex.hostedView.consumer( {
                    container: 'swedbank-pay-checkin',
                    culture: WC_Gateway_Swedbank_Pay_Checkout.culture,
                    style: WC_Gateway_Swedbank_Pay_Checkout.checkInStyle ? JSON.parse( WC_Gateway_Swedbank_Pay_Checkout.checkInStyle ) : null,
                    onConsumerIdentified: function( data ) {
                        console.log( 'hostedView: onConsumerIdentified' );
                        wc_sb_checkout.onConsumerIdentified( data );
                    },
                    onNewConsumer: function( data ) {
                        console.log( 'hostedView: onNewConsumer' );
                        wc_sb_checkout.onConsumerIdentified( data );
                    },
                    onConsumerRemoved: function( data ) {
                        console.log( 'hostedView: onConsumerRemoved' );
                    },
                    onBillingDetailsAvailable: function( data ) {
                        wc_sb_checkout.onAddressDetailsAvailable( 'billing', data );
                    },
                    onShippingDetailsAvailable: function( data ) {
                        if ( WC_Gateway_Swedbank_Pay_Checkout.needs_shipping_address ||
                            WC_Gateway_Swedbank_Pay_Checkout.ship_to_billing_address_only
                        ) {
                            wc_sb_checkout.onAddressDetailsAvailable( 'billing', data );
                        }

                        wc_sb_checkout.onAddressDetailsAvailable( 'shipping', data );
                    },
                    onError: function ( data ) {
                        wc_sb_checkout.logError( 'sb-checkin', data );
                        alert( data.details );
                        wc_sb_checkout.onError( data.details );
                    }
                } ).open();
            });
        },

        initCheckout: function( reference ) {
            // Show "Change shipping info" button
            $( '#change-shipping-info' ).show();

            wc_sb_checkout.form.find( '.swedbank_pay_customer_reference' ).remove();
            wc_sb_checkout.form.append( "<input type='hidden' class='swedbank_pay_customer_reference' name='swedbank_pay_customer_reference' value='" + reference + "'/>" );

            //wc_sb_checkout.form.submit();
            wc_sb_checkout.onSubmit();
        },

        isInstantCheckout() {
            return WC_Gateway_Swedbank_Pay_Checkout.instant_checkout;
        },

        isCheckinEnabled() {
            return WC_Gateway_Swedbank_Pay_Checkout.checkin;
        },

        isPaymentMethodChosen: function() {
            return $( '#payment_method_payex_checkout' ).is( ':checked' );
        },

        block: function() {
            let form_data = wc_sb_checkout.form.data();
            if ( 1 !== form_data['blockUI.isBlocked'] ) {
                wc_sb_checkout.form.block({
                    message: null,
                    overlayCSS: {
                        background: '#fff',
                        opacity: 0.6
                    }
                });
            }
        },

        unblock: function() {
            wc_sb_checkout.form.unblock();
        },

        onClose: function() {
            wc_sb_checkout.unblock();
        },

        onReady: function() {
            console.log( 'Ready' );
        },

        onUpdateCheckout: function() {
            console.log( 'onUpdateCheckout' );

            if ( ! wc_sb_checkout.isInstantCheckout() ) {
                return false;
            }

            // @todo
        },

        onUpdatedCheckout: function() {
            console.log( 'onUpdatedCheckout' );

            if ( ! wc_sb_checkout.isInstantCheckout() ) {
                return false;
            }

            if ( wc_sb_checkout.form.is( '.processing' ) ) {
                return false;
            }

            if ( ! wc_sb_checkout.validateForm() ) {
                console.log( 'onUpdatedCheckout: Validation is failed' );
                return false;
            }

            $( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
            wc_sb_checkout.updateOrder();
        },

        onSubmit: function( e ) {
            if ( wc_sb_checkout.form_submit ) {
                return true;
            }

            if ( wc_sb_checkout.isPaymentMethodChosen() ) {
                if ( typeof e !== 'undefined' ) {
                    e.preventDefault();
                }

                if ( ! wc_sb_checkout.validateForm() ) {
                    return false;
                }

                console.log( 'onSubmit' );

                if ( wc_sb_checkout.form.is( '.processing' ) ) {
                    return false;
                }

                $( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
                wc_sb_checkout.form.addClass( 'processing' );
                wc_sb_checkout.block();
                wc_sb_checkout.placeOrder()
                    .always( function ( response ) {
                        wc_sb_checkout.form.removeClass( 'processing' );
                        wc_sb_checkout.unblock();
                    } )
                    .fail( function( jqXHR, textStatus ) {
                        wc_sb_checkout.onError( textStatus );
                    } )
                    .done( function ( response) {
                        console.log( response );
                        if ( response.result !== 'success' ) {
                            // Reload page
                            if ( response.hasOwnProperty('reload') && true === response.reload ) {
                                window.location.reload();
                                return;
                            }

                            // Trigger update in case we need a fresh nonce
                            if ( true === response.result.refresh ) {
                                $( document.body ).trigger( 'update_checkout' );
                            }

                            wc_sb_checkout.onError( response.messages );
                            return;
                        }

                        // Load SwedBank Pay Checkout frame
                        wc_sb_checkout.js_url = response['js_url'];
                        wc_sb_checkout.initPaymentJS( wc_sb_checkout.js_url );
                    } );

                return false;
            }

            return true;
        },

        resetCheckout: function() {
            wc_sb_checkout.form_submit = false;
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
            // Creates a new script tag
            let script = document.createElement( 'script' );

            // Set script tag params
            script.setAttribute( 'src', js );
            script.setAttribute( 'type', 'text/javascript' );
            script.setAttribute( 'async', '' );
            script.addEventListener( 'load', function () {
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
            wc_sb_checkout.loadJs( url, function () {
                $( '#payment-swedbank-pay-checkout iframe' ).remove();

                // Load SwedBank Pay Checkout frame
                if ( wc_sb_checkout.isInstantCheckout() ) {
                    $( '#payment' ).hide();
                    wc_sb_checkout.initPaymentMenu( 'payment-swedbank-pay-checkout' );
                } else {
                    $.featherlight( '<div id="swedbank-pay-paymentmenu">&nbsp;</div>', {
                        variant: 'featherlight-swedbank-pay',
                        persist: true,
                        closeOnClick: false,
                        closeOnEsc: false,
                        afterOpen: function () {
                            wc_sb_checkout.initPaymentMenu( 'swedbank-pay-paymentmenu' );
                        },
                        afterClose: function () {
                            wc_sb_checkout.form.removeClass( 'processing' ).unblock();
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
                culture: WC_Gateway_Swedbank_Pay_Checkout.culture,
                style: WC_Gateway_Swedbank_Pay_Checkout.paymentMenuStyle ? JSON.parse( WC_Gateway_Swedbank_Pay_Checkout.paymentMenuStyle ) : null,
                onApplicationConfigured: function( data ) {
                    console.log( 'onApplicationConfigured' );
                    console.log( data );
                    wc_sb_checkout.isPaymentMenuLoaded = true;
                    callback( null );
                },
                onPaymentMenuInstrumentSelected: function ( data ) {
                    console.log( 'onPaymentMenuInstrumentSelected' );
                    console.log( data );
                    wc_sb_checkout.onPaymentMenuInstrumentSelected( data.name, data.instrument );
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
                    wc_sb_checkout.logError( 'payment-menu-cancel', data );
                },
                onPaymentFailed: function ( data ) {
                    console.log( 'onPaymentFailed' );
                    console.log( data );
                    wc_sb_checkout.logError( 'payment-menu-failed', data );
                    //self.location.href = data.redirectUrl;
                },
                onError: function ( data ) {
                    wc_sb_checkout.logError( 'payment-menu-error', data );
                    callback( data );
                }
            } );

            this.paymentMenu.open();
        },

        /**
         * Refresh
         */
        refreshPaymentMenu: function() {
            console.log( 'refreshPaymentMenu' );
            if ( typeof this.paymentMenu !== 'undefined' &&
                this.paymentMenu &&
                this.paymentMenu.hasOwnProperty( 'refresh' ) &&
                typeof this.paymentMenu.refresh === 'function' )
            {
                this.paymentMenu.refresh();
            } else {
                console.warn( 'refreshPaymentMenu: refresh workaround' );
                //wc_sb_checkout.initPaymentJS( wc_sb_checkout.js_url )
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
                url: WC_Gateway_Swedbank_Pay_Checkout.ajax_url,
                data: {
                    action: 'swedbank_pay_place_order',
                    nonce: WC_Gateway_Swedbank_Pay_Checkout.nonce,
                    data: fields
                },
                dataType: 'json'
            } ).done( function ( response ) {
                // Reload page
                if ( response.hasOwnProperty('reload') && true === response.reload ) {
                    window.location.reload();
                    return;
         		}

                if ( response.hasOwnProperty('result') && response.result === 'failure' ) {
                    wc_sb_checkout.logError( 'sb-place-order', response );
                    wc_sb_checkout.onError( response.messages );
                }
            } );
        },

        /**
         * Update Order
         * @param compatibility
         * @return {JQueryPromise<any>}
         */
        updateOrder: function ( compatibility ) {
            console.log( 'updateOrder' );
            let fields = $('.woocommerce-checkout').serialize();

            if ( typeof compatibility === 'undefined' ) {
                compatibility = false;
            }

            fields += '&compatibility=' + compatibility;

            wc_sb_checkout.form.addClass( 'processing' );
            wc_sb_checkout.block();

            if ( wc_sb_checkout.xhr ) {
                wc_sb_checkout.xhr.abort();
            }

            wc_sb_checkout.xhr = $.ajax( {
                type: 'POST',
                url: WC_Gateway_Swedbank_Pay_Checkout.ajax_url,
                data: {
                    action: 'swedbank_pay_update_order',
                    nonce: WC_Gateway_Swedbank_Pay_Checkout.nonce,
                    data: fields
                },
                dataType: 'json'
            } )
                .always( function ( response ) {
                    wc_sb_checkout.xhr = false;
                    wc_sb_checkout.form.removeClass( 'processing' );
                    wc_sb_checkout.unblock();
                } )
                .fail( function( jqXHR, textStatus ) {
                    console.log( 'updateOrder error:' + textStatus );
                    wc_sb_checkout.onError( textStatus );
                } )
                .done( function ( response) {
                    console.log( response );

                    if ( response.hasOwnProperty('result') && response.result === 'success' ) {
                        // Refresh Payment Menu
                        wc_sb_checkout.js_url = response['js_url'];
                        wc_sb_checkout.refreshPaymentMenu();

                        return;
                    }

                    if ( response.hasOwnProperty('result') && response.result === 'failure' ) {
                        // SwedBank Payment returns error that Order update is not available
                        if ( response.messages.indexOf( 'Order update is not available.' ) > -1 && ! compatibility ) {
                            // Force reload
                            console.warn( 'refreshPaymentMenu: refresh workaround. Force reload.' );
                            wc_sb_checkout.updateOrder( true ).done( function ( response ) {
                                if ( response.hasOwnProperty('js_url') ) {
                                    wc_sb_checkout.initPaymentJS( response.js_url );
                                }
                            } );

                            return;
                        }
                    }

                    // Reload page
                    if ( response.hasOwnProperty('reload') && true === response.reload ) {
                        window.location.reload();

                        return;
                    }

                    // Trigger update in case we need a fresh nonce
                    if ( true === response.result.refresh ) {
                        $( document.body ).trigger( 'update_checkout' );
                    }

                    wc_sb_checkout.logError( 'sb-update-order', response );
                    wc_sb_checkout.onError( response.messages );
                } );

                return  wc_sb_checkout.xhr;
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
                url: WC_Gateway_Swedbank_Pay_Checkout.ajax_url,
                data: {
                    action: 'swedbank_pay_checkout_customer_profile',
                    nonce: WC_Gateway_Swedbank_Pay_Checkout.nonce,
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
                wc_sb_checkout.initCheckout( data.consumerProfileRef );
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

            wc_sb_checkout.block();
            return $.ajax( {
                type: 'POST',
                url: WC_Gateway_Swedbank_Pay_Checkout.ajax_url,
                data: {
                    action: 'swedbank_pay_checkout_get_address',
                    nonce: WC_Gateway_Swedbank_Pay_Checkout.nonce,
                    type: type,
                    url: data.url
                },
                dataType: 'json'
            } ).always( function ( response ) {
                wc_sb_checkout.unblock();
            } ).done( function ( response) {
                console.log(response);
                if (!response.success) {
                    wc_sb_checkout.logError('sb-address-details', response);
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
                        el.closest('.form-row').removeClass('swedbank-pay-locked');
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
            $( document.body ).trigger( 'sb_payment_menu_instrument_selected', [name, instrument] );
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
                url: WC_Gateway_Swedbank_Pay_Checkout.ajax_url,
                data: {
                    action: 'swedbank_pay_checkout_log_error',
                    nonce: WC_Gateway_Swedbank_Pay_Checkout.nonce,
                    id: id,
                    data: JSON.stringify( data )
                },
                dataType: 'json'
            } );
        },

        onError: function ( data ) {
            console.log( 'onError', data );
            wc_sb_checkout.submit_error( '<div class="woocommerce-error">' + data + '</div>' );
        },
        submit_error: function( error_message ) {
            $( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
            wc_sb_checkout.form.prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + error_message + '</div>' );
            wc_sb_checkout.form.removeClass( 'processing' ).unblock();
            wc_sb_checkout.form.find( '.input-text, select, input:checkbox' ).trigger( 'validate' ).blur();
            wc_sb_checkout.scroll_to_notices();
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
        wc_sb_checkout.init( $( "form.checkout, form#order_review, form#add_payment_method" ) );
    } );
});

