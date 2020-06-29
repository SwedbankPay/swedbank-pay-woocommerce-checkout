/* global wc_checkout_params */
jQuery( function( $ ) {
    'use strict';

    window.wc_sb_checkin = {
        /**
         * Initialize
         */
        init: function() {
            if ( wc_sb_common.isCheckinEnabled() ) {
                $( document.body ).on( 'change', '#checkin_country', function () {
                    wc_sb_checkin.loadCheckIn( $(this).val() );
                } );

                $( document.body ).on( 'click', '#change-shipping-info', function () {
                    // Hide "Change shipping info" button
                    $( '#change-shipping-info' ).hide();

                    // Show Address Fields
                    wc_sb_checkin.showAddressFields();
                } );

                wc_sb_checkin.hideAddressFields();
                wc_sb_checkin.initCheckIn();
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
                wc_sb_checkin.loadJs( data.data, function () {
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
                    culture: WC_Gateway_Swedbank_Pay_Checkout.culture,
                    style: WC_Gateway_Swedbank_Pay_Checkout.checkInStyle ? JSON.parse( WC_Gateway_Swedbank_Pay_Checkout.checkInStyle ) : null,
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
                    },
                    onBillingDetailsAvailable: function( data ) {
                        wc_sb_checkin.onAddressDetailsAvailable( 'billing', data );
                    },
                    onShippingDetailsAvailable: function( data ) {
                        if ( WC_Gateway_Swedbank_Pay_Checkout.needs_shipping_address ||
                            WC_Gateway_Swedbank_Pay_Checkout.ship_to_billing_address_only
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

                // Initiate Checkout if active
                if ( wc_sb_common.isInstantCheckout ) {
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

            $( '#change-shipping-info' ).show();

            wc_sb_common.block();
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
                wc_sb_common.unblock();
            } ).done( function ( response) {
                console.log(response);
                if (!response.success) {
                    wc_sb_common.logError('sb-address-details', response);
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

    $(document).ready( function () {
        wc_sb_checkin.init( $( "form.checkout, form#order_review, form#add_payment_method" ) );
    } );
} );