<?php

namespace SwedbankPay\Checkout\WooCommerce;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use Exception;

class WC_Swedbank_Pay_Transactions {
	/**
	 * The single instance of the class.
	 *
	 * @var WC_Swedbank_Pay_Transactions
	 */
	protected static $_instance = null;

	/**
	 * Allowed Fields
	 * @var array
	 */
	protected static $_allowed_fields = array(
		'transaction_id',
		'transaction_data',
		'order_id',
		'payeeReference',
		'id',
		'created',
		'updated',
		'type',
		'state',
		'number',
		'amount',
		'vatAmount',
		'description',
	);

	/**
	 * Main WC_Swedbank_Transactions Instance.
	 *
	 * @static
	 * @return WC_Swedbank_Pay_Transactions
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Construct is forbidden.
	 */
	private function __construct() {
	}

	/**
	 * Cloning is forbidden.
	 */
	private function __clone() {
		/* ... @return Singleton */
	}

	/**
	 * Wakeup is forbidden.
	 */
	private function __wakeup() {
		/* ... @return Singleton */
	}

	/**
	 * Install DB Schema
	 */
	public function install_schema() {
		global $wpdb;

		// phpcs:disable
		$result = $wpdb->query( "
CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}payex_transactions` (
  `transaction_id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_data` text,
  `order_id` int(11) DEFAULT NULL,
  `id` varchar(255) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `type` varchar(128) DEFAULT NULL,
  `state` varchar(128) DEFAULT NULL,
  `number` varchar(128) DEFAULT NULL,
  `amount` bigint(20) DEFAULT NULL,
  `vatAmount` bigint(20) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `payeeReference` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`transaction_id`),
  UNIQUE KEY `number` (`number`),
  KEY `id` (`id`),
  KEY `order_id` (`order_id`)
) ENGINE=INNODB DEFAULT CHARSET={$wpdb->charset};
		" );
		// phpcs:enable

		if ( false === $result ) {
			throw new Exception(
				__( 'Failed to install the transaction table.', 'swedbank-pay-woocommerce-checkout' )
			);
		}
	}

	/**
	 * Add Transaction
	 *
	 * @param $fields
	 *
	 * @return int|WP_Error
	 */
	public function add( $fields ) {
		global $wpdb;

		if ( ! isset( $fields['created'] ) ) {
			$fields['created'] = gmdate( 'Y-m-d H:i:s' );
		}

		if ( ! isset( $fields['updated'] ) ) {
			$fields['updated'] = gmdate( 'Y-m-d H:i:s' );
		}

		$fields = $this->prepare( $fields );
		$result = $wpdb->insert( $wpdb->prefix . 'payex_transactions', $fields );
		if ( $result > 0 ) {
			return $wpdb->insert_id;
		}

		return new WP_Error(
			'insert_failed',
			__( 'Failed to insert the transaction to the table.', 'swedbank-pay-woocommerce-checkout' )
		);
	}

	/**
	 * Delete Transaction
	 *
	 * @param $transaction_id
	 *
	 * @return int|WP_Error
	 */
	public function delete( $transaction_id ) {
		global $wpdb;

		$result = $wpdb->delete(
			$wpdb->prefix . 'payex_transactions',
			array( 'transaction_id' => (int) $transaction_id )
		);

		if ( false === $result ) {
			return new WP_Error(
				'delete_failed',
				__( 'Failed to delete the transaction from the table.', 'swedbank-pay-woocommerce-checkout' )
			);
		}

		return $result;
	}

	/**
	 * Update Transaction Data
	 *
	 * @param $transaction_id
	 * @param $fields
	 *
	 * @return int|WP_Error
	 */
	public function update( $transaction_id, $fields ) {
		global $wpdb;

		if ( ! isset( $fields['updated'] ) ) {
			$fields['updated'] = gmdate( 'Y-m-d H:i:s' );
		}

		$fields = $this->prepare( $fields );
		$result = $wpdb->update(
			$wpdb->prefix . 'payex_transactions',
			$fields,
			array(
				'transaction_id' => (int) $transaction_id,
			)
		);

		if ( false === $result ) {
			return new WP_Error(
				'update_failed',
				__( 'Failed to update the transaction in the table.', 'swedbank-pay-woocommerce-checkout' )
			);
		}

		return $result;
	}

	/**
	 * Get Transaction
	 *
	 * @param $transaction_id
	 *
	 * @return array|null|object
	 */
	public function get( $transaction_id ) {
		global $wpdb;
		$query = $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}payex_transactions WHERE transaction_id = %d;",
			$transaction_id
		);

		// phpcs:disable
		return $wpdb->get_row( $query, ARRAY_A );
		// phpcs:enable
	}

	/**
	 * Get Transaction By Field
	 *
	 * @param      $field
	 * @param      $value
	 * @param bool $single
	 *
	 * @return array|null|object
	 */
	public function get_by( $field, $value, $single = true ) {
		global $wpdb;
		if ( ! in_array( $field, self::$_allowed_fields, true ) ) {
			$field = 'transaction_id';
		}

		// phpcs:disable
		$query = $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}payex_transactions WHERE {$field} = %s;",
			$value
		);

		return $single ? $wpdb->get_row( $query, ARRAY_A ) : $wpdb->get_results( $query, ARRAY_A );
		// phpcs:enable
	}

	/**
	 * Get Transactions by Conditionals
	 *
	 * @param array $conditionals
	 *
	 * @return array|null|object
	 */
	public function select( array $conditionals ) {
		global $wpdb;

		$lines = array();
		foreach ( $conditionals as $key => $value ) {
			if ( ! in_array( $key, self::$_allowed_fields, true ) ) {
				_doing_it_wrong( __METHOD__, __( 'Cheatin&#8217; huh?', 'woocommerce' ), '1.0.0' );
				die();
			}

			if ( ! is_numeric( $value ) ) {
				$value   = esc_sql( $value );
				$lines[] = "{$key} = '{$value}'";
			} else {
				$lines[] = "{$key} = {$value}";
			}
		}

		$lines = join( ' AND ', $lines );
		// phpcs:disable
		$query = "SELECT * FROM {$wpdb->prefix}payex_transactions WHERE {$lines};";

		return $wpdb->get_results( $query, ARRAY_A );
		// phpcs:enable
	}

	/**
	 * Prepare data
	 *
	 * @param $data
	 *
	 * @return array
	 */
	public function prepare( $data ) {
		$data['transaction_data'] = json_encode( $data, true );

		// Verify data
		foreach ( $data as $key => $value ) {
			if ( ! in_array( $key, self::$_allowed_fields ) ) {
				unset( $data[ $key ] );
			}
		}

		return $data;
	}

	/**
	 * Import Transaction
	 *
	 * @param $data
	 * @param $order_id
	 *
	 * @return bool|int|mixed
	 *
	 * @throws Exception
	 */
	public function import( $data, $order_id ) {
		$id               = $data['id'];
		$data             = $this->prepare( $data );
		$data['order_id'] = $order_id;

		$saved = $this->get_by( 'id', $id );
		if ( ! $saved ) {
			$data['created'] = gmdate( 'Y-m-d H:i:s' );
			$data['updated'] = gmdate( 'Y-m-d H:i:s' );

			$row_id = $this->add( $data );
			if ( is_wp_error( $row_id ) ) {
				/** @var WP_Error $row_id */
				throw new Exception( $row_id->get_error_message( ) );
			}

			return $row_id;
		}

		// Data should be updated
		$data['updated'] = gmdate( 'Y-m-d H:i:s' );
		$this->update( $saved['transaction_id'], $data );

		return $saved['transaction_id'];
	}

	/**
	 * Bulk Import Transaction
	 *
	 * @param $transactions
	 * @param $order_id
	 *
	 * @return array
	 *
	 * @throws Exception
	 */
	public function import_transactions( $transactions, $order_id ) {
		$result = array();
		foreach ( $transactions as $transaction ) {
			$result[] = $this->import( $transaction, $order_id );
		}

		return $result;
	}

}
