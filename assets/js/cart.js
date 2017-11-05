jQuery( function( $ ) {
	'use strict';
	$( document ).on( 'click', '#px-submit', function (e) {
		e.preventDefault();
		var elm = $(this),
			payment_id = elm.data('payment-id'),
			order_ref = elm.data('order-ref');

		// Init PayEx Checkout
		payex.checkout(payment_id, {
			onClose: function(){
				//wc_payex_form.onClose();
			},
			onComplete: function() {
				//wc_payex_form.payex_submit = true;
				//$form.submit();
				window.location.href = WC_Gateway_PayEx_Cart.redirect_url;
			},
			onError: function () {
				//wc_payex_form.onClose();
			},
			onOpen: function () {
				//wc_payex_form.block();
			}
		}, 'open');
	} );
} );
