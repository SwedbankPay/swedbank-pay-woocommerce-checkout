<?php

defined( 'ABSPATH' ) || exit;

/** @var string $selected_country */
/** @var array $consumer_data */
/** @var string $consumer_profile */
/** @var string $js_url */
?>

<?php if ($js_url): ?>
	<script id="payex-hostedview-script" src="<?php echo $js_url; ?>"></script>
<?php endif; ?>

<h3>1. <?php esc_html_e( 'Your information', 'woocommerce-gateway-payex-checkout' ); ?></h3>
<label for="checkin_country">
    <?php _e( 'Choose your country', 'woocommerce-gateway-payex-checkout' ); ?>
	<select id="checkin_country" name="checkin_country" class="select">
		<option <?php echo $selected_country === 'SE' ? 'selected' : '' ?> value="SE">
            <?php _e('Sweden','woocommerce'); ?>
        </option>
		<option <?php echo $selected_country === 'NO' ? 'selected' : '' ?> value="NO">
            <?php _e('Norway','woocommerce'); ?>
        </option>
	</select>
</label>
<div style="clear: both;">&nbsp;</div>

<div id="payex-checkin">
	<div class="consumer-info">
		<?php if ($consumer_data && $consumer_profile): ?>
            <div id="payex-consumer-profile" data-reference="<?php esc_html_e( $consumer_profile ); ?>"></div>
			<strong>
				<?php esc_html_e( 'You\'re logged in as payex customer.', 'woocommerce-gateway-payex-checkout' ); ?>
			</strong>
			<p>
				<?php esc_html_e( $consumer_data['first_name'] . ' ' . $consumer_data['last_name'] ); ?><br/>
				<?php esc_html_e( $consumer_data['postcode'] . ' ' . $consumer_data['city'] ); ?><br/>
				<?php esc_html_e( $consumer_data['email'] . ', ' . $consumer_data['phone'] ); ?><br/>
			</p>
		<?php endif; ?>
	</div>
</div>

<div style="clear: both;">&nbsp;</div>

<!-- <button id="change-shipping-info" type="button" class="button" style="display: none;">
	<?php esc_html_e( 'Change shipping information', 'woocommerce-gateway-payex-checkout' ); ?>
</button> -->
