<?php
/**
 * Stores the result of the last sync run for display in the admin.
 *
 * @package WeTicket\Sync
 */

namespace WeTicket\Sync;

defined( 'ABSPATH' ) || exit;

class Logger {

	const OPTION = 'weticket_sync_log';

	/**
	 * Record a successful run.
	 *
	 * @param array $stats Counts: created, updated, removed, total.
	 */
	public static function record_success( array $stats ) {
		update_option(
			self::OPTION,
			array(
				'status'  => 'success',
				'time'    => current_time( 'mysql' ),
				'stats'   => $stats,
				'message' => '',
			),
			false
		);
	}

	/**
	 * Record a failed run.
	 *
	 * @param string $message Human readable error.
	 */
	public static function record_error( $message ) {
		update_option(
			self::OPTION,
			array(
				'status'  => 'error',
				'time'    => current_time( 'mysql' ),
				'stats'   => array(),
				'message' => (string) $message,
			),
			false
		);
	}

	/**
	 * Get the last recorded run.
	 *
	 * @return array
	 */
	public static function get() {
		return get_option( self::OPTION, array() );
	}
}
