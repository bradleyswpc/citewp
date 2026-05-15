<?php
/**
 * Post list "GEO Score" column.
 *
 * Adds a sortable column showing the score with a colored grade dot
 * to the All Posts and All Pages screens.
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Admin;

use CiteWP\Aiso\Scoring\Repository;

defined( 'ABSPATH' ) || exit;

final class PostListColumn {

	private const COLUMN_KEY = 'citewp_aiso_geo_score';

	public function register(): void {
		// Posts.
		add_filter( 'manage_posts_columns',                [ $this, 'add_column' ] );
		add_action( 'manage_posts_custom_column',          [ $this, 'render_column' ], 10, 2 );
		add_filter( 'manage_edit-post_sortable_columns',   [ $this, 'mark_sortable' ] );

		// Pages.
		add_filter( 'manage_pages_columns',                [ $this, 'add_column' ] );
		add_action( 'manage_pages_custom_column',          [ $this, 'render_column' ], 10, 2 );
		add_filter( 'manage_edit-page_sortable_columns',   [ $this, 'mark_sortable' ] );

		add_action( 'pre_get_posts',                       [ $this, 'handle_sorting' ] );
		add_action( 'admin_enqueue_scripts',               [ $this, 'enqueue_assets' ] );
	}

	/**
	 * @param array<string, string> $cols
	 * @return array<string, string>
	 */
	public function add_column( array $cols ): array {
		// Insert after title if present, else at end.
		$new = [];
		foreach ( $cols as $key => $label ) {
			$new[ $key ] = $label;
			if ( $key === 'title' ) {
				$new[ self::COLUMN_KEY ] = __( 'GEO Score', 'ai-search-optimizer' );
			}
		}
		if ( ! isset( $new[ self::COLUMN_KEY ] ) ) {
			$new[ self::COLUMN_KEY ] = __( 'GEO Score', 'ai-search-optimizer' );
		}
		return $new;
	}

	public function render_column( string $column, int $post_id ): void {
		if ( $column !== self::COLUMN_KEY ) {
			return;
		}

		$total = get_post_meta( $post_id, Repository::META_KEY_TOTAL, true );
		$grade = get_post_meta( $post_id, Repository::META_KEY_GRADE, true );

		if ( $total === '' || $total === false ) {
			echo '<span class="citewp-aiso-score citewp-aiso-score--none" title="' . esc_attr__( 'Not yet scored', 'ai-search-optimizer' ) . '">—</span>';
			return;
		}

		$grade = is_string( $grade ) && in_array( $grade, [ 'red', 'orange', 'yellow', 'green' ], true )
			? $grade
			: 'red';

		printf(
			'<span class="citewp-aiso-score citewp-aiso-score--%1$s"><span class="citewp-aiso-score__dot"></span>%2$s</span>',
			esc_attr( $grade ),
			esc_html( (string) (int) $total )
		);
	}

	/**
	 * @param array<string, string> $cols
	 * @return array<string, string>
	 */
	public function mark_sortable( array $cols ): array {
		$cols[ self::COLUMN_KEY ] = self::COLUMN_KEY;
		return $cols;
	}

	public function handle_sorting( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( $query->get( 'orderby' ) !== self::COLUMN_KEY ) {
			return;
		}
		$query->set( 'meta_key', Repository::META_KEY_TOTAL );
		$query->set( 'orderby', 'meta_value_num' );
	}

	public function enqueue_assets( string $hook_suffix ): void {
		if ( $hook_suffix !== 'edit.php' ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, [ 'post', 'page' ], true ) ) {
			return;
		}
		wp_enqueue_style(
			'citewp-aiso-post-list-column',
			CITEWP_AISO_PLUGIN_URL . 'admin/css/citewp-aiso-post-list-column.css',
			[],
			CITEWP_AISO_VERSION
		);
	}
}
