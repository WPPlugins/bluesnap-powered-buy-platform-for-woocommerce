<?php


/**
 * Plugin Name: BlueSnap Powered Buy Platform for WooCommerce
 * Plugin URI: http://woothemes.com/products/bluesnap-powered-buy-platform-for-woocommerce/
 * Description: WooCommerce gateway module using CSE.
 * Version: 2.0.13
 * Author: WooThemes
 * Developer: FiSHa
 * Developer URI: http://wwww.fisha.co.il
 * Text Domain: bluesnap-powered-buy-platform-for-woocommerce
 * License: License by BlueSnap
 * WC requires at least: 2.6.7
 * WC tested up to: 2.6.13
 *
 * Copyright: © 2009-2015 WooThemes.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit();
}


// BlueSnap Gateway plugin base directory and URL
define( 'BLUESNAP_BASE_DIR', plugin_dir_path( __FILE__ ) );
define( 'BLUESNAP_BASE_URL', plugin_dir_url( __FILE__ ) );


// Load allowed payment methods
include_once( BLUESNAP_BASE_DIR . '/helper/allowed-payment-methods.php' );

include_once( BLUESNAP_BASE_DIR . '/helper/bluesnap-config.php' );
include_once( BLUESNAP_BASE_DIR . '/helper/fisha-bsnp-globals.php' );
include_once( BLUESNAP_BASE_DIR . '/helper/fisha-bsnp-db.php' );
include_once( BLUESNAP_BASE_DIR . '/helper/fisha-bsnp-http.php' );
include_once( BLUESNAP_BASE_DIR . '/helper/fisha-bsnp-functions.php' );

include_once( BLUESNAP_BASE_DIR . '/helper/fisha-bsnp-logger.php' );
include_once( BLUESNAP_BASE_DIR . '/helper/fisha-bsnp-ipn.php' );
include_once( BLUESNAP_BASE_DIR . '/helper/fisha-admin-html.php' );
include_once( BLUESNAP_BASE_DIR . '/helper/fisha-bsnp-api.php' );
include_once( BLUESNAP_BASE_DIR . '/helper/fisha-bsnp-validation.php' );
include_once( BLUESNAP_BASE_DIR . '/helper/fisha-bsnp-update.php' );

include_once( BLUESNAP_BASE_DIR . '/classes/class-bluesnap-currency-switcher.php' );

require_once( BLUESNAP_BASE_DIR . 'helper/bsnp-load-scripts.php' );

//if( class_exists( 'WC_Pre_Orders_Order' ) ) {
include_once( BLUESNAP_BASE_DIR . '/helper/fisha-bsnp-preorder.php' );
//}

//if ( class_exists( 'WC_Subscriptions_Order' ) ) {
include_once( BLUESNAP_BASE_DIR . '/helper/fisha-bsnp-subscription.php' );

//}


class Bsnp_Payment_Gateway {

	public function __construct() {
		// Load translations if exist (i18n support)
		load_plugin_textdomain( 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		add_action( 'init', array( $this, 'init_bluesnap_class' ), 0 );
		add_action( 'admin_init', array( $this, 'bsnp_gateway_init' ) );
		add_action( 'admin_notices', array( $this, 'bluesnap_admin_notices' ) );
//        add_action( 'admin_menu', array( $this, 'addAdminMenu' ) );

		add_action( 'woocommerce_api_bluesnapipn', array( $GLOBALS['FiSHa_BSNP_IPN'], 'ipn_handler' ) );

		add_action( 'woocommerce_before_my_account', array(
			$GLOBALS['FiSHa_BSNP_Subscriptions'],
			'bsnp_price_notification'
		) );

		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_bluesnap_classes' ), 0 );
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'bluesnap_filter_gateways' ), 0 );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'bluesnap_action_links' ) );

		if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
			register_activation_hook( __FILE__, array( $this, 'bluesnap_activation' ) );
			register_deactivation_hook( __FILE__, array( $this, 'bluesnap_deactivation' ) );
		}
	}

	/**
	 * Check for minimal requirements to run the plugin
	 */
	public function bluesnap_admin_notices() {
		if ( ( ! $this->bluesnap_is_woocommerece_installed_and_active() ) || ( ! $this->bluesnap_check_for_minimal_version() ) || ( ! $this->bsnp_is_currency_supported() ) || ( ! $this->bluesnap_are_permalinks_defined() ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
		}
		$this->do_ssl_check();
	}

	/**
	 * Create Admin panel
	 */
	public function bsnp_gateway_init() {
		global $current_user;
		$user_id = $current_user->ID;
		if ( isset( $_GET['dismiss_ssl_notice'] ) && $_GET['dismiss_ssl_notice'] != '' ) {
			update_user_meta( $user_id, 'ignore_notice_ssl', 'true' );
		}
		if ( isset( $_GET['dismiss_currency_notice'] ) && $_GET['dismiss_currency_notice'] != '' ) {
			update_user_meta( $user_id, 'ignore_notice_'.$_GET['dismiss_currency_notice'], 'true');
		}
	}

	/**
	 * Alert if force SSL is not check in admin options
	 */
	public function do_ssl_check() {
		if ( "no" == get_option( 'woocommerce_force_ssl_checkout' ) ) {
			printf( "<div class=\"error\"><p>" . sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a> You will not be able to use this Gateway without SSL in production mode", 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ), "BlueSnap payment gateway", admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . "</p></div>" );
		}
	}

	/**
	 * Checks if WooCommerce is installed and active
	 * @return bool
	 */
	public function bluesnap_is_woocommerece_installed_and_active() {
		if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			printf( '<div id="notice" class="error">' );
			printf( __( 'BlueSnap payment gateway MUST have ', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ) . '<a href="http://www.woothemes.com/woocommerce/" target="_new">' . __( 'WooCommerce', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ) . '</a>' . ' ' . __( 'plugin installed and active in order to run.', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ) );
			printf( '</div>', "\n" );

			return false;
		}

		return true;
	}

	/**
	 * Check if the version on of WooCommerce running on your system is sufficient
	 * @return bool
	 */
	public function bluesnap_check_for_minimal_version() {
		if ( version_compare( get_bloginfo( 'version' ), '4.1', '<' ) ) {  //For now, we will support 4.1 or greater, since WC does not support older versions
			printf( '<div id="notice" class="error">' );
			printf( __( 'Minimal WordPress version to run BlueSnap payment gateway is 4.1 please update your version and try again. ', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ) );
			printf( '</div>', "\n" );

			return false;
		}

		return true;
	}

	/**
	 * Return true if currency is supported by BlueSnap, else return false.
	 * @return string
	 */
	public function bsnp_is_currency_supported() {
		return $this->bsnp_get_currency_status( get_woocommerce_currency() );
	}

	/**
	 * Verify that permalinks are correctly defined
	 * @return bool
	 */
	public function bluesnap_are_permalinks_defined() {
		global $wp_rewrite;
		if ( empty( $wp_rewrite->permalink_structure ) ) {
			printf( '<div id="notice" class="error">' );
			printf( __( 'In order to use BlueSnap payment gateway you <b>MUST</b> <a href="%s">set</a> your permalink structure to something other than "default"', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ), admin_url( 'options-permalink.php' ) );
			printf( '</div>', "\n" );
			return false;
		}

		return true;
	}

	/**
	 * If shop currency is supported return true, else return false.
	 *
	 * @param $currency
	 *
	 * @return string
	 */
	public function bsnp_get_currency_status( $currency ) {
		global $wpdb;
		$bsnp_currency_conversion = $GLOBALS['FiSHa_BSNP_Globals']->bsnp_get_currency_table_name();
		$sql                      = "SELECT is_supported FROM {$bsnp_currency_conversion} WHERE currency_code = '$currency'";
		$result                   = $wpdb->get_results( $sql );
		if ( ! empty( $result ) ) {
			if ( "N" == $result[0]->is_supported ) {
				printf( '<div id="notice" class="error">' );
				printf( __( 'Your store currency ( %s ) is not locally supported by BlueSnap all charges will be converted to USD ($) ', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ), get_woocommerce_currency() );
				printf( '</div>', "\n" );
			}

			return true;
		}
		printf( '<div id="notice" class="error">' );
		printf( __( 'Your local currency ( %s ) is not supported by this plugin click <a href ="%s"> here </a> to change your store settings', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ), get_woocommerce_currency(), '/wp-admin/admin.php?page=wc-settings' );
		printf( '</div>', "\n" );

		return false;
	}

	/**
	 * Upon activation a conversion table is being created.
	 * This table will contain conversion data between WC shopper id and BlueSnap shopper id.
	 */
	function bluesnap_activation() {
		global $wpdb;
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		if ( ! get_option( 'bsnp_gateway_version' ) || get_option( 'bluesnap_gateway_version' ) != BLUESNAP_GATEWAY_VERSION ) {
			$charset_collate = '';
			if ( ! empty ( $wpdb->charset ) ) {
				$charset_collate = 'DEFAULT CHARACTER SET ' . $wpdb->charset;
			}
			if ( ! empty ( $wpdb->collate ) ) {
				$charset_collate .= ' COLLATE ' . $wpdb->collate;
			}
			$bsnp_currency_conversion = $GLOBALS['FiSHa_BSNP_Globals']->bsnp_get_currency_table_name(); //$wpdb->prefix . 'bluesnap_supported_currencies';
			$bsnp_currency_table_sql  = <<<EOS
            CREATE TABLE IF NOT EXISTS $bsnp_currency_conversion (
			`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			`currency_code` VARCHAR(5) NOT NULL COMMENT 'Short currency code',
			`country` VARCHAR(50) NOT NULL COMMENT 'Country full name',
			`is_supported` CHAR(1) NOT NULL COMMENT 'Is currency supported? Y = Yes, N = No',
			`bluesnap_ex_rate` DECIMAL(15,6) COMMENT 'BlueSnap currency exchange rates',
			`last_update` DATETIME COMMENT 'exchange rates last update',
			PRIMARY KEY (`id`)
		) $charset_collate COMMENT='List of currencies supported by BlueSnap';
EOS;
			dbDelta( $bsnp_currency_table_sql );

			if ( !get_option( 'bsnp_db_version' ) ) {
				add_option( 'bsnp_db_version', BLUESNAP_DB_VERSION );
				$GLOBALS['FiSHa_BSNP_Db']->load_csv_line_by_line( $bsnp_currency_conversion, "currencies.csv" );
			} else if ( get_option( 'bsnp_db_version' ) != BLUESNAP_DB_VERSION ) {
				update_option( 'bsnp_db_version', BLUESNAP_DB_VERSION );
				$this->bsnp_update_db();
			}
			if ( ! get_option( 'bsnp_gateway_version' ) ) {
				add_option( 'bsnp_gateway_version', BLUESNAP_GATEWAY_VERSION );
			} else if ( get_option( 'bsnp_gateway_version' ) != BLUESNAP_GATEWAY_VERSION ) {
				update_option( 'bsnp_gateway_version', BLUESNAP_GATEWAY_VERSION );
			}
			update_option( BSNP_PREFIX.'_rates_were_never_update', "yes" );

		}
	}

	/**
	 * Clear schedule actions that were added by Bluesnap
	 */
	public function bluesnap_deactivation() {
		wp_clear_scheduled_hook( 'bsnp_update_ex_rates' );
		wp_clear_scheduled_hook( 'bsnp_clear_logs' );
	}

	/**
	 * Upon logout, clear some globals
	 */
	public function bluesnap_logout() {
		if ( isset( $GLOBALS['bluesnap_config'] ) ) {
			$GLOBALS['bluesnap_config'] = false;
		}
		if ( isset( $GLOBALS['bluesnap_functions'] ) ) {
			$GLOBALS['bluesnap_functions'] = false;
		}
		if ( isset( $GLOBALS['currency_ex_rates'] ) ) {
			$GLOBALS['currency_ex_rates'] = false;
		}
	}

	/**
	 * Use this function to update/alter the db data and tables in next versions.
	 */
	public function bsnp_update_db() {
		// Upgrade users from version 1.x to 2.x
		// We need to take care of all the orders which created by the old version
		$GLOBALS['FiSHa_BSNP_UPDATE']->update_orders_to_new_architecture();
		$GLOBALS['FiSHa_BSNP_UPDATE']->update_users_to_new_architecture();
	}

	/**
	 * Add setting links for the gateways
	 *
	 * @param $links
	 *
	 * @return array
	 */
	public function bluesnap_action_links( $links ) {
		$plugin_links = array(
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_bluesnap_cc' ) . '">' . __( 'CSE Settings', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ) . '</a>',
		);

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Load payment gateways
	 */
	public function init_bluesnap_class() {
		global $bluesnap_available_payment_methods;
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}
		foreach ( $bluesnap_available_payment_methods as $class_postfix => $payment_method_postfix ) {
			require_once( dirname( __FILE__ ) . '/classes/class-bluesnap-' . $payment_method_postfix . '.php' );
		}
	}

	/**
	 * Add paymentgateway class files
	 */
	public function add_bluesnap_classes( $methods ) {
		global $bluesnap_available_payment_methods;
		foreach ( $bluesnap_available_payment_methods as $class_postfix => $payment_method_postfix ) {
			$methods[] = 'WC_Gateway_' . $class_postfix;
		}

		return $methods;
	}

	/**
	 * Disable payments methods that not configured with valid details
	 */
	public function bluesnap_filter_gateways( $gateways ) {
		global $bluesnap_available_payment_methods;
		$bs_undefined_gateways = array();
		foreach ( $bluesnap_available_payment_methods as $class_postfix => $payment_method_postfix ) {
			$configurations = $this->admin_settings( $class_postfix );
			$bs_isok = $this->validate_admin_setting( $configurations );
			$is_ssl_on_production = $this->get_production_ssl_status();
			if ( ! $bs_isok || ! $is_ssl_on_production ) {
				if("Bluesnap_Cc" == $class_postfix){
					$bs_undefined_gateways[] = "wc_gateway_bluesnap_cc";
				}
			}
		}
		$this->remove_gateway( $gateways, $bs_undefined_gateways );

		return $gateways;
	}

	/**
	 * Payment gateway will not be displayed, if not correctly configured
	 *
	 * @param $gateways
	 * @param $undefined_methods
	 */
	public function remove_gateway( &$gateways, $undefined_methods ) {
		foreach ( $undefined_methods as $method ) {
			unset( $gateways[ $method ] );
		}
	}

	/**
	 * Verify that merchant filled all of the data as requested
	 *
	 * @param $options
	 *
	 * @return bool
	 */
	public function validate_admin_setting( $options ) {
		foreach ( $options as $key => $val ) {
			if ( '' == $val ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Set relevant Settings for each gateway
	 *
	 * @param $bs_gateway
	 *
	 * @return array
	 */
	public function admin_settings( $bs_gateway ) {
		$bsnp_admin_settings = array();
		foreach ( $GLOBALS['FiSHa_BSNP_Globals']->bsnp_common_settings as $k => $v ) {
			$bsnp_admin_settings[ $v ] = get_option( BSNP_PREFIX . '_' . $v );
		}
		if ( 'Bluesnap_Cc' == $bs_gateway ) {
			unset( $bsnp_admin_settings['data_protection_key'] );
			unset( $bsnp_admin_settings['iframe_height'] );
			unset( $bsnp_admin_settings['iframe_width'] );
			if ( ! class_exists( 'WC_Subscriptions_Order' ) ) {
				unset( $bsnp_admin_settings['subscriptions_contract_id'] );
			}
		} else {
			unset( $bsnp_admin_settings['cse_key'] );
		}

		return $bsnp_admin_settings;
	}

	/**
	 * Force SSL on production but not on Sendbox environment
	 * @return bool
	 */
	private function get_production_ssl_status() {
		$environment = get_option( 'woocommerce_wc_gateway_bluesnap_cc_settings' );
		if ( "no" == $environment['environment'] ) {
			$force_ceckout_ssl = get_option( 'woocommerce_force_ssl_checkout' );
			if ( "no" == $force_ceckout_ssl ) {
				return false;
			}
		}

		return true;
	}

} // end of class bsnp_payment_gateway

$bsnp = new Bsnp_Payment_Gateway();