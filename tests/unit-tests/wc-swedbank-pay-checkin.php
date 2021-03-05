<?php

class WC_Swedbank_Pay_Checkin extends WC_Unit_Test_Case {
	/**
	 * @var WC_Gateway_Swedbank_Pay_Checkout
	 */
	private $gateway;

	/**
	 * @var array
	 */
	private $settings = array(
		'enabled'          => 'yes',
		'testmode'         => 'yes',
		'debug'            => 'yes',
		'method'           => 'redirect',
		'instant_checkout' => 'no'
	);

	/**
	 * Setup test case.
	 */
	public function setUp() {
		parent::setUp();

		$this->gateway = new WC_Gateway_Swedbank_Pay_Checkout();

		$this->settings['payee_id']     = getenv( 'PAYEE_ID' );
		$this->settings['access_token'] = getenv( 'ACCESS_TOKEN' );
		$this->settings                 = array_merge( $this->gateway->settings, $this->settings );

		if ( empty( $this->settings['payee_id'] ) || empty( $this->settings['access_token'] ) ) {
			$this->fail( "ACCESS_TOKEN or PAYEE_ID wasn't configured in environment variable." );
		}

		update_option(
			$this->gateway->get_option_key(),
			apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->gateway->id, $this->settings ),
			'yes'
		);

		$this->gateway->init_settings();
		$this->gateway = new WC_Gateway_Swedbank_Pay_Checkout();
	}

	/**
	 * Make sure that the setting are loaded on the admin page.
	 */
	public function test_method_options_loaded_for_admin_page() {
		set_current_screen( 'woocommerce_page_wc-settings' );
		$_REQUEST['page']    = 'wc-settings';
		$_REQUEST['tab']     = 'checkout';
		$_REQUEST['section'] = 'payex_checkout';

		$gateway = new WC_Gateway_Swedbank_Pay_Checkout();
		$form_fields = $gateway->get_form_fields();

		// Clean up!
		$GLOBALS['current_screen'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		unset( $_REQUEST['page'] );
		unset( $_REQUEST['tab'] );
		unset( $_REQUEST['section'] );

		$this->assertArrayHasKey( 'checkin', $form_fields );
		$this->assertArrayHasKey( 'checkin_edit', $form_fields );
	}
}