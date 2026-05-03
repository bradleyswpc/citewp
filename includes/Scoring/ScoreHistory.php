<?php
/**
 * Logs a daily average Cite Score to WP options for the Score History chart.
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Scoring;

defined( 'ABSPATH' ) || exit;

final class ScoreHistory {

	public const OPTION_KEY   = 'citewp_aiso_score_history';
	public const CRON_HOOK    = 'citewp_aiso_daily_score_log';
	private const MAX_ENTRIES = 365;

	public function register(): void {
		add_action( self::CRON_HOOK, [ $this, 'log_daily_average' ] );
	}

	public function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( strtotime( 'tomorrow midnight' ), 'daily', self::CRON_HOOK );
		}
	}

	public function unschedule(): void {
		$ts = wp_next_scheduled( self::CRON_HOOK );
		if ( $ts ) {
			wp_unschedule_event( $ts, self::CRON_HOOK );
		}
	}

	public function log_daily_average(): void {
		$avg = $this->compute_current_average();
		if ( $avg === null ) {
			return;
		}

		$history = $this->get_raw_history();
		$today   = current_time( 'Y-m-d' );

		$updated = false;
		foreach ( $history as &$entry ) {
			if ( $entry['date'] === $today ) {
				$entry['avg'] = $avg;
				$updated      = true;
				break;
			}
		}
		unset( $entry );

		if ( ! $updated ) {
			$history[] = [ 'date' => $today, 'avg' => $avg ];
		}

		usort( $history, static fn( $a, $b ) => strcmp( $a['date'], $b['date'] ) );

		if ( count( $history ) > self::MAX_ENTRIES ) {
			$history = array_slice( $history, -self::MAX_ENTRIES );
		}

		update_option( self::OPTION_KEY, $history, false );
	}

	/**
	 * @return array<int, array{date: string, avg: float}>
	 */
	public function get_history( int $days = 30 ): array {
		$all    = $this->get_raw_history();
		$cutoff = gmdate( 'Y-m-d', (int) strtotime( "-{$days} days" ) );
		return array_values(
			array_filter( $all, static fn( $e ) => $e['date'] >= $cutoff )
		);
	}

	private function compute_current_average(): ?float {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$avg = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != '' AND meta_value > 0",
				Repository::META_KEY_TOTAL
			)
		);
		return $avg !== null ? (float) $avg : null;
	}

	/**
	 * @return array<int, array{date: string, avg: float}>
	 */
	private function get_raw_history(): array {
		$data = get_option( self::OPTION_KEY, [] );
		return is_array( $data ) ? $data : [];
	}
}
