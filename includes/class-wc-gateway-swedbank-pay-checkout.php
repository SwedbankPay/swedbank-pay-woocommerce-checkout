<?php
defined( 'ABSPATH' ) || exit;

use SwedbankPay\Core\Adapter\WC_Adapter;
use SwedbankPay\Checkout\WooCommerce\WC_Background_Swedbank_Pay_Queue;
use SwedbankPay\Checkout\WooCommerce\WC_Swedbank_Pay_Transactions;
use SwedbankPay\Checkout\WooCommerce\WC_Payment_Token_Swedbank_Pay;
use SwedbankPay\Core\Core;
use SwedbankPay\Core\OrderInterface;
use SwedbankPay\Core\Log\LogLevel;

class WC_Gateway_Swedbank_Pay_Checkout extends WC_Payment_Gateway {
	/**
	 * @var Adapter
	 */
	public $adapter;

	/**
	 * @var Core
	 */
	public $core;

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
	 * Auto Capture
	 * @var string
	 */
	public $auto_capture = 'no';

	/**
	 * Url of Merchant Logo.
	 *
	 * @var string
	 */
	public $logo_url = '';

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
	public $checkin_style = '';

	/**
	 * Send payer info
	 * @var string
	 */
	public $use_payer_info = 'yes';

	/**
	 * Styles of PaymentMenu
	 * @var string
	 */
	public $payment_menu_style = '';

	public $is_new_credit_card;

	public $is_change_credit_card;

	/**
	 * Payment Token Class
	 * @var string
	 */
	public $payment_token_class = '\SwedbankPay\Checkout\WooCommerce\WC_Payment_Token_Swedbank_Pay';

	/**
	 * Init
	 */
	public function __construct() {
		$this->transactions = WC_Swedbank_Pay_Transactions::instance();

		$this->id           = 'payex_checkout';
		$this->has_fields   = true;
		$this->method_title = __( 'Swedbank Pay Checkout', 'swedbank-pay-woocommerce-checkout' );
		//$this->icon         = apply_filters( 'wc_swedbank_pay_checkout_icon', plugins_url( '/assets/images/checkout.gif', dirname( __FILE__ ) ) );
		$this->supports     = array(
			'products',
			'refunds',
			'tokenization',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			//'multiple_subscriptions',
		);

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables
		$this->enabled                = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'no';
		$this->title                  = isset( $this->settings['title'] ) ? $this->settings['title'] : '';
		$this->description            = isset( $this->settings['description'] ) ? $this->settings['description'] : '';
		$this->merchant_token         = isset( $this->settings['merchant_token'] ) ? $this->settings['merchant_token'] : $this->merchant_token;
		$this->payee_id               = isset( $this->settings['payee_id'] ) ? $this->settings['payee_id'] : $this->payee_id;
		$this->subsite                = isset( $this->settings['subsite'] ) ? $this->settings['subsite'] : $this->subsite;
		$this->testmode               = isset( $this->settings['testmode'] ) ? $this->settings['testmode'] : $this->testmode;
		$this->debug                  = isset( $this->settings['debug'] ) ? $this->settings['debug'] : $this->debug;
		$this->culture                = isset( $this->settings['culture'] ) ? $this->settings['culture'] : $this->culture;
		$this->auto_capture           = isset( $this->settings['auto_capture'] ) ? $this->settings['auto_capture'] : $this->auto_capture;
		$this->logo_url               = isset( $this->settings['logo_url'] ) ? $this->settings['logo_url'] : $this->logo_url;
		$this->instant_checkout       = isset( $this->settings['instant_checkout'] ) ? $this->settings['instant_checkout'] : $this->instant_checkout;
		$this->checkin                = isset( $this->settings['checkin'] ) ? $this->settings['checkin'] : $this->checkin;
		$this->checkin_country        = isset( $this->settings['checkin_country'] ) ? $this->settings['checkin_country'] : $this->checkin_country;
		$this->terms_url              = isset( $this->settings['terms_url'] ) ? $this->settings['terms_url'] : get_site_url();
		$this->save_cc                = 'no';
		$this->use_payer_info         = isset( $this->settings['use_payer_info'] ) ? $this->settings['use_payer_info'] : $this->use_payer_info;

		// Reject Cards
		$this->reject_credit_cards    = isset( $this->settings['reject_credit_cards'] ) ? $this->settings['reject_credit_cards'] : $this->reject_credit_cards;
		$this->reject_debit_cards     = isset( $this->settings['reject_debit_cards'] ) ? $this->settings['reject_debit_cards'] : $this->reject_debit_cards;
		$this->reject_consumer_cards  = isset( $this->settings['reject_consumer_cards'] ) ? $this->settings['reject_consumer_cards'] : $this->reject_consumer_cards;
		$this->reject_corporate_cards = isset( $this->settings['reject_corporate_cards'] ) ? $this->settings['reject_corporate_cards'] : $this->reject_corporate_cards;

		// Styles
		$this->custom_styles      = isset( $this->settings['custom_styles'] ) ? $this->settings['custom_styles'] : $this->custom_styles;
		$this->checkin_style      = isset( $this->settings['checkInStyle'] ) ? $this->settings['checkInStyle'] : $this->checkin_style;
		$this->payment_menu_style = isset( $this->settings['paymentMenuStyle'] ) ? $this->settings['paymentMenuStyle'] : $this->payment_menu_style;

		// TermsOfServiceUrl contains unsupported scheme value http in Only https supported.
		if ( ! filter_var( $this->terms_url, FILTER_VALIDATE_URL ) ) {
			$this->terms_url = '';
		} elseif ( 'https' !== parse_url( $this->terms_url, PHP_URL_SCHEME ) ) {
			$this->terms_url = '';
		}

		if ( 'yes' === $this->testmode ) {
			$this->backend_api_endpoint = 'https://api.externalintegration.payex.com';
		}

		// JS Scrips
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

		// Actions
		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array( $this, 'process_admin_options' )
		);
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );

		// Payment listener/API hook
		add_action( 'woocommerce_api_' . strtolower( __CLASS__ ), array( $this, 'return_handler' ) );

		// Payment confirmation
		add_action( 'the_post', array( $this, 'payment_confirm' ) );

		// Ajax Actions
		foreach (
			array(
				'checkout_get_address',
				'checkout_customer_profile',
				'place_order',
				'update_order',
				'checkout_log_error',
			) as $action
		) {
			add_action( 'wp_ajax_swedbank_pay_' . $action, array( $this, 'ajax_swedbank_pay_' . $action ) );
			add_action( 'wp_ajax_nopriv_swedbank_pay_' . $action, array( $this, 'ajax_swedbank_pay_' . $action ) );
		}

		// Check-in
		if ( 'yes' === $this->enabled && 'yes' === $this->checkin ) {
			add_action( 'woocommerce_before_checkout_billing_form', array( $this, 'before_checkout_billing_form' ) );
			add_action( 'wp_ajax_swedbank_pay_checkin', array( $this, 'ajax_swedbank_pay_checkin' ) );
			add_action( 'wp_ajax_nopriv_swedbank_pay_checkin', array( $this, 'ajax_swedbank_pay_checkin' ) );
		}

		// Instant Checkout
		if ( 'yes' === $this->enabled && 'yes' === $this->instant_checkout ) {
			add_action( 'woocommerce_checkout_init', array( $this, 'checkout_init' ), 10, 1 );
			add_action( 'woocommerce_checkout_billing', array( $this, 'checkout_form_billing' ) );

			add_action( 'woocommerce_checkout_order_review', array( $this, 'woocommerce_checkout_payment' ), 20 );
			add_filter( 'wc_get_template', array( $this, 'override_template' ), 5, 20 );
			add_filter( 'woocommerce_available_payment_gateways', array( $this, 'filter_gateways' ), 1 );
			add_filter( 'woocommerce_checkout_fields', array( $this, 'lock_checkout_fields' ), 10, 1 );
			add_filter( 'woocommerce_checkout_get_value', array( $this, 'checkout_get_value' ), 10, 2 );
			add_action( 'woocommerce_before_checkout_form_cart_notices', array( $this, 'init_order' ) );
		}

		// Subscriptions
		add_action( 'woocommerce_payment_complete', array( $this, 'add_subscription_card_id' ), 10, 1 );

		// Update failing payment method
		add_action(
			'woocommerce_subscription_failing_payment_method_updated_' . $this->id,
			array(
				$this,
				'update_failing_payment_method',
			),
			10,
			2
		);

		// Don't transfer customer meta to resubscribe orders
		add_action( 'wcs_resubscribe_order_created', array( $this, 'delete_resubscribe_meta' ), 10 );

		// Allow store managers to manually set card id as the payment method on a subscription
		add_filter( 'woocommerce_subscription_payment_meta', array( $this, 'add_subscription_payment_meta' ), 10, 2 );

		// Validate the payment meta data
		add_action(
			'woocommerce_subscription_validate_payment_meta',
			array(
				$this,
				'validate_subscription_payment_meta',
			),
			10,
			3
		);

		// Save payment method meta data for the Subscription
		add_action( 'wcs_save_other_payment_meta', array( $this, 'save_subscription_payment_meta' ), 10, 4 );

		// Charge the payment when a subscription payment is due
		add_action(
			'woocommerce_scheduled_subscription_payment_' . $this->id,
			array(
				$this,
				'scheduled_subscription_payment',
			),
			10,
			2
		);

		// Display the credit card used for a subscription in the "My Subscriptions" table
		add_filter(
			'woocommerce_my_subscriptions_payment_method',
			array(
				$this,
				'maybe_render_subscription_payment_method',
			),
			10,
			2
		);

		// Lock "Save card" if needs
		add_filter(
			'woocommerce_payment_gateway_save_new_payment_method_option_html',
			array(
				$this,
				'save_new_payment_method_option_html',
			),
			10,
			2
		);

		add_action( 'wp_ajax_swedbank_card_store', array( $this, 'swedbank_card_store' ) );
		add_action( 'wp_ajax_nopriv_swedbank_card_store', array( $this, 'swedbank_card_store' ) );

		$this->adapter = new WC_Adapter( $this );
		$this->core    = new Core( $this->adapter );
	}

	/**
	 * Initialise Settings Form Fields
	 * @return string|void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'                => array(
				'title'   => __( 'Enable/Disable', 'swedbank-pay-woocommerce-checkout' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable plugin', 'swedbank-pay-woocommerce-checkout' ),
				'default' => 'no',
			),
			'title'                  => array(
				'title'       => __( 'Title', 'swedbank-pay-woocommerce-checkout' ),
				'type'        => 'text',
				'description' => __(
					'This controls the title which the user sees during checkout.',
					'swedbank-pay-woocommerce-checkout'
				),
				'default'     => __( 'Swedbank Pay Checkout', 'swedbank-pay-woocommerce-checkout' ),
			),
			'description'            => array(
				'title'       => __( 'Description', 'swedbank-pay-woocommerce-checkout' ),
				'type'        => 'text',
				'description' => __(
					'This controls the description which the user sees during checkout.',
					'swedbank-pay-woocommerce-checkout'
				),
				'default'     => __( 'Swedbank Pay Checkout', 'swedbank-pay-woocommerce-checkout' ),
			),
			'merchant_token'         => array(
				'title'       => __( 'Merchant Token', 'swedbank-pay-woocommerce-checkout' ),
				'type'        => 'text',
				'description' => __( 'Merchant Token', 'swedbank-pay-woocommerce-checkout' ),
				'default'     => $this->merchant_token,
				'custom_attributes' => array(
					'required' => 'required'
				),
				'sanitize_callback' => function( $value ) {
					if ( empty( $value ) ) {
						throw new Exception( __( '"Merchant Token" field can\'t be empty.', 'swedbank-pay-woocommerce-payments' ) );
					}

					return $value;
				},
			),
			'payee_id'               => array(
				'title'       => __( 'Payee Id', 'swedbank-pay-woocommerce-checkout' ),
				'type'        => 'text',
				'description' => __( 'Payee Id', 'swedbank-pay-woocommerce-checkout' ),
				'default'     => $this->payee_id,
				'custom_attributes' => array(
					'required' => 'required'
				),
				'sanitize_callback' => function( $value ) {
					if ( empty( $value ) ) {
						throw new Exception( __( '"Payee Id" field can\'t be empty.', 'swedbank-pay-woocommerce-payments' ) );
					}

					return $value;
				},
			),
			'subsite'                => array(
				'title'       => __( 'Subsite', 'swedbank-pay-woocommerce-checkout' ),
				'type'        => 'text',
				'description' => __( 'Subsite', 'swedbank-pay-woocommerce-checkout' ),
				'default'     => $this->subsite,
			),
			'testmode'               => array(
				'title'   => __( 'Test Mode', 'swedbank-pay-woocommerce-checkout' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Swedbank Pay Test Mode', 'swedbank-pay-woocommerce-checkout' ),
				'default' => $this->testmode,
			),
			'debug'                  => array(
				'title'   => __( 'Debug', 'swedbank-pay-woocommerce-checkout' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable logging', 'swedbank-pay-woocommerce-checkout' ),
				'default' => $this->debug,
			),
			'culture'                => array(
				'title'       => __( 'Language', 'swedbank-pay-woocommerce-checkout' ),
				'type'        => 'select',
				'options'     => array(
					'en-US' => 'English',
					'sv-SE' => 'Swedish',
					'nb-NO' => 'Norway',
				),
				'description' => __(
					'Language of pages displayed by Swedbank Pay during payment.',
					'swedbank-pay-woocommerce-checkout'
				),
				'default'     => $this->culture,
			),
			'auto_capture'           => array(
				'title'   => __( 'Auto Capture', 'swedbank-pay-woocommerce-checkout' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Auto Capture', 'swedbank-pay-woocommerce-checkout' ),
				'default' => $this->auto_capture,
			),
			'instant_checkout'       => array(
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
			),
			'checkin'                => array(
				'title'   => __( 'Enable Checkin on Swedbank Pay Checkout', 'swedbank-pay-woocommerce-checkout' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Checkin on Swedbank Pay Checkout', 'swedbank-pay-woocommerce-checkout' ),
				'default' => $this->checkin,
			),
			'checkin_country'        => array(
				'title'       => __( 'Checkin country', 'swedbank-pay-woocommerce-checkout' ),
				'type'        => 'select',
				'options'     => array(
					'SE'     => __( 'Sweden', 'woocommerce' ),
					'NO'     => __( 'Norway', 'woocommerce' ),
					'SELECT' => __( 'Customer can choose', 'swedbank-pay-woocommerce-checkout' ),
				),
				'description' => __( 'Checkin country', 'swedbank-pay-woocommerce-checkout' ),
				'default'     => $this->checkin_country,
			),
			'terms_url'              => array(
				'title'       => __( 'Terms & Conditions Url', 'swedbank-pay-woocommerce-checkout' ),
				'type'        => 'text',
				'description' => __( 'Terms & Conditions Url', 'swedbank-pay-woocommerce-checkout' ),
				'default'     => get_site_url(),
			),
			'logo_url'              => array(
				'title'       => __( 'Logo Url', 'swedbank-pay-woocommerce-checkout' ),
				'type'        => 'text',
				'description' => __( 'The URL that will be used for showing the customer logo. Must be a picture with maximum 50px height and 400px width. Require https.', 'swedbank-pay-woocommerce-checkout' ),
				'desc_tip'    => true,
				'default'     => '',
				'sanitize_callback' => function( $value ) {
					if ( ! empty( $value ) ) {
						if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
							throw new Exception( __( 'Logo Url is invalid.', 'swedbank-pay-woocommerce-checkout' ) );
						} elseif ( 'https' !== parse_url( $value, PHP_URL_SCHEME ) ) {
							throw new Exception( __( 'Logo Url should use https scheme.', 'swedbank-pay-woocommerce-checkout' ) );
						}
					}

					return $value;
				},
			),
			'use_payer_info'        => array(
				'title'   => __( 'Send payer information', 'swedbank-pay-woocommerce-checkout' ),
				'type'    => 'checkbox',
				'label'   => __( 'Send billing/delivery addresses of payer to Swedbank Pay', 'swedbank-pay-woocommerce-checkout' ),
				'default' => $this->use_payer_info
			),
			'reject_credit_cards'    => array(
				'title'   => __( 'Reject Credit Cards', 'swedbank-pay-woocommerce-checkout' ),
				'type'    => 'checkbox',
				'label'   => __( 'Reject Credit Cards', 'swedbank-pay-woocommerce-checkout' ),
				'default' => $this->reject_credit_cards,
			),
			'reject_debit_cards'     => array(
				'title'   => __( 'Reject Debit Cards', 'swedbank-pay-woocommerce-checkout' ),
				'type'    => 'checkbox',
				'label'   => __( 'Reject Debit Cards', 'swedbank-pay-woocommerce-checkout' ),
				'default' => $this->reject_debit_cards,
			),
			'reject_consumer_cards'  => array(
				'title'   => __( 'Reject Consumer Cards', 'swedbank-pay-woocommerce-checkout' ),
				'type'    => 'checkbox',
				'label'   => __( 'Reject Consumer Cards', 'swedbank-pay-woocommerce-checkout' ),
				'default' => $this->reject_consumer_cards,
			),
			'reject_corporate_cards' => array(
				'title'   => __( 'Reject Corporate Cards', 'swedbank-pay-woocommerce-checkout' ),
				'type'    => 'checkbox',
				'label'   => __( 'Reject Corporate Cards', 'swedbank-pay-woocommerce-checkout' ),
				'default' => $this->reject_corporate_cards,
			),
		);
	}

	/**
	 * Output the gateway settings screen.
	 *
	 * @return void
	 */
	public function admin_options() {
		$this->display_errors();

		parent::admin_options();
	}

	/**
	 * payment_scripts function.
	 *
	 * Outputs scripts used for payment
	 *
	 * @return void
	 */
	public function payment_scripts() {
		if ( 'no' === $this->enabled ) {
			return;
		}

		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() && ! is_product() ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_style(
			'swedbank-pay-checkout-css',
			untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/css/style' . $suffix . '.css',
			array(),
			false,
			'all'
		);

		// Checkout scripts
		if ( is_checkout() || isset( $_GET['pay_for_order'] ) || is_add_payment_method_page() ) {
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

			wp_register_script(
				'wc-sb-checkin',
				untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/js/checkin' . $suffix . '.js',
				array(
					'wc-sb-common',
				),
				false,
				true
			);


			if ( 'yes' === $this->instant_checkout ) {
				// Instant Checkout
				wp_enqueue_style(
					'swedbank-pay-checkout-instant',
					untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/css/instant-checkout' . $suffix . '.css',
					array(),
					false,
					'all'
				);

				wp_register_script(
					'wc-gateway-swedbank-pay-checkout',
					untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/js/instant-checkout' . $suffix . '.js',
					array(
						'jquery',
						'wc-checkout',
						'wc-sb-common',
						'wc-sb-checkin'
					),
					false,
					true
				);
			} else {
				// Non-Instant Checkout
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

				wp_register_script(
					'wc-gateway-swedbank-pay-checkout',
					untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/js/instant-checkout' . $suffix . '.js',
					array(
						'jquery',
						'wc-checkout',
						'wc-sb-common',
						'wc-sb-checkin',
						'featherlight',
					),
					false,
					true
				);
			}

			// Localize the script with new data
			$translation_array = array(
				'culture'                      => $this->culture,
				'instant_checkout'             => ( 'yes' === $this->instant_checkout ),
				'needs_shipping_address'       => WC()->cart->needs_shipping(),
				'ship_to_billing_address_only' => wc_ship_to_billing_address_only(),
				'checkin'                      => ( 'yes' === $this->checkin ),
				'nonce'                        => wp_create_nonce( 'swedbank_pay_checkout' ),
				'ajax_url'                     => admin_url( 'admin-ajax.php' ),
				'paymentMenuStyle'             => null,
				'checkInStyle'                 => null,
			);

			// Add PM styles
			$styles = apply_filters( 'swedbank_pay_checkout_paymentmenu_style', $this->payment_menu_style );
			if ( $styles ) {
				$translation_array['paymentMenuStyle'] = $styles;
			}

			// Add CheckIn Styles
			$styles = apply_filters( 'swedbank_pay_checkout_checkin_style', $this->checkin_style );
			if ( $styles ) {
				$translation_array['checkInStyle'] = $styles;
			}

			wp_localize_script(
				'wc-sb-common',
				'WC_Gateway_Swedbank_Pay_Checkout',
				$translation_array
			);

			// Enqueued script with localized data.
			wp_enqueue_script( 'wc-sb-common' );
			wp_enqueue_script( 'wc-sb-checkin' );
			wp_enqueue_script( 'wc-gateway-swedbank-pay-checkout' );
		}
	}


	/**
	 * If There are no payment fields show the description if set.
	 */
	public function payment_fields() {
		parent::payment_fields();

		if ( ! is_add_payment_method_page() )  {
			$this->tokenization_script();
			$this->saved_payment_methods();
			$this->save_payment_method_checkbox();
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
		WC()->session->__unset( 'payex_paymentorder_id' );
	}

	/**
	 * Add Payment Method
	 * @return array
	 */
	public function add_payment_method() {
		$user_id = get_current_user_id();

		// Create a virtual order
		$order = wc_create_order(
			array(
				'customer_id'    => $user_id,
				'created_via'    => $this->id,
				'payment_method' => $this->id,
			)
		);
		$order->calculate_totals();

		try {
			// Initiate new payment card
			$this->is_new_credit_card = true;
			$result                      = $this->core->initiatePaymentOrderVerify( $order->get_id() );

			$order->update_meta_data( '_payex_generate_token', '1' );

			// Save payment ID
			$order->update_meta_data( '_payex_paymentorder_id', $result['paymentOrder']['id'] );
			$order->save_meta_data();

			// Redirect
			$order->add_order_note(
				__(
					'Customer has been redirected to Swedbank Pay.',
					'swedbank-pay-woocommerce-checkout'
				)
			);

		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );

			WC()->session->__unset( 'verification_payment_order_id' );

			return array(
				'result'   => 'failure',
				'redirect' => wc_get_account_endpoint_url( 'payment-methods' ),
			);
		}

		WC()->session->set( 'verification_payment_order_id', $result['paymentOrder']['id'] );

		// Redirect
		wp_redirect( $result->getOperationByRel( 'redirect-paymentorder' ) );
		exit();
	}


	/**
	 * Add Payment Method: Callback for Swedbank Pay Card
	 * @return void
	 */
	public function swedbank_card_store() {
		$payment_order_id = WC()->session->get( 'verification_payment_order_id' );

		try {
			if ( ! $payment_order_id ) {
				return;
			}

			$payment_id = $this->core->getPaymentIdByPaymentOrder( $payment_order_id );
			if ( ! $payment_id ) {
				throw new Exception( __( 'There was a problem adding the card.', 'swedbank-pay-woocommerce-payments' ) );
			}

			$verifications = $this->core->fetchVerificationList( $payment_id );
			foreach ( $verifications as $verification ) {
				$expiry_date = explode( '/', $verification->getExpireDate() );

				// Create Payment Token
				$token = new WC_Payment_Token_Swedbank_Pay();
				$token->set_gateway_id( $this->id );
				$token->set_token( $verification->getPaymentToken() );
				$token->set_recurrence_token( $verification->getRecurrenceToken() );
				$token->set_last4( substr( $verification->getMaskedPan(), - 4 ) );
				$token->set_expiry_year( $expiry_date[1] );
				$token->set_expiry_month( $expiry_date[0] );
				$token->set_card_type( strtolower( $verification->getCardBrand() ) );
				$token->set_user_id( get_current_user_id() );
				$token->set_masked_pan( $verification->getMaskedPan() );

				// Save Credit Card
				$token->save();

				if ( ! $token->get_id() ) {
					throw new Exception( __( 'There was a problem adding the card.', 'swedbank-pay-woocommerce-checkout' ) );
				}

				WC()->session->__unset( 'verification_payment_order_id' );

				wc_add_notice( __( 'Payment method successfully added.', 'swedbank-pay-woocommerce-checkout' ) );
				wp_redirect( wc_get_account_endpoint_url( 'payment-methods' ) );
				exit();
			}

			throw new Exception( __( 'There was a problem adding the card.', 'swedbank-pay-woocommerce-checkout' ) );
		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
			wp_redirect( wc_get_account_endpoint_url( 'add-payment-method' ) );
			exit();
		}
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
		$token_id        = isset( $_POST[ 'wc-' . $this->id . '-payment-token' ] ) ? $_POST[ 'wc-' . $this->id . '-payment-token' ] : null;
		$maybe_save_card = isset( $_POST['wc-' . $this->id . '-new-payment-method'] ) && (bool) $_POST['wc-' . $this->id . '-new-payment-method'];

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

			$result     = $this->core->fetchPaymentInfo( $payment_id );
			$js_url     = $result->getOperationByRel( 'view-paymentorder' );
			$update_url = $result->getOperationByRel( 'update-paymentorder-updateorder' );
			if ( empty( $update_url ) ) {
				throw new Exception( 'Order update is not available.' );
			}

			// Don't update if amount is not changed
			if ( round( $order->get_total() * 100 ) === (int) $result['paymentOrder']['amount'] ) {
				return array(
					'result'                   => 'success',
					'redirect'                 => '#!swedbank-pay-checkout',
					'is_swedbank_pay_checkout' => true,
					'js_url'                   => $js_url,
					'payment_id'               => $result['paymentOrder']['id'],
				);
			}

			// Update Order
			$result = $this->core->updatePaymentOrder( $update_url, $order->get_id() );

			return array(
				'result'                   => 'success',
				'redirect'                 => '#!swedbank-pay-checkout',
				'is_swedbank_pay_checkout' => true,
				'js_url'                   => $result->getOperationByRel( 'view-paymentorder' ),
				'payment_id'               => $result['paymentOrder']['id'],
			);
		}

		// Mode that workaround "Order update is not available"
		if ( isset( $_POST['is_update_backward_compat'] ) ) {
			$order->calculate_totals( true );

			// Delete Payment Order ID
			delete_post_meta( $order_id, '_payex_paymentorder_id' );
		}

		// Change a payment method
		// or process orders that have zero amount
		if ( (float) $order->get_total() < 0.01 || self::wcs_is_payment_change() ) {
			if ( absint( $token_id ) > 0 ) {
				// Replace the token to another saved before
				$token = new WC_Payment_Token_Swedbank_Pay( $token_id );
				if ( ! $token->get_id() ) {
					throw new Exception( 'Failed to load token.' );
				}

				// Check access
				if ( $token->get_user_id() !== $order->get_user_id() ) {
					throw new Exception( 'Access denied.' );
				}

				// Replace token
				delete_post_meta( $order->get_id(), '_payment_tokens' );
				$order->add_payment_token( $token );

				if ( self::wcs_is_payment_change() ) {
					wc_add_notice( __( 'Payment method was updated.', 'swedbank-pay-woocommerce-checkout' ) );
				}

				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				);
			} else {
				// Initiate new payment card
				$this->is_change_credit_card = true;
				$result                      = $this->core->initiatePaymentOrderVerify( $order->get_id() );

				$order->update_meta_data( '_payex_generate_token', '1' );
				$order->update_meta_data( '_payex_replace_token', '1' );

				// Save payment ID
				$order->update_meta_data( '_payex_paymentorder_id', $result['paymentOrder']['id'] );
				$order->update_meta_data( '_payex_payment_id', $result['payment']['id'] );
				$order->save_meta_data();

				// Redirect
				$order->add_order_note(
					__(
						'Customer has been redirected to Swedbank Pay.',
						'swedbank-pay-woocommerce-checkout'
					)
				);

				return array(
					'result'   => 'success',
					'redirect' => $result->getOperationByRel( 'redirect-paymentorder' ),
				);
			}
		}

		// Process the saved payment method if "Instant Checkout" is disabled
		if ( $token_id && 'no' === $this->instant_checkout ) {
			if ( absint( $token_id ) > 0 ) {
				$token = new WC_Payment_Token_Swedbank_Pay( $token_id );
				if ( ! $token->get_id() ) {
					throw new Exception( 'Failed to load token.' );
				}

				// Check access
				if ( $token->get_user_id() !== $order->get_user_id() ) {
					throw new Exception( 'Access denied.' );
				}

				// Charge the payment
				try {
					$this->process_recurring_payment( $order, $token->get_recurrence_token() );
				} catch ( \SwedbankPay\Core\Exception $e ) {
					throw new Exception( $e->getMessage() );
				}

				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				);
			} elseif ( (float) $order->get_total() < 0.01 || self::wcs_is_payment_change() ) {
				// Initiate new payment card
				$this->is_change_credit_card = true;
				$result                      = $this->core->initiatePaymentOrderVerify( $order->get_id() );

				if ( $maybe_save_card ) {
					$order->update_meta_data( '_payex_generate_token', '1' );
				}

				$order->update_meta_data( '_payex_replace_token', '1' );

				// Save payment ID
				$order->update_meta_data( '_payex_paymentorder_id', $result['paymentOrder']['id'] );
				$order->update_meta_data( '_payex_payment_id', $result['payment']['id'] );
				$order->save_meta_data();

				// Redirect
				$order->add_order_note(
					__(
						'Customer has been redirected to Swedbank Pay.',
						'swedbank-pay-woocommerce-checkout'
					)
				);

				return array(
					'result'   => 'success',
					'redirect' => $result->getOperationByRel( 'redirect-paymentorder' ),
				);
			} else {
				if ( self::order_contains_subscription( $order ) ) {
					$generate_token = true;

					// Flag that allows save token
					$order->update_meta_data( '_payex_generate_token', '1' );
					$order->save();
				} else {
					$generate_token = false;

					$order->delete_meta_data( '_payex_generate_token' );
					$order->save();
				}

				$result = $this->core->initiatePaymentOrderPurchase( $order_id, null, $generate_token );

				// Save payment ID
				$order->update_meta_data( '_payex_paymentorder_id', $result['paymentOrder']['id'] );
				$order->save_meta_data();

				return array(
					'result'   => 'success',
					'redirect' => $result->getOperationByRel( 'redirect-paymentorder' ),
				);
			}
		}

		// Get Consumer Profile
		$reference = isset( $_POST['swedbank_pay_customer_reference'] ) ? wc_clean( $_POST['swedbank_pay_customer_reference'] ) : null;
		if ( empty( $reference ) ) {
			$profile   = $this->get_consumer_profile( $order->get_user_id() );
			$reference = $profile['reference'];
		}

		// Generate a token if there's a subscription
		if ( self::order_contains_subscription( $order ) ) {
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
			$result = $this->core->initiatePaymentOrderPurchase( $order_id, $reference, $generate_token );
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
					$this->drop_consumer_profile( $order->get_user_id() );

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
					return $this->process_payment( $order_id );
				}
			}

			wc_add_notice( $e->getMessage(), 'error' );

			return false;
		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );

			return false;
		}

		// Save PaymentOrder ID
		update_post_meta( $order_id, '_payex_paymentorder_id', $result['paymentOrder']['id'] );
		WC()->session->set( 'swedbank_paymentorder_id', $result['paymentOrder']['id'] );

		// Get JS Url
		$js_url = $result->getOperationByRel( 'view-paymentorder' );

		// Save JS Url in session
		WC()->session->set( 'swedbank_pay_checkout_js_url', $js_url );

		return array(
			'result'                   => 'success',
			'redirect'                 => '#!swedbank-pay-checkout',
			'is_swedbank_pay_checkout' => true,
			'js_url'                   => $js_url,
			'payment_id'               => $result['paymentOrder']['id'],
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

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		if ( $order->get_payment_method() !== $this->id ) {
			return;
		}

		WC()->session->__unset( 'swedbank_paymentorder_id' );

		// Check payments list and extract Payment ID
		$payment_order = $order->get_meta( '_payex_paymentorder_id', true );

		// Fetch payment order info
		if ( empty( $payment_order ) ) {
			return;
		}

		$this->adapter->log( LogLevel::INFO, __METHOD__ );

		try {
			$result = $this->core->fetchPaymentInfo( $payment_order, 'currentPayment,payeeInfo' );
		} catch ( Exception $e ) {
			return;
		}

		// Update address
		try {
			$this->update_address( $order_id );
		} catch ( \Exception $e ) {
			// Ignore errors
		}

		if ( isset( $result['paymentOrder']['currentPayment'] ) &&
		     isset( $result['paymentOrder']['currentPayment']['payment']['id'] )
		) {
			$payment_id = $result['paymentOrder']['currentPayment']['payment']['id'];

			// Save Payment Id
			$order->add_meta_data( '_payex_payment_id', $payment_id );
			$order->save_meta_data();

			// Check payment state
			switch ( $result['paymentOrder']['currentPayment']['payment']['state'] ) {
				case 'Ready':
					// Replace token for:
					// Change Payment Method
					// Orders with Zero Amount
					if ( '1' === $order->get_meta( '_payex_replace_token' ) ) {
						// Save token on Verify operation
						if ( 'Verify' === $result['paymentOrder']['operation'] ) {
							$verifications = $this->core->fetchVerificationList( $payment_id );
							foreach ( $verifications as $verification ) {
								$expiry_date = explode( '/', $verification->getExpireDate() );

								// Create Payment Token
								$token = new WC_Payment_Token_Swedbank_Pay();
								$token->set_gateway_id( $this->id );
								$token->set_token( $verification->getPaymentToken() );
								$token->set_recurrence_token( $verification->getRecurrenceToken() );
								$token->set_last4( substr( $verification->getMaskedPan(), - 4 ) );
								$token->set_expiry_year( $expiry_date[1] );
								$token->set_expiry_month( $expiry_date[0] );
								$token->set_card_type( strtolower( $verification->getCardBrand() ) );
								$token->set_user_id( get_current_user_id() );
								$token->set_masked_pan( $verification->getMaskedPan() );

								// Save Credit Card
								$token->save();

								// Replace token
								delete_post_meta( $order->get_id(), '_payex_replace_token' );
								delete_post_meta( $order->get_id(), '_payment_tokens' );
								$order->add_payment_token( $token );

								wc_add_notice( __( 'Payment method was updated.', 'swedbank-pay-woocommerce-checkout' ) );

								// first only
								break;
							}
						} else {
							// For Authorization operation
							$authorizations = $this->core->fetchAuthorizationList( $payment_id  );
							foreach ( $authorizations as $authorization ) {
								$expiry_date = explode( '/', $authorization->getExpireDate() );

								// Create Payment Token
								$token = new WC_Payment_Token_Swedbank_Pay();
								$token->set_gateway_id( $this->id );
								$token->set_token( $authorization->getPaymentToken() );
								$token->set_recurrence_token( $authorization->getRecurrenceToken() );
								$token->set_last4( substr( $authorization->getMaskedPan(), - 4 ) );
								$token->set_expiry_year( $expiry_date[1] );
								$token->set_expiry_month( $expiry_date[0] );
								$token->set_card_type( strtolower( $authorization->getCardBrand() ) );
								$token->set_user_id( get_current_user_id() );
								$token->set_masked_pan( $authorization->getMaskedPan() );

								// Save Credit Card
								$token->save();

								// Replace token
								delete_post_meta( $order->get_id(), '_payex_replace_token' );
								delete_post_meta( $order->get_id(), '_payment_tokens' );
								$order->add_payment_token( $token );

								wc_add_notice( __( 'Payment method was updated.', 'swedbank-pay-woocommerce-checkout' ) );

								// first only
								break;
							}
						}
					}

					return;
				case 'Failed':
					$this->core->updateOrderStatus(
						OrderInterface::STATUS_FAILED,
						__( 'Payment failed.', 'swedbank-pay-woocommerce-checkout' )
					);

					return;
				case 'Aborted':
					$this->core->updateOrderStatus(
						OrderInterface::STATUS_CANCELLED,
						__( 'Payment canceled.', 'swedbank-pay-woocommerce-checkout' )
					);

					return;
				default:
					// Payment state is ok
			}
		}
	}

	/**
	 * IPN Callback
	 * @return void
	 * @throws \Exception
	 */
	public function return_handler() {
		$raw_body = file_get_contents( 'php://input' );

		$this->adapter->log(
			LogLevel::INFO,
			sprintf(
				'Incoming Callback: Initialized %s from %s',
				$_SERVER['REQUEST_URI'],
				$_SERVER['REMOTE_ADDR']
			)
		);
		$this->adapter->log(
			LogLevel::INFO,
			sprintf( 'Incoming Callback. Post data: %s', var_export( $raw_body, true ) )
		);

		// Decode raw body
		$data = json_decode( $raw_body, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			throw new Exception( 'Invalid webhook data' );
		}

		try {
			// Verify the order key
			$order_id  = absint(  wc_clean( $_GET['order_id'] ) ); // WPCS: input var ok, CSRF ok.
			$order_key = empty( $_GET['key'] ) ? '' : wc_clean( wp_unslash( $_GET['key'] ) ); // WPCS: input var ok, CSRF ok.

			if ( empty( $order_id ) || empty( $order_id ) ) {
				throw new Exception( 'An order ID or order key wasn\'t provided' );
			}

			$order = wc_get_order( $order_id );
			if ( ! $order || ! hash_equals( $order->get_order_key(), $order_key ) ) {
				throw new Exception( 'A provided order key has been invalid.' );
			}

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

			// Get Order by Order Payment Id
			$order_id = $this->get_post_id_by_meta( '_payex_paymentorder_id', $paymentorder_id );

			// Get Order ID from payeeInfo if is not exist
			if ( empty( $order_id ) ) {
				$result   = $this->core->fetchPaymentInfo( $paymentorder_id, 'payeeInfo' );
				$order_id = $result['paymentOrder']['payeeInfo']['orderReference'];

				if ( empty( $order_id ) ) {
					throw new \Exception(
						sprintf(
							'Error: Failed to get order Id by Payment Order Id %s',
							$paymentorder_id
						)
					);
				}

				// Save Order Payment Id
				$order->update_meta_data( '_payex_paymentorder_id', $paymentorder_id );
				$order->save_meta_data();
			}

			// Save Payment ID
			if ( isset( $data['payment'] ) ) {
				$payment_id = $data['payment']['id'];

				$order->update_meta_data( '_payex_payment_id', $payment_id );
				$order->save_meta_data();
			}

			// Update address
			try {
				$this->update_address( $order_id );
			} catch ( \Exception $e ) {
				// Ignore errors
			}

			// Create Background Process Task
			$background_process = new WC_Background_Swedbank_Pay_Queue();
			$background_process->push_to_queue(
				array(
					'payment_method_id' => $this->id,
					'webhook_data'      => $raw_body,
				)
			);
			$background_process->save();

			$this->adapter->log(
				LogLevel::INFO,
				sprintf( 'Incoming Callback: Task enqueued. Transaction ID: %s', $data['transaction']['number'] )
			);
		} catch ( \Exception $e ) {
			$this->adapter->log( LogLevel::INFO, sprintf( 'Incoming Callback: %s', $e->getMessage() ) );

			return;
		}
	}

	/**
	 * Process Recurring Payment.
	 *
	 * @param WC_Order $order
	 * @param string $token
	 *
	 * @return \SwedbankPay\Core\Api\Response
	 * @throws \SwedbankPay\Core\Exception
	 */
	public function process_recurring_payment( $order, $token ) {
		$result = $this->core->initiatePaymentOrderRecur(
			$order->get_id(),
			$token
		);

		if ( $result['paymentOrder']['state'] !== 'Ready' ) {
			throw new Exception( 'Payment have been failed. Wrong state.' );
		}

		// Save payment Order ID
		$paymentOrderId = $result['paymentOrder']['id'];
		$order->update_meta_data( '_payex_paymentorder_id', $paymentOrderId );

		// Fetch payment id
		$payment_id = $this->core->getPaymentIdByPaymentOrder( $paymentOrderId );

		// Save payment ID
		$order->update_meta_data( '_payex_payment_id', $result['payment']['id'] );
		$order->save_meta_data();

		// Fetch transactions list
		$transactions = $this->core->fetchTransactionsList( $payment_id );
		$this->core->saveTransactions( $order->get_id(), $transactions );

		// Process transactions list
		foreach ( $transactions as $transaction ) {
			// Process transaction
			try {
				// Disable status change hook
				remove_action(
					'woocommerce_order_status_changed',
					'\SwedbankPay\Payments\WooCommerce\WC_Swedbank_Plugin::order_status_changed',
					10
				);
				remove_action(
					'woocommerce_order_status_changed',
					'\SwedbankPay\Checkout\WooCommerce\WC_Swedbank_Plugin::order_status_changed',
					10
				);

				$this->core->processTransaction( $order->get_id(), $transaction );

				// Enable status change hook
				add_action(
					'woocommerce_order_status_changed',
					'\SwedbankPay\Payments\WooCommerce\WC_Swedbank_Plugin::order_status_changed',
					10
				);
				add_action(
					'woocommerce_order_status_changed',
					'\SwedbankPay\Checkout\WooCommerce\WC_Swedbank_Plugin::order_status_changed',
					10
				);
			} catch ( \Exception $e ) {
				$this->adapter->log(
					LogLevel::INFO,
					sprintf( '[WC_Subscriptions]: Warning: %s', $e->getMessage() )
				);

				// Enable status change hook
				add_action(
					'woocommerce_order_status_changed',
					'\SwedbankPay\Payments\WooCommerce\WC_Swedbank_Plugin::order_status_changed',
					10
				);
				add_action(
					'woocommerce_order_status_changed',
					'\SwedbankPay\Checkout\WooCommerce\WC_Swedbank_Plugin::order_status_changed',
					10
				);
			}
		}

		return $result;
	}

	/**
	 * Process Refund
	 *
	 * If the gateway declares 'refunds' support, this will allow it to refund
	 * a passed in amount.
	 *
	 * @param int $order_id
	 * @param float $amount
	 * @param string $reason
	 *
	 * @return  bool|wp_error True or false based on success, or a WP_Error object
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		// Full Refund
		if ( is_null( $amount ) ) {
			$amount = $order->get_total();
		}

		try {
			// Disable status change hook
			remove_action(
				'woocommerce_order_status_changed',
				'\SwedbankPay\Payments\WooCommerce\WC_Swedbank_Plugin::order_status_changed',
				10
			);
			remove_action(
				'woocommerce_order_status_changed',
				'\SwedbankPay\Checkout\WooCommerce\WC_Swedbank_Plugin::order_status_changed',
				10
			);

			$this->core->refundCheckout( $order->get_id(), $amount, 0 );

			return true;
		} catch ( \Exception $e ) {
			return new WP_Error( 'refund', $e->getMessage() );
		}
	}

	/**
	 * Capture
	 *
	 * @param WC_Order|int $order
	 * @param mixed $amount
	 * @param mixed $vat_amount
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function capture_payment( $order, $amount = false, $vat_amount = 0 ) {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		try {
			// Disable status change hook
			remove_action(
				'woocommerce_order_status_changed',
				'\SwedbankPay\Payments\WooCommerce\WC_Swedbank_Plugin::order_status_changed',
				10
			);
			remove_action(
				'woocommerce_order_status_changed',
				'\SwedbankPay\Checkout\WooCommerce\WC_Swedbank_Plugin::order_status_changed',
				10
			);

			$order_data = $this->adapter->getOrderData( $order->get_id() );
			$this->core->captureCheckout( $order->get_id(), $amount, $vat_amount, $order_data[ OrderInterface::ITEMS ] );
		} catch ( \SwedbankPay\Core\Exception $e ) {
			throw new Exception( $e->getMessage() );
		}
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

		try {
			// Disable status change hook
			remove_action(
				'woocommerce_order_status_changed',
				'\SwedbankPay\Payments\WooCommerce\WC_Swedbank_Plugin::order_status_changed',
				10
			);
			remove_action(
				'woocommerce_order_status_changed',
				'\SwedbankPay\Checkout\WooCommerce\WC_Swedbank_Plugin::order_status_changed',
				10
			);

			$this->core->cancelCheckout( $order->get_id() );
		} catch ( \SwedbankPay\Core\Exception $e ) {
			throw new Exception( $e->getMessage() );
		}
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
			$result = $this->core->request( 'GET', $paymentorder_id . '/payers' );

			if ( ! isset( $result['payer'] ) ) {
				return;
			}

			// Parse name field
			try {
				$parser = new \ADCI\FullNameParser\Parser();
				$name   = $parser->parse( $result['payer']['shippingAddress']['addressee'] );
			} catch ( Exception $e ) {
				// Ignore errors from the parser
				$this->adapter->log( LogLevel::INFO, 'Nameparser error: ' . $e->getMessage() );

				return;
			}

			$co     = ! empty( $result['payer']['shippingAddress']['coAddress'] ) ? 'c/o ' . $result['payer']['shippingAddress']['coAddress'] : '';

			$address = array(
				'first_name' => $name->getFirstName(),
				'last_name'  => trim( $name->getMiddleName() . ' ' . $name->getLastName() ),
				'company'    => '',
				'email'      => $result['payer']['email'],
				'phone'      => $result['payer']['msisdn'],
				'address_1'  => $result['payer']['shippingAddress']['streetAddress'],
				'address_2'  => $co,
				'city'       => $result['payer']['shippingAddress']['city'],
				'state'      => '',
				'postcode'   => $result['payer']['shippingAddress']['zipCode'],
				'country'    => $result['payer']['shippingAddress']['countryCode'],
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
			$order_id = WC()->checkout()->create_order(
				array(
					'payment_method'      => $this->id,
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
		$profile = $this->get_consumer_profile( get_current_user_id() );

		// Initiate Payment Order
		$_POST['swedbank_pay_customer_reference'] = $profile['reference'];
		$result                                   = $this->process_payment( $order_id );
		if ( is_array( $result ) && isset( $result['js_url'] ) ) {
			WC()->session->set( 'swedbank_pay_checkout_js_url', $result['js_url'] );
		} else {
			WC()->session->__unset( 'swedbank_pay_checkout_js_url' );
		}
	}

	/**
	 * Add Check-in widget on the checkout form.
	 * Use Hook before_checkout_billing_form
	 *
	 * @param WC_Checkout $checkout
	 */
	public function before_checkout_billing_form( $checkout ) {
		// Get saved consumerProfileRef
		$profile = $this->get_consumer_profile( get_current_user_id() );

		// Initiate consumer session to obtain consumerProfileRef after checkin
		$js_view_url = $profile['url'];
		if ( empty( $profile['reference'] ) ) {
			// Initiate consumer session
			try {
				$result = $this->core->initiateConsumerSession( $this->checkin_country );
				$js_view_url = $result->getOperationByRel( 'view-consumer-identification' );
			} catch ( Exception $e ) {
				$profile['reference'] = null;
				$profile['billing']   = null;
			}
		}

		WC()->session->set( 'consumer_js_url', $js_view_url );

		// Checkin Form
		wc_get_template(
			'checkout/swedbank-pay/checkin.php',
			array(
				'checkin_country'  => $this->checkin_country,
				'selected_country' => apply_filters( 'swedbank_pay_checkin_default_country', 'SE' ),
				'js_view_url'      => $js_view_url,
				'consumer_data'    => $profile['billing'],
				'consumer_profile' => $profile['reference'],
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
		if ( 'yes' === $this->enabled && 'yes' === $this->instant_checkout ) {
			$js_url = WC()->session->get( 'swedbank_pay_checkout_js_url' );

			wc_get_template(
				'checkout/swedbank-pay/payment.php',
				array(
					//'checkout' => WC()->checkout()
					'js_url' => $js_url,
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
			if ( ! in_array( $host, array( 'api.payex.com', 'api.externalintegration.payex.com' ), true ) ) {
				throw new Exception( 'Access denied' );
			}

			$result = $this->core->request( 'GET', $url );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
			exit();
		}

		$address = isset( $result['billingAddress'] ) ? $result['billingAddress'] : $result['shippingAddress'];

		// Parse name field
		$parser = new \ADCI\FullNameParser\Parser();
		$name   = $parser->parse( $address['addressee'] );

		$output = array(
			'first_name' => $name->getFirstName(),
			'last_name'  => trim( $name->getMiddleName() . ' ' . $name->getLastName() ),
			'country'    => $address['countryCode'],
			'postcode'   => $address['zipCode'],
			'address_1'  => $address['streetAddress'],
			'address_2'  => ! empty( $address['coAddress'] ) ? 'c/o ' . $address['coAddress'] : '',
			'city'       => $address['city'],
			'state'      => '',
			'phone'      => $result['msisdn'],
			'email'      => $result['email'],
		);

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
		try {
			$js_url = $this->core->initiateConsumerSession( $country )->getOperationByRel( 'view-consumer-identification' );
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
			wp_send_json_error( array( 'message' => 'Customer reference required' ) );
			exit();
		}

		// Store Customer Profile
		$url = WC()->session->get( 'consumer_js_url' );
		$this->update_consumer_profile( get_current_user_id(), $customer_reference, $url );

		wp_send_json_success();
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
		if ( $order->get_payment_method() === $this->id && $order->has_status( 'cancelled' ) ) {
			$order->update_status( 'failed' );
		}

		// Prepare $_POST data
		$data = array();
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
		WC()->session->set( 'chosen_payment_method', $this->id );

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

		$id   = isset( $_POST['id'] ) ? wc_clean( $_POST['id'] ) : null;
		$data = isset( $_POST['data'] ) ? wc_clean( $_POST['data'] ) : null;

		// Try to decode JSON
		$decoded = json_decode( $data );
		if ( json_last_error() === JSON_ERROR_NONE ) {
			$data = $decoded;
		}

		$this->adapter->log(
			LogLevel::INFO,
			sprintf(
				'[FRONTEND]: [%s]: [%s]: %s',
				$id,
				self::get_remote_address(),
				var_export( $data, true )
			)
		);

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

		if ( 'no' === $this->enabled || 'no' === $this->instant_checkout ) {
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
		if ( 'yes' === $this->enabled && 'yes' === $this->instant_checkout ) {
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
		if ( 'yes' === $this->enabled && 'yes' === $this->instant_checkout ) {
			$profile = $this->get_consumer_profile( get_current_user_id() );

			// Add default data
			$default  = array(
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
			);
			$billing  = array_merge( $default, is_array( $profile['billing'] ) ? $profile['billing'] : array() );
			$shipping = array_merge( $default, is_array( $profile['shipping'] ) ? $profile['shipping'] : array() );

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
		remove_action( 'woocommerce_checkout_billing', array( $checkout, 'checkout_form_billing' ), 10 );
		remove_action( 'woocommerce_checkout_shipping', array( $checkout, 'checkout_form_shipping' ), 10 );
	}

	/**
	 * Billing form
	 */
	public function checkout_form_billing() {
		wc_get_template(
			'checkout/swedbank-pay/form-billing.php',
			array(
				'checkout' => WC()->checkout(),
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
		if ( 'yes' !== $this->enabled || 'yes' !== $this->instant_checkout ) {
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
			$reference  = null;
			$url        = null;
		}

		// Fill the consumer address
		if ( $user_id > 0 ) {
			try {
				$customer = new WC_Customer( $user_id, false );
			} catch ( Exception $e ) {
				$customer = WC()->customer;
			}
		} else {
			$customer = WC()->customer;
		}

		// Fill the consumer billing address
		if ( empty( $billing ) ) {
			$billing = array(
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
			);

			if ( $user_id > 0 ) {
				update_user_meta( $user_id, '_payex_consumer_address_billing', $billing );
			}
		}

		// Fill the consumer shipping address
		if ( empty( $shipping ) ) {
			$shipping = array(
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
			);

			if ( $user_id > 0 ) {
				update_user_meta( $user_id, '_payex_consumer_address_shipping', $shipping );
			}
		}

		// Return result
		return array(
			'reference' => $reference,
			'url'       => $url,
			'billing'   => $billing,
			'shipping'  => $shipping,
		);
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

	/**
	 * Get Remove Address
	 * @return string
	 */
	protected static function get_remote_address() {
		$headers = array(
			'CLIENT_IP',
			'FORWARDED',
			'FORWARDED_FOR',
			'FORWARDED_FOR_IP',
			'HTTP_CLIENT_IP',
			'HTTP_FORWARDED',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED_FOR_IP',
			'HTTP_PC_REMOTE_ADDR',
			'HTTP_PROXY_CONNECTION',
			'HTTP_VIA',
			'HTTP_X_FORWARDED',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED_FOR_IP',
			'HTTP_X_IMFORWARDS',
			'HTTP_XROXY_CONNECTION',
			'VIA',
			'X_FORWARDED',
			'X_FORWARDED_FOR',
		);

		$remote_address = false;
		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$remote_address = $_SERVER[ $header ];
				break;
			}
		}

		if ( ! $remote_address ) {
			$remote_address = $_SERVER['REMOTE_ADDR'];
		}

		// Extract address from list
		if ( strpos( $remote_address, ',' ) !== false ) {
			$tmp            = explode( ',', $remote_address );
			$remote_address = trim( array_shift( $tmp ) );
		}

		// Remove port if exists (IPv4 only)
		// phpcs:disable
		$reg_ex = '/^([1-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(\.([0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3}$/';
		if ( preg_match( $reg_ex, $remote_address )
			 && ( $pos_temp = stripos( $remote_address, ':' ) ) !== false
		) {
			$remote_address = substr( $remote_address, 0, $pos_temp );
		}
		// phpcs:enable

		return $remote_address;
	}

	/**
	 * Get Post Id by Meta
	 *
	 * @param $key
	 * @param $value
	 *
	 * @return null|string
	 */
	private function get_post_id_by_meta( $key, $value ) {
		global $wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = %s AND meta_value = %s;",
				$key,
				$value
			)
		);
	}

	/**
	 * Checks an order to see if it contains a subscription.
	 * @see wcs_order_contains_subscription()
	 *
	 * @param WC_Order $order
	 *
	 * @return bool
	 */
	private static function order_contains_subscription( $order ) {
		if ( ! function_exists( 'wcs_order_contains_subscription' ) ) {
			return false;
		}

		return wcs_order_contains_subscription( $order );
	}

	/**
	 * WC Subscriptions: Is Payment Change.
	 *
	 * @return bool
	 */
	private static function wcs_is_payment_change() {
		return class_exists( 'WC_Subscriptions_Change_Payment_Gateway', false )
		       && WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment;
	}

	/**
	 * Check is Cart have Subscription Products.
	 *
	 * @return bool
	 */
	private static function wcs_cart_have_subscription() {
		if ( ! class_exists( 'WC_Subscriptions_Product' ) ) {
			return false;
		}

		// Check is Recurring Payment
		$cart = WC()->cart->get_cart();
		foreach ( $cart as $key => $item ) {
			if ( is_object( $item['data'] ) && WC_Subscriptions_Product::is_subscription( $item['data'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Add Card ID when Subscription created
	 *
	 * @param $order_id
	 */
	public function add_subscription_card_id( $order_id ) {
		if ( ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			return;
		}

		$subscriptions = wcs_get_subscriptions_for_order( $order_id, array( 'order_type' => 'parent' ) );
		foreach ( $subscriptions as $subscription ) {
			/** @var WC_Subscription $subscription */
			$tokens = $subscription->get_payment_tokens();
			if ( count( $tokens ) === 0 ) {
				$tokens = $subscription->get_parent()->get_payment_tokens();
				foreach ( $tokens as $token_id ) {
					$token = new WC_Payment_Token_Swedbank_Pay( $token_id );
					if ( $token->get_gateway_id() !== $this->id ) {
						continue;
					}

					$subscription->add_payment_token( $token );
				}
			}
		}
	}

	/**
	 * Update the card meta for a subscription after using this payment method
	 * to complete a payment to make up for an automatic renewal payment which previously failed.
	 *
	 * @access public
	 *
	 * @param WC_Subscription $subscription The subscription for which the failing payment method relates.
	 * @param WC_Order $renewal_order The order which recorded the successful payment (to make up for the failed automatic payment).
	 *
	 * @return void
	 */
	public function update_failing_payment_method( $subscription, $renewal_order ) {
		// Delete tokens
		delete_post_meta( $subscription->get_id(), '_payment_tokens' );
	}

	/**
	 * Don't transfer customer meta to resubscribe orders.
	 *
	 * @access public
	 *
	 * @param WC_Order $resubscribe_order The order created for the customer to resubscribe to the old expired/cancelled subscription
	 *
	 * @return void
	 */
	public function delete_resubscribe_meta( $resubscribe_order ) {
		// Delete tokens
		delete_post_meta( $resubscribe_order->get_id(), '_payment_tokens' );
	}

	/**
	 * Include the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscription screen in Subscriptions v2.0+.
	 *
	 * @param array $payment_meta associative array of meta data required for automatic payments
	 * @param WC_Subscription $subscription An instance of a subscription object
	 *
	 * @return array
	 */
	public function add_subscription_payment_meta( $payment_meta, $subscription ) {
		$payment_meta[ $this->id ] = array(
			'swedbankpay_meta' => array(
				'token_id' => array(
					'value' => implode( ',', $subscription->get_payment_tokens() ),
					'label' => 'Card Token ID',
				),
			),
		);

		return $payment_meta;
	}

	/**
	 * Validate the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscription screen in Subscriptions 2.0+.
	 *
	 * @param string $payment_method_id The ID of the payment method to validate
	 * @param array $payment_meta associative array of meta data required for automatic payments
	 * @param WC_Subscription $subscription
	 *
	 * @throws Exception
	 */
	public function validate_subscription_payment_meta( $payment_method_id, $payment_meta, $subscription ) {
		if ( $payment_method_id === $this->id ) {
			if ( empty( $payment_meta['swedbankpay_meta']['token_id']['value'] ) ) {
				throw new Exception( 'A "Card Token ID" value is required.' );
			}

			$tokens = explode( ',', $payment_meta['swedbankpay_meta']['token_id']['value'] );
			foreach ( $tokens as $token_id ) {
				$token = new WC_Payment_Token_Swedbank_Pay( $token_id );
				if ( ! $token->get_id() ) {
					throw new Exception( 'This "Card Token ID" value not found.' );
				}

				if ( $token->get_gateway_id() !== $this->id ) {
					throw new Exception( 'This "Card Token ID" value should related to Swedbank Pay.' );
				}

				if ( $token->get_user_id() !== $subscription->get_user_id() ) {
					throw new Exception( 'Access denied for this "Card Token ID" value.' );
				}
			}
		}
	}

	/**
	 * Save payment method meta data for the Subscription
	 *
	 * @param WC_Subscription $subscription
	 * @param string $meta_table
	 * @param string $meta_key
	 * @param string $meta_value
	 */
	public function save_subscription_payment_meta( $subscription, $meta_table, $meta_key, $meta_value ) {
		if ( $subscription->get_payment_method() === $this->id ) {
			if ( 'swedbankpay_meta' === $meta_table && 'token_id' === $meta_key ) {
				// Delete tokens
				delete_post_meta( $subscription->get_id(), '_payment_tokens' );

				// Add tokens
				$tokens = explode( ',', $meta_value );
				foreach ( $tokens as $token_id ) {
					$token = new WC_Payment_Token_Swedbank_Pay( $token_id );
					if ( $token->get_id() ) {
						$subscription->add_payment_token( $token );
					}
				}
			}
		}
	}

	/**
	 * When a subscription payment is due.
	 *
	 * @param          $amount_to_charge
	 * @param WC_Order $renewal_order
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
		try {
			$tokens = $renewal_order->get_payment_tokens();

			foreach ( $tokens as $token_id ) {
				$token = new WC_Payment_Token_Swedbank_Pay( $token_id );
				if ( $token->get_gateway_id() !== $this->id ) {
					continue;
				}

				if ( ! $token->get_id() ) {
					throw new Exception( 'Invalid Token Id' );
				}

				$this->process_recurring_payment( $renewal_order, $token->get_recurrence_token() );

				break;
			}
		} catch ( \Exception $e ) {
			$renewal_order->update_status( 'failed' );
			$renewal_order->add_order_note(
				sprintf(
				/* translators: 1: amount 2: error */ __( 'Failed to charge "%1$s". %2$s.', 'woocommerce' ),
					wc_price( $amount_to_charge ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Render the payment method used for a subscription in the "My Subscriptions" table
	 *
	 * @param string $payment_method_to_display the default payment method text to display
	 * @param WC_Subscription $subscription the subscription details
	 *
	 * @return string the subscription payment method
	 */
	public function maybe_render_subscription_payment_method( $payment_method_to_display, $subscription ) {
		if ( $this->id !== $subscription->get_payment_method() || ! $subscription->get_user_id() ) {
			return $payment_method_to_display;
		}

		$tokens = $subscription->get_payment_tokens();
		foreach ( $tokens as $token_id ) {
			$token = new WC_Payment_Token_Swedbank_Pay( $token_id );
			if ( $token->get_gateway_id() !== $this->id ) {
				continue;
			}

			return sprintf(
			/* translators: 1: pan 2: month 3: year */ __( 'Via %1$s card ending in %2$s/%3$s', 'swedbank-pay-woocommerce-checkout' ),
				$token->get_masked_pan(),
				$token->get_expiry_month(),
				$token->get_expiry_year()
			);
		}

		return $payment_method_to_display;
	}

	/**
	 * Modify "Save to account" to lock that if needs.
	 *
	 * @param string $html
	 * @param WC_Payment_Gateway $gateway
	 *
	 * @return string
	 */
	public function save_new_payment_method_option_html( $html, $gateway ) {
		if ( $gateway->id !== $this->id ) {
			return $html;
		}

		// Lock "Save to Account" for Recurring Payments / Payment Change
		if ( self::wcs_cart_have_subscription() || self::wcs_is_payment_change() ) {
			// Load XML
			libxml_use_internal_errors( true );
			$doc = new \DOMDocument();
			$status = @$doc->loadXML( $html );
			if ( false !== $status ) {
				$item = $doc->getElementsByTagName('input')->item( 0 );
				$item->setAttribute('checked','checked' );
				$item->setAttribute('disabled','disabled' );

				$html = $doc->saveHTML($doc->documentElement);
			}
		}

		return $html;
	}
}
