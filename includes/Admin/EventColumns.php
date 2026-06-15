<?php
/**
 * Adds custom columns to the events admin list table.
 *
 * @package WeTicket\Sync
 */

namespace WeTicket\Sync\Admin;

use WeTicket\Sync\PostType;
use WeTicket\Sync\Templates\Templates;

defined( 'ABSPATH' ) || exit;

class EventColumns {

	/**
	 * Register admin hooks.
	 */
	public function register() {
		add_filter( 'manage_' . PostType::SLUG . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . PostType::SLUG . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_filter( 'manage_edit-' . PostType::SLUG . '_sortable_columns', array( $this, 'sortable_columns' ) );
		add_action( 'pre_get_posts', array( $this, 'sort_by_start' ) );
	}

	/**
	 * Insert custom columns right after the title column.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function columns( $columns ) {
		$inserted = array(
			'weticket_start' => __( 'Start', 'weticket-sync' ),
			'weticket_venue' => __( 'Venue', 'weticket-sync' ),
		);

		$new = array();
		foreach ( $columns as $key => $label ) {
			// Clarify the built-in post date column vs. the event start.
			if ( 'date' === $key ) {
				$label = __( 'Creation date', 'weticket-sync' );
			}
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new = array_merge( $new, $inserted );
			}
		}

		// If there was no title column, append instead.
		if ( ! isset( $columns['title'] ) ) {
			$new = array_merge( $new, $inserted );
		}

		return $new;
	}

	/**
	 * Render a custom column's value.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public function render_column( $column, $post_id ) {
		switch ( $column ) {
			case 'weticket_start':
				$start = get_post_meta( $post_id, 'weticket_start', true );
				if ( $start ) {
					$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
					echo esc_html( Templates::filter_date( $start, $format ) );
				} else {
					echo '&mdash;';
				}
				break;

			case 'weticket_venue':
				$venue = get_post_meta( $post_id, 'weticket_venue', true );
				echo $venue ? esc_html( $venue ) : '&mdash;';
				break;
		}
	}

	/**
	 * Make the Start column sortable.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public function sortable_columns( $columns ) {
		$columns['weticket_start'] = 'weticket_start';
		return $columns;
	}

	/**
	 * Order the events admin screen by event start.
	 *
	 * Defaults the screen to start descending (newest first); when the user
	 * clicks the Start column header, their chosen direction is respected.
	 * Start values are ISO-8601 strings, which sort chronologically as text.
	 *
	 * @param \WP_Query $query The query.
	 */
	public function sort_by_start( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( PostType::SLUG !== $query->get( 'post_type' ) ) {
			return;
		}

		$orderby = $query->get( 'orderby' );

		if ( 'weticket_start' === $orderby ) {
			// User clicked the Start column; respect their chosen direction.
			$query->set( 'meta_key', 'weticket_start' );
			$query->set( 'orderby', 'meta_value' );
		} elseif ( '' === $orderby ) {
			// No explicit sort chosen: default to newest start first.
			$query->set( 'meta_key', 'weticket_start' );
			$query->set( 'orderby', 'meta_value' );
			$query->set( 'order', 'DESC' );
		}
	}
}
