# Universal Cite Score + Schema Meta Box — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add native WP meta boxes for Cite Score and Schema Suggestions so every editor (Classic, Elementor, Divi, Beaver Builder, Gutenberg compat panel) surfaces them — not just the Gutenberg PluginSidebar.

**Architecture:** Two new `final class` files under `includes/Admin/`, each self-contained with `register()` → hooks, `render(\WP_Post)` → HTML + inline JS, `inline_styles()` → scoped CSS. Both wired into `Plugin::boot()` under `is_admin()`. No new JS bundle, no `wp_enqueue_script`, no build step.

**Tech Stack:** PHP 8.0+, WordPress 6.5+, `$wpdb` post meta reads, existing REST endpoints (`citewp/aiso/v1`), `Schema\Generator` PHP class, vanilla JS `fetch` + `navigator.clipboard`.

---

## File Map

| Action | Path | Responsibility |
|---|---|---|
| **Create** | `includes/Admin/ScoreMetaBox.php` | Register + render Cite Score meta box; recalculate via REST |
| **Create** | `includes/Admin/SchemaMetaBox.php` | Register + render Schema Suggestions meta box; clipboard copy |
| **Modify** | `includes/Plugin.php` | Wire both modules in `is_admin()` block |

---

## Task 1: Create `ScoreMetaBox.php`

**Files:**
- Create: `includes/Admin/ScoreMetaBox.php`

**Key data facts (confirmed from `ScoreResult::to_array()`):**
- `Repository::META_KEY_FULL` → serialised array: `['total' => int, 'grade' => string, 'categories' => ['structure' => ['score', 'max', 'label'], 'citability' => [...], 'authority' => [...]], 'signals' => [...]]`
- `Repository::META_KEY_TIME` → MySQL GMT timestamp string
- `Repository::get(int $post_id)` → `?array` (null = not yet scored)
- Recalculate REST: `POST /wp-json/citewp/aiso/v1/score/{post_id}/recalculate` + `X-WP-Nonce: {wp_rest nonce}` → same array shape

- [ ] **Step 1: Create the file**

```php
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
					          + '<span class="citewp-aiso-mb-badge citewp-aiso-mb-badge--' + grade + '">' + total + '</span>'
					          + '<span class="citewp-aiso-mb-total-label"> / 100</span>'
					          + '</div>';
					if ( cats.structure ) {
						html += '<div class="citewp-aiso-mb-categories">';
						[ 'structure', 'citability', 'authority' ].forEach( function( k ) {
							if ( cats[ k ] ) {
								html += '<div class="citewp-aiso-mb-cat-row">'
								      + '<span class="citewp-aiso-mb-cat-label">' + cats[ k ].label + '</span>'
								      + '<span class="citewp-aiso-mb-cat-score">' + cats[ k ].score + ' / ' + cats[ k ].max + '</span>'
								      + '</div>';
							}
						} );
						html += '</div>';
					}
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
```

- [ ] **Step 2: Verify PHP syntax**

Open a PowerShell terminal from `C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer\` and run:

```powershell
& "C:\Users\KingpinBWP\Local Sites\citewp-dev\components\php\8.2.30\php.exe" -l includes/Admin/ScoreMetaBox.php
```

Expected: `No syntax errors detected in includes/Admin/ScoreMetaBox.php`

- [ ] **Step 3: Commit**

```bash
git add includes/Admin/ScoreMetaBox.php
git commit -m "feat: Cite Score meta box for Classic/Elementor/Divi editors (P22)"
```

---

## Task 2: Create `SchemaMetaBox.php`

**Files:**
- Create: `includes/Admin/SchemaMetaBox.php`

**Key data facts (confirmed from `SchemaController` + `Schema\Generator`):**
- `Generator::generate_article_schema(WP_Post)` → `array` (always non-empty for scorable posts)
- `Generator::generate_faq_schema(WP_Post)` → `array|[]` (empty array = no FAQ; null check uses `!empty()`)
- `Generator::detect_existing_types(WP_Post)` → `string[]` (root-level `@type` values found in post_content)
- Article is always available. FAQPage requires ≥ 2 Q&A pairs. Other detected types shown read-only.
- Copy action: clipboard only (no TinyMCE injection — see spec rationale). Fade timer cleared on repeat clicks (prevents stale-note bug).

- [ ] **Step 1: Create the file**

```php
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

	/** Callback for add_meta_boxes hook. */
	public function register_meta_box(): void {
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
		$faq_json     = ! empty( $faqpage )
			? wp_json_encode( $faqpage, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
			: null;
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
				// Note element is always the next sibling of the button in our markup.
				var note = btn.nextElementSibling;

				btn.addEventListener( 'click', function() {
					var schema    = btn.dataset.schema;
					var origLabel = btn.dataset.label;

					navigator.clipboard.writeText( schema ).then( function() {
						btn.textContent = <?php echo wp_json_encode( __( '✓ Copied to clipboard', 'ai-search-optimizer' ) ); ?>;

						// Clear any existing fade so repeated clicks show a fresh note.
						if ( fadeTimer ) { clearTimeout( fadeTimer ); }
						note.style.transition = '';
						note.style.opacity    = '1';
						note.style.display    = 'block';

						// Revert button label after 3 s.
						setTimeout( function() { btn.textContent = origLabel; }, 3000 );

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

					} ).catch( function() {
						btn.textContent = <?php echo wp_json_encode( __( 'Copy failed — try again', 'ai-search-optimizer' ) ); ?>;
						setTimeout( function() { btn.textContent = origLabel; }, 3000 );
					} );
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
```

- [ ] **Step 2: Verify PHP syntax**

```powershell
& "C:\Users\KingpinBWP\Local Sites\citewp-dev\components\php\8.2.30\php.exe" -l includes/Admin/SchemaMetaBox.php
```

Expected: `No syntax errors detected in includes/Admin/SchemaMetaBox.php`

- [ ] **Step 3: Commit**

```bash
git add includes/Admin/SchemaMetaBox.php
git commit -m "feat: Schema Suggestions meta box for Classic/Elementor/Divi editors (P22)"
```

---

## Task 3: Wire both meta boxes into `Plugin.php`

**Files:**
- Modify: `includes/Plugin.php` (lines 62–80, inside `is_admin()` block)

- [ ] **Step 1: Add both modules to `Plugin::boot()`**

In `includes/Plugin.php`, find the `is_admin()` block and add two lines after the existing `dashboard_widget` registration:

```php
			$this->modules['dashboard_widget'] = new Admin\DashboardWidget();
			$this->modules['dashboard_widget']->register();

			$this->modules['score_meta_box'] = new Admin\ScoreMetaBox();
			$this->modules['score_meta_box']->register();

			$this->modules['schema_meta_box'] = new Admin\SchemaMetaBox();
			$this->modules['schema_meta_box']->register();
		}
```

- [ ] **Step 2: Verify PHP syntax on Plugin.php**

```powershell
& "C:\Users\KingpinBWP\Local Sites\citewp-dev\components\php\8.2.30\php.exe" -l includes/Plugin.php
```

Expected: `No syntax errors detected in includes/Plugin.php`

- [ ] **Step 3: Verify JS build is unaffected**

No JS changes were made, but confirm:

```bash
npm run build
```

Expected: exits 0, no new warnings.

- [ ] **Step 4: Manual verification checklist**

Open `http://citewp-dev.local/wp-admin` and verify each item:

**Score meta box:**
- [ ] Edit any published post → "Cite Score" meta box appears in right sidebar below Yoast/Rank Math
- [ ] Score number shows with correct grade colour (green/yellow/orange/red)
- [ ] Three category rows show (Structure / Citability / Authority) with `score / max` format
- [ ] "Scored X ago" timestamp appears
- [ ] Click **Recalculate** → button shows "Recalculating…", re-enables, score updates in place without page reload
- [ ] Edit a post with no prior score (new draft) → "Score not yet calculated." shown → Recalculate triggers calculation and displays result
- [ ] Log out, visit edit screen as Subscriber role → meta box does not render (capability check)

**Schema meta box:**
- [ ] Edit a published post → "Schema Suggestions" meta box appears in right sidebar
- [ ] "Article Schema" row shows **Copy Article schema** button
- [ ] Click **Copy Article schema** → button text changes to "✓ Copied to clipboard", advisory note appears below
- [ ] Paste clipboard contents into a text editor → valid `<script type="application/ld+json">...</script>` block
- [ ] Click **Copy Article schema** a second time immediately → note resets correctly (fade timer cleared)
- [ ] Note fades after ~8 seconds
- [ ] Edit a post with FAQ content (≥ 2 H2/H3 questions) → "FAQPage Schema" row shows Copy button
- [ ] Edit a post without FAQ content → "FAQPage Schema" row shows "No FAQ content detected (need ≥ 2 Q&A pairs)"
- [ ] Edit a post that already has Article JSON-LD in content → "✓ Already in content" badge, no Copy button

**Both boxes:**
- [ ] Check LocalWP `debug.log` — no new PHP errors or warnings
- [ ] Both meta boxes absent from media library edit screen (only `post` base triggers)

- [ ] **Step 5: Commit**

```bash
git add includes/Plugin.php
git commit -m "feat: wire ScoreMetaBox + SchemaMetaBox into Plugin::boot() (P22)"
```

---

## Task 4: Session close

- [ ] Push to origin

```bash
git push
```

- [ ] Update `SESSION-LOG.md` with what shipped, carryover (none expected), next session focus
- [ ] Run `/session-end`
