<?php
/**
 * Crawler Logs admin page.
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Admin;

use CiteWP\Aiso\Database\Schema;

defined( 'ABSPATH' ) || exit;

final class LogsPage {

	private ?LogsTable $table = null;

	public function register(): void {
		add_action( 'admin_init',                    [ $this, 'maybe_init_table' ] );
		add_action( 'admin_post_citewp_aiso_export_logs', [ $this, 'handle_csv_export' ] );
	}

	/**
	 * WP_List_Table must be loaded only when we're actually on its screen.
	 */
	public function maybe_init_table(): void {
		if ( ! is_admin() ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET param to identify admin page; no data modification.
		if ( $page !== Menu::SLUG_PARENT ) {
			return;
		}

		if ( ! class_exists( '\WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}
		require_once CITEWP_AISO_PLUGIN_DIR . 'includes/Admin/LogsTable.php';

		$this->table = new LogsTable();
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;
		$table_name = esc_sql( Schema::table( Schema::TABLE_CRAWLER_LOGS ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin stats page; $table_name is esc_sql() of a hardcoded constant. Real-time data, intentionally uncached.
		$total     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count_24h = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE detected_at >= %s", gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ) ) );   // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count_7d  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE detected_at >= %s", gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) ) ) );   // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count_30d = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE detected_at >= %s", gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) ) ) );  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable

		$bot_filter   = isset( $_GET['citewp_aiso_bot'] )   ? sanitize_text_field( wp_unslash( $_GET['citewp_aiso_bot'] ) )   : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filter; no data modification.
		$range_filter = isset( $_GET['citewp_aiso_range'] ) ? sanitize_key( wp_unslash( $_GET['citewp_aiso_range'] ) )         : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filter; no data modification.

		$export_args = array_filter(
			[
				'action'            => 'citewp_aiso_export_logs',
				'citewp_aiso_bot'   => $bot_filter,
				'citewp_aiso_range' => $range_filter,
			]
		);
		$export_url = wp_nonce_url(
			add_query_arg( $export_args, admin_url( 'admin-post.php' ) ),
			'citewp_aiso_export_logs'
		);

		if ( $this->table ) {
			$this->table->prepare_items();
		}
		?>
		<div class="citewp-aiso-panel__title-row">
			<div>
				<h2><?php esc_html_e( 'Crawler Logs', 'ai-search-optimizer' ); ?></h2>
				<p class="citewp-aiso-panel__subtitle"><?php esc_html_e( 'AI crawler activity on your site.', 'ai-search-optimizer' ); ?></p>
			</div>
			<a href="<?php echo esc_url( $export_url ); ?>" class="citewp-aiso-btn citewp-aiso-btn--secondary">
				<?php esc_html_e( 'Export CSV', 'ai-search-optimizer' ); ?>
			</a>
		</div>
		<div class="citewp-aiso-page-body">

			<div class="citewp-aiso-kpi-row citewp-aiso-kpi-row--3col">
				<div class="citewp-aiso-kpi-card">
					<div class="citewp-aiso-kpi-card__orb citewp-aiso-kpi-card__orb--blue"><?php echo IconLibrary::icon( 'search', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary returns trusted SVG markup. ?></div>
					<div class="citewp-aiso-kpi-card__body">
						<p class="citewp-aiso-kpi-card__title"><?php esc_html_e( '24h Visits', 'ai-search-optimizer' ); ?></p>
						<p class="citewp-aiso-kpi-card__value"><?php echo esc_html( number_format_i18n( $count_24h ) ); ?></p>
					</div>
				</div>
				<div class="citewp-aiso-kpi-card">
					<div class="citewp-aiso-kpi-card__orb citewp-aiso-kpi-card__orb--blue"><?php echo IconLibrary::icon( 'search', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary returns trusted SVG markup. ?></div>
					<div class="citewp-aiso-kpi-card__body">
						<p class="citewp-aiso-kpi-card__title"><?php esc_html_e( '7d Visits', 'ai-search-optimizer' ); ?></p>
						<p class="citewp-aiso-kpi-card__value"><?php echo esc_html( number_format_i18n( $count_7d ) ); ?></p>
					</div>
				</div>
				<div class="citewp-aiso-kpi-card">
					<div class="citewp-aiso-kpi-card__orb citewp-aiso-kpi-card__orb--blue"><?php echo IconLibrary::icon( 'search', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary returns trusted SVG markup. ?></div>
					<div class="citewp-aiso-kpi-card__body">
						<p class="citewp-aiso-kpi-card__title"><?php esc_html_e( '30d Visits', 'ai-search-optimizer' ); ?></p>
						<p class="citewp-aiso-kpi-card__value"><?php echo esc_html( number_format_i18n( $count_30d ) ); ?></p>
					</div>
				</div>
			</div>

			<?php if ( $total === 0 ) : ?>
				<div class="citewp-aiso-empty">
					<?php echo IconLibrary::icon( 'calendar', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary returns trusted SVG markup. ?>
					<p><?php esc_html_e( 'No AI crawler activity yet. Once GPTBot, ClaudeBot, PerplexityBot, or another AI crawler visits your site, you\'ll see it here.', 'ai-search-optimizer' ); ?></p>
				</div>
			<?php elseif ( $this->table ) : ?>
				<div class="citewp-aiso-table-wrap">
					<form method="get">
						<input type="hidden" name="page" value="<?php echo esc_attr( Menu::SLUG_PARENT ); ?>" />
						<input type="hidden" name="citewp_section" value="crawler-logs" />
						<?php $this->table->display(); ?>
					</form>
				</div>
			<?php endif; ?>
		</div><!-- .citewp-aiso-page-body -->
		<?php
	}

	public function handle_csv_export(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'ai-search-optimizer' ) );
		}
		check_admin_referer( 'citewp_aiso_export_logs' );

		global $wpdb;
		$table = esc_sql( Schema::table( Schema::TABLE_CRAWLER_LOGS ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce already verified by check_admin_referer() above.
		$bot_filter   = isset( $_GET['citewp_aiso_bot'] )   ? sanitize_text_field( wp_unslash( $_GET['citewp_aiso_bot'] ) )   : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce already verified by check_admin_referer() above.
		$range_filter = isset( $_GET['citewp_aiso_range'] ) ? sanitize_key( wp_unslash( $_GET['citewp_aiso_range'] ) )         : '';

		// Validate bot against actual DB values.
		if ( $bot_filter !== '' ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Bot filter validation; $table is esc_sql() of a hardcoded constant.
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

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table export; $select/$where built from whitelisted values, $table is esc_sql(). Real-time admin data.
		if ( ! empty( $filter_args ) ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare( "{$select} WHERE 1=1 {$where} ORDER BY detected_at DESC", ...$filter_args ),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results( "{$select} ORDER BY detected_at DESC", ARRAY_A );
		}
		// phpcs:enable

		$rows     = is_array( $rows ) ? $rows : [];
		$filename = 'citewp-aiso-crawler-logs-' . gmdate( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// UTF-8 BOM for Excel compatibility. Streaming directly to output — no filesystem API needed.
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- csv_line() double-quotes and escapes all fields per RFC 4180; WP escaping functions would corrupt binary CSV output.
		echo "\xEF\xBB\xBF";
		echo $this->csv_line( [ 'Detected At (Local)', 'Bot Name', 'Bot Vendor', 'Request URI', 'IP Address', 'User Agent' ] );

		foreach ( $rows as $row ) {
			echo $this->csv_line(
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
		// phpcs:enable
		exit;
	}

	private function csv_line( array $fields ): string {
		$escaped = array_map(
			static function ( string $v ): string {
				return '"' . str_replace( '"', '""', $v ) . '"';
			},
			array_map( 'strval', $fields )
		);
		return implode( ',', $escaped ) . "\r\n";
	}

}
