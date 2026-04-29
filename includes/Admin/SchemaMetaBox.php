<?php
/**
 * Classic meta box — Schema Suggestions (Article + FAQPage JSON-LD).
 *
 * Visible in Classic Editor, Elementor WP editor view, Divi WP editor view,
 * Beaver Builder, and Gutenberg's meta box compatibility panel.
 * The Gutenberg PluginDocumentSettingPanel remains the primary experience.
 *
 * Copy-to-clipboard only for insert action — no TinyMCE injection.
 * Rationale: TinyMCE hides <script> tags in visual mode (silent, confusing);
 * Elementor/Divi don't reliably expose TinyMCE; clipboard keeps user in control.
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Admin;

use CiteWP\Aiso\Schema\Generator;

defined( 'ABSPATH' ) || exit;

final class SchemaMetaBox {

	/**
	 * Entry point — called once from Plugin::boot() inside is_admin().
	 * Priority 20 on add_meta_boxes: fires after Yoast/Rank Math (priority 10).
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', [ $this, 'register_meta_box' ], 20 );
		add_action( 'admin_head',     [ $this, 'inline_styles' ] );
	}

	/**
	 * Callback for add_meta_boxes hook.
	 * Suppressed when Gutenberg is active — PluginDocumentSettingPanel handles it there.
	 * Honors Classic Editor plugin overrides and per-post-type filters.
	 */
	public function register_meta_box( string $post_type = '' ): void {
		if ( $post_type && use_block_editor_for_post_type( $post_type ) ) {
			return;
		}
		add_meta_box(
			'citewp_aiso_schema',
			__( 'Schema Suggestions', 'ai-search-optimizer' ),
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

		$generator    = new Generator();
		$article      = $generator->generate_article_schema( $post );
		$faqpage      = $generator->generate_faq_schema( $post );
		$detected     = $generator->detect_existing_types( $post );
		$article_json = wp_json_encode( $article, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $article_json ) {
			echo '<p class="citewp-aiso-mb-empty-note">' . esc_html__( 'Schema could not be generated (content encoding error).', 'ai-search-optimizer' ) . '</p>';
			return;
		}
		$faq_encoded  = ! empty( $faqpage )
			? wp_json_encode( $faqpage, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
			: null;
		$faq_json     = ( false !== $faq_encoded ) ? $faq_encoded : null;
		$article_known = in_array( 'Article', $detected, true );
		$faq_known     = in_array( 'FAQPage', $detected, true );
		$box_id        = 'citewp-schema-box-' . $post->ID;

		// Advisory note shown after copy (X12 — advisory language, never prescriptive).
		$copy_note = __( 'Paste into a Custom HTML block (Gutenberg) or a Code/HTML widget in your page builder. The script will not be visible in the editor — that\'s correct behavior.', 'ai-search-optimizer' );
		?>
		<div class="citewp-aiso-schema-metabox" id="<?php echo esc_attr( $box_id ); ?>">

			<div class="citewp-aiso-mb-schema-row">
				<span class="citewp-aiso-mb-schema-label"><?php esc_html_e( 'Article Schema', 'ai-search-optimizer' ); ?></span>
				<?php if ( $article_known ) : ?>
					<span class="citewp-aiso-mb-detected"><?php esc_html_e( '✓ Already in content', 'ai-search-optimizer' ); ?></span>
				<?php else : ?>
					<button type="button"
					        class="button button-secondary citewp-aiso-copy-btn"
					        data-schema="<?php echo esc_attr( '<script type="application/ld+json">' . $article_json . '</script>' ); ?>"
					        data-label="<?php esc_attr_e( 'Copy Article schema', 'ai-search-optimizer' ); ?>">
						<?php esc_html_e( 'Copy Article schema', 'ai-search-optimizer' ); ?>
					</button>
					<span class="citewp-aiso-mb-copy-note"><?php echo esc_html( $copy_note ); ?></span>
				<?php endif; ?>
			</div>

			<div class="citewp-aiso-mb-schema-row">
				<span class="citewp-aiso-mb-schema-label"><?php esc_html_e( 'FAQPage Schema', 'ai-search-optimizer' ); ?></span>
				<?php if ( $faq_known ) : ?>
					<span class="citewp-aiso-mb-detected"><?php esc_html_e( '✓ Already in content', 'ai-search-optimizer' ); ?></span>
				<?php elseif ( $faq_json ) : ?>
					<button type="button"
					        class="button button-secondary citewp-aiso-copy-btn"
					        data-schema="<?php echo esc_attr( '<script type="application/ld+json">' . $faq_json . '</script>' ); ?>"
					        data-label="<?php esc_attr_e( 'Copy FAQPage schema', 'ai-search-optimizer' ); ?>">
						<?php esc_html_e( 'Copy FAQPage schema', 'ai-search-optimizer' ); ?>
					</button>
					<span class="citewp-aiso-mb-copy-note"><?php echo esc_html( $copy_note ); ?></span>
				<?php else : ?>
					<span class="citewp-aiso-mb-empty-note">
						<?php esc_html_e( 'No FAQ content detected (need ≥ 2 Q&A pairs)', 'ai-search-optimizer' ); ?>
					</span>
				<?php endif; ?>
			</div>

			<?php
			$other = array_values( array_filter( $detected, static fn( string $t ) => ! in_array( $t, [ 'Article', 'FAQPage' ], true ) ) );
			if ( ! empty( $other ) ) :
			?>
			<p class="citewp-aiso-mb-other-types">
				<?php
				printf(
					/* translators: %s: comma-separated list of schema @type values, e.g. "Person, Organization" */
					esc_html__( '%s schema detected — more types coming soon', 'ai-search-optimizer' ),
					esc_html( implode( ', ', $other ) )
				);
				?>
			</p>
			<?php endif; ?>

		</div>
		<script>
		(function() {
			var box = document.getElementById( <?php echo wp_json_encode( $box_id ); ?> );
			if ( ! box ) { return; }

			box.querySelectorAll( '.citewp-aiso-copy-btn' ).forEach( function( btn ) {
				var fadeTimer;
				var revertTimer;
				// Note element is always the next sibling of the button in our markup.
				var note = btn.nextElementSibling;

				btn.addEventListener( 'click', function() {
					var schema    = btn.dataset.schema;
					var origLabel = btn.dataset.label;

					function onSuccess() {
						btn.textContent = <?php echo wp_json_encode( __( '✓ Copied to clipboard', 'ai-search-optimizer' ) ); ?>;

						// Clear any existing timers so repeated clicks show a fresh state.
						if ( fadeTimer )   { clearTimeout( fadeTimer ); }
						if ( revertTimer ) { clearTimeout( revertTimer ); }
						note.style.transition = '';
						note.style.opacity    = '1';
						note.style.display    = 'block';

						// Revert button label after 3 s.
						revertTimer = setTimeout( function() { btn.textContent = origLabel; }, 3000 );

						// Begin fade at 8 s, finish in 0.6 s.
						fadeTimer = setTimeout( function() {
							note.style.transition = 'opacity 0.6s ease';
							note.style.opacity    = '0';
							setTimeout( function() {
								note.style.display    = 'none';
								note.style.opacity    = '1';
								note.style.transition = '';
							}, 650 );
						}, 8000 );
					}

					function onFailure() {
						btn.textContent = <?php echo wp_json_encode( __( 'Copy failed — try again', 'ai-search-optimizer' ) ); ?>;
						if ( revertTimer ) { clearTimeout( revertTimer ); }
						revertTimer = setTimeout( function() { btn.textContent = origLabel; }, 3000 );
					}

					// Use modern Clipboard API where available (requires HTTPS or localhost).
					// Fall back to execCommand for HTTP staging/local environments.
					if ( navigator.clipboard && navigator.clipboard.writeText ) {
						navigator.clipboard.writeText( schema ).then( onSuccess ).catch( onFailure );
					} else {
						try {
							var ta = document.createElement( 'textarea' );
							ta.value          = schema;
							ta.style.position = 'fixed';
							ta.style.opacity  = '0';
							document.body.appendChild( ta );
							ta.focus();
							ta.select();
							document.execCommand( 'copy' );
							document.body.removeChild( ta );
							onSuccess();
						} catch ( e ) {
							onFailure();
						}
					}
				} );
			} );
		})();
		</script>
		<?php
	}

	/** Inline styles — scoped to the meta box, loaded on post edit screens only. */
	public function inline_styles(): void {
		$screen = get_current_screen();
		if ( ! $screen || $screen->base !== 'post' || ! in_array( $screen->post_type, [ 'post', 'page' ], true ) ) {
			return;
		}
		?>
		<style>
			#citewp_aiso_schema .citewp-aiso-mb-schema-row { margin-bottom: 10px; }
			#citewp_aiso_schema .citewp-aiso-mb-schema-label { display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px; }
			#citewp_aiso_schema .citewp-aiso-mb-detected { font-size: 12px; color: #16a34a; font-weight: 600; }
			#citewp_aiso_schema .citewp-aiso-mb-empty-note { font-size: 12px; color: #9ca3af; }
			#citewp_aiso_schema .citewp-aiso-copy-btn { font-size: 12px; }
			#citewp_aiso_schema .citewp-aiso-mb-copy-note { display: none; font-size: 11px; color: #6b7280; margin-top: 6px; line-height: 1.5; }
			#citewp_aiso_schema .citewp-aiso-mb-other-types { font-size: 11px; color: #6b7280; margin: 8px 0 0; border-top: 1px solid #e5e7eb; padding-top: 8px; }
		</style>
		<?php
	}
}
