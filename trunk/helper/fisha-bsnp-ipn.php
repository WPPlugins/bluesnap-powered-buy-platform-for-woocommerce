<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Created by PhpStorm.
 * User: fisha
 * Date: 8/9/15
 * Time: 6:02 PM
 */
class Fisha_Bsnp_Ipn {

	/**
	 * Update order status from IPN
	 */
	function ipn_handler() {
		$GLOBALS['FiSHa_BSNP_Validation']->bsnp_is_valid_call();
		$referenceNumber = $GLOBALS['FiSHa_BSNP_Functions']->is_digits( $_REQUEST['referenceNumber'], true );
		$wc_order_id     = $GLOBALS['FiSHa_BSNP_Db']->bsnp_get_wc_order_id( $referenceNumber );
		if(empty($wc_order_id)){
			sleep(5); // In case of IPN is searching for invoice before it was placed in the Db.
			$wc_order_id = $GLOBALS['FiSHa_BSNP_Db']->bsnp_get_wc_order_id( $referenceNumber );
		}
		$GLOBALS['FiSHa_BSNP_Logger']->logger( 'Incoming IPN DATA for order# ' . $wc_order_id, json_encode( $_REQUEST ) );
		if ( '0' == $wc_order_id ) {
			$GLOBALS['FiSHa_BSNP_Logger']->logger( 'Data retrieve error', 'Failed to get order using BlueSnap id: ' . $referenceNumber );
			return;
		}
		$transactionType = $_REQUEST['transactionType'];
//		Temporary commented out until BSNP will support on their end
//		$refundAmount    = ( $_REQUEST['invoiceChargeAmount'] < 0 ) ? abs( $_REQUEST['invoiceChargeAmount'] ) : 0;
		$status          = '';
		$note            = '';
		if ( isset ( $transactionType ) ) {
			switch ( $transactionType ) {
				case BS_IPN_COMPLETE:
					$status = 'processing';
					$note   = __( 'Payment received', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' );
					break;
				case BS_IPN_CHARGEBACK:
					$status = 'refunded';
					$note   = __( 'Refunded due to Charge-back', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' );
					$GLOBALS['FiSHa_BSNP_Subscriptions']->upon_chargeback_cancel_subscription($wc_order_id);
					$GLOBALS['FiSHa_BSNP_Functions']->send_charge_back_email( $wc_order_id, $referenceNumber );
					break;
				case BS_IPN_REFUND:
// 					Temporary commented out until BSNP will support on their end
//                    $refund = wc_create_refund( array(
//                        'amount'     => $refundAmount,
//                        'reason'     => 'Refunded by BlueSnap team',
//                        'order_id'   => $wc_order_id,
//                        'line_items' => null,
//                    ));
//
//                    if ( is_wp_error( $refund ) ) {
//                        throw new Exception( $refund->get_error_message() );
//                    }
//					$GLOBALS['FiSHa_BSNP_Functions']->send_refund_email( $wc_order_id, $referenceNumber );
					break;
				case BS_IPN_DECLINE:
					$status = 'cancelled';
					$note   = __( 'Payment declined', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' );
					break;
				case BS_IPN_RECURRING:
					$status = 'processing';
					$note   = __( 'Payment received', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' );
					break;
			}
		}
		if ( $status != '' && $note != '' ) {
			$GLOBALS['FiSHa_BSNP_Db']->bsnp_update_order( $wc_order_id, $status, $note );
		}
	}
} // end of class fisha_basnp_ipn

$GLOBALS['FiSHa_BSNP_IPN'] = new Fisha_Bsnp_Ipn();