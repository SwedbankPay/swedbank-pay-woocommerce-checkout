<?php

class WC_Unit_Gateway_Swedbank_Pay_Checkout extends WC_Unit_Test_Case {
	/**
	 * @var WC_Gateway_Swedbank_Pay_Checkout
	 */
	private $gateway;

	/**
	 * @var WooCommerce
	 */
	private $wc;

	/**
	 * Setup test case.
	 */
	public function setUp() {
		parent::setUp();

		$this->wc = WC();

		// Init Swedbank Pay plugin
		$this->gateway              = new WC_Gateway_Swedbank_Pay_Checkout();
		$this->gateway->enabled     = 'yes';
		$this->gateway->testmode    = 'yes';
		$this->gateway->description = 'Test';

		// Add PayEx to PM List
		tests_add_filter( 'woocommerce_payment_gateways', [ $this, 'payment_gateways' ] );
	}

	/**
	 * Register Payment Gateway.
	 *
	 * @param $gateways
	 *
	 * @return mixed
	 */
	public function payment_gateways( $gateways ) {
		$payment_gateways[ $this->gateway->id ] = $this->gateway;

		return $gateways;
	}

	public function test_payment_gateway() {
		/** @var WC_Payment_Gateways $gateways */
		$gateways = $this->wc->payment_gateways();
		$this->assertInstanceOf( WC_Payment_Gateways::class, new $gateways );

		$gateways = $gateways->payment_gateways();
		//$this->assertIsArray( $gateways );
		$this->assertTrue( is_array( $gateways ) );
		$this->assertArrayHasKey( 'payex_checkout', $gateways );
	}

	public function test_order() {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( $this->gateway );
		$order->set_currency( 'SEK' );
		$order->save();

		$this->assertEquals( $this->gateway->id, $order->get_payment_method() );
	}

	public function test_process_payment() {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( $this->gateway );
		$order->set_currency( 'SEK' );
		$order->save();

		$result = $this->gateway->process_payment( $order->get_id() );

		$this->assertFalse( $result );
	}

	public function test_payment_confirm() {
		/** @var WC_Order $order */
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( $this->gateway );
		$order->set_currency( 'SEK' );
		$order->update_meta_data( '_payex_payment_id', '/invalid/payment/id' );
		$order->save();

		$_GET['key'] = $order->get_order_key();
		$result      = $this->gateway->payment_confirm();

		$this->assertNull( $result );
	}

	public function test_can_capture() {
		/** @var WC_Order $order */
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_payex_payment_state', 'Authorized' );
		$order->save();

		$result = $this->gateway->can_capture( $order );
		$this->assertTrue( $result );
	}

	public function test_can_cancel() {
		/** @var WC_Order $order */
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_payex_payment_state', 'Captured' );
		$order->save();

		$result = $this->gateway->can_cancel( $order );
		$this->assertFalse( $result );
	}

	/**
	 * @expectedException Exception
	 */
	public function test_can_refund() {
		/** @var WC_Order $order */
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_payex_payment_id', '/invalid/payment/id' );
		$order->update_meta_data( '_payex_payment_state', 'Captured' );
		$order->save();

		$this->gateway->can_refund( $order );
	}

	/**
	 * @expectedException Exception
	 */
	public function test_capture_payment() {
		$order = WC_Helper_Order::create_order();
		$this->gateway->capture_payment( $order );
	}

	/**
	 * @expectedException Exception
	 */
	public function test_cancel_payment() {
		$order = WC_Helper_Order::create_order();
		$this->gateway->cancel_payment( $order );
	}

	/**
	 * @expectedException Exception
	 */
	public function test_refund_payment() {
		$order = WC_Helper_Order::create_order();
		$this->gateway->refund_payment( $order );
	}
}