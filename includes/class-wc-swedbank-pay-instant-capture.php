<?php

namespace SwedbankPay\Checkout\WooCommerce;

defined( 'ABSPATH' ) || exit;

use SwedbankPay\Core\Log\LogLevel;
use SwedbankPay\Core\OrderItemInterface;

class WC_Swedbank_Pay_Instant_Capture {
	/** Payment IDs */
	const PAYMENT_METHODS = array(
		'payex_checkout',
	);

	/**
	 * CAPTURE Type options
	 */
	const CAPTURE_VIRTUAL = 'online_virtual';
	const CAPTURE_PHYSICAL = 'physical';
	const CAPTURE_RECURRING = 'recurring';
	const CAPTURE_FEE = 'fee';

	/**
	 * @var \WC_Gateway_Swedbank_Pay_Cc
	 */
	private $gateway;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_order_status_on-hold', array( $this, 'maybe_capture_instantly' ), 10, 1 );
	}

	/**
	 * Maybe capture instantly.
	 *
	 * @param $order_id
	 *
	 * @throws \SwedbankPay\Core\Exception
	 */
	public function maybe_capture_instantly( $order_id ) {
		$order = wc_get_order( $order_id );
		$payment_method = $order->get_payment_method();
		if ( ! in_array( $payment_method, WC_Swedbank_Plugin::PAYMENT_METHODS, true ) ) {
			return;
		}

		$this->gateway = $this->get_payment_method( $order );

		if ( 'yes' === $this->gateway->auto_capture ) {
			return;
		}

		$payment_id = $order->get_meta('_payex_payment_id');
		if ( empty( $payment_id ) ) {
			return;
		}

		// Fetch transactions list
		$transactions = $this->gateway->core->fetchTransactionsList( $payment_id );
		$this->gateway->core->saveTransactions( $order->get_id(), $transactions );

		// Check if have captured transactions
		$hasCaptured = false;
		foreach ( $transactions as $transaction ) {
			if ( \SwedbankPay\Core\Api\TransactionInterface::TYPE_CAPTURE === $transaction->getType() &&
			     $transaction->isCompleted()
			) {
				$hasCaptured = true;
			}
		}

		// Capture if possible
		if ( ! $hasCaptured ) {
			try {
				$this->instant_capture( $order );
			} catch ( \Exception $e ) {
				$this->gateway->adapter->log(
					LogLevel::INFO,
					sprintf( '%s: Warning: %s', __METHOD__, $e->getMessage() )
				);
			}
		}
	}

	/**
	 * Capture order using "Instant Capture".
	 *
	 * @param \WC_Order $order
	 *
	 * @throws \Exception
	 */
	private function instant_capture( $order ) {
		$items = $this->get_instant_capture_items( $order );
		$this->gateway->adapter->log( LogLevel::INFO, __METHOD__, [ $items ] );

		if ( count( $items ) > 0 ) {
			$amount     = array_sum( array_column( $items, OrderItemInterface::FIELD_AMOUNT ) ) / 100;
			$vat_amount = array_sum( array_column( $items, OrderItemInterface::FIELD_VAT_AMOUNT ) ) / 100;

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

				if ( 'payex_psp_invoice' === $order->get_payment_method() ) {
					$this->gateway->core->captureInvoice( $order->get_id(), $amount, $vat_amount, $items );
				} else {
					$this->gateway->core->capture( $order->get_id(), $amount, $vat_amount );
				}
			} catch ( \SwedbankPay\Core\Exception $e ) {
				throw new \Exception( $e->getMessage() );
			}
		}
	}

	/**
	 * Get Payment Method Instance.
	 *
	 * @param \WC_Order $order
	 *
	 * @return \WC_Gateway_Swedbank_Pay_Checkout|\WP_Error
	 */
	private function get_payment_method( $order ) {
		$payment_method = $order->get_payment_method();

		// Get Payment Gateway
		$gateways = WC()->payment_gateways()->get_available_payment_gateways();

		/** @var \WC_Gateway_Swedbank_Pay_Cc $gateway */

		if ( isset( $gateways[ $payment_method ] ) ) {
			return $gateways[ $payment_method ];
		}

		return new \WP_Error( 'not_found', 'Payment method is not found.' );
	}

	/**
	 * Get items which should be captured instantly.
	 *
	 * @param \WC_Order $order
	 * @return array
	 */
	private function get_instant_capture_items( $order ) {
		if ( count( $this->gateway->instant_capture ) === 0 ) {
			return array();
		}

		$items = array();
		foreach ( $order->get_items() as $order_item ) {
			/** @var \WC_Order_Item_Product $order_item */
			/** @var \WC_Product $product */
			$product        = $order_item->get_product();
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

			if (null === parse_url( $image, PHP_URL_SCHEME ) &&
			    mb_substr( $image, 0, mb_strlen(WP_CONTENT_URL), 'UTF-8' ) === WP_CONTENT_URL
			) {
				$image = wp_guess_url() . $image;
			}

			// Get Product Class
			$product_class = get_post_meta(
				$order_item->get_product()->get_id(),
				'_sb_product_class',
				true
			);

			if ( empty( $product_class ) ) {
				$product_class = 'ProductGroup1';
			}

			// Get Product Sku
			$product_reference = trim(
				str_replace(
					array( ' ', '.', ',' ),
					'-',
					$order_item->get_product()->get_sku()
				)
			);

			if ( empty( $product_reference ) ) {
				$product_reference = wp_generate_password( 12, false );
			}

			$product_name = trim( $order_item->get_name() );

			if ( in_array(self::CAPTURE_PHYSICAL, $this->gateway->instant_capture, true ) &&
			     ( ! self::wcs_is_subscription_product( $product ) &&
			       $product->needs_shipping() &&
			       ! $product->is_downloadable() )
			) {
				$items[] = array(
					// The field Reference must match the regular expression '[\\w-]*'
					OrderItemInterface::FIELD_REFERENCE   => $product_reference,
					OrderItemInterface::FIELD_NAME        => !empty($product_name) ? $product_name : '-',
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

				continue;
			} elseif ( in_array(self::CAPTURE_VIRTUAL, $this->gateway->instant_capture, true ) &&
			           ( ! self::wcs_is_subscription_product( $product ) &&
			             ( $product->is_virtual() || $product->is_downloadable() ) )
			) {
				$items[] = array(
					// The field Reference must match the regular expression '[\\w-]*'
					OrderItemInterface::FIELD_REFERENCE   => $product_reference,
					OrderItemInterface::FIELD_NAME        => !empty($product_name) ? $product_name : '-',
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

				continue;
			} elseif ( in_array( self::CAPTURE_RECURRING, $this->gateway->instant_capture, true ) &&
			           self::wcs_is_subscription_product( $product )
			) {
				$items[] = array(
					// The field Reference must match the regular expression '[\\w-]*'
					OrderItemInterface::FIELD_REFERENCE   => $product_reference,
					OrderItemInterface::FIELD_NAME        => !empty($product_name) ? $product_name : '-',
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

				continue;
			}
		}

		// Add Shipping Total
		if ( in_array(self::CAPTURE_PHYSICAL, $this->gateway->instant_capture ) ) {
			if ( (float) $order->get_shipping_total() > 0 ) {
				$shipping          = (float) $order->get_shipping_total();
				$tax               = (float) $order->get_shipping_tax();
				$shipping_with_tax = $shipping + $tax;
				$tax_percent       = ($tax > 0) ? round(100 / ($shipping / $tax)) : 0;
				$shipping_method   = trim( $order->get_shipping_method() );

				$items[] = array(
					OrderItemInterface::FIELD_REFERENCE   => 'shipping',
					OrderItemInterface::FIELD_NAME        => !empty($shipping_method) ? $shipping_method : __(
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
		}

		// Add fees
		if ( in_array(self::CAPTURE_FEE, $this->gateway->instant_capture ) ) {
			foreach ( $order->get_fees() as $order_fee ) {
				/** @var \WC_Order_Item_Fee $order_fee */
				$fee          = (float) $order_fee->get_total();
				$tax          = (float) $order_fee->get_total_tax();
				$fee_with_tax = $fee + $tax;
				$tax_percent  = ($tax > 0) ? round(100 / ($fee / $tax)) : 0;

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
		}

		// Add discounts
		if ( $order->get_total_discount( false ) > 0 ) {
			$discount          = abs( $order->get_total_discount( true ) );
			$discount_with_tax = abs( $order->get_total_discount( false ) );
			$tax               = $discount_with_tax - $discount;
			$tax_percent       = ( $tax > 0 ) ? round( 100 / ( $discount / $tax ) ) : 0;

			$items[] = array(
				OrderItemInterface::FIELD_REFERENCE   => 'discount',
				OrderItemInterface::FIELD_NAME        => __('Discount', 'swedbank-pay-woocommerce-payments'),
				OrderItemInterface::FIELD_TYPE        => OrderItemInterface::TYPE_DISCOUNT,
				OrderItemInterface::FIELD_CLASS       => 'ProductGroup1',
				OrderItemInterface::FIELD_QTY         => 1,
				OrderItemInterface::FIELD_QTY_UNIT    => 'pcs',
				OrderItemInterface::FIELD_UNITPRICE   => round(-100 * $discount_with_tax),
				OrderItemInterface::FIELD_VAT_PERCENT => round(100 * $tax_percent),
				OrderItemInterface::FIELD_AMOUNT      => round(-100 * $discount_with_tax),
				OrderItemInterface::FIELD_VAT_AMOUNT  => round(-100 * $tax),
			);
		}

		return $items;
	}


	/**
	 * Checks if there's Subscription Product.
	 *
	 * @param \WC_Product $product
	 *
	 * @return bool
	 */
	private static function wcs_is_subscription_product( $product ) {
		return class_exists( 'WC_Subscriptions_Product', false ) &&
		       WC_Subscriptions_Product::is_subscription( $product );
	}
}

new WC_Swedbank_Pay_Instant_Capture();
