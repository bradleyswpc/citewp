# Cite Score Page Run #5 — Polish Pass Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix two root-cause CSS bugs (uppercase bleed, tooltip overflow) and 12 individual spec deviations on the Cite Score sitewide dashboard, producing a pixel-accurate result against the reference mockup.

**Architecture:** Fix-in-place pass on two plugin files (`Menu.php` + `citewp-aiso-admin.css`), one PHP utility file (`IconLibrary.php` already has bot icon), and the Brain design doc (`UI-DESIGN-SYSTEM.md`). No JS changes. No Engine.php touches. 12 commits total (1 Brain, 11 plugin).

**Tech Stack:** PHP 8.0+, WordPress CSS custom properties (P38 v3 token system), Lucide SVG icons via `IconLibrary::icon()`.

---

## RESEARCH FINDINGS (verified before plan was written)

| Finding | Detail |
|---------|--------|
| Uppercase offenders | `.citewp-aiso-breakdown__head` line 1970; `.citewp-aiso-cs-table th` line 2182 |
| Bar thickness current | `.citewp-aiso-breakdown__bar { height: 4px }` at line 1999 |
| Gauge stops current | 3 stops: #ef4444 0% / #f7d84a 45% / #16a34a 100% |
| `bot` icon | EXISTS in `IconLibrary.php` line 58 |
| Row 2 grid | `.citewp-aiso-cs-top-grid { align-items: start }` at line 1865 |
| Issues count bug | `is_object($t_full)` always false — score stored as PHP array via `to_array()` |
| Signal array key | `$t_full['signals'][$i]['status']` — all array, no objects |
| Per-page default | `?? 10` currently — must change to `?? 5` |
| KPI `__visual` | 80×80px block duplicates the 28px `__orb` — must remove for compact layout |
| `__caption` CSS | No CSS rule for `__caption` exists — must add or rename to `__footer` |

---

## File Map

| File | Sections touched |
|------|-----------------|
| `includes/Admin/Menu.php` | `render_cite_score_panel()` (KPI cards, Cite Score Health, Score Breakdown, AI Recs, post table, history panel, per-page); `render_gauge_svg()` (gradient stops) |
| `admin/css/citewp-aiso-admin.css` | Lines 1970, 2182 (uppercase removal); Lines 1719–1751 (tooltip); Section 31 (many additions) |
| `C:\Users\KingpinBWP\Desktop\CiteWP\Brain\UI-DESIGN-SYSTEM.md` | Tooltip entry, Panel Head entry, Score Breakdown entry, Over Time entry, Last Updated |

---

## Task 1: UI-DESIGN-SYSTEM.md updates (Brain repo)

**Commit target:** citewp-brain repo (`C:\Users\KingpinBWP\Desktop\CiteWP`)
**Files:** `Brain/UI-DESIGN-SYSTEM.md`

- [ ] **Step 1.1: Read UI-DESIGN-SYSTEM.md and locate 4 targets**

Read the file. Find:
1. The "### Info Icon + Tooltip" entry (added in run #4) — to append right-align modifier
2. The Component Library section — to add new "Panel Head Title" entry
3. The "### Score Breakdown Panel" entry — to add bar thickness note
4. The "### Cite Score Over Time Chart" entry (if exists) — to add empty state copy note
5. The file's "Last Updated" or version header line

- [ ] **Step 1.2: Add right-align modifier to Info Icon + Tooltip entry**

Append to the end of the "### Info Icon + Tooltip" component entry (after the Accessibility note):

```markdown
**Right-align modifier:**
- For info icons in cards positioned at the right edge of the page (rightmost card in a row, or within ~280px of the page right edge), apply `.citewp-aiso-kpi-tooltip--align-right` to the tooltip wrapper element. This swaps `left: 0` to `right: 0`, anchoring the tooltip to the icon's right edge instead of left edge — preventing the tooltip from overflowing the viewport.
- Apply to: Issues Detected KPI card (rightmost in row 1), AI Recommendations panel title (rightmost in row 2), Cite Score Over Time panel title (rightmost in row 3).
```

- [ ] **Step 1.3: Add Panel Head Title spec entry**

After the "### Info Icon + Tooltip" entry, add a new component entry:

```markdown
### Panel Head Title

The title text displayed at the top of every card panel on the Cite Score page and Dashboard.

**Style lock:** Inter 700, 14px, `var(--citewp-obsidian)`, `text-transform: none`, `letter-spacing: 0`. No exceptions.

**Class:** `.citewp-aiso-cs-panel__title` (Cite Score panels) or whatever panel-head element carries the title. The `text-transform: none` must be explicit to prevent cascade from parent elements that may set `uppercase`.

**Why explicit:** The `.citewp-aiso-breakdown__head` and `.citewp-aiso-cs-table th` selectors historically set `text-transform: uppercase`. Removal of those rules (run #5) plus this explicit lock ensures consistent title-case rendering.
```

- [ ] **Step 1.4: Add bar thickness note to Score Breakdown Panel entry**

Find the "### Score Breakdown Panel" entry. Append to it:

```markdown
**Bar dimensions:** Track height 9px, fill height 9px (updated in run #5 from 4px for legibility). Bar colors per category: Structure → `var(--citewp-tint-purple)`, Citability → `var(--citewp-tint-blue)`, Authority → `var(--citewp-tint-teal)`.
```

- [ ] **Step 1.5: Add empty state note to Cite Score Over Time entry (if exists)**

If a "Cite Score Over Time" or "Score History Chart" component entry exists, append:

```markdown
**Empty state copy:** "Not enough history yet. Site Cite Score is recorded daily — check back tomorrow for your first data point."
```

If no such entry exists, skip this step.

- [ ] **Step 1.6: Update Last Updated line**

Find the "Last Updated" line (if present) at the top of the file. Change the date to `2026-05-03`.

- [ ] **Step 1.7: Commit to citewp-brain repo**

```bash
cd "C:\Users\KingpinBWP\Desktop\CiteWP\Brain"
git add UI-DESIGN-SYSTEM.md
git commit -m "docs: right-align tooltip modifier, Panel Head Title spec, bar thickness + empty state notes (S19 run #5)"
git push
```

---

## Task 2: Root Cause A — text-transform: uppercase removal + panel title lock

**Commit target:** plugin repo
**Files:** `admin/css/citewp-aiso-admin.css`

**Regression scope:** `.citewp-aiso-breakdown__head` exists ONLY on the Cite Score page. `.citewp-aiso-cs-table th` exists ONLY on the Cite Score page. Dashboard panel heads use different selectors — this change is Cite Score-scoped and cannot break Dashboard.

- [ ] **Step 2.1: Read admin.css lines 1960–1985 and 2175–2195**

Read both areas to see the exact current rules.

- [ ] **Step 2.2: Remove uppercase from `.citewp-aiso-breakdown__head`**

Find around line 1970:
```css
.citewp-aiso-breakdown__head {
  ...
  text-transform: uppercase;
  ...
}
```

Delete the `text-transform: uppercase;` line. Leave all other properties intact.

- [ ] **Step 2.3: Remove uppercase from `.citewp-aiso-cs-table th`**

Find around line 2182:
```css
.citewp-aiso-cs-table th {
  ...
  text-transform: uppercase;
  ...
}
```

Delete the `text-transform: uppercase;` line. Leave all other properties intact.

- [ ] **Step 2.4: Add panel title lock to Section 31**

In Section 31 of the CSS file (around line 1841), find the `.citewp-aiso-cs-panel__title` rule (or add it near the top of Section 31). Set or add:

```css
.citewp-aiso-cs-panel__title {
  font: 700 14px/1.2 'Inter', system-ui, sans-serif;
  color: var(--citewp-obsidian);
  text-transform: none;
  letter-spacing: 0;
  margin: 0 0 var(--sp-4);
}
```

If `.citewp-aiso-cs-panel__title` already exists in Section 31, merge these properties into it (don't duplicate).

- [ ] **Step 2.5: Add `text-transform: none` to `.citewp-aiso-kpi-tooltip__text`**

Find `.citewp-aiso-kpi-tooltip__text` around line 1719. Add `text-transform: none;` to the rule so tooltip text is never uppercased even when inside an element that has uppercase cascade.

Also change `max-width` from its current value to `320px`.

The rule should include:
```css
max-width: 320px;
white-space: normal;
text-transform: none;
```

- [ ] **Step 2.6: Commit**

```bash
git add admin/css/citewp-aiso-admin.css
git commit -m "fix: Root Cause A — remove text-transform uppercase from breakdown head + table th, lock panel title style, tooltip text-transform none (S19 run #5)"
```

---

## Task 3: Root Cause B — tooltip right-align modifier + apply to right-edge elements

**Commit target:** plugin repo
**Files:** `admin/css/citewp-aiso-admin.css`, `includes/Admin/Menu.php`

- [ ] **Step 3.1: Add right-align modifier CSS**

In `admin/css/citewp-aiso-admin.css`, find the `.citewp-aiso-kpi-tooltip__text` rule (around line 1719). Immediately after the existing rule block, add:

```css
/* Right-align modifier: anchors tooltip right edge to icon right edge */
.citewp-aiso-kpi-tooltip--align-right .citewp-aiso-kpi-tooltip__text {
  left: auto;
  right: 0;
}
```

- [ ] **Step 3.2: Apply modifier to Issues Detected KPI card in Menu.php**

In `render_cite_score_panel()`, find Card 3 (Issues Detected) — the third KPI card in the header band. Find its `<span class="citewp-aiso-kpi-tooltip">` and change to:

```php
<span class="citewp-aiso-kpi-tooltip citewp-aiso-kpi-tooltip--align-right">
```

- [ ] **Step 3.3: Apply modifier to AI Recommendations panel title**

Find the AI Recommendations panel `citewp-aiso-insights__header` section. The `<span class="citewp-aiso-kpi-tooltip">` inside `__header` becomes:

```php
<span class="citewp-aiso-kpi-tooltip citewp-aiso-kpi-tooltip--align-right">
```

- [ ] **Step 3.4: Apply modifier to Cite Score Over Time panel title**

Find the `citewp-aiso-cs-history-head` section. The `<span class="citewp-aiso-kpi-tooltip">` inside the `<h3>` becomes:

```php
<span class="citewp-aiso-kpi-tooltip citewp-aiso-kpi-tooltip--align-right">
```

- [ ] **Step 3.5: Commit**

```bash
git add admin/css/citewp-aiso-admin.css includes/Admin/Menu.php
git commit -m "fix: Root Cause B — tooltip right-align modifier, apply to Issues Detected / AI Recs / Cite Score Over Time (S19 run #5)"
```

---

## Task 4: FIX 1.1 — KPI card compact layout

**Commit target:** plugin repo
**Files:** `admin/css/citewp-aiso-admin.css` (Section 31), `includes/Admin/Menu.php` (3 KPI cards in header band)

**What's wrong:** Run #4 KPI cards have `__head` (orb + title) as one row + `__body` (80px visual + data) as a second row. The spec requires a single horizontal row: [28px orb LEFT] [title + value + caption stacked RIGHT]. The `__visual` 80px block must be removed. A `--compact` modifier drives the new layout.

**Regression check:** The `--compact` modifier is additive and scoped. Dashboard KPI cards do NOT use `--compact` and are unaffected.

- [ ] **Step 4.1: Add `citewp-aiso-kpi-card--compact` CSS to Section 31**

Add after the header-band rules in Section 31:

```css
/* KPI card compact variant: single horizontal row, no __visual block */
.citewp-aiso-kpi-card--compact {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
  gap: var(--sp-3);
  padding: var(--sp-4);
}

.citewp-aiso-kpi-card--compact .citewp-aiso-kpi-card__orb {
  flex-shrink: 0;
  margin-top: 2px;
}

.citewp-aiso-kpi-card--compact .citewp-aiso-kpi-card__data {
  flex: 1;
  min-width: 0;
}

.citewp-aiso-kpi-card--compact .citewp-aiso-kpi-card__title-row {
  display: flex;
  align-items: center;
  gap: 4px;
  margin-bottom: 2px;
}

.citewp-aiso-kpi-card--compact .citewp-aiso-kpi-card__title {
  font: 700 12px/1.2 'Inter', system-ui, sans-serif;
  color: var(--citewp-text-muted);
  text-transform: none;
}

.citewp-aiso-kpi-card--compact .citewp-aiso-kpi-card__value {
  font: 800 28px/1 'Inter', system-ui, sans-serif;
  color: var(--citewp-obsidian);
  margin: var(--sp-1) 0;
}

.citewp-aiso-kpi-card--compact .citewp-aiso-kpi-card__caption {
  font: 400 12px/1.3 'Inter', system-ui, sans-serif;
  color: var(--citewp-text-muted);
}
```

- [ ] **Step 4.2: Read current KPI card HTML in Menu.php (lines ~854–922)**

Read the header band section to see the exact current 3 KPI card blocks before replacing them.

- [ ] **Step 4.3: Replace all 3 KPI cards in the header band**

Find the `<div class="citewp-aiso-kpi-row citewp-aiso-kpi-row--3col">` block (inside `.citewp-aiso-cs-header-band`). Replace its entire contents (the 3 card divs) with:

```php
<!-- Card 1: Average Cite Score -->
<div class="citewp-aiso-kpi-card citewp-aiso-kpi-card--compact">
    <div class="citewp-aiso-kpi-card__orb" style="background:var(--citewp-purple-tint);color:var(--citewp-tint-purple)">
        <?php echo IconLibrary::icon( 'gauge', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>
    <div class="citewp-aiso-kpi-card__data">
        <div class="citewp-aiso-kpi-card__title-row">
            <span class="citewp-aiso-kpi-card__title"><?php esc_html_e( 'Average Cite Score', 'ai-search-optimizer' ); ?></span>
            <span class="citewp-aiso-kpi-tooltip">
                <?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <span class="citewp-aiso-kpi-tooltip__text"><?php esc_html_e( 'Average Cite Score across all scored posts and pages on this site.', 'ai-search-optimizer' ); ?></span>
            </span>
        </div>
        <div class="citewp-aiso-kpi-card__value citewp-aiso-kpi-score--<?php echo esc_attr( $avg_grade ); ?>"><?php echo $avg_score !== null ? esc_html( (string) $avg_score ) : '—'; ?></div>
        <div class="citewp-aiso-kpi-card__caption"><?php esc_html_e( 'site-wide average', 'ai-search-optimizer' ); ?></div>
    </div>
</div>

<!-- Card 2: Posts Optimized -->
<div class="citewp-aiso-kpi-card citewp-aiso-kpi-card--compact">
    <div class="citewp-aiso-kpi-card__orb" style="background:var(--citewp-green-tint);color:var(--citewp-tint-green)">
        <?php echo IconLibrary::icon( 'check-circle', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>
    <div class="citewp-aiso-kpi-card__data">
        <div class="citewp-aiso-kpi-card__title-row">
            <span class="citewp-aiso-kpi-card__title"><?php esc_html_e( 'Posts Optimized', 'ai-search-optimizer' ); ?></span>
            <span class="citewp-aiso-kpi-tooltip">
                <?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <span class="citewp-aiso-kpi-tooltip__text"><?php esc_html_e( 'Posts and pages with a Cite Score of 50 or above.', 'ai-search-optimizer' ); ?></span>
            </span>
        </div>
        <div class="citewp-aiso-kpi-card__value"><?php echo esc_html( (string) $posts_optimized ); ?></div>
        <div class="citewp-aiso-kpi-card__caption"><?php esc_html_e( 'score ≥ 50', 'ai-search-optimizer' ); ?></div>
    </div>
</div>

<!-- Card 3: Issues Detected (right-align tooltip — rightmost card) -->
<div class="citewp-aiso-kpi-card citewp-aiso-kpi-card--compact">
    <div class="citewp-aiso-kpi-card__orb" style="background:rgba(249,115,22,0.12);color:var(--citewp-tint-orange)">
        <?php echo IconLibrary::icon( 'alert-triangle', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>
    <div class="citewp-aiso-kpi-card__data">
        <div class="citewp-aiso-kpi-card__title-row">
            <span class="citewp-aiso-kpi-card__title"><?php esc_html_e( 'Issues Detected', 'ai-search-optimizer' ); ?></span>
            <span class="citewp-aiso-kpi-tooltip citewp-aiso-kpi-tooltip--align-right">
                <?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <span class="citewp-aiso-kpi-tooltip__text"><?php esc_html_e( 'Posts and pages with a Cite Score below 50 — they have the most room for improvement.', 'ai-search-optimizer' ); ?></span>
            </span>
        </div>
        <div class="citewp-aiso-kpi-card__value"><?php echo esc_html( (string) $issue_count ); ?></div>
        <div class="citewp-aiso-kpi-card__caption"><?php esc_html_e( 'score < 50', 'ai-search-optimizer' ); ?></div>
    </div>
</div>
```

Note: Task 3's right-align modifier for Issues Detected is embedded here. If Task 3 already applied it, this step replaces the old markup anyway so it's idempotent.

- [ ] **Step 4.4: Commit**

```bash
git add admin/css/citewp-aiso-admin.css includes/Admin/Menu.php
git commit -m "fix: FIX 1.1 — KPI card compact layout (orb left, data stacked right, no visual block) (S19 run #5)"
```

---

## Task 5: FIX 2.1 + 2.2 — Cite Score Health: score-wrap grid + status copy + View Score Guide button

**Commit target:** plugin repo
**Files:** `admin/css/citewp-aiso-admin.css` (Section 31), `includes/Admin/Menu.php` (Cite Score Health panel block)

- [ ] **Step 5.1: Add `.citewp-aiso-cs-score-wrap` CSS to Section 31**

```css
/* Cite Score Health — 2-col grid: gauge left (240px), copy + button right */
.citewp-aiso-cs-score-wrap {
  display: grid;
  grid-template-columns: 240px 1fr;
  align-items: center;
  gap: var(--sp-6);
  margin-top: var(--sp-4);
}

.citewp-aiso-cs-score-copy {
  font: 400 13px/1.6 'Inter', system-ui, sans-serif;
  color: var(--citewp-text-muted);
  max-width: 280px;
}

.citewp-aiso-cs-score-right {
  display: flex;
  flex-direction: column;
  gap: var(--sp-4);
}
```

- [ ] **Step 5.2: Add status copy map in render_cite_score_panel() PHP**

Read `includes/Admin/Menu.php` around line 820 (where `$avg_grade` / `$avg_score` are first available — they may be computed around lines 720–780). Find the right place to add the status copy map — after `$avg_grade` is set.

Add:

```php
$cs_status_copy = [
    'red'    => __( 'Your site needs improvement. Fix the issues below to increase your AI citation potential.',       'ai-search-optimizer' ),
    'orange' => __( 'Your site has moderate AI citation potential. Fix the issues below to increase your score.',     'ai-search-optimizer' ),
    'yellow' => __( 'Your site is performing well. Continue improving to maximize AI citation.',                       'ai-search-optimizer' ),
    'green'  => __( 'Your site is excellently optimized for AI citation.',                                            'ai-search-optimizer' ),
    'empty'  => __( 'No posts have been scored yet. Score a post to see your site\'s AI citation potential.',         'ai-search-optimizer' ),
];
$cs_status_text = $cs_status_copy[ $avg_grade ] ?? $cs_status_copy['empty'];
```

- [ ] **Step 5.3: Wrap gauge output in score-wrap and add status copy + button**

Find the Cite Score Health panel block (around lines 940–960). Currently it looks like:

```php
<div class="citewp-aiso-cs-panel">
    <h3 class="citewp-aiso-cs-panel__title">
        Cite Score Health
        <span class="citewp-aiso-kpi-tooltip">...</span>
    </h3>
    <?php $this->render_gauge_svg( $avg_score ?? 0, $avg_grade ); ?>
    <p class="citewp-cite-score-gauge__meta">
```

Change the block so the gauge and status copy are in a 2-column grid:

```php
<div class="citewp-aiso-cs-panel">
    <h3 class="citewp-aiso-cs-panel__title">
        <?php esc_html_e( 'Cite Score Health', 'ai-search-optimizer' ); ?>
        <span class="citewp-aiso-kpi-tooltip">
            <?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <span class="citewp-aiso-kpi-tooltip__text"><?php esc_html_e( 'Site-wide Cite Score is the average across all scored posts. Higher scores mean better AI citation potential.', 'ai-search-optimizer' ); ?></span>
        </span>
    </h3>
    <div class="citewp-aiso-cs-score-wrap">
        <div>
            <?php $this->render_gauge_svg( $avg_score ?? 0, $avg_grade ); ?>
            <p class="citewp-cite-score-gauge__meta">
```

Then after the closing `</p>` of the meta paragraph, add `</div>` (close the gauge div) and add the right column:

```php
            </p>
        </div><!-- /gauge col -->
        <div class="citewp-aiso-cs-score-right">
            <p class="citewp-aiso-cs-score-copy"><?php echo esc_html( $cs_status_text ); ?></p>
            <a href="https://citewp.com/cite-score-guide" target="_blank" rel="noopener noreferrer"
               class="citewp-aiso-btn citewp-aiso-btn--outline citewp-aiso-cs-score-guide-btn">
                <?php esc_html_e( 'View Score Guide →', 'ai-search-optimizer' ); ?>
            </a>
        </div><!-- /copy col -->
    </div><!-- /.citewp-aiso-cs-score-wrap -->
```

And add this CSS to Section 31:
```css
.citewp-aiso-cs-score-guide-btn {
  align-self: flex-start;
}
```

**Verify:** Read the gauge panel block before and after to confirm the HTML is well-formed (proper nesting). The `<p class="citewp-cite-score-gauge__meta">` line needs to close before the right column opens.

- [ ] **Step 5.4: Commit**

```bash
git add admin/css/citewp-aiso-admin.css includes/Admin/Menu.php
git commit -m "fix: FIX 2.1+2.2 — Cite Score Health score-wrap grid, status copy by grade, View Score Guide button (S19 run #5)"
```

---

## Task 6: FIX 2.4 — Gauge 4-stop gradient

**Commit target:** plugin repo
**Files:** `includes/Admin/Menu.php` (`render_gauge_svg()`)

- [ ] **Step 6.1: Read `render_gauge_svg()` to find the linearGradient block**

Read around lines 1295–1315. Find:

```xml
<linearGradient id="citewp-gauge-gradient" ...>
    <stop offset="0%"   stop-color="#ef4444" />
    <stop offset="45%"  stop-color="#f7d84a" />
    <stop offset="100%" stop-color="#16a34a" />
</linearGradient>
```

- [ ] **Step 6.2: Replace with 4-stop gradient**

```xml
<linearGradient id="citewp-gauge-gradient" x1="30" y1="120" x2="210" y2="120" gradientUnits="userSpaceOnUse">
    <stop offset="0%"   stop-color="#ef4444" />
    <stop offset="33%"  stop-color="#f97316" />
    <stop offset="66%"  stop-color="#f7d84a" />
    <stop offset="100%" stop-color="#16a34a" />
</linearGradient>
```

- [ ] **Step 6.3: Commit**

```bash
git add includes/Admin/Menu.php
git commit -m "fix: FIX 2.4 — gauge gradient 4 stops (red/orange/yellow/green at 0/33/66/100%) (S19 run #5)"
```

---

## Task 7: FIX 2.5 — Score Breakdown bar thickness 9px

**Commit target:** plugin repo
**Files:** `admin/css/citewp-aiso-admin.css`

- [ ] **Step 7.1: Read `.citewp-aiso-breakdown__bar` rule (line ~1999)**

Confirm `height: 4px` is the current value.

- [ ] **Step 7.2: Change bar height to 9px**

In `.citewp-aiso-breakdown__bar`:
```css
height: 9px;
```

The `__fill` uses `height: 100%` and will auto-inherit. Verify `.citewp-aiso-breakdown__fill` also has a `border-radius` that matches the updated track (should be `999px` or similar).

- [ ] **Step 7.3: Commit**

```bash
git add admin/css/citewp-aiso-admin.css
git commit -m "fix: FIX 2.5 — Score Breakdown bar thickness 4px → 9px (S19 run #5)"
```

---

## Task 8: FIX 2.7 + 2.8 — AI Recommendations: bot icon hero block + outline button

**Commit target:** plugin repo
**Files:** `includes/Admin/Menu.php` (Panel 3 AI Recommendations block)

- [ ] **Step 8.1: Read the `citewp-aiso-insights__nested-top` block in Menu.php**

Read around lines 1009–1025. The current block looks like:

```php
<div class="citewp-aiso-insights__nested-top">
    <div class="citewp-aiso-insights__orb">
        <?php echo IconLibrary::icon( 'sparkles', 24 ); ?>
    </div>
    <div class="citewp-aiso-insights__headline-wrap">
        ...
    </div>
</div>
```

- [ ] **Step 8.2: Replace the `__orb` with a 64×64 teal bot icon**

Find the `<div class="citewp-aiso-insights__orb">` line and replace it with:

```php
<div class="citewp-aiso-insights__orb"
     style="width:64px;height:64px;border-radius:14px;background:rgba(20,184,166,0.08);color:var(--citewp-tint-teal);flex-shrink:0">
    <?php echo IconLibrary::icon( 'bot', 32 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</div>
```

- [ ] **Step 8.3: Replace the "View All Recommendations →" panel link with outline button**

Find the line at the bottom of the AI Recommendations block:

```php
<a href="<?php echo esc_url( $cs_recs_url ); ?>" class="citewp-aiso-crawlers__view-all"><?php esc_html_e( 'View All Recommendations →', 'ai-search-optimizer' ); ?></a>
```

Replace with:

```php
<a href="<?php echo esc_url( $cs_recs_url ); ?>"
   class="citewp-aiso-btn citewp-aiso-btn--outline citewp-aiso-cs-recs-btn">
    <?php esc_html_e( 'View All Recommendations →', 'ai-search-optimizer' ); ?>
</a>
```

- [ ] **Step 8.4: Add `.citewp-aiso-cs-recs-btn` CSS to Section 31**

```css
.citewp-aiso-cs-recs-btn {
  display: block;
  text-align: center;
  margin-top: var(--sp-3);
}
```

- [ ] **Step 8.5: Commit**

```bash
git add admin/css/citewp-aiso-admin.css includes/Admin/Menu.php
git commit -m "fix: FIX 2.7+2.8 — AI Recs bot icon hero (64px teal), View All as outline button (S19 run #5)"
```

---

## Task 9: FIX 3.1 + 3.3 + 3.5 — Row gap fix, post link styles, per-page default 5

**Commit target:** plugin repo
**Files:** `admin/css/citewp-aiso-admin.css` (Section 31), `includes/Admin/Menu.php` (per-page line)

- [ ] **Step 9.1: Per-page default: change `?? 10` to `?? 5`**

Read `includes/Admin/Menu.php` line ~805. Find:

```php
$per_page  = in_array( (int) ( $_GET['cspp'] ?? 10 ), [ 5, 10, 25 ], true ) ? (int) ( $_GET['cspp'] ?? 10 ) : 10; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
```

Change both `?? 10` occurrences and both `: 10` occurrences to `?? 5` and `: 5`:

```php
$per_page  = in_array( (int) ( $_GET['cspp'] ?? 5 ), [ 5, 10, 25 ], true ) ? (int) ( $_GET['cspp'] ?? 5 ) : 5; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
```

- [ ] **Step 9.2: Post link styles**

In Section 31 of `admin/css/citewp-aiso-admin.css`, add:

```css
/* Post-Level table: post title links — Obsidian, no underline */
.citewp-aiso-cs-table td a {
  color: var(--citewp-obsidian);
  text-decoration: none;
}

.citewp-aiso-cs-table td a:hover {
  color: var(--citewp-tint-blue);
  text-decoration: none;
}
```

- [ ] **Step 9.3: Row 3 gap — verify and fix outer layout gap**

Read Section 31 in the CSS around lines 1862–1875 to find the lower-grid rule (`.citewp-aiso-cs-lower-grid` or equivalent). Check its `gap` or `margin-top` value.

The gap between row 2 and row 3 should be `var(--sp-4)` (16px) or `18px` — matching the gap between row 1 and row 2. If there's an excessive `margin-top` or `gap` on the lower-grid, reduce it to `var(--sp-4)`.

If the lower-grid doesn't exist as a named class, look for the outer `.citewp-aiso-cs-page` or wrapping div that contains all rows and check its `gap` or row gaps.

- [ ] **Step 9.4: Commit**

```bash
git add admin/css/citewp-aiso-admin.css includes/Admin/Menu.php
git commit -m "fix: FIX 3.1+3.3+3.5 — per-page default 5, post link Obsidian no-underline, row 3 gap consistent (S19 run #5)"
```

---

## Task 10: FIX 3.4 — Issues count data bug

**Commit target:** plugin repo
**Files:** `includes/Admin/Menu.php` (post table loop, Issues count block)

**Root cause confirmed:** `Repository::store()` calls `update_post_meta($post_id, META_KEY_FULL, $result->to_array())`. `to_array()` returns a PHP associative array. WordPress serializes it with `maybe_serialize()` and `maybe_unserialize()` returns a PHP array, NOT an object. Current code does `is_object($t_full)` → always false → `$t_issues` always stays 0.

**Signal structure:**
```php
$t_full = [
    'total'      => int,
    'grade'      => string,
    'categories' => [...],
    'signals'    => [
        0 => [ 'id' => 'slug', 'status' => 'pass'|'partial'|'fail', ... ],
        1 => [ 'id' => ..., 'status' => ..., ... ],
        ...
    ],
]
```

- [ ] **Step 10.1: Read the Issues count block in Menu.php**

Read around lines 1133–1148. Find:

```php
$t_full_raw  = get_post_meta( $t_id, '_citewp_aiso_geo_score', true );
$t_issues    = 0;
if ( $t_full_raw !== '' ) {
    $t_full = maybe_unserialize( $t_full_raw );
    if ( is_object( $t_full ) && isset( $t_full->signals ) && is_array( $t_full->signals ) ) {
        foreach ( $t_full->signals as $sig ) {
            if ( isset( $sig->status ) && $sig->status === 'fail' ) {
                $t_issues++;
            }
        }
    }
}
```

- [ ] **Step 10.2: Replace with array-based check**

```php
$t_full_raw = get_post_meta( $t_id, Repository::META_KEY_FULL, true );
$t_issues   = 0;
if ( $t_full_raw !== '' ) {
    $t_full = maybe_unserialize( $t_full_raw );
    if ( is_array( $t_full ) && ! empty( $t_full['signals'] ) && is_array( $t_full['signals'] ) ) {
        foreach ( $t_full['signals'] as $sig ) {
            if ( is_array( $sig ) && ( $sig['status'] ?? '' ) === 'fail' ) {
                $t_issues++;
            }
        }
    }
}
```

Note: uses `Repository::META_KEY_FULL` instead of the hardcoded string `'_citewp_aiso_geo_score'` — they're the same key, but this is cleaner. Verify `Repository` is `use`'d at the top of the method's class (grep the file for `use CiteWP\Aiso\Scoring\Repository`).

- [ ] **Step 10.3: Commit**

```bash
git add includes/Admin/Menu.php
git commit -m "fix: FIX 3.4 — Issues count data bug — array not object check, $t_full['signals'][$i]['status'] (S19 run #5)"
```

---

## Task 11: FIX 2.3 + 4.1 — Card height enforcement (align-items: stretch on row 2 + row 3 grids)

**Commit target:** plugin repo
**Files:** `admin/css/citewp-aiso-admin.css` (Section 31)

- [ ] **Step 11.1: Change row 2 grid align-items to stretch**

Find `.citewp-aiso-cs-top-grid` (around line 1865). Change `align-items: start` to `align-items: stretch`.

- [ ] **Step 11.2: Find and change row 3 grid align-items to stretch**

Search for `.citewp-aiso-cs-lower-grid` or equivalent (the 2-column grid holding the post table + history chart). Change `align-items: start` to `align-items: stretch` there too (if it uses `start`).

Also verify that `.citewp-aiso-cs-panel` has no fixed `height` that would prevent stretch. If needed, ensure `.citewp-aiso-cs-panel` has `height: 100%` so it fills the grid cell.

- [ ] **Step 11.3: Verify Cite Score Health panel card can stretch**

The Cite Score Health panel now has a score-wrap grid. Ensure the panel itself has `align-self: stretch` or inherits stretch from the grid container. Add if needed:

```css
.citewp-aiso-cs-top-grid > .citewp-aiso-cs-panel {
  height: 100%;
  box-sizing: border-box;
}
```

- [ ] **Step 11.4: Commit**

```bash
git add admin/css/citewp-aiso-admin.css
git commit -m "fix: FIX 2.3+4.1 — align-items stretch on row 2 + row 3 grids for equal card heights (S19 run #5)"
```

---

## Task 12: Code-reviewer + X20 audit + cleanup commit

**Commit target:** plugin repo (cleanup diff only)
**Files:** Any files flagged by review

- [ ] **Step 12.1: Dispatch code-reviewer subagent**

Use `feature-dev:code-reviewer` with this context:
- Files changed: `includes/Admin/Menu.php`, `admin/css/citewp-aiso-admin.css`
- Key checks:
  1. Root Cause A: grep `admin/css/citewp-aiso-admin.css` for `text-transform: uppercase` within `.citewp-aiso-breakdown__head` and `.citewp-aiso-cs-table th` — should return 0
  2. Issues count: confirm `is_array($t_full)` (not `is_object`) and `$sig['status']` (not `$sig->status`)
  3. KPI cards: confirm NO `citewp-aiso-kpi-card__visual` or `citewp-aiso-kpi-card__body` elements in the Cite Score KPI cards
  4. Regression: Dashboard sections of Menu.php should still use original KPI card structure (no `--compact`)

- [ ] **Step 12.2: X20 component spec audit**

| Component | Required class | Check |
|-----------|---------------|-------|
| KPI Card | `citewp-aiso-kpi-card citewp-aiso-kpi-card--compact` | grep |
| KPI Card compact layout | `citewp-aiso-kpi-card__data` + `__title-row` | grep |
| AI Insights outer | `citewp-aiso-insights` (not `citewp-aiso-recs`) | grep |
| Pro Tip Footer | `citewp-aiso-protip` | grep |
| Gauge container | `citewp-cite-score-gauge` | grep |
| Score pill | `citewp-aiso-score-pill` | grep |
| Panel link | "View All Posts" uses `citewp-aiso-crawlers__view-all` | grep |
| "View All Recs" | uses `citewp-aiso-btn--outline` NOT `citewp-aiso-crawlers__view-all` | grep |
| Right-align tooltips | Issues Detected + AI Recs + History title use `--align-right` modifier | grep |

- [ ] **Step 12.3: Fix all blockers**

Apply any fixes from the reviewer or X20 audit.

- [ ] **Step 12.4: Cleanup commit**

```bash
git add includes/Admin/Menu.php admin/css/citewp-aiso-admin.css
git commit -m "fix: code-reviewer + X20 audit cleanup (S19 run #5)"
```

Then push:

```bash
git push
```

---

## Smoke Test Checklist

Manual browser verification at `admin.php?page=citewp#cite-score`:

- [ ] **No uppercase anywhere unintentional.** "Score Breakdown" renders as Title Case (not SCORE BREAKDOWN). "Post" column header renders as "Post" (not "POST"). Tooltip body text renders as written, not uppercase.
- [ ] **Tooltip alignment.** KPI card 1 (Average Cite Score) tooltip opens LEFT-aligned. KPI card 3 (Issues Detected) tooltip opens RIGHT-aligned and does not overflow right edge. AI Recommendations tooltip opens RIGHT-aligned. Cite Score Over Time tooltip opens RIGHT-aligned.
- [ ] **KPI cards:** Row 1 shows [28px orb LEFT] | [title / big value / small caption] stacked right. No 80px visual icon block. Paper background. Score value on card 1 shows score-band color.
- [ ] **Cite Score Health:** Gauge LEFT (240px). Status copy paragraph RIGHT ("Your site needs improvement..." or similar by grade). "View Score Guide →" outline button at bottom-right. Panel height matches Score Breakdown card.
- [ ] **Gauge gradient:** Inspect SVG — 4 stop-color attributes: #ef4444 / #f97316 / #f7d84a / #16a34a. At score 31, gauge arc is in late-red/early-orange zone.
- [ ] **Score Breakdown bars:** 9px tall (inspect DevTools height). Purple / Blue / Teal bar colors.
- [ ] **AI Recommendations hero block:** 64×64 teal-tinted square with bot SVG centered. Headline + subheadline to right.
- [ ] **AI Recommendations bottom:** "View All Recommendations →" renders as an OUTLINE BUTTON (not plain link).
- [ ] **Post table:** Issues column shows actual numbers (NOT "No issues" for posts with low scores). Post with score 22 should show multiple issues.
- [ ] **Post table:** Per-page defaults to 5. Changing to 10 or 25 works.
- [ ] **Post table:** Post title links are Obsidian color, no underline. Hover changes to tint-blue.
- [ ] **Card heights:** Row 2 cards (Cite Score Health / Score Breakdown / AI Recs) are equal height. Row 3 cards (Post table / History chart) are equal height.
- [ ] **No regressions:** Dashboard, Crawler Logs, Settings, EditorPanel sidebar — spot check that panel heads are unaffected by the uppercase removal.
- [ ] **Dashboard KPI cards**: Still use original layout (head + body + visual) — no `--compact` class on Dashboard cards.

---

## Constraints

- `Engine.php` NO-TOUCH
- `SCORING-RUBRIC.md` NO-TOUCH
- Brain commits to `citewp-brain` repo (`C:\Users\KingpinBWP\Desktop\CiteWP\Brain`)
- Plugin commits to `ai-search-optimizer` repo
- `npm run build` NOT required (PHP + CSS only)
- PHP lint not configured
