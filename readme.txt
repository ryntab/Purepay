=== Plugin Name ===
Contributors: purepay, ryantaber
Donate link: https://purepay.eu/
Tags: payment, gateway, purepay, high risk payment
Requires at least: 4.0
Tested up to: 5.4
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Plugin that allows integration with PurePay Payment Gateway

== Description ==

Process online payments with PurePay using the hosted Payment Gateway or direct. To understand more; go to https://purepay.eu/ for more information.

== Installation ==

= Minimum Requirements =
* WooCommerce 2.1 or higher
* WooCommerce Subscriptions (to make use of subscription features)

There are two ways in which you can install the PurePay Payment Gateway into your WordPress/WooCommerce website, Automatic and Manual.

= Option 1 - Automatic Installation =
This is the easiest way to install the PurePay Payment Gateway plugin into your website. Simply log into your WordPress dahsboard and navigate to the Plugins menu, then click Add New.

In the search box, type PurePay and click Search Plugins. Once you have found the PurePay plugin you can install it by simply clicking Install Now.

= Option 2 - Manual Installation =
This option requires access to your servers directory.

1. Upload the plugin folder to your /wp-content/plugins/ directory
2. Activate the plugin through the Plugins menu in WordPress
3. Go to WooCommerce -> Settings and click on the Checkout tab.
4. Find PurePay in the Payment Gateways section
5. Click the settings button to configure and enable the gateway.
6. Click 'Save Changes'.

== Changelog ==

= 1.0 =
* First working version
= 1.1 =
* Revised callback function
= 1.2 =
* Enables the use of custom and responsive forms
= 1.3 =
* Added embedded form
* Less code duplication
* Correct wordpress-escaped responses
= 1.4 =
* Adds support for WooCommerce Subscriptions
= 1.5 =
* Adds interactive card for direct checkout method
* Adds support for Woocommerce 4.0.1
* Tested for Wordpress 5.4
= 1.6 =
* Adds multilingual support
