<?php

/**
 * Created by PhpStorm.
 * User: fisha
 * Date: 8/6/15
 * Time: 1:07 PM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}


class Fisha_Bsnp_Globals {
	const TABLE_PREFIX = 'bluesnap';
	const SUPPORTED_CURRENCIES_TABLE_NAME = 'bluesnap_supported_currencies';

	public $bsnp_common_settings = array(
		'api_login',
		'password',
		'store_id',
		'order_contract_id',
		'cse_key',
		'data_protection_key',
		'subscriptions_contract_id',
	    'merchant_id' );
	public $refundable_payment_methods = array( 'PAYPAL', 'WEBMONEY', 'CC' );
	public $refundable_payment_methods_string = "PayPal/WebMoney/CreditCard";
	public $bsnp_xml;
	public $is_preorder_payment = false;
	public $preorder_user;
	public $payload = array();
	public $environment_url;
	public $environment;
	public $payment_status;
	public $manual_update_rates;
	public $bsnp_shopper_id;


	/**
	 * Return currencies table name
	 * @return string
	 */
	public function bsnp_get_currency_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::SUPPORTED_CURRENCIES_TABLE_NAME;
	}

	/**
	 * Return working environment url (Sandbox/Production)
	 *
	 * @param string $environment
	 * @param bool $fraud
	 *
	 * @return string
	 */
	public function bsnp_get_environment_url( $environment, $fraud = false ) {
		$bsnp_sandbox    = ( "yes" == $environment ) ? true : false;
		$environment_url = ( $bsnp_sandbox )
			? BS_SAND_BOX
			: BS_PRODUCTION;
		$environment_url .= ( ! $fraud ) ? BS_FULL_DOMAIN : '';

		return $environment_url;
	}
}

$GLOBALS['FiSHa_BSNP_Globals'] = new Fisha_Bsnp_Globals();