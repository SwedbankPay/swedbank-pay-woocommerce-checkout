jQuery( function( $ ) {
	'use strict';

	/**
	 * Object to handle Payex Checkout payment forms.
	 */
	var wc_payex_form = {

		/**
		 * Initialize e handlers and UI state.
		 */
		init: function( form ) {
			this.form          = form;
			this.payex_submit  = false;

			$( this.form )
			// We need to bind directly to the click (and not checkout_place_order_payex_checkout) to avoid popup blockers
			// especially on mobile devices (like on Chrome for iOS) from blocking payex.checkout(payment_id, {}, 'open'); from opening a tab
				.on( 'click', '#place_order', this.onSubmit )

				// WooCommerce lets us return a false on checkout_place_order_{gateway} to keep the form from submitting
				.on( 'submit checkout_place_order_payex_checkout' );

			$( document.body ).on( 'checkout_error', this.resetCheckout );
		},


		block: function() {
			wc_payex_form.form.block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});
		},

		unblock: function() {
			wc_payex_form.form.unblock();
		},

		onClose: function() {
			wc_payex_form.unblock();
		},

		onSubmit: function( e ) {
			if (wc_payex_form.payex_submit) {
				return true;
			}

			if ( $( '#payment_method_payex_checkout' ).is( ':checked' ) ) {
				e.preventDefault();

				var $form = wc_payex_form.form,
					$data = $( '#payex-payment-data' ),
					payment_id = $data.data('payment-id');

				if ( ! wc_payex_form.validateForm() ) {
					return false;
				}

				// Init PayEx Checkout
				payex.checkout(payment_id, {
					onClose: function(){
						wc_payex_form.onClose();
					},
					onComplete: function() {
						wc_payex_form.payex_submit = true;
						$form.submit();
					},
					onError: function () {
						wc_payex_form.onClose();
					},
					onOpen: function () {
						wc_payex_form.block();
					}
				}, 'open');

				return false;
			}

			return true;
		},

		resetCheckout: function() {
			wc_payex_form.payex_submit = false;
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
		}
	};

	wc_payex_form.init( $( "form.checkout, form#order_review, form#add_payment_method" ) );
} );
