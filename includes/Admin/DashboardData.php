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
use CiteWP\Aiso\Scoring\ScoreHistory;

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
	 * Returns the most-crawled URLs in the given window, with title resolution.
	 *
	 * @param string|null $cutoff MySQL datetime string (detected_at >= cutoff); null = all-time.
	 * @param int         $limit  Number of rows to return.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_top_crawled_pages( ?string $cutoff = null, int $limit = 5 ): array {
		global $wpdb;

		$table_name = esc_sql( Schema::table( Schema::TABLE_CRAWLER_LOGS ) );

		$sql  = "SELECT request_uri, COUNT(*) AS visits, COUNT(DISTINCT bot_name) AS bot_count
         FROM {$table_name}";
		$args = [];

		if ( $cutoff !== null ) {
			$sql   .= ' WHERE detected_at >= %s';
			$args[] = $cutoff;
		}

		$sql   .= ' GROUP BY request_uri ORDER BY visits DESC LIMIT %d';
		$args[] = $limit;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		// Reason: Custom table not queryable via WP_Query. No-cache acceptable for admin analytics. $table_name is esc_sql() of a hardcoded constant; $sql uses only prepare() placeholders.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$args ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- variadic args match placeholders exactly.
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return [];
		}

		// Resolve each URI to a post title in PHP (small N, no SQL join needed).
		$results = [];
		foreach ( $rows as $row ) {
			if ( $row['request_uri'] === '/' ) {
				$front_page_id = (int) get_option( 'page_on_front' );
				if ( $front_page_id > 0 ) {
					$post_id = $front_page_id;
					$title   = get_the_title( $front_page_id );
				} else {
					$post_id = 0;
					$title   = __( 'Homepage', 'ai-search-optimizer' );
				}
			} else {
				$uri     = ltrim( $row['request_uri'], '/' );
				$post_id = url_to_postid( home_url( $uri ) );
				$title   = $post_id > 0 ? get_the_title( $post_id ) : $row['request_uri'];
			}

			$results[] = [
				'request_uri' => $row['request_uri'],
				'visits'      => (int) $row['visits'],
				'bot_count'   => (int) $row['bot_count'],
				'post_id'     => $post_id,
				'title'       => $title,
			];
		}

		return $results;
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

		$now        = time();
		$table_name = esc_sql( Schema::table( Schema::TABLE_CRAWLER_LOGS ) );
		$cutoff     = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days", $now ) );

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
		$today      = gmdate( 'Y-m-d', $now );
		$start_date = gmdate( 'Y-m-d', $now - ( ( $days - 1 ) * DAY_IN_SECONDS ) );

		$date_range = [];
		$cursor     = $start_date;
		while ( $cursor <= $today ) {
			$date_range[] = $cursor;
			$cursor       = gmdate( 'Y-m-d', strtotime( $cursor ) + DAY_IN_SECONDS );
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
				return ( $bot_totals[ $b ] <=> $bot_totals[ $a ] ) ?: strcmp( $a, $b );
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

	/**
	 * Renders a minimal sparkline SVG for a daily-totals array (output of get_visits_by_day with $top_n = null).
	 *
	 * Callers must echo the return value directly with a phpcs:ignore EscapeOutput comment,
	 * since all dynamic SVG path data is escaped via esc_attr() internally.
	 *
	 * @param array<int, array<string, mixed>> $daily_totals Output of get_visits_by_day( $days, null ).
	 * @param string                           $variant      CSS modifier class suffix (e.g. 'bot-visits').
	 * @return string SVG markup string.
	 */
	public function render_sparkline_svg( array $daily_totals, string $variant = 'bot-visits' ): string {
		$n = count( $daily_totals );

		// Faint dashed baseline: keeps card height stable whether populated or empty.
		$empty_svg = '<svg class="citewp-aiso-kpi-card__sparkline citewp-aiso-kpi-card__sparkline--'
			. esc_attr( $variant )
			. '" viewBox="0 0 100 30" preserveAspectRatio="none" aria-hidden="true">'
			. '<line x1="0" y1="15" x2="100" y2="15" stroke="var(--citewp-border)" stroke-width="1" stroke-dasharray="4 2"/>'
			. '</svg>';

		if ( $n === 0 ) {
			return $empty_svg;
		}

		$sums = array_map(
			static function ( array $d ): int { return (int) ( $d['sum'] ?? 0 ); },
			$daily_totals
		);
		$max = max( $sums );

		if ( $max === 0 ) {
			return $empty_svg;
		}

		// Fixed coordinate space: 100 × 30.
		$vw      = 100.0;
		$vh      = 30.0;
		$pad     = 2.0;
		$chart_h = $vh - $pad * 2; // 26

		$pts = [];
		foreach ( $sums as $i => $v ) {
			$x     = $n > 1 ? round( $i / ( $n - 1 ) * $vw, 2 ) : $vw / 2.0;
			$y     = round( $pad + ( 1.0 - $v / $max ) * $chart_h, 2 );
			$pts[] = "$x,$y";
		}

		$first_x = 0.0;
		$last_x  = $n > 1 ? $vw : $vw / 2.0;
		$bot_y   = round( $vh - $pad, 2 );

		$line_d = 'M ' . implode( ' L ', $pts );
		$fill_d = $line_d . " L $last_x,$bot_y L $first_x,$bot_y Z";

		$cls = 'citewp-aiso-kpi-card__sparkline citewp-aiso-kpi-card__sparkline--' . esc_attr( $variant );

		return '<svg class="' . esc_attr( $cls ) . '" viewBox="0 0 100 30" preserveAspectRatio="none" aria-hidden="true">'
			. '<path class="fill" d="' . esc_attr( $fill_d ) . '"/>'
			. '<path class="line" d="' . esc_attr( $line_d ) . '"/>'
			. '</svg>';
	}

	/**
	 * Counts posts with red (critical) and orange (minor) score grades,
	 * excluding posts opted out of llms.txt per P49.
	 *
	 * @return array{critical: int, minor: int}
	 */
	public function get_issue_severity_counts(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Admin stat; real-time data, intentionally uncached.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_value AS grade, COUNT(DISTINCT pm.post_id) AS cnt
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 LEFT JOIN {$wpdb->postmeta} excl
				        ON excl.post_id = pm.post_id
				       AND excl.meta_key = '_citewp_aiso_exclude_from_llms'
				 WHERE pm.meta_key = %s
				   AND pm.meta_value IN ('red', 'orange')
				   AND p.post_status = 'publish'
				   AND p.post_type IN ('post', 'page')
				   AND ( excl.meta_value IS NULL OR excl.meta_value != '1' )
				 GROUP BY pm.meta_value",
				Repository::META_KEY_GRADE
			),
			ARRAY_A
		);

		$counts = [ 'critical' => 0, 'minor' => 0 ];
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( 'red' === $row['grade'] ) {
					$counts['critical'] = (int) $row['cnt'];
				} elseif ( 'orange' === $row['grade'] ) {
					$counts['minor'] = (int) $row['cnt'];
				}
			}
		}
		return $counts;
	}

	/**
	 * Averages per-category scores across all scored, non-excluded posts
	 * and returns each as a percentage of its maximum.
	 * (structure/35, citability/40, authority/25)
	 *
	 * @return array{structure: int, citability: int, authority: int}
	 */
	public function get_average_category_scores(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Admin stat; real-time data, intentionally uncached.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_value
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 LEFT JOIN {$wpdb->postmeta} excl
				        ON excl.post_id = pm.post_id
				       AND excl.meta_key = '_citewp_aiso_exclude_from_llms'
				 WHERE pm.meta_key = %s
				   AND p.post_status = 'publish'
				   AND p.post_type IN ('post', 'page')
				   AND ( excl.meta_value IS NULL OR excl.meta_value != '1' )",
				Repository::META_KEY_FULL
			),
			ARRAY_A
		);

		$structure_sum  = 0;
		$citability_sum = 0;
		$authority_sum  = 0;
		$count          = 0;

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$data = maybe_unserialize( $row['meta_value'] );
				if ( ! is_array( $data ) || ! isset( $data['categories'] ) ) {
					continue;
				}
				$structure_sum  += (int) ( $data['categories']['structure']['score']  ?? 0 );
				$citability_sum += (int) ( $data['categories']['citability']['score'] ?? 0 );
				$authority_sum  += (int) ( $data['categories']['authority']['score']  ?? 0 );
				++$count;
			}
		}

		if ( $count === 0 ) {
			return [ 'structure' => 0, 'citability' => 0, 'authority' => 0 ];
		}

		return [
			'structure'  => (int) round( ( $structure_sum  / $count ) / 35 * 100 ),
			'citability' => (int) round( ( $citability_sum / $count ) / 40 * 100 ),
			'authority'  => (int) round( ( $authority_sum  / $count ) / 25 * 100 ),
		];
	}

	/**
	 * Computes this-week vs last-week average score delta using ScoreHistory.
	 *
	 * @return array{delta: int}
	 */
	public function get_avg_score_trend(): array {
		$history       = ( new ScoreHistory() )->get_history( 14 );
		$seven_ago     = gmdate( 'Y-m-d', strtotime( '-7 days' ) );
		$this_week_sum = 0.0;
		$this_week_cnt = 0;
		$last_week_sum = 0.0;
		$last_week_cnt = 0;

		foreach ( $history as $entry ) {
			if ( $entry['date'] > $seven_ago ) {
				$this_week_sum += (float) $entry['avg'];
				++$this_week_cnt;
			} else {
				$last_week_sum += (float) $entry['avg'];
				++$last_week_cnt;
			}
		}

		if ( $this_week_cnt === 0 || $last_week_cnt === 0 ) {
			return [ 'delta' => 0 ];
		}

		$delta = ( $this_week_sum / $this_week_cnt ) - ( $last_week_sum / $last_week_cnt );
		return [ 'delta' => (int) round( $delta ) ];
	}

	/**
	 * For each red/orange-grade post, counts which category is weakest (lowest % of max).
	 * P49 exclusion applies.
	 *
	 * @return array{structure: int, citability: int, authority: int}
	 */
	public function get_issue_counts_by_category(): array {
		global $wpdb;

		$counts = [ 'structure' => 0, 'citability' => 0, 'authority' => 0 ];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Admin stat; real-time data, intentionally uncached.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT full_pm.meta_value
				 FROM {$wpdb->postmeta} full_pm
				 INNER JOIN {$wpdb->postmeta} grade_pm ON grade_pm.post_id = full_pm.post_id AND grade_pm.meta_key = %s
				 INNER JOIN {$wpdb->posts} p ON p.ID = full_pm.post_id
				 LEFT JOIN {$wpdb->postmeta} excl
				        ON excl.post_id = full_pm.post_id
				       AND excl.meta_key = '_citewp_aiso_exclude_from_llms'
				 WHERE full_pm.meta_key = %s
				   AND grade_pm.meta_value IN ('red', 'orange')
				   AND p.post_status = 'publish'
				   AND p.post_type IN ('post', 'page')
				   AND ( excl.meta_value IS NULL OR excl.meta_value != '1' )",
				Repository::META_KEY_GRADE,
				Repository::META_KEY_FULL
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return $counts;
		}

		$maxes = [ 'structure' => 35, 'citability' => 40, 'authority' => 25 ];

		foreach ( $rows as $row ) {
			$data = maybe_unserialize( $row['meta_value'] );
			if ( ! is_array( $data ) || ! isset( $data['categories'] ) ) {
				continue;
			}
			$pcts = [];
			foreach ( [ 'structure', 'citability', 'authority' ] as $cat ) {
				$score        = (int) ( $data['categories'][ $cat ]['score'] ?? 0 );
				$pcts[ $cat ] = $maxes[ $cat ] > 0 ? ( $score / $maxes[ $cat ] ) : 0.0;
			}
			asort( $pcts );
			$weakest = (string) array_key_first( $pcts );
			if ( isset( $counts[ $weakest ] ) ) {
				++$counts[ $weakest ];
			}
		}

		return $counts;
	}

	/**
	 * Returns up to $limit recent plugin activity events, merged from multiple sources.
	 * P49: excludes opted-out posts from score-related events.
	 *
	 * @return array<int, array{type: string, text: string, timestamp: int, icon: string}>
	 */
	public function get_recent_activity( int $limit = 3 ): array {
		global $wpdb;

		$events   = [];
		$thirty_d = strtotime( '-30 days' );

		// 1. Latest score history entry.
		$history = get_option( ScoreHistory::OPTION_KEY, [] );
		if ( is_array( $history ) && ! empty( $history ) ) {
			$latest = end( $history );
			$ts     = (int) strtotime( $latest['date'] . ' 23:59:59' );
			if ( $ts >= $thirty_d ) {
				$events[] = [
					'type'      => 'score-update',
					'text'      => __( 'Site score recalculated', 'ai-search-optimizer' ),
					'timestamp' => $ts,
					'icon'      => 'cite-score',
				];
			}
		}

		// 2. llms.txt last regenerated.
		$llms_regen = get_option( 'citewp_aiso_llms_last_regenerated' );
		if ( $llms_regen && is_numeric( $llms_regen ) && (int) $llms_regen >= $thirty_d ) {
			$events[] = [
				'type'      => 'llms-regenerated',
				'text'      => __( 'llms.txt regenerated', 'ai-search-optimizer' ),
				'timestamp' => (int) $llms_regen,
				'icon'      => 'llms-txt',
			];
		}

		// 3. Most recent AI crawler visit.
		$table = esc_sql( Schema::table( Schema::TABLE_CRAWLER_LOGS ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$visit = $wpdb->get_row( "SELECT bot_name, detected_at FROM {$table} ORDER BY detected_at DESC LIMIT 1", ARRAY_A );
		if ( is_array( $visit ) && ! empty( $visit['detected_at'] ) ) {
			$ts = (int) strtotime( $visit['detected_at'] );
			if ( $ts >= $thirty_d ) {
				$events[] = [
					'type'      => 'top-bot-visit',
					/* translators: %s: bot name */
					'text'      => sprintf( __( '%s visited', 'ai-search-optimizer' ), $visit['bot_name'] ),
					'timestamp' => $ts,
					'icon'      => 'bot',
				];
			}
		}

		// 4. Most recently scored post (P49-aware).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$scored = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT p.post_title, p.post_modified_gmt
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 LEFT JOIN {$wpdb->postmeta} excl
				        ON excl.post_id = pm.post_id
				       AND excl.meta_key = '_citewp_aiso_exclude_from_llms'
				 WHERE pm.meta_key = %s
				   AND p.post_status = 'publish'
				   AND p.post_type IN ('post', 'page')
				   AND ( excl.meta_value IS NULL OR excl.meta_value != '1' )
				 ORDER BY p.post_modified_gmt DESC
				 LIMIT 1",
				Repository::META_KEY_TOTAL
			),
			ARRAY_A
		);
		if ( is_array( $scored ) && ! empty( $scored['post_modified_gmt'] ) ) {
			$ts = (int) strtotime( $scored['post_modified_gmt'] );
			if ( $ts >= $thirty_d ) {
				$title    = wp_trim_words( $scored['post_title'], 5, '…' );
				$events[] = [
					'type'      => 'high-impact-issue',
					/* translators: %s: post title */
					'text'      => sprintf( __( '%s scored', 'ai-search-optimizer' ), $title ),
					'timestamp' => $ts,
					'icon'      => 'alert-triangle',
				];
			}
		}

		if ( empty( $events ) ) {
			return [];
		}

		usort( $events, static fn( $a, $b ) => $b['timestamp'] - $a['timestamp'] );
		return array_slice( $events, 0, $limit );
	}
}
