# Cite Score Page Run #4 — Fix-in-Place Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix all component reuse failures and UI spec deviations in the Cite Score sitewide dashboard, working in-place on the run #3 codebase — no rebuilds.

**Architecture:** Fix-in-place pass on two files (`Menu.php` + `citewp-aiso-admin.css`) plus one Brain edit (`UI-DESIGN-SYSTEM.md`). Each FIX maps to a specific code location. Ten commits, one per logical group. No Engine.php touches. No JS changes.

**Tech Stack:** PHP 8.0+, WordPress CSS custom properties, Lucide SVG icons via `IconLibrary::icon()`.

---

## REUSE AUDIT — Must pass before code-reviewer sign-off

| Component | Dashboard class (canonical) | Run #3 class | Status |
|-----------|----------------------------|--------------|--------|
| KPI Card | `citewp-aiso-kpi-card` | `citewp-aiso-kpi-card` | ✅ PASS |
| AI Insights outer | `citewp-aiso-insights` | `citewp-aiso-recs` | ❌ FAIL — Task 4 fixes |
| Pro Tip Footer | `citewp-aiso-protip` | `citewp-aiso-protip` | ✅ PASS |
| Panel Link style | `citewp-aiso-crawlers__view-all` | none | ❌ MISSING — Task 4 adds |
| Tooltip | `citewp-aiso-kpi-tooltip` | not used on CS page | ❌ MISSING — Tasks 3,5,7 add |

---

## File Map

| File | Sections touched |
|------|-----------------|
| `includes/Admin/Menu.php` | `render_cite_score_panel()` lines ~803–1168, `render_gauge_svg()` lines ~1170–1210, `render_history_svg()` |
| `admin/css/citewp-aiso-admin.css` | Section 31 (lines 1841+), tooltip CSS (lines 1719–1751) |
| `C:\Users\KingpinBWP\Desktop\CiteWP\Brain\UI-DESIGN-SYSTEM.md` | Add Info Icon + Tooltip component entry after Panel Link |

---

## Task 1: Brain edits — UI-DESIGN-SYSTEM.md tooltip component entry (FIX X-2)

**Commit target:** `citewp-brain` repo
**Files:** `C:\Users\KingpinBWP\Desktop\CiteWP\Brain\UI-DESIGN-SYSTEM.md`

- [ ] **Step 1.1: Open UI-DESIGN-SYSTEM.md and locate "Panel Link" entry in Component Library**

Find the Panel Link component entry. The new "Info Icon + Tooltip" entry goes immediately after it.

- [ ] **Step 1.2: Add Info Icon + Tooltip component entry**

After the Panel Link entry, append:

```markdown
### Info Icon + Tooltip

Inline info icon next to a panel title that reveals a tooltip on hover/focus. Used to provide supplementary context without crowding the visible UI.

**Anatomy:**
- Icon: 14px Lucide `info` SVG, `color: var(--citewp-text-muted)`, `margin-left: 6px` from preceding title text
- Wrapper: `<span class="citewp-aiso-kpi-tooltip">` (position: relative, inline-flex, cursor: help)
- Tooltip box: `<span class="citewp-aiso-kpi-tooltip__text">` — max-width 280px, padding 8px 10px, background `var(--citewp-white)`, border `1px solid var(--citewp-border)`, border-radius `var(--radius-sm)`, font Inter 400 12px line-height 1.4, color `var(--citewp-text-secondary)`, box-shadow subtle
- Show on wrapper hover; hide otherwise. Transition: opacity 0.15s.
- **LEFT-aligned** (tooltip left edge aligned to icon left edge): `left: 0; right: auto` — NOT centered, NOT right-anchored.
- Z-index: 100

**HTML pattern:**
```html
<span class="citewp-aiso-kpi-tooltip">
  <?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore ?>
  <span class="citewp-aiso-kpi-tooltip__text">Tooltip copy here.</span>
</span>
```

**Usage:** Every panel head with optional supplementary context. Cite Score Health, Score Breakdown, AI Recommendations, Post-Level Cite Scores, Cite Score Over Time.

**Accessibility:** The `<span>` wrapper has `cursor: help`. Screen readers read tooltip text via the `info` SVG's `<title>` element.
```

- [ ] **Step 1.3: Add score-band bar color note to Score Breakdown Panel entry (if it exists)**

Find the Score Breakdown Panel component entry. Add note: "Bar colors are per-category tint (NOT score-band): Structure = `var(--citewp-tint-purple)`, Citability = `var(--citewp-tint-blue)`, Authority = `var(--citewp-tint-teal)`."

- [ ] **Step 1.4: Commit to citewp-brain repo**

```bash
cd "C:\Users\KingpinBWP\Desktop\CiteWP"
git add Brain/UI-DESIGN-SYSTEM.md
git commit -m "docs: Info Icon + Tooltip component entry + bar color note (S19 run #4)"
git push
```

---

## Task 2: Gauge replacement (FIX 2.1 + 2.2 + 2.3)

**Commit target:** plugin repo
**Files:** `admin/css/citewp-aiso-admin.css` (Section 31), `includes/Admin/Menu.php` (`render_gauge_svg` + gauge panel HTML)

### 2A — CSS: Replace gauge class names in Section 31

- [ ] **Step 2.1: In `admin/css/citewp-aiso-admin.css`, replace the gauge CSS block (lines ~1877–1925)**

The current block uses `.citewp-aiso-cs-gauge*` classes. Replace entirely with:

```css
/* Gauge: citewp-cite-score-gauge container + scoped gauge-* internals */
.citewp-cite-score-gauge {
  width: 240px;
  max-width: 100%;
}

.citewp-cite-score-gauge svg {
  width: 100%;
  height: auto;
  overflow: visible;
}

.citewp-cite-score-gauge .gauge-bg {
  fill: none;
  stroke: #e5e7eb;
  stroke-width: 14;
  stroke-linecap: round;
}

.citewp-cite-score-gauge .gauge-score {
  fill: none;
  stroke: url(#citewp-gauge-gradient);
  stroke-width: 14;
  stroke-linecap: round;
  stroke-dasharray: var(--score) 100;
  stroke-dashoffset: 0;
}

.citewp-cite-score-gauge .gauge-number {
  font-size: 52px;
  font-weight: 800;
  fill: var(--citewp-obsidian);
  font-family: 'JetBrains Mono', monospace;
}

.citewp-cite-score-gauge .gauge-total {
  font-size: 18px;
  fill: var(--citewp-text-muted);
}

.citewp-cite-score-gauge .gauge-label {
  font-size: 13px;
  font-weight: 700;
}

/* Score-band label colors — driven by modifier class on container */
.citewp-cite-score-gauge--red    .gauge-label { fill: var(--citewp-score-red); }
.citewp-cite-score-gauge--orange .gauge-label { fill: var(--citewp-score-orange); }
.citewp-cite-score-gauge--yellow .gauge-label { fill: var(--citewp-score-yellow); }
.citewp-cite-score-gauge--green  .gauge-label { fill: var(--citewp-score-green); }
.citewp-cite-score-gauge--empty  .gauge-label { fill: var(--citewp-text-muted); }
```

Remove the old `.citewp-aiso-cs-gauge__meta` rule and the old `citewp-aiso-cs-gauge*` rules entirely.

For the `citewp-aiso-cs-gauge__meta` paragraph (scored count text), add a replacement rule:

```css
.citewp-cite-score-gauge__meta {
  margin-top: 12px;
  font: 400 12px/1.4 'Inter', system-ui, sans-serif;
  color: var(--citewp-text-muted);
  text-align: center;
}
```

### 2B — PHP: Replace `render_gauge_svg()` method

- [ ] **Step 2.2: In `includes/Admin/Menu.php`, replace the entire `render_gauge_svg()` method body (lines ~1170–1210)**

Find the method starting with `private function render_gauge_svg( int $score, string $grade ): void {` and replace its body:

```php
private function render_gauge_svg( int $score, string $grade ): void {
    $score        = max( 0, min( 100, $score ) );
    $grade_labels = [
        'green'  => __( 'Excellent',         'ai-search-optimizer' ),
        'yellow' => __( 'Good',              'ai-search-optimizer' ),
        'orange' => __( 'Fair',              'ai-search-optimizer' ),
        'red'    => __( 'Needs Improvement', 'ai-search-optimizer' ),
        'empty'  => __( 'No data',           'ai-search-optimizer' ),
    ];
    $grade_label = $grade_labels[ $grade ] ?? '';
    ?>
    <div class="citewp-cite-score-gauge citewp-cite-score-gauge--<?php echo esc_attr( $grade ); ?>"
         style="--score:<?php echo esc_attr( (string) $score ); ?>">
        <svg viewBox="0 0 240 140" role="img"
             aria-label="<?php printf( esc_attr__( 'Cite Score %1$d out of 100, %2$s', 'ai-search-optimizer' ), $score, $grade_label ); ?>">
            <defs>
                <linearGradient id="citewp-gauge-gradient" x1="30" y1="120" x2="210" y2="120" gradientUnits="userSpaceOnUse">
                    <stop offset="0%"   stop-color="#ef4444" />
                    <stop offset="45%"  stop-color="#f7d84a" />
                    <stop offset="100%" stop-color="#16a34a" />
                </linearGradient>
            </defs>
            <path class="gauge-bg" d="M 30 120 A 90 90 0 0 1 210 120" pathLength="100" />
            <path class="gauge-score" d="M 30 120 A 90 90 0 0 1 210 120" pathLength="100" />
            <text x="120" y="88" text-anchor="middle" class="gauge-number">
                <?php echo esc_html( $score > 0 ? (string) $score : '—' ); ?>
            </text>
            <text x="120" y="112" text-anchor="middle" class="gauge-total">/100</text>
            <text x="120" y="132" text-anchor="middle" class="gauge-label">
                <?php echo esc_html( $grade_label ); ?>
            </text>
        </svg>
    </div>
    <?php
}
```

### 2C — PHP: Add tooltip to "Cite Score Health" panel title and remove View Score Guide button (FIX 2.3 + 2.2)

- [ ] **Step 2.3: In `render_cite_score_panel()`, find the Cite Score Health panel HTML (around line 927) and update**

Find:
```php
<div class="citewp-aiso-cs-panel">
    <h3 class="citewp-aiso-cs-panel__title"><?php esc_html_e( 'Cite Score Health', 'ai-search-optimizer' ); ?></h3>
    <?php $this->render_gauge_svg( $avg_score ?? 0, $avg_grade ); ?>
    <p class="citewp-aiso-cs-gauge__meta">
```

Replace with:
```php
<div class="citewp-aiso-cs-panel">
    <h3 class="citewp-aiso-cs-panel__title">
        <?php esc_html_e( 'Cite Score Health', 'ai-search-optimizer' ); ?>
        <span class="citewp-aiso-kpi-tooltip">
            <?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <span class="citewp-aiso-kpi-tooltip__text"><?php esc_html_e( 'Site-wide Cite Score is the average across all scored posts. Higher scores mean better AI citation potential.', 'ai-search-optimizer' ); ?></span>
        </span>
    </h3>
    <?php $this->render_gauge_svg( $avg_score ?? 0, $avg_grade ); ?>
    <p class="citewp-cite-score-gauge__meta">
```

Also verify: no `<button>` / "View Score Guide" element exists in this panel block. If it does, delete it.

- [ ] **Step 2.4: Commit**

```bash
git add admin/css/citewp-aiso-admin.css includes/Admin/Menu.php
git commit -m "fix: gauge — citewp-cite-score-gauge class, literal gradient stops, obsidian number, score-band label, tooltip on Cite Score Health title (S19 run #4)"
```

---

## Task 3: Row 1 KPI restructure (FIX 1.1 + 1.2)

**Commit target:** plugin repo
**Files:** `admin/css/citewp-aiso-admin.css` (Section 31), `includes/Admin/Menu.php` (render_cite_score_panel header area)

### 3A — CSS: Header band layout

- [ ] **Step 3.1: Add CSS for header band in Section 31 of `citewp-aiso-admin.css`**

Find the Section 31 header and add after the existing grid rules:

```css
/* Row 1: header band (title+desc left, 3 KPI cards right) */
.citewp-aiso-cs-header-band {
  display: grid;
  grid-template-columns: 1fr auto;
  align-items: center;
  gap: var(--sp-6);
  margin-bottom: var(--sp-6);
}

.citewp-aiso-cs-header-band__left h2 {
  margin: 0 0 var(--sp-1);
  font: 800 20px/1.2 'Inter', system-ui, sans-serif;
  color: var(--citewp-text-primary);
}

.citewp-aiso-cs-header-band__left p {
  margin: 0;
  font: 400 13px/1.5 'Inter', system-ui, sans-serif;
  color: var(--citewp-text-muted);
}
```

### 3B — PHP: Merge page header + KPI row into header band

- [ ] **Step 3.2: In `render_cite_score_panel()`, replace the separate page-header div + kpi-row div with a combined header band**

Find this block (lines ~844–912):
```php
<!-- Page header strip -->
<div class="citewp-aiso-page-header">
    <div class="citewp-aiso-page-header__left">
        <h2 class="citewp-aiso-page-header__title"><?php esc_html_e( 'Cite Score', ... ); ?></h2>
        <p class="citewp-aiso-page-header__desc"><?php esc_html_e( 'Track and improve...', ... ); ?></p>
    </div>
</div>

<!-- KPI cards row -->
<div class="citewp-aiso-kpi-row citewp-aiso-kpi-row--3col">
    <!-- Card 1 ... -->
    <!-- Card 2 ... -->
    <!-- Card 3 ... -->
</div><!-- /.citewp-aiso-kpi-row -->
```

Replace with (one combined element):
```php
<!-- Row 1: page title + KPI cards in single horizontal band -->
<div class="citewp-aiso-cs-header-band">
    <div class="citewp-aiso-cs-header-band__left">
        <h2><?php esc_html_e( 'Cite Score', 'ai-search-optimizer' ); ?></h2>
        <p><?php esc_html_e( 'Track and improve your site\'s AI citation potential.', 'ai-search-optimizer' ); ?></p>
    </div>

    <div class="citewp-aiso-kpi-row citewp-aiso-kpi-row--3col">

        <!-- Card 1: Average Cite Score -->
        <div class="citewp-aiso-kpi-card">
            <div class="citewp-aiso-kpi-card__head">
                <div class="citewp-aiso-kpi-card__orb" style="background:var(--citewp-purple-tint);color:var(--citewp-tint-purple)">
                    <?php echo IconLibrary::icon( 'gauge', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
                <span class="citewp-aiso-kpi-card__title"><?php esc_html_e( 'Average Cite Score', 'ai-search-optimizer' ); ?></span>
                <span class="citewp-aiso-kpi-tooltip">
                    <?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <span class="citewp-aiso-kpi-tooltip__text"><?php esc_html_e( 'Average Cite Score across all scored posts and pages on this site.', 'ai-search-optimizer' ); ?></span>
                </span>
            </div>
            <div class="citewp-aiso-kpi-card__body">
                <div class="citewp-aiso-kpi-card__visual" style="background:var(--citewp-purple-tint);color:var(--citewp-tint-purple)">
                    <?php echo IconLibrary::icon( 'gauge', 36 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
                <div class="citewp-aiso-kpi-card__data">
                    <div class="citewp-aiso-kpi-card__value citewp-aiso-kpi-score--<?php echo esc_attr( $avg_grade ); ?>"><?php echo $avg_score !== null ? esc_html( (string) $avg_score ) : '—'; ?></div>
                    <div class="citewp-aiso-kpi-card__caption"><?php esc_html_e( 'site-wide average', 'ai-search-optimizer' ); ?></div>
                </div>
            </div>
        </div>

        <!-- Card 2: Posts Optimized -->
        <div class="citewp-aiso-kpi-card">
            <div class="citewp-aiso-kpi-card__head">
                <div class="citewp-aiso-kpi-card__orb" style="background:var(--citewp-green-tint);color:var(--citewp-tint-green)">
                    <?php echo IconLibrary::icon( 'check-circle', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
                <span class="citewp-aiso-kpi-card__title"><?php esc_html_e( 'Posts Optimized', 'ai-search-optimizer' ); ?></span>
                <span class="citewp-aiso-kpi-tooltip">
                    <?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <span class="citewp-aiso-kpi-tooltip__text"><?php esc_html_e( 'Posts and pages with a Cite Score of 50 or above.', 'ai-search-optimizer' ); ?></span>
                </span>
            </div>
            <div class="citewp-aiso-kpi-card__body">
                <div class="citewp-aiso-kpi-card__visual" style="background:var(--citewp-green-tint);color:var(--citewp-tint-green)">
                    <?php echo IconLibrary::icon( 'check-circle', 36 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
                <div class="citewp-aiso-kpi-card__data">
                    <div class="citewp-aiso-kpi-card__value"><?php echo esc_html( (string) $posts_optimized ); ?></div>
                    <div class="citewp-aiso-kpi-card__caption"><?php esc_html_e( 'score ≥ 50', 'ai-search-optimizer' ); ?></div>
                </div>
            </div>
        </div>

        <!-- Card 3: Issues Detected -->
        <div class="citewp-aiso-kpi-card">
            <div class="citewp-aiso-kpi-card__head">
                <div class="citewp-aiso-kpi-card__orb" style="background:rgba(249,115,22,0.12);color:var(--citewp-tint-orange)">
                    <?php echo IconLibrary::icon( 'alert-triangle', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
                <span class="citewp-aiso-kpi-card__title"><?php esc_html_e( 'Issues Detected', 'ai-search-optimizer' ); ?></span>
                <span class="citewp-aiso-kpi-tooltip">
                    <?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <span class="citewp-aiso-kpi-tooltip__text"><?php esc_html_e( 'Posts and pages with a Cite Score below 50 — they have the most room for improvement.', 'ai-search-optimizer' ); ?></span>
                </span>
            </div>
            <div class="citewp-aiso-kpi-card__body">
                <div class="citewp-aiso-kpi-card__visual" style="background:rgba(249,115,22,0.12);color:var(--citewp-tint-orange)">
                    <?php echo IconLibrary::icon( 'alert-triangle', 36 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
                <div class="citewp-aiso-kpi-card__data">
                    <div class="citewp-aiso-kpi-card__value"><?php echo esc_html( (string) $issue_count ); ?></div>
                    <div class="citewp-aiso-kpi-card__caption"><?php esc_html_e( 'score < 50', 'ai-search-optimizer' ); ?></div>
                </div>
            </div>
        </div>

    </div><!-- /.citewp-aiso-kpi-row -->
</div><!-- /.citewp-aiso-cs-header-band -->
```

**Reuse verification (required):** Inspect element in browser after this step. Confirm `.citewp-aiso-kpi-card` and `.citewp-aiso-kpi-card__head`, `__orb`, `__title`, `__body`, `__visual`, `__data`, `__value`, `__caption` are the LITERAL class names rendered in the HTML, matching the Dashboard's KPI cards exactly.

- [ ] **Step 3.3: Commit**

```bash
git add admin/css/citewp-aiso-admin.css includes/Admin/Menu.php
git commit -m "fix: Row 1 — merge page header + KPI row into header band, add tooltips to KPI cards (S19 run #4)"
```

---

## Task 4: AI Recommendations rebuild against AI Insights class (FIX 2.8)

**Commit target:** plugin repo
**Files:** `admin/css/citewp-aiso-admin.css` (add rec-row CSS), `includes/Admin/Menu.php` (Panel 3 HTML)

**REUSE REQUIREMENT:** The outer card MUST use `citewp-aiso-insights`. All `citewp-aiso-recs*` class names are replaced. The nested hero block reuses `citewp-aiso-insights__nested`, `citewp-aiso-insights__nested-top`, `citewp-aiso-insights__nested-bottom`. The 3 rec rows use new `citewp-aiso-cs-rec-row*` classes (Cite Score-specific content, no Dashboard equivalent).

### 4A — CSS: Add rec-row classes and "View All" panel link

- [ ] **Step 4.1: In `admin/css/citewp-aiso-admin.css`, remove all `citewp-aiso-recs*` rules from Section 31 (lines ~2067–2125)**

Delete the entire block of `.citewp-aiso-recs`, `.citewp-aiso-recs__head`, `.citewp-aiso-recs__icon`, `.citewp-aiso-recs__title`, `.citewp-aiso-recs__badge`, `.citewp-aiso-recs__row`, `.citewp-aiso-recs__label`, `.citewp-aiso-recs__affected`, `.citewp-aiso-recs__copy` rules.

- [ ] **Step 4.2: Add rec-row CSS in their place**

```css
/* AI Recommendations rec rows (Cite Score-specific, inside citewp-aiso-insights) */
.citewp-aiso-cs-rec-row {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-3) 0;
  border-top: 1px solid var(--citewp-border);
}

.citewp-aiso-cs-rec-row:first-child {
  border-top: none;
  padding-top: 0;
}

.citewp-aiso-cs-rec-row__orb {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.citewp-aiso-cs-rec-row__text {
  flex: 1;
  min-width: 0;
}

.citewp-aiso-cs-rec-row__title {
  font: 600 13px/1.3 'Inter', system-ui, sans-serif;
  color: var(--citewp-text-primary);
}

.citewp-aiso-cs-rec-row__sub {
  font: 400 12px/1.4 'Inter', system-ui, sans-serif;
  color: var(--citewp-text-muted);
  margin-top: 2px;
}
```

### 4B — PHP: Replace Panel 3 HTML in render_cite_score_panel()

- [ ] **Step 4.3: Replace the entire Panel 3 block (lines ~972–999) with the citewp-aiso-insights structure**

Find the block starting with `<!-- Panel 3: AI Recommendations -->` through `</div>` for `citewp-aiso-recs` and replace with:

```php
<?php
// Category → orb tint + icon mapping for rec rows
$rec_cat_meta = [
    'structure'  => [ 'bg' => 'rgba(124,58,237,0.12)', 'color' => 'var(--citewp-tint-purple)', 'icon' => 'layout'   ],
    'citability' => [ 'bg' => 'rgba(37,99,235,0.12)',   'color' => 'var(--citewp-tint-blue)',   'icon' => 'quote'    ],
    'authority'  => [ 'bg' => 'rgba(20,184,166,0.12)',  'color' => 'var(--citewp-tint-teal)',   'icon' => 'shield'   ],
    ''           => [ 'bg' => 'rgba(100,116,139,0.12)', 'color' => 'var(--citewp-text-muted)',  'icon' => 'sparkles' ],
];
$recs_count = count( array_filter( $recs_display, static fn( $r ) => isset( $r['label'] ) && $r['label'] !== __( 'Keep publishing', 'ai-search-optimizer' ) ) );
$cs_recs_url = admin_url( 'edit.php' );
?>
<!-- Panel 3: AI Recommendations (uses citewp-aiso-insights class — same as Dashboard AI Insights) -->
<div class="citewp-aiso-insights">
    <div class="citewp-aiso-insights__header">
        <span class="citewp-aiso-insights__title"><?php esc_html_e( 'AI Recommendations', 'ai-search-optimizer' ); ?></span>
        <span class="citewp-aiso-insights__badge"><?php esc_html_e( 'BETA', 'ai-search-optimizer' ); ?></span>
        <span class="citewp-aiso-kpi-tooltip">
            <?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <span class="citewp-aiso-kpi-tooltip__text"><?php esc_html_e( 'Recommendations are derived from the most common failed signals across your scored posts. Fixing these has the highest impact on your overall Cite Score.', 'ai-search-optimizer' ); ?></span>
        </span>
    </div>
    <div class="citewp-aiso-insights__body">
        <div class="citewp-aiso-insights__nested">
            <div class="citewp-aiso-insights__nested-top">
                <div class="citewp-aiso-insights__orb">
                    <?php echo IconLibrary::icon( 'sparkles', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
                <div class="citewp-aiso-insights__headline-wrap">
                    <p class="citewp-aiso-insights__headline"><?php esc_html_e( 'Your content can rank higher in AI search results.', 'ai-search-optimizer' ); ?></p>
                    <p class="citewp-aiso-insights__sub">
                        <?php
                        printf(
                            /* translators: %d: number of high-impact opportunities */
                            esc_html__( 'We found %d high-impact opportunities to improve.', 'ai-search-optimizer' ),
                            max( 1, $recs_count )
                        );
                        ?>
                    </p>
                </div>
            </div>
            <div class="citewp-aiso-insights__nested-bottom">
                <?php foreach ( $recs_display as $idx => $rec ) :
                    $rec_signal_id = $top_rec_ids[ $idx ] ?? '';
                    $fail_count    = $signal_fails[ $rec_signal_id ] ?? 0;
                    $cat_key       = $rec['category'] ?? '';
                    $cat_orb       = $rec_cat_meta[ $cat_key ] ?? $rec_cat_meta[''];
                    $view_url      = admin_url( 'edit.php' );
                ?>
                <div class="citewp-aiso-cs-rec-row">
                    <div class="citewp-aiso-cs-rec-row__orb" style="background:<?php echo esc_attr( $cat_orb['bg'] ); ?>;color:<?php echo esc_attr( $cat_orb['color'] ); ?>">
                        <?php echo IconLibrary::icon( $cat_orb['icon'], 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                    <div class="citewp-aiso-cs-rec-row__text">
                        <div class="citewp-aiso-cs-rec-row__title">
                            <?php echo esc_html( $rec['label'] );
                            if ( $fail_count > 0 ) {
                                echo esc_html( ' (' . $fail_count . ' ' . _n( 'page', 'pages', $fail_count, 'ai-search-optimizer' ) . ')' );
                            } ?>
                        </div>
                        <div class="citewp-aiso-cs-rec-row__sub"><?php echo esc_html( $rec['copy'] ); ?></div>
                    </div>
                    <a href="<?php echo esc_url( $view_url ); ?>" class="citewp-aiso-btn citewp-aiso-btn--outline"><?php esc_html_e( 'View Pages', 'ai-search-optimizer' ); ?></a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <a href="<?php echo esc_url( $cs_recs_url ); ?>" class="citewp-aiso-crawlers__view-all"><?php esc_html_e( 'View All Recommendations →', 'ai-search-optimizer' ); ?></a>
    </div>
</div>
```

**Reuse verification (required):** Inspect element. Confirm outer container has class `citewp-aiso-insights` (NOT `citewp-aiso-recs`). Confirm `citewp-aiso-insights__header`, `__body`, `__nested`, `__nested-top`, `__nested-bottom` are present. Confirm panel link uses `citewp-aiso-crawlers__view-all`.

**Icon check:** Verify `IconLibrary::icon('layout', 16)`, `IconLibrary::icon('quote', 16)`, `IconLibrary::icon('shield', 16)` return SVG strings. If any icon is missing from `IconLibrary::$icons`, add it using the same Lucide inline SVG pattern as the other icons in that file.

- [ ] **Step 4.4: Commit**

```bash
git add admin/css/citewp-aiso-admin.css includes/Admin/Menu.php
git commit -m "fix: AI Recommendations — replace citewp-aiso-recs with citewp-aiso-insights class reuse, add rec rows + View All panel link (S19 run #4)"
```

---

## Task 5: Score Breakdown bar colors + Obsidian values + tooltips + factor icons (FIX 2.4 + 2.5 + 2.6 + 2.7)

**Commit target:** plugin repo
**Files:** `includes/Admin/Menu.php` (Score Breakdown panel block, lines ~943–970)

- [ ] **Step 5.1: Add `$cat_colors` map near `$cat_meta` (line ~826)**

After the `$cat_meta` array declaration, add:

```php
$cat_colors = [
    'structure'  => 'var(--citewp-tint-purple)',
    'citability' => 'var(--citewp-tint-blue)',
    'authority'  => 'var(--citewp-tint-teal)',
];
$cat_icons = [
    'structure'  => 'layout',
    'citability' => 'quote',
    'authority'  => 'shield',
];
```

- [ ] **Step 5.2: Replace Score Breakdown panel HTML (lines ~943–970)**

Find the `<!-- Panel 2: Score Breakdown -->` block and replace with:

```php
<!-- Panel 2: Score Breakdown -->
<div class="citewp-aiso-breakdown">
    <div class="citewp-aiso-breakdown__head">
        <?php esc_html_e( 'Score Breakdown', 'ai-search-optimizer' ); ?>
        <span class="citewp-aiso-kpi-tooltip">
            <?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <span class="citewp-aiso-kpi-tooltip__text"><?php esc_html_e( 'Your Cite Score breaks down across 3 categories. Each category contains multiple signals that AI engines consider when citing content.', 'ai-search-optimizer' ); ?></span>
        </span>
    </div>
    <?php foreach ( $cat_meta as $cat_key => $cat_info ) :
        $avg_cat   = $cat_avgs[ $cat_key ] ?? 0;
        $cat_max   = $cat_info['max'];
        $pct       = $cat_max > 0 ? ( $avg_cat / $cat_max ) * 100 : 0;
        $bar_color = $cat_colors[ $cat_key ] ?? 'var(--citewp-text-muted)';
        $cat_icon  = $cat_icons[ $cat_key ] ?? 'gauge';
    ?>
    <div class="citewp-aiso-breakdown__row">
        <div class="citewp-aiso-breakdown__label-row">
            <span class="citewp-aiso-breakdown__label">
                <span style="color:<?php echo esc_attr( $bar_color ); ?>;display:inline-flex;align-items:center;margin-right:4px">
                    <?php echo IconLibrary::icon( $cat_icon, 12 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </span>
                <?php echo esc_html( $cat_info['label'] ); ?>
            </span>
            <span class="citewp-aiso-breakdown__score" style="color:var(--citewp-obsidian)">
                <?php echo esc_html( $avg_cat . ' / ' . $cat_max ); ?>
            </span>
        </div>
        <div class="citewp-aiso-breakdown__bar">
            <div class="citewp-aiso-breakdown__fill" style="width:<?php echo esc_attr( round( $pct, 1 ) . '%' ); ?>;background:<?php echo esc_attr( $bar_color ); ?>"></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
```

**Key changes:** `$bar_color` is now category-tint (not score-band). Score value `color` is `var(--citewp-obsidian)` (not `$bar_color`). Each row has a small tint-colored category icon.

**Icon check:** Same as Task 4 — verify `layout`, `quote`, `shield` exist in IconLibrary.

- [ ] **Step 5.3: Commit**

```bash
git add includes/Admin/Menu.php
git commit -m "fix: Score Breakdown — category tint bar colors, Obsidian score values, tooltip on title, category icons (S19 run #4)"
```

---

## Task 6: Post-Level table — header, columns, score pills, footer, per-page default (FIX 3.1–3.5)

**Commit target:** plugin repo
**Files:** `admin/css/citewp-aiso-admin.css` (score pill CSS), `includes/Admin/Menu.php` (table section)

### 6A — CSS: Score pill and pagination layout

- [ ] **Step 6.1: Add score pill CSS and updated pagination CSS to Section 31**

```css
/* Score badge: numeric pill */
.citewp-aiso-score-pill {
  display: inline-flex;
  justify-content: center;
  align-items: center;
  min-width: 42px;
  padding: 7px 8px;
  border-radius: 8px;
  font: 700 12px/1 'JetBrains Mono', monospace;
}
.citewp-aiso-score-pill--red    { background: rgba(239,68,68,0.12);   color: var(--citewp-score-red); }
.citewp-aiso-score-pill--orange { background: rgba(249,115,22,0.12);  color: var(--citewp-score-orange); }
.citewp-aiso-score-pill--yellow { background: rgba(219,166,23,0.12);  color: var(--citewp-score-yellow); }
.citewp-aiso-score-pill--green  { background: rgba(0,163,42,0.12);    color: var(--citewp-score-green); }

/* Table: trend + issues cells */
.citewp-aiso-cs-table__trend--up   { color: var(--citewp-tint-green); font: 700 12px/1 'Inter', system-ui, sans-serif; }
.citewp-aiso-cs-table__trend--down { color: var(--citewp-tint-red);   font: 700 12px/1 'Inter', system-ui, sans-serif; }
.citewp-aiso-cs-table__trend--flat { color: var(--citewp-text-muted); font: 700 12px/1 'Inter', system-ui, sans-serif; }
.citewp-aiso-cs-table__issues--active { color: var(--citewp-tint-orange); font: 700 12px/1 'Inter', system-ui, sans-serif; }
.citewp-aiso-cs-table__issues--none   { color: var(--citewp-tint-green);  font: 700 12px/1 'Inter', system-ui, sans-serif; }

/* Pagination: 3-zone footer layout */
.citewp-aiso-cs-pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-4);
  padding: var(--sp-3) 0 0;
  border-top: 1px solid var(--citewp-border);
  margin-top: var(--sp-3);
}

.citewp-aiso-cs-pagination__pages {
  display: flex;
  align-items: center;
  gap: 4px;
}

.citewp-aiso-cs-pagination__page {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 28px;
  height: 28px;
  padding: 0 6px;
  border-radius: 6px;
  border: 1px solid var(--citewp-border);
  font: 400 12px/1 'Inter', system-ui, sans-serif;
  color: var(--citewp-obsidian);
  text-decoration: none;
  background: transparent;
}

.citewp-aiso-cs-pagination__page.is-active {
  background: var(--citewp-obsidian);
  color: #ffffff;
  border-color: var(--citewp-obsidian);
}

/* Table panel header: title + tooltip left, search right */
.citewp-aiso-cs-table-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-4);
  margin-bottom: var(--sp-4);
}

.citewp-aiso-cs-table-head__title {
  display: flex;
  align-items: center;
  gap: 4px;
  font: 700 14px/1 'Inter', system-ui, sans-serif;
  color: var(--citewp-text-primary);
}
```

### 6B — PHP: Fix per-page default + valid values (FIX 3.5)

- [ ] **Step 6.2: In `render_cite_score_panel()`, fix line ~805**

Find:
```php
$per_page  = in_array( (int) ( $_GET['cspp'] ?? 20 ), [ 10, 20, 50 ], true ) ? (int) ( $_GET['cspp'] ?? 20 ) : 20;
```

Replace with:
```php
$per_page  = in_array( (int) ( $_GET['cspp'] ?? 10 ), [ 5, 10, 25 ], true ) ? (int) ( $_GET['cspp'] ?? 10 ) : 10; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
```

### 6C — PHP: Replace table section HTML

- [ ] **Step 6.3: Replace the entire `<!-- Lower-left: Post-level score table -->` block (lines ~1007–1119)**

```php
<!-- Lower-left: Post-level score table -->
<div class="citewp-aiso-cs-table-wrap">

    <!-- Panel head: title + tooltip + search -->
    <div class="citewp-aiso-cs-table-head">
        <span class="citewp-aiso-cs-table-head__title">
            <?php esc_html_e( 'Post-Level Cite Scores', 'ai-search-optimizer' ); ?>
            <span class="citewp-aiso-kpi-tooltip">
                <?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <span class="citewp-aiso-kpi-tooltip__text"><?php esc_html_e( 'All scored posts on your site, sorted by lowest Cite Score first. Click Optimize to open the post and improve its score.', 'ai-search-optimizer' ); ?></span>
            </span>
        </span>
        <form method="get" action="<?php echo esc_url( $base_url ); ?>">
            <input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG_PARENT ); ?>">
            <input type="hidden" name="cspp" value="<?php echo esc_attr( (string) $per_page ); ?>">
            <input
                type="search"
                name="css"
                class="citewp-aiso-cs-search"
                value="<?php echo esc_attr( $search_q ); ?>"
                placeholder="<?php esc_attr_e( 'Search posts…', 'ai-search-optimizer' ); ?>"
            >
        </form>
    </div>

    <!-- Per-page select (moves to footer, but keep hidden input here for search form compat) -->

    <?php if ( $tbl_q->have_posts() ) : ?>
    <table class="citewp-aiso-cs-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Post',       'ai-search-optimizer' ); ?></th>
                <th><?php esc_html_e( 'Cite Score', 'ai-search-optimizer' ); ?></th>
                <th><?php esc_html_e( 'Trend',      'ai-search-optimizer' ); ?></th>
                <th><?php esc_html_e( 'Last Updated', 'ai-search-optimizer' ); ?></th>
                <th><?php esc_html_e( 'Issues',     'ai-search-optimizer' ); ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php while ( $tbl_q->have_posts() ) :
                $tbl_q->the_post();
                $t_id       = (int) get_the_ID();
                $t_score    = (int) get_post_meta( $t_id, Repository::META_KEY_TOTAL, true );
                $t_grade    = get_post_meta( $t_id, Repository::META_KEY_GRADE, true );
                $t_grade    = is_string( $t_grade ) && in_array( $t_grade, [ 'red', 'orange', 'yellow', 'green' ], true ) ? $t_grade : 'red';
                $t_time_raw = get_post_meta( $t_id, Repository::META_KEY_TIME, true );
                $t_time_ts  = is_string( $t_time_raw ) && $t_time_raw !== '' ? (int) strtotime( $t_time_raw ) : 0;
                $t_time_ago = $t_time_ts > 0 ? human_time_diff( $t_time_ts, time() ) . ' ' . __( 'ago', 'ai-search-optimizer' ) : '—';
                $t_post_type = (string) get_post_type();
                $t_type_icon = $t_post_type === 'page' ? 'file-text' : 'file';
                $t_edit_url  = get_edit_post_link( $t_id );
                // Issues: count failed signals from full score result
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
                // Trend: no per-post history available yet → show "—"
                $t_trend_html = '<span class="citewp-aiso-cs-table__trend--flat">—</span>';
            ?>
            <tr>
                <td>
                    <span style="display:inline-flex;align-items:center;gap:4px">
                        <span style="color:var(--citewp-text-muted);display:inline-flex"><?php echo IconLibrary::icon( $t_type_icon, 12 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        <?php if ( $t_edit_url ) : ?>
                        <a href="<?php echo esc_url( $t_edit_url ); ?>"><?php echo esc_html( get_the_title() ?: __( '(no title)', 'ai-search-optimizer' ) ); ?></a>
                        <?php else : ?>
                        <?php echo esc_html( get_the_title() ?: __( '(no title)', 'ai-search-optimizer' ) ); ?>
                        <?php endif; ?>
                    </span>
                </td>
                <td><span class="citewp-aiso-score-pill citewp-aiso-score-pill--<?php echo esc_attr( $t_grade ); ?>"><?php echo esc_html( (string) $t_score ); ?></span></td>
                <td><?php echo $t_trend_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                <td class="citewp-aiso-cs-table__time"><?php echo esc_html( $t_time_ago ); ?></td>
                <td>
                    <?php if ( $t_issues > 0 ) : ?>
                    <span class="citewp-aiso-cs-table__issues--active"><?php echo esc_html( $t_issues . ' ' . _n( 'issue', 'issues', $t_issues, 'ai-search-optimizer' ) ); ?></span>
                    <?php else : ?>
                    <span class="citewp-aiso-cs-table__issues--none"><?php esc_html_e( 'No issues', 'ai-search-optimizer' ); ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ( $t_edit_url ) : ?>
                    <a href="<?php echo esc_url( $t_edit_url ); ?>" class="citewp-aiso-btn citewp-aiso-btn--outline"><?php esc_html_e( 'Optimize →', 'ai-search-optimizer' ); ?></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; wp_reset_postdata(); ?>
        </tbody>
    </table>

    <!-- Footer: View All Posts (left) | numbered pagination (center) | per-page select (right) -->
    <div class="citewp-aiso-cs-pagination">
        <a href="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>" class="citewp-aiso-crawlers__view-all"><?php esc_html_e( 'View All Posts →', 'ai-search-optimizer' ); ?></a>

        <?php if ( $total_pages > 1 ) : ?>
        <div class="citewp-aiso-cs-pagination__pages">
            <?php for ( $pg = 1; $pg <= $total_pages; $pg++ ) :
                $pg_url = esc_url( add_query_arg( array_merge( $base_q, [ 'csp' => $pg, 'cspp' => $per_page, 'css' => $search_q ] ), $base_url ) . '#cite-score' );
            ?>
            <?php if ( $pg === $paged ) : ?>
            <span class="citewp-aiso-cs-pagination__page is-active"><?php echo esc_html( (string) $pg ); ?></span>
            <?php else : ?>
            <a href="<?php echo $pg_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" class="citewp-aiso-cs-pagination__page"><?php echo esc_html( (string) $pg ); ?></a>
            <?php endif; ?>
            <?php endfor; ?>
        </div>
        <?php else : ?>
        <span></span>
        <?php endif; ?>

        <form method="get" action="<?php echo esc_url( $base_url ); ?>">
            <input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG_PARENT ); ?>">
            <input type="hidden" name="css"  value="<?php echo esc_attr( $search_q ); ?>">
            <select name="cspp" class="citewp-aiso-cs-perpage" onchange="this.form.submit()">
                <?php foreach ( [ 5, 10, 25 ] as $pp ) : ?>
                <option value="<?php echo esc_attr( (string) $pp ); ?>"<?php selected( $pp, $per_page ); ?>><?php echo esc_html( $pp . ' ' . __( 'per page', 'ai-search-optimizer' ) ); ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php else : ?>
    <div class="citewp-aiso-cs-empty">
        <div class="citewp-aiso-empty__icon"><?php echo IconLibrary::icon( 'gauge', 48 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
        <h3 class="citewp-aiso-empty__title">
            <?php echo $search_q !== '' ? esc_html__( 'No posts match your search.', 'ai-search-optimizer' ) : esc_html__( 'No scored posts found.', 'ai-search-optimizer' ); ?>
        </h3>
    </div>
    <?php endif; ?>

</div><!-- /.citewp-aiso-cs-table-wrap -->
```

**Icon check:** Verify `file`, `file-text` exist in IconLibrary. Add if missing.

- [ ] **Step 6.4: Commit**

```bash
git add admin/css/citewp-aiso-admin.css includes/Admin/Menu.php
git commit -m "fix: Post-Level table — panel head+tooltip, new columns (Post/Score/Trend/LastUpdated/Issues/Action), numeric score pills, 3-zone footer pagination, default 10/page (S19 run #4)"
```

---

## Task 7: Cite Score Over Time — spacing, SELECT dropdown, tooltip, empty state copy (FIX 4.1–4.4)

**Commit target:** plugin repo
**Files:** `includes/Admin/Menu.php` (history panel block lines ~1122–1149, `render_history_svg()`)

- [ ] **Step 7.1: Replace the Cite Score Over Time panel HTML (lines ~1122–1149)**

Find `<!-- Lower-right: Score history chart -->` block and replace:

```php
<!-- Lower-right: Cite Score Over Time -->
<div class="citewp-aiso-cs-panel">
    <div class="citewp-aiso-cs-history-head">
        <h3 class="citewp-aiso-cs-panel__title">
            <?php esc_html_e( 'Cite Score Over Time', 'ai-search-optimizer' ); ?>
            <span class="citewp-aiso-kpi-tooltip">
                <?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <span class="citewp-aiso-kpi-tooltip__text"><?php esc_html_e( 'Site-wide Cite Score is recorded daily. The chart shows your average across the selected timeframe.', 'ai-search-optimizer' ); ?></span>
            </span>
        </h3>
        <form method="get" action="<?php echo esc_url( $base_url ); ?>" style="margin:0">
            <input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG_PARENT ); ?>">
            <input type="hidden" name="csp"  value="<?php echo esc_attr( (string) $paged ); ?>">
            <input type="hidden" name="cspp" value="<?php echo esc_attr( (string) $per_page ); ?>">
            <input type="hidden" name="css"  value="<?php echo esc_attr( $search_q ); ?>">
            <select name="cs_range" class="citewp-aiso-cs-perpage" onchange="this.form.submit()">
                <?php foreach ( [ 7 => __( 'Last 7 Days', 'ai-search-optimizer' ), 30 => __( 'Last 30 Days', 'ai-search-optimizer' ), 90 => __( 'Last 90 Days', 'ai-search-optimizer' ) ] as $days => $label ) : ?>
                <option value="<?php echo esc_attr( (string) $days ); ?>"<?php selected( $days, $history_range ); ?>><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <?php $this->render_history_svg( $history ); ?>
    <?php if ( $hist_avg !== null ) : ?>
    <div class="citewp-aiso-history-panel__stats">
        <div>
            <div class="citewp-aiso-history-panel__stat-label"><?php esc_html_e( 'Avg Score', 'ai-search-optimizer' ); ?></div>
            <div class="citewp-aiso-history-panel__stat-value"><?php echo esc_html( (string) $hist_avg ); ?></div>
        </div>
        <div>
            <div class="citewp-aiso-history-panel__stat-label"><?php esc_html_e( 'Peak', 'ai-search-optimizer' ); ?></div>
            <div class="citewp-aiso-history-panel__stat-value"><?php echo esc_html( (string) $hist_peak ); ?></div>
        </div>
    </div>
    <?php endif; ?>
</div>
```

- [ ] **Step 7.2: Add `.citewp-aiso-cs-history-head` CSS to Section 31**

```css
.citewp-aiso-cs-history-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-4);
  margin-bottom: var(--sp-4);
}

.citewp-aiso-cs-history-head .citewp-aiso-cs-panel__title {
  margin: 0;
  display: flex;
  align-items: center;
  gap: 4px;
}
```

- [ ] **Step 7.3: Fix empty state copy in `render_history_svg()`**

Find the empty state string in `render_history_svg()`. Look for "Not enough history yet" or "Scores appear after the daily cron runs" and replace with:

```php
esc_html_e( 'Not enough history yet. Site Cite Score is recorded daily — check back tomorrow for your first data point.', 'ai-search-optimizer' )
```

- [ ] **Step 7.4: Commit**

```bash
git add admin/css/citewp-aiso-admin.css includes/Admin/Menu.php
git commit -m "fix: Cite Score Over Time — history-head layout, SELECT dropdown timeframe, tooltip, empty state copy (S19 run #4)"
```

---

## Task 8: Pro Tip Footer button label + correct copy (FIX 5.1)

**Commit target:** plugin repo
**Files:** `includes/Admin/Menu.php` (lines ~1153–1165)

- [ ] **Step 8.1: Update Pro Tip HTML in render_cite_score_panel()**

Find the Pro Tip block (lines ~1153–1165):

```php
<div class="citewp-aiso-protip">
    <div class="citewp-aiso-protip__left">
        <div class="citewp-aiso-protip__orb"><?php echo IconLibrary::icon( 'zap', 16 ); ?></div>
        <div class="citewp-aiso-protip__content">
            <p class="citewp-aiso-protip__heading"><?php esc_html_e( 'Pro Tip', 'ai-search-optimizer' ); ?></p>
            <p class="citewp-aiso-protip__body"><?php esc_html_e( 'Adding FAQ schema to your top posts is the fastest way to raise your site-wide Cite Score.', 'ai-search-optimizer' ); ?></p>
        </div>
    </div>
    <a href="https://citewp.com/pro" target="_blank" rel="noopener noreferrer" class="citewp-aiso-btn citewp-aiso-btn--primary-paper">
        <?php esc_html_e( 'Learn More →', 'ai-search-optimizer' ); ?>
    </a>
</div>
```

Replace with:

```php
<?php
$cs_protip = apply_filters(
    'citewp_aiso/protip',
    __( 'Connect Google Search Console to get more insights and improve your Cite Score faster.', 'ai-search-optimizer' ),
    'cite-score'
);
?>
<div class="citewp-aiso-protip">
    <div class="citewp-aiso-protip__left">
        <div class="citewp-aiso-protip__orb"><?php echo IconLibrary::icon( 'zap', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
        <div class="citewp-aiso-protip__content">
            <p class="citewp-aiso-protip__heading"><?php esc_html_e( 'Pro Tip', 'ai-search-optimizer' ); ?></p>
            <p class="citewp-aiso-protip__body"><?php echo esc_html( $cs_protip ); ?></p>
        </div>
    </div>
    <a href="https://citewp.com/pro" target="_blank" rel="noopener noreferrer" class="citewp-aiso-btn citewp-aiso-btn--primary-paper">
        <?php esc_html_e( 'Connect Now →', 'ai-search-optimizer' ); ?>
    </a>
</div>
```

**Reuse verification:** Inspect element. Confirm outer element is `citewp-aiso-protip` (matches Dashboard's Pro Tip class exactly).

- [ ] **Step 8.2: Commit**

```bash
git add includes/Admin/Menu.php
git commit -m "fix: Pro Tip footer — Connect Now button label, correct copy via apply_filters protip (S19 run #4)"
```

---

## Task 9: Tooltip alignment — left-aligned (FIX X-1)

**Commit target:** plugin repo
**Files:** `admin/css/citewp-aiso-admin.css` (lines ~1728–1751)

- [ ] **Step 9.1: Change tooltip text alignment from right to left**

Find `.citewp-aiso-kpi-tooltip__text` CSS rule (around line 1728):

```css
.citewp-aiso-kpi-tooltip__text {
  position: absolute;
  top: calc(100% + 6px);
  right: 0;       /* ← current: right-aligned */
  left: auto;     /* ← current */
  ...
```

Change to:

```css
.citewp-aiso-kpi-tooltip__text {
  position: absolute;
  top: calc(100% + 6px);
  left: 0;        /* LEFT-aligned per FIX X-1 */
  right: auto;
  ...
```

Everything else in the rule stays the same.

- [ ] **Step 9.2: Commit**

```bash
git add admin/css/citewp-aiso-admin.css
git commit -m "fix: tooltip alignment — left: 0 (left-aligned, per FIX X-1) across all citewp-aiso-kpi-tooltip instances (S19 run #4)"
```

---

## Task 10: Code-reviewer + X20 audit + cleanup

**Commit target:** plugin repo (cleanup diff only)
**Files:** Any file flagged by the review

- [ ] **Step 10.1: Dispatch code-reviewer subagent**

Use `feature-dev:code-reviewer` with this context:
- Files changed: `includes/Admin/Menu.php`, `admin/css/citewp-aiso-admin.css`
- Reuse audit to perform (literal class name check, not near-name):
  - KPI cards: HTML must contain `citewp-aiso-kpi-card` (not aliased)
  - AI Recommendations outer: HTML must contain `citewp-aiso-insights` (not `citewp-aiso-recs`)
  - Pro Tip: HTML must contain `citewp-aiso-protip`
  - Panel link: AI Recommendations "View All" must use `citewp-aiso-crawlers__view-all`
- X20 component spec audit: compare each Component Library entry in UI-DESIGN-SYSTEM.md against the implementation

- [ ] **Step 10.2: X20 spec audit — manually verify each component**

| Component | Spec class | Implementation class | Pass? |
|-----------|-----------|---------------------|-------|
| KPI Card | `citewp-aiso-kpi-card` | (inspect) | |
| AI Insights outer | `citewp-aiso-insights` | (inspect) | |
| Pro Tip Footer | `citewp-aiso-protip` | (inspect) | |
| Gauge container | `citewp-cite-score-gauge` | (inspect) | |
| Score pill | `citewp-aiso-score-pill` | (inspect) | |
| Panel link | `citewp-aiso-crawlers__view-all` | (inspect) | |
| Tooltip | `citewp-aiso-kpi-tooltip` | (inspect) | |

- [ ] **Step 10.3: Fix all code-reviewer and X20 blockers**

Apply any fixes flagged.

- [ ] **Step 10.4: Commit cleanup**

```bash
git add includes/Admin/Menu.php admin/css/citewp-aiso-admin.css
git commit -m "fix: code-reviewer + X20 audit cleanup (S19 run #4)"
```

---

## Smoke Test Checklist

After all commits, manually verify in browser at `admin.php?page=citewp#cite-score`:

- [ ] Row 1: page title + desc left, 3 KPI cards right in same horizontal band. Inspect element: cards have `citewp-aiso-kpi-card` class exactly.
- [ ] KPI cards: hover info icon shows left-aligned tooltip.
- [ ] Gauge: number is Obsidian regardless of score. Label below is score-band colored. Gradient sweeps proportionally.
- [ ] Score Breakdown bars: purple (Structure) / blue (Citability) / teal (Authority). NO red or score-band colors.
- [ ] Score Breakdown values (e.g., "9 / 35"): all Obsidian. Tooltip shows on title hover.
- [ ] AI Recommendations: inspect element shows `citewp-aiso-insights` as outer class (NOT `citewp-aiso-recs`). Hero block + 3 rec rows + "View All Recommendations →" panel link visible.
- [ ] Post table: columns are Post / Cite Score / Trend / Last Updated / Issues / (action). NO Grade column. Score cells show numeric pills. Action button says "Optimize →".
- [ ] Post table footer: "View All Posts →" left, pagination center (hidden if 1 page), per-page select right (default "10 per page").
- [ ] Cite Score Over Time: title with tooltip left, SELECT dropdown right. Verify empty state reads "Not enough history yet. Site Cite Score is recorded daily — check back tomorrow for your first data point."
- [ ] Pro Tip footer: button says "Connect Now →". Tip text says "Connect Google Search Console to get more insights and improve your Cite Score faster."
- [ ] All tooltips open LEFT-aligned (tooltip left edge aligns to icon left edge).
- [ ] NO regressions on Dashboard, Crawler Logs, Settings.
