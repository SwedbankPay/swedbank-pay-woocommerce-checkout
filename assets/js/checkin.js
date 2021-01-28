/* global wc_checkout_params */
jQuery( function( $ ) {
    'use strict';

    window.wc_sb_checkin = {
        /**
         * Initialize
         */
        init: function() {
            if ( this.isCheckinEnabled() ) {
                var self = this;
                $( document.body ).on( 'click change', '#checkin_country', function () {
                    self.loadCheckIn( $( this ).val() );
                } );

                // Select the first item
                let checkin = $( '#checkin_country' );
                if ( checkin.length > 0 ) {
                    self.loadCheckIn( checkin.val() );
                } else {
                    self.loadCheckIn( WC_Gateway_Swedbank_Pay_Checkin.checkin_country );
                }

                $( document.body ).on( 'click', '#change-address-info', function ( event ) {
                    event.preventDefault();

                    // Show Address Fields
                    self.showAddressFields();
                } );

                self.hideAddressFields();
            }
        },

        /**
         * Check if the Checkin is active
         * @return {boolean}
         */
        isCheckinEnabled() {
            return WC_Gateway_Swedbank_Pay_Checkin.enabled;
        },

        /**
         * Load CheckIn
         * @param country
         * @returns {*}
         */
        loadCheckIn: function( country ) {
            return $.ajax( {
                type: 'POST',
                url: WC_Gateway_Swedbank_Pay_Checkin.ajax_url,
                data: {
                    action: 'swedbank_pay_checkin',
                    nonce: WC_Gateway_Swedbank_Pay_Checkin.nonce,
                    country: country
                },
                dataType: 'json'
            } ).done( function ( data ) {
                if ( ! data.success ) {
                    wc_sb_common.logError( 'sb-checkin-loader', data );
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
                wc_sb_common.loadJs( data.data, function () {
                    wc_sb_checkin.initCheckIn();
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

            wc_sb_common.waitForLoading('payex.hostedView.consumer', function ( err ) {
                if ( err ) {
                    console.warn( err );
                    return;
                }

                // Init PayEx hostedView
                window.payex.hostedView.consumer( {
                    container: 'swedbank-pay-checkin',
                    culture: WC_Gateway_Swedbank_Pay_Checkin.culture,
                    style: WC_Gateway_Swedbank_Pay_Checkin.checkInStyle ? JSON.parse( WC_Gateway_Swedbank_Pay_Checkin.checkInStyle ) : null,
                    onConsumerIdentified: function( data ) {
                        console.log( 'hostedView: onConsumerIdentified' );
                        wc_sb_checkin.onConsumerIdentified( data );
                    },
                    onNewConsumer: function( data ) {
                        console.log( 'hostedView: onNewConsumer' );
                        wc_sb_checkin.onConsumerIdentified( data );
                    },
                    onConsumerRemoved: function( data ) {
                        console.log( 'hostedView: onConsumerRemoved' );
                        console.log ( data );
                    },
                    onBillingDetailsAvailable: function( data ) {
                        wc_sb_checkin.onAddressDetailsAvailable( 'billing', data );
                    },
                    onShippingDetailsAvailable: function( data ) {
                        if ( WC_Gateway_Swedbank_Pay_Checkin.needs_shipping_address ||
                            WC_Gateway_Swedbank_Pay_Checkin.ship_to_billing_address_only
                        ) {
                            wc_sb_checkin.onAddressDetailsAvailable( 'billing', data );
                        }

                        wc_sb_checkin.onAddressDetailsAvailable( 'shipping', data );
                    },
                    onError: function ( data ) {
                        wc_sb_common.logError( 'sb-checkin', data );
                        alert( data.details );
                    }
                } ).open();
            });
        },

        /**
         * On Consumer Identified
         * @param data
         * @returns {*}
         */
        onConsumerIdentified: function ( data ) {
            console.log( 'onConsumerIdentified', data );

            return $.ajax( {
                type: 'POST',
                url: WC_Gateway_Swedbank_Pay_Checkin.ajax_url,
                data: {
                    action: 'swedbank_pay_checkout_customer_profile',
                    nonce: WC_Gateway_Swedbank_Pay_Checkin.nonce,
                    consumerProfileRef: data.consumerProfileRef
                },
                dataType: 'json'
            } ).always( function ( response ) {
                //
            } ).done( function ( response) {
                if (!response.success) {
                    alert(response.data.message);
                    return;
                }

                // Show button witch allows to edit the address
                $('#swedbank-pay-checkin-edit').show();

                // Add the reference to the checkout form
                let checkout_form = $( "form.checkout, form#order_review, form#add_payment_method" );
                checkout_form.find( '.swedbank_pay_customer_reference' ).remove();
                checkout_form.append( "<input type='hidden' class='swedbank_pay_customer_reference' name='swedbank_pay_customer_reference' value='" + data.consumerProfileRef + "'/>" );

                // Initiate Instant Checkout if active
                if ( wc_sb_common.isInstantCheckout() ) {
                    wc_sb_checkout.initCheckout( data.consumerProfileRef );
                }
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

            wc_sb_common.block();
            return $.ajax( {
                type: 'POST',
                url: WC_Gateway_Swedbank_Pay_Checkin.ajax_url,
                data: {
                    action: 'swedbank_pay_checkout_get_address',
                    nonce: WC_Gateway_Swedbank_Pay_Checkin.nonce,
                    type: type,
                    url: data.url
                },
                dataType: 'json'
            } ).always( function () {
                wc_sb_common.unblock();
            } ).done( function ( response ) {
                console.log( response );
                if ( ! response.success ) {
                    wc_sb_common.logError( 'sb-address-details', response );
                    alert( response.data.message );
                    return;
                }

                // Process address
                let data = response.data;
                $.each( data, function ( key, value ) {
                    [type].forEach( function( section ) {
                        let el = $( 'input[name="' + section + '_' + key + '"]' );
                        if ( el.length === 0 ) {
                            return;
                        }

                        el.prop( 'readonly', false );
                        el.closest( '.form-row' ).removeClass( 'swedbank-pay-locked' );
                        el.val( value ).change();

                        if ( key === 'country' || key === 'state' ) {
                            let el1 = $( '#' + section + '_' + key );
                            if ( typeof window.Select2 !== 'undefined' ) {
                                el1.select2('val', value);
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

                $( document.body ).trigger( 'update_checkout' );
            } );
        },
        /**
         * Hide Address Fields on the checkout
         */
        hideAddressFields: function () {
            $( '.woocommerce-billing-fields__field-wrapper, .woocommerce-shipping-fields' ).hide();
        },
        /**
         * Show Address Fields on the checkout
         */
        showAddressFields: function () {
            $( '.woocommerce-billing-fields__field-wrapper, .woocommerce-shipping-fields' ).show();
        }
    }

    $( document ).ready( function () {
        wc_sb_checkin.init( $( "form.checkout, form#order_review, form#add_payment_method" ) );
    } );
} );