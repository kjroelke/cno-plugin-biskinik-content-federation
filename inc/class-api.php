<?php
/**
 * API class to handle data fetching
 *
 * @package ChoctawNation
 * @subpackage BiskinikContentFederation
 */

namespace ChoctawNation\BiskinikContentFederation;

use Exception;

/**
 * API class to handle data fetching
 */
class API {
	/**
	 * The base path for the API
	 *
	 * @var string $remote_path
	 */

	/**
	 * The remote path for the API to make fetches to
	 *
	 * @var string $remote_path
	 */
	private string $remote_path;

	/**
	 * The API key for the Nation site
	 *
	 * @var string $api_key
	 */
	private string|false $api_key;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->api_key     = get_option( 'cno_biskinik_federated_content' );
		$this->remote_path = 'https://www.choctawnation.com/wp-json/wp/v2';
	}

	/**
	 * Checks the API key to see if it exists
	 */
	private function api_key_exists(): bool {
		return is_string( $this->api_key ) && ! empty( $this->api_key );
	}

	/**
	 * Returns the headers for the API request
	 *
	 * @param array $additional_params Additional parameters to add to the header
	 * @return array The auth header
	 * @throws Exception If the API key is not set.
	 */
	private function get_auth_header( array $additional_params = array() ): array {
		if ( ! $this->api_key_exists() ) {
			throw new Exception( 'API key not set' );
		}
		return array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( "cnodigital:{$this->api_key}" ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			),
			...$additional_params,
		);
	}

	/**
	 * Grabs the ID of the term from the Nation site
	 *
	 * @param string $taxonomy The taxonomy to search
	 * @param string $title    The title of the term to search for
	 * @return int|false The ID of the term, or false on failure
	 */
	public function fetch_term_id( $taxonomy, $title ): int|false {
		try {
			$response = wp_remote_get(
				"{$this->remote_path}/{$taxonomy}/?search=" . sanitize_title( $title ) . '&_fields=id',
				array( $this->get_auth_header() )
			);
			if ( is_wp_error( $response ) ) {
				return false;
			}
			$body = wp_remote_retrieve_body( $response );
			if ( is_wp_error( $body ) ) {
				return false;
			}
			$body = json_decode( $body );
			return $body[0]->id ?? false;
		} catch ( Exception $e ) {
			error_log( 'Error fetching term ID: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Grabs the latest post from the Nation site
	 *
	 * @param string|int $term_id The term ID to search for
	 * @param string     $taxonomy The taxonomy to search
	 * @return object|false The latest post, or false on failure
	 */
	public function fetch_latest_post( string|int $term_id, string $taxonomy ) {
		$response = wp_remote_get( "{$this->remote_path}/{$taxonomy}?{$taxonomy}={$term_id}&order=desc&orderby=date&_fields=id,title,link,status,date&per_page=1" );
		if ( is_wp_error( $response ) ) {
			return false;
		}
		$body = wp_remote_retrieve_body( $response );
		if ( is_wp_error( $body ) ) {
			return false;
		}
		return json_decode( $body );
	}
}
