<?php

namespace SwedbankPay\Checkout\WooCommerce;

use SwedbankPay\Core\Log\LogLevel;
use WC_Payment_Gateway;
use SwedbankPay\Core\PaymentAdapter;
use SwedbankPay\Core\PaymentAdapterInterface;
use SwedbankPay\Core\ConfigurationInterface;
use SwedbankPay\Core\Order\PlatformUrlsInterface;
use SwedbankPay\Core\OrderInterface;
use SwedbankPay\Core\OrderItemInterface;
use SwedbankPay\Core\Order\RiskIndicatorInterface;
use SwedbankPay\Core\Order\PayeeInfoInterface;

defined( 'ABSPATH' ) || exit;

class WC_Adapter extends PaymentAdapter implements PaymentAdapterInterface {
	/**
	 * @var WC_Payment_Gateway
	 */
	private $gateway;

	/**
	 * WC_Adapter constructor.
	 *
	 * @param WC_Payment_Gateway $gateway
	 */
	public function __construct( WC_Payment_Gateway $gateway ) {
		$this->gateway = $gateway;
	}

	/**
	 * Log a message.
	 *
	 * @param $level
	 * @param $message
	 * @param array $context
	 *
	 * @see WC_Log_Levels
	 */
	public function log( $level, $message, array $context = array() ) {
		$logger = wc_get_logger();

		if ( ! is_string( $message ) ) {
			$message = var_export( $message, true );
		}

		$logger->log(
			\WC_Log_Levels::INFO,
			sprintf( '%s %s %s', $level, $message, var_export( $context, true ) ),
			array(
				'source'  => $this->gateway->id,
				'_legacy' => true,
			)
		);
	}

	/**
	 * Get Adapter Configuration.
	 *
	 * @return array
	 */
	public function getConfiguration() {
		// @todo Fix it: Undefined property
		return array(
			ConfigurationInterface::DEBUG                  => 'yes' === $this->gateway->debug,
			ConfigurationInterface::MERCHANT_TOKEN         => $this->gateway->merchant_token,
			ConfigurationInterface::PAYEE_ID               => $this->gateway->payee_id,
			ConfigurationInterface::PAYEE_NAME             => get_bloginfo( 'name' ),
			ConfigurationInterface::MODE                   => 'yes' === $this->gateway->testmode,
			ConfigurationInterface::AUTO_CAPTURE           => 'yes' === $this->gateway->auto_capture,
			ConfigurationInterface::SUBSITE                => $this->gateway->subsite,
			ConfigurationInterface::LANGUAGE               => $this->gateway->culture,
			ConfigurationInterface::SAVE_CC                => 'yes' === $this->gateway->save_cc,
			ConfigurationInterface::TERMS_URL              => $this->gateway->terms_url,
			ConfigurationInterface::REJECT_CREDIT_CARDS    => 'yes' === $this->gateway->reject_credit_cards,
			ConfigurationInterface::REJECT_DEBIT_CARDS     => 'yes' === $this->gateway->reject_debit_cards,
			ConfigurationInterface::REJECT_CONSUMER_CARDS  => 'yes' === $this->gateway->reject_consumer_cards,
			ConfigurationInterface::REJECT_CORPORATE_CARDS => 'yes' === $this->gateway->reject_corporate_cards,
		);
	}

	/**
	 * Get Platform Urls of Actions of Order (complete, cancel, callback urls).
	 *
	 * @param mixed $order_id
	 *
	 * @return array
	 */
	public function getPlatformUrls( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( $this->gateway->is_new_credit_card ) {
			return array(
				PlatformUrlsInterface::COMPLETE_URL => add_query_arg(
					'action',
					'swedbank_card_store',
					admin_url( 'admin-ajax.php' )
				),
				PlatformUrlsInterface::CANCEL_URL   => wc_get_account_endpoint_url( 'payment-methods' ),
				PlatformUrlsInterface::CALLBACK_URL => WC()->api_request_url( get_class( $this->gateway ) ),
				PlatformUrlsInterface::TERMS_URL    => '',
			);
		}

		if ( $this->gateway->is_change_credit_card ) {
			return array(
				PlatformUrlsInterface::COMPLETE_URL => add_query_arg(
					array(
						'verify' => 'true',
						'key'    => $order->get_order_key(),
					),
					$this->gateway->get_return_url( $order )
				),
				PlatformUrlsInterface::CANCEL_URL   => $order->get_cancel_order_url_raw(),
				PlatformUrlsInterface::CALLBACK_URL => WC()->api_request_url( get_class( $this->gateway ) ),
				PlatformUrlsInterface::TERMS_URL    => $this->getConfiguration()[ ConfigurationInterface::TERMS_URL ],
			);
		}

		return array(
			PlatformUrlsInterface::COMPLETE_URL => $this->gateway->get_return_url( $order ),
			PlatformUrlsInterface::CANCEL_URL   => $order->get_cancel_order_url_raw(),
			PlatformUrlsInterface::CALLBACK_URL => WC()->api_request_url( get_class( $this->gateway ) ),
			PlatformUrlsInterface::TERMS_URL    => $this->getConfiguration()[ ConfigurationInterface::TERMS_URL ],
		);
	}

	/**
	 * Get Order Data.
	 *
	 * @param mixed $order_id
	 *
	 * @return array
	 */
	public function getOrderData( $order_id ) {
		$order = wc_get_order( $order_id );

		$countries = WC()->countries->countries;
		$states    = WC()->countries->states;

		// Order Info
		$info = $this->get_order_info( $order );

		// Get order items
		$items = array();

		foreach ( $order->get_items() as $order_item ) {
			/** @var \WC_Order_Item_Product $order_item */
			$price          = $order->get_line_subtotal( $order_item, false, false );
			$price_with_tax = $order->get_line_subtotal( $order_item, true, false );
			$tax            = $price_with_tax - $price;
			$tax_percent    = ( $tax > 0 ) ? round( 100 / ( $price / $tax ) ) : 0;
			$qty            = $order_item->get_quantity();
			$image          = wp_get_attachment_image_src( $order_item->get_product()->get_image_id(), 'full' );

			if ( $image ) {
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

			// Get Product Sku
			$product_reference = trim( str_replace( array( ' ', '.', ',' ), '-', $order_item->get_product()->get_sku() ) );
			if ( empty( $product_reference ) ) {
				$product_reference = wp_generate_password( 12, false );
			}

			$product_name = trim( $order_item->get_name() );

			$items[] = array(
				// The field Reference must match the regular expression '[\\w-]*'
				OrderItemInterface::FIELD_REFERENCE   => $product_reference,
				OrderItemInterface::FIELD_NAME        => ! empty( $product_name ) ? $product_name : '-',
				OrderItemInterface::FIELD_TYPE        => OrderItemInterface::TYPE_PRODUCT,
				OrderItemInterface::FIELD_CLASS       => $product_class,
				OrderItemInterface::FIELD_ITEM_URL    => $order_item->get_product()->get_permalink(),
				OrderItemInterface::FIELD_IMAGE_URL   => $image,
				OrderItemInterface::FIELD_DESCRIPTION => $order_item->get_name(),
				OrderItemInterface::FIELD_QTY         => $qty,
				OrderItemInterface::FIELD_QTY_UNIT    => 'pcs',
				OrderItemInterface::FIELD_UNITPRICE   => round( $price_with_tax / $qty * 100 ),
				OrderItemInterface::FIELD_VAT_PERCENT => round( $tax_percent * 100 ),
				OrderItemInterface::FIELD_AMOUNT      => round( $price_with_tax * 100 ),
				OrderItemInterface::FIELD_VAT_AMOUNT  => round( $tax * 100 ),
			);
		}

		// Add Shipping Line
		if ( (float) $order->get_shipping_total() > 0 ) {
			$shipping          = $order->get_shipping_total();
			$tax               = $order->get_shipping_tax();
			$shipping_with_tax = $shipping + $tax;
			$tax_percent       = ( $tax > 0 ) ? round( 100 / ( $shipping / $tax ) ) : 0;
			$shipping_method   = trim( $order->get_shipping_method() );

			$items[] = array(
				OrderItemInterface::FIELD_REFERENCE   => 'shipping',
				OrderItemInterface::FIELD_NAME        => ! empty( $shipping_method ) ? $shipping_method : __(
					'Shipping',
					'woocommerce'
				),
				OrderItemInterface::FIELD_TYPE        => OrderItemInterface::TYPE_SHIPPING,
				OrderItemInterface::FIELD_CLASS       => 'ProductGroup1',
				OrderItemInterface::FIELD_QTY         => 1,
				OrderItemInterface::FIELD_QTY_UNIT    => 'pcs',
				OrderItemInterface::FIELD_UNITPRICE   => round( $shipping_with_tax * 100 ),
				OrderItemInterface::FIELD_VAT_PERCENT => round( $tax_percent * 100 ),
				OrderItemInterface::FIELD_AMOUNT      => round( $shipping_with_tax * 100 ),
				OrderItemInterface::FIELD_VAT_AMOUNT  => round( $tax * 100 ),
			);
		}

		// Add fee lines
		foreach ( $order->get_fees() as $order_fee ) {
			/** @var \WC_Order_Item_Fee $order_fee */
			$fee          = $order_fee->get_total();
			$tax          = $order_fee->get_total_tax();
			$fee_with_tax = $fee + $tax;
			$tax_percent  = ( $tax > 0 ) ? round( 100 / ( $fee / $tax ) ) : 0;

			$items[] = array(
				OrderItemInterface::FIELD_REFERENCE   => 'fee',
				OrderItemInterface::FIELD_NAME        => $order_fee->get_name(),
				OrderItemInterface::FIELD_TYPE        => OrderItemInterface::TYPE_OTHER,
				OrderItemInterface::FIELD_CLASS       => 'ProductGroup1',
				OrderItemInterface::FIELD_QTY         => 1,
				OrderItemInterface::FIELD_QTY_UNIT    => 'pcs',
				OrderItemInterface::FIELD_UNITPRICE   => round( $fee_with_tax * 100 ),
				OrderItemInterface::FIELD_VAT_PERCENT => round( $tax_percent * 100 ),
				OrderItemInterface::FIELD_AMOUNT      => round( $fee_with_tax * 100 ),
				OrderItemInterface::FIELD_VAT_AMOUNT  => round( $tax * 100 ),
			);
		}

		// Add discount line
		if ( $order->get_total_discount( false ) > 0 ) {
			$discount          = abs( $order->get_total_discount( true ) );
			$discount_with_tax = abs( $order->get_total_discount( false ) );
			$tax               = $discount_with_tax - $discount;
			$tax_percent       = ( $tax > 0 ) ? round( 100 / ( $discount / $tax ) ) : 0;

			$items[] = array(
				OrderItemInterface::FIELD_REFERENCE   => 'discount',
				OrderItemInterface::FIELD_NAME        => __( 'Discount', 'swedbank-pay-woocommerce-payments' ),
				OrderItemInterface::FIELD_TYPE        => OrderItemInterface::TYPE_DISCOUNT,
				OrderItemInterface::FIELD_CLASS       => 'ProductGroup1',
				OrderItemInterface::FIELD_QTY         => 1,
				OrderItemInterface::FIELD_QTY_UNIT    => 'pcs',
				OrderItemInterface::FIELD_UNITPRICE   => round( - 100 * $discount_with_tax ),
				OrderItemInterface::FIELD_VAT_PERCENT => round( 100 * $tax_percent ),
				OrderItemInterface::FIELD_AMOUNT      => round( - 100 * $discount_with_tax ),
				OrderItemInterface::FIELD_VAT_AMOUNT  => round( - 100 * $tax ),
			);
		}

		// Payer reference
		// Get Customer UUID
		$user_id = $order->get_customer_id();
		if ( $user_id > 0 ) {
			$payer_reference = get_user_meta( $user_id, '_payex_customer_uuid', true );
			if ( empty( $payer_reference ) ) {
				$payer_reference = $this->get_uuid( $user_id );
				update_user_meta( $user_id, '_payex_customer_uuid', $payer_reference );
			}
		} else {
			$payer_reference = $this->get_uuid( uniqid( $order->get_billing_email() ) );
		}

		$shipping_country = isset( $countries[ $order->get_shipping_country() ] ) ? $countries[ $order->get_shipping_country() ] : '';
		$billing_country  = isset( $countries[ $order->get_billing_country() ] ) ? $countries[ $order->get_billing_country() ] : '';

		return array(
			OrderInterface::ORDER_ID              => $order->get_id(),
			OrderInterface::AMOUNT                => apply_filters(
				'swedbank_pay_order_amount',
				$order->get_total(),
				$order
			),
			OrderInterface::VAT_AMOUNT            => apply_filters(
				'swedbank_pay_order_vat',
				$info['vat_amount'],
				$order
			),
			OrderInterface::VAT_RATE              => 0, // Can be different
			OrderInterface::SHIPPING_AMOUNT       => 0, // @todo
			OrderInterface::SHIPPING_VAT_AMOUNT   => 0, // @todo
			OrderInterface::DESCRIPTION           => sprintf(
			/* translators: 1: order id */                __( 'Order #%1$s', 'swedbank-pay-woocommerce-payments' ),
				$order->get_order_number()
			),
			OrderInterface::CURRENCY              => $order->get_currency(),
			OrderInterface::STATUS                => $order->get_status(),
			OrderInterface::CREATED_AT            => gmdate( 'Y-m-d H:i:s', $order->get_date_created()->getTimestamp() ),
			OrderInterface::PAYMENT_ID            => $order->get_meta( '_payex_payment_id' ),
			OrderInterface::PAYMENT_ORDER_ID      => $order->get_meta( '_payex_paymentorder_id' ),
			OrderInterface::NEEDS_SAVE_TOKEN_FLAG => '1' === $order->get_meta( '_payex_generate_token' ) &&
													 0 === count( $order->get_payment_tokens() ),

			OrderInterface::HTTP_ACCEPT           => isset( $_SERVER['HTTP_ACCEPT'] ) ? $_SERVER['HTTP_ACCEPT'] : null,
			OrderInterface::HTTP_USER_AGENT       => $order->get_customer_user_agent(),
			OrderInterface::BILLING_COUNTRY       => $billing_country,
			OrderInterface::BILLING_COUNTRY_CODE  => $order->get_billing_country(),
			OrderInterface::BILLING_ADDRESS1      => $order->get_billing_address_1(),
			OrderInterface::BILLING_ADDRESS2      => $order->get_billing_address_2(),
			OrderInterface::BILLING_ADDRESS3      => null,
			OrderInterface::BILLING_CITY          => $order->get_billing_city(),
			OrderInterface::BILLING_STATE         => $order->get_billing_state(),
			OrderInterface::BILLING_POSTCODE      => $order->get_billing_postcode(),
			OrderInterface::BILLING_PHONE         => $order->get_billing_phone(),
			OrderInterface::BILLING_EMAIL         => $order->get_billing_email(),
			OrderInterface::BILLING_FIRST_NAME    => $order->get_billing_first_name(),
			OrderInterface::BILLING_LAST_NAME     => $order->get_billing_last_name(),
			OrderInterface::SHIPPING_COUNTRY      => $shipping_country,
			OrderInterface::SHIPPING_COUNTRY_CODE => $order->get_shipping_country(),
			OrderInterface::SHIPPING_ADDRESS1     => $order->get_shipping_address_1(),
			OrderInterface::SHIPPING_ADDRESS2     => $order->get_shipping_address_2(),
			OrderInterface::SHIPPING_ADDRESS3     => null,
			OrderInterface::SHIPPING_CITY         => $order->get_shipping_city(),
			OrderInterface::SHIPPING_STATE        => $order->get_shipping_state(),
			OrderInterface::SHIPPING_POSTCODE     => $order->get_shipping_postcode(),
			OrderInterface::SHIPPING_PHONE        => $order->get_billing_phone(),
			OrderInterface::SHIPPING_EMAIL        => $order->get_billing_email(),
			OrderInterface::SHIPPING_FIRST_NAME   => $order->get_shipping_first_name(),
			OrderInterface::SHIPPING_LAST_NAME    => $order->get_shipping_last_name(),
			OrderInterface::CUSTOMER_ID           => (int) $order->get_customer_id(),
			OrderInterface::CUSTOMER_IP           => $order->get_customer_ip_address(),
			OrderInterface::PAYER_REFERENCE       => $payer_reference,
			OrderInterface::ITEMS                 => apply_filters( 'swedbank_pay_order_items', $items, $order ),
			OrderInterface::LANGUAGE              => $this->getConfiguration()[ ConfigurationInterface::LANGUAGE ],
		);
	}

	/**
	 * Get Risk Indicator of Order.
	 *
	 * @param mixed $order_id
	 *
	 * @return array
	 */
	public function getRiskIndicator( $order_id ) {
		$order = wc_get_order( $order_id );

		$result = array();

		// Downloadable
		if ( $order->has_downloadable_item() ) {
			// For electronic delivery, the email address to which the merchandise was delivered
			$result[ RiskIndicatorInterface::DELIVERY_EMAIL_ADDRESS ] = $order->get_billing_email();

			// Electronic Delivery
			$result[ RiskIndicatorInterface::DELIVERY_TIME_FRAME_INDICATOR ] = '01';

			// Digital goods, includes online services, electronic giftcards and redemption codes
			$result[ RiskIndicatorInterface::SHIP_INDICATOR ] = '05';
		}

		// Shippable
		if ( $order->needs_processing() ) {
			// Two-day or more shipping
			$result['deliveryTimeFrameIndicator'] = '04';

			// Compare billing and shipping addresses
			$billing  = $order->get_address( 'billing' );
			$shipping = $order->get_address( 'shipping' );
			$diff     = array_diff( $billing, $shipping );
			if ( 0 === count( $diff ) ) {
				// Ship to cardholder's billing address
				$result[ RiskIndicatorInterface::SHIP_INDICATOR ] = '01';
			} else {
				// Ship to address that is different than cardholder's billing address
				$result[ RiskIndicatorInterface::SHIP_INDICATOR ] = '03';
			}
		}

		// @todo Add features of WooThemes Order Delivery and Pre-Orders WooCommerce Extensions

		return apply_filters( 'swedbank_pay_risk_indicator', $result, $order, $this );
	}

	/**
	 * Get Payee Info of Order.
	 *
	 * @param mixed $order_id
	 *
	 * @return array
	 */
	public function getPayeeInfo( $order_id ) {
		$order = wc_get_order( $order_id );

		return array(
			PayeeInfoInterface::ORDER_REFERENCE => $order->get_id(),
		);
	}

	/**
	 * Update Order Status.
	 *
	 * @param mixed $order_id
	 * @param string $status
	 * @param string|null $message
	 * @param mixed|null $transaction_id
	 */
	public function updateOrderStatus( $order_id, $status, $message = null, $transaction_id = null ) {
		$order = wc_get_order( $order_id );

		if ( $order->get_meta( '_payex_payment_state' ) === $status ) {
			$this->log( LogLevel::WARNING, sprintf( 'Action of Transaction #%s already performed', $transaction_id ) );

			return;
		}

		if ( $transaction_id ) {
			$order->update_meta_data( '_transaction_id', $transaction_id );
			$order->save_meta_data();
		}

		switch ( $status ) {
			case OrderInterface::STATUS_PENDING:
				$order->update_meta_data( '_payex_payment_state', $status );
				$order->update_status( 'on-hold', $message );
				break;
			case OrderInterface::STATUS_AUTHORIZED:
				$order->update_meta_data( '_payex_payment_state', $status );
				$order->save_meta_data();

				// Reduce stock
				$order_stock_reduced = $order->get_meta( '_order_stock_reduced' );
				if ( ! $order_stock_reduced ) {
					wc_reduce_stock_levels( $order->get_id() );
				}

				$order->update_status( 'on-hold', $message );

				break;
			case OrderInterface::STATUS_CAPTURED:
				$order->update_meta_data( '_payex_payment_state', $status );
				$order->save_meta_data();

				$order->payment_complete( $transaction_id );
				$order->add_order_note( $message );
				break;
			case OrderInterface::STATUS_CANCELLED:
				$order->update_meta_data( '_payex_payment_state', $status );
				$order->save_meta_data();

				if ( ! $order->has_status( 'cancelled' ) ) {
					$order->update_status( 'cancelled', $message );
				} else {
					$order->add_order_note( $message );
				}
				break;
			case OrderInterface::STATUS_REFUNDED:
				// @todo Implement Refunds creation
				// @see wc_create_refund()

				$order->update_meta_data( '_payex_payment_state', $status );
				$order->save_meta_data();

				if ( ! $order->has_status( 'refunded' ) ) {
					$order->update_status( 'refunded', $message );
				} else {
					$order->add_order_note( $message );
				}

				break;
			case OrderInterface::STATUS_FAILED:
				$order->update_status( 'failed', $message );
				break;
		}
	}

	/**
	 * Save Transaction data.
	 *
	 * @param mixed $order_id
	 * @param array $transaction_data
	 */
	public function saveTransaction( $order_id, array $transaction_data = array() ) {
		$this->gateway->transactions->import( $transaction_data, $order_id );
	}

	/**
	 * Find for Transaction.
	 *
	 * @param $field
	 * @param $value
	 *
	 * @return array
	 */
	public function findTransaction( $field, $value ) {
		return $this->gateway->transactions->get_by( $field, $value, true );
	}

	/**
	 * Save Payment Token.
	 *
	 * @param mixed $customer_id
	 * @param string $payment_token
	 * @param string $recurrence_token
	 * @param string $card_brand
	 * @param string $masked_pan
	 * @param string $expiry_date
	 * @param mixed|null $order_id
	 */
	public function savePaymentToken(
		$customer_id,
		$payment_token,
		$recurrence_token,
		$card_brand,
		$masked_pan,
		$expiry_date,
		$order_id = null
	) {
		$expiry_date = explode( '/', $expiry_date );

		// Create Payment Token
		$token = new \WC_Payment_Token_Swedbank_Pay();
		$token->set_gateway_id( $this->gateway->id );
		$token->set_token( $payment_token );
		$token->set_recurrence_token( $recurrence_token );
		$token->set_last4( substr( $masked_pan, - 4 ) );
		$token->set_expiry_year( $expiry_date[1] );
		$token->set_expiry_month( $expiry_date[0] );
		$token->set_card_type( strtolower( $card_brand ) );
		$token->set_user_id( $customer_id );
		$token->set_masked_pan( $masked_pan );
		$token->save();
		if ( ! $token->get_id() ) {
			throw new \Exception( __( 'There was a problem adding the card.', 'swedbank-pay-woocommerce-payments' ) );
		}

		// Add payment token
		if ( $order_id ) {
			$order = wc_get_order( $order_id );
			$order->add_payment_token( $token );
		}
	}

	/**
	 * Get Order Lines
	 *
	 * @param \WC_Order $order
	 *
	 * @return array
	 */
	private function get_order_items( $order ) {
		$item = array();

		foreach ( $order->get_items() as $order_item ) {
			/** @var \WC_Order_Item_Product $order_item */
			$price          = $order->get_line_subtotal( $order_item, false, false );
			$price_with_tax = $order->get_line_subtotal( $order_item, true, false );
			$tax            = $price_with_tax - $price;
			$tax_percent    = ( $tax > 0 ) ? round( 100 / ( $price / $tax ) ) : 0;

			$item[] = array(
				'type'              => 'product',
				'name'              => $order_item->get_name(),
				'qty'               => $order_item->get_quantity(),
				'price_with_tax'    => sprintf( '%.2f', $price_with_tax ),
				'price_without_tax' => sprintf( '%.2f', $price ),
				'tax_price'         => sprintf( '%.2f', $tax ),
				'tax_percent'       => sprintf( '%.2f', $tax_percent ),
			);
		};

		// Add Shipping Line
		if ( (float) $order->get_shipping_total() > 0 ) {
			$shipping          = $order->get_shipping_total();
			$tax               = $order->get_shipping_tax();
			$shipping_with_tax = $shipping + $tax;
			$tax_percent       = ( $tax > 0 ) ? round( 100 / ( $shipping / $tax ) ) : 0;

			$item[] = array(
				'type'              => 'shipping',
				'name'              => $order->get_shipping_method(),
				'qty'               => 1,
				'price_with_tax'    => sprintf( '%.2f', $shipping_with_tax ),
				'price_without_tax' => sprintf( '%.2f', $shipping ),
				'tax_price'         => sprintf( '%.2f', $tax ),
				'tax_percent'       => sprintf( '%.2f', $tax_percent ),
			);
		}

		// Add fee lines
		foreach ( $order->get_fees() as $order_fee ) {
			/** @var \WC_Order_Item_Fee $order_fee */
			$fee          = $order_fee->get_total();
			$tax          = $order_fee->get_total_tax();
			$fee_with_tax = $fee + $tax;
			$tax_percent  = ( $tax > 0 ) ? round( 100 / ( $fee / $tax ) ) : 0;

			$item[] = array(
				'type'              => 'fee',
				'name'              => $order_fee->get_name(),
				'qty'               => 1,
				'price_with_tax'    => sprintf( '%.2f', $fee_with_tax ),
				'price_without_tax' => sprintf( '%.2f', $fee ),
				'tax_price'         => sprintf( '%.2f', $tax ),
				'tax_percent'       => sprintf( '%.2f', $tax_percent ),
			);
		}

		// Add discount line
		if ( $order->get_total_discount( false ) > 0 ) {
			$discount          = $order->get_total_discount( true );
			$discount_with_tax = $order->get_total_discount( false );
			$tax               = $discount_with_tax - $discount;
			$tax_percent       = ( $tax > 0 ) ? round( 100 / ( $discount / $tax ) ) : 0;

			$item[] = array(
				'type'              => 'discount',
				'name'              => __( 'Discount', 'swedbank-pay-woocommerce-payments' ),
				'qty'               => 1,
				'price_with_tax'    => sprintf( '%.2f', - 1 * $discount_with_tax ),
				'price_without_tax' => sprintf( '%.2f', - 1 * $discount ),
				'tax_price'         => sprintf( '%.2f', - 1 * $tax ),
				'tax_percent'       => sprintf( '%.2f', $tax_percent ),
			);
		}

		return $item;
	}

	/**
	 * Get Order Info
	 *
	 * @param \WC_Order $order
	 *
	 * @return array
	 */
	private function get_order_info( $order ) {
		$amount       = 0;
		$vat_amount   = 0;
		$descriptions = array();
		$items        = $this->get_order_items( $order );
		foreach ( $items as $item ) {
			$amount        += $item['price_with_tax'];
			$vat_amount    += $item['tax_price'];
			$descriptions[] = array(
				'amount'      => $item['price_with_tax'],
				'vatAmount'   => $item['tax_price'], // @todo Validate
				'itemAmount'  => sprintf( '%.2f', $item['price_with_tax'] / $item['qty'] ),
				'quantity'    => $item['qty'],
				'description' => $item['name'],
			);
		}

		return array(
			'amount'     => $amount,
			'vat_amount' => $vat_amount,
			'items'      => $descriptions,
		);
	}

	/**
	 * Generate UUID
	 *
	 * @param $node
	 *
	 * @return string
	 */
	private function get_uuid( $node ) {
		//return \Ramsey\Uuid\Uuid::uuid5( \Ramsey\Uuid\Uuid::NAMESPACE_OID, $node )->toString();
		return apply_filters( 'swedbank_pay_generate_uuid', $node );
	}
}

