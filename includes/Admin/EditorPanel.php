<?php
declare( strict_types=1 );

namespace CiteWP\Aiso\Admin;

use CiteWP\Aiso\Scoring\Repository;
use CiteWP\Aiso\Schema\Generator;
use CiteWP\Aiso\Database\Schema;

defined( 'ABSPATH' ) || exit;

final class EditorPanel {
	public function register(): void {
		add_action( 'add_meta_boxes',        [ $this, 'register_meta_box' ], 20 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );
	}

	public function enqueue_styles( string $hook ): void {
		if ( $hook !== 'post.php' && $hook !== 'post-new.php' ) {
			return;
		}
		wp_enqueue_style(
			'citewp-aiso-editor-panel',
			CITEWP_AISO_PLUGIN_URL . 'admin/css/citewp-aiso-admin.css',
			[],
			CITEWP_AISO_VERSION
		);
	}

	public function register_meta_box( string $post_type = '' ): void {
		// Suppress in Gutenberg — PluginSidebar handles it there
		if ( $post_type && use_block_editor_for_post_type( $post_type ) ) {
			return;
		}
		add_meta_box(
			'citewp_aiso_editor_panel',
			__( 'Cite Score', 'ai-search-optimizer' ),
			[ $this, 'render' ],
			[ 'post', 'page' ],
			'normal',   // ← P31: was 'side', now 'normal'
			'high'      // ← P31: was 'default', now 'high'
		);
	}

	public function render( \WP_Post $post ): void {
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		/**
		 * Filters EditorPanel tabs for the score (General) context.
		 * Add tabs here to appear alongside or after the General tab.
		 *
		 * @param array<int, array{slug: string, label: string, render: callable}> $tabs
		 * @param \WP_Post $post
		 * @param string   $context Always 'score' for this call.
		 */
		$score_tabs = apply_filters(
			'citewp_aiso/metabox/tabs',
			[
				[
					'slug'   => 'general',
					'label'  => __( 'General', 'ai-search-optimizer' ),
					'render' => [ $this, 'render_general_tab' ],
				],
			],
			$post,
			'score'
		);

		/**
		 * Filters EditorPanel tabs for the schema context.
		 * Add tabs here to appear alongside or after the Schema tab.
		 *
		 * @param array<int, array{slug: string, label: string, render: callable}> $tabs
		 * @param \WP_Post $post
		 * @param string   $context Always 'schema' for this call.
		 */
		$schema_tabs = apply_filters(
			'citewp_aiso/metabox/tabs',
			[
				[
					'slug'   => 'schema',
					'label'  => __( 'Schema', 'ai-search-optimizer' ),
					'render' => [ $this, 'render_schema_tab' ],
				],
			],
			$post,
			'schema'
		);

		$tabs = array_merge( $score_tabs, $schema_tabs );

		$box_id = 'citewp-editor-panel-' . $post->ID;
		?>
		<div class="citewp-aiso-ep" id="<?php echo esc_attr( $box_id ); ?>">
			<div class="citewp-aiso-ep__tabs">
				<?php foreach ( $tabs as $i => $tab ) : ?>
					<button type="button"
					        class="citewp-aiso-ep__tab<?php echo $i === 0 ? ' is-active' : ''; ?>"
					        data-tab="<?php echo esc_attr( $tab['slug'] ); ?>">
						<?php echo esc_html( $tab['label'] ); ?>
					</button>
				<?php endforeach; ?>
			</div>
			<div class="citewp-aiso-ep__panels">
				<?php foreach ( $tabs as $i => $tab ) : ?>
					<div class="citewp-aiso-ep__panel<?php echo $i === 0 ? ' is-active' : ''; ?>"
					     data-panel="<?php echo esc_attr( $tab['slug'] ); ?>">
						<?php
						if ( is_callable( $tab['render'] ) ) {
							call_user_func( $tab['render'], $post );
						}
						?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<script>
		(function() {
			var ep = document.getElementById( <?php echo wp_json_encode( $box_id ); ?> );
			if ( ! ep ) { return; }
			ep.querySelectorAll( '.citewp-aiso-ep__tab' ).forEach( function( tab ) {
				tab.addEventListener( 'click', function() {
					var target = tab.dataset.tab;
					ep.querySelectorAll( '.citewp-aiso-ep__tab' ).forEach( function( t ) { t.classList.remove( 'is-active' ); } );
					ep.querySelectorAll( '.citewp-aiso-ep__panel' ).forEach( function( p ) { p.classList.remove( 'is-active' ); } );
					tab.classList.add( 'is-active' );
					var panel = ep.querySelector( '.citewp-aiso-ep__panel[data-panel="' + target + '"]' );
					if ( panel ) { panel.classList.add( 'is-active' ); }
				} );
			} );
		})();
		</script>
		<?php
	}

	public function render_general_tab( \WP_Post $post ): void {
		// Migrated from ScoreMetaBox::render() — score display + recalc button
		$repo       = new Repository();
		$data       = $repo->get( $post->ID );
		$total      = isset( $data['total'] ) ? (int) $data['total'] : null;
		$grade      = isset( $data['grade'] ) && is_string( $data['grade'] ) ? $data['grade'] : 'red';
		$categories = isset( $data['categories'] ) && is_array( $data['categories'] ) ? $data['categories'] : [];
		$scored_at  = get_post_meta( $post->ID, Repository::META_KEY_TIME, true );
		$nonce      = wp_create_nonce( 'wp_rest' );
		$recalc_url = rest_url( 'citewp/aiso/v1/score/' . $post->ID . '/recalculate' );
		$content_id = 'citewp-ep-general-' . $post->ID;
		?>
		<div class="citewp-aiso-ep-general" id="<?php echo esc_attr( $content_id ); ?>">
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
					<?php
					$ts = $scored_at && is_string( $scored_at ) ? strtotime( $scored_at ) : false;
					if ( false !== $ts ) :
					?>
						<p class="citewp-aiso-mb-time">
							<?php
							printf(
								/* translators: %s: human-readable time difference */
								esc_html__( 'Scored %s ago', 'ai-search-optimizer' ),
								esc_html( human_time_diff( $ts, time() ) )
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
					.replace(/&/g, '&amp;').replace(/</g, '&lt;')
					.replace(/>/g, '&gt;').replace(/"/g, '&quot;');
			}
			var wrap   = document.getElementById( <?php echo wp_json_encode( $content_id ); ?> );
			if ( ! wrap ) { return; }
			var btn    = wrap.querySelector( '.citewp-aiso-recalc-btn' );
			var errEl  = wrap.querySelector( '.citewp-aiso-recalc-error' );
			var contEl = wrap.querySelector( '.citewp-aiso-mb-content' );
			if ( ! btn || ! errEl ) { return; }

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
								      + '<span class="citewp-aiso-mb-cat-label">' + esc(cats[ k ].label ?? '') + '</span>'
								      + '<span class="citewp-aiso-mb-cat-score">' + esc(String(cats[ k ].score ?? 0)) + ' / ' + esc(String(cats[ k ].max ?? 0)) + '</span>'
								      + '</div>';
							}
						} );
						html += '</div>';
					}
					html += '<p class="citewp-aiso-mb-time">' + <?php echo wp_json_encode( __( 'Scored just now', 'ai-search-optimizer' ) ); ?> + '</p>';
					if ( contEl ) { contEl.innerHTML = html; }
					btn.disabled    = false;
					btn.textContent = origText;
				} )
				.catch( function( status ) {
					btn.disabled    = false;
					btn.textContent = origText;
					if ( status === 403 ) {
						errEl.textContent = <?php echo wp_json_encode( __( 'Session expired — please reload the page.', 'ai-search-optimizer' ) ); ?>;
					}
					errEl.style.display = 'block';
				} );
			} );
		})();
		</script>
		<?php
	}

	public function render_schema_tab( \WP_Post $post ): void {
		// Migrated from SchemaMetaBox::render()
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
		$box_id        = 'citewp-ep-schema-' . $post->ID;
		$copy_note     = __( 'Paste into a Custom HTML block (Gutenberg) or a Code/HTML widget in your page builder.', 'ai-search-optimizer' );
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
					/* translators: %s: comma-separated list of schema @type values */
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
				var fadeTimer, revertTimer;
				var note = btn.nextElementSibling;
				btn.addEventListener( 'click', function() {
					var schema    = btn.dataset.schema;
					var origLabel = btn.dataset.label;
					function onSuccess() {
						btn.textContent = <?php echo wp_json_encode( __( '✓ Copied to clipboard', 'ai-search-optimizer' ) ); ?>;
						if ( fadeTimer )   { clearTimeout( fadeTimer ); }
						if ( revertTimer ) { clearTimeout( revertTimer ); }
						note.style.transition = '';
						note.style.opacity    = '1';
						note.style.display    = 'block';
						revertTimer = setTimeout( function() { btn.textContent = origLabel; }, 3000 );
						fadeTimer = setTimeout( function() {
							note.style.transition = 'opacity 0.6s ease';
							note.style.opacity    = '0';
							setTimeout( function() { note.style.display = 'none'; note.style.opacity = '1'; note.style.transition = ''; }, 650 );
						}, 8000 );
					}
					function onFailure() {
						btn.textContent = <?php echo wp_json_encode( __( 'Copy failed — try again', 'ai-search-optimizer' ) ); ?>;
						if ( revertTimer ) { clearTimeout( revertTimer ); }
						revertTimer = setTimeout( function() { btn.textContent = origLabel; }, 3000 );
					}
					if ( navigator.clipboard && navigator.clipboard.writeText ) {
						navigator.clipboard.writeText( schema ).then( onSuccess ).catch( onFailure );
					} else {
						try {
							var ta = document.createElement( 'textarea' );
							ta.value = schema; ta.style.position = 'fixed'; ta.style.opacity = '0';
							document.body.appendChild( ta ); ta.focus(); ta.select();
							document.execCommand( 'copy' ); document.body.removeChild( ta ); onSuccess();
						} catch ( e ) { onFailure(); }
					}
				} );
			} );
		})();
		</script>
		<?php
	}

	/**
	 * @return array{rows: list<object>, n_more: int}
	 */
	private function query_bot_visits( int $post_id ): array {
		global $wpdb;

		$table = Schema::table( 'citewp_aiso_crawler_logs' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT bot_signature, COUNT(*) AS visits, MAX(created_at) AS last_seen
				 FROM {$table}
				 WHERE post_id = %d
				   AND created_at > NOW() - INTERVAL 7 DAY
				 GROUP BY bot_signature
				 ORDER BY visits DESC
				 LIMIT 6",
				$post_id
			)
		);

		if ( null === $rows ) {
			return [ 'rows' => [], 'n_more' => 0 ];
		}

		$n_more = 0;
		if ( count( $rows ) === 6 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total  = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT bot_signature)
					 FROM {$table}
					 WHERE post_id = %d
					   AND created_at > NOW() - INTERVAL 7 DAY",
					$post_id
				)
			);
			$n_more = max( 0, $total - 5 );
			$rows   = array_slice( $rows, 0, 5 );
		}

		return [ 'rows' => $rows, 'n_more' => $n_more ];
	}

	private function bot_dot_color( string $sig ): string {
		$palette = [ '#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6' ];
		return $palette[ abs( crc32( $sig ) ) % count( $palette ) ];
	}

}
