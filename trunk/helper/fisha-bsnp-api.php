<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Created by PhpStorm.
 * User: fisha
 * Date: 8/17/15
 * Time: 1:57 PM
 */
class Fisha_Bsnp_Api {


	/**
	 * Extract error code form response
	 *
	 * @param $response
	 *
	 * @return int
	 */
	public function get_bsnp_error_code( $response ) {
		if ( isset( $response['message']['code'] ) ) {
			$GLOBALS['FiSHa_BSNP_Logger']->logger( 'An error has occurred with the following data: ', 'Response error msg: ' . json_encode( $response ) );
			$bsnp_error_code = $response['message']['code'];

			return $bsnp_error_code;
		} else {
			$GLOBALS['FiSHa_BSNP_Logger']->logger( 'Returned unknown error code', 'Returned unknown error code with unknown error' );

			return 999;
		}
	}

	/**
	 * Return error message for given error code
	 *
	 * @param $err_code
	 *
	 * @return string|void
	 */
	public function get_bsnp_error_msg( $err_code ) {
		$bsnp_general_error_code = __( "Your payment could not be processed at this time. Please make sure the card information was entered correctly and resubmit. If the problem persists, please contact your credit card company to authorize the purchase.", "woocommerce-bluesnap-powered-buy-platform-for-woocommerce" );
		switch ( $err_code ) {
			case '10000':
				$error_string = $bsnp_general_error_code;
				break;
			case '10001':
				$error_string = __("This card brand / type ('AMEX') is not currently supported for this transaction. Please try again using a different card.", "woocommerce-bluesnap-powered-buy-platform-for-woocommerce");
				break;
			case '14001':
				$error_string = $bsnp_general_error_code;
				break;
			case '14002':
				$error_string = $bsnp_general_error_code;
				break;
			case '15003':
				$error_string = __( "The currency selected is not supported by this payment method. Please try a different currency or payment method", "woocommerce-bluesnap-powered-buy-platform-for-woocommerce");
				break;
			/**
			 * For now we are using general error message for all error code.
			 * Detailed description is being kept in logs.
			 * Note above is a partial list of error codes.
			 * For complete list please visit:
			 * http://docs.bluesnap.com/api/error-handling/error-codes
			 */
			case '999':
				$error_string = $bsnp_general_error_code;
				break;
			default:
				$error_string = $bsnp_general_error_code;
		}
		return $error_string;
	}

	/**
	 * Updates shopper data with new credit card details
	 *
	 * @param $payload
	 *
	 * @return array
	 * @internal param $customer_order
	 */
	public function bsnp_update_shopper_details( $payload ) {
		$environment_url                         = $GLOBALS['FiSHa_BSNP_Globals']->environment_url . BS_RETURN_ROUT . $GLOBALS['FiSHa_BSNP_Functions']->bsnp_get_wc_user_id();
		$GLOBALS['FiSHa_BSNP_Globals']->payload  = $payload;
		$GLOBALS['FiSHa_BSNP_Globals']->bsnp_xml = new SimpleXMLElement( '<shopper xmlns="http://ws.plimus.com"> </shopper>' );
		$GLOBALS['FiSHa_BSNP_Functions']->array_to_xml( $GLOBALS['FiSHa_BSNP_Globals']->payload, $GLOBALS['FiSHa_BSNP_Globals']->bsnp_xml );
		$GLOBALS['FiSHa_BSNP_Logger']->logger( 'Attempt ot update shopper details', 'Sending update request to bsnp server' );
		$GLOBALS['FiSHa_BSNP_Http']->bsnp_put_curl( $environment_url, $GLOBALS['FiSHa_BSNP_Globals']->bsnp_xml->asXML() );
	}

	/**
	 * Given order number, return BlueSnap shopper ID.
	 * NOTE!!! this function is for Shopping context ONLY!
	 *
	 * @param $bsnp_order_id
	 *
	 * @return mixed
	 */
	public function bsnp_bluesnap_shopper_id( $bsnp_order_id ) {
		$retrieve_shopping_context_url = $GLOBALS['FiSHa_BSNP_Globals']->bsnp_get_environment_url( $GLOBALS['FiSHa_BSNP_Globals']->environment ) . BS_SHOPPING_CONTEXT . $bsnp_order_id;
		$GLOBALS['FiSHa_BSNP_Logger']->logger( 'Request BlueSnap shopper id', 'Sending request to: ' . $retrieve_shopping_context_url );
		$retrieve_order_body = $GLOBALS['FiSHa_BSNP_Http']->bsnp_get_curl( $retrieve_shopping_context_url );
		$bsnp_get_shopper_id = simplexml_load_string( $retrieve_order_body )->{'order-details'}->order->{'ordering-shopper'}->{'shopper-id'}->__toString();
		if ( empty( $bsnp_get_shopper_id ) ) {
			$bsnp_error_msg = __( 'Unknown error has occurred', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' );
			$GLOBALS['FiSHa_BSNP_Logger']->logger( 'Check for Order status', 'BlueSnap response with empty data' );
			$GLOBALS['FiSHa_BSNP_Logger']->logger( 'Request BlueSnap shopper id', 'Couldn\'t retrieve shopper id due to: No response from BlueSnap server' );
			wc_add_notice( $bsnp_error_msg, 'error' );

			return null;
		}
		$GLOBALS['FiSHa_BSNP_Logger']->logger( 'Request BlueSnap shopper id', 'Success' );

		return $bsnp_get_shopper_id;
	}
}

$GLOBALS['FiSHa_BSNP_Api'] = new Fisha_Bsnp_Api();