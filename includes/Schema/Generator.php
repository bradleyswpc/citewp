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

	private int              $cached_post_id  = 0;
	private ?ContentAnalysis $cached_analysis = null;

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
		$pairs = $this->extract_faq_pairs( $this->analysis_for( $post ) );

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
	 * Extract FAQ Q/A pairs from rendered post HTML.
	 *
	 * A heading (h2/h3) qualifies as a question if its text:
	 *   - starts with a question word (how, what, why, when, where, can, should, is, does, do, will), OR
	 *   - ends with '?'
	 *
	 * The first <p> following each qualifying heading is used as the answer.
	 * Returns an empty array if fewer than 2 pairs are found.
	 *
	 * @return array<int, array{question: string, answer: string}>
	 */
	private function extract_faq_pairs( ContentAnalysis $analysis ): array {
		$parts = preg_split( '/<h[23][^>]*>/i', $analysis->rendered_html, -1, PREG_SPLIT_NO_EMPTY );
		if ( $parts === false || count( $parts ) < 2 ) {
			return [];
		}

		$question_re = '/^(how|what|why|when|where|can|should|is|does|do|will)\b/i';
		$pairs       = [];

		// $parts[0] is content before the first h2/h3 — skip it.
		foreach ( array_slice( $parts, 1 ) as $part ) {
			// Extract heading text (everything before the closing </h2> or </h3>).
			if ( ! preg_match( '#^(.*?)</h[23]>#is', $part, $heading_m ) ) {
				continue;
			}
			$heading_text = trim( wp_strip_all_tags( $heading_m[1] ) );

			// Accept: starts with a question word, OR ends with '?'.
			if ( ! preg_match( $question_re, $heading_text ) && ! str_ends_with( $heading_text, '?' ) ) {
				continue;
			}

			// Restrict search to content before the next h2/h3 so block-injected markup
			// between a heading and its prose paragraph doesn't capture the wrong <p>.
			$after_heading = substr( $part, strlen( $heading_m[0] ) );
			$segments      = preg_split( '/<h[23][^>]*>/i', $after_heading, 2 );
			$search_window = ( $segments !== false && isset( $segments[0] ) ) ? $segments[0] : $after_heading;

			if ( ! preg_match( '#<p[^>]*>(.*?)</p>#is', $search_window, $para_m ) ) {
				continue;
			}
			$answer = trim( wp_strip_all_tags( $para_m[1] ) );
			if ( $answer === '' ) {
				continue;
			}

			$pairs[] = [
				'question' => $heading_text,
				'answer'   => $answer,
			];
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
		if ( null === $this->cached_analysis || $this->cached_post_id !== $post->ID ) {
			$this->cached_analysis = new ContentAnalysis( $post );
			$this->cached_post_id  = $post->ID;
		}
		return $this->cached_analysis;
	}
}
