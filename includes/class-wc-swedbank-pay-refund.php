<?php

namespace SwedbankPay\Checkout\WooCommerce;

use Exception;
use WC_Product;
use WC_Order_Refund;
use WC_Order_Item;
use WC_Order_Item_Product;
use WC_Order_Item_Fee;
use WC_Order_Item_Shipping;
use WC_Order_Item_Coupon;
use SwedbankPay\Core\Log\LogLevel;
use SwedbankPay\Core\OrderInterface;
use SwedbankPay\Core\OrderItemInterface;

defined( 'ABSPATH' ) || exit;

class WC_Swedbank_Pay_Refund {
	public function __construct() {
		add_action( 'woocommerce_create_refund', __CLASS__ . '::save_refund_parameters', 10, 2 );
		add_action( 'woocommerce_order_refunded', __CLASS__ . '::remove_refund_parameters', 10, 2 );
	}

	/**
	 * Save refund parameters to perform refund with specified products and amounts.
	 *
	 * @param \WC_Order_Refund $refund
	 * @param $args
	 */
	public static function save_refund_parameters( $refund, $args ) {
		if ( ! isset( $args['order_id'] ) ) {
			return;
		}

		$order = wc_get_order( $args['order_id'] );
		if ( ! $order ) {
			return;
		}

		if ( ! in_array( $order->get_payment_method(), WC_Swedbank_Plugin::PAYMENT_METHODS ) ) {
			return;
		}

		// Prevent refund credit memo creation through Callback
		set_transient( 'sb_refund_block_' . $args['order_id'], $args['order_id'], 5 * MINUTE_IN_SECONDS );

		// Save order items of refund
		set_transient(
			'sb_refund_parameters_' . $args['order_id'],
			$args,
			5 * MINUTE_IN_SECONDS
		);

		// Preserve refund
		$refund_id = $refund->save();
		if ( $refund_id ) {
			// Save refund ID to store transaction_id
			set_transient(
				'sb_current_refund_id_' . $args['order_id'],
				$refund_id,
				5 * MINUTE_IN_SECONDS
			);
		}
	}

	/**
	 * Remove refund parameters.
	 *
	 * @param $order_id
	 * @param $refund_id
	 *
	 * @return void
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public static function remove_refund_parameters( $order_id, $refund_id ) {
		delete_transient( 'sb_refund_parameters_' . $order_id );
		delete_transient( 'sb_current_refund_id_' . $order_id );
		delete_transient( 'sb_refund_block_' . $order_id );
	}

	/**
	 * Perform Refund.
	 *
	 * @param \WC_Gateway_Swedbank_Pay_Checkout $gateway
	 * @param \WC_Order $order
	 * @param $amount
	 * @param $reason
	 *
	 * @return void
	 * @throws \SwedbankPay\Core\Exception
	 * @throws \Exception
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public static function refund( $gateway, $order, $amount, $reason ) {
		$args = get_transient( 'sb_refund_parameters_' . $order->get_id() );
		if ( empty( $args ) ) {
			$args = array();
		}

		$lines = isset( $args['line_items'] ) ? $args['line_items'] : [];
		$items = [];

		// Get order lines
		if ( 0 === count( $lines ) ) {
			$line_items = $order->get_items( array( 'line_item', 'shipping', 'fee', 'coupon' ) );
			foreach ( $line_items as $item_id => $item ) {
				switch ( $item->get_type() ) {
					case 'line_item':
						/** @var WC_Order_Item_Product $item */
						// Use subtotal to get amount without discounts
						$lines[$item_id] = array(
							'qty' => $item->get_quantity(),
							'refund_total' => $item->get_subtotal(),
							'refund_tax' => array(
								$item->get_subtotal_tax()
							)
						);

						break;
					case 'fee':
					case 'shipping':
						/** @var WC_Order_Item_Fee|WC_Order_Item_Shipping $item */
						$lines[$item_id] = array(
							'qty' => $item->get_quantity(),
							'refund_total' => $item->get_total(),
							'refund_tax' => array(
								$item->get_total_tax()
							)
						);

						break;
					case 'coupon':
						/** @var WC_Order_Item_Coupon $item */
						$lines[$item_id] = array(
							'qty' => $item->get_quantity(),
							'refund_total' => -1 * $item->get_discount(),
							'refund_tax' => array(
								-1 * $item->get_discount_tax()
							)
						);

						break;
				}
			}
		}

		// Refund with specific items
		// Build order items list
		foreach ( $lines as $item_id => $line ) {
			/** @var WC_Order_Item $item */
			$item = $order->get_item( $item_id );
			if ( ! $item ) {
				throw new \Exception( 'Unable to retrieve order item: ' . $item_id );
			}

			$product_name = trim( $item->get_name() );
			if ( empty( $product_name ) ) {
				$product_name = '-';
			}

			$qty = (int) $line['qty'];
			if ( $qty < 1 ) {
				$qty = 1;
			}

			$refund_total  = (float) $line['refund_total'];
			$refund_tax    = (float) array_shift( $line['refund_tax'] );
			$tax_percent   = ( $refund_tax > 0 ) ? round( 100 / ( $refund_total / $refund_tax ) ) : 0;
			$unit_price    = $qty > 0 ? ( ( $refund_total + $refund_tax ) / $qty ) : 0;
			$refund_amount = $refund_total + $refund_tax;

			if ( empty( $refund_total ) ) {
				// Skip zero items
				continue;
			}

			$gateway->core->log(
				LogLevel::INFO,
				sprintf(
					'Refund item %s. qty: %s, total: %s. tax: %s. amount: %s',
					$item_id,
					$qty,
					$refund_total,
					$refund_tax,
					$refund_amount
				)
			);

			$order_item = array(
				OrderItemInterface::FIELD_NAME        => $product_name,
				OrderItemInterface::FIELD_DESCRIPTION => $product_name,
				OrderItemInterface::FIELD_UNITPRICE   => (int)bcmul(100, $unit_price),
				OrderItemInterface::FIELD_VAT_PERCENT => (int)bcmul(100, $tax_percent),
				OrderItemInterface::FIELD_AMOUNT      => (int)bcmul(100, $refund_amount),
				OrderItemInterface::FIELD_VAT_AMOUNT  => (int)bcmul(100, $refund_tax),
				OrderItemInterface::FIELD_QTY         => $qty,
				OrderItemInterface::FIELD_QTY_UNIT    => 'pcs',
			);

			switch ( $item->get_type() ) {
				case 'line_item':
					/** @var WC_Order_Item_Product $item */

					$image = null;
					$product_class = 'ProductGroup1';

					/**
					 * @var WC_Product $product
					 */
					$product = $item->get_product();
					if ( $product ) {
						// Get Product Sku
						$reference = trim(
							str_replace(
								array( ' ', '.', ',' ),
								'-',
								$product->get_sku()
							)
						);

						// Get Product image
						$image = wp_get_attachment_image_src( $product->get_image_id(), 'full' );
						if ( $image ) {
							$image = array_shift( $image );
						}

						// Get Product Class
						$product_class = $product->get_meta( '_sb_product_class' );

						if ( empty( $product_class ) ) {
							$product_class = apply_filters(
								'sb_product_class',
								'ProductGroup1',
								$product
							);
						}
					}

					if ( empty( $reference ) ) {
						$reference = wp_generate_password( 12, false );
					}

					if ( empty( $image ) ) {
						$image = wc_placeholder_img_src( 'full' );
					}

					if ( null === parse_url( $image, PHP_URL_SCHEME ) &&
						 mb_substr( $image, 0, mb_strlen( WP_CONTENT_URL ), 'UTF-8' ) === WP_CONTENT_URL
					) {
						$image = wp_guess_url() . $image;
					}

					// The field Reference must match the regular expression '[\\w-]*'
					$order_item[OrderItemInterface::FIELD_REFERENCE] = $reference;
					$order_item[OrderItemInterface::FIELD_TYPE] = OrderItemInterface::TYPE_PRODUCT;
					$order_item[OrderItemInterface::FIELD_CLASS] = $product_class;
					$order_item[OrderItemInterface::FIELD_ITEM_URL] = $product->get_permalink();
					$order_item[OrderItemInterface::FIELD_IMAGE_URL] = $image;

					break;
				case 'shipping':
					/** @var WC_Order_Item_Shipping $item */
					$order_item[OrderItemInterface::FIELD_REFERENCE] = 'shipping';
					$order_item[OrderItemInterface::FIELD_TYPE] = OrderItemInterface::TYPE_SHIPPING;
					$order_item[OrderItemInterface::FIELD_CLASS] = apply_filters(
						'sb_product_class_shipping',
						'ProductGroup1',
						$order
					);

					break;
				case 'fee':
					/** @var WC_Order_Item_Fee $item */
					$order_item[OrderItemInterface::FIELD_REFERENCE] = 'fee';
					$order_item[OrderItemInterface::FIELD_TYPE] = OrderItemInterface::TYPE_OTHER;
					$order_item[OrderItemInterface::FIELD_CLASS] = apply_filters(
						'sb_product_class_fee',
						'ProductGroup1',
						$order
					);

					break;
				case 'coupon':
					/** @var WC_Order_Item_Coupon $item */
					$order_item[OrderItemInterface::FIELD_REFERENCE] = 'coupon';
					$order_item[OrderItemInterface::FIELD_TYPE] = OrderItemInterface::TYPE_OTHER;
					$order_item[OrderItemInterface::FIELD_CLASS] = apply_filters(
						'sb_product_class_coupon',
						'ProductGroup1',
						$order
					);

					break;
				default:
					/** @var WC_Order_Item $item */
					$order_item[OrderItemInterface::FIELD_REFERENCE] = 'other';
					$order_item[OrderItemInterface::FIELD_TYPE] = OrderItemInterface::TYPE_OTHER;
					$order_item[OrderItemInterface::FIELD_CLASS] = apply_filters(
						'sb_product_class_other',
						'ProductGroup1',
						$order
					);

					break;
			}

			$items[] = $order_item;
		}

		$result = $gateway->core->refundCheckout( $order->get_id(), $items );
		if ( ! isset( $result['reversal'] ) ) {
			throw new Exception( 'Refund has been failed.' );
		}

		$transaction_id = $result['reversal']['transaction']['number'];

		$order->add_order_note(
			sprintf(
				__(
					'Refund process has been executed from order admin. Transaction ID: %s. State: %s. Reason: %s',
					'swedbank-pay-woocommerce-checkout'
				),
				$transaction_id,
				$result['reversal']['transaction']['state'],
				$reason
			)
		);

		// Add transaction id
		$refund_id = get_transient( 'sb_current_refund_id_' . $order->get_id() );
		if ( $refund_id && $transaction_id ) {
			// Save transaction id
			$refund = new WC_Order_Refund( $refund_id );
			if ( $refund->get_id() ) {
				$refund->update_meta_data( '_transaction_id', $transaction_id );
				$refund->save_meta_data();
			}
		}
	}
}

new WC_Swedbank_Pay_Refund();
