<?php
/**
 * Created by PhpStorm.
 * User: yarivkohn
 * Date: 6/4/15
 * Time: 9:07 AM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}


function bsnp_ex_scripts() {
	$scriptsrc = BLUESNAP_BASE_URL . 'js/';
	wp_register_script( 'bsnp-ex', $scriptsrc . BS_EX_JS, array( 'jquery' ), '0.6' );
	wp_register_script( 'bsnp-ex-cookie', $scriptsrc . BS_EX_COOKIE_JS, array( 'jquery' ), '0.6' );
	wp_enqueue_script( 'bsnp-ex' );
	wp_enqueue_script( 'bsnp-ex-cookie' );
}

add_action( 'wp_enqueue_scripts', 'bsnp_ex_scripts' );
