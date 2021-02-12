/* global wc_checkout_params */
jQuery( function( $ ) {
    window.wc_sb_common = {
        /**
         * Check if the Instant Checkout is active
         * @return {boolean}
         */
        isInstantCheckout() {
            return 'yes' === WC_Gateway_Swedbank_Pay_Checkout.instant_checkout;
        },

        /**
         * Check if the redirect method is active
         * @return {boolean}
         */
        isRedirectMethodEnabled() {
            return 'redirect' === WC_Gateway_Swedbank_Pay_Checkout.redirect_method;
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
         * Wait for object is loaded
         * @param var_string
         * @param callback
         */
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
         * Block the checkout form
         */
        block: function() {
            if ( typeof wc_sb_checkout !== 'undefined' ) {
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
            }
        },

        /**
         * Unblock the checkout form
         */
        unblock: function() {
            if ( typeof wc_sb_checkout !== 'undefined' ) {
                wc_sb_checkout.form.unblock();
            }
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

        /**
         * Validate checkout fields on the checkout form
         * @return {boolean}
         */
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
    }
} );
