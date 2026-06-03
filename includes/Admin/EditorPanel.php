<?php
declare( strict_types=1 );

namespace CiteWP\Aiso\Admin;

use CiteWP\Aiso\Scoring\Repository;
use CiteWP\Aiso\Schema\Generator;
use CiteWP\Aiso\Schema\Detector;
use CiteWP\Aiso\Schema\HeadInjector;
use CiteWP\Aiso\Database\Schema;

defined( 'ABSPATH' ) || exit;

final class EditorPanel {
	public function register(): void {
		add_action( 'add_meta_boxes',        [ $this, 'register_meta_box' ], 20 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );
		add_action( 'save_post',             [ $this, 'save_meta' ], 20, 2 );
	}

	public function enqueue_styles( string $hook ): void {
		if ( $hook !== 'post.php' && $hook !== 'post-new.php' ) {
			return;
		}
		$css_path    = CITEWP_AISO_PLUGIN_DIR . 'admin/css/citewp-aiso-admin.css';
		$css_version = file_exists( $css_path ) ? filemtime( $css_path ) : CITEWP_AISO_VERSION;

		wp_enqueue_style(
			'citewp-aiso-editor-panel',
			CITEWP_AISO_PLUGIN_URL . 'admin/css/citewp-aiso-admin.css',
			[],
			$css_version
		);
	}

	/**
	 * Persist the llms.txt toggle on classic-editor form save.
	 *
	 * Gutenberg saves meta via REST: $_POST is empty on those requests,
	 * so the nonce check returns early — this handler never overwrites
	 * a Gutenberg-committed meta value.
	 *
	 * @param int      $post_id Post being saved.
	 * @param \WP_Post $post    Post object.
	 */
	public function save_meta( int $post_id, \WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		// Not a classic-editor form submission.
		if ( ! isset( $_POST['_citewp_aiso_ep_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		if ( ! wp_verify_nonce(
			sanitize_key( wp_unslash( $_POST['_citewp_aiso_ep_nonce'] ) ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'citewp_aiso_ep_' . $post_id
		) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		// Checkbox present = user wants to include the post; absent = exclude.
		$include = isset( $_POST['citewp_aiso_llms_include'] );
		update_post_meta( $post_id, '_citewp_aiso_exclude_from_llms', $include ? '0' : '1' );
	}

	public function register_meta_box( string $post_type = '' ): void {
		// Suppress only when the current screen is actually rendering the block editor.
		// use_block_editor_for_post_type() reports post-type capability, not the active
		// editor, so it returns true on Classic Editor (plugin) screens and wrongly
		// suppresses the box. P22/P24: box must appear on every non-Gutenberg surface.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
			return;
		}
		add_meta_box(
			'citewp_aiso_editor_panel',
			__( 'Cite Score', 'citewp-ai-search-optimizer' ),
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
					'label'  => __( 'General', 'citewp-ai-search-optimizer' ),
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
					'label'  => __( 'Schema', 'citewp-ai-search-optimizer' ),
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
		$repo       = new Repository();
		$data       = $repo->get( $post->ID );
		$total      = isset( $data['total'] ) ? (int) $data['total'] : null;
		$grade      = isset( $data['grade'] ) && is_string( $data['grade'] ) ? $data['grade'] : 'red';
		$categories     = isset( $data['categories'] ) && is_array( $data['categories'] ) ? $data['categories'] : [];
		$scored_at      = get_post_meta( $post->ID, Repository::META_KEY_TIME, true );
		$signals_by_cat = [];
		if ( isset( $data['signals'] ) && is_array( $data['signals'] ) ) {
			foreach ( $data['signals'] as $sig ) {
				if ( is_array( $sig ) && isset( $sig['category'] ) && $sig['category'] !== '' ) {
					$signals_by_cat[ $sig['category'] ][] = $sig;
				}
			}
		}
		$nonce      = wp_create_nonce( 'wp_rest' );
		$recalc_url = rest_url( 'citewp/aiso/v1/score/' . $post->ID . '/recalculate' );
		$content_id = 'citewp-ep-general-' . $post->ID;
		?>
		<div class="citewp-aiso-ep-general" id="<?php echo esc_attr( $content_id ); ?>">
			<div class="citewp-aiso-ep-columns">

				<div class="citewp-aiso-ep-col-left">
					<div class="citewp-aiso-mb-content">
						<?php if ( $total !== null ) : ?>
							<div class="citewp-aiso-mb-score">
								<span class="citewp-aiso-mb-badge citewp-aiso-mb-badge--<?php echo esc_attr( $grade ); ?>">
									<?php echo esc_html( (string) $total ); ?>
								</span>
								<span class="citewp-aiso-mb-total-label"><?php esc_html_e( '/ 100', 'citewp-ai-search-optimizer' ); ?></span>
							</div>
							<?php if ( ! empty( $categories ) ) : ?>
								<div class="citewp-aiso-mb-categories">
									<?php foreach ( $categories as $cat_key => $cat ) : ?>
										<?php
										if ( ! is_array( $cat ) ) {
											continue;
										}
										$score     = (int) ( $cat['score'] ?? 0 );
										$max       = (int) ( $cat['max'] ?? 0 );
										$pct       = $max > 0 ? (int) round( ( $score / $max ) * 100 ) : 0;
										$grade_cat = $pct >= 80 ? 'green' : ( $pct >= 60 ? 'yellow' : ( $pct >= 40 ? 'orange' : 'red' ) );
										$cat_sigs  = $signals_by_cat[ $cat_key ] ?? [];
										?>
										<div class="citewp-aiso-mb-cat-row citewp-aiso-mb-cat-row--toggle"
										     data-cat="<?php echo esc_attr( $cat_key ); ?>">
											<span class="citewp-aiso-mb-cat-chevron">&#9654;</span>
											<span class="citewp-aiso-mb-cat-label">
												<?php echo esc_html( (string) ( $cat['label'] ?? '' ) ); ?>
											</span>
											<div class="citewp-aiso-mb-cat-bar-wrap">
												<div class="citewp-aiso-mb-cat-bar-fill citewp-aiso-mb-cat-bar-fill--<?php echo esc_attr( $grade_cat ); ?>"
													 style="width:<?php echo esc_attr( (string) $pct ); ?>%"></div>
											</div>
											<span class="citewp-aiso-mb-cat-score">
												<?php echo esc_html( $score . '/' . $max ); ?>
											</span>
										</div>
										<?php if ( ! empty( $cat_sigs ) ) : ?>
										<div class="citewp-aiso-mb-cat-signals"
										     data-cat-signals="<?php echo esc_attr( $cat_key ); ?>"
										     style="display:none;">
											<?php foreach ( $cat_sigs as $sig ) : ?>
												<?php if ( ! is_array( $sig ) ) { continue; } ?>
												<div class="citewp-aiso-mb-signal-row">
													<span class="citewp-aiso-mb-signal-dot citewp-aiso-mb-signal-dot--<?php echo esc_attr( $sig['status'] ?? 'fail' ); ?>"></span>
													<span class="citewp-aiso-mb-signal-label"><?php echo esc_html( (string) ( $sig['label'] ?? '' ) ); ?></span>
													<span class="citewp-aiso-mb-signal-score"><?php echo esc_html( (int) ( $sig['score'] ?? 0 ) . '/' . (int) ( $sig['max'] ?? 0 ) ); ?></span>
												</div>
												<?php if ( ! empty( $sig['recommendation'] ) && (int) ( $sig['score'] ?? 0 ) < (int) ( $sig['max'] ?? 0 ) ) : ?>
												<p class="citewp-aiso-mb-signal-rec"><?php echo esc_html( (string) $sig['recommendation'] ); ?></p>
												<?php endif; ?>
											<?php endforeach; ?>
										</div>
										<?php endif; ?>
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
										esc_html__( 'Scored %s ago', 'citewp-ai-search-optimizer' ),
										esc_html( human_time_diff( $ts, time() ) )
									);
									?>
								</p>
							<?php endif; ?>
						<?php else : ?>
							<p class="citewp-aiso-mb-empty">
								<?php esc_html_e( 'Score not yet calculated.', 'citewp-ai-search-optimizer' ); ?>
							</p>
						<?php endif; ?>
					</div>
					<p class="citewp-aiso-mb-action">
						<button type="button"
								class="button button-secondary citewp-aiso-recalc-btn"
								data-nonce="<?php echo esc_attr( $nonce ); ?>"
								data-url="<?php echo esc_url( $recalc_url ); ?>">
							<?php esc_html_e( 'Recalculate', 'citewp-ai-search-optimizer' ); ?>
						</button>
					</p>
					<p class="citewp-aiso-recalc-error" style="display:none;">
						<?php esc_html_e( 'Recalculation failed — please try again.', 'citewp-ai-search-optimizer' ); ?>
					</p>
				</div>

				<div class="citewp-aiso-ep-col-right">
					<?php $this->render_bot_visits( $post ); ?>
				</div>

			</div>

			<?php $this->render_publishing_controls( $post ); ?>
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

			function attachToggles( container ) {
				container.querySelectorAll( '.citewp-aiso-mb-cat-row--toggle' ).forEach( function( row ) {
					row.addEventListener( 'click', function() {
						var cat    = row.dataset.cat;
						var drawer = container.querySelector( '[data-cat-signals="' + cat + '"]' );
						if ( ! drawer ) { return; }
						var opening = ! row.classList.contains( 'is-open' );
						// Close all drawers first (accordion: only one open at a time).
						container.querySelectorAll( '.citewp-aiso-mb-cat-row--toggle' ).forEach( function( r ) {
							r.classList.remove( 'is-open' );
							var d = container.querySelector( '[data-cat-signals="' + r.dataset.cat + '"]' );
							if ( d ) { d.style.display = 'none'; }
						} );
						if ( opening ) {
							row.classList.add( 'is-open' );
							drawer.style.display = 'block';
						}
					} );
				} );
			}

			function buildSignalRows( signals, catKey ) {
				var catSigs = signals.filter( function( s ) { return s.category === catKey; } );
				if ( ! catSigs.length ) { return ''; }
				var h = '<div class="citewp-aiso-mb-cat-signals" data-cat-signals="' + esc( catKey ) + '" style="display:none;">';
				catSigs.forEach( function( sig ) {
					h += '<div class="citewp-aiso-mb-signal-row">'
					   + '<span class="citewp-aiso-mb-signal-dot citewp-aiso-mb-signal-dot--' + esc( sig.status || 'fail' ) + '"></span>'
					   + '<span class="citewp-aiso-mb-signal-label">' + esc( sig.label || '' ) + '</span>'
					   + '<span class="citewp-aiso-mb-signal-score">' + esc( String( sig.score || 0 ) ) + '/' + esc( String( sig.max || 0 ) ) + '</span>'
					   + '</div>';
					if ( sig.recommendation && ( sig.score || 0 ) < ( sig.max || 0 ) ) {
						h += '<p class="citewp-aiso-mb-signal-rec">' + esc( sig.recommendation ) + '</p>';
					}
				} );
				h += '</div>';
				return h;
			}

			if ( contEl ) { attachToggles( contEl ); }

			btn.addEventListener( 'click', function() {
				var origText = btn.textContent;
				btn.disabled    = true;
				btn.textContent = <?php echo wp_json_encode( __( 'Recalculating…', 'citewp-ai-search-optimizer' ) ); ?>;
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
					var sigs  = data.signals    || [];
					var html  = '<div class="citewp-aiso-mb-score">'
					          + '<span class="citewp-aiso-mb-badge citewp-aiso-mb-badge--' + esc(grade) + '">' + esc(String(total)) + '</span>'
					          + '<span class="citewp-aiso-mb-total-label"> / 100</span>'
					          + '</div>';
					if ( cats.structure ) {
						html += '<div class="citewp-aiso-mb-categories">';
						[ 'structure', 'citability', 'authority' ].forEach( function( k ) {
							if ( cats[ k ] ) {
								var score = cats[k].score ?? 0;
								var max   = cats[k].max   ?? 0;
								var pct   = max ? Math.round( ( score / max ) * 100 ) : 0;
								var gc    = pct >= 80 ? 'green' : pct >= 60 ? 'yellow' : pct >= 40 ? 'orange' : 'red';
								html += '<div class="citewp-aiso-mb-cat-row citewp-aiso-mb-cat-row--toggle" data-cat="' + esc(k) + '">'
								      + '<span class="citewp-aiso-mb-cat-chevron">&#9654;</span>'
								      + '<span class="citewp-aiso-mb-cat-label">' + esc( cats[k].label ?? '' ) + '</span>'
								      + '<div class="citewp-aiso-mb-cat-bar-wrap"><div class="citewp-aiso-mb-cat-bar-fill citewp-aiso-mb-cat-bar-fill--' + esc(gc) + '" style="width:' + pct + '%"></div></div>'
								      + '<span class="citewp-aiso-mb-cat-score">' + esc( String(score) ) + '/' + esc( String(max) ) + '</span>'
								      + '</div>'
								      + buildSignalRows( sigs, k );
							}
						} );
						html += '</div>';
					}
					html += '<p class="citewp-aiso-mb-time">' + <?php echo wp_json_encode( __( 'Scored just now', 'citewp-ai-search-optimizer' ) ); ?> + '</p>';
					if ( contEl ) {
						contEl.innerHTML = html;
						attachToggles( contEl );
					}
					btn.disabled    = false;
					btn.textContent = origText;
				} )
				.catch( function( status ) {
					btn.disabled    = false;
					btn.textContent = origText;
					if ( status === 403 ) {
						errEl.textContent = <?php echo wp_json_encode( __( 'Session expired — please reload the page.', 'citewp-ai-search-optimizer' ) ); ?>;
					}
					errEl.style.display = 'block';
				} );
			} );
		})();
		</script>
		<?php
	}

	public function render_schema_tab( \WP_Post $post ): void {
		$generator = new Generator();
		$detector  = new Detector();
		$injector  = new HeadInjector();

		// What CiteWP has already head-injected for this post.
		$stored        = $injector->get_stored( $post->ID );

		// Rendered-page detection: article_valid / faq_valid drive Insert/Already-detected state.
		// tier1/tier2 = full rendered page; tier3/cold_start = post_content only (no HTTP self-request).
		$schema_result = $detector->get_detected_types( $post->ID );

		// Full "Detected on this page" list — merge rendered + post_content. Left exactly as-is.
		$from_content = $generator->detect_existing_types( $post );
		$all_detected = array_values( array_unique( array_merge( $schema_result['types'], $from_content ) ) );

		// FAQ pair count — needed for the unavailable-state message.
		$faq_count = $generator->count_faq_pairs( $post );

		// Article: injected by CiteWP → Remove; valid by another emitter → Already detected; else → Insert.
		if ( isset( $stored['article'] ) ) {
			$article_state = 'injected';
		} elseif ( ! empty( $schema_result['article_valid'] ) ) {
			$article_state = 'detected';
		} else {
			$article_state = 'available';
		}

		// FAQ: injected → detected → available (≥ 2 pairs) → unavailable (0 or 1 pair).
		if ( isset( $stored['faqpage'] ) ) {
			$faq_state = 'injected';
		} elseif ( ! empty( $schema_result['faq_valid'] ) ) {
			$faq_state = 'detected';
		} elseif ( $faq_count >= 2 ) {
			$faq_state = 'available';
		} else {
			$faq_state = 'unavailable';
		}

		$box_id     = 'citewp-ep-schema-' . $post->ID;
		$nonce      = wp_create_nonce( 'wp_rest' );
		$inject_url = rest_url( 'citewp/aiso/v1/schema/' . $post->ID . '/inject' );
		?>
		<div class="citewp-aiso-schema-metabox" id="<?php echo esc_attr( $box_id ); ?>">

			<div class="citewp-aiso-mb-schema-row" data-schema-type="article">
				<span class="citewp-aiso-mb-schema-label"><?php esc_html_e( 'Article Schema', 'citewp-ai-search-optimizer' ); ?></span>
				<?php if ( 'injected' === $article_state ) : ?>
					<button type="button"
					        class="button button-secondary citewp-aiso-schema-btn"
					        data-type="article"
					        data-action="remove">
						<?php esc_html_e( 'Remove', 'citewp-ai-search-optimizer' ); ?>
					</button>
				<?php elseif ( 'detected' === $article_state ) : ?>
					<span class="citewp-aiso-mb-detected"><?php esc_html_e( 'Already detected', 'citewp-ai-search-optimizer' ); ?></span>
				<?php else : ?>
					<button type="button"
					        class="button button-secondary citewp-aiso-schema-btn"
					        data-type="article"
					        data-action="inject">
						<?php esc_html_e( 'Insert', 'citewp-ai-search-optimizer' ); ?>
					</button>
				<?php endif; ?>
			</div>

			<div class="citewp-aiso-mb-schema-row" data-schema-type="faqpage">
				<span class="citewp-aiso-mb-schema-label"><?php esc_html_e( 'FAQPage Schema', 'citewp-ai-search-optimizer' ); ?></span>
				<?php if ( 'injected' === $faq_state ) : ?>
					<button type="button"
					        class="button button-secondary citewp-aiso-schema-btn"
					        data-type="faqpage"
					        data-action="remove">
						<?php esc_html_e( 'Remove', 'citewp-ai-search-optimizer' ); ?>
					</button>
				<?php elseif ( 'detected' === $faq_state ) : ?>
					<span class="citewp-aiso-mb-detected"><?php esc_html_e( 'Already detected', 'citewp-ai-search-optimizer' ); ?></span>
				<?php elseif ( 'available' === $faq_state ) : ?>
					<button type="button"
					        class="button button-secondary citewp-aiso-schema-btn"
					        data-type="faqpage"
					        data-action="inject">
						<?php esc_html_e( 'Insert', 'citewp-ai-search-optimizer' ); ?>
					</button>
				<?php else : ?>
					<span class="citewp-aiso-mb-empty-note">
						<?php
						if ( 0 === $faq_count ) {
							esc_html_e( 'No FAQ content detected on this page.', 'citewp-ai-search-optimizer' );
						} else {
							esc_html_e( 'Only 1 question/answer pair detected. FAQPage schema requires at least 2 pairs.', 'citewp-ai-search-optimizer' );
						}
						?>
					</span>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $all_detected ) ) : ?>
			<p class="citewp-aiso-mb-other-types">
				<strong><?php esc_html_e( 'Detected on this page:', 'citewp-ai-search-optimizer' ); ?></strong>
				<?php echo esc_html( ' ' . implode( ', ', $all_detected ) ); ?>
			</p>
			<?php endif; ?>

		</div>
		<script>
		(function() {
			var box   = document.getElementById( <?php echo wp_json_encode( $box_id ); ?> );
			var nonce = <?php echo wp_json_encode( $nonce ); ?>;
			var url   = <?php echo wp_json_encode( esc_url_raw( $inject_url ) ); ?>;
			if ( ! box ) { return; }

			box.addEventListener( 'click', function( e ) {
				var btn = e.target.closest( '.citewp-aiso-schema-btn' );
				if ( ! btn ) { return; }

				var type   = btn.dataset.type;
				var action = btn.dataset.action;
				var row    = btn.closest( '[data-schema-type]' );
				if ( ! type || ! action || ! row ) { return; }

				var origText    = btn.textContent.trim();
				btn.disabled    = true;
				btn.textContent = 'inject' === action
					? <?php echo wp_json_encode( __( 'Inserting…', 'citewp-ai-search-optimizer' ) ); ?>
					: <?php echo wp_json_encode( __( 'Removing…', 'citewp-ai-search-optimizer' ) ); ?>;

				fetch( url, {
					method:  'POST',
					headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
					body:    JSON.stringify( { type: type, action: action } )
				} )
				.then( function( r ) {
					if ( r.status === 409 ) {
						// Another emitter already has valid same-@type schema (P67-C conflict guard).
						replaceAction( row, type, 'already' );
						return null;
					}
					return r.ok ? r.json() : Promise.reject( r.status );
				} )
				.then( function( data ) {
					if ( data === null ) { return; }
					var isInjected = Array.isArray( data.injected ) && data.injected.indexOf( type ) !== -1;
					replaceAction( row, type, isInjected ? 'remove' : 'insert' );
				} )
				.catch( function() {
					btn.disabled    = false;
					btn.textContent = origText;
				} );
			} );

			function replaceAction( row, type, mode ) {
				var label = row.querySelector( '.citewp-aiso-mb-schema-label' );
				row.innerHTML = '';
				if ( label ) { row.appendChild( label ); }

				if ( 'already' === mode ) {
					var pill = document.createElement( 'span' );
					pill.className   = 'citewp-aiso-mb-detected';
					pill.textContent = <?php echo wp_json_encode( __( 'Already detected', 'citewp-ai-search-optimizer' ) ); ?>;
					row.appendChild( pill );
				} else {
					var btn = document.createElement( 'button' );
					btn.type      = 'button';
					btn.className = 'button button-secondary citewp-aiso-schema-btn';
					btn.dataset.type   = type;
					btn.dataset.action = 'remove' === mode ? 'remove' : 'inject';
					btn.textContent    = 'remove' === mode
						? <?php echo wp_json_encode( __( 'Remove', 'citewp-ai-search-optimizer' ) ); ?>
						: <?php echo wp_json_encode( __( 'Insert', 'citewp-ai-search-optimizer' ) ); ?>;
					row.appendChild( btn );
				}
			}
		})();
		</script>
		<?php
	}

	/**
	 * @return array{rows: list<object>, n_more: int}
	 */
	private function query_bot_visits( int $post_id ): array {
		global $wpdb;

		$table = esc_sql( Schema::table( 'citewp_aiso_crawler_logs' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table queries; $table is esc_sql() of a hardcoded constant; $post_id is passed via $wpdb->prepare(); real-time admin display, intentionally uncached.
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
		// phpcs:enable

		return [ 'rows' => $rows, 'n_more' => $n_more ];
	}

	private function bot_dot_color( string $sig ): string {
		$palette = [ '#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6' ];
		return $palette[ abs( crc32( $sig ) ) % count( $palette ) ];
	}

	private function render_bot_visits( \WP_Post $post ): void {
		$result   = $this->query_bot_visits( $post->ID );
		$rows     = $result['rows'];
		$n_more   = $result['n_more'];
		$has_data = ! empty( $rows );

		// Lucide bot SVG — citrine stroke for icon block, currentColor for empty state
		$bot_svg_citrine = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#e8d400" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/></svg>';
		$bot_svg_muted   = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/></svg>';
		?>
		<div class="citewp-aiso-bv">
			<div class="citewp-aiso-bv__header">
				<div class="citewp-aiso-bv__title-wrap">
					<span class="citewp-aiso-bv__icon">
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo $bot_svg_citrine;
						?>
					</span>
					<span class="citewp-aiso-bv__title"><?php esc_html_e( 'Bot Visits', 'citewp-ai-search-optimizer' ); ?></span>
				</div>
				<span class="citewp-aiso-bv__pill"><?php esc_html_e( 'Last 7 days', 'citewp-ai-search-optimizer' ); ?></span>
			</div>

			<?php if ( $has_data ) : ?>

				<table class="citewp-aiso-bv__table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Bot', 'citewp-ai-search-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Visits', 'citewp-ai-search-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Last seen', 'citewp-ai-search-optimizer' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							$dot_color = $this->bot_dot_color( (string) $row->bot_signature );
							$last_seen = sprintf(
								/* translators: %s: human-readable time difference, e.g. "2 hours" */
								__( '%s ago', 'citewp-ai-search-optimizer' ),
								human_time_diff( (int) strtotime( (string) $row->last_seen ), time() )
							);
							?>
							<tr>
								<td>
									<div class="citewp-aiso-bv__bot-cell">
										<span class="citewp-aiso-bv__dot"
											  style="background:<?php echo esc_attr( $dot_color ); ?>;"
											  aria-hidden="true"></span>
										<?php echo esc_html( (string) $row->bot_signature ); ?>
									</div>
								</td>
								<td class="citewp-aiso-bv__visits"><?php echo esc_html( (string) $row->visits ); ?></td>
								<td><?php echo esc_html( $last_seen ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php if ( $n_more > 0 ) : ?>
					<p class="citewp-aiso-bv__overflow">
						<?php
						printf(
							/* translators: %d: number of additional bot signatures */
							esc_html__( 'and %d more', 'citewp-ai-search-optimizer' ),
							(int) $n_more
						);
						?>
					</p>
				<?php endif; ?>

				<p class="citewp-aiso-bv__footer">
					<?php
					echo wp_kses(
						__( 'Free tier shows 7 days of crawler activity. <strong>Pro extends to 90 days.</strong>', 'citewp-ai-search-optimizer' ),
						[ 'strong' => [] ]
					);
					?>
				</p>

			<?php else : ?>

				<div class="citewp-aiso-bv__empty">
					<div class="citewp-aiso-bv__empty-icon">
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo $bot_svg_muted;
						?>
					</div>
					<p class="citewp-aiso-bv__empty-title">
						<?php esc_html_e( 'No AI bot visits yet', 'citewp-ai-search-optimizer' ); ?>
					</p>
					<p class="citewp-aiso-bv__empty-desc">
						<?php esc_html_e( 'Most bots discover new posts within 24–72 hours of publishing.', 'citewp-ai-search-optimizer' ); ?>
					</p>
				</div>

			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Publishing Controls — full-width section below the 2-col grid.
	 *
	 * Only rendered on non-Gutenberg screens (meta box is suppressed in Gutenberg
	 * by register_meta_box() returning early for block-editor post types — P24).
	 *
	 * The X15 filter allows FB30 (Cite Bridges), FB40 (CPT scope), and future
	 * per-post controls to register additional rows without modifying this method.
	 *
	 * @param \WP_Post $post Post being edited.
	 */
	private function render_publishing_controls( \WP_Post $post ): void {
		$excluded    = get_post_meta( $post->ID, '_citewp_aiso_exclude_from_llms', true ) === '1';
		$checkbox_id = 'citewp_aiso_llms_include_' . $post->ID;

		/**
		 * Register additional Publishing Controls rows.
		 *
		 * Each item must be an array with a 'render' key holding a callable(\WP_Post): void.
		 *
		 * @param array<int, array{key: string, render: callable(\WP_Post): void}> $items
		 * @param \WP_Post $post
		 * @security Each registered callable is responsible for escaping its own output.
		 */
		$extra_items = (array) apply_filters( 'citewp_aiso/publishing_controls/items', [], $post );
		?>
		<div class="citewp-aiso-pc">
			<?php wp_nonce_field( 'citewp_aiso_ep_' . $post->ID, '_citewp_aiso_ep_nonce', false ); ?>
			<div class="citewp-aiso-pc__header">
				<span class="citewp-aiso-pc__title">
					<?php esc_html_e( 'Publishing Controls', 'citewp-ai-search-optimizer' ); ?>
				</span>
			</div>

			<div class="citewp-aiso-pc__row">
				<div class="citewp-aiso-pc__label-wrap">
					<label class="citewp-aiso-pc__label" for="<?php echo esc_attr( $checkbox_id ); ?>">
						<?php esc_html_e( 'Include in llms.txt', 'citewp-ai-search-optimizer' ); ?>
					</label>
					<span class="citewp-aiso-pc__help">
						<?php esc_html_e( 'AI search engines may discover this post via llms.txt. Toggle off to exclude this post from the file.', 'citewp-ai-search-optimizer' ); ?>
					</span>
				</div>
				<label class="citewp-aiso-pc__toggle" aria-label="<?php esc_attr_e( 'Include in llms.txt', 'citewp-ai-search-optimizer' ); ?>">
					<input type="checkbox"
					       id="<?php echo esc_attr( $checkbox_id ); ?>"
					       name="citewp_aiso_llms_include"
					       value="1"
					       <?php checked( ! $excluded ); ?>>
					<span class="citewp-aiso-pc__slider" aria-hidden="true"></span>
				</label>
			</div>

			<?php foreach ( $extra_items as $item ) : ?>
				<?php
				if ( isset( $item['render'] ) && is_callable( $item['render'] ) ) {
					call_user_func( $item['render'], $post );
				}
				?>
			<?php endforeach; ?>
		</div>
		<?php
	}

}
