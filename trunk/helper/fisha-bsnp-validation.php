<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Created by PhpStorm.
 * User: fisha
 * Date: 8/10/15
 * Time: 10:29 AM
 */
class Fisha_Bsnp_Validation {

	const ALLOWED_IPS_CSV_FILENAME = "allowed-ips.csv";

	public $has_error;
	public $cookie_integrity_failure = false;
	protected $list_of_countries_with_states = array( 'US', 'CA' );


	/**
	 *  Exit if IPN was not received form BSNP.
	 */
	public function bsnp_is_valid_call() {
		$BlueSnapIps = $this->load_allowed_ip_list( self::ALLOWED_IPS_CSV_FILENAME );
		if ( array_search( $_SERVER['REMOTE_ADDR'], $BlueSnapIps ) === false ) {
			exit( $_SERVER['REMOTE_ADDR'] . " is not a BlueSnap server!!!" );
		}
	}


	/**
	 * Load list of allowed ips from csv file
	 *
	 * @param $csv_file
	 *
	 * @return array
	 */
	private function load_allowed_ip_list( $csv_file ) {
		$list_of_ips  = array();
		$path_to_file = BLUESNAP_BASE_DIR . "csv/" . $csv_file;
		$file_handle  = fopen( $path_to_file, 'r' );
		while ( ! feof( $file_handle ) ) {
			$csv_line      = fgetcsv( $file_handle, 1024 );
			$list_of_ips[] = $csv_line[0];
		}
		fclose( $file_handle );

		return $list_of_ips;
	}

	/**
	 * Validate shopper details before sending form
	 * @return bool
	 */
	public function bsnp_validate_details() {
		$this->has_error = false;
		$this->bsnp_validate_shopper_name_length();
		$this->bsnp_validate_shopper_last_name_length();

		return $this->has_error;
	}

	/**
	 * Verify minimal length for first name
	 */
	private function bsnp_validate_shopper_name_length() {
		if ( isset( $_POST['billing_first_name'] ) && strlen( $_POST['billing_first_name'] ) < BSNP_MIN_SHOPPER_NAME_LENGTH ) {
			wc_add_notice( sprintf( __( 'Billing first name must be at least %s characters long', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ), BSNP_MIN_SHOPPER_NAME_LENGTH ), 'error' );
			$this->has_error = true;
		}

		if ( ( isset( $_POST['billing_first_name'] ) ) && ( strlen( $_POST['billing_first_name'] ) > BSNP_MAX_SHOPPER_NAME_LENGTH ) ) {
			wc_add_notice( sprintf( __( 'Billing first name must be %s (or less) characters long', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ), BSNP_MAX_SHOPPER_NAME_LENGTH ), 'error' );
			$this->has_error = true;
		}
		if ( isset( $_POST['ship_to_different_address'] ) && "1" == $_POST['ship_to_different_address'] ) {
			if ( isset( $_POST['shipping_first_name'] ) && strlen( $_POST['shipping_first_name'] ) < BSNP_MIN_SHOPPER_NAME_LENGTH ) {
				wc_add_notice( sprintf( __( 'Shipping first name must be at least %s characters long', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ), BSNP_MIN_SHOPPER_NAME_LENGTH ), 'error' );
				$this->has_error = true;
			}
			if ( ( isset( $_POST['shipping_first_name'] ) ) && ( strlen( $_POST['shipping_first_name'] ) > BSNP_MAX_SHOPPER_NAME_LENGTH ) ) {
				wc_add_notice( sprintf( __( 'Shipping first name must be %s (or less) characters long', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ), BSNP_MAX_SHOPPER_NAME_LENGTH ), 'error' );
				$this->has_error = true;
			}
		}
	}

	/**
	 * Verify minimal length for last name
	 */
	private function bsnp_validate_shopper_last_name_length() {
		if ( ( isset( $_POST['billing_last_name'] ) ) && ( strlen( $_POST['billing_last_name'] ) < BSNP_MIN_SHOPPER_NAME_LENGTH ) ) {
			wc_add_notice( sprintf( __( 'Billing last name must be at least %s characters long', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ), BSNP_MIN_SHOPPER_NAME_LENGTH ), 'error' );
			$this->has_error = true;
		}
		if ( ( isset( $_POST['billing_last_name'] ) ) && ( strlen( $_POST['billing_last_name'] ) > BSNP_MAX_SHOPPER_NAME_LENGTH ) ) {
			wc_add_notice( sprintf( __( 'Billing last name must be %s (or less) characters long', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ), BSNP_MAX_SHOPPER_NAME_LENGTH ), 'error' );
			$this->has_error = true;
		}
		if ( isset( $_POST['ship_to_different_address'] ) && "1" == $_POST['ship_to_different_address'] ) {
			if ( ( isset( $_POST['shipping_last_name'] ) ) && ( strlen( $_POST['shipping_last_name'] ) < BSNP_MIN_SHOPPER_NAME_LENGTH ) ) {
				wc_add_notice( sprintf( __( 'Shipping last name must be at least %s characters long', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ), BSNP_MIN_SHOPPER_NAME_LENGTH ), 'error' );
				$this->has_error = true;
			}
			if ( ( isset( $_POST['shipping_last_name'] ) ) && ( strlen( $_POST['shipping_last_name'] ) > BSNP_MAX_SHOPPER_NAME_LENGTH ) ) {
				wc_add_notice( sprintf( __( 'Shipping last name must be %s (or less) characters long', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ), BSNP_MAX_SHOPPER_NAME_LENGTH ), 'error' );
				$this->has_error = true;
			}
		}
	}

	/**
	 * Validates credit card details
	 * @return mixed
	 */
	public function bsnp_validate_cc_details() {
		if ( ! ( isset( $_POST['reused_credit_card'] ) && ( $_POST['reused_credit_card'] ) != '' ) ) {
			$this->bsnp_validate_cc_length();
			$this->bsnp_validate_cvv_length();
			$this->bsnp_validate_exp_date();

			return $this->has_error;
		}

	}

	/**
	 * Validate CC number entered by the user
	 */
	public function bsnp_validate_cc_length() {
		if ( null == $_POST['encryptedCreditCard'] ) {
			wc_add_notice( __( 'Please enter credit card number', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ), 'error' );
			$this->has_error = true;
		} else {
			$creditCardMniMax = $this->getCreditCardMinMax( sanitize_text_field( $_POST['credit-card-type'] ) );
			if ( ! $this->bsnp_validate_length( $GLOBALS['FiSHa_BSNP_Functions']->is_digits( $_POST['credit-card-digit-num'], true ), $creditCardMniMax ) ) {
				wc_add_notice( __( 'The number of digits entered is not suitable for your card type', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ), 'error' );
				$this->has_error = true;
			}
		}

	}

	/**
	 * Validate CVV length entered by the user
	 */
	public function bsnp_validate_cvv_length() {
		if ( null == $_POST['encryptedCvv'] ) {
			wc_add_notice( __( 'Please enter cvv number', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ), 'error' );
			$this->has_error = true;
		} else {
			$cvvMinMaxDigit = $this->getCvvMinMaxDigit( sanitize_text_field( $_POST['credit-card-type'] ) );
			if ( ! $this->bsnp_validate_length( $GLOBALS['FiSHa_BSNP_Functions']->is_digits( $_POST['cvv-digit-num'], true ), $cvvMinMaxDigit ) ) {
				wc_add_notice( __( 'CVV number should be 4 digits for AMEX or 3 digits for all other cards', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ), 'error' );
				$this->has_error = true;
			}
		}
	}

	/**
	 * Validates exp date entered by the user
	 */
	public function bsnp_validate_exp_date() {
		$year  = date( "Y" );
		$month = date( "m" );
		if ( ( "" == $_POST['wc_gateway_bluesnap_cc_exp_month'] ) || ( "" == $_POST['wc_gateway_bluesnap_cc_exp_year'] ) ) {
			wc_add_notice( __( 'Please enter card expiry date', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ), 'error' );
			$this->has_error = true;
		} else {
			if ( ( $_POST['wc_gateway_bluesnap_cc_exp_year'] < $year ) ) {
				wc_add_notice( __( 'Your exp year is incorrect', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ), 'error' );
				$this->has_error = true;
			}
			if ( ( $year == $_POST['wc_gateway_bluesnap_cc_exp_year'] ) && ( $_POST['wc_gateway_bluesnap_cc_exp_month'] < $month ) ) {
				wc_add_notice( __( 'Your exp month is incorrect', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ), 'error' );
				$this->has_error = true;
			}
		}
	}

	/**
	 * Check if a given string has desired length
	 *
	 * @param $stringToValidate
	 * @param $available_lengths
	 *
	 * @return bool
	 */
	private function bsnp_validate_length( $stringToValidate, $available_lengths ) {
		$length = intval( $stringToValidate );
		if ( ! in_array( $length, $available_lengths ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Return min and max number of digits for a given credit card
	 * Default is 16 digits, we only consider special cases in the switch.
	 *
	 * @param $ccType
	 *
	 * @return array
	 */
	private function getCreditCardMinMax( $ccType ) {

		switch ( $ccType ) {
			case 'visa':
				$creditCardMinMax = array( 13, 16 );
				break;
			case 'dinersclub':
			case 'diners':
				$creditCardMinMax = array( 14, 14 );
				break;
			case 'amex':
				$creditCardMinMax = array( 15, 15 );
				break;
			case 'jcb':
				$creditCardMinMax = array( 15, 16 );
				break;
			case 'mastercard':
				$creditCardMinMax = array( 16, 16 );
				break;
			default:
				$creditCardMinMax = array( 16, 16 );

		}

		return $creditCardMinMax;
	}

	/**
	 * Return number of CVV digits for a given Credit Card.
	 *
	 * @param $ccType
	 *
	 * @return array
	 */
	private function getCvvMinMaxDigit( $ccType ) {
		$cvvMixMax = array( 3, 3 );
		if ( 'amex' == $ccType ) {
			$cvvMixMax = array( 4, 4 );
		}

		return $cvvMixMax;
	}


	/**
	 * Only provide states to specific countries
	 *
	 * @param $customer_order
	 *
	 * @return string
	 */
	public function country_shipping_states_approved( $customer_order ) {
		$shipping_state = $customer_order->shipping_state;
		if ( ! in_array( $customer_order->shipping_country, $this->list_of_countries_with_states ) ) {
			$shipping_state = '';
		}

		return $shipping_state;
	}


	/**
	 * Only provide states to specific countries
	 *
	 * @param $customer_order
	 *
	 * @return string
	 */
	public function country_billing_states_approved( $customer_order ) {
		$billing_state = $customer_order->billing_state;
		if ( ! in_array( $customer_order->shipping_country, $this->list_of_countries_with_states ) ) {
			$billing_state = '';
		}

		return $billing_state;
	}


	/**
	 * Verify that the Bluesnap encyption function is working
	 * @return bool
	 */
	public function validate_credit_card_details_encryption() {
		if ( ! isset( $_POST['encryptedCreditCard'] ) || ! isset( $_POST['encryptedCvv'] ) ) {
			$GLOBALS['FiSHa_BSNP_Logger']->logger( 'CSE data encryption', 'Credit card details encryption has failed' );

			return false;
		} else {
			return true;
		}
	}


	/**
	 * If shopper update failed, show an error message
	 */
	public function shopper_update_error() {
		$bsnp_error_msg = __( 'Unknown error has occurred', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' );
		$GLOBALS['FiSHa_BSNP_Logger']->logger( 'Update shopper error', 'BlueSnap response with empty data' );
		wc_add_notice( $bsnp_error_msg, 'error' );
	}

	/**
	 * If shopper update failed, show an error message
	 */
	public function shopper_general_error() {
		$bsnp_error_msg = __( 'Unknown error has occurred', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' );
		$GLOBALS['FiSHa_BSNP_Logger']->logger( 'Unknown error', 'BlueSnap response with empty data' );
		wc_add_notice( $bsnp_error_msg, 'error' );
	}


	/**
	 * Validate data integrity of the cookie
	 * @return bool
	 */
	public function bsnp_validate_cookie_details() {
		$bluesnap_config = get_option('woocommerce_wc_gateway_bluesnap_cc_settings');

//		$bsnp_ex_status     = get_option( BSNP_PREFIX.'_cs_status' );
		$bsnp_ex_status     = $bluesnap_config['cs_status'];

//		$bsnp_ex_currencies = get_option( BSNP_PREFIX.'_cs_currencies' );
		$bsnp_ex_currencies = $bluesnap_config['cs_currencies'];
		if ( $bsnp_ex_status != 1 ) {
			// if currency switcher is off, then no validation is due
			return false;
		}
		$this->validate_currency_code( unserialize( $bsnp_ex_currencies ) );
		$this->validate_is_currency_locally_supported();
		$this->validate_us_ex_factor();
		$this->validate_ex_factor();

		return $this->cookie_integrity_failure;
	}

	/**
	 * Validate forbidden changes to the paying currency code
	 *
	 * @param $allowed_currencies
	 */
	private function validate_currency_code( $allowed_currencies ) {
		if ( isset( $_COOKIE['currency_code'] ) && ! in_array( $_COOKIE['currency_code'], $allowed_currencies ) && $_COOKIE['currency_code'] != 'Default currency' ) {
			$this->cookie_integrity_failure = true;
		}

	}

	/**
	 * Validate changes for is currency locally supported or not
	 */
	private function validate_is_currency_locally_supported() {
		if ( $this->cookie_integrity_failure ) {
			return; // if previous test fail no need to continue testing
		}
		if ( isset( $_COOKIE['currency_code'] ) && $_COOKIE['currency_code'] != 'Default currency' ) {
			if ( isset( $_COOKIE['is_shopper_selection_supported'] ) && 'Y' == $_COOKIE['is_shopper_selection_supported'] ) {
				$is_locally_supported = true;
			} else {
				$is_locally_supported = false;
			}
			$is_currency_code_supported = $GLOBALS['FiSHa_BSNP_Currency_Switcher']->bsnp_currency_locally_supported( $_COOKIE['currency_code'] );
		} else {
			if ( isset( $_COOKIE['is_locally_supported'] ) && 'Y' == $_COOKIE['is_locally_supported'] ) {
				$is_locally_supported = true;
			} else {
				$is_locally_supported = false;
			}
			$is_currency_code_supported = $GLOBALS['FiSHa_BSNP_Currency_Switcher']->bsnp_currency_locally_supported( get_woocommerce_currency() );
		}
		if ( ! ( $is_locally_supported === $is_currency_code_supported ) ) {
			$this->cookie_integrity_failure = true;
		}
	}


	/**
	 * Validate changes to the exchange rates
	 */
	private function validate_us_ex_factor() {
		if ( $this->cookie_integrity_failure ) {
			return; // if previous test fail no need to continue testing
		}
		$local_to_us_ex_rate = round( ( 1 / $GLOBALS['FiSHa_BSNP_Currency_Switcher']->get_usd_exchange_rate( get_woocommerce_currency() ) ), 2 );
		if ( $local_to_us_ex_rate != round( $_COOKIE['us_ex_rate'], 2 ) ) {
			$this->cookie_integrity_failure = true;
		}
	}


	/**
	 * Validate changes to the exchange rates
	 */
	private function validate_ex_factor() {

		if ( $this->cookie_integrity_failure ) {

			return; // if previous test fail no need to continue testing

		}

		$currency_code_for_convertion = ( "Default currency" == $_COOKIE['currency_code'] ) ? get_woocommerce_currency() : $_COOKIE['currency_code'];

		// Ex rate for USD:Shopper selection
		$ex_rate = 1 / $GLOBALS['FiSHa_BSNP_Currency_Switcher']->get_usd_exchange_rate( $currency_code_for_convertion );

		// Ex rate for USD:Store base currency
		$local_to_us_ex_rate = 1 / $GLOBALS['FiSHa_BSNP_Currency_Switcher']->get_usd_exchange_rate( get_woocommerce_currency() );

		// Ex rate for Store base currency:Shopper selection
		$calculated_ex_rate = round( ( $local_to_us_ex_rate / $ex_rate ), 2 );


		if ( isset( $_COOKIE['ex_factor'] ) && ( $_COOKIE['currency_code'] != 'Default currency' ) ) { // There was some change in the currency selection, and the shopper didn't set it back to 'Default currency'

			if ( $calculated_ex_rate != round( $_COOKIE['ex_factor'], 2 ) ) {

				$this->cookie_integrity_failure = true;

			}

		} else { //The currency was never changed, and 'Default currency' is selected as default

			if ( $calculated_ex_rate != 1 || $_COOKIE['currency_code'] != 'Default currency' ) {

				$this->cookie_integrity_failure = true;

			}

		}


	}

} // end of Fisha_Bsnp_Validation class


$GLOBALS['FiSHa_BSNP_Validation'] = new Fisha_Bsnp_Validation();