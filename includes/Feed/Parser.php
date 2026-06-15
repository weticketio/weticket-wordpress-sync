<?php
/**
 * Parses the WeTicket RSS feed into normalized event arrays.
 *
 * @package WeTicket\Sync
 */

namespace WeTicket\Sync\Feed;

use WP_Error;

defined( 'ABSPATH' ) || exit;

class Parser {

	const NS_EV      = 'http://purl.org/rss/1.0/modules/event/';
	const NS_MEDIA   = 'http://search.yahoo.com/mrss/';
	const NS_CONTENT = 'http://purl.org/rss/1.0/modules/content/';

	/**
	 * Parse a raw XML string into a list of normalized event arrays.
	 *
	 * Each event is an associative array with normalized keys:
	 * guid, title, link, description, start, end, organizer, location,
	 * image_url, pubdate.
	 *
	 * @param string $xml_string Raw (encoding-normalized) feed body.
	 * @return array<int,array>|WP_Error
	 */
	public function parse( $xml_string ) {
		$previous = libxml_use_internal_errors( true );
		// LIBXML_NOCDATA folds CDATA (e.g. content:encoded HTML) into text nodes.
		$xml = simplexml_load_string( (string) $xml_string, 'SimpleXMLElement', LIBXML_NOCDATA );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( false === $xml || ! isset( $xml->channel ) ) {
			return new WP_Error( 'weticket_parse', __( 'Could not parse the feed XML.', 'weticket-sync' ) );
		}

		$events = array();

		foreach ( $xml->channel->item as $item ) {
			$ev      = $item->children( self::NS_EV );
			$media   = $item->children( self::NS_MEDIA );
			$content = $item->children( self::NS_CONTENT );

			$guid = trim( (string) $item->guid );
			if ( '' === $guid ) {
				// Fall back to the link as a stable identifier.
				$guid = trim( (string) $item->link );
			}
			if ( '' === $guid ) {
				continue;
			}

			$images = $this->extract_images( $item, $media );

			$events[] = array(
				'guid'        => $guid,
				'title'       => trim( (string) $item->title ),
				'link'        => trim( (string) $item->link ),
				'description' => trim( (string) $item->description ),
				'text_html'   => isset( $content->encoded ) ? trim( (string) $content->encoded ) : '',
				'pubdate'     => trim( (string) $item->pubDate ),
				'start'       => isset( $ev->startdate ) ? trim( (string) $ev->startdate ) : '',
				'end'         => isset( $ev->enddate ) ? trim( (string) $ev->enddate ) : '',
				'organizer'   => isset( $ev->organizer ) ? trim( (string) $ev->organizer ) : '',
				'location'    => isset( $ev->location ) ? trim( (string) $ev->location ) : '',
				'image_url'   => $images ? $images[0] : '',
				'image_urls'  => $images,
			);
		}

		return $events;
	}

	/**
	 * Extract image URLs for an item.
	 *
	 * Handles the three common representations, in order of preference:
	 * media:content (filtered to images), media:thumbnail, and RSS <enclosure>
	 * with an image MIME type. Returns a de-duplicated, ordered list.
	 *
	 * @param \SimpleXMLElement $item  The RSS item (default namespace).
	 * @param \SimpleXMLElement $media Media-namespaced children of the item.
	 * @return string[] Image URLs (first is the main image).
	 */
	private function extract_images( $item, $media ) {
		$images = array();

		// media:content entries that look like images.
		if ( isset( $media->content ) ) {
			foreach ( $media->content as $content ) {
				$attrs = $content->attributes();
				$url   = isset( $attrs['url'] ) ? trim( (string) $attrs['url'] ) : '';
				if ( '' === $url ) {
					continue;
				}
				$medium = isset( $attrs['medium'] ) ? strtolower( (string) $attrs['medium'] ) : '';
				$type   = isset( $attrs['type'] ) ? strtolower( (string) $attrs['type'] ) : '';

				// Treat as an image when explicitly marked, or when untyped.
				$is_image = ( 'image' === $medium )
					|| ( 0 === strpos( $type, 'image/' ) )
					|| ( '' === $medium && '' === $type );

				if ( $is_image ) {
					$images[] = $url;
				}
			}
		}

		// media:thumbnail.
		if ( isset( $media->thumbnail ) ) {
			foreach ( $media->thumbnail as $thumb ) {
				$attrs = $thumb->attributes();
				if ( isset( $attrs['url'] ) ) {
					$url = trim( (string) $attrs['url'] );
					if ( '' !== $url ) {
						$images[] = $url;
					}
				}
			}
		}

		// RSS <enclosure> with an image MIME type.
		if ( isset( $item->enclosure ) ) {
			foreach ( $item->enclosure as $enc ) {
				$attrs = $enc->attributes();
				$url   = isset( $attrs['url'] ) ? trim( (string) $attrs['url'] ) : '';
				$type  = isset( $attrs['type'] ) ? strtolower( (string) $attrs['type'] ) : '';
				if ( '' !== $url && 0 === strpos( $type, 'image/' ) ) {
					$images[] = $url;
				}
			}
		}

		return array_values( array_unique( $images ) );
	}
}
