<?php
/**
 * Renders Twig templates.
 *
 * Renders a single template via an ArrayLoader with a custom i18n `date`
 * filter and non-strict variables (so unavailable fields render empty instead
 * of throwing). A SyntaxError becomes a WP_Error.
 *
 * @package WeTicket\Sync
 */

namespace WeTicket\Sync\Templates;

use WP_Error;

defined( 'ABSPATH' ) || exit;

class Templates {

	/**
	 * Render a Twig template against the given data.
	 *
	 * @param string $template Twig template source.
	 * @param array  $data     Template data.
	 * @return string|WP_Error Rendered output, or WP_Error on a template error.
	 */
	public static function render( $template, array $data ) {
		if ( ! class_exists( '\\Twig\\Environment' ) ) {
			$autoload = WETICKET_SYNC_PATH . 'vendor/autoload.php';
			if ( is_readable( $autoload ) ) {
				require_once $autoload;
			}
		}

		if ( ! class_exists( '\\Twig\\Environment' ) ) {
			return new WP_Error(
				'weticket_twig_missing',
				__( 'Twig is not installed. Run "composer install" in the plugin directory.', 'weticket-sync' )
			);
		}

		$loader = new \Twig\Loader\ArrayLoader( array( 'weticket.html' => (string) $template ) );
		$twig   = new \Twig\Environment( $loader );

		// Replace the built-in 'date' filter with one that supports i18n dates.
		$twig->addFilter( new \Twig\TwigFilter( 'date', array( __CLASS__, 'filter_date' ) ) );

		try {
			return $twig->render( 'weticket.html', $data );
		} catch ( \Twig\Error\Error $e ) {
			return new WP_Error( 'weticket_template', $e->getMessage() );
		}
	}

	/**
	 * Twig `date` filter with i18n support.
	 *
	 * @param string|int  $date   Date string or timestamp.
	 * @param string|null $format PHP date format; falls back to the WP date_format option.
	 * @return string
	 */
	public static function filter_date( $date, $format = null ) {
		if ( null === $format ) {
			$format = get_option( 'date_format' );
		}

		$date = trim( (string) $date );
		if ( '' === $date ) {
			return '';
		}

		if ( is_numeric( $date ) && ( false === strtotime( $date ) || 8 !== strlen( $date ) ) ) {
			$timestamp = intval( $date );
		} else {
			// Strip high-precision fractional seconds (e.g. ".0000000") that
			// strtotime() cannot parse, then read the absolute (UTC) instant.
			$timestamp = strtotime( preg_replace( '/\.\d+/', '', $date ) );
		}

		if ( false === $timestamp ) {
			return '';
		}

		// wp_date() renders the absolute timestamp in the site's timezone.
		// date_i18n() would keep the source UTC wall-clock time instead.
		if ( function_exists( 'wp_date' ) ) {
			return wp_date( $format, $timestamp );
		}

		return date_i18n( $format, $timestamp );
	}
}
