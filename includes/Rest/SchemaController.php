<?php
/**
 * REST endpoints for JSON-LD schema suggestions and head injection.
 *
 * Routes:
 *   GET  /citewp/aiso/v1/schema/<post_id>          — generated schema + detected types + injected list
 *   POST /citewp/aiso/v1/schema/<post_id>/inject    — store or remove CiteWP head-injected schema
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Rest;

use CiteWP\Aiso\Schema\Detector;
use CiteWP\Aiso\Schema\Generator;
use CiteWP\Aiso\Schema\HeadInjector;

defined( 'ABSPATH' ) || exit;

final class SchemaController {

	private const NAMESPACE = 'citewp/aiso/v1';

	private Generator    $generator;
	private Detector     $detector;
	private HeadInjector $injector;

	public function __construct(
		?Generator $generator = null,
		?Detector $detector = null,
		?HeadInjector $injector = null
	) {
		$this->generator = $generator ?? new Generator();
		$this->detector  = $detector ?? new Detector();
		$this->injector  = $injector ?? new HeadInjector();
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

		register_rest_route(
			self::NAMESPACE,
			'/schema/(?P<post_id>\d+)/inject',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'inject_schema' ],
				'permission_callback' => [ $this, 'permission_check' ],
				'args'                => [
					'post_id' => [
						'validate_callback' => static fn( $v ) => is_numeric( $v ) && (int) $v > 0,
						'sanitize_callback' => 'absint',
					],
					'type'   => [
						'required'          => true,
						'validate_callback' => static fn( $v ) => in_array( $v, [ 'article', 'faqpage' ], true ),
						'sanitize_callback' => 'sanitize_key',
					],
					'action' => [
						'default'           => 'inject',
						'validate_callback' => static fn( $v ) => in_array( $v, [ 'inject', 'remove' ], true ),
						'sanitize_callback' => 'sanitize_key',
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
				__( 'You do not have permission to view schema for this post.', 'citewp-ai-search-optimizer' ),
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

		// Use emitter-agnostic detection for the 'detected' badge list.
		// tier1/tier2 = full rendered page — authoritative, no supplement needed.
		// tier3/cold_start = post_content only — head-injected Article from SEO plugins is
		// not visible; supplement with detect_existing_types so in-content JSON-LD still
		// contributes (note: head schema requires tier1/tier2 to appear as detected).
		$schema_result = $this->detector->get_detected_types( $post->ID );
		if ( in_array( $schema_result['source'], [ 'tier1', 'tier2' ], true ) ) {
			$detected = $schema_result['types'] ?: $this->generator->detect_existing_types( $post );
		} else {
			$from_generator = $this->generator->detect_existing_types( $post );
			$detected       = array_values( array_unique( array_merge( $schema_result['types'], $from_generator ) ) );
		}

		$stored_types = $this->injector->get_stored( $post_id );

		// Suppress Insert offer when: (a) another emitter already has valid schema (S40
		// flag-don't-inject), OR (b) CiteWP already injected it — user sees Remove instead.
		$article = ( $schema_result['article_valid'] || isset( $stored_types['article'] ) )
			? null
			: $this->generator->generate_article_schema( $post );

		$faqpage   = null;
		$faq_count = $this->generator->count_faq_pairs( $post );
		if ( ! $schema_result['faq_valid'] && ! isset( $stored_types['faqpage'] ) ) {
			$faqpage = $this->generator->generate_faq_schema( $post ) ?: null;
		}

		return new \WP_REST_Response(
			[
				'article'   => $article,
				'faqpage'   => $faqpage,
				'detected'  => array_values( (array) $detected ),
				'faq_count' => $faq_count,
				'injected'  => array_keys( $stored_types ),
			],
			200
		);
	}

	public function inject_schema( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = (int) $request['post_id'];
		$post    = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return new \WP_REST_Response( [ 'error' => 'post_not_found' ], 404 );
		}

		$type   = (string) $request['type'];
		$action = (string) $request['action'];

		if ( $action === 'remove' ) {
			$this->injector->remove( $post_id, $type );
			// Clear detection cache so the next GET re-evaluates without CiteWP's emission.
			$this->detector->clear_cache( $post_id );
			return new \WP_REST_Response(
				[ 'injected' => array_keys( $this->injector->get_stored( $post_id ) ) ],
				200
			);
		}

		// Conflict check at inject time (not at emit time — HeadInjector is unconditional).
		// Skip when CiteWP already owns this slot — treat as a refresh/overwrite.
		$stored = $this->injector->get_stored( $post_id );
		if ( ! isset( $stored[ $type ] ) ) {
			$schema_result = $this->detector->get_detected_types( $post_id );
			$valid_key     = $type === 'faqpage' ? 'faq_valid' : 'article_valid';
			if ( ! empty( $schema_result[ $valid_key ] ) ) {
				return new \WP_REST_Response(
					[ 'error' => 'conflict', 'type' => $type ],
					409
				);
			}
		}

		if ( $type === 'faqpage' ) {
			$schema = $this->generator->generate_faq_schema( $post );
			if ( ! $schema ) {
				return new \WP_REST_Response( [ 'error' => 'no_faq_pairs' ], 422 );
			}
		} else {
			$schema = $this->generator->generate_article_schema( $post );
		}

		$this->injector->store( $post_id, $type, $schema );

		return new \WP_REST_Response(
			[ 'injected' => array_keys( $this->injector->get_stored( $post_id ) ) ],
			200
		);
	}
}
