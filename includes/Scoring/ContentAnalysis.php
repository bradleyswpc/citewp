<?php
/**
 * Parses a post's content into structures that signal checks can query.
 *
 * Done once per analysis, then signals query the parsed result.
 * Cheaper than each signal re-parsing the HTML.
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Scoring;

defined( 'ABSPATH' ) || exit;

final class ContentAnalysis {

	public \WP_Post $post;
	public string $rendered_html  = '';
	public string $plain_text     = '';
	public int    $word_count     = 0;
	public string $first_paragraph = '';

	/** @var array<int, array{level: int, text: string}> */
	public array $headings = [];

	public bool $has_lists  = false;
	public bool $has_tables = false;

	/** Found schema types via parsed JSON-LD or schema plugin meta. */
	public array $schema_types = [];

	public bool $has_faq_schema  = false;

	/** @var string[] */
	public array $external_links = [];
	/** @var string[] */
	public array $internal_links = [];

	public int $statistic_count = 0;
	public int $entity_count    = 0;

	/** Promotional language hits (count of superlatives/marketing-speak). */
	public int $promo_hits = 0;

	public function __construct( \WP_Post $post ) {
		$this->post = $post;
		$this->parse();
	}

	private function parse(): void {
		$raw = $this->post->post_content;

		// Render shortcodes/blocks the same way the front-end would.
		$html = apply_filters( 'the_content', $raw ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Calling WP core filter, not registering a plugin hook.
		$html = strip_shortcodes( $html );
		$this->rendered_html = $html;

		// Plain text + word count.
		$plain            = wp_strip_all_tags( $html );
		$plain            = preg_replace( '/\s+/', ' ', $plain ) ?? '';
		$this->plain_text = trim( $plain );
		$this->word_count = str_word_count( $this->plain_text );

		// Headings: extract ALL h1-h6 with their level.
		if ( preg_match_all( '#<h([1-6])[^>]*>(.*?)</h\1>#is', $html, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $match ) {
				$this->headings[] = [
					'level' => (int) $match[1],
					'text'  => trim( wp_strip_all_tags( $match[2] ) ),
				];
			}
		}

		// First paragraph (post answer-first detection).
		if ( preg_match( '#<p[^>]*>(.*?)</p>#is', $html, $m ) ) {
			$this->first_paragraph = trim( wp_strip_all_tags( $m[1] ) );
		}

		// Lists / tables.
		$this->has_lists  = (bool) preg_match( '#<(ul|ol)[^>]*>#i', $html );
		$this->has_tables = (bool) preg_match( '#<table[^>]*>#i', $html );

		// Links — separate internal vs external.
		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( preg_match_all( '#<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>#i', $html, $m ) ) {
			foreach ( $m[1] as $href ) {
				$href_host = wp_parse_url( $href, PHP_URL_HOST );
				if ( $href_host === null || $href_host === '' || $href_host === $home_host ) {
					$this->internal_links[] = $href;
				} else {
					$this->external_links[] = $href;
				}
			}
		}

		// Schema detection: parse any inline JSON-LD blocks.
		$this->detect_schema( $html );

		// Statistics density: numbers, percentages, dollar amounts, year mentions.
		$this->statistic_count = $this->count_statistics( $this->plain_text );

		// Entity count: capitalized multi-word proper noun phrases (rough).
		$this->entity_count = $this->count_entities( $this->plain_text );

		// Promotional tone hits.
		$this->promo_hits = $this->count_promo_language( $this->plain_text );
	}

	private function detect_schema( string $html ): void {
		// Inline JSON-LD scripts.
		if ( preg_match_all( '#<script[^>]*\btype=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $m ) ) {
			foreach ( $m[1] as $blob ) {
				$decoded = json_decode( trim( $blob ), true );
				if ( ! $decoded ) {
					continue;
				}
				$this->collect_schema_types( $decoded );
			}
		}

		// has_faq_schema reflects only post_content-embedded JSON-LD (hand-rolled wp:html blocks).
		// Hook-injected schema (Rank Math, Yoast, AIOSEO) is detected by Schema\Detector instead.
		$this->has_faq_schema = in_array( 'FAQPage', $this->schema_types, true )
			|| in_array( 'Question', $this->schema_types, true );
	}

	/**
	 * @param mixed $data
	 */
	private function collect_schema_types( $data ): void {
		if ( ! is_array( $data ) ) {
			return;
		}
		if ( isset( $data['@type'] ) ) {
			$type = $data['@type'];
			if ( is_array( $type ) ) {
				foreach ( $type as $t ) {
					$this->schema_types[] = (string) $t;
				}
			} else {
				$this->schema_types[] = (string) $type;
			}
		}
		// Recurse into @graph nodes etc.
		foreach ( $data as $value ) {
			if ( is_array( $value ) ) {
				$this->collect_schema_types( $value );
			}
		}
	}

	private function count_statistics( string $text ): int {
		$count = 0;
		// Percentages: 41%, 4.8%
		$count += preg_match_all( '/\b\d+(?:\.\d+)?\s?%/', $text ) ?: 0;
		// Dollar amounts: $5, $1.2M
		$count += preg_match_all( '/\$\d+(?:[,\.]\d+)*[KMB]?\b/i', $text ) ?: 0;
		// Years (2020-2099): 2024, 2026
		$count += preg_match_all( '/\b(?:19|20)\d{2}\b/', $text ) ?: 0;
		// Plain numbers > 2 digits with comma formatting: 1,500 / 15,000
		$count += preg_match_all( '/\b\d{1,3}(?:,\d{3})+\b/', $text ) ?: 0;
		// Multiplier shorthand: 4x, 156%
		$count += preg_match_all( '/\b\d+(?:\.\d+)?x\b/i', $text ) ?: 0;
		return $count;
	}

	private function count_entities( string $text ): int {
		// Heuristic: capitalized 2+ word phrases that aren't sentence starts.
		// Not perfect — full NER would need an external service — but good enough for v1.
		preg_match_all(
			'/(?<![\.\?\!]\s)(?<!^)\b([A-Z][a-z]+(?:\s+[A-Z][a-z]+){1,4})\b/',
			$text,
			$m
		);
		$entities = array_unique( $m[1] ?? [] );

		// Filter common false positives.
		$blacklist = [ 'New York Times', 'Wall Street Journal' ]; // example — could be configurable
		$entities  = array_diff( $entities, $blacklist );

		return count( $entities );
	}

	private function count_promo_language( string $text ): int {
		$promo_terms = [
			'best', 'amazing', 'incredible', 'revolutionary', 'world-class',
			'cutting-edge', 'state-of-the-art', 'industry-leading', 'unparalleled',
			'premier', 'top-notch', 'unbeatable', 'must-have', 'game-changer',
			'next-generation', 'unmatched',
		];
		$count = 0;
		$lower = strtolower( $text );
		foreach ( $promo_terms as $term ) {
			$count += substr_count( $lower, $term );
		}
		return $count;
	}
}
