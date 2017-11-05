<?php
/** @var string $payment_id */
/** @var string $order_ref */
?>
<a id="px-submit"
   href="<?php echo esc_html($link); ?>"
   data-payment-id="<?php echo esc_html($payment_id); ?>"
   data-order-ref="<?php echo esc_html($order_ref); ?>"
   class="checkout-button button alt wc-forward">
	<?php _e( 'Proceed to PayEx checkout', 'woocommerce-gateway-payex-checkout' ); ?>
</a>
