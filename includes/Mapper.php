<?php
/**
 * Maps normalized feed events to Twig template data.
 *
 * @package WeTicket\Sync
 */

namespace WeTicket\Sync;

defined( 'ABSPATH' ) || exit;

class Mapper {

	/**
	 * Build the Twig template data for an event.
	 *
	 * Variable names follow a stable, documented convention so templates keep working.
	 * Fields the RSS feed does not provide are intentionally omitted; under
	 * non-strict Twig they render as empty rather than throwing.
	 *
	 * @param array $event Normalized event from the Parser.
	 * @return array
	 */
	public function to_template_data( array $event ) {
		$venue = $event['location'];

		$data = array(
			'title'            => $event['title'],
			'description'      => $event['description'],
			'text'             => $event['description'],
			'textHtml'         => $event['text_html'],
			'start'            => $event['start'],
			'end'              => $event['end'],
			'tickets_url'      => $event['link'],
			'status'           => 'onsale', // The feed only lists upcoming/available events.
			'organizationName' => $event['organizer'],
			'guid'             => $event['guid'],
			'pubDate'          => $event['pubdate'],
			'venue'            => array(
				'title' => $venue,
				'city'  => '',
			),
			'location'         => array(
				'name' => $venue,
				'city' => '',
			),
			'media'            => array(
				'mainImageUrl'        => $event['image_url'],
				'additionalImageUrls' => isset( $event['image_urls'] ) ? array_slice( $event['image_urls'], 1 ) : array(),
			),
		);

		/**
		 * Filter the template data for an event before rendering.
		 *
		 * @param array $data  Template data.
		 * @param array $event Normalized event.
		 */
		return apply_filters( 'weticket_sync_template_data', $data, $event );
	}

	/**
	 * Build the post meta map for an event.
	 *
	 * @param array $event Normalized event from the Parser.
	 * @return array<string,string>
	 */
	public function to_meta( array $event ) {
		return array(
			'weticket_start'       => $event['start'],
			'weticket_end'         => $event['end'],
			'weticket_tickets_url' => $event['link'],
			'weticket_venue'       => $event['location'],
			'weticket_organizer'   => $event['organizer'],
			'weticket_description' => $event['description'],
			'weticket_text_html'   => $event['text_html'],
			'weticket_status'      => 'onsale',
			'weticket_pubdate'     => $event['pubdate'],
		);
	}
}
