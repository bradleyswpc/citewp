<?php
/**
 * REST endpoints for the GEO Score Gutenberg sidebar.
 *
 * Routes:
 *   GET  /citewp/v1/score/<post_id>           — get cached score
 *   POST /citewp/v1/score/<post_id>/recalculate — force recalc and return fresh score
 *
 * @package CiteWP
 */

declare( strict_types=1 );

namespace CiteWP\Rest;

use CiteWP\Scoring\Repository;
use CiteWP\Scoring\Engine;

defined( 'ABSPATH' ) || exit;

final class ScoreController {

	private const NAMESPACE = 'citewp/v1';

	private Repository $repo;

	public function __construct( ?Repository $repo = null ) {
		$this->repo = $repo ?? new Repository();
	}

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/score/(?P<post_id>\d+)',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_score' ],
				'permission_callback' => [ $this, 'permission_check' ],
				'args'                => [
					'post_id' => [
						'validate_callback' => static fn( $v ) => is_numeric( $v ) && (int) $v > 0,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/score/(?P<post_id>\d+)/recalculate',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'recalculate' ],
				'permission_callback' => [ $this, 'permission_check' ],
				'args'                => [
					'post_id' => [
						'validate_callback' => static fn( $v ) => is_numeric( $v ) && (int) $v > 0,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
	}

	public function permission_check( \WP_REST_Request $request ): bool|\WP_Error {
		$post_id = (int) $request['post_id'];
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error(
				'citewp_forbidden',
				__( 'You do not have permission to view scores for this post.', 'citewp' ),
				[ 'status' => 403 ]
			);
		}
		return true;
	}

	public function get_score( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = (int) $request['post_id'];
		$cached  = $this->repo->get( $post_id );

		if ( $cached === null ) {
			// First-ever request — compute on demand.
			$result = $this->repo->recalculate( $post_id );
			if ( $result === null ) {
				return new \WP_REST_Response(
					[ 'error' => 'unscorable_post_type' ],
					400
				);
			}
			return new \WP_REST_Response( $result->to_array(), 200 );
		}

		return new \WP_REST_Response( $cached, 200 );
	}

	public function recalculate( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = (int) $request['post_id'];
		$result  = $this->repo->recalculate( $post_id );

		if ( $result === null ) {
			return new \WP_REST_Response(
				[ 'error' => 'unscorable_post_type' ],
				400
			);
		}

		return new \WP_REST_Response( $result->to_array(), 200 );
	}
}
