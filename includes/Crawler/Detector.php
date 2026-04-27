<?php
/**
 * AI crawler detection and logging.
 *
 * Hooks into `init` to inspect the User-Agent of every front-end request.
 * If matched against the bot registry, logs to citewp_aiso_crawler_logs.
 *
 * Skipped contexts (no logging):
 *   - WP-CLI, cron, AJAX, REST, admin, login pages
 *   - Empty user agents
 *   - Disabled in settings
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Crawler;

use CiteWP\Aiso\Database\Schema;

defined( 'ABSPATH' ) || exit;

final class Detector {

	public function register(): void {
		// `init` runs early enough to capture all front-end traffic
		// but late enough that WP globals are available.
		add_action( 'init', [ $this, 'maybe_log_request' ], 1 );

		// Daily cleanup of old logs.
		add_action( 'citewp_aiso_daily_cleanup', [ $this, 'prune_old_logs' ] );
		if ( ! wp_next_scheduled( 'citewp_aiso_daily_cleanup' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'citewp_aiso_daily_cleanup' );
		}
	}

	public function maybe_log_request(): void {
		if ( ! $this->should_log() ) {
			return;
		}

		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: '';

		if ( $user_agent === '' ) {
			return;
		}

		$bot = BotRegistry::match( $user_agent );
		if ( $bot === null ) {
			return;
		}

		$this->insert_log( $bot, $user_agent );
	}

	/**
	 * Gate logging on context + settings. Cheap checks first.
	 */
	private function should_log(): bool {
		if ( ( defined( 'WP_CLI' ) && WP_CLI )
			|| ( defined( 'DOING_CRON' ) && DOING_CRON )
			|| ( defined( 'DOING_AJAX' ) && DOING_AJAX )
			|| ( defined( 'REST_REQUEST' ) && REST_REQUEST )
			|| is_admin()
		) {
			return false;
		}

		$settings = get_option( 'citewp_aiso_settings', [] );
		return ! empty( $settings['enable_crawler_detection'] );
	}

	/**
	 * @param array{match: string, name: string, vendor: string, purpose: string} $bot
	 */
	private function insert_log( array $bot, string $user_agent ): void {
		global $wpdb;

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- High-frequency bot logging; no WP API equivalent for custom table inserts.
			Schema::table( Schema::TABLE_CRAWLER_LOGS ),
			[
				'detected_at'  => current_time( 'mysql', true ), // GMT
				'bot_name'     => $bot['name'],
				'bot_vendor'   => $bot['vendor'],
				'user_agent'   => mb_substr( $user_agent, 0, 512 ),
				'ip_address'   => $this->client_ip(),
				'request_uri'  => $this->request_uri(),
				'http_status'  => null, // Filled in via shutdown hook in v0.2
				'referer'      => $this->referer(),
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ]
		);
	}

	/**
	 * Best-effort client IP. Honors common proxy headers but doesn't trust them blindly.
	 * For now we just use REMOTE_ADDR — proxy support in a later session if needed.
	 */
	private function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] )
			? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- filter_var( FILTER_VALIDATE_IP ) on the return line validates and sanitizes.
			: '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	private function request_uri(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] )
			? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- esc_url_raw() sanitizes on the return line.
			: '';
		return mb_substr( esc_url_raw( $uri ), 0, 512 );
	}

	private function referer(): ?string {
		if ( empty( $_SERVER['HTTP_REFERER'] ) ) {
			return null;
		}
		$ref = (string) wp_unslash( $_SERVER['HTTP_REFERER'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- esc_url_raw() sanitizes on the return line.
		return mb_substr( esc_url_raw( $ref ), 0, 512 );
	}

	/**
	 * Prune logs older than the retention window (default 7 days for free tier).
	 */
	public function prune_old_logs(): void {
		global $wpdb;

		$settings = get_option( 'citewp_aiso_settings', [] );
		$days     = (int) ( $settings['log_retention_days'] ?? 7 );
		if ( $days < 1 ) {
			return;
		}

		$table  = esc_sql( Schema::table( Schema::TABLE_CRAWLER_LOGS ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Pruning custom table; DELETE does not benefit from caching; no WP API equivalent.
			$wpdb->prepare( "DELETE FROM {$table} WHERE detected_at < %s", $cutoff ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is esc_sql() of a hardcoded prefix + constant string.
		);
	}
}
