<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly


class WC_Gateway_Swedbank_Pay_Checkout extends WC_Gateway_Swedbank_Pay_Cc
	implements WC_Payment_Gateway_Swedbank_Pay_Interface {

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
	 * Checkin Country
	 * @var string
	 */
	public $checkin_country = 'SE';

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
		$this->transactions = WC_Swedbank_Pay_Transactions::instance();

		$this->id           = 'payex_checkout';
		$this->has_fields   = true;
		$this->method_title = __( 'Swedbank Pay Checkout', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN );
		//$this->icon         = apply_filters( 'wc_swedbank_pay_checkout_icon', plugins_url( '/assets/images/checkout.gif', dirname( __FILE__ ) ) );
		$this->supports     = [
			'products',
			'refunds',
		];

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
		$this->checkin_country  = isset( $this->settings['checkin_country'] ) ? $this->settings['checkin_country'] : $this->checkin_country;
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
		add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		add_action( 'woocommerce_thankyou_' . $this->id, [ $this, 'thankyou_page' ] );

		// Payment listener/API hook
		add_action( 'woocommerce_api_' . strtolower( __CLASS__ ), [ $this, 'return_handler' ] );

		// Payment confirmation
		add_action( 'the_post', [ $this, 'payment_confirm' ] );

		// Ajax Actions
		foreach ( [
			'checkout_get_address',
			'checkout_customer_profile',
			'checkin',
			'place_order',
			'update_order',
			'checkout_log_error' ] as $action
		) {
			add_action( 'wp_ajax_swedbank_pay_' . $action, [ $this, 'ajax_swedbank_pay_' . $action ] );
			add_action( 'wp_ajax_nopriv_swedbank_pay_' . $action, [ $this, 'ajax_swedbank_pay_' . $action ] );
		}

		// Instant Checkout
		if ( $this->instant_checkout === 'yes' ) {
			add_action( 'woocommerce_checkout_init', [ $this, 'checkout_init' ], 10, 1 );
			add_action( 'woocommerce_checkout_billing', [ $this, 'checkout_form_billing' ] );
			add_action( 'woocommerce_before_checkout_billing_form', [ $this, 'before_checkout_billing_form' ] );
			add_action( 'woocommerce_checkout_order_review', [ $this, 'woocommerce_checkout_payment' ], 20 );
			add_filter( 'wc_get_template', [ $this, 'override_template'], 5, 20 );
			add_filter( 'woocommerce_available_payment_gateways', [ $this, 'filter_gateways' ], 1 );
			add_filter( 'woocommerce_checkout_fields', [ $this, 'lock_checkout_fields' ], 10, 1 );
			add_filter( 'woocommerce_checkout_get_value', [ $this, 'checkout_get_value' ], 10, 2 );
			add_action( 'woocommerce_before_checkout_form_cart_notices', [ $this, 'init_order' ] );
		}

		// Filters
		add_filter( 'swedbank_pay_checkout_order_items', __CLASS__ . '::checkout_order_items', 10, 2 );
		add_filter( 'swedbank_pay_checkout_order_vat', __CLASS__ . '::checkout_order_vat', 10, 2 );
		add_filter( 'swedbank_pay_checkout_order_amount', __CLASS__ . '::checkout_order_amount', 10, 2 );
	}

	/**
	 * Initialise Settings Form Fields
	 * @return string|void
	 */
	public function init_form_fields() {
		$this->form_fields = [
			'enabled'          => [
				'title'   => __( 'Enable/Disable', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable plugin', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				'default' => 'no'
			],
			'title'            => [
				'title'       => __( 'Title', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				'default'     => __( 'Swedbank Pay Checkout', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN )
			],
			'description'      => [
				'title'       => __( 'Description', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				'default'     => __( 'Swedbank Pay Checkout', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
			],
			'merchant_token'   => [
				'title'       => __( 'Merchant Token', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				'type'        => 'text',
				'description' => __( 'Merchant Token', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				'default'     => $this->merchant_token
			],
			'payee_id'         => [
				'title'       => __( 'Payee Id', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				'type'        => 'text',
				'description' => __( 'Payee Id', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				'default'     => $this->payee_id
			],
			'subsite'         => [
				'title'       => __( 'Subsite', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				'type'        => 'text',
				'description' => __( 'Subsite', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				'default'     => $this->subsite
			],
			'testmode'         => [
				'title'   => __( 'Test Mode', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Swedbank Pay Test Mode', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				'default' => $this->testmode
			],
			'debug'            => [
				'title'   => __( 'Debug', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable logging', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				'default' => $this->debug
			],
			'culture'          => [
				'title'       => __( 'Language', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				'type'        => 'select',
				'options'     => [
					'en-US' => 'English',
					'sv-SE' => 'Swedish',
					'nb-NO' => 'Norway',
				],
				'description' => __( 'Language of pages displayed by Swedbank Pay during payment.', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				'default'     => $this->culture
			],
			'instant_checkout' => [
				'title'   => __( 'Use Swedbank Pay Checkout instead of WooCommerce Checkout', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				'type'    => 'checkbox',
				'label'   => __( 'Use Swedbank Pay Checkout instead of WooCommerce Checkout', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				'default' => $this->instant_checkout
			],
			'checkin' => [
				'title'   => __( 'Enable Checkin on Swedbank Pay Checkout', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Checkin on Swedbank Pay Checkout', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				'default' => $this->checkin
			],
			'checkin_country'  => [
				'title'       => __( 'Checkin country', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				'type'        => 'select',
				'options'     => [
					'SE'     => __( 'Sweden', 'woocommerce' ),
					'NO'     => __( 'Norway', 'woocommerce' ),
					'SELECT' => __( 'Customer can choose', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				],
				'description' => __( 'Checkin country', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				'default'     => $this->checkin_country
			],
			'terms_url'        => [
				'title'       => __( 'Terms & Conditions Url', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				'type'        => 'text',
				'description' => __( 'Terms & Conditions Url', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				'default'     => get_site_url()
			],
			'reject_credit_cards' => [
				'title'   => __( 'Reject Credit Cards', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				'type'    => 'checkbox',
				'label'   => __( 'Reject Credit Cards', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				'default' => $this->reject_credit_cards
			],
			'reject_debit_cards' => [
				'title'   => __( 'Reject Debit Cards', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				'type'    => 'checkbox',
				'label'   => __( 'Reject Debit Cards', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				'default' => $this->reject_debit_cards
			],
			'reject_consumer_cards' => [
				'title'   => __( 'Reject Consumer Cards', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				'type'    => 'checkbox',
				'label'   => __( 'Reject Consumer Cards', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				'default' => $this->reject_consumer_cards
			],
			'reject_corporate_cards' => [
				'title'   => __( 'Reject Corporate Cards', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				'type'    => 'checkbox',
				'label'   => __( 'Reject Corporate Cards', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
				'default' => $this->reject_corporate_cards
			],
		];
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

		wp_enqueue_style( 'swedbank-pay-checkout-css', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/css/style' . $suffix . '.css', [], false, 'all' );

		if ( $this->instant_checkout === 'yes' ) {
			wp_enqueue_style( 'swedbank-pay-checkout-instant', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/css/instant' . $suffix . '.css', [], false, 'all' );
		}

		// Checkout scripts
		if ( is_checkout() || isset( $_GET['pay_for_order'] ) || is_add_payment_method_page() ) {
			if ( $this->instant_checkout === 'yes' ) {
				wp_register_script( 'wc-gateway-swedbank-pay-checkout', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/js/checkout' . $suffix . '.js', [
					'jquery',
					'wc-checkout'
				], false, true );
			} else {
				// Styles
				wp_enqueue_script( 'featherlight', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/js/featherlight/featherlight' . $suffix . '.js', [ 'jquery' ], '1.7.13', true );
				wp_enqueue_style( 'featherlight-css', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/js/featherlight/featherlight' . $suffix . '.css', [], '1.7.13', 'all' );

				wp_register_script( 'wc-gateway-swedbank-pay-checkout', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/js/checkout' . $suffix . '.js', [
					'jquery',
					'wc-checkout',
					'featherlight'
				], false, true );
			}

			// Localize the script with new data
			$translation_array = [
				'culture'                      => $this->culture,
				'instant_checkout'             => ( $this->instant_checkout === 'yes' ),
				'needs_shipping_address'       => WC()->cart->needs_shipping(),
				'ship_to_billing_address_only' => wc_ship_to_billing_address_only(),
				'checkin'                      => ( $this->checkin === 'yes' ),
				'nonce'                        => wp_create_nonce( 'swedbank_pay_checkout' ),
				'ajax_url'                     => admin_url( 'admin-ajax.php' ),
				'paymentMenuStyle'             => null,
				'checkInStyle'                 => null,
			];

			// Add PM styles
			if ( $styles = apply_filters( 'swedbank_pay_checkout_paymentmenu_style', $this->paymentMenuStyle ) ) {
				$translation_array['paymentMenuStyle'] = $styles;
			}

			// Add CheckIn Styles
			if ( $styles = apply_filters( 'swedbank_pay_checkout_checkin_style', $this->checkInStyle ) ) {
				$translation_array['checkInStyle'] = $styles;
			}

			wp_localize_script( 'wc-gateway-swedbank-pay-checkout', 'WC_Gateway_Swedbank_Pay_Checkout', $translation_array );

			// Enqueued script with localized data.
			wp_enqueue_script( 'wc-gateway-swedbank-pay-checkout' );
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

		// Get Order Items
		$items = apply_filters( 'swedbank_pay_checkout_order_items', [], $order );

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
				return [
					'result'               => 'success',
					'redirect'             => '#!swedbank-pay-checkout',
					'is_swedbank_pay_checkout' => true,
					'js_url'               => $js_url,
					'payment_id'           => $result['paymentOrder']['id'],
				];
			}

			// Update Order
			$params = [
				'paymentorder' => [
					'operation' => 'UpdateOrder',
					'amount'    => apply_filters( 'swedbank_pay_checkout_order_amount', 0, $order ),
					'vatAmount' => apply_filters( 'swedbank_pay_checkout_order_vat', 0, $order ),
					'orderItems' => $items
				]
			];

			$result = $this->request( 'PATCH', $update_url, $params );

			// Get JS Url
			$js_url = self::get_operation( $result['operations'], 'view-paymentorder' );

			return [
				'result'               => 'success',
				'redirect'             => '#!swedbank-pay-checkout',
				'is_swedbank_pay_checkout' => true,
				'js_url'               => $js_url,
				'payment_id'           => $result['paymentOrder']['id'],
			];
		}

		// Mode that workaround "Order update is not available"
		if ( isset( $_POST['is_update_backward_compat'] ) ) {
			$order->calculate_totals( true );

			// Delete Payment Order ID
			delete_post_meta( $order_id, '_payex_paymentorder_id' );
		}

		// Check terms_url value
		if ( parse_url( $this->terms_url, PHP_URL_SCHEME ) !== 'https' ) {
			$this->terms_url = '';
		}

		$payeeReference = apply_filters( 'swedbank_pay_get_payee_reference', null, $order, false );
		if ( ! $payeeReference ) {
			// It seem there's old version of payex-woocommerce-payments
			$payeeReference = $order->get_order_number();
		}

		$params = [
			'paymentorder' => [
				'operation'   => 'Purchase',
				'currency'    => $order->get_currency(),
				'amount'      => apply_filters( 'swedbank_pay_checkout_order_amount', 0, $order ),
				'vatAmount'   => apply_filters( 'swedbank_pay_checkout_order_vat', 0, $order ),
				'description' => sprintf( __( 'Order #%s', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ), $order->get_order_number() ),
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
					'payeeReference'  => $payeeReference,
					'payeeName'       => get_bloginfo( 'name' ),
					'orderReference'  => $order->get_order_number()
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
				'orderItems' => $items,
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
		$reference = isset( $_POST['swedbank_pay_customer_reference'] ) ? wc_clean( $_POST['swedbank_pay_customer_reference'] ) : null;
		if ( empty( $reference ) ) {
			$profile   = $this->get_consumer_profile( $order->get_user_id() );
			$reference = $profile['reference'];
		}

		// Add consumerProfileRef if exists
		if ( ! empty( $reference ) ) {
			$params['paymentorder']['payer'] = [
				'consumerProfileRef' => $reference
			];
		}

		// Initiate Payment Order
		try {
			$result = $this->request( 'POST', '/psp/paymentorders', $params );
		} catch ( Exception $e ) {
			// Parse response
			// https://tools.ietf.org/html/rfc7807
			$response = @json_decode( $this->response_body, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				wc_add_notice( $e->getMessage(), 'error' );

				return false;
			}

			// Check problems
			foreach ($response['problems'] as $problem) {
				// PayeeReference: The given PayeeReference has already been used for another payment (xxxxx).
				// @todo Check cause of different name "PaymentOrder.PayeeInfo.PayeeReference"
				if ( $problem['name'] === 'PayeeReference' ) {
					// Generate reference with salt
					$order->delete_meta_data( '_sb_payee_reference' );
					$order->save_meta_data();
					apply_filters( 'swedbank_pay_get_payee_reference', null, $order, true );

					return $this->process_payment( $order_id );
				}

				// consumerProfileRef: Reference *** is not active, unable to complete
				if ( $problem['name'] === 'consumerProfileRef' ) {
					// Remove the inactive customer reference
					$_POST['swedbank_pay_customer_reference'] = null;
					$this->drop_consumer_profile( $order->get_user_id() );

					// Remove saved address
					//delete_user_meta( $order->get_user_id(), '_payex_consumer_address_billing' );
					//delete_user_meta( $order->get_user_id(), '_payex_consumer_address_shipping' );
					//WC()->session->__unset( 'swedbank_pay_checkin_billing' );
					//WC()->session->__unset( 'swedbank_pay_checkin_shipping' );

					// Reload checkout
					if ( $this->instant_checkout === 'yes' ) {
						wc_add_notice( __( 'Unable to verify consumer profile reference. Try to login again.', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ), 'error' );

						return [
							'result'   => 'failure',
							'messages' => __( 'Unable to verify consumer profile reference. Try to login again.', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
							'reload'   => true,
						];
					}

					// Try again
					return $this->process_payment( $order_id );
				}
			}

			wc_add_notice( $e->getMessage(), 'error' );

			return false;
		}

		// Save PaymentOrder ID
		update_post_meta( $order_id, '_payex_paymentorder_id', $result['paymentOrder']['id'] );
		WC()->session->set( 'swedbank_paymentorder_id', $result['paymentOrder']['id'] );

		// Get JS Url
		$js_url = self::get_operation( $result['operations'], 'view-paymentorder' );

		// Save JS Url in session
		WC()->session->set( 'swedbank_pay_checkout_js_url', $js_url );

		return [
			'result'               => 'success',
			'redirect'             => '#!swedbank-pay-checkout',
			'is_swedbank_pay_checkout' => true,
			'js_url'               => $js_url,
			'payment_id'           => $result['paymentOrder']['id'],
		];
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

		if ( swedbank_pay_obj_prop( $order, 'payment_method' ) !== $this->id ) {
			return;
		}

		WC()->session->__unset( 'swedbank_paymentorder_id' );

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
			$order_id = swedbank_pay_get_post_id_by_meta( '_payex_paymentorder_id', $paymentorder_id );

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

			/** @var WC_Gateway_Swedbank_Pay_Invoice $gateway */
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

			/** @var WC_Gateway_Swedbank_Pay_Invoice $gateway */
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

			/** @var WC_Gateway_Swedbank_Pay_Invoice $gateway */
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

			$address = [
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
			];

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
			$order_id = WC()->checkout()->create_order( [
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
			] );

			if ( is_wp_error( $order_id ) ) {
				throw new Exception( $order_id->get_error_message() );
			}

			// Store Order ID in session so it can be re-used after payment failure
			WC()->session->set( 'order_awaiting_payment', $order_id );
		}

		// Get Customer Profile
		$profile = $this->get_consumer_profile( get_current_user_id() );

		// Initiate Payment Order
		$_POST['swedbank_pay_customer_reference'] = $profile['reference'];
		$result = $this->process_payment( $order_id );
		if ( is_array( $result ) && isset( $result['js_url'] ) ) {
			WC()->session->set( 'swedbank_pay_checkout_js_url', $result['js_url'] );
		} else {
			WC()->session->__unset( 'swedbank_pay_checkout_js_url' );
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
		$profile = $this->get_consumer_profile( get_current_user_id() );

		// Initiate consumer session to obtain consumerProfileRef after checkin
		$js_url = $profile['url'];
		if ( empty( $profile['reference'] ) ) {
			// Initiate consumer session
			$params = [
				'operation'           => 'initiate-consumer-session',
				'consumerCountryCode' => $this->checkin_country,
			];

			try {
				$result = $this->request( 'POST', '/psp/consumers', $params );
				$js_url = self::get_operation( $result['operations'], 'view-consumer-identification' );
			} catch ( Exception $e ) {
				$profile['reference'] = null;
				$profile['billing'] = null;
			}
		}

		WC()->session->set( 'consumer_js_url', $js_url );

		// Checkin Form
		wc_get_template(
			'checkout/swedbank-pay/checkin.php',
			[
				'checkin_country'  => $this->checkin_country,
				'selected_country' => apply_filters( 'swedbank_pay_checkin_default_country', 'SE' ),
				'js_url'           => $js_url,
				'consumer_data'    => $profile['billing'],
				'consumer_profile' => $profile['reference']
			],
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
		if ( $this->enabled === 'yes' && $this->instant_checkout === 'yes' ) {
			$js_url = WC()->session->get( 'swedbank_pay_checkout_js_url' );

			wc_get_template(
				'checkout/swedbank-pay/payment.php',
				[
					//'checkout' => WC()->checkout()
					'js_url' => $js_url
				],
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
	public function ajax_swedbank_pay_checkout_get_address() {
		check_ajax_referer( 'swedbank_pay_checkout', 'nonce' );

		$type = isset( $_POST['type'] ) ? wc_clean( $_POST['type'] ) : '';
		$url  = isset( $_POST['url'] ) ? wc_clean( $_POST['url'] ) : '';

		// https://developer.payex.com/xwiki/wiki/developer/view/Main/ecommerce/technical-reference/consumers-resource/#HRetrieveConsumerShippingDetails
		try {
			// Check url
			if ( mb_substr( $url, 0, 1, 'UTF-8' ) === '/' ) {
				$url = $this->backend_api_endpoint . $url;
			}

			$host = parse_url( $url, PHP_URL_HOST );
			if ( ! in_array( $host, [ 'api.payex.com', 'api.externalintegration.payex.com' ] ) ) {
				throw new Exception( 'Access denied' );
			}

			$result = $this->request( 'GET', $url );
		} catch ( Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
			exit();
		}

		$address = isset( $result['billingAddress'] ) ? $result['billingAddress'] : $result['shippingAddress'];

		// Parse name field
		$parser = new \FullNameParser();
		$name   = $parser->parse_name( $address['addressee'] );

		$output = [
			'first_name' => $name['fname'],
			'last_name'  => $name['lname'],
			'country'    => $address['countryCode'],
			'postcode'   => $address['zipCode'],
			'address_1'  => $address['streetAddress'],
			'address_2'  => ! empty ( $address['coAddress'] ) ? 'c/o ' . $address['coAddress'] : '',
			'city'       => $address['city'],
			'state'      => '',
			'phone'      => $result['msisdn'],
			'email'      => $result['email'],
		];

		// Save address
		$this->update_consumer_address( get_current_user_id(), $type, $output );

		wp_send_json_success( $output );
	}

	/**
	 * Ajax: CheckIn
	 */
	public function ajax_swedbank_pay_checkin() {
		check_ajax_referer( 'swedbank_pay_checkout', 'nonce' );

		$country = isset( $_POST['country'] ) ? wc_clean( $_POST['country'] ) : '';

		// Initiate consumer session
		$params = [
			'operation'           => 'initiate-consumer-session',
			'consumerCountryCode' => $country,
		];

		try {
			$result = $this->request( 'POST', '/psp/consumers', $params );
			$js_url = self::get_operation( $result['operations'], 'view-consumer-identification' );
			wp_send_json_success( $js_url );
		} catch ( Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * Ajax: Retrieve Consumer Profile Reference
	 *
	 * @return void
	 */
	public function ajax_swedbank_pay_checkout_customer_profile() {
		check_ajax_referer( 'swedbank_pay_checkout', 'nonce' );

		$customer_reference = isset( $_POST['consumerProfileRef'] ) ? wc_clean( $_POST['consumerProfileRef'] ) : '';
		if ( empty( $customer_reference ) ) {
			wp_send_json_error( [ 'message' => 'Customer reference required' ] );
			exit();
		}

		// Store Customer Profile
		$url = WC()->session->get( 'consumer_js_url' );
		$this->update_consumer_profile( get_current_user_id(),  $customer_reference, $url );

		wp_send_json_success();
	}

	/**
	 * Ajax: Place Order.
	 *
	 * @throws Exception
	 */
	public function ajax_swedbank_pay_place_order() {
		check_ajax_referer( 'swedbank_pay_checkout', 'nonce' );

		$data = [];
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
	public function ajax_swedbank_pay_update_order() {
		check_ajax_referer( 'swedbank_pay_checkout', 'nonce' );

		// Get Order
		$order_id = absint( WC()->session->get( 'order_awaiting_payment' ) );
		if ( $order_id === 0 ) {
			wp_send_json( [
				'result'   => 'failure',
				'messages' => 'Order is not exists',
			] );

			return;
		}

		// Get Order
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json( [
				'result'   => 'failure',
				'messages' => 'Order is not exists',
			] );

			return;
		}

		// Mark order failed instead of cancelled
		if ( $order->get_payment_method() === $this->id && $order->has_status( 'cancelled' ) ) {
			$order->update_status( 'failed' );
		}

		// Prepare $_POST data
		$data = [];
		parse_str( $_POST['data'], $data );
		$_POST = $data;
		unset( $_POST['terms-field'], $_POST['terms'] );

		$_POST['payment_method'] = $this->id;

		if ( ! empty( $_POST['compat'] ) && (bool) $_POST['compat'] ) {
			$_POST['is_update_backward_compat'] = 1;
		} else {
			$_POST['is_update'] = '1';
		}

		// Update Checkout
		// @see WC_AJAX::update_order_review()
		if ( ! empty( $_POST['shipping_method'] ) && ! is_array( $_POST['shipping_method'] ) ) {
			$shipping = $_POST['shipping_method'];
			$_POST['shipping_method'] = [ $shipping ];
		}

		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
		if ( isset( $_POST['shipping_method'] ) && is_array( $_POST['shipping_method'] ) ) {
			foreach ( $_POST['shipping_method'] as $i => $value ) {
				$chosen_shipping_methods[ $i ] = wc_clean( $value );
			}
		}

		WC()->session->set( 'chosen_shipping_methods', $chosen_shipping_methods );
		WC()->session->set( 'chosen_payment_method', $this->id );

		// Update address data
		foreach ($_POST as $key => $value) {
			if ( ( strpos( $key, 'billing_' ) !== false ) || ( strpos( $key, 'shipping_' ) !== false ) ) {
				if ( is_callable( [ $order, "set_{$key}" ] ) ) {
					$order->{"set_{$key}"}( $value );
				}

				WC()->customer->set_props( [ $key => $value ] );
			}
		}

		// Recalculate cart
		WC()->customer->set_calculated_shipping( true );
		WC()->customer->save();
		WC()->cart->calculate_totals();

		// Recalculate order
		$order->calculate_totals( true );
		$order->save();

		// Process checkout
		$_REQUEST['woocommerce-process-checkout-nonce'] = wp_create_nonce( 'woocommerce-process_checkout' );
		$_POST['_wpnonce']                              = wp_create_nonce( 'woocommerce-process_checkout' );
		WC()->checkout()->process_checkout();
	}

	/**
	 * FrontEnd Error logger
	 */
	public function ajax_swedbank_pay_checkout_log_error() {
		check_ajax_referer( 'swedbank_pay_checkout', 'nonce' );

		$id = isset( $_POST['id'] ) ? wc_clean( $_POST['id'] ) : null;
		$data = isset( $_POST['data'] ) ? wc_clean( $_POST['data'] ) : null;

		// Try to decode JSON
		$decoded = @json_decode( $data );
		if ( json_last_error() === JSON_ERROR_NONE ) {
			$data = $decoded;
		}

		$this->log( sprintf( '[FRONTEND]: [%s]: [%s]: %s',
			$id,
			swedbank_pay_get_remote_address(),
			var_export( $data, true )
		) );

		wp_send_json_success();
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
			// Fill form with these data
			foreach ( $fieldset as $section => &$fields ) {
				foreach ( $fields as $key => &$field ) {
					$field['default'] = $this->checkout_get_value( null, $key );

					$field['custom_attributes']['readonly'] = 'readonly';
					$field['class'][]                       = 'swedbank-locked';
				}
			}
		}

		return $fieldset;
	}

	/**
	 * Fill checkout fields
	 *
	 * @param mixed $value
	 * @param mixed $input
	 *
	 * @return mixed
	 */
	public function checkout_get_value( $value, $input ) {
		if ( $this->enabled === 'yes' && $this->instant_checkout === 'yes' ) {
			$profile = $this->get_consumer_profile( get_current_user_id() );

			// Add default data
			$default = [
				'first_name' => WC()->customer->get_billing_first_name(),
				'last_name'  => WC()->customer->get_billing_last_name(),
				'postcode'   => WC()->customer->get_billing_postcode(),
				'city'       => WC()->customer->get_billing_city(),
				'email'      => WC()->customer->get_billing_email(),
				'phone'      => WC()->customer->get_billing_phone(),
				'country'    => WC()->customer->get_billing_country(),
				'state'      => WC()->customer->get_billing_state(),
				'address_1'  => WC()->customer->get_billing_address_1(),
				'address_2'  => WC()->customer->get_billing_address_2(),
			];
			$billing = array_merge( $default, is_array( $profile['billing'] ) ? $profile['billing'] : [] );
			$shipping = array_merge( $default, is_array( $profile['shipping'] ) ? $profile['shipping'] : [] );

			// Fill form with these data
			switch ( $input ) {
				case 'billing_first_name':
					$value = $billing['first_name'];
					break;
				case 'billing_last_name':
					$value = $billing['last_name'];
					break;
				case 'billing_country':
					$value = $billing['country'];
					break;
				case 'billing_address_1':
					$value = $billing['address_1'];
					break;
				case 'billing_address_2':
					$value = $billing['address_2'];
					break;
				case 'billing_postcode':
					$value = $billing['postcode'];
					break;
				case 'billing_city':
					$value = $billing['city'];
					break;
				case 'billing_state':
					$value = $billing['state'];
					break;
				case 'billing_phone':
					$value = $billing['phone'];
					break;
				case 'shipping_first_name':
					$value = $shipping['first_name'];
					break;
				case 'shipping_last_name':
					$value = $shipping['last_name'];
					break;
				case 'shipping_country':
					$value = $shipping['country'];
					break;
				case 'shipping_address_1':
					$value = $shipping['address_1'];
					break;
				case 'shipping_address_2':
					$value = $shipping['address_2'];
					break;
				case 'shipping_postcode':
					$value = $shipping['postcode'];
					break;
				case 'shipping_city':
					$value = $shipping['city'];
					break;
				case 'shipping_state':
					$value = $shipping['state'];
					break;
				case 'shipping_phone':
					$value = $shipping['phone'];
					break;
				default:
					// no default
			}
		}

		return $value;
	}

	/**
	 * Checkout initialization
	 *
	 * @param WC_Checkout $checkout
	 */
	public function checkout_init( $checkout ) {
		remove_action( 'woocommerce_checkout_billing', [ $checkout, 'checkout_form_billing' ], 10 );
		remove_action( 'woocommerce_checkout_shipping', [ $checkout, 'checkout_form_shipping' ], 10 );
	}

	/**
	 * Billing form
	 */
	public function checkout_form_billing() {
		wc_get_template(
			'checkout/swedbank-pay/form-billing.php',
			[
				'checkout' => WC()->checkout()
			],
			'',
			dirname( __FILE__ ) . '/../templates/'
		);
	}

	/**
	 * Shipping Info
	 */
	public function checkout_form_shipping() {
		wc_get_template( 'checkout/form-shipping.php', [ 'checkout' => WC()->checkout() ] );
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
				'checkout/swedbank-pay/form-checkout.php',
				$template_path,
				dirname( __FILE__ ) . '/../templates/'
			);
		}

		return $located;
	}

	/**
	 * Get Order Lines
	 *
	 * @param array $items
	 * @param WC_Order $order
	 *
	 * @return array
	 */
	public static function checkout_order_items( $items, $order ) {
		if ( ! is_array( $items ) ) {
			$items = [];
		}

		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order );
		}

		foreach ( $order->get_items() as $order_item ) {
			/** @var WC_Order_Item_Product $order_item */
			$price        = $order->get_line_subtotal( $order_item, false, false );
			$priceWithTax = $order->get_line_subtotal( $order_item, true, false );
			$tax          = $priceWithTax - $price;
			$taxPercent   = ( $tax > 0 ) ? round( 100 / ( $price / $tax ) ) : 0;
			$qty          = $order_item->get_quantity();

			if ( $image = wp_get_attachment_image_src( $order_item->get_product()->get_image_id(), 'full' ) ) {
				$image = array_shift( $image );
			} else {
				$image = wc_placeholder_img_src( 'full' );
			}

			if ( null === parse_url( $image, PHP_URL_SCHEME ) &&
				 mb_substr( $image, 0, mb_strlen( WP_CONTENT_URL ), 'UTF-8' ) === WP_CONTENT_URL
			) {
				$image = wp_guess_url() . $image;
			}

			// Get Product Class
			$product_class = get_post_meta( $order_item->get_product()->get_id(), '_sb_product_class', true );
			if ( empty( $product_class ) ) {
				$product_class = 'ProductGroup1';
			}

			$productReference = trim( str_replace( ' ', '-', $order_item->get_product()->get_sku() ) );
			$productName = trim( $order_item->get_name() );

			$items[] = [
				// The field Reference must match the regular expression '[\\w-]*'
				'reference'    => ! empty( $productReference ) ? $productReference : wp_generate_password(),
				'name'         => ! empty( $productName ) ? $productName : '-',
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
			$shippingMethod = trim( $order->get_shipping_method() );

			$items[] = [
				'reference' => 'shipping',
				'name' => ! empty( $shippingMethod ) ? $shippingMethod : __( 'Shipping', 'woocommerce' ),
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

			$items[] = [
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
			$discount        = abs( $order->get_total_discount( true ) );
			$discountWithTax = abs( $order->get_total_discount( false ) );
			$tax             = $discountWithTax - $discount;
			$taxPercent      = ( $tax > 0 ) ? round( 100 / ( $discount / $tax ) ) : 0;

			$items[] = [
				'reference' => 'discount',
				'name' => __( 'Discount', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ),
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

		return $items;
	}

	/**
	 * Get Order VAT
	 *
	 * @param int $vatAmount
	 * @param WC_Order $order
	 *
	 * @return int
	 */
	public static function checkout_order_vat( $vatAmount, $order ) {
		$items = apply_filters( 'swedbank_pay_checkout_order_items', [], $order );

		return array_sum( array_column( $items, 'vatAmount') );
	}

	/**
	 * Get Order Amount
	 *
	 * @param int $amount
	 * @param WC_Order $order
	 *
	 * @return int
	 */
	public static function checkout_order_amount( $amount, $order ) {
		return round( 100 * $order->get_total() );
	}

	/**
	 * Get Consumer Profile
	 *
	 * @param $user_id
	 *
	 * @return array
	 */
	protected function get_consumer_profile( $user_id ) {
		if ( $user_id > 0 ) {
			$expiration = get_user_meta( $user_id, '_payex_profile_expiration', true );
			$reference  = get_user_meta( $user_id, '_payex_consumer_profile', true );
			$url        = get_user_meta( $user_id, '_payex_consumer_url', true );

			// Get saved the consumer address
			$billing  = get_user_meta( $user_id, '_payex_consumer_address_billing', true );
			$shipping = get_user_meta( $user_id, '_payex_consumer_address_shipping', true );
		} else {
			$expiration = WC()->session->get( 'swedbank_pay_consumer_profile_expiration' );
			$reference  = WC()->session->get( 'swedbank_pay_consumer_profile' );
			$url        = WC()->session->get( 'swedbank_pay_consumer_url' );

			// Get saved the consumer address
			$billing  = WC()->session->get( 'swedbank_pay_checkin_billing' );
			$shipping = WC()->session->get( 'swedbank_pay_checkin_shipping' );
		}

		// Check if expired
		if ( ( absint( $expiration ) > 0 && time() >= $expiration ) || // Expired
			 ( ! empty( $reference ) && empty( $expiration ) ) || // Deprecate saved reference without expiration
			 ( ! empty( $reference ) && empty( $url ) ) // Deprecate saved reference without url
		) {
			// Remove expired data
			$this->drop_consumer_profile( $user_id );

			$expiration = null;
			$reference = null;
			$url       = null;
		}

		// Fill the consumer address
		if ( $user_id > 0 ) {
			try {
				$customer = new WC_Customer( $user_id, false );
			} catch (Exception $e) {
				$customer = WC()->customer;
			}
		} else {
			$customer = WC()->customer;
		}

		// Fill the consumer billing address
		if ( empty( $billing ) ) {
			$billing = [
				'first_name' => $customer->get_billing_first_name(),
				'last_name'  => $customer->get_billing_last_name(),
				'postcode'   => $customer->get_billing_postcode(),
				'city'       => $customer->get_billing_city(),
				'email'      => $customer->get_billing_email(),
				'phone'      => $customer->get_billing_phone(),
				'country'    => $customer->get_billing_country(),
				'state'      => $customer->get_billing_state(),
				'address_1'  => $customer->get_billing_address_1(),
				'address_2'  => $customer->get_billing_address_2(),
			];

			if ( $user_id > 0 ) {
				update_user_meta( $user_id, '_payex_consumer_address_billing', $billing );
			}
		}

		// Fill the consumer shipping address
		if ( empty( $shipping ) ) {
			$shipping = [
				'first_name' => $customer->get_shipping_first_name(),
				'last_name'  => $customer->get_shipping_last_name(),
				'postcode'   => $customer->get_shipping_postcode(),
				'city'       => $customer->get_shipping_city(),
				'email'      => $customer->get_billing_email(),
				'phone'      => $customer->get_billing_phone(),
				'country'    => $customer->get_shipping_country(),
				'state'      => $customer->get_shipping_state(),
				'address_1'  => $customer->get_shipping_address_1(),
				'address_2'  => $customer->get_shipping_address_2(),
			];

			if ( $user_id > 0 ) {
				update_user_meta( $user_id, '_payex_consumer_address_shipping', $shipping );
			}
		}

		// Return result
		return [
			'reference' => $reference,
			'url'       => $url,
			'billing'   => $billing,
			'shipping'  => $shipping
		];
	}

	/**
	 * Update Consumer Profile
	 *
	 * @param $user_id
	 * @param $reference
	 * @param $url
	 */
	protected function update_consumer_profile( $user_id, $reference, $url = null ) {
		if ( $user_id > 0 ) {
			update_user_meta( $user_id, '_payex_profile_expiration', strtotime( '+48 hours' ) );
			update_user_meta( $user_id, '_payex_consumer_profile', $reference );
			update_user_meta( $user_id, '_payex_consumer_url', $url );
		} else {
			WC()->session->set( 'swedbank_pay_consumer_profile_expiration', strtotime( '+48 hours' ) );
			WC()->session->set( 'swedbank_pay_consumer_profile', $reference );
			WC()->session->set( 'swedbank_pay_consumer_url', $url );
		}
	}

	/**
	 * Drop Consumer Profile
	 *
	 * @param $user_id
	 */
	protected function drop_consumer_profile( $user_id ) {
		if ( $user_id > 0 ) {
			delete_user_meta( $user_id, '_payex_profile_expiration' );
			delete_user_meta( $user_id, '_payex_consumer_profile' );
			delete_user_meta( $user_id, '_payex_consumer_url' );
		} else {
			WC()->session->__unset( 'swedbank_pay_consumer_profile_expiration' );
			WC()->session->__unset( 'swedbank_pay_consumer_profile' );
			WC()->session->__unset( 'swedbank_pay_consumer_url' );
		}
	}

	/**
	 * Update Consumer Address
	 *
	 * @param $user_id
	 * @param $type
	 * @param $address
	 */
	protected function update_consumer_address( $user_id, $type, $address ) {
		if ( $user_id > 0 ) {
			update_user_meta( $user_id, '_payex_consumer_address_' . $type, $address );
		} else {
			WC()->session->set( 'swedbank_pay_checkin_' . $type, $address );
		}
	}
}

// Register Gateway
WC_Swedbank_Pay::register_gateway( 'WC_Gateway_Swedbank_Pay_Checkout' );
