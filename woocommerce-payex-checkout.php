<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Backwards compat, change plugin main file
 */
$active_plugins = get_option( 'active_plugins', array() );
foreach ( $active_plugins as $key => $active_plugin ) {
	if ( strstr( $active_plugin, '/woocommerce-payex-checkout.php' ) ) {
		$active_plugins[ $key ] = str_replace( '/woocommerce-payex-checkout.php', '/payex-woocommerce-checkout.php', $active_plugin );
	}
}

update_option( 'active_plugins', $active_plugins );
