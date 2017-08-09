<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Created by PhpStorm.
 * User: fisha
 * Date: 8/17/15
 * Time: 3:17 PM
 */
class Fisha_Bsnp_Preorder {

	private $bsnp_fruad_session;
	private $order_details;
	private $purchase_amount;
	private $purchase_currency;

	public function __construct() {
		$this->bsnp_fruad_session = $GLOBALS['FiSHa_BSNP_Functions']->gen_uuid();
		$this->order_contract_id  = get_option( 'bsnp_order_contract_id' );
	}

	/**
	 * Place Order for PreOrder products
	 * @param $order
	 */
	public function process_pre_order_payments( $order ) {
		if ( ! WC_Pre_Orders_Order::order_will_be_charged_upon_release( $order ) ) {
			// If order was charged upfront, than no need to set payment here.
			$order->payment_complete();
			return;
		}
//        $order_data = $GLOBALS['FiSHa_BSNP_Db']->bsnp_get_order_details( $order->id );
//        $this->order_details = $order_data[0];
		$this->order_details                                = $GLOBALS['FiSHa_BSNP_Db']->bsnp_get_order_details( $order->id );
		$this->purchase_currency                            = $this->order_details->charged_currency;
		$ex_rate                                            = $this->order_details->bluesnap_ex_rate;
		$this->purchase_amount                              = $order->get_total() * $ex_rate;
		$bsnp_order_id                                      = $GLOBALS['FiSHa_BSNP_Functions']->bsnp_get_order_id( $order->id );
		$GLOBALS['FiSHa_BSNP_Globals']->is_preorder_payment = true;
		$GLOBALS['FiSHa_BSNP_Globals']->preorder_user       = $order->get_user_id();
		$GLOBALS['FiSHa_BSNP_Globals']->environment_url     = $GLOBALS['FiSHa_BSNP_Globals']->bsnp_get_environment_url( $GLOBALS['FiSHa_BSNP_Globals']->environment );
		$bsnp_environment_url                               = $GLOBALS['FiSHa_BSNP_Globals']->environment_url . BS_SHOPPING_CONTEXT . $bsnp_order_id;
		$GLOBALS['FiSHa_BSNP_Globals']->payload             = $this->bsnp_update_preorder( $order );
		$GLOBALS['FiSHa_BSNP_Globals']->bsnp_xml            = new SimpleXMLElement( '<shopping-context xmlns="http://ws.plimus.com"> </shopping-context>' );
		$GLOBALS['FiSHa_BSNP_Functions']->array_to_xml( $GLOBALS['FiSHa_BSNP_Globals']->payload, $GLOBALS['FiSHa_BSNP_Globals']->bsnp_xml );
		$GLOBALS['FiSHa_BSNP_Http']->bsnp_put_curl( $bsnp_environment_url, $GLOBALS['FiSHa_BSNP_Globals']->bsnp_xml->asXML() );  // NOTE: We have a HTTP PUT request here, This is the actual order placement
		if ( "204" == $GLOBALS['FiSHa_BSNP_Http']->global_response_code ) {  // That mean successful payment
			$order->payment_complete();
			$this->bsnp_save_preorder( $order );
//			$this->update_today_order_value($this->purchase_amount, $this->purchase_currency, $order);
		} else {
			$order->update_status( 'pending' );
		}
	}

	/**
	 * Return invoice
	 * @param $bsnp_order_id
	 * @return bool
	 */
	private function get_order_invoice_id( $bsnp_order_id ) {
		$retrieve_order_url = $GLOBALS['FiSHa_BSNP_Globals']->environment_url . BS_RETRIEVE_ORDER . $bsnp_order_id;
		$response           = simplexml_load_string( $GLOBALS['FiSHa_BSNP_Http']->bsnp_get_curl( $retrieve_order_url, true ) );
		if ( is_object( $response ) ) {
			$invoice_id = $response->{'post-sale-info'}->invoices->invoice->{'invoice-id'}->__toString();

			return $invoice_id;
		}

		return false;
	}


	/**
	 * Updates tables with Pre-order invoice id
	 * @param $order
	 */
	public function bsnp_save_preorder( $order ) {
		$invoice_id = $this->get_order_invoice_id( $this->order_details->bluesnap_order_id );
		if ( ! $invoice_id ) {
			$order->update_status( 'pending' );

			return;
		}
		if(!empty($invoice_id)){
			update_post_meta( $order->id, '_bluesnap_invoice_id', $invoice_id );
		}

	}

	/**
	 * Update paymet on a preorder order
	 * @param $customer_order
	 * @return array
	 */
	public function bsnp_update_preorder( $customer_order ) {
		$payload = array(
			'step'          => 'PLACED',
			'web-info'      => array(
				'ip'          => $customer_order->customer_ip_address,
				'remote-host' => isset( $_SERVER['REMOTE_HOST'] ) ? $_SERVER['REMOTE_HOST'] : $_SERVER['REMOTE_ADDR'],
				'user-agent'  => $_SERVER['HTTP_USER_AGENT'],
			),
			'order-details' => array(
				'order' => array(
					'ordering-shopper' => array(
						'fraud-info' => array( 'fraud-session-id' => $this->bsnp_fruad_session ),
						'shopper-id' => $this->order_details->bluesnap_shopper_id,
					),

					'cart'                 => array(
						'cart-item' => array(
							'sku'           => array(
								'sku-id'           => $this->order_contract_id,
								'sku-charge-price' => array(
									'charge-type' => 'initial',
									'amount'      => $this->purchase_amount,
									'currency'    => $this->purchase_currency,
								),
							),
							'quantity'      => '1',
							'sku-parameter' => array(
								'param-name'  => 'woo_order_id',
								'param-value' => $customer_order->id,
							),
						),
					),
					'expected-total-price' => array(
						'amount'   => $this->purchase_amount,
						'currency' => $this->purchase_currency,
					),
				),
			),
		);

		return $payload;
	}

	/**
	 * Cancel subscription on bsnp server
	 * @param $order
	 * @return bool
	 */
	function bsnp_preorder_cancellation( $order ) {
		// Currently BlueSnap does not support cancellation of shopping context
		// Therefore we are unable to cancel PreOrder on the BlueSnap server as well as on WC.
		return;
	}

	/**
	 * Update order total according to ex rate upon release date
	 * @param $purchase_amount
	 * @param $purchase_currency
	 * @param $order
     */
	private function update_today_order_value($purchase_amount, $purchase_currency, $order)
	{
		$currency_ex_rate = $GLOBALS['FiSHa_BSNP_Currency_Switcher']->convert_currencies_ex_rates($purchase_currency, get_woocommerce_currency());
		$capture_final = $purchase_amount / $currency_ex_rate;
		update_post_meta($order->id, '_order_origin_total', $order->get_total());
		update_post_meta($order->id, '_order_total', round($capture_final, 2));
	}

}

$GLOBALS['FiSHa_BSNP_Preorder'] = new Fisha_Bsnp_Preorder();