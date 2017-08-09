<?php
/**
 * Created by PhpStorm.
 * User: yarivkohn
 * Date: 3/15/15
 * Time: 2:42 PM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Add allowed payment methods for this plugin
 * use key=.value, while key is the id and value is the name.
 * for each method need to create file named class-bluesnap-id, and class named WC_Gateway_Bluesnap_key
 * i.e in order to add a method named payme with class named payit:
 * 1: create file named class-blusnap-payme.php.
 * 2: inside the file declare class named WC_Gateway_Bluesnap_payit extends WC_Payment_Gateway
 * 3: add 'Bluesnap_payit' => 'payme' to the $bluesnap_available_payment_methods array.
 **/

$bluesnap_available_payment_methods = array(
	'Bluesnap_Cc' => 'cc',
);
