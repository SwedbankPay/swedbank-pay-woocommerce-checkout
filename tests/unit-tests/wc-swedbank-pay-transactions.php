<?php

use SwedbankPay\Checkout\WooCommerce\WC_Swedbank_Pay_Transactions;

class WC_Swedbank_Pay_Transactions_Test extends WC_Unit_Test_Case {
	public function test_data() {
		$order = WC_Helper_Order::create_order();
		$transactions = WC_Swedbank_Pay_Transactions::instance();

		$transactions->install_schema();

		$fields = array(
			'transaction_id' => 123,
			'order_id' => $order->get_id(),
			'payeeReference' => 'zz',
			'id' => 1,
			'type' => 'zz',
			'state' => 'zz',
			'number' => '123',
			'amount' => $order->get_total(),
			'vatAmount' => 0,
			'description' => 'zz',
			'INVALID_FIELD' => 'zz' // Should be ignored
		);

		$insert_id = $transactions->add( $fields );
		$this->assertIsInt( $insert_id );

		$result = $transactions->update( 123, $fields );
		$this->assertIsInt( $result );

		$result = $transactions->delete( 123 );
		$this->assertIsInt( $result );
	}

	public function test_import() {
		$order = WC_Helper_Order::create_order();
		$transactions = WC_Swedbank_Pay_Transactions::instance();

		$transactions->install_schema();

		$fields = array(
			'transaction_id' => 123,
			'order_id' => $order->get_id(),
			'payeeReference' => 'zz',
			'id' => 1,
			'type' => 'zz',
			'state' => 'zz',
			'number' => '123',
			'amount' => $order->get_total(),
			'vatAmount' => 0,
			'description' => 'zz',
			'created' => 'One minute ago',
			'updated' => 'now',
			'INVALID_FIELD' => 'zz' // Should be ignored
		);
		// Run import on an order which does not have a corresponding row in the transactions table.
		$add = $transactions->import( $fields, $order->get_id() );
		$this->assertIsInt( $add );

		// Run import again, this time the transactions table should be updated.
		$update = $transactions->import( $fields, $order->get_id() );
		$this->assertIsInt( $update );
	}
}
