<?php

/**
 * Created by PhpStorm.
 * User: fisha
 * Date: 8/9/15
 * Time: 11:18 AM
 */

if (!defined('ABSPATH')) {
    exit();
}

class Fisha_Bsnp_Db
{

    private $csvFilesDir = BLUESNAP_BASE_DIR;


//    /**
//     * Load csv file into given table
//     * @param $table_name
//     * @param $csvFilename
//     */
//    public function load_csv_into_table( $table_name, $csvFilename ) {
//        global $wpdb;
//        $filename = $this->csvFilesDir."csv/". $csvFilename;
//        $filename = str_replace("\\", "/", $filename);
//        $sql2 = $wpdb->prepare("LOAD DATA INFILE %s REPLACE INTO TABLE $table_name
//                                FIELDS TERMINATED BY ','
//                                ENCLOSED BY '\"'
//                                LINES TERMINATED BY '\\n'
//                                IGNORE 1 LINES", $filename ) ;
//        $wpdb->query( $sql2 );
//        // Some MySQL version require the addition of the "LOCAL" attribute into the query
//        if($wpdb->last_error !=""){
//            $sql2 = $wpdb->prepare("LOAD DATA LOCAL INFILE %s REPLACE INTO TABLE $table_name
//                                FIELDS TERMINATED BY ','
//                                ENCLOSED BY '\"'
//                                LINES TERMINATED BY '\\n'
//                                IGNORE 1 LINES", $filename ) ;
//            $wpdb->query( $sql2 );
//        }
//    }

    /**
     * load CSV data into table row by row
     *
     * @param $table_name
     * @param $csv_file_name
     */
    public function load_csv_line_by_line($table_name, $csv_file_name)
    {
        $row = 1;
        $filename = $this->csvFilesDir . "csv/" . $csv_file_name;
        $filename = str_replace("\\", "/", $filename);
        if (($handle = fopen($filename, "r")) !== false) {
            while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                if ($row > 1) {
                    $this->insert_row_into_table($table_name, $data);
                }
                $row++;
            }
            fclose($handle);
        }
    }

    /**
     * Given BlueSnap invoice id, return WC order id.
     * @return string
     */
    function bsnp_get_wc_order_id($bsnp_invoice_id)
    {
        global $wpdb;
        //$bsnp_order_conversion = $GLOBALS['FiSHa_BSNP_Globals']->bsnp_get_order_converter_table_name();
        $table_name = $wpdb->prefix . 'postmeta';
        $sql = $wpdb->prepare("SELECT post_id FROM $table_name WHERE meta_key ='_bluesnap_invoice_id' AND meta_value = %s", $bsnp_invoice_id);
        $result = $wpdb->get_results($sql);
        if (!empty($result)) {
            return $result['0']->post_id;
        }

        return '0';
//        global $wpdb;
//        $bsnp_order_conversion = $GLOBALS['FiSHa_BSNP_Globals']->bsnp_get_order_converter_table_name();
//        $sql2 = $wpdb->prepare( "SELECT wc_order_id FROM {$bsnp_order_conversion} WHERE bluesnap_invoice_id = %s", $bsnp_invoice_id );
//        $result = $wpdb->get_results( $sql2 );
//        if ( !empty( $result ) ) {
//            return $result['0']->wc_order_id;
//        }
//        return '0';
    }


    /**
     * Given WC order id, return BSNP invoice id
     * @return string
     */
    function bsnp_get_invoice_id($wc_order_id)
    {
//        global $wpdb;
//        $bsnp_order_conversion = $GLOBALS['FiSHa_BSNP_Globals']->bsnp_get_order_converter_table_name();
//        $sql2 = $wpdb->prepare( "SELECT bluesnap_invoice_id FROM {$bsnp_order_conversion} WHERE  wc_order_id = %s", $wc_order_id );
//        $result = $wpdb->get_results( $sql2 );
//        if ( !empty( $result ) ) {
//            return $result['0']->bluesnap_invoice_id;
//        }
//        return '0';
        $bsnp_invoice_id = get_post_meta($wc_order_id, '_bluesnap_invoice_id');
        if (isset($bsnp_invoice_id[0])) {
            return $bsnp_invoice_id[0];
        } else {
            return '0';
        }

    }


    /**
     * Update Order status.
     * If order was cancelled prior to IPN sent, do not update status from IPN
     *
     * @param $wc_order_id
     * @param $status
     * @param $note
     */
    function bsnp_update_order($wc_order_id, $status, $note)
    {
        $customer_order = wc_get_order($wc_order_id);
        switch ($customer_order->get_status()) {
            case 'cancelled':
                $GLOBALS['FiSHa_BSNP_Logger']->logger('Updating order status via IPN ', 'Failed to update order status. order ' . $wc_order_id . ' was already in status "cancelled".');
                return;
            case 'completed':
                if ('refunded' != $status) { //Allow charge back of completed order
                    return;
                }
                break;
            case 'pending':
                if ('processing' == $status) { //This is a CHARGE IPN
                    $status = 'completed';
                    $product_list = $customer_order->get_items();
                    foreach ($product_list as $product) {
                        $line_item = wc_get_product($product['product_id']);
                        //If at least one product is not both downalodable and virtual then status should be processing and not completed.
                        if (!$line_item->is_virtual() && !$line_item->is_downloadable()) {
                            $status = 'processing';
                        }
                    }
                }
                break;
        }
        $GLOBALS['FiSHa_BSNP_Logger']->logger('Updating order status via IPN ', 'New order status: ' . $status . ' Order note: ' . $note);
        $customer_order->update_status($status, $note);
    }

    /**
     * Return content of a column from given table
     *
     * @param $column
     * @param $table_name
     * @param $parent_process (will use for db lookup)
     * @param null $where_condition
     *
     * @return mixed|null
     */
    public function get_column_data($column, $table_name, $parent_process, $where_condition = null)
    {
        global $wpdb;
        $sql = "SELECT {$column} FROM {$table_name}";
        if ($where_condition != '') {
            $keys = array_keys($where_condition);
            $values = array_values($where_condition);
            $sql .= " WHERE " . $keys[0] . " = '" . $values[0] . "'";
        }
        try {
            $result = $wpdb->get_results($sql);
        } catch (Exception $e) {
            $GLOBALS['FiSHa_BSNP_Logger']->logger($parent_process, 'The following error has occurred while trying to fetch data from Db: ' . $e->getMessage());
        }
        if (!empty($result)) {
            return $result;
        }

        return null;
    }


    /**
     * Return content of a column from given table
     *
     * @param $column
     * @param $table_name
     * @param $parent_process (will use for db lookup)
     * @param null $where_condition
     *
     * @return mixed|null
     */
    public function get_multi_column_data($column, $table_name, $parent_process, $where_condition = null)
    {

        global $wpdb;

        $columns = '';

        foreach ($column as $col) {

            $columns .= $col . ', ';

        }

        $columns = rtrim($columns, ', ');

        $sql = "SELECT $columns FROM {$table_name}";

        if ($where_condition != '') {

            $keys = array_keys($where_condition);

            $values = array_values($where_condition);

            $sql .= " WHERE '" . $keys[0] . "' = '" . $values[0] . "'";

        }

        try {

            $result = $wpdb->get_results($sql);

        } catch (Exception $e) {

            $GLOBALS['FiSHa_BSNP_Logger']->logger($parent_process, 'The following error has occurred while trying to fetch data from Db: ' . $e->getMessage());

        }


        if (!empty($result)) {

            return $result;

        }

        return null;
    }


    /**
     * Update db table with new exchange rates
     *
     * @param $table_name
     * @param $columns
     * @param $data
     * @param $where_condition
     * @param $parent_process
     *
     * @internal param $currency_code
     * @internal param $bsnp_ex_rate
     */
    function update_table($table_name, $columns, $data, $where_condition, $parent_process, $save_logs = true)
    {

        global $wpdb;

        try {

            if (count($columns) != count($data)) {

                return;

            }

            $update_data = array();

            foreach ($columns as $key => $column_name) {

                $update_data[$column_name] = $data[$key];

            }

            $wpdb->update(

                $table_name,

                $update_data,

                $where_condition

            );

            if ($save_logs) {

                $GLOBALS['FiSHa_BSNP_Logger']->logger($parent_process, 'Added the following data' . json_encode($data) . ' into table ' . $table_name);

            }

        } catch (Exception $e) {

            $GLOBALS['FiSHa_BSNP_Logger']->logger($parent_process, 'The following error has occurred while trying to update table ' . $table_name . ': ' . $e->getMessage());

        }
    }


    /**
     * Save bsnp shopper id for returning shoppers followup
     *
     * @param $bsnp_shopper_id
     * @param $wc_shopper_id
     */
    public function bsnp_create_return_shopper($bsnp_shopper_id, $wc_shopper_id)
    {
        if (0 == $wc_shopper_id) {
            return;
        }
        $GLOBALS['FiSHa_BSNP_Logger']->logger('Attempt to create returning shopper', 'WC Shopper id: ' . $wc_shopper_id . ' ,BlueSnap shopper id: ' . $bsnp_shopper_id);
        $wp_shipper_id = $GLOBALS['FiSHa_BSNP_Functions']->is_digits($wc_shopper_id, true);
        $bsnp_shopper_id = $GLOBALS['FiSHa_BSNP_Functions']->is_digits($bsnp_shopper_id, true);
        add_user_meta($wp_shipper_id, '_bsnp_shopper_id', $bsnp_shopper_id, true);
    }


    /**
     * Given WC Order id return subscription id (if applicable)
     * In case no subscription id was found return 0 (this should never happen).
     *
     * @param $order_id
     *
     * @return string
     */
    public function get_bsnp_subscription_id($order_id)
    {
        $result = get_post_meta($order_id, '_bluesnap_subscription_id', true);
//		if ( isset( $result[0] ) ) {
        if (!empty($result)) {
            return $result;
        } else {
            return '0';
        }
    }

    /**
     * Given WC Order id return bluesnap shopper id for subscription (if applicable)
     * In case no shopper id was found return 0 (this should never happen).
     *
     * @param $order_id
     *
     * @return string
     */
    public function get_subscription_bluesnap_shopper_id($order_id)
    {
        $result = get_post_meta($order_id, '_bluesnap_shopper_id', true);
//		if ( isset( $result[0] ) ) {
        if (!empty($result)) {
            return $result;
        } else {
            return '0';
        }

    }

    /**
     * Return currency used for purchase of subscription
     *
     * @param $wc_order_id
     *
     * @return string
     */
    public function get_subscription_currency($wc_order_id)
    {
        $result = get_post_meta($wc_order_id, '_charged_currency', true);
        //if ( isset( $result[0] ) ) {
        if (!empty($result)) {
            return $result;
        } else {
            return get_woocommerce_currency();
        }
    }

    /**
     * Given order id, return order details
     *
     * @param $order_id
     *
     * @return mixed|string
     */
    public function bsnp_get_order_details($order_id)
    {
        $order_details = new stdClass();
        $order_details->wc_order_id = $order_id;
        $order_details->wc_shopper_id = get_post_meta($order_id, '_wc_shopper_id', true);
        $order_details->bluesnap_order_id = get_post_meta($order_id, '_bluesnap_order_id', true);
        $order_details->bluesnap_shopper_id = get_post_meta($order_id, '_bluesnap_shopper_id', true);
        $order_details->bluesnap_subscription_id = get_post_meta($order_id, '_bluesnap_subscription_id', true);
        $order_details->charged_currency = get_post_meta($order_id, '_charged_currency', true);
        $order_details->bluesnap_ex_rate = get_post_meta($order_id, '_bsnp_ex_rate', true);
        $order_details->bluesnap_invoice_id = get_post_meta($order_id, '_bluesnap_invoice_id', true);
        $order_details->card_type = get_post_meta($order_id, '_card_type', true);
        $order_details->card_last_4_digit = get_post_meta($order_id, '_card_last_4_digit', true);
        if (is_null($order_details->wc_shopper_id)) {
            return '0';
        }

        return $order_details;

    }

    /**
     * Insert data into table row
     *
     * @param $table_name
     * @param $data
     */
    private function insert_row_into_table($table_name, $data)
    {
        global $wpdb;
        switch ($table_name) {
            case $GLOBALS['FiSHa_BSNP_Globals']->bsnp_get_currency_table_name():
                $wpdb->insert(
                    $GLOBALS['FiSHa_BSNP_Globals']->bsnp_get_currency_table_name(),
                    array(
                        'id' => $data[0],
                        'currency_code' => $data[1],
                        'country' => $data[2],
                        'is_supported' => $data[3],
                        'bluesnap_ex_rate' => $data[4],
                        'last_update' => $data[5]
                    ));
                break;
        }
    }

} //end of class Fisha_Db


$GLOBALS['FiSHa_BSNP_Db'] = new Fisha_Bsnp_Db();