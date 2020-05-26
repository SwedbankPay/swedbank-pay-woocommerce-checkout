<?php
/** @var WC_Gateway_Swedbank_Pay_Cc $gateway */
/** @var WC_Order $order */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

?>

<?php if ( $gateway->core->canCapture( $order->get_id() ) ) : ?>
	<button id="swedbank_pay_capture"
			type="button" class="button button-primary"
			data-nonce="<?php echo wp_create_nonce( 'swedbank_pay' ); ?>"
			data-order-id="<?php echo esc_html( $order->get_id() ); ?>">
		<?php _e( 'Capture Payment', 'swedbank-pay-woocommerce-checkout' ); ?>
	</button>
<?php endif; ?>

<?php if ( $gateway->core->canCancel( $order->get_id() ) ) : ?>
	<button id="swedbank_pay_cancel"
			type="button" class="button button-primary"
			data-nonce="<?php echo wp_create_nonce( 'swedbank_pay' ); ?>"
			data-order-id="<?php echo esc_html( $order->get_id() ); ?>">
		<?php _e( 'Cancel Payment', 'swedbank-pay-woocommerce-checkout' ); ?>
	</button>
<?php endif; ?>
