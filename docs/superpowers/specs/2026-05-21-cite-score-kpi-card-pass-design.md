# Cite Score KPI Card Pass — Design Spec
**Session 37 | Date: 2026-05-21**

## Scope

Restyle the 4 KPI cards on the Cite Score page to the Dashboard KPI Card pattern (P57). Shrink existing 36px `__visual` orbs to small inline icons (P62). Add a new 4th card — Schema Coverage — backed by a new `DashboardData::schema_coverage()` aggregate method. Grid expands from 3-column to 4-column.

**Files changed:**
- `includes/Admin/Menu.php` — `render_cite_score_panel()`: restyle HTML for cards 1–3, add card 4 HTML
- `includes/Admin/DashboardData.php` — add `schema_coverage()` method
- `admin/css/citewp-aiso-admin.css` — add `--4col` grid modifier, remove/restyle Cite Score `__visual` orb rules

**No-touch:** `includes/Scoring/Engine.php` — schema_coverage() reads stored meta only.

---

## Decisions Locked

| Constraint | Decision |
|---|---|
| P57 | Dashboard KPI Card pattern applies to Cite Score page cards |
| P62 | Shrink 36px orbs to 16px inline icons in head row — do not remove icons |
| P65 | activity__heading stays muted Tier 3 — do not revert |
| P49 | schema_coverage() excludes posts opted out of llms.txt (same WP_Query guard as render_cite_score_panel) |
| X12 | "Partial" caption for schema stays advisory — does not imply confirmed schema output |
| P41 | All 4 KPI cards use outline buttons. "View AI Recommendations →" lower on page remains sole primary-paper CTA |
| Typography | Card titles use shared `.citewp-aiso-t2` block (600 14px/1 Inter primary). No ad-hoc font declarations |
| X4 | Per-step commits + pushes |
| X20 | Spec-compliance audit between code-review and browser verify |
| X13 | Full pipeline (brainstorm → plan → implement → review → verify) |

---

## Grid Change

| Before | After |
|---|---|
| `citewp-aiso-kpi-row--3col` | `citewp-aiso-kpi-row--4col` |

Add `--4col` modifier to CSS: 4 equal columns, ~25% each at 1440px viewport. Existing `--3col` can be retained (used by Crawler Logs page — do not remove).

---

## Card Designs

### Card 1 — Top Crawler (restyle only)

**What changes:** Remove `__visual` 36px orb + `__data` side-by-side wrapper. Add 16px inline bot icon to head row. Add two stacked secondary stats below the visit count.

| Slot | Content |
|---|---|
| Head-left | `IconLibrary::icon('bot', 16)` + "Top Crawler" (Tier 2) |
| Head-right | Info icon + tooltip: "The AI bot that's visited your site most often in the last 7 days." |
| Value | Bot display name (e.g. "GPTBot") — JetBrains Mono 700 28px obsidian |
| Sub 1 | "N visits in last 7 days" |
| Sub 2 | "Top page: {resolved title}" — from `DashboardData::get_top_crawled_pages($cutoff_7d, 1)`, title clamped to one line (`text-overflow: ellipsis; white-space: nowrap; overflow: hidden`), no wrap. **Height-gated** (see below). |
| Sub 3 | "{N} AI bots detected this week." — from `DashboardData::get_unique_bot_count($cutoff_7d)` |
| Trend | ↑/↓/→ vs prior 7 days (existing delta logic — preserve as-is) |
| Button | "View Crawler Logs →" outline, full-width |

**Height gate (X20 + browser-verify kill-switch):** At 100% zoom, if Card 1 renders taller than Cards 3 or 4 (the tile cards), remove Sub 2 (most-hit page) and keep only Sub 3 (bot count). Bot count is the keeper — it is short and never wraps. Most-hit page yields first because a long title can still overflow even with ellipsis on narrow 4-col cards. Document the outcome (kept or cut) as a named checklist item in Task 9 and Task 11.

Empty state (no crawlers): value = "—", Sub 1 = "No AI crawler visits yet", Sub 2 + Sub 3 omitted, trend = flat, button remains.

---

### Card 2 — Posts/Pages Optimized (restyle only)

**What changes:** Remove `__visual` 36px orb + `__data` wrapper. **Keep `__kpi-progress` bar** (reinstated — matches Dashboard KPI density pattern). Add 16px inline icon to head row.

| Slot | Content |
|---|---|
| Head-left | `IconLibrary::icon('check-circle', 16)` + "Posts/Pages Optimized" (Tier 2) |
| Head-right | none |
| Value | "X / Y" fraction — X = `$posts_optimized`, Y = `$total_scored`. Main span 28px, denom span 18px muted |
| Caption | "posts & pages with Cite Score ≥ 50" |
| Sub | "N% of your scored content" |
| Progress bar | `__kpi-progress` bar driven by `$pct_optimized` — same pattern as Dashboard KPI row |
| Trend | None — removed. Equal card height comes from body density (fraction hero + sub + progress bar), not a filler trend row. |
| Button | "View All →" outline, full-width (scrolls to per-post table anchor) |

Empty state (no scored posts): value = "0 / 0", caption = "No posts scored yet", sub + progress bar omitted.

---

### Card 3 — Needs Attention (rename + restyle)

**What changes:** Rename "Issues Detected" → "Needs Attention". Remove `__visual` 36px orb. Add 16px inline icon. Add head-right "View All →" link (matches Dashboard Needs Attention card). No bottom button (head-right link is the action — consistent with Dashboard).

| Slot | Content |
|---|---|
| Head-left | `IconLibrary::icon('alert-triangle', 16)` + "Needs Attention" (Tier 2) |
| Head-right | "View All →" link (scrolls to per-post table) |
| Value | `$issue_count` — score-grade color (`citewp-aiso-kpi-score--{grade}`) |
| Caption | "posts need work" |
| Severity tiles | Critical / Minor tiles (keep existing `__severity-tile` pattern — matches Dashboard) |
| Trend | None — removed. Body density comes from severity tiles. |
| Button | None (head-right link serves as action affordance) |

Empty state (issue_count = 0): value = "0", caption = "All posts are looking good", severity tiles hidden.

---

### Card 4 — Schema Coverage (new)

**What changes:** New card. Requires `DashboardData::schema_coverage()`.

| Slot | Content |
|---|---|
| Head-left | `IconLibrary::icon('layers', 16)` + "Schema Coverage" (Tier 2) |
| Head-right | None — "Add Schema →" dropped (links to a read-only table, not a schema-adding surface) |
| Value | `$pct_confirmed`% — JetBrains Mono 700 28px obsidian |
| Caption | "posts with confirmed inline schema" |
| Tiles (3-tile row) | **Confirmed** (green, count) / **SEO Plugin** (yellow, count) / **None** (red, count) |
| Trend | None — removed. Body density comes from 3-tile row. No historical delta exists (schema_coverage() is point-in-time only). |
| Button | "View Schema Gaps →" outline, full-width (scrolls to per-post table top) |

Tile labels use advisory phrasing per X12: "SEO Plugin" (not "Partial configured") to distinguish from "confirmed inline JSON-LD."

Empty state (no scored posts): value = "—", caption = "Score your posts to see schema coverage", tiles hidden.

---

## New Method: `DashboardData::schema_coverage()`

```php
/**
 * Aggregates schema signal states across all scored, llms.txt-included posts.
 *
 * Reads the stored 'schema' signal from _citewp_aiso_geo_score post meta.
 * Does NOT invoke Engine::check_schema() or perform any live scoring —
 * this is a read-side aggregate of the cached 6/3/0 signal from the last
 * scoring run. 'partial' means an SEO plugin was detected at score time;
 * it does NOT verify the plugin outputs schema for this post type (FB42,
 * deferred — render-time detection).
 *
 * Excludes posts opted out of llms.txt (P49) using the same
 * (NOT EXISTS OR != '1') WP_Query guard as render_cite_score_panel().
 *
 * @return array{confirmed: int, partial: int, none: int, total: int, pct_confirmed: int}
 */
public function schema_coverage(): array
```

**Return shape:**
```php
[
    'confirmed'     => int,  // signal status = 'pass'  (6 pts inline JSON-LD)
    'partial'       => int,  // signal status = 'partial' (3 pts — SEO plugin detected)
    'none'          => int,  // signal status = 'fail'  (0 pts)
    'total'         => int,  // confirmed + partial + none
    'pct_confirmed' => int,  // round(confirmed / total * 100), 0 when total = 0
]
```

**Extensibility filters (FB40 / FB42):**
```php
// Post types scope — FB40 CPT detection will extend this list
$post_types = apply_filters( 'citewp_aiso/data/scored_post_types', [ 'post', 'page' ] );

// Return value — FB42 render-time detection will augment confirmed count
return apply_filters( 'citewp_aiso/data/schema_coverage', $result );
```

**Data path:** WP_Query for published posts with `Repository::META_KEY_TOTAL` + P49 exclusion guard → for each post ID, read `_citewp_aiso_geo_score` → find signal with `id = 'schema'` → bucket by status.

**Performance note:** Identical query shape to the existing `$scored_ids` loop in `render_cite_score_panel()`. Cap at 1000 posts (same as existing). This is admin-only, called once per page load.

---

## Link Destinations (all per-post table affordances)

Cards 2, 3, and 4 all send the user to the per-post table on the same page. Confirm the table anchor (`id` attribute) exists in `render_cite_score_panel()` HTML and that all four hrefs point at the same value.

| Affordance | Card | Destination |
|---|---|---|
| "View All →" button | Card 2 | per-post table top anchor |
| "View All →" head-right link | Card 3 | per-post table top anchor |
| "View Schema Gaps →" button | Card 4 | per-post table top anchor |

**No filtered view this session.** A schema-fail-only filter on the per-post table requires query-param plumbing (RecommendationFilter-style) — separate FB candidate.

---

## Denominator Reconciliation (Plan Step — Item #3)

`schema_coverage()['total']` and Card 2's `$total_scored` are computed by separate query paths but should be identical: both query published, non-excluded, scored posts for `post`/`page`. A post with a stored score (`_citewp_aiso_geo_score_total` EXISTS) always has all 17 signals including `schema` in its full score array — so any mismatch is a bug signal.

**Plan step:** During `schema_coverage()` implementation, add an assertion comment: if `$schema_total !== $total_scored_from_card2`, log a `_doing_it_wrong()` notice so the discrepancy surfaces in debug.log. The implementation loop should handle the edge case where a scored post has no `schema` signal in its stored array (count it as `none` and log a notice).

---

## IconLibrary Checklist (Plan Step — Item #5)

Verify these icons exist in `IconLibrary.php` before wiring card HTML. If missing, add the Lucide SVG path (in-scope — it's a one-line path constant):

| Icon key | Used by | Known to exist? |
|---|---|---|
| `bot` | Card 1 | Yes (S28) |
| `check-circle` | Card 2 | Verify |
| `alert-triangle` | Card 3 | Verify |
| `layers` | Card 4 | Verify |
| `info` | Card 1 head-right | Yes (S18) |

---

## Extensibility Hooks (X15)

| Filter | Purpose |
|---|---|
| `citewp_aiso/data/scored_post_types` | FB40: let CPT scope extend posts counted in schema_coverage() |
| `citewp_aiso/data/schema_coverage` | FB42: let render-time detection augment confirmed count without rewriting the method |

---

## CSS Changes

**Add:**
- `citewp-aiso-kpi-row--4col` — 4-column grid modifier (CSS Grid, repeat(4, 1fr))
- `.citewp-aiso-cs-kpi-row .citewp-aiso-kpi-card__footer { margin-top: auto; }` — pins footer to card bottom in flex-column cards; buttons baseline-align regardless of body height differences. Do NOT use `flex:1` on a trend row for this.
- `.citewp-aiso-cs-kpi-row .citewp-aiso-kpi-card__footer .citewp-aiso-btn { display: block; width: 100%; text-align: center; }` — full-width buttons for Cards 1, 2, and 4.
- Sub 2 ellipsis rule for Card 1 top-page line: `white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%;`

**Remove / restyle for Cite Score page cards:**
- `citewp-aiso-kpi-card__visual` rules scoped to `.citewp-aiso-cs-kpi-row` — no longer needed
- `citewp-aiso-kpi-card__data` side-by-side layout for Cite Score cards

**Keep:**
- `citewp-aiso-kpi-row--3col` (Crawler Logs page uses this)
- `citewp-aiso-kpi-card__visual` base rules (may be used elsewhere)
- `citewp-aiso-kpi-progress` CSS — Card 2 reinstates the progress bar; do NOT scope this out for `.citewp-aiso-cs-kpi-row`

---

---

## S37 Follow-Up Amendment — Progress Bar Fix + Score-Band Color + Icon Tints

**Session 37 (continuation) | Date: 2026-05-21**

### Task A — Diagnosis (complete)

Root cause: HTML emits `.citewp-aiso-kpi-progress__bar`; CSS targets `.citewp-aiso-kpi-progress__fill`. The fill element has no height or background rule, so the bar is invisible. Fix: rename the inner element in Card 2 HTML from `__bar` to `__fill`.

### Task B — Score-Band Colored Progress Bar

**Card 2 progress bar fill** must be colored by the optimized ratio using the P44 score-band ramp applied to `$pct_optimized`:

| `$pct_optimized` | Grade | Token |
|---|---|---|
| ≥ 80 | green | `--citewp-score-green` |
| ≥ 60 | yellow | `--citewp-score-yellow` |
| ≥ 40 | orange | `--citewp-score-orange` |
| < 40 | red | `--citewp-score-red` |

**PHP:** Compute `$optimized_grade` using a `match(true)` on `$pct_optimized` (same thresholds). Add modifier class `citewp-aiso-kpi-progress--{grade}` to the outer `.citewp-aiso-kpi-progress` element.

**CSS:** Add four modifier rules that override `__fill` background per grade. The base `__fill { background: var(--citewp-score-green) }` remains as a no-modifier fallback (Dashboard KPI bars use the element without a modifier).

**Empty state** (`$total_scored === 0`): bar hidden — consistent with Card 2 empty state.

### Task C — Head-Icon Tints (decorative, per P38/P62)

Override the shared `__head-main svg { color: var(--citewp-text-muted) }` rule with per-card decorative tints. Tints are wallpaper-level (P38) — they do NOT encode state. Functional score-band colors on Card 3 value and Card 4 tiles are unaffected.

Add unique modifier classes to Cards 2, 3, 4 in Menu.php (Card 1 already has `citewp-aiso-kpi-card--top-crawler`):
- Card 2: `citewp-aiso-kpi-card--optimized`
- Card 3: `citewp-aiso-kpi-card--needs-attention`
- Card 4: `citewp-aiso-kpi-card--schema-coverage`

| Card | Tint token | Rationale |
|---|---|---|
| Card 1 Top Crawler | `--citewp-tint-teal` | Bot/crawler = teal (matches Dashboard crawler widget) |
| Card 2 Posts/Pages Optimized | `--citewp-score-green` | Optimized = positive = green |
| Card 3 Needs Attention | `--citewp-score-orange` | Warning state = orange |
| Card 4 Schema Coverage | `--citewp-tint-purple` | Schema/data = purple (matches Structure category) |

**Kill-switch:** If the row feels visually busy at browser-verify, revert Task C only (remove the four icon-tint overrides from CSS and the card modifier classes from HTML). Task B is independent and must stay regardless.

**X20 additions:**
- Card 2 `__fill` element present in DOM (not `__bar`)
- `__fill` has computed non-zero height AND non-transparent background
- `__fill` background matches the expected score-band token for `$pct_optimized`
- Icon tints (if Task C kept): decorative tokens, not score tokens; do not collide with Card 3 value color or Card 4 tile colors

---

## Spec Self-Review

- **Placeholders:** None. All slots defined for all 4 cards including empty states.
- **Contradictions:** P41 (one primary-paper max) ✓ — all 4 cards use outline. "View AI Recommendations →" lower on page is the sole primary-paper.
- **Scope:** Single session deliverable. No scoring logic changes (Engine.php untouched). No new REST endpoints.
- **Ambiguity resolved:** All 4 table affordances (Cards 2/3/4) scroll to per-post table top. No filtered view this session — locked per user decision. Filtered schema-fail view is a future FB candidate.
- **Typography:** All card titles reference `.citewp-aiso-t2` — no ad-hoc declarations. ✓
- **P65:** No activity__heading selectors touched. ✓
