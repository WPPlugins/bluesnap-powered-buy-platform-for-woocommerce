<?php

if ( ( ! defined( 'ABSPATH' ) ) ) {
	exit();
}

/**
 * Created by PhpStorm.
 * User: yarivkohn
 * Date: 3/15/15
 * Time: 3:05 PM
 */
class WC_Gateway_Bluesnap_Cc extends WC_Payment_Gateway {

	private $bsnp_response_body;
	private $subid;
	private $payload = array();
	private $customer_order;
	private $environment_url;
	private $bsnp_order_id;
	private $bsnp_invoice_id;
	private $bsnp_subscription_product;
	private $bsnp_is_preorder;
	private $bsnp_is_subsacription;
	private $bsnp_xml;
	private $bsnp_shopper_id;
	private $subscriptions_contract_id;
	private $order_contract_id;
	private $purchase_currency;
	private $purchase_ammount;
	private $bsnp_is_upfront;
	private $bsnp_subscription_init_fee;
	private $payment_status;
	private $bsnp_fruad_session;
	private $merchant_id;
	private $bsnp_return_shopper_data_after_update;

	public function __construct() {
		// Generates UUID for fraud implementation
		$this->bsnp_fruad_session = $GLOBALS['FiSHa_BSNP_Functions']->gen_uuid();
		$this->id                 = "wc_gateway_bluesnap_cc";
		$this->subid 			  = "wc_gateway_bluesnap_cc";
		$this->method_title       = __( "BlueSnap Card Payments", 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' );
		$this->method_description = __( "BlueSnap Payment Gateway Plug-in for WooCommerce", 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' );
		$bsnp_cc_options          = get_option( 'woocommerce_.' . $this->subid . '_settings' );
		$this->title              = $bsnp_cc_options['title'];
		$this->description        = $bsnp_cc_options['description'];
		$this->has_fields         = true;
		$this->supports           = array(
			'subscriptions',
			'products',
			'subscription_cancellation',
			'subscription_reactivation',
			'subscription_suspension',
			'subscription_amount_changes',
			'subscription_payment_method_change_customer',
			'default_credit_card_form',
			'refunds',
			'pre-orders'
		);
		$this->init_form_fields();
		$this->init_settings();
		foreach ( $GLOBALS['FiSHa_BSNP_Globals']->bsnp_common_settings as $k => $v ) {
			if ( isset( $_POST[ 'woocommerce_wc_gateway_bluesnap_cc_' . $v ] ) && $_POST[ 'woocommerce_wc_gateway_bluesnap_cc_' . $v ] != null ) {
				$option_data = is_array( $_POST[ 'woocommerce_wc_gateway_bluesnap_cc_' . $v ] ) ? serialize( $_POST[ 'woocommerce_wc_gateway_bluesnap_cc_' . $v ] ) : $_POST[ 'woocommerce_wc_gateway_bluesnap_cc_' . $v ];
				update_option( BSNP_PREFIX . '_' . $v, sanitize_text_field( $option_data ) );
			}
			$this->settings[ $v ] = get_option( BSNP_PREFIX . '_' . $v );
		}
		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}
		$GLOBALS['FiSHa_BSNP_Globals']->environment = $this->environment;
		require_once( BLUESNAP_BASE_DIR . 'helper/bsnp-load-scripts.php' );
		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->subid, array(
				$this,
				'process_admin_options'
			) );
			add_action( 'woocommerce_admin_order_data_after_shipping_address', array(
				$GLOBALS['FiSHa_BSNP_Functions'],
				'bsnp_add_order_invoice_or_number_to_order_details'
			) );

//			Disabled on 02/01/2017 see BSQA-210
//			add_action( 'woocommerce_admin_order_totals_after_total', array(
//				$GLOBALS['FiSHa_BSNP_Functions'],
//				'bsnp_add_order_actual_paid_currency'
//			) );
		}

		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			// Subscription integration
			add_action( 'woocommerce_scheduled_subscription_payment_wc_gateway_bluesnap_cc', array(
				$GLOBALS['FiSHa_BSNP_Subscriptions'],
				'bsnp_scheduled_subscription_payment'
			), 10, 2 );
			add_action( 'woocommerce_scheduled_subscription_expiration', array(
				$GLOBALS['FiSHa_BSNP_Subscriptions'],
				'shopper_cancel_order'
			), 10, 2 );
//            add_action( 'woocommerce_subscription_status_on-hold', array( $GLOBALS['FiSHa_BSNP_Subscriptions'], 'bsnp_subscription_suspension' ), 10 , 1 );
			add_action( 'woocommerce_subscription_status_active', array(
				$GLOBALS['FiSHa_BSNP_Subscriptions'],
				'bsnp_subscription_reactivation'
			), 10, 1 );
			add_action( 'woocommerce_subscription_status_cancelled', array(
				$GLOBALS['FiSHa_BSNP_Subscriptions'],
				'shopper_cancel_order'
			), 10, 1 );
			add_action( 'subscriptions_expired_for_order', array(
				$GLOBALS['FiSHa_BSNP_Subscriptions'],
				'shopper_cancel_order'
			), 10, 2 );
			add_action( 'processed_subscription_payments_for_order', array(
				$GLOBALS['FiSHa_BSNP_Subscriptions'],
				'subscription_log_order_renewal'
			), 10, 1 );
			add_action( 'woocommerce_renewal_order_payment_complete', array(
				$GLOBALS['FiSHa_BSNP_Subscriptions'],
				'save_subscription_renewal'
			), 10, 1 );
		}

		if ( class_exists( 'WC_Pre_Orders_Order' ) ) {
			//PreOrder AutoProcess integration
			add_action( 'wc_pre_orders_process_pre_order_completion_payment_wc_gateway_bluesnap_cc', array(
				$GLOBALS['FiSHa_BSNP_Preorder'],
				'process_pre_order_payments'
			), 10, 1 );
		}

		//Update email notification
		add_action( 'woocommerce_email_after_order_table', array(
			$GLOBALS['FiSHa_BSNP_Currency_Switcher'],
			'add_email_order_meta'
		), 10, 2 );

//		add_action( 'woocommerce_after_order_notes', array($this, 'test_ff') );

//        add_action( 'woocommerce_email_customer_details', array($GLOBALS['FiSHa_BSNP_Currency_Switcher'], 'add_email_order_meta'), 10, 2 );
	} // end of __construct()


	/**
	 * Show description if exist
	 */
	public function payment_fields() {
		if ( $description = $this->get_description() ) {
			echo wpautop( wptexturize( $description ) );
		}
		if ( $this->supports( 'default_credit_card_form' ) ) {
			$this->credit_card_form();
		}
	}

	/**
	 * Build the administration fields for BlueSnap credit card
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'                   => array(
				'title'   => __( 'BlueSnap payment using Credit/Debit Card', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ),
				'label'   => __( 'Enable BlueSnap payment via Credit/Debit Card', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ),
				'type'    => 'checkbox',
				'default' => 'no',
			),
			'environment'               => array(
				'title'       => __( 'Sandbox mode for Credit/Debit Card', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ),
				'label'       => __( 'Enable Sandbox mode for Credit/Debit Card', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Place BlueSnap payment gateway in testing mode', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ),
				'default'     => 'no',
			),
			'title'                     => array(
				'title'    => __( 'Title', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ),
				'type'     => 'text',
				'desc_tip' => __( 'Type here the name that you want the user to see', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ),
				'default'  => 'Credit/Debit Card',
			),
			'description'               => array(
				'title'    => __( 'Description', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ),
				'type'     => 'text',
				'desc_tip' => __( 'Enter your payment method description here (optional)', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ),
				'default'  => 'Pay using your Credit/Debit Card',
			),
			'api_login'                 => array(
				'title'    => __( 'API username', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ),
				'type'     => 'text',
				'desc_tip' => __( 'This is the API Username provided by BlueSnap when you signed up for an account.', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ),
				'default'  => '',
			),
			'password'                  => array(
				'title'    => __( 'API password', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ),
				'type'     => 'password',
				'desc_tip' => __( 'This is the API Password provided by BlueSnap when you signed up for an account.', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ),
				'default'  => '',
			),
            'merchant_id' => array(
                'title' => __( 'Merchant ID', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ),
                'type' => 'text',
                'desc_tip' => __('This is the Merchant Id provided by BlueSnap when you signed up for an account.', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ),
                'default' => '',
            ),
			'store_id'                  => array(
				'title'    => __( 'Store ID', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ),
				'type'     => 'text',
				'desc_tip' => __( 'Store id provided by BlueSnap when you signed up for an account.', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ),
				'default'  => '',
			),
			'order_contract_id'         => array(
				'title'    => __( 'Order contract id', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ),
				'type'     => 'text',
				'desc_tip' => __( 'This is the contract id provided to you by BlueSnap.', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ),
				'default'  => '',
			),
			'subscriptions_contract_id' => array(
				'title'    => __( 'Subscriptions contract id', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ),
				'type'     => 'text',
				'desc_tip' => __( 'This is the contract id provided to you by BlueSnap.', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ),
				'default'  => '',
			),
			'cse_key'                   => array(
				'title'    => __( 'CSE key', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ),
				'type'     => 'textarea',
				'desc_tip' => __( 'Client Side Encryption (CSE) Key, provided to you by BlueSnap.', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ),
				'default'  => '',
			),
			'cs_status'                 => array(
				'title'    => __( 'BlueSnap currency converter', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ),
				'label'    => __( 'Enable BlueSnap currency converter', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ),
				'desc_tip' => __( 'Currency exchange rates are being automatically updated daily', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ),
				'type'     => 'checkbox',
				'default'  => 'no',
			),
			'cs_currencies'             => array(
				'title'             => __( 'Select currencies to display in your shop', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ),
				'type'              => 'multiselect',
				'options'           => $GLOBALS['FiSHa_BSNP_Currency_Switcher']->get_available_currencies_list_flipped(),
				'css'               => 'width: 200px;',
				'custom_attributes' => array( 'size' => 15, 'name' => 'currency_switcher_currencies' ),
			),
			'update_rates'              => array(
				'title' => __( 'Update rates now', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ),
				'type'  => 'button',
				'value' => __( 'Update rates', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ),
				'class' => "button-primary update_rates"
			),
			'ipn'                       => array(
				'type' => 'ipn',
			),
		);
		if ( ! class_exists( 'WC_Subscriptions_Order' ) ) {  // If subscription is not installed, remove subscription contract id field
			unset( $this->form_fields['subscriptions_contract_id'] );
		}
		//Hide currency switcher setting until the user entered his credentials
		if (empty(get_option('bsnp_api_login')) ||
			empty((get_option('bsnp_password'))) ||
			empty((get_option('bsnp_store_id'))) ||
			empty((get_option('bsnp_order_contract_id'))) ||
			empty((get_option('bsnp_cse_key')))
		) {
			unset($this->form_fields['cs_status']);
			unset($this->form_fields['cs_currencies']);
			unset($this->form_fields['update_rates']);
		}
	}

	/**
	 * Add IPN URL display to admin panel
	 */
	public function generate_ipn_html() {
		$GLOBALS['FiSHa_BSNP_Html']->bsnp_display_ipn_rout();
	}


	/**
	 * Override original function.
	 * Changelog - 1. remove class selector to avoid css. 2. change cols number.
	 *
	 * @param mixed $key
	 * @param mixed $data
	 *
	 * @return string
	 */
	public function generate_textarea_html( $key, $data ) {
		$field    = $this->plugin_id . $this->subid . '_' . $key;
		$defaults = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array()
		);
		$data = wp_parse_args( $data, $defaults );
		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
				<?php echo $this->get_tooltip_html( $data ); ?>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span>
					</legend>
                    <textarea rows="3" cols="46" type="<?php echo esc_attr( $data['type'] ); ?>"
                              name="<?php echo esc_attr( $field ); ?>" id="<?php echo esc_attr( $field ); ?>"
                              style="<?php echo esc_attr( $data['css'] ); ?>"
                              placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>" <?php disabled( $data['disabled'], true ); ?> <?php echo $this->get_custom_attribute_html( $data ); ?>><?php echo esc_textarea( $this->get_option( $key ) ); ?></textarea>
					<?php echo $this->get_description_html( $data ); ?>
				</fieldset>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}


	/**
	 * Override original function.
	 * Changelog - 1. remove class selector to avoid css. 2. change cols number.
	 *
	 * @param mixed $key
	 * @param mixed $data
	 *
	 * @return string
	 */
	public function generate_button_html( $key, $data ) {
		$field    = $this->plugin_id . $this->subid . '_' . $key;
		$defaults = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array()
		);
		$data     = wp_parse_args( $data, $defaults );
		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
				<?php echo $this->get_tooltip_html( $data ); ?>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span>
					</legend>
					<button
						class="<?php echo esc_attr( $data['class'] ); ?>
                    style="<?php esc_attr( $data['css'] ); ?>">
					<?php echo __( 'Update rates', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ) ?> </button>
				</fieldset>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}


	/**
	 * Retrieve previously used card for returning shopper.
	 * Note: data contain last 4 digits only.
	 *
	 * @param $bsnp_user_id
	 *
	 * @return mixed
	 * @throws Exception
	 */
	private function bsnp_get_previous_cards( $bsnp_user_id ) {
		$environment_url = $GLOBALS['FiSHa_BSNP_Globals']->bsnp_get_environment_url( $this->environment );
		$bsnp_call_url   = $environment_url . BS_RETURN_ROUT . $bsnp_user_id;
		return $GLOBALS['FiSHa_BSNP_Http']->bsnp_get_curl( $bsnp_call_url );
	}

	/**
	 * Generates checkout fields
	 *
	 * @param array $args
	 * @param array $fields
	 */
	public function credit_card_form( $args = array(), $fields = array() ) {
		wp_enqueue_script( 'wc-credit-card-form' );
		$default_return_fields = array();

		$bsnp_return_shopper   = $GLOBALS['FiSHa_BSNP_Functions']->bsnp_is_returnig_shopper();
		if ( $bsnp_return_shopper ) {
			try{
				$bsnp_return_shopper_data = $GLOBALS['FiSHa_BSNP_Functions']->bsnp_xml_to_array( $this->bsnp_get_previous_cards( $GLOBALS['FiSHa_BSNP_Functions']->bsnp_get_wc_user_id() ) );
			} catch (Exception $e) {
				$bsnp_return_shopper_data = false;
				?>
					<div><p><?php echo "Failed to communicate with server"  ?></p></div>
				<?php
			}
			$bsnp_shopper_credit_card_list = '';
			if ( $bsnp_return_shopper_data ) {
				$bsnp_credit_card_list         = $bsnp_return_shopper_data['shopper-info']['payment-info']['credit-cards-info']['credit-card-info'];
				if ( isset( $bsnp_credit_card_list['credit-card'] ) ) { // Shopper have only one saved card
					$bsnp_shopper_credit_card_list = " <input type = 'radio' name = 'reused_credit_card' checked = 'checked' value = '" . esc_attr( $bsnp_credit_card_list['credit-card']['card-last-four-digits'] ) . ";" . esc_attr( $bsnp_credit_card_list['credit-card']["card-type"] ) . "'> " .
					                                 "<b>" . esc_attr( $bsnp_credit_card_list['credit-card']['card-type'] ) . "</b>   xxxx xxxx xxxx <b>" . esc_attr( $bsnp_credit_card_list['credit-card']['card-last-four-digits'] ) . "</b><br>";
				} elseif ( is_array( $bsnp_credit_card_list ) ) {
					try {
						foreach ( $bsnp_credit_card_list as $cards ) { // Shopper have 2 or more saved cards
							if ( $cards == end( $bsnp_credit_card_list ) ) {
								$selected = " checked = 'checked' ";
							} else {
								$selected = '';
							}
							$this_card = $cards['credit-card'];
							$bsnp_shopper_credit_card_list .= " <input type = 'radio' name = 'reused_credit_card'" . esc_attr( $selected ) . " value = '" . esc_attr( $this_card['card-last-four-digits'] ) . ";" . esc_attr( $this_card["card-type"] ) . "'> " .
							                                  "<b>" . esc_attr( $this_card["card-type"] ) . "</b> xxxx xxxx xxxx <b>" . esc_attr( $this_card["card-last-four-digits"] ) . "</b><br>";
						}
					} catch ( Exception $e ) {
						$GLOBALS['FiSHa_BSNP_Logger']->logger( 'Generating payment form', 'Couldn\'t receive previously used Credit/Debit Cards' );
					}
				}
				if ( '' == $bsnp_shopper_credit_card_list ) {
					$bsnp_shopper_credit_card_list = "<p>" . __( 'We were unable to retrieve your saved Credit/Debit Card please try again later', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ) . "<p>";
				}
			}
			$default_return_fields = array(
				'return card'          => "<div id='bsnp_return_shopper'> $bsnp_shopper_credit_card_list ",
				'new card'             => '<a class="bsnp_new_card" src="javascript:void(0)">' . __( 'Use a different card', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ) . '</a></div>',
				'is returning shopper' => '<input type="hidden" class="is_return_shopper" value="true">'
			);
		}
		$bsnp_public_key          = get_option( 'bsnp_cse_key' );
		$this->merchant_id        = get_option( 'bsnp_merchant_id' );
		$this->bsnp_fruad_session = $GLOBALS['FiSHa_BSNP_Functions']->gen_uuid();
        $bsnp_fraud_url = $GLOBALS[ 'FiSHa_BSNP_Globals']->bsnp_get_environment_url( $this->environment, true ). BS_FRAUD_URL. '?d='.$this->merchant_id. '&s='.$this->bsnp_fruad_session;
        $bsnp_fraud_img = $GLOBALS[ 'FiSHa_BSNP_Globals']->bsnp_get_environment_url( $this->environment, true ). 'logo.gif?d='.$this->merchant_id.'&s='. $this->bsnp_fruad_session;
		$is_supported_style = ( $GLOBALS['FiSHa_BSNP_Currency_Switcher']->bsnp_currency_locally_supported( $_COOKIE['currency_code'] ) ) ? "style='display:none'" : "";
		$default_fields     = array_merge(
			$default_return_fields,
			array(
				'bluesnap-encryption'  => "<script>
                                        BlueSnap.publicKey = '{$bsnp_public_key}';
                                      </script>",
				'fraud'                => '<iframe width="1" height="1" frameborder="0" scrolling="no" src=' . $bsnp_fraud_url . '>
                            <img width="1" height="1" src=' . $bsnp_fraud_img . '</iframe>',
				'ajax_class'           => '<div class="bsnp_ajax_notification"></div>',
				'ex_rate'              => '<input type="hidden" class="ex_rate" name="basp_ex_rate">',
				'price_override'       => '<input type="hidden" id="bsnp_cc_price_override" name="bsnp_cc_price_override" value="0" >',
				'<input type="hidden" id="bsnp_cc_currency" name="bsnp_cc_currency" value="" >',
				'Shopper notification' => "<div id='bsnp_currency_is_not_supported'" . $is_supported_style . "><b>" . __( 'Please note that you will be charged in USD($)', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ) . "</b></div>",
				'card-name-field'      => '',
				'card-number-field'    => '<tr> <td style="border:0;"> <label for="creditCard">Card Number<span class="required">*</span></label> </td>
                                         <td style="border:0;"> <input type="text" placeholder="Enter Card Number" name="creditCard" id="creditCard" maxlength="20" class="input-text wc-credit-card-form-card-number" data-bluesnap="encryptedCreditCard"> </td>
                                    </tr>',
				'card_expiry_field'    => '<tr><td style="border:0;">
					       		                <label for="' . esc_attr( $this->subid ) . '_expiry_date">' . __( 'Expiration Date (Month/Year)', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ) . ' <span class="required">*</span></label></td>
					                         <td style="border:0;">
					                <select id="' . esc_attr( $this->subid ) . '_exp_month" name = "' . esc_attr( $this->subid ) . '_exp_month" >' . $GLOBALS['FiSHa_BSNP_Html']->get_valid_expiry_month() . '</select>
					                <select id="' . esc_attr( $this->subid ) . '_exp_year" name = "' . esc_attr( $this->subid ) . '_exp_year" >' . $GLOBALS['FiSHa_BSNP_Html']->get_valid_expiry_year() . '</select>
				                    </td></tr>',
				'card-cvc-field'       => '<tr>
                                 <td style="border:0;"> <label for="cvv">CVV<span class="required">*</span></label> </td>
                                 <td class="cvv-td" style="border:0;"> <input type="text" name="cvv" id="cvv" placeholder="CVV" class="input-text wc-credit-card-form-card-cvc" data-bluesnap="encryptedCvv" size="4" maxlength="4"></td>
                                 <td class="cvv-hints-td" style="border:0;">
                                 <div  id="bsnp_cvv_hint"> <img src="' . BLUESNAP_BASE_URL . 'img/cvc_hint.png" border="0" style="margin-top:0px;" alt="CCV/CVC?"></div>
                                 <div  id="bsnp_cvv_hint_mobile"> <img src="' . BLUESNAP_BASE_URL . 'img/cvc_hint.png" border="0" style="margin-top:0px;" alt="CCV/CVC?"></div>
                                 <div class="pli-cvv-wrapper">
                                 <div class="mobile_close_btn">Close</div>
                                 <h2><span>Credit Card Security Code</span></h2>
                                 <p><span>A three or four digit code on your Credit/Debit Card, separate from the 16 digit card number.<br>The location varies slightly depending on your type of card:</span></p>
                                 <div class="pli-left-col">
                                 <div class="pli-cvv-visa-img">&nbsp;</div>
                                 <p><span>On the reverse side of your card, you should see either the entire 16-digit Credit/Debit Card number or just the last four digits followed by a special 3-digit code. This 3-digit code is your Card Security Code.</span>
                                 </p>
                                 </div>
                                <div class="pli-right-col">
                                <div class="pli-cvv-amex-img">&nbsp;</div>
                                <p><span>Look for the 4-digit code printed on the front of your card just above and to the right of your main Credit/Debit Card number. This 4-digit code is your Card Security Code.</span>
                                </p>
                                </div>
                                 <div class="mobile_close_btn">Close</div>
                                </div>

                                </td>
                                </tr>
                                <input type="hidden" class="cvv-digit-num" name="cvv-digit-num">',
			)
		);
		$fields             = wp_parse_args( $fields, apply_filters( 'woocommerce_credit_card_form_fields', $default_fields, $this->subid ) );
		?>
		<fieldset id="<?php echo $this->subid; ?>-cc-form">
			<div class="defualt-credit-card-form form-row form-row-wide">
				<input type="hidden" class="credit-card-name-field" name="credit-card-type">
				<input type="hidden" class="credit-card-last-digit" name="credit-card-digit">
				<input type="hidden" class="credit-card-digit-num" name="credit-card-digit-num">
			<?php
			wp_enqueue_script( 'wc-credit-card-form' );
			do_action( 'woocommerce_credit_card_form_start', $this->subid );
			echo "<table class = 'bsnp_checkout_table'>";
			foreach ( $fields as $field ) {
				echo $field;
			}
			echo "</table>";
			?>
			<?php do_action( 'woocommerce_credit_card_form_end', $this->subid ); ?>
			<div class="clear"></div>
				</div>
		</fieldset>
		<?php
	}

	/**
	 * Return current shop product sku
	 * @return string
	 */
	private function bsnp_get_product_sku( $bsnp_is_subscription ) {
		return ( $bsnp_is_subscription ) ? $this->subscriptions_contract_id : $this->order_contract_id;
	}

	/**
	 * Submit payment to BlueSnap and handle order and errors
	 *
	 * @param int $order_id
	 *
	 * @return array|void
	 * @throws Exception
	 */
	public function process_payment( $order_id ) {
		$bluesnap_config = get_option('woocommerce_wc_gateway_bluesnap_cc_settings');
		$currency_switcher_data = isset($bluesnap_config['cs_currencies']) ? $bluesnap_config['cs_currencies'] : array(); //array( 'currencies' => get_option( BSNP_PREFIX.'_cs_currencies' ) );
		if ( $GLOBALS['FiSHa_BSNP_Validation']->bsnp_validate_details() || $GLOBALS['FiSHa_BSNP_Validation']->bsnp_validate_cc_details() ) {
			return false;
		}

		if ( count( $currency_switcher_data['currencies'] ) > 0 ) {
			if ( $GLOBALS['FiSHa_BSNP_Validation']->bsnp_validate_cookie_details() ) {
				wc_add_notice( __( 'Data integrity failure.', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ), 'error' );
				return false;
			}
		}
		$ex_rate = $this->bsnp_get_currenct_ex_rate();
		$GLOBALS['FiSHa_BSNP_Logger']->logger( 'Start payment process for order #' . $order_id, 'Starting order placement' );
		$this->customer_order    = wc_get_order( $order_id );
		$this->purchase_currency = $GLOBALS['FiSHa_BSNP_Currency_Switcher']->bsnp_get_purchase_currency();
		$this->purchase_ammount  = round( ( $this->customer_order->get_total() * $ex_rate ), 2 );

		// If currency switcher is activated, we need to update currency and purchase amount
		// In that case the price was already been rounded via JS
		$this->bsnp_update_price_and_currency();
		$GLOBALS['FiSHa_BSNP_Globals']->payload         = array();
		$this->bsnp_subscription_product                = false;
		$GLOBALS['FiSHa_BSNP_Globals']->environment_url = $GLOBALS['FiSHa_BSNP_Globals']->bsnp_get_environment_url( $this->environment );
		$this->order_contract_id                        = get_option( 'bsnp_order_contract_id' );
		$this->bsnp_set_is_preorder( $order_id );
		$this->bsnp_set_is_subscription( $order_id, $ex_rate );
		$GLOBALS['FiSHa_BSNP_Logger']->logger( 'Start payment process for order #' . $order_id, 'Product type is: ' . $this->bsnp_get_product_type() );

		// This is returning shopper
		if ( $GLOBALS['FiSHa_BSNP_Functions']->bsnp_is_returnig_shopper() ) {
			$GLOBALS['FiSHa_BSNP_Api']->bsnp_update_shopper_details( $this->bsnp_update_shopper( $this->customer_order ) );
			if ( $GLOBALS['FiSHa_BSNP_Http']->update_error ) {
				$GLOBALS['FiSHa_BSNP_Logger']->logger( 'Attempt to update shopper', 'Update failed for shopper #' . $GLOBALS['FiSHa_BSNP_Globals']->bsnp_shopper_id );
				$this->bsnp_order_failed( $order_id );
				return false;
			}
			$this->bsnp_return_shopper_data_after_update = $GLOBALS['FiSHa_BSNP_Functions']->bsnp_xml_to_array( $this->bsnp_get_previous_cards( $GLOBALS['FiSHa_BSNP_Functions']->bsnp_get_wc_user_id() ) );
			if ( isset( $_POST['woocommerce_change_payment'] ) ) { // This is subscription payment method update
				$update = $this->bsnp_update_subscription_credit_card( $order_id );
				if ( ! $update ) {
					return false;
				}
				return $this->bsnp_go_to_ty_page();
			}
			$GLOBALS['FiSHa_BSNP_Globals']->bsnp_xml = new SimpleXMLElement( '<order xmlns="http://ws.plimus.com"> </order>' );
			$bsnp_environment_url                    = $this->bsnp_set_payload();
		} // This is not a returning shopper
		else {
			if ( $this->bsnp_is_preorder || $this->bsnp_is_subsacription ) {
				$bsnp_environment_url                    = $GLOBALS['FiSHa_BSNP_Globals']->environment_url . BS_SHOPPING_CONTEXT;
				$GLOBALS['FiSHa_BSNP_Globals']->payload  = $this->bsnp_create_preorder_payload( $this->customer_order );
				$GLOBALS['FiSHa_BSNP_Globals']->bsnp_xml = new SimpleXMLElement( '<shopping-context xmlns="http://ws.plimus.com"> </shopping-context>' );
			} else {
				$bsnp_environment_url                    = $GLOBALS['FiSHa_BSNP_Globals']->environment_url . BS_ORDER_ROUT;
				$GLOBALS['FiSHa_BSNP_Globals']->payload  = $this->bsnp_create_payload( $this->customer_order );
				$GLOBALS['FiSHa_BSNP_Globals']->bsnp_xml = new SimpleXMLElement( '<batch-order xmlns="http://ws.plimus.com"> </batch-order>' );
			}
		}
		//Payment request creation ends here

		// Send request to Bluesnap
		$GLOBALS['FiSHa_BSNP_Functions']->array_to_xml( $GLOBALS['FiSHa_BSNP_Globals']->payload, $GLOBALS['FiSHa_BSNP_Globals']->bsnp_xml );
		$GLOBALS['FiSHa_BSNP_Logger']->logger( 'Start payment process for order #' . $order_id, 'Attempt to place order' );
		$response = $GLOBALS['FiSHa_BSNP_Http']->bsnp_send_curl( $bsnp_environment_url, $GLOBALS['FiSHa_BSNP_Globals']->bsnp_xml->asXML() );
		// end of send request


		// Parsing the response
		if ( ( ! $this->bsnp_is_preorder ) && ( ! $this->bsnp_is_subsacription ) ) {
			$this->bsnp_response_body = $GLOBALS['FiSHa_BSNP_Http']->global_response_body;
			$this->bsnp_response_body = @simplexml_load_string( $GLOBALS['FiSHa_BSNP_Http']->global_response_body );
			if ( ! is_object( $this->bsnp_response_body ) ) {
				$GLOBALS['FiSHa_BSNP_Validation']->shopper_general_error();

				return false;
			}
		} else {
			if ( $GLOBALS['FiSHa_BSNP_Http']->http_code != "201" ) {
				//$GLOBALS['FiSHa_BSNP_Validation']->shopper_general_error();
				$this->bsnp_order_failed( $order_id );

				return false;
			}
		}
		if ( $response ) {
			if ( ( ! $this->bsnp_is_preorder ) && ( ! $this->bsnp_is_subsacription ) ) {
				if ( ! $GLOBALS['FiSHa_BSNP_Functions']->bsnp_is_returnig_shopper() ) {
					$this->bsnp_shopper_id = $this->bsnp_response_body->shopper->{'shopper-info'}->{'shopper-id'}->__toString();
					$this->bsnp_order_id   = $this->bsnp_response_body->order->{'order-id'}->__toString();
					$this->bsnp_invoice_id = $this->bsnp_response_body->order->{'post-sale-info'}->invoices->invoice->{'invoice-id'}->__toString();
					$GLOBALS['FiSHa_BSNP_Db']->bsnp_create_return_shopper( $this->bsnp_shopper_id, $this->customer_order->get_user_id() );
				} else if ( $GLOBALS['FiSHa_BSNP_Functions']->bsnp_is_returnig_shopper() ) {
					$this->bsnp_shopper_id = $this->bsnp_response_body->{'ordering-shopper'}->{'shopper-id'}->__toString(); // try
					$this->bsnp_order_id   = $this->bsnp_response_body->{'order-id'}->__toString();
					$this->bsnp_invoice_id = $this->bsnp_response_body->{'post-sale-info'}->invoices->invoice->{'invoice-id'}->__toString();
				}
				$GLOBALS['FiSHa_BSNP_Functions']->bsnp_save_bsnp_order( $this->bsnp_order_id, $this->bsnp_invoice_id, $order_id, $this->bsnp_shopper_id, $this->customer_order->get_user_id() );
			}
			if ( $this->bsnp_is_preorder || $this->bsnp_is_subsacription ) {
				$this->bsnp_order_id   = trim( $GLOBALS['FiSHa_BSNP_Http']->bsnp_get_order_id_from_header( $GLOBALS['FiSHa_BSNP_Http']->header ), '/' );
				$this->bsnp_shopper_id = $GLOBALS['FiSHa_BSNP_Api']->bsnp_bluesnap_shopper_id( $this->bsnp_order_id );
				if ( $this->bsnp_is_subsacription ) {
					/** For subscription we update the shopping context now with amount of 0.0$ in order to create subscription id  */
					$GLOBALS['FiSHa_BSNP_Globals']->environment_url = $GLOBALS['FiSHa_BSNP_Globals']->bsnp_get_environment_url( $this->environment );
					$bsnp_environment_url                           = $GLOBALS['FiSHa_BSNP_Globals']->environment_url . BS_SHOPPING_CONTEXT . $this->bsnp_order_id;
					$GLOBALS['FiSHa_BSNP_Globals']->payload         = $this->bsnp_update_subscription_payload( $this->customer_order );
					$GLOBALS['FiSHa_BSNP_Globals']->bsnp_xml        = new SimpleXMLElement( '<shopping-context xmlns="http://ws.plimus.com"> </shopping-context>' );
					$GLOBALS['FiSHa_BSNP_Functions']->array_to_xml( $GLOBALS['FiSHa_BSNP_Globals']->payload, $GLOBALS['FiSHa_BSNP_Globals']->bsnp_xml );
					$GLOBALS['FiSHa_BSNP_Http']->bsnp_put_curl( $bsnp_environment_url, $GLOBALS['FiSHa_BSNP_Globals']->bsnp_xml->asXML() ); // NOTE: We have a HTTP PUT request here, This is the actual order placement
				}

				if ( $GLOBALS['FiSHa_BSNP_Globals']->bsnp_shopper_id == null ) {
					$GLOBALS['FiSHa_BSNP_Db']->bsnp_create_return_shopper( $this->bsnp_shopper_id, $this->customer_order->get_user_id() );

				}
				$GLOBALS['FiSHa_BSNP_Functions']->bsnp_save_bsnp_order( $this->bsnp_order_id, $this->bsnp_invoice_id, $order_id, $this->bsnp_shopper_id, $this->customer_order->get_user_id() );
			}

			if ( $this->bsnp_is_upfront ) {
				// Here we change the status back to true, in order to set status as Pre-ordered.
				$this->bsnp_is_preorder = true;
			}

			$GLOBALS['FiSHa_BSNP_Currency_Switcher']->bsnp_keep_exchange_rate( $this->customer_order->id, $this->purchase_currency, $ex_rate );
			if ( ! $this->bsnp_is_preorder && ! $this->bsnp_is_subsacription ) {
				$this->bsnp_check_order_status();
			} else if ( $this->bsnp_is_preorder ) {
				WC_Pre_Orders_Order::mark_order_as_pre_ordered( $this->customer_order );
			} else if ( $this->bsnp_is_subsacription ) {
				$GLOBALS['FiSHa_BSNP_Subscriptions']->bsnp_save_subscription( $this->bsnp_order_id, $this->customer_order->id  );
				if ( "Approved" == $GLOBALS['FiSHa_BSNP_Globals']->payment_status ) {
					$this->bsnp_payment_complete();
				} else if ( "Decline" == $GLOBALS['FiSHa_BSNP_Globals']->payment_status ) {
					$this->bsnp_order_failed( $order_id );

					return false;
				}
			}
			WC()->cart->empty_cart();
			$GLOBALS['FiSHa_BSNP_Logger']->logger( 'Finished payment process for order #' . $order_id, 'Payment process completed' );
			add_user_meta( $this->customer_order->get_user_id(), '_bsnp_approved_shopper', 1, true );
			if(!empty($ex_rate)){
				update_post_meta( $this->customer_order->id, '_bsnp_ex_rate', $ex_rate );
			}

			return $this->bsnp_go_to_ty_page();

		} else if ( ! $response ) {  // Transaction was not successful, add notice to the shopper
			$this->bsnp_order_failed( $order_id );
		}

		return true;
	} // end of process payment


	/**
	 * Update payments method details on BSNP server
	 *
	 * @param $order_id
	 *
	 * @return bool
	 */
	public function bsnp_update_subscription_credit_card( $order_id ) {
		$last4_digits = $_POST['credit-card-digit'];
		if(empty($last4_digits)){
			if ( isset( $_POST['reused_credit_card'] ) ) {
				$credit_card_details = explode('_', $_POST['reused_credit_card']);
				$last4_digits = $credit_card_details[0];
			}
		}
		$bsnp_card_type = $this->get_bsnp_saved_card_type($this->bsnp_return_shopper_data_after_update, $last4_digits);
		if ( isset( $_POST['reused_credit_card'] ) ) {
			$credit_card_details        = explode( ';', $_POST['reused_credit_card'] );
			$credit_card_last_for_digit = $GLOBALS['FiSHa_BSNP_Functions']->is_digits( $credit_card_details[0], true );
			$credit_card_type           = sanitize_text_field( $credit_card_details[1] );
		} else {
			$credit_card_last_for_digit = $GLOBALS['FiSHa_BSNP_Functions']->is_digits( $last4_digits, true );
			$credit_card_type           = $bsnp_card_type;
		}
		$parent_id                               = wp_get_post_parent_id( $order_id );
		$subscription_id                         = $GLOBALS['FiSHa_BSNP_Db']->get_bsnp_subscription_id( $parent_id );
		$bsnp_environment_url                    = $GLOBALS['FiSHa_BSNP_Globals']->environment_url . BS_SUBSCRIPTION . $subscription_id;
		$GLOBALS['FiSHa_BSNP_Globals']->bsnp_xml = new SimpleXMLElement( '<subscription xmlns="http://ws.plimus.com"> </subscription>' );
		$payload                                 = array(
			'credit-card' => array(
				'card-last-four-digits' => $credit_card_last_for_digit,
				'card-type'             => $credit_card_type,
			),
		);
		$GLOBALS['FiSHa_BSNP_Functions']->array_to_xml( $payload, $GLOBALS['FiSHa_BSNP_Globals']->bsnp_xml );
		$result = $GLOBALS['FiSHa_BSNP_Http']->bsnp_put_curl( $bsnp_environment_url, $GLOBALS['FiSHa_BSNP_Globals']->bsnp_xml->asXML() );
		if ( ! $result || ! preg_match( '/^2\d{2}$/', $GLOBALS['FiSHa_BSNP_Http']->global_response_code ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Return Product type
	 * @return string
	 */
	private function bsnp_get_product_type() {
		if ( $this->bsnp_is_subsacription ) {
			return "Subscription";
		} else if ( $this->bsnp_is_preorder ) {
			return "Pre-Order";
		} else {
			return "Regular";
		}
	}


//	function test_ff( $checkout ) {
//
//		echo '<div id="my_custom_checkout_field"><h2>' . __('My Field') . '</h2>';
//
//		woocommerce_form_field( 'my_field_name', array(
//			'type'          => 'text',
//			'class'         => array('my-field-class form-row-wide'),
//			'label'         => __('Fill in this field'),
//			'placeholder'   => __('Enter something'),
//		), $checkout->get_value( 'my_field_name' ));
//
//		echo '</div>';
//
//	}

	/**
	 * Handle refund case
	 *
	 * @param int $order_id
	 * @param null $amount
	 * @param string $reason
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$customer_order   = wc_get_order( $order_id );
		$ex_rate          = get_post_meta( $order_id, '_bsnp_ex_rate', true );
		$amount_to_refund = round( $amount * $ex_rate, 2 );
		if ( 0 == $ex_rate ) {
			return $this->refund_error();
		}
		$bsnp_reason        = ( $reason == '' ) ? 'Not specified' : $reason;
		$bsnp_refund_string = 'User refund amount of: ' . $amount . ', due to: ' . $bsnp_reason . ' (Order id: ' . $order_id . ')';
		$GLOBALS['FiSHa_BSNP_Logger']->logger( 'Refund shopper', $bsnp_refund_string );
		$invoiceId       = $this->getInvoiceId( $order_id );
		$environment_url = $GLOBALS['FiSHa_BSNP_Globals']->bsnp_get_environment_url( $this->environment );
		$environment_url .= BS_REFUND . '?invoiceid=';
		//This flag is to prevent subscription from being cancelled on bsnp server due to refund
		$environment_url .= $invoiceId . '&cancelsubscriptions=false';
		if ( ( $amount != null ) && ( $amount < $customer_order->get_total() ) ) {
			$environment_url .= '&amount=' . $amount_to_refund;
		}
		$GLOBALS['FiSHa_BSNP_Http']->bsnp_put_curl( $environment_url, null );
		if ( "204" == $GLOBALS['FiSHa_BSNP_Http']->global_response_code ) {  // That mean successful refund
//			if ( $amount == null || $amount != $customer_order->get_total() ) {
//				$customer_order->add_order_note( sprintf( __( 'Refunded with the amount of %s', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ), $amount ) );
//			} else {
//				$customer_order->add_order_note( sprintf( __( 'Refunded successfully', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ), $amount ) );
//			}
			return true;
		} else {
			return $this->refund_error();
		}
	}

	/**
	 * @param $order_id
	 *
	 * @throws Exception
	 */
	public function bsnp_order_failed( $order_id ) {
		$bsnp_error_code = $GLOBALS['FiSHa_BSNP_Api']->get_bsnp_error_code( $GLOBALS['FiSHa_BSNP_Functions']->bsnp_xml_to_array( $GLOBALS['FiSHa_BSNP_Http']->global_response_body ) );
		if ( ! $bsnp_error_code ) {
			wc_add_notice( __( 'Unfortunately an error has occurred and your payment cannot be processed at this time, please verify your payment details or try again later.', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ), 'error' );
		}
		$bsnp_error_msg = $GLOBALS['FiSHa_BSNP_Api']->get_bsnp_error_msg( $bsnp_error_code );
		$this->customer_order->add_order_note( __( 'Error: Order was not sent to merchant \'Please see logs for more info\'', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ) );
		$GLOBALS['FiSHa_BSNP_Logger']->logger( 'Payment process for order #' . $order_id . ' was not completed', $bsnp_error_msg . " BSNP Code: " . $bsnp_error_code );
		wc_add_notice( $bsnp_error_msg, 'error' );
	}

	/**
	 * Check for order status
	 * Set order status to Approved / Pending
	 */
	public function bsnp_check_order_status() {
		/**  Check here X times for order status, else wait for IPN */
		$status              = $GLOBALS['FiSHa_BSNP_Functions']->bsnp_is_returnig_shopper() ? // Response for returning shopper does not contain "order" node
			$this->bsnp_response_body->{'post-sale-info'}->invoices->invoice->{'financial-transactions'}->{'financial-transaction'}->status->__toString() :
			$this->bsnp_response_body->order->{'post-sale-info'}->invoices->invoice->{'financial-transactions'}->{'financial-transaction'}->status->__toString();
		$this->bsnp_order_id = $GLOBALS['FiSHa_BSNP_Functions']->bsnp_is_returnig_shopper() ?
			$this->bsnp_response_body->{'order-id'}->__toString() : $this->bsnp_response_body->order->{'order-id'}->__toString(); // Response for returning shopper does not contain "order" node
		if ( $status != "Approved" ) {
			$GLOBALS['FiSHa_BSNP_Functions']->bsnp_ask_for_order_status( $this->bsnp_order_id, $this->customer_order, $this->environment );
		} else {
			if ( "Approved" == $status ) {
				$this->bsnp_payment_complete();
			}
		}
	}


	/**
	 * Given BlueSnap order id, return invoice id
	 *
	 * @param $bsnp_order_id
	 *
	 * @return mixed
	 */
	private function getInvoiceId( $bsnp_order_id ) {
		return $GLOBALS['FiSHa_BSNP_Db']->bsnp_get_invoice_id( $bsnp_order_id );
	}

	/**
	 * Redirect to "Thank you" page
	 * @return array
	 */
	public function bsnp_go_to_ty_page() {
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $this->customer_order ),
		);
	}

	/**
	 * Create payload, and create XML for order by type
	 */
	public function bsnp_set_payload() {
		if ( $this->bsnp_is_preorder ) {
			$GLOBALS['FiSHa_BSNP_Globals']->payload  = $this->bsnp_return_shopping_context_with_credit_card( $this->customer_order );
			$bsnp_environment_url                    = $GLOBALS['FiSHa_BSNP_Globals']->environment_url . BS_SHOPPING_CONTEXT;
			$GLOBALS['FiSHa_BSNP_Globals']->bsnp_xml = new SimpleXMLElement( '<shopping-context xmlns="http://ws.plimus.com"> </shopping-context>' );
		} else if ( $this->bsnp_is_subsacription ) {
			$GLOBALS['FiSHa_BSNP_Globals']->payload  = $this->bsnp_return_subscription_payload( $this->customer_order );
			$bsnp_environment_url                    = $GLOBALS['FiSHa_BSNP_Globals']->environment_url . BS_SHOPPING_CONTEXT;
			$GLOBALS['FiSHa_BSNP_Globals']->bsnp_xml = new SimpleXMLElement( '<shopping-context xmlns="http://ws.plimus.com"> </shopping-context>' );
		} else {
			$GLOBALS['FiSHa_BSNP_Globals']->payload = $this->bsnp_return_shopper_with_credit_card( $this->customer_order );
			$bsnp_environment_url                   = $GLOBALS['FiSHa_BSNP_Globals']->environment_url . BS_REORDER_ROUT;
		}

		return $bsnp_environment_url;
	}


	/**
	 * Create new shopping context deal
	 */
	public function bsnp_init_new_shopping_context_deal() {
		$bsnp_environment_url                    = $GLOBALS['FiSHa_BSNP_Globals']->environment_url . BS_SHOPPING_CONTEXT . $this->bsnp_order_id;
		$GLOBALS['FiSHa_BSNP_Globals']->payload  = $this->bsnp_update_shopping_context( $this->customer_order );
		$GLOBALS['FiSHa_BSNP_Globals']->bsnp_xml = new SimpleXMLElement( '<shopping-context xmlns="http://ws.plimus.com"> </shopping-context>' );
		$GLOBALS['FiSHa_BSNP_Functions']->array_to_xml( $GLOBALS['FiSHa_BSNP_Globals']->payload, $GLOBALS['FiSHa_BSNP_Globals']->bsnp_xml );
		$GLOBALS['FiSHa_BSNP_Http']->bsnp_put_curl( $bsnp_environment_url, $GLOBALS['FiSHa_BSNP_Globals']->bsnp_xml->asXML() );
	}


	/**
	 * Payment complete procedure
	 */
	public function bsnp_payment_complete() {
		$this->customer_order->payment_complete();
	}


	/**
	 * Set exchange rate
	 * @return float|int
	 */
	public function bsnp_get_currenct_ex_rate() {
		$ex_rate = 0;
		// Currency switcher is enabled
		if ( isset ( $_COOKIE['ex_factor'] ) && ( $_COOKIE['ex_factor'] != '' ) ) {
			// Shopper uses shop base currency
			if ( 'Default currency' == $_COOKIE['currency_code'] ) {
				if ( $GLOBALS['FiSHa_BSNP_Currency_Switcher']->bsnp_currency_locally_supported( get_woocommerce_currency() ) ) {
					$ex_rate = 1;
				} else {
					$ex_rate = 1 / $GLOBALS['FiSHa_BSNP_Currency_Switcher']->get_usd_exchange_rate( get_woocommerce_currency() );;
				}

				return $ex_rate;
				// Shopper uses his own currency selction
			} else if ( ( $_COOKIE['currency_code'] != 'Default currency' ) ) {
				if ( ( 'Y' == $_COOKIE['is_shopper_selection_supported'] ) ) {
					$ex_rate = $_COOKIE['ex_factor'];
				}
				if ( ( 'N' == $_COOKIE['is_shopper_selection_supported'] ) ) {
					$ex_rate = 1 / $GLOBALS['FiSHa_BSNP_Currency_Switcher']->get_usd_exchange_rate( get_woocommerce_currency() );
				}

				return $ex_rate;
			}
			//Currency switcher is disabled
		} else {
			// Base currency is locally supported
			if ( $GLOBALS['FiSHa_BSNP_Currency_Switcher']->bsnp_currency_locally_supported( get_woocommerce_currency() ) ) {
				$ex_rate = 1;
				//Base currency is not locally supported
			} else {
				$ex_rate = 1 / $GLOBALS['FiSHa_BSNP_Currency_Switcher']->get_usd_exchange_rate( get_woocommerce_currency() );
			}

			return $ex_rate;
		}
	}


	/**
	 * Get data from cookie
	 */
	public function bsnp_update_price_and_currency() {
		if ( isset( $_POST['bsnp_cc_price_override'] ) && ( $_POST['bsnp_cc_price_override'] != 0 ) ) {
			$this->purchase_ammount = floatval( $_POST['bsnp_cc_price_override'] );
		}
		if ( isset( $_POST['bsnp_cc_currency'] ) && ( $_POST['bsnp_cc_currency'] != '' ) ) {
			$this->purchase_currency = sanitize_text_field( $_POST['bsnp_cc_currency'] );
		}
	}

	/**
	 * Set some vars for Preorders support
	 *
	 * @param $order_id
	 */
	public function bsnp_set_is_preorder( $order_id ) {
		if ( class_exists( 'WC_Pre_Orders_Order' ) ) {
			$this->bsnp_is_preorder = WC_Pre_Orders_Order::order_contains_pre_order( $order_id );
			if ( ( $this->bsnp_is_preorder ) && ( ! WC_Pre_Orders_Order::order_will_be_charged_upon_release( $order_id ) ) ) {
				$this->bsnp_is_upfront = true;
			} else {
				$this->bsnp_is_upfront = false;
			}
			if ( $this->bsnp_is_upfront ) {
				// We temporarily change this status to false,
				// in order to process the payment as regular order.
				// We will change it back downstream in order to mark tis order as pre-ordered.
				$this->bsnp_is_preorder = false;
			}
		} else {
			$this->bsnp_is_preorder = false;
		}
	}

	/**
	 * Set some var Subscriptions support
	 *
	 * @param $order_id
	 * @param $ex_rate
	 */
	public function bsnp_set_is_subscription( $order_id, $ex_rate ) {
		$this->bsnp_is_subsacription = ( class_exists( 'WC_Subscriptions_Order' ) ) ? wcs_order_contains_subscription( $this->customer_order ) : false;
		if ( $this->bsnp_is_subsacription ) { // Collect initial subscription data
			$this->bsnp_subscription_product  = true;
			$this->subscriptions_contract_id  = get_option( 'bsnp_subscriptions_contract_id' );
			$initial_fee                      = $this->customer_order->get_total() * $ex_rate;
			$this->bsnp_subscription_init_fee = round( $initial_fee, 2 );
		}
	}

	/**
	 * Return error message
	 * @return WP_Error
	 */
	public function refund_error() {
		$data   = sprintf( __( 'Refund failed, please contact merchant at "%s" for more details', 'woocommerce-bluesnap-powered-buy-platform-for-woocommerce' ), BSNP_REFUND_SUPPORT_EMAIL );
		$result = new WP_Error( 'refund failed', $data, array('status' => 'bsnp_gateway') );
		return $result;
	}


	/** Below are all of the Payloads objects */

	/**
	 * Create order object
	 *
	 * @param $customer_order
	 *
	 * @return array
	 */
	public function bsnp_create_payload( $customer_order ) {
		if ( ! $GLOBALS['FiSHa_BSNP_Validation']->validate_credit_card_details_encryption() ) {
			return array();
		}
		$customer_order->shipping_state = $GLOBALS['FiSHa_BSNP_Validation']->country_shipping_states_approved( $customer_order );
		$customer_order->billing_state  = $GLOBALS['FiSHa_BSNP_Validation']->country_billing_states_approved( $customer_order );
		$payload                        = array(
			'shopper' => array(
				'web-info'     => array(
					'ip' => $customer_order->customer_ip_address,
				),
				'shopper-info' => array(
					'shopper-currency'     => $this->purchase_currency,
					'charged-currency'     => $this->purchase_currency,
					'store-id'             => get_option( 'bsnp_store_id' ),
					'locale'               => $GLOBALS['FiSHa_BSNP_Functions']->bsnp_get_locale(),
					'shopper-contact-info' => array(
						'first-name' => $customer_order->billing_first_name,
						'last-name'  => $customer_order->billing_last_name,
						'email'      => $customer_order->billing_email,
						'state'      => $customer_order->billing_state,
						'country'    => $customer_order->billing_country,
					),
					'payment-info'         => array(
						'credit-cards-info' => array(
							'credit-card-info' => array(
								'billing-contact-info' => array(
									'first-name' => $customer_order->billing_first_name,
									'last-name'  => $customer_order->billing_last_name,
									'state'      => $customer_order->billing_state,
									'country'    => $customer_order->billing_country,
								),
								'credit-card'          => array(
									'encrypted-card-number'   => $_POST['encryptedCreditCard'],
									'encrypted-security-code' => $_POST['encryptedCvv'],
//                                    'card-type' => strtoupper( sanitize_text_field( $_POST['credit-card-type'] ) ),
									'expiration-month'        => $GLOBALS['FiSHa_BSNP_Functions']->is_digits( $_POST['wc_gateway_bluesnap_cc_exp_month'], true ),
									'expiration-year'         => $GLOBALS['FiSHa_BSNP_Functions']->is_digits( $_POST['wc_gateway_bluesnap_cc_exp_year'], true ),
								),
							),
						),
					),
				),
			),
			'order'   => array(
				'ordering-shopper'     => array(
					'fraud-info' => array( 'fraud-session-id' => $this->bsnp_fruad_session ),
					'web-info'   => array(
						'ip'          => $customer_order->customer_ip_address,
						'remote-host' => $_SERVER['REMOTE_ADDR'],
						'user-agent'  => $_SERVER['HTTP_USER_AGENT'],
					),
				),
				'cart'                 => array(
					'charged-currency' => $this->purchase_currency,
					'cart-item'        => array(
						'sku'           => array(
							'sku-id'           => $this->bsnp_get_product_sku( $this->bsnp_subscription_product ),
							'sku-charge-price' => array(
								'charge-type'      => 'initial',
								'amount'           => $this->purchase_ammount,
								'currency'         => $this->purchase_currency,
								'charged-currency' => $this->purchase_currency,
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
					'amount'           => $this->purchase_ammount,
					'currency'         => $this->purchase_currency,
					'charged-currency' => $this->purchase_currency,
				),
			),
		);

		return $payload;
	}


	/**
	 * Create order object
	 *
	 * @param $customer_order
	 *
	 * @return array
	 */
	public function bsnp_create_preorder_payload( $customer_order ) {
		if ( ! $GLOBALS['FiSHa_BSNP_Validation']->validate_credit_card_details_encryption() ) {
			return array();
		}
		$customer_order->shipping_state = $GLOBALS['FiSHa_BSNP_Validation']->country_shipping_states_approved( $customer_order );
		$customer_order->billing_state  = $GLOBALS['FiSHa_BSNP_Validation']->country_billing_states_approved( $customer_order );
		$payload                        = array(
			'step'            => 'CREATED',
			'web-info'        => array(
				'ip'          => $customer_order->customer_ip_address,
				'remote-host' => isset( $_SERVER['REMOTE_HOST'] ) ? $_SERVER['REMOTE_HOST'] : $_SERVER['REMOTE_ADDR'],
				'user-agent'  => $_SERVER['HTTP_USER_AGENT'],
			),
			'shopper-details' => array(
				'shopper' => array(
					'fraud-info'   => array( 'fraud-session-id' => $this->bsnp_fruad_session ),
					'shopper-info' => array(
						'shopper-contact-info'  => array(
							'first-name'   => $customer_order->billing_first_name,
							'last-name'    => $customer_order->billing_last_name,
							'email'        => $customer_order->billing_email,
							'company-name' => $customer_order->billing_company,
							'state'        => $customer_order->billing_state,
							'country'      => $customer_order->billing_country,
						),
						'invoice-contacts-info' => array(
							'invoice-contact-info' => array(
								'default'    => 'true',
								'first-name' => $customer_order->billing_first_name,
								'last-name'  => $customer_order->billing_last_name,
								'state'      => $customer_order->billing_state,
								'country'    => $customer_order->billing_country,
								'email'      => $customer_order->billing_email,
							),
						),
						'payment-info'          => array(
							'credit-cards-info' => array(
								'credit-card-info' => array(
									'billing-contact-info' => array(
										'first-name' => $customer_order->billing_first_name,
										'last-name'  => $customer_order->billing_last_name,
										'state'      => $customer_order->billing_state,
										'country'    => $customer_order->billing_country,
									),
									'credit-card'          => array(
										'encrypted-card-number'   => $_POST['encryptedCreditCard'],
										'encrypted-security-code' => $_POST['encryptedCvv'],
//                                        'card-type' => strtoupper( sanitize_text_field( $_POST['credit-card-type'] ) ),
										'expiration-month'        => $GLOBALS['FiSHa_BSNP_Functions']->is_digits( $_POST['wc_gateway_bluesnap_cc_exp_month'], true ),
										'expiration-year'         => $GLOBALS['FiSHa_BSNP_Functions']->is_digits( $_POST['wc_gateway_bluesnap_cc_exp_year'], true ),
									),
								),

							),

						),
						'store-id'              => get_option( 'bsnp_store_id' ),
						'shopper-currency'      => $this->purchase_currency,
						'locale'                => $GLOBALS['FiSHa_BSNP_Functions']->bsnp_get_locale(),
					),
				),
			),
			'order-details'   => array(
				'order' => array(
					'cart'                 => array(

						'cart-item' => array(
							'charged-currency' => $this->purchase_currency,
							'sku'              => array(
								'sku-id'           => $this->bsnp_get_product_sku( $this->bsnp_subscription_product ),
								'sku-charge-price' => array(
									'charge-type' => 'initial',
									'amount'      => ( $this->bsnp_is_subsacription ) ? $this->bsnp_subscription_init_fee : $this->purchase_ammount,
									'currency'    => $this->purchase_currency,
								),
							),
							'quantity'         => '1',
							'sku-parameter'    => array(
								'param-name'  => 'woo_order_id',
								'param-value' => $customer_order->id,
							),
						),
					),
					'expected-total-price' => array(
						'amount'   => ( $this->bsnp_is_subsacription ) ? $this->bsnp_subscription_init_fee : $this->purchase_ammount,
						'currency' => $this->purchase_currency,
					),
				),
			),
		);

		return $payload;
	}

	/**
	 * Create object for BlueSnap Shopper using previous credit card API
	 *
	 * @param $customer_order
	 *
	 * @return array
	 */
	public function bsnp_return_shopper_with_credit_card( $customer_order ) {
		$last4_digits = $_POST['credit-card-digit'];
		$bsnp_card_type = $this->get_bsnp_saved_card_type($this->bsnp_return_shopper_data_after_update, $last4_digits);
		$banp_card_details = isset( $_POST['reused_credit_card'] ) ? explode( ';', $_POST['reused_credit_card'] ) :
			array(
				$GLOBALS['FiSHa_BSNP_Functions']->is_digits( $last4_digits, true ),
				$bsnp_card_type,
		);

		$payload = array(
			'ordering-shopper'     => array(
				'fraud-info'       => array( 'fraud-session-id' => $this->bsnp_fruad_session ),
				'shopper-id'       => $GLOBALS['FiSHa_BSNP_Functions']->bsnp_get_wc_user_id(),
				'shopper-currency' => $this->purchase_currency,
				// added by yariv on 19.04.2015 in order to avoid exchange by bsnp
				'web-info'         => array(
					'ip'          => $customer_order->customer_ip_address,
					'remote-host' => $_SERVER['REMOTE_ADDR'],
					'user-agent'  => $_SERVER['HTTP_USER_AGENT'],
				),
				'credit-card'      => array(
					'card-last-four-digits' => $GLOBALS['FiSHa_BSNP_Functions']->is_digits( $banp_card_details[0], true ),
					'card-type'             => strtoupper( sanitize_text_field( $banp_card_details[1] ) ),
				),
			),
			'cart'                 => array(
				'cart-item' => array(
					'charged-currency' => $this->purchase_currency,
					'sku'              => array(
						'sku-id'           => $this->bsnp_get_product_sku( $this->bsnp_subscription_product ),
						'sku-charge-price' => array(
							'charge-type' => 'initial',
							'amount'      => $this->purchase_ammount,
							'currency'    => $this->purchase_currency,
						),
					),
					'quantity'         => '1',
					'sku-parameter'    => array(
						'param-name'  => 'woo_order_id',
						'param-value' => $customer_order->id,
					),
				),
			),
			'expected-total-price' => array(
				'amount'   => $this->purchase_ammount,
				'currency' => $this->purchase_currency,
			),
		);
		return $payload;
	}

	public function bsnp_return_subscription_payload( $customer_order ) {
		$last4_digits = $_POST['credit-card-digit'];
		$bsnp_card_type = $this->get_bsnp_saved_card_type($this->bsnp_return_shopper_data_after_update, $last4_digits);
		$banp_card_details = isset( $_POST['reused_credit_card'] ) ? explode( ';', $_POST['reused_credit_card'] ) : array(
			( $GLOBALS['FiSHa_BSNP_Functions']->is_digits( $last4_digits, true ) ),
			$bsnp_card_type,
		);
		$payload           = array(
			'web-info'      => array(
				'ip'          => $this->customer_order->customer_ip_address,
				'remote-host' => $_SERVER['REMOTE_ADDR'],
				'user-agent'  => $_SERVER['HTTP_USER_AGENT'],
			),
			'order-details' => array(
				'order' => array(
					'ordering-shopper'     => array(
						'fraud-info'  => array( 'fraud-session-id' => $this->bsnp_fruad_session ),
						'shopper-id'  => $GLOBALS['FiSHa_BSNP_Functions']->bsnp_get_wc_user_id(),
						'credit-card' => array(
							'card-last-four-digits' => $GLOBALS['FiSHa_BSNP_Functions']->is_digits( $banp_card_details[0], true ),
							'card-type'             => strtoupper( sanitize_text_field( $banp_card_details[1] ) ),
						),
					),
					'cart'                 => array(
						'cart-item' => array(
							'sku'           => array(
								'sku-id'           => $this->bsnp_get_product_sku( $this->bsnp_subscription_product ),
								'sku-charge-price' => array(
									'charge-type' => 'initial',
									'amount'      => $this->bsnp_subscription_init_fee,
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
						'amount'   => $this->bsnp_subscription_init_fee,
						'currency' => $this->purchase_currency,
					),
				),
			),

		);

		return $payload;
	}


	/**
	 * Create object for BlueSnap Shopper using previous credit card API
	 *
	 * @param $customer_order
	 *
	 * @return array
	 */
	public function bsnp_return_shopping_context_with_credit_card( $customer_order ) {
		$last4_digits = $_POST['credit-card-digit'];
		$bsnp_card_type = $this->get_bsnp_saved_card_type($this->bsnp_return_shopper_data_after_update, $last4_digits);
		$bsnp_card_details = isset( $_POST['reused_credit_card'] ) ? explode( ';', $_POST['reused_credit_card'] ) : array(
			$GLOBALS['FiSHa_BSNP_Functions']->is_digits( $last4_digits, true ),
			$bsnp_card_type,
		);
		$payload           = array(
			'web-info'      => array(
				'ip'          => $customer_order->customer_ip_address,
				'remote-host' => $_SERVER['REMOTE_ADDR'],
				'user-agent'  => $_SERVER['HTTP_USER_AGENT'],
			),
			'order-details' => array(
				'order' => array(
					'ordering-shopper'     => array(
						'fraud-info'  => array( 'fraud-session-id' => $this->bsnp_fruad_session ),
						'shopper-id'  => $GLOBALS['FiSHa_BSNP_Functions']->bsnp_get_wc_user_id(),
						'credit-card' => array(
							'card-last-four-digits' => $GLOBALS['FiSHa_BSNP_Functions']->is_digits( $bsnp_card_details[0], true ),
							'card-type'             => strtoupper( sanitize_text_field( $bsnp_card_details[1] ) ),
						),
					),
					'cart'                 => array(
						'cart-item' => array(
							'charged-currency' => $this->purchase_currency,
							'sku'              => array(
								'sku-id'           => $this->bsnp_get_product_sku( $this->bsnp_subscription_product ),
								'sku-charge-price' => array(
									'charge-type' => 'initial',
									'amount'      => $this->purchase_ammount,
									'currency'    => $this->purchase_currency,
								),
							),
							'quantity'         => '1',
							'sku-parameter'    => array(
								'param-name'  => 'woo_order_id',
								'param-value' => $customer_order->id,
							),
						),
					),
					'expected-total-price' => array(
						'amount'   => $this->purchase_ammount,
						'currency' => $this->purchase_currency,
					),
				),
			),
		);

		return $payload;
	}

	/**
	 * Create object for BlueSnap shopper update API
	 *
	 * @param $customer_order
	 *
	 * @return array
	 */
	public function bsnp_update_shopper( $customer_order ) {
		$customer_order->shipping_state = $GLOBALS['FiSHa_BSNP_Validation']->country_shipping_states_approved( $customer_order );
		$customer_order->billing_state  = $GLOBALS['FiSHa_BSNP_Validation']->country_billing_states_approved( $customer_order );
		$bsnp_card_info                 = null;
		if ( isset( $_POST['reused_credit_card'] ) ) {
			$bsnp_card_details = explode( ';', $_POST['reused_credit_card'] );
			$bsnp_card_info    = array(
				'card-last-four-digits' => $GLOBALS['FiSHa_BSNP_Functions']->is_digits( $bsnp_card_details[0], true ),
				'card-type'             => strtoupper( sanitize_text_field( $bsnp_card_details[1] ) ),
			);
		} else {
			if ( ! $GLOBALS['FiSHa_BSNP_Validation']->validate_credit_card_details_encryption() ) {
				return array();
			}
		}
		$payload = array(
			'web-info'     => array(
				'ip' => $customer_order->customer_ip_address,
			),
			'fraud-info'   => array( 'fraud-session-id' => $this->bsnp_fruad_session ),
			// placed here according to api docs
			'shopper-info' => array(
				'store-id'              => get_option( 'bsnp_store_id' ),
				'shopper-currency'      => $this->purchase_currency,
				'shopper-contact-info'  => array(
					'first-name' => $customer_order->billing_first_name,
					'last-name'  => $customer_order->billing_last_name,
					'email'      => $customer_order->billing_email,
					'state'      => $customer_order->billing_state,
					'country'    => $customer_order->billing_country,
				),
				'invoice-contacts-info' => array(
					'invoice-contact-info' => array(
						'default'    => 'true',
						'first-name' => $customer_order->billing_first_name,
						'last-name'  => $customer_order->billing_last_name,
						'state'      => $customer_order->billing_state,
						'country'    => $customer_order->billing_country,
						'email'      => $customer_order->billing_email,
					),
				),
				'payment-info'          => array(
					'credit-cards-info' => array(
						'credit-card-info' => array(
							'billing-contact-info' => array(
								'first-name' => $customer_order->billing_first_name,
								'last-name'  => $customer_order->billing_last_name,
								'state'      => $customer_order->billing_state,
								'country'    => $customer_order->billing_country,
							),
							'credit-card'          => array(
								'encrypted-card-number'   => $_POST['encryptedCreditCard'],
								'encrypted-security-code' => $_POST['encryptedCvv'],
//                                'card-type' => strtoupper( sanitize_text_field( $_POST['credit-card-type'] ) ),
								'expiration-month'        => $GLOBALS['FiSHa_BSNP_Functions']->is_digits( $_POST['wc_gateway_bluesnap_cc_exp_month'], true ),
								'expiration-year'         => $GLOBALS['FiSHa_BSNP_Functions']->is_digits( $_POST['wc_gateway_bluesnap_cc_exp_year'], true ),
							),
						),

					),
				),
			),
		);
		if ( "" == $_POST['credit-card-type'] and "" == $_POST['credit-card-digit'] ) {
			$payload['shopper-info']['payment-info']['credit-cards-info']['credit-card-info']['credit-card'] = $bsnp_card_info;

		}

		return $payload;
	}


	/**
	 * Update paymet on a preorder order
	 *
	 * @param $customer_order
	 *
	 * @return array
	 */
	public function bsnp_update_subscription_payload( $customer_order ) {
		$payload = array(
			'step'          => 'PLACED',
			'web-info'      => array(
				'ip'          => $customer_order->customer_ip_address,
				'remote-host' => isset( $_SERVER['REMOTE_HOST'] ) ? $_SERVER['REMOTE_HOST'] : $_SERVER['REMOTE_ADDR'],
				'user-agent'  => $_SERVER['HTTP_USER_AGENT'],
			),
			'order-details' => array(
				'order' => array(
					'ordering-shopper'     => array(
						'fraud-info' => array( 'fraud-session-id' => $this->bsnp_fruad_session ),
						'shopper-id' => $this->bsnp_shopper_id,
					),
					'cart'                 => array(
						'cart-item' => array(
							'sku'           => array(
								'sku-id'           => $this->bsnp_get_product_sku( $this->bsnp_subscription_product ),
								'sku-charge-price' => array(
									'charge-type' => 'initial',
									'amount'      => $this->bsnp_subscription_init_fee,
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
						'amount'   => $this->bsnp_subscription_init_fee,
						'currency' => $this->purchase_currency,
					),
				),
			),

		);

		return $payload;
	}


	/**
	 * Update payment on a subscriptions order
	 *
	 * @param $customer_order
	 *
	 * @return array
	 */
	public function bsnp_update_shopping_context( $customer_order ) {
		$payload = array(
			'step'          => 'PLACED',
			'web-info'      => array(
				'ip'          => $customer_order->customer_ip_address,
				'remote-host' => isset( $_SERVER['REMOTE_HOST'] ) ? $_SERVER['REMOTE_HOST'] : $_SERVER['REMOTE_ADDR'],
				'user-agent'  => $_SERVER['HTTP_USER_AGENT'],
			),
			'order-details' => array(
				'order' => array(
					'ordering-shopper'     => array(
						'fraud-info' => array( 'fraud-session-id' => $this->bsnp_fruad_session ),
						'shopper-id' => $GLOBALS['FiSHa_BSNP_Api']->bsnp_bluesnap_shopper_id( $GLOBALS['FiSHa_BSNP_Functions']->bsnp_get_order_id( $customer_order->id ) )
					),
					'cart'                 => array(
						'cart-item' => array(
							'sku'           => array(
								'sku-id'           => $this->bsnp_get_product_sku( $this->bsnp_subscription_product ),
								'sku-charge-price' => array(
									'charge-type' => 'initial',
									'amount'      => $customer_order->order_total,
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
						'amount'   => $customer_order->order_total,
						'currency' => $this->purchase_currency,
					),
				),
			),

		);

		return $payload;
	}

	private function get_bsnp_saved_card_type($array, $last4_digits)
	{
		static $card_type;
		foreach( $array as $k => $v) {
			if(is_array($v)) {
				$this->get_bsnp_saved_card_type($v, $last4_digits);
			} else {
				if( 'card-last-four-digits' == $k &&
					$v == $last4_digits ) {
					$card_type = $array['card-type'];
				}
			}
		}
		return $card_type;
	}


} // End of class