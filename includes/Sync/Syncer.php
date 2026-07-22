<?php
/**
 * Orchestrates a sync run: fetch, parse, map, upsert, prune.
 *
 * @package WeTicket\Sync
 */

namespace WeTicket\Sync\Sync;

use WeTicket\Sync\Feed\Client;
use WeTicket\Sync\Feed\Parser;
use WeTicket\Sync\Logger;
use WeTicket\Sync\Mapper;
use WeTicket\Sync\Options;
use WeTicket\Sync\PostType;
use WeTicket\Sync\Templates\Templates;
use WP_Error;

defined( 'ABSPATH' ) || exit;

class Syncer {

	/**
	 * Run a full sync.
	 *
	 * @return array|WP_Error Stats on success, WP_Error on failure.
	 */
	public function run() {
		$options = Options::get();

		$body = ( new Client() )->fetch( $options['feed_url'] );
		if ( is_wp_error( $body ) ) {
			Logger::record_error( $body->get_error_message() );
			return $body;
		}

		$events = ( new Parser() )->parse( $body );
		if ( is_wp_error( $events ) ) {
			Logger::record_error( $events->get_error_message() );
			return $events;
		}

		$mapper   = new Mapper();
		$seen_ids = array();
		$created  = 0;
		$updated  = 0;

		foreach ( $events as $event ) {
			$post_id = $this->upsert( $event, $mapper, $options, $created, $updated );
			if ( $post_id ) {
				$seen_ids[] = $post_id;
			}
		}

		$removed = $this->prune( $seen_ids, $options['on_removal'] );

		$stats = array(
			'total'   => count( $events ),
			'created' => $created,
			'updated' => $updated,
			'removed' => $removed,
		);

		Logger::record_success( $stats );

		/**
		 * Fires after a sync run completes successfully.
		 *
		 * @param array $stats Run statistics.
		 */
		do_action( 'weticket_sync_completed', $stats );

		return $stats;
	}

	/**
	 * Create or update the post for a single event.
	 *
	 * @param array  $event   Normalized event.
	 * @param Mapper $mapper  Mapper instance.
	 * @param array  $options Plugin options.
	 * @param int    $created Running count of created posts (by reference).
	 * @param int    $updated Running count of updated posts (by reference).
	 * @return int Post ID, or 0 on failure.
	 */
	private function upsert( array $event, Mapper $mapper, array $options, &$created, &$updated ) {
		$data     = $mapper->to_template_data( $event );
		$existing = $this->find_by_guid( $event['guid'] );

		$title = Templates::render( $options['title_template'], $data );
		if ( is_wp_error( $title ) || '' === trim( (string) $title ) ) {
			$title = $event['title'];
		}

		$content = Templates::render( $options['content_template'], $data );
		if ( is_wp_error( $content ) ) {
			$content = '';
		}

		$postarr = array(
			'post_type'    => PostType::SLUG,
			'post_title'   => wp_strip_all_tags( $title ),
			'post_content' => $content,
			'post_excerpt' => $event['short_description'],
			'post_status'  => 'publish',
		);

		if ( $existing ) {
			$postarr['ID'] = $existing->ID;
			$post_id       = wp_update_post( $postarr, true );
			++$updated;
		} else {
			$post_id = wp_insert_post( $postarr, true );
			++$created;
		}

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			// Roll back the counter we optimistically incremented.
			if ( $existing ) {
				--$updated;
			} else {
				--$created;
			}
			return 0;
		}

		// Public, visible event fields.
		foreach ( $mapper->to_meta( $event ) as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		// Internal plumbing (kept protected with a leading underscore).
		update_post_meta( $post_id, '_weticket_guid', $event['guid'] );
		update_post_meta( $post_id, '_weticket_synced_at', current_time( 'mysql' ) );

		$this->delete_legacy_meta( $post_id );
		$this->maybe_set_featured_image( $post_id, $event['image_url'] );

		return (int) $post_id;
	}

	/**
	 * Find an existing event post by its WeTicket GUID.
	 *
	 * Searches any post status so previously pruned (drafted/trashed) posts
	 * are revived rather than duplicated.
	 *
	 * @param string $guid WeTicket event GUID.
	 * @return \WP_Post|null
	 */
	private function find_by_guid( $guid ) {
		$posts = get_posts(
			array(
				'post_type'        => PostType::SLUG,
				'post_status'      => 'any',
				'numberposts'      => 1,
				'meta_key'         => '_weticket_guid', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => $guid,             // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'suppress_filters' => false,
			)
		);

		return $posts ? $posts[0] : null;
	}

	/**
	 * Remove meta from the pre-1.0 protected-key scheme (now public keys).
	 *
	 * Keeps the GUID, sync timestamp and image tracker, which remain internal.
	 *
	 * @param int $post_id Post ID.
	 */
	private function delete_legacy_meta( $post_id ) {
		$legacy = array(
			'_weticket_start',
			'_weticket_end',
			'_weticket_tickets_url',
			'_weticket_venue',
			'_weticket_organizer',
			'_weticket_description',
			'_weticket_status',
			'_weticket_pubdate',
		);
		foreach ( $legacy as $key ) {
			delete_post_meta( $post_id, $key );
		}
	}

	/**
	 * Sideload and attach a featured image, only when the source URL changes.
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $image_url Source image URL (may be empty).
	 */
	private function maybe_set_featured_image( $post_id, $image_url ) {
		if ( empty( $image_url ) ) {
			return;
		}

		$stored = get_post_meta( $post_id, '_weticket_image_url', true );
		if ( $stored === $image_url && has_post_thumbnail( $post_id ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_sideload_image( $image_url, $post_id, null, 'id' );
		if ( is_wp_error( $attachment_id ) ) {
			return;
		}

		set_post_thumbnail( $post_id, $attachment_id );
		update_post_meta( $post_id, '_weticket_image_url', $image_url );
	}

	/**
	 * Handle events that are no longer in the feed.
	 *
	 * @param int[]  $seen_ids   Post IDs seen during this run.
	 * @param string $on_removal draft | trash | keep.
	 * @return int Number of posts affected.
	 */
	private function prune( array $seen_ids, $on_removal ) {
		if ( 'keep' === $on_removal ) {
			return 0;
		}

		$stale = get_posts(
			array(
				'post_type'        => PostType::SLUG,
				'post_status'      => 'publish',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'post__not_in'     => $seen_ids ? $seen_ids : array( 0 ),
				'meta_key'         => '_weticket_guid', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'suppress_filters' => false,
			)
		);

		foreach ( $stale as $post_id ) {
			if ( 'trash' === $on_removal ) {
				wp_trash_post( $post_id );
			} else {
				wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => 'draft',
					)
				);
			}
		}

		return count( $stale );
	}
}
