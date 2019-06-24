<?php

defined( 'ABSPATH' ) || exit;

/** @var array $consumer_data */
/** @var string $consumer_profile */
/** @var string $js_url */
?>

<?php if ($js_url): ?>
	<script id="payex-hostedview-script" src="<?php echo $js_url; ?>"></script>
<?php endif; ?>

<h3>1. <?php esc_html_e( 'Your information', 'woocommerce-gateway-payex-checkout' ); ?></h3>
<div id="payex-checkin">
	<div class="consumer-info">
		<?php if ($consumer_data): ?>
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

<?php if ($consumer_profile): ?>
	<div id="payex-consumer-profile" data-reference="<?php esc_html_e( $consumer_profile ); ?>"></div>
<?php endif; ?>

<br />

<button id="change-shipping-info" type="button" class="button" style="display: none;">
	<?php esc_html_e( 'Change shipping information', 'woocommerce-gateway-payex-checkout' ); ?>
</button>
