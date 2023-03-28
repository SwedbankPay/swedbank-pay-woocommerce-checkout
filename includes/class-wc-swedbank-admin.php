<?php

namespace SwedbankPay\Checkout\WooCommerce;

defined( 'ABSPATH' ) || exit;

use WC_Order;
use Exception;

class WC_Swedbank_Admin {
	/**
	 * Constructor
	 */
	public function __construct() {
		// Add statuses for payment complete
		add_filter(
			'woocommerce_valid_order_statuses_for_payment_complete',
			array( $this, 'add_valid_order_statuses' ),
			10,
			2
		);

		// Add meta boxes
		add_action( 'add_meta_boxes', __CLASS__ . '::add_meta_boxes', 10, 2 );

		// Add action buttons
		add_action( 'woocommerce_order_item_add_action_buttons', __CLASS__ . '::add_action_buttons', 10, 1 );

		// Add scripts and styles for admin
		add_action( 'admin_enqueue_scripts', __CLASS__ . '::admin_enqueue_scripts' );

		// Add Admin Backend Actions
		add_action( 'wp_ajax_swedbank_pay_capture', array( $this, 'ajax_swedbank_pay_capture' ) );
		add_action( 'wp_ajax_swedbank_pay_cancel', array( $this, 'ajax_swedbank_pay_cancel' ) );
		add_action( 'wp_ajax_swedbank_pay_refund', array( $this, 'ajax_swedbank_pay_refund' ) );
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
		if ( in_array( $payment_method, WC_Swedbank_Plugin::PAYMENT_METHODS, true ) ) {
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
	 * @param $screen_id
	 * @param WC_Order|\WP_Post $order
	 * @return void
	 */
	public static function add_meta_boxes( $screen_id, $order ) {
		$hook_to_check = sb_is_hpos_enabled() ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
		if ( $hook_to_check === $screen_id ) {
			$order          = wc_get_order( $order );
			$payment_method = $order->get_payment_method();
			if ( in_array( $payment_method, WC_Swedbank_Plugin::PAYMENT_METHODS, true ) ) {
				$payment_id = $order->get_meta( '_payex_payment_id' );
				if ( ! empty( $payment_id ) ) {
					$screen = sb_is_hpos_enabled() ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';

					add_meta_box(
						'swedbank_payment_actions',
						__( 'Swedbank Pay Payments Actions', 'swedbank-pay-woocommerce-checkout' ),
						__CLASS__ . '::order_meta_box_payment_actions',
						$screen,
						'side',
						'high'
					);
				}
			}
		}
	}

	/**
	 * MetaBox for Payment Actions
	 * @param WC_Order|\WP_Post $order
	 * @return void
	 */
	public static function order_meta_box_payment_actions( $order ) {
		$order      = wc_get_order( $order );
 		$payment_id = $order->get_meta( '_payex_payment_id' );
		if ( empty( $payment_id ) ) {
			return;
		}

		// Get Payment Gateway
		$gateway = self::get_payment_method( $order );
		if ( ! $gateway ) {
			return;
		}

		// Fetch payment info
		try {
			/** @var \WC_Gateway_Swedbank_Pay_Checkout $gateway */
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
				'order_id'   => $order->get_id(),
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
		if ( function_exists( 'wcs_is_subscription' ) && wcs_is_subscription( $order ) ) {
			// Buttons are available for orders only
			return;
		}

		// Get Payment Gateway
		$payment_method = $order->get_payment_method();
		if ( in_array( $payment_method, WC_Swedbank_Plugin::PAYMENT_METHODS, true ) ) {
			// Get Payment Gateway
			$gateway = self::get_payment_method( $order );
			if ( ! $gateway ) {
				return;
			}

			/** @var \WC_Gateway_Swedbank_Pay_Checkout $gateway */
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

	/**
	 * Enqueue Scripts in admin
	 *
	 * @param $hook
	 *
	 * @return void
	 */
	public static function admin_enqueue_scripts( $hook ) {
		$hook_to_check = sb_is_hpos_enabled() ? wc_get_page_screen_id( 'shop-order' ) : 'post.php';
		if ( $hook_to_check === $hook ) {
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
		$gateway = self::get_payment_method( $order );
		if ( $gateway ) {
			try {
				$gateway->capture_payment( $order_id );
				wp_send_json_success( __( 'Capture success.', 'swedbank-pay-woocommerce-checkout' ) );
			} catch ( Exception $e ) {
				wp_send_json_error( $e->getMessage() );
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
		$gateway = self::get_payment_method( $order );
		if ( $gateway ) {
			try {
				$gateway->cancel_payment( $order_id );
				wp_send_json_success( __( 'Cancel success.', 'swedbank-pay-woocommerce-checkout' ) );
			} catch ( Exception $e ) {
				wp_send_json_error( $e->getMessage() );
			}
		}
	}

	/**
	 * Action for Full Refund
	 */
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
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * Get Payment Method.
	 *
	 * @param WC_Order $order
	 *
	 * @return false|\WC_Payment_Gateway
	 */
	private static function get_payment_method( WC_Order $order ) {
		// Get Payment Gateway
		$gateways = WC()->payment_gateways()->payment_gateways();
		if ( ! isset( $gateways[ $order->get_payment_method() ] ) ) {
			return false;
		}

		/** @var \WC_Payment_Gateway $gateway */
		return $gateways[ $order->get_payment_method() ];
	}
}

new WC_Swedbank_Admin();
