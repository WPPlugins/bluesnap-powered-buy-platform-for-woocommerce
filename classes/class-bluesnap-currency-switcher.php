<?php
/**
 * Created by PhpStorm.
 * User: yarivkohn
 * Date: 5/26/15
 * Time: 8:39 AM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

require_once( BLUESNAP_BASE_DIR . 'helper/bsnp-ex-scripts.php' );
require_once( BLUESNAP_BASE_DIR . '/helper/fisha-bsnp-logger.php' );
require_once( BLUESNAP_BASE_DIR . '/helper/fisha-bsnp-globals.php' );
require_once( BLUESNAP_BASE_DIR . '/helper/fisha-bsnp-db.php' );
require_once( BLUESNAP_BASE_DIR . '/helper/fisha-bsnp-ipn.php' );
require_once( BLUESNAP_BASE_DIR . '/helper/fisha-bsnp-http.php' );
require_once( BLUESNAP_BASE_DIR . '/helper/fisha-bsnp-functions.php' );
require_once( BLUESNAP_BASE_DIR . '/helper/fisha-admin-html.php' );
require_once( BLUESNAP_BASE_DIR . '/helper/fisha-bsnp-api.php' );
require_once( BLUESNAP_BASE_DIR . '/helper/fisha-bsnp-validation.php' );
require_once( ABSPATH . 'wp-includes/query.php' );

//if( class_exists( 'WC_Pre_Orders_Order' ) ) {
include_once( BLUESNAP_BASE_DIR . '/helper/fisha-bsnp-preorder.php' );
//}

//if ( class_exists( 'WC_Subscriptions_Order' ) ) {
include_once( BLUESNAP_BASE_DIR . '/helper/fisha-bsnp-subscription.php' );

//}


class Fisha_Bsnp_Currency_Exchange {

	const SIX_DECIMAL_POINT_PLACE_HOLDER = 0.000000;
	public $option_name = 'bsnp_ex_options';
	public $bsnp_exchange_rate;

	public function __construct() {
		$this->bsnp_add_currencies_list();
//      Add WP_Cron to update twice a day the currency exchange rates
		if ( ! wp_next_scheduled( 'bsnp_update_ex_rates' ) ) {
			wp_schedule_event( time(), "daily", 'bsnp_update_ex_rates' ); // can be daily, hourly or twicedaily
		}
		add_action( 'bsnp_update_ex_rates', array( $this, 'bsnp_get_daily_ex_rates' ) );
		add_action( 'admin_footer', array( $this, 'bsnp_manual_update_javascript' ) );
		add_action( 'wp_ajax_bsnp_manual_update_rates', array( $this, 'bsnp_manually_get_daily_ex_rates' ) );
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'add_order_meta' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'bluesnap_admin_notices' ) );
	}

	/**
	 * Gets daily exchange rates from BlueSnap server
	 * Rates are only being updates for currencies not locally supported by BlueSnap
	 * All exchange rates are vs. BS_DEFAULT_CURRENCY
	 */
	public function bsnp_get_daily_ex_rates() {
		$got_credentials = get_option( 'bsnp_api_login' );
		if ( '' == $got_credentials ) {
			return; // Don't try to update if plugin is not configured yet.
		}
		$GLOBALS['FiSHa_BSNP_Logger']->logger( 'Update exchange rates', 'Starting daily ex rates update' );
		$bsnp_currency_list = $this->get_currencies_list();
		foreach ( $bsnp_currency_list as $currency ) {
			$bsnp_ex_rate = $this->bsnp_convert_rates( $currency->currency_code );
			$this->update_table( $currency->currency_code, $bsnp_ex_rate );
		}
//        $ex_options = get_option('bsnp_ex_options' );
//        $ex_options['rates_were_never_update'] = false;
		update_option( BSNP_PREFIX.'_rates_were_never_update', "no" );
		$GLOBALS['FiSHa_BSNP_Logger']->logger( 'Update exchange rates', 'Finished daily ex rates update' );
	}

	/**
	 * Update exchange rates manually from admin panel
	 */
	public function bsnp_manually_get_daily_ex_rates() {
		$this->bsnp_get_daily_ex_rates();
		wp_die();
	}

	/**
	 * Update db table with new exchange rates
	 *
	 * @param $currency_code
	 * @param $bsnp_ex_rate
	 */
	public function update_table( $currency_code, $bsnp_ex_rate ) {
		$table_name = $GLOBALS['FiSHa_BSNP_Globals']->bsnp_get_currency_table_name();

		return $GLOBALS['FiSHa_BSNP_Db']->update_table( $table_name, array(
			'bluesnap_ex_rate',
			'last_update',
		), array(
			$bsnp_ex_rate,
			date( 'Y-m-d H:i:s' ),
		), array( 'currency_code' => $currency_code ), 'Update exchange rates', false );
	}

	/**
	 * Return list of currencies not locally supported
	 * @return mixed|null
	 */

	public function get_currencies_list() {
		$bsnp_currency_conversion = $GLOBALS['FiSHa_BSNP_Globals']->bsnp_get_currency_table_name();

		return $GLOBALS['FiSHa_BSNP_Db']->get_multi_column_data( array(
			'currency_code',
			'bluesnap_ex_rate'
		), $bsnp_currency_conversion, 'Update ex rates' );
	}

	/**
	 * Return exchange rate for each coin vs USD
	 *
	 * @param $currency_code
	 *
	 * @return float
	 */
	public function bsnp_convert_rates( $currency_code ) {
		//$environment = get_option( 'bsnp_ex_options' );
		$environment     = get_option( 'woocommerce_wc_gateway_bluesnap_cc_settings' );
		$is_sand_box     = $environment['environment'];
		$environment_url = $GLOBALS['FiSHa_BSNP_Globals']->bsnp_get_environment_url( $is_sand_box ) . BS_CURRENCY_CONVERTER . "?from=" . BS_DEFAULT_CURRENCY . "&to=" . $currency_code . "&amount=" . ( 1 * BS_ACCURACY_FACTOR );
		$response        = $GLOBALS['FiSHa_BSNP_Http']->bsnp_get_curl( $environment_url, true );
		if ( ! $response ) {
			return $this->currency_bad_ex_rate_log( $currency_code );
		}
		$new_amount = simplexml_load_string( $response );
		if ( $new_amount === false ) {
			return $this->currency_bad_ex_rate_log( $currency_code );
		}
		if ( isset( $new_amount->value ) && is_int( intval( $new_amount->value ) ) && ( intval( $new_amount->value ) > 0 ) ) {
			$new_amount           = $new_amount->value->__toString();
			$actual_exchange_rate = (int) $new_amount / BS_ACCURACY_FACTOR;

			return $actual_exchange_rate;
		} else {
			return $this->currency_update_error( $currency_code, $new_amount );
		}
	}


	/**
	 * @param $currency_code
	 *
	 * @return float
	 */
	public function currency_update_error( $currency_code, $response_container ) {
		if ( isset( $response_container->message->description ) ) {
			$GLOBALS['FiSHa_BSNP_Logger']->logger( 'Update exchange rates', 'The following error has occurred while trying to update exchange rates for ' . $currency_code . ' Error description: ' . $response_container->message->description->__toString() );
		} else {
			$GLOBALS['FiSHa_BSNP_Logger']->logger( 'Update exchange rates', 'An error has occurred while trying to update exchange rates for ' . $currency_code );
		}

		return self::SIX_DECIMAL_POINT_PLACE_HOLDER;
	}

	/**
	 * @param $currency_code
	 *
	 * @return float
	 */
	public function currency_bad_ex_rate_log( $currency_code ) {
		$GLOBALS['FiSHa_BSNP_Logger']->logger( 'Update exchange rates', 'An error has occurred while trying to update exchange rates for ' . $currency_code );

		return self::SIX_DECIMAL_POINT_PLACE_HOLDER;
	}

	/**
	 * Get all of the currencies from DB.
	 * @return array
	 */
	public function get_available_currencies_list() {
		$result     = $this->get_currencies_list();
		$currencies = array();
		if ( ! empty( $result ) ) {
			foreach ( $result as $currency ) {
				$GLOBALS['currency_ex_rates'][ $currency->currency_code ] = $currency->bluesnap_ex_rate;
				if ( $currency->bluesnap_ex_rate != 0 ) {
					$currencies[ $currency->currency_code ] = $currency->bluesnap_ex_rate;
				}
			}
		}
		return $currencies;
	}


	/**
	 * Get all of the currencies from DB.
	 * @return array
	 */
	/*public function get_available_currencies_list() {
		$result     = $this->get_currencies_list();
		$currencies = array();
		if ( ! empty( $result ) ) {
			foreach ( $result as $currency ) {
				$GLOBALS['currency_ex_rates'][ $currency->currency_code ] = $currency->bluesnap_ex_rate;
				if ( $currency->bluesnap_ex_rate != 0 ) {
					$currencies[ $currency->currency_code ] = $currency->bluesnap_ex_rate;
				} else {
					$skip_notice = get_option( BSNP_PREFIX.'_rates_were_never_update' );
					if ( "no" == $skip_notice && is_admin() ) {
						$this->bluesnap_bad_ex_rate( $currency->currency_code );
					}
				}
			}
		}
		return $currencies;*/



	/**
	 * replace array values value with their key name for display in multiselect html field
	 * @return array
	 */
	public function get_available_currencies_list_flipped() {
		$current_list = $this->get_available_currencies_list();
		unset( $current_list[ get_woocommerce_currency() ] );
		foreach ( $current_list as $k => $v ) {
			$current_list[ $k ] = $k;
		}

		return $current_list;
	}

	/**
	 * Show merchant a comment about bad exchange rate
	 */
	public function bluesnap_bad_ex_rate( $currency ) {
		global $current_user;
		$user_id = $current_user->ID;
		if ( ! get_user_meta( $user_id, 'ignore_notice_' . $currency ) ) {
			parse_str( $_SERVER['QUERY_STRING'], $params );
			$callback_url = '?' . http_build_query( array_merge( $params, array( 'dismiss_currency_notice' => $currency ) ) );
			printf( '<div id="notice" class="error">' );
			printf( __( 'Please note that currency %s returned bad exchange rate, and therefore can not be used in the store | <a href="%s">Hide Notice</a>', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ), $currency, $callback_url );
			printf( '</div>');
		}
	}

	/**
	 * Get list of selected currencies from db
	 * This list is being updates by the merchant via the admin panel.
	 * @return mixed|string|void
	 */
	public function get_supported_currencies() {
		$bluesnap_config = get_option('woocommerce_wc_gateway_bluesnap_cc_settings');
		$list_of_currencies = isset($bluesnap_config['cs_currencies'])? $bluesnap_config['cs_currencies'] : array();
		$list_of_ex_rates   = $this->get_selected_ex_rates( $list_of_currencies );
		return json_encode( $list_of_ex_rates );
	}

	/**
	 * @param $list_of_currencies
	 *
	 * @return array
	 */
	private function get_selected_ex_rates( $list_of_currencies ) {
		$list_with_ex             = array();
		$bsnp_currency_conversion = $GLOBALS['FiSHa_BSNP_Globals']->bsnp_get_currency_table_name();
		$result                   = $GLOBALS['FiSHa_BSNP_Db']->get_multi_column_data( array(
			'currency_code',
			'bluesnap_ex_rate',
			'is_supported'
		), $bsnp_currency_conversion, 'Get currencies list' );
		if ( ! empty( $result ) ) {
			foreach ( $result as $currency ) {
				if ( is_array($list_of_currencies) && in_array( $currency->currency_code, $list_of_currencies ) ) {
					$list_with_ex[ $currency->currency_code ] = array(
						$currency->bluesnap_ex_rate,
						$currency->is_supported
					);
				}
			}

			return $list_with_ex;
		}
	}

	/**
	 * This function is only used in BN2 module
	 * .@deprecated
	 * @return string
	 */
	public function bsnp_get_pay_currency() {
		if ( isset ( $_COOKIE['currency_code'] ) && $_COOKIE['currency_code'] != 'Default currency' ) {
			return $_COOKIE['currency_code'];
		}

		return ( $this->bsnp_currency_locally_supported( get_woocommerce_currency() ) ) ? get_woocommerce_currency() : "USD";
	}

	/**
	 * If shop currency is locally supported return true, else return false.
	 *
	 * @param $currency
	 *
	 * @return string
	 */
	function bsnp_currency_locally_supported( $currency ) {
		return true; // Bsnp now support all of the curencies
	}


	/**
	 * Given currency code return exchange_rate
	 *
	 * @param $currency
	 *
	 * @return mixed
	 */
	public function get_usd_exchange_rate( $currency ) {
		global $wpdb;
		$currency_table = $GLOBALS['FiSHa_BSNP_Globals']->bsnp_get_currency_table_name();
		$sql2           = $wpdb->prepare( "SELECT * FROM {$currency_table} WHERE currency_code = %s", $currency );
		$result         = $wpdb->get_results( $sql2 );
		if ( ! empty( $result ) ) {
			return $result[0]->bluesnap_ex_rate;
		} else if ( $currency != 'Default currency' ) {
			wc_add_notice( __( 'Unable to retrieve currency exchange rates, please try again later', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ), 'error' );
			$GLOBALS['FiSHa_BSNP_Logger']->logger( 'Data retrieve error', 'Failed to get currency exchange rates for currency: ' . $currency );
		}
	}

	/**
	 * Given wc_order_id return purchase currency
	 *
	 * @param $order_id
	 *
	 * @return mixed
	 */
	public function get_purchase_currency( $order_id ) {
		$order_data = $GLOBALS['FiSHa_BSNP_Db']->bsnp_get_order_details( $order_id );
		if ( ! is_object( $order_data ) ) {
			return null;
		}

		return $order_data->charged_currency;
	}


	/**
	 * Given wc_order_id return exchange_rate
	 *
	 * @param $order_id
	 *
	 * @return mixed
	 */
	public function get_shopperselection_exchange_rate( $order_id ) {
		$order_data = $GLOBALS['FiSHa_BSNP_Db']->bsnp_get_order_details( $order_id );
		if ( is_object( $order_data ) ) {
			return $order_data->bluesnap_ex_rate;
		}

		return 0;
	}


	/**
	 * If local currency is supported return local currency, else return BSNP default (USD)
	 * @return string
	 */
	public function bsnp_get_purchase_currency() {
		if ( $this->bsnp_currency_locally_supported( get_woocommerce_currency() ) ) {
			return get_woocommerce_currency();
		} else {
			return BS_DEFAULT_CURRENCY;
		}
	}


	/**
	 * Converts given amount using the relevant exchange rates
	 * Conversion is based upon BlueSnap servers
	 *
	 * @param $amount_to_convert
	 * @param $local_currency
	 * @param $is_new_order
	 *
	 * @return float
	 */
	public function bsnp_convert_ammount( $amount_to_convert, $local_currency, $is_new_order = false, $wc_order_id = null ) {
		if ( $is_new_order ) {
			//This is a new order, and base currency is locally supported
			$this->bsnp_exchange_rate = 1;
			// This is a new order, and base currency is not locally supported
			if ( ! $this->bsnp_currency_locally_supported( $local_currency ) ) {
				$this->bsnp_exchange_rate = $this->bsnp_get_exchange_rates( get_woocommerce_currency() );
			}
		} else if ( ! $is_new_order ) {
			// This order was already placed, take exchange rates for day of purchase
			$this->bsnp_exchange_rate = ( $this->bsnp_get_order_ex_rate( $wc_order_id ) );
		}
		if ( ( ! $this->bsnp_exchange_rate ) || ( 0 == $this->bsnp_exchange_rate ) ) {
			// in case of error with exchange rates
			return false;
		}

		return round( $amount_to_convert * $this->bsnp_exchange_rate, 2 );
	}


	/**
	 * Given currency code, returns the USD exchange rate
	 *
	 * @param $currency
	 *
	 * @return mixed
	 */
	public function bsnp_get_exchange_rates( $currency ) {
		$table_name = $GLOBALS['FiSHa_BSNP_Globals']->bsnp_get_currency_table_name();
		$result     = $GLOBALS['FiSHa_BSNP_Db']->get_column_data( 'bluesnap_ex_rate', $table_name, 'Get Exchange rates', array( 'currency_code' => $currency ) );

		return $result[0]->bluesnap_ex_rate;
	}


	/**
	 * Given order id, returns the exchange rates in day of purchase
	 *
	 * @param $order_id
	 *
	 * @return array
	 */
	public function bsnp_get_order_ex_rate( $order_id ) {
		$result = $GLOBALS['FiSHa_BSNP_Db']->bsnp_get_order_details( $order_id );
		if ( is_object( $result ) ) {
			return $result->bluesnap_ex_rate;
		} else {
			return array();
		}
	}

	/**
	 * Saves exchange rate for future refunds
	 *
	 * @param $wc_order_id
	 * @param $currency
	 * @param $ex_rate
	 */
	public function bsnp_keep_exchange_rate( $wc_order_id, $currency, $ex_rate ) {
		if ( 'none' == $ex_rate ) {
			$ex_rate = $this->bsnp_get_exchange_rate( $currency );
		}
		if(!empty($currency)){
			update_post_meta( $wc_order_id, '_charged_currency', $currency );
		}
		if(!empty($ex_rate)){
			update_post_meta( $wc_order_id, '_bsnp_ex_rate', $ex_rate );
		}
	}


	/**
	 * Return bluesnap exchange rates
	 *
	 * @param $local_currency
	 *
	 * @return bool
	 */
	public function bsnp_get_exchange_rate( $local_currency ) {
		global $wpdb;
		$bsnp_currency_table = $GLOBALS['FiSHa_BSNP_Globals']->bsnp_get_currency_table_name();
		$sql2                = $wpdb->prepare( "SELECT bluesnap_ex_rate FROM {$bsnp_currency_table} WHERE currency_code = %s", $local_currency );
		$result              = $wpdb->get_results( $sql2 );
		if ( ! empty( $result ) ) {
			return $result['0']->bluesnap_ex_rate;
		}

		return false;
	}


	/**
	 * load list of currencies to the store as JS variable.
	 */
	public function add_currency_script() {
		$local_to_us_rate = $this->bsnp_get_exchange_rate( get_woocommerce_currency() );
		?>
		<script type="text/javascript">
			list_of_currencies = <?php echo $this->get_supported_currencies() . ";"; ?>
				local_to_us_ex_rate = <?php if ( 0 == floatval( $local_to_us_rate ) ) {
					echo "false;";
				} else {
					echo ( 1 / $local_to_us_rate ) . ";";
				}
					?>
					is_currency_locally_supported = <?php echo ( $this->bsnp_currency_locally_supported( get_woocommerce_currency() ) ) ? "'Y'" : "'N'" . ";"; ?>
		</script>
		<?php
		if ( ! isset( $_COOKIE['local_to_us_ex_rate'] ) ) {
			$_COOKIE['local_to_us_ex_rate'] = $local_to_us_rate;
		}

	}

	/**
	 * Generate list of currencies selected by the merchant.
	 * This is the available coins that the shopper will be able to use.
	 */
	public function bsnp_add_currencies_list() {
		if ( ! isset( $GLOBALS['bsnp_ex_rate'] ) ) {
			$GLOBALS['bsnp_ex_rate'] = true;
			$bluesnap_config = get_option('woocommerce_wc_gateway_Bluesnap_Cc_settings');
			$bsnp_currency_ex_status = isset( $bluesnap_config['cs_status'] )? $bluesnap_config['cs_status'] : 0;
			if ( "yes" == $bsnp_currency_ex_status ) {
				add_action( 'wp_head', array( $this, 'add_currency_script' ) );
			}
		}
	}


	/**
	 * Add Javascript for 'Update rates now' button
	 */
	public function bsnp_manual_update_javascript(){
		$got_credentials = get_option('bsnp_api_login');
		if ('' != $got_credentials): ?>
			<script>
				jQuery(document).ready(function () {
					jQuery('.update_rates').click(function () {
						var element = document.querySelector("form");
						element.addEventListener("submit", function (event) {
							event.preventDefault();
						});

						var clickBtnValue = "update_rates";
						data = {
							'action': 'bsnp_manual_update_rates',
							'update_rates': clickBtnValue
						};
						jQuery('#wpbody').append('<div style="position:fixed;top:30%;left:20%;width:50%;height:3%;padding:10px;margin:5px;background-color:dimgray;font-size:20px;text-align:center;z-index:9999" id="ajax_loading_wrapper_notice"><?php echo __('Updating rates, please wait... May take up to two minutes', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce'); ?> </div>');
						jQuery('body').append('<div style="position:fixed;top:0;left:0;width:100%;height:100%;background-color:rgba(96,96,96, 0.5);z-index:9999" id="ajax_loading_wrapper"></div>');
						jQuery.post(ajaxurl, data, function (response) {
							jQuery('#ajax_loading_wrapper').remove();
							jQuery('#ajax_loading_wrapper_notice').remove();
                            element.removeEventListener("submit",function (event) {

                            });
							alert("Exchange rates were successfully updated");
							location.reload();
						});
					});
				});
			</script>
		<?php endif; ?>
		<?php
	}

	/**
	 * If shopper bought a product in currency other than base currency,
	 * Display real purchase amount in the "my account->View" page
	 *
	 * @param $order
	 */
	public function add_order_meta( $order ) {
		if ( isset( $_GET['key'] ) ) {
			return;
		}
		$this->order_meta_text( $order );
	}


	/**
	 * If shopper bought a product in currency other than base currency,
	 * add real purchase amount to email
	 *
	 * @param $order
	 */
	public function add_email_order_meta( $order, $sent_to_admin ) {
		$this->order_meta_text( $order );
	}


	/**
	 * Add comment with actual paid amount fo order
	 *
	 * @param $order
	 */
	public function order_meta_text( $order ) {
		if('wc_gateway_bluesnap_cc' != $order->payment_method) {
			return; //only add this data for BSNP orders!
		}
		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			$parent_order = wcs_get_subscriptions_for_renewal_order( $order );
			if ( $parent_order ) {
				$order = reset( $parent_order )->order;
			}
		}

		$purchase_currency = $this->get_purchase_currency( $order->id );
		if ( ! ( $purchase_currency == get_woocommerce_currency() ) ) {
			$order_exchange_rate = $this->bsnp_get_order_ex_rate( $order->id );
			if ( is_array( $order_exchange_rate ) ) {
				return;
			}
			$amount = $order->get_total();
			$charged_amount = round( ( $amount * $order_exchange_rate ), 2 );
			$isSubscription = ( class_exists( 'WC_Subscriptions_Order' ) ) ? wcs_order_contains_subscription( $order->id ) : false;
			/*if ( class_exists( 'WC_Pre_Orders_Order' ) ) {
				$bsnp_is_preorder = WC_Pre_Orders_Order::order_contains_pre_order( $order->id );
				if ( ( $bsnp_is_preorder ) && ( WC_Pre_Orders_Order::order_will_be_charged_upon_release( $order->id ) ) ) {
					return;
				}
			}*/
			?>
			<div class="bsnp_my_account_order">
				<h2>
					<?php if ( $isSubscription ) {
						$recurring_charge_amount = round( ( WC_Subscriptions_Order::get_recurring_total( $order ) * $order_exchange_rate ), 2 );
						printf( sprintf( __( 'NOTE: Actual charge for this subscription is %.2f %s up front, and %.2f %s for each renewal', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ), $charged_amount, $purchase_currency, $recurring_charge_amount, $purchase_currency ) );
					} else {
						printf( sprintf( __( 'NOTE: Actual charge for this order was: %.2f %s', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ), $charged_amount, $purchase_currency ) );
					}
					?>
				</h2>
			</div>
			<br>
			<?php
		}
	}

	/**
	 * Return exchange rates between 2 currencies
	 *
	 * @param $from
	 * @param $to
	 *
	 * @return float|mixed|null
	 */
	public function convert_currencies_ex_rates( $from, $to ) {
		$ex_rate = null;
		if ( 'USD' == $from ) {
			$ex_rate = $this->get_usd_exchange_rate( $to );
		} else {
			$ex_rate = $this->get_usd_exchange_rate( $from ) / $this->get_usd_exchange_rate( $to );
		}

		return $ex_rate;
	}


	/**
	 * In case of bad exchange rate show admin notice about the error
     */
	public function bluesnap_admin_notices() {
		$result = $this->get_currencies_list();
		if (!empty($result)) {
			foreach ($result as $currency) {
				if(empty((float)$currency->bluesnap_ex_rate)){
					$skip_notice = get_option(BSNP_PREFIX . '_rates_were_never_update');
					if ("no" == $skip_notice && is_admin()) {
						$this->bluesnap_bad_ex_rate($currency->currency_code);
					}
				}
			}
		}
	}
}

$GLOBALS['FiSHa_BSNP_Currency_Switcher'] = new Fisha_Bsnp_Currency_Exchange();




