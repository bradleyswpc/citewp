<?php
/**
 * WordPress Dashboard widget.
 *
 * Shows average GEO score, bot visit trend, top crawled pages,
 * and lowest-scoring posts to prompt action.
 *
 * @package CiteWP
 */

declare( strict_types=1 );

namespace CiteWP\Admin;

use CiteWP\Database\Schema;
use CiteWP\Scoring\Repository;

defined( 'ABSPATH' ) || exit;

final class DashboardWidget {

	private const WIDGET_ID = 'citewp_dashboard_widget';

	public function register(): void {
		add_action( 'wp_dashboard_setup', [ $this, 'add_widget' ] );
		add_action( 'admin_head',         [ $this, 'inline_styles' ] );
	}

	public function add_widget(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			self::WIDGET_ID,
			__( 'CiteWP — GEO Overview', 'citewp' ),
			[ $this, 'render' ]
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$avg_score    = $this->get_average_score();
		$trend        = $this->get_visit_trend();
		$top_crawled  = $this->get_top_crawled_pages();
		$lowest_posts = $this->get_lowest_scoring_posts();
		$logs_url     = admin_url( 'admin.php?page=citewp-logs' );
		$all_posts_url = admin_url( 'edit.php?orderby=citewp_geo_score&order=asc' );

		$avg_grade = 'red';
		if ( $avg_score !== null ) {
			if ( $avg_score >= 80 ) {
				$avg_grade = 'green';
			} elseif ( $avg_score >= 60 ) {
				$avg_grade = 'yellow';
			} elseif ( $avg_score >= 40 ) {
				$avg_grade = 'orange';
			}
		}

		$this_week  = $trend['this_week'];
		$last_week  = $trend['last_week'];
		$diff       = $this_week - $last_week;
		$trend_icon = '';
		$trend_class = '';
		if ( $last_week > 0 ) {
			$pct = ( $diff / $last_week ) * 100;
			if ( $pct >= 5 ) {
				$trend_icon  = '▲';
				$trend_class = 'citewp-trend--up';
			} elseif ( $pct <= -5 ) {
				$trend_icon  = '▼';
				$trend_class = 'citewp-trend--down';
			} else {
				$trend_icon  = '—';
				$trend_class = 'citewp-trend--flat';
			}
		}
		?>
		<div class="citewp-widget">

			<div class="citewp-widget__stats">
				<div class="citewp-stat">
					<span class="citewp-stat__label"><?php esc_html_e( 'Avg GEO Score', 'citewp' ); ?></span>
					<?php if ( $avg_score !== null ) : ?>
						<span class="citewp-stat__value citewp-stat__value--<?php echo esc_attr( $avg_grade ); ?>"><?php echo esc_html( (string) $avg_score ); ?></span>
						<span class="citewp-stat__sub"><?php esc_html_e( 'across scored posts', 'citewp' ); ?></span>
					<?php else : ?>
						<span class="citewp-stat__value citewp-stat__value--none">—</span>
						<span class="citewp-stat__sub"><?php esc_html_e( 'No posts scored yet', 'citewp' ); ?></span>
					<?php endif; ?>
				</div>

				<div class="citewp-stat">
					<span class="citewp-stat__label"><?php esc_html_e( 'Bot Visits (7d)', 'citewp' ); ?></span>
					<span class="citewp-stat__value"><?php echo esc_html( number_format_i18n( $this_week ) ); ?></span>
					<span class="citewp-stat__sub">
						<?php if ( $trend_icon ) : ?>
							<span class="citewp-trend <?php echo esc_attr( $trend_class ); ?>"><?php echo esc_html( $trend_icon . ' ' . number_format_i18n( abs( $diff ) ) ); ?></span>
						<?php endif; ?>
						<?php esc_html_e( 'vs. prior 7 days', 'citewp' ); ?>
					</span>
				</div>
			</div>

			<?php if ( ! empty( $top_crawled ) ) : ?>
			<div class="citewp-widget__section">
				<h4 class="citewp-widget__heading"><?php esc_html_e( 'Most Crawled Pages (Last 7 Days)', 'citewp' ); ?></h4>
				<ul class="citewp-list">
					<?php foreach ( $top_crawled as $row ) : ?>
					<li class="citewp-list__item">
						<span class="citewp-list__count"><?php echo esc_html( number_format_i18n( (int) $row->visit_count ) ); ?></span>
						<span class="citewp-list__uri" title="<?php echo esc_attr( $row->request_uri ); ?>"><?php echo esc_html( $row->request_uri ); ?></span>
					</li>
					<?php endforeach; ?>
				</ul>
				<a href="<?php echo esc_url( $logs_url ); ?>" class="citewp-widget__link"><?php esc_html_e( 'View all crawler logs →', 'citewp' ); ?></a>
			</div>
			<?php else : ?>
			<p class="citewp-widget__empty">
				<?php esc_html_e( 'No bot visits logged yet.', 'citewp' ); ?>
				<a href="<?php echo esc_url( $logs_url ); ?>"><?php esc_html_e( 'View logs →', 'citewp' ); ?></a>
			</p>
			<?php endif; ?>

			<?php if ( ! empty( $lowest_posts ) ) : ?>
			<div class="citewp-widget__section">
				<h4 class="citewp-widget__heading"><?php esc_html_e( 'Lowest GEO Scores — Needs Attention', 'citewp' ); ?></h4>
				<ul class="citewp-list">
					<?php foreach ( $lowest_posts as $post ) : ?>
					<?php
					$score    = (int) get_post_meta( $post->ID, Repository::META_KEY_TOTAL, true );
					$grade    = get_post_meta( $post->ID, Repository::META_KEY_GRADE, true );
					$grade    = is_string( $grade ) && in_array( $grade, [ 'red', 'orange', 'yellow', 'green' ], true )
						? $grade : 'red';
					$edit_url = get_edit_post_link( $post->ID );
					?>
					<li class="citewp-list__item">
						<span class="citewp-score-badge citewp-score-badge--<?php echo esc_attr( $grade ); ?>"><?php echo esc_html( (string) $score ); ?></span>
						<?php if ( $edit_url ) : ?>
							<a href="<?php echo esc_url( $edit_url ); ?>" class="citewp-list__title"><?php echo esc_html( get_the_title( $post ) ); ?></a>
						<?php else : ?>
							<span class="citewp-list__title"><?php echo esc_html( get_the_title( $post ) ); ?></span>
						<?php endif; ?>
					</li>
					<?php endforeach; ?>
				</ul>
				<a href="<?php echo esc_url( $all_posts_url ); ?>" class="citewp-widget__link"><?php esc_html_e( 'See all post scores →', 'citewp' ); ?></a>
			</div>
			<?php endif; ?>

		</div>
		<?php
	}

	private function get_average_score(): ?int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin stat; real-time data, intentionally uncached.
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ROUND( AVG( CAST( pm.meta_value AS UNSIGNED ) ) )
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = %s
				   AND p.post_status = 'publish'
				   AND p.post_type IN ('post', 'page')",
				Repository::META_KEY_TOTAL
			)
		);

		return $result !== null ? (int) $result : null;
	}

	/**
	 * @return array<int, object>
	 */
	private function get_top_crawled_pages(): array {
		global $wpdb;

		$table = esc_sql( Schema::table( Schema::TABLE_CRAWLER_LOGS ) );
		$since = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; $table is esc_sql() of a hardcoded constant. Real-time widget data.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT request_uri, COUNT(*) AS visit_count
				 FROM {$table}
				 WHERE detected_at >= %s
				 GROUP BY request_uri
				 ORDER BY visit_count DESC
				 LIMIT 5",
				$since
			)
		);
		// phpcs:enable

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * @return array{this_week: int, last_week: int}
	 */
	private function get_visit_trend(): array {
		global $wpdb;

		$table        = esc_sql( Schema::table( Schema::TABLE_CRAWLER_LOGS ) );
		$now          = gmdate( 'Y-m-d H:i:s' );
		$seven_ago    = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
		$fourteen_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-14 days' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; $table is esc_sql() of a hardcoded constant. Real-time widget data.
		$this_week = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE detected_at >= %s AND detected_at < %s",
				$seven_ago,
				$now
			)
		);

		$last_week = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE detected_at >= %s AND detected_at < %s",
				$fourteen_ago,
				$seven_ago
			)
		);
		// phpcs:enable

		return [ 'this_week' => $this_week, 'last_week' => $last_week ];
	}

	/**
	 * @return \WP_Post[]
	 */
	private function get_lowest_scoring_posts(): array {
		$posts = get_posts(
			[
				'post_type'      => [ 'post', 'page' ],
				'post_status'    => 'publish',
				'posts_per_page' => 5,
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
				'meta_key'       => Repository::META_KEY_TOTAL, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Intentional; orderby meta_value_num requires meta_key.
			]
		);

		return is_array( $posts ) ? $posts : [];
	}

	public function inline_styles(): void {
		$screen = get_current_screen();
		if ( ! $screen || $screen->base !== 'dashboard' ) {
			return;
		}
		?>
		<style>
			.citewp-widget { font-size: 13px; }
			.citewp-widget__stats { display: flex; gap: 16px; margin-bottom: 16px; }
			.citewp-stat { flex: 1; background: #f9f9f9; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px 14px; }
			.citewp-stat__label { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; margin-bottom: 4px; }
			.citewp-stat__value { display: block; font-size: 28px; font-weight: 700; line-height: 1; color: #111827; }
			.citewp-stat__value--green  { color: #16a34a; }
			.citewp-stat__value--yellow { color: #ca8a04; }
			.citewp-stat__value--orange { color: #ea580c; }
			.citewp-stat__value--red    { color: #dc2626; }
			.citewp-stat__value--none   { color: #9ca3af; }
			.citewp-stat__sub { display: block; font-size: 11px; color: #6b7280; margin-top: 4px; }
			.citewp-trend--up   { color: #16a34a; font-weight: 600; }
			.citewp-trend--down { color: #dc2626; font-weight: 600; }
			.citewp-trend--flat { color: #6b7280; }
			.citewp-widget__section { margin-top: 14px; border-top: 1px solid #e5e7eb; padding-top: 12px; }
			.citewp-widget__heading { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #374151; margin: 0 0 8px; font-weight: 600; }
			.citewp-list { margin: 0 0 8px; padding: 0; list-style: none; }
			.citewp-list__item { display: flex; align-items: center; gap: 8px; padding: 4px 0; border-bottom: 1px solid #f3f4f6; }
			.citewp-list__item:last-child { border-bottom: none; }
			.citewp-list__count { min-width: 32px; font-weight: 700; font-variant-numeric: tabular-nums; color: #111827; text-align: right; }
			.citewp-list__uri { color: #4b5563; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 220px; font-family: monospace; font-size: 12px; }
			.citewp-list__title { color: #2271b1; text-decoration: none; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 230px; }
			.citewp-list__title:hover { text-decoration: underline; }
			.citewp-score-badge { min-width: 30px; text-align: center; font-weight: 700; font-size: 12px; padding: 2px 6px; border-radius: 4px; flex-shrink: 0; }
			.citewp-score-badge--green  { background: #dcfce7; color: #16a34a; }
			.citewp-score-badge--yellow { background: #fef9c3; color: #ca8a04; }
			.citewp-score-badge--orange { background: #ffedd5; color: #ea580c; }
			.citewp-score-badge--red    { background: #fee2e2; color: #dc2626; }
			.citewp-widget__link { font-size: 12px; color: #2271b1; text-decoration: none; }
			.citewp-widget__link:hover { text-decoration: underline; }
			.citewp-widget__empty { color: #6b7280; font-size: 12px; margin: 0; }
		</style>
		<?php
	}
}
