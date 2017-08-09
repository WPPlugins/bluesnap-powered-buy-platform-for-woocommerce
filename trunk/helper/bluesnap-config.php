<?php
/**
 * Created by PhpStorm.
 * User: yarivkohn
 * Date: 3/15/15
 * Time: 2:26 PM
 * This file will contain all of plugin's constants
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}


// System params
define( 'DS', DIRECTORY_SEPARATOR );

// BlueSnap JS file for CSE payment methods
define( 'BLUESNAP_JS_LOCATION', 'https://gateway.bluesnap.com/js/cse/v1.0.1/bluesnap.js' );

// GLOBAL params definition
define( 'BSNP_PREFIX', 'bsnp' );
define( 'BLUESNAP_GATEWAY_VERSION', '2.0.13' );
define( 'BLUESNAP_DB_VERSION', '0.1.0' );
define( 'BS_DEFAULT_CURRENCY', 'USD' );
define( 'BS_ACCURACY_FACTOR', 100000000 );
define( 'BSNP_MIN_SHOPPER_NAME_LENGTH', 2 );
define( 'BSNP_MAX_SHOPPER_NAME_LENGTH', 42 );
define( 'BSNP_REFUND_SUPPORT_EMAIL', 'merchants@bluesnap.com' );


// Working environment definition
define( 'BS_API_VERSION', '2' );
define( 'BS_SAND_BOX', 'https://sandbox.bluesnap.com/' );
define( 'BS_PRODUCTION', 'https://ws.bluesnap.com/' );
define( 'BS_FULL_DOMAIN', 'services/' . BS_API_VERSION . '/' );

// BlueSnap API URLs
define( 'BS_ORDER_ROUT', 'batch/order-placement' );
define( 'BS_RETURN_ROUT', 'shoppers/' );
define( 'BS_REORDER_ROUT', 'orders' );
define( 'BS_RETRIEVE_ORDER', 'orders/' );
define( 'BS_SHOPPING_CONTEXT', 'shopping-context/' );
define( 'BS_REFUND', 'orders/refund' );
define( 'BS_SUBSCRIPTION', 'subscriptions/' );
define( 'BS_CURRENCY_CONVERTER', 'tools/merchant-currency-convertor' );
define( 'BS_FRAUD_URL', 'servlet/logo.htm' );
define( 'BS_ENCRYPT_URL', 'tools/param-encryption' );
define( 'BS_CREATE_SUBSCRIPTION_CHARGE', '/subscription-charges?fulldescription=true' );

//Email config
define( 'BS_CHARGEBACK_SUPPORT_EMAIL', 'chargeback@bluesnap.com' );
define( 'MERCHANT_EMAIL', get_option( 'admin_email' ) );

// Check order's status definition
define( 'BS_TIMES_TO_CHECK', 0 );
define( 'BS_TIME_TO_SLEEP_SEC', 0 );

// IPN PARAMS
define( 'BS_IPN_COMPLETE', 'CHARGE' );
define( 'BS_IPN_DECLINE', 'DECLINE' );
define( 'BS_IPN_CHARGEBACK', 'CHARGEBACK' );
define( 'BS_IPN_RECURRING', 'RECURRING' );
define( 'BS_IPN_REFUND', 'REFUND' );

// External JS files
define( 'BS_BN2_JS', 'bsnp-checkout-bn2.js' );
define( 'BS_CSE_JS', 'bsnp-checkout-cc.js' );
define( 'BS_EX_JS', 'bsnp-currency-switcher.js' );
define( 'BS_EX_COOKIE_JS', 'js.cookie.js' );

// External CSS
define( 'BS_CSE_CSS', 'bsnp-checkout-cc.css' );
define( 'BS_LOGGER_CSS', 'bsnp-logger-css' );