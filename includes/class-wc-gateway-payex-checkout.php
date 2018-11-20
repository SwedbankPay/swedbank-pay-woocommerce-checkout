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
     * Terms URL
	 * @var string
	 */
	public $terms_url = '';

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
		$this->enabled           = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'no';
		$this->title             = isset( $this->settings['title'] ) ? $this->settings['title'] : '';
		$this->description       = isset( $this->settings['description'] ) ? $this->settings['description'] : '';
		$this->merchant_token    = isset( $this->settings['merchant_token'] ) ? $this->settings['merchant_token'] : $this->merchant_token;
		$this->payee_id          = isset( $this->settings['payee_id'] ) ? $this->settings['payee_id'] : $this->payee_id;
		$this->testmode          = isset( $this->settings['testmode'] ) ? $this->settings['testmode'] : $this->testmode;
		$this->debug             = isset( $this->settings['debug'] ) ? $this->settings['debug'] : $this->debug;
		$this->culture           = isset( $this->settings['culture'] ) ? $this->settings['culture'] : $this->culture;
		$this->instant_checkout  = isset( $this->settings['instant_checkout'] ) ? $this->settings['instant_checkout'] : $this->instant_checkout;
		$this->terms_url         = isset( $this->settings['terms_url'] ) ? $this->settings['terms_url'] : get_site_url();

		if ( $this->testmode === 'yes' ) {
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
		add_action( 'woocommerce_api_' . strtolower( __CLASS__ ), array(
			$this,
			'return_handler'
		) );

		// Payment confirmation
		add_action( 'the_post', array( &$this, 'payment_confirm' ) );

		// Place Order
		add_action( 'wp_ajax_payex_place_order', array( $this, 'payex_place_order' ) );
		add_action( 'wp_ajax_nopriv_payex_place_order', array( $this, 'payex_place_order' ) );

		// Checkout Page
		add_filter( 'the_title', array( $this, 'override_endpoint_title' ) );
		add_filter( 'wc_get_template', array(
			$this,
			'override_checkout'
		), 10, 5 );
		add_action( 'payex_checkout_page', array( $this, 'payex_checkout_page' ), 10, 1 );

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
			'payee_id'       => array(
				'title'       => __( 'Payee Id', 'woocommerce-gateway-payex-checkout' ),
				'type'        => 'text',
				'description' => __( 'Payee Id', 'woocommerce-gateway-payex-checkout' ),
				'default'     => $this->payee_id
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
			'instant_checkout'    => array(
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
		wp_enqueue_script( 'featherlight', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/js/featherlight/featherlight' . $suffix . '.js', array('jquery'), '1.7.13', TRUE );
		wp_enqueue_style( 'featherlight-css', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/js/featherlight/featherlight' . $suffix . '.css', array(), '1.7.13', 'all' );
		wp_enqueue_style( 'payex-checkout-css', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/css/style.css', array(), FALSE, 'all' );

		// Checkout scripts
		// @todo Add suffix
		if ( is_checkout() || isset( $_GET['pay_for_order'] ) || is_add_payment_method_page() ) {
			wp_register_script( 'wc-gateway-payex-checkout', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/js/checkout.js', array(
				'jquery',
				'wc-checkout',
				'featherlight'
			), FALSE, TRUE );

			// Localize the script with new data
			$translation_array = array(
			    'culture'                  => $this->culture,
				'nonce'                    => wp_create_nonce( 'payex_checkout' ),
				'ajax_url'                 => admin_url( 'admin-ajax.php' ),
				'action_payex_place_order' => add_query_arg( 'action', 'payex_place_order', admin_url( 'admin-ajax.php' ) ),
			);
			wp_localize_script( 'wc-gateway-payex-checkout', 'WC_Gateway_PayEx_Checkout', $translation_array );

			// Enqueued script with localized data.
			wp_enqueue_script( 'wc-gateway-payex-checkout' );

			// Add OnePage Script
			wp_enqueue_script( 'wc-payex-onepage', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/js/onepage.js', array(
				'jquery', 'wc-gateway-payex-checkout',
			), FALSE, FALSE );
		}
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
		//
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

		// Get Order UUID
		$order_uuid = mb_strimwidth( px_uuid( uniqid() ), 0, 30, '', 'UTF-8' );

		$params = [
			'paymentorder' => [
				'operation' => 'Purchase',
				'currency' => $order->get_currency(),
				'amount' => round(100 * $order->get_total()),
				'vatAmount' => 0,
				'description' => sprintf( __( 'Order #%s', 'woocommerce-gateway-payex-checkout' ), $order->get_order_number() ),
				'userAgent' => $_SERVER['HTTP_USER_AGENT'],
				'language' => $this->culture,
				'urls' => [
					'hostUrls' => [
						get_bloginfo( 'url' )
					],
					'completeUrl' => html_entity_decode( $this->get_return_url( $order ) ),
					'cancelUrl' => $order->get_cancel_order_url_raw(),
					'callbackUrl' => WC()->api_request_url( __CLASS__ ),
					'termsAndConditionsUrl' => $this->terms_url
				],
				'payeeInfo' => [
					'payeeId' => $this->payee_id,
					'payeeReference' => str_replace('-', '', $order_uuid),
					'payeeName' => 'Merchant1',
					'productCategory' => 'A123'
				],
				'metadata' => [
					'order_id' => $order_id
				],
				'items' => [
					[
						'creditCard' => [
							'no3DSecure' => FALSE
						]
					]
				]
			]
		];

		// Get Consumer Profile
		$consumer_profile = get_post_meta( $order_id, '_payex_consumer_profile', TRUE );
		if ( ! empty( $consumer_profile ) ) {
			$params['paymentorder']['payer'] = [
				'consumerProfileRef' => $consumer_profile
			];
		}

		try {
			$result = $this->request( 'POST', '/psp/paymentorders', $params );
		} catch ( Exception $e ) {
			if ( strpos( $e->getMessage(), 'is not active' ) !== false ) {
				// Reference *** is not active, unable to complete
				delete_user_meta( get_current_user_id(), '_payex_consumer_profile' );

				// Try again
				return $this->process_payment( $order_id );
			} else {
				wc_add_notice( $e->getMessage(), 'error' );
			}


		    wc_add_notice( $e->getMessage(), 'error' );

		    return FALSE;
		}

		// Save PaymentOrder ID
		update_post_meta( $order_id, '_payex_paymentorder_id', $result['paymentOrder']['id'] );

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

		// Check payments list and extract Payment ID
        $payment_order = $order->get_meta( '_payex_paymentorder_id', TRUE );
		try {
			$payments = $this->request( 'GET', $payment_order . '/payments' );
			if ( isset( $payments['payments']['paymentList'][0]['id'] ) ) {
				$payment_id = $payments['payments']['paymentList'][0]['id'];
				$order->add_meta_data('_payex_payment_id', $payment_id);
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
		$this->log( sprintf( 'Incoming Callback. Post data: %s', var_export( $raw_body, TRUE ) ) );

		// Decode raw body
		$data = @json_decode( $raw_body, TRUE );

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

			// Check Payment ID
			$order_id = $this->get_post_id_by_meta( '_payex_payment_id', $paymentorder_id );
			if ( empty( $order_id ) ) {
				// Get Order by Order Payment Id
				$order_id = $this->get_post_id_by_meta( '_payex_paymentorder_id', $paymentorder_id );
				if ( ! $order_id ) {
					throw new \Exception( sprintf( 'Error: Failed to get order Id by Payment Order Id %s', $paymentorder_id ) );
				}

				// Save Payment ID
				update_post_meta( $order_id, '_payex_payment_id', $payment_id );
			}

			// Update address
			$this->update_address( $order_id );
		} catch ( \Exception $e ) {
			$this->log( sprintf( 'IPN: %s', $e->getMessage() ) );
			return;
		}

		parent::return_handler();
	}

	/**
	 * Ajax Action
	 * @throws Exception
	 */
	public function payex_place_order() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'payex_checkout' ) ) {
			exit( 'No naughty business' );
		}

		$user_id = get_current_user_id();

		// Create Order
		$data = array(
			'payment_method' => $this->id,
			'customer_id'    => $user_id,
			'customer_note'  => 'Placed by PayEx Checkout',
		);

		$order_id = WC()->checkout()->create_order( $data );
		if ( is_wp_error( $order_id ) ) {
			wp_send_json_error( $order_id->get_error_message() );
			return;
		}

		$order = wc_get_order( $order_id );
		do_action( 'woocommerce_checkout_order_processed', $order_id, $data, $order );

		// Add consumer profile
		if ( isset( $_REQUEST['consumerProfileRef'] ) ) {
			$consumer_profile = wc_clean( $_REQUEST['consumerProfileRef'] );
			update_post_meta( $order_id, '_payex_consumer_profile', $consumer_profile );

			// Store Customer Profile
			if ( is_user_logged_in() ) {
			    $stored = get_user_meta( $user_id, '_payex_consumer_profile', TRUE );
			    if ( empty( $stored ) ) {
				    update_user_meta( $user_id, '_payex_consumer_profile', $consumer_profile );
                }
            }
		}

		$result = $this->process_payment( $order_id );
		if ( ! isset($result['result'] ) || $result['result'] !== 'success' ) {
			wp_send_json_error( __( 'Failed to process payment', 'woocommerce-gateway-payex-checkout' ) );
			return;
		}

		wp_send_json_success( array(
			'js_url' => $result['js_url'],
			'payment_id' => $result['payment_id']
		) );
	}

	/**
	 * Override Endpoint Title
	 * @param $title
	 *
	 * @return string
	 */
	public function override_endpoint_title( $title ) {
		if ( $this->enabled !== 'yes' || $this->instant_checkout !== 'yes' ) {
			return $title;
		}

		global $wp_query;
		$is_endpoint = isset( $wp_query->query_vars[ 'order-pay' ] );
		if ( $is_endpoint && ! is_admin() && is_main_query() && in_the_loop() ) {
			// New page title.
			$title = __( 'Checkout', 'woocommerce' );
		}
		return $title;
	}

	/**
	 * Override Standard Checkout template
	 * @param $located
	 * @param $template_name
	 * @param $args
	 * @param $template_path
	 * @param $default_path
	 *
	 * @return string
	 */
	public function override_checkout( $located, $template_name, $args, $template_path, $default_path ) {
		if ( $this->enabled !== 'yes' || $this->instant_checkout !== 'yes' ) {
			return $located;
		}

		if ( strpos( $located, 'checkout/form-checkout.php' ) !== false ) {
			do_action( 'payex_checkout_page', $args );

			$located = wc_locate_template(
				'checkout/payex/checkout.php',
				$template_path,
				dirname( __FILE__ ) . '/../templates/'
			);
		}

		return $located;
	}

	/**
     * Checkout Page Action
	 * @param $args
     * @return void
	 */
	public function payex_checkout_page( $args ) {
		if ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
			$consumer_profile = get_user_meta( $user_id, '_payex_consumer_profile', TRUE );
			if ( ! empty( $consumer_profile ) ) {
				return;
            }
		}

	    // Initiate consumer session
		$params = [
			'operation'           => 'initiate-consumer-session',
			'consumerCountryCode' => 'SE',
		];

		try {
			$result = $this->request( 'POST', '/psp/consumers', $params );
		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );

			return;
		}

		$js_url = self::get_operation( $result['operations'], 'view-consumer-identification' );

		?>

		<script src="<?php echo $js_url; ?>"></script>
        <?php
	}

	/**
     * Update Address
	 * @param $order_id
	 *
	 * @throws Exception
	 */
	public function update_address( $order_id ) {
		$paymentorder_id = get_post_meta( $order_id, '_payex_paymentorder_id', TRUE );
		if ( ! empty( $paymentorder_id ) ) {
			$result = $this->request( 'GET', $paymentorder_id . '/payers' );

			if (!isset($result['payer'])) {
			    return;
            }

			// Parse name field
			$parser = new \FullNameParser();
			$name = $parser->parse_name( $result['payer']['shippingAddress']['addressee'] );
			$co = ! empty( $result['payer']['shippingAddress']['coAddress'] ) ? 'c/o ' . $result['payer']['shippingAddress']['coAddress'] : '';

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
}

// Register Gateway
WC_Payex_Psp::register_gateway( 'WC_Gateway_Payex_Checkout' );
