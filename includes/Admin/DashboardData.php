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

	public function get_scored_count(): int {
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
				   AND p.post_status = 'publish'
				   AND p.post_type IN ('post','page')
				   AND ( excl.meta_value IS NULL OR excl.meta_value != '1' )",
				Repository::META_KEY_TOTAL
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

	/**
	 * Returns per-day visit counts, optionally broken down by top-N bots.
	 *
	 * When $top_n is null (sparkline mode) each entry contains only:
	 *   [ 'date' => 'YYYY-MM-DD', 'sum' => N ]
	 *
	 * When $top_n is an integer each entry contains:
	 *   [ 'date' => 'YYYY-MM-DD', 'totals' => [ 'BotName' => N, ... ], 'other' => N, 'sum' => N ]
	 *
	 * @param int      $days  Number of days to look back (clamped to >= 1).
	 * @param int|null $top_n Number of top bots to break out; null = sparkline mode.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_visits_by_day( int $days, ?int $top_n = 5 ): array {
		global $wpdb;

		// Defensive clamping.
		$days  = max( 1, $days );
		$top_n = ( $top_n !== null ) ? max( 0, $top_n ) : null;

		$table_name = esc_sql( Schema::table( Schema::TABLE_CRAWLER_LOGS ) );
		$cutoff     = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; $table_name is esc_sql() of a hardcoded constant. Admin-only, real-time data.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(detected_at) AS day, bot_name, COUNT(*) AS visits
				 FROM {$table_name}
				 WHERE detected_at >= %s
				 GROUP BY day, bot_name
				 ORDER BY day ASC",
				$cutoff
			),
			ARRAY_A
		);
		// phpcs:enable

		// Build the zero-fill date range: oldest day first, up to and including today.
		$today      = wp_date( 'Y-m-d' );
		$start_date = gmdate( 'Y-m-d', strtotime( $today . ' -' . ( $days - 1 ) . ' days' ) );

		$date_range = [];
		$cursor     = $start_date;
		while ( $cursor <= $today ) {
			$date_range[] = $cursor;
			$cursor       = gmdate( 'Y-m-d', strtotime( $cursor . ' +1 day' ) );
		}

		// ── Sparkline path ($top_n === null) ────────────────────────────────────
		if ( $top_n === null ) {
			// Aggregate: total visits per day.
			$day_sums = [];
			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					$day                 = $row['day'];
					$day_sums[ $day ]    = ( $day_sums[ $day ] ?? 0 ) + (int) $row['visits'];
				}
			}

			$output = [];
			foreach ( $date_range as $date ) {
				$output[] = [
					'date' => $date,
					'sum'  => $day_sums[ $date ] ?? 0,
				];
			}
			return $output;
		}

		// ── Top-N breakdown path ─────────────────────────────────────────────────

		// Build intermediate map: $map[day][bot_name] = visits.
		$map = [];
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$day      = $row['day'];
				$bot      = $row['bot_name'];
				$visits   = (int) $row['visits'];
				if ( ! isset( $map[ $day ] ) ) {
					$map[ $day ] = [];
				}
				$map[ $day ][ $bot ] = ( $map[ $day ][ $bot ] ?? 0 ) + $visits;
			}
		}

		// Compute total visits per bot across ALL days.
		$bot_totals = [];
		foreach ( $map as $day_bots ) {
			foreach ( $day_bots as $bot => $visits ) {
				$bot_totals[ $bot ] = ( $bot_totals[ $bot ] ?? 0 ) + $visits;
			}
		}

		// Sort descending by total visits; tiebreaker: alpha ascending by bot_name.
		uksort(
			$bot_totals,
			function ( string $a, string $b ) use ( $bot_totals ): int {
				$diff = $bot_totals[ $b ] - $bot_totals[ $a ];
				if ( $diff !== 0 ) {
					return $diff;
				}
				return strcmp( $a, $b );
			}
		);

		// Select top-N bots.
		$top_bots = array_slice( array_keys( $bot_totals ), 0, $top_n );

		// Build output with zero-filling.
		$output = [];
		foreach ( $date_range as $date ) {
			$day_bots = $map[ $date ] ?? [];

			$totals = [];
			$other  = 0;
			$sum    = 0;

			// Tally top bots and others.
			foreach ( $day_bots as $bot => $visits ) {
				if ( in_array( $bot, $top_bots, true ) ) {
					$totals[ $bot ] = ( $totals[ $bot ] ?? 0 ) + $visits;
				} else {
					$other += $visits;
				}
				$sum += $visits;
			}

			// Zero-fill every top-bot key so the shape is consistent across all days.
			foreach ( $top_bots as $bot ) {
				if ( ! isset( $totals[ $bot ] ) ) {
					$totals[ $bot ] = 0;
				}
			}

			$output[] = [
				'date'   => $date,
				'totals' => $totals,
				'other'  => $other,
				'sum'    => $sum,
			];
		}

		return $output;
	}
}
