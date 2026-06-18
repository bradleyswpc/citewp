<?php
/**
 * WP-CLI commands for the Cite Score engine.
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\CLI;

use CiteWP\Aiso\Scoring\Backfill;
use CiteWP\Aiso\Scoring\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Manage Cite Scores from the command line.
 */
final class ScoreCommand {

	/**
	 * Score every published post/page that has no Cite Score yet.
	 *
	 * Runs synchronously (no cron) — fine on CLI where there is no request timeout.
	 * Use this on a site where content predates the plugin or was bulk-imported.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : List how many posts would be scored without scoring them.
	 *
	 * ## EXAMPLES
	 *
	 *     wp citewp-aiso backfill
	 *     wp citewp-aiso backfill --dry-run
	 *
	 * @param array<int, string>    $args       Positional args (unused).
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function backfill( array $args, array $assoc_args ): void {
		$backfill = new Backfill();
		$ids      = $backfill->unscored_ids();
		$count    = count( $ids );

		if ( 0 === $count ) {
			\WP_CLI::success( 'All published posts and pages already have a Cite Score.' );
			return;
		}

		if ( isset( $assoc_args['dry-run'] ) ) {
			\WP_CLI::log( sprintf( '%d post(s) would be scored.', $count ) );
			return;
		}

		$repository = new Repository();
		$progress   = \WP_CLI\Utils\make_progress_bar( sprintf( 'Scoring %d post(s)', $count ), $count );

		$scored = 0;
		foreach ( $ids as $post_id ) {
			if ( null !== $repository->recalculate( (int) $post_id ) ) {
				++$scored;
			}
			$progress->tick();
		}

		$progress->finish();
		\WP_CLI::success( sprintf( 'Scored %d of %d post(s).', $scored, $count ) );
	}
}
