<?php
/**
 * Transient cache for generated llms.txt content.
 *
 * Cache TTL: 1 hour. Invalidated when:
 *  - Any post is published, updated, trashed, or deleted
 *  - Plugin settings change
 *  - Theme/plugin activation
 *  - Manual "regenerate" button
 *
 * @package CiteWP
 */

declare( strict_types=1 );

namespace CiteWP\Llms;

defined( 'ABSPATH' ) || exit;

final class Cache {

	public const TTL = HOUR_IN_SECONDS;

	private const KEY_SHORT = 'citewp_llms_short';
	private const KEY_FULL  = 'citewp_llms_full';

	public function register(): void {
		// Bust on any post status transition that affects publication state.
		add_action( 'transition_post_status', [ $this, 'on_post_transition' ], 10, 3 );

		// Bust when an existing post is updated.
		add_action( 'save_post', [ $this, 'on_save_post' ], 10, 2 );

		// Bust on settings save.
		add_action( 'update_option_citewp_llms_settings', [ $this, 'flush' ] );
		add_action( 'update_option_citewp_settings', [ $this, 'flush' ] );

		// Bust on plugin/theme changes (cornerstone meta could change availability).
		add_action( 'activated_plugin', [ $this, 'flush' ] );
		add_action( 'deactivated_plugin', [ $this, 'flush' ] );
		add_action( 'switch_theme', [ $this, 'flush' ] );
	}

	public function get_short(): ?string {
		$value = get_transient( self::KEY_SHORT );
		return is_string( $value ) ? $value : null;
	}

	public function get_full(): ?string {
		$value = get_transient( self::KEY_FULL );
		return is_string( $value ) ? $value : null;
	}

	public function set_short( string $content ): void {
		set_transient( self::KEY_SHORT, $content, self::TTL );
	}

	public function set_full( string $content ): void {
		set_transient( self::KEY_FULL, $content, self::TTL );
	}

	public function flush(): void {
		delete_transient( self::KEY_SHORT );
		delete_transient( self::KEY_FULL );
	}

	/**
	 * Only flush when a post crosses the publish boundary.
	 *
	 * @param string   $new
	 * @param string   $old
	 * @param \WP_Post $post
	 */
	public function on_post_transition( string $new, string $old, \WP_Post $post ): void {
		if ( $new === $old ) {
			return;
		}
		if ( $new === 'publish' || $old === 'publish' ) {
			$this->flush();
		}
	}

	public function on_save_post( int $post_id, \WP_Post $post ): void {
		// Skip autosaves and revisions.
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( $post->post_status !== 'publish' ) {
			return;
		}
		$this->flush();
	}
}
