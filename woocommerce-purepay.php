<?php
/*
Plugin Name: WooCommerce PurePay
Plugin URI: http://woothemes.com/woocommerce/
Description: Provides the PurePay Payment Gateway for WooCommerce
Version: 1.6
Author: PurePay
Text Domain: Purepay
Domain Path: /languages
Author URI: http://www.purepay.eu/
License: GPL2
*/

/*  Copyright 2019  PurePay  (support@purepay.eu)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/

/**
 * Initialise PurePay Gateway
 **/
add_action('plugins_loaded', 'init_purepay', 0);

function init_purepay() {

	if (!class_exists('WC_Payment_Gateway')) {
		return;
	}

	add_filter('plugin_action_links', 'PP_add_action_plugin', 10, 5);

	include('classes/purepay.php');

	add_filter('woocommerce_payment_gateways', 'add_purepay_paymentgateway' );

}

function PP_add_action_plugin($actions, $plugin_file)
{
	static $plugin;

	if (!isset($plugin))
	{
		$plugin = plugin_basename(__FILE__);
	}

	if ($plugin == $plugin_file)
	{
		$actions = array_merge(array('settings' => '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=purepay') . '">' . __('Settings', 'General') . '</a>'), $actions);
	}

	return $actions;
}

function add_purepay_paymentgateway($methods) {
	$methods[] = 'WC_PurePay';
	return $methods;
}


  
/**
 * Load plugin textdomain.
 */
function Purepay_load_textdomain() {
  load_plugin_textdomain( 'Purepay', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); 
}

add_action( 'plugins_loaded', 'Purepay_load_textdomain', 0 );