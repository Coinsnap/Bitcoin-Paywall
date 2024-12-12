<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Coinsnap_Bitcoin_Paywall_BTCPayHandler {
	private $store_id;
	private $api_key;
	private $url;

	public function __construct( $store_id, $api_key, $url ) {
		$this->store_id = $store_id;
		$this->api_key  = $api_key;
		$this->url      = rtrim( $url, '/' );
	}

	public function createInvoice( $amount, $currency, $redirectUrl ) {
		$data = wp_json_encode( [
			'amount'   => $amount,
			'currency' => $currency,
			'checkout' => [
				'redirectUrl'           => $redirectUrl,
				'redirectAutomatically' => true,
			]
		] );

		$response = wp_remote_post( "{$this->url}/api/v1/stores/" . $this->store_id . "/invoices", [
			'method'  => 'POST',
			'headers' => [
				'Authorization' => 'token ' . $this->api_key,
				'Content-Type'  => 'application/json'
			],
			'body'    => $data,
			'timeout' => 60,
		] );

		// Enhanced error handling
		if ( is_wp_error( $response ) ) {
                    //  Debug Invoice Creation
                    //error_log( 'BTCPay Invoice Creation Error: ' . $response->get_error_message() );

			return [
				'success' => false,
				'error'   => $response->get_error_message()
			];
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body          = wp_remote_retrieve_body( $response );

		// Check HTTP status code
		if ( $response_code !== 200 ) {
                    //  Debug Invoice Creation HTTP
                    //error_log( 'BTCPay Invoice Creation HTTP Error: ' . $response_code . ' - ' . $body );

			return [
				'success' => false,
				'error'   => "HTTP {$response_code}",
				'body'    => $body
			];
		}

		$decoded_body = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
                    //  Debug Invoice Creation JSON
                    //error_log( 'BTCPay Invoice Creation JSON Decode Error: ' . json_last_error_msg() );

			return [
				'success'  => false,
				'error'    => 'JSON Decode Error',
				'raw_body' => $body
			];
		}

		return [
			'success' => true,
			'data'    => $decoded_body
		];
	}

	public function getInvoiceStatus( $invoice_id ) {
		$url = $this->url . '/api/v1/stores/' . $this->store_id . '/invoices/' . $invoice_id;

		// Set the request headers
		$headers = [
			'Authorization' => 'token ' . $this->api_key,
			'Content-Type'  => 'application/json'
		];

		// Make the GET request to BTCPay API
		$response = wp_remote_get( $url, [
			'headers' => $headers,
		] );

		// Check if the response is valid
		if ( is_wp_error( $response ) ) {
			return null;
		}

		// Decode the response
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		wp_send_json_success( $data );

		// Check if the data is valid and return invoice status
		if ( isset( $data['status'] ) ) {
			return $data;
		}
	}

	/**
	 * Test connection to BTCPay API
	 * @return array
	 */
	public function testConnection() {
		try {
			$response = wp_remote_get( "{$this->url}/api/v1/stores/" . $this->store_id, [
				'headers' => [
					'Authorization' => 'token ' . $this->api_key,
					'Content-Type'  => 'application/json'
				],
			] );

			// Check for WP errors
			if ( is_wp_error( $response ) ) {
				return [
					'success' => false,
					'message' => 'Connection failed: ' . $response->get_error_message()
				];
			}

			// Check response code
			$response_code = wp_remote_retrieve_response_code( $response );
			if ( $response_code !== 200 ) {
				return [
					'success' => false,
					'message' => "Connection failed. HTTP Error: {$response_code}"
				];
			}

			// If we get here, connection is successful
			return [
				'success' => true,
				'message' => 'Connection to BTCPay successful!'
			];

		} catch ( Exception $e ) {
			return [
				'success' => false,
				'message' => 'Connection error: ' . $e->getMessage()
			];
		}
	}
}
