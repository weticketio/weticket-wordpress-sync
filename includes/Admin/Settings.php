<?php
/**
 * Settings screen under Settings → WeTicket Sync.
 *
 * @package WeTicket\Sync
 */

namespace WeTicket\Sync\Admin;

use WeTicket\Sync\Logger;
use WeTicket\Sync\Options;
use WeTicket\Sync\Scheduler;

defined( 'ABSPATH' ) || exit;

class Settings {

	const PAGE = 'weticket-sync';

	/**
	 * Register admin hooks.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add the options page.
	 */
	public function add_menu() {
		add_options_page(
			__( 'WeTicket Sync', 'weticket-sync' ),
			__( 'WeTicket Sync', 'weticket-sync' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the settings, section, and fields.
	 */
	public function register_settings() {
		register_setting(
			'weticket_sync',
			Options::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => Options::defaults(),
			)
		);

		add_settings_section( 'weticket_sync_main', '', '__return_false', self::PAGE );

		$fields = array(
			'feed_url'         => __( 'Feed URL', 'weticket-sync' ),
			'interval'         => __( 'Sync interval', 'weticket-sync' ),
			'on_removal'       => __( 'When an event leaves the feed', 'weticket-sync' ),
			'title_template'   => __( 'Title template (Twig)', 'weticket-sync' ),
			'content_template' => __( 'Content template (Twig)', 'weticket-sync' ),
		);

		foreach ( $fields as $key => $label ) {
			add_settings_field(
				$key,
				$label,
				array( $this, 'render_field' ),
				self::PAGE,
				'weticket_sync_main',
				array( 'key' => $key )
			);
		}
	}

	/**
	 * Sanitize submitted settings.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = Options::defaults();

		$feed_url = isset( $input['feed_url'] ) ? esc_url_raw( trim( $input['feed_url'] ) ) : '';

		$interval = isset( $input['interval'] ) ? sanitize_key( $input['interval'] ) : 'hourly';
		if ( ! array_key_exists( $interval, Scheduler::recurrence_choices() ) ) {
			$interval = 'hourly';
		}

		$on_removal = isset( $input['on_removal'] ) ? sanitize_key( $input['on_removal'] ) : 'draft';
		if ( ! in_array( $on_removal, array( 'draft', 'trash', 'keep' ), true ) ) {
			$on_removal = 'draft';
		}

		return array(
			'feed_url'         => $feed_url ? $feed_url : $defaults['feed_url'],
			'interval'         => $interval,
			'on_removal'       => $on_removal,
			// Twig source may legitimately contain HTML; do not strip it. Only managers reach this screen.
			'title_template'   => isset( $input['title_template'] ) ? trim( wp_unslash( $input['title_template'] ) ) : $defaults['title_template'],
			'content_template' => isset( $input['content_template'] ) ? trim( wp_unslash( $input['content_template'] ) ) : $defaults['content_template'],
		);
	}

	/**
	 * Render a single settings field.
	 *
	 * @param array $args Field args with 'key'.
	 */
	public function render_field( $args ) {
		$key     = $args['key'];
		$options = Options::get();
		$value   = isset( $options[ $key ] ) ? $options[ $key ] : '';
		$name    = Options::OPTION . '[' . $key . ']';

		switch ( $key ) {
			case 'feed_url':
				printf(
					'<input type="url" class="large-text code" name="%s" value="%s" />',
					esc_attr( $name ),
					esc_attr( $value )
				);
				break;

			case 'interval':
				echo '<select name="' . esc_attr( $name ) . '">';
				foreach ( Scheduler::recurrence_choices() as $slug => $label ) {
					printf(
						'<option value="%s"%s>%s</option>',
						esc_attr( $slug ),
						selected( $value, $slug, false ),
						esc_html( $label )
					);
				}
				echo '</select>';
				break;

			case 'on_removal':
				$choices = array(
					'draft' => __( 'Set to draft', 'weticket-sync' ),
					'trash' => __( 'Move to trash', 'weticket-sync' ),
					'keep'  => __( 'Leave published', 'weticket-sync' ),
				);
				echo '<select name="' . esc_attr( $name ) . '">';
				foreach ( $choices as $slug => $label ) {
					printf(
						'<option value="%s"%s>%s</option>',
						esc_attr( $slug ),
						selected( $value, $slug, false ),
						esc_html( $label )
					);
				}
				echo '</select>';
				break;

			case 'title_template':
				printf(
					'<input type="text" class="large-text code" name="%s" value="%s" />',
					esc_attr( $name ),
					esc_attr( $value )
				);
				break;

			case 'content_template':
				printf(
					'<textarea class="large-text code" rows="12" name="%s">%s</textarea>',
					esc_attr( $name ),
					esc_textarea( $value )
				);
				echo '<p class="description">' . esc_html__( 'Available Twig variables: title, description, text, textHtml, start, end, tickets_url, status, venue.title, location.name, organizationName, media.mainImageUrl, guid. Variables that this feed does not provide render empty.', 'weticket-sync' ) . '</p>';
				break;
		}
	}

	/**
	 * Render the settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->maybe_render_sync_notice();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WeTicket Sync', 'weticket-sync' ); ?></h1>

			<?php $this->render_status_box(); ?>

			<form action="options.php" method="post">
				<?php
				settings_fields( 'weticket_sync' );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Manual sync', 'weticket-sync' ); ?></h2>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="weticket_sync_now" />
				<?php wp_nonce_field( 'weticket_sync_now' ); ?>
				<?php submit_button( __( 'Sync now', 'weticket-sync' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Show an admin notice after a manual sync.
	 */
	private function maybe_render_sync_notice() {
		if ( ! isset( $_GET['weticket_synced'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$status = sanitize_key( wp_unslash( $_GET['weticket_synced'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'ok' === $status ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Sync completed.', 'weticket-sync' ) . '</p></div>';
		} else {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Sync failed. See the status below.', 'weticket-sync' ) . '</p></div>';
		}
	}

	/**
	 * Render the last-run status box.
	 */
	private function render_status_box() {
		$log = Logger::get();
		if ( empty( $log ) ) {
			echo '<p>' . esc_html__( 'No sync has run yet.', 'weticket-sync' ) . '</p>';
			return;
		}

		$next = wp_next_scheduled( Scheduler::HOOK );

		echo '<table class="widefat striped" style="max-width:640px"><tbody>';
		printf(
			'<tr><th>%s</th><td>%s</td></tr>',
			esc_html__( 'Last run', 'weticket-sync' ),
			esc_html( isset( $log['time'] ) ? $log['time'] : '—' )
		);
		printf(
			'<tr><th>%s</th><td>%s</td></tr>',
			esc_html__( 'Result', 'weticket-sync' ),
			'success' === ( $log['status'] ?? '' )
				? esc_html__( 'Success', 'weticket-sync' )
				: esc_html( sprintf( /* translators: %s: error message. */ __( 'Error: %s', 'weticket-sync' ), $log['message'] ?? '' ) )
		);
		if ( 'success' === ( $log['status'] ?? '' ) && ! empty( $log['stats'] ) ) {
			$s = $log['stats'];
			printf(
				'<tr><th>%s</th><td>%s</td></tr>',
				esc_html__( 'Counts', 'weticket-sync' ),
				esc_html(
					sprintf(
						/* translators: 1: total, 2: created, 3: updated, 4: removed. */
						__( '%1$d in feed · %2$d created · %3$d updated · %4$d removed', 'weticket-sync' ),
						$s['total'] ?? 0,
						$s['created'] ?? 0,
						$s['updated'] ?? 0,
						$s['removed'] ?? 0
					)
				)
			);
		}
		printf(
			'<tr><th>%s</th><td>%s</td></tr>',
			esc_html__( 'Next scheduled run', 'weticket-sync' ),
			$next ? esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $next ), 'Y-m-d H:i:s' ) ) : esc_html__( 'Not scheduled', 'weticket-sync' )
		);
		echo '</tbody></table>';
	}
}
