<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Created by PhpStorm.
 * User: fisha
 * Date: 8/17/15
 * Time: 11:46 AM
 */
class Fisha_Bsnp_Functions {

	/**
	 * Generates 32 chars UUID
	 * @return array
	 */
	public function gen_uuid() {
		$uuid                  = array(
			'time_low'      => 0,
			'time_mid'      => 0,
			'time_hi'       => 0,
			'clock_seq_hi'  => 0,
			'clock_seq_low' => 0,
			'node'          => array()
		);
		$uuid['time_low']      = mt_rand( 0, 0xffff ) + ( mt_rand( 0, 0xffff ) << 16 );
		$uuid['time_mid']      = mt_rand( 0, 0xffff );
		$uuid['time_hi']       = ( 4 << 12 ) | ( mt_rand( 0, 0x1000 ) );
		$uuid['clock_seq_hi']  = ( 1 << 7 ) | ( mt_rand( 0, 128 ) );
		$uuid['clock_seq_low'] = mt_rand( 0, 255 );
		for ( $i = 0; $i < 6; $i ++ ) {
			$uuid['node'][ $i ] = mt_rand( 0, 255 );
		}
		$uuid = sprintf( '%08x%04x%04x%02x%02x%02x%02x%02x%02x%02x%02x',
			$uuid['time_low'],
			$uuid['time_mid'],
			$uuid['time_hi'],
			$uuid['clock_seq_hi'],
			$uuid['clock_seq_low'],
			$uuid['node'][0],
			$uuid['node'][1],
			$uuid['node'][2],
			$uuid['node'][3],
			$uuid['node'][4],
			$uuid['node'][5]
		);

		return $uuid;
	}


	/**
	 * Return locale in bsnp format
	 * @return mixed
	 */
	public function bsnp_get_locale() {
		$bsnp_locale      = get_locale();
		$bsnp_mini_locale = explode( '_', $bsnp_locale );

		return $bsnp_mini_locale[0];
	}

	/**
	 * If WC shopper is a BlueSnap returning shopper, return BlueSnap id, else return 0.
	 * @return string
	 */
	public function bsnp_get_wc_user_id() {
		$bsnp_preorder_user_request = ( $GLOBALS['FiSHa_BSNP_Globals']->is_preorder_payment ) ? $GLOBALS['FiSHa_BSNP_Globals']->preorder_user : $GLOBALS['user_ID'];
		$wc_user_id                 = get_user_meta( $bsnp_preorder_user_request, '_bsnp_shopper_id' );
		if ( is_null( $wc_user_id ) ) {
			return '0';
		} else {
			return $wc_user_id[0];
		}
	}

	/**
	 * Return true if customer is a returning customer,
	 * @return bool
	 */
	public function bsnp_is_returnig_shopper() {
		$bsnp_id               = ( $GLOBALS['FiSHa_BSNP_Globals']->is_preorder_payment ) ? $GLOBALS['FiSHa_BSNP_Globals']->preorder_user : $GLOBALS['user_ID'];
		$is_returning_shopper  = get_user_meta( $bsnp_id, '_bsnp_shopper_id', true );
		$bsnp_approved_shopper = get_user_meta( $bsnp_id, '_bsnp_approved_shopper', true );
		if( !empty($is_returning_shopper) &&
			1 == $bsnp_approved_shopper ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Convert XML string back to array
	 *
	 * @param $response
	 *
	 * @return array|mixed
	 */
	public function bsnp_xml_to_array( $response ) {
		try {
			if ( @simplexml_load_string( $response ) ) {
				$xml = simplexml_load_string( $response );
			} else {
				return false;
			}
			$json = json_encode( $xml );

			return json_decode( $json, true );
		} catch ( Exception $e ) {
			$GLOBALS['FiSHa_BSNP_Logger']->logerr( 'Convert XML to array', 'Error while converting the following XML to array ( ' . ( $response ) ? $response : 'empty response' . ' )' );
		}

		return false;
	}


	/**
	 * Convert array into XML object
	 *
	 * @param $student_info
	 * @param $xml_student_info
	 */
	function array_to_xml( $student_info, &$xml_student_info ) {
		foreach ( $student_info as $key => $value ) {
			if ( is_array( $value ) ) {
				if ( ! is_numeric( $key ) ) {
					$subnode = $xml_student_info->addChild( "$key" );
					$this->array_to_xml( $value, $subnode );
				} else {
					$subnode = $xml_student_info->addChild( "item$key" );
					$this->array_to_xml( $value, $subnode );
				}
			} else {
				$xml_student_info->addChild( "$key", htmlspecialchars( "$value" ) );
			}
		}
	}


	/**
	 * Given WC order id, return BlueSnap order id.
	 * @return string
	 */
	public function bsnp_get_order_id( $bsnp_wc_order_id ) {
		$result = get_post_meta( $bsnp_wc_order_id, '_bluesnap_order_id' );
		if ( isset( $result[0] ) ) {
			return $result[0];
		} else {
			return '0';
		}
	}

	/**
	 * Return if given input is numeric
	 * @return Boolean
	 */
	public function is_digits( $input = '', $return = false ) {
		$input = trim( $input );
		if ( $input != '' && preg_match( "/^[0-9]+$/", $input ) ) {
			if ( $return ) {
				return trim( $input );
			} else {
				return true;
			}
		}
		return false;
	}


	/**
	 * Save BlueSnap that belongs to WC order ID
	 *
	 * @param $bsnp_order_id
	 * @param $wc_order_id
	 */
	public function bsnp_save_bsnp_order( $bsnp_order_id, $bsnp_invoice_id, $wc_order_id, $bsnp_shopper_id, $wc_shopper_id, $payment_method = null ) {
		add_post_meta( $wc_order_id, '_wc_shopper_id', $wc_shopper_id );
		add_post_meta( $wc_order_id, '_bluesnap_order_id', $bsnp_order_id );
		add_post_meta( $wc_order_id, '_bluesnap_shopper_id', $bsnp_shopper_id );
		add_post_meta( $wc_order_id, '_bluesnap_invoice_id', $bsnp_invoice_id );
	}


	/**
	 * Send "get order's status" X time every Y seconds.
	 * Return Approved/Pending.
	 * NOTE: Need to define global params - TIMES_TO_CHECK and TIME_TO_SLEEP_SEC
	 *
	 * @param $bsnp_order_id
	 * @param $customer_order
	 * @param $environment
	 * @param bool $isBn2 (optional)
	 *
	 * @return string
	 * @throws Exception
	 */
	public function bsnp_ask_for_order_status( $bsnp_order_id, $customer_order, $environment, $isBn2 = false ) {
		$retrieve_order_url = $GLOBALS['FiSHa_BSNP_Globals']->bsnp_get_environment_url( $environment ) . BS_RETRIEVE_ORDER . ( ( $isBn2 ) ? 'resolve?invoiceId=' : '' ) . $bsnp_order_id;
		$times_to_check     = ( $isBn2 ) ? 1 : BS_TIMES_TO_CHECK;
		for ( $i = 0; $i < $times_to_check; $i ++ ) {
			$GLOBALS['FiSHa_BSNP_Logger']->logger( 'Checking for order status', 'Sending request to: ' . $retrieve_order_url );
			$retrieve_order_body = $GLOBALS['FiSHa_BSNP_Http']->bsnp_get_curl( $retrieve_order_url );
			try {
				$bsnp_order_response = simplexml_load_string( $retrieve_order_body )->{'post-sale-info'}->invoices->invoice->{'financial-transactions'}->{'financial-transaction'}->status;
				if ( is_object( $bsnp_order_response ) ) {
					$bsnp_order_status = $bsnp_order_response->__toString();
				}
			} catch ( Exception $e ) {
				$GLOBALS['FiSHa_BSNP_Logger']->logger( 'Checking for order status', 'Was not able to get order status due to the following error: ' . $e->getMessage() );
			}
			if ( "Approved" == $bsnp_order_status ) {
				$GLOBALS['FiSHa_BSNP_Logger']->logger( 'Checking for order status', 'Order status: payment approved' );
				//  $customer_order->update_status( 'processing' );
				$customer_order->reduce_order_stock();
				break;
			}
			if ( "Decline" == $bsnp_order_status ) {
				$customer_order->update_status( 'cancelled' );
				$GLOBALS['FiSHa_BSNP_Logger']->logger( 'Checking for order status', 'Order status: declined by BlueSnap' );
			} else {
				$customer_order->update_status( 'pending' );
				$GLOBALS['FiSHa_BSNP_Logger']->logger( 'Checking for order status', 'Order status: pending payment' );
			}
			sleep( BS_TIME_TO_SLEEP_SEC );
		}
	}


	/**
	 * In case of ChargeBack request, send an email to the merchant
	 *
	 * @param $wc_order_id
	 * @param $bsnp_invoice_id
	 */
	public function send_charge_back_email( $wc_order_id, $bsnp_invoice_id ) {
		$bsnp_chargeback_email = BS_CHARGEBACK_SUPPORT_EMAIL;
		$email_headers         = array( "From: BlueSnap <$bsnp_chargeback_email>", );
		$email_subject         = "Important: New chargeback received for Order ID " . $wc_order_id;
		$email_message         = __( "Dear merchant,\n
                         Please note BlueSnap has received a chargeback for your Order ID {$wc_order_id}.\n
                         This order has therefore been automatically refunded.\n
                         A chargeback occurs when a shopper contacts their card issuing bank and disputes a transaction they see on their statements.\n
                         If you would like to know more, please log in to your BlueSnap account. You can find the order using Order locator. Invoice ID {$bsnp_invoice_id}.\n\n\n
                         Kind regards,\n
                         The BlueSnap team\n", 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' );
		try{
			wp_mail( MERCHANT_EMAIL, $email_subject, $email_message, $email_headers );
		} catch (Exception $e) {
			$GLOBALS['FiSHa_BSNP_Logger']->logerr( 'Send Chargeback email', 'Failed to send email due to: '.$e->getMessage() );
		}

	}


	/**
	 * In case of ChargeBack request, send an email to the merchant
	 *
	 * @param $wc_order_id
	 * @param $bsnp_invoice_id
	 */
	public function send_refund_email( $wc_order_id, $bsnp_invoice_id ) {
		$bsnp_refund_email = BS_CHARGEBACK_SUPPORT_EMAIL;
		$email_headers     = array( "From: BlueSnap <$bsnp_refund_email>", );
		$email_subject     = "Important: New refund received for Order ID " . $wc_order_id;
		$email_message     = __( "Dear merchant,\n
                         Please note BlueSnap has received a refund request for your Order ID {$wc_order_id}.\n
                         This order has therefore been refunded on our end.\n
                         If you would like to know more, please log in to your BlueSnap account. You can find the order using Order locator. Invoice ID {$bsnp_invoice_id}.\n\n\n
                         Kind regards,\n
                         The BlueSnap team\n", 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' );
		try{
			wp_mail( MERCHANT_EMAIL, $email_subject, $email_message, $email_headers );
		} catch (Exception $e) {
			$GLOBALS['FiSHa_BSNP_Logger']->logerr( 'Send Refund email', 'Failed to send email due to: '.$e->getMessage() );
		}
	}

	/**
	 * Given WC order number return bsnp invoice id
	 * if invoice id is not applicable return bsnp order id
	 *
	 * @param $order
	 */
	public function bsnp_add_order_invoice_or_number_to_order_details( $order ) {
		if($this->bsnp_is_shop_subscription($order)){
			return; // Do not display message on subscription manager entity
		}
		$order_details = $GLOBALS['FiSHa_BSNP_Db']->bsnp_get_order_details( $order->id );
		if ( is_object(  $order_details  ) &&
			'wc_gateway_bluesnap_cc' == get_post_meta( $order->id, '_payment_method',true )) { //Show this block only for orders which were paid with BSNP
			$invoice_id = $order_details->bluesnap_invoice_id;
			$order_id   = $order_details->bluesnap_order_id;
			if ( isset( $invoice_id ) && ! is_null( $invoice_id ) && "" != $invoice_id ) {
				printf( "<div><b>".__( "BlueSnap Invoice ID: %d", 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' )."</b></div>", $invoice_id );
			} else if ( isset( $order_id ) && ! is_null( $order_id ) && "" != $order_id ) {
				printf(  "<div><b>".__("BlueSnap Order ID: %d", 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' )."</b></div>", $order_id );
			} else {
				echo "<div><b>" . __( "Unable to retrieve BlueSnap order info", 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ) . "</div></b>";
			}
		} else {
			echo "<div><b>" . __( "BlueSnap order id is not available. It is possible that the order was not sent to Bluesnap", 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ) . "</div></b>";

		}
	}

    /**
     * If the user payed for the order with currency other than
     * the Shop base currency, then reflect to the merchant what was
     * the shopper selection.
     * @param $order_id
     */
    public function bsnp_add_order_actual_paid_currency($order_id)
	{
		$actual_paid_currency = get_post_meta($order_id, '_charged_currency', true);
		$order_total = get_post_meta($order_id, '_order_total', true);
		$ex_rate = get_post_meta($order_id, '_bsnp_ex_rate', true);
		$wc_version = get_option('woocommerce_version');
		$wc_version = explode('.', $wc_version);
		if (!empty($actual_paid_currency) &&
			$actual_paid_currency != get_woocommerce_currency()
		) {
			$actual_pay = round($order_total * $ex_rate, 2);
			?>
			<tr>
				<td class="label"><?php
					echo wc_help_tip(__('The transaction was processed for this amount and currency.', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce'));
					echo __('Shopper paid', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce'); ?>:
				</td>
				<?php
				// In version 2.6.0 they have changed the table structure
				if($wc_version[1] >= '6') {
					echo "<td></td>";
				}
				?>
				<td class="total">
					<div class="view"><span class="woocommerce-Price-amount amount"><?php echo $actual_pay . " " . $actual_paid_currency ?></span></div>
				</td>
			</tr>
			<?php
		}
	}

	/**
	 * Given order return true if order is Subscription manager,
	 * else return false
	 * @param $order
	 * @return bool
     */
	private function bsnp_is_shop_subscription($order)
	{
		$post_id = $order->id;
		return  'shop_subscription' == get_post_type( $post_id );
	}

}

$GLOBALS['FiSHa_BSNP_Functions'] = new Fisha_Bsnp_Functions();