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
		add_action( 'admin_init',                    [ $this, 'maybe_init_table' ] );
		add_action( 'admin_head',                    [ $this, 'inline_styles' ] );
		add_action( 'admin_post_citewp_export_logs', [ $this, 'handle_csv_export' ] );
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

		global $wpdb;
		$table_name = Schema::table( Schema::TABLE_CRAWLER_LOGS );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin stats page; real-time data, intentionally uncached.
		$total     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count_24h = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE detected_at >= %s", gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ) ) );   // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count_7d  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE detected_at >= %s", gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) ) ) );   // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count_30d = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE detected_at >= %s", gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) ) ) );  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable

		$bot_filter   = isset( $_GET['citewp_bot'] )   ? sanitize_text_field( wp_unslash( $_GET['citewp_bot'] ) )   : '';
		$range_filter = isset( $_GET['citewp_range'] ) ? sanitize_key( wp_unslash( $_GET['citewp_range'] ) )         : '';

		$export_args = array_filter(
			[
				'action'       => 'citewp_export_logs',
				'citewp_bot'   => $bot_filter,
				'citewp_range' => $range_filter,
			]
		);
		$export_url = wp_nonce_url(
			add_query_arg( $export_args, admin_url( 'admin-post.php' ) ),
			'citewp_export_logs'
		);

		if ( $this->table ) {
			$this->table->prepare_items();
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'AI Crawler Logs', 'citewp' ); ?></h1>

			<div class="citewp-logs-banner">
				<div class="citewp-logs-stat">
					<span class="citewp-logs-stat__label"><?php esc_html_e( 'Last 24 hours', 'citewp' ); ?></span>
					<span class="citewp-logs-stat__value"><?php echo esc_html( number_format_i18n( $count_24h ) ); ?></span>
				</div>
				<div class="citewp-logs-stat">
					<span class="citewp-logs-stat__label"><?php esc_html_e( 'Last 7 days', 'citewp' ); ?></span>
					<span class="citewp-logs-stat__value"><?php echo esc_html( number_format_i18n( $count_7d ) ); ?></span>
				</div>
				<div class="citewp-logs-stat">
					<span class="citewp-logs-stat__label"><?php esc_html_e( 'Last 30 days', 'citewp' ); ?></span>
					<span class="citewp-logs-stat__value"><?php echo esc_html( number_format_i18n( $count_30d ) ); ?></span>
				</div>
				<div class="citewp-logs-banner__export">
					<a href="<?php echo esc_url( $export_url ); ?>" class="button">
						<?php esc_html_e( 'Export CSV', 'citewp' ); ?>
					</a>
				</div>
			</div>

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

	public function handle_csv_export(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'citewp' ) );
		}
		check_admin_referer( 'citewp_export_logs' );

		global $wpdb;
		$table = Schema::table( Schema::TABLE_CRAWLER_LOGS );

		$bot_filter   = isset( $_GET['citewp_bot'] )   ? sanitize_text_field( wp_unslash( $_GET['citewp_bot'] ) )   : '';
		$range_filter = isset( $_GET['citewp_range'] ) ? sanitize_key( wp_unslash( $_GET['citewp_range'] ) )         : '';

		// Validate bot against actual DB values.
		if ( $bot_filter !== '' ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE bot_name = %s LIMIT 1", $bot_filter ) );
			if ( ! $exists ) {
				$bot_filter = '';
			}
		}

		// Whitelist range values.
		if ( ! in_array( $range_filter, [ '24h', '7d', '30d' ], true ) ) {
			$range_filter = '';
		}

		$since = match ( $range_filter ) {
			'24h'   => gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
			'7d'    => gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) ),
			'30d'   => gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) ),
			default => null,
		};

		$where       = '';
		$filter_args = [];

		if ( $bot_filter !== '' ) {
			$where        .= ' AND bot_name = %s';
			$filter_args[] = $bot_filter;
		}
		if ( $since !== null ) {
			$where        .= ' AND detected_at >= %s';
			$filter_args[] = $since;
		}

		$select = "SELECT detected_at, bot_name, bot_vendor, request_uri, ip_address, user_agent FROM {$table}";

		if ( ! empty( $filter_args ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $where built from whitelisted values.
			$rows = $wpdb->get_results(
				$wpdb->prepare( "{$select} WHERE 1=1 {$where} ORDER BY detected_at DESC", ...$filter_args ),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results( "{$select} ORDER BY detected_at DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		$rows     = is_array( $rows ) ? $rows : [];
		$filename = 'citewp-crawler-logs-' . gmdate( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );
		if ( $output === false ) {
			exit;
		}

		// UTF-8 BOM for Excel compatibility.
		fwrite( $output, "\xEF\xBB\xBF" );

		fputcsv( $output, [ 'Detected At (Local)', 'Bot Name', 'Bot Vendor', 'Request URI', 'IP Address', 'User Agent' ] );

		foreach ( $rows as $row ) {
			fputcsv(
				$output,
				[
					get_date_from_gmt( $row['detected_at'], 'Y-m-d H:i:s' ),
					$row['bot_name'],
					$row['bot_vendor'],
					$row['request_uri'],
					$row['ip_address'],
					$row['user_agent'],
				]
			);
		}

		fclose( $output );
		exit;
	}

	public function inline_styles(): void {
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, Menu::SLUG_LOGS ) === false ) {
			return;
		}
		?>
		<style>
			.citewp-logs-banner { display: flex; align-items: center; gap: 12px; margin: 16px 0; flex-wrap: wrap; }
			.citewp-logs-stat { background: #f9f9f9; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 16px; min-width: 110px; }
			.citewp-logs-stat__label { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; margin-bottom: 2px; }
			.citewp-logs-stat__value { display: block; font-size: 22px; font-weight: 700; color: #111827; line-height: 1; }
			.citewp-logs-banner__export { margin-left: auto; }
		</style>
		<?php
	}
}
