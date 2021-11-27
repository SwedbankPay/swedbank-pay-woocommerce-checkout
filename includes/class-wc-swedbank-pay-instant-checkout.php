<?php

namespace SwedbankPay\Checkout\WooCommerce;

defined( 'ABSPATH' ) || exit;

use Exception;
use WC_Gateway_Swedbank_Pay_Checkout;
use WC_Customer;
use WP_Error;

class WC_Swedbank_Pay_Instant_Checkout {
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
	 * Locale
	 * @var string
	 */
	public $culture = 'en-US';

	/**
	 * Checkout method
	 * @var string
	 */
	public $method = WC_Gateway_Swedbank_Pay_Checkout::METHOD_SEAMLESS;

	/**
	 * Use Instant Checkout
	 * @var string
	 */
	public $instant_checkout = 'no';

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

		// Settings of the gateway
		$this->enabled          = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'no';
		$this->culture          = isset( $this->settings['culture'] ) ? $this->settings['culture'] : $this->culture;
		$this->method           = isset( $this->settings['method'] ) ? $this->settings['method'] : $this->method;

		// Checkin settings
		$this->instant_checkout = isset( $this->settings['instant_checkout'] ) ? $this->settings['instant_checkout'] : $this->instant_checkout;

		// JS Scrips
		add_action( 'wp_enqueue_scripts', array( $this, 'add_scripts' ) );

		// Instant Checkout
		if ( 'yes' === $this->enabled && 'yes' === $this->instant_checkout ) {
			// Override "payments" template to remove "Place order" button
			add_action( 'woocommerce_checkout_order_review', array( $this, 'woocommerce_checkout_payment' ), 20 );
			add_filter( 'wc_get_template', array( $this, 'override_template' ), 5, 20 );
			add_filter( 'woocommerce_available_payment_gateways', array( $this, 'filter_gateways' ), 1 );
			add_action( 'woocommerce_before_checkout_form_cart_notices', array( $this, 'init_order' ) );
		}

		// Ajax Actions
		add_action( 'wp_ajax_swedbank_pay_place_order', array( $this, 'ajax_swedbank_pay_place_order' ) );
		add_action( 'wp_ajax_nopriv_swedbank_pay_place_order', array( $this, 'ajax_swedbank_pay_place_order' ) );

		add_action( 'wp_ajax_swedbank_pay_update_order', array( $this, 'ajax_swedbank_pay_update_order' ) );
		add_action( 'wp_ajax_nopriv_swedbank_pay_update_order', array( $this, 'ajax_swedbank_pay_update_order' ) );
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
		$form_fields['instant_checkout'] = array(
			'title'   => __(
				'Use Swedbank Pay Checkout instead of WooCommerce Checkout',
				'swedbank-pay-woocommerce-checkout'
			),
			'type'    => 'checkbox',
			'label'   => __(
				'Use Swedbank Pay Checkout instead of WooCommerce Checkout',
				'swedbank-pay-woocommerce-checkout'
			),
			'default' => $this->instant_checkout,
		);

		return $form_fields;
	}

	/**
	 * add_scripts function.
	 *
	 * Outputs scripts
	 *
	 * @return void
	 */
	public function add_scripts() {
		if ( ! is_checkout() || 'no' === $this->enabled ) {
			return;
		}

		if ( 'no' === $this->instant_checkout ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		if ( is_checkout() || isset( $_GET['pay_for_order'] ) || is_add_payment_method_page() ) {
			// Common styles
			wp_enqueue_style(
				'swedbank-pay-checkout-css',
				untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/css/style' . $suffix . '.css',
				array(),
				false,
				'all'
			);

			if ( 'yes' === $this->instant_checkout ) {
				// Add the style which hides "payment methods list"
				wp_enqueue_style(
					'swedbank-pay-checkout-instant',
					untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/css/instant-checkout' . $suffix . '.css',
					array(),
					false,
					'all'
				);
			}

			wp_register_script(
				'wc-sb-common',
				untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/js/common' . $suffix . '.js',
				array(
					'jquery',
					'wc-checkout',
				),
				false,
				true
			);

			// This script uses for Instant and non-instant checkout
			// Non-instant checkout uses featherlight
			wp_register_script(
				'wc-gateway-swedbank-pay-checkout',
				untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/js/instant-checkout' . $suffix . '.js',
				array(
					'jquery',
					'wc-checkout',
					'wc-sb-common',
				),
				false,
				true
			);

			if ( 'no' === $this->instant_checkout ) {
				// Add featherlight for Non-Instant Checkout
				wp_enqueue_script(
					'featherlight',
					untrailingslashit(
						plugins_url(
							'/',
							__FILE__
						)
					) . '/../assets/js/featherlight/featherlight' . $suffix . '.js',
					array( 'jquery' ),
					'1.7.13',
					true
				);

				wp_enqueue_style(
					'featherlight-css',
					untrailingslashit(
						plugins_url(
							'/',
							__FILE__
						)
					) . '/../assets/js/featherlight/featherlight' . $suffix . '.css',
					array(),
					'1.7.13',
					'all'
				);
			}

			// Localize the script with new data
			$translation_array = array(
				'culture'                      => $this->culture,
				'instant_checkout'             => $this->instant_checkout,
				'method'                       => $this->method,
				'payment_url'                  => WC()->session->get( 'sb_payment_url' ),
				'nonce'                        => wp_create_nonce( 'swedbank_pay_checkout' ),
				'ajax_url'                     => admin_url( 'admin-ajax.php' ),
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

			// Add PM styles
			$styles = apply_filters( 'swedbank_pay_checkout_paymentmenu_style', $this->gateway->payment_menu_style );
			if ( $styles ) {
				$translation_array['paymentMenuStyle'] = $styles;
			}

			wp_localize_script(
				'wc-gateway-swedbank-pay-checkout',
				'WC_Gateway_Swedbank_Pay_Checkout',
				$translation_array
			);

			// Enqueued script with localized data.
			wp_enqueue_script( 'wc-sb-common' );
			wp_enqueue_script( 'wc-gateway-swedbank-pay-checkout' );
		}
	}

	/**
	 * Process the payment.
	 *
	 * @param mixed $order_id
	 *
	 * @return array|false|WP_Error
	 * @throws \SwedbankPay\Core\Exception
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( isset( $_POST['is_update'] ) ) {
			$order->calculate_totals( true );

			// Get Payment Order ID
			$payment_id = $order->get_meta( '_payex_paymentorder_id', true );
			if ( empty( $payment_id ) ) {
				$payment_id = WC()->session->get( 'payex_paymentorder_id' );
			}

			if ( empty( $payment_id ) ) {
				// PaymentOrder is unknown
				return false;
			}

			$result     = $this->gateway->core->fetchPaymentInfo( $payment_id );
			$js_url     = $result->getOperationByRel( 'view-paymentorder' );
			$update_url = $result->getOperationByRel( 'update-paymentorder-updateorder' );
			if ( empty( $update_url ) ) {
				return new WP_Error(
					'checkout_error',
					'Order update is not available.'
				);
			}

			// Don't update if amount is not changed
			if ( round( $order->get_total() * 100 ) === (int) $result['payment_order']['amount'] ) {
				return array(
					'result'                   => 'success',
					'redirect'                 => '#!swedbank-pay-checkout',
					'is_swedbank_pay_checkout' => true,
					'js_url'                   => $js_url,
					'redirect_url'             => $result->getOperationByRel( 'redirect-paymentorder' ),
					'payment_id'               => $result['payment_order']['id'],
				);
			}

			// Update Order
			$result = $this->gateway->core->updatePaymentOrder( $update_url, $order->get_id() );

			$js_url = $result->getOperationByRel( 'view-paymentorder' );
			$redirect_url = $result->getOperationByRel( 'redirect-paymentorder' );

			$order->update_meta_data( '_sb_view_paymentorder', $js_url );
			$order->update_meta_data( '_sb_redirect_paymentorder', $redirect_url );
			$order->save_meta_data();

			WC()->session->set( 'sb_payment_url', $js_url );

			return array(
				'result'                   => 'success',
				'redirect'                 => '#!swedbank-pay-checkout',
				'is_swedbank_pay_checkout' => true,
				'js_url'                   => $result->getOperationByRel( 'view-paymentorder' ),
				'redirect_url'             => $result->getOperationByRel( 'redirect-paymentorder' ),
			);
		}

		// Get Consumer Profile
		$reference = isset( $_POST['swedbank_pay_customer_reference'] ) ? wc_clean( $_POST['swedbank_pay_customer_reference'] ) : null;
		if ( empty( $reference ) ) {
			$profile   = $this->gateway->get_consumer_profile( $order->get_user_id() );
			$reference = $profile['reference'];
		}

		if ( function_exists('wcs_order_contains_subscription') && wcs_order_contains_subscription( $order ) ) {
			$generate_token = true;

			// Flag that allows save token
			$order->update_meta_data( '_payex_generate_token', '1' );
			$order->save();
		} else {
			$generate_token = false;

			$order->delete_meta_data( '_payex_generate_token' );
			$order->save();
		}

		// Initiate Payment Order
		try {
			$result = $this->gateway->core->initiatePaymentOrderPurchase( $order_id, $reference, $generate_token );
		} catch ( \SwedbankPay\Core\Exception $e ) {
			$problems = $e->getProblems();

			// Check problems
			foreach ( $problems as $problem ) {
				// PayeeReference: The given PayeeReference has already been used for another payment (xxxxx).
				// @todo Check cause of different name "PaymentOrder.PayeeInfo.PayeeReference"
				if ( 'PayeeReference' === $problem['name'] ) {
					return $this->process_payment( $order_id );
				}

				// PaymentOrder.Payer.BillingAddress.Msisdn: The Msisdn is not valid, no configuration for +45739000001 exist.
				if ( in_array(
					$problem['name'],
					[ 'PaymentOrder.Payer.BillingAddress.Msisdn', 'PaymentOrder.Payer.ShippingAddress.Msisdn' ],
					true )
				) {
					if ( $order->get_user_id() > 0 ) {
						$customer = new WC_Customer( $order->get_user_id(), true );
						$customer->set_billing_phone( '' );
						$customer->save();

						clean_user_cache( $customer->get_id() );
					}

					$order->set_billing_phone( '' );
					$order->save();
					clean_post_cache( $order->get_id() );

					wc_add_notice(
						__(
							'Phone number is invalid. Please change it and submit the form again.',
							'swedbank-pay-woocommerce-checkout'
						),
						'error'
					);

					return array(
						'result'   => 'failure',
						'messages' => __(
							'Phone number is invalid. Please change it and submit the form again.',
							'swedbank-pay-woocommerce-checkout'
						),
						'reload'   => true,
					);
				}

				// consumerProfileRef: Reference *** is not active, unable to complete
				if ( 'consumerProfileRef' === $problem['name'] ) {
					// Remove the inactive customer reference
					$_POST['swedbank_pay_customer_reference'] = null;
					$this->gateway->drop_consumer_profile( $order->get_user_id() );

					// Remove saved address
					//delete_user_meta( $order->get_user_id(), '_payex_consumer_address_billing' );
					//delete_user_meta( $order->get_user_id(), '_payex_consumer_address_shipping' );
					//WC()->session->__unset( 'swedbank_pay_checkin_billing' );
					//WC()->session->__unset( 'swedbank_pay_checkin_shipping' );

					// Reload checkout
					if ( 'yes' === $this->instant_checkout ) {
						wc_add_notice(
							__(
								'Unable to verify consumer profile reference. Try to login again.',
								'swedbank-pay-woocommerce-checkout'
							),
							'error'
						);

						return array(
							'result'   => 'failure',
							'messages' => __(
								'Unable to verify consumer profile reference. Try to login again.',
								'swedbank-pay-woocommerce-checkout'
							),
							'reload'   => true,
						);
					}

					// Try again
					return $this->gateway->process_payment( $order_id );
				}
			}

			return new WP_Error(
				'checkout_error',
				$e->getMessage()
			);
		} catch ( Exception $e ) {
			return new WP_Error(
				'checkout_error',
				$e->getMessage()
			);
		}

		// Save payment ID
		$order->update_meta_data( '_payex_paymentorder_id', $result['payment_order']['id'] );
		$order->save_meta_data();

		WC()->session->set( 'swedbank_paymentorder_id', $result['payment_order']['id'] );

		// Get JS Url
		$js_url = $result->getOperationByRel( 'view-paymentorder' );
		$redirect_url = $result->getOperationByRel( 'redirect-paymentorder' );

		$order->update_meta_data( '_sb_view_paymentorder', $js_url );
		$order->update_meta_data( '_sb_redirect_paymentorder', $redirect_url );
		$order->save_meta_data();

		// Save JS Url in session
		WC()->session->set( 'swedbank_paymentorder_id', $result['payment_order']['id'] );
		WC()->session->set( 'swedbank_pay_checkout_js_url', $js_url );
		WC()->session->set( 'sb_payment_url', $js_url );

		return array(
			'result'                   => 'success',
			'redirect'                 => '#!swedbank-pay-checkout',
			'is_swedbank_pay_checkout' => true,
			'js_url'                   => $js_url,
			'redirect_url'             => $redirect_url,
			'payment_id'               => $result['payment_order']['id'],
		);
	}

	/**
	 * Ajax: Place Order.
	 *
	 * @throws Exception
	 */
	public function ajax_swedbank_pay_place_order() {
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
	 * Init Order
	 */
	public function init_order() {
		$order_id = absint( WC()->session->get( 'order_awaiting_payment' ) );
		if ( $order_id > 0 ) {
			$order = wc_get_order( $order_id );
			$order->set_payment_method( $this->gateway->id );
			$order->save();
		} else {
			// Place Order
			$customer = WC()->customer;
			$order_id = WC()->checkout()->create_order(
				array(
					'payment_method'      => $this->gateway->id,
					'billing_first_name'  => $customer->get_billing_first_name(),
					'billing_last_name'   => $customer->get_billing_last_name(),
					'billing_company'     => $customer->get_billing_company(),
					'billing_address_1'   => $customer->get_billing_address_1(),
					'billing_address_2'   => $customer->get_billing_address_2(),
					'billing_city'        => $customer->get_billing_city(),
					'billing_state'       => $customer->get_billing_state(),
					'billing_postcode'    => $customer->get_billing_postcode(),
					'billing_country'     => $customer->get_billing_country(),
					'billing_email'       => $customer->get_billing_email(),
					'billing_phone'       => $customer->get_billing_phone(),
					'shipping_first_name' => $customer->get_shipping_first_name(),
					'shipping_last_name'  => $customer->get_shipping_last_name(),
					'shipping_company'    => $customer->get_shipping_company(),
					'shipping_address_1'  => $customer->get_shipping_address_1(),
					'shipping_address_2'  => $customer->get_shipping_address_2(),
					'shipping_city'       => $customer->get_shipping_city(),
					'shipping_state'      => $customer->get_shipping_state(),
					'shipping_postcode'   => $customer->get_shipping_postcode(),
					'shipping_country'    => $customer->get_shipping_country(),
					'shipping_email'      => $customer->get_billing_email(),
					'shipping_phone'      => $customer->get_billing_phone(),
				)
			);

			if ( is_wp_error( $order_id ) ) {
				throw new Exception( $order_id->get_error_message() );
			}

			// Store Order ID in session so it can be re-used after payment failure
			WC()->session->set( 'order_awaiting_payment', $order_id );
		}

		// Get Customer Profile
		$profile = $this->gateway->get_consumer_profile( get_current_user_id() );

		// Initiate Payment Order
		$_POST['swedbank_pay_customer_reference'] = $profile['reference'];
		$result                                   = $this->gateway->process_payment( $order_id );
		if ( is_array( $result ) && isset( $result['js_url'] ) ) {
			WC()->session->set( 'swedbank_pay_checkout_js_url', $result['js_url'] );
		} else {
			WC()->session->__unset( 'swedbank_pay_checkout_js_url' );
		}
	}

	/**
	 * Render Payment Methods HTML.
	 *
	 * @return void
	 */
	public function woocommerce_checkout_payment() {
		$js_url = WC()->session->get( 'swedbank_pay_checkout_js_url' );

		wc_get_template(
			'checkout/swedbank-pay/instant-checkout/payment.php',
			array(
				//'checkout' => WC()->checkout()
				'js_url' => $js_url,
			),
			'',
			dirname( __FILE__ ) . '/../templates/'
		);
	}

	/**
	 * Unset all payment methods except Swedbank Pay Checkout
	 *
	 * @param $gateways
	 *
	 * @return mixed
	 */
	public function filter_gateways( $gateways ) {
		if ( is_admin() ) {
			return $gateways;
		}

		foreach ( $gateways as $gateway ) {
			if ( $gateway->id !== $this->gateway->id ) {
				unset( $gateways[ $gateway->id ] );
			}
		}

		return $gateways;
	}

	/**
	 * Override Standard Checkout template
	 *
	 * @param $located
	 * @param $template_name
	 * @param $args
	 * @param $template_path
	 * @param $default_path
	 *
	 * @return string
	 */
	public function override_template( $located, $template_name, $args, $template_path, $default_path ) {
		if ( strpos( $located, 'checkout/form-checkout.php' ) !== false ) {
			$located = wc_locate_template(
				'checkout/swedbank-pay/form-checkout.php',
				$template_path,
				dirname( __FILE__ ) . '/../templates/'
			);
		}

		return $located;
	}

	/**
	 * Ajax: Update Order's data.
	 *
	 * @throws Exception
	 */
	public function ajax_swedbank_pay_update_order() {
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
}
