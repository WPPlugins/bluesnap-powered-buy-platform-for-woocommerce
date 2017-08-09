<?php
/**
 * Created by PhpStorm.
 * User: fisha
 * Date: 19/07/2016
 * Time: 9:31 AM
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit();
}

class Fisha_Update_Orders
{
    /**
     * If Updated from older version,
     * get list of all past orders
     * @return array|null|object
     */
    private function get_all_orders(){
      global $wpdb;
      $table_name = $wpdb->prefix . 'bluesnap_order_data';
      return $this->getTableData($wpdb, $table_name);
  }

    /**
     * If Updated from older version,
     * get list of all past users
     * @return array|null|object
     */
    private function get_all_users(){
        global $wpdb;
        $table_name = $wpdb->prefix . 'bluesnap_store_shopper_converter';
        return $this->getTableData($wpdb, $table_name);
    }


    /**
     * Update each order from the old architecture
     * to the new one
     */
    public function update_orders_to_new_architecture(){
        global $wpdb;
        $old_orders = $this->get_all_orders();
        if(empty($old_orders)){
            return; // If old orders do not exist, no update is due
        }
        foreach($old_orders as $order ){
            $this->update_order($order);
        }
        $table_name = $wpdb->prefix. 'postmeta';
        $sql = $wpdb->prepare("UPDATE {$table_name} SET `meta_value` = 'wc_gateway_bluesnap_cc' WHERE `meta_value` = 'Bluesnap_Cc'");
        $wpdb->get_results($sql);
    }

    /**
     * Update each users from the old architecture
     * to the new one
     */
    public function update_users_to_new_architecture(){
        $old_users = $this->get_all_users();
        if(empty($old_users)){
            return; // If old orders do not exist, no update is due
        }
        foreach($old_users as $user ){
            $this->update_user($user);
        }
    }


    /**
     * Move date from orders table into order's post meta
     * @param stdClass $order
     */
    private function update_order(stdClass $order)
    {
        $wc_order_id = $order->wc_order_id;
        if (!empty($order->wc_shopper_id)) {
            update_post_meta($wc_order_id, '_wc_shopper_id', $order->wc_shopper_id);
        }
        if (!empty($order->bluesnap_order_id)) {
            update_post_meta($wc_order_id, '_bluesnap_order_id', $order->bluesnap_order_id);
        }
        if (!empty($order->bluesnap_shopper_id)) {
            update_post_meta($wc_order_id, '_bluesnap_shopper_id', $order->bluesnap_shopper_id);
        }
        if (!empty($order->charged_currency)) {
            update_post_meta($wc_order_id, '_charged_currency', $order->charged_currency);
        }
        if (!empty($order->bluesnap_ex_rate)) {
            update_post_meta($wc_order_id, '_bsnp_ex_rate', $order->bluesnap_ex_rate);
        }
        if (!empty($order->bluesnap_invoice_id)) {
            update_post_meta($wc_order_id, '_bluesnap_invoice_id', $order->bluesnap_invoice_id);
        }
        if (!empty($order->card_type)) {
            update_post_meta($wc_order_id, '_card_type', $order->card_type);
        }
        if (!empty($order->card_last_4_digit)) {
            update_post_meta($wc_order_id, '_card_last_4_digit', $order->card_last_4_digit);
        }
        if (!empty($order->bluesnap_subscription_id)) {
            update_post_meta($wc_order_id, '_bluesnap_subscription_id', $order->bluesnap_subscription_id);
        }
        update_post_meta($wc_order_id, '_payment_method', 'wc_gateway_bluesnap_cc');
    }

    /**
     * Move date from userss table into users's post meta
     * @param stdClass $user
     */
    private function update_user(stdClass $user){
        $user_id = $user->wc_shopper_id;
        update_user_meta($user_id, '_bsnp_shopper_id', $user->bsnp_shopper_id );
        update_user_meta($user_id, '_bsnp_approved_shopper', $user->bsnp_approved_shopper);
    }

    /**
     * @param $wpdb
     * @param $table_name
     * @return mixed
     */
    private function getTableData($wpdb, $table_name)
    {
        $sql = $wpdb->prepare("SELECT * FROM $table_name");
        $order_list = $wpdb->get_results($sql);
        return $order_list;
    }
}

$GLOBALS['FiSHa_BSNP_UPDATE'] = new Fisha_Update_Orders();