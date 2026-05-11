# llms.txt Per-Post Widget Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a per-post "Include in llms.txt" toggle to two surfaces — EditorPanel meta box (classic/Elementor/Divi editors) and Gutenberg PluginDocumentSettingPanel — plus align the `--citewp-score-orange` token to a true orange.

**Architecture:** Three backend changes (meta registration, cache hook, save handler) + two UI surfaces (PHP meta box row, React panel). Both surfaces read/write `_citewp_aiso_exclude_from_llms`. P24 suppression ensures each editor sees exactly one surface. The toggle is positive ("Include in llms.txt") so its checked=true state maps to meta absent/`'0'`; checked=false maps to meta `'1'`.

**Tech Stack:** PHP 8.0+, WordPress 6.5+, React via @wordpress/scripts, `@wordpress/components` ToggleControl, `@wordpress/editor` PluginDocumentSettingPanel.

---

## Pre-flight: open questions resolved

| Q | Answer | Action |
|---|--------|--------|
| Q1 — `register_post_meta` with `show_in_rest`? | **Not registered** — zero hits in codebase | Task 1 adds it |
| Q2 — Does `ContentSelector` honor the meta key? | **Yes** — `ContentSelector.php:234` already reads it | No change needed |
| Q3 — Does `Cache::on_save_post` fire on meta-only Gutenberg saves? | Gutenberg REST save fires `save_post` via `wp_update_post()`; belt-and-suspenders: also hook `updated_post_meta` | Task 2 adds the meta hook |
| Q4 — Section label copy | **"Publishing Controls"** (EditorPanel) + **"AI Visibility"** (Gutenberg) — per brief §2.6 | Locked |

## File map

| File | Action | What changes |
|------|--------|--------------|
| `includes/Plugin.php` | Modify | Add `register_post_meta_fields()` + `init` hook in `boot()` |
| `includes/Llms/Cache.php` | Modify | Add `on_meta_update()` + `updated_post_meta`/`added_post_meta` hooks |
| `includes/Admin/EditorPanel.php` | Modify | Add `save_meta()` + `save_post` hook; add `render_publishing_controls()`; call from `render_general_tab()` |
| `admin/css/citewp-aiso-admin.css` | Modify | Append Section 31 Publishing Controls styles; align orange token |
| `src/sidebar/index.js` | Modify | Add `ToggleControl` import; add `AiVisibility` component + `registerPlugin` |
| `src/sidebar/style.scss` | Modify | Token alignment: `$citewp-score-orange: #E86612` |

---

## Task 1: Register `_citewp_aiso_exclude_from_llms` with REST API

**Files:**
- Modify: `includes/Plugin.php`

**Why:** Gutenberg's `ToggleControl` reads/writes post meta via the WP REST API. Meta must be registered with `show_in_rest: true` for `editPost({ meta: { ... } })` to persist. Currently no `register_post_meta` call exists anywhere in the codebase — without this Task 6's toggle saves nothing.

- [ ] **Step 1: Add `init` hook in `boot()`**

In `includes/Plugin.php`, locate the block that registers `score_history`. Add one line immediately after it, before the `if ( is_admin() )` block:

```php
		$this->modules['score_history'] = new Scoring\ScoreHistory();
		$this->modules['score_history']->register();

		// Register post meta with REST API support (needed on all requests, including REST).
		add_action( 'init', [ $this, 'register_post_meta_fields' ] );

		// Admin-only modules.
		if ( is_admin() ) {
```

- [ ] **Step 2: Add `register_post_meta_fields()` method**

Add this public method to the `Plugin` class directly after the closing brace of `boot()` and before the `activate()` method:

```php
	/**
	 * Register post meta with REST API support.
	 *
	 * Separate register_post_meta() calls per post type (not one call with an array)
	 * so FB40 (CPT scope) can extend to additional types from its own module
	 * without modifying this method.
	 */
	public function register_post_meta_fields(): void {
		foreach ( [ 'post', 'page' ] as $post_type ) {
			register_post_meta(
				$post_type,
				'_citewp_aiso_exclude_from_llms',
				[
					'type'              => 'boolean',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'rest_sanitize_boolean',
					'auth_callback'     => static function ( bool $allowed, string $meta_key, int $post_id ): bool {
						return current_user_can( 'edit_post', $post_id );
					},
				]
			);
		}
	}
```

- [ ] **Step 3: Verify PHP syntax**

```powershell
& "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\php\8.2.30\php.exe" -l "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer\includes\Plugin.php"
```

Expected: `No syntax errors detected in ...Plugin.php`

- [ ] **Step 4: Commit**

```powershell
git -C "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer" add includes/Plugin.php
git -C "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer" commit -m "feat: register _citewp_aiso_exclude_from_llms post meta with REST API support"
```

---

## Task 2: Cache flush on meta-only updates

**Files:**
- Modify: `includes/Llms/Cache.php`

**Why:** `on_save_post` covers the Gutenberg REST-save path (which fires `save_post` via `wp_update_post()`). Belt-and-suspenders: `updated_post_meta` / `added_post_meta` catch any code path that writes the meta key directly (CLI, bulk-edit, third-party code) without triggering `save_post`. Both hooks use the same handler — the key check means there is zero performance cost on unrelated meta writes.

- [ ] **Step 1: Add hooks in `register()`**

In `includes/Llms/Cache.php`, find the last three lines of `register()`:

```php
		add_action( 'activated_plugin', [ $this, 'flush' ] );
		add_action( 'deactivated_plugin', [ $this, 'flush' ] );
		add_action( 'switch_theme', [ $this, 'flush' ] );
	}
```

Replace with:

```php
		add_action( 'activated_plugin', [ $this, 'flush' ] );
		add_action( 'deactivated_plugin', [ $this, 'flush' ] );
		add_action( 'switch_theme', [ $this, 'flush' ] );

		// Flush when the per-post llms.txt toggle is written via any code path.
		// Covers direct update_post_meta() calls that don't trigger save_post.
		add_action( 'updated_post_meta', [ $this, 'on_meta_update' ], 10, 3 );
		add_action( 'added_post_meta',   [ $this, 'on_meta_update' ], 10, 3 );
	}
```

- [ ] **Step 2: Add `on_meta_update()` method**

Add this method after `on_save_post()` in `Cache.php`:

```php
	/**
	 * Flush when _citewp_aiso_exclude_from_llms is updated via any path.
	 *
	 * Hooks: updated_post_meta, added_post_meta.
	 * Both fire with ( $meta_id, $object_id, $meta_key, $meta_value ) — we only
	 * need the first three args to filter by key and check post status.
	 *
	 * @param int    $meta_id  wp_postmeta row ID (unused).
	 * @param int    $post_id  Post being updated.
	 * @param string $meta_key Meta key that changed.
	 */
	public function on_meta_update( int $meta_id, int $post_id, string $meta_key ): void {
		if ( $meta_key !== '_citewp_aiso_exclude_from_llms' ) {
			return;
		}
		if ( get_post_status( $post_id ) !== 'publish' ) {
			return;
		}
		$this->flush();
	}
```

- [ ] **Step 3: Verify PHP syntax**

```powershell
& "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\php\8.2.30\php.exe" -l "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer\includes\Llms\Cache.php"
```

Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```powershell
git -C "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer" add includes/Llms/Cache.php
git -C "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer" commit -m "fix: flush llms.txt cache on _citewp_aiso_exclude_from_llms meta change"
```

---

## Task 3: EditorPanel — save handler (classic editor path)

**Files:**
- Modify: `includes/Admin/EditorPanel.php`

**Why:** When the user clicks Update/Publish in Classic Editor, Elementor WP-mode, Divi, or Beaver Builder, WordPress submits the post form via HTTP POST. A `save_post` hook reads the checkbox name from `$_POST` and writes the meta. For Gutenberg REST saves, `$_POST` is empty — the nonce check returns early, so the REST-saved value is never overwritten.

- [ ] **Step 1: Add `save_post` hook to `register()`**

Find the current `register()` method:

```php
	public function register(): void {
		add_action( 'add_meta_boxes',        [ $this, 'register_meta_box' ], 20 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );
	}
```

Replace with:

```php
	public function register(): void {
		add_action( 'add_meta_boxes',        [ $this, 'register_meta_box' ], 20 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );
		add_action( 'save_post',             [ $this, 'save_meta' ], 20, 2 );
	}
```

- [ ] **Step 2: Add `save_meta()` method**

Add this public method immediately after `enqueue_styles()` and before `register_meta_box()`:

```php
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
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		// Checkbox present = user wants to include the post; absent = exclude.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above
		$include = isset( $_POST['citewp_aiso_llms_include'] );
		update_post_meta( $post_id, '_citewp_aiso_exclude_from_llms', $include ? '0' : '1' );
	}
```

- [ ] **Step 3: Verify PHP syntax**

```powershell
& "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\php\8.2.30\php.exe" -l "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer\includes\Admin\EditorPanel.php"
```

Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```powershell
git -C "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer" add includes/Admin/EditorPanel.php
git -C "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer" commit -m "feat: EditorPanel save_meta handler for llms.txt toggle (classic editor)"
```

---

## Task 4: EditorPanel — Publishing Controls render

**Files:**
- Modify: `includes/Admin/EditorPanel.php`

**Why:** Adds the visible Publishing Controls section below the 2-col grid in the General tab. Contains a nonce hidden field, section header, and a checkbox-as-toggle with label + help text. An X15 filter (`citewp_aiso/publishing_controls/items`) allows FB30 / FB40 to add their own rows to this section without modifying EditorPanel.php.

- [ ] **Step 1: Wire `render_publishing_controls()` call in `render_general_tab()`**

In `includes/Admin/EditorPanel.php`, inside `render_general_tab()`, find the closing divs at the end of the HTML block (the `</div>` for `.citewp-aiso-ep-columns` followed by the `</div>` for `.citewp-aiso-ep-general`, then the `<script>` tag):

```php
		</div>

	</div>
	<script>
```

Replace with:

```php
		</div>

		<?php $this->render_publishing_controls( $post ); ?>
	</div>
	<script>
```

- [ ] **Step 2: Add `render_publishing_controls()` method**

Add this private method to `EditorPanel.php` after `render_bot_visits()` (before the final `}` that closes the class):

```php
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

		wp_nonce_field( 'citewp_aiso_ep_' . $post->ID, '_citewp_aiso_ep_nonce', false );

		/**
		 * Register additional Publishing Controls rows.
		 *
		 * Each item must be an array with a 'render' key holding a callable(\WP_Post): void.
		 *
		 * @param array<int, array{key: string, render: callable(\WP_Post): void}> $items
		 * @param \WP_Post $post
		 */
		$extra_items = apply_filters( 'citewp_aiso/publishing_controls/items', [], $post );
		?>
		<div class="citewp-aiso-pc">
			<div class="citewp-aiso-pc__header">
				<span class="citewp-aiso-pc__title">
					<?php esc_html_e( 'Publishing Controls', 'ai-search-optimizer' ); ?>
				</span>
			</div>

			<div class="citewp-aiso-pc__row">
				<div class="citewp-aiso-pc__label-wrap">
					<label class="citewp-aiso-pc__label" for="<?php echo esc_attr( $checkbox_id ); ?>">
						<?php esc_html_e( 'Include in llms.txt', 'ai-search-optimizer' ); ?>
					</label>
					<span class="citewp-aiso-pc__help">
						<?php esc_html_e( 'AI search engines may discover this post via llms.txt. Toggle off to exclude this post from the file.', 'ai-search-optimizer' ); ?>
					</span>
				</div>
				<label class="citewp-aiso-pc__toggle" aria-label="<?php esc_attr_e( 'Include in llms.txt', 'ai-search-optimizer' ); ?>">
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
```

- [ ] **Step 3: Verify PHP syntax**

```powershell
& "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\php\8.2.30\php.exe" -l "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer\includes\Admin\EditorPanel.php"
```

Expected: `No syntax errors detected`

- [ ] **Step 4: Verify the call is present in the file**

```powershell
Select-String -Path "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer\includes\Admin\EditorPanel.php" -Pattern "render_publishing_controls"
```

Expected: 2 matches — one in the method definition, one in the call site inside `render_general_tab()`.

- [ ] **Step 5: Commit**

```powershell
git -C "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer" add includes/Admin/EditorPanel.php
git -C "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer" commit -m "feat: EditorPanel Publishing Controls render — llms.txt toggle + X15 filter"
```

---

## Task 5: Publishing Controls CSS

**Files:**
- Modify: `admin/css/citewp-aiso-admin.css`

**X20 checklist compliance:**
- Paper surface, P38 tokens — no inline hex (all values via CSS custom properties)
- Section divider: `--citewp-border`, 1px — hairline
- Help text: `--citewp-text-muted`, 12px, sits below the label
- Toggle: pill-switch pattern (appearance: none + ::after dot), Citrine active state consistent with other CiteWP admin toggles
- No new fonts (toggle label inherits system-ui stack)
- No P41 button elements introduced

- [ ] **Step 1: Append Section 31 to the end of admin CSS**

Open `admin/css/citewp-aiso-admin.css` and append this block at the very end of the file:

```css
/* =========================================================
   Section 31 — Publishing Controls (EditorPanel row)
   ========================================================= */

#citewp_aiso_editor_panel .citewp-aiso-pc {
	border-top: 1px solid var(--citewp-border);
	margin-top: 16px;
	padding-top: 16px;
}

#citewp_aiso_editor_panel .citewp-aiso-pc__header {
	margin-bottom: 8px;
}

#citewp_aiso_editor_panel .citewp-aiso-pc__title {
	font-size: 11px;
	font-weight: 600;
	letter-spacing: 0.5px;
	text-transform: uppercase;
	color: var(--citewp-text-muted);
}

#citewp_aiso_editor_panel .citewp-aiso-pc__row {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 16px;
}

#citewp_aiso_editor_panel .citewp-aiso-pc__label-wrap {
	display: flex;
	flex-direction: column;
	gap: 4px;
	flex: 1;
}

#citewp_aiso_editor_panel .citewp-aiso-pc__label {
	font-size: 13px;
	font-weight: 600;
	color: var(--citewp-text);
	cursor: pointer;
}

#citewp_aiso_editor_panel .citewp-aiso-pc__help {
	font-size: 12px;
	color: var(--citewp-text-muted);
	line-height: 1.5;
}

/* Toggle — checkbox styled as pill switch */
#citewp_aiso_editor_panel .citewp-aiso-pc__toggle {
	position: relative;
	display: inline-flex;
	align-items: center;
	flex-shrink: 0;
	cursor: pointer;
}

#citewp_aiso_editor_panel .citewp-aiso-pc__toggle input[type="checkbox"] {
	width: 36px;
	height: 20px;
	appearance: none;
	-webkit-appearance: none;
	background: var(--citewp-border);
	border-radius: 10px;
	cursor: pointer;
	transition: background 0.15s ease;
	margin: 0;
	padding: 0;
	outline: none;
	position: relative;
	vertical-align: middle;
}

#citewp_aiso_editor_panel .citewp-aiso-pc__toggle input[type="checkbox"]::after {
	content: '';
	position: absolute;
	top: 2px;
	left: 2px;
	width: 16px;
	height: 16px;
	background: #fff;
	border-radius: 50%;
	transition: transform 0.15s ease;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

#citewp_aiso_editor_panel .citewp-aiso-pc__toggle input[type="checkbox"]:checked {
	background: var(--citewp-citrine);
}

#citewp_aiso_editor_panel .citewp-aiso-pc__toggle input[type="checkbox"]:checked::after {
	transform: translateX(16px);
}

#citewp_aiso_editor_panel .citewp-aiso-pc__toggle input[type="checkbox"]:focus-visible {
	box-shadow: 0 0 0 2px var(--wp-admin-theme-color, #3858e9);
}

/* Slider span is present in markup (aria-hidden) but styling is handled by input::after above */
#citewp_aiso_editor_panel .citewp-aiso-pc__slider {
	display: none;
}
```

- [ ] **Step 2: Commit**

```powershell
git -C "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer" add admin/css/citewp-aiso-admin.css
git -C "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer" commit -m "feat: Publishing Controls CSS — toggle row, Section 31 admin CSS"
```

---

## Task 6: Gutenberg AI Visibility panel

**Files:**
- Modify: `src/sidebar/index.js`

**Why:** Gutenberg users need the llms.txt toggle in the Document Settings sidebar. `PluginDocumentSettingPanel` is the highest-discoverability surface — always visible when Document tab is active. P24 suppression means the EditorPanel meta box doesn't render in Gutenberg, so there is no duplication.

**Toggle polarity:** `_citewp_aiso_exclude_from_llms` is an exclusion flag. Toggle is an inclusion label. So:
- `checked = ! meta['_citewp_aiso_exclude_from_llms']`
- `onChange(newValue)` → `editPost({ meta: { _citewp_aiso_exclude_from_llms: !newValue } })`

When `newValue` is `true` (user toggled ON = include): store `false` → REST stores `''` → `ContentSelector.php` `=== '1'` check fails → post IS included. ✓
When `newValue` is `false` (user toggled OFF = exclude): store `true` → REST stores `'1'` → `=== '1'` → post IS excluded. ✓

**Note on FB39 (Publish block injection):** The new `registerPlugin( 'citewp-aiso-ai-visibility', ... )` call is structurally independent of the existing `citewp-aiso-geo-score` and `citewp-aiso-schema-suggestions` plugins. FB39 will add a fourth standalone `registerPlugin` call when scheduled — no refactor needed.

- [ ] **Step 1: Add `ToggleControl` to the components import**

Find:

```js
import { Button, Spinner, PanelBody } from '@wordpress/components';
```

Replace with:

```js
import { Button, Spinner, PanelBody, ToggleControl } from '@wordpress/components';
```

- [ ] **Step 2: Add `AiVisibility` component**

Insert this new component and registration block after the last `registerPlugin` call in the file (after `registerPlugin( 'citewp-aiso-schema-suggestions', ... );`):

```js
// === AI Visibility — Document Settings panel ===

function AiVisibility() {
	const meta         = useSelect( ( s ) => s( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {}, [] );
	const { editPost } = useDispatch( 'core/editor' );
	const isIncluded   = ! meta['_citewp_aiso_exclude_from_llms'];

	return (
		<div className="citewp-aiso-ai-visibility">
			<ToggleControl
				label="Include in llms.txt"
				help="AI search engines may discover this post via llms.txt. Toggle off to exclude this post from the file."
				checked={ isIncluded }
				onChange={ ( newValue ) => {
					editPost( { meta: { _citewp_aiso_exclude_from_llms: ! newValue } } );
				} }
			/>
		</div>
	);
}

registerPlugin( 'citewp-aiso-ai-visibility', {
	render: () => (
		<PluginDocumentSettingPanel
			name="citewp-aiso-ai-visibility"
			title="AI Visibility"
			className="citewp-aiso-ai-visibility-panel"
		>
			<AiVisibility />
		</PluginDocumentSettingPanel>
	),
} );
```

- [ ] **Step 3: Build**

```powershell
Set-Location "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer"
npm run build
```

Expected: clean build, no errors. `build/index.js` and `build/index.asset.php` updated.

- [ ] **Step 4: Verify the plugin string was bundled**

```powershell
Select-String -Path "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer\build\index.js" -Pattern "citewp-aiso-ai-visibility" -Quiet
```

Expected: `True`

- [ ] **Step 5: Commit**

```powershell
git -C "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer" add src/sidebar/index.js build/index.js build/index.asset.php
git -C "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer" commit -m "feat: Gutenberg AI Visibility panel — PluginDocumentSettingPanel with ToggleControl"
```

---

## Task 7: Token alignment — `--citewp-score-orange`

**Files:**
- Modify: `src/sidebar/style.scss`
- Modify: `admin/css/citewp-aiso-admin.css`

**Why (secondary deliverable from S24):** Both files define `#D63638` for the orange score token. That hex is WP's error-red — not visually distinct from `--citewp-score-red`. A true orange (`#E86612`) is legible on the 4-tier ramp (green → yellow → orange → red). Both files match today, so a single commit aligns both. **P44 thresholds (≥80/≥60/≥40) are unchanged.**

- [ ] **Step 1: Update SCSS token**

In `src/sidebar/style.scss`, replace:

```scss
$citewp-score-orange: #D63638;
```

with:

```scss
$citewp-score-orange: #E86612;
```

- [ ] **Step 2: Update admin CSS global token (root-level)**

In `admin/css/citewp-aiso-admin.css`, find the root-level custom property (around line 95 in the `:root` block):

```css
  --citewp-score-orange:  #D63638;
```

Replace with:

```css
  --citewp-score-orange:  #E86612;
```

- [ ] **Step 3: Update admin CSS EditorPanel-scoped token**

In `admin/css/citewp-aiso-admin.css`, find the EditorPanel-scoped override (around line 2528 inside `#citewp_aiso_editor_panel { ... }`):

```css
	--citewp-score-orange: #D63638;
```

Replace with:

```css
	--citewp-score-orange: #E86612;
```

- [ ] **Step 4: Build to recompile SCSS**

```powershell
Set-Location "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer"
npm run build
```

Expected: clean build. `build/style-index.css` will contain updated orange values.

- [ ] **Step 5: Verify no old value remains**

```powershell
Select-String -Path "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer\src\sidebar\style.scss", "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer\admin\css\citewp-aiso-admin.css" -Pattern "D63638"
```

Expected: no matches.

- [ ] **Step 6: Commit**

```powershell
git -C "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer" add src/sidebar/style.scss admin/css/citewp-aiso-admin.css build/style-index.css build/style-index-rtl.css
git -C "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer" commit -m "fix: align --citewp-score-orange to true orange #E86612 (admin CSS + sidebar SCSS)"
```

---

## Self-review

### Spec coverage

| Requirement | Task |
|-------------|------|
| `register_post_meta()` with `show_in_rest` | Task 1 |
| Cache flush on meta-only update paths | Task 2 |
| EditorPanel save handler (classic editor) | Task 3 |
| EditorPanel Publishing Controls render + nonce + X15 filter | Task 4 |
| Publishing Controls CSS — P38 tokens, toggle, divider, help text | Task 5 |
| Gutenberg AI Visibility PluginDocumentSettingPanel | Task 6 |
| Token alignment `#D63638 → #E86612` | Task 7 |
| P24 suppression (meta box hidden in Gutenberg) | Existing — `register_meta_box()` already returns early for block-editor types. No change. |
| ContentSelector already honors the meta key | Existing — `ContentSelector.php:234`. No change. |
| Section label "Publishing Controls" | Task 4 |
| Panel title "AI Visibility" | Task 6 |
| Toggle label "Include in llms.txt" + advisory help text (X12) | Tasks 4 + 6 |
| X15 extensibility filter | Task 4 — `citewp_aiso/publishing_controls/items` |
| FB39 not blocked by JS structure | Task 6 — new `registerPlugin` is standalone, no shared closure |
| Secondary deliverable (token alignment) | Task 7 |

### Placeholder scan

No TBD / TODO / "implement later" / "similar to Task N" present.

### Type/name consistency

- `render_publishing_controls( \WP_Post $post ): void` — private method, called as `$this->render_publishing_controls( $post )` from `render_general_tab()`. ✓
- `save_meta( int $post_id, \WP_Post $post ): void` — public, registered as `[ $this, 'save_meta' ]`. ✓
- `register_post_meta_fields(): void` — public, registered as `[ $this, 'register_post_meta_fields' ]`. ✓
- `on_meta_update( int $meta_id, int $post_id, string $meta_key ): void` — public, registered with `10, 3` (3 args). Hook fires 4 args; PHP silently drops the 4th. ✓
- Meta key `_citewp_aiso_exclude_from_llms` consistent: Task 1 registers it, Task 2 filters on it, Task 3 writes it, Task 4 reads it, Task 6 reads/writes it. ✓
- Toggle polarity consistent: PHP `! $excluded` = `checked` state; JS `! meta['_citewp_aiso_exclude_from_llms']` = `checked` state. Both mean "Include=ON when exclude flag is falsy." ✓

---

## Post-implementation verification (before push)

Run these checks after all tasks complete:

1. **Functional round-trip:**
   - Open a published post in Classic Editor → Publishing Controls row visible in General tab → uncheck "Include in llms.txt" → Update → visit `/llms.txt` → post NOT in output
   - Re-check → Update → `/llms.txt` → post back in output
   - New post never touched: visit `/llms.txt` → post IS in output (default ON behaviour unchanged)

2. **Gutenberg path:**
   - Open a post in Gutenberg → Document Settings → "AI Visibility" panel visible → toggle OFF → Update → `/llms.txt` → post NOT in output
   - EditorPanel meta box NOT visible in Gutenberg (P24 confirmed)

3. **Cache bust:**
   - `/llms.txt` immediately reflects toggle change (no stale serve within same browser session)

4. **Elementor canvas:**
   - EditorPanel meta box not visible in Elementor canvas (documented P22 limitation)
   - Reachable via "Edit in WordPress" link

5. **`debug.log`:** no new PHP errors after all tasks

6. **`npm run build`:** clean (already run in Tasks 6 + 7)
