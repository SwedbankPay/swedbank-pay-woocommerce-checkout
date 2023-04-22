<?php

use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * Checks if High-Performance Order Storage is enabled.
 *
 * @see https://woocommerce.com/document/high-performance-order-storage/
 * @see https://github.com/woocommerce/woocommerce/wiki/High-Performance-Order-Storage-Upgrade-Recipe-Book
 * @return bool
 */
function sb_is_hpos_enabled() {
	if ( ! class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
		return false;
	}

	if ( ! method_exists( OrderUtil::class, 'custom_orders_table_usage_is_enabled' ) ) {
		return false;
	}

	return OrderUtil::custom_orders_table_usage_is_enabled();
}

/**
 * Get Post Id by Meta
 *
 * @param $key
 * @param $value
 *
 * @return null|string
 */
function sb_get_post_id_by_meta( $key, $value ) {
	if ( sb_is_hpos_enabled() ) {
		global $wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = %s AND meta_value = %s;",
				$key,
				$value
			)
		);
	}

	$orders = wc_get_orders(
		array(
			'return'     => 'ids',
			'limit'      => 1,
			'meta_query' => array(
				array(
					'key'   => $key,
					'value' => $value,
				),
			),
		)
	);

	if ( count( $orders ) > 0 ) {
		$order = array_shift( $orders );

		if ( is_int( $order ) ) {
			return $order;
		}

		return $order->get_id();
	}

	return null;
}
