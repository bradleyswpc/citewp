# Universal Cite Score + Schema Meta Box — Design Spec

**Date:** 2026-04-29  
**Session:** 11  
**Decisions:** P22, P20, X12, P16  
**Deliverable:** Two new files + Plugin.php wiring

---

## Overview

Add native WordPress meta boxes so the Cite Score and Schema Suggestions are visible to every user regardless of editor — Classic Editor, Elementor, Divi, Beaver Builder, and any other page builder that respects the standard WP post edit screen.

The Gutenberg `PluginSidebar` and `PluginDocumentSettingPanel` remain the primary experience. The meta boxes are the universal fallback that brings ~95%+ editor coverage with ~300 lines of new PHP.

---

## Files

| Action | Path |
|---|---|
| **Create** | `includes/Admin/ScoreMetaBox.php` |
| **Create** | `includes/Admin/SchemaMetaBox.php` |
| **Modify** | `includes/Plugin.php` |

No new JS files. No new build step. No `wp_enqueue_script` registration. Inline `<script>` tags inside `render()` methods — consistent with `DashboardWidget.php`.

---

## ScoreMetaBox

### Registration

```php
add_action( 'add_meta_boxes', [ $this, 'register_box' ], 20 );
add_action( 'admin_head',     [ $this, 'inline_styles' ] );
```

Hook priority 20 on `add_meta_boxes` — fires after Yoast (priority 10) and Rank Math (priority 10), preventing visual displacement of established SEO plugin meta boxes.

```php
add_meta_box(
    'citewp_aiso_cite_score',
    __( 'Cite Score', 'ai-search-optimizer' ),
    [ $this, 'render' ],
    [ 'post', 'page' ],   // matches Repository::SCORABLE_TYPES
    'side',
    'default'
);
```

`'side'` context + `'default'` priority = sits in the sidebar, below Yoast/Rank Math's high-priority boxes, above the custom fields box. Clean, non-intrusive placement.

### Capability Check

Before rendering anything:

```php
if ( ! current_user_can( 'edit_post', $post->ID ) ) {
    return;
}
```

Matches the `permission_callback` in `ScoreController.php` — a user who cannot trigger recalculate via REST also cannot see the button that would call it.

### Render Output (states)

**State 1 — Scored:** Score badge (grade-colored number) + three category rows (Structure / Citability / Authority with sub-scores) + last scored timestamp + Recalculate button.

**State 2 — Not yet scored:** "Score not yet calculated." inline note + Recalculate button (triggers on-demand calculation).

**State 3 — Loading (JS):** Button disabled, label changes to "Recalculating…" while fetch is in flight.

**State 4 — Error (JS):** Button re-enabled, brief inline error message "Recalculation failed — please try again."

### Recalculate Mechanism

Inline JS in `render()` output. Uses the existing REST endpoint — no new server-side logic:

```
POST /wp-json/citewp/aiso/v1/score/{post_id}/recalculate
X-WP-Nonce: {wp_rest nonce}
```

Nonce output pattern (matches DashboardWidget inline-script approach):

```php
$nonce = wp_create_nonce( 'wp_rest' );
// echoed into a <script> block inside the meta box container
```

On success: updates score badge, category rows, and timestamp in place (no page reload).

### Styles

Inline `<style>` block in `inline_styles()`, scoped to `#citewp_aiso_cite_score`. Reuses the grade color tokens already defined in `DashboardWidget.php` (green/yellow/orange/red). No new color values introduced.

Meta box container is narrow (`~280px`). Category rows use a two-column layout: label left, score right. Score badge matches existing `.citewp-aiso-score-badge` pattern.

---

## SchemaMetaBox

### Registration

Same hook priority pattern as ScoreMetaBox:

```php
add_action( 'add_meta_boxes', [ $this, 'register_box' ], 20 );
add_action( 'admin_head',     [ $this, 'inline_styles' ] );
```

```php
add_meta_box(
    'citewp_aiso_schema',
    __( 'Schema Suggestions', 'ai-search-optimizer' ),
    [ $this, 'render' ],
    [ 'post', 'page' ],
    'side',
    'default'
);
```

### Capability Check

Same pattern as ScoreMetaBox — `current_user_can( 'edit_post', $post->ID )` before rendering.

### Data Source

Calls `Schema\Generator` directly in PHP — no REST round-trip:

```php
$generator = new Schema\Generator();
$article   = $generator->generate_article_schema( $post );
$faqpage   = $generator->generate_faq_schema( $post );
$detected  = $generator->detect_existing_types( $post );
```

This is the same call `SchemaController::get_schema()` makes. Calling it directly avoids an internal HTTP request and is ~3× faster on shared hosting.

### Render Output

**Article section (always shown):**
- Header: "Article Schema"
- "Already detected" badge if `'Article'` is in `$detected` (button disabled)
- Otherwise: "Copy Article schema" button
- No JSON-LD preview pane — too wide for the sidebar. Label + button is sufficient; the user doesn't need to read the schema to copy it.

**FAQPage section:**
- Header: "FAQPage Schema"
- If `$faqpage` is not null: "Already detected" badge OR "Copy FAQPage schema" button
- If `$faqpage` is null: "No FAQ content detected (need ≥ 2 Q&A pairs)" — matches Gutenberg panel copy exactly

**Other detected types (read-only):**
- If `$detected` contains types other than Article/FAQPage: "X schema detected — more types coming soon" — matches Gutenberg panel behavior

### Copy Action (X12 — advisory language, clipboard-only)

Clipboard-only for all non-Gutenberg surfaces. No TinyMCE injection. Rationale (from design session):

- TinyMCE does not render `<script>` tags in visual mode — user clicks Insert, sees no change, assumes it failed or is alarmed.
- Elementor/Divi don't use TinyMCE in the same way — silent injection into the wrong buffer is worse than no injection.
- The cautious-marketer persona needs to feel in control of every content change. Clipboard keeps the user as the actor.

Button behavior (inline JS):

1. Click → `navigator.clipboard.writeText( jsonLdString )`
2. Button text changes to "✓ Copied to clipboard"
3. Inline note appears below button:
   > "Paste into a Custom HTML block (Gutenberg) or a Code/HTML widget in your page builder. The script will not be visible in the editor — that's correct behavior."
4. Note fades after 8 seconds (CSS transition via class toggle). Button reverts to original label after 3 seconds.

Note text is advisory (X12) — it explains what to do, not what the user must do.

### Styles

Inline `<style>` block in `inline_styles()`, scoped to `#citewp_aiso_schema`. Buttons use WordPress native `.button .button-secondary` classes — no custom button styles. Badges reuse `.citewp-aiso-score-badge` pattern for visual consistency with ScoreMetaBox.

---

## Plugin.php Wiring

Inside the `is_admin()` block, after existing modules:

```php
$this->modules['score_meta_box'] = new Admin\ScoreMetaBox();
$this->modules['score_meta_box']->register();

$this->modules['schema_meta_box'] = new Admin\SchemaMetaBox();
$this->modules['schema_meta_box']->register();
```

Two lines each, identical pattern to every other admin module.

---

## What Is NOT in Scope

- Custom post types: not in this session. `add_meta_box()` targets `['post', 'page']` only, matching `Repository::SCORABLE_TYPES`. CPT support deferred.
- JSON-LD preview pane in SchemaMetaBox: not needed. The copy button is the action; the user doesn't need to read the schema.
- Elementor/Divi/Beaver Builder SDK integrations: not needed. All major page builders render standard WP meta boxes on the edit screen.
- New JS bundle / `wp_enqueue_script` registration: explicitly out (Approach A decision).
- Recalculate in SchemaMetaBox: schema suggestions are generated fresh from post content on every render — no stale state, no recalculate button needed.

---

## Success Criteria

1. Cite Score meta box appears on post/page edit screen in Classic Editor, Elementor (WP editor view), Divi, and Gutenberg (in the meta box compatibility panel).
2. Score displays correctly from post meta — no REST call required for initial render.
3. Recalculate button fetches the REST endpoint, updates score in place without page reload, shows loading state.
4. Schema Suggestions meta box appears on same screens.
5. Article schema copy button copies valid JSON-LD to clipboard and shows confirmation note.
6. FAQPage section shows "No FAQ content" message when fewer than 2 Q&A pairs exist.
7. "Already detected" badges appear correctly when schema type is found in post content.
8. Both meta boxes are absent for users without `edit_post` capability.
9. `npm run build` still passes (no JS changes to sidebar).
10. No new PHP errors in `debug.log`.
