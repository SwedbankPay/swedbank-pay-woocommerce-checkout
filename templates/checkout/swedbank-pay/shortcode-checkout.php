<?php

// Based on template checkout/form-checkout.php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @var WC_Cart $cart
 * @var WC_Checkout $checkout
 * @var string $consumer_js_url
 * @var array $consumer_data
 * @var string $consumer_profile
 */

do_action( 'woocommerce_before_checkout_form', $checkout );

// If checkout registration is disabled and not logged in, the user cannot checkout.
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
	return;
}

?>

<form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">
	<?php if ( $consumer_js_url ) : ?>
        <script id="swedbank-hostedview-script" src="<?php echo $consumer_js_url; ?>"></script>
	<?php endif; ?>

	<h3>1. <?php esc_html_e( 'Your information', 'swedbank-pay-woocommerce-checkout' ); ?></h3>
	<div id="swedbank-pay-checkin">
		<div class="consumer-info">
			<?php if ( $consumer_data && $consumer_profile ) : ?>
				<div id="swedbank-pay-consumer-profile" data-reference="<?php esc_html_e( $consumer_profile ); ?>"></div>
			<?php endif; ?>
		</div>
	</div>
	<div style="clear: both;">&nbsp;</div>
	<div id="swedbank-pay-checkin-edit" style="display: none;">
		<button type="button" id="change-address-info">
			<?php _e( 'Change the address', 'swedbank-pay-woocommerce-checkout' ); ?>
		</button>
	</div>
	<div style="clear: both;">&nbsp;</div>

    <div id="address-fields" style="display: none;">
	    <?php if ( $checkout->get_checkout_fields() ) : ?>

		    <?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

            <div class="col2-set" id="customer_details">
                <div class="col-1">
				    <?php do_action( 'woocommerce_checkout_billing' ); ?>
                </div>

                <div class="col-2">
				    <?php do_action( 'woocommerce_checkout_shipping' ); ?>
                </div>
            </div>

		    <?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>

	    <?php endif; ?>
    </div>

	<?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>
	<h3 id="order_review_heading1">2. <?php esc_html_e( 'Your order', 'woocommerce' ); ?></h3>
	<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>
	<div id="order_review1" class="woocommerce-checkout-review-order">
        <?php
        remove_all_actions( 'woocommerce_checkout_order_review', 20 );
        remove_action( 'woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20 );
        ?>
		<?php do_action( 'woocommerce_checkout_order_review' ); ?>
	</div>
	<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>

    <div id="payment" class="woocommerce-checkout-payment" style="display: none;">
        <input type="radio" name="payment_method" value="payex_checkout" checked="checked" style="display: none;">
    </div>
    <h3>3. <?php _e( 'Payment', 'swedbank-pay-woocommerce-checkout' ); ?></h3>

    <?php if ( WC()->cart->needs_payment() ) : ?>
        <div id="payment" class="woocommerce-checkout-payment" style="display: none;">
            <input id="payment_method_payex_checkout" type="radio" name="payment_method" value="payex_checkout" checked="checked" style="display: none;">
        </div>
    <?php endif; ?>

    <div id="payment-swedbank-pay-checkout" class="form-row"></div>
    <div style="clear: both;">&nbsp;</div>

    <div class="form-row place-order">
	    <?php wc_get_template( 'checkout/terms.php' ); ?>

	    <?php do_action( 'woocommerce_review_order_before_submit' ); ?>

	    <?php echo apply_filters( 'woocommerce_order_button_html', '<button type="submit" class="button alt" name="woocommerce_checkout_place_order" id="place_order" value="' . esc_attr( $order_button_text ) . '" data-value="' . esc_attr( $order_button_text ) . '">' . esc_html( $order_button_text ) . '</button>' ); // @codingStandardsIgnoreLine ?>

	    <?php do_action( 'woocommerce_review_order_after_submit' ); ?>

	    <?php wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>
    </div>
</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>

