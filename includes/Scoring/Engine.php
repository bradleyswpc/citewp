<?php
/**
 * GEO Score engine.
 *
 * Runs all signals against a parsed ContentAnalysis and produces a ScoreResult.
 *
 * SCORING RUBRIC v1 (100 points total):
 *
 * STRUCTURE (35 points)
 *   - has_faq_schema_or_qa_pattern .... 8
 *   - heading_hierarchy_valid ......... 6
 *   - structured_blocks (lists/tables). 5
 *   - answer_first_formatting ......... 8
 *   - paragraph_chunk_size ............ 4
 *   - word_count_appropriate .......... 4
 *
 * CITABILITY (40 points)
 *   - statistics_density ............. 10
 *   - inline_external_citations ....... 8
 *   - entity_density .................. 7
 *   - non_promotional_tone ............ 8
 *   - freshness_signal ................ 4
 *   - audience_or_use_case ............ 3
 *
 * AUTHORITY (25 points)
 *   - author_byline_eeat .............. 6
 *   - internal_link_density ........... 5
 *   - schema_present_appropriate ...... 6
 *   - meta_description_substantive .... 4
 *   - featured_image_with_alt ......... 4
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Scoring;

use CiteWP\Aiso\Schema\Detector;

defined( 'ABSPATH' ) || exit;

final class Engine {

	private Detector $detector;

	public function __construct( ?Detector $detector = null ) {
		$this->detector = $detector ?? new Detector();
	}

	public function score( \WP_Post $post, bool $explicit_recalculate = false ): ScoreResult {
		$analysis = new ContentAnalysis( $post );
		$result   = new ScoreResult();

		// --- Structure signals (35 max) ---
		$result->signals[] = $this->check_faq_schema_or_qa( $analysis, $explicit_recalculate );
		$result->signals[] = $this->check_heading_hierarchy( $analysis );
		$result->signals[] = $this->check_structured_blocks( $analysis );
		$result->signals[] = $this->check_answer_first( $analysis );
		$result->signals[] = $this->check_paragraph_chunks( $analysis );
		$result->signals[] = $this->check_word_count( $analysis );

		// --- Citability signals (40 max) ---
		$result->signals[] = $this->check_statistics( $analysis );
		$result->signals[] = $this->check_external_citations( $analysis );
		$result->signals[] = $this->check_entities( $analysis );
		$result->signals[] = $this->check_non_promotional( $analysis );
		$result->signals[] = $this->check_freshness( $analysis );
		$result->signals[] = $this->check_audience_use_case( $analysis );

		// --- Authority signals (25 max) ---
		$result->signals[] = $this->check_author_byline( $analysis );
		$result->signals[] = $this->check_internal_links( $analysis );
		$result->signals[] = $this->check_schema( $analysis, $explicit_recalculate );
		$result->signals[] = $this->check_meta_description( $analysis );
		$result->signals[] = $this->check_featured_image( $analysis );

		// Roll up subtotals by category.
		foreach ( $result->signals as $sig ) {
			match ( $sig->category ) {
				'structure'  => $result->structure_score  += $sig->score,
				'citability' => $result->citability_score += $sig->score,
				'authority'  => $result->authority_score  += $sig->score,
				default      => null,
			};
		}

		$result->compute_total();
		return $result;
	}

	// =========================================================================
	// STRUCTURE SIGNALS
	// =========================================================================

	private function check_faq_schema_or_qa( ContentAnalysis $a, bool $explicit_recalculate = false ): SignalResult {
		$schema = $this->detector->get_detected_types( $a->post->ID, $explicit_recalculate );

		// Full credit: valid FAQPage or Question types detected from any emitter.
		if ( $schema['faq_valid'] || in_array( 'Question', $schema['types'], true ) ) {
			return new SignalResult(
				'faq_schema_or_qa', 'structure', 'FAQ schema or Q&A pattern',
				8, 8, 'pass',
				'FAQ schema detected — AI engines can extract Q&A pairs directly.'
			);
		}

		// Cold-start fallback: check ContentAnalysis in-content schema.
		if ( $schema['state'] === 'not_verified' && $a->has_faq_schema ) {
			return new SignalResult(
				'faq_schema_or_qa', 'structure', 'FAQ schema or Q&A pattern',
				8, 8, 'pass',
				'FAQ schema detected — AI engines can extract Q&A pairs directly.'
			);
		}

		// Partial credit: question-pattern headings (How, What, Why, When, Can, Should, Is, Does).
		$q_headings = 0;
		foreach ( $a->headings as $h ) {
			if ( preg_match( '/^(how|what|why|when|where|can|should|is|does|do|will)\b/i', $h['text'] ) ) {
				$q_headings++;
			}
		}
		if ( $q_headings >= 3 ) {
			return new SignalResult(
				'faq_schema_or_qa', 'structure', 'FAQ schema or Q&A pattern',
				5, 8, 'partial',
				sprintf( '%d question-format headings detected. Add FAQ schema for full credit.', $q_headings ),
				'Use a schema plugin (Rank Math, Yoast) or add a JSON-LD block to mark up Q&A sections as FAQPage schema.'
			);
		}
		if ( $q_headings >= 1 ) {
			return new SignalResult(
				'faq_schema_or_qa', 'structure', 'FAQ schema or Q&A pattern',
				2, 8, 'partial',
				sprintf( 'Only %d question-format heading found.', $q_headings ),
				'Add more question-format subheadings (How, What, Why) and FAQ schema.'
			);
		}

		return new SignalResult(
			'faq_schema_or_qa', 'structure', 'FAQ schema or Q&A pattern',
			0, 8, 'fail',
			'No FAQ schema or question-format headings found.',
			'AI engines weight FAQ-structured content ~40% higher. Add Q&A sections with FAQ schema.'
		);
	}

	private function check_heading_hierarchy( ContentAnalysis $a ): SignalResult {
		if ( empty( $a->headings ) ) {
			return new SignalResult(
				'heading_hierarchy', 'structure', 'Heading hierarchy',
				0, 6, 'fail',
				'No headings found in content.',
				'Add H2 subheadings to break up your content for AI extraction.'
			);
		}

		$last_level = 1; // H1 is implicit (the title).
		$violations = 0;
		foreach ( $a->headings as $h ) {
			if ( $h['level'] > $last_level + 1 ) {
				$violations++;
			}
			$last_level = $h['level'];
		}

		if ( $violations === 0 && count( $a->headings ) >= 2 ) {
			return new SignalResult(
				'heading_hierarchy', 'structure', 'Heading hierarchy',
				6, 6, 'pass',
				'Heading hierarchy is well-structured.'
			);
		}
		if ( $violations === 0 ) {
			return new SignalResult(
				'heading_hierarchy', 'structure', 'Heading hierarchy',
				3, 6, 'partial',
				'Heading hierarchy is valid but the content has only one heading.',
				'Add 2-3 more H2 subheadings to improve scannability.'
			);
		}

		return new SignalResult(
			'heading_hierarchy', 'structure', 'Heading hierarchy',
			max( 0, 6 - ( $violations * 2 ) ), 6, 'partial',
			sprintf( '%d heading-level skip(s) detected.', $violations ),
			'Don\'t skip heading levels (e.g. H2 → H4). Use H2 → H3 → H4 in order.'
		);
	}

	private function check_structured_blocks( ContentAnalysis $a ): SignalResult {
		$score = 0;
		if ( $a->has_lists )  $score += 3;
		if ( $a->has_tables ) $score += 2;

		if ( $score >= 5 ) {
			return new SignalResult(
				'structured_blocks', 'structure', 'Lists or tables',
				5, 5, 'pass',
				'Both lists and tables found.'
			);
		}
		if ( $score > 0 ) {
			return new SignalResult(
				'structured_blocks', 'structure', 'Lists or tables',
				$score, 5, 'partial',
				$a->has_lists ? 'Lists found, no tables.' : 'Tables found, no lists.',
				'Adding the missing structure type can improve AI extraction.'
			);
		}
		return new SignalResult(
			'structured_blocks', 'structure', 'Lists or tables',
			0, 5, 'fail',
			'No lists or tables found.',
			'Multi-modal structured content sees 156% higher AI selection rates. Add bullet lists or comparison tables.'
		);
	}

	private function check_answer_first( ContentAnalysis $a ): SignalResult {
		$first = $a->first_paragraph;
		if ( $first === '' ) {
			return new SignalResult(
				'answer_first', 'structure', 'Answer-first format',
				0, 8, 'fail',
				'No opening paragraph detected.',
				'Open with a 2-3 sentence summary that directly answers the page\'s implied question.'
			);
		}

		$word_count = str_word_count( $first );

		// Sweet spot: 30-80 words for a self-contained opening answer.
		if ( $word_count >= 30 && $word_count <= 80 ) {
			return new SignalResult(
				'answer_first', 'structure', 'Answer-first format',
				8, 8, 'pass',
				'Opening paragraph is well-sized for direct extraction by AI.'
			);
		}
		if ( $word_count > 80 && $word_count <= 167 ) {
			return new SignalResult(
				'answer_first', 'structure', 'Answer-first format',
				6, 8, 'partial',
				'Opening paragraph is slightly long for a direct-answer extraction.',
				'AI engines extract best from 30-80 word opening summaries.'
			);
		}
		if ( $word_count < 30 ) {
			return new SignalResult(
				'answer_first', 'structure', 'Answer-first format',
				3, 8, 'partial',
				'Opening paragraph is too short to be a useful summary.',
				'Expand to 30-80 words that directly answer the implied question.'
			);
		}
		return new SignalResult(
			'answer_first', 'structure', 'Answer-first format',
			2, 8, 'partial',
			'Opening paragraph is too long for clean extraction.',
			'Lead with a 30-80 word TL;DR before going deeper.'
		);
	}

	private function check_paragraph_chunks( ContentAnalysis $a ): SignalResult {
		// Count paragraphs that fall in the 134-167 word "extraction sweet spot."
		if ( ! preg_match_all( '#<p[^>]*>(.*?)</p>#is', $a->rendered_html, $m ) ) {
			return new SignalResult(
				'paragraph_chunks', 'structure', 'Self-contained passages',
				0, 4, 'fail',
				'No paragraph blocks detected.'
			);
		}
		$sweet_spot = 0;
		foreach ( $m[1] as $p ) {
			$wc = str_word_count( wp_strip_all_tags( $p ) );
			if ( $wc >= 80 && $wc <= 200 ) {
				$sweet_spot++;
			}
		}
		if ( $sweet_spot >= 3 ) {
			return new SignalResult(
				'paragraph_chunks', 'structure', 'Self-contained passages',
				4, 4, 'pass',
				sprintf( '%d paragraphs in the AI-extraction sweet spot (80-200 words).', $sweet_spot )
			);
		}
		if ( $sweet_spot >= 1 ) {
			return new SignalResult(
				'paragraph_chunks', 'structure', 'Self-contained passages',
				2, 4, 'partial',
				sprintf( 'Only %d paragraph(s) in the extraction sweet spot.', $sweet_spot ),
				'Aim for self-contained 80-200 word passages that fully answer a sub-question.'
			);
		}
		return new SignalResult(
			'paragraph_chunks', 'structure', 'Self-contained passages',
			0, 4, 'fail',
			'No paragraphs in the AI-extraction sweet spot.',
			'Restructure into 80-200 word self-contained passages.'
		);
	}

	private function check_word_count( ContentAnalysis $a ): SignalResult {
		$is_page = $a->post->post_type === 'page';
		$min     = $is_page ? 300 : 800;

		if ( $a->word_count >= $min ) {
			return new SignalResult(
				'word_count', 'structure', 'Word count',
				4, 4, 'pass',
				sprintf( '%d words — adequate length.', $a->word_count )
			);
		}
		if ( $a->word_count >= (int) ( $min * 0.6 ) ) {
			return new SignalResult(
				'word_count', 'structure', 'Word count',
				2, 4, 'partial',
				sprintf( '%d words — below the %d-word target for this content type.', $a->word_count, $min ),
				'Substantive content (800+ words for posts, 300+ for pages) gets more citations.'
			);
		}
		return new SignalResult(
			'word_count', 'structure', 'Word count',
			0, 4, 'fail',
			sprintf( '%d words — too short for AI to consider authoritative.', $a->word_count ),
			sprintf( 'Aim for at least %d words.', $min )
		);
	}

	// =========================================================================
	// CITABILITY SIGNALS
	// =========================================================================

	private function check_statistics( ContentAnalysis $a ): SignalResult {
		// Per 1000 words. Density matters more than raw count.
		$per_1k = $a->word_count > 0 ? ( $a->statistic_count / $a->word_count ) * 1000 : 0;

		if ( $per_1k >= 8 ) {
			return new SignalResult(
				'statistics', 'citability', 'Statistics density',
				10, 10, 'pass',
				sprintf( '%d statistics found (%.1f per 1k words).', $a->statistic_count, $per_1k )
			);
		}
		if ( $per_1k >= 4 ) {
			return new SignalResult(
				'statistics', 'citability', 'Statistics density',
				7, 10, 'partial',
				sprintf( '%d statistics — moderate density.', $a->statistic_count ),
				'Adding statistics is shown to improve AI visibility by ~41%. Aim for 8+ per 1000 words.'
			);
		}
		if ( $a->statistic_count >= 1 ) {
			return new SignalResult(
				'statistics', 'citability', 'Statistics density',
				4, 10, 'partial',
				sprintf( '%d statistics — low density.', $a->statistic_count ),
				'AI engines preferentially cite content with verifiable numbers, percentages, dates, and dollar amounts.'
			);
		}
		return new SignalResult(
			'statistics', 'citability', 'Statistics density',
			0, 10, 'fail',
			'No statistics, percentages, or specific numerical data found.',
			'This is the highest-impact change you can make. Add concrete numbers, percentages, and dates.'
		);
	}

	private function check_external_citations( ContentAnalysis $a ): SignalResult {
		$count = count( $a->external_links );
		if ( $count >= 3 ) {
			return new SignalResult(
				'external_citations', 'citability', 'External citations',
				8, 8, 'pass',
				sprintf( '%d external citations found.', $count )
			);
		}
		if ( $count >= 1 ) {
			return new SignalResult(
				'external_citations', 'citability', 'External citations',
				4, 8, 'partial',
				sprintf( '%d external citations.', $count ),
				'Cross-referenced content correlates strongly (r=0.89) with AI citation. Aim for 3+ authoritative sources.'
			);
		}
		return new SignalResult(
			'external_citations', 'citability', 'External citations',
			0, 8, 'fail',
			'No external citations found.',
			'Cite authoritative sources inline. AI engines weight cross-referenced content much higher.'
		);
	}

	private function check_entities( ContentAnalysis $a ): SignalResult {
		$n = $a->entity_count;
		if ( $n >= 15 ) {
			return new SignalResult(
				'entities', 'citability', 'Named entity density',
				7, 7, 'pass',
				sprintf( '%d named entities — pages with 15+ see 4.8× higher citation.', $n )
			);
		}
		if ( $n >= 8 ) {
			return new SignalResult(
				'entities', 'citability', 'Named entity density',
				4, 7, 'partial',
				sprintf( '%d named entities.', $n ),
				'Reaching 15+ named entities (people, organizations, places, products) improves citation rates significantly.'
			);
		}
		return new SignalResult(
			'entities', 'citability', 'Named entity density',
			$n > 0 ? 2 : 0, 7, $n > 0 ? 'partial' : 'fail',
			sprintf( 'Only %d named entities detected.', $n ),
			'Reference specific people, organizations, products, and places by name.'
		);
	}

	private function check_non_promotional( ContentAnalysis $a ): SignalResult {
		$hits     = $a->promo_hits;
		$density  = $a->word_count > 0 ? ( $hits / $a->word_count ) * 1000 : 0;

		if ( $hits === 0 ) {
			return new SignalResult(
				'non_promotional', 'citability', 'Non-promotional tone',
				8, 8, 'pass',
				'No promotional language detected. Promotional tone correlates -26% with AI citations.'
			);
		}
		if ( $density <= 2 ) {
			return new SignalResult(
				'non_promotional', 'citability', 'Non-promotional tone',
				5, 8, 'partial',
				sprintf( '%d promotional terms found.', $hits ),
				'Watch for "best", "amazing", "industry-leading", and similar superlatives.'
			);
		}
		return new SignalResult(
			'non_promotional', 'citability', 'Non-promotional tone',
			max( 0, 8 - $hits ), 8, 'fail',
			sprintf( '%d promotional/superlative terms found.', $hits ),
			'Promotional tone correlates -26% with AI citation. Replace marketing-speak with specific facts.'
		);
	}

	private function check_freshness( ContentAnalysis $a ): SignalResult {
		$modified  = strtotime( $a->post->post_modified_gmt . ' UTC' );
		$age_days  = $modified ? floor( ( time() - $modified ) / DAY_IN_SECONDS ) : 9999;

		if ( $age_days <= 90 ) {
			return new SignalResult(
				'freshness', 'citability', 'Freshness',
				4, 4, 'pass',
				sprintf( 'Updated %d days ago.', (int) $age_days )
			);
		}
		if ( $age_days <= 365 ) {
			return new SignalResult(
				'freshness', 'citability', 'Freshness',
				2, 4, 'partial',
				sprintf( 'Last updated %d days ago.', (int) $age_days ),
				'Refresh content within the last 90 days for full freshness signal.'
			);
		}
		return new SignalResult(
			'freshness', 'citability', 'Freshness',
			0, 4, 'fail',
			sprintf( 'Last updated %d days ago.', (int) $age_days ),
			'Freshness is a top-3 ranking factor. Update with current data and republish.'
		);
	}

	private function check_audience_use_case( ContentAnalysis $a ): SignalResult {
		$markers = [ 'for ', 'designed for', 'ideal for', 'best for', 'who should', 'when to use', 'use case' ];
		$lower   = strtolower( $a->plain_text );
		$found   = 0;
		foreach ( $markers as $m ) {
			if ( str_contains( $lower, $m ) ) {
				$found++;
			}
		}
		if ( $found >= 2 ) {
			return new SignalResult(
				'audience_use_case', 'citability', 'Defined audience or use case',
				3, 3, 'pass',
				'Audience or use case is explicitly stated.'
			);
		}
		if ( $found === 1 ) {
			return new SignalResult(
				'audience_use_case', 'citability', 'Defined audience or use case',
				2, 3, 'partial',
				'Audience signals present but could be more explicit.'
			);
		}
		return new SignalResult(
			'audience_use_case', 'citability', 'Defined audience or use case',
			0, 3, 'fail',
			'No clear audience or use case stated.',
			'Explicitly state who the content is for and when to use the information.'
		);
	}

	// =========================================================================
	// AUTHORITY SIGNALS
	// =========================================================================

	private function check_author_byline( ContentAnalysis $a ): SignalResult {
		$author_id = (int) $a->post->post_author;
		if ( ! $author_id ) {
			return new SignalResult(
				'author_byline', 'authority', 'Author byline & E-E-A-T',
				0, 6, 'fail',
				'No author assigned to this post.'
			);
		}
		$bio = (string) get_user_meta( $author_id, 'description', true );
		$has_bio = trim( $bio ) !== '';

		// Check for common E-E-A-T meta keys (Yoast author schema, etc).
		$has_extras = (bool) get_user_meta( $author_id, 'twitter', true )
			|| (bool) get_user_meta( $author_id, 'linkedin', true )
			|| (bool) get_user_meta( $author_id, 'url', true );

		if ( $has_bio && $has_extras ) {
			return new SignalResult(
				'author_byline', 'authority', 'Author byline & E-E-A-T',
				6, 6, 'pass',
				'Author has bio and external profile links.'
			);
		}
		if ( $has_bio ) {
			return new SignalResult(
				'author_byline', 'authority', 'Author byline & E-E-A-T',
				4, 6, 'partial',
				'Author bio present but no external profile links.',
				'Add LinkedIn or Twitter profile links to the author profile for stronger E-E-A-T signals.'
			);
		}
		return new SignalResult(
			'author_byline', 'authority', 'Author byline & E-E-A-T',
			1, 6, 'fail',
			'Author has no biographical info.',
			'96% of AI citations come from sources with strong E-E-A-T. Add an author bio with credentials.'
		);
	}

	private function check_internal_links( ContentAnalysis $a ): SignalResult {
		$n = count( $a->internal_links );
		if ( $n >= 3 ) {
			return new SignalResult(
				'internal_links', 'authority', 'Internal link density',
				5, 5, 'pass',
				sprintf( '%d internal links found.', $n )
			);
		}
		if ( $n >= 1 ) {
			return new SignalResult(
				'internal_links', 'authority', 'Internal link density',
				3, 5, 'partial',
				sprintf( '%d internal link(s).', $n ),
				'Aim for 3+ internal links to related content for stronger topical authority.'
			);
		}
		return new SignalResult(
			'internal_links', 'authority', 'Internal link density',
			0, 5, 'fail',
			'No internal links found.',
			'Link to related cornerstone pages on your site to build topical clusters.'
		);
	}

	private function check_schema( ContentAnalysis $a, bool $explicit_recalculate = false ): SignalResult {
		$schema = $this->detector->get_detected_types( $a->post->ID, $explicit_recalculate );

		// Schema confirmed on rendered page — full credit, any emitter.
		if ( $schema['state'] === 'detected' ) {
			return new SignalResult(
				'schema', 'authority', 'Schema markup',
				6, 6, 'pass',
				sprintf( 'Schema types detected: %s.', implode( ', ', $schema['types'] ) )
			);
		}

		// Cold-start: could not reach the rendered page yet.
		if ( $schema['state'] === 'not_verified' ) {
			// Last-resort: check for in-content JSON-LD that the block renderer exposes.
			$inline_types = array_unique( $a->schema_types );
			if ( ! empty( $inline_types ) ) {
				return new SignalResult(
					'schema', 'authority', 'Schema markup',
					6, 6, 'pass',
					sprintf( 'Schema types detected: %s.', implode( ', ', $inline_types ) )
				);
			}
			return new SignalResult(
				'schema', 'authority', 'Schema markup',
				0, 6, 'partial',
				'Schema not yet verified — visit this post\'s URL once, then Recalculate.',
				'Open the post in a browser tab, then click Recalculate in the sidebar to scan for schema.'
			);
		}

		// Confirmed not_found: page was checked, no schema present.
		return new SignalResult(
			'schema', 'authority', 'Schema markup',
			0, 6, 'fail',
			'No schema markup detected.',
			'Install Yoast, Rank Math, or AIOSEO — or use the Schema Suggestions panel to insert JSON-LD directly.'
		);
	}

	private function check_meta_description( ContentAnalysis $a ): SignalResult {
		$desc = (string) get_post_meta( $a->post->ID, '_yoast_wpseo_metadesc', true );
		if ( $desc === '' ) {
			$desc = (string) get_post_meta( $a->post->ID, 'rank_math_description', true );
		}
		if ( $desc === '' ) {
			$desc = (string) get_post_meta( $a->post->ID, '_aioseo_description', true );
		}
		if ( $desc === '' && ! empty( $a->post->post_excerpt ) ) {
			$desc = $a->post->post_excerpt;
		}

		$len = mb_strlen( wp_strip_all_tags( $desc ) );
		if ( $len >= 120 && $len <= 160 ) {
			return new SignalResult(
				'meta_description', 'authority', 'Meta description',
				4, 4, 'pass',
				sprintf( 'Meta description is %d chars — well-sized.', $len )
			);
		}
		if ( $len >= 70 ) {
			return new SignalResult(
				'meta_description', 'authority', 'Meta description',
				2, 4, 'partial',
				sprintf( 'Meta description is %d chars.', $len ),
				'Aim for 120-160 characters for optimal display in search and AI snippets.'
			);
		}
		return new SignalResult(
			'meta_description', 'authority', 'Meta description',
			0, 4, 'fail',
			'Meta description is missing or too short.',
			'Write a 120-160 character description. CiteWP uses this for llms.txt entries too.'
		);
	}

	private function check_featured_image( ContentAnalysis $a ): SignalResult {
		$thumb_id = (int) get_post_thumbnail_id( $a->post );
		if ( ! $thumb_id ) {
			return new SignalResult(
				'featured_image', 'authority', 'Featured image with alt',
				0, 4, 'fail',
				'No featured image set.',
				'Set a featured image with descriptive alt text. Multi-modal content sees 156% higher AI selection.'
			);
		}
		$alt = trim( (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) );
		if ( $alt === '' ) {
			return new SignalResult(
				'featured_image', 'authority', 'Featured image with alt',
				1, 4, 'partial',
				'Featured image present but missing alt text.',
				'Add descriptive alt text to the featured image.'
			);
		}
		if ( str_word_count( $alt ) >= 4 ) {
			return new SignalResult(
				'featured_image', 'authority', 'Featured image with alt',
				4, 4, 'pass',
				'Featured image has substantive alt text.'
			);
		}
		return new SignalResult(
			'featured_image', 'authority', 'Featured image with alt',
			2, 4, 'partial',
			'Alt text is present but very brief.',
			'Aim for 4+ words of descriptive alt text.'
		);
	}
}
