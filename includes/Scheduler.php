<?php
/**
 * WP-Cron scheduling and the manual "Sync now" action.
 *
 * @package WeTicket\Sync
 */

namespace WeTicket\Sync;

use WeTicket\Sync\Sync\Syncer;

defined( 'ABSPATH' ) || exit;

class Scheduler {

	const HOOK = 'weticket_sync_cron';

	const LOCK = 'weticket_schedule_lock';

	/**
	 * Register hooks.
	 */
	public function register() {
		add_filter( 'cron_schedules', array( $this, 'add_schedules' ) );
		add_action( self::HOOK, array( $this, 'run_sync' ) );
		add_action( 'admin_post_weticket_sync_now', array( $this, 'handle_sync_now' ) );
		// Reschedule when the configured interval changes.
		add_action( 'update_option_' . Options::OPTION, array( $this, 'on_options_updated' ), 10, 2 );
		// Self-heal: the event is created on activation, but a cleared cron
		// queue (migration, restore, cleanup plugin) would otherwise leave
		// the sync unscheduled until someone reactivates the plugin.
		add_action( 'init', array( $this, 'ensure_scheduled' ) );
	}

	/**
	 * Add a 15-minute schedule (hourly/twicedaily/daily are built in).
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public function add_schedules( $schedules ) {
		if ( ! isset( $schedules['weticket_15min'] ) ) {
			$schedules['weticket_15min'] = array(
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 15 minutes (WeTicket)', 'weticket-sync' ),
			);
		}
		return $schedules;
	}

	/**
	 * Available recurrence choices for the settings screen.
	 *
	 * @return array<string,string>
	 */
	public static function recurrence_choices() {
		return array(
			'weticket_15min' => __( 'Every 15 minutes', 'weticket-sync' ),
			'hourly'         => __( 'Hourly', 'weticket-sync' ),
			'twicedaily'     => __( 'Twice daily', 'weticket-sync' ),
			'daily'          => __( 'Daily', 'weticket-sync' ),
		);
	}

	/**
	 * Schedule the recurring sync if not already scheduled.
	 */
	public function schedule() {
		// Ensure the custom interval exists even outside a normal bootstrap:
		// the activation hook fires after plugins_loaded, so register() — and
		// with it the cron_schedules filter — has not run in that request.
		// Without this, activating with the 15-minute interval selected made
		// wp_schedule_event() fail silently on an unknown schedule.
		add_filter( 'cron_schedules', array( $this, 'add_schedules' ) );

		$recurrence = Options::get_value( 'interval' );
		if ( ! array_key_exists( $recurrence, self::recurrence_choices() ) ) {
			$recurrence = 'hourly';
		}
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, $recurrence, self::HOOK );
		}
	}

	/**
	 * Self-heal: re-create the recurring event if it is missing, guarded
	 * against concurrent requests double-scheduling it.
	 *
	 * wp_schedule_event() does not deduplicate recurring events, so two
	 * requests racing through the wp_next_scheduled() check could each add
	 * an event under a slightly different timestamp, leaving overlapping
	 * syncs behind. Only the request that wins the lock schedules.
	 */
	public function ensure_scheduled() {
		if ( wp_next_scheduled( self::HOOK ) ) {
			return;
		}

		if ( ! $this->acquire_lock() ) {
			return;
		}

		// Another request may have scheduled between the check above and
		// acquiring the lock, and this process's in-memory options cache
		// would not see that write; refresh before re-checking.
		wp_cache_delete( 'alloptions', 'options' );
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			$this->schedule();
		}

		delete_option( self::LOCK );
	}

	/**
	 * Atomically acquire the scheduling lock.
	 *
	 * Same technique as WP_Upgrader::create_lock(): INSERT IGNORE against
	 * the unique option_name key succeeds for exactly one request.
	 *
	 * @return bool Whether the lock was acquired.
	 */
	private function acquire_lock() {
		global $wpdb;

		$acquired = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO `$wpdb->options` ( `option_name`, `option_value`, `autoload` ) VALUES (%s, %s, 'no') /* LOCK */",
				self::LOCK,
				(string) time()
			)
		);

		if ( $acquired ) {
			return true;
		}

		// The lock exists. Treat it as stale after a minute (the holder may
		// have died before releasing) so self-healing cannot jam. Read via
		// $wpdb: the row was inserted around the options cache.
		$held_since = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT option_value FROM `$wpdb->options` WHERE option_name = %s", self::LOCK )
		);
		if ( $held_since && time() - $held_since > MINUTE_IN_SECONDS ) {
			delete_option( self::LOCK );
			return $this->acquire_lock();
		}

		return false;
	}

	/**
	 * Clear the recurring sync.
	 */
	public function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Reschedule when the interval setting changes.
	 *
	 * @param mixed $old_value Previous options.
	 * @param mixed $value     New options.
	 */
	public function on_options_updated( $old_value, $value ) {
		$old_interval = is_array( $old_value ) && isset( $old_value['interval'] ) ? $old_value['interval'] : '';
		$new_interval = is_array( $value ) && isset( $value['interval'] ) ? $value['interval'] : '';
		if ( $old_interval !== $new_interval ) {
			$this->unschedule();
			$this->schedule();
		}
	}

	/**
	 * Cron callback.
	 */
	public function run_sync() {
		( new Syncer() )->run();
	}

	/**
	 * Handle the "Sync now" admin-post submission.
	 */
	public function handle_sync_now() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'weticket-sync' ) );
		}
		check_admin_referer( 'weticket_sync_now' );

		$result = ( new Syncer() )->run();
		$status = is_wp_error( $result ) ? 'error' : 'ok';

		$redirect = add_query_arg(
			'weticket_synced',
			$status,
			admin_url( 'options-general.php?page=weticket-sync' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}
}
