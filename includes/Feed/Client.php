<?php
/**
 * Fetches the WeTicket Storefront RSS feed.
 *
 * @package WeTicket\Sync
 */

namespace WeTicket\Sync\Feed;

use WP_Error;

defined( 'ABSPATH' ) || exit;

class Client {

	/**
	 * Fetch the raw feed body.
	 *
	 * The feed declares encoding="utf-16" in its XML prolog but the bytes are
	 * actually UTF-8. We rewrite the declared encoding to utf-8 before handing
	 * the body to the parser, otherwise SimpleXML/DOM refuses to parse it.
	 *
	 * @param string $url Feed URL.
	 * @return string|WP_Error Raw XML body, or WP_Error on failure.
	 */
	public function fetch( $url ) {
		if ( empty( $url ) || ! wp_http_validate_url( $url ) ) {
			return new WP_Error( 'weticket_feed_url', __( 'The configured feed URL is invalid.', 'weticket-sync' ) );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
				'headers' => array(
					'Accept' => 'application/rss+xml, application/xml;q=0.9, */*;q=0.8',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error(
				'weticket_feed_http',
				/* translators: %d: HTTP status code. */
				sprintf( __( 'The feed returned HTTP status %d.', 'weticket-sync' ), $code )
			);
		}

		$body = wp_remote_retrieve_body( $response );
		if ( '' === trim( (string) $body ) ) {
			return new WP_Error( 'weticket_feed_empty', __( 'The feed returned an empty response.', 'weticket-sync' ) );
		}

		// The prolog lies about its encoding; normalize it to utf-8.
		$body = preg_replace( '/(<\?xml[^>]*encoding=")[^"]*(")/i', '${1}utf-8${2}', $body, 1 );

		return $body;
	}
}
