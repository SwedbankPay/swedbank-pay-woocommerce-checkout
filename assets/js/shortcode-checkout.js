/* global wc_checkout_params */
/* global WC_Shortcode_Checkout */
jQuery( function( $ ) {
    'use strict';

    /**
     * Object to handle Swedbank Pay checkout payment forms.
     */
    window.wc_sb_shortcode_checkout = {
        customer_identified: null,
        customer_reference: null,
        billing_address: null,
        shipping_address: null,
        js_url: null,
        payment_url: WC_Shortcode_Checkout.payment_url,
        paymentMenu: null,
        is_payment_menu_loaded: false,
        xhr: false,
        updateTimer: null,

        /**
         * Initialize e handlers and UI state.
         */
        init: function( form ) {
            console.log( 'init' );

            var self = this;

            // Init Checkin
            if ( this.isCheckinEnabled() ) {
                this.loadCheckIn();

                // Hide Place Order
                if ( this.isCheckinRequired() ) {
                    this.hidePlaceOrder();
                }

                $( document.body ).on( 'click', '#change-address-info', function ( event ) {
                    event.preventDefault();

                    // Show Address Fields
                    self.showAddressFields();
                } );

                this.hideAddressFields();
            }

            this.form = form;
            $( this.form )
                // We need to bind directly to the click (and not checkout_place_order_payex_checkout) to avoid popup blockers
                // especially on mobile devices (like on Chrome for iOS) from blocking payex_checkout(payment_id, {}, 'open'); from opening a tab
                .on( 'click', '#place_order', {'obj': self}, this.onSubmit )

                // WooCommerce lets us return a false on checkout_place_order_{gateway} to keep the form from submitting
                .on( 'submit checkout_place_order_payex_checkout' );

            //$( document.body ).on( 'checkout_error', this.resetCheckout );
            //$( document.body ).on( 'blur', this.onUpdatedCheckout );
            //$( document.body ).on( 'updated_shipping_method', this.onUpdatedShippingMethod );

            $( document.body ).on(
                'updated_checkout',
                null,
                {'obj': self},
                this.onInitOrUpdateCheckout
            );

            this.checkPaymentUrl( function ( loaded ) {
                if ( ! loaded ) {
                    // Initialize Instant Checkout
                    self.initInstantCheckout();
                }
            } );
        },

        /**
         * Check if the Checkin is active
         * @return {boolean}
         */
        isCheckinEnabled() {
            return 'yes' === WC_Shortcode_Checkout.checkin_enabled;
        },

        /**
         * Check if the Checkin is required
         * @return {boolean}
         */
        isCheckinRequired() {
            return this.isCheckinEnabled() && 'yes' === WC_Shortcode_Checkout.checkin_required;
        },

        /**
         * Check if customer was identified
         * @return {boolean}
         */
        isCustomerIdentified() {
            return this.customer_identified === true;
        },

        /**
         * Check if the redirect method is active
         * @return {boolean}
         */
        isRedirectMethodEnabled() {
            return 'redirect' === WC_Shortcode_Checkout.redirect_method;
        },

        /**
         * Load CheckIn
         * @returns {*}
         */
        loadCheckIn: function() {
            var self = this;

            // Get `view-consumer-identification`
            return $.ajax( {
                type: 'POST',
                url: WC_Shortcode_Checkout.ajax_url,
                data: {
                    action: 'swedbank_pay_checkin',
                    nonce: WC_Shortcode_Checkout.nonce,
                },
                dataType: 'json'
            } ).done( function ( data ) {
                if ( ! data.success ) {
                    self.logError( 'sb-checkin-loader', data );
                    alert( data.details );
                    return;
                }

                // Destroy
                if ( window.hasOwnProperty( 'payex' ) && window.payex.hasOwnProperty( 'hostedView' ) ) {
                    //if ( typeof window.payex.hostedView.consumer !== 'undefined' ) {
                    //window.payex.hostedView.consumer().close();
                    //}
                }

                // Destroy JS
                //$( "script[src*='px.consumer.client']" ).remove();
                //$( '#swedbank-pay-checkin iframe' ).remove();
                wc_sb_common.loadJs( data.data, function () {
                    self.initCheckIn();
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

            var self = this;

            wc_sb_common.waitForLoading('payex.hostedView.consumer', function ( err ) {
                if ( err ) {
                    console.warn( err );
                    return;
                }

                // Init PayEx hostedView
                window.payex.hostedView.consumer( {
                    container: 'swedbank-pay-checkin',
                    culture: WC_Shortcode_Checkout.culture,
                    style: WC_Shortcode_Checkout.checkInStyle ? JSON.parse( WC_Shortcode_Checkout.checkInStyle ) : null,
                    onConsumerIdentified: function( data ) {
                        console.log( 'hostedView: onConsumerIdentified' );
                        self.onConsumerIdentified( data );
                    },
                    onNewConsumer: function( data ) {
                        console.log( 'hostedView: onNewConsumer' );
                        self.onConsumerIdentified( data );
                    },
                    onConsumerRemoved: function( data ) {
                        console.log( 'hostedView: onConsumerRemoved' );
                        console.log ( data );
                    },
                    onBillingDetailsAvailable: function( data ) {
                        self.onAddressDetailsAvailable( 'billing', data );
                    },
                    onShippingDetailsAvailable: function( data ) {
                        self.onAddressDetailsAvailable( 'shipping', data );
                    },
                    onError: function ( data ) {
                        self.logError( 'sb-checkin', data );

                        alert( data.details );
                    }
                } ).open();
            });
        },

        /**
         * On Consumer Identified
         * @param data
         */
        onConsumerIdentified: function ( data ) {
            console.log( 'onConsumerIdentified', data );

            var self = this;

            async.parallel(
                {
                    save_customer_ref: function( callback2 ) {
                        self.saveCustomerRef( data, function ( err, response ) {
                            if ( err ) {
                                alert( err );
                            }

                            callback2( null, response );
                        } );
                    },
                    init_checkout: function( callback2 ) {
                        // Tick "terms and conditions" if exists
                        let terms = $( '#terms' );
                        if ( terms.length > 0 && ! terms.prop( 'checked' ) ) {
                            terms.prop( 'checked', true )
                        }

                        self.showPlaceOrder();

                        // Set customer is identified
                        self.customer_identified = true;

                        // Add the reference to the checkout form
                        self.form.find( '.swedbank_pay_customer_reference' ).remove();
                        self.form.append( "<input type='hidden' class='swedbank_pay_customer_reference' name='swedbank_pay_customer_reference' value='" + data.consumerProfileRef + "'/>" );

                        callback2( null, [] );
                    },
                },
                function( err, results ) {
                    console.log( 'onConsumerIdentified: loaded', results );

                    // Set customer is identified
                    self.customer_identified = true;

                    // Save customer reference
                    self.customer_reference = data.consumerProfileRef;
                }
            );
        },

        /**
         * Save Customer Reference.
         *
         * @param data
         * @param callback
         */
        saveCustomerRef: function ( data, callback ) {
            $.ajax( {
                type: 'POST',
                url: WC_Shortcode_Checkout.ajax_url,
                data: {
                    action: 'swedbank_pay_checkout_customer_profile',
                    nonce: WC_Shortcode_Checkout.nonce,
                    consumerProfileRef: data.consumerProfileRef
                },
                dataType: 'json'
            } ).always( function ( response ) {
                //
            } ).done( function ( response) {
                if ( ! response.success ) {
                    callback( response.data.message, response );
                } else {
                    callback( null, response );
                }
            } );
        },

        /**
         * Fetch address.
         *
         * @param type
         * @param url
         * @param callback
         * @returns {JQueryPromise<any>}
         */
        fetchAddress: function ( type, url, callback ) {
            return $.ajax( {
                type: 'POST',
                url: WC_Shortcode_Checkout.ajax_url,
                data: {
                    action: 'swedbank_pay_checkout_get_address',
                    nonce: WC_Shortcode_Checkout.nonce,
                    type: type,
                    url: url
                },
                dataType: 'json'
            } ).always( function () {
                //
            } ).done( function ( response ) {
                console.log( response );
                if ( ! response.success ) {
                    callback( response.data.message )
                    return;
                }

                // Fill the address
                let data = response.data;
                $.each( data, function ( key, value ) {
                    [ type ].forEach( function ( section ) {
                        let el = $( 'input[name="' + section + '_' + key + '"]' );
                        if ( el.length === 0 ) {
                            return;
                        }

                        el.prop('readonly', false);
                        el.closest( '.form-row' ).removeClass( 'swedbank-pay-locked' );
                        el.val( value ).change();

                        if ( key === 'country' || key === 'state' ) {
                            let el1 = $( '#' + section + '_' + key );
                            if ( typeof window.Select2 !== 'undefined' ) {
                                el1.select2( 'val', value );
                            } else if ( typeof $.fn.chosen !== 'undefined' ) {
                                // Chosen
                                el1.val( value ).trigger( 'chosen:updated' );
                                //el1.chosen().change();
                            } else {
                                el1.change();
                            }
                        }
                    } );
                } );

                callback( null, data );
            });
        },

        /**
         * On Address Details Available
         * @param type billing or shipping
         * @param data
         * @returns {*}
         */
        onAddressDetailsAvailable: function( type, data ) {
            console.log( 'onAddressDetailsAvailable', type, data );

            var self = this;

            self.fetchAddress( type, data.url, function ( err ) {
                if ( err ) {
                    self.onError( err );
                    return;
                }

                self[ type + '_address' ] = data;

                // Show button witch allows to edit the address
                $('#swedbank-pay-checkin-edit').show();

                // Update checkout to have shipping methods
                self.enqueueCheckoutUpdate();
            } );
        },

        /**
         * Hide Address Fields on the checkout
         */
        hideAddressFields: function () {
            $( '#address-fields' ).hide();
        },
        /**
         * Show Address Fields on the checkout
         */
        showAddressFields: function () {
            $( '#address-fields' ).show();
        },

        /**
         * Initialize Instant Checkout
         */
        initInstantCheckout: function () {
            console.log( 'Initialization of Shortcode Checkout...' );
        },

        /**
         * Initialize Checkout
         * @param reference
         */
        initCheckout: function( reference ) {
            console.log( 'initCheckout' );

            // Add customer reference if exists
            if ( reference ) {
                this.form.find( '.swedbank_pay_customer_reference' ).remove();
                this.form.append( "<input type='hidden' class='swedbank_pay_customer_reference' name='swedbank_pay_customer_reference' value='" + reference + "'/>" );
            }

            var self = this;

            self.block();
            this.loadCheckout( function ( err ) {
                self.unblock();

                if ( err ) {
                    self.submit_error( '<div class="woocommerce-error">' + err + '</div>' );
                }
            } );
        },

        isPaymentMethodChosen: function() {
            //return $( '[name="payment_method"]' ).is( ':checked' );
            return true;
        },

        onSubmit: function ( event ) {
            if ( typeof event !== 'undefined' ) {
                event.preventDefault();
            }

            var self = event.data.obj;
            self.loadCheckout( function ( err ) {
                if ( err ) {
                    self.submit_error( '<div class="woocommerce-error">' + err + '</div>' );
                }
            } );
        },

        onUpdatedCheckout: function( event ) {
            console.log( 'onUpdatedCheckout' );

            var self = event.data.obj;

            if ( self.form.is( '.processing' ) ) {
                return false;
            }

            if ( ! wc_sb_common.validateForm() ) {
                console.log( 'onUpdatedCheckout: Validation is failed' );

                return false;
            }

            $( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
            self.updateOrder( function () {
                console.log( 'Order has been updated' );
            } );
        },

        onUpdatedShippingMethod: function ( event ) {
            console.log( 'onUpdatedShippingMethod' );

            var self = event.data.obj;

            if ( self.form.is( '.processing' ) ) {
                return false;
            }

            self.updateOrder( function () {
                console.log( 'Order has been updated' );
            } );
        },

        /**
         * Load Checkout.
         *
         * @param callback
         */
        loadCheckout: function( callback ) {
            console.log('loadCheckout');

            var self = this;
            let terms = $( '#terms' );

            // Validate "terms and conditions" if exists
            if ( terms.length > 0 && ! terms.prop( 'checked' ) ) {
                callback( WC_Shortcode_Checkout.terms_error );
                return;
            }

            // Verify the checkin
            if ( self.isCheckinEnabled() ) {
                if ( self.isCheckinRequired() && ! self.isCustomerIdentified() ) {
                    callback( WC_Shortcode_Checkout.needs_checkin );
                    return;
                }
            }

            // Validate the checkout form
            if ( ! wc_sb_common.validateForm() ) {
                console.log( 'The checkout form validation is failed.' );

                callback( WC_Shortcode_Checkout.checkin_error );
                return;
            }

            if ( ! self.isPaymentMethodChosen() ) {
                console.log( 'payex_checkout must be chosen.' );

                callback( 'Please select payment method.' );
                return;
            }

            $( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
            self.form.addClass( 'processing' );
            self.block();

            if ( self.xhr ) {
                self.xhr.abort();
            }

            // Place WooCommerce Order
            self.xhr = self.placeOrder()
                .always( function ( response ) {
                    self.form.removeClass( 'processing' );
                    self.unblock();
                } )
                .fail( function( jqXHR, textStatus ) {
                    callback( textStatus );
                } )
                .done( function ( response) {
                    console.log( response );

                    // Reload page
                    if ( response.hasOwnProperty('reload') && true === response.reload ) {
                        window.location.reload();
                        return;
                    }

                    // Trigger update in case we need a fresh nonce
                    if ( true === response.result.refresh ) {
                        $( document.body ).trigger( 'update_checkout' );
                        callback( null, response );
                        return;
                    }

                    if ( response.result !== 'success' ) {
                        callback( response.messages );
                        return;
                    }

                    if ( self.isRedirectMethodEnabled() ) {
                        // Redirect to the payment gateway page
                        console.log( 'Redirecting to ' + response['redirect_url'] );
                        window.location.href = response['redirect_url'];

                        callback( null, response );
                    } else {
                        // Load SwedBank Pay Checkout frame
                        self.js_url = response['js_url'];
                        self.initPaymentJS( self.js_url, function () {
                            // Hide "Place order"
                            self.hidePlaceOrder();

                            callback( null, response );
                        } );
                    }
                } );

            return false;
        },

        resetCheckout: function() {
            //
        },

        /**
         * Initiate Payment Javascript
         * @param url
         * @param callback
         */
        initPaymentJS: function ( url, callback ) {
            var self = this;
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

                // Load SwedBank Pay Checkout frame
                // Initiate the payment menu in "Instant Checkout"
                $( '#payment' ).hide();
                self.initPaymentMenu( 'payment-swedbank-pay-checkout', function () {
                    callback();
                } );
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

            var self = this;

            if ( typeof callback === 'undefined' ) {
                callback = function () {};
            }

            // Load SwedBank Pay Checkout frame
            this.paymentMenu = window.payex.hostedView.paymentMenu( {
                container: id,
                culture: WC_Shortcode_Checkout.culture,
                style: WC_Shortcode_Checkout.paymentMenuStyle ? JSON.parse( WC_Shortcode_Checkout.paymentMenuStyle ) : null,
                onApplicationConfigured: function( data ) {
                    console.log( 'onApplicationConfigured' );
                    console.log( data );
                    self.is_payment_menu_loaded = true;
                    callback( null );
                },
                onPaymentMenuInstrumentSelected: function ( data ) {
                    console.log( 'onPaymentMenuInstrumentSelected' );
                    console.log( data );
                    self.onPaymentMenuInstrumentSelected( data.name, data.instrument );
                },
                onPaymentCreated: function () {
                    console.log( 'onPaymentCreated' );
                },
                onPaymentCompleted: function ( data ) {
                    console.log( 'onPaymentCompleted' );
                    console.log( data );
                    window.location.href = data.redirectUrl;
                },
                onPaymentCanceled: function ( data ) {
                    console.log( 'onPaymentCanceled' );
                    console.log( data );
                    self.logError( 'payment-menu-cancel', data );
                },
                onPaymentFailed: function ( data ) {
                    console.log( 'onPaymentFailed' );
                    console.log( data );
                    self.logError( 'payment-menu-failed', data );
                    //self.location.href = data.redirectUrl;
                },
                onError: function ( data ) {
                    self.logError( 'payment-menu-error', data );
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
            if ( typeof this.paymentMenu !== 'undefined' && this.paymentMenu ) {
                if ( this.paymentMenu.hasOwnProperty( 'refresh' ) && typeof this.paymentMenu.refresh === 'function' ) {
                    this.paymentMenu.refresh();
                } else {
                    console.warn( 'refreshPaymentMenu: paymentMenu doesn\'t support refresh' );
                }
            }
        },

        /**
         * Place Order
         * @return {JQueryPromise<any>}
         */
        placeOrder: function () {
            console.log( 'placeOrder' );

            var self = this;
            let fields = $('.woocommerce-checkout').serialize();

            return $.ajax( {
                type: 'POST',
                url: WC_Shortcode_Checkout.ajax_url,
                data: {
                    action: 'sbp_submit_order',
                    nonce: WC_Shortcode_Checkout.nonce,
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
                    self.logError( 'sb-place-order', response );
                    self.onError( response.messages );
                }
            } );
        },

        /**
         * On Init Or Update Checkout.
         *
         * @param event
         * @returns {boolean}
         */
        onInitOrUpdateCheckout: function ( event ) {
            console.log( 'onInitOrUpdateCheckout' );

            var self = this;
            if ( typeof event !== 'undefined' ) {
                self = event.data.obj;
            }

            if ( ! wc_sb_common.validateForm() ) {
                console.log( 'onInitOrUpdateCheckout: Validation is failed' );

                return false;
            }

            if ( typeof self.paymentMenu !== 'undefined' && self.paymentMenu ) {
                self.updateOrder( function () {
                    console.log( 'Order has been updated' );
                } );
            } else {
                // Init Checkout
                if ( self.isCheckinRequired() && ! self.customer_reference ) {
                    console.log( 'Checkin is required.' );
                } else {
                    self.initCheckout( self.customer_reference );
                }
            }
        },

        /**
         * Update Order
         * @param callback
         * @return {JQueryPromise<any>}
         */
        updateOrder: function ( callback ) {
            console.log( 'updateOrder' );
            var self = this;

            let fields = $('.woocommerce-checkout').serialize();

            self.form.addClass( 'processing' );
            self.block();

            if ( self.xhr ) {
                self.xhr.abort();
            }

            self.xhr = $.ajax( {
                type: 'POST',
                url: WC_Shortcode_Checkout.ajax_url,
                data: {
                    action: 'sbp_update_order',
                    nonce: WC_Shortcode_Checkout.nonce,
                    data: fields
                },
                dataType: 'json'
            } )
                .always( function ( response ) {
                    //self.xhr = false;
                    self.form.removeClass( 'processing' );
                    self.unblock();
                } )
                .fail( function( jqXHR, textStatus ) {
                    console.log( 'updateOrder error:' + textStatus );
                    callback( textStatus );
                } )
                .done( function ( response) {
                    console.log( response );
                    callback( null, response );

                    // Update is successful
                    if ( response.hasOwnProperty('result') && response.result === 'success' ) {
                        // Refresh Payment Menu
                        self.refreshPaymentMenu();

                        return;
                    }

                    // Errors
                    if ( response.hasOwnProperty('result') && response.result === 'failure' ) {
                        if ( response.messages === '' ) {
                            console.warn( 'Error message is empty.' );
                            return;
                        }

                        // SwedBank Payment returns error that Order update is not available
                        if ( response.messages.indexOf( 'Order update is not available.' ) > -1 ) {
                            alert( 'Order update is not available.' );
                            return;
                        }
                    }

                    // Reload page
                    if ( response.hasOwnProperty('reload') && true === response.reload ) {
                        window.location.reload();

                        return;
                    }

                    // Trigger update in case we need a fresh nonce
                    if ( response.hasOwnProperty( 'result' ) && true === response.result.refresh ) {
                        $( document.body ).trigger( 'update_checkout' );
                    }
                } );

            return self.xhr;
        },

        /**
         * Enqueue Checkout Update.
         */
        enqueueCheckoutUpdate: function () {
            var self = this;

            if ( self.updateTimer ) {
                window.clearTimeout( self.updateTimer );
            }

            self.updateTimer = setTimeout( function () {
                console.log( 'Update checkout...' );
                //self.onInitOrUpdateCheckout();
                $( document.body ).trigger( 'update_checkout' );
            }, 1000 );
        },

        /**
         * On Payment Menu Instrument Selected
         * @param name
         * @param instrument
         */
        onPaymentMenuInstrumentSelected: function ( name, instrument ) {
            console.log( 'onPaymentMenuInstrumentSelected', name, instrument );
            //$( document.body ).trigger( 'sb_payment_menu_instrument_selected', [name, instrument] );

            var self = this;

            // Apply/remove additional fees
            async.parallel(
                {
                    invoice_fee: function( callback2 ) {
                        // Apply or Remove invoice fee
                        if ( 'no' === WC_Shortcode_Checkout.invoice_fee_enabled ) {
                            callback2( null, false );

                            return;
                        }

                        self.block();

                        var xhr;
                        if ( instrument === 'Invoice' ) {
                            xhr = sb_invoice_fee.apply_fee( false );
                        } else {
                            xhr = sb_invoice_fee.remove_fee( false );
                        }

                        xhr.done( function () {
                            callback2( null, true );
                        } );
                    },
                    carpay_discount: function( callback2 ) {
                        // Apply or Remove CarPay discount
                        if ( 'no' === WC_Shortcode_Checkout.carpay_enabled ) {
                            callback2( null, false );

                            return;
                        }

                        self.block();

                        var xhr;
                        if ( name === 'CarPay' ) {
                            // Apply discount
                            xhr = sb_carpay.apply_discount( false );
                        } else {
                            // Remove discount
                            xhr = sb_carpay.remove_discount( false );
                        }

                        xhr.done( function () {
                            callback2( null, true );
                        } );
                    },
                },
                function( err, results ) {
                    if ( results.invoice_fee || results.carpay_discount ) {
                        self.unblock();

                        // Update checkout
                        $( document.body ).trigger( 'update_checkout' );
                        //$( document.body ).trigger( 'update' );
                    }
                }
            );
        },

        onError: function ( data ) {
            console.log( 'onError', data );
            this.submit_error( '<div class="woocommerce-error">' + data + '</div>' );
        },

        submit_error: function( error_message ) {
            $( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
            this.form.prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + error_message + '</div>' );
            this.form.removeClass( 'processing' ).unblock();
            this.form.find( '.input-text, select, input:checkbox' ).trigger( 'validate' ).blur();
            this.scroll_to_notices();
            $( document.body ).trigger( 'checkout_error' );
        },

        scroll_to_notices: function() {
            let scrollElement = $( '.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout' );
            if ( ! scrollElement.length ) {
                scrollElement = $( '.form.checkout' );
            }

            $.scroll_to_notices( scrollElement );
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
                url: WC_Shortcode_Checkout.ajax_url,
                data: {
                    action: 'swedbank_pay_checkout_log_error',
                    nonce: WC_Shortcode_Checkout.nonce,
                    id: id,
                    data: JSON.stringify( data )
                },
                dataType: 'json'
            } );
        },

        /**
         * Check the payment url.
         *
         * @param callback
         */
        checkPaymentUrl: function ( callback ) {
            if ( !! ( new URLSearchParams( document.location.search ) ).get( 'payment_url' ) && this.payment_url ) {
                // Lock the checkout
                $( '.woocommerce-checkout' ).block( {
                    message: null,
                    overlayCSS: {
                        background: '#fff',
                        opacity: 0.6
                    }
                } );

                // Init Payment JS
                this.initPaymentJS( this.payment_url, function () {
                    console.log( 'Payment url has been loaded.' );
                    callback( true );
                } );
            } else {
                callback( false );
            }
        },

        /**
         * Block the checkout form
         */
        block: function() {
            var self = this;
            if ( self.form ) {
                let form_data = self.form.data();
                if ( 1 !== form_data['blockUI.isBlocked'] ) {
                    self.form.block({
                        message: null,
                        overlayCSS: {
                            background: '#fff',
                            opacity: 0.6
                        }
                    });
                }
            }
        },

        /**
         * Unblock the checkout form
         */
        unblock: function() {
            if ( this.form ) {
                this.form.unblock();
            }
        },

        /**
         * Hide "Place order" button.
         */
        hidePlaceOrder: function () {
            $('#place_order').hide();
            $('.place-order').hide();

            // Tick "terms and conditions" if exists
            let terms = $( '#terms' );
            if ( terms.length > 0 && ! terms.prop( 'checked' ) ) {
                terms.prop( 'checked', true )
            }
        },

        /**
         * Show "Place order" button.
         */
        showPlaceOrder: function () {
            $('#place_order').show();
            $('.place-order').show();
        }
    };

    $(document).ready( function () {
        wc_sb_shortcode_checkout.init( $( "form.checkout, form#order_review, form#add_payment_method" ) );
    } );
});

