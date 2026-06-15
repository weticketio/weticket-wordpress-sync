<?php
/**
 * Plugin bootstrap: wires components and lifecycle hooks.
 *
 * @package WeTicket\Sync
 */

namespace WeTicket\Sync;

defined( 'ABSPATH' ) || exit;

class Plugin {

	/**
	 * Boot the plugin on plugins_loaded.
	 */
	public static function boot() {
		$plugin = new self();
		$plugin->register();
	}

	/**
	 * Register runtime hooks.
	 */
	public function register() {
		( new PostType() )->register();
		( new Scheduler() )->register();

		if ( is_admin() ) {
			( new Admin\Settings() )->register();
			( new Admin\EventColumns() )->register();
		}
	}

	/**
	 * Activation: register the CPT, flush rewrites, schedule the cron.
	 */
	public static function activate() {
		( new PostType() )->register_post_type();
		flush_rewrite_rules();
		( new Scheduler() )->schedule();
	}

	/**
	 * Deactivation: clear the cron and flush rewrites.
	 */
	public static function deactivate() {
		( new Scheduler() )->unschedule();
		flush_rewrite_rules();
	}
}
