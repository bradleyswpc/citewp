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

		// Read filters before KPI queries so the WHERE clause is available.
		$bot_filter   = isset( $_GET['citewp_aiso_bot'] )   ? sanitize_text_field( wp_unslash( $_GET['citewp_aiso_bot'] ) )   : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filter; no data modification.
		$range_filter = isset( $_GET['citewp_aiso_range'] ) ? sanitize_key( wp_unslash( $_GET['citewp_aiso_range'] ) )         : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filter; no data modification.
		if ( ! in_array( $range_filter, [ '24h', '7d', '30d' ], true ) ) {
			$range_filter = '';
		}

		$range = $this->range_clause( $range_filter );
		$where = $range['where'];

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin stats; $table_name is esc_sql() of a hardcoded constant; $where is output of $wpdb->prepare(). Real-time data, intentionally uncached.
		$total         = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE 1=1{$where}" );                      // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$unique_bots   = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT bot_name) FROM {$table_name} WHERE 1=1{$where}" );     // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$pages_crawled = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT request_uri) FROM {$table_name} WHERE 1=1{$where}" );  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable

		$trend_total = null;
		$trend_bots  = null;
		$trend_pages = null;

		if ( $range['days'] !== null ) {
			$period_start = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $range['days'] . ' days' ) );
			$prior_start  = $range['cutoff'];

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prior-period trend stats; $table_name is esc_sql() of a hardcoded constant. Admin-only, real-time data.
			$prior_total  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE detected_at >= %s AND detected_at < %s", $prior_start, $period_start ) );                      // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$prior_bots   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT bot_name) FROM {$table_name} WHERE detected_at >= %s AND detected_at < %s", $prior_start, $period_start ) );     // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$prior_pages  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT request_uri) FROM {$table_name} WHERE detected_at >= %s AND detected_at < %s", $prior_start, $period_start ) );  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:enable

			$trend_total = $this->compute_trend( $total, $prior_total );
			$trend_bots  = $this->compute_trend( $unique_bots, $prior_bots );
			$trend_pages = $this->compute_trend( $pages_crawled, $prior_pages );
		}

		if ( $range['days'] === null ) {
			// All-time: divide by elapsed days since earliest visit.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin stats; $table_name is esc_sql() of a hardcoded constant.
			$earliest     = (string) $wpdb->get_var( "SELECT MIN(detected_at) FROM {$table_name}" );
			$ts           = $earliest ? strtotime( $earliest ) : false;
			$days_elapsed = ( $ts !== false ) ? max( 1, (int) ceil( ( time() - $ts ) / DAY_IN_SECONDS ) ) : 1;
			$avg_freq     = $total > 0 ? round( $total / $days_elapsed, 1 ) : 0.0;
			$freq_unit    = __( 'per day', 'ai-search-optimizer' );
		} elseif ( $range['days'] === 1 ) {
			$avg_freq  = $total > 0 ? round( $total / 24, 1 ) : 0.0;
			$freq_unit = __( 'per hour', 'ai-search-optimizer' );
		} else {
			$avg_freq  = $total > 0 ? round( $total / $range['days'], 1 ) : 0.0;
			$freq_unit = __( 'per day', 'ai-search-optimizer' );
		}

		// Chart data: use the same $range window; cap all-time to 30 days (chart needs a bounded window).
		$chart_days = $range['days'] ?? 30;
		$chart_data = ( new DashboardData() )->get_visits_by_day( $chart_days, 5 );

		// Resolve canonical bot slot order: sum visits per bot across the window, sort desc.
		$bot_sums = [];
		foreach ( $chart_data as $day ) {
			if ( isset( $day['totals'] ) ) {
				foreach ( $day['totals'] as $bot => $v ) {
					$bot_sums[ $bot ] = ( $bot_sums[ $bot ] ?? 0 ) + (int) $v;
				}
			}
		}
		arsort( $bot_sums );
		$ordered_bots = array_keys( $bot_sums );

		// Max y value across all days.
		$chart_max = 0;
		foreach ( $chart_data as $day ) {
			$chart_max = max( $chart_max, $day['sum'] );
		}
		$chart_is_empty = ( $chart_max === 0 );

		// SVG coordinate helpers (fixed viewBox 600×180).
		$vw      = 600;
		$vh      = 180;
		$pad_top = 12;
		$pad_bot = 4;
		$chart_h = $vh - $pad_top - $pad_bot; // 164
		$n_days  = count( $chart_data );

		$x_pos = function ( int $i ) use ( $n_days, $vw ): float {
			return $n_days > 1 ? round( $i / ( $n_days - 1 ) * $vw, 2 ) : $vw / 2.0;
		};
		$y_pos = function ( int $v ) use ( $chart_max, $pad_top, $chart_h ): float {
			if ( $chart_max === 0 ) {
				return (float) ( $pad_top + $chart_h );
			}
			return round( $pad_top + ( 1.0 - $v / $chart_max ) * $chart_h, 2 );
		};

		// Build stacked layer paths — bottom→top: slot 1 (highest visits) at bottom.
		$chart_layers = [];
		$cum          = array_fill( 0, $n_days, 0 );

		foreach ( array_values( $ordered_bots ) as $slot_idx => $bot_name ) {
			$lower = $cum;
			foreach ( $chart_data as $day_idx => $day ) {
				$cum[ $day_idx ] += (int) ( $day['totals'][ $bot_name ] ?? 0 );
			}
			$upper = $cum;

			$fwd = [];
			for ( $i = 0; $i < $n_days; $i++ ) {
				$fwd[] = $x_pos( $i ) . ',' . $y_pos( $upper[ $i ] );
			}
			$bwd = [];
			for ( $i = $n_days - 1; $i >= 0; $i-- ) {
				$bwd[] = $x_pos( $i ) . ',' . $y_pos( $lower[ $i ] );
			}
			$chart_layers[] = [
				'color_var' => '--citewp-bot-' . ( $slot_idx + 1 ),
				'bot_name'  => $bot_name,
				'path'      => 'M ' . implode( ' L ', $fwd ) . ' L ' . implode( ' L ', $bwd ) . ' Z',
			];
		}

		// 'other' layer — only if any day has other > 0.
		$has_other = false;
		foreach ( $chart_data as $day ) {
			if ( ( $day['other'] ?? 0 ) > 0 ) {
				$has_other = true;
				break;
			}
		}
		if ( $has_other ) {
			$other_lower = $cum;
			foreach ( $chart_data as $day_idx => $day ) {
				$cum[ $day_idx ] += (int) ( $day['other'] ?? 0 );
			}
			$other_upper = $cum;

			$fwd = [];
			for ( $i = 0; $i < $n_days; $i++ ) {
				$fwd[] = $x_pos( $i ) . ',' . $y_pos( $other_upper[ $i ] );
			}
			$bwd = [];
			for ( $i = $n_days - 1; $i >= 0; $i-- ) {
				$bwd[] = $x_pos( $i ) . ',' . $y_pos( $other_lower[ $i ] );
			}
			$chart_layers[] = [
				'color_var' => '--citewp-bot-other',
				'bot_name'  => 'other',
				'path'      => 'M ' . implode( ' L ', $fwd ) . ' L ' . implode( ' L ', $bwd ) . ' Z',
			];
		}

		// X-axis label density.
		if ( $n_days <= 7 ) {
			$label_step = 1;
		} elseif ( $n_days <= 30 ) {
			$label_step = 5;
		} else {
			$label_step = 15;
		}

		// Base URL for date range filter pills.
		$base_url = add_query_arg(
			[ 'page' => Menu::SLUG_PARENT, 'citewp_section' => 'crawler-logs' ],
			admin_url( 'admin.php' )
		);

		$range_options = [
			''    => __( 'All time', 'ai-search-optimizer' ),
			'24h' => __( 'Last 24h', 'ai-search-optimizer' ),
			'7d'  => __( '7 days', 'ai-search-optimizer' ),
			'30d' => __( '30 days', 'ai-search-optimizer' ),
		];

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
		<div class="citewp-aiso-page-header">
			<div class="citewp-aiso-page-header__left">
				<h1 class="citewp-aiso-page-header__title"><?php esc_html_e( 'Crawler Logs', 'ai-search-optimizer' ); ?></h1>
				<p class="citewp-aiso-page-header__desc"><?php esc_html_e( 'AI crawler activity on your site.', 'ai-search-optimizer' ); ?></p>
			</div>
			<div class="citewp-aiso-page-header__right">
				<div class="citewp-aiso-filter-pills">
					<?php foreach ( $range_options as $value => $label ) :
						$is_active  = $range_filter === $value;
						$pill_url   = $value === ''
							? $base_url
							: add_query_arg( 'citewp_aiso_range', $value, $base_url );
						$pill_class = $is_active
							? 'citewp-aiso-filter-pill citewp-aiso-filter-pill--active'
							: 'citewp-aiso-filter-pill citewp-aiso-filter-pill--inactive';
					?>
						<a href="<?php echo esc_url( $pill_url ); ?>" class="<?php echo esc_attr( $pill_class ); ?>">
							<?php echo esc_html( $label ); ?>
						</a>
					<?php endforeach; ?>
				</div>
				<a href="<?php echo esc_url( $export_url ); ?>" class="citewp-aiso-btn citewp-aiso-btn--secondary">
					<?php esc_html_e( 'Export CSV', 'ai-search-optimizer' ); ?>
				</a>
			</div>
		</div>
		<div class="citewp-aiso-page-body">

			<div class="citewp-aiso-kpi-row citewp-aiso-kpi-row--4col">

				<div class="citewp-aiso-kpi-card">
					<div class="citewp-aiso-kpi-card__head">
						<div class="citewp-aiso-kpi-card__orb citewp-aiso-kpi-card__orb--blue"><?php echo IconLibrary::icon( 'search', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary returns trusted SVG markup. ?></div>
						<span class="citewp-aiso-kpi-card__head-title"><?php esc_html_e( 'Total Crawls', 'ai-search-optimizer' ); ?></span>
					</div>
					<p class="citewp-aiso-kpi-card__value">
						<?php echo esc_html( number_format_i18n( $total ) ); ?>
						<?php echo wp_kses( $this->render_trend_badge( $trend_total ), [ 'span' => [ 'class' => [] ] ] ); ?>
					</p>
					<p class="citewp-aiso-kpi-card__caption">
						<?php echo esc_html( sprintf( /* translators: %s: date range label e.g. "All time", "Last 24h" */ __( '%s AI crawler visits', 'ai-search-optimizer' ), $range['label'] ) ); ?>
					</p>
				</div>

				<div class="citewp-aiso-kpi-card">
					<div class="citewp-aiso-kpi-card__head">
						<div class="citewp-aiso-kpi-card__orb citewp-aiso-kpi-card__orb--purple"><?php echo IconLibrary::icon( 'bot', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary returns trusted SVG markup. ?></div>
						<span class="citewp-aiso-kpi-card__head-title"><?php esc_html_e( 'Unique Bots', 'ai-search-optimizer' ); ?></span>
					</div>
					<p class="citewp-aiso-kpi-card__value">
						<?php echo esc_html( number_format_i18n( $unique_bots ) ); ?>
						<?php echo wp_kses( $this->render_trend_badge( $trend_bots ), [ 'span' => [ 'class' => [] ] ] ); ?>
					</p>
					<p class="citewp-aiso-kpi-card__caption"><?php esc_html_e( 'Distinct AI engines detected', 'ai-search-optimizer' ); ?></p>
				</div>

				<div class="citewp-aiso-kpi-card">
					<div class="citewp-aiso-kpi-card__head">
						<div class="citewp-aiso-kpi-card__orb citewp-aiso-kpi-card__orb--teal"><?php echo IconLibrary::icon( 'eye', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary returns trusted SVG markup. ?></div>
						<span class="citewp-aiso-kpi-card__head-title"><?php esc_html_e( 'Pages Crawled', 'ai-search-optimizer' ); ?></span>
					</div>
					<p class="citewp-aiso-kpi-card__value">
						<?php echo esc_html( number_format_i18n( $pages_crawled ) ); ?>
						<?php echo wp_kses( $this->render_trend_badge( $trend_pages ), [ 'span' => [ 'class' => [] ] ] ); ?>
					</p>
					<p class="citewp-aiso-kpi-card__caption"><?php esc_html_e( 'Unique URLs visited', 'ai-search-optimizer' ); ?></p>
				</div>

				<div class="citewp-aiso-kpi-card">
					<div class="citewp-aiso-kpi-card__head">
						<div class="citewp-aiso-kpi-card__orb citewp-aiso-kpi-card__orb--citrine"><?php echo IconLibrary::icon( 'calendar', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary returns trusted SVG markup. ?></div>
						<span class="citewp-aiso-kpi-card__head-title"><?php esc_html_e( 'Avg Frequency', 'ai-search-optimizer' ); ?></span>
					</div>
					<p class="citewp-aiso-kpi-card__value"><?php echo esc_html( $avg_freq . ' ' . $freq_unit ); ?></p>
					<p class="citewp-aiso-kpi-card__caption">
						<?php echo esc_html( sprintf( /* translators: %s: date range label e.g. "All time", "7 days" */ __( '%s average', 'ai-search-optimizer' ), $range['label'] ) ); ?>
					</p>
				</div>

			</div>

				<div class="citewp-aiso-crawler-row-2col">

					<!-- Left: Bot Visits Over Time chart -->
					<div class="citewp-aiso-cs-panel citewp-aiso-bot-visits-panel">
						<div class="citewp-aiso-cs-panel__head">
							<span class="citewp-aiso-cs-panel__title"><?php esc_html_e( 'Bot Visits Over Time', 'ai-search-optimizer' ); ?></span>
							<span class="citewp-aiso-kpi-tooltip citewp-aiso-kpi-tooltip--align-left">
								<?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary returns trusted SVG markup. ?>
								<span class="citewp-aiso-kpi-tooltip__text">
									<?php esc_html_e( 'AI crawler visits per day, stacked by bot. Responds to the date filter above.', 'ai-search-optimizer' ); ?>
								</span>
							</span>
						</div>

						<?php if ( $chart_is_empty ) : ?>
							<div class="citewp-aiso-bvot__empty">
								<svg viewBox="0 0 600 180" style="position:absolute;inset:0;width:100%;height:100%;opacity:.2" preserveAspectRatio="none" aria-hidden="true">
									<line x1="0" y1="90" x2="600" y2="90" stroke="var(--citewp-border)" stroke-width="1" stroke-dasharray="8 4"/>
								</svg>
								<p style="position:relative;z-index:1;"><?php esc_html_e( 'No crawler activity in this period.', 'ai-search-optimizer' ); ?></p>
							</div>
						<?php else : ?>
							<div class="citewp-aiso-bvot">
								<svg class="citewp-aiso-bvot__svg" viewBox="0 0 600 180" preserveAspectRatio="none" aria-hidden="true">
									<?php foreach ( $chart_layers as $layer ) : ?>
										<path d="<?php echo esc_attr( $layer['path'] ); ?>" fill="var(<?php echo esc_attr( $layer['color_var'] ); ?>)"/>
									<?php endforeach; ?>
								</svg>
								<div class="citewp-aiso-chart-xlabels">
									<?php foreach ( $chart_data as $i => $day ) :
										if ( $n_days > 1 && $i % $label_step !== 0 && $i !== $n_days - 1 ) {
											continue;
										}
										$left_pct  = $n_days > 1 ? round( $i / ( $n_days - 1 ) * 100, 2 ) : 50.0;
										$parts     = explode( '-', $day['date'] );
										$label_str = ltrim( $parts[1] ?? '', '0' ) . '/' . ltrim( $parts[2] ?? '', '0' );
									?>
										<span class="citewp-aiso-chart-xlabels__label" style="left:<?php echo esc_attr( $left_pct . '%' ); ?>">
											<?php echo esc_html( $label_str ); ?>
										</span>
									<?php endforeach; ?>
								</div>
							</div>

							<div class="citewp-aiso-bvot__legend">
								<?php foreach ( $chart_layers as $layer ) :
									if ( $layer['bot_name'] === 'other' ) {
										continue;
									}
								?>
									<span class="citewp-aiso-bvot__legend-item">
										<span class="citewp-aiso-bvot__legend-swatch" style="background:var(<?php echo esc_attr( $layer['color_var'] ); ?>)"></span>
										<?php echo esc_html( $layer['bot_name'] ); ?>
									</span>
								<?php endforeach; ?>
								<?php if ( $has_other ) : ?>
									<span class="citewp-aiso-bvot__legend-item">
										<span class="citewp-aiso-bvot__legend-swatch" style="background:var(--citewp-bot-other)"></span>
										<?php esc_html_e( 'Other', 'ai-search-optimizer' ); ?>
									</span>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>

					<!-- Right: empty placeholder — T2c (Top Crawled Pages) fills this cell -->
					<div></div>

				</div><!-- .citewp-aiso-crawler-row-2col -->

			<?php if ( $total === 0 && $range_filter === '' ) : ?>
				<div class="citewp-aiso-empty">
					<?php echo IconLibrary::icon( 'calendar', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary returns trusted SVG markup. ?>
					<p><?php esc_html_e( 'No AI crawler activity yet. Once GPTBot, ClaudeBot, PerplexityBot, or another AI crawler visits your site, you\'ll see it here.', 'ai-search-optimizer' ); ?></p>
				</div>
			<?php elseif ( $total === 0 && $range_filter !== '' ) : ?>
				<div class="citewp-aiso-empty">
					<?php echo IconLibrary::icon( 'calendar', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary returns trusted SVG markup. ?>
					<p><?php esc_html_e( 'No crawler activity in this period.', 'ai-search-optimizer' ); ?></p>
				</div>
			<?php elseif ( $this->table ) : ?>
				<div class="citewp-aiso-logs-table-card citewp-aiso-table-wrap">
					<form method="get">
						<input type="hidden" name="page" value="<?php echo esc_attr( Menu::SLUG_PARENT ); ?>" />
						<input type="hidden" name="citewp_section" value="crawler-logs" />
						<?php $this->table->display(); ?>
					</form>
				</div>
			<?php endif; ?>

			<div class="citewp-aiso-protip">
				<div class="citewp-aiso-protip__left">
					<div class="citewp-aiso-protip__orb"><?php echo IconLibrary::icon( 'sparkles', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					<div class="citewp-aiso-protip__content">
						<p class="citewp-aiso-protip__heading"><?php esc_html_e( 'Pro Tip', 'ai-search-optimizer' ); ?></p>
						<p class="citewp-aiso-protip__body"><?php esc_html_e( 'Frequent AI crawler visits signal your content is being actively indexed. Upgrade to CiteWP Pro for 1-year log retention and advanced bot analytics.', 'ai-search-optimizer' ); ?></p>
					</div>
				</div>
			</div>

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

	private function range_clause( string $range_filter ): array {
		global $wpdb;
		return match ( $range_filter ) {
			'24h' => [
				'where'  => $wpdb->prepare( ' AND detected_at >= %s', gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ) ),
				'label'  => __( 'Last 24h', 'ai-search-optimizer' ),
				'days'   => 1,
				'cutoff' => gmdate( 'Y-m-d H:i:s', strtotime( '-2 days' ) ),
			],
			'7d' => [
				'where'  => $wpdb->prepare( ' AND detected_at >= %s', gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) ) ),
				'label'  => __( '7 days', 'ai-search-optimizer' ),
				'days'   => 7,
				'cutoff' => gmdate( 'Y-m-d H:i:s', strtotime( '-14 days' ) ),
			],
			'30d' => [
				'where'  => $wpdb->prepare( ' AND detected_at >= %s', gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) ) ),
				'label'  => __( '30 days', 'ai-search-optimizer' ),
				'days'   => 30,
				'cutoff' => gmdate( 'Y-m-d H:i:s', strtotime( '-60 days' ) ),
			],
			default => [
				'where'  => '',
				'label'  => __( 'All time', 'ai-search-optimizer' ),
				'days'   => null,
				'cutoff' => null,
			],
		};
	}

	private function compute_trend( int $current, int $prior ): ?array {
		if ( $prior === 0 && $current > 0 ) {
			// Intentional: blank badge, not a bug. No prior-period baseline means a % would be meaningless (∞).
			return null;
		}
		if ( $prior === 0 ) {
			return [ 'pct' => 0, 'direction' => 'flat' ];
		}
		$pct = (int) round( ( ( $current - $prior ) / $prior ) * 100 );
		return [
			'pct'       => abs( $pct ),
			'direction' => $pct > 0 ? 'up' : ( $pct < 0 ? 'down' : 'flat' ),
		];
	}

	private function render_trend_badge( ?array $trend ): string {
		if ( $trend === null ) {
			return '';
		}
		if ( $trend['direction'] === 'flat' ) {
			return '<span class="citewp-aiso-kpi-card__trend citewp-aiso-kpi-card__trend--flat">→ 0%</span>';
		}
		$arrow = $trend['direction'] === 'up' ? '↑' : '↓';
		$cls   = $trend['direction'] === 'up' ? 'up' : 'down';
		return sprintf(
			'<span class="citewp-aiso-kpi-card__trend citewp-aiso-kpi-card__trend--%s">%s %d%%</span>',
			esc_attr( $cls ),
			$arrow,
			$trend['pct']
		);
	}

}
