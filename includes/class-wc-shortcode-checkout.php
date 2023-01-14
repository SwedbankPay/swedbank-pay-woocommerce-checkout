<?php

namespace SwedbankPay\Checkout\WooCommerce;

defined( 'ABSPATH' ) || exit;

use WC_Gateway_Swedbank_Pay_Checkout;
use Exception;

class WC_Shortcode_Checkout {
	const SHORTCODE = 'instant_checkout';

	/**
	 * @var array
	 */
	public $settings = array();

	/**
	 * @var WC_Gateway_Swedbank_Pay_Checkout
	 */
	public $gateway;

	/**
	 * @var string
	 */
	public $enabled = 'no';

	/**
	 * Locale.
	 *
	 * @var string
	 */
	public $culture = 'en-US';

	/**
	 * Use Instant Checkout.
	 *
	 * @var string
	 */
	public $instant_checkout = 'no';

	/**
	 * Invoice Fee.
	 *
	 * @var int|float
	 */
	public $invoice_fee = 0;

	/**
	 * Enable Checkin.
	 *
	 * @var string
	 */
	public $checkin = 'yes';

	/**
	 * Require checkin
	 *
	 * @var string
	 */
	public $checkin_required = 'no';

	/**
	 * Allow to edit checkout fields.
	 *
	 * @var string
	 */
	public $checkin_edit = 'no';

	/**
	 * Custom styles.
	 *
	 * @var string
	 */
	public $custom_styles = 'no';

	/**
	 * Styles of Checkin.
	 *
	 * @var string
	 */
	public $checkin_style = '';

	/**
	 * Styles of PaymentMenu
	 * @var string
	 */
	public $payment_menu_style = '';

	/**
	 * Shortcode is active.
	 *
	 * @var string
	 */
	public $shortcode_enabled = 'no';

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Load settings
		$this->settings = get_option( 'woocommerce_payex_checkout_settings' );
		if ( ! is_array( $this->settings ) ) {
			$this->settings = array();
		}

		// Add settings
		add_action( 'woocommerce_after_register_post_type', array( $this, 'register_post_type' ), 100 );

		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );

		// Settings of the gateway
		$this->enabled           = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'no';
		$this->culture           = isset( $this->settings['culture'] ) ? $this->settings['culture'] : $this->culture;
		$this->instant_checkout  = isset( $this->settings['instant_checkout'] ) ? $this->settings['instant_checkout'] : $this->instant_checkout;
		$this->invoice_fee       = isset( $this->settings['fee'] ) ? $this->settings['fee'] : $this->invoice_fee;
		$this->shortcode_enabled = isset( $this->settings['shortcode_enabled'] ) ? $this->settings['shortcode_enabled'] : $this->shortcode_enabled;

		// Checkin settings
		$this->checkin          = isset( $this->settings['checkin'] ) ? $this->settings['checkin'] : $this->checkin;
		$this->checkin_required = isset( $this->settings['checkin_required'] ) ? $this->settings['checkin_required'] : $this->checkin_required;
		$this->checkin_edit     = isset( $this->settings['checkin_edit'] ) ? $this->settings['checkin_edit'] : $this->checkin_edit;

		// Styles
		$this->custom_styles      = isset( $this->settings['custom_styles'] ) ? $this->settings['custom_styles'] : $this->custom_styles;
		$this->checkin_style      = isset( $this->settings['checkInStyle'] ) ? $this->settings['checkInStyle'] : $this->checkin_style;
		$this->payment_menu_style = isset( $this->settings['paymentMenuStyle'] ) ? $this->settings['paymentMenuStyle'] : $this->payment_menu_style;

		if ( 'yes' === $this->shortcode_enabled ) {
			// JS Scrips
			add_action( 'wp_enqueue_scripts', array( $this, 'add_scripts' ) );

			// Add short code
			add_shortcode( self::SHORTCODE, array( $this, 'shortcode' ) );

			add_filter( 'woocommerce_get_checkout_url', array( $this, 'get_checkout_url' ) );
			add_action( 'woocommerce_checkout_init', array( $this, 'checkout_init' ), 10, 1 );
			add_filter( 'wc_get_template', array( $this, 'override_template' ), 5, 20 );
			add_action( 'the_post', array( $this, 'override_checkout_page' ), 9 );
		}

		// Ajax Actions
		add_action( 'wp_ajax_sbp_submit_order', array( $this, 'ajax_sbp_submit_order' ) );
		add_action( 'wp_ajax_nopriv_sbp_submit_order', array( $this, 'ajax_sbp_submit_order' ) );

		add_action( 'wp_ajax_sbp_update_order', array( $this, 'ajax_sbp_update_order' ) );
		add_action( 'wp_ajax_nopriv_sbp_update_order', array( $this, 'ajax_sbp_update_order' ) );

		add_action( 'wp_ajax_sbp_recalculate', array( $this, 'ajax_sbp_recalculate' ) );
		add_action( 'wp_ajax_nopriv_sbp_recalculate', array( $this, 'ajax_sbp_recalculate' ) );
	}

	/**
	 * WooCommerce Init.
	 */
	public function register_post_type() {
		add_filter(
			'woocommerce_settings_api_form_fields_payex_checkout',
			array(
				$this,
				'add_settings',
			)
		);
	}

	/**
	 * Add settings.
	 *
	 * @param $form_fields
	 *
	 * @return array
	 */
	public function add_settings( $form_fields ) {
		$form_fields['shortcode_enabled'] = array(
			'title'   => sprintf( __( 'Enable shortcode', 'swedbank-pay-woocommerce-checkout' ),
				self::SHORTCODE
			),
			'type'    => 'checkbox',
			'label'   => sprintf( __( 'Enable shortcode [%s] to replace the checkout page (experimental)', 'swedbank-pay-woocommerce-checkout' ),
				self::SHORTCODE
			),
			'default' => $this->shortcode_enabled,
		);

		return $form_fields;
	}

	/**
	 * Init the gateway instance
	 */
	public function plugins_loaded() {
		try {
			$this->gateway = $this->get_gateway();
		} catch ( Exception $e ) {
			$this->shortcode_enabled = 'no';
		}
	}

	public function add_scripts() {
		global $post;

		// Check if page has short code
		if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, self::SHORTCODE ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';


		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_register_script(
			'async',
			untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/js/async' . $suffix . '.js',
			array(),
			'3.2.0',
			true
		);

		wp_register_script(
			'wc-sb-common',
			untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/js/common' . $suffix . '.js',
			array(
				'jquery',
			),
			rand(0, 100),
			true
		);

		wp_enqueue_style(
			'sb-shortcode-checkout-styles',
			untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/css/shortcode-checkout.css',
			array(),
			rand(0, 100),
			'all'
		);

		wp_register_script(
			'sb-shortcode-checkout',
			untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/js/shortcode-checkout.js',
			array(
				'wc-checkout',
				'jquery',
				'async',
				'wc-sb-common'
			),
			rand(0, 100),
			true
		);

		// Localize the script with new data
		$translation_array = array(
			'nonce'                        => wp_create_nonce( 'swedbank_pay_checkout' ),
			'ajax_url'                     => admin_url( 'admin-ajax.php' ),
			'payment_url'                  => WC()->session->get( 'sb_payment_url' ),
			'checkin_enabled'              => $this->checkin,
			'culture'                      => $this->culture,
			'checkin_required'             => $this->checkin_required,
			'checkin_edit'                 => ( 'yes' === $this->checkin_edit ),
			'invoice_fee_enabled'          => $this->invoice_fee > 0.01 ? 'yes' : 'no',
			'carpay_enabled'               => is_plugin_active( 'aait-sbpay-helper/aait-sbpay-helper.php' )  ? 'yes' : 'no' ,
			'needs_shipping_address'       => WC()->cart->needs_shipping() ? 'yes' : 'no',
			'ship_to_billing_address_only' => wc_ship_to_billing_address_only() ? 'yes' : 'no',
			'tos_enabled'                  => apply_filters( 'woocommerce_checkout_show_terms', true ) &&
			                                  function_exists( 'wc_terms_and_conditions_checkbox_enabled' ),
			'checkInStyle'                 => null,
			'needs_checkin'                => __(
				'You must check in to be able to pay.',
				'swedbank-pay-woocommerce-checkout'
			),
			'paymentMenuStyle'             => null,
			'terms_error'                  => __(
				'Please read and accept the terms and conditions to proceed with your order.',
				'woocommerce'
			),
			'checkin_error'                => __(
				'Validation is failed. Please check entered data on the form.',
				'swedbank-pay-woocommerce-checkout'
			),
		);

		// Add CheckIn Styles
		$styles = apply_filters( 'swedbank_pay_checkout_checkin_style', $this->checkin_style );
		if ( $styles ) {
			$translation_array['checkInStyle'] = $styles;
		}

		// Add PM styles
		$styles = apply_filters( 'swedbank_pay_checkout_paymentmenu_style', $this->payment_menu_style );
		if ( $styles ) {
			$translation_array['paymentMenuStyle'] = $styles;
		}

		wp_localize_script(
			'sb-shortcode-checkout',
			'WC_Shortcode_Checkout',
			$translation_array
		);

		wp_enqueue_script( 'sb-shortcode-checkout' );
	}

	public function shortcode( $atts ) {
		// Extract options
		extract( shortcode_atts( array(), $atts) );

		// Check cart has contents.
		if ( WC()->cart->is_empty() && ! is_customize_preview() && apply_filters( 'woocommerce_checkout_redirect_empty_cart', true ) ) {
			ob_start();
			wc_get_template( 'cart/cart-empty.php' );
			$return = ob_get_contents();
			ob_end_flush();

			return $return;
		}

		// Init check-in
		remove_all_actions( 'woocommerce_checkout_init', 10 );
		remove_all_actions( 'woocommerce_checkout_billing', 10 );
		remove_all_actions( 'woocommerce_checkout_shipping', 10 );

		// Get saved consumerProfileRef
		$profile = $this->gateway->get_consumer_profile( get_current_user_id() );

		// Initiate consumer session to obtain consumerProfileRef after checkin
		$js_view_url = $profile['url'];
		if ( empty( $profile['reference'] ) ) {
			// Initiate consumer session
			try {
				$result = $this->gateway->core->initiateConsumerSession(
					$this->gateway->culture,
					true,
					array_keys( WC()->countries->get_shipping_countries() )
				);
				$js_view_url = $result->getOperationByRel( 'view-consumer-identification' );
			} catch ( Exception $e ) {
				$profile['reference'] = null;
				$profile['billing']   = null;
			}
		}

		WC()->session->set( 'consumer_js_url', $js_view_url );

		ob_start();
		wc_get_template(
			'checkout/swedbank-pay/shortcode-checkout.php',
			array(
				'cart' => WC()->cart,
				'checkout' => WC()->checkout(),
				'consumer_js_url' => $js_view_url,
				'consumer_data' => $profile['billing'],
				'consumer_profile' => $profile['reference'],
				'available_gateways' => WC()->payment_gateways->get_available_payment_gateways(),
				'order_button_text'  => apply_filters( 'woocommerce_pay_order_button_text', __( 'Pay for order', 'woocommerce' ) ),
			),
			'',
			dirname( __FILE__ ) . '/../templates/'
		);
		$return_string = ob_get_contents();
		ob_end_clean();

		return $return_string;
	}

	/**
	 * Checkout initialization
	 *
	 * @param \WC_Checkout $checkout
	 */
	public function checkout_init( $checkout ) {
		//
	}

	public function get_checkout_url( $url ) {
		return $url;
	}

	/**
	 * Get Checkout Gateway.
	 *
	 * @return WC_Gateway_Swedbank_Pay_Checkout
	 * @throws Exception
	 */
	private function get_gateway() {
		$gateways = WC()->payment_gateways()->payment_gateways();
		foreach ( $gateways as $gateway ) {
			if ( 'payex_checkout' === $gateway->id ) {
				return $gateway;
			}
		}

		throw new Exception('Checkout payment gateway is unavailable.');
	}

	public function override_template( $located, $template_name, $args, $template_path, $default_path ) {
		if ( strpos( $located, 'checkout/payment.php' ) !== false ) {
			$located = wc_locate_template(
				'checkout/swedbank-pay/payment.php',
				$template_path,
				dirname( __FILE__ ) . '/../templates/'
			);
		}

		return $located;
	}

	public function override_checkout_page() {
		global $wp;

		if ( 'yes' !== $this->shortcode_enabled ) {
			return;
		}

		if ( ! is_checkout() ) {
			return;
		}

		if ( ! empty( $wp->query_vars['order-received'] ) || ! empty( $wp->query_vars['order-pay'] ) ) {
			return;
		}

		$url = null;

		$args = array(
			'post_status' => 'publish',
		);

		$pages = get_pages( $args );
		foreach ( $pages as $page) {
			/** @var \WP_Post $page */
			if ( has_shortcode( $page->post_content, self::SHORTCODE ) ) {
				$url = get_page_link( $page );
				break;
			}
		}

		if ( ! $url ) {
			return;
		}

		if ( isset( $_GET['payment_url'] ) ) {
			$url = add_query_arg( 'payment_url', '1', $url );
		}

		wp_redirect( $url );
		exit();
	}

	/**
	 * Ajax: Submit Order.
	 *
	 * @throws Exception
	 */
	public function ajax_sbp_submit_order() {
		check_ajax_referer( 'swedbank_pay_checkout', 'nonce' );

		$data = array();
		parse_str( $_POST['data'], $data );
		$_POST = $data;
		unset( $_POST['terms-field'], $_POST['terms'] );

		$_POST['payment_method'] = 'payex_checkout';

		$_REQUEST['woocommerce-process-checkout-nonce'] = wp_create_nonce( 'woocommerce-process_checkout' );
		$_POST['_wpnonce']                              = wp_create_nonce( 'woocommerce-process_checkout' );

		WC()->checkout()->process_checkout();
	}

	/**
	 * Ajax: Update Order.
	 *
	 * @throws Exception
	 */
	public function ajax_sbp_update_order() {
		check_ajax_referer( 'swedbank_pay_checkout', 'nonce' );

		// Get Order
		$order_id = absint( WC()->session->get( 'order_awaiting_payment' ) );
		if ( ! $order_id ) {
			wp_send_json(
				array(
					'result'   => 'failure',
					'messages' => 'Order is not exists',
				)
			);

			return;
		}

		// Get Order
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json(
				array(
					'result'   => 'failure',
					'messages' => 'Order is not exists',
				)
			);

			return;
		}

		// Mark order failed instead of cancelled
		if ( $order->get_payment_method() === $this->gateway->id && $order->has_status( 'cancelled' ) ) {
			$order->update_status( 'failed' );
		}

		// Prepare $_POST data
		$data = array();
		parse_str( $_POST['data'], $data );
		$_POST = $data;
		unset( $_POST['terms-field'], $_POST['terms'] );

		$_POST['payment_method'] = $this->gateway->id;
		$_POST['is_update'] = '1';

		// Update Checkout
		// @see WC_AJAX::update_order_review()
		if ( ! empty( $_POST['shipping_method'] ) && ! is_array( $_POST['shipping_method'] ) ) {
			$shipping                 = $_POST['shipping_method'];
			$_POST['shipping_method'] = array( $shipping );
		}

		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
		if ( isset( $_POST['shipping_method'] ) && is_array( $_POST['shipping_method'] ) ) {
			foreach ( $_POST['shipping_method'] as $i => $value ) {
				$chosen_shipping_methods[ $i ] = wc_clean( $value );
			}
		}

		WC()->session->set( 'chosen_shipping_methods', $chosen_shipping_methods );
		WC()->session->set( 'chosen_payment_method', $this->gateway->id );

		// Update address data
		foreach ( $_POST as $key => $value ) {
			if ( ( strpos( $key, 'billing_' ) !== false ) || ( strpos( $key, 'shipping_' ) !== false ) ) {
				if ( is_callable( array( $order, "set_{$key}" ) ) ) {
					$order->{"set_{$key}"}( $value );
				}

				WC()->customer->set_props( array( $key => $value ) );
			}
		}

		// Recalculate cart
		WC()->customer->set_calculated_shipping( true );
		WC()->customer->save();
		WC()->cart->calculate_totals();

		// Recalculate order
		$order->set_cart_hash( WC()->cart->get_cart_hash() );
		$order->calculate_totals( true );
		$order->save();

		// Process checkout
		$_REQUEST['woocommerce-process-checkout-nonce'] = wp_create_nonce( 'woocommerce-process_checkout' );
		$_POST['_wpnonce']                              = wp_create_nonce( 'woocommerce-process_checkout' );
		WC()->checkout()->process_checkout();
	}

	/**
	 * Ajax: Calculate totals
	 *
	 * @throws Exception
	 */
	public function ajax_sbp_recalculate() {
		check_ajax_referer( 'swedbank_pay_checkout', 'nonce' );

		$cart = WC()->cart;
		$cart->calculate_totals();
		$cart->calculate_shipping();
		$cart->calculate_fees();

		$order_id = absint( WC()->session->get( 'order_awaiting_payment' ) );
		if ( $order_id > 0 ) {
			$order = wc_get_order( $order_id );

			if ( $order ) {
				// Recalculate order
				$order->calculate_totals( true );
				$order->save();
			}
		}
	}
}

new WC_Shortcode_Checkout();
