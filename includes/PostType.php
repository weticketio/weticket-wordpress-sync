<?php
/**
 * Registers the weticket_event custom post type.
 *
 * @package WeTicket\Sync
 */

namespace WeTicket\Sync;

defined( 'ABSPATH' ) || exit;

class PostType {

	const SLUG = 'weticket_event';

	/**
	 * Hook registration into init.
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_post_type' ) );
	}

	/**
	 * Register the custom post type.
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => __( 'Events', 'weticket-sync' ),
			'singular_name'      => __( 'Event', 'weticket-sync' ),
			'menu_name'          => __( 'WeTicket Events', 'weticket-sync' ),
			'add_new_item'       => __( 'Add New Event', 'weticket-sync' ),
			'edit_item'          => __( 'Edit Event', 'weticket-sync' ),
			'view_item'          => __( 'View Event', 'weticket-sync' ),
			'all_items'          => __( 'All Events', 'weticket-sync' ),
			'search_items'       => __( 'Search Events', 'weticket-sync' ),
			'not_found'          => __( 'No events found.', 'weticket-sync' ),
			'not_found_in_trash' => __( 'No events found in Trash.', 'weticket-sync' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => true,
			'has_archive'         => true,
			'show_in_rest'        => true,
			'menu_icon'           => 'dashicons-tickets-alt',
			'rewrite'             => array( 'slug' => 'events' ),
			'supports'            => array( 'title', 'editor', 'thumbnail', 'custom-fields', 'excerpt' ),
		);

		/**
		 * Filter the arguments used to register the weticket_event post type.
		 *
		 * @param array $args Post type registration arguments.
		 */
		$args = apply_filters( 'weticket_sync_post_type_args', $args );

		register_post_type( self::SLUG, $args );

		// Register the public event meta fields (visible + REST-exposed).
		Fields::register();
	}
}
