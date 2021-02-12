<?php // phpcs:disable
	/*
	 * Plugin Name: Swedbank Pay Checkout
	 * Plugin URI: https://www.swedbankpay.com/
	 * Description: Provides the Swedbank Pay Checkout for WooCommerce.
	 * Author: Swedbank Pay
	 * Author URI: https://profiles.wordpress.org/swedbankpay/
	 * License: Apache License 2.0
	 * License URI: http://www.apache.org/licenses/LICENSE-2.0
	 * Version: 4.3.0
	 * Text Domain: swedbank-pay-woocommerce-checkout
	 * Domain Path: /languages
	 * WC requires at least: 3.0.0
	 * WC tested up to: 4.9.2
	 */

	use SwedbankPay\Checkout\WooCommerce\WC_Swedbank_Plugin;

	defined( 'ABSPATH' ) || exit;

	include_once( dirname( __FILE__ ) . '/includes/class-wc-swedbank-plugin.php' );

	class WC_Swedbank_Pay_Checkout extends WC_Swedbank_Plugin {
	const TEXT_DOMAIN = 'swedbank-pay-woocommerce-checkout';
	// phpcs:enable

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		// Activation
		register_activation_hook( __FILE__, array( $this, 'install' ) );

		// Actions
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ), 0 );
		add_action( 'woocommerce_loaded', array( $this, 'woocommerce_loaded' ), 30 );
	}

	/**
	 * Install
	 */
	public function install() {
		// Check dependencies
		if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
			die( 'This plugin can\'t be activated. Please run `composer install` to install dependencies.' );
		}

		parent::install();

		// Set Version
		if ( ! get_option( 'woocommerce_payex_checkout_version' ) ) {
			add_option( 'woocommerce_payex_checkout_version', '1.0.0' );
		}
	}

	/**
	 * Add relevant links to plugins page
	 *
	 * @param array $links
	 *
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$plugin_links = array(
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=payex_checkout' ) . '">' . __(
				'Settings',
				'swedbank-pay-woocommerce-checkout'
			) . '</a>',
		);

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Init localisations and files
	 * @return void
	 */
	public function init() {
		// Localization
		load_plugin_textdomain(
			'swedbank-pay-woocommerce-checkout',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	}

	/**
	 * WooCommerce Loaded: load classes
	 * @return void
	 */
	public function woocommerce_loaded() {
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-swedbank-pay-checkout.php' );

		// Register Gateway
		WC_Swedbank_Pay_Checkout::register_gateway( 'WC_Gateway_Swedbank_Pay_Checkout' );
	}
}

	new WC_Swedbank_Pay_Checkout();
