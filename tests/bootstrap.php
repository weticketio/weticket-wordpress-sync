<?php
/**
 * Lightweight bootstrap for unit tests.
 *
 * Stubs the handful of WordPress functions/classes that the Parser, Mapper and
 * Templates classes touch, so they can be exercised without a full WP install.
 *
 * @package WeTicket\Sync
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'WETICKET_SYNC_PATH', dirname( __DIR__ ) . '/' );
define( 'WETICKET_SYNC_VERSION', 'test' );

require_once WETICKET_SYNC_PATH . 'vendor/autoload.php';

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		/** @var string */
		private $code;
		/** @var string */
		private $message;

		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		public function get_error_message() {
			return $this->message;
		}

		public function get_error_code() {
			return $this->code;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) {
		return $value;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		if ( 'date_format' === $name ) {
			return 'j F Y';
		}
		return $default;
	}
}

if ( ! function_exists( 'date_i18n' ) ) {
	function date_i18n( $format, $timestamp ) {
		return gmdate( $format, $timestamp );
	}
}

if ( ! function_exists( 'wp_timezone' ) ) {
	// Fixed test timezone so timezone conversion is assertable.
	function wp_timezone() {
		return new DateTimeZone( 'Europe/Amsterdam' );
	}
}

if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( $format, $timestamp = null, $timezone = null ) {
		if ( null === $timestamp ) {
			$timestamp = time();
		}
		if ( null === $timezone ) {
			$timezone = wp_timezone();
		}
		$datetime = ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $timezone );
		return $datetime->format( $format );
	}
}

// Autoloader for plugin classes (mirrors the main plugin file).
spl_autoload_register(
	function ( $class ) {
		$prefix = 'WeTicket\\Sync\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$path     = WETICKET_SYNC_PATH . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $path ) ) {
			require $path;
		}
	}
);
