=== Official BlueSnap Payment Gateway Plugin ===
Author: BlueSnap
Version: 2.0.13
Contributors: fishaonline
Tested up to: 4.7.2
Stable tag: 2.0.13
Tags: bluesnap, payments, gateway, woocommerce
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Accept payments in your WordPress site with the official BlueSnap plugin built by Fishaonline for BlueSnap.

== Description ==

= Best credit and debit card processing payment gateway for global shoppers =
BlueSnap offers a merchant account and payment gateway solution all-in-one. We process your credit and debit card transactions with secure and frictionless checkout for your global shoppers. BlueSnap for WooCommerce brings an embedded checkout form directly into your checkout page so that shoppers never leave your store. And the module is free!
Includes full support for [WooCommerce Subscriptions](https://www.woothemes.com/products/braintree/woothemes.com/products/subscriptions/) and [WooCommerce Pre-Orders](https://www.woothemes.com/products/woocommerce-pre-orders/).
Start using the BlueSnap payment gateway today!

= Why BlueSnap is your best option for domestic and global shoppers =
Accept all Major Credit Cards / Debit Cards – Visa®, MasterCard®, American Express®, Discover®, Diner’s Club, JCB
Get a merchant account and payment gateway all-in-one
Seamless checkout experience, customers stay on your site
Multi-currency support for increased conversions
Keep payment information safe and secure with client-side encryption
Full support for WooCommerce Subscriptions so you can generate recurring revenue NEW
Full support for WooCommerce Pre-orders for taking orders prior to availability NEW
Digital and Physical good merchants
Process full or partial refunds directly in WooCommerce

= Seamless Checkout Experience =
The Bluesnap extension is designed to give your shoppers an easy payment experience. The embedded, secure form looks and feels like it is part of your page for a frictionless payment process.

= Multi-currency =
13% of shoppers will leave their cart if their total is presented in a foreign currency. Avoid checkout abandonment with local currencies that shoppers trust. Merchants can decide what currencies they would like to support.

= Safe & Secure =
With the BlueSnap Extension you are getting the highest level of security. The embedded checkout form uses Client-Side-Encryption. When the shopper enters their sensitive card data, it is encrypted before it passes through your server to ours. That means you never have to store credit card data which greatly reduces your PCI compliance scope. In addition, we have the industry’s leading fraud protection built into our platform, so you can rest easy knowing that we’ve got your back.

= Subscriptions =
BlueSnap fully supports [WooCommerce Subscriptions](https://www.woothemes.com/products/braintree/woothemes.com/products/subscriptions/) for subscription billing.  Build your recurring revenue business today!  Merchants that use our subscription engine are able to convert 25% more shoppers to buyers than merchants without subscriptions.

= Pre-Orders =
BlueSnap also fully supports [WooCommerce Pre-Orders](https://www.woothemes.com/products/woocommerce-pre-orders/). This is great for capturing payment information prior to product availability, and then processing those payments automatically when the pre-order becomes available.

= Digital and Physical Goods =
BlueSnap is an ideal way to take payments for your digital or physical goods. We make it easy and secure to process transactions, through one-time charges, subscriptions, pre-orders or in multiple currencies. Download the extension and get started today.


== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/bluesnap` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress
1. Use the Settings->BlueSnap Payment Gateway->CSE Settings to configure the plugin (You will need BlueSnap account in order to get your settings and credentials, see home.bluesnap.com)


== Screenshots ==

1. Checkout form
2. Currency converter

== Changelog ==

= 1.2.13 =
* First release.

= 1.3.1 =
* Added: support for WC Subscriptions 2.
* New currencies are now available.
* All currencies are now supported by BlueSnap.

= 1.3.2 =
* Added: New option to sort logs by ASC/DESC date
* Added: Function for logs cleanup (default 90 days)
* Added: When using subscription, show shopper notification regarding renewal price changes
* Change: Currency switcher will be set to off by default

= 1.3.3 =
* Change: If the plugin is in sandbox mode force currency into sandbox mode as well

= 1.3.6 =
* Fix: MySQL data load issue.

= 1.3.7 =
* Minor code changes, and code refractor.

= 1.3.8 =
* Added: New IPN for refund.
* Added: Show BlueSnap invoice id/order id in the order details page.
* Change: Remove some data from API calls to BSNP servers.
* Fixed: If order was cancelled, then don't reactivate it via IPN.

= 2.0.11 =
* Change: Major code, data base and design changes changes.
* Plugin structure was changed in order to comply with WC code demands.

= 2.0.13 =
* Fixed: Hide error is now working
* Fixed: IPN status handler. IPN will not override status when status is complete
* Change: minor layout and design changes