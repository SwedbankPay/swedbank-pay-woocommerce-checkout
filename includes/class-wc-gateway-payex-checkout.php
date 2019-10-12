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
	 * Subsite
	 * @var string
	 */
	public $subsite = '';

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
	 * Enable Checkin
	 * @var string
	 */
	public $checkin = 'yes';

	/**
	 * Backend Api Endpoint
	 * @var string
	 */
	public $backend_api_endpoint = 'https://api.payex.com';

	/**
	 * Reject Credit Cards
	 * @var string
	 */
	public $reject_credit_cards = 'no';

	/**
	 * Reject Debit Cards
	 * @var string
	 */
	public $reject_debit_cards = 'no';

	/**
	 * Reject Consumer Cards
	 * @var string
	 */
	public $reject_consumer_cards = 'no';

	/**
	 * Reject Corporate Cards
	 * @var string
	 */
	public $reject_corporate_cards = 'no';

	/**
	 * Custom styles
	 * @var string
	 */
	public $custom_styles = 'no';

	/**
	 * Styles of Checkin
	 * @var string
	 */
	public $checkInStyle = '';

	/**
	 * Styles of PaymentMenu
	 * @var string
	 */
	public $paymentMenuStyle = '';

	/**
	 * Init
	 */
	public function __construct() {
		$this->transactions = WC_Payex_Transactions::instance();

		$this->id           = 'payex_checkout';
		$this->has_fields   = true;
		$this->method_title = __( 'PayEx Checkout', 'woocommerce-gateway-payex-checkout' );
		//$this->icon         = apply_filters( 'woocommerce_payex_checkout_icon', plugins_url( '/assets/images/payex.gif', dirname( __FILE__ ) ) );
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
		$this->subsite          = isset( $this->settings['subsite'] ) ? $this->settings['subsite'] : $this->subsite;
		$this->testmode         = isset( $this->settings['testmode'] ) ? $this->settings['testmode'] : $this->testmode;
		$this->debug            = isset( $this->settings['debug'] ) ? $this->settings['debug'] : $this->debug;
		$this->culture          = isset( $this->settings['culture'] ) ? $this->settings['culture'] : $this->culture;
		$this->instant_checkout = isset( $this->settings['instant_checkout'] ) ? $this->settings['instant_checkout'] : $this->instant_checkout;
		$this->checkin          = isset( $this->settings['checkin'] ) ? $this->settings['checkin'] : $this->checkin;
		$this->terms_url        = isset( $this->settings['terms_url'] ) ? $this->settings['terms_url'] : get_site_url();

		// Reject Cards
		$this->reject_credit_cards    = isset( $this->settings['reject_credit_cards'] ) ? $this->settings['reject_credit_cards'] : $this->reject_credit_cards;
		$this->reject_debit_cards     = isset( $this->settings['reject_debit_cards'] ) ? $this->settings['reject_debit_cards'] : $this->reject_debit_cards;
		$this->reject_consumer_cards  = isset( $this->settings['reject_consumer_cards'] ) ? $this->settings['reject_consumer_cards'] : $this->reject_consumer_cards;
		$this->reject_corporate_cards = isset( $this->settings['reject_corporate_cards'] ) ? $this->settings['reject_corporate_cards'] : $this->reject_corporate_cards;

		// Styles
		$this->custom_styles    = isset( $this->settings['custom_styles'] ) ? $this->settings['custom_styles'] : $this->custom_styles;
		$this->checkInStyle     = isset( $this->settings['checkInStyle'] ) ? $this->settings['checkInStyle'] : $this->checkInStyle;
		$this->paymentMenuStyle = isset( $this->settings['paymentMenuStyle'] ) ? $this->settings['paymentMenuStyle'] : $this->paymentMenuStyle;

		// TermsOfServiceUrl contains unsupported scheme value http in Only https supported.
		if ( ! filter_var( $this->terms_url, FILTER_VALIDATE_URL ) ) {
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
		add_action( 'woocommerce_checkout_init', array( $this, 'checkout_init' ), 10, 1 );
		add_action( 'woocommerce_checkout_billing', array( $this, 'checkout_form_billing' ) );
		add_action( 'woocommerce_before_checkout_billing_form', array( $this, 'before_checkout_billing_form' ) );
		add_action( 'woocommerce_checkout_order_review', array( $this, 'woocommerce_checkout_payment' ), 20 );
		add_filter( 'wc_get_template', array( $this, 'override_template' ), 5, 20 );

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

		add_action( 'wp_ajax_payex_checkout_log_error', array( $this, 'ajax_payex_checkout_log_error' ) );
		add_action( 'wp_ajax_nopriv_payex_checkout_log_error', array( $this, 'ajax_payex_checkout_log_error' ) );

		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'filter_gateways' ), 1 );

		if ( $this->instant_checkout === 'yes' ) {
			//add_filter( 'woocommerce_checkout_fields', array( $this, 'lock_checkout_fields' ), 10, 1 );
			add_action( 'woocommerce_before_checkout_form_cart_notices', array( $this, 'init_order' ) );
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
			'subsite'         => array(
				'title'       => __( 'Subsite', 'woocommerce-gateway-payex-checkout' ),
				'type'        => 'text',
				'description' => __( 'Subsite', 'woocommerce-gateway-payex-checkout' ),
				'default'     => $this->subsite
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
			'checkin' => array(
				'title'   => __( 'Enable Checkin on PayEx Checkout', 'woocommerce-gateway-payex-checkout' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Checkin on PayEx Checkout', 'woocommerce-gateway-payex-checkout' ),
				'default' => $this->checkin
			),
			'terms_url'        => array(
				'title'       => __( 'Terms & Conditions Url', 'woocommerce-gateway-payex-checkout' ),
				'type'        => 'text',
				'description' => __( 'Terms & Conditions Url', 'woocommerce-gateway-payex-checkout' ),
				'default'     => get_site_url()
			),
			'reject_credit_cards' => array(
				'title'   => __( 'Reject Credit Cards', 'payex-woocommerce-payments' ),
				'type'    => 'checkbox',
				'label'   => __( 'Reject Credit Cards', 'payex-woocommerce-payments' ),
				'default' => $this->reject_credit_cards
			),
			'reject_debit_cards' => array(
				'title'   => __( 'Reject Debit Cards', 'payex-woocommerce-payments' ),
				'type'    => 'checkbox',
				'label'   => __( 'Reject Debit Cards', 'payex-woocommerce-payments' ),
				'default' => $this->reject_debit_cards
			),
			'reject_consumer_cards' => array(
				'title'   => __( 'Reject Consumer Cards', 'payex-woocommerce-payments' ),
				'type'    => 'checkbox',
				'label'   => __( 'Reject Consumer Cards', 'payex-woocommerce-payments' ),
				'default' => $this->reject_consumer_cards
			),
			'reject_corporate_cards' => array(
				'title'   => __( 'Reject Corporate Cards', 'payex-woocommerce-payments' ),
				'type'    => 'checkbox',
				'label'   => __( 'Reject Corporate Cards', 'payex-woocommerce-payments' ),
				'default' => $this->reject_corporate_cards
			),
			'custom_styles' => array(
				'title'   => __( 'Enable Custom Styles', 'payex-woocommerce-payments' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Custom Styles', 'payex-woocommerce-payments' ),
				'default' => $this->custom_styles
			),
			'checkInStyle' => array(
				'title'   => __( 'Style of CheckIn', 'payex-woocommerce-payments' ),
				'type'    => 'textarea',
				'label'   => __( 'Style of CheckIn', 'payex-woocommerce-payments' ),
				'default' => file_get_contents( __DIR__ . '/../assets/json/style.json' ),
				'css' => 'height: 270px;'
			),
			'paymentMenuStyle' => array(
				'title'   => __( 'Style of PaymentMenu', 'payex-woocommerce-payments' ),
				'type'    => 'textarea',
				'label'   => __( 'Style of PaymentMenu', 'payex-woocommerce-payments' ),
				'default' => file_get_contents( __DIR__ . '/../assets/json/style.json' ),
				'css' => 'height: 270px;'
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

		wp_enqueue_style( 'payex-checkout-css', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/css/style' . $suffix . '.css', array(), false, 'all' );

		if ( $this->instant_checkout === 'yes' ) {
			wp_enqueue_style( 'payex-checkout-instant', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/css/instant' . $suffix . '.css', array(), false, 'all' );
		}

		// Checkout scripts
		if ( is_checkout() || isset( $_GET['pay_for_order'] ) || is_add_payment_method_page() ) {
			if ( $this->instant_checkout === 'yes' ) {
				wp_register_script( 'wc-gateway-payex-checkout', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/js/checkout' . $suffix . '.js', array(
					'jquery',
					'wc-checkout'
				), false, true );
			} else {
				// Styles
				wp_enqueue_script( 'featherlight', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/js/featherlight/featherlight' . $suffix . '.js', array( 'jquery' ), '1.7.13', true );
				wp_enqueue_style( 'featherlight-css', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/js/featherlight/featherlight' . $suffix . '.css', array(), '1.7.13', 'all' );

				wp_register_script( 'wc-gateway-payex-checkout', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/js/checkout' . $suffix . '.js', array(
					'jquery',
					'wc-checkout',
					'featherlight'
				), false, true );
			}

			// Localize the script with new data
			$translation_array = array(
				'culture'          => $this->culture,
				'instant_checkout' => ( $this->instant_checkout === 'yes' ),
				'checkin'          => ( $this->checkin === 'yes' ),
				'nonce'            => wp_create_nonce( 'payex_checkout' ),
				'ajax_url'         => admin_url( 'admin-ajax.php' ),
				'paymentMenuStyle' => null,
				'checkInStyle'     => null,
			);

			// Add styles
			if ( $this->custom_styles === 'yes' ) {
				$translation_array['paymentMenuStyle'] = apply_filters( 'payex_checkout_paymentmenu_style', $this->paymentMenuStyle );
				$translation_array['checkInStyle'] = apply_filters( 'payex_checkout_checkin_style', $this->checkInStyle );
			}

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

		// Order Info
		$info = $this->get_order_info( $order );

		if ( isset( $_POST['is_update'] ) ) {
			$order->calculate_totals( true );

			// Get Payment Order ID
			$payment_id = get_post_meta( $order_id, '_payex_paymentorder_id', true );
			if ( empty( $payment_id ) ) {
				$payment_id = WC()->session->get( 'payex_paymentorder_id' );
			}

			if ( empty( $payment_id ) ) {
				// PaymentOrder is unknown
				return false;
			}

			$result     = $this->request( 'GET', $payment_id );
			$js_url     = self::get_operation( $result['operations'], 'view-paymentorder' );
			$update_url = self::get_operation( $result['operations'], 'update-paymentorder-updateorder' );
			if ( empty( $update_url ) ) {
				throw new Exception( 'Order update is not available.' );
			}

			// Don't update if amount is not changed
			if ( (int) $result['paymentOrder']['amount'] === round( $order->get_total() * 100 ) ) {
				return array(
					'result'            => 'success',
					'redirect'          => '#!payex-checkout',
					'is_payex_checkout' => true,
					'js_url'            => $js_url,
					'payment_id'        => $result['paymentOrder']['id'],
				);
			}

			// Update Order
			$params = [
				'paymentorder' => [
					'operation' => 'UpdateOrder',
					'amount'    => round( $order->get_total() * 100 ),
					'vatAmount' => 0,
					//'vatAmount' => round( $info['vat_amount'] * 100 ),
					//'orderItems' => $this->get_checkout_order_items( $order )
				]
			];

			$result = $this->request( 'PATCH', $update_url, $params );

			// Get JS Url
			$js_url = self::get_operation( $result['operations'], 'view-paymentorder' );

			return array(
				'result'            => 'success',
				'redirect'          => '#!payex-checkout',
				'is_payex_checkout' => true,
				'js_url'            => $js_url,
				'payment_id'        => $result['paymentOrder']['id'],
			);
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
				'vatAmount'   => round( $info['vat_amount'] * 100 ),
				'description' => sprintf( __( 'Order #%s', 'woocommerce-gateway-payex-checkout' ), $order->get_order_number() ),
				'userAgent'   => isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : $order->get_customer_user_agent(),
				'language'    => $this->culture,
				'generateRecurrenceToken' => false,
				'disablePaymentMenu' => false,
				'urls'        => [
					'hostUrls'          => [
						get_bloginfo( 'url' )
					],
					'completeUrl'       => html_entity_decode( $this->get_return_url( $order ) ),
					'cancelUrl'         => $order->get_cancel_order_url_raw(),
					'callbackUrl'       => WC()->api_request_url( __CLASS__ ),
					'termsOfServiceUrl' => $this->terms_url
				],
				'payeeInfo'   => [
					'payeeId'         => $this->payee_id,
					'payeeReference'  => str_replace( '-', '', $order_uuid ),
					'payeeName'       => get_bloginfo( 'name' ),
					'orderReference'  => $order->get_id()
				],
				'payer'       => [
					'firstName' => $order->get_billing_first_name(),
					'lastName' => $order->get_billing_last_name(),
					'email' => $order->get_billing_email(),
					'msisdn' => $order->get_billing_phone(),
					'homePhoneNumber' => $order->get_billing_phone(),
					'workPhoneNumber' => $order->get_billing_phone(),
					'shippingAddress' => [
						'firstName' => $order->get_shipping_first_name(),
						'lastName' => $order->get_shipping_last_name(),
						'email' => $order->get_billing_email(),
						'msisdn' => $order->get_billing_phone(),
						'streetAddress' => implode(', ', [$order->get_shipping_address_1(), $order->get_shipping_address_2()]),
						'coAddress' => '',
						'city' => $order->get_shipping_city(),
						'zipCode' => $order->get_shipping_postcode(),
						'countryCode' => $order->get_shipping_country()
					],
					'billingAddress' => [
						'firstName' => $order->get_billing_first_name(),
						'lastName' => $order->get_billing_last_name(),
						'email' => $order->get_billing_email(),
						'msisdn' => $order->get_billing_phone(),
						'streetAddress' => implode(', ', [$order->get_billing_address_1(), $order->get_billing_address_2()]),
						'coAddress' => '',
						'city' => $order->get_billing_city(),
						'zipCode' => $order->get_billing_postcode(),
						'countryCode' => $order->get_billing_country()
					],
				],
				'orderItems' => $this->get_checkout_order_items( $order ),
				'metadata'    => [
					'order_id' => $order_id
				],
				'items'       => [
					[
						'creditCard' => $this->get_card_options()
					]
				]
			]
		];

		// Add subsite
		if ( ! empty( $this->subsite ) ) {
			$params['payment']['payeeInfo']['subsite'] = $this->subsite;
		}

		// Get Consumer Profile
		$consumer_profile = isset( $_POST['payex_customer_reference'] ) ? wc_clean( $_POST['payex_customer_reference'] ) : null;
		if ( empty( $consumer_profile ) ) {
			if ( absint( $order->get_user_id() ) > 0 ) {
				$consumer_profile = get_user_meta( $order->get_user_id(), '_payex_consumer_profile', true );
			} else {
				$consumer_profile = WC()->session->get( 'payex_consumer_profile' );
			}
		}

		// Add consumerProfileRef if exists
		if ( ! empty( $consumer_profile ) ) {
			$params['paymentorder']['payer'] = [
				'consumerProfileRef' => $consumer_profile
			];
		}

		// Initiate Payment Order
		try {
			$result = $this->request( 'POST', '/psp/paymentorders', $params );
		} catch ( Exception $e ) {
			if ( ( strpos( $this->response_body, 'is not active' ) !== false ) ||
			     ( strpos( $this->response_body, 'Unable to verify consumerProfileRef' ) !== false ) ) {
				// Reference *** is not active, unable to complete
				$_POST['payex_customer_reference'] = null;
				delete_user_meta( $order->get_user_id(), '_payex_consumer_profile' );
				WC()->session->__unset( 'payex_consumer_profile' );

				$this->log( sprintf( 'It seem consumerProfileRef "%s" is invalid and was removed. Gateway returned error: %s', $consumer_profile, $e->getMessage() ) );

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

		// Get JS Url
		$js_url = self::get_operation( $result['operations'], 'view-paymentorder' );

		// Save JS Url in session
		WC()->session->set( 'payex_checkout_js_url', $js_url );

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
	 * @return void
	 * @throws \Exception
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
			$order_id = px_get_post_id_by_meta( '_payex_paymentorder_id', $paymentorder_id );

			// Get Order ID from payeeInfo if is not exist
			if ( empty( $order_id ) ) {
				$result      = $this->request( 'GET', $paymentorder_id . '/payeeInfo' );
				$order_id    = $result['payeeInfo']['orderReference'];

				if ( empty( $order_id ) ) {
					throw new \Exception( sprintf( 'Error: Failed to get order Id by Payment Order Id %s', $paymentorder_id ) );
				}

				// Save Order Payment Id
				update_post_meta( $order_id, '_payex_paymentorder_id', $paymentorder_id );
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
	 * @return void
	 * @throws \Exception
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
	 * @return void
	 * @throws \Exception
	 */
	public function cancel_payment( $order ) {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		$payment_id = get_post_meta( $order->get_id(), '_payex_payment_id', true );
		if ( empty( $payment_id ) ) {
			throw new Exception( 'Unable to get payment ID' );
		}

		// Use Invoice cancel
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

			$gateway->cancel_payment( $order );

			return;
		}

		parent::cancel_payment( $order );
	}

	/**
	 * Refund
	 *
	 * @param WC_Order|int $order
	 * @param bool $amount
	 * @param string $reason
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function refund_payment( $order, $amount = false, $reason = '' ) {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		$payment_id = get_post_meta( $order->get_id(), '_payex_payment_id', true );
		if ( empty( $payment_id ) ) {
			throw new Exception( 'Unable to get payment ID' );
		}

		// Use Invoice cancel
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
	 * Init Order
	 */
	public function init_order() {
		$order_id = absint( WC()->session->get( 'order_awaiting_payment' ) );
		if ( $order_id > 0 ) {
			$order = wc_get_order( $order_id );
			$order->set_payment_method( $this->id );
			$order->save();
		} else {
			// Place Order
			$customer = WC()->customer;
			$order_id = WC()->checkout()->create_order( array(
				'payment_method'        => $this->id,
				'billing_first_name'    => $customer->get_billing_first_name(),
				'billing_last_name'     => $customer->get_billing_last_name(),
				'billing_company'       => $customer->get_billing_company(),
				'billing_address_1'     => $customer->get_billing_address_1(),
				'billing_address_2'     => $customer->get_billing_address_2(),
				'billing_city'          => $customer->get_billing_city(),
				'billing_state'         => $customer->get_billing_state(),
				'billing_postcode'      => $customer->get_billing_postcode(),
				'billing_country'       => $customer->get_billing_country(),
				'billing_email'         => $customer->get_billing_email(),
				'billing_phone'         => $customer->get_billing_phone(),
				'shipping_first_name'   => $customer->get_shipping_first_name(),
				'shipping_last_name'    => $customer->get_shipping_last_name(),
				'shipping_company'      => $customer->get_shipping_company(),
				'shipping_address_1'    => $customer->get_shipping_address_1(),
				'shipping_address_2'    => $customer->get_shipping_address_2(),
				'shipping_city'         => $customer->get_shipping_city(),
				'shipping_state'        => $customer->get_shipping_state(),
				'shipping_postcode'     => $customer->get_shipping_postcode(),
				'shipping_country'      => $customer->get_shipping_country(),
				'shipping_email'        => $customer->get_billing_email(),
				'shipping_phone'        => $customer->get_billing_phone(),
			) );

			if ( is_wp_error( $order_id ) ) {
				throw new Exception( $order_id->get_error_message() );
			}

			// Store Order ID in session so it can be re-used after payment failure
			WC()->session->set( 'order_awaiting_payment', $order_id );
		}

		// Get Customer Profile
		if ( is_user_logged_in() ) {
			$_POST['payex_customer_reference']  = get_user_meta( get_current_user_id(), '_payex_consumer_profile', true );
		} else {
			$_POST['payex_customer_reference'] = WC()->session->get( 'payex_consumer_profile' );
		}

		// Initiate Payment Order
		$result = $this->process_payment( $order_id );
		if ( is_array( $result ) && isset( $result['js_url'] ) ) {
			WC()->session->set( 'payex_checkout_js_url', $result['js_url'] );
		} else {
			WC()->session->__unset( 'payex_checkout_js_url' );
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

		if ( $this->checkin !== 'yes' ) {
			return;
		}

		// Get saved consumerProfileRef
		if ( is_user_logged_in() ) {
			$consumer_profile = get_user_meta( get_current_user_id(), '_payex_consumer_profile', true );
			$consumer_data    = get_user_meta( get_current_user_id(), '_payex_consumer_address_billing', true );
			if ( empty( $consumer_data ) ) {
				// Deprecated
				$consumer_data = get_user_meta( get_current_user_id(), '_payex_consumer_address', true );
			}
		} else {
			$consumer_profile = WC()->session->get( 'payex_consumer_profile' );
			$consumer_data    = WC()->session->get( 'payex_checkin' );
		}

		// Initiate consumer session to obtain consumerProfileRef after checkin
		$js_url = null;
		if ( empty( $consumer_profile ) ) {
			// Initiate consumer session
			$params = [
				'operation'           => 'initiate-consumer-session',
				'consumerCountryCode' => 'SE', // @todo Allow choose country
			];

			try {
				$result = $this->request( 'POST', '/psp/consumers', $params );
				$js_url = self::get_operation( $result['operations'], 'view-consumer-identification' );
			} catch ( Exception $e ) {
				$consumer_profile = null;
				$consumer_data = null;
			}
		}

		// Checkin Form
		wc_get_template(
			'checkout/payex/checkin.php',
			array(
				'js_url'           => $js_url,
				'consumer_data'    => $consumer_data,
				'consumer_profile' => $consumer_profile
			),
			'',
			dirname( __FILE__ ) . '/../templates/'
		);
	}

	/**
	 * Render Payment Methods HTML.
	 *
	 * @return void
	 */
	public function woocommerce_checkout_payment() {
		if ( $this->instant_checkout === 'yes' ) {
			$js_url = WC()->session->get( 'payex_checkout_js_url' );

			wc_get_template(
				'checkout/payex/payment.php',
				array(
					//'checkout' => WC()->checkout()
					'js_url' => $js_url
				),
				'',
				dirname( __FILE__ ) . '/../templates/'
			);
		}
	}

	/**
	 * Ajax: Retrieve Address
	 *
	 * @return void
	 */
	public function ajax_payex_checkout_get_address() {
		check_ajax_referer( 'payex_checkout', 'nonce' );

		$type = isset( $_POST['type'] ) ? wc_clean( $_POST['type'] ) : '';
		$url  = isset( $_POST['url'] ) ? wc_clean( $_POST['url'] ) : '';

		// https://developer.payex.com/xwiki/wiki/developer/view/Main/ecommerce/technical-reference/consumers-resource/#HRetrieveConsumerShippingDetails
		try {
			// Check url
			if ( mb_substr( $url, 0, 1, 'UTF-8' ) === '/' ) {
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

	/**
	 * Ajax: Retrieve Consumer Profile Reference
	 *
	 * @return void
	 */
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
			$profile  = get_user_meta( $user_id, '_payex_consumer_profile', true );
			if ( empty( $profile ) ) {
				update_user_meta( $user_id, '_payex_consumer_profile', $customer_reference );
			}

			// Fill consumer data
			$consumer_data = get_user_meta( $user_id, '_payex_consumer_address_billing', true );
			if ( empty( $consumer_data ) ) {
				$consumer_data = array(
					'first_name' => WC()->customer->get_billing_first_name(),
					'last_name'  => WC()->customer->get_billing_last_name(),
					'postcode'   => WC()->customer->get_billing_postcode(),
					'city'       => WC()->customer->get_billing_city(),
					'email'      => WC()->customer->get_billing_email(),
					'phone'      => WC()->customer->get_billing_phone(),
				);

				update_user_meta( $user_id, '_payex_consumer_address_billing', $consumer_data );
			}
		} else {
			WC()->session->set( 'payex_consumer_profile', $customer_reference );

			// Fill consumer data
			$consumer_data = WC()->session->get( 'payex_checkin' );
			if ( empty( $consumer_data ) ) {
				$consumer_data = array(
					'first_name' => WC()->customer->get_billing_first_name(),
					'last_name'  => WC()->customer->get_billing_last_name(),
					'postcode'   => WC()->customer->get_billing_postcode(),
					'city'       => WC()->customer->get_billing_city(),
					'email'      => WC()->customer->get_billing_email(),
					'phone'      => WC()->customer->get_billing_phone(),
				);

				WC()->session->set( 'payex_checkin', $consumer_data );
			}
		}

		wp_send_json_success();
	}

	/**
	 * Ajax: Place Order.
	 *
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

	/**
	 * Ajax: Update Order's data.
	 *
	 * @throws Exception
	 */
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
				if ( $order->has_status( 'cancelled' ) ) {
					$order->update_status( 'failed' );
				}
			}
		}

		WC()->checkout()->process_checkout();
	}

	/**
	 * FrontEnd Error logger
	 */
	public function ajax_payex_checkout_log_error() {
		check_ajax_referer( 'payex_checkout', 'nonce' );

		$id = isset( $_POST['id'] ) ? wc_clean( $_POST['id'] ) : null;
		$data = isset( $_POST['data'] ) ? wc_clean( $_POST['data'] ) : null;

		// Try to decode JSON
		$decoded = @json_decode( $data );
		if ( json_last_error() === JSON_ERROR_NONE ) {
			$data = $decoded;
		}

		$this->log( sprintf( '[FRONTEND]: [%s]: [%s]: %s',
			$id,
			px_get_remote_address(),
			var_export( $data, true )
		) );

		wp_send_json_success();
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

		if ( $this->enabled === 'no' || $this->instant_checkout !== 'yes' ) {
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
	 *
	 * @return array
	 */
	public function lock_checkout_fields( $fieldset ) {
		if ( $this->enabled === 'yes' && $this->instant_checkout === 'yes' ) {
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
						$field['class'][]                       = 'payex-locked';
					}
				}
			}
		}

		return $fieldset;
	}

	/**
	 * Checkout initialization
	 *
	 * @param WC_Checkout $checkout
	 */
	public function checkout_init( $checkout ) {
		remove_action( 'woocommerce_checkout_billing', array( $checkout, 'checkout_form_billing' ), 10 );
		remove_action( 'woocommerce_checkout_shipping', array( $checkout, 'checkout_form_shipping' ), 10 );
	}

	/**
	 * Billing form
	 */
	public function checkout_form_billing() {
		wc_get_template(
			'checkout/payex/form-billing.php',
			array(
				'checkout' => WC()->checkout()
			),
			'',
			dirname( __FILE__ ) . '/../templates/'
		);
	}

	/**
	 * Shipping Info
	 */
	public function checkout_form_shipping() {
		wc_get_template( 'checkout/form-shipping.php', array( 'checkout' => WC()->checkout() ) );
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
		if ( $this->enabled !== 'yes' || $this->instant_checkout !== 'yes' ) {
			return $located;
		}

		if ( strpos( $located, 'checkout/form-checkout.php' ) !== false ) {
			$located = wc_locate_template(
				'checkout/payex/form-checkout.php',
				$template_path,
				dirname( __FILE__ ) . '/../templates/'
			);
		}

		return $located;
	}

	/**
	 * Get Order Lines
	 *
	 * @param WC_Order $order
	 *
	 * @return array
	 */
	protected function get_checkout_order_items( $order ) {
		$item = [];

		foreach ( $order->get_items() as $order_item ) {
			/** @var WC_Order_Item_Product $order_item */
			$price        = $order->get_line_subtotal( $order_item, false, false );
			$priceWithTax = $order->get_line_subtotal( $order_item, true, false );
			$tax          = $priceWithTax - $price;
			$taxPercent   = ( $tax > 0 ) ? round( 100 / ( $price / $tax ) ) : 0;
			$qty          = $order_item->get_quantity();

			if ( $image = wp_get_attachment_image_src( $order_item->get_product()->get_image_id(), 'full' ) ) {
				$image = $image[0];
			} else {
				$image = wc_placeholder_img_src( 'full' );
			}

			// Get Product Class
			$product_class = get_post_meta( $order_item->get_product()->get_id(), '_payex_product_class', true );
			if ( empty( $product_class ) ) {
				$product_class = 'ProductGroup1';
			}

			$item[] = [
				// The field Reference must match the regular expression '[\\w-]*'
				'reference'    => str_replace( ' ', '-', $order_item->get_product()->get_sku() ),
				'name'         => $order_item->get_name(),
				'type'         => 'PRODUCT',
				'class'        => $product_class,
				'itemUrl'      => $order_item->get_product()->get_permalink(),
				'imageUrl'     => $image,
				'description'  => $order_item->get_name(),
				'quantity'     => $qty,
				'quantityUnit' => 'pcs',
				'unitPrice'    => round( $price / $qty * 100 ),
				'vatPercent'   => round( $taxPercent * 100 ),
				'amount'       => round( $priceWithTax * 100 ),
				'vatAmount'    => round( $tax * 100 )
			];
		}

		// Add Shipping Line
		if ( (float) $order->get_shipping_total() > 0 ) {
			$shipping        = $order->get_shipping_total();
			$tax             = $order->get_shipping_tax();
			$shippingWithTax = $shipping + $tax;
			$taxPercent      = ( $tax > 0 ) ? round( 100 / ( $shipping / $tax ) ) : 0;

			$item[] = [
				'reference' => 'shipping',
				'name' => $order->get_shipping_method(),
				'type' => 'SHIPPING_FEE',
				'class' => 'ProductGroup1',
				'quantity' => 1,
				'quantityUnit' => 'pcs',
				'unitPrice' => round( $shipping * 100 ),
				'vatPercent' => round( $taxPercent * 100 ),
				'amount' => round( $shippingWithTax * 100 ),
				'vatAmount' => round( $tax * 100 )
			];
		}

		// Add fee lines
		foreach ( $order->get_fees() as $order_fee ) {
			/** @var WC_Order_Item_Fee $order_fee */
			$fee        = $order_fee->get_total();
			$tax        = $order_fee->get_total_tax();
			$feeWithTax = $fee + $tax;
			$taxPercent = ( $tax > 0 ) ? round( 100 / ( $fee / $tax ) ) : 0;

			$item[] = [
				'reference' => 'fee',
				'name' => $order_fee->get_name(),
				'type' => 'OTHER',
				'class' => 'ProductGroup1',
				'quantity' => 1,
				'quantityUnit' => 'pcs',
				'unitPrice' => round( $fee * 100 ),
				'vatPercent' => round( $taxPercent * 100 ),
				'amount' => round( $feeWithTax * 100 ),
				'vatAmount' => round( $tax * 100 )
			];
		}

		// Add discount line
		if ( $order->get_total_discount( false ) > 0 ) {
			$discount        = $order->get_total_discount( true );
			$discountWithTax = $order->get_total_discount( false );
			$tax             = $discountWithTax - $discount;
			$taxPercent      = ( $tax > 0 ) ? round( 100 / ( $discount / $tax ) ) : 0;

			$item[] = [
				'reference' => 'discount',
				'name' => __( 'Discount', 'payex-woocommerce-payments' ),
				'type' => 'DISCOUNT',
				'class' => 'ProductGroup1',
				'quantity' => 1,
				'quantityUnit' => 'pcs',
				'unitPrice' => round( - 100 * $discount ),
				'vatPercent' => round( 100 * $taxPercent ),
				'amount' => round( - 100 * $discountWithTax ),
				'vatAmount' => round( - 100 * $tax )
			];
		}

		return $item;
	}
}

// Register Gateway
WC_Payex_Psp::register_gateway( 'WC_Gateway_Payex_Checkout' );
