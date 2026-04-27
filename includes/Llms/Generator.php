<?php
/**
 * Builds the llms.txt and llms-full.txt content per spec at https://llmstxt.org/
 *
 * Format:
 *   # Site Name
 *   > Tagline / one-line description
 *
 *   Optional intro paragraph.
 *
 *   ## Section Name
 *   - [Title](URL): Optional description
 *
 * llms-full.txt extends with full content body for each entry.
 *
 * @package CiteWP
 */

declare( strict_types=1 );

namespace CiteWP\Llms;

defined( 'ABSPATH' ) || exit;

final class Generator {

	private ContentSelector $selector;

	public function __construct( ?ContentSelector $selector = null ) {
		$this->selector = $selector ?? new ContentSelector();
	}

	/**
	 * Generate the standard llms.txt (titles + descriptions, no full bodies).
	 */
	public function build_short(): string {
		$out  = $this->build_header();
		$out .= $this->build_sections( false );
		return $out;
	}

	/**
	 * Generate llms-full.txt (includes full post bodies, markdown-formatted).
	 */
	public function build_full(): string {
		$out  = $this->build_header();
		$out .= $this->build_sections( true );
		return $out;
	}

	private function build_header(): string {
		$site_name = wp_strip_all_tags( get_bloginfo( 'name' ) );
		$tagline   = wp_strip_all_tags( get_bloginfo( 'description' ) );
		$site_url  = home_url( '/' );

		$header  = "# {$site_name}\n\n";
		if ( $tagline !== '' ) {
			$header .= "> {$tagline}\n\n";
		}
		$header .= "Site: {$site_url}\n";
		$header .= 'Generated: ' . gmdate( 'Y-m-d' ) . " by CiteWP\n\n";

		return $header;
	}

	private function build_sections( bool $include_bodies ): string {
		$posts = $this->selector->select();
		if ( empty( $posts ) ) {
			return "## Content\n\n_No published content yet._\n";
		}

		// Group by post type for clean section headers.
		$grouped = [];
		foreach ( $posts as $post ) {
			$grouped[ $post->post_type ][] = $post;
		}

		$out = '';
		foreach ( $grouped as $post_type => $bucket ) {
			$label = $this->section_label_for( $post_type );
			$out  .= "## {$label}\n\n";

			foreach ( $bucket as $post ) {
				$out .= $this->format_entry( $post, $include_bodies );
			}

			$out .= "\n";
		}

		return $out;
	}

	private function section_label_for( string $post_type ): string {
		$obj = get_post_type_object( $post_type );
		if ( $obj && ! empty( $obj->labels->name ) ) {
			return (string) $obj->labels->name;
		}
		return ucfirst( $post_type ) . 's';
	}

	private function format_entry( \WP_Post $post, bool $include_body ): string {
		$title = wp_strip_all_tags( get_the_title( $post ) );
		$url   = get_permalink( $post );
		$desc  = $this->extract_description( $post );

		$line = "- [{$title}]({$url})";
		if ( $desc !== '' ) {
			$line .= ": {$desc}";
		}
		$line .= "\n";

		if ( $include_body ) {
			$body  = $this->markdown_body( $post );
			$line .= "\n{$body}\n\n---\n\n";
		}

		return $line;
	}

	/**
	 * Description hierarchy: SEO meta description → manual excerpt → auto excerpt.
	 */
	private function extract_description( \WP_Post $post ): string {
		// Yoast.
		$d = (string) get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true );
		if ( $d !== '' ) {
			return wp_strip_all_tags( $d );
		}

		// Rank Math.
		$d = (string) get_post_meta( $post->ID, 'rank_math_description', true );
		if ( $d !== '' ) {
			return wp_strip_all_tags( $d );
		}

		// AIOSEO.
		$d = (string) get_post_meta( $post->ID, '_aioseo_description', true );
		if ( $d !== '' ) {
			return wp_strip_all_tags( $d );
		}

		// Manual excerpt.
		if ( ! empty( $post->post_excerpt ) ) {
			return wp_strip_all_tags( $post->post_excerpt );
		}

		// Auto excerpt — first 160 chars of cleaned content.
		$plain = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
		$plain = preg_replace( '/\s+/', ' ', $plain ) ?? '';
		$plain = trim( $plain );
		if ( mb_strlen( $plain ) > 160 ) {
			$plain = mb_substr( $plain, 0, 157 ) . '…';
		}
		return $plain;
	}

	/**
	 * Convert post content to a markdown-ish body for llms-full.txt.
	 * Not a full HTML→MD converter; aims for readable, structured plaintext.
	 */
	private function markdown_body( \WP_Post $post ): string {
		$html = apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Calling WP core filter, not registering a plugin hook.
		$html = strip_shortcodes( $html );

		// Convert headings.
		$html = preg_replace_callback(
			'#<h([1-6])[^>]*>(.*?)</h\1>#is',
			static function ( $m ) {
				$level = (int) $m[1];
				$text  = wp_strip_all_tags( $m[2] );
				return "\n\n" . str_repeat( '#', $level ) . " {$text}\n\n";
			},
			$html
		) ?? $html;

		// Lists: rough conversion.
		$html = preg_replace( '#<li[^>]*>(.*?)</li>#is', "- $1\n", $html ) ?? $html;
		$html = preg_replace( '#</?(?:ul|ol)[^>]*>#i', "\n", $html ) ?? $html;

		// Paragraphs and breaks.
		$html = preg_replace( '#</p>\s*<p[^>]*>#i', "\n\n", $html ) ?? $html;
		$html = preg_replace( '#<br\s*/?>#i', "\n", $html ) ?? $html;

		// Strip remaining tags, normalize whitespace.
		$text = wp_strip_all_tags( $html );
		$text = preg_replace( "/\n{3,}/", "\n\n", $text ) ?? $text;

		return trim( $text );
	}
}
