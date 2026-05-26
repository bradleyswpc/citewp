<?php
/**
 * Outputs stored CiteWP schema via wp_head.
 *
 * Emits unconditionally — conflict prevention lives at inject time (the REST
 * endpoint), not here. If the meta has an entry, it belongs there.
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Schema;

defined( 'ABSPATH' ) || exit;

final class HeadInjector {

	public const META_KEY = '_citewp_aiso_injected_schema';

	public function register(): void {
		add_action( 'wp_head', [ $this, 'emit' ], 10 );
	}

	public function emit(): void {
		if ( ! is_singular() ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		$stored = $this->get_stored( $post->ID );
		if ( empty( $stored ) ) {
			return;
		}
		$blocks = [];
		foreach ( $stored as $schema ) {
			$json = wp_json_encode(
				$schema,
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
			);
			if ( $json !== false ) {
				$blocks[] = '<script type="application/ld+json">' . $json . '</script>';
			}
		}
		if ( empty( $blocks ) ) {
			return;
		}
		echo '<!-- CiteWP AI Search Optimizer - https://citewp.com -->' . "\n";
		echo implode( "\n", $blocks ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safe: each block is a hardcoded opening script tag, wp_json_encode output (JSON_HEX_TAG flag escapes angle brackets in string values so a closing script tag cannot appear), and a hardcoded closing script tag. No user data reaches the output unescaped.
		echo '<!-- /CiteWP AI Search Optimizer -->' . "\n";
	}

	/**
	 * @return array<string, array<mixed>>
	 */
	public function get_stored( int $post_id ): array {
		$raw = get_post_meta( $post_id, self::META_KEY, true );
		if ( ! is_string( $raw ) || $raw === '' ) {
			return [];
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : [];
	}

	/**
	 * @param array<mixed> $schema
	 */
	public function store( int $post_id, string $type, array $schema ): void {
		$stored          = $this->get_stored( $post_id );
		$stored[ $type ] = $schema;
		update_post_meta( $post_id, self::META_KEY, wp_json_encode( $stored ) );
	}

	public function remove( int $post_id, string $type ): void {
		$stored = $this->get_stored( $post_id );
		unset( $stored[ $type ] );
		if ( empty( $stored ) ) {
			delete_post_meta( $post_id, self::META_KEY );
		} else {
			update_post_meta( $post_id, self::META_KEY, wp_json_encode( $stored ) );
		}
	}
}
