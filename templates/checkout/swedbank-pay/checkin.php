<?php

defined( 'ABSPATH' ) || exit;

/** @var string $checkin_edit */
/** @var array $consumer_data */
/** @var string $consumer_profile */
/** @var string $js_view_url */
?>

<?php if ( $js_view_url ) : ?>
	<script id="swedbank-hostedview-script" src="<?php echo $js_view_url; ?>"></script>
<?php endif; ?>

<h3>1. <?php esc_html_e( 'Your information', 'swedbank-pay-woocommerce-checkout' ); ?></h3>

<div id="swedbank-pay-checkin">
	<div class="consumer-info">
		<?php if ( $consumer_data && $consumer_profile ) : ?>
			<div id="swedbank-pay-consumer-profile" data-reference="<?php esc_html_e( $consumer_profile ); ?>"></div>
		<?php endif; ?>
	</div>
</div>


<?php if ( 'yes' === $checkin_edit ) : ?>
    <div style="clear: both;">&nbsp;</div>
    <div id="swedbank-pay-checkin-edit" style="display: none;">
        <button type="button" id="change-address-info">
	        <?php _e( 'Change the address', 'swedbank-pay-woocommerce-checkout' ); ?>
        </button>
    </div>
<?php endif; ?>

<div style="clear: both;">&nbsp;</div>

