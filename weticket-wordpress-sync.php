<?php
/**
 * Plugin Name:       WeTicket WordPress Sync
 * Plugin URI:        https://weticket.io/
 * Description:       Periodically syncs events from a WeTicket Storefront RSS feed into a custom post type. Renders titles and content with Twig templates.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            WeTicket
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       weticket-sync
 *
 * @package WeTicket\Sync
 */

namespace WeTicket\Sync;

defined( 'ABSPATH' ) || exit;

define( 'WETICKET_SYNC_VERSION', '1.0.0' );
define( 'WETICKET_SYNC_FILE', __FILE__ );
define( 'WETICKET_SYNC_PATH', plugin_dir_path( __FILE__ ) );
define( 'WETICKET_SYNC_URL', plugin_dir_url( __FILE__ ) );

/**
 * PSR-4 style autoloader for the WeTicket\Sync namespace.
 *
 * Maps WeTicket\Sync\Feed\Client => includes/Feed/Client.php
 */
spl_autoload_register(
	function ( $class ) {
		$prefix = __NAMESPACE__ . '\\';
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

register_activation_hook( __FILE__, array( __NAMESPACE__ . '\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( __NAMESPACE__ . '\\Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( __NAMESPACE__ . '\\Plugin', 'boot' ) );
