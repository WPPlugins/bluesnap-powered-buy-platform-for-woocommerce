<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Created by PhpStorm.
 * User: fisha
 * Date: 8/16/15
 * Time: 5:30 PM
 */
class Fisha_Bsnp_Http {

	public $bsnp_response_body;
	public $global_response_body;
	public $header;
	public $http_code;
	public $global_response_code;
	public $update_error = false;
	private $secure_log;
	private $log_forbidden_keys = array(
		'encrypted-card-number',
		'encrypted-security-code'
	); // You can add here list of key that will be encrypted in logs


	/**
	 * Remove confidential data from strings before logging
	 *
	 * @param $xml_data
	 */
	function remove_encrypted_strings( &$xml_data ) {
		if ( ! $xml_data ) {
			return;
		}
		foreach ( $xml_data as $datum_key => &$datum ) {
			if ( is_array( $datum ) ) {
				$this->remove_encrypted_strings( $datum );
			} else {
				if ( in_array( $datum_key, $this->log_forbidden_keys ) ) {
					$xml_data[ $datum_key ] = "**************************************";
				}
			}
		}
		$this->secure_log = json_encode( $xml_data );
	}

	/**
	 * Send POST http request vis cUrl
	 *
	 * @param $url
	 * @param $bsnp_xml
	 * @return bool
	 * @throws Exception
	 */
	public function bsnp_send_curl( $url, $bsnp_xml ) {
		$args     = array(
			'headers' => $this->set_headers(),
			'timeout' => 45,
			'body'    => $bsnp_xml,
		);
		$response = wp_remote_post( $url, $args );
		if ( is_wp_error( $response ) ) {
			throw new Exception( $response->get_error_message() );
		}
		$this->bsnp_response_body = $response['body'];
		$this->http_code          = $response['response']['code'];
		$xml_data                 = json_decode( json_encode( simplexml_load_string( $bsnp_xml ) ), true );
		$this->remove_encrypted_strings( $xml_data );
		$GLOBALS['FiSHa_BSNP_Logger']->logger( 'WS/URL: "' . $url . '" (Using method POST)', 'Sending request with the following XML: ' . $this->secure_log );
		$this->global_response_body = $this->bsnp_response_body;
		$this->header               = isset($response['headers']['location'])? $response['headers']['location'] : false;
		if ( ! preg_match( '/^2\d{2}$/', $this->http_code ) ) {
			$GLOBALS['FiSHa_BSNP_Logger']->logger( 'WS/URL: "' . $url . '" (Using method POST)', 'Request returned an error, with the following details: ' . json_encode( simplexml_load_string( $this->global_response_body ) ) );
			return false;
		}
		$GLOBALS['FiSHa_BSNP_Logger']->logger( 'WS/URL: "' . $url . '" (Using method POST)', 'Response O.K with the following data: ' . json_encode( simplexml_load_string( $this->global_response_body ) ) );

		return true;
	}


	/**
	 * Send POST http request vis cUrl
	 *
	 * @param $url
	 * @param $bsnp_xml
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function bsnp_put_curl( $url, $bsnp_xml ) {
		$args = array(
			'method'  => 'PUT',
			'headers' => $this->set_headers(),
			'timeout' => 45,
			'body'    => $bsnp_xml,
		);

		$response = wp_remote_post( $url, $args );
		if ( is_wp_error( $response ) ) {
			throw new Exception( $response->get_error_message() );
		}
		$this->bsnp_response_body   = $response['body'];
		$this->http_code            = $response['response']['code'];
		$this->global_response_body = $this->bsnp_response_body;
		$this->global_response_code = $this->http_code;

		$xml_data = json_decode( json_encode( simplexml_load_string( $bsnp_xml ) ), true );
		$this->remove_encrypted_strings( $xml_data );

		$GLOBALS['FiSHa_BSNP_Logger']->logger( 'WS/URL: "' . $url . '" (Using method PUT)', 'Sending request with the following XML: ' . $this->secure_log );
		if ( ! preg_match( '/^2\d{2}$/', $this->http_code ) ) {
			$GLOBALS['FiSHa_BSNP_Logger']->logger( 'WS/URL: "' . $url . '" (Using method PUT)', 'Request returned an error, with the following details: ' . json_encode( @simplexml_load_string( $this->global_response_body ) ) );
			$this->update_error = true;

			return false;
		}
		$GLOBALS['FiSHa_BSNP_Logger']->logger( 'WS/URL: "' . $url . '" (Using method PUT)', 'Request result O.K, with the following data: ' . json_encode( @simplexml_load_string( $this->global_response_body ) ) );

		return true;
	}

	/**
	 * Send a GET http request via cUrl
	 *
	 * @param $url
	 * @param $do_not_show_logs
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function bsnp_get_curl( $url, $do_not_show_logs = false ) {
		if ( ! $do_not_show_logs ) {
			$GLOBALS['FiSHa_BSNP_Logger']->logger( 'WS/URL: "' . $url . '" (Using method GET)', 'Attempting to get data from url: ' . $url );
		}

		$args = array(
			'headers' => $this->set_headers(),
			'timeout' => 45,
		);
		$bsnp_response_body = wp_remote_get( $url, $args );
		if ( is_wp_error( $bsnp_response_body ) ) {
				$GLOBALS['FiSHa_BSNP_Logger']->logger( 'WS/URL: "' . $url . '" (Using method GET)', 'Response error, with the following data: ' . json_encode($bsnp_response_body));
				throw new Exception( $bsnp_response_body->get_error_message() );
		}
		if ( ! $do_not_show_logs ) {
			if ( !is_object( $bsnp_response_body['body'] ) ) {
				$err_message = json_encode( simplexml_load_string( $bsnp_response_body['body'] ) );
			} else {
				$err_message = $bsnp_response_body['body'];
			}
			$GLOBALS['FiSHa_BSNP_Logger']->logger( 'WS/URL: "' . $url . '" (Using method GET)', 'Response O.K, The following data was received: ' . $err_message );
		}

		return $bsnp_response_body['body'];
	}

	/**
	 * Set headers for methods authentication
	 * @return array
	 */
	private function set_headers() {
		$bsnp_api_login    = get_option( 'bsnp_api_login' );
		$bsnp_api_password = get_option( 'bsnp_password' );
		$bsnp_auth_token   = base64_encode( $bsnp_api_login . ':' . $bsnp_api_password );

		return array( 'Content-Type' => 'application/xml', 'Authorization' => 'Basic ' . $bsnp_auth_token );
	}

	/**
	 * Extract Order ID from Headers
	 *
	 * @param string $header
	 *
	 * @return string
	 */
	public function bsnp_get_order_id_from_header( $header ) {
//        $order_id = 0;
//        foreach ($headers as $header) {
//            if (strpos($header, 'Location') !== false) {
//                $order_id = strrchr($header, '/');
//                break;
//            }
//        }
//        return $order_id;
		return strrchr( $header, '/' );
	}

}

$GLOBALS['FiSHa_BSNP_Http'] = new Fisha_Bsnp_Http();