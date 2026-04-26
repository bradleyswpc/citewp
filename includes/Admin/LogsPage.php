<?php
/**
 * Crawler Logs admin page.
 *
 * @package CiteWP
 */

declare( strict_types=1 );

namespace CiteWP\Admin;

use CiteWP\Database\Schema;

defined( 'ABSPATH' ) || exit;

final class LogsPage {

	private ?LogsTable $table = null;

	public function register(): void {
		add_action( 'admin_init', [ $this, 'maybe_init_table' ] );
	}

	/**
	 * WP_List_Table must be loaded only when we're actually on its screen.
	 */
	public function maybe_init_table(): void {
		if ( ! is_admin() ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( $page !== Menu::SLUG_LOGS ) {
			return;
		}

		if ( ! class_exists( '\WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}
		require_once CITEWP_PLUGIN_DIR . 'includes/Admin/LogsTable.php';

		$this->table = new LogsTable();
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Counter for the page header.
		global $wpdb;
		$table_name = Schema::table( Schema::TABLE_CRAWLER_LOGS );
		$total      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );

		$last_24h = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE detected_at >= %s",
				gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS )
			)
		);

		if ( $this->table ) {
			$this->table->prepare_items();
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'AI Crawler Logs', 'citewp' ); ?></h1>
			<p class="description">
				<?php
				printf(
					/* translators: 1: total count, 2: 24h count */
					esc_html__( '%1$s total visits logged. %2$s in the last 24 hours.', 'citewp' ),
					'<strong>' . esc_html( number_format_i18n( $total ) ) . '</strong>',
					'<strong>' . esc_html( number_format_i18n( $last_24h ) ) . '</strong>'
				);
				?>
			</p>

			<?php if ( $total === 0 ) : ?>
				<div class="notice notice-info inline">
					<p>
						<?php esc_html_e( 'No AI crawler activity yet. Once GPTBot, ClaudeBot, PerplexityBot, or another AI crawler visits your site, you\'ll see it here.', 'citewp' ); ?>
					</p>
				</div>
			<?php elseif ( $this->table ) : ?>
				<form method="get">
					<input type="hidden" name="page" value="<?php echo esc_attr( Menu::SLUG_LOGS ); ?>" />
					<?php $this->table->display(); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}
}
