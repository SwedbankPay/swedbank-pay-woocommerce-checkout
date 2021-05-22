<?php

namespace SwedbankPay\Checkout\WooCommerce;

defined( 'ABSPATH' ) || exit;

use WC_Payment_Gateway;
use WC_Subscription;
use WC_Order;
use Exception;

use WC_Gateway_Swedbank_Pay_Checkout;

class WC_Swedbank_Subscriptions {
	const PAYMENT_ID = 'payex_checkout';

	public function __construct() {
		// Add payment token when subscription was created
		add_action( 'woocommerce_payment_complete', __CLASS__ . '::add_subscription_card_id', 10, 1 );
		add_action(
			'woocommerce_payment_complete_order_status_on-hold',
			__CLASS__ . '::add_subscription_card_id',
			10,
			1
		);

		// Update failing payment method
		add_action(
			'woocommerce_subscription_failing_payment_method_updated_' . self::PAYMENT_ID,
			__CLASS__ . '::update_failing_payment_method',
			10,
			1
		);

		// Don't transfer customer meta to resubscribe orders
		add_action( 'wcs_resubscribe_order_created', __CLASS__ . '::delete_resubscribe_meta', 10 );

		// Allow store managers to manually set card id as the payment method on a subscription
		add_filter( 'woocommerce_subscription_payment_meta', __CLASS__ . '::add_subscription_payment_meta', 10, 2 );

		// Customize credit cards in admin
		add_action(
			'woocommerce_subscription_payment_meta_input_' . self::PAYMENT_ID . '_swedbankpay_meta_token_id',
			__CLASS__ . '::payment_meta_input',
			10,
			4
		);

		// Validate the payment meta data
		add_action(
			'woocommerce_subscription_validate_payment_meta',
			__CLASS__ . '::validate_subscription_payment_meta',
			10,
			3
		);

		// Save payment method meta data for the Subscription
		add_action( 'wcs_save_other_payment_meta', __CLASS__ . '::save_subscription_payment_meta', 10, 4 );

		// Charge the payment when a subscription payment is due
		add_action(
			'woocommerce_scheduled_subscription_payment_'  . self::PAYMENT_ID,
			__CLASS__ . '::scheduled_subscription_payment',
			10,
			2
		);

		// Display the credit card used for a subscription in the "My Subscriptions" table
		add_filter(
			'woocommerce_my_subscriptions_payment_method',
			__CLASS__ . '::maybe_render_subscription_payment_method',
			10,
			2
		);

		// Lock "Save card" if needs
		add_filter(
			'woocommerce_payment_gateway_save_new_payment_method_option_html',
			__CLASS__ . '::save_new_payment_method_option_html',
			10,
			2
		);
	}

	/**
	 * Add Card ID when Subscription was created
	 *
	 * @param $order_id
	 */
	public static function add_subscription_card_id( $order_id ) {
		if ( ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		$tokens = $order->get_payment_tokens();
		if ( count( $tokens ) > 0 ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order_id, array( 'order_type' => 'parent' ) );
			foreach ( $subscriptions as $subscription ) {
				/** @var WC_Subscription $subscription */

				foreach ( $tokens as $token_id ) {
					$token = new WC_Payment_Token_Swedbank_Pay( $token_id );
					if ( self::PAYMENT_ID !== $token->get_gateway_id() ) {
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
	public static function update_failing_payment_method( $subscription, $renewal_order ) {
		// Delete tokens
		//delete_post_meta( $subscription->get_id(), '_payment_tokens' );
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
	public static function delete_resubscribe_meta( $resubscribe_order ) {
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
	public static function add_subscription_payment_meta( $payment_meta, $subscription ) {
		$payment_meta[ self::PAYMENT_ID ] = array(
			'swedbankpay_meta' => array(
				'token_id' => array(
					'value' => implode( ',', $subscription->get_payment_tokens() ),
					'label' => __( 'Card Token ID', 'swedbank-pay-woocommerce-checkout' ),
				),
			),
		);

		return $payment_meta;
	}

	/**
	 * @param WC_Subscription $subscription
	 * @param $field_id
	 * @param $field_value
	 * @param $meta_data
	 */
	public static function payment_meta_input( $subscription, $field_id, $field_value, $meta_data ) {
		$tokens = \WC_Payment_Tokens::get_tokens( array(
			'gateway_id' => self::PAYMENT_ID,
			'user_id' => $subscription->get_user_id()
		) );

		echo '<select class="short" name="' . esc_attr( $field_id ) . '" id="' . esc_attr( $field_id ) . '>';

		foreach ($tokens as $token):
			/** @var WC_Payment_Token_Swedbank_Pay $token */

			$selected = $field_value == $token->get_id() ? ' selected ' : '';
			echo '<option value="' . esc_attr( $token->get_id() ) . '" ' . $selected . ' >' .
			     esc_html(
			     	$token->get_meta( 'masked_pan' ) .
			        '(' . $token->get_expiry_month() . '/' . substr( $token->get_expiry_year(), 2 ) . ')'
			     ) .
			     '</option>'
			?>
			<?php
		endforeach;

		echo '</select>';
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
	public static function validate_subscription_payment_meta( $payment_method_id, $payment_meta, $subscription ) {
		if ( $payment_method_id === self::PAYMENT_ID ) {
			if ( empty( $payment_meta['swedbankpay_meta']['token_id']['value'] ) ) {
				throw new Exception( 'A "Card Token ID" value is required.' );
			}

			$tokens = explode( ',', $payment_meta['swedbankpay_meta']['token_id']['value'] );
			foreach ( $tokens as $token_id ) {
				$token = new WC_Payment_Token_Swedbank_Pay( $token_id );
				if ( ! $token->get_id() ) {
					throw new Exception( 'This "Card Token ID" value not found.' );
				}

				if ( $token->get_gateway_id() !== self::PAYMENT_ID ) {
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
	public static function save_subscription_payment_meta( $subscription, $meta_table, $meta_key, $meta_value ) {
		if ( $subscription->get_payment_method() !== self::PAYMENT_ID ) {
			return;
		}

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

	/**
	 * When a subscription payment is due.
	 *
	 * @param          $amount_to_charge
	 * @param WC_Order $renewal_order
	 */
	public static function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
		$user_agent = $renewal_order->get_customer_user_agent();
		if ( empty( $user_agent ) ) {
			$renewal_order->set_customer_user_agent( 'WooCommerce/' . WC()->version );
			$renewal_order->save();
		}

		try {
			$tokens = $renewal_order->get_payment_tokens();

			foreach ( $tokens as $token_id ) {
				$token = new WC_Payment_Token_Swedbank_Pay( $token_id );
				if ( $token->get_gateway_id() !== self::PAYMENT_ID ) {
					continue;
				}

				if ( ! $token->get_id() ) {
					throw new Exception( 'Invalid Token Id' );
				}

				$gateway = self::get_payment_gateway();
				$gateway->process_recurring_payment( $renewal_order, $token->get_recurrence_token() );

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
	public static function maybe_render_subscription_payment_method( $payment_method_to_display, $subscription ) {
		if ( self::PAYMENT_ID !== $subscription->get_payment_method() || ! $subscription->get_user_id() ) {
			return $payment_method_to_display;
		}

		$tokens = $subscription->get_payment_tokens();
		foreach ( $tokens as $token_id ) {
			$token = new WC_Payment_Token_Swedbank_Pay( $token_id );
			if ( $token->get_gateway_id() !== self::PAYMENT_ID ) {
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
	public static function save_new_payment_method_option_html( $html, $gateway ) {
		if ( self::PAYMENT_ID !== $gateway->id ) {
			return $html;
		}

		// Lock "Save to Account" for Recurring Payments / Payment Change
		if ( self::wcs_cart_has_subscription() || self::wcs_is_payment_change() ) {
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

	/**
	 * Get Payment Method Instance.
	 *
	 * @return WC_Gateway_Swedbank_Pay_Checkout
	 * @throws Exception
	 */
	private static function get_payment_gateway() {
		$gateways = WC()->payment_gateways()->get_available_payment_gateways();

		if ( isset( $gateways[ self::PAYMENT_ID ] ) ) {
			return $gateways[ self::PAYMENT_ID ];
		}

		throw new \Exception( 'The checkout payment gateway is unavailable.' );
	}


	/**
	 * Check is Cart have Subscription Products.
	 *
	 * @return bool
	 */
	private static function wcs_cart_has_subscription() {
		if ( ! class_exists( 'WC_Subscriptions_Product' ) ) {
			return false;
		}

		// Check is Recurring Payment
		$cart = WC()->cart->get_cart();
		foreach ( $cart as $item ) {
			if ( is_object( $item['data'] ) && \WC_Subscriptions_Product::is_subscription( $item['data'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * WC Subscriptions: Is Payment Change.
	 *
	 * @return bool
	 */
	private static function wcs_is_payment_change() {
		return class_exists( '\\WC_Subscriptions_Change_Payment_Gateway', false )
		       && \WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment;
	}
}

new WC_Swedbank_Subscriptions();