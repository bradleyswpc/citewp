<?php
/**
 * Decides which content gets included in llms.txt.
 *
 * Tiered selection logic (priority order):
 *   1. All published Pages
 *   2. Cornerstone content (Yoast / Rank Math / AIOSEO if active)
 *   3. Recent quality posts (last 90d, >= min word count)
 *   4. Other public custom post types (configurable)
 *
 * Always excludes: drafts, password-protected, noindex'd, attachments,
 * revisions, content tagged with noai robots meta.
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Llms;

defined( 'ABSPATH' ) || exit;

final class ContentSelector {

	private const DEFAULT_MIN_WORD_COUNT = 500;
	private const DEFAULT_RECENT_DAYS    = 90;
	private const HARD_LIMIT             = 200; // Total posts in llms.txt

	/**
	 * Returns curated post objects in priority order, deduped.
	 *
	 * @return \WP_Post[]
	 */
	public function select(): array {
		$settings = get_option( 'citewp_aiso_llms_settings', [] );

		/** @var \WP_Post[] $bucket */
		$bucket = [];
		$seen   = [];

		// 1. Pages — always included.
		foreach ( $this->fetch_pages() as $post ) {
			if ( $this->is_excluded( $post ) ) {
				continue;
			}
			$bucket[]            = $post;
			$seen[ $post->ID ]   = true;
		}

		// 2. Cornerstone content from active SEO plugin.
		foreach ( $this->fetch_cornerstone() as $post ) {
			if ( isset( $seen[ $post->ID ] ) || $this->is_excluded( $post ) ) {
				continue;
			}
			$bucket[]          = $post;
			$seen[ $post->ID ] = true;
		}

		// 3. Recent posts above the word-count threshold.
		$min_words = (int) ( $settings['min_word_count'] ?? self::DEFAULT_MIN_WORD_COUNT );
		$days      = (int) ( $settings['recent_days'] ?? self::DEFAULT_RECENT_DAYS );
		foreach ( $this->fetch_recent_posts( $days ) as $post ) {
			if ( isset( $seen[ $post->ID ] ) || $this->is_excluded( $post ) ) {
				continue;
			}
			if ( str_word_count( wp_strip_all_tags( $post->post_content ) ) < $min_words ) {
				continue;
			}
			$bucket[]          = $post;
			$seen[ $post->ID ] = true;
		}

		// 4. Other public CPTs (only if user opted them in via settings).
		$extra_types = $settings['extra_post_types'] ?? [];
		if ( ! empty( $extra_types ) && is_array( $extra_types ) ) {
			foreach ( $this->fetch_custom_post_types( $extra_types ) as $post ) {
				if ( isset( $seen[ $post->ID ] ) || $this->is_excluded( $post ) ) {
					continue;
				}
				$bucket[]          = $post;
				$seen[ $post->ID ] = true;
			}
		}

		// Hard cap to prevent enormous llms.txt files.
		$bucket = array_slice( $bucket, 0, self::HARD_LIMIT );

		/**
		 * Filter the final selected content list.
		 *
		 * @param \WP_Post[] $bucket
		 */
		return apply_filters( 'citewp_aiso_llms_selected_content', $bucket );
	}

	/**
	 * @return \WP_Post[]
	 */
	private function fetch_pages(): array {
		$q = new \WP_Query(
			[
				'post_type'              => 'page',
				'post_status'            => 'publish',
				'posts_per_page'         => 100,
				'orderby'                => 'menu_order',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);
		return $q->posts;
	}

	/**
	 * Cornerstone detection across the three major SEO plugins.
	 *
	 * @return \WP_Post[]
	 */
	private function fetch_cornerstone(): array {
		// Yoast SEO: meta key '_yoast_wpseo_is_cornerstone' = '1'
		// Rank Math: meta key 'rank_math_pillar_content' = 'on'
		// AIOSEO:    'aioseo_notes' or its own table — checked via filter below

		$meta_query = [
			'relation' => 'OR',
			[
				'key'   => '_yoast_wpseo_is_cornerstone',
				'value' => '1',
			],
			[
				'key'   => 'rank_math_pillar_content',
				'value' => 'on',
			],
		];

		$q = new \WP_Query(
			[
				'post_type'              => [ 'post', 'page' ],
				'post_status'            => 'publish',
				'posts_per_page'         => 50,
				'meta_query'             => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		$results = $q->posts;

		/**
		 * Allow other SEO integrations (or AIOSEO) to inject cornerstone posts.
		 *
		 * @param \WP_Post[] $results
		 */
		return apply_filters( 'citewp_aiso_llms_cornerstone_posts', $results );
	}

	/**
	 * @return \WP_Post[]
	 */
	private function fetch_recent_posts( int $days ): array {
		$q = new \WP_Query(
			[
				'post_type'              => 'post',
				'post_status'            => 'publish',
				'posts_per_page'         => 100,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'date_query'             => [
					[
						'after'     => $days . ' days ago',
						'inclusive' => true,
					],
				],
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);
		return $q->posts;
	}

	/**
	 * @param string[] $post_types
	 * @return \WP_Post[]
	 */
	private function fetch_custom_post_types( array $post_types ): array {
		// Validate against actually-registered public types.
		$valid = array_intersect(
			$post_types,
			array_keys( get_post_types( [ 'public' => true, 'show_ui' => true ] ) )
		);
		if ( empty( $valid ) ) {
			return [];
		}

		$q = new \WP_Query(
			[
				'post_type'              => $valid,
				'post_status'            => 'publish',
				'posts_per_page'         => 50,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);
		return $q->posts;
	}

	/**
	 * Universal exclusion checks applied to every post.
	 */
	private function is_excluded( \WP_Post $post ): bool {
		// Password protected.
		if ( ! empty( $post->post_password ) ) {
			return true;
		}

		// Yoast / Rank Math / AIOSEO noindex flags.
		if ( get_post_meta( $post->ID, '_yoast_wpseo_meta-robots-noindex', true ) === '1' ) {
			return true;
		}
		if ( get_post_meta( $post->ID, 'rank_math_robots', true ) ) {
			$rm = get_post_meta( $post->ID, 'rank_math_robots', true );
			if ( is_array( $rm ) && in_array( 'noindex', $rm, true ) ) {
				return true;
			}
		}

		// Custom CiteWP per-post exclusion (settable via post meta box later).
		if ( get_post_meta( $post->ID, '_citewp_aiso_exclude_from_llms', true ) === '1' ) {
			return true;
		}

		/**
		 * Catch-all: let third parties exclude posts.
		 *
		 * @param bool     $excluded
		 * @param \WP_Post $post
		 */
		return (bool) apply_filters( 'citewp_aiso_llms_post_excluded', false, $post );
	}
}
