<?php

namespace SwedbankPay\Checkout\WooCommerce;

defined( 'ABSPATH' ) || exit;

use Exception;
use WC_Gateway_Swedbank_Pay_Checkout;

class WC_Swedbank_Pay_Checkin {
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
	 * Enable Checkin.
	 *
	 * @var string
	 */
	public $checkin = 'yes';

	/**
	 * Checkin Country
	 * @var string
	 */
	public $checkin_country = 'SE';

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
		$this->instant_checkout = isset( $this->settings['instant_checkout'] ) ? $this->settings['instant_checkout'] : $this->instant_checkout;

		// Checkin settings
		$this->checkin          = isset( $this->settings['checkin'] ) ? $this->settings['checkin'] : $this->checkin;
		$this->checkin_country  = isset( $this->settings['checkin_country'] ) ? $this->settings['checkin_country'] : $this->checkin_country;
		$this->checkin_required = isset( $this->settings['checkin_required'] ) ? $this->settings['checkin_required'] : $this->checkin_required;
		$this->checkin_edit     = isset( $this->settings['checkin_edit'] ) ? $this->settings['checkin_edit'] : $this->checkin_edit;

		// Styles
		$this->custom_styles    = isset( $this->settings['custom_styles'] ) ? $this->settings['custom_styles'] : $this->custom_styles;
		$this->checkin_style    = isset( $this->settings['checkInStyle'] ) ? $this->settings['checkInStyle'] : $this->checkin_style;

		// JS Scrips
		add_action( 'wp_enqueue_scripts', array( $this, 'add_scripts' ) );

		// Check-in
		if ( 'yes' === $this->enabled && 'yes' === $this->checkin ) {
			// Override the default billing form
			add_action( 'woocommerce_checkout_init', array( $this, 'checkout_init' ), 10, 1 );
			add_action( 'woocommerce_checkout_billing', array( $this, 'checkout_form_billing' ) );
			add_action( 'woocommerce_checkout_shipping', array( $this, 'checkout_form_shipping' ) );

			// Add the checkin form
			add_action( 'woocommerce_before_checkout_billing_form', array( $this, 'before_checkout_billing_form' ) );

			// Lock the fields and autofill it if Instant Checkout is enabled
			if ( 'yes' === $this->instant_checkout && 'no' === $this->checkin_edit ) {
				add_filter( 'woocommerce_checkout_fields', array( $this, 'lock_checkout_fields' ), 10, 1 );
				add_filter( 'woocommerce_checkout_get_value', array( $this, 'checkout_get_value' ), 10, 2 );
			}

			// Ajax actions
			add_action( 'wp_ajax_swedbank_pay_checkin', array( $this, 'ajax_swedbank_pay_checkin' ) );
			add_action( 'wp_ajax_nopriv_swedbank_pay_checkin', array( $this, 'ajax_swedbank_pay_checkin' ) );
			add_action( 'wp_ajax_swedbank_pay_checkout_get_address', array( $this, 'ajax_swedbank_pay_checkout_get_address' ) );
			add_action( 'wp_ajax_nopriv_swedbank_pay_checkout_get_address', array( $this, 'ajax_swedbank_pay_checkout_get_address' ) );
			add_action( 'wp_ajax_swedbank_pay_checkout_customer_profile', array( $this, 'ajax_swedbank_pay_checkout_customer_profile' ) );
			add_action( 'wp_ajax_nopriv_swedbank_pay_checkout_customer_profile', array( $this, 'ajax_swedbank_pay_checkout_customer_profile' ) );
		}
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
		$form_fields['checkin'] = array(
			'title'   => __( 'Enable Checkin on Swedbank Pay Checkout', 'swedbank-pay-woocommerce-checkout' ),
			'type'    => 'checkbox',
			'label'   => __( 'Enable Checkin on Swedbank Pay Checkout', 'swedbank-pay-woocommerce-checkout' ),
			'default' => $this->checkin,
		);

		$form_fields['checkin_country'] = array(
			'title'       => __( 'Checkin country', 'swedbank-pay-woocommerce-checkout' ),
			'type'        => 'select',
			'options'     => array(
				'SE'     => __( 'Sweden', 'woocommerce' ),
				'NO'     => __( 'Norway', 'woocommerce' ),
				'SELECT' => __( 'Customer can choose', 'swedbank-pay-woocommerce-checkout' ),
			),
			'description' => __( 'Checkin country', 'swedbank-pay-woocommerce-checkout' ),
			'default'     => $this->checkin_country,
		);

		$form_fields['checkin_required'] = array(
			'title'   => __( 'Require checkin', 'swedbank-pay-woocommerce-checkout' ),
			'type'    => 'checkbox',
			'label'   => __( 'Require checkin', 'swedbank-pay-woocommerce-checkout' ),
			'default' => $this->checkin_required,
		);

		$form_fields['checkin_edit'] = array(
			'title'   => __( 'Allow to edit the address after Checkin.', 'swedbank-pay-woocommerce-checkout' ),
			'type'    => 'checkbox',
			'label'   => __( 'Allow to edit the address after Checkin.', 'swedbank-pay-woocommerce-checkout' ),
			'default' => $this->checkin_edit,
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
		if ( ! is_checkout() || 'no' === $this->enabled || 'no' === $this->checkin ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

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

		// Localize the script with new data
		$translation_array = array(
			'enabled'                      => $this->checkin,
			'culture'                      => $this->culture,
			'checkin_required'             => $this->checkin_required,
			'checkin_edit'                 => ( 'yes' === $this->checkin_edit ),
			'checkin_country'              => apply_filters( 'swedbank_pay_checkin_default_country', 'SE' ),
			'needs_shipping_address'       => WC()->cart->needs_shipping(),
			'ship_to_billing_address_only' => wc_ship_to_billing_address_only(),
			'nonce'                        => wp_create_nonce( 'swedbank_pay_checkout' ),
			'ajax_url'                     => admin_url( 'admin-ajax.php' ),
			'checkInStyle'                 => null,
			'needs_checkin'                => __(
				'You must check in to be able to pay.',
				'swedbank-pay-woocommerce-checkout'
			)
		);

		// Add CheckIn Styles
		$styles = apply_filters( 'swedbank_pay_checkout_checkin_style', $this->checkin_style );
		if ( $styles ) {
			$translation_array['checkInStyle'] = $styles;
		}

		wp_localize_script(
			'wc-sb-checkin',
			'WC_Gateway_Swedbank_Pay_Checkin',
			$translation_array
		);

		wp_enqueue_script( 'wc-sb-common' );
		wp_enqueue_script( 'wc-sb-checkin' );
	}


	/**
	 * Checkout initialization
	 *
	 * @param \WC_Checkout $checkout
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
		wc_get_template(
			'checkout/swedbank-pay/form-shipping.php',
			array(
				'checkout' => WC()->checkout(),
			),
			'',
			dirname( __FILE__ ) . '/../templates/'
		);
	}

	/**
	 * Add Check-in widget on the checkout form.
	 * Use Hook before_checkout_billing_form
	 *
	 * @param \WC_Checkout $checkout
	 */
	public function before_checkout_billing_form( $checkout ) {
		// Get saved consumerProfileRef
		$profile = $this->gateway->get_consumer_profile( get_current_user_id() );

		// Initiate consumer session to obtain consumerProfileRef after checkin
		$js_view_url = $profile['url'];
		if ( empty( $profile['reference'] ) ) {
			// Initiate consumer session
			try {
				$result = $this->gateway->core->initiateConsumerSession( $this->checkin_country );
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
				'checkin_edit'     => $this->checkin_edit,
				'js_view_url'      => $js_view_url,
				'consumer_data'    => $profile['billing'],
				'consumer_profile' => $profile['reference'],
			),
			'',
			dirname( __FILE__ ) . '/../templates/'
		);
	}

	/**
	 * Lock checkout fields
	 *
	 * @param $fieldset
	 *
	 * @return array
	 */
	public function lock_checkout_fields( $fieldset ) {
		foreach ( $fieldset as $section => &$fields ) {
			if ( ! in_array( $section, array( 'billing', 'shipping' ) ) ) {
				continue;
			}

			foreach ( $fields as $key => &$field ) {
				if ( isset( $field['type'] ) && 'password' === $field['type'] ) {
					continue;
				}

				if ( isset( $field['class'] ) && in_array( 'notes', $field['class'] ) ) {
					continue;
				}

				// Fill the checkout form from the checkin profile
				$field['default'] = $this->checkout_get_value( null, $key );

				// Lock fields
				$field['custom_attributes']['readonly'] = 'readonly';
				$field['class'][]                       = 'swedbank-pay-locked';
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
		$profile = $this->gateway->get_consumer_profile( get_current_user_id() );

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

		return $value;
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

		// Retrieve Consumer Shipping Details
		try {
			// Check url
			if ( mb_substr( $url, 0, 1, 'UTF-8' ) === '/' ) {
				$url = $this->gateway->backend_api_endpoint . $url;
			}

			$host = parse_url( $url, PHP_URL_HOST );
			if ( ! in_array( $host, array( 'api.payex.com', 'api.externalintegration.payex.com' ), true ) ) {
				throw new \Exception( 'Access denied' );
			}

			$result = $this->gateway->core->request( 'GET', $url );
		} catch ( \Exception $e ) {
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
		$this->gateway->update_consumer_address( get_current_user_id(), $type, $output );

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
			$js_url = $this->gateway->core->initiateConsumerSession( $country )->getOperationByRel( 'view-consumer-identification' );
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
		if ( $url ) {
			$this->gateway->update_consumer_profile( get_current_user_id(), $customer_reference, $url );
		}

		wp_send_json_success();
	}
}

