<?php

use SwedbankPay\Checkout\WooCommerce\WC_Swedbank_Pay_Payment_Url as Payment_Url;

class WC_Swedbank_Pay_Payment_Url extends WC_Unit_Test_Case {
	/** @var Payment_Url */
	private $payment_url;

	/**
	 * Setup test case.
	 */
	public function setUp() {
		parent::setUp();

		$this->payment_url = new Payment_Url();
	}

	public function test_override_checkout_shortcode() {
		$result = $this->payment_url->override_checkout_shortcode();
		$this->assertEquals( null, $result );
	}

	public function test_shortcode_woocommerce_checkout() {
		$result = $this->payment_url->shortcode_woocommerce_checkout( array() );
		$this->assertInternalType( 'string', $result );
	}
}