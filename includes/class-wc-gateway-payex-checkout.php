<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly


class WC_Gateway_Payex_Checkout extends WC_Gateway_Payex_Cc
	implements WC_Payment_Gateway_Payex_Interface {

	/**
	 * Merchant Token
	 * @var string
	 */
	public $merchant_token = '';

	/**
	 * Payee Id
	 * @var string
	 */
	public $payee_id = '';

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
	 * Use Instant Checkout
	 * @var string
	 */
	public $instant_checkout = 'no';

	/**
	 * Backend Api Endpoint
	 * @var string
	 */
	public $backend_api_endpoint = 'https://api.payex.com';

	/**
	 * Init
	 */
	public function __construct() {
		$this->transactions = WC_Payex_Transactions::instance();

		$this->id           = 'payex_checkout';
		$this->has_fields   = true;
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
		$this->enabled          = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'no';
		$this->title            = isset( $this->settings['title'] ) ? $this->settings['title'] : '';
		$this->description      = isset( $this->settings['description'] ) ? $this->settings['description'] : '';
		$this->merchant_token   = isset( $this->settings['merchant_token'] ) ? $this->settings['merchant_token'] : $this->merchant_token;
		$this->payee_id         = isset( $this->settings['payee_id'] ) ? $this->settings['payee_id'] : $this->payee_id;
		$this->testmode         = isset( $this->settings['testmode'] ) ? $this->settings['testmode'] : $this->testmode;
		$this->debug            = isset( $this->settings['debug'] ) ? $this->settings['debug'] : $this->debug;
		$this->culture          = isset( $this->settings['culture'] ) ? $this->settings['culture'] : $this->culture;
		$this->instant_checkout = isset( $this->settings['instant_checkout'] ) ? $this->settings['instant_checkout'] : $this->instant_checkout;
		$this->terms_url        = isset( $this->settings['terms_url'] ) ? $this->settings['terms_url'] : get_site_url();

		// TermsOfServiceUrl contains unsupported scheme value http in Only https supported.
		if ( ! filter_var($this->terms_url, FILTER_VALIDATE_URL) ) {
			$this->terms_url = '';
		} elseif ( 'https' !== parse_url( $this->terms_url, PHP_URL_SCHEME ) ) {
			$this->terms_url = '';
		}

		if ( $this->testmode === 'yes' ) {
			$this->backend_api_endpoint = 'https://api.externalintegration.payex.com';
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
		add_action( 'woocommerce_api_' . strtolower( __CLASS__ ), array(
			$this,
			'return_handler'
		) );

		// Payment confirmation
		add_action( 'the_post', array( &$this, 'payment_confirm' ) );

		// Add SSN Checkout Field
		add_action( 'woocommerce_before_checkout_billing_form', array( $this, 'before_checkout_billing_form' ) );
		add_action( 'woocommerce_checkout_order_review', array( $this, 'woocommerce_checkout_payment' ), 20 );
		if ( $this->instant_checkout === 'yes' ) {
			//remove_action( 'woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20 );
		}

		add_action( 'wp_ajax_payex_checkout_get_address', array( $this, 'ajax_payex_checkout_get_address' ) );
		add_action( 'wp_ajax_nopriv_payex_checkout_get_address', array( $this, 'ajax_payex_checkout_get_address' ) );

		add_action( 'wp_ajax_payex_checkout_customer_profile', array( $this, 'ajax_payex_checkout_customer_profile' ) );
		add_action( 'wp_ajax_nopriv_payex_checkout_customer_profile', array(
			$this,
			'ajax_payex_checkout_customer_profile'
		) );

		add_action( 'wp_ajax_payex_place_order', array( $this, 'ajax_payex_place_order' ) );
		add_action( 'wp_ajax_nopriv_payex_place_order', array( $this, 'ajax_payex_place_order' ) );

		add_action( 'wp_ajax_payex_update_order', array( $this, 'ajax_payex_update_order' ) );
		add_action( 'wp_ajax_nopriv_payex_update_order', array( $this, 'ajax_payex_update_order' ) );

		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'filter_gateways' ), 1 );

		if ( $this->instant_checkout === 'yes' ) {
			add_filter( 'woocommerce_checkout_fields', array( $this, 'lock_checkout_fields' ), 10, 1 );
		}
	}

	/**
	 * Initialise Settings Form Fields
	 * @return string|void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'          => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-gateway-payex-checkout' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable plugin', 'woocommerce-gateway-payex-checkout' ),
				'default' => 'no'
			),
			'title'            => array(
				'title'       => __( 'Title', 'woocommerce-gateway-payex-checkout' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-payex-checkout' ),
				'default'     => __( 'PayEx Checkout', 'woocommerce-gateway-payex-checkout' )
			),
			'description'      => array(
				'title'       => __( 'Description', 'woocommerce-gateway-payex-checkout' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-payex-checkout' ),
				'default'     => __( 'PayEx Checkout', 'woocommerce-gateway-payex-checkout' ),
			),
			'merchant_token'   => array(
				'title'       => __( 'Merchant Token', 'woocommerce-gateway-payex-checkout' ),
				'type'        => 'text',
				'description' => __( 'Merchant Token', 'woocommerce-gateway-payex-checkout' ),
				'default'     => $this->merchant_token
			),
			'payee_id'         => array(
				'title'       => __( 'Payee Id', 'woocommerce-gateway-payex-checkout' ),
				'type'        => 'text',
				'description' => __( 'Payee Id', 'woocommerce-gateway-payex-checkout' ),
				'default'     => $this->payee_id
			),
			'testmode'         => array(
				'title'   => __( 'Test Mode', 'woocommerce-gateway-payex-checkout' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable PayEx Test Mode', 'woocommerce-gateway-payex-checkout' ),
				'default' => $this->testmode
			),
			'debug'            => array(
				'title'   => __( 'Debug', 'woocommerce-gateway-payex-checkout' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable logging', 'woocommerce-gateway-payex-checkout' ),
				'default' => $this->debug
			),
			'culture'          => array(
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
			'instant_checkout' => array(
				'title'   => __( 'Use PayEx Checkout instead of WooCommerce Checkout', 'woocommerce-gateway-payex-checkout' ),
				'type'    => 'checkbox',
				'label'   => __( 'Use PayEx Checkout instead of WooCommerce Checkout', 'woocommerce-gateway-payex-checkout' ),
				'default' => $this->instant_checkout
			),
			'terms_url'        => array(
				'title'       => __( 'Terms & Conditions Url', 'woocommerce-gateway-payex-checkout' ),
				'type'        => 'text',
				'description' => __( 'Terms & Conditions Url', 'woocommerce-gateway-payex-checkout' ),
				'default'     => get_site_url()
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

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		wp_enqueue_script( 'featherlight', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/js/featherlight/featherlight' . $suffix . '.js', array( 'jquery' ), '1.7.13', true );
		wp_enqueue_style( 'featherlight-css', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/js/featherlight/featherlight' . $suffix . '.css', array(), '1.7.13', 'all' );
		wp_enqueue_style( 'payex-checkout-css', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/css/style.css', array(), false, 'all' );

		if ( $this->instant_checkout === 'yes' ) {
			wp_enqueue_style( 'payex-checkout-instant', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/css/instant.css', array(), FALSE, 'all' );
		}

		// Checkout scripts
		// @todo Add suffix
		if ( is_checkout() || isset( $_GET['pay_for_order'] ) || is_add_payment_method_page() ) {
			wp_register_script( 'wc-gateway-payex-checkout', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/js/checkout.js', array(
				'jquery',
				'wc-checkout',
				'featherlight'
			), false, true );

			// Localize the script with new data
			$translation_array = array(
				'culture'          => $this->culture,
				'instant_checkout' => ( $this->instant_checkout === 'yes' ),
				'nonce'            => wp_create_nonce( 'payex_checkout' ),
				'ajax_url'         => admin_url( 'admin-ajax.php' ),
			);
			wp_localize_script( 'wc-gateway-payex-checkout', 'WC_Gateway_PayEx_Checkout', $translation_array );

			// Enqueued script with localized data.
			wp_enqueue_script( 'wc-gateway-payex-checkout' );
		}
	}


	/**
	 * If There are no payment fields show the description if set.
	 */
	public function payment_fields() {

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
		WC()->session->__unset( 'payex_paymentorder_id' );
	}

	/**
	 * Process Payment
	 *
	 * @param int $order_id
	 *
	 * @return array|false
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( isset( $_POST['is_update'] ) ) {
			$params = [
				'paymentorder' => [
					'operation' => 'UpdateOrder',
					'amount'    => (int) round( $order->get_total() * 100 ),
					'vatAmount' => 0,
				]
			];

			// Get Payment Order ID
			$payment_id = get_post_meta( $order_id, '_payex_paymentorder_id', true );
			if ( empty( $payment_id ) ) {
				$payment_id = WC()->session->get( 'payex_paymentorder_id' );
			}

			if ( ! empty( $payment_id ) ) {
				$result = $this->request( 'PATCH', $payment_id, $params );

				// Get JS URl
				$js_url = self::get_operation( $result['operations'], 'view-paymentorder' );

				return array(
					'result'            => 'success',
					'redirect'          => '#!payex-checkout',
					'is_payex_checkout' => true,
					'js_url'            => $js_url,
					'payment_id'        => $result['paymentOrder']['id'],
				);
			}

			unset( $_POST['is_update'] );
		}

		// Get Order UUID
		$order_uuid = mb_strimwidth( px_uuid( uniqid() ), 0, 30, '', 'UTF-8' );

		// Check terms_url value
		if ( parse_url( $this->terms_url, PHP_URL_SCHEME ) !== 'https' ) {
			$this->terms_url = '';
        }

		$params = [
			'paymentorder' => [
				'operation'   => 'Purchase',
				'currency'    => $order->get_currency(),
				'amount'      => round( 100 * $order->get_total() ),
				'vatAmount'   => 0,
				'description' => sprintf( __( 'Order #%s', 'woocommerce-gateway-payex-checkout' ), $order->get_id() ),
				'userAgent'   => $_SERVER['HTTP_USER_AGENT'],
				'language'    => $this->culture,
				'urls'        => [
					'hostUrls'              => [
						get_bloginfo( 'url' )
					],
					'completeUrl'           => html_entity_decode( $this->get_return_url( $order ) ),
					'cancelUrl'             => $order->get_cancel_order_url_raw(),
					'callbackUrl'           => WC()->api_request_url( __CLASS__ ),
					'termsOfServiceUrl'     => $this->terms_url
				],
				'payeeInfo'   => [
					'payeeId'         => $this->payee_id,
					'payeeReference'  => str_replace( '-', '', $order_uuid ),
					'payeeName'       => get_bloginfo( 'name' ),
					'productCategory' => 'A123'
				],
				'metadata'    => [
					'order_id' => $order_id
				],
				'items'       => [
					[
						'creditCard' => [
							'no3DSecure' => false
						]
					]
				]
			]
		];

		// Get Consumer Profile
        $consumer_profile = isset( $_POST['payex_customer_reference'] ) ? wc_clean( $_POST['payex_customer_reference'] ) : null;
        if ( empty( $consumer_profile ) ) {
            $consumer_profile = get_user_meta( $order->get_user_id(), '_payex_consumer_profile', true );
        }

        if ( empty( $consumer_profile ) ) {
	        $consumer_profile = WC()->session->get( 'payex_consumer_profile' );
        }

		if ( ! empty( $consumer_profile ) ) {
			$params['paymentorder']['payer'] = [
				'consumerProfileRef' => $consumer_profile
			];
		}

		try {
			$result = $this->request( 'POST', '/psp/paymentorders', $params );
		} catch ( Exception $e ) {
			if ( ( strpos( $e->getMessage(), 'is not active' ) !== false ) ||
                 ( strpos( $e->getMessage(), 'Unable to verify consumerProfileRef' ) !== false ) )
			{
				// Reference *** is not active, unable to complete
				delete_user_meta( $order->get_user_id(), '_payex_consumer_profile' );

				// Try again
				return $this->process_payment( $order_id );
			} else {
				wc_add_notice( $e->getMessage(), 'error' );
			}


			wc_add_notice( $e->getMessage(), 'error' );

			return false;
		}

		// Save PaymentOrder ID
		update_post_meta( $order_id, '_payex_paymentorder_id', $result['paymentOrder']['id'] );
		WC()->session->set( 'payex_paymentorder_id', $result['paymentOrder']['id'] );

		// Get JS URl
		$js_url = self::get_operation( $result['operations'], 'view-paymentorder' );

		return array(
			'result'            => 'success',
			'redirect'          => '#!payex-checkout',
			'is_payex_checkout' => true,
			'js_url'            => $js_url,
			'payment_id'        => $result['paymentOrder']['id'],
		);
	}

	/**
	 * Payment confirm action
	 */
	public function payment_confirm() {
		if ( empty( $_GET['key'] ) ) {
			return;
		}

		// Validate Payment Method
		$order_id = wc_get_order_id_by_order_key( $_GET['key'] );
		if ( ! $order_id ) {
			return;
		}

		if ( ! ( $order = wc_get_order( $order_id ) ) ) {
			return;
		}

		if ( px_obj_prop( $order, 'payment_method' ) !== $this->id ) {
			return;
		}

		WC()->session->__unset( 'payex_paymentorder_id' );

		// Check payments list and extract Payment ID
		$payment_order = $order->get_meta( '_payex_paymentorder_id', true );
		try {
			$payments = $this->request( 'GET', $payment_order . '/payments' );
			if ( isset( $payments['payments']['paymentList'][0]['id'] ) ) {
				$payment_id = $payments['payments']['paymentList'][0]['id'];
				$order->add_meta_data( '_payex_payment_id', $payment_id );
				$order->save_meta_data();
			}
		} catch ( \Exception $e ) {
			// Ignore errors
		}

		// Update address
		try {
			$this->update_address( $order_id );
		} catch ( \Exception $e ) {
			// Ignore errors
		}

		parent::payment_confirm();
	}

	/**
	 * IPN Callback
	 * @throws \Exception
	 * @return void
	 */
	public function return_handler() {
		$raw_body = file_get_contents( 'php://input' );

		$this->log( sprintf( 'IPN: Initialized %s from %s', $_SERVER['REQUEST_URI'], $_SERVER['REMOTE_ADDR'] ) );
		$this->log( sprintf( 'Incoming Callback. Post data: %s', var_export( $raw_body, true ) ) );

		// Decode raw body
		$data = @json_decode( $raw_body, true );

		try {
			if ( ! isset( $data['paymentOrder'] ) || ! isset( $data['paymentOrder']['id'] ) ) {
				throw new \Exception( 'Error: Invalid paymentOrder value' );
			}

			if ( ! isset( $data['payment'] ) || ! isset( $data['payment']['id'] ) ) {
				throw new \Exception( 'Error: Invalid payment value' );
			}

			if ( ! isset( $data['transaction'] ) || ! isset( $data['transaction']['number'] ) ) {
				throw new \Exception( 'Error: Invalid transaction number' );
			}

			$paymentorder_id = $data['paymentOrder']['id'];
			$payment_id      = $data['payment']['id'];

			// Get Order by Order Payment Id
			$order_id = $this->get_post_id_by_meta( '_payex_paymentorder_id', $paymentorder_id );
			if ( empty( $order_id ) ) {
			    // Extract Order ID from description
				$result = $this->request('GET', $paymentorder_id );
				$description = $result['paymentOrder']['description'];

				$matches = [];
				preg_match( '/#(\d+)/iu', $description, $matches );
				if ( ! empty( $matches[1] ) ) {
					$order_id = $matches[1];
					update_post_meta( $order_id, '_payex_paymentorder_id', $result['paymentOrder']['id'] );
				}

				if ( empty( $order_id ) ) {
					throw new \Exception( sprintf( 'Error: Failed to get order Id by Payment Order Id %s', $paymentorder_id ) );
				}
			}

			// Save Payment ID
			update_post_meta( $order_id, '_payex_payment_id', $payment_id );

			// Update address
			$this->update_address( $order_id );
		} catch ( \Exception $e ) {
			$this->log( sprintf( 'IPN: %s', $e->getMessage() ) );

			return;
		}

		parent::return_handler();
	}

	/**
	 * Capture
	 *
	 * @param WC_Order|int $order
	 * @param bool $amount
	 *
	 * @throws \Exception
	 * @return void
	 */
	public function capture_payment( $order, $amount = false ) {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		$payment_id = get_post_meta( $order->get_id(), '_payex_payment_id', true );
		if ( empty( $payment_id ) ) {
			throw new Exception( 'Unable to get payment ID' );
		}

		// Use Invoice capture
		$result = $this->request( 'GET', $payment_id );
		if ( $result['payment']['instrument'] === 'Invoice' ) {
			$gateways = WC()->payment_gateways()->payment_gateways();
			if ( ! isset( $gateways['payex_psp_invoice'] ) ) {
				throw new Exception( 'Unable to get Invoice gateway' );
			}

			/** @var WC_Gateway_Payex_Invoice $gateway */
			$gateway                 = $gateways['payex_psp_invoice'];
			$gateway->merchant_token = $this->merchant_token;
			$gateway->payee_id       = $this->payee_id;
			$gateway->testmode       = $this->testmode;

			$gateway->capture_payment( $order, $amount );

			return;
		}

		parent::capture_payment( $order, $amount );
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

		$payment_id = get_post_meta( $order->get_id(), '_payex_payment_id', TRUE );
		if ( empty( $payment_id ) ) {
			throw new Exception('Unable to get payment ID');
		}

		// Use Invoice cancel
		$result = $this->request( 'GET', $payment_id );
		if ($result['payment']['instrument'] === 'Invoice') {
			$gateways = WC()->payment_gateways()->payment_gateways();
			if (!isset($gateways[ 'payex_psp_invoice' ])) {
				throw new Exception('Unable to get Invoice gateway');
			}

			/** @var WC_Gateway_Payex_Invoice $gateway */
			$gateway = $gateways[ 'payex_psp_invoice' ];
			$gateway->merchant_token = $this->merchant_token;
			$gateway->payee_id = $this->payee_id;
			$gateway->testmode = $this->testmode;

			$gateway->cancel_payment( $order );
			return;
		}

		parent::cancel_payment( $order );
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

		$payment_id = get_post_meta( $order->get_id(), '_payex_payment_id', TRUE );
		if ( empty( $payment_id ) ) {
			throw new Exception('Unable to get payment ID');
		}

		// Use Invoice cancel
		$result = $this->request( 'GET', $payment_id );
		if ($result['payment']['instrument'] === 'Invoice') {
			$gateways = WC()->payment_gateways()->payment_gateways();
			if (!isset($gateways[ 'payex_psp_invoice' ])) {
				throw new Exception('Unable to get Invoice gateway');
			}

			/** @var WC_Gateway_Payex_Invoice $gateway */
			$gateway = $gateways[ 'payex_psp_invoice' ];
			$gateway->merchant_token = $this->merchant_token;
			$gateway->payee_id = $this->payee_id;
			$gateway->testmode = $this->testmode;

			$gateway->refund_payment( $order, $amount, $reason );
			return;
		}

		parent::refund_payment( $order, $amount, $reason );
	}

	/**
	 * Update Address
	 *
	 * @param $order_id
	 *
	 * @throws Exception
	 */
	public function update_address( $order_id ) {
		$paymentorder_id = get_post_meta( $order_id, '_payex_paymentorder_id', true );
		if ( ! empty( $paymentorder_id ) ) {
			$result = $this->request( 'GET', $paymentorder_id . '/payers' );

			if ( ! isset( $result['payer'] ) ) {
				return;
			}

			// Parse name field
			$parser = new \FullNameParser();
			$name   = $parser->parse_name( $result['payer']['shippingAddress']['addressee'] );
			$co     = ! empty( $result['payer']['shippingAddress']['coAddress'] ) ? 'c/o ' . $result['payer']['shippingAddress']['coAddress'] : '';

			$address = array(
				'first_name' => $name['fname'],
				'last_name'  => $name['lname'],
				'company'    => '',
				'email'      => $result['payer']['email'],
				'phone'      => $result['payer']['msisdn'],
				'address_1'  => $result['payer']['shippingAddress']['streetAddress'],
				'address_2'  => $co,
				'city'       => $result['payer']['shippingAddress']['city'],
				'state'      => '',
				'postcode'   => $result['payer']['shippingAddress']['zipCode'],
				'country'    => $result['payer']['shippingAddress']['countryCode']
			);

			$order = wc_get_order( $order_id );
			$order->set_address( $address, 'billing' );
			if ( $order->needs_shipping_address() ) {
				$order->set_address( $address, 'shipping' );
			}
		}
	}

	/**
	 * Hook before_checkout_billing_form
	 *
	 * @param $checkout
	 */
	public function before_checkout_billing_form( $checkout ) {
		if ( $this->instant_checkout !== 'yes' ) {
			return;
		}

		if ( is_user_logged_in() ) {
			$consumer_profile = get_user_meta( get_current_user_id(), '_payex_consumer_profile', true );
			$consumer_data = get_user_meta( get_current_user_id(), '_payex_consumer_address_billing', true );
			if (empty($consumer_data)) {
			    // Deprecated
				$consumer_data = get_user_meta( get_current_user_id(), '_payex_consumer_address', true );
            }
		} else {
			$consumer_profile = WC()->session->get( 'payex_consumer_profile' );
			$consumer_data = WC()->session->get( 'payex_checkin' );
		}

		if ( empty( $consumer_profile ) ) {
			// Initiate consumer session
			$params = [
				'operation'           => 'initiate-consumer-session',
				'consumerCountryCode' => 'SE',
			];

			try {
				$result = $this->request( 'POST', '/psp/consumers', $params );
			} catch ( Exception $e ) {
				return;
			}

			$js_url = self::get_operation( $result['operations'], 'view-consumer-identification' );

			?>
            <script id="payex-hostedview-script" src="<?php echo $js_url; ?>"></script>
            <h2><?php _e( 'Your information', 'woocommerce-gateway-payex-checkout' ); ?></h2>
            <div id="payex-checkin">

            </div>
			<?php
		} else {
			?>
            <h2><?php _e( 'Your information', 'woocommerce-gateway-payex-checkout' ); ?></h2>
            <div id="payex-checkin">
	            <strong>
		            <?php _e( 'You\'re loggedin as payex customer.', 'woocommerce-gateway-payex-checkout' ); ?>
                </strong>
	            <?php if (isset($consumer_data['first_name'])): ?>
                    <p>
	                    <?php echo $consumer_data['first_name'] . ' ' . $consumer_data['last_name']; ?><br/>
	                    <?php echo $consumer_data['postcode'] . ' ' . $consumer_data['city']; ?><br/>
	                    <?php echo $consumer_data['email'] . ', ' . $consumer_data['phone']; ?><br/>
                    </p>
	            <?php endif; ?>
            </div>

            <div id="payex-consumer-profile" data-reference="<?php echo $consumer_profile; ?>"></div>
			<?php
		}
	}

	public function woocommerce_checkout_payment() {
		?>
        <!-- <button type="submit" class="button alt" name="woocommerce_checkout_place_order" id="place_order" value="Place order" data-value="Place order">Place order</button> -->
        <div id="payex-checkout"></div>
		<?php
	}

	public function ajax_payex_checkout_get_address() {
		check_ajax_referer( 'payex_checkout', 'nonce' );

		$type = isset( $_POST['type'] ) ? wc_clean( $_POST['type'] ) : '';
		$url = isset( $_POST['url'] ) ? wc_clean( $_POST['url'] ) : '';

		// https://developer.payex.com/xwiki/wiki/developer/view/Main/ecommerce/technical-reference/consumers-resource/#HRetrieveConsumerShippingDetails
		try {
			// Check url
			if (mb_substr($url, 0, 1, 'UTF-8') === '/') {
				$url = $this->backend_api_endpoint . $url;
			}

			$host = parse_url( $url, PHP_URL_HOST );
			if ( ! in_array( $host, array( 'api.payex.com', 'api.externalintegration.payex.com' ) ) ) {
				throw new Exception( 'Access denied' );
			}

			$result = $this->request( 'GET', $url );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
			exit();
		}

		$address = $type === 'billing' ? $result['billingAddress'] : $result['shippingAddress'];

		// Parse name field
		$parser = new \FullNameParser();
		$name   = $parser->parse_name( $address['addressee'] );

		$output = array(
			'first_name' => $name['fname'],
			'last_name'  => $name['lname'],
			'country'    => $address['countryCode'],
			'postcode'   => $address['zipCode'],
			'address_1'  => $address['streetAddress'],
			'address_2'  => '',
			'city'       => $address['city'],
			'state'      => '',
			'phone'      => $result['msisdn'],
			'email'      => $result['email'],
		);

		if ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
			update_user_meta( $user_id, '_payex_consumer_address_' . $type, $output );
        }

		WC()->session->set( 'payex_checkin', $output );
		wp_send_json_success( $output );
	}

	public function ajax_payex_checkout_customer_profile() {
		check_ajax_referer( 'payex_checkout', 'nonce' );

		$customer_reference = isset( $_POST['consumerProfileRef'] ) ? wc_clean( $_POST['consumerProfileRef'] ) : '';

		if ( empty( $customer_reference ) ) {
			wp_send_json_error( array( 'message' => 'Customer reference required' ) );
			exit();
		}

		// Store Customer Profile
		if ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
			$stored  = get_user_meta( $user_id, '_payex_consumer_profile', true );
			if ( empty( $stored ) ) {
				update_user_meta( $user_id, '_payex_consumer_profile', $customer_reference );
			}
		} else {
			WC()->session->set( 'payex_consumer_profile', $customer_reference );
		}

		wp_send_json_success();
	}

	/**
	 * Ajax Action
	 * @throws Exception
	 */
	public function ajax_payex_place_order() {
		check_ajax_referer( 'payex_checkout', 'nonce' );

		$data = array();
		parse_str( $_POST['data'], $data );
		$_POST = $data;
		unset( $_POST['terms-field'], $_POST['terms'] );

		$_POST['payment_method'] = 'payex_checkout';

		$_REQUEST['woocommerce-process-checkout-nonce'] = wp_create_nonce( 'woocommerce-process_checkout' );
		$_POST['_wpnonce']                              = wp_create_nonce( 'woocommerce-process_checkout' );

		WC()->checkout()->process_checkout();
	}

	public function ajax_payex_update_order() {
		check_ajax_referer( 'payex_checkout', 'nonce' );

		$data = array();
		parse_str( $_POST['data'], $data );
		$_POST = $data;
		unset( $_POST['terms-field'], $_POST['terms'] );

		$_POST['payment_method'] = 'payex_checkout';
		$_POST['is_update']      = '1';

		$_REQUEST['woocommerce-process-checkout-nonce'] = wp_create_nonce( 'woocommerce-process_checkout' );
		$_POST['_wpnonce']                              = wp_create_nonce( 'woocommerce-process_checkout' );

		$order_id = absint( WC()->session->get( 'order_awaiting_payment' ) );
		if ( $order_id > 0 ) {
		    $order = wc_get_order( $order_id );
		    if ( $order->get_payment_method() === $this->id ) {
		        // Mark order failed instead of cancelled
		        if ( $order->has_status('cancelled') ) {
		            $order->update_status( 'failed' );
                }
            }
        }

		WC()->checkout()->process_checkout();
	}

	/**
	 * Unset all payment methods except PayEx Checkout
	 *
	 * @param $gateways
	 *
	 * @return mixed
	 */
	public function filter_gateways( $gateways ) {
		if ( is_admin() ) {
			return $gateways;
		}

		if ( $this->instant_checkout !== 'yes' ) {
			return $gateways;
		}

		foreach ( $gateways as $gateway ) {
			if ( $gateway->id !== $this->id ) {
				unset( $gateways[ $gateway->id ] );
			}
		}

		return $gateways;
	}

	/**
	 * Lock checkout fields
	 *
	 * @param $fieldset
	 * @return array
	 */
	public function lock_checkout_fields( $fieldset ) {
		if ( $this->instant_checkout === 'yes' ) {
			if ( is_user_logged_in() ) {
				$consumer_profile = get_user_meta( get_current_user_id(), '_payex_consumer_profile', true );
			} else {
				$consumer_profile = WC()->session->get( 'payex_consumer_profile' );
			}

			// @todo Fill form with these data
			if ( empty ( $consumer_profile ) ) {
				foreach ( $fieldset as $section => &$fields ) {
					foreach ( $fields as $key => &$field ) {
						$field['custom_attributes']['readonly'] = 'readonly';
						$field['class'][] = 'payex-locked';
					}
				}
            }
		}

	    return $fieldset;
	}
}

// Register Gateway
WC_Payex_Psp::register_gateway( 'WC_Gateway_Payex_Checkout' );
