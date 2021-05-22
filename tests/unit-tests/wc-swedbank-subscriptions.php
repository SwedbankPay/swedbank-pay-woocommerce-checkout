<?php

use \SwedbankPay\Checkout\WooCommerce\WC_Swedbank_Subscriptions as Subscriptions;

class WC_Swedbank_Subscriptions extends WC_Unit_Test_Case {
	public function test_add_subscription_card_id() {
		/** @var WC_Order $order */
		$order = WC_Helper_Order::create_order();
		$order->set_billing_country( 'SE' );
		$order->set_payment_method( 'payex_checkout' );
		$order->save();

		$result = Subscriptions::add_subscription_card_id( $order->get_id() );

		$this->assertEquals( null, $result );
	}

	public function test_delete_resubscribe_meta() {
		/** @var WC_Order $order */
		$order = WC_Helper_Order::create_order();
		$order->set_billing_country( 'SE' );
		$order->set_payment_method( 'payex_checkout' );
		$order->save();

		Subscriptions::delete_resubscribe_meta( $order );

		$this->assertEquals( 0, count( $order->get_payment_tokens() ) );
	}

	public function test_add_subscription_payment_meta() {
		/** @var WC_Order $order */
		$order = WC_Helper_Order::create_order();
		$order->set_billing_country( 'SE' );
		$order->set_payment_method( 'payex_checkout' );
		$order->save();

		$payment_meta = Subscriptions::add_subscription_payment_meta( array(), $order );
		$this->assertIsArray( $payment_meta );
		$this->assertArrayHasKey( 'swedbankpay_meta', $payment_meta );
	}

	public function test_payment_meta_input() {
		/** @var WC_Order $order */
		$order = WC_Helper_Order::create_order();
		$order->set_billing_country( 'SE' );
		$order->set_payment_method( 'payex_checkout' );
		$order->save();

		$result = Subscriptions::payment_meta_input( $order, 'field_id', null, null );
		$this->assertIsString( $result );
	}

	public function test_validate_subscription_payment_meta() {
		/** @var WC_Order $order */
		$order = WC_Helper_Order::create_order();
		$order->set_billing_country( 'SE' );
		$order->set_payment_method( 'payex_checkout' );
		$order->save();

		$this->expectException( Exception::class );
		Subscriptions::validate_subscription_payment_meta(
			'payex_checkout',
			array(
				'swedbankpay_meta' => array(
					'token_id' => array(
						'value' => null
					)
				)
			),
			$order
		);
	}

}
