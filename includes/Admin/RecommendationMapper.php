<?php
/**
 * Maps scoring signal IDs to human-readable recommendation copy.
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Admin;

defined( 'ABSPATH' ) || exit;

final class RecommendationMapper {

	/**
	 * @var array<string, array{label: string, copy: string, category: string}>
	 */
	private const MAP = [
		'faq_schema_or_qa'   => [
			'label'    => 'FAQ schema or Q&A pattern',
			'category' => 'structure',
			'copy'     => 'Add a FAQ section with schema markup. AI engines extract Q&A pairs directly from structured data.',
		],
		'heading_hierarchy'  => [
			'label'    => 'Heading hierarchy',
			'category' => 'structure',
			'copy'     => 'Use H2 and H3 headings in logical order. Well-structured headings help AI parse your content.',
		],
		'structured_blocks'  => [
			'label'    => 'Lists or tables',
			'category' => 'structure',
			'copy'     => 'Add bullet lists or data tables where appropriate. Structured content is easier for AI to extract.',
		],
		'answer_first'       => [
			'label'    => 'Answer-first format',
			'category' => 'structure',
			'copy'     => 'Start posts with a direct answer (50–160 words). AI engines favour content that answers the question upfront.',
		],
		'paragraph_chunks'   => [
			'label'    => 'Self-contained passages',
			'category' => 'structure',
			'copy'     => 'Keep paragraphs between 80–200 words. Self-contained chunks are easier for AI to extract as citations.',
		],
		'word_count'         => [
			'label'    => 'Word count',
			'category' => 'structure',
			'copy'     => 'Aim for at least 600 words. Longer, substantive posts are cited more often than thin content.',
		],
		'statistics'         => [
			'label'    => 'Statistics density',
			'category' => 'citability',
			'copy'     => 'Include specific statistics, percentages, or data points. Posts with concrete numbers are cited more often.',
		],
		'external_citations' => [
			'label'    => 'External citations',
			'category' => 'citability',
			'copy'     => 'Link to 2–3 authoritative external sources. Citations signal credibility to AI retrieval systems.',
		],
		'entities'           => [
			'label'    => 'Named entity density',
			'category' => 'citability',
			'copy'     => 'Mention specific people, places, organisations, and products. Entity-rich content performs better in AI retrieval.',
		],
		'non_promotional'    => [
			'label'    => 'Non-promotional tone',
			'category' => 'citability',
			'copy'     => 'Reduce promotional language. AI systems favour objective, informational content over sales copy.',
		],
		'freshness'          => [
			'label'    => 'Freshness',
			'category' => 'citability',
			'copy'     => 'Update older posts with recent information. Freshness signals help AI prefer newer, relevant content.',
		],
		'audience_use_case'  => [
			'label'    => 'Defined audience or use case',
			'category' => 'citability',
			'copy'     => 'Clearly define who the content is for. AI systems match content to specific user intents.',
		],
		'author_byline'      => [
			'label'    => 'Author byline & E-E-A-T',
			'category' => 'authority',
			'copy'     => 'Add an author bio with credentials. Authorship signals expertise and builds E-E-A-T trust with AI crawlers.',
		],
		'internal_links'     => [
			'label'    => 'Internal link density',
			'category' => 'authority',
			'copy'     => 'Link to 2–5 related posts on your site. Internal linking reinforces topical authority.',
		],
		'schema'             => [
			'label'    => 'Schema markup',
			'category' => 'authority',
			'copy'     => 'Add JSON-LD schema to your post content via the Schema Suggestions panel, or verify your SEO plugin is configured to output schema for this post type. Structured data helps AI engines understand and cite your content.',
		],
		'meta_description'   => [
			'label'    => 'Meta description',
			'category' => 'authority',
			'copy'     => 'Write a clear meta description. AI systems use meta descriptions to understand page intent.',
		],
		'featured_image'     => [
			'label'    => 'Featured image with alt',
			'category' => 'authority',
			'copy'     => 'Set a featured image with descriptive alt text. Rich media signals completeness and improves citation probability.',
		],
	];

	/**
	 * @return array{label: string, copy: string, category: string}|null
	 */
	public function get( string $signal_id ): ?array {
		return self::MAP[ $signal_id ] ?? null;
	}

	/**
	 * @param  string[]                                                        $signal_ids
	 * @return array<string, array{label: string, copy: string, category: string}>
	 */
	public function get_many( array $signal_ids ): array {
		$out = [];
		foreach ( $signal_ids as $id ) {
			if ( isset( self::MAP[ $id ] ) ) {
				$out[ $id ] = self::MAP[ $id ];
			}
		}
		return $out;
	}
}
