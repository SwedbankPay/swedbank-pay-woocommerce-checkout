<?php

defined( 'ABSPATH' ) || exit;

/** @var string $checkin_country */
/** @var string $selected_country */
/** @var array $consumer_data */
/** @var string $consumer_profile */
/** @var string $js_url */
?>

<?php if ( $js_url ) : ?>
	<script id="swedbank-hostedview-script" src="<?php echo $js_url; ?>"></script>
<?php endif; ?>

<h3>1. <?php esc_html_e( 'Your information', 'swedbank-pay-woocommerce-checkout' ); ?></h3>
<?php if ( $checkin_country === 'SELECT' ) : ?>
<label for="checkin_country">
	<?php _e( 'Choose your country', 'swedbank-pay-woocommerce-checkout' ); ?>
	<select id="checkin_country" name="checkin_country" class="select">
		<option <?php echo 'SE' === $selected_country ? 'selected' : ''; ?> value="SE">
			<?php _e( 'Sweden', 'woocommerce' ); ?>
		</option>
		<option <?php echo 'NO' === $selected_country ? 'selected' : ''; ?> value="NO">
			<?php _e( 'Norway', 'woocommerce' ); ?>
		</option>
	</select>
</label>
<div style="clear: both;">&nbsp;</div>
<?php endif; ?>

<div id="swedbank-pay-checkin">
	<div class="consumer-info">
		<?php if ( $consumer_data && $consumer_profile ) : ?>
			<div id="swedbank-pay-consumer-profile" data-reference="<?php esc_html_e( $consumer_profile ); ?>"></div>
		<?php endif; ?>
	</div>
</div>

<div style="clear: both;">&nbsp;</div>

