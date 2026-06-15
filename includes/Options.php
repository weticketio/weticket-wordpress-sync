<?php
/**
 * Centralized access to plugin settings and their defaults.
 *
 * @package WeTicket\Sync
 */

namespace WeTicket\Sync;

defined( 'ABSPATH' ) || exit;

class Options {

	const OPTION = 'weticket_sync_options';

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'feed_url'         => 'https://api.staging.weticket.io/storefront/organizations/f2142682-5cb6-4a93-b52c-04b7a5aa6780/events.xml',
			'interval'         => 'hourly',
			'on_removal'       => 'draft', // draft | trash | keep.
			'title_template'   => self::default_title_template(),
			'content_template' => self::default_content_template(),
		);
	}

	/**
	 * Get merged settings (saved values over defaults).
	 *
	 * @return array
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::defaults() );
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get_value( $key ) {
		$all = self::get();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Default Twig template for the post title.
	 *
	 * @return string
	 */
	public static function default_title_template() {
		return self::load_template_file( 'default-title.twig', '{{ title }}' );
	}

	/**
	 * Default Twig template for the post content.
	 *
	 * Uses the documented template variables and degrades gracefully when fields
	 * are absent (non-strict Twig renders undefined variables as empty).
	 *
	 * @return string
	 */
	public static function default_content_template() {
		return self::load_template_file( 'default-content.twig', '{% if description %}<p>{{ description }}</p>{% endif %}' );
	}

	/**
	 * Load a bundled template file, falling back to a literal default.
	 *
	 * @param string $file     File name within the templates/ directory.
	 * @param string $fallback Fallback template source.
	 * @return string
	 */
	private static function load_template_file( $file, $fallback ) {
		$path = WETICKET_SYNC_PATH . 'templates/' . $file;
		if ( is_readable( $path ) ) {
			$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local bundled file.
			if ( false !== $contents && '' !== trim( $contents ) ) {
				return $contents;
			}
		}
		return $fallback;
	}
}
