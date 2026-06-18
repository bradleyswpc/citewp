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

use CiteWP\Aiso\Llms\Cache;
use CiteWP\Aiso\Scoring\Repository;

defined( 'ABSPATH' ) || exit;

final class PostListColumn {

	private const COLUMN_KEY  = 'citewp_aiso_geo_score';
	private const COLUMN_LLMS = 'citewp_aiso_llms';
	private const AJAX_ACTION  = 'citewp_aiso_toggle_llms';
	private const EXCLUDE_META = '_citewp_aiso_exclude_from_llms';

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
		add_action( 'admin_print_footer_scripts',          [ $this, 'inline_script' ] );

		// AJAX endpoint for flipping the per-post llms.txt inclusion toggle.
		add_action( 'wp_ajax_' . self::AJAX_ACTION,        [ $this, 'ajax_toggle' ] );
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
				$new[ self::COLUMN_KEY ]  = __( 'Cite Score', 'citewp-ai-search-optimizer' );
				$new[ self::COLUMN_LLMS ] = __( 'llms.txt', 'citewp-ai-search-optimizer' );
			}
		}
		if ( ! isset( $new[ self::COLUMN_KEY ] ) ) {
			$new[ self::COLUMN_KEY ]  = __( 'Cite Score', 'citewp-ai-search-optimizer' );
			$new[ self::COLUMN_LLMS ] = __( 'llms.txt', 'citewp-ai-search-optimizer' );
		}
		return $new;
	}

	public function render_column( string $column, int $post_id ): void {
		if ( $column === self::COLUMN_KEY ) {
			$this->render_score( $post_id );
			return;
		}
		if ( $column === self::COLUMN_LLMS ) {
			$this->render_llms_toggle( $post_id );
		}
	}

	private function render_score( int $post_id ): void {
		$total = get_post_meta( $post_id, Repository::META_KEY_TOTAL, true );
		$grade = get_post_meta( $post_id, Repository::META_KEY_GRADE, true );

		if ( $total === '' || $total === false ) {
			echo '<span class="citewp-aiso-score citewp-aiso-score--none" title="' . esc_attr__( 'Not yet scored', 'citewp-ai-search-optimizer' ) . '">—</span>';
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
	 * Renders an accessible on/off switch. "On" (default) = included in llms.txt;
	 * stored as the ABSENCE of the exclude meta, so new content is included by default.
	 */
	private function render_llms_toggle( int $post_id ): void {
		$included = get_post_meta( $post_id, self::EXCLUDE_META, true ) !== '1';
		$can_edit = current_user_can( 'edit_post', $post_id );

		if ( ! $can_edit ) {
			echo $included // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static literals only.
				? '<span class="citewp-aiso-llms-state citewp-aiso-llms-state--on">' . esc_html__( 'Included', 'citewp-ai-search-optimizer' ) . '</span>'
				: '<span class="citewp-aiso-llms-state citewp-aiso-llms-state--off">' . esc_html__( 'Excluded', 'citewp-ai-search-optimizer' ) . '</span>';
			return;
		}

		printf(
			'<button type="button" role="switch" class="citewp-aiso-llms-toggle" aria-checked="%1$s" data-postid="%2$d" data-nonce="%3$s" title="%4$s"><span class="citewp-aiso-llms-toggle__track"><span class="citewp-aiso-llms-toggle__thumb"></span></span><span class="citewp-aiso-llms-toggle__label">%5$s</span></button>',
			$included ? 'true' : 'false',
			(int) $post_id,
			esc_attr( wp_create_nonce( self::AJAX_ACTION . '_' . $post_id ) ),
			esc_attr__( 'Include this content in llms.txt', 'citewp-ai-search-optimizer' ),
			$included ? esc_html__( 'On', 'citewp-ai-search-optimizer' ) : esc_html__( 'Off', 'citewp-ai-search-optimizer' )
		);
	}

	/**
	 * AJAX: flip the per-post llms.txt inclusion. Included is stored as the
	 * absence of the exclude meta; excluded sets it to '1'. The llms.txt cache
	 * is flushed automatically by Llms\Cache on the meta change.
	 */
	public function ajax_toggle(): void {
		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;

		check_ajax_referer( self::AJAX_ACTION . '_' . $post_id );

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'citewp-ai-search-optimizer' ) ], 403 );
		}

		$include = isset( $_POST['include'] ) ? (string) sanitize_text_field( wp_unslash( $_POST['include'] ) ) : '';
		$include = ( $include === '1' || $include === 'true' );

		if ( $include ) {
			// Default state — remove the exclusion flag entirely.
			delete_post_meta( $post_id, self::EXCLUDE_META );
		} else {
			update_post_meta( $post_id, self::EXCLUDE_META, '1' );
		}

		// delete_post_meta fires deleted_post_meta (not the add/update hooks Cache
		// listens to), so flush here to cover both toggle directions.
		( new Cache() )->flush();

		wp_send_json_success( [ 'included' => $include ] );
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
			.column-citewp_aiso_geo_score { width: 110px; }
			.column-citewp_aiso_llms { width: 96px; }
			.citewp-aiso-score { display:inline-flex; align-items:center; gap:6px; font-weight:600; font-variant-numeric: tabular-nums; }
			.citewp-aiso-score__dot { width:10px; height:10px; border-radius:50%; display:inline-block; }
			.citewp-aiso-score--green  .citewp-aiso-score__dot { background:#00A32A; }
			.citewp-aiso-score--yellow .citewp-aiso-score__dot { background:#DBA617; }
			.citewp-aiso-score--orange .citewp-aiso-score__dot { background:#D63638; }
			.citewp-aiso-score--red    .citewp-aiso-score__dot { background:#8C1B1B; }
			.citewp-aiso-score--none   { color:#9ca3af; font-weight:400; }

			.citewp-aiso-llms-toggle { display:inline-flex; align-items:center; gap:8px; background:none; border:0; padding:2px; cursor:pointer; color:inherit; }
			.citewp-aiso-llms-toggle:focus-visible { outline:2px solid var(--wp-admin-theme-color, #2271b1); outline-offset:2px; border-radius:4px; }
			.citewp-aiso-llms-toggle[disabled] { cursor:default; opacity:.6; }
			.citewp-aiso-llms-toggle__track { position:relative; width:32px; height:18px; border-radius:9px; background:#c3c4c7; transition:background .15s ease; flex:none; }
			.citewp-aiso-llms-toggle__thumb { position:absolute; top:2px; left:2px; width:14px; height:14px; border-radius:50%; background:#fff; transition:transform .15s ease; }
			.citewp-aiso-llms-toggle[aria-checked="true"] .citewp-aiso-llms-toggle__track { background:var(--wp-admin-theme-color, #2271b1); }
			.citewp-aiso-llms-toggle[aria-checked="true"] .citewp-aiso-llms-toggle__thumb { transform:translateX(14px); }
			.citewp-aiso-llms-toggle__label { font-size:12px; font-weight:600; min-width:20px; text-align:left; }
			.citewp-aiso-llms-toggle.is-busy { opacity:.5; pointer-events:none; }
			.citewp-aiso-llms-state { font-size:12px; }
			.citewp-aiso-llms-state--off { color:#9ca3af; }
		</style>
		<?php
	}

	public function inline_script(): void {
		$screen = get_current_screen();
		if ( ! $screen || $screen->base !== 'edit' || ! in_array( $screen->post_type, [ 'post', 'page' ], true ) ) {
			return;
		}

		$ajax_url = esc_js( admin_url( 'admin-ajax.php' ) );
		$action   = esc_js( self::AJAX_ACTION );
		$on_text  = esc_js( __( 'On', 'citewp-ai-search-optimizer' ) );
		$off_text = esc_js( __( 'Off', 'citewp-ai-search-optimizer' ) );
		?>
		<script>
		( function () {
			var AJAX = '<?php echo $ajax_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_js applied above. ?>';
			var ACTION = '<?php echo $action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_js applied above. ?>';
			var ON = '<?php echo $on_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_js applied above. ?>';
			var OFF = '<?php echo $off_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_js applied above. ?>';

			document.addEventListener( 'click', function ( e ) {
				var btn = e.target.closest( '.citewp-aiso-llms-toggle' );
				if ( ! btn || btn.classList.contains( 'is-busy' ) ) { return; }

				var nextIncluded = btn.getAttribute( 'aria-checked' ) !== 'true';
				btn.classList.add( 'is-busy' );

				var body = new URLSearchParams();
				body.set( 'action', ACTION );
				body.set( 'post_id', btn.dataset.postid );
				body.set( '_wpnonce', btn.dataset.nonce );
				body.set( 'include', nextIncluded ? '1' : '0' );

				fetch( AJAX, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: body.toString()
				} )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					if ( res && res.success ) {
						var inc = !! res.data.included;
						btn.setAttribute( 'aria-checked', inc ? 'true' : 'false' );
						var label = btn.querySelector( '.citewp-aiso-llms-toggle__label' );
						if ( label ) { label.textContent = inc ? ON : OFF; }
					} else {
						window.alert( ( res && res.data && res.data.message ) || 'Could not update.' );
					}
				} )
				.catch( function () { window.alert( 'Network error.' ); } )
				.finally( function () { btn.classList.remove( 'is-busy' ); } );
			} );
		}() );
		</script>
		<?php
	}
}
