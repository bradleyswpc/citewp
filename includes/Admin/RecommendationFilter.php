<?php
/**
 * Filters the WordPress posts list by a specific AI recommendation signal.
 *
 * Registered by Plugin::boot() for all admin requests.
 * Reads the `aiso_recommendation` query param, narrows the main WP_Query to
 * only posts where that signal has status 'fail' or 'partial', and renders
 * an inline notice with a clear-filter link.
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Admin;

use CiteWP\Aiso\Scoring\Repository;

defined( 'ABSPATH' ) || exit;

final class RecommendationFilter {

	/** Per-request cache: signal_id → matching post IDs. */
	private static array $id_cache = [];

	public function register(): void {
		add_action( 'pre_get_posts', [ $this, 'apply_filter' ] );
		add_action( 'admin_notices',  [ $this, 'render_notice' ] );
	}

	/**
	 * Returns IDs of scored posts (any status) where the given signal has
	 * status 'fail' or 'partial'.
	 *
	 * Results are cached per signal_id for the duration of the request so
	 * multiple callers (e.g. Menu.php card counts + filter hook + notice)
	 * don't duplicate the meta scan.
	 *
	 * @return int[]
	 */
	public static function get_affected_ids( string $signal_id ): array {
		if ( isset( self::$id_cache[ $signal_id ] ) ) {
			return self::$id_cache[ $signal_id ];
		}

		$all_ids = get_posts( [
			'post_type'              => [ 'post', 'page' ],
			'post_status'            => [ 'publish', 'draft' ],
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'meta_key'               => Repository::META_KEY_FULL,  // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_compare'           => 'EXISTS',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		] );

		$repo     = new Repository();
		$matching = [];

		foreach ( (array) $all_ids as $pid ) {
			$data = $repo->get( (int) $pid );
			if ( ! is_array( $data ) || empty( $data['signals'] ) ) {
				continue;
			}
			foreach ( $data['signals'] as $sig ) {
				if ( ( $sig['id'] ?? '' ) === $signal_id
					&& in_array( $sig['status'] ?? '', [ 'fail', 'partial' ], true )
				) {
					$matching[] = (int) $pid;
					break;
				}
			}
		}

		self::$id_cache[ $signal_id ] = $matching;
		return $matching;
	}

	/**
	 * Returns the dominant post type ('post' or 'page') among the affected IDs
	 * for a given signal, so recommendation links route to the correct list screen.
	 */
	public static function dominant_post_type( string $signal_id ): string {
		$ids = self::get_affected_ids( $signal_id );
		if ( empty( $ids ) ) {
			return 'post';
		}
		$counts = [ 'post' => 0, 'page' => 0 ];
		foreach ( $ids as $pid ) {
			$type = get_post_type( $pid );
			if ( isset( $counts[ $type ] ) ) {
				$counts[ $type ]++;
			}
		}
		return $counts['page'] > $counts['post'] ? 'page' : 'post';
	}

	/**
	 * Narrows the main posts-list query when ?aiso_recommendation= is set.
	 */
	public function apply_filter( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'edit' !== $screen->base ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$signal_id = sanitize_key( $_GET['aiso_recommendation'] ?? '' );
		if ( '' === $signal_id ) {
			return;
		}

		$ids = self::get_affected_ids( $signal_id );
		// post__in with an empty array returns ALL posts, so force zero-result set instead.
		$query->set( 'post__in', ! empty( $ids ) ? $ids : [ 0 ] );
	}

	/**
	 * Renders the filter-active notice above the posts list.
	 */
	public function render_notice(): void {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'edit' !== $screen->base ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$signal_id = sanitize_key( $_GET['aiso_recommendation'] ?? '' );
		if ( '' === $signal_id ) {
			return;
		}

		$mapper = new RecommendationMapper();
		$rec    = $mapper->get( $signal_id );
		$label  = $rec ? $rec['label'] : $signal_id;
		$count  = count( self::get_affected_ids( $signal_id ) );
		$clear  = remove_query_arg( 'aiso_recommendation' );

		printf(
			'<div class="notice notice-info"><p>%s &nbsp;&nbsp;<a href="%s">%s</a></p></div>',
			esc_html(
				sprintf(
					/* translators: 1: number of posts, 2: recommendation label */
					_n(
						'Showing %1$d post flagged for: %2$s.',
						'Showing %1$d posts flagged for: %2$s.',
						$count,
						'citewp-ai-search-optimizer'
					),
					$count,
					$label
				)
			),
			esc_url( $clear ),
			esc_html__( '× All posts', 'citewp-ai-search-optimizer' )
		);
	}
}
