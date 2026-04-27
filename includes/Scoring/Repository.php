<?php
/**
 * Persists GEO scores to post meta and triggers recalculation on publish/update.
 *
 * Stored as a single serialized array under _citewp_aiso_geo_score for fast retrieval.
 * Also stores _citewp_aiso_geo_score_total as a top-level integer for orderby/list-table queries.
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Scoring;

defined( 'ABSPATH' ) || exit;

final class Repository {

	public const META_KEY_FULL  = '_citewp_aiso_geo_score';       // serialized array
	public const META_KEY_TOTAL = '_citewp_aiso_geo_score_total'; // int (queryable)
	public const META_KEY_GRADE = '_citewp_aiso_geo_score_grade'; // red|orange|yellow|green
	public const META_KEY_TIME  = '_citewp_aiso_geo_score_time';  // mysql gmt timestamp

	/** Post types that get scored. */
	private const SCORABLE_TYPES = [ 'post', 'page' ];

	private Engine $engine;

	public function __construct( ?Engine $engine = null ) {
		$this->engine = $engine ?? new Engine();
	}

	public function register(): void {
		// Recalculate when a post is saved (publish or update).
		add_action( 'save_post', [ $this, 'on_save_post' ], 20, 3 );

		// Allow recalculation via a filter for testing/manual triggers.
		add_filter( 'citewp_aiso_recalculate_score', [ $this, 'recalculate' ] );
	}

	/**
	 * Recalculate and persist a post's score.
	 */
	public function recalculate( int $post_id ): ?ScoreResult {
		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, $this->scorable_types(), true ) ) {
			return null;
		}

		$result = $this->engine->score( $post );
		$this->save( $post_id, $result );
		return $result;
	}

	public function get( int $post_id ): ?array {
		$data = get_post_meta( $post_id, self::META_KEY_FULL, true );
		return is_array( $data ) ? $data : null;
	}

	public function save( int $post_id, ScoreResult $result ): void {
		update_post_meta( $post_id, self::META_KEY_FULL,  $result->to_array() );
		update_post_meta( $post_id, self::META_KEY_TOTAL, $result->total );
		update_post_meta( $post_id, self::META_KEY_GRADE, $result->grade );
		update_post_meta( $post_id, self::META_KEY_TIME,  current_time( 'mysql', true ) );
	}

	public function on_save_post( int $post_id, \WP_Post $post, bool $update ): void {
		// Skip autosaves, revisions, non-scorable types.
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! in_array( $post->post_type, $this->scorable_types(), true ) ) {
			return;
		}
		if ( $post->post_status !== 'publish' && $post->post_status !== 'draft' ) {
			return;
		}

		$this->recalculate( $post_id );
	}

	/**
	 * @return string[]
	 */
	private function scorable_types(): array {
		/**
		 * Filter which post types are scored.
		 *
		 * @param string[] $types
		 */
		return apply_filters( 'citewp_aiso_scorable_post_types', self::SCORABLE_TYPES );
	}
}
