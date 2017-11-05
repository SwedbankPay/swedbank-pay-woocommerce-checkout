<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly


class WC_Gateway_Payex_Checkout extends WC_Payment_Gateway_Payex
	implements WC_Payment_Gateway_Payex_Interface {

	/**
	 * Merchant Token
	 * @var string
	 */
	public $merchant_token = '';

	/**
	 * Test Mode
	 * @var string
	 */
	public $testmode = 'yes';

	/**
	 * Debug Mode
	 * @var string
	 */
	public $debug = 'yes';

	/**
	 * Locale
	 * @var string
	 */
	public $culture = 'en-US';

	/**
	 * Frontend Api Endpoint
	 * @var string
	 */
	public $frontend_api_endpoint = 'https://checkout.payex.com/js/payex-checkout.min.js';

	/**
	 * Init
	 */
	public function __construct() {
		$this->transactions = WC_Payex_Transactions::instance();

		$this->id           = 'payex_checkout';
		$this->has_fields   = TRUE;
		$this->method_title = __( 'PayEx Checkout', 'woocommerce-gateway-payex-checkout' );
		$this->icon         = apply_filters( 'woocommerce_payex_payment_icon', plugins_url( '/assets/images/payex.gif', dirname( __FILE__ ) ) );
		$this->supports     = array(
			'products',
			'refunds',
		);

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables
		$this->enabled        = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'no';
		$this->title          = isset( $this->settings['title'] ) ? $this->settings['title'] : '';
		$this->description    = isset( $this->settings['description'] ) ? $this->settings['description'] : '';
		$this->merchant_token = isset( $this->settings['merchant_token'] ) ? $this->settings['merchant_token'] : $this->merchant_token;
		$this->testmode       = isset( $this->settings['testmode'] ) ? $this->settings['testmode'] : $this->testmode;
		$this->debug          = isset( $this->settings['debug'] ) ? $this->settings['debug'] : $this->debug;
		$this->culture        = isset( $this->settings['culture'] ) ? $this->settings['culture'] : $this->culture;

		if ( $this->testmode === 'yes' ) {
			$this->frontend_api_endpoint = 'https://checkout.externalintegration.payex.com/js/payex-checkout.min.js';
			$this->backend_api_endpoint  = 'https://api.externalintegration.payex.com';
		}

		// JS Scrips
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		) );
		add_action( 'woocommerce_thankyou_' . $this->id, array(
			$this,
			'thankyou_page'
		) );
		// Payment listener/API hook
		add_action( 'woocommerce_api_wc_gateway_' . $this->id, array(
			$this,
			'return_handler'
		) );

		add_action( 'woocommerce_checkout_update_order_meta', array(
			$this,
			'set_order_reference'
		), 10, 2 );

		// Add PayEx button to Cart Page
		add_action( 'woocommerce_proceed_to_checkout', array(
			$this,
			'add_px_button_to_cart'
		) );

		// Add PayEx button to Product Page
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '3.0.0', '<' ) ) {
			add_action( 'woocommerce_after_add_to_cart_button', array(
				$this,
				'add_px_button_to_product_page'
			), 1 );
		} else {
			add_action( 'woocommerce_after_add_to_cart_quantity', array(
				$this,
				'add_px_button_to_product_page'
			), 1 );
		}

		add_action( 'wp_loaded', array( $this, 'action_buy_now' ), 20 );
	}

	/**
	 * Initialise Settings Form Fields
	 * @return string|void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'        => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-gateway-payex-checkout' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable plugin', 'woocommerce-gateway-payex-checkout' ),
				'default' => 'no'
			),
			'title'          => array(
				'title'       => __( 'Title', 'woocommerce-gateway-payex-checkout' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-payex-checkout' ),
				'default'     => __( 'PayEx Checkout', 'woocommerce-gateway-payex-checkout' )
			),
			'description'    => array(
				'title'       => __( 'Description', 'woocommerce-gateway-payex-checkout' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-payex-checkout' ),
				'default'     => __( 'PayEx Checkout', 'woocommerce-gateway-payex-checkout' ),
			),
			'merchant_token' => array(
				'title'       => __( 'Merchant Token', 'woocommerce-gateway-payex-checkout' ),
				'type'        => 'text',
				'description' => __( 'Merchant Token', 'woocommerce-gateway-payex-checkout' ),
				'default'     => $this->merchant_token
			),
			'testmode'       => array(
				'title'   => __( 'Test Mode', 'woocommerce-gateway-payex-checkout' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable PayEx Test Mode', 'woocommerce-gateway-payex-checkout' ),
				'default' => $this->testmode
			),
			'debug'          => array(
				'title'   => __( 'Debug', 'woocommerce-gateway-payex-checkout' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable logging', 'woocommerce-gateway-payex-checkout' ),
				'default' => $this->debug
			),
			'culture'        => array(
				'title'       => __( 'Language', 'woocommerce-gateway-payex-checkout' ),
				'type'        => 'select',
				'options'     => array(
					'en-US' => 'English',
					'sv-SE' => 'Swedish',
					'nb-NO' => 'Norway',
				),
				'description' => __( 'Language of pages displayed by PayEx during payment.', 'woocommerce-gateway-payex-checkout' ),
				'default'     => $this->culture
			),
		);
	}

	/**
	 * payment_scripts function.
	 *
	 * Outputs scripts used for payment
	 *
	 * @return void
	 */
	public function payment_scripts() {
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() && ! is_product() ) {
			return;
		}

		// Add frontend script by PayEx
		wp_enqueue_script( 'payex-checkout', $this->frontend_api_endpoint, NULL, NULL, TRUE );

		// Checkout scripts
		if ( is_checkout() || isset( $_GET['pay_for_order'] ) || is_add_payment_method_page() ) {
			wp_register_script( 'wc-gateway-payex-checkout', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/js/checkout.js', array(
				'jquery',
				'wc-checkout',
				'payex-checkout'
			), FALSE, TRUE );

			// Localize the script with new data
			$translation_array = array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
			);
			wp_localize_script( 'wc-gateway-payex-checkout', 'WC_Gateway_PayEx_Checkout', $translation_array );

			// Enqueued script with localized data.
			wp_enqueue_script( 'wc-gateway-payex-checkout' );
		}

		// Cart Scripts
		if ( is_cart() || is_product() ) {
			wp_register_script( 'wc-gateway-payex-cart', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/js/cart.js', array( 'payex-checkout' ), NULL, TRUE );

			// Localize the script with new data
			$translation_array = array(
				'ajax_url'     => admin_url( 'admin-ajax.php' ),
				'redirect_url' => add_query_arg( 'action', 'pxcheckout', WC()->api_request_url( __CLASS__ ) )
			);
			wp_localize_script( 'wc-gateway-payex-cart', 'WC_Gateway_PayEx_Cart', $translation_array );

			// Enqueued script with localized data.
			wp_enqueue_script( 'wc-gateway-payex-cart' );
		}
	}

	/**
	 * If There are no payment fields show the description if set.
	 */
	public function payment_fields() {
		$order_id = uniqid( 'cart_' );
		$currency = get_woocommerce_currency();
		$total    = WC()->cart->total;

		// If paying from order, we need to get total from order not cart.
		if ( isset( $_GET['pay_for_order'] ) && ! empty( $_GET['key'] ) ) {
			$order    = wc_get_order( wc_get_order_id_by_order_key( wc_clean( $_GET['key'] ) ) );
			$order_id = px_obj_prop( $order, 'id' );
			$total    = $order->get_total();
			$currency = px_obj_prop( $order, 'currency' );
		}

		// Save Reference in session
		WC()->session->set( 'payex_checkout_reference', $order_id );

		try {
			$payment_session_url = $this->init_payment_session();
			WC()->session->set( 'payex_payment_session', $payment_session_url );

			$result = $this->init_payment( $payment_session_url, $order_id, $total, $currency );
			WC()->session->set( 'payex_payment_id', $result['id'] );

			echo '<div id="payex-payment-data" data-payment-id="' . esc_attr( $result['id'] ) . '" ></div';
		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );

			return;
		}

		parent::payment_fields();
	}

	/**
	 * Validate Frontend Fields
	 * @return bool|void
	 */
	public function validate_fields() {
		//
	}

	/**
	 * Thank you page
	 *
	 * @param $order_id
	 */
	public function thankyou_page( $order_id ) {
		WC()->session->__unset( 'payex_payment_id' );
		WC()->session->__unset( 'payex_payment_session' );
	}

	/**
	 * Process Payment
	 *
	 * @param int $order_id
	 *
	 * @return array|false
	 */
	public function process_payment( $order_id ) {
		$order           = wc_get_order( $order_id );
		$payment_session = WC()->session->get( 'payex_payment_session' );
		if ( empty( $payment_session ) ) {
			wc_add_notice( 'Undefined payment session', 'error' );

			return FALSE;
		}

		$payment_id = WC()->session->get( 'payex_payment_id' );
		if ( empty( $payment_id ) ) {
			wc_add_notice( 'Undefined payment id', 'error' );

			return FALSE;
		}

		try {
			// Get Payment Session
			$result = $this->request( 'GET', $payment_session );
			if ( ! empty( $result['addressee'] ) ) {
				$address_url = $result['addressee'];
				$result      = $this->request( 'GET', $address_url );
				if ( ! isset( $result['payment'] ) ) {
					throw new Exception( 'Invalid payment response' );
				}

				// Parse name
				$parser = new FullNameParser();
				$name   = $parser->parse_name( $result['fullName'] );

				// Update address fields
				$address = array(
					'first_name' => $name['fname'],
					'last_name'  => $name['lname'],
					'company'    => '',
					'address_1'  => $result['address']['streetAddress'],
					'address_2'  => ! empty( $result['address']['coAddress'] ) ? 'c/o ' . $result['address']['coAddress'] : '',
					'city'       => $result['address']['city'],
					'state'      => '',
					'postcode'   => $result['address']['zipCode'],
					'country'    => $result['address']['countryCode'],
					'email'      => $result['email'],
					'phone'      => $result['mobilePhoneNumber'],
				);
				$order->set_address( $address, 'billing' );
				$order->set_address( $address, 'shipping' );
			}


			// Get Payment Url
			$result = $this->request( 'GET', $payment_id );
			if ( ! isset( $result['payment'] ) ) {
				throw new Exception( 'Invalid payment response' );
			}

			// Get Payment Status
			$payment_url = $result['payment'];
			$result      = $this->request( 'GET', $payment_url );
			if ( ! isset( $result['payment'] ) ) {
				throw new Exception( 'Invalid payment response' );
			}

		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );

			return FALSE;
		}

		update_post_meta( $order_id, '_payex_payment_id', $payment_id );
		update_post_meta( $order_id, '_payex_payment_url', $payment_url );
		update_post_meta( $order_id, '_payex_payment_state', $result['payment']['state'] );
		foreach ( $result['operations'] as $id => $operation ) {
			update_post_meta( $order_id, '_payex_operation_' . $operation['rel'], $operation['href'] );
		}

		switch ( $result['payment']['state'] ) {
			case 'Ready':
				$order->update_status( 'on-hold', 'Payment accepted' );
				WC()->cart->empty_cart();
				break;
			case 'Pending':
				$order->update_status( 'on-hold', 'Payment pending' );
				WC()->cart->empty_cart();
				break;
			case 'Failed':
			case 'Aborted':
				$order->update_status( 'cancelled', 'Payment ' . $result['payment']['state'] );
				break;
			default:
				$order->update_status( 'cancelled', 'Unknown payment state' );
				break;
		}

		return in_array( $result['payment']['state'], array(
			'Ready',
			'Pending'
		) ) ? array(
			'result'   => 'success',
			'redirect' => html_entity_decode( $this->get_return_url( $order ) )
		) : array(
			'result'   => 'success',
			'redirect' => $order->get_cancel_order_url_raw()
		);
	}

	/**
	 * Set Checkout Reference for Order
	 *
	 * @param $order_id
	 * @param $data
	 *
	 * @return void
	 */
	public function set_order_reference( $order_id, $data ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_payment_method() !== $this->id ) {
			return;
		}

		$reference = WC()->session->__get( 'payex_checkout_reference' );
		if ( ! empty( $reference ) ) {
			update_post_meta( $order_id, '_payex_checkout_reference', $reference );
			WC()->session->__unset( 'payex_checkout_reference' );
		}
	}

	/**
	 * IPN Callback
	 * @todo Implement transactions import
	 * @throws \Exception
	 * @return void
	 */
	public function return_handler() {
		if ( wc_clean( $_GET['action'] ) === 'pxcheckout' ) {
			$this->process_payex_checkout();
			return;
		}

		// IPN
		$this->log( sprintf( 'IPN: Initialized %s from %s', $_SERVER['REQUEST_URI'], px_get_remote_address() ) );

		// Get body
		$raw_body = file_get_contents( 'php://input' );
		$this->log( sprintf( 'IPN: Raw body: %s', $raw_body ) );

		$reference = wc_clean( $_GET['reference'] );
		if ( empty( $reference ) ) {
			$this->log( 'IPN: Error: Undefined checkout reference' );

			return;
		}

		$order_id = $this->get_post_id_by_meta( '_payex_checkout_reference', $reference );
		if ( ! $order_id ) {
			$this->log( sprintf( 'IPN: Error: Failed to get order Id by reference %s', $reference ) );

			return;
		}

		// Get Order
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			$this->log( sprintf( 'IPN: Error: Failed to get order Id %s', $order_id ) );

			return;
		}

		// Decode raw body
		$data = @json_decode( $raw_body, TRUE );
		//$this->log( var_export( $data, TRUE ) );

		if ( ! isset( $data['transaction'] ) || ! isset( $data['transaction']['id'] ) ) {
			$this->log( 'IPN: Error: Invalid transaction value' );

			return;
		}

		$url    = $data['transaction']['id'];
		$result = $this->request( 'GET', $url );
		$this->log( sprintf( 'IPN: Debug: Transaction Url: %s. Response: %s', $url, var_export( $result, TRUE ) ) );

		// Get Action
		$action = '';
		if ( isset( $result['authorization'] ) ) {
			$action = 'authorization';
		} elseif ( isset( $result['capture'] ) ) {
			$action = 'capture';
		} elseif ( isset( $result['reversal'] ) ) {
			$action = 'reversal';
		}

		// Get State
		$state = get_post_meta( $order_id, '_payex_payment_state', TRUE );
		$this->log( sprintf( 'IPN: Debug: Action: %s. Current payment state: %s', $action, $state ) );

		// Check transaction state
		if ( $result[ $action ]['transaction']['state'] !== 'Completed' ) {
			$message = isset( $result[ $action ]['transaction']['failedReason'] ) ? $result[ $action ]['transaction']['failedReason'] : __( 'Transaction failed.', 'woocommerce-gateway-payex-checkout' );
			$this->log( sprintf( 'IPN: Error: Order %s. Transaction failed: %s', $order_id, $message ) );

			return;
		}

		switch ( $action ) {
			case 'authorization':
				if ( ! empty( $state ) ) {
					$this->log( sprintf( 'IPN: Info: Authorization: Order %s already have state "%s"', $order_id, $state ) );

					return;
				}

				$payment_id  = $result['payment'];
				$payment_url = $result['payment'];

				// Get Payment Status
				$result = $this->request( 'GET', $payment_url );
				if ( ! isset( $result['payment'] ) ) {
					$this->log( sprintf( 'IPN: Info: Authorization: "%s"', 'Invalid payment response' ) );

					return;
				}

				update_post_meta( $order_id, '_payex_payment_id', $payment_id );
				update_post_meta( $order_id, '_payex_payment_url', $payment_url );
				update_post_meta( $order_id, '_payex_payment_state', $result['payment']['state'] );
				foreach ( $result['operations'] as $id => $operation ) {
					update_post_meta( $order_id, '_payex_operation_' . $operation['rel'], $operation['href'] );
				}

				switch ( $result['payment']['state'] ) {
					case 'Ready':
						$order->update_status( 'on-hold', 'Payment accepted' );
						WC()->cart->empty_cart();
						break;
					case 'Pending':
						$order->update_status( 'on-hold', 'Payment pending' );
						WC()->cart->empty_cart();
						break;
					case 'Failed':
					case 'Aborted':
						$order->update_status( 'cancelled', 'Payment ' . $result['payment']['state'] );
						break;
					default:
						$order->update_status( 'cancelled', 'Unknown payment state' );
						break;
				}

				break;
			case 'capture':
				if ( $state === 'Captured' || $order->is_paid() ) {
					$this->log( sprintf( 'IPN: Info: Order %s already captured', $order_id ) );

					return;
				}

				update_post_meta( $order_id, '_payex_payment_state', 'Captured' );
				update_post_meta( $order_id, '_payex_transaction_capture', $result['capture']['transaction']['id'] );

				$order->add_order_note( __( 'Transaction captured.', 'woocommerce-gateway-payex-checkout' ) );
				$order->payment_complete();

				break;
			case 'reversal':
				if ( $state !== 'Captured' ) {
					$this->log( sprintf( 'IPN: Error: Unable to refund: Order %s should be captured', $order_id ) );

					return;
				}

				$amount = $result['reversal']['transaction']['amount'] / 100;
				$reason = $result['reversal']['transaction']['description'];

				// Check refund is already performed
				$refunds = $order->get_refunds();
				foreach ( $refunds as $refund ) {
					/** @var WC_Order_Refund $refund */
					$refunded = $refund->get_amount();
					if ( $refunded === $amount ) {
						$this->log( sprintf( 'IPN: Info: Order %s already refunded', $order_id ) );

						return;
					}
				}

				// Create Refund
				$refund = wc_create_refund( array(
					'amount'   => $amount,
					'reason'   => $reason,
					'order_id' => $order_id
				) );

				if ( $refund ) {
					//update_post_meta( $order_id, '_payex_payment_state', 'Refunded' );
					update_post_meta( $order_id, '_payex_transaction_refund', $result['reversal']['transaction']['id'] );
					$order->add_order_note( sprintf( __( 'Refunded: %s. Reason: %s', 'woocommerce-gateway-payex-payment' ), wc_price( $amount ), $reason ) );
				}

				break;
			default:
				//
				break;
		}

		echo 'OK';
		exit();
	}

	/**
	 * Check is Capture possible
	 *
	 * @param WC_Order|int $order
	 * @param bool         $amount
	 *
	 * @return bool
	 */
	public function can_capture( $order, $amount = FALSE ) {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		$order_id = px_obj_prop( $order, 'id' );

		$url = get_post_meta( $order_id, '_payex_operation_create-checkout-capture', TRUE );
		if ( empty( $url ) ) {
			// Capture unavailable
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Check is Cancel possible
	 *
	 * @param WC_Order|int $order
	 *
	 * @return bool
	 */
	public function can_cancel( $order ) {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		$order_id = px_obj_prop( $order, 'id' );

		// Get Payment Id
		$state = get_post_meta( $order_id, '_payex_payment_state', TRUE );
		if ( ! in_array( $state, array( 'Ready' ) ) ) {
			// Wrong payment state
			return FALSE;
		}

		$url = get_post_meta( $order_id, '_payex_operation_create-checkout-cancellation', TRUE );
		if ( empty( $url ) ) {
			// Cancellation unavailable
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Check is Refund possible
	 *
	 * @param WC_Order|int $order
	 * @param bool         $amount
	 *
	 * @return bool
	 */
	public function can_refund( $order, $amount = FALSE ) {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		$order_id = px_obj_prop( $order, 'id' );

		// Get Payment Id
		$state = get_post_meta( $order_id, '_payex_payment_state', TRUE );
		if ( ! in_array( $state, array( 'Captured' ) ) ) {
			// The transaction must be captured
			return FALSE;
		}

		$url = get_post_meta( $order_id, '_payex_operation_create-checkout-reversal', TRUE );
		if ( empty( $url ) ) {
			// Refund unavailable
			return FALSE;

		}

		return TRUE;
	}

	/**
	 * Capture
	 *
	 * @param WC_Order|int $order
	 * @param bool         $amount
	 *
	 * @throws \Exception
	 * @return void
	 */
	public function capture_payment( $order, $amount = FALSE ) {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		$order_id = px_obj_prop( $order, 'id' );

		// Get Payment Id
		$state = get_post_meta( $order_id, '_payex_payment_state', TRUE );
		if ( ! in_array( $state, array( 'Ready' ) ) ) {
			throw new Exception( __( 'Wrong payment state', 'woocommerce-gateway-payex-checkout' ) );
		}

		$url = get_post_meta( $order_id, '_payex_operation_create-checkout-capture', TRUE );
		if ( empty( $url ) ) {
			throw new Exception( __( 'Capture unavailable', 'woocommerce-gateway-payex-checkout' ) );
		}

		// Order Info
		$info = $this->get_order_info( $order );

		$params = array(
			'transaction' => array(
				'amount'      => $order->get_total(),
				'vatAmount'   => $info['vat_amount'],
				'description' => sprintf( 'Capture for Order #%s', $order_id )
			),

			'itemDescriptions' => $info['items']
		);
		$result = $this->request( 'POST', $url, $params );

		switch ( $result['capture']['transaction']['state'] ) {
			case 'Completed':
				update_post_meta( $order_id, '_payex_payment_state', 'Captured' );
				update_post_meta( $order_id, '_payex_transaction_capture', $result['capture']['transaction']['id'] );

				$order->add_order_note( __( 'Transaction captured.', 'woocommerce-gateway-payex-checkout' ) );
				$order->payment_complete();

				break;
			default:
				$message = isset( $result['capture']['transaction']['failedReason'] ) ? $result['capture']['transaction']['failedReason'] : __( 'Capture failed.', 'woocommerce-gateway-payex-checkout' );
				throw new Exception( $message );
				break;
		}
	}

	/**
	 * Cancel
	 *
	 * @param WC_Order|int $order
	 *
	 * @throws \Exception
	 * @return void
	 */
	public function cancel_payment( $order ) {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		$order_id = px_obj_prop( $order, 'id' );

		// Get Payment Id
		$state = get_post_meta( $order_id, '_payex_payment_state', TRUE );
		if ( ! in_array( $state, array( 'Ready' ) ) ) {
			throw new Exception( __( 'Wrong payment state', 'woocommerce-gateway-payex-checkout' ) );
		}

		$url = get_post_meta( $order_id, '_payex_operation_create-checkout-cancellation', TRUE );
		if ( empty( $url ) ) {
			throw new Exception( __( 'Cancellation unavailable', 'woocommerce-gateway-payex-checkout' ) );
		}

		$params = array(
			'transaction' => array(
				'description' => sprintf( 'Cancellation for Order #%s', $order_id )
			),
		);
		$result = $this->request( 'POST', $url, $params );

		switch ( $result['cancellation']['transaction']['state'] ) {
			case 'Completed':
				update_post_meta( $order_id, '_payex_payment_state', 'Cancelled' );
				update_post_meta( $order_id, '_payex_transaction_cancel', $result['cancellation']['transaction']['id'] );

				$order->add_order_note( __( 'Transaction cancelled.', 'woocommerce-gateway-payex-checkout' ) );

				break;
			default:
				$message = isset( $result['cancellation']['transaction']['failedReason'] ) ? $result['cancellation']['transaction']['failedReason'] : __( 'Cancel failed.', 'woocommerce-gateway-payex-checkout' );
				throw new Exception( $message );
				break;
		}
	}

	/**
	 * Refund
	 *
	 * @param WC_Order|int $order
	 * @param bool         $amount
	 * @param string       $reason
	 *
	 * @throws \Exception
	 * @return void
	 */
	public function refund_payment( $order, $amount = FALSE, $reason = '' ) {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		$order_id = px_obj_prop( $order, 'id' );

		// Full Refund
		if ( is_null( $amount ) ) {
			$amount = $order->get_total();
		}

		// Get Payment Id
		$state = get_post_meta( $order_id, '_payex_payment_state', TRUE );
		if ( ! in_array( $state, array( 'Captured' ) ) ) {
			throw new \Exception( __( 'Unable to perform refund. The transaction must be captured.', 'woocommerce-gateway-payex-checkout' ) );
		}

		$url = get_post_meta( $order_id, '_payex_operation_create-checkout-reversal', TRUE );
		if ( empty( $url ) ) {
			// Get Payment Status
			$payment_url = get_post_meta( $order_id, '_payex_payment_url', TRUE );
			$result      = $this->request( 'GET', $payment_url );
			if ( ! isset( $result['payment'] ) ) {
				throw new \Exception( 'Invalid payment response' );
			}

			// Update operations
			foreach ( $result['operations'] as $id => $operation ) {
				update_post_meta( $order_id, '_payex_operation_' . $operation['rel'], $operation['href'] );
			}

			$url = get_post_meta( $order_id, '_payex_operation_create-checkout-reversal', TRUE );
		}

		$params = array(
			'transaction' => array(
				'amount'      => $amount,
				'vatAmount'   => 0,
				'description' => sprintf( 'Refund for Order #%s. Reason: %s', $order_id, $reason )
			),
		);
		$result = $this->request( 'POST', $url, $params );

		switch ( $result['reversal']['transaction']['state'] ) {
			case 'Completed':
				//update_post_meta( $order_id, '_payex_payment_state', 'Refunded' );
				update_post_meta( $order_id, '_payex_transaction_refund', $result['reversal']['transaction']['id'] );
				$order->add_order_note( sprintf( __( 'Refunded: %s. Reason: %s', 'woocommerce-gateway-payex-payment' ), wc_price( $amount ), $reason ) );

				break;
			case 'Failed':
			default:
				$message = isset( $result['reversal']['transaction']['failedReason'] ) ? $result['reversal']['transaction']['failedReason'] : __( 'Refund failed.', 'woocommerce-gateway-payex-checkout' );
				throw new \Exception( $message );
				break;
		}
	}

	/**
	 * Init Payment Session
	 * @return mixed
	 * @throws \Exception
	 */
	public function init_payment_session() {
		// Init Session
		$session = $this->request( 'GET', '/psp/checkout' );
		if ( ! $session['authorized'] ) {
			throw new Exception( 'Unauthorized. Please check settings.' );
		}

		return $session['paymentSession'];
	}

	/**
	 * Init Payment
	 *
	 * @param $payment_session_url
	 * @param $reference
	 * @param $total
	 * @param $currency
	 *
	 * @return array|mixed|object
	 */
	public function init_payment( $payment_session_url, $reference, $total, $currency ) {
		// Get Payment URL
		$params = array(
			'amount'      => $total,
			'vatAmount'   => 0,
			'currency'    => $currency,
			'callbackUrl' => add_query_arg( 'reference', $reference, WC()->api_request_url( __CLASS__ ) ),
			'reference'   => $reference,
			'culture'     => $this->culture,
			'acquire'     => array(
				"email",
				"mobilePhoneNumber",
				"shippingAddress"
			)
		);

		//$email = WC()->customer->get_billing_email();
		//$phone = WC()->customer->get_billing_phone();

		//if ( ! empty( $email ) ) {
		//	$params['payer']['email'] = $email;
		//}

		//if ( ! empty( $phone ) ) {
		//	$params['payer']['mobilePhoneNumber'] = $phone;
		//}
		$result = $this->request( 'POST', $payment_session_url, $params );

		return $result;
	}

	/**
	 * Add Button to Cart page
	 */
	public function add_px_button_to_cart() {
		$order_id = uniqid( 'cart_' );
		$currency = get_woocommerce_currency();
		$total    = WC()->cart->total;

		// Backup Cart
		$cart = array();
		foreach ( WC()->cart->cart_session_data as $key => $default ) {
			$cart[ $key ] = WC()->session->get( $key, $default );
		}
		$cart['cart'] = WC()->session->get( 'cart', NULL );
		WC()->session->set( 'payex_saved_cart', $cart );

		try {
			$payment_session_url = $this->init_payment_session();
			WC()->session->set( 'payex_payment_session', $payment_session_url );

			$result = $this->init_payment( $payment_session_url, $order_id, $total, $currency );
			WC()->session->set( 'payex_payment_id', $result['id'] );
		} catch ( Exception $e ) {
			$this->log( sprintf( 'add_px_button_to_cart: %s', $e->getMessage() ), 'error' );

			return;
		}

		wc_get_template(
			'payex-checkout/cart-button.php',
			array(
				'payment_id' => $result['id'],
				'order_ref'  => $order_id,
				'link'       => '#'
			),
			'',
			dirname( __FILE__ ) . '/../templates/'
		);
	}

	/**
	 * Add Button to Single Product page
	 */
	public function add_px_button_to_product_page() {
		if ( ! is_single() ) {
			return;
		}

		global $post;
		$product = wc_get_product( $post->ID );
		if ( ! is_object( $product ) ) {
			return;
		}


		$payment_id = WC()->session->get( 'payex_payment_id' );

		wc_get_template(
			'payex-checkout/product-button.php',
			array(
				'product_id' => $product->get_id(),
				'payment_id' => ! empty( $_POST ) && isset( $_POST['buy-payex'] ) ? $payment_id : NULL,
				'link'       => '#'
			),
			'',
			dirname( __FILE__ ) . '/../templates/'
		);

	}

	/**
	 * Action for "Buy Now"
	 */
	public function action_buy_now() {
		if ( empty( $_REQUEST['buy-payex'] ) || ! is_numeric( $_REQUEST['buy-payex'] ) ) {
			return;
		}

		$product_id = apply_filters( 'woocommerce_add_to_cart_product_id', absint( $_REQUEST['buy-payex'] ) );
		$product    = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}

		$qty = empty( $_REQUEST['quantity'] ) ? 1 : wc_stock_amount( $_REQUEST['quantity'] );

		// Add Products to Cart
		WC()->cart->empty_cart( TRUE );
		WC()->cart->add_to_cart( $product_id, $qty );
		WC()->cart->calculate_totals();

		// Backup Cart
		$cart = array();
		foreach ( WC()->cart->cart_session_data as $key => $default ) {
			$cart[ $key ] = WC()->session->get( $key, $default );
		}
		$cart['cart'] = WC()->session->get( 'cart', NULL );
		WC()->session->set( 'payex_saved_cart', $cart );

		// Init payment
		$order_id = uniqid( 'cart_' );
		$currency = get_woocommerce_currency();
		$total    = wc_get_price_including_tax( $product, array(
			'qty'   => $qty,
			'price' => $product->get_price()
		) );

		try {
			$payment_session_url = $this->init_payment_session();
			WC()->session->set( 'payex_payment_session', $payment_session_url );

			$result = $this->init_payment( $payment_session_url, $order_id, $total, $currency );
			WC()->session->set( 'payex_payment_id', $result['id'] );
		} catch ( Exception $e ) {
			$this->log( sprintf( 'action_buy_now: %s', $e->getMessage() ), 'error' );

			return;
		}
	}

	public function process_payex_checkout() {
		// Restore Cart
		$cart = WC()->session->get( 'payex_saved_cart', array() );
		foreach ( $cart as $key => $value ) {
			WC()->session->set( $key, $value );
		}
		WC()->cart->get_cart_from_session();

		// Create Order
		$data = array(
			'payment_method' => $this->id,
			//'status'        => $transaction_data['status'] === 'approved' ? 'completed' : 'failed',
			'customer_id'    => get_current_user_id(),
			'customer_note'  => 'Placed by PayEx Checkout',
			//'total'         => $transaction_data['amount'],
			//'total' => WC()->cart->total
		);

		$order_id = WC()->checkout()->create_order( $data );
		$order    = wc_get_order( $order_id );

		if ( is_wp_error( $order_id ) ) {
			throw new Exception( $order_id->get_error_message() );
		}

		do_action( 'woocommerce_checkout_order_processed', $order_id, $data, $order );

		//if ( WC()->cart->needs_payment() ) {
		//	$this->process_order_payment( $order_id, $posted_data['payment_method'] );
		//} else {
		//	$this->process_order_without_payment( $order_id );
		//}

		$result = $this->process_payment( $order_id );

		wp_redirect( $result['redirect'] );
	}
}

// Register Gateway
WC_Payex_Psp::register_gateway( 'WC_Gateway_Payex_Checkout' );
