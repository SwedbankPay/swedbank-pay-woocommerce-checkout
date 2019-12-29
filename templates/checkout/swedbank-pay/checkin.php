<?php

defined( 'ABSPATH' ) || exit;

/** @var string $checkin_country */
/** @var string $selected_country */
/** @var array $consumer_data */
/** @var string $consumer_profile */
/** @var string $js_url */
?>

<?php if ($js_url): ?>
	<script id="swedbank-hostedview-script" src="<?php echo $js_url; ?>"></script>
<?php endif; ?>

<h3>1. <?php esc_html_e( 'Your information', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ); ?></h3>
<?php if ( $checkin_country === 'SELECT' ): ?>
<label for="checkin_country">
	<?php _e( 'Choose your country', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ); ?>
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
<?php endif; ?>

<div id="swedbank-pay-checkin">
	<div class="consumer-info">
		<?php if ($consumer_data && $consumer_profile): ?>
			<div id="swedbank-pay-consumer-profile" data-reference="<?php esc_html_e( $consumer_profile ); ?>"></div>
			<!-- <strong>
				<?php esc_html_e( 'You\'re logged in as Swedbank Pay customer.', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ); ?>
			</strong>
			<p>
				<?php esc_html_e( $consumer_data['first_name'] . ' ' . $consumer_data['last_name'] ); ?><br/>
				<?php esc_html_e( $consumer_data['postcode'] . ' ' . $consumer_data['city'] ); ?><br/>
				<?php esc_html_e( $consumer_data['email'] . ', ' . $consumer_data['phone'] ); ?><br/>
			</p> -->
		<?php endif; ?>
	</div>
</div>

<div style="clear: both;">&nbsp;</div>

