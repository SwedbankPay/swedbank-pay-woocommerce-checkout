<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Swedbank_Pay_Invoice_Fee {
	/**
	 * Constructor.
	 */
	public function __construct() {
		// Load settings
		$settings = get_option( 'woocommerce_payex_checkout_settings' );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		define( 'SB_INVOICE_DEBUG', isset( $settings['debug'] ) ? $settings['debug'] : 'yes' );
		define( 'SB_INVOICE_FEE', isset( $settings['fee'] ) ? (float) $settings['fee'] : 0 );
		define( 'SB_INVOICE_FEE_IS_TAXABLE',
			isset( $settings['fee_is_taxable'] ) ? $settings['fee_is_taxable'] : 'no' );
		define( 'SB_INVOICE_FEE_TAX_CLASS',
			isset( $settings['fee_tax_class'] ) ? $settings['fee_tax_class'] : 'standard' );

		add_action( 'woocommerce_init', array( $this, 'woocommerce_init' ) );

		// JS Scrips
		if ( 'yes' === $settings['enabled'] && SB_INVOICE_FEE > 0 ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		}

		// Add settings
		add_action( 'woocommerce_after_register_post_type', array( $this, 'register_post_type' ), 100 );

		add_action( 'wp_ajax_sb_invoice_apply_fee', array( $this, 'ajax_sb_invoice_apply_fee' ) );
		add_action( 'wp_ajax_nopriv_sb_invoice_apply_fee', array( $this, 'ajax_sb_invoice_apply_fee' ) );

		add_action( 'wp_ajax_sb_invoice_unset_fee', array( $this, 'ajax_sb_invoice_unset_fee' ) );
		add_action( 'wp_ajax_nopriv_sb_invoice_unset_fee', array( $this, 'ajax_sb_invoice_unset_fee' ) );

		// Payment fee
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_cart_fee' ) );
	}

	/**
	 * WooCommerce Init.
	 */
	public function woocommerce_init() {
		if ( session_status() === PHP_SESSION_NONE && ! headers_sent() ) {
			session_start();
		}
	}

	/**
	 * Enqueue scripts
	 */
	public function enqueue_scripts() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_register_script(
			'swedbank-pay-checkout-invoice-fee',
			untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/js/invoice-fee' . $suffix . '.js',
			array(),
			false,
			true
		);

		// Localize the script with new data
		$translation_array = array(
			'nonce'    => wp_create_nonce( 'swedbank_pay_checkout_invoice' ),
			'ajax_url' => admin_url( 'admin-ajax.php' ),
		);

		wp_localize_script(
			'swedbank-pay-checkout-invoice-fee',
			'WC_Gateway_Swedbank_Pay_Checkout_Invoice',
			$translation_array
		);

		wp_enqueue_script( 'swedbank-pay-checkout-invoice-fee' );
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
		$form_fields['fee'] = array(
			'title'             => __( 'Invoice Fee', 'swedbank-pay-woocommerce-checkout' ),
			'type'              => 'number',
			'custom_attributes' => array(
				'step' => 'any'
			),
			'description'       => __( 'Invoice fee. Set 0 to disable.', 'swedbank-pay-woocommerce-checkout' ),
			'default'           => '0'
		);

		$form_fields['fee_is_taxable'] = array(
			'title'   => __( 'Invoice Fee is Taxable', 'swedbank-pay-woocommerce-checkout' ),
			'type'    => 'checkbox',
			'label'   => __( 'Invoice Fee is Taxable', 'swedbank-pay-woocommerce-checkout' ),
			'default' => 'no'
		);

		$form_fields['fee_tax_class'] = array(
			'title'       => __( 'Tax class of Invoice Fee', 'swedbank-pay-woocommerce-checkout' ),
			'type'        => 'select',
			'options'     => $this->getTaxClasses(),
			'description' => __( 'Tax class of Invoice Fee.', 'swedbank-pay-woocommerce-checkout' ),
			'default'     => 'standard'
		);

		return $form_fields;
	}

	/**
	 * Add fee when selected payment method.
	 */
	public function add_cart_fee() {
		$this->log( __METHOD__ . ': woocommerce_cart_calculate_fees' );

		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		// Is Fee is not specified
		if ( abs( SB_INVOICE_FEE ) < 0.01 ) {
			return;
		}

		$flag = isset( $_SESSION['sb_invoice_fee_apply'] ) ? $_SESSION['sb_invoice_fee_apply'] : null;
		$this->log( __METHOD__ . var_export( $flag, true ) );
		if ( 'yes' === $flag ) {
			$this->log( __METHOD__ . ': Add cart fee.' );

			// Add Fee
			WC()->cart->add_fee(
				__( 'Invoice Fee', 'swedbank-pay-woocommerce-checkout' ),
				SB_INVOICE_FEE,
				( SB_INVOICE_FEE_IS_TAXABLE === 'yes' ),
				SB_INVOICE_FEE_TAX_CLASS
			);
		}
	}

	/**
	 * Ajax: Add invoice fee.
	 */
	public function ajax_sb_invoice_apply_fee() {
		check_ajax_referer( 'swedbank_pay_checkout_invoice', 'nonce' );

		$this->log( __METHOD__ . ': Set invoice fee.' );

		$_SESSION['sb_invoice_fee_apply'] = 'yes';

		wp_send_json_success();
	}

	/**
	 * Ajax: Remove invoice fee.
	 */
	public function ajax_sb_invoice_unset_fee() {
		check_ajax_referer( 'swedbank_pay_checkout_invoice', 'nonce' );

		$this->log( __METHOD__ . ': Remove invoice fee.' );

		$_SESSION['sb_invoice_fee_apply'] = 'no';

		wp_send_json_success();
	}

	/**
	 * Get Tax Classes.
	 *
	 * @return array
	 */
	private function getTaxClasses() {
		// Get tax classes
		$tax_classes = array_filter( array_map( 'trim',
			explode( "\n", get_option( 'woocommerce_tax_classes' ) )
		) );

		$tax_class_options     = array();
		$tax_class_options[''] = __( 'Standard', 'woocommerce' );
		foreach ( $tax_classes as $class ) {
			$tax_class_options[ sanitize_title( $class ) ] = $class;
		}

		return $tax_class_options;
	}

	/**
	 * Debug Log.
	 *
	 * @param $message
	 * @param $level
	 *
	 * @return void
	 * @see WC_Log_Levels
	 *
	 */
	private function log( $message, $level = 'notice' ) {
		if ( 'yes' === SB_INVOICE_DEBUG) {
			// Get Logger instance
			$log = new WC_Logger();

			// Write message to log
			if ( ! is_string( $message ) ) {
				$message = var_export( $message, true );
			}

			$log->log( $level, $message, [
				'source'  => 'payex_checkout-invoice-fee',
				'_legacy' => true
			] );
		}
	}
}

new WC_Swedbank_Pay_Invoice_Fee();
