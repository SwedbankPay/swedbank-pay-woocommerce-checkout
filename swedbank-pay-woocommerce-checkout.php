<?php
/*
 * Plugin Name: Swedbank Pay WooCommerce Checkout
 * Plugin URI: https://www.swedbankpay.com/
 * Description: Provides a Credit Card Payment Gateway through Swedbank Pay for WooCommerce.
 * Author: Swedbank Pay
 * Author URI: https://www.swedbankpay.com/
 * License: Apache License 2.0
 * License URI: http://www.apache.org/licenses/LICENSE-2.0
 * Version: 2.2.0
 * Text Domain: swedbank-pay-woocommerce-checkout
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 3.8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Swedbank_Pay_Checkout {
	const TEXT_DOMAIN = 'swedbank-pay-woocommerce-checkout';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_notices', [ $this, 'display_admin_notices' ] );

		// Activation
		register_activation_hook( __FILE__, [ $this, 'activate' ] );

		// Actions
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'plugin_action_links' ] );
		add_action( 'plugins_loaded', [ $this, 'init' ], 0 );
		add_action( 'woocommerce_loaded', [ $this, 'woocommerce_loaded' ], 20 );
	}

	/**
	 * Activate Plugin
	 */
	public function activate() {
		// Required plugin: Swedbank Pay WooCommerce Payments
		if ( class_exists( 'WC_Swedbank_Pay', false ) ) {
			return true;
		}

		// Download and Install PSP package
		include_once ABSPATH . '/wp-includes/pluggable.php';
		include_once ABSPATH . '/wp-admin/includes/plugin.php';
		include_once ABSPATH . '/wp-admin/includes/file.php';

		try {
			if ( ! $plugin = self::get_psp_plugin() ) {
				// Install plugin
				self::install_psp_plugin();

				// Plugin path
				$plugin = self::get_psp_plugin();
			}

			// Check is active
			if ( ! is_plugin_active( $plugin ) ) {
				// Activate plugin
				self::activate_psp_plugin();

				WC_Admin_Notices::add_custom_notice(
					'wc-payex-checkout-notice',
					__( 'Required Swedbank Pay WooCommerce payments plugin was automatically installed.', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN )
				);
			}
		} catch ( \Exception $e ) {
			self::add_admin_notice( $e->getMessage(), 'error' );

			return false;
		}

		// Set Version
		if ( ! get_option( 'woocommerce_payex_checkout_version' ) ) {
			add_option( 'woocommerce_payex_checkout_version', '1.0.0' );
		}

		return true;
	}

	/**
	 * Add relevant links to plugins page
	 *
	 * @param array $links
	 *
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$plugin_links = [
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=payex_checkout' ) . '">' . __( 'Settings', WC_Swedbank_Pay_Checkout::TEXT_DOMAIN ) . '</a>'
		];

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Init localisations and files
	 * @return void
	 */
	public function init() {
		// Localization
		load_plugin_textdomain( WC_Swedbank_Pay_Checkout::TEXT_DOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * WooCommerce Loaded: load classes
	 * @return void
	 */
	public function woocommerce_loaded() {
		if ( ! class_exists( 'WC_Swedbank_Pay', false ) ) {
			return;
		}

		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-swedbank-pay-checkout.php' );
	}

	/**
	 * Display admin notices
	 * @return void
	 */
	public function display_admin_notices() {
		$notices = self::get_admin_notices();
		if ( count( $notices ) === 0 ) {
			return;
		}

		foreach ( $notices as $type => $messages ):
			?>
			<div class="<?php echo esc_html( $type ); ?> notice">
				<?php foreach ( $messages as $message ): ?>
					<p>
						<?php echo esc_html( $message ); ?>
					</p>
				<?php endforeach; ?>
			</div>
		<?php
		endforeach;

		// Remove notices
		delete_transient( 'wc-payex-checkout-notice' );

		// Deactivate plugin
		deactivate_plugins( [ __FILE__ ], true );
	}

	/**
	 * Add admin notice
	 *
	 * @param string $message
	 * @param string $type
	 *
	 * @return void
	 */
	public static function add_admin_notice( $message, $type = 'error' ) {
		wp_cache_delete( 'wc-payex-checkout-notice', 'transient' );
		if ( ! ( $notices = get_transient( 'wc-payex-checkout-notice' ) ) ) {
			$notices = [];
		}

		if ( ! isset( $notices[ $type ] ) ) {
			$notices[ $type ] = [];
		}

		$notices[ $type ][] = $message;

		set_transient( 'wc-payex-checkout-notice', $notices );
	}

	/**
	 * Get admin notices
	 * @return array
	 */
	public static function get_admin_notices() {
		if ( ! ( $notices = get_transient( 'wc-payex-checkout-notice' ) ) ) {
			$notices = [];
		}

		return $notices;
	}

	/**
	 * Activate PSP Plugin
	 * @return bool
	 * @throws \Exception
	 */
	public static function activate_psp_plugin() {
		if ( $plugin = self::get_psp_plugin() ) {
			$result = activate_plugin( $plugin );
			if ( is_wp_error( $result ) ) {
				throw new Exception( $result->get_error_message() );
			}

			return true;
		}

		throw new Exception( 'Failed to activate plugin' );
	}

	/**
	 * Get PSP Plugin path
	 * @return bool|string
	 */
	protected static function get_psp_plugin() {
		wp_cache_delete( 'plugins', 'plugins' );

		$plugins = get_plugins();
		foreach ( $plugins as $file => $plugin ) {
			if ( strpos( $file, 'swedbank-pay-woocommerce-payments.php' ) !== false ) {
				return $file;
			}
		}

		return false;
	}

	/**
	 * Install PSP Plugin
	 * @return void
	 * @throws \Exception
	 */
	protected static function install_psp_plugin() {
		WP_Filesystem();

		/** @var WP_Filesystem_Base $wp_filesystem */
		global $wp_filesystem;

		// Install plugin
		// Get latest release from Github
		$response = wp_remote_get( 'https://github.com/SwedbankPay/swedbank-pay-woocommerce-payments/releases/latest', [
			'headers' => [ 'Accept' => 'application/vnd.github.v3+json' ],
		] );
		if ( is_wp_error( $response ) ) {
			throw new Exception( $response->get_error_message() );
		}

		$release = json_decode( $response['body'], true );
		if ( ! isset( $release['zipball_url'] ) ) {
			throw new Exception( 'Failed to get latest release of Swedbank Pay WooCommerce payments plugin' );
		}

		// Download package
		$tmpfile = download_url( $release['zipball_url'] );
		if ( is_wp_error( $tmpfile ) ) {
			throw new Exception( $tmpfile->get_error_message() );
		}

		// Extract package
		$tmpdir = rtrim( get_temp_dir(), '/' ) . '/' . uniqid( 'psp_' );
		if ( ! $wp_filesystem->exists( $tmpdir ) ) {
			$wp_filesystem->mkdir( $tmpdir, FS_CHMOD_DIR );
		}
		$result = unzip_file( $tmpfile, $tmpdir );
		if ( is_wp_error( $result ) ) {
			throw new Exception( $result->get_error_message() );
		}

		// Remove temp file
		$wp_filesystem->delete( $tmpfile );

		// Move plugin to plugins directory
		$files = $wp_filesystem->dirlist( $tmpdir );
		foreach ( $files as $name => $details ) {
			if ( strpos( $name, 'swedbank-pay-woocommerce-payments' ) !== false ) {
				$destination = WP_PLUGIN_DIR . '/swedbank-pay-woocommerce-payments';
				// Remove destination directory if exists
				if ( $wp_filesystem->exists( $destination ) ) {
					$wp_filesystem->rmdir( $destination );
				}

				// Make destination directory
				$wp_filesystem->mkdir( $destination, FS_CHMOD_DIR );

				// Copy unpacked directory to destination directory
				$result = copy_dir( $tmpdir . '/' . $name, $destination );
				if ( is_wp_error( $result ) ) {
					throw new Exception( $result->get_error_message() );
				}

				// Remove temp directory
				$wp_filesystem->rmdir( $tmpdir );

				return;
			}
		}

		// Remove temp directory
		$wp_filesystem->rmdir( $tmpdir );

		throw new Exception( 'Failed to install "Swedbank Pay WooCommerce Payments" plugin' );
	}
}

new WC_Swedbank_Pay_Checkout();
