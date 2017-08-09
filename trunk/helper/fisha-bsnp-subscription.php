<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Created by PhpStorm.
 * User: fisha
 * Date: 8/17/15
 * Time: 4:03 PM
 */
class Fisha_Bsnp_Subscriptions {

	public $customer_order;
	private $subscription_invoice_id;

	public function __construct() {

	}

	/**
	 * Generate Subscription payment on demand ( Auto or Manual )
	 *
	 * @param $total
	 * @param $order
	 *
	 * @internal param $order_id
	 */
	public function bsnp_scheduled_subscription_payment( $total, $order ) {
//        WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $this->customer_order );
//        return;
		if($order->payment_method !="wc_gateway_bluesnap_cc"){
			return; // Do not update subscriptions form other payment methods
		}
        $this->customer_order = $order;
		$post_meta            = get_post_meta( $order->id, '_subscription_renewal', true );
		$parent_id            = wp_get_post_parent_id( $post_meta );
		$subscription_id      = $GLOBALS['FiSHa_BSNP_Db']->get_bsnp_subscription_id( $parent_id );
		$renewal_total        = $this->customer_order->get_total();
		$currency_of_payment  = $GLOBALS['FiSHa_BSNP_Db']->get_subscription_currency( $parent_id );
		$purchase_day_ex_rate = $GLOBALS['FiSHa_BSNP_Currency_Switcher']->get_shopperselection_exchange_rate( $parent_id );
		if ( 0 == $purchase_day_ex_rate ) {
			WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $this->customer_order );
		}
		$calculated_amount = $renewal_total * $purchase_day_ex_rate;
		$amount_to_charge  = round( $calculated_amount, 2 );

		$bsnp_environment_url                    = $GLOBALS['FiSHa_BSNP_Globals']->bsnp_get_environment_url( $GLOBALS['FiSHa_BSNP_Globals']->environment ) . BS_SUBSCRIPTION . $subscription_id . BS_CREATE_SUBSCRIPTION_CHARGE;
		$GLOBALS['FiSHa_BSNP_Globals']->bsnp_xml = new SimpleXMLElement( '<subscription-charge xmlns="http://ws.plimus.com"> </subscription-charge>' );
		$GLOBALS['FiSHa_BSNP_Globals']->payload  = array(
			'charge-info'          => array(
				'charge-description' => 'Create Subscription charge for Subscription #' . $subscription_id,
			),
			'sku-charge-price'     => array(
				'amount'   => $amount_to_charge,
				'currency' => $currency_of_payment,
			),
			'expected-total-price' => array(
				'amount'   => $amount_to_charge,
				'currency' => $currency_of_payment,
			),
		);
		$GLOBALS['FiSHa_BSNP_Functions']->array_to_xml( $GLOBALS['FiSHa_BSNP_Globals']->payload, $GLOBALS['FiSHa_BSNP_Globals']->bsnp_xml );
		$response      = $GLOBALS['FiSHa_BSNP_Http']->bsnp_send_curl( $bsnp_environment_url, $GLOBALS['FiSHa_BSNP_Globals']->bsnp_xml->asXML() );
		$response_body = simplexml_load_string( $GLOBALS['FiSHa_BSNP_Http']->global_response_body );
		if ( ! $response || ! is_object( $response_body ) ) {
			WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $this->customer_order );
		} else if ( $response ) {
			$this->subscription_invoice_id = $response_body->{'charge-invoice-info'}->{'invoice-id'}->__toString();
			if ( isset( $this->subscription_invoice_id ) ) {
				$GLOBALS['FiSHa_BSNP_Functions']->bsnp_save_bsnp_order( null, $this->subscription_invoice_id, $order->id, null, $order->get_user_id() , null );
				$this->customer_order->add_order_note( __( 'Payment renewal sent to BSNP', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ) );
				WC_Subscriptions_Manager::process_subscription_payments_on_order( $order->id );
				$this->customer_order->payment_complete();
				if ( $currency_of_payment != get_woocommerce_currency() ) {
					$this->update_merchant_total( $amount_to_charge, $currency_of_payment );
				} else {
					update_post_meta( $this->customer_order->id, '_bsnp_ex_rate', 1 );
				}
			} else {
				WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $this->customer_order );
			}
		}
	}


	/**
	 * Save data for child subscription
	 *
	 * @param $order_id
	 */
	public function save_subscription_renewal( $order_id ) {

	}

	/**
	 * @param $sbuscription_id
	 * @param $bsnp_shopper_id
	 * @param $status
	 */
	public function bsnp_update_subscription_status_payload( $sbuscription_id, $bsnp_shopper_id, $status ) {
		$new_status = '';
		switch ( $status ) {
			case 'CANCEL':
				$new_status = 'C';
				break;
			case 'REACTIVATE':
				$new_status = 'A';
				break;
		}
		$GLOBALS['FiSHa_BSNP_Globals']->payload = array(
			'subscription-id'   => $sbuscription_id,
			'status'            => $new_status,
			'underlying-sku-id' => get_option( 'bsnp_subscriptions_contract_id' ),
			'shopper-id'        => $bsnp_shopper_id,
		);
	}

	/**
	 * Cancel subscription on bsnp server
	 *
	 * @param $order
	 *
	 * @return bool
	 */
	function bsnp_subscription_cancellation( $order ) {
		if ( isset( $_REQUEST['post_type'] ) && 'scheduled-action' == $_REQUEST['post_type'] ) {
			return;
		}
		if($order->payment_method !="wc_gateway_bluesnap_cc"){
			return; // Do not update subscriptions form other payment methods
		}
		$sbuscription_id      = $GLOBALS['FiSHa_BSNP_Db']->get_bsnp_subscription_id( $order->id );
		$bsnp_shopper_id      = $GLOBALS['FiSHa_BSNP_Db']->get_subscription_bluesnap_shopper_id( $order->id );
		$bsnp_environment_url = $GLOBALS['FiSHa_BSNP_Globals']->bsnp_get_environment_url( $GLOBALS['FiSHa_BSNP_Globals']->environment ) . BS_SUBSCRIPTION . $sbuscription_id;
		$this->bsnp_update_subscription_status_payload( $sbuscription_id, $bsnp_shopper_id, 'CANCEL' );
		$GLOBALS['FiSHa_BSNP_Globals']->bsnp_xml = new SimpleXMLElement( '<subscription xmlns="http://ws.plimus.com"></subscription>' );
		$GLOBALS['FiSHa_BSNP_Functions']->array_to_xml( $GLOBALS['FiSHa_BSNP_Globals']->payload, $GLOBALS['FiSHa_BSNP_Globals']->bsnp_xml );
		$GLOBALS['FiSHa_BSNP_Http']->bsnp_put_curl( $bsnp_environment_url, $GLOBALS['FiSHa_BSNP_Globals']->bsnp_xml->asXML() );
		if ( "204" == $GLOBALS['FiSHa_BSNP_Http']->http_code ) {
			$GLOBALS['FiSHa_BSNP_Logger']->logger( 'Cancel subscription', 'Subscription was cancelled (Order id: ' . $order->id );
		} else {
			$GLOBALS['FiSHa_BSNP_Logger']->logger( 'Cancel subscription', 'Failed to cancel subscription (Order id: ' . $order->id );
		}
	}

	/**
	 * Shopper cancels subscription form "My account"
	 *
	 * @param $subscription
	 */
	public function shopper_cancel_order( $subscription ) {
		if($subscription->payment_method !="wc_gateway_bluesnap_cc"){
			return; // Do not update subscriptions form other payment methods
		}
		if ( ! is_object( $subscription ) ) {
			$subscription = new WC_Subscription( $subscription );
		}
		$this->bsnp_subscription_suspension( $subscription );
	}


	/**
	 * Suspend subscription on bsnp server
	 * For now suspention and cancellation use the same methods
	 *
	 * @param $subscription
	 */
	function bsnp_subscription_suspension( $subscription ) {
		if($subscription->payment_method !="wc_gateway_bluesnap_cc"){
			return; // Do not update subscriptions form other payment methods
		}
		$customer_order             = $subscription->order;
		$order_uses_manual_payments = ( $subscription->is_manual() ) ? true : false;
		if ( $order_uses_manual_payments && ( isset( $_REQUEST['post_type'] ) && 'scheduled-action' == $_REQUEST['post_type'] ) ) {
			return;
		}
		$this->bsnp_subscription_cancellation( $customer_order );
	}

	/**
	 * Reactivate order on bsnp server
	 *
	 * @param $subscription
	 *
	 * @return bool
	 */
	function bsnp_subscription_reactivation( $subscription ) {
		if ( isset( $_REQUEST['post_type'] ) && 'scheduled-action' == $_REQUEST['post_type'] ) {
			return;
		}
		if($subscription->payment_method !="wc_gateway_bluesnap_cc"){
			return; // Do not update subscriptions form other payment methods
		}
		$customer_order       = $subscription->order;
		$sbuscription_id      = $GLOBALS['FiSHa_BSNP_Db']->get_bsnp_subscription_id( $customer_order->id );
		$bsnp_shopper_id      = $GLOBALS['FiSHa_BSNP_Db']->get_subscription_bluesnap_shopper_id( $customer_order->id );
		$bsnp_environment_url = $environment_url = $GLOBALS['FiSHa_BSNP_Globals']->bsnp_get_environment_url( $GLOBALS['FiSHa_BSNP_Globals']->environment ) . BS_SUBSCRIPTION . $sbuscription_id;
		$this->bsnp_update_subscription_status_payload( $sbuscription_id, $bsnp_shopper_id, 'REACTIVATE' );
		$GLOBALS['FiSHa_BSNP_Globals']->bsnp_xml = new SimpleXMLElement( '<subscription xmlns="http://ws.plimus.com"></subscription>' );
		$GLOBALS['FiSHa_BSNP_Functions']->array_to_xml( $GLOBALS['FiSHa_BSNP_Globals']->payload, $GLOBALS['FiSHa_BSNP_Globals']->bsnp_xml );
		$GLOBALS['FiSHa_BSNP_Http']->bsnp_put_curl( $bsnp_environment_url, $GLOBALS['FiSHa_BSNP_Globals']->bsnp_xml->asXML() );
		if ( "204" == $GLOBALS['FiSHa_BSNP_Http']->http_code ) {
			$GLOBALS['FiSHa_BSNP_Logger']->logger( 'Activate subscription', 'Subscription was activated (Order id: ' . $customer_order->id );
		} else {
			$GLOBALS['FiSHa_BSNP_Logger']->logger( 'Activate subscription', 'Failed to activate subscription (Order id: ' . $customer_order->id );
			$this->shopper_cancel_order( $subscription );
			$subscription->update_status( "on-hold" );
		}
	}

	/**
	 * Process to run in case of failed subscription
	 *
	 * @param $original_order
	 * @param $new_order
	 */
	public function failed_subscription_action( $original_order, $new_order ) {
		// For now we take no action in case of failed subscription
	}


	/**
	 * Extract subscription ID from request
	 *
	 * @param $url
	 *
	 * @return int|string
	 * @internal param $headers
	 */
	public function bsnp_extract_subscription_id( $url ) {
		return $subscription_id = strrchr( $url, '/' );
	}

	/**
	 * Log renewal process for subscription
	 *
	 * @param $order
	 */
	public function subscription_log_order_renewal( $order ) {
		global $typenow;
		if ( 'scheduled-action' == $typenow ) {
			if ( is_object( $order ) ) {
				$order_id = $order->id;
			} else {
				$order_id = $order;
			}
			$GLOBALS['FiSHa_BSNP_Logger']->logger( 'Subscription Renewal', 'Payment was placed for order #WP id:' . $order_id );
		}

	}

	/**
	 * Updates tables with subscription's data
	 *
	 * @param $bsnp_order_id
	 */
	public function bsnp_save_subscription($bsnp_order_id, $wc_order_id)
	{
		$subscription_data = $this->bsnp_retrieve_subscription_data($bsnp_order_id);
		if(!empty($subscription_data['charged_currency'])){
			update_post_meta($wc_order_id, '_charged_currency', sanitize_text_field($subscription_data['charged_currency']));
		}
		if(!empty($subscription_data['card_type'])){
			update_post_meta($wc_order_id, '_card_type', strtoupper(sanitize_text_field($subscription_data['card_type'])));
		}
		if(!empty($subscription_data['card_last_4_digit'])){
			update_post_meta($wc_order_id, '_card_last_4_digit', $GLOBALS['FiSHa_BSNP_Functions']->is_digits($subscription_data['card_last_4_digit'],true));
		}
		if(!empty($subscription_data['invoice_id'])){
			update_post_meta($wc_order_id, '_bluesnap_invoice_id', $GLOBALS['FiSHa_BSNP_Functions']->is_digits($subscription_data['invoice_id'], true));
		}
		if(!empty($subscription_data['subscription_id'])){
			update_post_meta($wc_order_id, '_bluesnap_subscription_id', $GLOBALS['FiSHa_BSNP_Functions']->is_digits($subscription_data['subscription_id'], true));
		}
		if(!empty($subscription_data['shopper_id'])){
			update_post_meta($wc_order_id, '_bluesnap_shopper_id', $GLOBALS['FiSHa_BSNP_Functions']->is_digits($subscription_data['shopper_id'], true));
		}






	}

	/**
	 * Given order id, return subscription data from Db
	 *
	 * @param $bsnp_order_id
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function bsnp_retrieve_subscription_data( $bsnp_order_id ) {
		$environment_url = $GLOBALS['FiSHa_BSNP_Globals']->bsnp_get_environment_url( $GLOBALS['FiSHa_BSNP_Globals']->environment );
		$environment_url .= BS_RETRIEVE_ORDER . $bsnp_order_id;
		$response     = $GLOBALS['FiSHa_BSNP_Http']->bsnp_get_curl( $environment_url );
		$shopper_data = $GLOBALS['FiSHa_BSNP_Functions']->bsnp_xml_to_array( $response );
		if ( ! $shopper_data ) {
			throw new Exception( __( 'Failed to retrieve subscription data', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ) );
		}
		$url                                           = $shopper_data['cart']['cart-item']['url'];
		$GLOBALS['FiSHa_BSNP_Globals']->payment_status = $shopper_data['post-sale-info']['invoices']['invoice']['financial-transactions']['financial-transaction']['status'];
		$subscription_data['subscription_id']          = trim( $this->bsnp_extract_subscription_id( $url ), '/' );
		$subscription_data['shopper_id']               = $shopper_data['ordering-shopper']['shopper-id'];
		$subscription_data['charged_currency']         = $shopper_data['cart']['charged-currency'];
		$subscription_data['order_id']                 = $shopper_data['order-id'];
		$subscription_data['invoice_id']               = $shopper_data['post-sale-info']['invoices']['invoice']['invoice-id'];
		$subscription_data['card_type']                = $shopper_data['post-sale-info']['invoices']['invoice']['financial-transactions']['financial-transaction']['credit-card']['card-type'];
		$subscription_data['card_last_4_digit']        = $shopper_data['post-sale-info']['invoices']['invoice']['financial-transactions']['financial-transaction']['credit-card']['card-last-four-digits'];

		return $subscription_data;
	}

	/**
	 * Update real total in store currency according to current exchange rate
	 * @param $purchase_amount
	 * @param $currency_of_payment
	 */
	private function update_merchant_total($purchase_amount, $currency_of_payment ) {
		$todays_ex_rate_for_shopper_selection  = $GLOBALS['FiSHa_BSNP_Currency_Switcher']->bsnp_get_exchange_rate( $currency_of_payment );
		$todays_ex_rate_for_merchant_selection = $GLOBALS['FiSHa_BSNP_Currency_Switcher']->bsnp_get_exchange_rate( get_woocommerce_currency() );
		// calculated ex rate from store base currency to shopper selection
		$todays_ex_from_shopper_selection = $todays_ex_rate_for_shopper_selection / $todays_ex_rate_for_merchant_selection;
		$final_charge = round( $purchase_amount / $todays_ex_from_shopper_selection, 2 );
		if(!empty($todays_ex_from_shopper_selection)){
			update_post_meta( $this->customer_order->id, '_bsnp_ex_rate', $todays_ex_from_shopper_selection );
		}
		if(!empty($purchase_amount)){
			update_post_meta( $this->customer_order->id, '_order_origin_total', $purchase_amount ); // Save origin price, just as backup
		}
		if(!empty($final_charge)){
			update_post_meta( $this->customer_order->id, '_order_total', $final_charge );
		}
		if(!empty($currency_of_payment)){
			update_post_meta( $this->customer_order->id, '_charged_currency', $currency_of_payment );
		}
	}

	/**
	 * Display customer notification
	 */
	public function bsnp_price_notification() {
		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			wc_get_template( 'my-account.php', null, 'bluesnap/', BLUESNAP_BASE_DIR . 'templates/' );
		}
	}

    public function upon_chargeback_cancel_subscription($wc_order_id)
    {
		$post_meta = $this->bsnp_get_subscription_parent_id($wc_order_id);
		$subscription = wc_get_order( $post_meta );
		if( !empty( $subscription ) ){
			$subscription->update_status( 'cancelled' );
		}

    }

	/**
	 * @param $wc_order_id
	 * @return mixed
	 */
	private function bsnp_get_subscription_parent_id($wc_order_id)
	{
		$post_meta = get_post_meta($wc_order_id, '_subscription_renewal', true);
		if (empty($post_meta)) {
			$subscription_id = get_post_meta($wc_order_id, '_bluesnap_subscription_id', true);
			if (!empty($subscription_id)) {
				$post_meta = $this->get_subscription_from_parent_order($wc_order_id);
				return $post_meta;
			}
			return $post_meta;
		}
		return $post_meta;
	}

	private function get_subscription_from_parent_order($wc_order_id)
	{
		global $wpdb;
		//$bsnp_order_conversion = $GLOBALS['FiSHa_BSNP_Globals']->bsnp_get_order_converter_table_name();
		$table_name = $wpdb->prefix . 'posts';
		$sql        = $wpdb->prepare( "SELECT ID FROM $table_name WHERE post_parent = %s", $wc_order_id );
		$result     = $wpdb->get_results( $sql );
		if ( ! empty( $result ) ) {
			return $result['0']->ID;
		}
		return $wc_order_id;
	}
}

$GLOBALS['FiSHa_BSNP_Subscriptions'] = new Fisha_Bsnp_Subscriptions();