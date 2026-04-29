<?php
/**
 * Classic meta box — Cite Score display and recalculate action.
 *
 * Visible in Classic Editor, Elementor WP editor view, Divi WP editor view,
 * Beaver Builder, and Gutenberg's meta box compatibility panel.
 * The Gutenberg PluginSidebar (A3) remains the primary experience.
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Admin;

use CiteWP\Aiso\Scoring\Repository;

defined( 'ABSPATH' ) || exit;

final class ScoreMetaBox {

	/**
	 * Entry point — called once from Plugin::boot() inside is_admin().
	 * Hooks add_meta_boxes at priority 20 so Yoast/Rank Math (priority 10)
	 * register first and occupy the top sidebar positions.
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', [ $this, 'register_meta_box' ], 20 );
		add_action( 'admin_head',     [ $this, 'inline_styles' ] );
	}

	/** Callback for add_meta_boxes hook. */
	public function register_meta_box(): void {
		add_meta_box(
			'citewp_aiso_cite_score',
			__( 'Cite Score', 'ai-search-optimizer' ),
			[ $this, 'render' ],
			[ 'post', 'page' ],
			'side',
			'default'
		);
	}

	/** Render callback — receives WP_Post as first arg per WP core contract. */
	public function render( \WP_Post $post ): void {
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		$repo       = new Repository();
		$data       = $repo->get( $post->ID );
		$total      = isset( $data['total'] ) ? (int) $data['total'] : null;
		$grade      = isset( $data['grade'] ) && is_string( $data['grade'] ) ? $data['grade'] : 'red';
		$categories = isset( $data['categories'] ) && is_array( $data['categories'] ) ? $data['categories'] : [];
		$scored_at  = get_post_meta( $post->ID, Repository::META_KEY_TIME, true );
		$nonce      = wp_create_nonce( 'wp_rest' );
		$recalc_url = rest_url( 'citewp/aiso/v1/score/' . $post->ID . '/recalculate' );
		$box_id     = 'citewp-score-box-' . $post->ID;
		?>
		<div class="citewp-aiso-metabox" id="<?php echo esc_attr( $box_id ); ?>">

			<div class="citewp-aiso-mb-content">
				<?php if ( $total !== null ) : ?>
					<div class="citewp-aiso-mb-score">
						<span class="citewp-aiso-mb-badge citewp-aiso-mb-badge--<?php echo esc_attr( $grade ); ?>">
							<?php echo esc_html( (string) $total ); ?>
						</span>
						<span class="citewp-aiso-mb-total-label"><?php esc_html_e( '/ 100', 'ai-search-optimizer' ); ?></span>
					</div>

					<?php if ( ! empty( $categories ) ) : ?>
					<div class="citewp-aiso-mb-categories">
						<?php foreach ( $categories as $cat ) : ?>
							<?php if ( ! is_array( $cat ) ) { continue; } ?>
							<div class="citewp-aiso-mb-cat-row">
								<span class="citewp-aiso-mb-cat-label"><?php echo esc_html( (string) ( $cat['label'] ?? '' ) ); ?></span>
								<span class="citewp-aiso-mb-cat-score">
									<?php echo esc_html( ( $cat['score'] ?? 0 ) . ' / ' . ( $cat['max'] ?? 0 ) ); ?>
								</span>
							</div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>

					<?php if ( $scored_at && is_string( $scored_at ) ) : ?>
					<p class="citewp-aiso-mb-time">
						<?php
						printf(
							/* translators: %s: human-readable time difference, e.g. "5 minutes" */
							esc_html__( 'Scored %s ago', 'ai-search-optimizer' ),
							esc_html( human_time_diff( (int) strtotime( $scored_at ), time() ) )
						);
						?>
					</p>
					<?php endif; ?>

				<?php else : ?>
					<p class="citewp-aiso-mb-empty">
						<?php esc_html_e( 'Score not yet calculated.', 'ai-search-optimizer' ); ?>
					</p>
				<?php endif; ?>
			</div>

			<p class="citewp-aiso-mb-action">
				<button type="button"
				        class="button button-secondary citewp-aiso-recalc-btn"
				        data-nonce="<?php echo esc_attr( $nonce ); ?>"
				        data-url="<?php echo esc_url( $recalc_url ); ?>">
					<?php esc_html_e( 'Recalculate', 'ai-search-optimizer' ); ?>
				</button>
			</p>
			<p class="citewp-aiso-recalc-error" style="display:none;">
				<?php esc_html_e( 'Recalculation failed — please try again.', 'ai-search-optimizer' ); ?>
			</p>

		</div>
		<script>
		(function() {
			function esc(s) {
				return String(s)
					.replace(/&/g, '&amp;')
					.replace(/</g, '&lt;')
					.replace(/>/g, '&gt;')
					.replace(/"/g, '&quot;');
			}

			var box    = document.getElementById( <?php echo wp_json_encode( $box_id ); ?> );
			if ( ! box ) { return; }
			var btn    = box.querySelector( '.citewp-aiso-recalc-btn' );
			var errEl  = box.querySelector( '.citewp-aiso-recalc-error' );
			var contEl = box.querySelector( '.citewp-aiso-mb-content' );

			btn.addEventListener( 'click', function() {
				var origText = btn.textContent;
				btn.disabled = true;
				btn.textContent = <?php echo wp_json_encode( __( 'Recalculating…', 'ai-search-optimizer' ) ); ?>;
				errEl.style.display = 'none';

				fetch( btn.dataset.url, {
					method: 'POST',
					headers: { 'X-WP-Nonce': btn.dataset.nonce, 'Content-Type': 'application/json' }
				} )
				.then( function( r ) { return r.ok ? r.json() : Promise.reject( r.status ); } )
				.then( function( data ) {
					var grade = data.grade || 'red';
					var total = data.total || 0;
					var cats  = data.categories || {};
					var html  = '<div class="citewp-aiso-mb-score">'
					          + '<span class="citewp-aiso-mb-badge citewp-aiso-mb-badge--' + esc(grade) + '">' + esc(String(total)) + '</span>'
					          + '<span class="citewp-aiso-mb-total-label"> / 100</span>'
					          + '</div>';
					if ( cats.structure ) {
						html += '<div class="citewp-aiso-mb-categories">';
						[ 'structure', 'citability', 'authority' ].forEach( function( k ) {
							if ( cats[ k ] ) {
								html += '<div class="citewp-aiso-mb-cat-row">'
								      + '<span class="citewp-aiso-mb-cat-label">' + esc(cats[ k ].label) + '</span>'
								      + '<span class="citewp-aiso-mb-cat-score">' + esc(String(cats[ k ].score)) + ' / ' + esc(String(cats[ k ].max)) + '</span>'
								      + '</div>';
							}
						} );
						html += '</div>';
					}
					html += '<p class="citewp-aiso-mb-time">' + <?php echo wp_json_encode( __( 'Scored just now', 'ai-search-optimizer' ) ); ?> + '</p>';
					contEl.innerHTML = html;
					btn.disabled    = false;
					btn.textContent = origText;
				} )
				.catch( function() {
					btn.disabled    = false;
					btn.textContent = origText;
					errEl.style.display = 'block';
				} );
			} );
		})();
		</script>
		<?php
	}

	/** Inline styles — scoped to the meta box, loaded on post edit screens only. */
	public function inline_styles(): void {
		$screen = get_current_screen();
		if ( ! $screen || $screen->base !== 'post' ) {
			return;
		}
		?>
		<style>
			#citewp_aiso_cite_score .citewp-aiso-mb-score { display: flex; align-items: baseline; gap: 6px; margin-bottom: 10px; }
			#citewp_aiso_cite_score .citewp-aiso-mb-badge { font-size: 32px; font-weight: 700; line-height: 1; }
			#citewp_aiso_cite_score .citewp-aiso-mb-badge--green  { color: #16a34a; }
			#citewp_aiso_cite_score .citewp-aiso-mb-badge--yellow { color: #ca8a04; }
			#citewp_aiso_cite_score .citewp-aiso-mb-badge--orange { color: #ea580c; }
			#citewp_aiso_cite_score .citewp-aiso-mb-badge--red    { color: #dc2626; }
			#citewp_aiso_cite_score .citewp-aiso-mb-total-label { font-size: 13px; color: #6b7280; }
			#citewp_aiso_cite_score .citewp-aiso-mb-categories { border-top: 1px solid #e5e7eb; padding-top: 8px; margin-bottom: 8px; }
			#citewp_aiso_cite_score .citewp-aiso-mb-cat-row { display: flex; justify-content: space-between; padding: 3px 0; font-size: 12px; }
			#citewp_aiso_cite_score .citewp-aiso-mb-cat-label { color: #374151; }
			#citewp_aiso_cite_score .citewp-aiso-mb-cat-score { color: #111827; font-weight: 600; font-variant-numeric: tabular-nums; }
			#citewp_aiso_cite_score .citewp-aiso-mb-time { font-size: 11px; color: #9ca3af; margin: 0 0 8px; }
			#citewp_aiso_cite_score .citewp-aiso-mb-empty { font-size: 12px; color: #6b7280; margin: 0 0 8px; }
			#citewp_aiso_cite_score .citewp-aiso-mb-action { margin: 8px 0 0; }
			#citewp_aiso_cite_score .citewp-aiso-recalc-error { font-size: 12px; color: #dc2626; margin: 4px 0 0; }
		</style>
		<?php
	}
}
