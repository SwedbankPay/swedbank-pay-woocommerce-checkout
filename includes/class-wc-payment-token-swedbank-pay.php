<?php

namespace SwedbankPay\Checkout\WooCommerce;

use WC_Payment_Token_CC;

defined( 'ABSPATH' ) || exit;

class WC_Payment_Token_Swedbank_Pay extends WC_Payment_Token_CC {
	/**
	 * Token Type String.
	 *
	 * @var string
	 */
	protected $type = 'Swedbankpay';

	/**
	 * Stores Credit Card payment token data.
	 *
	 * @var array
	 */
	protected $extra_data = array(
		'last4'             => '',
		'expiry_year'       => '',
		'expiry_month'      => '',
		'card_type'         => '',
		'masked_pan'        => '',
		'payment_token'     => '',
		'recurrence_token'  => '',
		'unscheduled_token' => '',
	);

	/**
	 * Get type to display to user.
	 *
	 * @param string $deprecated Deprecated since WooCommerce 3.0.
	 *
	 * @return string
	 */
	public function get_display_name( $deprecated = '' ) {
		ob_start();
		?>
		<img src="<?php echo \WC_HTTPS::force_https_url( \WC()->plugin_url() . '/assets/images/icons/credit-cards/' . $this->get_card_type() . '.png' ); ?>"
			 alt="<?php echo wc_get_credit_card_type_label( $this->get_card_type() ); ?>"/>
		<?php echo esc_html( $this->get_meta( 'masked_pan' ) ); ?>
		<?php echo esc_html( $this->get_expiry_month() . '/' . substr( $this->get_expiry_year(), 2 ) ); ?>

		<?php
		$display = ob_get_contents();
		ob_end_clean();

		return $display;
	}

	/**
	 * Validate credit card payment tokens.
	 *
	 * @return boolean True if the passed data is valid
	 */
	public function validate() {
		if ( false === parent::validate() ) {
			return false;
		}

		if ( ! $this->get_masked_pan( 'edit' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Hook prefix
	 * @return string
	 */
	protected function get_hook_prefix() {
		return 'woocommerce_payment_token_swedbankpay_get_';
	}

	/**
	 * Returns Masked Pan
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return string Masked Pan
	 */
	public function get_masked_pan( $context = 'view' ) {
		return $this->get_prop( 'masked_pan', $context );
	}

	/**
	 * Set the last four digits.
	 *
	 * @param string $masked_pan Masked Pan
	 */
	public function set_masked_pan( $masked_pan ) {
		$this->set_prop( 'masked_pan', $masked_pan );
	}

	/**
	 * Returns Payment token
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return string Payment token
	 */
	public function get_payment_token( $context = 'view' ) {
		return $this->get_prop( 'payment_token', $context );
	}

	/**
	 * Set Payment token
	 *
	 * @param string $token Payment token
	 */
	public function set_payment_token( $token ) {
		$this->set_prop( 'payment_token', $token );
	}

	/**
	 * Returns Recurrence token
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return string Recurrence token
	 */
	public function get_recurrence_token( $context = 'view' ) {
		return $this->get_prop( 'recurrence_token', $context );
	}

	/**
	 * Set Recurrence token
	 *
	 * @param string $token Recurrence token
	 */
	public function set_recurrence_token( $token ) {
		$this->set_prop( 'recurrence_token', $token );
	}

	/**
	 * Returns Unscheduled token
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return string Unscheduled token
	 */
	public function get_unscheduled_token( $context = 'view' ) {
		return $this->get_prop( 'unscheduled_token', $context );
	}

	/**
	 * Set Unscheduled token
	 *
	 * @param string $token Unscheduled token
	 */
	public function set_unscheduled_token( $token ) {
		$this->set_prop( 'unscheduled_token', $token );
	}

	/**
	 * Returns if the token is marked as default.
	 *
	 * @return boolean True if the token is default
	 */
	public function is_default() {
		// Mark Method as Checked on "Payment Change" page
		if ( class_exists( '\\WC_Subscriptions_Change_Payment_Gateway', false ) &&
			 \WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment &&
			 isset( $_GET['change_payment_method'] ) &&
			 abs( $_GET['change_payment_method'] ) > 0
		) {
			$subscription = wcs_get_subscription( $_GET['change_payment_method'] );
			$tokens       = $subscription->get_payment_tokens();
			foreach ( $tokens as $token_id ) {
				if ( $this->get_id() === $token_id ) {
					return true;
				}
			}

			return false;
		}

		return parent::is_default();
	}

	/**
	 * Delete an object, set the ID to 0, and return result.
	 *
	 * @since  2.6.0
	 * @param  bool $force_delete Should the date be deleted permanently.
	 * @return bool result
	 */
	public function delete( $force_delete = false ) {
		// @todo Remove token
		do_action( 'sb_checkout_delete_token', $this );

		return parent::delete( $force_delete );
	}

	/**
	 * Controls the output for credit cards on the my account page.
	 *
	 * @param array $item Individual list item from woocommerce_saved_payment_methods_list.
	 * @param \WC_Payment_Token $payment_token The payment token associated with this method entry.
	 *
	 * @return array                           Filtered item.
	 */
	public static function wc_get_account_saved_payment_methods_list_item( $item, $payment_token ) {
		if ( ! in_array( strtolower( $payment_token->get_type() ), array('swedbankpay', 'swedbank', 'payex') ) ) {
			return $item;
		}

		$card_type               = $payment_token->get_card_type();
		$item['method']['id']    = $payment_token->get_id();
		$item['method']['last4'] = $payment_token->get_last4();
		$item['method']['brand'] = ( ! empty( $card_type ) ? ucfirst( $card_type ) : esc_html__(
			'Credit card',
			'woocommerce'
		) );
		$item['expires']         = $payment_token->get_expiry_month() . '/' . substr(
			$payment_token->get_expiry_year(),
			- 2
		);

		return $item;
	}

	/**
	 * Controls the output for credit cards on the my account page.
	 *
	 * @param $method
	 *
	 * @return void
	 */
	public static function wc_account_payment_methods_column_method( $method ) {
		if ( 'payex_checkout' === $method['method']['gateway'] ) {
			if ( isset($method['method']['id'])) {
				$token = new WC_Payment_Token_Swedbank_Pay( $method['method']['id'] );
				echo $token->get_display_name(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}

			return;
		}

		if ( ! isset($method['method']['brand']) ) {
			return;
		}

		// Default output
		// @see woocommerce/myaccount/payment-methods.php
		if ( ! empty( $method['method']['last4'] ) ) {
			/* translators: 1: credit card type 2: last 4 digits */
			echo sprintf( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				__( '%1$s ending in %2$s', 'woocommerce' ),
				esc_html( wc_get_credit_card_type_label( $method['method']['brand'] ) ),
				esc_html( $method['method']['last4'] )
			);
		} else {
			echo esc_html( wc_get_credit_card_type_label( $method['method']['brand'] ) );
		}
	}

	/**
	 * Fix html on Payment methods list
	 *
	 * @param string $html
	 * @param \WC_Payment_Token $token
	 * @param \WC_Payment_Gateway $gateway
	 *
	 * @return string
	 */
	public static function wc_get_saved_payment_method_option_html( $html, $token, $gateway ) {
		if ( 'payex_checkout' === $token->get_gateway_id() ) {
			// Revert esc_html()
			$html = html_entity_decode( $html, ENT_COMPAT | ENT_XHTML, 'UTF-8' );
		}

		return $html;
	}

	/**
	 * @param $class_name
	 * @param $type
	 *
	 * @return string
	 */
	public static function payment_token_class( $class_name, $type ) {
		if ( in_array( strtolower( $type ), array('swedbankpay', 'swedbank', 'payex') ) ) {
			$class_name = __CLASS__;
		}

		return $class_name;
	}
}

// Improve Payment Method output
add_filter(
	'woocommerce_payment_methods_list_item',
	'\SwedbankPay\Checkout\WooCommerce\WC_Payment_Token_Swedbank_Pay::wc_get_account_saved_payment_methods_list_item',
	10,
	2
);

add_action(
	'woocommerce_account_payment_methods_column_method',
	'\SwedbankPay\Checkout\WooCommerce\WC_Payment_Token_Swedbank_Pay::wc_account_payment_methods_column_method',
	10,
	1
);

add_filter(
	'woocommerce_payment_gateway_get_saved_payment_method_option_html',
	'\SwedbankPay\Checkout\WooCommerce\WC_Payment_Token_Swedbank_Pay::wc_get_saved_payment_method_option_html',
	10,
	3
);

// Backward compatibility
add_filter(
	'woocommerce_payment_token_class',
	'\SwedbankPay\Checkout\WooCommerce\WC_Payment_Token_Swedbank_Pay::payment_token_class',
	10,
	2
);
