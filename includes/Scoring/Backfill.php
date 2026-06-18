<?php
/**
 * Batch-scores published posts/pages that have never been scored.
 *
 * Why this exists:
 *   Scores live in post meta (_citewp_aiso_geo_score_total) written only when a
 *   post is saved while the plugin is active (save_post / transition_post_status).
 *   Content that predates plugin activation — or was bulk-imported via paths that
 *   skip those hooks — has no score meta, so it is missing from every dashboard
 *   surface that counts scored posts (Indexed Pages card, averages, blind spots)
 *   even though it IS present in llms.txt (which selects published content directly).
 *
 *   Backfill closes that gap by scoring the backlog in small cron-driven chunks,
 *   so large sites never hit a request timeout.
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Scoring;

defined( 'ABSPATH' ) || exit;

final class Backfill {

	/** Cron hook that processes a single chunk. */
	public const CRON_HOOK = 'citewp_aiso_backfill_batch';

	/** Option storing progress for admin UI feedback. */
	public const OPTION_STATE = 'citewp_aiso_backfill_state';

	/** Posts scored per cron tick. Kept low so a tick stays well under any timeout. */
	private const BATCH_SIZE = 20;

	private Repository $repository;

	public function __construct( ?Repository $repository = null ) {
		$this->repository = $repository ?? new Repository();
	}

	/**
	 * Register the cron callback. Must run on every request so WP-Cron can fire it.
	 */
	public function register(): void {
		add_action( self::CRON_HOOK, [ $this, 'run_batch' ] );
	}

	/**
	 * Published, scorable posts that have no stored score yet.
	 *
	 * @param int $limit -1 for all, or a positive chunk size.
	 * @return int[]
	 */
	public function unscored_ids( int $limit = -1 ): array {
		$q = new \WP_Query(
			[
				'post_type'              => $this->repository->scorable_types(),
				'post_status'            => 'publish',
				'posts_per_page'         => $limit,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- One-off backfill scan, not a hot path.
					[
						'key'     => Repository::META_KEY_TOTAL,
						'compare' => 'NOT EXISTS',
					],
				],
			]
		);

		return array_map( 'intval', $q->posts );
	}

	/**
	 * How many published posts still need a score.
	 */
	public function pending_count(): int {
		return count( $this->unscored_ids() );
	}

	/**
	 * Kick off (or restart) a backfill run. Idempotent: re-calling while one is
	 * already scheduled simply refreshes the counters.
	 *
	 * @return int Number of posts queued for scoring.
	 */
	public function start(): int {
		$pending = $this->pending_count();

		update_option(
			self::OPTION_STATE,
			[
				'running'   => $pending > 0,
				'total'     => $pending,
				'remaining' => $pending,
				'started'   => current_time( 'mysql', true ),
				'updated'   => current_time( 'mysql', true ),
			],
			false
		);

		if ( $pending > 0 ) {
			$this->schedule_next();
		}

		return $pending;
	}

	/**
	 * Score one chunk, then reschedule if work remains. Cron callback.
	 */
	public function run_batch(): void {
		$ids = $this->unscored_ids( self::BATCH_SIZE );

		foreach ( $ids as $post_id ) {
			$this->repository->recalculate( $post_id );
		}

		$remaining = $this->pending_count();

		$state              = $this->state();
		$state['remaining'] = $remaining;
		$state['updated']   = current_time( 'mysql', true );
		$state['running']   = ( $remaining > 0 && ! empty( $ids ) );
		update_option( self::OPTION_STATE, $state, false );

		// Reschedule only if we made progress and work is left. Empty $ids with
		// remaining > 0 means nothing scored this tick (e.g. all remaining are
		// unscorable) — stop rather than loop forever.
		if ( $remaining > 0 && ! empty( $ids ) ) {
			$this->schedule_next();
		}
	}

	/**
	 * Current progress state, with safe defaults.
	 *
	 * @return array{running: bool, total: int, remaining: int, started: string, updated: string}
	 */
	public function state(): array {
		$state = get_option( self::OPTION_STATE, [] );
		$state = is_array( $state ) ? $state : [];

		return [
			'running'   => (bool) ( $state['running'] ?? false ),
			'total'     => (int) ( $state['total'] ?? 0 ),
			'remaining' => (int) ( $state['remaining'] ?? 0 ),
			'started'   => (string) ( $state['started'] ?? '' ),
			'updated'   => (string) ( $state['updated'] ?? '' ),
		];
	}

	public function is_running(): bool {
		return (bool) wp_next_scheduled( self::CRON_HOOK );
	}

	/**
	 * Stop a run in progress and clear the scheduled chunk.
	 */
	public function cancel(): void {
		$ts = wp_next_scheduled( self::CRON_HOOK );
		while ( $ts ) {
			wp_unschedule_event( $ts, self::CRON_HOOK );
			$ts = wp_next_scheduled( self::CRON_HOOK );
		}

		$state            = $this->state();
		$state['running'] = false;
		$state['updated'] = current_time( 'mysql', true );
		update_option( self::OPTION_STATE, $state, false );
	}

	/**
	 * Queue the next chunk a moment from now, unless one is already queued.
	 */
	private function schedule_next(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 5, self::CRON_HOOK );
		}
	}
}
