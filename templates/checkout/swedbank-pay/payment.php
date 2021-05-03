<?php

defined( 'ABSPATH' ) || exit;

/** @var string $js_url */
?>

<!-- <button type="submit" class="button alt" name="woocommerce_checkout_place_order" id="place_order" value="Place order" data-value="Place order">Place order</button> -->
<h3>3. <?php _e( 'Payment', 'swedbank-pay-woocommerce-checkout' ); ?></h3>

<?php if ( WC()->cart->needs_payment() ) : ?>
    <div id="payment" class="woocommerce-checkout-payment" style="display: none;">
        <input id="payment_method_payex_checkout" type="radio" name="payment_method" value="payex_checkout" checked="checked" style="display: none;">
    </div>
<?php endif; ?>

