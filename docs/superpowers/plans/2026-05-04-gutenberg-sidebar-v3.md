# Gutenberg Sidebar v3 Polish — Session 22 Plan

**Date:** 2026-05-04  
**Session:** 22  
**Pipeline:** X13 (multi-file React + SCSS + PHP + npm build + code-reviewer)  
**Status:** Brainstorm approved — ready for subagent execution

---

## Scope Summary

Two Gutenberg-registered plugins in `src/sidebar/index.js` get v3 polish:

1. **`citewp-aiso-geo-score`** (`PluginSidebar`) — Cite Score display with category breakdown and Recalculate button
2. **`citewp-aiso-schema-suggestions`** (`PluginDocumentSettingPanel`) — Article and FAQPage schema suggestion rows

**Not in scope:** Engine.php, REST endpoints, PHP meta box, new schema types (FB29 build), Cite Bridges (FB30), new tab structure, gauge SVG render, per-signal recommendation copy, WP.org tasks, Cite Score badge rectangular shape.

---

## Design Decisions (locked in brainstorm)

### GAP A — Label renames
- `PluginSidebarMoreMenuItem` text: `"CiteWP GEO Score"` → `"Cite Score"`
- `PluginSidebar title` prop: `"CiteWP GEO Score"` → `"Cite Score"`
- `TotalScore` inline label: `"GEO Score"` → `"Cite Score"`
- Plugin slug `citewp-aiso-geo-score` and `registerPlugin` name **stay unchanged** — changing them breaks `PluginSidebarMoreMenuItem`'s `target` prop and user-pinned sidebar state

### GAP E — Sidebar icon
- Replace `chartBar` (from `@wordpress/icons`) with a `CiteWPIcon` React component
- Source: `Brain/brand/logos/icon.svg` — `viewBox="0 0 512 512"`, Citrine (`#E8D400`) rect background, `[A]` text in `system-ui, -apple-system, sans-serif` at `font-weight="800"`, `fill="#0C0C0D"`. Plus Jakarta Sans was dropped from the brand system in P37 (Session 14); the sidebar icon uses the system font fallback for portability — the bracket + Citrine background + letter shape are the recognisable identity, not the specific typeface.
- Strip all SMIL: remove `<animate>` on rect, remove `@keyframes mark-breathe` CSS animation, remove `@import` Google Fonts
- Result: static SVG React component, inlined at top of `src/sidebar/index.js`
- Passed as `icon={ <CiteWPIcon /> }` to `registerPlugin` and `PluginSidebarMoreMenuItem`

### GAP F — Score color ramp
- **`GRADE_COLORS` constant deleted from JS entirely**
- P38 hex values live exclusively in `style.scss` via `$citewp-score-*` Sass variables → CSS modifier classes
- Grade thresholds verified against `ScoreResult.php::compute_total()` — **80 / 60 / 40** (not 90/70/50):
  ```php
  $this->total >= 80 => 'green'
  $this->total >= 60 => 'yellow'
  $this->total >= 40 => 'orange'
  default            => 'red'
  ```
- JS retains the `grade` string from the API response (`green / yellow / orange / red`) for class name construction only

### GAP D — Schema panel pill
- "Already detected" pill aligns to v3 grade badge pattern: tint of `$citewp-score-green` background, `$citewp-score-green` text, `border-radius: 9999px`
- Class: `.citewp-aiso-sidebar-schema-row__pill`

### Inline styles — modifier class strategy
- **All structural inline styles** (`style={{ ... }}`) replaced by `className` strings
- **Dynamic color values** use CSS modifier classes — no inline hex in JS:
  - Grade color: `citewp-aiso-sidebar-score__value--{grade}` (four rules in SCSS)
  - Category fill color: `citewp-aiso-sidebar-category__fill--{grade}`
  - Signal status color: `citewp-aiso-sidebar-signal__icon--{status}` (pass / partial / fail)
  - Recommendation border-left: `citewp-aiso-sidebar-signal__rec--{status}`
- `STATUS_ICONS` constant **survives** (accessibility: characters stay in DOM, not CSS `::before`)
  - Render: `<span className={`citewp-aiso-sidebar-signal__icon citewp-aiso-sidebar-signal__icon--${status}`}>{STATUS_ICONS[status]}</span>`
  - Color controlled by modifier class only

### FB29 — Schema panel refactor (option a)
- Replace two hardcoded `SchemaTypeRow` elements with `SCHEMA_TYPES.map()`
- Data shape (user-specified):
  ```js
  const SCHEMA_TYPES = [
    {
      key: 'article',
      label: 'Article',
      variants: ['Article', 'NewsArticle', 'BlogPosting'],
      emptyMessage: null,
    },
    {
      key: 'faqpage',
      label: 'FAQPage',
      variants: ['FAQPage'],
      emptyMessage: 'No FAQ content detected (need ≥ 2 Q&A pairs)',
    },
  ];
  ```
- Detection per row: `schema.detected.some(t => type.variants.includes(t))`
- REST access: `schema[type.key]` — keys `schema.article` and `schema.faqpage` **unchanged**. No REST contract change.
- `otherDetected` block: after the `.map()`, collect `detected` types not found in any `SCHEMA_TYPES.variants` entry → render as a single generic line: `"Other detected types: X, Y — more types coming soon"`. Per-type rows not needed for this trailing block.

---

## Files Changed

| File | Action | Notes |
|------|--------|-------|
| `src/sidebar/index.js` | Modify | Label renames, CiteWPIcon, GRADE_COLORS deletion, class names, SCHEMA_TYPES |
| `src/sidebar/style.scss` | **Create** | Sass tokens, @font-face, all class definitions |
| `includes/Admin/EditorAssets.php` | Modify | Add `wp_enqueue_style` with `filemtime` version |
| `build/style-index.css` | Generated | Emitted by `npm run build`; stage as new untracked file |

**`.gitignore` status:** `build/` is fully tracked (confirmed — no exclusion in `.gitignore`). `build/style-index.css` stages as a new file after first build. No `.gitignore` edit needed.

---

## `src/sidebar/style.scss` — Full Structure

```scss
// CiteWP Sidebar — v3 palette + typography
// SNAPSHOT of P38 brand system (2026-05-04).
// Score color tokens mirror $citewp-score-* — update manually if P38 changes.
// JetBrains Mono registered here because enqueue_block_editor_assets is a
// separate hook from admin_enqueue_scripts; admin CSS does not reach the
// editor sidebar chrome.

@font-face {
  font-family: 'JetBrains Mono';
  font-style: normal;
  font-weight: 400;
  font-display: swap;
  src: url('../admin/fonts/jetbrains-mono-400.woff2') format('woff2');
}

// Score-band tokens (P38 canonical)
$citewp-score-green:  #00A32A;
$citewp-score-yellow: #DBA617;
$citewp-score-orange: #D63638;
$citewp-score-red:    #8C1B1B;

// Text tokens (P38 paper-surface)
$citewp-text-body:  #0C0C0D;   // Obsidian
$citewp-text-muted: #64748B;   // P38 canonical muted

// Surface tokens
$citewp-border:       #e5e7eb;
$citewp-border-faint: #f3f4f6;
$citewp-bg-subtle:    #f9fafb;
```

**Font note:** Only `admin/fonts/jetbrains-mono-400.woff2` exists on disk. The @font-face declares weight 400; bold rendering at heavier weights uses browser synthesis (same behaviour as the existing admin CSS). Fallback: `'Courier New', monospace`.

**Relative path:** `style.scss` compiles to `build/style-index.css`. From there, `../admin/fonts/jetbrains-mono-400.woff2` resolves correctly. Lock during writing-plans by verifying the actual emitted file path.

---

## Class Naming Map (BEM: `citewp-aiso-sidebar-{block}__{element}--{modifier}`)

### Score block
| Element | Class |
|---------|-------|
| Score wrapper | `.citewp-aiso-sidebar-score` |
| Score number | `.citewp-aiso-sidebar-score__value` |
| Grade modifier | `.citewp-aiso-sidebar-score__value--green/yellow/orange/red` |
| `/100` denominator | `.citewp-aiso-sidebar-score__denom` |
| "Cite Score" label | `.citewp-aiso-sidebar-score__label` |
| Loading wrapper | `.citewp-aiso-sidebar-loading` |
| Error wrapper | `.citewp-aiso-sidebar-error` |
| Recalculate hint | `.citewp-aiso-sidebar-recalc-hint` |

### Category block
| Element | Class |
|---------|-------|
| Category wrapper | `.citewp-aiso-sidebar-category` |
| Toggle button | `.citewp-aiso-sidebar-category__toggle` |
| Score fraction | `.citewp-aiso-sidebar-category__score` |
| Progress bar track | `.citewp-aiso-sidebar-category__bar` |
| Progress bar fill | `.citewp-aiso-sidebar-category__fill` |
| Grade modifier | `.citewp-aiso-sidebar-category__fill--green/yellow/orange/red` |
| Signals container | `.citewp-aiso-sidebar-category__signals` |

### Signal block
| Element | Class |
|---------|-------|
| Signal wrapper | `.citewp-aiso-sidebar-signal` |
| Header row | `.citewp-aiso-sidebar-signal__header` |
| Status icon span | `.citewp-aiso-sidebar-signal__icon` |
| Status modifier | `.citewp-aiso-sidebar-signal__icon--pass/partial/fail` |
| Signal label | `.citewp-aiso-sidebar-signal__label` |
| Score fraction | `.citewp-aiso-sidebar-signal__score` |
| Message line | `.citewp-aiso-sidebar-signal__message` |
| Recommendation block | `.citewp-aiso-sidebar-signal__rec` |
| Rec status modifier | `.citewp-aiso-sidebar-signal__rec--pass/partial/fail` |

### Schema block
| Element | Class |
|---------|-------|
| Schema row wrapper | `.citewp-aiso-sidebar-schema-row` |
| Row label | `.citewp-aiso-sidebar-schema-row__label` |
| "Already detected" pill | `.citewp-aiso-sidebar-schema-row__pill` |
| Empty message | `.citewp-aiso-sidebar-schema-row__empty` |
| "Other detected" line | `.citewp-aiso-sidebar-schema-other` |

---

## `EditorAssets.php` — Enqueue Addition

```php
// Resolve actual CSS filename from build output (confirmed during writing-plans)
$css_path    = CITEWP_AISO_PLUGIN_DIR . 'build/{ACTUAL_FILENAME}';
$css_version = file_exists( $css_path ) ? filemtime( $css_path ) : CITEWP_AISO_VERSION;

wp_enqueue_style(
    'citewp-aiso-sidebar',
    CITEWP_AISO_PLUGIN_URL . 'build/{ACTUAL_FILENAME}',
    [],
    $css_version
);
```

- `{ACTUAL_FILENAME}` locked from observed `npm run build` output during writing-plans — not assumed
- Shared `file_exists( $asset_file )` guard at top of `enqueue()` covers both JS and CSS — no second guard needed
- `filemtime()` busts cache on every CSS rebuild regardless of JS change; OS-cached, microsecond-cheap
- Style handle `citewp-aiso-sidebar` matches script handle — WP script/style registries are separate

---

## Subagent Execution Order (strictly sequential)

**SA1 must complete before SA2 starts** — SA1 produces the class names SA2's SCSS consumes. Parallel execution risks class-name drift between JS and CSS.

### Subagent 1 — `src/sidebar/index.js` refactor
1. Add `CiteWPIcon` component (static SVG, SMIL stripped)
2. Delete `GRADE_COLORS` constant
3. Delete `ARTICLE_VARIANTS` constant (absorbed into `SCHEMA_TYPES[0].variants`)
4. Label renames (three strings → "Cite Score")
5. Replace all `icon={ chartBar }` with `icon={ <CiteWPIcon /> }`
6. Replace all inline `style={{ ... }}` with `className` strings per naming map
7. Apply grade/status modifier classes (`--${grade}`, `--${status}`):
   - `TotalScore`: grade comes from `score.grade` API field — use directly
   - `CategoryRow`: compute `grade` string from percentage (`pct >= 80 → 'green'`, `>= 60 → 'yellow'`, `>= 40 → 'orange'`, else `'red'`) replacing current `color` hex computation. Same thresholds as before, new output type. Add an inline code comment: `// Category bars use the top-line score thresholds (80/60/40) by visual convention; per-category thresholds are not formally defined in SCORING-RUBRIC.md.`
   - `SignalRow`: `signal.status` is already `'pass' | 'partial' | 'fail'` — use directly
8. Keep `STATUS_ICONS` constant; render characters in DOM with modifier class for color
9. Replace hardcoded `SchemaTypeRow` elements with `SCHEMA_TYPES.map()`
10. `otherDetected` trailing render: before writing this block, **read `includes/Schema/Generator.php`** (specifically whatever method detects existing schema types — look for `detect_existing_types` or equivalent). Confirm whether `'Question'` is emitted as a standalone `@type` by the generator or only as a child node of `FAQPage`. If it is NOT standalone (current assumption — it appears alongside `FAQPage` in the API response as a child type, not an independent detection): proceed with `(schema.detected || []).filter(t => !allKnownVariants.includes(t) && t !== 'Question')` where `allKnownVariants = SCHEMA_TYPES.flatMap(t => t.variants)`. Add an inline comment: `// 'Question' is a child node type of FAQPage schema, not a standalone @type from the generator — excluded to avoid double-counting alongside FAQPage.` If the assumption is WRONG and `'Question'` is a legitimate standalone detection: adjust the filter and document accordingly. Do not silently drop it.
11. Verify: no `style={{` props remain (grep); no "GEO Score" strings remain (grep)

### Subagent 2 — `style.scss` + `EditorAssets.php` + build
1. Create `src/sidebar/style.scss` with preamble (header comment, @font-face, Sass variables)
2. Write all class definitions per naming map using SA1's output as source of truth
3. Write grade modifier rules (4 per grade block: `.--green`, `.--yellow`, `.--orange`, `.--red`)
4. Write status modifier rules (pass=green, partial=yellow, fail=red) for signal icon + rec border
5. Write schema pill styles aligned to v3 grade badge pattern
6. Run `npm run build` — observe actual CSS output filename in `build/`
7. Update `EditorAssets.php`: add `wp_enqueue_style` with observed filename and `filemtime` version
8. `git add build/{observed-filename}`
9. Verify: `build/style-index.css` (or observed name) exists and is non-empty

---

## Verification Checklist

- [ ] No `style={{ ... }}` props remain in `src/sidebar/index.js`
- [ ] No "GEO Score" string remains in any visible UI label
- [ ] `GRADE_COLORS` constant deleted from JS
- [ ] `CiteWPIcon` component renders `[A]` SVG with no SMIL attributes
- [ ] `SCHEMA_TYPES` array drives schema panel rendering; REST keys `schema.article` / `schema.faqpage` unchanged
- [ ] `src/sidebar/style.scss` exists with P38 Sass variables and snapshot header comment
- [ ] `EditorAssets.php` enqueues both JS (`wp_enqueue_script`) and CSS (`wp_enqueue_style`) with `filemtime` version on CSS
- [ ] `npm run build` succeeds with no new warnings
- [ ] Block editor: sidebar opens → title "Cite Score" → score renders in correct grade color → category bars render → signal rows expand → Recalculate works
- [ ] Block editor: Document Settings → Schema Suggestions → rows render with v3 styling → "Already detected" pill matches v3 green badge → Insert adds Custom HTML block
- [ ] Save post → score auto-recalculates (regression check)
- [ ] No PHP errors in LocalWP `debug.log`
- [ ] No console errors in browser devtools during sidebar/schema panel interaction

---

## Carryover / Deferrals

- EditorPanel PHP meta box v3 polish → Session 23
- New schema types FB29 (HowTo, Product, LocalBusiness) → FB29 build session
- Cite Bridges tab FB30 → FB30 build session
- Global meta defaults FB34 → FB34 build session
- Score gauge SVG render in sidebar → feature work, not polish
- Cite Score badge rectangular shape → separate deferred task
- UI-DESIGN-SYSTEM.md "Block Editor Sidebar" component entry → Brain consolidation session (Desktop, not Code)
