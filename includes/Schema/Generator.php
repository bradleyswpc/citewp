<?php
/**
 * Generates JSON-LD schema markup from post content.
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Schema;

use CiteWP\Aiso\Scoring\ContentAnalysis;

defined( 'ABSPATH' ) || exit;

final class Generator {

	/** @var array<int, ContentAnalysis> Memoization cache — one entry per post ID per request. */
	private array $analysis_cache = [];

	/** @var array<int, array<int, array{question: string, answer: string}>> Memoized FAQ pairs — keyed by post ID. */
	private array $pairs_cache = [];

	/**
	 * Returns a complete Article JSON-LD array for the given post.
	 *
	 * @return array<string, mixed>
	 */
	public function generate_article_schema( \WP_Post $post ): array {
		$analysis = $this->analysis_for( $post );

		$schema = [
			'@context' => 'https://schema.org',
			'@type'    => 'Article',
			'headline' => wp_strip_all_tags( get_the_title( $post ) ),
		];

		// Dates.
		if ( $post->post_date_gmt && '0000-00-00 00:00:00' !== $post->post_date_gmt ) {
			$schema['datePublished'] = gmdate( 'c', (int) strtotime( $post->post_date_gmt ) );
		}
		if ( $post->post_modified_gmt && '0000-00-00 00:00:00' !== $post->post_modified_gmt ) {
			$schema['dateModified'] = gmdate( 'c', (int) strtotime( $post->post_modified_gmt ) );
		}

		// Description: excerpt first, then first paragraph.
		$description = '';
		if ( ! empty( $post->post_excerpt ) ) {
			$description = wp_strip_all_tags( $post->post_excerpt );
		} elseif ( $analysis->first_paragraph !== '' ) {
			$description = $analysis->first_paragraph;
		}
		if ( $description !== '' ) {
			$schema['description'] = $description;
		}

		// Author.
		if ( $post->post_author ) {
			$author_name = get_the_author_meta( 'display_name', (int) $post->post_author );
			if ( $author_name ) {
				$schema['author'] = [
					'@type' => 'Person',
					'name'  => $author_name,
					'url'   => get_author_posts_url( (int) $post->post_author ),
				];
			}
		}

		// Featured image.
		$thumb_id = get_post_thumbnail_id( $post->ID );
		if ( $thumb_id ) {
			$image_url = get_the_post_thumbnail_url( $post->ID, 'full' );
			if ( $image_url ) {
				$schema['image'] = $image_url;
			}
		}

		// Publisher: site icon → custom logo → name only (logo omitted if neither found).
		$publisher = [
			'@type' => 'Organization',
			'name'  => get_bloginfo( 'name' ),
			'url'   => home_url( '/' ),
		];
		$site_icon = get_site_icon_url( 60 );
		if ( $site_icon ) {
			$publisher['logo'] = [ '@type' => 'ImageObject', 'url' => $site_icon ];
		} else {
			$logo_id = (int) get_theme_mod( 'custom_logo', 0 );
			if ( $logo_id ) {
				$logo_url = wp_get_attachment_image_url( $logo_id, 'full' );
				if ( $logo_url ) {
					$publisher['logo'] = [ '@type' => 'ImageObject', 'url' => $logo_url ];
				}
			}
		}
		$schema['publisher'] = $publisher;

		// Canonical URL.
		$schema['mainEntityOfPage'] = [
			'@type' => 'WebPage',
			'@id'   => get_permalink( $post->ID ),
		];

		return $schema;
	}

	/**
	 * Returns a complete FAQPage JSON-LD array, or an empty array if fewer than 2 Q/A pairs are found.
	 *
	 * @return array<string, mixed>
	 */
	public function generate_faq_schema( \WP_Post $post ): array {
		$pairs = $this->get_faq_pairs( $post );

		if ( count( $pairs ) < 2 ) {
			return [];
		}

		$questions = [];
		foreach ( $pairs as $pair ) {
			$questions[] = [
				'@type'          => 'Question',
				'name'           => $pair['question'],
				'acceptedAnswer' => [
					'@type' => 'Answer',
					'text'  => $pair['answer'],
				],
			];
		}

		return [
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $questions,
		];
	}

	/**
	 * Returns FAQ pairs after applying the X15 extensibility filter.
	 *
	 * Filter: citewp_aiso/schema/faq_pairs
	 * Allows third-party code (e.g. FB29 schema type expansion) to add, remove,
	 * or modify detected FAQ pairs before schema generation and pair counting.
	 *
	 * @param \WP_Post $post
	 * @return array<int, array{question: string, answer: string}>
	 */
	private function get_faq_pairs( \WP_Post $post ): array {
		if ( ! isset( $this->pairs_cache[ $post->ID ] ) ) {
			$pairs = $this->extract_faq_pairs( $this->analysis_for( $post ) );
			/** @var array<int, array{question: string, answer: string}> $pairs */
			$this->pairs_cache[ $post->ID ] = (array) apply_filters( 'citewp_aiso/schema/faq_pairs', $pairs, $post );
		}
		return $this->pairs_cache[ $post->ID ];
	}

	/**
	 * Returns the number of FAQ pairs detected in the post content.
	 * Used by SchemaController to populate the faq_count field in the REST response,
	 * which drives the 3-state message in the Schema Suggestions panel.
	 *
	 * @param \WP_Post $post
	 * @return int
	 */
	public function count_faq_pairs( \WP_Post $post ): int {
		return count( $this->get_faq_pairs( $post ) );
	}

	/**
	 * Returns top-level @type values found in JSON-LD blocks within post_content.
	 *
	 * Deliberately does NOT recurse into nested sub-objects (author → Person,
	 * publisher → Organization, mainEntityOfPage → WebPage). Those are structural
	 * sub-nodes of an Article/FAQPage, not independent schema types on the page.
	 * Only @graph nodes are descended into, since @graph is a flat list of top-level
	 * entities by convention.
	 *
	 * Limitation: only detects schema embedded in post_content (Custom HTML blocks).
	 * Schema injected by Yoast / Rank Math / AIOSEO via wp_head hooks is output at
	 * template render time and is not stored in post_content — detecting it would
	 * require fetching the live front-end URL, which is too expensive for a REST call.
	 * For v1 we accept this gap: "already detected" badges reflect in-content schema only.
	 *
	 * @return string[]
	 */
	public function detect_existing_types( \WP_Post $post ): array {
		$html  = $this->analysis_for( $post )->rendered_html;
		$types = [];

		if ( ! preg_match_all( '#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $m ) ) {
			return $types;
		}

		foreach ( $m[1] as $blob ) {
			$decoded = json_decode( trim( $blob ), true );
			if ( ! is_array( $decoded ) ) {
				continue;
			}
			$this->collect_root_types( $decoded, $types );
		}

		return array_values( array_unique( $types ) );
	}

	/**
	 * Returns the text of the first <p> after $node, stopping at the next heading.
	 */
	private function first_p_after( \DOMNode $node ): string {
		$sib = $node->nextSibling;
		while ( $sib ) {
			if ( $sib instanceof \DOMElement ) {
				if ( $sib->nodeName === 'p' ) {
					return trim( wp_strip_all_tags( $sib->textContent ) );
				}
				if ( preg_match( '/^h[1-6]$/i', $sib->nodeName ) ) {
					break;
				}
				if ( in_array( $sib->nodeName, [ 'div', 'section' ], true ) ) {
					foreach ( $sib->childNodes as $child ) {
						if ( $child instanceof \DOMElement && $child->nodeName === 'p' ) {
							return trim( wp_strip_all_tags( $child->textContent ) );
						}
					}
				}
			}
			$sib = $sib->nextSibling;
		}
		return '';
	}

	/**
	 * Returns the text body of a <details> element, excluding the <summary> text.
	 */
	private function details_body( \DOMElement $details, \DOMElement $summary ): string {
		$parts = [];
		foreach ( $details->childNodes as $child ) {
			if ( $child->isSameNode( $summary ) ) {
				continue;
			}
			$text = trim( wp_strip_all_tags( $child->textContent ?? '' ) );
			if ( $text !== '' ) {
				$parts[] = $text;
			}
		}
		return implode( ' ', $parts );
	}

	/**
	 * Returns the answer text for a WAI-ARIA accordion button element.
	 * Resolution: aria-controls → ID lookup; next sibling div; parent's next sibling.
	 */
	private function aria_answer( \DOMXPath $xpath, \DOMElement $btn ): string {
		$controls = $btn->getAttribute( 'aria-controls' );
		if ( $controls !== '' ) {
			$target = $xpath->query( '//*[@id="' . esc_attr( $controls ) . '"]' )->item( 0 );
			if ( $target ) {
				return trim( wp_strip_all_tags( $target->textContent ) );
			}
		}
		$sib = $btn->nextSibling;
		while ( $sib ) {
			if ( $sib instanceof \DOMElement ) {
				return trim( wp_strip_all_tags( $sib->textContent ) );
			}
			$sib = $sib->nextSibling;
		}
		$parent = $btn->parentNode;
		if ( $parent instanceof \DOMElement ) {
			$sib = $parent->nextSibling;
			while ( $sib ) {
				if ( $sib instanceof \DOMElement ) {
					return trim( wp_strip_all_tags( $sib->textContent ) );
				}
				$sib = $sib->nextSibling;
			}
		}
		return '';
	}

	/**
	 * Returns true if $node has an ancestor element with the given tag name.
	 */
	private function has_ancestor_tag( \DOMNode $node, string $tag ): bool {
		$parent = $node->parentNode;
		while ( $parent instanceof \DOMElement ) {
			if ( strtolower( $parent->nodeName ) === strtolower( $tag ) ) {
				return true;
			}
			$parent = $parent->parentNode;
		}
		return false;
	}

	/**
	 * Collects top-level @type values from a decoded JSON-LD object.
	 * Descends into @graph nodes only — not into arbitrary sub-objects.
	 *
	 * @param array<mixed> $data
	 * @param string[]     $types
	 */
	private function collect_root_types( array $data, array &$types ): void {
		if ( isset( $data['@type'] ) ) {
			$type = $data['@type'];
			if ( is_array( $type ) ) {
				foreach ( $type as $t ) {
					$types[] = (string) $t;
				}
			} else {
				$types[] = (string) $type;
			}
		}
		// Descend into @graph (flat list of top-level entities) but not into sub-nodes.
		if ( isset( $data['@graph'] ) && is_array( $data['@graph'] ) ) {
			foreach ( $data['@graph'] as $node ) {
				if ( is_array( $node ) ) {
					$this->collect_root_types( $node, $types );
				}
			}
		}
	}

	/**
	 * Extracts FAQ pairs from rendered post HTML using DOMDocument.
	 *
	 * Detects 4 patterns:
	 *   1. <h2>/<h3>/<h4> followed by first <p> sibling (existing behaviour preserved)
	 *   2. <details>/<summary> — HTML5 native + Kadence Blocks
	 *   3. Elements with role="button" or aria-expanded — WAI-ARIA accordion pattern
	 *      used by Elementor, Divi, Beaver Builder, Bricks, Spectra
	 *   4. CSS-class containers (class contains "accordion"/"faq"/"toggle"/"collapse")
	 *      — fallback for builders without ARIA roles
	 *
	 * Note: rendered_html = apply_filters('the_content', post_content). Detects content
	 * stored in post_content (Gutenberg/block builders). Elementor/Divi Classic content
	 * stored in post_meta may not appear here depending on the_content filter context.
	 *
	 * @param ContentAnalysis $analysis
	 * @return array<int, array{question: string, answer: string}>
	 */
	private function extract_faq_pairs( ContentAnalysis $analysis ): array {
		$html = trim( $analysis->rendered_html );
		if ( $html === '' ) {
			return [];
		}

		$prev_errors = libxml_use_internal_errors( true );
		$dom         = new \DOMDocument( '1.0', 'UTF-8' );
		$dom->loadHTML(
			'<?xml encoding="utf-8" ?>' . $html,
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		libxml_clear_errors();
		libxml_use_internal_errors( $prev_errors );

		$xpath = new \DOMXPath( $dom );
		$pairs = [];
		$seen  = [];
		$q_re  = '/^(how|what|why|when|where|can|should|is|does|do|will)\b/i';

		// ── Pattern 1: h2 / h3 / h4 + first <p> sibling ────────────────────────
		$headings = $xpath->query( '//h2|//h3|//h4' );
		if ( $headings ) {
			foreach ( $headings as $heading ) {
				$q = trim( wp_strip_all_tags( $heading->textContent ) );
				if ( $q === '' || isset( $seen[ $q ] ) ) {
					continue;
				}
				if ( ! preg_match( $q_re, $q ) && ! str_ends_with( $q, '?' ) ) {
					continue;
				}
				$a = $this->first_p_after( $heading );
				if ( $a === '' ) {
					continue;
				}
				$seen[ $q ] = true;
				$pairs[]    = [ 'question' => $q, 'answer' => $a ];
			}
		}

		// ── Pattern 2: <details> / <summary> ────────────────────────────────────
		$details_list = $xpath->query( '//details' );
		if ( $details_list ) {
			foreach ( $details_list as $details ) {
				/** @var \DOMElement $details */
				$summary_list = $xpath->query( 'summary', $details );
				$summary      = $summary_list ? $summary_list->item( 0 ) : null;
				if ( ! $summary instanceof \DOMElement ) {
					continue;
				}
				$q = trim( wp_strip_all_tags( $summary->textContent ) );
				if ( $q === '' || isset( $seen[ $q ] ) ) {
					continue;
				}
				if ( ! preg_match( $q_re, $q ) && ! str_ends_with( $q, '?' ) ) {
					continue;
				}
				$a = $this->details_body( $details, $summary );
				if ( $a === '' ) {
					continue;
				}
				$seen[ $q ] = true;
				$pairs[]    = [ 'question' => $q, 'answer' => $a ];
			}
		}

		// ── Pattern 3: WAI-ARIA accordion buttons ────────────────────────────────
		$aria_nodes = $xpath->query( '//*[@role="button" or @aria-expanded]' );
		if ( $aria_nodes ) {
			foreach ( $aria_nodes as $btn ) {
				/** @var \DOMElement $btn */
				if ( $this->has_ancestor_tag( $btn, 'details' ) ) {
					continue;
				}
				// Skip outer ARIA wrappers — prefer the innermost ARIA element.
				// Builders like Spectra nest role="button" inside role="tab"/aria-expanded,
				// causing the outer element's textContent (all descendants) to differ from
				// the inner question text, defeating the $seen dedup.
				$inner_aria = $xpath->query( './/*[@role="button" or @aria-expanded]', $btn );
				if ( $inner_aria && $inner_aria->length > 0 ) {
					continue;
				}
				$q = trim( wp_strip_all_tags( $btn->textContent ) );
				if ( $q === '' || isset( $seen[ $q ] ) ) {
					continue;
				}
				if ( ! preg_match( $q_re, $q ) && ! str_ends_with( $q, '?' ) ) {
					continue;
				}
				$a = $this->aria_answer( $xpath, $btn );
				if ( $a === '' ) {
					continue;
				}
				$seen[ $q ] = true;
				$pairs[]    = [ 'question' => $q, 'answer' => $a ];
			}
		}

		// ── Pattern 4: CSS-class accordion containers ────────────────────────────
		$css_query = '//*[contains(@class,"accordion") or contains(@class,"faq")'
			. ' or contains(@class,"toggle") or contains(@class,"collapse")]'
			. '[not(@role) and not(@aria-expanded)]';
		$css_nodes = $xpath->query( $css_query );
		if ( $css_nodes ) {
			foreach ( $css_nodes as $container ) {
				/** @var \DOMElement $container */
				$q_node = $xpath->query(
					'.//*[contains(@class,"question") or contains(@class,"title")'
					. ' or contains(@class,"header") or contains(@class,"heading")]',
					$container
				);
				$a_node = $xpath->query(
					'.//*[contains(@class,"answer") or contains(@class,"body")'
					. ' or contains(@class,"content") or contains(@class,"panel")]',
					$container
				);
				$q_el = $q_node ? $q_node->item( 0 ) : null;
				$a_el = $a_node ? $a_node->item( 0 ) : null;
				if ( ! $q_el || ! $a_el ) {
					continue;
				}
				$q = trim( wp_strip_all_tags( $q_el->textContent ) );
				$a = trim( wp_strip_all_tags( $a_el->textContent ) );
				if ( $q === '' || $a === '' || isset( $seen[ $q ] ) ) {
					continue;
				}
				if ( ! preg_match( $q_re, $q ) && ! str_ends_with( $q, '?' ) ) {
					continue;
				}
				$seen[ $q ] = true;
				$pairs[]    = [ 'question' => $q, 'answer' => $a ];
			}
		}

		return $pairs;
	}

	/**
	 * Returns ContentAnalysis for the given post, reusing the cached instance within the same request.
	 *
	 * Avoids calling apply_filters('the_content') multiple times when generate_article_schema(),
	 * generate_faq_schema(), and detect_existing_types() are all called for the same post.
	 */
	private function analysis_for( \WP_Post $post ): ContentAnalysis {
		if ( ! isset( $this->analysis_cache[ $post->ID ] ) ) {
			$this->analysis_cache[ $post->ID ] = new ContentAnalysis( $post );
		}
		return $this->analysis_cache[ $post->ID ];
	}
}
