# Cite Score Page Run #6 — Final Polish Pass Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restructure the Cite Score page to a two-column independent stack layout, reverse tooltip default alignment, fix Score Breakdown title, and polish the Post-Level Cite Scores table — finalizing S19 Task 2.

**Architecture:** Fix-in-place on two plugin files (`Menu.php` + `citewp-aiso-admin.css`) and the Brain design doc (`UI-DESIGN-SYSTEM.md`). The PHP layout restructure moves panels from two horizontal rows (3-col + 2-col) to a single 2fr/1fr outer grid with column-internal stacking. 6 commits total.

**Tech Stack:** PHP 8.0+, WordPress CSS custom properties (P38 v3 tokens), Lucide SVG via `IconLibrary::icon()`.

---

## RESEARCH FINDINGS (verified before plan was written)

| Finding | Detail |
|---------|--------|
| Row 2 grid CSS | `.citewp-aiso-cs-top-grid` lines 1915–1921: `grid-template-columns: 1.3fr 1.1fr 1.35fr` |
| Row 3 grid CSS | `.citewp-aiso-cs-lower-grid` lines 1923–1929: `grid-template-columns: 1.65fr 1fr` |
| Tooltip default | `.citewp-aiso-kpi-tooltip__text` line ~1728: `left: 0; right: auto` (LEFT-aligned by default) |
| Tooltip max-width | `320px` currently |
| Right-align modifier | `.citewp-aiso-kpi-tooltip--align-right` lines 1755–1758: `left: auto; right: 0` |
| Modifier usage | 3 instances: Issues Detected (line 916), AI Recs (line 1026), Over Time (line 1237) |
| Table cell padding | `padding: var(--sp-3)` = 12px all sides |
| Table header font | `font: 700 11px/1 'Inter'...` |
| Actions column th | Blank `<th></th>` at line 1127 |
| Row 2 PHP | Lines 938–1088: top-grid with Health, Breakdown, AI Recs |
| Row 3 PHP | Lines 1090–1269: lower-grid with Post table, Over Time |
| AI Recs PHP vars | Lines 1010–1019: `$rec_cat_meta`, `$recs_count`, `$cs_recs_url` inside top-grid |
| Score Breakdown head | `.citewp-aiso-breakdown__head` (line 977) — different title class than `.citewp-aiso-cs-panel__title` |

---

## File Map

| File | Sections touched |
|------|-----------------|
| `Brain/UI-DESIGN-SYSTEM.md` | Info Icon + Tooltip entry, Two-Column Stack layout pattern, Post-Level table note, Last Updated |
| `admin/css/citewp-aiso-admin.css` | Lines 1915–1929 (old grids → new layout); lines 1728–1758 (tooltip reversal + max-width); `__head` font; `th` font 12px; Section 31 additions |
| `includes/Admin/Menu.php` | Lines 938–1269 (layout restructure + AI Recs move); line 916 (tooltip rename); line 1127 (Actions th); first-td span |

---

## Task 1: UI-DESIGN-SYSTEM.md updates (Brain doc — file edits only, no git)

**Files:**
- Modify: `C:\Users\KingpinBWP\Desktop\CiteWP\Brain\UI-DESIGN-SYSTEM.md`

- [ ] **Step 1.1: Read UI-DESIGN-SYSTEM.md**

Read the full file. Locate:
1. The "### Info Icon + Tooltip" entry and its "Right-align modifier" sub-section (added in run #5)
2. Any "Layout Patterns" section
3. Any "### Post-Level Cite Scores Table" or similar entry
4. The "Last Updated" header line

- [ ] **Step 1.2: Update Info Icon + Tooltip entry (FIX T3)**

Find the "Right-align modifier" sub-section inside "### Info Icon + Tooltip". Replace the entire "Right-align modifier" paragraph with:

```markdown
**Default alignment: right-anchored.** Tooltip extends to the LEFT of the icon (icon at the tooltip's right edge). This works for icons in the left and middle of any row because the tooltip has full page width to spread leftward.

**Left-align modifier (`.citewp-aiso-kpi-tooltip--align-left`):** Apply this modifier on icons in the LAST card of any row (rightmost card, or any icon within ~280px of the page right edge). The modifier anchors the tooltip's left edge to the icon's left edge so the tooltip extends rightward instead of leftward — preventing it from running off the left edge of its parent card.

Apply to: Issues Detected KPI card (rightmost in row 1), AI Recommendations panel title (right column), Cite Score Over Time panel title (right column).

Tooltip max-width: 360px.
```

- [ ] **Step 1.3: Add "Two-Column Independent Stack" layout pattern (Update C)**

Find or create a "## Layout Patterns" section. Add:

```markdown
### Two-Column Independent Stack

Used on: Cite Score page.

**Outer grid:** `display: grid; grid-template-columns: 2fr 1fr; gap: 18px; margin-top: 24px`

**Left column:** `display: flex; flex-direction: column; gap: 18px` — may contain a sub-row for paired cards.

**Left sub-row:** `display: grid; grid-template-columns: 1fr 1fr; gap: 18px; align-items: stretch` — `stretch` enforces equal height within the sub-row only.

**Right column:** `display: flex; flex-direction: column; gap: 18px` — cards flow at natural heights.

Key rule: the two columns flow independently. Left column height ≠ right column height. Cards in the right column are NOT height-matched to any left column card. Column bottom offset is intentional and matches the reference design.
```

- [ ] **Step 1.4: Update Post-Level Cite Scores Table entry (Update B)**

Find any "### Post-Level Cite Scores" or "### Post-Level Cite Scores Table" entry. If it exists, append:

```markdown
**Column header font:** Inter 700, 12px, `var(--citewp-text-muted)`, `text-transform: none`, `letter-spacing: 0.04em`.

**Action column header:** `Actions` (title case, matching other column headers — not a blank `<th>`).
```

If no such entry exists, skip this step.

- [ ] **Step 1.5: Update Last Updated line (Update D)**

Find the "Last Updated" line. Change or append the date to `2026-05-03`.

*(No git commit — Brain folder has no git repo. File save is sufficient.)*

---

## Task 2: FIX L1 — Layout restructure to two-column independent stack

**Files:**
- Modify: `admin/css/citewp-aiso-admin.css`
- Modify: `includes/Admin/Menu.php`

**Commit 2 target:** Plugin repo.

### Step 2.1: Read CSS lines 1910–1960

Read to confirm exact current content of `.citewp-aiso-cs-top-grid`, `.citewp-aiso-cs-lower-grid`, and any `> .citewp-aiso-cs-panel` height rules added in run #5.

### Step 2.2: Replace old grid CSS with new layout classes

Find the entire block from `.citewp-aiso-cs-top-grid {` through the closing `}` of `.citewp-aiso-cs-lower-grid > .citewp-aiso-cs-panel` (or through all run #5 height rules that target these grids). Replace with:

```css
/* === Two-column independent stack — replaces old top-grid + lower-grid (run #6) === */
.citewp-aiso-cite-score-page__body {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 18px;
  margin-top: 24px;
}

.citewp-aiso-cite-score-page__left {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.citewp-aiso-cite-score-page__left-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 18px;
  align-items: stretch;
}

.citewp-aiso-cite-score-page__left-row > .citewp-aiso-cs-panel,
.citewp-aiso-cite-score-page__left-row > .citewp-aiso-breakdown {
  height: 100%;
  box-sizing: border-box;
}

.citewp-aiso-cite-score-page__right {
  display: flex;
  flex-direction: column;
  gap: 18px;
}
```

### Step 2.3: Read Menu.php lines 936–1095

Read to confirm the exact text of the grid open/close tags before making edits.

### Step 2.4: Replace top-grid open with new outer structure

Find (exact match including whitespace):
```php
		<!-- Row 2: 3-column top grid -->
		<div class="citewp-aiso-cs-top-grid">
```

Replace with:
```php
		<!-- Body: Two-column independent stack -->
		<div class="citewp-aiso-cite-score-page__body">

			<!-- Left column: 2fr — Cite Score Health + Score Breakdown sub-row, then Post table -->
			<div class="citewp-aiso-cite-score-page__left">

				<!-- Left sub-row: Cite Score Health + Score Breakdown (equal height via stretch) -->
				<div class="citewp-aiso-cite-score-page__left-row">
```

### Step 2.5: Read Menu.php lines 1008–1092

Read to see the exact PHP vars block ($rec_cat_meta, $recs_count, $cs_recs_url) + AI Recs panel + top-grid close. You will need to both (a) remove this block from here and (b) add it inside the right column in Step 2.7. Note the exact content so you can reproduce it accurately in Step 2.7.

### Step 2.6: Remove PHP vars + AI Recs + top-grid close from the left section

Find the block starting with:
```php
			<?php
			// Category → orb tint + icon mapping for rec rows
			$rec_cat_meta = [
```

...ending with:
```php
		</div><!-- /.citewp-aiso-cs-top-grid -->
```

Replace this entire block (from the `<?php` comment line through the top-grid close div) with:
```php
			</div><!-- /.citewp-aiso-cite-score-page__left-row -->
```

### Step 2.7: Replace lower-grid open + comment

Find:
```php
		<!-- Row 3: 2-column lower grid -->
		<div class="citewp-aiso-cs-lower-grid">

			<!-- Lower-left: Post-level score table -->
```

Replace with:
```php
			<!-- Post-Level Cite Scores table — full width of left column -->
```

### Step 2.8: Replace lower-grid close + Over Time with full right column

Read lines 1228–1270 to confirm exact current content of the table-wrap close + Over Time panel + lower-grid close.

Find the section starting with:
```php
			</div><!-- /.citewp-aiso-cs-table-wrap -->

			<!-- Lower-right: Cite Score Over Time -->
```

...through:
```php
		</div><!-- /.citewp-aiso-cs-lower-grid -->
```

Replace this entire block with the following (which closes the left column, opens the right column, re-adds AI Recs + Over Time, closes right column and body):

```php
			</div><!-- /.citewp-aiso-cs-table-wrap -->

		</div><!-- /.citewp-aiso-cite-score-page__left -->

		<!-- Right column: 1fr — AI Recommendations + Cite Score Over Time -->
		<div class="citewp-aiso-cite-score-page__right">

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
					<span class="citewp-aiso-kpi-tooltip citewp-aiso-kpi-tooltip--align-left">
						<?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span class="citewp-aiso-kpi-tooltip__text"><?php esc_html_e( 'Recommendations are derived from the most common failed signals across your scored posts. Fixing these has the highest impact on your overall Cite Score.', 'ai-search-optimizer' ); ?></span>
					</span>
				</div>
				<div class="citewp-aiso-insights__body">
					<div class="citewp-aiso-insights__nested">
						<div class="citewp-aiso-insights__nested-top">
							<div class="citewp-aiso-insights__orb"
								 style="width:64px;height:64px;border-radius:14px;background:rgba(20,184,166,0.08);color:var(--citewp-tint-teal);flex-shrink:0">
								<?php echo IconLibrary::icon( 'bot', 32 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
							<div class="citewp-aiso-insights__headline-wrap">
								<p class="citewp-aiso-insights__headline"><?php esc_html_e( 'Your content can rank higher in AI search results.', 'ai-search-optimizer' ); ?></p>
								<?php if ( $recs_count > 0 ) : ?>
								<p class="citewp-aiso-insights__sub">
									<?php
									printf(
										/* translators: %d: number of high-impact opportunities */
										esc_html__( 'We found %d high-impact opportunities to improve.', 'ai-search-optimizer' ),
										(int) $recs_count
									);
									?>
								</p>
								<?php else : ?>
								<p class="citewp-aiso-insights__sub"><?php esc_html_e( 'Your content is performing well. Keep publishing to improve further.', 'ai-search-optimizer' ); ?></p>
								<?php endif; ?>
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
					<a href="<?php echo esc_url( $cs_recs_url ); ?>"
					   class="citewp-aiso-btn citewp-aiso-btn--outline citewp-aiso-cs-recs-btn">
					    <?php esc_html_e( 'View All Recommendations →', 'ai-search-optimizer' ); ?>
					</a>
				</div>
			</div>

			<!-- Cite Score Over Time -->
			<div class="citewp-aiso-cs-panel">
				<div class="citewp-aiso-cs-history-head">
					<h3 class="citewp-aiso-cs-panel__title">
						<?php esc_html_e( 'Cite Score Over Time', 'ai-search-optimizer' ); ?>
						<span class="citewp-aiso-kpi-tooltip citewp-aiso-kpi-tooltip--align-left">
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

		</div><!-- /.citewp-aiso-cite-score-page__right -->

	</div><!-- /.citewp-aiso-cite-score-page__body -->
```

**IMPORTANT:** Before making this Edit, verify in Step 2.5 that the AI Recs block content exactly matches what is currently in the file. The reproduction above is based on the code at the time the plan was written — if any variables or content differ in the actual file, use the actual file content.

### Step 2.9: Verify HTML structure

After all edits, read lines 936–1290 and verify:
- `citewp-aiso-cite-score-page__body` wraps everything
- `__left` contains `__left-row` (with Health + Breakdown) then table-wrap
- `__right` contains AI Recs + Over Time
- All divs are properly closed
- No `citewp-aiso-cs-top-grid` or `citewp-aiso-cs-lower-grid` remain in the HTML

### Step 2.10: Commit

```bash
git add admin/css/citewp-aiso-admin.css includes/Admin/Menu.php
git commit -m "feat: FIX L1 — two-column independent stack (Health+Breakdown left sub-row, AI Recs+Over Time right col) (S19 run #6)"
```

---

## Task 3: FIX T1 + T2 — Tooltip alignment reversal + max-width increase

**Files:**
- Modify: `admin/css/citewp-aiso-admin.css`
- Modify: `includes/Admin/Menu.php` (Issues Detected KPI at line ~916)

**Regression note:** The Dashboard (lines 387–449) also uses `citewp-aiso-kpi-tooltip`. After changing the default to right-anchored (tooltip extends LEFT), Dashboard KPI tooltips that are near the left edge of the content area may overflow. The code reviewer in Task 6 will check this. If any Dashboard card overflows, apply `citewp-aiso-kpi-tooltip--align-left` to that card.

### Step 3.1: Read tooltip CSS lines 1725–1760

Confirm exact current values of `left`/`right` properties in `.citewp-aiso-kpi-tooltip__text` and the `--align-right` modifier.

### Step 3.2: Change `.citewp-aiso-kpi-tooltip__text` default to right-anchored

In `.citewp-aiso-kpi-tooltip__text`:
- Change `left: 0;` → `left: auto;`
- Change `right: auto;` → `right: 0;`
- Change `max-width: 320px;` → `max-width: 360px;`

All other properties remain unchanged.

### Step 3.3: Replace `--align-right` CSS rule with `--align-left`

Find:
```css
.citewp-aiso-kpi-tooltip--align-right .citewp-aiso-kpi-tooltip__text {
  left: auto;
  right: 0;
}
```

Replace with:
```css
/* Left-align modifier: tooltip extends RIGHT from icon — use on rightmost page cards */
.citewp-aiso-kpi-tooltip--align-left .citewp-aiso-kpi-tooltip__text {
  left: 0;
  right: auto;
}
```

### Step 3.4: Update Issues Detected KPI tooltip in Menu.php

Read lines 910–922. Find:
```php
					<span class="citewp-aiso-kpi-tooltip citewp-aiso-kpi-tooltip--align-right">
```
(Inside the Issues Detected KPI card, the 3rd card in `.citewp-aiso-kpi-row--3col`.)

Replace with:
```php
					<span class="citewp-aiso-kpi-tooltip citewp-aiso-kpi-tooltip--align-left">
```

Note: AI Recs and Over Time tooltips were already updated to `--align-left` in Task 2 Step 2.8. Only this one remaining instance needs updating.

### Step 3.5: Verify zero `--align-right` instances remain

Grep `includes/Admin/Menu.php` for `citewp-aiso-kpi-tooltip--align-right` — must return 0.
Grep `admin/css/citewp-aiso-admin.css` for `align-right` (within tooltip context) — must return 0.

### Step 3.6: Commit

```bash
git add admin/css/citewp-aiso-admin.css includes/Admin/Menu.php
git commit -m "fix: FIX T1+T2 — reverse tooltip default right-anchored, --align-left modifier, max-width 360px (S19 run #6)"
```

---

## Task 4: FIX 5.1 — Score Breakdown title formatting

**Files:**
- Modify: `admin/css/citewp-aiso-admin.css`

### Step 4.1: Read `.citewp-aiso-breakdown__head` CSS rule

Search `admin/css/citewp-aiso-admin.css` for `.citewp-aiso-breakdown__head`. Read the full rule block. Note current font, color, and any text-transform values.

Also read `.citewp-aiso-cs-panel__title` for comparison. The breakdown head must match the panel title's font spec.

### Step 4.2: Update `__head` to match panel title spec

In the `.citewp-aiso-breakdown__head` rule, ensure these properties exist with these exact values:
```css
font: 700 14px/1.2 'Inter', system-ui, sans-serif;
color: var(--citewp-obsidian);
text-transform: none;
letter-spacing: 0;
```

Add any that are missing. Update any that differ. Do NOT remove existing layout properties (display, justify-content, align-items, gap, margin, padding).

### Step 4.3: Commit

```bash
git add admin/css/citewp-aiso-admin.css
git commit -m "fix: FIX 5.1 — Score Breakdown title matches panel title spec (Inter 700 14px obsidian) (S19 run #6)"
```

---

## Task 5: FIX 6.1–6.6 + FIX 4.1 verification — Post-Level Cite Scores table polish

**Files:**
- Modify: `admin/css/citewp-aiso-admin.css` (Section 31 table rules)
- Modify: `includes/Admin/Menu.php` (thead + first td)

### Step 5.1: FIX 6.1 — td padding 16px 12px

In `admin/css/citewp-aiso-admin.css`, find `.citewp-aiso-cs-table td`. Change padding to:
```css
padding: 16px 12px;
```

### Step 5.2: FIX 6.2 — Bold post title links (scoped to first td)

In Section 31, after the existing `.citewp-aiso-cs-table td a` rule, add:

```css
/* Post title links: bold — scoped to first-child only (not Optimize button) */
.citewp-aiso-cs-table td:first-child a {
  font-weight: 700;
}
```

This builds on the existing `.citewp-aiso-cs-table td a` rule (which already sets `color: var(--citewp-obsidian); text-decoration: none`) and adds `font-weight: 700` only to the title links, not the Optimize button in the last column.

### Step 5.3: FIX 6.3 — Icon top-align for multi-line titles

Read `includes/Admin/Menu.php` lines 1159–1168 to see the exact current `<td>` structure with the inner span.

Find (inside the first `<td>` of the table row):
```php
								<span style="display:inline-flex;align-items:center;gap:4px">
									<span style="color:var(--citewp-text-muted);display:inline-flex">
```

Replace with:
```php
								<span style="display:inline-flex;align-items:flex-start;gap:8px">
									<span style="color:var(--citewp-text-muted);display:inline-flex;flex-shrink:0;margin-top:2px">
```

This changes `align-items: center` → `align-items: flex-start` on the wrapper span and adds `flex-shrink: 0; margin-top: 2px` to the icon span so icons top-align with the first line of multi-line titles.

### Step 5.4: FIX 6.4 — Column header font: 11px → 12px + explicit text-transform: none

In `admin/css/citewp-aiso-admin.css`, find `.citewp-aiso-cs-table th`. Change `11px` to `12px` in the font shorthand. Add `text-transform: none` if not present:

```css
.citewp-aiso-cs-table th {
  font: 700 12px/1 'Inter', system-ui, sans-serif;
  color: var(--citewp-text-muted);
  letter-spacing: 0.04em;
  padding: var(--sp-2) var(--sp-3);
  border-bottom: 1px solid var(--citewp-border);
  text-align: left;
  white-space: nowrap;
  text-transform: none;
}
```

### Step 5.5: FIX 6.5 — "Actions" column header

In `includes/Admin/Menu.php`, read line ~1127. Find:
```php
							<th></th>
```
(The blank action column header — last `<th>` in the thead row.)

Replace with:
```php
							<th><?php esc_html_e( 'Actions', 'ai-search-optimizer' ); ?></th>
```

### Step 5.6: FIX 6.6 — Optimize button white-space: nowrap

In Section 31 of `admin/css/citewp-aiso-admin.css`, add:

```css
/* Prevent Optimize button from wrapping in narrow action column */
.citewp-aiso-cs-table .citewp-aiso-btn--outline {
  white-space: nowrap;
}
```

### Step 5.7: FIX 4.1 — Verify status copy logic (no code change expected)

Grep `includes/Admin/Menu.php` for `$cs_status_copy`. Read the array. Confirm it has keys `red`, `orange`, `yellow`, `green`, `empty` with copy text matching:
- `red`: "Your site needs improvement. Fix the issues below to increase your AI citation potential."
- `orange`: "Your site has moderate AI citation potential. Fix the issues below to increase your score."
- `yellow`: "Your site is performing well. Continue improving to maximize AI citation."
- `green`: "Your site is excellently optimized for AI citation."

Also grep for where `$avg_grade` is assigned. Confirm the grade-to-score-band thresholds match the spec (0-49 = red, 50-69 = orange, 70-89 = yellow, 90-100 = green).

If logic is correct, report "FIX 4.1 confirmed: status copy logic correct, no code change needed."
If logic is wrong, fix it.

### Step 5.8: Commit

```bash
git add admin/css/citewp-aiso-admin.css includes/Admin/Menu.php
git commit -m "fix: FIX 6.1-6.6 — post table td padding, bold titles, icon top-align, th 12px, Actions header, nowrap btn (S19 run #6)"
```

---

## Task 6: Code-reviewer + X20 audit + cleanup commit + push

**Files:** Any flagged by review.

### Step 6.1: Dispatch code-reviewer subagent (`feature-dev:code-reviewer`)

Checks to run:

1. **Layout structure:** Grep `includes/Admin/Menu.php` for `citewp-aiso-cite-score-page__body` — must exist. Grep for `citewp-aiso-cs-top-grid` in rendered HTML (not CSS comments) — must return 0. Grep for `citewp-aiso-cs-lower-grid` in rendered HTML — must return 0.

2. **AI Recs variable scope:** Grep for `$rec_cat_meta` in `includes/Admin/Menu.php` — should appear exactly once (inside the right column). Grep for `$recs_display` — should not be used before it's defined.

3. **Tooltip regression:** Check lines 387–449 (Dashboard KPI tooltips). With default now right-anchored, do any Dashboard KPI cards lack a modifier? If yes, assess whether leftward extension could overflow. Apply `citewp-aiso-kpi-tooltip--align-left` to any Dashboard card where leftward overflow is a risk (typically first column KPI cards if content area is tight).

4. **`--align-right` removal:** Grep both files for `align-right` — must return 0 in tooltip contexts.

5. **HTML nesting:** Read the section from `<?php else : ?>` (empty state check) through `</div><!-- /.citewp-aiso-cite-score-page__body -->`. Verify proper nesting: body > (left > (left-row > (Health, Breakdown), table-wrap), right > (AI Recs, Over Time)).

6. **Security:** Confirm no raw `echo` in changed blocks — all dynamic output uses `esc_html()`, `esc_attr()`, `esc_url()`.

### Step 6.2: X20 component spec audit

| Component | Required class / state | Check |
|-----------|----------------------|-------|
| Page body | `citewp-aiso-cite-score-page__body` | grep |
| Left sub-row | `citewp-aiso-cite-score-page__left-row` | grep |
| Right column | `citewp-aiso-cite-score-page__right` | grep |
| KPI card compact | `citewp-aiso-kpi-card--compact` (3 instances) | grep |
| AI Insights outer | `citewp-aiso-insights` (not `recs`) | grep |
| Tooltip on Issues Detected | `--align-left` (not `--align-right`) | grep |
| Tooltip on AI Recs | `--align-left` inside `citewp-aiso-insights__header` | grep |
| Tooltip on Over Time | `--align-left` inside `citewp-aiso-cs-history-head` | grep |
| Score Breakdown head | `font: 700 14px` in `.citewp-aiso-breakdown__head` | CSS read |
| Actions th | Not blank — contains "Actions" text | grep |
| Pro Tip | `citewp-aiso-protip` | grep |
| Gauge | `citewp-cite-score-gauge` | grep |

### Step 6.3: Fix all blockers

Apply any fixes identified by the reviewer or X20 audit.

### Step 6.4: Cleanup commit + push

```bash
git add admin/css/citewp-aiso-admin.css includes/Admin/Menu.php
git commit -m "fix: code-reviewer + X20 audit cleanup (S19 run #6)"
git push
```

---

## Smoke Test Checklist

Manual browser verification at `admin.php?page=citewp#cite-score`:

- [ ] **Tooltip 1 (Average Cite Score, row 1 col 1):** tooltip extends LEFT from icon (right-anchored default, no modifier).
- [ ] **Tooltip 2 (Issues Detected, row 1 last col):** tooltip extends RIGHT from icon (`--align-left` modifier active).
- [ ] **Tooltip 3 (Cite Score Health, left sub-row left):** tooltip extends LEFT (no modifier — not last column).
- [ ] **Tooltip 4 (Score Breakdown, left sub-row right):** tooltip extends LEFT (no modifier — right column is still to its right, so it is NOT the last column).
- [ ] **Tooltip 5 (Post-Level Cite Scores, below sub-row):** tooltip extends LEFT (no modifier).
- [ ] **Tooltip 6 (AI Recommendations, right col top):** tooltip extends RIGHT (`--align-left` modifier active).
- [ ] **Tooltip 7 (Cite Score Over Time, right col bottom):** tooltip extends RIGHT (`--align-left` modifier active).
- [ ] Tooltip body text wraps within 360px — no excessively narrow vertical text stacks.
- [ ] **Layout left column:** Cite Score Health + Score Breakdown side-by-side, equal height. Post table directly below spanning full left column width.
- [ ] **Layout right column:** AI Recommendations stacked above Cite Score Over Time. Both same width. Left and right edges align with each other.
- [ ] **Column independence:** Bottom edges of left and right columns may be offset — not forced equal.
- [ ] Score Breakdown title renders same visual weight/size as "Cite Score Health" and "AI Recommendations" titles.
- [ ] Post table: cells have 16px top/bottom, 12px left/right padding.
- [ ] Post table: post title links are bold (Inter 700), Obsidian color, no underline.
- [ ] Post table: post-type icon aligns to first line of multi-line titles (not vertically centered).
- [ ] Post table: column headers render 12px, muted color, title case, no uppercase.
- [ ] Post table: "Actions" text visible above the Optimize column.
- [ ] Post table: "Optimize →" text stays on one line (no wrap).
- [ ] Dashboard KPI tooltips: spot check — no leftward overflow off the content area edge.
- [ ] No regressions on Dashboard, Crawler Logs, Settings, EditorPanel meta box.

---

## Constraints

- `Engine.php` NO-TOUCH
- `SCORING-RUBRIC.md` NO-TOUCH
- Brain doc: file edits only — no git repo in Brain folder
- Plugin commits to `ai-search-optimizer` repo (`git push` at end of Task 6)
- `npm run build` NOT required (PHP + CSS only)
- After smoke tests pass: `/session-end` — S19 closes
- If anything still broken after this run: log as S20 carryover. Do NOT create Run #7.
