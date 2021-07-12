<?php
/** @var string $action */
/** @var string $form_url */
/** @var array $errors */
/** @var array $notices */

defined( 'ABSPATH' ) || exit;

global $wp_http_referer;
?>

<?php if ( count( $errors ) > 0 ): ?>
	<div class="error">
	<?php foreach ( $errors as $error ): ?>
		<p>
			<?php echo esc_html( $error ); ?>
		</p>
	<?php endforeach; ?>
	</div>
<?php endif; ?>

<?php if ( count( $notices ) > 0 ): ?>
	<div class="updated">
		<?php foreach ( $notices as $notice ): ?>
			<p>
				<?php echo esc_html( $notice ); ?>
			</p>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<form id="support" action="<?php echo esc_url( $form_url ); ?>" method="post" novalidate="novalidate">
	<?php wp_nonce_field( 'support_submit' ); ?>
	<?php if ( $wp_http_referer ) : ?>
		<input type="hidden" name="wp_http_referer" value="<?php echo esc_url( $wp_http_referer ); ?>" />
	<?php endif; ?>
	<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>" />

	<h2><?php _e( 'Support', 'swedbank-pay-woocommerce-checkout' ); ?></h2>

	<p>
		<?php
		echo esc_html__( 'If you\'re having difficulties then you can submit a request to Swedbank Pay that will automatically include your store settings, logs and system information.', 'swedbank-pay-woocommerce-checkout' );
		?>
	</p>

	<table class="form-table" role="presentation">
		<tr class="email-wrap">
			<th scope="row">
				<?php _e( 'Your e-mail', 'swedbank-pay-woocommerce-checkout' ); ?>
				<span class="description"><?php _e( '(required)' ); ?></span>
			</th>
			<td>
				<label for="email">
					<input name="email" type="email" id="email" value="" class="regular-text" />
				</label>
			</td>
		</tr>
		<tr class="message-wrap">
			<th>
				<label for="message">
					<?php _e( 'Describe your problem', 'swedbank-pay-woocommerce-checkout' ); ?>
					<span class="description"><?php _e( '(required)' ); ?></span>
				</label>
			</th>
			<td>
				<textarea name="message" id="message" cols="20" rows="7" class="regular-text"></textarea>
			</td>
		</tr>
	</table>

	<?php submit_button( __( 'Submit', 'swedbank-pay-woocommerce-checkout' ) ); ?>

</form>
