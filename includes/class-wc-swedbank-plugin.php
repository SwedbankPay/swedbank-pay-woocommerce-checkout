<?php

namespace SwedbankPay\Checkout\WooCommerce;

defined( 'ABSPATH' ) || exit;

use WC_Order;
use Automattic\Jetpack\Constants;
use Exception;

class WC_Swedbank_Plugin {

	/** Payment IDs */
	const PAYMENT_METHODS = array(
		'payex_checkout',
	);

	const PLUGIN_NAME = 'Swedbank Pay Checkout plugin';
	const PLUGIN_PATH = 'swedbank-pay-woocommerce-checkout/swedbank-pay-woocommerce-checkout.php';
	const SUPPORT_EMAIL = 'support.ecom@payex.com';
	const DB_VERSION = '1.0.0';
	const DB_VERSION_SLUG = 'swedbank_pay_checkout_version';
	const ADMIN_SUPPORT_PAGE_SLUG = 'swedbank-pay-checkout-support';
	const ADMIN_UPGRADE_PAGE_SLUG = 'swedbank-pay-checkout-upgrade';

	/**
	 * @var WC_Background_Swedbank_Pay_Queue
	 */
	public static $background_process;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Includes
		$this->includes();

		// Actions
		add_filter( 'plugin_action_links_' . self::PLUGIN_PATH, array( $this, 'plugin_action_links' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ), 0 );
		add_action( 'woocommerce_init', array( $this, 'woocommerce_init' ) );
		add_action( 'woocommerce_loaded', array( $this, 'woocommerce_hook_loaded' ) );

		// Add statuses for payment complete
		add_filter(
			'woocommerce_valid_order_statuses_for_payment_complete',
			array( $this, 'add_valid_order_statuses' ),
			10,
			2
		);

		// Add meta boxes
		add_action( 'add_meta_boxes', __CLASS__ . '::add_meta_boxes' );

		// Add action buttons
		add_action( 'woocommerce_order_item_add_action_buttons', __CLASS__ . '::add_action_buttons', 10, 1 );

		// Add scripts and styles for admin
		add_action( 'admin_enqueue_scripts', __CLASS__ . '::admin_enqueue_scripts' );

		// Add Admin Backend Actions
		add_action( 'wp_ajax_swedbank_pay_capture', array( $this, 'ajax_swedbank_pay_capture' ) );

		add_action( 'wp_ajax_swedbank_pay_cancel', array( $this, 'ajax_swedbank_pay_cancel' ) );

		add_action( 'wp_ajax_swedbank_pay_refund', array( $this, 'ajax_swedbank_pay_refund' ) );

		// Filters
		add_filter( 'swedbank_pay_generate_uuid', array( $this, 'generate_uuid' ), 10, 1 );
		add_filter( 'swedbank_pay_payment_description', __CLASS__ . '::payment_description', 10, 2 );
		add_filter( 'swedbank_pay_order_billing_phone', __CLASS__ . '::billing_phone', 10, 2 );

		// Process swedbank queue
		if ( ! is_multisite() ) {
			add_action( 'customize_save_after', array( $this, 'maybe_process_queue' ) );
			add_action( 'after_switch_theme', array( $this, 'maybe_process_queue' ) );
		}

		// Add admin menu
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 99 );

		add_action( 'init', __CLASS__ . '::may_add_notice' );

		add_action( 'admin_post_' . self::ADMIN_SUPPORT_PAGE_SLUG, __CLASS__ . '::support_submit' );
	}

	public function includes() {
		if ( ! defined( 'VERSION' ) ) {
			define( 'VERSION', '1.0.0' );
		}

		$vendors_dir = dirname( __FILE__ ) . '/../vendor';

		// Check if the payments plugin was installed
		include_once ABSPATH . '/wp-admin/includes/plugin.php';
		$plugins = get_plugins();
		foreach ( $plugins as $file => $plugin ) {
			if ( strpos( $file, 'swedbank-pay-woocommerce-checkout.php' ) !== false ) {
				if ( file_exists( dirname( $file ) . '/vendor/autoload.php') ) {
					$vendors_dir = dirname( $file ) . '/vendor';
					break;
				}
			}
		}

		if ( file_exists( $vendors_dir . '/autoload.php' ) ) {
			// Prevent conflicts of the composer
			$content = file_get_contents( $vendors_dir . '/composer/autoload_real.php' );
			$matches = array();
			preg_match('/class\s+(\w+)(.*)?/', $content, $matches, PREG_OFFSET_CAPTURE, 0);
			if ( ! isset( $matches[1] ) || ! class_exists( $matches[1][0], false ) ) {
				require_once $vendors_dir . '/autoload.php';
			}
		}

		require_once( dirname( __FILE__ ) . '/class-wc-swedbank-pay-transactions.php' );
		require_once( dirname( __FILE__ ) . '/class-wc-swedbank-subscriptions.php' );
		require_once( dirname( __FILE__ ) . '/class-wc-swedbank-pay-checkin.php' );
		require_once( dirname( __FILE__ ) . '/class-wc-swedbank-pay-instant-checkout.php' );
		require_once( dirname( __FILE__ ) . '/class-wc-swedbank-pay-instant-capture.php' );
		require_once( dirname( __FILE__ ) . '/class-wc-swedbank-pay-invoice-fee.php' );
		require_once( dirname( __FILE__ ) . '/class-wc-shortcode-checkout.php' );
	}

	/**
	 * Install
	 */
	public function install() {
		// Install Schema
		WC_Swedbank_Pay_Transactions::instance()->install_schema();

		// Set Version
		if ( ! get_option( self::DB_VERSION_SLUG ) ) {
			add_option( self::DB_VERSION_SLUG, self::DB_VERSION );
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
			'<a href="' . esc_url( admin_url( 'admin.php?page=' . self::ADMIN_SUPPORT_PAGE_SLUG ) ) . '">' . __(
				'Support',
				'swedbank-pay-woocommerce-checkout'
			) . '</a>',
		);

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Init localisations and files
	 */
	public function init() {
		// Functions
	}

	/**
	 * WooCommerce Init
	 */
	public function woocommerce_init() {
		include_once( dirname( __FILE__ ) . '/class-wc-background-swedbank-pay-queue.php' );
		self::$background_process = new WC_Background_Swedbank_Pay_Queue();
	}

	/**
	 * WooCommerce Loaded: load classes
	 */
	public function woocommerce_hook_loaded() {
		// Includes
		include_once( dirname( __FILE__ ) . '/class-wc-payment-token-swedbank-pay.php' );
	}

	/**
	 * Register payment gateway
	 *
	 * @param string $class_name
	 */
	public static function register_gateway( $class_name ) {
		global $px_gateways;

		if ( ! $px_gateways ) {
			$px_gateways = array();
		}

		if ( ! isset( $px_gateways[ $class_name ] ) ) {
			// Initialize instance
			$gateway = new $class_name;

			if ( $gateway ) {
				$px_gateways[] = $class_name;

				// Register gateway instance
				add_filter(
					'woocommerce_payment_gateways',
					function ( $methods ) use ( $gateway ) {
						$methods[] = $gateway;

						return $methods;
					}
				);
			}
		}
	}

	/**
	 * Allow processing/completed statuses for capture
	 *
	 * @param array $statuses
	 * @param WC_Order $order
	 *
	 * @return array
	 */
	public function add_valid_order_statuses( $statuses, $order ) {
		$payment_method = $order->get_payment_method();
		if ( in_array( $payment_method, self::PAYMENT_METHODS, true ) ) {
			$statuses = array_merge(
				$statuses,
				array(
					'processing',
					'completed',
				)
			);
		}

		return $statuses;
	}

	/**
	 * Add meta boxes in admin
	 * @return void
	 */
	public static function add_meta_boxes() {
		global $post_id;

		$order = wc_get_order( $post_id );

		if ( $order ) {
			$payment_method = $order->get_payment_method();
			if ( in_array( $payment_method, self::PAYMENT_METHODS, true ) ) {
				$payment_id = get_post_meta( $post_id, '_payex_payment_id', true );
				if ( ! empty( $payment_id ) ) {
					add_meta_box(
						'swedbank_payment_actions',
						__( 'Swedbank Pay Payments Actions', 'swedbank-pay-woocommerce-checkout' ),
						__CLASS__ . '::order_meta_box_payment_actions',
						'shop_order',
						'side',
						'default'
					);
				}
			}
		}
	}

	/**
	 * MetaBox for Payment Actions
	 * @return void
	 */
	public static function order_meta_box_payment_actions() {
		global $post_id;

		$order      = wc_get_order( $post_id );
		$payment_id = get_post_meta( $post_id, '_payex_payment_id', true );
		if ( empty( $payment_id ) ) {
			return;
		}

		// Get Payment Gateway
		$gateways = WC()->payment_gateways()->get_available_payment_gateways();
		if ( ! isset( $gateways[ $order->get_payment_method() ] ) ) {
			return;
		}

		/** @var \WC_Gateway_Swedbank_Pay_Cc $gateway */
		$gateway = $gateways[ $order->get_payment_method() ];

		// Fetch payment info
		try {
			$result = $gateway->core->fetchPaymentInfo( $payment_id );
		} catch ( \Exception $e ) {
			// Request failed
			return;
		}

		wc_get_template(
			'admin/payment-actions.php',
			array(
				'gateway'    => $gateway,
				'order'      => $order,
				'order_id'   => $post_id,
				'payment_id' => $payment_id,
				'info'       => $result,
			),
			'',
			dirname( __FILE__ ) . '/../templates/'
		);
	}

	/**
	 * Add action buttons to Order view
	 *
	 * @param WC_Order $order
	 */
	public static function add_action_buttons( $order ) {
		if (function_exists('wcs_is_subscription') && wcs_is_subscription($order)) {
		    // Buttons are available for orders only
		    return;
		}

		// Get Payment Gateway
		$payment_method = $order->get_payment_method();
		if ( in_array( $payment_method, self::PAYMENT_METHODS, true ) ) {
			$gateways = WC()->payment_gateways()->get_available_payment_gateways();

			if ( isset( $gateways[ $payment_method ] ) ) {
				/** @var \WC_Gateway_Swedbank_Pay_Cc $gateway */
				$gateway = $gateways[ $payment_method ];

				wc_get_template(
					'admin/action-buttons.php',
					array(
						'gateway' => $gateway,
						'order'   => $order,
					),
					'',
					dirname( __FILE__ ) . '/../templates/'
				);
			}
		}
	}

	/**
	 * Enqueue Scripts in admin
	 *
	 * @param $hook
	 *
	 * @return void
	 */
	public static function admin_enqueue_scripts( $hook ) {
		if ( 'post.php' === $hook ) {
			// Scripts
			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
			wp_register_script(
				'swedbank-pay-admin-js',
				plugin_dir_url( __FILE__ ) . '../assets/js/admin' . $suffix . '.js'
			);

			// Localize the script
			$translation_array = array(
				'ajax_url'  => admin_url( 'admin-ajax.php' ),
				'text_wait' => __( 'Please wait...', 'swedbank-pay-woocommerce-checkout' ),
			);
			wp_localize_script( 'swedbank-pay-admin-js', 'SwedbankPay_Admin', $translation_array );

			// Enqueued script with localized data
			wp_enqueue_script( 'swedbank-pay-admin-js' );
		}
	}

	/**
	 * Action for Capture
	 */
	public function ajax_swedbank_pay_capture() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'swedbank_pay' ) ) {
			exit( 'No naughty business' );
		}

		$order_id = (int) $_REQUEST['order_id'];
		$order    = wc_get_order( $order_id );

		// Get Payment Gateway
		$gateways = WC()->payment_gateways()->get_available_payment_gateways();
		if ( isset( $gateways[ $order->get_payment_method() ] ) ) {
			$gateway = $gateways[ $order->get_payment_method() ];

			try {
				$gateway->capture_payment( $order_id );
				wp_send_json_success( __( 'Capture success.', 'swedbank-pay-woocommerce-checkout' ) );
			} catch ( Exception $e ) {
				$message = $e->getMessage();
				wp_send_json_error( $message );
			}
		}
	}

	/**
	 * Action for Cancel
	 */
	public function ajax_swedbank_pay_cancel() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'swedbank_pay' ) ) {
			exit( 'No naughty business' );
		}

		$order_id = (int) $_REQUEST['order_id'];
		$order    = wc_get_order( $order_id );

		// Get Payment Gateway
		$gateways = WC()->payment_gateways()->get_available_payment_gateways();
		if ( isset( $gateways[ $order->get_payment_method() ] ) ) {
			$gateway = $gateways[ $order->get_payment_method() ];

			try {
				$gateway->cancel_payment( $order_id );
				wp_send_json_success( __( 'Cancel success.', 'swedbank-pay-woocommerce-checkout' ) );
			} catch ( Exception $e ) {
				$message = $e->getMessage();
				wp_send_json_error( $message );
			}
		}
	}

	public function ajax_swedbank_pay_refund() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'swedbank_pay' ) ) {
			exit( 'No naughty business' );
		}

		$order_id = (int) $_REQUEST['order_id'];
		$order    = wc_get_order( $order_id );

		try {
			// Create the refund object.
			$refund = wc_create_refund(
				array(
					'amount'         => $order->get_total(),
					'reason'         => __( 'Full refund.', 'swedbank-pay-woocommerce-checkout' ),
					'order_id'       => $order_id,
					'refund_payment' => true
				)
			);

			if ( is_wp_error( $refund ) ) {
				throw new Exception( $refund->get_error_message() );
			}

			wp_send_json_success( __( 'Refund has been successful.', 'swedbank-pay-woocommerce-checkout' ) );
		} catch ( Exception $e ) {
			$message = $e->getMessage();
			wp_send_json_error( $message );
		}
    }

	/**
	 * Generate UUID
	 *
	 * @param $node
	 *
	 * @return string
	 */
	public function generate_uuid( $node ) {
		return \Ramsey\Uuid\Uuid::uuid5( \Ramsey\Uuid\Uuid::NAMESPACE_OID, $node )->toString();
	}

	/**
	 * Payment Description.
	 *
	 * @param string $description
	 * @param WC_Order $order
	 *
	 * @return string
	 */
	public static function payment_description( $description, $order ) {
		return $description;
	}

	/**
	 * Billing phone.
	 *
	 * @param string $billing_phone
	 * @param WC_Order $order
	 *
	 * @return string
	 */
	public static function billing_phone( $billing_phone, $order ) {
		$billing_country = $order->get_billing_country();
		$billing_phone = preg_replace( '/[^0-9\+]/', '', $billing_phone );

		if ( ! preg_match('/^((00|\+)([1-9][1-9])|0([1-9]))(\d*)/', $billing_phone, $matches) ) {
			return null;
		}

		switch ($billing_country) {
			case 'SE':
				$country_code = '46';
				break;
			case 'NO':
				$country_code = '47';
				break;
			case 'DK':
				$country_code = '45';
				break;
			default:
				return '+' . ltrim( $billing_phone, '+' );
		}

		if ( isset( $matches[3] ) && isset( $matches[5]) ) { // country code present
			$billing_phone = $matches[3] . $matches[5];
		}

		if ( isset( $matches[4]) && isset( $matches[5]) ) { // no country code present. removing leading 0
			$billing_phone = $country_code . $matches[4] . $matches[5];
		}

		return strlen( $billing_phone ) > 7 && strlen( $billing_phone ) < 16 ? '+' . $billing_phone : null;
	}

	/**
	 * Dispatch Background Process
	 */
	public function maybe_process_queue() {
		self::$background_process->dispatch();
	}

	/**
	 * Add Upgrade notice
	 */
	public static function may_add_notice() {
		// Check if WooCommerce is missing
		if ( ! class_exists( 'WooCommerce', false ) || ! defined( 'WC_ABSPATH' ) ) {
			add_action( 'admin_notices', __CLASS__ . '::missing_woocommerce_notice' );
		}

		// Check dependencies
		add_action( 'admin_notices', __CLASS__ . '::check_dependencies' );

		if ( version_compare( get_option( self::DB_VERSION_SLUG, self::DB_VERSION ), self::DB_VERSION, '<' ) &&
		     current_user_can( 'manage_woocommerce' )
		) {
			add_action( 'admin_notices', __CLASS__ . '::upgrade_notice' );
		}

		// Check the decimal settings
        if ( 0 === wc_get_price_decimals() ) {
	        add_action( 'admin_notices', __CLASS__ . '::wrong_decimals_notice' );
	        remove_action(
	            'admin_notices',
                '\SwedbankPay\Payments\WooCommerce\WC_Swedbank_Plugin::wrong_decimals_notice'
            );
        }
	}

	/**
	 * Provide Admin Menu items
	 */
	public function admin_menu() {
		// Add Upgrade Page
		global $_registered_pages;

		// Add Support Page
		$hookname = get_plugin_page_hookname( self::ADMIN_SUPPORT_PAGE_SLUG, '' );
		if ( ! empty( $hookname ) ) {
			add_action( $hookname, __CLASS__ . '::support_page' );
		}

		$_registered_pages[ $hookname ] = true;

		$hookname = get_plugin_page_hookname( self::ADMIN_UPGRADE_PAGE_SLUG, '' );
		if ( ! empty( $hookname ) ) {
			add_action( $hookname, __CLASS__ . '::upgrade_page' );
		}

		$_registered_pages[ $hookname ] = true;
	}

	/**
	 * Support Page
	 */
	public static function support_page() {
		// Init sessions
		if ( session_status() === PHP_SESSION_NONE ) {
			@session_start();
		}

		wc_get_template(
			'admin/support.php',
			array(
				'form_url' => admin_url( 'admin-post.php' ),
				'action' => self::ADMIN_SUPPORT_PAGE_SLUG,
				'errors' => isset( $_SESSION['form_errors'] ) ? (array) $_SESSION['form_errors'] : array(),
				'notices' => isset( $_SESSION['form_notices'] ) ? (array) $_SESSION['form_notices'] : array(),
			),
			'',
			dirname( __FILE__ ) . '/../templates/'
		);

		// Reset the form
		$_SESSION['form_errors'] = array();
		$_SESSION['form_notices'] = array();
	}

	/**
	 * Upgrade Page
	 */
	public static function upgrade_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Run Database Update
		include_once( dirname( __FILE__ ) . '/class-wc-swedbank-pay-update.php' );
		WC_Swedbank_Pay_Update::update();

		echo esc_html__( 'Upgrade finished.', 'swedbank-pay-woocommerce-checkout' );
	}

	/**
	 * Upgrade Notice
	 */
	public static function upgrade_notice() {
		?>
		<div id="message" class="error">
			<p>
				<?php
				echo sprintf(
				    /* translators: 1: plugin name */                        esc_html__(
					    'Warning! %1$s requires to update the database structure.',
					    'swedbank-pay-woocommerce-checkout'
				    ),
					self::PLUGIN_NAME
				);

				echo ' ' . sprintf(
					/* translators: 1: start tag 2: end tag */                        esc_html__(
						'Please click %1$s here %2$s to start upgrade.',
						'swedbank-pay-woocommerce-checkout'
					),
						'<a href="' . esc_url( admin_url( 'admin.php?page=' . self::ADMIN_UPGRADE_PAGE_SLUG ) ) . '">',
						'</a>'
					);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Check if WooCommerce is missing, and deactivate the plugin if needs
	 */
	public static function missing_woocommerce_notice() {
		?>
        <div id="message" class="error">
            <p class="main">
                <strong><?php echo esc_html__( 'WooCommerce is inactive or missing.', 'swedbank-pay-woocommerce-checkout' ); ?></strong>
            </p>
            <p>
				<?php
				echo esc_html__( 'WooCommerce plugin is inactive or missing. Please install and active it.', 'swedbank-pay-woocommerce-checkout' );
				echo '<br />';
				echo sprintf(
				    /* translators: 1: plugin name */                        esc_html__(
					    '%1$s will be deactivated.',
					    'swedbank-pay-woocommerce-checkout'
				    ),
					self::PLUGIN_NAME
				);

				?>
            </p>
        </div>
		<?php

		// Deactivate the plugin
		deactivate_plugins( self::PLUGIN_PATH, true );
	}


	/**
	 * Check dependencies
	 */
	public static function check_dependencies() {
		$dependencies = array( 'curl', 'bcmath', 'json' );

		$errors = array();
		foreach ($dependencies as $dependency) {
			if ( ! extension_loaded( $dependency ) ) {
				$errors[] = sprintf( esc_html__( 'Extension %s is missing.', 'swedbank-pay-woocommerce-checkout' ), $dependency );
			}
		}

		if ( count( $errors ) > 0 ):
			?>
            <div id="message" class="error">
                <p class="main">
                    <strong><?php echo esc_html__( 'Required extensions are missing.', 'swedbank-pay-woocommerce-checkout' ); ?></strong>
                </p>
                <p>
					<?php
					foreach ( $errors as $error ) {
						echo $error;
					}
					echo '<br />';
					echo sprintf(
					/* translators: 1: plugin name */                        esc_html__(
						'%1$s requires that. Please configure PHP or contact the server administrator.',
						'swedbank-pay-woocommerce-checkout'
					),
						self::PLUGIN_NAME
					);

					?>
                </p>
            </div>
		<?php
		endif;
	}

	/**
	 * Check if "Number of decimals" of WooCommerce is configured incorrectly
	 */
	public static function wrong_decimals_notice() {
		?>
        <div id="message" class="error">
            <p class="main">
                <strong><?php echo esc_html__( 'Invalid value of "Number of decimals" detected.', 'swedbank-pay-woocommerce-checkout' ); ?></strong>
            </p>
            <p>
				<?php
				echo sprintf(
					/* translators: 1: start tag 2: end tag */                        esc_html__(
						'"Number of decimals" is configured with zero value. It creates problems with rounding and checkout. Please change it to "2" on %1$sSettings page%2$s.',
						'swedbank-pay-woocommerce-checkout'
					),
						'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=general' ) ) . '">',
						'</a>'
					);
				?>
            </p>
        </div>
		<?php
	}

	/**
	 * Send support message
	 */
	public static function support_submit() {
		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'support_submit' ) ) {
			exit( 'No naughty business' );
		}

		if ( ! extension_loaded( 'zip' ) ) {
			$_SESSION['form_errors'] = array();
			$_SESSION['form_errors'][] = __( 'zip extension is required to perform this operation.', 'swedbank-pay-woocommerce-checkout' );

			wp_redirect( $_POST['_wp_http_referer'] );
			return;
		}

		// Init sessions
		if ( session_status() === PHP_SESSION_NONE ) {
			@session_start();
		}

		// Validate the fields
		if ( empty( $_POST['email'] ) || empty( $_POST['message'] ) ) {
			$_SESSION['form_errors'] = array();
			$_SESSION['form_errors'][] = __( 'Invalid form data', 'swedbank-pay-woocommerce-checkout' );

			wp_redirect( $_POST['_wp_http_referer'] );
			return;
		}

		// Validate email
		if ( ! is_email( $_POST['email'] ) ) {
			$_SESSION['form_errors'] = array();
			$_SESSION['form_errors'][] = __( 'Invalid email', 'swedbank-pay-woocommerce-checkout' );

			wp_redirect( $_POST['_wp_http_referer'] );
			return;
		}

		// Export settings
		$settings = array();
		foreach ( self::PAYMENT_METHODS as $payment_method ) {
			$conf = get_option( 'woocommerce_' . $payment_method . '_settings' );
			if ( ! is_array( $conf ) ) {
				$conf = array();
			}

			$settings[ $payment_method ] = $conf;
		}

		$jsonSettings = get_temp_dir() . '/settings.json';
		file_put_contents( $jsonSettings, json_encode( $settings, JSON_PRETTY_PRINT ) );

		// Export system information
		$jsonReport = get_temp_dir() . '/wc-report.json';
		$report = wc()->api->get_endpoint_data( '/wc/v3/system_status' );
		file_put_contents( $jsonReport, json_encode( $report, JSON_PRETTY_PRINT ) );

		// Make zip
		$zipFile = WC_LOG_DIR . uniqid( 'swedbank' ) . '.zip';
		$zipArchive = new \ZipArchive();
		$zipArchive->open( $zipFile,  \ZipArchive::CREATE );

		// Add files
		$zipArchive->addFile( $jsonSettings, basename( $jsonSettings ) );
		$zipArchive->addFile( $jsonReport, basename( $jsonReport ) );

		// Add logs
		$files = self::get_log_files();
		foreach ($files as $file) {
			$zipArchive->addFile( WC_LOG_DIR . $file, basename( $file ) );
		}

		$zipArchive->close();

		// Get the plugin information
		$plugin = get_plugin_data( WP_PLUGIN_DIR . '/' . self::PLUGIN_PATH  );

		// Make message
		$message = sprintf(
			"Date: %s\nFrom: %s\nMessage: %s\nSite: %s\nPHP version: %s\nWC Version: %s\nWordPress Version: %s\nPlugin Name: %s\nPlugin Version: %s",
			date( 'Y-m-d H:i:s' ),
			$_POST['email'],
			$_POST['message'],
			get_option( 'siteurl' ),
			phpversion(),
			Constants::get_constant( 'WC_VERSION' ),
			get_bloginfo( 'version' ),
			$plugin['Name'],
			$plugin['Version']
		);

		// Send message
		$result = wp_mail(
			self::SUPPORT_EMAIL,
			'Site support: ' . get_option( 'siteurl' ),
			$message,
			array(
				'Reply-To: ' . $_POST['email'],
				'Content-Type: text/plain; charset=UTF-8'
			),
			array( $zipFile )
		);

		// Remove temporary files
		@unlink( $jsonSettings );
		@unlink( $zipFile );
		@unlink( $jsonReport );

		if ( ! $result ) {
			$_SESSION['form_errors'] = array();
			$_SESSION['form_errors'][] = __( 'Unable to send mail message.', 'swedbank-pay-woocommerce-checkout' );
		} else {
			$_SESSION['form_notices'] = array();
			$_SESSION['form_notices'][] = __( 'Your message has been sent.', 'swedbank-pay-woocommerce-checkout' );
		}

		wp_redirect( $_POST['_wp_http_referer'] );
	}

	/**
	 * Get log files.
	 *
	 * @return string[]
	 */
	private static function get_log_files() {
		$result = array();
		$files = \WC_Log_Handler_File::get_log_files();
		foreach ( $files as $file ) {
			foreach ( self::PAYMENT_METHODS as $payment_method ) {
				if ( strpos( $file, $payment_method ) !== false ||
				     strpos( $file, 'wc_swedbank' ) !== false ||
				     strpos( $file, 'fatal-errors' ) !== false
				) {
					$result[] = $file;
				}
			}
		}

		return $result;
	}
}
