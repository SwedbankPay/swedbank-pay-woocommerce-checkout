<?php
/** @var string $payment_id */
/** @var string $product_id */
?>
<div style="clear: both">&nbsp;</div>

<?php if ( ! empty( $payment_id ) ): ?>
	<button
		type="submit"
		name="buy-payex"
		data-payment-id="<?php echo esc_html($payment_id); ?>"
		value="<?php echo esc_html($product_id); ?>"
		class="single_add_to_cart_button button alt">
		Buy now using PayEx
	</button>
	<script>
		jQuery(function ($) {
			$(document).ready(function () {
				var payment_id = $("[name='buy-payex']").first().data('payment-id');

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
			});
		});
	</script>
<?php else: ?>
<button
	type="submit"
	name="buy-payex"
	value="<?php echo esc_html($product_id); ?>"
	class="single_add_to_cart_button button alt">
	Buy now using PayEx
</button>

<?php endif; ?>

<div style="clear: both">&nbsp;</div>