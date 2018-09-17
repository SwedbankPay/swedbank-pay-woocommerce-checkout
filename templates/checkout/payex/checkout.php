<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wc_print_notices();

/** @var WC_Checkout $checkout */
//do_action( 'woocommerce_before_checkout_form', $checkout );
?>

<form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">
    <?php
    $consumer_profile = '';
    if ( ( $user_id = get_current_user_id() ) > 0 ) {
	    $consumer_profile = get_user_meta( $user_id, '_payex_consumer_profile', TRUE );
    }
    ?>
    <input type="hidden" id="payex_customer_profile" value="<?php echo esc_attr( $consumer_profile ); ?>" />
	<div id="payex-checkout"></div>
    <div id="payex-checkout1"></div>
</form>


<script type="application/javascript">
    jQuery(function ($) {
        'use strict';
        $(document).ready(function () {
            var consumer_profile = $('#payex_customer_profile').val();
            if ( consumer_profile.length > 0 ) {
                wc_payex_onepage.onConsumerIdentified ( {
                    consumerProfileRef: consumer_profile
                } );
            } else {
                wc_payex_onepage.init();
            }
        });
    });
</script>
