<?php
/**
 * Created by PhpStorm.
 * User: yarivkohn
 * Date: 4/2/15
 * Time: 11:40 AM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

function bsnp_scripts() {
	wp_register_script( 'bsnp-cse', BLUESNAP_JS_LOCATION );
	wp_enqueue_script( 'bsnp-cse' );
}

function bsnp_cc_scripts() {
	$css_location = BLUESNAP_BASE_URL . 'css/';
	$scriptsrc    = BLUESNAP_BASE_URL . 'js/';
	wp_register_script( 'bsnp-cc', $scriptsrc . BS_CSE_JS, array( 'jquery' ), '0.6', true );
	wp_enqueue_script( 'bsnp-cc' );
	wp_register_style( 'bsnp-css', $css_location . BS_CSE_CSS );
	wp_enqueue_style( 'bsnp-css' );
}

add_action( 'wp_enqueue_scripts', 'bsnp_cc_scripts', 0 );
add_action( 'wp_enqueue_scripts', 'bsnp_scripts', 10 );

