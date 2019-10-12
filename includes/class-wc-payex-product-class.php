<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Payex_Product_Class {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'woocommerce_product_data_tabs', __CLASS__ . '::add_product_tabs' );
		add_action( 'woocommerce_product_data_panels', __CLASS__ . '::product_options_product_tab_content' );
		add_action( 'woocommerce_process_product_meta', __CLASS__ . '::save_field' );
	}

	/**
	 * Add Tab to Product editor
	 *
	 * @param array $tabs
	 *
	 * @return array
	 */
	public static function add_product_tabs( $tabs ) {
		$tabs['payex'] = array(
			'label'  => __( 'PayEx', 'woocommerce-gateway-payex-checkout' ),
			'target' => 'product_options',
			'class'  => array( 'show_if_simple' ),
		);

		return $tabs;
	}

	/**
	 * Tab Content
	 */
	public static function product_options_product_tab_content() {
		$product_class = get_post_meta( get_the_ID(), '_payex_product_class', true );
		?>
		<div id='product_options' class='panel woocommerce_options_panel'>
			<div class='options_group'>
				<?php
				woocommerce_wp_text_input(
					array(
						'id'                => '_payex_product_class',
						'value'             => $product_class,
						'label'             => __( 'Product Class', 'woocommerce-gateway-payex-checkout' ),
						'desc_tip'          => true,
						'description'       => __( 'Product Class', 'woocommerce-gateway-payex-checkout' ),
						'type'              => 'text'
					)
				);
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Save Handler
	 */
	public static function save_field() {
		global $post_id;

		if ( empty( $post_id ) ) {
			return;
		}

		if ( isset( $_POST['_payex_product_class'] ) ) {
			update_post_meta( $post_id, '_payex_product_class', wc_clean( $_POST['_payex_product_class'] ) );
		}
	}
}

new WC_Payex_Product_Class();
