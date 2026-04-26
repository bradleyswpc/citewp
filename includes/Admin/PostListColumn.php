<?php
/**
 * Post list "GEO Score" column.
 *
 * Adds a sortable column showing the score with a colored grade dot
 * to the All Posts and All Pages screens.
 *
 * @package CiteWP
 */

declare( strict_types=1 );

namespace CiteWP\Admin;

use CiteWP\Scoring\Repository;

defined( 'ABSPATH' ) || exit;

final class PostListColumn {

	private const COLUMN_KEY = 'citewp_geo_score';

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
		add_action( 'admin_head',                          [ $this, 'inline_styles' ] );
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
				$new[ self::COLUMN_KEY ] = __( 'GEO Score', 'citewp' );
			}
		}
		if ( ! isset( $new[ self::COLUMN_KEY ] ) ) {
			$new[ self::COLUMN_KEY ] = __( 'GEO Score', 'citewp' );
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
			echo '<span class="citewp-score citewp-score--none" title="' . esc_attr__( 'Not yet scored', 'citewp' ) . '">—</span>';
			return;
		}

		$grade = is_string( $grade ) && in_array( $grade, [ 'red', 'orange', 'yellow', 'green' ], true )
			? $grade
			: 'red';

		printf(
			'<span class="citewp-score citewp-score--%1$s"><span class="citewp-score__dot"></span>%2$s</span>',
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

	public function inline_styles(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->base, [ 'edit' ], true ) ) {
			return;
		}
		?>
		<style>
			.column-citewp_geo_score { width: 110px; }
			.citewp-score { display:inline-flex; align-items:center; gap:6px; font-weight:600; font-variant-numeric: tabular-nums; }
			.citewp-score__dot { width:10px; height:10px; border-radius:50%; display:inline-block; }
			.citewp-score--green  .citewp-score__dot { background:#16a34a; }
			.citewp-score--yellow .citewp-score__dot { background:#ca8a04; }
			.citewp-score--orange .citewp-score__dot { background:#ea580c; }
			.citewp-score--red    .citewp-score__dot { background:#dc2626; }
			.citewp-score--none   { color:#9ca3af; font-weight:400; }
		</style>
		<?php
	}
}
