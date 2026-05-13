<?php
/**
 * Shared data queries for CiteWP dashboard surfaces.
 *
 * Used by both the WP Dashboard widget (DashboardWidget) and the
 * plugin's own Dashboard admin page (Menu::render_dashboard).
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Admin;

use CiteWP\Aiso\Database\Schema;
use CiteWP\Aiso\Scoring\Repository;

defined( 'ABSPATH' ) || exit;

final class DashboardData {

	public function get_average_score(): ?int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Admin stat; real-time data, intentionally uncached.
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ROUND( AVG( score ) )
				 FROM (
				     SELECT CAST( pm.meta_value AS UNSIGNED ) AS score
				     FROM {$wpdb->postmeta} pm
				     INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				     LEFT JOIN {$wpdb->postmeta} excl
				            ON excl.post_id = pm.post_id
				           AND excl.meta_key = '_citewp_aiso_exclude_from_llms'
				     WHERE pm.meta_key = %s
				       AND p.post_status = 'publish'
				       AND p.post_type IN ('post', 'page')
				       AND ( excl.meta_value IS NULL OR excl.meta_value != '1' )
				 ) AS included_scores",
				Repository::META_KEY_TOTAL
			)
		);

		return $result !== null ? (int) $result : null;
	}

	/**
	 * @return array<int, object>
	 */
	public function get_top_crawled_pages(): array {
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
	public function get_visit_trend(): array {
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

	public function get_unique_bot_count(): int {
		global $wpdb;

		$table     = esc_sql( Schema::table( Schema::TABLE_CRAWLER_LOGS ) );
		$seven_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; $table is esc_sql() of a hardcoded constant. Real-time widget data.
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT bot_name) FROM {$table} WHERE detected_at >= %s",
				$seven_ago
			)
		);
		// phpcs:enable

		return $count;
	}

	/**
	 * @return \WP_Post[]
	 */
	public function get_lowest_scoring_posts(): array {
		$posts = get_posts(
			[
				'post_type'      => [ 'post', 'page' ],
				'post_status'    => 'publish',
				'posts_per_page' => 3,
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
				'meta_key'       => Repository::META_KEY_TOTAL, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Intentional; orderby meta_value_num requires meta_key.
				'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Exclude posts opted out of llms.txt.
					'relation' => 'OR',
					[ 'key' => '_citewp_aiso_exclude_from_llms', 'compare' => 'NOT EXISTS' ],
					[ 'key' => '_citewp_aiso_exclude_from_llms', 'value' => '1', 'compare' => '!=' ],
				],
			]
		);

		return is_array( $posts ) ? $posts : [];
	}

	public function get_issue_count(): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Admin stat; real-time data, intentionally uncached.
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT pm.post_id)
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 LEFT JOIN {$wpdb->postmeta} excl
				        ON excl.post_id = pm.post_id
				       AND excl.meta_key = '_citewp_aiso_exclude_from_llms'
				 WHERE pm.meta_key = %s
				   AND pm.meta_value IN ('red','orange')
				   AND p.post_status = 'publish'
				   AND p.post_type IN ('post','page')
				   AND ( excl.meta_value IS NULL OR excl.meta_value != '1' )",
				Repository::META_KEY_GRADE
			)
		);
		return (int) ( $result ?? 0 );
	}

	public function get_excluded_count(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Admin stat; real-time data, intentionally uncached.
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT pm.post_id)
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = %s
				   AND pm.meta_value = '1'
				   AND p.post_status = 'publish'
				   AND p.post_type IN ('post','page')",
				'_citewp_aiso_exclude_from_llms'
			)
		);
		return (int) ( $result ?? 0 );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function get_top_crawlers( int $limit = 5 ): array {
		global $wpdb;

		$table        = esc_sql( Schema::table( Schema::TABLE_CRAWLER_LOGS ) );
		$since        = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
		$prior_start  = gmdate( 'Y-m-d H:i:s', strtotime( '-14 days' ) );
		$prior_end    = $since;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; $table is esc_sql() of a hardcoded constant. Real-time widget data.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT bot_name, COUNT(*) AS visits
				 FROM {$table}
				 WHERE detected_at >= %s
				 GROUP BY bot_name
				 ORDER BY visits DESC
				 LIMIT %d",
				$since,
				$limit
			),
			ARRAY_A
		);

		$prior_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT bot_name, COUNT(*) AS visits
				 FROM {$table}
				 WHERE detected_at >= %s AND detected_at < %s
				 GROUP BY bot_name",
				$prior_start,
				$prior_end
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( empty( $rows ) ) {
			return [];
		}

		// Index prior-period counts by bot_name for O(1) lookup.
		$prior_map = [];
		if ( is_array( $prior_rows ) ) {
			foreach ( $prior_rows as $prior_row ) {
				$prior_map[ $prior_row['bot_name'] ] = (int) $prior_row['visits'];
			}
		}

		$display_map = [
			'GPTBot'        => [ 'label' => 'GPTBot',      'class' => 'citewp-bot--gpt' ],
			'ClaudeBot'     => [ 'label' => 'Claude',      'class' => 'citewp-bot--claude' ],
			'Claude-Web'    => [ 'label' => 'Claude',      'class' => 'citewp-bot--claude' ],
			'PerplexityBot' => [ 'label' => 'Perplexity',  'class' => 'citewp-bot--perp' ],
			'Googlebot'     => [ 'label' => 'Google',      'class' => 'citewp-bot--google' ],
			'Bingbot'       => [ 'label' => 'Bing',        'class' => 'citewp-bot--default' ],
		];

		$bot_type_map = [
			'GPTBot'          => 'Search Engine',
			'ChatGPT-User'    => 'AI Assistant',
			'ClaudeBot'       => 'AI Assistant',
			'Claude-Web'      => 'AI Assistant',
			'PerplexityBot'   => 'AI Assistant',
			'Googlebot'       => 'Search Engine',
			'Google-Extended' => 'Search Engine',
			'Bingbot'         => 'AI Assistant',
		];

		$out = [];
		foreach ( $rows as $row ) {
			$bot_name = $row['bot_name'] ?? '';
			$meta     = $display_map[ $bot_name ] ?? [ 'label' => $bot_name, 'class' => 'citewp-bot--default' ];
			$out[] = [
				'bot_name'     => $bot_name,
				'display_name' => $meta['label'],
				'visits'       => (int) $row['visits'],
				'initial'      => strtoupper( substr( $meta['label'], 0, 1 ) ),
				'color_class'  => $meta['class'],
				'bot_type'     => $bot_type_map[ $bot_name ] ?? 'AI Crawler',
				'prior_visits' => $prior_map[ $bot_name ] ?? 0,
			];
		}
		return $out;
	}
}
