<?php

namespace SwedbankPay\Checkout\WooCommerce;

defined( 'ABSPATH' ) || exit;

use WC_Shortcodes;

class WC_Swedbank_Pay_Payment_Url {
	/**
	 * @var array
	 */
	public $settings = array();

	/**
	 * Locale.
	 *
	 * @var string
	 */
	public $culture = 'en-US';

	/**
	 * Styles of PaymentMenu
	 * @var string
	 */
	public $payment_menu_style = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Load settings
		$this->settings = get_option( 'woocommerce_payex_checkout_settings' );
		if ( ! is_array( $this->settings ) ) {
			$this->settings = array();
		}

		$this->culture = isset( $this->settings['culture'] ) ? $this->settings['culture'] : $this->culture;

		$this->payment_menu_style = isset( $this->settings['paymentMenuStyle'] ) ? $this->settings['paymentMenuStyle'] : $this->payment_menu_style;

		if ( isset( $_GET['payment_url'] ) ) { // WPCS: input var ok, CSRF ok.
			add_action( 'init', array( $this, 'override_checkout_shortcode' ), 100 );
		}
	}

	/**
	 * Override woocommerce_checkout shortcode
	 */
	public function override_checkout_shortcode()
	{
		remove_shortcode( 'woocommerce_checkout' );
		add_shortcode(
			apply_filters( 'woocommerce_checkout_shortcode_tag', 'woocommerce_checkout' ),
			array( $this, 'shortcode_woocommerce_checkout' )
		);
	}

	/**
	 * Addes "payment-url" script to finish the payment.
	 *
	 * @param $atts
	 *
	 * @return string
	 */
	public function shortcode_woocommerce_checkout( $atts )
	{
		// Check WC sessions
		if ( ! WC()->session ) {
			WC()->initialize_session();
		}

		$payment_url = WC()->session->get( 'sb_payment_url' );

		if ( empty( $payment_url ) ) {
			$order_id = absint( WC()->session->get( 'order_awaiting_payment' ) );
			if ( $order_id > 0 ) {
				$order       = wc_get_order( $order_id );
				$payment_url = $order->get_meta( '_sb_view_paymentorder' );
			}
		}

		if ( ! empty( $payment_url ) ) {
			wp_dequeue_script( 'featherlight' );
			wp_dequeue_script( 'wc-sb-seamless-checkout' );
			wp_dequeue_script( 'wc-sb-checkout' );
			wp_dequeue_script( 'swedbank-pay-checkout-instant' );
			wp_dequeue_script( 'wc-gateway-swedbank-pay-checkout' );

			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

			wp_register_script(
				'wc-sb-payment-url',
				untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/js/payment-url' . $suffix . '.js',
				array(
					'jquery',
					'wc-sb-common',
				),
				false,
				true
			);

			// Localize the script with new data
			$translation_array = array(
				'culture'          => $this->culture,
				'payment_url'      => $payment_url,
				'paymentMenuStyle' => null,
			);

			// Add PM styles
			$styles = apply_filters( 'swedbank_pay_checkout_paymentmenu_style', $this->payment_menu_style );
			if ( $styles ) {
				$translation_array['paymentMenuStyle'] = $styles;
			}

			wp_localize_script(
				'wc-sb-payment-url',
				'WC_Sb_Payment_Url',
				$translation_array
			);

			// Enqueued script with localized data.
			wp_enqueue_script( 'wc-sb-common' );
			wp_enqueue_script( 'wc-sb-payment-url' );

			return '<div id="payment-swedbank-pay-checkout"></div>';
		}

		return WC_Shortcodes::checkout( $atts );
	}
}

new WC_Swedbank_Pay_Payment_Url();
