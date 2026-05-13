# CiteWP — Session Log

> Per-session record of what shipped, what broke, what carried over, and what's next.
> Follow the "Session Protocol" section of `Desktop\CiteWP\Brain\00-CITEWP-MASTER.md` for entry format.
> Most recent session at the top.

---

## Session 28 (Polish Fix 2) — Chart Height + Table Visibility + Title Clamp ✅

**Date:** 2026-05-13

### Deliverable

Follow-up CSS bug fixes after Polish Pass introduced a regression: the chart panel inherited `height: 100%` from the `.citewp-aiso-cs-panel` base rule, causing it to consume the entire left column height and visually crush the Post & Page Cite Scores table. No version bump — all fixes are part of the in-progress v0.7.4 polish pass. No PHP logic or scoring changes.

### What shipped

1. **Chart panel height fix (`admin/css/citewp-aiso-admin.css`):**
   - Root cause: `.citewp-aiso-cs-panel { height: 100% }` applied to the chart wrapper div, which resolved to full left-column height in a flex column context
   - First attempt (`90e03e4`): added `height: auto` to `.citewp-aiso-cite-score-page__left-chart` — failed because `.citewp-aiso-cs-panel` comes later in the sheet at equal specificity
   - Fix (`e989c8a`): scoped rule `.citewp-aiso-cite-score-page__left > .citewp-aiso-cite-score-page__left-chart { height: auto }` added alongside the existing right-column override — wins on specificity (0,2,0 vs 0,1,0)
   - `min-height: 280px` also removed from `.citewp-aiso-history-panel` (was wrong target from original brief, harmless but clean to remove)

2. **Chart-to-table gap (`admin/css/citewp-aiso-admin.css`):**
   - Removed `margin-top: var(--sp-6)` from `.citewp-aiso-cite-score-page__left-chart` — flex `gap: 18px` on the left column is sufficient; extra margin was making the gap visually taller than other element gaps

3. **Post/Page title clamp (`admin/css/citewp-aiso-admin.css`, `includes/Admin/Menu.php`):**
   - Title link in Post & Page table clamped to 2 lines with `-webkit-line-clamp: 2`
   - `title` attribute added to the link so full title shows on hover (using `esc_attr( get_the_title() )`)

### Files modified

- `admin/css/citewp-aiso-admin.css`
- `includes/Admin/Menu.php`

### Commits

- `4a663df` fix: contain chart panel + table panel heights; nudge Top Crawler value font
- `90e03e4` fix: override height:100% on chart panel — restores table visibility (first attempt)
- `e989c8a` fix: scoped height:auto on left-chart panel — wins over base cs-panel rule
- `9545753` style: remove extra margin-top from chart panel — matches flex gap above
- `223d537` style: clamp post/page title to 3 lines with ellipsis in Cite Score table
- `823bc66` fix: truncate post titles to 2 lines + add full-title hover

All commits pushed to origin/main ✅

### Decisions made

None — pure CSS bug-fix and polish pass.

### Verified

- debug.log: clean (no file present)
- npm build: not required (CSS + PHP attribute only)
- Manual browser test: pending (see carryover)

### Carryover into S29

- **Manual smoke test** (citewp-dev.local): verify table shows all rows, chart sizes to content, Pro Tip below chart, title clamp + hover working, Top Crawler card balanced
- **S26 Bug B live verification**: upload v0.7.4 to citewp.com, verify schema signal 3/6 on Rank Math page, 6/6 after Article JSON-LD insert
- **S27 smoke test**: toggle post on/off, verify Dashboard + Cite Score KPI values update
- **WP.org approval check** (hello@citewp.com)

### Next session focus

S29 — run carryover smoke tests, then next backlog priority (FB38 Dashboard Cite Score restructure, FB30 Cite Bridges, or FB39 Publish block injection per master file)

---

## Session 28 (Polish Pass) — Post-Review Polish: Chart Layout + Pill Alignment + Long Bot Name + v0.7.4 ✅

**Date:** 2026-05-13

### Deliverable

Four targeted fixes discovered during post-session review of S28's output. Light-touch pipeline (plan → single subagent → code review → push). No new features; no Engine.php changes. Version bump 0.7.3 → 0.7.4.

### What shipped

1. **Fix A — Top Crawler card long-name containment (`Menu.php`, admin CSS):**
   - Scoped `.citewp-aiso-kpi-card--top-crawler` modifier: value text at 22px, `-webkit-line-clamp: 2`, `word-break: break-word`
   - KPI row flex parity: `min-height: 160px; display: flex; flex-direction: column` on all KPI cards in row so varying bot name lengths don't break height alignment

2. **Fix B — Chart into left column; Pro Tip to true footer (`Menu.php`, admin CSS):**
   - Removed `citewp-aiso-cite-score-page__chart-fullwidth` full-width wrapper (S28 T5 error)
   - Chart now lives inside `.citewp-aiso-cite-score-page__left`, after Post table, in new wrapper `.citewp-aiso-cite-score-page__left-chart`
   - Pro Tip card moved outside `.citewp-aiso-cite-score-page__body` entirely (true footer, full-width)

3. **Fix C — Post-type pill right-justified in Title column (`Menu.php`, admin CSS):**
   - Removed inner `<span>` wrapper from `citewp-aiso-cs-post-cell` TD
   - TD gets `display: flex; justify-content: space-between; gap: var(--sp-3)`; title link `flex: 1 1 auto; min-width: 0`; pill `flex: 0 0 auto`
   - Pill no longer floats inline with title text — always pinned to right edge of cell

4. **Fix D — Version bump:**
   - `0.7.3` → `0.7.4` in plugin header, `CITEWP_AISO_VERSION`, readme.txt Stable tag
   - `= 0.7.4 =` changelog entry added

### Files modified

- `includes/Admin/Menu.php`
- `admin/css/citewp-aiso-admin.css`
- `ai-search-optimizer.php`
- `readme.txt`

### Commits

- `e3c1573` fix: contain Top Crawler card value text + align KPI card icons
- `7d4cbb7` fix: move Cite Score Over Time chart into left column + Pro Tip to true footer
- `4d504b2` fix: right-justify post-type pill in Post & Page table
- `50351e0` chore: version bump 0.7.3 → 0.7.4 + readme changelog

All 4 commits pushed to origin/main ✅

### Carryover into S29

- Same carryover as S28 main (smoke tests, S26 Bug B live verify, WP.org check) — unchanged

---

## Session 28 — Cite Score Page Polish + AI Recommendations Routing Fix + Rail Densification ✅

**Date:** 2026-05-13

### Deliverable

Seven UX polish items discovered from a full day of dogfooding citewp.com. No scoring math touched (Engine.php untouched, RecommendationMapper extended with label/copy/anchor only). Full X13 pipeline: subagent-driven execution (7 tasks, 10 commits) → spec + quality review per task → final overall review → push. Version bump 0.7.2 → 0.7.3.

### What shipped

1. **T1 — Top Crawler KPI card (`Menu.php`, `DashboardData.php`):**
   - Replaced duplicative Avg Cite Score Card 1 (same number as the gauge directly below) with a Top Crawler card
   - Shows bot display name, visit count "N visits in last 7 days", trend (↑ ↓ →), empty state
   - Data from `DashboardData::get_top_crawlers(1)`; footer links to `#crawler-logs`
   - Decision: P50

2. **T2 — Label fix (`Menu.php`):**
   - "Posts Optimized" → "Posts/Pages Optimized" (KPI Card 2 title)
   - "across N posts needing attention" → "across N items needing attention" (Card 3 caption)

3. **T3 — Post & Page Cite Scores table (`Menu.php`, `admin/css/citewp-aiso-admin.css`):**
   - Section renamed "Post & Page Cite Scores" (was "Post-Level Cite Scores")
   - Column header "Post" → "Title"
   - Post-type pill added to each row (Post=blue/citewp-blue-tint, Page=purple/citewp-purple-tint)

4. **T4 — AI Recommendations affordances + routing fix (`Menu.php`, `RecommendationMapper.php`, `RecommendationFilter.php`, admin CSS):**
   - Bug fix: `$view_url` hardcoded `post_type=post` → now uses `RecommendationFilter::dominant_post_type($signal_id)` — routes to Pages list when affected content is pages-dominant
   - New `dominant_post_type()` method: counts post/page among affected IDs, ties default to 'post'
   - "Learn More →" inline link added to each rec card sub-copy, linking to `citewp.com/cite-score#anchor`
   - All 17 MAP entries in `RecommendationMapper` got `'anchor'` keys (full rubric anchor map)
   - Admin Dashboard rec button renamed "View AI Recommendations →"
   - Decision: P51

5. **T5 — Chart relocation (`Menu.php`, admin CSS):**
   - Cite Score Over Time chart cut from right column, placed full-width after two-column body, before Pro Tip
   - New wrapper: `citewp-aiso-cite-score-page__chart-fullwidth` with `grid-column: 1 / -1`

6. **T6 — Left rail densification (admin CSS):**
   - `.citewp-aiso-rail__item` padding: 10px → 8px vertical
   - `.citewp-aiso-rail__item-desc` line-height: 1.3 → 1.25
   - Improves usability at 100% browser zoom

7. **T7 — Version bump + readme:**
   - `0.7.2` → `0.7.3` in plugin header + constant + readme.txt Stable tag
   - `= 0.7.3 =` changelog entry added

### Files modified

- `includes/Admin/Menu.php`
- `includes/Admin/RecommendationMapper.php`
- `includes/Admin/RecommendationFilter.php`
- `admin/css/citewp-aiso-admin.css`
- `ai-search-optimizer.php`
- `readme.txt`

### npm build

✅ Clean (`webpack 5.x compiled successfully`)

### Decisions made

- P49 (carried from S27 — was missing from DECISIONS.md): aggregate metrics exclude opted-out posts
- P50: Cite Score page Card 1 surfaces Top Crawler instead of duplicating gauge
- P51: AI Recommendations card affordances pattern + post-type-aware routing

### Verified

- All 10 commits pushed to origin/main ✅
- npm build clean ✅
- `debug.log`: pending manual check on citewp-dev.local
- Manual smoke test: pending (see carryover)

### Carryover into S29

- **Manual smoke test** (citewp-dev.local): open Cite Score page, verify T1–T6 render correctly at 100% zoom; click a rec "View N posts" for a pages-only signal, verify `post_type=page` in URL; click "Learn More →", verify opens `citewp.com/cite-score#anchor` in new tab
- **S26 Bug B live verification** (still outstanding): upload v0.7.3 to citewp.com, verify schema signal = 3/6 on Rank Math page, 6/6 after Article JSON-LD insert
- **S27 manual smoke test** (still outstanding): toggle a post on/off, verify Dashboard + Cite Score KPI values update
- **WP.org approval check** (hello@citewp.com)
- **P49 DECISIONS.md entry** — ✅ resolved during S28 close (Brain repo commit `16f23ae`)

### Next session focus

S29 — run carryover smoke tests, then next backlog priority (FB38 Dashboard restructure, FB30 Cite Bridges, or FB39 Publish block injection per master file)

---

## Session 27 — Exclude Opted-Out Posts from Aggregate Cite Score Metrics ✅

**Date:** 2026-05-13

### Deliverable

Exclude posts where `_citewp_aiso_exclude_from_llms = '1'` from every aggregate Cite Score metric on the admin UI. Per-post surfaces (post list column, Cite Score page per-post table, Gutenberg sidebar, EditorPanel meta box) are unchanged. Version bump 0.7.1 → 0.7.2. Full X13 pipeline: plan → subagent-driven execution (5 tasks, 8 commits) → spec + quality review per task → final overall review → push.

**What shipped:**

1. **`includes/Admin/DashboardData.php` (T1):**
   - `get_average_score()`: LEFT JOIN exclusion wrapped in subquery alias to prevent AVG fanout
   - `get_issue_count()`: LEFT JOIN exclusion
   - `get_lowest_scoring_posts()`: nested AND/OR `meta_query` exclusion
   - `get_excluded_count()` (new): COUNT of published posts/pages opted out
   - `get_scored_count()` (new): COUNT(DISTINCT pm.post_id) of scored non-opted-out published posts

2. **`includes/Admin/Menu.php` (T2, T4):**
   - `render_cite_score_panel()`: `$scored_ids` WP_Query now uses nested AND/OR `meta_query` (EXISTS + NOT EXISTS / != '1') with `post_status => 'publish'`; sub-line replaced with two-span pattern (pages included + conditional exclusion note with tooltip)
   - `render_dashboard_panel()`: Dashboard KPI Card 1 gets same two-span sub-line
   - `$excluded_count` sourced from named `$data = new DashboardData()` instance (not inline anonymous instantiation)

3. **`includes/Scoring/ScoreHistory.php` (T3):**
   - `compute_current_average()`: same LEFT JOIN + subquery pattern as `DashboardData::get_average_score()`

4. **`admin/css/citewp-aiso-admin.css` (T4):**
   - `.citewp-aiso-kpi-card__exclusion-note`: `display: block`, `color: var(--citewp-text-muted)`, `margin-top: 2px`, `cursor: help` (font-size inherits from parent `.citewp-aiso-kpi-card__sub`)

5. **`ai-search-optimizer.php` + `readme.txt` (T5):**
   - Version bumped 0.7.1 → 0.7.2
   - `= 0.7.2 =` changelog entry in readme.txt

**Key bugs caught and fixed during review:**
- AVG fanout risk (two postmeta JOINs) → subquery wrapper on all AVG queries
- `meta_key` + OR `meta_query` short-circuit → nested AND/OR `meta_query` only
- Dashboard KPI tooltip missing second sentence → fixed
- `post_status` mismatch (draft included in `$scored_ids`, excluded from SQL) → restricted to `publish`
- Redundant `font-size` on exclusion-note CSS child → removed

**Files modified:**
- `includes/Admin/DashboardData.php`
- `includes/Admin/Menu.php`
- `includes/Scoring/ScoreHistory.php`
- `admin/css/citewp-aiso-admin.css`
- `ai-search-optimizer.php`
- `readme.txt`

**npm build:** ✅ Clean (not rebuilt — no JS changes this session)

**Decisions made:** P49 (aggregate metrics exclude opted-out posts — see DECISIONS.md)

**Verified:**
- All 8 commits pushed to origin/main ✅
- `debug.log`: pending manual check
- Manual smoke test: pending (5-step toggle test + SQL spot-check + cron verification)

**Carryover into next session:**
- Manual smoke test on citewp-dev.local: toggle a post on/off, verify Dashboard + Cite Score KPI values update, check SQL spot-check query, run WP cron manually, check debug.log
- S26 live verification: 10 FAQ fixture test + Bug B on citewp.com (upload v0.7.2, verify schema signal = 3/6 partial on Rank Math page)
- WP.org approval check (hello@citewp.com)

**Next session focus:** S26 + S27 live verification, then next backlog priority per master file.

---

## Session 26 — FAQ Detection v2 + Schema Signal Grading + 3-State Panel Message ✅

**Date:** 2026-05-12

### Deliverable

Primary: Rewrote `extract_faq_pairs()` in `includes/Schema/Generator.php` using DOMDocument to detect FAQ content across 6 page-builder accordion patterns (Kadence, Elementor, Divi, Beaver Builder, Bricks, Spectra) plus HTML5 `<details>/<summary>` and standard h2/h3 headings. X15 extensibility filter `citewp_aiso/schema/faq_pairs` registered. A new public `count_faq_pairs()` method feeds a new `faq_count` field in the schema REST response. The `SchemaSuggestions` panel now renders one of 3 messages: "No FAQ content detected" (0 pairs), "Only 1 question/answer pair detected. FAQPage schema requires at least 2 pairs." (1 pair), or "FAQ detected: N question/answer pairs." with Insert button (≥2 pairs).

Secondary: Bug B fix — `Engine::check_schema()` graded 6/3/0 instead of binary 6/0. Inline JSON-LD=6pts, active SEO plugin with no inline schema=3pts (partial), no schema at all=0pts. SEO plugins covered: Yoast (`WPSEO_VERSION`), Rank Math (`RANK_MATH_VERSION`), AIOSEO (`AIOSEO_VERSION`/`AIOSEO_VERSION_LITE`), SEOPress (`SEOPRESS_VERSION`/`SEOPRESS_PRO_VERSION`). `RecommendationMapper` schema copy updated to cover partial state.

Version bump: 0.7.0 → 0.7.1. Full X13 pipeline: fixtures → plan → subagent-driven execution (6 tasks, 8 commits) → spec + quality review per task → push.

**What shipped:**

1. **10 FAQ detection fixtures (`tests/fixtures/faq-detection/`):**
   - `01-heading-basic.html` — h2/h3+p pattern (3 pairs)
   - `02-details-summary.html` — HTML5 `<details>/<summary>` (2 pairs)
   - `03-kadence-accordion.html` — Kadence Blocks (2 pairs)
   - `04-elementor-accordion.html` — Elementor WAI-ARIA (2 pairs)
   - `05-divi-accordion.html` — Divi h5 role=button (2 pairs)
   - `06-beaver-accordion.html` — Beaver Builder aria-controls (2 pairs)
   - `07-bricks-accordion.html` — Bricks role=button wrapper (2 pairs)
   - `08-spectra-faq.html` — Spectra UAGB FAQ block (2 pairs)
   - `09-one-pair-only.html` — edge case: 1 pair, no FAQPage schema generated
   - `10-no-faq-false-positive-guard.html` — topic headings, no question phrasing (0 pairs)

2. **`includes/Schema/Generator.php` — DOMDocument rewrite:**
   - `extract_faq_pairs()` replaced with 4-pattern DOMDocument implementation (h2/h3/h4+p, `<details>/<summary>`, WAI-ARIA `role="button"`, CSS-class fallback)
   - New private `get_faq_pairs(\WP_Post)` — calls extract + applies `citewp_aiso/schema/faq_pairs` filter (X15)
   - New public `count_faq_pairs(\WP_Post)` — returns pair count for REST response
   - `$analysis_cache` instance memoization (prevents double content-parse per request)
   - `$pairs_cache` instance memoization (perf fix caught at T2→T3 review hook — count + generate were double-running DOMDocument)
   - Dead properties `$cached_post_id`, `$cached_analysis` removed (superseded by `$analysis_cache`)
   - 4 new private helpers: `first_p_after()`, `details_body()`, `aria_answer()`, `has_ancestor_tag()`

3. **`includes/Rest/SchemaController.php`:**
   - `faq_count` field added to schema REST response (from `$this->generator->count_faq_pairs($post)`)

4. **`src/sidebar/index.js` — 3-state FAQ message:**
   - `SchemaSuggestions` computes dynamic `emptyMsg` and `statusText` from `schema.faq_count ?? 0`
   - 0 pairs: "No FAQ content detected on this page."
   - 1 pair: "Only 1 question/answer pair detected. FAQPage schema requires at least 2 pairs."
   - ≥2 pairs: `statusText` = "FAQ detected: N question/answer pairs." (alongside Insert button)
   - `SchemaTypeRow` updated with `statusText` prop + `__label-group` wrapper div

5. **`src/sidebar/style.scss`:**
   - `.citewp-aiso-sidebar-schema-row__label-group` (flex column)
   - `.citewp-aiso-sidebar-schema-row__status-text` (11px, `#757575`)

6. **`includes/Scoring/Engine.php`:**
   - `check_schema()` rewritten: 3-state graded signal 6/3/0 (P47)
   - `$has_seo_plugin` covers: `WPSEO_VERSION`, `RANK_MATH_VERSION`, `AIOSEO_VERSION`, `AIOSEO_VERSION_LITE`, `SEOPRESS_VERSION`, `SEOPRESS_PRO_VERSION`

7. **`includes/Admin/RecommendationMapper.php`:**
   - `schema` copy updated to cover partial state ("verify your SEO plugin is configured to output schema for this post type")

8. **`ai-search-optimizer.php` + `readme.txt`:**
   - Version bumped 0.7.0 → 0.7.1
   - `= 0.7.1 =` changelog entry added to readme.txt

**Files created:**
- `tests/fixtures/faq-detection/` (10 HTML fixture files)

**Files modified:**
- `includes/Schema/Generator.php` — DOMDocument rewrite, memoization, new public/private methods
- `includes/Rest/SchemaController.php` — `faq_count` REST field
- `src/sidebar/index.js` — 3-state FAQ message, `statusText` prop, `label-group` wrapper
- `src/sidebar/style.scss` — `.citewp-aiso-sidebar-schema-row__status-text` rule
- `includes/Scoring/Engine.php` — 6/3/0 graded `check_schema()` + SEOPress constants
- `includes/Admin/RecommendationMapper.php` — schema partial-state copy
- `ai-search-optimizer.php` — version 0.7.1
- `readme.txt` — 0.7.1 changelog entry

**npm build:** ✅ Clean — `webpack compiled successfully` (no new warnings; 3 pre-existing chartLine icon warnings are known).

**Decisions made:** P47 (schema signal graded 6/3/0 — see DECISIONS.md). P48 (lazy migration: no bulk recalculate script — see DECISIONS.md).

**Verified:**
- `npm run build`: ✅ Clean
- `debug.log`: not found (no PHP errors) ✅
- All 8 commits pushed to origin/main ✅
- Manual fixture verification: pending (paste 10 HTML fixtures into test post, verify `faq_count` + panel state per plan checklist)
- Bug B live verification on citewp.com: pending (package updated plugin, upload, verify schema signal = 3/6 partial on Rank Math page with no inline JSON-LD)

**Carryover into next session:**
- Manual fixture verification against plan Post-Task Verification Checklist (10 fixtures × expected `faq_count` + panel state)
- Bug B live verification: `.\package.ps1` → upload to citewp.com → confirm schema signal = 3/6 partial on page with Rank Math active + no inline schema; insert Article JSON-LD → recalculate → confirm jumps to 6/6

**Next session focus:** Complete S26 live verification (fixture test + Bug B), then FB38 (full Dashboard Cite Score restructure with category bars + Bot Visits) or next backlog priority per master file.

---

## Session 25 — llms.txt Per-Post Toggle + 100% Zoom Polish ✅

**Date:** 2026-05-11

**Context note:** Between Sessions 24 and 25, a parallel marketing/infrastructure session ran independently: Supabase project setup, Edge Function for waitlist capture, and citewp.com marketing site. Not tracked in this plugin log but part of the overall project timeline.

### Deliverable

Primary: A binary "Include in llms.txt" toggle exposed on every post/page in two surfaces: (A1) Classic Editor "Publishing Controls" meta box section, and (G2) Gutenberg "AI Visibility" `PluginDocumentSettingPanel`. Meta key: `_citewp_aiso_exclude_from_llms` ('1' = exclude, absent/'0' = include). Full X13 pipeline: planning → subagents → spec + quality review → X20 audit → browser verification → smoke test.

Secondary: 100% browser zoom polish across two surfaces — Cite Score page dial layout and Dashboard KPI row.

**What shipped:**

1. **Post meta registration (`Plugin.php`):**
   - `register_post_meta_fields()` called on `init`; registers `_citewp_aiso_exclude_from_llms` for `post` and `page` with `type: 'string'`, `show_in_rest: true`, `sanitize_callback: 'sanitize_text_field'`, `auth_callback` checks `edit_post` capability

2. **Cache flush on meta-only updates (`Cache.php`):**
   - Registered `updated_post_meta` + `added_post_meta` hooks → `on_meta_update()` method
   - Flushes `citewp_aiso_llms_short` / `_full` transients when `_citewp_aiso_exclude_from_llms` changes on a published post
   - Belt-and-suspenders alongside existing `on_save_post` (priority 10) flush

3. **Classic Editor "Publishing Controls" section (`EditorPanel.php`):**
   - `save_meta()` method: DOING_AUTOSAVE guard → nonce verify → capability check → `update_post_meta()`; hooked to `save_post` at priority 20
   - `render_publishing_controls()`: BEM wrapper `.citewp-aiso-pc`, pill-switch toggle (checkbox + `.citewp-aiso-pc__slider`), label + help text, nonce field; rendered inside `render_general_tab()` via `apply_filters( 'citewp_aiso/publishing_controls/items', [], $post )` for X15 extensibility
   - `register()`: added `save_post` (priority 20, 2 args) hook

4. **Gutenberg "AI Visibility" panel (`src/sidebar/index.js`):**
   - `AiVisibility` component: reads `_citewp_aiso_exclude_from_llms` via `getEditedPostAttribute('meta')`, drives `ToggleControl` `checked={isIncluded}`, dispatches `editPost({ meta: { ... } })` on change; uses `?? {}` for null-safe meta access
   - Registered via `registerPlugin('citewp-aiso-ai-visibility')` → `PluginDocumentSettingPanel`

5. **Publishing Controls CSS + orange token fix (Section 31, `admin/css/citewp-aiso-admin.css`):**
   - All `.citewp-aiso-pc*` BEM selectors: wrapper, header/title, row, label-wrap, label, help, pill-switch toggle + slider
   - `--citewp-score-orange: #E86612` (was `#D63638` — wrong red) in both `:root` and scoped `#citewp_aiso_editor_panel` blocks (P46)

6. **SCSS token alignment (`src/sidebar/style.scss`):**
   - `$citewp-score-orange: #E86612` (matched CSS token fix, P46)

7. **Cite Score page — dial layout polish (`admin/css/citewp-aiso-admin.css`):**
   - `.citewp-aiso-cs-score-wrap` dial column reduced from 240px to 160px; `@media (max-width: 1280px)` stacks dial/copy/button vertically
   - Fixes layout collapse at 100% browser zoom on 1440px viewport

8. **Dashboard Site Score Health card — KPI row coherence (`includes/Admin/Menu.php`, `includes/Admin/ScoreDial.php`, `admin/css/citewp-aiso-admin.css`):**
   - Replaced full-size dial + 2-column layout with standard KPI card pattern matching the 3 row siblings
   - Card 1 now: gauge icon orb (80px, purple tint) → hero number "45" (JetBrains Mono, grade-colored) → "Needs Improvement" grade label → "→ no recent changes" trend → "View Scores →" footer button
   - Fixed KPI row grid: `1.5fr 1fr 1fr 1fr` → `repeat(4, 1fr)` — all 4 cards equal 25% width and uniform height
   - Added `ScoreDial::grade_label()` and `render_mini()` static helpers (grade_label used; render_mini retained for future FB38 use)
   - Removed dead CSS: `.citewp-aiso-kpi-card--dial`, `__body--dial`, `__dial-wrap`, `.citewp-aiso-btn--dial-inline`

**Files created:** None.

**Files modified:**
- `includes/Plugin.php` — `register_post_meta_fields()` method + `init` hook
- `includes/Llms/Cache.php` — `on_meta_update()` + two new hook registrations
- `includes/Admin/EditorPanel.php` — `save_meta()`, `render_publishing_controls()`, updated `register()`
- `includes/Admin/ScoreDial.php` — added `grade_label()` + `render_mini()` static methods
- `includes/Admin/Menu.php` — Dashboard Card 1 restructured to KPI pattern; KPI row grid fixed to 4×1fr
- `src/sidebar/index.js` — `AiVisibility` component + `registerPlugin`
- `admin/css/citewp-aiso-admin.css` — Publishing Controls CSS, orange token fix, Cite Score page dial polish, Dashboard KPI row polish
- `src/sidebar/style.scss` — orange token fix

**npm build:** ✅ Clean — `webpack compiled successfully` (no new warnings; 3 pre-existing chartLine icon warnings are known).

**Decisions made:** P45 (see DECISIONS.md) — per-post llms.txt toggle placement locked: Classic EditorPanel "Publishing Controls" (A1) + Gutenberg "AI Visibility" PluginDocumentSettingPanel (G2); meta key polarity '1'=exclude preserved. P46 — `--citewp-score-orange` corrected `#D63638` → `#E86612` (resolves P44 known S24 cleanup).

**Verified:**
- Classic Editor: toggle renders, saves meta '1'/'0' correctly, cache flushes, llms.txt excludes post ✓
- Gutenberg: AI Visibility panel renders in Document Settings, ToggleControl reflects live meta, save round-trip works ✓
- `ContentSelector::is_excluded()` correctly respects meta '1' ✓
- Browser HTTP cache (`Cache-Control: public, max-age=3600`) was the red herring — `cache: 'reload'` confirmed correct server-side exclusion ✓
- Smoke test: 9/9 applicable steps pass (Step 10 deferred — `uninstall.php` unchanged) ✓
- Cite Score page: dial/copy/button stacks cleanly at 100% zoom / 1280px breakpoint ✓
- Dashboard KPI row: all 4 cards equal width and height at 1440px / 100% zoom ✓
- `debug.log`: not present (no PHP errors) ✓

**Carryover into next session:** None.

**Next session focus:** FB38 (full Dashboard Cite Score restructure with category bars + Bot Visits) or next priority from master file backlog.

---

## Session 24 — Cite Score KPI Densification + AI Recommendations Filter ✅

**Date:** 2026-05-11

### Deliverable

Three iterative UI tasks on the Cite Score page: densified the 3 KPI cards, polished away redundant captions, and made the AI Recommendations "View Pages" buttons functional by wiring them to a filtered WordPress posts list.

**What shipped:**

1. **Cite Score KPI card densification:**
   - Card 1 (Avg Cite Score): grade badge pill + "X of Y pages scored" sub-line
   - Card 2 (Posts Optimized): percentage chip + 4px progress bar
   - Card 3 (Issues Detected): critical (score < 40) / minor (score 40–49) split row
   - New CSS: `.citewp-aiso-kpi-badge`, `.citewp-aiso-kpi-progress`, `.citewp-aiso-kpi-card__split`

2. **KPI card polish pass:**
   - Removed grade badge (belongs on dial, not KPI card)
   - Removed old captions ("site-wide average", "score ≥ 50", "score < 50")
   - Card 1: sole caption is "X of Y pages scored" + week-over-week delta line (gracefully omitted when < 2 weeks of history)
   - Card 2: caption is "N% of your content is optimized"
   - Card 3: caption is "across N posts needing attention"
   - Delta line uses `$history` (already loaded) split at 7-day boundary — no extra DB query

3. **AI Recommendations filtered posts list:**
   - New class `RecommendationFilter` registered via Plugin.php
   - `get_affected_ids(signal_id)`: full meta scan returning exact post IDs where signal is fail/partial; per-request static cache prevents duplicate scans across card rendering + filter hook + notice
   - `pre_get_posts` hook narrows edit.php query to affected IDs when `?aiso_recommendation={slug}` is present
   - `admin_notices` hook renders "Showing N posts flagged for: X. × All posts" notice with clear-filter link (no JS-dismiss — the link IS the dismiss)
   - Menu.php: pre-computes `$displayable_recs` using exact counts; button label "View N post(s)"; cards with zero affected posts hidden; empty state shown if all recs have zero
   - 4-grep defensive pass: "View Pages" / old caption strings confirmed gone

**Files created:**
- `includes/Admin/RecommendationFilter.php` — filter class (new)

**Files modified:**
- `includes/Admin/Menu.php` — KPI card HTML × 3, delta computation, recs loop rewrite
- `admin/css/citewp-aiso-admin.css` — badge, progress bar, split, rec-empty styles
- `includes/Plugin.php` — registered RecommendationFilter

**npm build:** Not required (PHP + CSS only).

**Decisions made:** None requiring DECISIONS.md entries.

**Verified:**
- 4-grep defensive passes: all old strings confirmed absent
- `debug.log`: not present (no PHP errors)
- Manual test required: visit Cite Score page, verify KPI cards render correctly; click recommendation button, verify filtered posts list + notice; test × clear link; test empty state

**Carryover into next session:** None from this session. See Next Session Focus.

**Next session focus:** TBD — see master file backlog.

---

## Session 23 — EditorPanel v3 Polish + Bot Visits Widget ✅

**Date:** 2026-05-05

### Deliverable

EditorPanel meta box v3 migration (all inline styles → CSS custom properties + BEM classes) and per-post AI bot crawler activity widget.

**What shipped:**

1. **EditorPanel v3 CSS migration:**
   - All `inline_styles()` hex color values → CSS custom properties in `admin/css/citewp-aiso-admin.css`
   - Properties scoped to `#citewp_aiso_editor_panel` (top-level ID selector)
   - `enqueue_styles()` hook added; CSS loaded on `post.php`/`post-new.php` via `admin_enqueue_scripts`
   - Tokens: `--citewp-navy`, `--citewp-citrine`, `--citewp-white`, `--citewp-smoke`, `--citewp-border`, `--citewp-success`, `--citewp-warning`, `--citewp-alert`, `--citewp-muted`

2. **Tab filter split (FB30 unblock):**
   - `citewp_aiso/metabox/tabs` filter updated from single call to two contextual calls
   - `'score'` context: General tab (default)
   - `'schema'` context: Schema tab
   - Enables FB30 ("Cite Bridges" schema extensions) to filter tab content per context

3. **Bot Visits data layer:**
   - `query_bot_visits()`: 7-day window, LIMIT 6 overflow detection, `Schema::table()` helper, GROUP BY bot + vendor
   - `bot_dot_color()`: crc32 mod palette (assigns stable colors to repeated bot IPs across posts)

4. **Bot Visits widget:**
   - `render_bot_visits()`: per-post AI crawler activity (GeoIP lookup, bot type, visit count, last seen)
   - 3-column table: Bot / Visits / Last seen (human_time_diff format)
   - Overflow: "and N more bots" if >6 unique IPs in 7-day window
   - Tier disclosure footer (bottom of populated state only): "Shown: top 6 bots by frequency. Include tier status?"
   - Empty state: "No AI bot visits yet / Most bots discover new posts within 24–72 hours of publishing." — vertically centered in right column, no footer

5. **General tab layout:**
   - 2-column CSS grid (50/50 split, left: score display, right: Bot Visits widget)
   - Category rows: visual fill bars (horizontal progress) with grade-specific colors (green/yellow/orange/red, thresholds ≥80/≥60/≥40/else) matching S22 sidebar pattern

6. **Recalculate button rebuild:**
   - JS helper updated to output bar fill markup during request
   - Maintains feature parity with S22 Gutenberg sidebar

7. **Composition note — "Last seen" emergent dimension:**
   - Not in original Q3 spec; emerged from wider-canvas work (meta box can display richer table than sidebar mockup)
   - Data already available: `MAX(created_at)` in GROUP BY from data layer
   - No schema change required

**Files changed:**
- `includes/Admin/EditorPanel.php` — full rewrite (inline_styles deleted, data layer wired, two tabs, Bot Visits widget)
- `admin/css/citewp-aiso-admin.css` — EditorPanel v3 section appended (~200 lines)

**npm build:** Not required (PHP + CSS only).

**Smoke test:** Manual verification on `post.php` post editor.
- Score display renders with category bars (fill visual)
- Bot Visits table populated for test post
- Recalculate button works, updates live without reload
- Empty state displays correctly for new/uncrawled posts
- Responsive: grid stays 2-column above 1024px, consider mobile fallback if needed

**Commits:** 7 commits shipped and pushed to `origin/main`.

**Post-ship fixes (same session):** Two issues caught during browser verification:
- Column ratio changed from 45/55 → 50/50 (45/55 gave Bot Visits too much visual weight)
- Category bar colors: initial implementation used flat `--citewp-navy`; fixed to emit grade modifier classes (`citewp-aiso-mb-cat-bar-fill--{grade}`) with grade computed from pct thresholds (≥80/≥60/≥40/else), matching S22 sidebar pattern. Both PHP render and JS recalculate rebuild updated.

**X20 pipeline gap flagged:** Both post-ship issues (column ratio, bar modifier classes) should have been caught by an X20 component spec compliance audit comparing rendered output against UI-DESIGN-SYSTEM.md before manual browser verification. X20 was not explicitly run in the S23 execution pipeline. S24 plan should include an X20 audit step between code-reviewer and smoke test.

**Decisions made:** None new.

### Carryover into Session 24

**Next session focus:** llms.txt per-post widget (right panel detail view, FB32).

**Rolling carryover:**
- WP.org approval → SVN commit (user action)
- readme.txt polish + WP.org assets
- Anti-cloaking content (S7)
- Brain consolidation session
- P33 (Posts/Pages stat split)

---

## Session 22 — Gutenberg Sidebar + Schema Suggestions v3 Polish ✅

**Date:** 2026-05-05

### Deliverable

Full v3 polish of both Gutenberg registered plugins in `src/sidebar/index.js`:

- **Cite Score PluginSidebar:** label "CiteWP GEO Score" → "Cite Score", `chartBar` → `CiteWPIcon` (inline `[A]` SVG, P37-compliant `system-ui` font), all inline styles → BEM class names, `GRADE_COLORS` deleted, grade/status strings drive CSS modifier classes.
- **Schema Suggestions PluginDocumentSettingPanel:** `ARTICLE_VARIANTS` deleted, `SCHEMA_TYPES` data-driven array introduced, `SchemaTypeRow` all inline styles → BEM classes, `otherDetected` trailing block with `Question` exclusion (verified against `Generator.php::collect_root_types()`).
- **`src/sidebar/style.scss` (new):** P38 palette tokens (`$citewp-score-green: #00A32A` etc.), JetBrains Mono 400 `@font-face` (required here because `enqueue_block_editor_assets` ≠ `admin_enqueue_scripts`), all BEM block/element/modifier definitions.
- **`includes/Admin/EditorAssets.php`:** `wp_enqueue_style()` added with `filemtime()`-based cache busting.

**CSS output filename:** `build/style-index.css` (not `index.css` — `@wordpress/scripts` uses `style-index` for SCSS extracted via entry point). Font copied to `build/fonts/jetbrains-mono-400.2fe080a3.woff2`.

**Font path fix (auto-corrected by SA2):** `../admin/fonts/` → `../../admin/fonts/` (correct relative path from `src/sidebar/`).

**Browser verification (DevTools):**
- Citability bar (30/40 = 75%): class `citewp-aiso-sidebar-category__fill--yellow`, computed bg `rgb(219, 166, 23)` = `#DBA617`. Correct per 80/60/40 ramp. No bugs.
- Sidebar label: "Cite Score", icon: `[A]` yellow square confirmed.

**Files modified/created:**
- `src/sidebar/index.js` — 9 refactor commits (Tasks 1–9)
- `src/sidebar/style.scss` — created
- `includes/Admin/EditorAssets.php` — `wp_enqueue_style` added
- `build/style-index.css`, `build/style-index-rtl.css`, `build/fonts/jetbrains-mono-400.2fe080a3.woff2` — generated

**npm build:** Required and completed. Build clean.

**Commits:** 15 commits — `a1c6f16` through `a1ef65c` — pushed to `origin/main`.

**Decisions made:**
- CSS output filename is `style-index.css` for SCSS-via-entry-point (not `index.css`)
- `filemtime()` used for CSS version (JS `index.asset.php` hash does not reflect SCSS-only changes)
- `STATUS_ICONS` characters stay in DOM (not moved to CSS `::before`) for screen reader compatibility

### Carryover into Session 23

**Next session focus:** EditorPanel PHP meta box v3 polish.

**Deferred from this session:**
- FB29 new schema types (HowTo, Product, LocalBusiness)
- FB30 Cite Bridges tab
- FB34 Global meta defaults
- Score gauge SVG in sidebar
- Cite Score badge rectangular shape
- UI-DESIGN-SYSTEM.md "Block Editor Sidebar" component entry → Brain consolidation session

**Rolling carryover:**
- WP.org approval → SVN commit (user action — check hello@citewp.com)
- readme.txt polish + WP.org assets
- Anti-cloaking content (S7)
- "Suggest a Feature" link in admin
- Page-builder canvas-mode awareness surface
- Per-post surface consolidation doc
- Brain consolidation session
- Needs Attention "what's wrong" reasoning logic
- P33 (Posts/Pages stat split)

---

## Session 21 — Cite Score smoke test + Dashboard/Cite Score CSS polish ✅

**Date:** 2026-05-04

### Deliverable

Manual Cite Score page smoke test completed (user). Two CSS polish fixes shipped.

**Fix 1 — Tooltip width reduced**
- `.citewp-aiso-kpi-tooltip__text`: `min-width: 220px → 200px`, `max-width: 280px → 260px`
- Resolves Score Breakdown panel-title tooltip clipping at right edge.

**Fix 2 — Needs Attention card height aligned with AI Insights**
- `.citewp-aiso-needs`: `padding: var(--sp-5)` → `padding: var(--sp-5) var(--sp-5) var(--sp-8)`
- Adds 12px extra bottom padding (20px→32px) so card bottom aligns with AI Insights card in col-a.
- Note: `--sp-7` does not exist in the spacing scale (jumps sp-6→sp-8); using an undefined token in a shorthand invalidates the entire property (all padding collapses to 0).

**Files modified:**
- `admin/css/citewp-aiso-admin.css` — tooltip width, Needs Attention bottom padding

**npm build:** Not required (CSS only).

**Commit:** `4eea11a` — pushed to `origin/main`.

**WP.org status:** Still awaiting approval at hello@citewp.com. SVN commit is priority 1 when email arrives.

**Decisions made:** None.

### Carryover into Session 22

**Next session focus:** EditorPanel + Gutenberg sidebar v3 polish (multi-file, X13 pipeline).

**Deferred:**
- Cite Score badge rectangular shape (S20 Group 6)
- WP.org approval → SVN commit (user action — check hello@citewp.com)

**Rolling carryover:**
- readme.txt polish + WP.org assets
- Anti-cloaking content (S7)
- "Suggest a Feature" link in admin
- Page-builder canvas-mode awareness surface
- Per-post surface consolidation doc
- `citewp_aiso/metabox/tabs` filter `$context` arg
- Brain consolidation session
- Needs Attention "what's wrong" reasoning logic
- P33 (Posts/Pages stat split)

---

## Session 20 — Cite Score page Run #8 polish ✅

**Date:** 2026-05-04

### Deliverable

Fix-in-place final polish pass on the Cite Score sitewide dashboard, continuing from Run #7. 4 active groups (2 were no-ops) + 1 extra Dashboard request. All direct edits, code-reviewer clean pass.

**Group 1 — Remove KPI card tooltips (Cite Score page only)**
- Removed `.citewp-aiso-kpi-tooltip` wrapper + info icon + tooltip text from all 3 compact KPI cards in `citewp-aiso-cs-kpi-row` (Average Cite Score, Posts Optimized, Issues Detected).
- Removed same tooltip wrapper from AI Recommendations panel header.
- Panel-title tooltips (Cite Score Health, Score Breakdown, Post-Level Cite Scores) intentionally left intact.

**Group 2 — Panel-title `--align-left` removal (no-op)**
- All 3 panel-title tooltips already had no `--align-left` modifier. No changes needed.

**Group 3 — Move Pro Tip into left column**
- Moved `citewp-aiso-protip` block (including `apply_filters('citewp_aiso/protip', ...)` PHP call) from full-width position after `citewp-aiso-cite-score-page__body` into `citewp-aiso-cite-score-page__left` as last child, after the table wrap.

**Group 4 — Post-type icon mapping (no-op)**
- Current code (`page` → `file-text`, everything else → `file`) already handles `elementor_library` and all non-page types correctly. No changes needed.

**Group 5 — BETA pill enlargement**
- `.citewp-aiso-insights__badge`: font 600→700, size 10px→11px, padding 3px→4px (vertical), border-radius 20px→6px (pill → chip).
- Right-justification already handled by `flex: 1` on `__title` — no additional CSS needed.

**Extra — Remove Dashboard KPI card orb divs**
- Removed `citewp-aiso-kpi-card__orb` div (14px icon circle) from `__head` of all 4 Dashboard KPI cards (Avg Cite Score, Bot Visits, Indexed Pages, llms.txt).
- `__visual` blocks with 36px icons preserved untouched.

**Commits (4):**
- `c441b28` — fix: S20 Run #8 — Cite Score page final polish pass (Groups 1,3,5 + Extra)
- `ac811e8` — fix: add --align-left to 3 Cite Score panel-title tooltips (open right, prevent left clip)
- `94a43b4` — fix: Cite Score KPI cards — drop compact variant, use full card layout with 36px square visual icons

**Files modified:**
- `includes/Admin/Menu.php` — KPI card tooltip removal; AI Recs header tooltip removal; Pro Tip relocated; Dashboard KPI orb divs removed; panel-title tooltips `--align-left` added; compact card structure → full card with `__head`/`__body`/`__visual`
- `admin/css/citewp-aiso-admin.css` — Section 13: `.citewp-aiso-insights__badge` enlarged

**npm build:** Not required (CSS + PHP only, no JS changes).

**debug.log:** File absent — no PHP errors this session.

**Code reviewer:** Clean pass — all 8 checklist items passed, no issues found. Verdict: Production ready.

**Carryover into Session 21:**

**Still required (smoke tests):**
- Manual Cite Score page smoke test (22-item checklist from `docs/superpowers/plans/2026-05-03-cite-score-run6-final-polish.md`) — visual verification by user, not yet completed
- S18 Dashboard / Crawler Logs / Settings smoke test — still outstanding

**Deferred items:**
- Cite Score badge rectangular shape (S20 Group 6 — deferred)
- KPI orb size 28×28 → 36×36 with 18px Lucide icon (deferred; schedule when Dashboard parity work happens)
- Token name reconciliation (`--citewp-text-primary` / `--citewp-text-secondary` / `--citewp-text-muted` drift vs UI-DESIGN-SYSTEM.md) — defer to Brain consolidation session
- WP.org approval check — user action (check hello@citewp.com)

---

## Session 20 — Cite Score page Run #7 polish ✅

**Date:** 2026-05-04

### Deliverable

CSS/PHP fix-in-place pass on the Cite Score sitewide dashboard. 5 groups of visual polish, direct edits + code-reviewer pass. No new components, no scoring math touched, no JS changes.

**Group 1 — Page header strip parity**
- Replaced `.citewp-aiso-cs-header-band` (custom 1fr/auto grid with title+KPI in one row) with the shared `.citewp-aiso-page-header` strip (matching Crawler Logs / Settings).
- KPI cards moved to a separate `.citewp-aiso-kpi-row--3col` row below the strip.
- Removed `.citewp-aiso-cs-header-band*` CSS; added `.citewp-aiso-cs-kpi-row { margin-top: var(--sp-5); }`.

**Group 2 — Left sub-row column ratio + score-copy**
- `.citewp-aiso-cite-score-page__left-row`: `1fr 1fr` → `1.3fr 1fr` (Health panel wider).
- `.citewp-aiso-cs-score-copy`: `max-width: 280px; font 13px/1.6` → `max-width: none; font 12px/1.55`.

**Group 3 — Right column natural height**
- `.citewp-aiso-cite-score-page__right`: added `align-self: start` (prevents grid from force-stretching right column to left column height).
- Scoped override: `.citewp-aiso-cite-score-page__right .citewp-aiso-cs-panel { height: auto; }`.
- `.citewp-aiso-history-panel`: added `min-height: 280px` floor for empty state.

**Group 4 — Table header breathing room + column widths**
- `.citewp-aiso-cs-table-head`: added `padding: var(--sp-4) var(--sp-5)` (was no padding).
- `.citewp-aiso-cs-pagination`: consolidated duplicate blocks into one with `padding: var(--sp-3) var(--sp-5)`; removed dead `__info`/`__nav` child rules (caught by code-reviewer).
- `<th>` column widths: Post 36% / Cite Score 10% / Trend 8% / Last Updated 14% / Issues 12% / Actions 20%.
- `.citewp-aiso-cs-table__issues--active/none`: added `white-space: nowrap`.

**Group 5 — Tooltip width**
- `.citewp-aiso-kpi-tooltip__text`: `max-width: 360px` → `max-width: 280px`; added `min-width: 220px`.

**Commits (6):**
- `b8603dd` — fix: Cite Score page run #7 — Group 1 — page-header strip + separate KPI row (S20)
- `e012614` — fix: Cite Score page run #7 — Group 2 — left-row 1.3fr/1fr + score-copy 12px no max-width (S20)
- `c4e1db9` — fix: Cite Score page run #7 — Group 3 — right column natural height, history min-height 280px (S20)
- `0d1db0b` — fix: Cite Score page run #7 — Group 4 — table-head/pagination padding, column widths, issues nowrap (S20)
- `87bd05e` — fix: Cite Score page run #7 — Group 5 — tooltip min-width 220px, max-width 280px (S20)
- `1ac66e9` — fix: Cite Score run #7 — remove duplicate pagination CSS block (code-reviewer S20)

**Files modified:**
- `admin/css/citewp-aiso-admin.css` — Section 31: header-band → cs-kpi-row; left-row ratio; right column height; table-head padding; pagination dedup + padding; issues nowrap; tooltip width
- `includes/Admin/Menu.php` — render_cite_score_panel(): page-header strip markup; KPI row as separate block; `<th>` column widths

**npm build:** Not required (CSS + PHP only, no JS changes).

**Smoke test:** Visual verification required by user — open `admin.php?page=citewp#cite-score` and check header strip parity with Crawler Logs, tooltip behaviour, right column height, table column widths.

**Code reviewer:** Ran post-Group 5. One Important issue found and fixed (duplicate pagination block). No Critical issues. Verdict: Ready to merge (with fix applied).

**→ Continued in Run #8 (same session date). See entry above.**

**Pre-S20 rolling carryover:**
- **EditorPanel + Gutenberg sidebar v3 polish** — S17 carryover
- **Per-post surface consolidation doc** — S12 carryover
- **`citewp_aiso/metabox/tabs` filter `$context` arg** — S12 carryover
- **Brain consolidation session** — tighten DECISIONS.md after amendment sessions
- **readme.txt + WP.org assets** — v0.7.0 changelog, screenshots review
- **Anti-cloaking content (S7)** — landing page section + first blog post
- **"Suggest a Feature" link** in admin
- **Page-builder canvas-mode awareness surface**
- **P33** — Posts/Pages stat split
- **Engine.php entities detector bug** — A11-gated
- **Needs Attention "what's wrong" reasoning logic**

---

## Session 19 — P41 button taxonomy + Cite Score sitewide dashboard ✅

**Date:** 2026-05-03

### Task 2 deliverable (Cite Score sitewide dashboard v3 — run #3 layout)

First-pass per-post Cite Score page replaced with a full sitewide dashboard using the structural mockup layout (4-row spec): page header strip → 3 KPI stat cards → 3-col top grid (Cite Score Health gauge / Score Breakdown / AI Recommendations) → 2-col lower grid (Post-Level Scores table / Cite Score Over Time chart) → purple-tint Pro Tip footer. Gauge uses pathLength=100 + CSS `--score` variable + linearGradient approach. Infrastructure: `ScoreHistory.php` (daily cron, 365-entry WP options log) + `RecommendationMapper.php` (17 signals → copy). Brain design system updated with P42 component specs.

**Commits (plugin repo, Session 19 Task 2):**
- `2017b1f` — feat: ScoreHistory — daily avg cron + WP options, 365-entry cap (S19)
- `9c59d5e` — feat: RecommendationMapper — 17-signal recommendation copy map (S19)
- `aeb3171` — refactor: remove first-pass per-post Cite Score code (S19)
- `91ec621` — feat: Cite Score sitewide dashboard CSS — Section 31 (S19)
- `cba81d6` — feat: Cite Score sitewide dashboard — PHP render (gauge, breakdown, recs, table) (S19)
- `c02a3f1` — fix: remove dead $issue_count variable in render_cite_score_panel (S19)
- `83048ff` — feat: Cite Score page run #3 — 4-row mockup layout (KPI row, 3-col panels, 2-col lower, pathLength gauge) (S19)
- `f57a1bb` — fix: clamp gauge --score CSS var to 0-100 (S19 run #3 reviewer note)

**Brain edits (Session 19, Task 2):**
- `UI-DESIGN-SYSTEM.md` — P42: Semi-circle gauge spec added to Donut Chart Panel; Score Breakdown Panel, AI Recommendations Panel, Post-Level Score Table added to Component Library; Cite Score layout in P40 updated to sitewide-only.

**Files modified (plugin):**
- `includes/Scoring/ScoreHistory.php` — NEW: daily avg cron hook, schedule/unschedule, get_history(days), 365-entry cap
- `includes/Admin/RecommendationMapper.php` — NEW: 17-signal → {label, copy, category} map, get()/get_many()
- `includes/Plugin.php` — ScoreHistory wired: register() in boot(), schedule() in activate(), unschedule() in deactivate()
- `admin/css/citewp-aiso-admin.css` — Section 31 replaced: 4-row layout grids (.citewp-aiso-cs-top-grid 1.3fr/1.1fr/1.35fr, .citewp-aiso-cs-lower-grid 1.65fr/1fr), .citewp-aiso-cs-panel chrome, pathLength=100 gauge classes (.citewp-aiso-cs-gauge*), breakdown/recs/table/history/pagination/empty-state classes
- `includes/Admin/Menu.php` — render_cite_score_panel(): 4-row layout with KPI row + 3-col top grid + 2-col lower grid; render_gauge_svg(): pathLength=100 + CSS variable + score clamp; render_history_svg(): unchanged

**npm build:** Not run — PHP + CSS only, no JS changes.

**Smoke test (run #3):** Playwright browser context closed; manual verification needed (see Carryover).

---

### Task 2 polish — Runs #4–6 (subagent-driven fix-in-place passes)

Three fix-in-place polish runs after run #3 shipped the structural build. All executed via subagent-driven development (fresh subagent per task + spec compliance + code quality gates per X13 pipeline).

**Run #4 (10 commits) — Root Cause A+B isolation + 12 targeted fixes**

Root Cause A: `text-transform: uppercase` leaking from WP admin baseline into panel titles. Root Cause B: tooltip `--align-right` modifier built backward. 10 targeted fixes: KPI compact layout, gauge 4-stop gradient, score-wrap grid, 9px bar thickness, AI Recs bot icon, outline button, per-page default 5, post link styles, Issues count data bug (`is_array` not `is_object`), card height enforcement (`align-items: stretch`).

**Run #5 (10 commits) — Root Cause A+B resolved + remaining polish**

Root Cause A fixed: `text-transform: none` on `.citewp-aiso-cs-panel__title`. Root Cause B fixed: `--align-right` modifier correctly adds right-anchor. All run #4 fixes applied. Pushed to `origin/main` (`50e36bf` head after push).

**Run #6 (7 commits) — Final polish: 2-col layout + tooltip reversal + table**

- **FIX L1:** Replaced 3-col+2-col row structure with 2fr/1fr two-column independent stack. AI Recs + Over Time moved to right column. Health + Breakdown in left sub-row (1fr/1fr, stretch).
- **FIX T1+T2:** Tooltip default reversed to right-anchored (`right:0; left:auto`). `--align-left` modifier for rightmost icons. Dashboard KPI cards 1–3 patched with `--align-left` to prevent left-edge overflow. Max-width 360px.
- **FIX 5.1:** Score Breakdown `__head` → Inter 700 14px, obsidian, `text-transform:none`, `letter-spacing:0`.
- **FIX 6.1–6.6:** Post table — `td` padding 16px/12px, bold title links, icon top-align (flex-start), `th` 12px, Actions column header, nowrap Optimize button.
- **FIX 4.1:** `$avg_grade` thresholds corrected to 90/70/50 per spec (were 80/60/40).

**Commits (Run #6):**
- `3ac2ec2` — feat: FIX L1 — two-column independent stack (S19 run #6)
- `58785d5` — fix: remove dead media query CSS for old grids (S19 run #6)
- `7b2953b` — fix: FIX T1+T2 — tooltip right-anchored default, --align-left modifier, 360px (S19 run #6)
- `8a950fa` — fix: Dashboard KPI cards 1-3 --align-left overflow fix (S19 run #6)
- `4fb52b0` — fix: FIX 5.1 — Score Breakdown title Inter 700 14px obsidian (S19 run #6)
- `95cf187` — fix: FIX 6.1-6.6 — post table polish (S19 run #6)
- `ef656eb` — fix: FIX 4.1 — avg_grade thresholds corrected to 90/70/50 per spec (S19 run #6)

**Brain edits (Runs #4–6, file-save only — Brain folder has no git repo):**
`UI-DESIGN-SYSTEM.md` — tooltip default reversed to right-anchored; `--align-left` modifier documented; Two-Column Independent Stack layout pattern added; Post-Level Score Table column header + Actions spec locked; stale anatomy bullet corrected; Last Updated: 2026-05-03 run #6.

**Key learning — run #1–3 reuse-failure pattern:**
Runs #1–3 adapted/extended a prior 2-col mockup structure rather than treating the reference design as an independent spec. The reference uses a 2-col independent stack (not 3-col+2-col rows). Structural mismatch caused compounding debt requiring 3 extra runs. Lesson: always implement from the reference design directly — do not carry forward structural decisions from a superseded build.

---

### Task 1 deliverable (P41 button taxonomy rollout + hero alignment fix)

UI-DESIGN-SYSTEM.md button section rewritten per P41 four-style taxonomy (primary-paper / primary-navy / outline / soft). Plugin CSS renamed `--primary-action` → `--primary-paper`, spec fixed (padding 16px, font 13px, radius 8px), `--outline` updated to obsidian + hairline border, `--soft` class added. Improve buttons in Needs Attention demoted from primary-paper to outline. Post-smoke-test: hero top-justify fix (Section 30) + Top Crawlers cap 3 → 5.

**Commits (plugin repo, Session 19 Task 1):**
- `da4fe3d` — feat: P41 button taxonomy — rename primary-action→primary-paper, fix spec, update outline, add soft
- `f8cbc12` — fix: demote Needs Attention Improve buttons to outline; rename primary-action→primary-paper in PHP
- `c774809` — fix: hero top-justify + breathing room; Top Crawlers cap → 5 (Session 19)

**Brain edits (Session 19, Task 1):**
- `UI-DESIGN-SYSTEM.md` — Button section fully rewritten: four-style taxonomy, hierarchy rule, surface mapping, Why Blue rationale, CSS class naming per A14. 7 component library entries updated.

**Files modified (plugin):**
- `admin/css/citewp-aiso-admin.css` — Section 25: `--primary-action` → `--primary-paper` + spec fix. `--outline` updated. `--soft` added. Section 30: hero alignment fixes.
- `includes/Admin/Menu.php` — Improve/View Recommendations/Connect Now classes updated. `get_top_crawlers(5)`.
- `includes/Settings/Page.php` — Save Changes button class updated.

**Decisions made (Task 1):**
- P41 expanded to four-style: `--soft` added as paper-tinted tertiary.
- CSS class A14 prefix confirmed: `citewp-aiso-btn` base + `citewp-aiso-btn--{modifier}`.

**Smoke test (Task 1):** LocalWP 502 prevented browser automation. User verified dashboard manually post-fix.

**Carryover into Session 20:**

**Required first tasks:**
- **Manual smoke test — Cite Score page (REQUIRED):** Open `admin.php?page=citewp#cite-score`, run the 22-item checklist from `docs/superpowers/plans/2026-05-03-cite-score-run6-final-polish.md`. If anything fails → S20 fix, no Run #7.
- **S18 Dashboard / Crawler Logs / Settings smoke test** — outstanding from S18, still not completed.

**S20 polish (Run #7 items that didn't ship):**
- Right column natural height — AI Recommendations panel cropping, Cite Score Over Time panel stretching
- Cite Score Health vs Score Breakdown sub-row width ratio: 1.3fr / 1fr
- Cite Score Over Time `min-height: 280px` for empty state
- KPI orb size in row 1: bump 28×28 → 36×36 with 18px Lucide icon
- Post-Level table column widths: Post 36% / Score 10% / Trend 8% / Updated 14% / Issues 12% / Actions 20%
- View Score Guide button `white-space: nowrap`
- Tooltip positioning context verification — right-column cards still cropping

**S19 carryover still open:**
- Token name reconciliation: `citewp-aiso-admin.css` uses `--citewp-text-primary` / `--citewp-text-secondary` / `--citewp-text-muted`; `UI-DESIGN-SYSTEM.md` names `--citewp-text-muted` only — defer to Brain consolidation session
- WP.org approval check — still pending as of S19 open; check at start of S20
- Dashboard KPI orb alignment — if Cite Score page bumps orbs to 36×36 in S20, Dashboard (28×28) becomes the inconsistent surface; schedule Dashboard polish to match
- Score History chart empty state until cron runs — expected behaviour

**Pre-S19 rolling carryover:**
- **EditorPanel + Gutenberg sidebar v3 polish** — S17 carryover
- **Per-post surface consolidation doc** — S12 carryover
- **`citewp_aiso/metabox/tabs` filter `$context` arg** — S12 carryover
- **Brain consolidation session** — tighten DECISIONS.md entries after multiple amendment sessions
- **readme.txt + WP.org assets** — v0.7.0 changelog, screenshots review
- **Anti-cloaking content (S7)** — landing page section + first blog post
- **"Suggest a Feature" link** in admin
- **Page-builder canvas-mode awareness surface**
- **P33** — Posts/Pages stat split
- **Engine.php entities detector bug** — A11-gated (requires Engine.php edit approval)
- **Needs Attention "what's wrong" reasoning logic**

---

## Session 18 — Dashboard polish rounds 1-4 + P41 button migration ✅

**Date:** 2026-05-03

**Deliverable:** Four rounds of Dashboard polish (Groups A/B/C, D/E/F, H/I, J/K) shipped. P41 button migration complete. Cite Score page v3 deferred to Session 19 (polish work consumed full session).

**Commits (plugin repo, Session 18):**
- `9c7ee19` — feat: Dashboard polish round 1 — section icon headers, KPI tooltips, Pro Tip relocation, type pills, Quick Actions arrow, data cap at 3 rows (Groups A/B/C)
- `504ed66` — fix: Dashboard polish round 2 — tooltip fix (info icon added), KPI title color, grid overflow fix, icon stroke-width 2.5, navy darkened to #07111F, arrow inline, type pill in meta row, protip/hero/button spacing (Groups D/E/F + F1/F2)
- `1e49d88` — feat: Dashboard polish round 3 — KPI tooltip right-anchor, hero dual-gap, P41 button migration: --primary-action weight 600, Improve/View Recommendations/Connect Now migrated to blue-on-paper (Groups H/I)
- `f9bf068` — fix: consolidate tooltip left:auto into original block (code-reviewer H1)
- `17d1ba6` — fix: Dashboard polish round 4 — hero padding-block sp-8→sp-10 (taller hero card, gaps now have room); tooltip width max-width→explicit 220px (root cause of tall-narrow render: absolute element was shrinking to icon parent width ~16px) (Groups J/K)

**Brain edits (Desktop Commander, before Code work):**
- `DECISIONS.md` — P41 logged: Tint Blue `#2563EB` permitted as primary action color on paper surfaces; Citrine remains primary on navy; three-style button system defined (primary-paper / primary-navy / secondary-tertiary).

**Files modified (plugin):**
- `admin/css/citewp-aiso-admin.css` — Sections 27–29 appended; Section 25 `--primary-action` weight 700→600; `--citewp-navy` darkened #1E2A3B→#07111F; grid overflow fix; icon stroke-width 2.5
- `includes/Admin/IconLibrary.php` — `info` icon added; stroke-width 1.5→2.5
- `includes/Admin/Menu.php` — section icon+heading wrappers, KPI tooltip HTML, Pro Tip relocated to col-b, type pill moved into meta row, Improve/View Recommendations/Connect Now buttons migrated to `--primary-action`
- `includes/Admin/DashboardData.php` — `get_lowest_scoring_posts()` capped at 3 rows
- `includes/Settings/Page.php` — tab nav hidden when only one tab (single-tab rhythm fix)

**Decisions made:**
- P41: `--primary-action` is the canonical paper-primary button class (no new class added); blue on paper, Citrine on navy.
- Connect Now (Pro Tip, col-b paper surface) confirmed as paper surface — citrine→blue migration correct.
- `--citewp-navy` value change is a CSS token update under the existing P38 system, not a new P-row.

**Smoke test:** Playwright process closed during verification — browser test could not complete. Manual verification required before S19 code work begins (open Dashboard, verify blue buttons on paper, Citrine on rail, tooltip right-anchor, hero gaps).

**npm build:** Not run this session (CSS-only + PHP changes, no JS modifications).

**PHP lint:** Not run — PHP CLI not configured in LocalWP shell (per CLAUDE.md).

**X20 Audit:** UI-DESIGN-SYSTEM.md button entries (KPI Button component, token descriptions for `--citewp-paper-tinted` and `--citewp-tint-blue`) are stale — pre-P41 spec. Rewrite deferred to Session 19 Desktop work per carryover below.

**Carryover into Session 19:**
- **UI-DESIGN-SYSTEM.md button section rewrite per P41** — Desktop work, first task in Session 19. Three-style spec: primary-paper (blue), primary-navy (Citrine), secondary/tertiary. Update KPI Button component entry and token descriptions.
- **Cite Score page v3** — Donut Chart Panel + Line Chart Panel + signal breakdown table. Session 18 master-file deliverable; polish work consumed full session.
- **EditorPanel + Gutenberg sidebar v3 polish** — Session 17 carryover
- **Per-post surface consolidation doc** — document all post-level scoring surfaces in one place
- **`citewp_aiso/metabox/tabs` filter `$context` arg** — Session 12 carryover, still open
- **Brain consolidation session** — tighten DECISIONS.md entries after multiple consecutive amendment sessions
- **readme.txt + WP.org assets** — v0.7.0 changelog, screenshots, banner/icon review
- **Anti-cloaking content (S7)** — landing page section + first blog post
- **"Suggest a Feature" link** in admin
- **Page-builder canvas-mode awareness surface**
- **P33 (Posts/Pages stat split)** — Dashboard data layer, deferred
- **Engine.php entities detector bug** — A11-gated
- **Needs Attention "what's wrong" reasoning logic**

---

## Session 17 — Crawler Logs + Settings v3 polish ✅

**Date:** 2026-05-02

**Deliverable:** Full v3 treatment shipped to Crawler Logs and Settings admin pages per P40. Replaced light v3 styling with component-spec-compliant markup and CSS. Crawler Logs: page header strip (title + description + filter pills for date range), 4-col KPI row (Total Crawls / Unique Bots / Pages Crawled / Avg Crawl Frequency), full-width logs table in paper card container, Pro Tip footer. Settings: consolidated to single General tab with 3 stacked FSCards (AI Crawler Detection / llms.txt Generation / Maintenance), page header strip, blue Save Changes button, KPI Button–styled Regenerate action. CSS Sections 20–25 appended to `admin/css/citewp-aiso-admin.css` in Task 1 before PHP work to prevent merge conflicts. P40 Brain edits (DECISIONS.md + UI-DESIGN-SYSTEM.md) completed in brainstorming phase before implementation.

**Commits (plugin repo, Session 17):**
- `897e54a` — docs: Session 17 implementation plan
- `467d3a7` — docs: plan-review resolutions — scope cut, schema verified
- `3904508` — docs: plan amendment — per-card orb on FSCard
- `69da08b` — feat: add v3 page header strip, filter pills, KPI v3, FSCard, settings tab nav CSS (Sections 20–25)
- `1d468ba` — feat: Crawler Logs v3 polish — page header strip, filter pills, 4-col KPI row, paper table card, Pro Tip footer
- `bb829cd` — feat: Settings v3 polish — page header strip with tab nav, Form Section Cards, blue Save button
- `2abaf93` — fix: Settings v3 polish — FSCard two-col layout, tab consolidation, llms.txt URL row, Regenerate button, tab spacing

**Brain edits (brainstorming phase, before plugin build):**
- `DECISIONS.md` — P40 logged: page header strip pattern — Hero Card is Dashboard-only; all other admin pages use slim Page Header Strip (title + description + optional right-side controls).
- `UI-DESIGN-SYSTEM.md` — "Other page layouts" section rewritten; Hero Card annotated as Dashboard-only; 4 new Component Library entries: Page Header Strip, KPI Card (page-level), Filter Pill, Form Section Card (FSCard).

**Files modified (plugin):**
- `admin/css/citewp-aiso-admin.css` — Sections 20–25 appended (page header strip, filter pills, KPI stat cards, logs table card, Pro Tip footer, FSCard, settings tab nav)
- `includes/Admin/LogsPage.php` — rewritten: page header strip + filter pills, 4-col KPI queries (total/unique bots/pages/avg freq), paper table card wrapper, Pro Tip footer
- `includes/Admin/LogsTable.php` — `extra_tablenav()` date range `<select>` removed (moved to header filter pills); bot filter preserved; early-return guard added
- `includes/Settings/Page.php` — rewritten: page header strip, single General tab with 3 stacked FSCards, KPI Button–styled Regenerate, `__left` wrappers on all row left cells, `__row--info` for llms.txt URL
- `docs/superpowers/plans/2026-05-02-crawler-logs-settings-v3.md` — implementation plan (new file)

**Decisions made:**
- Filter pills in page header strip replace `LogsTable::extra_tablenav()` date range select; bot filter kept in `extra_tablenav()` (data only available after `prepare_items()`)
- Settings consolidated from 3 tabs to single General tab with 3 stacked FSCards; single-tab nav preserved for visual rhythm
- Avg Crawl Frequency: `$count_30d / 30` → "X/day" (reuses existing 30d query, no extra DB hit)

**Smoke test:** Manual browser verification passed. Crawler Logs: KPI row rendering with correct data, filter pills pass GET params, table wrapped in paper card, Pro Tip footer visible. Settings: FSCard two-col layout correct, Regenerate button KPI-styled, llms.txt URL rendered as info row inside card, tab nav underline active.

**npm build:** Ran successfully (webpack 1037ms). No JavaScript changes this session — PHP and CSS only.

**PHP lint:** Not run — PHP CLI not configured in LocalWP shell (per CLAUDE.md). No new PHP errors observed in debug.log.

**WP.org status:** In review (submitted 2026-04-29). Awaiting approval email at hello@citewp.com. SVN commit held per X16.

**Carryover into Session 18:**
- **Cite Score page v3** — Donut Chart Panel + Line Chart Panel + signal breakdown table (Session 18 primary deliverable)
- **Dashboard polish (6 items):** wordmark weight, hero card spacing, filter pill click handler, AI Insights button spacing, rail navy color / Quick Actions panel chrome, Pro Plans button spacing
- **Settings polish (6 items):** single-tab nav right-alignment, Regenerate button end-of-row position, info-row spacing, FSCard header vertical padding, code URL tint verification, tab nav consistency cross-check
- **EditorPanel + Gutenberg sidebar v3 polish** — candidate for Session 18 or 19
- **Per-post surface consolidation doc** — document all post-level scoring surfaces in one place
- **`citewp_aiso/metabox/tabs` filter `$context` arg** — Session 12 carryover, still open
- **Brain consolidation session** — tighten DECISIONS.md entries after multiple consecutive amendment sessions
- **readme.txt + WP.org assets** — v0.7.0 changelog, screenshots, banner/icon review (Session 16 carryover)
- **Anti-cloaking content (S7)** — landing page section + first blog post (Session 16 carryover)
- **"Suggest a Feature" link** in admin (Session 16 carryover)
- **Page-builder canvas-mode awareness surface** (Session 16 carryover)
- **P33 (Posts/Pages stat split)** — Dashboard data layer, still deferred
- **Engine.php entities detector bug** — A11-gated, requires explicit user approval

**Next session focus:** Session 18 — Cite Score page v3 (Donut Chart Panel + Line Chart Panel + signal breakdown table). Check WP.org approval status first — if approval email arrived at hello@citewp.com, SVN commit is Task 1.

---

## Session 16 — Plugin admin v3 migration ✅

**Date:** 2026-05-01

**Deliverable:** Full plugin admin v3 migration shipped. `admin/css/citewp-aiso-admin.css` rewritten (~1,042 lines, 21 sections, all 18 P38 tokens active). `Menu.php` `render_dashboard_panel()` fully rebuilt to v3 composition per P39 Verdict 3: hero card with personalized greeting + 3 stat cards, 4-card KPI row, two-column lower section (AI Insights two-tone nested + Top Crawlers table / Needs Attention list + Quick Actions 4-wide grid), Pro Tip footer. P31 meta box consolidated into `EditorPanel.php` (replaces `ScoreMetaBox.php` + `SchemaMetaBox.php`). `IconLibrary.php` added (20 Lucide SVG icons). Settings + LogsPage light v3 styling. GEO Score → Cite Score rename throughout all PHP and CSS. `DashboardData.php` extended with `get_issue_count()` and `get_top_crawlers()` bot_type + prior_visits fields. X20 logged (component spec compliance audit before manual verification — derived from Session 16 multi-pass cleanup analysis). **P36 plugin code freeze lifted — v3 admin migration condition met.**

**Commits (plugin repo, Session 16):**
- `495f9b8` — chore: remove plus-jakarta-sans-800.woff2 (replaced by Inter v3)
- `6377390` — fix: swap bot avatar colors to P38 tint tokens; add solid tint token set to :root
- `a9c27b5` — feat: subagent C — Menu.php v3 rewrite, rail brand area, dashboard composition
- `b90094a` — feat: subagent E — EditorPanel P31 metabox, retire ScoreMetaBox + SchemaMetaBox
- `33e76b5` — feat: subagent F — swap Tailwind hex to P38 colors, rename GEO Score → Cite Score
- `6b8ba7e` — feat: subagent G — Settings + LogsPage light v3 (title rows, KPI cards, table wrap)
- `7402ea5` — fix: code-review blockers + warnings
- `402c638` — feat: Session 16 cleanup pass (KPI trends, hero stat cards, Needs Attention, AI Insights two-tone, rail descriptions, footer spacing)
- `095a2da` — feat: Session 16 tightening pass (brand area, KPI anatomy, hero chrome, AI Insights contrast, column swap + 45/55 ratio, 4-wide Quick Actions, Top Crawlers full table, Pro Tip orb)
- `38a054e` — feat: Session 16 closeout pass (wordmark color, Citrine brackets, Pro card copy, hero greeting, icon contrast, KPI score-band, AI Insights spacing, Quick Actions orb colors, Top Crawlers cap 3 + View Full Report, Pro Tip gradient, logo audit)

**Brain edits (Session 16 close, direct file edits — Brain folder has no accessible git shell):**
- `DECISIONS.md` — X20 logged: component spec compliance audit before manual browser verification. Derived from Session 16 multi-pass cleanup analysis — all deviations caught in three cleanup passes were enumerated in the UI-DESIGN-SYSTEM.md Component Library and required no judgment, just deterministic spec comparison.
- `UI-DESIGN-SYSTEM.md` — wordmark spec amended (28-30px, "CiteWP" in `--citewp-text-on-navy`), plugin name spec (16px), Pro card copy updated to match shipped implementation.
- `00-CITEWP-MASTER.md` — Last updated, Session 16 appended to Shipped list, Current status updated (P36 freeze lifted, last commit `38a054e`), Next Session updated to Session 17 scope, Phase 1.5 sequence Session 16 marked ✅ shipped.

**Files modified (plugin):**
- `admin/css/citewp-aiso-admin.css` — full v3 rewrite
- `includes/Admin/Menu.php` — Dashboard panel renderer, rail brand area
- `includes/Admin/EditorPanel.php` — P31 consolidated meta box (new file)
- `includes/Admin/IconLibrary.php` — 20 Lucide SVG icons (new file)
- `includes/Admin/DashboardData.php` — `get_issue_count()` added, `get_top_crawlers()` extended
- `includes/Admin/ScoreMetaBox.php` — retired (logic absorbed into EditorPanel)
- `includes/Admin/SchemaMetaBox.php` — retired (logic absorbed into EditorPanel)
- `includes/Admin/Settings/Page.php` — light v3 styling
- `includes/Admin/LogsPage.php` — light v3 styling
- `includes/Plugin.php` — register EditorPanel, deregister old meta boxes

**Decisions made:**
- X20 — component spec compliance audit before manual browser verification (2026-05-01)

**Smoke test:** Manual browser verification passed across three review passes. Stats data flowing (108 visits this week, Top Crawlers rendered, KPI cards displayed, hero greeting personalized). No dedicated `/smoke-test` slash command found in `.claude/commands/` — slash command rebuild deferred per CLAUDE.md note (Session 7.5 carryover).

**npm build:** N/A — no JavaScript changes this session. CSS and PHP only.

**PHP lint:** Not run — PHP CLI not configured in LocalWP shell (per CLAUDE.md). No new PHP errors observed in debug.log during browser verification.

**WP.org status:** In review (submitted 2026-04-29). Awaiting approval email at hello@citewp.com. SVN commit held until approval confirmed per X16.

**Carryover into Session 17:**
- **WP.org SVN commit** — first task if approval email has arrived before Session 17 starts.
- **readme.txt + WP.org assets** — v0.7.0 changelog, screenshot updates, banner/icon review.
- **Anti-cloaking content** (S7) — landing page section + first blog post.
- **"Suggest a Feature" link** in admin — GitHub Discussions lean.
- **Page-builder canvas-mode awareness surface** — admin notice on Elementor/Divi/Bricks screens.
- **P33 (Posts/Pages stat split)** — Dashboard data layer. Still deferred.
- **Engine.php entities detector bug** — A11-gated. Still requires explicit user approval.
- **`citewp_aiso/metabox/tabs` filter `$context` arg** — Session 12 carryover, ships with EditorPanel follow-up if needed.

**Next session focus:** Session 17 — Phase 1.5 remaining: readme.txt + WP.org assets, anti-cloaking content (S7), "Suggest a Feature" link, page-builder canvas-mode awareness. Check WP.org approval status first — if approval email arrived, SVN commit is Task 1.

---

## Session 15 — v3 brand system reset, Citrine on Navy ✅

**Date:** 2026-04-30

**Deliverable:** Full v3 brand system shipped across Brain folder. P38 palette locked (18 tokens), P39 surface + layout verdicts, brand-kit-v3-citrine-on-navy.html canonical reference built, UI-DESIGN-SYSTEM.md fully rewritten under v3 with new Component Library section, X19 logged covering coherence audit + carry-over verdict discipline. **No plugin code shipped this session — P36 plugin code freeze remains in effect through Session 15.** Plugin admin migration to v3 is Session 16+ work via X13 pipeline.

**Brain edits (per X10 per-edit commit cadence):**
- `DECISIONS.md` — P38 logged (v3 palette: 18 tokens organized as 3 brand identity + 2 navy + 3 paper + 6 decorative tints + 4 score colors + 3 text + 1 border). Citrine `#E8D400` retained (user explicit: "I like poppy color"); reference CSS's softer `#F7D84A` rejected. Blue + Teal demoted to decorative tints only — re-skinned in reference: blue links/buttons → Citrine on navy / Obsidian on paper. P34 (Teal semantic citation/data role) and P35 (soft gray rail) explicitly superseded. SHA `16053c2`.
- `DECISIONS.md` — P39 logged covering three Step 2 verdicts: (Verdict 1) tagline placement amends P30 — moves from page header to rail, stacked under wordmark + plugin name lockup; (Verdict 2) surface-by-surface palette amends P26 with v3 specifics — admin pages full v3, WP Dashboard widget WP-native + score colors, post list column score colors only; (Verdict 3) Dashboard layout target amends P32 — hero card + 4-card stat row + 2-column lower section + Pro Tip footer composition. SHA `4a26bc7`.
- `DECISIONS.md` — X19 logged covering two paired rules: (1) coherence audit at session-start when amendment density crosses threshold (3+ amendments across 2+ consecutive sessions to same cluster); (2) carry-over verdict discipline when amending a P-row with dependents — each dependent gets explicit verdict, even when "principle survives." SHA `8a3cde9`.
- `Brain/brand/brand-kit-v3-citrine-on-navy.html` — 7-section brand kit demonstrating v3 design system: Hero / Architecture (three-name system) / Color (full P38 palette swatches) / Typography (Inter + JetBrains Mono on paper and navy) / Components (Dashboard composition: rail + hero card + 4-card stat row + AI Insights nested two-tone + Top Crawlers + Needs Attention + Quick Actions + Pro Tip footer) / Voice / Specs. Iterated through 4 review passes addressing user feedback (squared rail items, Lucide icons, two-tone AI Insights, score-band Cite Score color, KPI button styling). SHA `2f41530`.
- `Brain/design-reference/v3-reference-{dashboard,crawler-logs,cite-score}.png` — three ChatGPT-generated reference mockups committed (retroactive — Session 14 commit missed them). SHA `9535386`.
- `Brain/SESSION-15-BRIEF.md` — Step 5 stale line corrected (typography review marked RESOLVED by P37). SHA `800bda7`.
- `Brain/UI-DESIGN-SYSTEM.md` — full v3 rewrite (~800 lines, replaces v2 system). Major sections: Design Philosophy (v3 SaaS register, deliberate inversion of v2), Color System (full P38 + Surface 1/2/3 application), Typography (P37 stack), Spacing (8px grid survives), Layout Patterns (rail spec from P28 + P39 V1, Dashboard composition from P39 V3), **Component Library (NEW)** — 12 components catalogued with structural specs (Wordmark, Rail brand area, Hero Card, KPI Card, KPI Button, AI Insights two-tone nested, Top Crawlers Table, Needs Attention List, Quick Actions, Pro Tip Footer, Score Badge, Score Gauge, Panel Link), What NOT to Do (rewritten — 12 new v3 rules + 3 surviving from v2), Cross-References. SHA `4050c47`.

**Decisions made:**
- P38 — v3 palette locked (Citrine retained, Navy adopted, Blue/Teal demoted to decorative tints)
- P39 — three Step 2 verdicts (tagline placement, surface palette, Dashboard layout) under v3
- X19 — coherence audit + carry-over verdict discipline (paired rules)

**Decisions superseded this session:**
- P34 — Teal semantic citation/data role (superseded by P38; Teal now decorative tint)
- P35 — Soft gray rail (superseded by P38; rail is now navy)
- P26 — surface-by-surface palette principle survives but specifics replaced (P39 V2 amends)
- P30 — tagline wording survives but placement moves rail (P39 V1 amends)
- P32 — three-column principle survives but layout target updated (P39 V3 amends)

**v2 vs v3 register difference:** v2 chased "look like the best WordPress plugin" (WP-native styling, no custom fonts, system color scheme). v3 deliberately inverts: "look like a modern SaaS product" (navy shell + Citrine accents + Inter typography + Lucide icons + decorative tint orbs). Buyer signal: mortgage industry + regulated industry buyers (S2) read WP-native as "free WordPress thing"; SaaS register reads as "professional software worth paying for." Plus: v3 register works across plugin admin + citewp.com website + Pro SaaS app — single visual language for three surfaces. v2 only worked on plugin and forced website + Pro to invent their own.

**Brand kit iteration history (Session 15, in-conversation):**
- Pass 1 (medium fidelity, ~630 lines) — structural skeleton with placeholder square icons. User feedback: rail brackets need to be squared not rounded, icons needed, cards should be same height, Cite Score 44 needs threshold color, AI Insights needs panel title.
- Pass 2 (~715 lines) — Lucide-style SVG icons, squared rail items + 3px Citrine left edge, paper-bg main column (not navy), trend percentages on KPI cards, hero stat icons added, AI Insights header strip with BETA badge, score-band-red on Cite Score 44, half-Citrine half-Blue button test.
- Pass 3 — all buttons unified to Blue text + paper-tinted background (Blue won the side-by-side); AI Insights wrapped in nested two-tone teal/purple-tinted card; hero card Cite Score back to white (red on navy looked bad); KPI cards restructured to icon-orb-left-of-title.
- Pass 4 (final, ~775 lines) — Improve buttons → Blue, panel-link → Blue, Top Crawlers reduced from 4 to 3 rows, KPI orbs reduced from 36×36 to 28×28 (icons 14px — ~25% larger than 13px title text), hero stat icons colorized purple/teal/orange.

**Final user assessment of v3:** "Serious upgrade." (Verbatim transcript not rendered here — full discussion in conversation history.)

**Plugin code status:** No plugin code shipped Session 15. P36 freeze remains active through end-of-session. Last plugin commits remain Session 14's three CSS commits (`6ae59cf` single-row header, `5633a69` soft gray rail, `d09b8a6` Teal tokens) — all stay in dev-branch history as artifact, will be superseded by Session 16 v3 migration before any user sees them. v0.6.0 stays in WP.org review hold pending coordinated launch with citewp.com website + Pro app.

**npm build:** N/A (no plugin code touched). **PHP lint:** N/A. **Browser verification:** brand kit visually verified by user in browser across 4 iteration passes.

**Carryover into Session 16:**
- **Plugin admin migration to v3 brand system** (X13 pipeline — multi-file CSS + PHP). Implements UI-DESIGN-SYSTEM.md v3 spec across `admin/css/citewp-aiso-admin.css`, `Menu.php` panel renderers, `PageHeader.php` (recreate — was deleted Session 13, needs to come back for the new rail + hero structure), Settings page, Logs page. All 18 P38 tokens active. Replaces Session 14's superseded CSS commits.
- **Inter + JetBrains Mono fonts:** add `inter-{400,500,700,800}.woff2` and `jetbrains-mono-{400,500,700}.woff2` to `admin/fonts/`. Add `@font-face` blocks at top of `citewp-aiso-admin.css`. Drop Plus Jakarta Sans and Fraunces font files (P37 dropped both).
- **WP Dashboard widget review** — verify it stays WP-native per P39 Verdict 2 (Surface 2). Score colors allowed for emphasis, no Citrine, no decorative tints, no Inter, WP system font stack only. Likely already correct from Session 12 implementation; needs verification.
- **Post list score column review** — verify minimal styling per P39 Verdict 2 (Surface 3). Score colors only. Already correct from prior sessions; needs verification.
- **P31 metabox consolidation styled to v3** — universal meta box position `'normal'` + tabbed structure from Session 13/14 carryover. Light v3 styling per UI-DESIGN-SYSTEM.md (sits between Surface 1 and WP-native).
- **Posts/Pages stat split** (number pre-allocated as P33, no DECISIONS.md row yet — log before Session 16 Dashboard build per SESSION-15-BRIEF.md guidance) — deferred from Session 13/14. Still relevant for Session 16 Dashboard data layer.
- **Engine.php entities detector bug** (A11-gated, dedicated session) — `ContentAnalysis.php` line ~165 `count_entities()` regex bug. Requires explicit user approval before any Engine.php touch.
- **DashboardData draft-exclusion comment** — `WHERE p.post_status = 'publish'` inline comment.
- **`citewp_aiso/metabox/tabs` filter `$context` arg** — Session 12 carryover, should ship with P31 build.
- **readme.txt + WP.org assets** — deferred until coordinated launch (website + Pro app + v0.7.0).

**Next session focus:** Session 16 — plugin admin v3 migration via X13 pipeline. Long session expected (multi-file refactor with PHP + CSS + JS coordination + browser verification + code-reviewer pass).

---

## Session 14 — Brain folder backup setup + brand system reset (X10 + P36 + P37) ✅

**Date:** 2026-04-30

**Retroactive entry:** This entry was not written at Session 14 close (end-of-session protocol gap caught at Session 15 start by Claude Code). Reconstructed from DECISIONS.md rows logged + git commit history + chat history. SHA references verified against actual git log.

**Deliverable:** Brain folder backed up to separate private GitHub repo (X10 executed); plugin admin styling experiments shipped (single-row header, soft gray rail, Teal tokens) then reset entirely when brand system was rebooted to Citrine on Navy. Three carry-over P-rows (P30, P31, P32) and one new X-row (X18) logged. Plugin code freeze imposed at session close pending Session 15 fleshout of v3 system.

**Brain repo creation (X10 executed):**
- New private GitHub repo `bradleyswpc/citewp-brain` created.
- Brain folder initialized with git, `.gitignore` configured (excludes `Brain-BACKUP/` same-disk redundant copy, OS junk, editor temp).
- Git identity configured (`bradleyswpc <131623150+bradleyswpc@users.noreply.github.com>` after initial placeholder identity discovered + corrected).
- Per-edit commit + push cadence operational from Session 14 forward.
- Brand assets relocated from `Desktop\CiteWP\brand\` → `Brain\brand\` to bring them under Brain repo cadence. `Brain\brand\` is canonical going forward.
- `brand-kit-v2-yellow.html` renamed to `brand-kit-v2-yellow-ARCHIVED.html` at session close per P36 closeout.

**Plugin code shipped (now superseded artifacts, will not reach users):**
- `6ae59cf` — single-row header layout per P30 work (lockup + tagline below in single column)
- `5633a69` — soft gray rail revert (replaced Session 13's experimental Obsidian rail) per P35
- `d09b8a6` — Session 14 CSS tokens (Teal semantic tokens per P34, soft gray rail tokens per P35)

**These plugin commits stay in dev-branch history but will be superseded by Session 16 v3 migration before any user sees them.** v0.6.0 stays in WP.org review hold; no SVN push to live until coordinated launch.

**Decisions made:**
- P30 — canonical brand tagline "SEO gets you ranked. CiteWP gets you cited." locked for all brand surfaces (placement amended later by P39 V1)
- P31 — universal meta box position `'normal'` + tabbed structure mirroring Yoast/Rank Math (P22 amendment)
- P32 — P27 amendment: column-count and card-grid pattern are distinct concerns; three-column permitted when columns hold distinct content types
- P34 — Signal Teal `#006D70` added as citation/data semantic accent (later superseded by P38 — Teal demoted to decorative tint)
- P35 — Admin rail soft gray `#F1EFE8` (Gray-50) with Obsidian text + 3px Citrine left-edge active (later superseded by P36/P38 — rail is now navy)
- P36 — **brand system reset to Citrine on Navy.** Mid-session, user surfaced ChatGPT-generated dashboard mockup as visual reference. Three motivations: (1) v2 Citrine + Obsidian palette accumulated complexity that read sterile; (2) pre-launch timing meant zero users would see the reset; (3) Citrine on Navy works across plugin + website + Pro app where v2 only worked on plugin. P21, P30, P34, P35 superseded as a set pending Session 15 fleshout. **Plugin code freeze imposed:** no further admin styling touches the plugin until Session 15 ships the new system.
- P37 — typography reset (Session 14 close addendum). Inter for UI + headings + wordmark; JetBrains Mono for numbers; Plus Jakarta Sans + Fraunces dropped entirely. Supersedes typography portion of P21.
- X18 — process decisions (X-rows) with deferred execution must be re-surfaced at every session-start protocol until executed, amended, or withdrawn. Soft deferrals must convert to hard task entries. Session-start grep `\[EXEC:` audit. Caught the X10 5-session gap (Brain backup deferred since Session 8) at Session 14 start.

**Brain edits Session 14 timeline:**
- Session start: X10 [EXEC: Phase 1.5] surfaced and executed — Brain repo bradleyswpc/citewp-brain created, per-edit cadence operational.
- Mid-session: P34 + P35 logged after Session 13 Obsidian rail experiment was reverted to soft gray + Teal added as semantic accent.
- Mid-session: brand kit v2-yellow visual review prompted reference mockup discussion → P36 brand system reset.
- Session close: P37 logged as addendum (typography settled before Session 15 starts).
- Session close: X18 logged from the deferred-execution drift pattern X10's gap exposed.
- Session close: SESSION-15-BRIEF.md written with rewrite scope.
- Session close: brand-kit-v2-yellow.html renamed to brand-kit-v2-yellow-ARCHIVED.html.
- Session close: UI-DESIGN-SYSTEM.md retained text + header note flagging pending Session 15 rewrite.

**npm build:** ✅ clean (CSS-only changes). **PHP lint:** ✅. **debug.log:** ✅ clean. **Browser verification:** Session 14 plugin commits visually verified before brand reset; reset rendered all of them artifact.

**Carryover into Session 15 (per SESSION-15-BRIEF.md, executed):**
- ✅ Step 1 — palette decisions (P38 logged Session 15)
- ✅ Step 2 — carry-over verdicts on P21/P30/P32/P34/P35/P26 (P39 logged Session 15)
- ✅ Step 3 — brand-kit-v3-citrine-on-navy.html build (committed Session 15)
- ✅ Step 4 — UI-DESIGN-SYSTEM.md major rewrite (committed Session 15)
- ✅ Step 5 — X19 logged (Session 15)
- ✅ Step 6 — SESSION-LOG.md retroactive Session 14 + Session 15 entries (this file, Session 15 close)
- Step 7 — handoff to Session 16 (Session 15 close, this entry)

**Next session focus (at Session 14 close):** Session 15 — full v3 brand system fleshout per SESSION-15-BRIEF.md. Plugin code freeze remains in effect through Session 15. (Session 15 executed all 7 brief steps; v3 system now ready for Session 16 plugin migration.)

---

## Session 13 close — Admin layout refactor + brand identity (P27 + P28 + P29) ✅

**Date:** 2026-04-29

**Deliverable:** Full admin layout refactor replacing the Session 12 hybrid (WP submenus + horizontal top tabs) with a single `add_menu_page`, Obsidian left rail, URL hash dispatch, and shared card surface. Visual polish pass brought the register in line with WP Rocket's reference. Brand identity locked: `[CiteWP]` wordmark with Citrine brackets, "AI Search Optimizer" plugin name inline, and canonical tagline "SEO gets you ranked. CiteWP gets you cited." in Fraunces 800 regular at 16px (Georgia fallback until `fraunces-800.woff2` loaded).

**Shipped / Modified:**
- `includes/Admin/Menu.php` — fully rewritten. Single `add_menu_page` (no `add_submenu_page`). `render_page()`: left rail loop, panel loop, inline JS with 5-priority `resolveSlug()` (hash → `citewp_section` param → `settings-updated` flag → sessionStorage → default), `hashchange` listener, click handlers with `history.pushState`. `citewp_aiso/admin/nav` filter extended with render-callback schema (X15 — FB28/FB30/FB34 register panels via this filter). `render_dashboard_panel()`: P27 single-column layout, SVG gauge, Needs Attention list, quick-action buttons. Header: `__brand` wrapper → `__lockup` (wordmark with Citrine `__bracket` spans + `__plugin-name`) + `__tagline` paragraph (canonical brand tagline). Null-guards on `module()` callables; `wp_nonce_url()` for regenerate button.
- `includes/Settings/Page.php` — stripped to pure panel renderer. Removed submenu registration, `.wrap` wrapper, `PageHeader` nav. Settings inner-tab state moved to `localStorage` (avoids hash conflict with outer `#settings`). Redirect uses `Menu::SLUG_PARENT` + `citewp_section=settings`.
- `includes/Admin/LogsPage.php` — stripped to pure panel renderer. `maybe_init_table()` now guards on `SLUG_PARENT`. List table form submits `page=citewp` + hidden `citewp_section=crawler-logs` field.
- `includes/Admin/PageHeader.php` — **deleted**. Wordmark inlined in `Menu::render_page()`.
- `admin/css/citewp-aiso-admin.css` — appended ~200 lines. Shared card surface (`.citewp-aiso-page` = outer card, `.citewp-aiso-rail` = 220px Obsidian strip, `.citewp-aiso-main` = padded white content area; Obsidian/white contrast replaces explicit right-border divider). Left rail BEM block: two-line items (`__item-label` uppercase + `__item-desc`), light gray text on Obsidian, white on hover/active, Citrine 3px left-edge accent + `rgba(232,212,0,0.08)` tint on active. Panel visibility (`display:none`/`display:block`). Stat-row Dashboard styles. Primary button brand override (`.toplevel_page_citewp .button-primary` → Obsidian fill, Citrine border on hover). Header card chrome: `__brand` column-flex wrapper, `__lockup` row, `__plugin-name`, `__tagline` (Fraunces/Georgia serif 800 normal 16px `--citewp-citrine-text`, `margin-top: 4px`), `__bracket` Citrine color. Responsive stack at ≤782px with `rgba` bottom divider. `--citewp-citrine-text: #8A7800` confirmed in `:root`.

**Commits:** `3863b1f` (Settings strip), `f2b9fe4` (LogsPage strip), `00d359a` (Menu rewrite), `8d6164d` (null-guard + admin_url hash fix), `5d47f52` (CSS Phase 2), `6104dce` (delete PageHeader + docs), `40f1937` (6-fix polish pass), `0352b9f` (wp_nonce_url fix), `78a7a1c` (rail descs + no flood-fill + Citrine brackets), `2914762` (header lockup container), `ff969db` (tagline → Plus Jakarta Sans), `ef59ab4` (canonical tagline below lockup), `9adcb7a` (shared card surface), `d18223d` (session log + P30), `8bd508f` (Obsidian rail + tagline Fraunces 800 normal 16px). Pushed to `origin/main`.

**Decisions made:**
- P30 logged: canonical brand tagline "SEO gets you ranked. CiteWP gets you cited." — locked wording for all brand surfaces. Fraunces 800 regular (italic dropped after visual review). `--citewp-citrine-text` color. P21 verbal-identity amendment.

**Post-session Brain updates:**
- `DECISIONS.md` — P30 added. Header datestamp updated.
- `UI-DESIGN-SYSTEM.md` — left rail spec, shared card surface, lockup/tagline component rules should be updated in Session 14 to reflect final implementation (deferred — visual pass confirmed in browser first).

**Font carryover:** `fraunces-800.woff2` not yet in `admin/fonts/`. Tagline renders in Georgia/serif fallback. To activate Fraunces: drop `fraunces-800.woff2` into `admin/fonts/`, add `@font-face` (weight 800, normal) near top of `citewp-aiso-admin.css`. `plus-jakarta-sans-500.woff2` also not loaded — `__plugin-name` renders at weight 800 fallback until added.

**npm build:** ✅ clean (no JS changes). **PHP lint:** ✅. **debug.log:** ✅ clean. **Browser verification:** pending user confirmation — Obsidian rail is experimental; revert target is `9adcb7a` if it doesn't land.

**Carryover into Session 14:**
- **Posts vs Pages Dashboard stat split** (new, from Bradley's call): Replace single "Avg Cite Score" stat with two items — "Avg Post Score" + "Avg Page Score". Update `DashboardData` with optional `$post_type` param or separate methods. Empty state "—" when 0 scored posts/pages of that type. Log as **P32** in DECISIONS.md before build (P30 is the tagline decision, P31 is the meta box amendment, both logged this session).
- **Stat-row visual consistency** (new): 3-stat row (Posts avg + Pages avg + Bot visits) needs consistent chrome and vertical alignment. Current gauge vs bare-number treatment is inconsistent.
- **Engine.php entities detector bug** (new, A11-gated, NOT Session 14 scope — dedicated session needed): `ContentAnalysis.php` line ~165 `count_entities()` regex returns 0 on 20+ entity posts. Three regex flaws: (1) `{1,4}` min-2-word match excludes single-word entities (ChatGPT, Claude), (2) `[a-z]+` after capital excludes acronyms + CamelCase (AIOSEO, ChatGPT), (3) post-sentence-punctuation words excluded. Requires explicit user approval before any Engine.php touch.
- **DashboardData draft-exclusion comment** (minor): `WHERE p.post_status = 'publish'` is correct behaviour confirmed — add inline code comment explaining constraint; possible future tooltip "Includes published posts only".
- **Font loading**: `fraunces-800.woff2` + `plus-jakarta-sans-500.woff2` → `admin/fonts/`; add `@font-face` blocks.
- **UI-DESIGN-SYSTEM.md update**: Document final left rail layout, shared card surface, lockup/tagline component, Obsidian rail pattern, P30 tagline spec.
- `citewp_aiso/metabox/tabs` filter needs `$context` arg (`'score'`/`'schema'`) before Pro ships (carried from Session 12).
- **Session 14 primary**: readme.txt polish + WP.org asset prep (per P28 deferral). Likely longer session than originally scoped given additions above.

**Next session focus:** Session 14 — readme.txt + WP.org assets. Font loading. Dashboard stat split (P32). UI-DESIGN-SYSTEM.md update.

---

## Session 12 close — Admin UI polish pass (WP Rocket IA) ✅

**Date:** 2026-04-29

**Deliverable:** Full admin UI polish across all three CiteWP admin pages (Dashboard, Settings, Crawler Logs) and the WP Dashboard widget. WP Rocket-inspired IA with wordmark top nav, card-based sections, toggles, and X15 filter hooks on all four surfaces.

**Shipped / Modified:**
- `admin/css/citewp-aiso-admin.css` — new file (571 lines). Two `@font-face` rules (Plus Jakarta Sans 800, JetBrains Mono 400). `:root` design tokens (citrine `#E8D400`, obsidian, spacing/radius/shadow scale). All admin UI components: nav, cards, gauge, badges, stats banner, tabs, toggles, form sections, empty states. Toggle ON state uses `var(--wp-admin-theme-color)`, not citrine. BEM naming: `.citewp-aiso-nav` block.
- `admin/fonts/plus-jakarta-sans-800.woff2` — self-hosted font (12 KB, jsDelivr).
- `admin/fonts/jetbrains-mono-400.woff2` — self-hosted font (21 KB, jsDelivr).
- `includes/Admin/PageHeader.php` — new file. `render_nav(string $current_page)` static method. Four nav items. `apply_filters('citewp_aiso/admin/nav', $defaults)` (X15 hook #1).
- `includes/Admin/DashboardData.php` — new file. Service extracted from `DashboardWidget`. Four public methods: `get_average_score()`, `get_visit_trend()`, `get_top_crawled_pages()`, `get_lowest_scoring_posts()`. Shared by both `Menu.php` and `DashboardWidget.php`.
- `includes/Admin/Menu.php` — fully rebuilt. Added `enqueue_assets()` for CSS-only load on CiteWP screens. `render_dashboard()`: DashboardData queries, SVG semicircle gauge, 3-col card grid, `apply_filters('citewp_aiso/dashboard/cards', [])` (X15 hook #4), PageHeader nav.
- `includes/Settings/Page.php` — fully rebuilt. PageHeader nav, tabbed layout (general / crawler-detection / llms-txt), `apply_filters('citewp_aiso/settings/tabs', $default_tabs)` (X15 hook #2), toggle switches, card sections, inline JS tab switcher with URL hash persistence. Removed `inline_styles()` and its `admin_head` hook.
- `includes/Admin/LogsPage.php` — updated. PageHeader nav added. Stats banner migrated from inline styles to CSS classes. `inline_styles()` method deleted.
- `includes/Admin/DashboardWidget.php` — updated. Delegated four data methods to `DashboardData`. Added `apply_filters('citewp_aiso/dashboard/cards', [])` stub. Removed stale `Schema` import.
- `includes/Admin/ScoreMetaBox.php` — updated. Added `apply_filters('citewp_aiso/metabox/tabs', [])` stub (X15 hook #3).
- `includes/Admin/SchemaMetaBox.php` — updated. Same `apply_filters('citewp_aiso/metabox/tabs', [])` stub added.

**Commits:** `d0d736d`, `e212729`, `624811b`, `1d8eaf7`, `f72750d`, `8517e11`, `d6fa62b`, `06e2134`, `de44713`, `7771613`, `10a994e`, `9370db3`, `bc0e881`, `ad47bc4`. Pushed to `origin/main`.

**X15 extensibility hooks added:**
1. `citewp_aiso/admin/nav` — admin top nav items
2. `citewp_aiso/settings/tabs` — settings page tabs
3. `citewp_aiso/metabox/tabs` — score + schema meta box tabs
4. `citewp_aiso/dashboard/cards` — dashboard summary cards

**Decisions made (code session):** None new. Existing P19/P26/X15 drove all choices.

**Post-session Brain updates (Desktop, 2026-04-29):**
- P25 logged: plugin admin vs SaaS dashboard architectural separation (was phantom reference — resolved).
- P26 logged: P16 amended to polish-by-surface; UI-DESIGN-SYSTEM.md is the per-surface spec authority.
- P27 logged: single-column content areas for Phase 1.5 admin pages; card grid on Dashboard dropped.
- P28 logged: left rail nav inside the plugin page; WP submenus removed; Session 13 scoped as layout refactor.
- P29 logged: Session 13 implementation specifics — single `add_menu_page` (no submenus), URL hash dispatch (`admin.php?page=citewp#dashboard`), JS `hashchange` handler, all sections in DOM with `display:none` toggled client-side. Server-side separate slug dispatch (Rank Math pattern) explicitly rejected.
- X16 logged: end-of-session cross-reference + outcome accuracy verification rule.
- X17 logged: FEATURE-BACKLOG.md candidates use `FB` prefix (FB28–FB35); DECISIONS.md keeps `P/X/A/R/S` — distinct namespaces enforced permanently.
- UI-DESIGN-SYSTEM.md updated: surface-by-surface palette + left rail layout pattern. Spec current.
- Phase 1.5 sequence updated in master file: Session 13 = layout refactor, Session 14 = readme.txt + WP.org assets, Session 15+ = anti-cloaking.

**Note on cross-references:** Any entry above this that says "P28–P35 candidates" refers to what are now FB28–FB35. Per X17, preserved as-written historical artifacts — not retroactively edited.

**Forward design note (pre-Pro):** `citewp_aiso/metabox/tabs` fires from both `ScoreMetaBox` and `SchemaMetaBox` with the same key and no context argument. Before Pro ships, add a `$context` string argument (`'score'` / `'schema'`) so Pro can target each independently.

**npm build:** ✅ clean. **PHP lint:** ✅ all 8 new/modified files clean. **Browser verification:** ✅ all three admin pages, WP Dashboard widget, tab switching + hash URLs, toggles, CSV export, gauge rendering.

**Carryover into Session 13:**
- Layout refactor per P28 + P29: single `add_menu_page` (remove `add_submenu_page` for Logs + Settings), URL hash dispatch (`admin.php?page=citewp#dashboard` / `#settings` / `#crawler-logs`), JS `hashchange` handler, all section panels in DOM. `citewp_aiso/admin/nav` filter is the registration point for left rail items.
- Layout refactor per P27: drop 3-col Dashboard card grid; rebuild as single-column content area.
- UI-DESIGN-SYSTEM.md left rail spec is current — read it before writing the plan.
- `citewp_aiso/metabox/tabs` filter needs a `$context` arg before Pro ships.

**Next session focus:** Session 13 — Layout refactor per P27 + P28 + P29. Single `add_menu_page`, URL hash dispatch, JS hashchange handler, single-column content areas. Readme.txt + WP.org assets deferred to Session 14 per P28.

---

## Session 11 close (final) — Audit follow-up, FEATURE-BACKLOG.md, X15 ✅

**Date:** 2026-04-29

**Deliverable:** Log Rank Math audit findings as backlog candidates; add filter-extensibility rule (X15); wire backlog scan into `/session-start` protocol; add 6th Brain file. Pure documentation session — no code shipped.

**Shipped / Modified:**
- `Brain/FEATURE-BACKLOG.md` — new file. P28 (Cite Audit), P29 (Schema expansion), P30 (Cite Bridges), P31 (Role Manager), P32 (migration tools), P33 (email reports), P34 (Global Meta defaults), P35 (AI Citation Competitor Analyzer), Cite Tracker architecture reference, Rank Math pattern observations. Reference material only — not a roadmap.
- `Brain/00-CITEWP-MASTER.md` — rule 2 updated to "6 active files: master, decisions, scoring-rubric, competitors, ui-design-system, feature-backlog"; rule 7 added (backlog scan requirement per X15); Active Brain files table expanded to 6 rows; Horizon FEATURE-BACKLOG.md cross-reference added with X15 note; last-updated bumped.
- `Brain/DECISIONS.md` — X15 appended (filter-extensibility: settings tabs, meta box tabs, admin nav rail, dashboard widget cards all use `apply_filters` from first build); last-updated bumped.
- `.claude/commands/session-start.md` — Hard rules updated to "6 active files" list; Backlog Scan section appended (6-step protocol, surface taxonomy, format template, skip-is-a-violation enforcement). Committed `fad4f68`, pushed to `origin/main`.

**Decisions made:** X15 (filter-extensibility requirement for all UI surfaces hosting FEATURE-BACKLOG.md candidates).

**No code, no JS build, no debug.log errors.**

**Verified:** All file edits confirmed by read-back. Plugin commit pushed to GitHub (`fad4f68`). Brain files protected by robocopy backup.

**Carryover into Session 12:**
- Add P25 (plugin admin vs SaaS dashboard architectural separation) to DECISIONS.md Product table — text drafted in Session 11, deferred twice
- Manual browser verification: confirm meta boxes absent in Gutenberg, present in Classic/Elementor/Divi; TypeError resolved; Structure sub-score > 0 on post with H2/H3/list content

**Next session focus:** Session 12 — UI polish pass per `Brain\UI-DESIGN-SYSTEM.md` (P19/X7). **Before writing the plan, run the backlog scan per X15**: Session 12 touches admin nav, settings tabs, dashboard widget cards, and meta box layout — P28, P30, P34 all overlap these surfaces and must be confirmed reserved (via filter hooks) or explicitly deferred.

---

## Session 11 close — Bookkeeping corrections ✅

**Date:** 2026-04-29

**Deliverable:** Correct Session 11 close bookkeeping. No code shipped.

**Changes made:**
- `Brain/DECISIONS.md` — P17 rewritten: plugin name locked as "CiteWP AI Search Optimizer – Optimize Content for AI Engines" in both `Plugin Name:` file header and readme.txt. The Session 11 amendment recommending shortening the file header was wrong and reverted. Added cross-reference to X14.
- `Brain/DECISIONS.md` — X14 added (Process table): AI agents must cite 3+ comparable products before recommending changes to user-facing naming, branding, or copy fields. "Shorter / cleaner" instinct is often wrong when a category convention exists (e.g. Yoast SEO's 60+ character display name in WP admin is the convention, not an exception).
- `memory/project_v070_checklist.md` — "Plugin Name: shortened to AI Search Optimizer" item removed. Note added: name stays as submitted per P17/X14.

**Decisions made:** X14 (agent naming/copy change discipline — cite 3+ comparables first).

**No code, no build, no debug.log check.**

**Verified:** DECISIONS.md edits confirmed by read-back. Checklist confirmed by read-back.

**Carryover into Session 12:**
- Add P25 (plugin admin vs SaaS dashboard architectural separation) to DECISIONS.md Product table — text drafted in Session 11, deferred to Session 12 close
- Manual browser verification: confirm meta boxes absent in Gutenberg, present in Classic/Elementor/Divi; TypeError resolved; Structure sub-score > 0 on post with H2/H3/list content

**Next session focus:** UI polish pass per `Brain\UI-DESIGN-SYSTEM.md` (P19/X7).

---

## Session 11 — Universal Score + Schema Meta Boxes ✅

**Date:** 2026-04-29

**Deliverable:** Add native WP meta boxes for Cite Score and Schema Suggestions per P22, so both features are visible to non-Gutenberg users (Classic Editor, Elementor, Divi, Beaver Builder, Bricks). Brings ~95%+ editor coverage. Per P24 confirmed Session 11, meta boxes suppress when Gutenberg is active — sidebar and meta box surfaces are mutually exclusive per editor.

**Shipped:**
- `includes/Admin/ScoreMetaBox.php` — Cite Score display + recalculate button. Renders four states (scored / not yet scored / loading / error). Reads from post meta directly for display; calls existing REST recalculate endpoint via inline fetch.
- `includes/Admin/SchemaMetaBox.php` — Schema Suggestions with Article + FAQPage detection. Calls `Schema\Generator` directly in PHP (no REST round-trip). Clipboard-only copy action with advisory note (X12 compliant). Fade timer cleared on repeated clicks.
- `includes/Plugin.php` — wired both new modules into `boot()` under `is_admin()` block.
- Gutenberg-suppression logic — both meta boxes check `use_block_editor_for_post_type()` at registration; only appear when Gutenberg is NOT active.
- `src/sidebar/index.js` — `PluginDocumentSettingPanel` import moved from `@wordpress/edit-post` to `@wordpress/editor` per WP 6.6 deprecation. `npm run build` passes 0 warnings.

**Workflow validation:**
- Full Superpowers + code-reviewer pipeline used end-to-end for the first time (X13 logged as a result).
- Subagent dispatch: 2 parallel agents for ScoreMetaBox + SchemaMetaBox, sequential for Plugin.php wiring.
- 9 bugs caught across 2 code-reviewer rounds before commit.
- Manual browser verification caught 3 additional issues post-commit: duplicate Schema Suggestions panel in Gutenberg, PluginDocumentSettingPanel deprecation warning, unrelated TypeError on post-new.php.
- Performance verified: 82ms recalculate on no-throttle, ~2s on Slow 3G.

**Decisions made:** P24 (meta box / Gutenberg mutual exclusion), X13 (Superpowers + code-reviewer pipeline as required for multi-file feature work).

**Verified:**
- Cite Score meta box renders all four states correctly
- Recalculate succeeds, updates badge + sub-scores + timestamp in place, no page reload
- Schema Suggestions clipboard copy works, advisory note appears and fades
- "Already detected" badge logic correct (root-level @type collection, no sub-node noise)
- "No FAQ content detected" message appears for posts with < 2 Q&A pairs
- Capability checks aligned with REST permission_callback
- Hook priority 20 — no visual conflict with Yoast/Rank Math
- Build passes, no PHP errors in debug.log

**Carryover into Session 12:**
- Manual browser verification of Gutenberg-suppression fix (confirm meta boxes do NOT appear in Gutenberg, sidebar surfaces do; confirm meta boxes DO appear in Classic/Elementor/Divi)
- Manual verification of TypeError fix (confirm error gone after deprecation import update)
- Score test post with deliberate H2 + H3 + bulleted list structure, verify Structure sub-score > 0 (flagged in Session 11 as possible silent scoring bug — low priority, low cost to test)

**Next session focus:** UI polish pass per `Brain\UI-DESIGN-SYSTEM.md` (P19/X7). Tabbed top nav across CiteWP admin pages, card-based settings layout, toggle switches over checkboxes, score gauge in dashboard widget, empty states with explanatory copy. Pending UI strategy sessions before code begins.

---

## Session 10 — Schema Generator (Phase 1.5) ✅

**Date:** 2026-04-28

**Deliverable:** Build the JSON-LD schema generator (P14) — auto-suggest Article and FAQPage schema markup from post content, surfaced as a "Schema Suggestions" panel in the Gutenberg Document Settings sidebar.

**Shipped:**
- `includes/Schema/Generator.php` — new `CiteWP\Aiso\Schema\Generator` service:
  - `generate_article_schema(WP_Post)`: complete Article JSON-LD (headline, dates, description, author, featured image, publisher with site icon → custom logo fallback, mainEntityOfPage)
  - `generate_faq_schema(WP_Post)`: FAQPage JSON-LD when ≥ 2 question/answer pairs found; H2/H3 detection by question-word prefix OR trailing `?`; first `<p>` before next heading as answer; returns `[]` if < 2 pairs
  - `detect_existing_types(WP_Post)`: top-level `@type` values from JSON-LD blocks in post_content only (not recursive — prevents nested sub-nodes like Person/Organization/WebPage from appearing as independent detected types)
  - Internal `ContentAnalysis` caching: one `apply_filters('the_content')` call per request
- `includes/Rest/SchemaController.php` — `GET /citewp/aiso/v1/schema/{post_id}` → `{article, faqpage|null, detected[]}`, permission `current_user_can('edit_post')`
- `src/sidebar/index.js` — `SchemaSuggestions` component via `PluginDocumentSettingPanel` (`@wordpress/edit-post`) in Document Settings sidebar (always visible; separate from the Cite Score `PluginSidebar`):
  - Article + FAQPage rows: Insert button, "Already detected" badge, "✓ Added" badge
  - FAQPage empty state: "No FAQ content detected (need ≥ 2 Q&A pairs)"
  - Other detected types: read-only "X schema detected — more types coming soon"
  - Re-fetches after post save (mirrors ScoreSidebar `wasSaving` pattern)
  - Block inserted via `insertBlock(block)` with no index (safe append, avoids root-count-only error with nested blocks)

**Modified:**
- `includes/Plugin.php` — wired `Rest\SchemaController` into `boot()` outside `is_admin()` block
- `build/index.js` + `build/index.asset.php` — recompiled; new dependencies: `wp-blocks`, `wp-edit-post`

**Bugs caught and fixed during code review:**
- FAQ `<p>` extraction now scoped to content before next heading (prevents block-injected markup capturing wrong answer)
- `insertBlock(block)` without index (was `insertBlock(block, getBlockCount())` — root-count broken for nested blocks)
- `setInserted({})` moved before try block in `fetchSchema` (prevents stale badge on failed re-fetch)
- `detect_existing_types()` changed from recursive to root-level-only collection (fixes Person/Organization/WebPage noise)

**Decisions made:** P20 (DECISIONS.md) — schema generator implementation choices.

**Verified:**
- "Schema Suggestions" panel appears in Document Settings sidebar
- Article Insert → Custom HTML block at end of post with valid Article JSON-LD
- "Already detected" badge shown after autosave + re-fetch
- "No FAQ content detected" shown for posts with no FAQ structure
- Person/Organization/WebPage sub-type noise removed
- REST endpoint returns 403 (not 404) without auth — route registered, permission_callback active
- No debug.log PHP errors
- `npm run build` 0 warnings

**Carryover into Session 11:** None — Session 10 deliverable complete.

**Next session focus:** Phase 1.5 — UI polish pass per `UI-DESIGN-SYSTEM.md` (P19/X7): tabbed top nav, card-based settings layout, toggle switches, score gauge in dashboard widget, empty states. User has strategy sessions on UI design pending before this session begins.

---

## Session 9 — WP.org Submission ✅

**Date:** 2026-04-28

**Deliverable:** Complete and submit the WP.org plugin package for `ai-search-optimizer` v0.6.0.

**Shipped:**
- `assets/` folder — all WP.org submission assets committed:
  - `icon.svg` (copied from Desktop/CiteWP/logos/, `[A]` mark, Citrine bg, SMIL animated)
  - `icon-128x128.png` + `icon-256x256.png` (from logo-export-kit.html, fixed `[C]`→`[A]` bug in `drawIcon()`)
  - `banner-772x250.png` + `banner-1544x500.png` (designed in Canva: `[AISO]` wordmark, tagline, 3 feature callouts, Citrine divider)
  - `screenshot-1.png` through `screenshot-5.png` (Gutenberg sidebar, post list column, dashboard widget, crawler logs, settings)
- `readme.txt` — `== Screenshots ==` section added (5 descriptions matching screenshot order)
- `.distignore` — `assets/` added to exclusion list
- `.gitattributes` — `export-ignore` rules for all dev-only files; `git archive` now produces a clean distribution zip
- `.gitignore` — `build/` removed from ignored files; compiled JS now tracked so distribution zip includes the Gutenberg sidebar
- `src/sidebar/index.js` — two fixes:
  - `chartLine` → `chartBar` (chartLine removed from @wordpress/icons; build now compiles with 0 warnings)
  - Recalculate hint text moved below button (was clipping in narrow sidebar at `justifyContent: space-between`)
- `logos/logo-export-kit.html` — fixed `[C]`→`[A]` in `drawIcon()` (icon PNGs now match plugin identity, not parent brand)
- `ai-search-optimizer-v0.6.0.zip` — built via `git archive --format=zip`, submitted to WP.org plugin directory

**Commits (4):**
- `feat: WP.org submission assets, screenshots, sidebar icon + hint text fix (v0.6.0)`
- `chore: add .gitattributes export-ignore rules for WP.org SVN package`
- `chore: track build/ in git for WP.org distribution`
- pushed to `origin/main` (`0431cfa..40c7e1f`)

**Decisions made:** None new.

**WP.org outcome:** Submitted 2026-04-29 (corrected 2026-04-29 — earlier "approved/live" entry was incorrect; the WP.org submission confirmation page was misread as approval). Slug assigned: `ai-search-optimizer`. Awaiting review email from `plugins@wordpress.org` to hello@citewp.com. Typical queue 2-14 days. Plugin NOT yet committed to WP.org SVN — that step happens only after approval email arrives.

**Carryover into Session 10:** None — Session 9 deliverable complete.

**Next session focus:** Phase 1.5 — UI polish pass per `UI-DESIGN-SYSTEM.md` (P19/X7): tabbed top nav across CiteWP admin pages, card-based settings layout, toggle switches, score gauge in dashboard widget, empty states. OR Schema generator (P14) — user to choose priority at session start.

---

## Session 8 — Brand Kit & WP.org Asset Prep (Side Mission) ✅

**Date:** 2026-04-28

**Deliverable:** Nail down brand architecture before creating WP.org submission assets. Resulted in complete brand kit, logo export toolkit, and animated SVG plugin icon. Main WP.org submission track carries into Session 9.

**Shipped:**
- `brand-kit-v2-yellow.html` — Obsidian & Citrine brand kit (active, canonical)
- `logos/logo-export-kit.html` — browser-based export tool; downloads all WP.org PNGs (icon-128, icon-256, banner-772, banner-1544) + standalone wordmarks via Canvas API
- `logos/icon.svg` — animated plugin icon; SMIL background pulse (#E8D400 → #F5EC30) + CSS scale breathe; runs as `<img>` on WP.org without JavaScript
- `logos/color-comparison.html` — ecosystem color analysis page (reference, not a deliverable)
- `Desktop/CiteWP/.agents/product-marketing-context.md` — full 12-section product marketing context document

**Archived (not deleted):**
- `brand-kit-v1-ink-ivy-ARCHIVED.html` — Ink & Ivy palette (green + gold)
- `brand-kit-v2-ember-ARCHIVED.html` — Obsidian & Ember (orange — kept for future use)
- `brand-kit-v2-teal-ARCHIVED.html` — Obsidian & Verdigris (teal — archived reference for future per-product color)

**Decisions made:**
- S8 (DECISIONS.md): Single Citrine accent across CiteWP parent and all current products. Per-product color differentiation deferred until a second product ships. Rationale: multi-neon-on-black reads as 80s synthwave, not expert AI SEO authority.
- Logo convention confirmed: `[Name]` full wordmark / `[X]` icon. Plus Jakarta Sans 800. Bracket positioning (text in lower-center of bracket span) is typographically correct — not a mistake.
- WP.org SVG icon strategy confirmed: `icon.svg` takes precedence over PNGs when placed in `assets/`. SMIL animations run even as `<img>`.
- Ecosystem color exploration concluded: Citrine (53°) + Teal (168°) + Magenta (318°) triangle rejected — 80s aesthetic. Option A chosen: one color, ship the product.

**Memory updated:** `project_brand_kit.md` — palette tokens, logo rules, ecosystem color policy, logo export kit path.

**Carryover into Session 9:** Main Session 8 track — WP.org submission assets still need to be downloaded from export kit and packaged. readme.txt `== Screenshots ==` section not yet written. SVN package not yet built. Submission not yet made.

**Next session focus:** Resume WP.org submission — download assets from logo-export-kit.html, write screenshots section in readme.txt, smoke test, build SVN package, submit.

---

## Session 7.5b — `.claude/` Infrastructure Rebuild ✅

**Date:** 2026-04-27

**Deliverable:** Rebuild the `.claude/` directory lost in the Session 7.5 git corruption. Resolves the last open carryover thread from Session 7.5. Parallel to Session 8 — does not touch plugin code.

**Shipped (2 commits on `main`):**
- `0fd0759` chore: rebuild .claude/ infrastructure (hooks, commands, settings)
- `d51a569` docs: update CLAUDE.md and SESSION-LOG.md to current Brain file names + add UI Design Rules section

**Files added (committed under `.claude/`):**
- `settings.json` — registers four hooks (PreToolUse, PostToolUse x2, Stop)
- `hooks/block-engine-edit.sh` — blocks edits to `includes/Scoring/Engine.php` unless `.claude/.engine-edit-approved` sentinel exists. Master file rule #4 / DECISIONS.md A11 enforced.
- `hooks/php-syntax-check.sh` — runs `php -l` on edited `.php` files. Non-blocking. Searches LocalWP-bundled PHP path if `php` isn't on PATH.
- `hooks/js-build-reminder.sh` — prints reminder to run `npm run build` when `src/**/*.js` or `*.jsx` is touched.
- `hooks/stop-checklist.sh` — prints end-of-session checklist with most-skipped steps highlighted (SESSION-LOG, DECISIONS, master file, push).
- `commands/session-start.md` — `/session-start` slash command.
- `commands/session-end.md` — `/session-end` slash command.
- `commands/smoke-test.md` — `/smoke-test` slash command (10-step Session 7.5 smoke test, X6-compliant).
- `README.md` — documents polarity, hooks, commands, gitignore pattern.

**Files updated (commit 2):**
- `CLAUDE.md` (plugin) — replaced dead Brain references (`11-SESSION-PROTOCOL.md` → master file section; `08-DECISION-LOG.md` → `DECISIONS.md`; `12-SCORING-RUBRIC.md` → `SCORING-RUBRIC.md`). Added UI Design Rules section pointing at `Brain/UI-DESIGN-SYSTEM.md` per X7.
- `SESSION-LOG.md` (this file) — same dead-name cleanup.

**`.gitignore` patched:** `.claude/*` ignored by default with explicit un-ignores for `hooks/`, `commands/`, `settings.json`, `README.md`. `settings.local.json` and `.engine-edit-approved` remain gitignored (machine-local state).

**Default state set:** `.engine-edit-approved` deleted post-rebuild, so Engine.php is locked by default. Sentinel must be manually created (`New-Item -ItemType File -Path .claude/.engine-edit-approved`) before any approved edit, deleted after.

**Decisions made:** X8 (`.claude/` content split: hooks + commands + project `settings.json` committed; machine-local state gitignored).

**Process lesson — chat→clipboard→editor autolink mangling:**

When pasting code blocks containing filenames like `name.md` or `name.sh` from Claude.ai chat into a Markdown-aware editor (or via certain clipboards), the bare filename gets converted to a Markdown autolink: `[name.sh](http://name.sh)`. This affected both filenames-on-disk and file contents during the rebuild attempt.

The chat renderer also displays clean text as autolinked when shown back in the chat window, which created confusion during diagnosis — eyes-on-terminal screenshots were the only reliable ground truth.

**Resolution:** Built the rebuild as a downloadable zip (`citewp-claude-rebuild.zip`) generated from a sandbox where no autolink processing exists, then extracted into the plugin folder. Verified with a `verify.ps1` script that asserted (a) all 9 expected files present, (b) no filename contains brackets/URLs, (c) no file CONTENT contains the bracketed-URL pattern. All 19 checks PASS before commit.

**Going-forward rule for AI-assisted file creation in this project:** if the file content references other filenames with extensions (`.md`, `.sh`, `.json`, etc.), do not paste from chat through any Markdown-aware editor. Use one of: (a) downloadable zip, (b) Filesystem MCP, (c) PowerShell `Set-Content` with literal here-strings typed (not pasted) directly into the terminal.

**Verified:**
- `git status` clean post-cleanup.
- Hook executable bits set on first commit (mode `100755` for all four `.sh` files in commit `0fd0759`) — fresh clones inherit correctness.
- Hooks not yet end-to-end tested in a live Claude Code session (will verify next time Session 8 work resumes from the plugin folder).

**Carryover into next session:** None. The Session 7.5 carryover thread is closed.

**Next session focus:** Resume Session 8 — WP.org submission prep (image assets, screenshots section in readme.txt, final smoke test, SVN package, submit).

---

## Session 7.5 — Multi-Product Architecture Refactor ✅

**Date:** 2026-04-27

**Deliverable:** Refactor codebase to multi-product architecture per A14. Plugin folder, slug, namespace, and ~110 identifiers renamed to align with WP.org distribution strategy (CiteWP parent brand + AI Search Optimizer plugin slug).

**Final identifier contract (per A14):**
- WP.org slug: `ai-search-optimizer`
- Display name: "AI Search Optimizer – Optimize Content for AI Engines"
- Author: CiteWP
- PHP namespace: `CiteWP\Aiso\` (CiteWP\ stays as company-level root for future plugins)
- DB tables: `wp_citewp_aiso_*`
- Options/transients/cron/post meta: `citewp_aiso_*` / `_citewp_aiso_*`
- REST namespace: `citewp/aiso/v1`
- Constants: `CITEWP_AISO_*`
- Text domain: `ai-search-optimizer`
- Script handles: `citewp-aiso-*`
- CSS classes: `citewp-aiso-*`

**Shipped (10 commits on `refactor/multi-product-architecture` branch):**
- `c1394bc` Step 2: rename main file, update header/constants/autoloader/hooks
- `d1dab98` Step 3: namespace + @package across 22 PHP files (CiteWP\Aiso sub-namespacing); Engine.php math untouched
- `d26a08c` Step 4: option keys, table names, post meta, transients, cron hooks, constants → citewp_aiso_*
- `47a2c16` Step 5: REST namespace citewp/v1 → citewp/aiso/v1
- `2a8aabe` Step 6: text domain 'citewp' → 'ai-search-optimizer' (63 i18n calls across 7 files)
- `dbcd238` Step 7: JS sidebar slug citewp-geo-score → citewp-aiso-geo-score, header comment
- `315feca` Step 8: script handle + CSS class prefix citewp- → citewp-aiso- (~100 occurrences across DashboardWidget, LogsPage, PostListColumn, EditorAssets, Settings/Page, Menu)
- `9bc7651` + `01dcf94` Step 9: readme.txt replaced entirely with draft v0.6.0 (initial commit was a partial merge; fixed in follow-up)
- `c1eca79` Step 11: CLAUDE.md updated to reflect AISO architecture
- `cd21381` Step 12 fix: removed Domain Path header (no languages folder yet), shortened readme short description to <150 chars (Plugin Check fixes)

**Smoke test (10/10 passed):**
1. Plugin activates with new display name — PASS
2. DB table `wp_citewp_aiso_crawler_logs` created on activation — PASS
3. Three options created with `citewp_aiso_*` prefix — PASS
4. Gutenberg sidebar loads, score displays, recalculate works — PASS
5. `/llms.txt` and `/llms-full.txt` serve correctly — PASS
6. `curl -A "GPTBot/1.0"` logs to new table with correct vendor mapping — PASS
7. Dashboard widget renders with score data, bot counts, top crawled pages — PASS
8. Crawler Logs admin page renders, filters work, CSV export works — PASS
9. REST endpoint `/wp-json/citewp/aiso/v1/score/{id}` returns expected response (`citewp_aiso_forbidden` from address bar without nonce — confirms route registration AND error code rename) — PASS
10. uninstall.php cleans all data — PASS (highest-risk step; verified zero `citewp%` options, zero `_citewp%` post meta, zero `wp_citewp%` tables after delete)

**Plugin Check final state:** Same baseline as Session 7. All remaining warnings are local infrastructure files (`.claude/`, `.gitignore`, `CLAUDE.md`, `SESSION-LOG.md`) excluded from WP.org SVN package via `.distignore`. Zero real errors in shipped code.

**Decisions made:** A14 (multi-product architecture, CiteWP\ as company root with per-plugin sub-namespaces); P17 amended (slug `ai-search-optimizer`, display name keyword-stuffed, internal architecture refactored, Cite Score remains as feature name).

**Catastrophic loss + recovery (documented for posterity):**

During the FIRST attempt at this refactor, the local `.git/` directory got corrupted at Step 1 — only `.git/objects/` survived, all other git metadata (HEAD, refs, config, index) and the entire working tree were destroyed. Cause not definitively identified but strong correlation with: (a) folder rename happening while Claude Code's terminal CWD was inside the renamed folder, (b) Windows Defender real-time scanning of `node_modules/`, (c) possible LocalWP file watcher activity. Steps 2-11 of the first attempt were never committed (original brief specified single-commit-at-Step-14) and were lost entirely.

A SECOND corruption occurred during Step 13's smoke test: WordPress's plugin Delete action partially succeeded (uninstall.php ran cleanly, all DB cleanup verified) but the physical file deletion failed mid-operation due to a file lock on a deeply-nested `node_modules/` file. Same `.git/` corruption signature (only `objects/` remained).

**Recovery:** Both incidents were recoverable because: (1) every step of the SECOND attempt was committed and pushed to `origin/refactor/multi-product-architecture` immediately (per-step commit cadence introduced as a process change), and (2) a robocopy backup excluding `node_modules/`, `build/`, and `.git/` was made to `Desktop/ai-search-optimizer-BACKUP/` before the uninstall test.

**Process changes adopted:**
1. **Per-step commits AND pushes** instead of single-commit-at-end. Cost: slightly messier history that can be squashed later via `git rebase -i`. Benefit: catastrophic loss = minutes lost, not hours.
2. **Manual folder renames** via Windows Explorer with Claude Code session fully closed first. Never rename a folder while a tool has the folder as its CWD.
3. **Defensive 4-grep policy** after every step: bare prefix (`citewp[_-]`), REST/path (`citewp/`), namespace (`(namespace|use)\s+CiteWP\\(?!Aiso)`), and `@package CiteWP$` patterns. Caught 100+ identifiers across multiple categories that the brief did not enumerate.
4. **LocalWP plugins folder added to Windows Defender exclusion list** to reduce file-lock interference during dev work.
5. **Never test WordPress plugin Delete against the live source folder.** WP core's `delete_plugins()` calls `WP_Filesystem::delete($plugin_dir, true)` — a recursive delete on the entire plugin folder. Combined with Windows file locks on deeply-nested `node_modules/` files, this reliably corrupts `.git/`. For uninstall.php verification, use one of: (a) `git archive --format=zip --output=../test.zip HEAD` then upload the zip via WP Admin (clean install separate from source), (b) move the live folder out of plugins/ temporarily before testing, or (c) maintain a second LocalWP site for destructive testing only.

**Known carryover into Session 8:**
- WP.org submission image assets not yet created: icon-128x128.png, icon-256x256.png, banner-772x250.png, banner-1544x500.png, screenshot-1.png through screenshot-N.png. CiteWP company logo (blue/purple chat-bubble) becomes the WP.org plugin icon; banner uses CiteWP branding with "AI Search Optimizer" wordmark.
- `readme.txt` is missing a `== Screenshots ==` section. Add when actual screenshots exist.
- `.claude/` infrastructure rebuild deferred (was lost in corruption, was always gitignored, never on GitHub). Not blocking. Schedule as standalone session before Session 8 if needed.
- The `ai-search-optimizer-WP-DELETION-BROKEN/` and `ai-search-optimizer-BROKEN-DO-NOT-USE/` folders sit in the plugins directory as recovery artifacts. Delete after a clean Windows reboot when file locks are released.
- `Brain/14-REFACTOR-BRIEF-SESSION-7-5.md` should be archived (refactor complete) or kept as a reference for the architecture pattern.

**Next session focus:** Session 8 — create WP.org submission image assets (icon, banner, screenshots), add Screenshots section to readme.txt, final pre-submission smoke test, submit to WP.org plugin directory.

---

## Session 7 — WP.org Plugin Check + Submission Prep ✅

**Date:** 2026-04-26

**Deliverable:** Run WP.org Plugin Check, fix all errors/warnings, rename plugin to comply with WP.org trademark rules, bump to v0.5.0.

**Shipped:**
- `citewp.php` — Plugin Name header renamed to "Cite Score — AI Search Optimization"; version bumped to 0.5.0
- `readme.txt` — display name, FAQ, installation section updated to "Cite Score"; Tested up to bumped to 6.9; Stable tag 0.5.0; 0.5.0 changelog entry added
- `.distignore` — new file; excludes `.claude/`, `CLAUDE.md`, `SESSION-LOG.md`, `src/`, `package*.json`, `node_modules/` from WP.org SVN package
- `includes/Admin/LogsPage.php` — CSV export replaced `fwrite`/`fclose` with direct `echo` streaming; `esc_sql()` on `$table`; phpcs annotations for nonce false-positives and custom-table queries
- `includes/Admin/LogsTable.php` — `esc_sql()` on `$table`; unified phpcs:disable block for all custom-table query annotations; phpcs:ignore on 4 GET filter params
- `includes/Admin/DashboardWidget.php` — `esc_sql()` on `$table`; moved DirectQuery phpcs:ignore to query lines
- `includes/Crawler/Detector.php` — `sanitize_text_field()` on `HTTP_USER_AGENT`; phpcs:ignore on IP/URI/referer (sanitized by `filter_var`/`esc_url_raw`); `esc_sql()` on prune `$table`
- `includes/Settings/Page.php` — phpcs:ignore on read-only GET display flags
- `includes/Llms/Generator.php` — phpcs:ignore on `apply_filters('the_content')` core filter call
- `includes/Scoring/ContentAnalysis.php` — same as above
- `uninstall.php` — phpcs:ignore on global vars, SchemaChange, slow meta_key

**Decisions made:** P8 amended — WP.org display name changed to "Cite Score — AI Search Optimization", WP.org slug to be requested as `cite-score`. Internal code (namespace, DB tables, REST routes, option keys, folder name, GitHub, domain) unchanged (see DECISION-LOG.md).

**Verified:**
- `npm run build` succeeded (3 pre-existing chartLine warnings only — not introduced this session).
- Plugin Check re-run pending (user to verify after LocalWP cache clears).
- Note: `.claude/` directory errors will still appear in LOCAL Plugin Check runs — this is expected. `.distignore` excludes them from the actual WP.org SVN submission package.
- Committed `48bd190` and pushed.

**Carryover into Session 8:**
- WP.org submission image assets not yet created: `icon-128x128.png`, `icon-256x256.png`, `banner-772x250.png`, `banner-1544x500.png`, `screenshot-1.png` through `screenshot-N.png`. These require design work.
- `readme.txt` is missing a `== Screenshots ==` section with descriptions — needs to be added before submission.
- After assets are ready: add Screenshots section to readme.txt, do final smoke test, submit to WP.org.

**Next session focus:** Session 8 — add Screenshots section to readme.txt, final smoke test (all features working), WP.org submission.

---

## Session 6 — Security Audit + Plugin Check Prep ✅

**Date:** 2026-04-26

**Deliverable:** Pre-WP.org submission security pass, hygiene cleanup, and version bump to 0.4.0.

**Modified:**
- `LICENSE` — added GPL v2 license file (WP.org submission requirement)
- `citewp.php` — version bumped to 0.4.0
- `readme.txt` — version bumped to 0.4.0; description rewritten to reflect actually-shipped features (crawler logs, llms.txt, GEO Score, dashboard widget, filters, CSV); 0.4.0 changelog entry added
- `uninstall.php` — added missing `citewp_llms_settings` option to cleanup; added all `_citewp_*` post meta cleanup; added transient cleanup
- `includes/Llms/Router.php` — removed `nocache_headers()` call that contradicted the explicit `Cache-Control` header set immediately after
- `includes/Admin/LogsPage.php` — added `phpcs:ignore` annotations on direct DB stat queries (Plugin Check expects either a justification or a wpdb wrapper; annotations chosen since stats need raw aggregation)
- `includes/Admin/LogsTable.php` — same `phpcs:ignore` treatment on count/filter queries
- `includes/Admin/DashboardWidget.php` — removed redundant `meta_query EXISTS` (the `meta_key` filter already enforces existence; was a double-check that confused Plugin Check)
- `includes/Admin/Menu.php` — removed stale placeholder comment

**Decisions made:** None new. Standard pre-submission hygiene against existing Plugin Check expectations.

**Verified:**
- LICENSE file present and correct (GPL v2 — required for WP.org).
- Uninstall genuinely removes everything: crawler_logs table, both options, all post meta, transients.
- llms.txt cache headers no longer contradict each other.
- Direct DB queries justified inline; Plugin Check annotations in place.
- Committed and pushed: `f5e5699` — `feat: security audit + Plugin Check prep (v0.4.0)`.

**Carryover into Session 7:**
- None. Plugin should now pass WP.org Plugin Check tool. Next session is to actually run the tool and address anything it flags.

**Next session focus:** Session 7 — run WP.org Plugin Check, fix any remaining warnings, prepare WP.org submission assets (banner, icon, screenshots).

---

## Session 5 — Settings Polish + Crawler Stats + CSV Export ✅

**Date:** 2026-04-26

**Deliverable:** Summary stats banner (24h/7d/30d), bot type filter, date range filter, and CSV export on the Crawler Logs page. Settings page inline-style cleanup.

**Modified:**
- `includes/Admin/LogsTable.php` — added `extra_tablenav()` with bot + date-range filter dropdowns; updated `prepare_items()` to apply both filters to COUNT and data queries; added `validated_bot_filter()`, `validated_range_filter()`, `range_to_since()` helpers
- `includes/Admin/LogsPage.php` — replaced thin description text with 3-stat banner (24h/7d/30d); added "Export CSV" button; added `handle_csv_export()` (nonce + capability checked, streams UTF-8 BOM CSV with current filters applied); added `inline_styles()` scoped to logs screen
- `includes/Settings/Page.php` — moved `style="display:block"` inline style to `.citewp-cpt-label` CSS class via `inline_styles()` / `admin_head` hook

**Decisions made:** None new.

**Verified:**
- Stats banner renders correctly on Crawler Logs page.
- Bot type and date range filters narrow the table and persist in pagination.
- Export CSV downloads a valid file with headers and local timestamps; Chrome download prompt is expected browser behavior on localhost, not a plugin issue.
- `npm run build` succeeded (3 pre-existing warnings only).
- Committed and pushed: `28e91e8`.

**Carryover into Session 6:**
- None.

**Next session focus:** Session 6 — Plugin Check + security audit.

---

## Session 4 — Dashboard Widget ✅

**Date:** 2026-04-26

**Deliverable:** WordPress Dashboard home widget showing avg GEO score, bot visit trend, top crawled pages, and lowest-scoring posts.

**Shipped:**
- `includes/Admin/DashboardWidget.php` — registers on `wp_dashboard_setup`; displays 4 sections: avg GEO score stat (grade-colored), bot visits last 7d vs prior 7d with trend arrow, top 5 crawled URIs (last 7 days), lowest 5 scored posts with edit links

**Modified:**
- `includes/Plugin.php` — wired `DashboardWidget` into `is_admin()` block

**Decisions made:** None new. All decisions consistent with prior sessions.

**Verified:**
- Widget appears on WP Dashboard home (wp-admin/index.php).
- Avg GEO score, bot visit trend, crawled pages, and lowest-scoring posts all render correctly.
- `npm run build` succeeded (3 pre-existing warnings from sidebar `chartLine` icon — not introduced this session).
- Committed and pushed: `12b993e`.

**Carryover into Session 5:**
- None.

**Next session focus:** Session 5 — Settings page polish + crawler dashboard summary stats + CSV export.

---

## Session 3 — GEO Score (Engine + REST + Sidebar + Post Column) ✅

**Date:** 2026-04-26

**Deliverable:** Per-post GEO Score visible in Gutenberg sidebar with sub-category drilldown, and as a sortable colored column on the post list.

**Shipped:**
- `includes/Scoring/SignalResult.php` — per-signal data structure
- `includes/Scoring/ScoreResult.php` — full result data structure
- `includes/Scoring/ContentAnalysis.php` — parses post content into queryable structures
- `includes/Scoring/Engine.php` — runs 17 signals, produces 100-point result ⚠️ NO-TOUCH
- `includes/Scoring/Repository.php` — post meta persistence + `save_post` hook
- `includes/Rest/ScoreController.php` — `/wp-json/citewp/v1/score/{id}` GET + `/recalculate` POST
- `includes/Admin/PostListColumn.php` — sortable colored column on All Posts/Pages
- `includes/Admin/EditorAssets.php` — enqueues built sidebar JS in editor
- `src/sidebar/index.js` — React sidebar (total + 3 categories + signal drilldown + recalculate button)
- `package.json` — npm + `@wordpress/scripts` build pipeline

**Modified:**
- `citewp.php` — version bumped to 0.3.0
- `includes/Plugin.php` — wired Scoring + Rest + PostListColumn + EditorAssets into `boot()`
- `readme.txt` — 0.3.0 changelog entry
- `.gitignore` — added `build/` and `node_modules/`

**Decisions made (logged in `Brain/08-DECISION-LOG.md`):**
- A11: Scoring engine in PHP, not JS (single source of truth, REST-served to React)
- A12: On-save scoring, not real-time per keystroke
- P9: 100-point system, 3 categories (Structure 35 / Citability 40 / Authority 25), 17 signals
- P10: Both Gutenberg sidebar AND post list column
- P12: Default sidebar discoverability via kebab menu (no auto-pin)
- S6: Public scoring rubric as differentiator
- X1: Two CLAUDE.md files (strategy + code)
- X2: SESSION-LOG.md inside plugin folder

**New canonical doc:** `Brain/12-SCORING-RUBRIC.md` — full rubric specification.

**Verified:**
- "Hello world!" default post scored 16/100 red — math behaving correctly.
- Sidebar registers in editor (kebab menu → Panels → CiteWP GEO Score; star to pin).
- Category expansion shows per-signal pass/partial/fail with messages and recommendations.
- Recalculate button refreshes score.
- Save → auto-recalculates.
- Post list shows colored sortable score column.
- `npm run build` succeeded after installing `@wordpress/icons`.
- 23 PHP files lint clean.

**Carryover into Session 4:**
- None.

**Next session focus:** Session 4 — Dashboard widget (avg GEO score, top crawled pages, bot visit trend, link to logs).

---

## Session 2 — llms.txt Auto-Generator ✅

**Date:** 2026-04-26

**Deliverable:** `/llms.txt` and `/llms-full.txt` served dynamically by WordPress with content selection logic.

**Shipped:**
- `includes/Llms/ContentSelector.php` — tiered selection (Pages → cornerstone → recent quality posts → opted-in CPTs)
- `includes/Llms/Generator.php` — builds llms.txt content per llmstxt.org spec
- `includes/Llms/Cache.php` — 1-hour transient with smart invalidation hooks
- `includes/Llms/Router.php` — registers rewrite rules and serves dynamic content
- `includes/Settings/Page.php` — admin UI for llms.txt configuration

**Modified:**
- `citewp.php` — version bumped to 0.2.0
- `includes/Plugin.php` — wired Llms + Settings modules into `boot()`
- `readme.txt` — 0.2.0 changelog entry

**Decisions made (logged in `Brain/08-DECISION-LOG.md`):**
- A10: llms.txt via rewrite rule, not physical file in webroot
- P11: Tiered content selection with smart defaults

**Verified:**
- `http://citewp-dev.local/llms.txt` serves valid output.
- `http://citewp-dev.local/llms-full.txt` serves expanded content version.
- SEO plugin integration confirmed (Yoast/Rank Math/AIOSEO meta detection).
- Cache busts on post publish/update.
- Manual "Regenerate" button works.

**Carryover into Session 3:**
- None.

---

## Session 1 — Plugin Scaffold + Crawler Detection ✅

**Date:** 2026-04-26

**Deliverable:** Plugin activates cleanly. AI bot visits to the site are detected and logged. Admin page displays the log.

**Shipped:**
- `citewp.php` — main plugin file with PSR-4 autoloader
- `includes/Plugin.php` — singleton orchestrator
- `includes/Database/Schema.php` — `citewp_crawler_logs` custom table with proper indexes
- `includes/Crawler/BotRegistry.php` — 41 AI bot signatures (GPTBot, ClaudeBot, PerplexityBot, Google-Extended, Applebot-Extended, etc.)
- `includes/Crawler/Detector.php` — UA matching + DB logging on `init` action
- `includes/Admin/Menu.php` — top-level CiteWP admin menu
- `includes/Admin/LogsPage.php` + `LogsTable.php` — paginated/sortable logs
- `uninstall.php` — drops table + options on plugin delete
- `readme.txt` — WP.org listing draft
- `.gitignore`

**Decisions made (logged in `Brain/08-DECISION-LOG.md`):**
- A6: PSR-4 namespaced autoloader (NOT flat-file convention from original Build Plan)
- A7: Singleton pattern for `Plugin` orchestrator
- A8: GMT timestamps in DB, local time at render
- A9: No proxy IP detection yet — REMOTE_ADDR only
- A13: LocalWP over wp-env for dev environment
- P8: Project name "CiteWP", slug `citewp`
- S5: citewp.com primary, citewp.ai redirect (transferring to Cloudflare)

**Environment setup completed:**
- LocalWP site created (`citewp-dev`, PHP 8.2.30, MySQL 8.0, Nginx)
- GitHub repo created (`bradleyswpc/citewp`, private)
- Git authenticated, pushed initial commits
- Node.js + npm verified installed

**Verified:**
- Plugin activates without errors.
- `wp_citewp_crawler_logs` table created.
- `curl.exe -A "GPTBot/1.0" http://citewp-dev.local/` produces a row in admin logs page.
- 3 of 3 test bots logged successfully (GPTBot, ClaudeBot, PerplexityBot).

**Carryover into Session 2:**
- None.

---

*Add new sessions ABOVE this line. Newest first.*
