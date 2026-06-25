<?php
/**
 * Discogs Importer API Client Class.
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Discogs_Importer_API {

	/**
	 * Discogs API Base URL.
	 */
	private static $api_base = 'https://api.discogs.com/';

	/**
	 * Retrieve saved Personal Access Token.
	 *
	 * @return string
	 */
	private static function get_token() {
		return get_option( 'discogs_importer_token', '' );
	}

	/**
	 * Build HTTP request headers.
	 *
	 * @return array
	 */
	private static function get_headers() {
		$token = self::get_token();
		$headers = array(
			'User-Agent' => 'DiscogsRecordImporterWordPressPlugin/' . DISCOGS_IMPORTER_VERSION . ' +http://localhost',
		);

		if ( ! empty( $token ) ) {
			$headers['Authorization'] = 'Discogs token=' . trim( $token );
		}

		return $headers;
	}

	/**
	 * Perform a GET request to the Discogs API.
	 *
	 * @param string $endpoint The endpoint relative to the base URL.
	 * @param array  $query    Query parameters.
	 * @return array|WP_Error  Decoded response body or WP_Error.
	 */
	private static function make_request( $endpoint, $query = array() ) {
		$url = self::$api_base . ltrim( $endpoint, '/' );
		
		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		$args = array(
			'headers'    => self::get_headers(),
			'timeout'    => 15,
			'sslverify'  => true,
		);

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body          = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			$message = __( 'Unknown Discogs API error.', 'discogs-importer' );
			if ( ! empty( $body ) ) {
				$decoded = json_decode( $body, true );
				if ( isset( $decoded['message'] ) ) {
					$message = $decoded['message'];
				}
			}
			
			if ( 401 === $response_code ) {
				$message = __( 'Unauthorized. Please check your Discogs API Token.', 'discogs-importer' );
			} elseif ( 429 === $response_code ) {
				$message = __( 'Discogs API Rate Limit exceeded. Please try again in a minute.', 'discogs-importer' );
			}

			return new WP_Error( 'discogs_api_error', $message, array( 'status' => $response_code ) );
		}

		$data = json_decode( $body, true );
		if ( null === $data ) {
			return new WP_Error( 'json_parse_error', __( 'Failed to parse JSON response from Discogs.', 'discogs-importer' ) );
		}

		return $data;
	}

	/**
	 * Search Discogs Database.
	 *
	 * @param array $args Search arguments.
	 * @return array|WP_Error
	 */
	public static function search( $args ) {
		// Enforce release type.
		$args['type'] = 'release';
		
		// If page is not set.
		if ( ! isset( $args['page'] ) ) {
			$args['page'] = 1;
		}
		if ( ! isset( $args['per_page'] ) ) {
			$args['per_page'] = 12;
		}

		return self::make_request( 'database/search', $args );
	}

	/**
	 * Get Release Details by ID.
	 *
	 * @param int|string $release_id The Discogs release ID.
	 * @return array|WP_Error
	 */
	public static function get_release( $release_id ) {
		if ( empty( $release_id ) ) {
			return new WP_Error( 'invalid_id', __( 'Invalid Discogs Release ID.', 'discogs-importer' ) );
		}

		return self::make_request( 'releases/' . rawurlencode( $release_id ) );
	}
}
