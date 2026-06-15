<?php
/**
 * Public event meta fields: the single source of truth for the field schema.
 *
 * These keys are intentionally unprefixed (no leading underscore) so they are
 * visible in the Custom Fields metabox, and they are registered with the REST
 * API so themes, ACF, and page builders can read them as first-class fields.
 *
 * @package WeTicket\Sync
 */

namespace WeTicket\Sync;

defined( 'ABSPATH' ) || exit;

class Fields {

	/**
	 * Field definitions, keyed by public meta key.
	 *
	 * @return array<string,array>
	 */
	public static function definitions() {
		return array(
			'weticket_start'       => array(
				'label'    => __( 'Start date/time', 'weticket-sync' ),
				'type'     => 'string',
				'sanitize' => 'sanitize_text_field',
			),
			'weticket_end'         => array(
				'label'    => __( 'End date/time', 'weticket-sync' ),
				'type'     => 'string',
				'sanitize' => 'sanitize_text_field',
			),
			'weticket_tickets_url' => array(
				'label'    => __( 'Tickets URL', 'weticket-sync' ),
				'type'     => 'string',
				'sanitize' => 'esc_url_raw',
			),
			'weticket_venue'       => array(
				'label'    => __( 'Venue', 'weticket-sync' ),
				'type'     => 'string',
				'sanitize' => 'sanitize_text_field',
			),
			'weticket_organizer'   => array(
				'label'    => __( 'Organizer', 'weticket-sync' ),
				'type'     => 'string',
				'sanitize' => 'sanitize_text_field',
			),
			'weticket_description' => array(
				'label'    => __( 'Description (plain text)', 'weticket-sync' ),
				'type'     => 'string',
				'sanitize' => 'sanitize_textarea_field',
			),
			'weticket_text_html'   => array(
				'label'    => __( 'Description (HTML)', 'weticket-sync' ),
				'type'     => 'string',
				'sanitize' => 'wp_kses_post',
			),
			'weticket_status'      => array(
				'label'    => __( 'Status', 'weticket-sync' ),
				'type'     => 'string',
				'sanitize' => 'sanitize_text_field',
			),
			'weticket_pubdate'     => array(
				'label'    => __( 'Publication date', 'weticket-sync' ),
				'type'     => 'string',
				'sanitize' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * The public meta keys.
	 *
	 * @return string[]
	 */
	public static function keys() {
		return array_keys( self::definitions() );
	}

	/**
	 * Register the public meta fields for the event post type.
	 *
	 * Runs on `init` (via PostType) so the fields show in the Custom Fields
	 * metabox and are exposed in the REST API.
	 */
	public static function register() {
		foreach ( self::definitions() as $key => $def ) {
			register_post_meta(
				PostType::SLUG,
				$key,
				array(
					'type'              => $def['type'],
					'single'            => true,
					'show_in_rest'      => true,
					'description'       => $def['label'],
					'sanitize_callback' => $def['sanitize'],
					'auth_callback'     => function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}
}
