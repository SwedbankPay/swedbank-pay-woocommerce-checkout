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

            // Initialize Instant Checkout
            if ( wc_sb_common.isInstantCheckout() ) {
                wc_sb_checkout.initInstantCheckout();
            }
        },

        /**
         * Initialize Instant Checkout
         */
        initInstantCheckout: function () {
            console.log( 'Initialization of Instant Checkout...' );

            if ( wc_sb_common.isCheckinEnabled() ) {
                // Checkout will be loaded after checkin
            } else {
                //wc_sb_checkout.initCheckout( data.consumerProfileRef );
            }

            if ( wc_sb_common.isCheckinEnabled() ) {
                ////wc_sb_common.hideAddressFields();

                // Use saved consumerProfileRef
                let consumerProfileElm = $( '#swedbank-pay-consumer-profile' );
                if ( consumerProfileElm.length > 0 ) {
                    let reference = consumerProfileElm.data( 'reference' );
                    console.log( 'Initiate consumerProfileRef', reference );
                    //wc_sb_checkout.initCheckout( reference );
                    ////wc_sb_checkin.initCheckIn();
                } else {
                    // Initiate checkin
                    ////wc_sb_checkin.initCheckIn();
                }
            }
        },

        initCheckout: function( reference ) {
            // Show "Change shipping info" button
            $( '#change-shipping-info' ).show();

            wc_sb_checkout.form.find( '.swedbank_pay_customer_reference' ).remove();
            wc_sb_checkout.form.append( "<input type='hidden' class='swedbank_pay_customer_reference' name='swedbank_pay_customer_reference' value='" + reference + "'/>" );

            //wc_sb_checkout.form.submit();
            wc_sb_checkout.onSubmit();
        },

        isPaymentMethodChosen: function() {
            return $( '#payment_method_payex_checkout' ).is( ':checked' );
        },

        onClose: function() {
            wc_sb_common.unblock();
        },

        onReady: function() {
            console.log( 'Ready' );
        },

        onUpdateCheckout: function() {
            console.log( 'onUpdateCheckout' );

            if ( ! wc_sb_common.isInstantCheckout() ) {
                return false;
            }

            // @todo
        },

        onUpdatedCheckout: function() {
            console.log( 'onUpdatedCheckout' );

            if ( ! wc_sb_common.isInstantCheckout() ) {
                return false;
            }

            if ( wc_sb_checkout.form.is( '.processing' ) ) {
                return false;
            }

            if ( ! wc_sb_common.validateForm() ) {
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

                if ( ! wc_sb_common.validateForm() ) {
                    return false;
                }

                console.log( 'onSubmit' );

                if ( wc_sb_checkout.form.is( '.processing' ) ) {
                    return false;
                }

                $( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
                wc_sb_checkout.form.addClass( 'processing' );
                wc_sb_common.block();
                wc_sb_checkout.placeOrder()
                    .always( function ( response ) {
                        wc_sb_checkout.form.removeClass( 'processing' );
                        wc_sb_common.unblock();
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

                // Load SwedBank Pay Checkout frame
                if ( wc_sb_common.isInstantCheckout() ) {
                    // Initiate the payment menu in "Instant Checkout"
                    $( '#payment' ).hide();
                    wc_sb_checkout.initPaymentMenu( 'payment-swedbank-pay-checkout' );
                } else {
                    // Initiate the payment menu in the frame
                    console.log( 'non-instant checkout' );
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
                    wc_sb_common.logError( 'sb-place-order', response );
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
            wc_sb_common.block();

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
                    wc_sb_common.unblock();
                } )
                .fail( function( jqXHR, textStatus ) {
                    console.log( 'updateOrder error:' + textStatus );
                    //wc_sb_checkout.onError( textStatus );
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
                        if (response.messages === '') {
                            console.warn( 'Error message is empty.' );
                            return;
                        }

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

                    wc_sb_common.logError( 'sb-update-order', response );
                    //wc_sb_checkout.onError( response.messages );
                } );

                return  wc_sb_checkout.xhr;
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
    };

    $(document).ready( function () {
        wc_sb_checkout.init( $( "form.checkout, form#order_review, form#add_payment_method" ) );
    } );
});

