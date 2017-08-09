<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

$title                = 'Credit/Debit card';
$payment_method_title = get_option( 'woocommerce_wc_gateway_bluesnap_cc_settings' );
if ( ! is_null( $payment_method_title['title'] ) && $payment_method_title['title'] != '' ) {
	$title = $payment_method_title['title'];
}
echo sprintf( __( '%s Please note: %s When using %s, subscription renewal prices in store currency might be different than original price due to changes in currency rates. %s
                      This will not affect you, as any charges will be done on the currency of your choice, and these values will be the same at every subscription renewal. Any refunds will also be applied in the same currency the initial purchase was done. %s', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ), "<b><u>", "</u><p>", $title, "<br>", "</b></p>" );