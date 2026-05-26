<?php
/**
 * Emitter-agnostic schema detection via rendered page output.
 *
 * Detection priority:
 *   Tier 1 — sync wp_remote_get() to permalink (explicit Recalculate + published only).
 *   Tier 2 — cached result from template_redirect (real front-end render, full page).
 *   Tier 3 — post_content scan, FENCED to hand-rolled wp:html blocks only.
 *             Never credits hook-injected schema from Rank Math / Yoast / AIOSEO.
 *   Cold-start — 'not_verified' when all tiers fail; do NOT credit or proxy.
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Schema;

defined( 'ABSPATH' ) || exit;

final class Detector {

	public const META_KEY      = '_citewp_aiso_schema_cache';
	public const META_KEY_TIME = '_citewp_aiso_schema_cache_time';

	private const CACHE_TTL            = DAY_IN_SECONDS;
	private const SELF_REQUEST_TIMEOUT = 3;

	private const ARTICLE_TYPES = [
		'Article',
		'BlogPosting',
		'NewsArticle',
		'TechArticle',
		'ScholarlyArticle',
		'SocialMediaPosting',
		'Report',
	];

	private ?int $capture_post_id = null;

	public function register(): void {
		add_action( 'template_redirect', [ $this, 'on_template_redirect' ] );
		add_action( 'save_post', [ $this, 'on_save_post' ], 10 );
	}

	// ── Tier 2: template_redirect full-page capture ──────────────────────────

	public function on_template_redirect(): void {
		if ( is_admin() || wp_doing_ajax() || ! is_singular() ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		$cached_time = (int) get_post_meta( $post->ID, self::META_KEY_TIME, true );
		if ( $cached_time > ( time() - self::CACHE_TTL ) ) {
			return;
		}
		$this->capture_post_id = $post->ID;
		ob_start( [ $this, 'capture_full_page' ] );
	}

	/**
	 * Output-buffer callback fired at page flush. Extracts JSON-LD and stores cache.
	 * Must return the HTML unchanged so the page renders normally.
	 */
	public function capture_full_page( string $html ): string {
		if ( $this->capture_post_id !== null ) {
			$result = $this->parse_jsonld_html( $html );
			$this->store_cache( $this->capture_post_id, $result );
		}
		return $html;
	}

	// ── Cache invalidation ────────────────────────────────────────────────────

	public function on_save_post( int $post_id ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		$this->clear_cache( $post_id );
	}

	public function clear_cache( int $post_id ): void {
		delete_post_meta( $post_id, self::META_KEY );
		delete_post_meta( $post_id, self::META_KEY_TIME );
	}

	// ── Primary entry point ───────────────────────────────────────────────────

	/**
	 * Returns the detection result for a post, consulting tiers in priority order.
	 *
	 * Possible states:
	 *   'detected'     — one or more JSON-LD @type values confirmed on the rendered page.
	 *   'not_found'    — page was checked; no JSON-LD present.
	 *   'not_verified' — could not check (cold-start / loopback blocked / non-published).
	 *
	 * @return array{state: string, types: string[], faq_valid: bool, article_valid: bool, source: string}
	 */
	public function get_detected_types( int $post_id, bool $explicit_recalculate = false ): array {
		$post = get_post( $post_id );

		// Tier 1: sync self-request — explicit Recalculate on published posts only.
		if ( $explicit_recalculate && $post instanceof \WP_Post && $post->post_status === 'publish' ) {
			$result = $this->fetch_from_permalink( $post_id );
			if ( $result !== null ) {
				return array_merge( $result, [ 'source' => 'tier1' ] );
			}
		}

		// Tier 2: template_redirect full-page cache.
		$cached = get_post_meta( $post_id, self::META_KEY, true );
		if ( is_string( $cached ) && $cached !== '' ) {
			$decoded = json_decode( $cached, true );
			if ( is_array( $decoded ) && isset( $decoded['state'] ) ) {
				return array_merge( $decoded, [ 'source' => 'tier2' ] );
			}
		}

		// Tier 3: post_content scan — fenced to hand-rolled wp:html blocks only.
		$result = $this->scan_post_content( $post_id );
		if ( $result !== null ) {
			return array_merge( $result, [ 'source' => 'tier3' ] );
		}

		// Cold-start: could not verify, do not credit.
		return [
			'state'         => 'not_verified',
			'types'         => [],
			'faq_valid'     => false,
			'article_valid' => false,
			'source'        => 'cold_start',
		];
	}

	// ── Tier 1: sync self-request ─────────────────────────────────────────────

	/**
	 * @return array{state: string, types: string[], faq_valid: bool, article_valid: bool}|null
	 */
	private function fetch_from_permalink( int $post_id ): ?array {
		$permalink = get_permalink( $post_id );
		if ( ! $permalink ) {
			return null;
		}
		$response = wp_remote_get(
			$permalink,
			[
				'timeout'     => self::SELF_REQUEST_TIMEOUT,
				'httpversion' => '1.0',
				'user-agent'  => 'CiteWP-Schema-Check/1.0',
				'sslverify'   => false,
			]
		);
		if ( is_wp_error( $response ) ) {
			return null;
		}
		$body = wp_remote_retrieve_body( $response );
		if ( $body === '' ) {
			return null;
		}
		$result = $this->parse_jsonld_html( $body );
		$this->store_cache( $post_id, $result );
		return $result;
	}

	// ── Tier 3: post_content fence ────────────────────────────────────────────

	/**
	 * Scans raw post_content for JSON-LD.
	 *
	 * Because hook-injected schema (Rank Math, Yoast, AIOSEO) is never stored in
	 * post_content, this scan is structurally incapable of crediting it — making
	 * this fence reliable without any explicit plugin-name checks.
	 *
	 * @return array{state: string, types: string[], faq_valid: bool, article_valid: bool}|null
	 */
	private function scan_post_content( int $post_id ): ?array {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return null;
		}
		$result = $this->parse_jsonld_html( $post->post_content );
		return $result['state'] === 'not_found' ? null : $result;
	}

	// ── JSON-LD parsing + structural validation ───────────────────────────────

	/**
	 * Extracts and structurally validates all JSON-LD blocks from an HTML string.
	 * Flattens Yoast-style @graph arrays so every top-level node is inspected.
	 *
	 * @return array{state: string, types: string[], faq_valid: bool, article_valid: bool}
	 */
	public function parse_jsonld_html( string $html ): array {
		$types         = [];
		$faq_valid     = false;
		$article_valid = false;

		if ( ! preg_match_all(
			'#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is',
			$html,
			$m
		) ) {
			return [
				'state'         => 'not_found',
				'types'         => [],
				'faq_valid'     => false,
				'article_valid' => false,
			];
		}

		foreach ( $m[1] as $blob ) {
			$decoded = json_decode( trim( $blob ), true );
			if ( ! is_array( $decoded ) ) {
				continue;
			}

			// Flatten @graph (Yoast combined graph) into individual nodes.
			$nodes = isset( $decoded['@graph'] ) && is_array( $decoded['@graph'] )
				? $decoded['@graph']
				: [ $decoded ];

			foreach ( $nodes as $node ) {
				if ( ! is_array( $node ) || ! isset( $node['@type'] ) ) {
					continue;
				}
				$node_types = is_array( $node['@type'] ) ? $node['@type'] : [ $node['@type'] ];
				foreach ( $node_types as $t ) {
					if ( is_string( $t ) && $t !== '' ) {
						$types[] = $t;
					}
				}

				if ( ! $faq_valid && in_array( 'FAQPage', $node_types, true ) ) {
					$faq_valid = $this->validate_faq_schema( $node );
				}

				if ( ! $article_valid ) {
					foreach ( $node_types as $t ) {
						if ( in_array( $t, self::ARTICLE_TYPES, true ) ) {
							$article_valid = $this->validate_article_schema( $node );
							break;
						}
					}
				}
			}
		}

		$types = array_values(
			(array) apply_filters( 'citewp_aiso/schema/detected_types', array_unique( $types ) )
		);

		return [
			'state'         => $types ? 'detected' : 'not_found',
			'types'         => $types,
			'faq_valid'     => $faq_valid,
			'article_valid' => $article_valid,
		];
	}

	/**
	 * FAQPage is structurally valid when mainEntity contains at least one Question
	 * node with a non-empty acceptedAnswer.text.
	 */
	private function validate_faq_schema( array $node ): bool {
		if ( empty( $node['mainEntity'] ) || ! is_array( $node['mainEntity'] ) ) {
			return false;
		}
		foreach ( $node['mainEntity'] as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$item_types = isset( $item['@type'] )
				? ( is_array( $item['@type'] ) ? $item['@type'] : [ $item['@type'] ] )
				: [];
			if ( ! in_array( 'Question', $item_types, true ) ) {
				continue;
			}
			$text = $item['acceptedAnswer']['text'] ?? '';
			if ( is_string( $text ) && trim( $text ) !== '' ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Article schema is valid when it carries at least one identifying property.
	 * Intentionally permissive — Google Rich Results accepts minimal Article schema.
	 */
	private function validate_article_schema( array $node ): bool {
		return ! empty( $node['headline'] )
			|| ! empty( $node['name'] )
			|| ! empty( $node['url'] )
			|| ! empty( $node['mainEntityOfPage'] );
	}

	// ── Storage ───────────────────────────────────────────────────────────────

	private function store_cache( int $post_id, array $result ): void {
		update_post_meta( $post_id, self::META_KEY, wp_json_encode( $result ) );
		update_post_meta( $post_id, self::META_KEY_TIME, time() );
	}
}
