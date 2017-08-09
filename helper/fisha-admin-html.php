<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Created by PhpStorm.
 * User: fisha
 * Date: 8/17/15
 * Time: 12:30 PM
 */
class Fisha_Bsnp_Admin_Html {
//    private $option_name ='bsnp_ex_options';
	private $id = "wc_gateway_bluesnap_cc";

	/**
	 * Shows IPN rout in admin panel
	 */
	function bsnp_display_ipn_rout() {
		echo "<tr><th>";
		printf( __( 'Use this url in BlueSnap store account, in order to define IPN:', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ) );
		echo "</th><td>";
		printf( " <code>" . get_site_url() . "/wc-api/bluesnapipn/ </code>" );
		echo "</td></tr>";
	}


	/**
	 * create selection data for month
	 * @return string
	 */
	public function get_valid_expiry_month() {
		$bsnp_cc_month = "<option value=''>" . __( 'Month', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ) . "</option>";
		for ( $i = 1; $i <= 12; $i ++ ) {
			$i_val = ( ( $i <= 9 ) ? '0' . $i : $i );
			$bsnp_cc_month .= "<option value=" . $i_val . ">" . $i_val . "</option>";
		}

		return $bsnp_cc_month;
	}

	/**
	 * Create selection data for year
	 * @return string
	 */
	public function get_valid_expiry_year() {
		$bsnp_cc_year = "<option value=''>" . __( 'Year', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ) . "</option>";
		$today        = getdate();
		$limit        = 15;
		if ( isset( $this->valid_year_limit ) && trim( $this->valid_year_limit ) > 0 && $GLOBALS['FiSHa_BSNP_Functions']->is_digits( $this->valid_year_limit ) ) {
			$limit = $this->valid_year_limit;
		}
		for ( $i = $today['year']; $i < ( $today['year'] + $limit ); $i ++ ) {
			$bsnp_cc_year .= "<option value=" . $i . ">" . $i . "</option>";
		}

		return $bsnp_cc_year;
	}
}

$GLOBALS['FiSHa_BSNP_Html'] = new Fisha_Bsnp_Admin_Html();