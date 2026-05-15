<?php
/**
 * WordPress Dashboard widget.
 *
 * Shows average GEO score, bot visit trend, top crawled pages,
 * and lowest-scoring posts to prompt action.
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Admin;

use CiteWP\Aiso\Database\Schema;
use CiteWP\Aiso\Scoring\Repository;

defined( 'ABSPATH' ) || exit;

final class DashboardWidget {

	private const WIDGET_ID = 'citewp_aiso_dashboard_widget';

	public function register(): void {
		add_action( 'wp_dashboard_setup', [ $this, 'add_widget' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function add_widget(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			self::WIDGET_ID,
			__( 'CiteWP — GEO Overview', 'ai-search-optimizer' ),
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
		$logs_url     = admin_url( 'admin.php?page=citewp-aiso-crawler-logs' );
		$all_posts_url = admin_url( 'edit.php?orderby=citewp_aiso_geo_score&order=asc' );

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
				$trend_class = 'citewp-aiso-trend--up';
			} elseif ( $pct <= -5 ) {
				$trend_icon  = '▼';
				$trend_class = 'citewp-aiso-trend--down';
			} else {
				$trend_icon  = '—';
				$trend_class = 'citewp-aiso-trend--flat';
			}
		}
		?>
		<div class="citewp-aiso-widget">

			<div class="citewp-aiso-widget__stats">
				<div class="citewp-aiso-stat">
					<span class="citewp-aiso-stat__label"><?php esc_html_e( 'Avg GEO Score', 'ai-search-optimizer' ); ?></span>
					<?php if ( $avg_score !== null ) : ?>
						<span class="citewp-aiso-stat__value citewp-aiso-stat__value--<?php echo esc_attr( $avg_grade ); ?>"><?php echo esc_html( (string) $avg_score ); ?></span>
						<span class="citewp-aiso-stat__sub"><?php esc_html_e( 'across scored posts', 'ai-search-optimizer' ); ?></span>
					<?php else : ?>
						<span class="citewp-aiso-stat__value citewp-aiso-stat__value--none">—</span>
						<span class="citewp-aiso-stat__sub"><?php esc_html_e( 'No posts scored yet', 'ai-search-optimizer' ); ?></span>
					<?php endif; ?>
				</div>

				<div class="citewp-aiso-stat">
					<span class="citewp-aiso-stat__label"><?php esc_html_e( 'Bot Visits (7d)', 'ai-search-optimizer' ); ?></span>
					<span class="citewp-aiso-stat__value"><?php echo esc_html( number_format_i18n( $this_week ) ); ?></span>
					<span class="citewp-aiso-stat__sub">
						<?php if ( $trend_icon ) : ?>
							<span class="citewp-aiso-trend <?php echo esc_attr( $trend_class ); ?>"><?php echo esc_html( $trend_icon . ' ' . number_format_i18n( abs( $diff ) ) ); ?></span>
						<?php endif; ?>
						<?php esc_html_e( 'vs. prior 7 days', 'ai-search-optimizer' ); ?>
					</span>
				</div>
			</div>

			<?php if ( ! empty( $top_crawled ) ) : ?>
			<div class="citewp-aiso-widget__section">
				<h4 class="citewp-aiso-widget__heading"><?php esc_html_e( 'Most Crawled Pages (Last 7 Days)', 'ai-search-optimizer' ); ?></h4>
				<ul class="citewp-aiso-list">
					<?php foreach ( $top_crawled as $row ) : ?>
					<li class="citewp-aiso-list__item">
						<span class="citewp-aiso-list__count"><?php echo esc_html( number_format_i18n( (int) $row->visit_count ) ); ?></span>
						<span class="citewp-aiso-list__uri" title="<?php echo esc_attr( $row->request_uri ); ?>"><?php echo esc_html( $row->request_uri ); ?></span>
					</li>
					<?php endforeach; ?>
				</ul>
				<a href="<?php echo esc_url( $logs_url ); ?>" class="citewp-aiso-widget__link"><?php esc_html_e( 'View all crawler logs →', 'ai-search-optimizer' ); ?></a>
			</div>
			<?php else : ?>
			<p class="citewp-aiso-widget__empty">
				<?php esc_html_e( 'No bot visits logged yet.', 'ai-search-optimizer' ); ?>
				<a href="<?php echo esc_url( $logs_url ); ?>"><?php esc_html_e( 'View logs →', 'ai-search-optimizer' ); ?></a>
			</p>
			<?php endif; ?>

			<?php if ( ! empty( $lowest_posts ) ) : ?>
			<div class="citewp-aiso-widget__section">
				<h4 class="citewp-aiso-widget__heading"><?php esc_html_e( 'Lowest GEO Scores — Needs Attention', 'ai-search-optimizer' ); ?></h4>
				<ul class="citewp-aiso-list">
					<?php foreach ( $lowest_posts as $post ) : ?>
					<?php
					$score    = (int) get_post_meta( $post->ID, Repository::META_KEY_TOTAL, true );
					$grade    = get_post_meta( $post->ID, Repository::META_KEY_GRADE, true );
					$grade    = is_string( $grade ) && in_array( $grade, [ 'red', 'orange', 'yellow', 'green' ], true )
						? $grade : 'red';
					$edit_url = get_edit_post_link( $post->ID );
					?>
					<li class="citewp-aiso-list__item">
						<span class="citewp-aiso-score-badge citewp-aiso-score-badge--<?php echo esc_attr( $grade ); ?>"><?php echo esc_html( (string) $score ); ?></span>
						<?php if ( $edit_url ) : ?>
							<a href="<?php echo esc_url( $edit_url ); ?>" class="citewp-aiso-list__title"><?php echo esc_html( get_the_title( $post ) ); ?></a>
						<?php else : ?>
							<span class="citewp-aiso-list__title"><?php echo esc_html( get_the_title( $post ) ); ?></span>
						<?php endif; ?>
					</li>
					<?php endforeach; ?>
				</ul>
				<a href="<?php echo esc_url( $all_posts_url ); ?>" class="citewp-aiso-widget__link"><?php esc_html_e( 'See all post scores →', 'ai-search-optimizer' ); ?></a>
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

	public function enqueue_assets( string $hook_suffix ): void {
		if ( $hook_suffix !== 'index.php' ) {
			return;
		}
		wp_enqueue_style(
			'citewp-aiso-dashboard-widget',
			CITEWP_AISO_PLUGIN_URL . 'admin/css/citewp-aiso-dashboard-widget.css',
			[],
			CITEWP_AISO_VERSION
		);
	}
}
