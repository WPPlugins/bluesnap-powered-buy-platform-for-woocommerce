<?php

/**
 * Created by PhpStorm.
 * User: fisha
 * Date: 8/6/15
 * Time: 11:45 AM
 */

if ( ( ! defined( 'ABSPATH' ) ) ) {
	exit();
}

class Fisha_WP_Logger {

	function __construct() {
		add_filter( 'cron_schedules', array( $this, 'cron_definer' ) );
		if ( ! wp_next_scheduled( 'bsnp_clear_logs' ) ) {
//            wp_schedule_event(time(), "monthly", 'bsnp_clear_logs');
			wp_schedule_event( '00:00:00', "daily", 'bsnp_clear_logs' );
		}
		add_action( 'bsnp_clear_logs', array( $this, 'clear_logs' ) );
	}

	/**
	 * Add new Corn definition 'monthly'
	 *
	 * @param $schedules
	 *
	 * @return mixed
	 */
	function cron_definer( $schedules ) {
		$schedules['monthly'] = array(
			'interval' => 2592000,
			'display'  => __( 'Once Every 30 Days', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' )
		);

		return $schedules;
	}

	/**
	 * Creates DB log
	 *
	 * @param $process_name
	 * @param $msg_string
	 * @param bool $front_log
	 *
	 * @internal param $err_string
	 */
	public function logger( $process_name, $msg_string, $front_log = true ) {
		$log_message = array(
			'date' => time(),
			'wc_user_id' => intval( $GLOBALS['user_ID'] ),
			'process' => sanitize_text_field( $process_name ),
			'details' => sanitize_text_field( $msg_string ),
			'front_log' => ( $front_log ) ? 1 : 0,
		);
		$logger      = new WC_Logger();
		$logger->add( 'bluesnap_process', json_encode( $log_message ) );
	}

	/**
	 * Clear logs every X days
	 * X is taken from Admin panel configuration
	 */
	public function clear_logs() {
		$logger = new WC_Logger();
		$logger->clear( 'bluesnap_process' );
	}

}

$GLOBALS['FiSHa_BSNP_Logger'] = new Fisha_WP_Logger();