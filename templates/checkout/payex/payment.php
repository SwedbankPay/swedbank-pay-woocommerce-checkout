<?php

defined( 'ABSPATH' ) || exit;

/** @var string $js_url */
?>
<!-- <button type="submit" class="button alt" name="woocommerce_checkout_place_order" id="place_order" value="Place order" data-value="Place order">Place order</button> -->
<h3>3. <?php _e( 'Payment', 'woocommerce-gateway-payex-checkout' ); ?></h3>
<div id="payment-payex-checkout"></div>
<?php if ($js_url): ?>
	<script>
        window.onload = function () {
            wc_payex_checkout.initPaymentJS( '<?php echo $js_url; ?>' );
        }
    </script>
<?php endif; ?>
