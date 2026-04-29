<?php
/**
 * REST endpoint for JSON-LD schema suggestions.
 *
 * Route:
 *   GET /citewp/aiso/v1/schema/<post_id> — returns generated schema + detected types
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Rest;

use CiteWP\Aiso\Schema\Generator;

defined( 'ABSPATH' ) || exit;

final class SchemaController {

	private const NAMESPACE = 'citewp/aiso/v1';

	private Generator $generator;

	public function __construct( ?Generator $generator = null ) {
		$this->generator = $generator ?? new Generator();
	}

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/schema/(?P<post_id>\d+)',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_schema' ],
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
				'citewp_aiso_forbidden',
				__( 'You do not have permission to view schema for this post.', 'ai-search-optimizer' ),
				[ 'status' => 403 ]
			);
		}
		return true;
	}

	public function get_schema( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = (int) $request['post_id'];
		$post    = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return new \WP_REST_Response( [ 'error' => 'post_not_found' ], 404 );
		}

		$article  = $this->generator->generate_article_schema( $post );
		$faqpage  = $this->generator->generate_faq_schema( $post );
		$detected = $this->generator->detect_existing_types( $post );

		return new \WP_REST_Response(
			[
				'article'  => $article,
				'faqpage'  => $faqpage ?: null,
				'detected' => array_values( $detected ),
			],
			200
		);
	}
}
