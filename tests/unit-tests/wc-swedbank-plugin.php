<?php

class WC_Swedbank_Plugin extends WC_Unit_Test_Case {
	public function test_billing_phone()
	{
		$order = WC_Helper_Order::create_order();
		$order->set_billing_country( 'SE' );
		$order->save();

		$result = \SwedbankPay\Checkout\WooCommerce\WC_Swedbank_Plugin::billing_phone(
			'0739000001',
			$order
		);

		$this->assertEquals( '+46739000001', $result );
	}
}
