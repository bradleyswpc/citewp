# Cite Score KPI Card Pass — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restyle the 4 KPI cards on the Cite Score admin page to match the Dashboard KPI Card pattern (P57), shrink 36px orb icons to 16px inline icons (P62), rename Card 3, and add a new Card 4 (Schema Coverage) backed by `DashboardData::schema_coverage()`.

**Architecture:** All changes are read-side and presentation-only. `DashboardData::schema_coverage()` aggregates stored `check_schema` signal status from post meta — no scoring logic, Engine.php untouched. CSS gains a `--4col` grid modifier and schema tile styles. Menu.php `render_cite_score_panel()` gets updated HTML for cards 1–3 and new HTML for card 4.

**Tech Stack:** PHP 8.0+, WordPress 6.5+, vanilla CSS (no build step — no JS changes).

---

## Constraints (read before touching anything)

- **Engine.php is NO-TOUCH.** `schema_coverage()` reads stored post meta only.
- **P65:** Do not touch `.citewp-aiso-activity__heading` or any `activity__heading` selector.
- **Typography:** Card titles use the shared `.citewp-aiso-t2` CSS block (600 14px/1 Inter `--citewp-text-primary`). No ad-hoc font declarations on card titles.
- **P41:** All 4 KPI cards use `citewp-aiso-btn--outline` buttons. The `citewp-aiso-btn--primary-paper` "View AI Recommendations →" button lower on the page is the sole primary CTA for this surface — do not add a second one.
- **X4:** Commit AND push after every task. Single-commit-at-end is not acceptable.
- No `npm run build` needed — no JS changes in this plan.
- **CSS radius token:** `--radius-sm` (4px) is the correct token in this codebase — no `citewp-` prefix. Verified in `:root`. Do not "fix" it to `--citewp-radius-sm` (undefined).
- **Schema tile "None" color:** use `--citewp-score-red` (#8C1B1B, rgb 140,27,27). None = worst state. Do not use `--citewp-score-orange` (#E86612) or the pre-P46 hardcoded `rgba(214,54,56,...)`.
- **Card 4 head-right:** No head-right link on Card 4. "Add Schema →" was dropped — it linked to a read-only table, not a schema-adding surface. The bottom "View Schema Gaps →" button is the only action.

---

## Task 1: Verify and patch `layers` icon in IconLibrary.php

**Files:**
- Read + potentially modify: `includes/Admin/IconLibrary.php`

The icons `bot`, `check-circle`, `alert-triangle`, and `info` are confirmed present (used in existing Card 1–3 HTML). The `layers` icon (needed for Card 4) needs verification.

- [ ] **Step 1.1: Grep for existing icons**

```bash
grep -n "'layers'\|'check-circle'\|'alert-triangle'\|'bot'" includes/Admin/IconLibrary.php
```

Expected: `check-circle`, `alert-triangle`, `bot` all appear. If `layers` appears — skip Step 1.2.

- [ ] **Step 1.2: Add `layers` icon if missing**

Open `includes/Admin/IconLibrary.php`. Find the array or switch that maps icon names to SVG paths. Add this entry using the same pattern as existing icons (Lucide stroke SVG, `viewBox="0 0 24 24"`, `fill="none"`, `stroke="currentColor"`, `stroke-width="2"`, `stroke-linecap="round"`, `stroke-linejoin="round"`):

```php
'layers' => '<svg xmlns="http://www.w3.org/2000/svg" width="{size}" height="{size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 12.5-8.56 3.89a2 2 0 0 1-1.66 0L3 12.5"/><path d="m22 17.5-8.56 3.89a2 2 0 0 1-1.66 0L3 17.5"/></svg>',
```

Match the exact format of existing entries — if they use `str_replace('{size}', $size, ...)` or similar interpolation, apply it the same way.

- [ ] **Step 1.3: Verify PHP syntax**

The PostToolUse hook runs PHP syntax check automatically on save. Confirm no errors in debug.log.

- [ ] **Step 1.4: Commit and push**

```bash
git add includes/Admin/IconLibrary.php
git commit -m "feat: add layers icon to IconLibrary for Schema Coverage card"
git push
```

---

## Task 2: Add `DashboardData::schema_coverage()`

**Files:**
- Modify: `includes/Admin/DashboardData.php`

This method aggregates the stored `check_schema` signal across all scored, llms.txt-included posts. It reads post meta only — no live scoring.

- [ ] **Step 2.1: Confirm `Repository` use statement**

At the top of `DashboardData.php`, verify `use CiteWP\Aiso\Database\Repository;` is present. It already is (the class uses `Repository::META_KEY_TOTAL` in existing methods). No change needed — just confirm before proceeding.

- [ ] **Step 2.2: Add `schema_coverage()` method**

Open `DashboardData.php`. After the last public method (currently `get_recent_activity()` at ~L735), add:

```php
/**
 * Aggregates schema signal states across all scored, llms.txt-included posts.
 *
 * Reads the stored 'schema' signal status from _citewp_aiso_geo_score post meta.
 * Does NOT invoke Engine::check_schema() or perform live scoring — this is a
 * read-side aggregate of the cached 6/3/0 signal from the last scoring run.
 * 'partial' means an SEO plugin was active at score time; it does NOT verify
 * the plugin outputs schema for this post type (FB42, render-time detection, deferred).
 *
 * Excludes posts opted out of llms.txt (P49): same WP_Query guard as
 * render_cite_score_panel() — (NOT EXISTS OR != '1') on _citewp_aiso_exclude_from_llms.
 *
 * @return array{confirmed: int, partial: int, none: int, total: int, pct_confirmed: int}
 */
public function schema_coverage(): array {
    /** @var string[] $post_types — FB40: CPT scope hook */
    $post_types = apply_filters( 'citewp_aiso/data/scored_post_types', [ 'post', 'page' ] );

    $scored_ids = get_posts( [
        'post_type'      => $post_types,
        'post_status'    => 'publish',
        'posts_per_page' => 1000,
        'no_found_rows'  => true,
        'fields'         => 'ids',
        'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin-only, once per page load. Required: filters to scored posts while excluding llms.txt opt-outs.
            'relation' => 'AND',
            [
                'key'     => Repository::META_KEY_TOTAL,
                'compare' => 'EXISTS',
            ],
            [
                'relation' => 'OR',
                [ 'key' => '_citewp_aiso_exclude_from_llms', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_citewp_aiso_exclude_from_llms', 'value' => '1', 'compare' => '!=' ],
            ],
        ],
    ] );

    $confirmed = 0;
    $partial   = 0;
    $none      = 0;

    foreach ( $scored_ids as $post_id ) {
        $data = ( new Repository() )->get( (int) $post_id );

        if ( ! $data || empty( $data['signals'] ) ) {
            _doing_it_wrong(
                __METHOD__,
                sprintf( 'Post %d has a stored Cite Score but no signals array in _citewp_aiso_geo_score. Counted as uncovered.', (int) $post_id ),
                CITEWP_AISO_VERSION
            );
            ++$none;
            continue;
        }

        $schema_status = 'fail';
        foreach ( $data['signals'] as $signal ) {
            if ( isset( $signal['id'] ) && 'schema' === $signal['id'] ) {
                $schema_status = $signal['status'] ?? 'fail';
                break;
            }
        }

        match ( $schema_status ) {
            'pass'    => ++$confirmed,
            'partial' => ++$partial,
            default   => ++$none,
        };
    }

    $total         = $confirmed + $partial + $none;
    $pct_confirmed = $total > 0 ? (int) round( ( $confirmed / $total ) * 100 ) : 0;

    /** @var array{confirmed:int,partial:int,none:int,total:int,pct_confirmed:int} $result */
    $result = compact( 'confirmed', 'partial', 'none', 'total', 'pct_confirmed' );

    /** FB42: render-time detection will augment $result['confirmed'] via this filter */
    return apply_filters( 'citewp_aiso/data/schema_coverage', $result );
}
```

- [ ] **Step 2.3: Verify PHP syntax**

PostToolUse hook runs syntax check on save. Confirm no errors in debug.log.

- [ ] **Step 2.4: Commit and push**

```bash
git add includes/Admin/DashboardData.php
git commit -m "feat: add DashboardData::schema_coverage() aggregate method (P47/P49/X15)"
git push
```

---

## Task 3: CSS — `--4col` modifier, `__head-main` rule, schema tiles, Cite Score card cleanup

**Files:**
- Modify: `admin/css/citewp-aiso-admin.css`

- [ ] **Step 3.1: Find the `--3col` modifier and the Cite Score KPI card rules**

```bash
grep -n "\-\-3col\|cs-kpi\|__visual\|__data\|kpi-progress" admin/css/citewp-aiso-admin.css
```

Note the line numbers for:
- `.citewp-aiso-kpi-row--3col` (to add `--4col` nearby)
- Any `.citewp-aiso-cs-kpi-row .citewp-aiso-kpi-card__visual` rules (to remove/restyle)
- `.citewp-aiso-kpi-card__data` (to remove/restyle for cs-kpi context)
- `.citewp-aiso-kpi-progress` rules (to remove or scope)

- [ ] **Step 3.2: Add `--4col` modifier directly after the `--3col` block**

In `admin/css/citewp-aiso-admin.css`, locate the `--3col` rule and add `--4col` immediately after it:

```css
.citewp-aiso-kpi-row--4col {
	grid-template-columns: repeat( 4, 1fr );
}
```

Do NOT modify or remove `--3col` — it is used by the Crawler Logs page.

- [ ] **Step 3.3: Add `__head-main` flex wrapper rule**

Add after the `--4col` block (or after existing `__head` rules — search for `kpi-card__head` to find the right location):

```css
/* KPI card head-main — inline icon + Tier-2 title, aligned */
.citewp-aiso-kpi-card__head-main {
	display: inline-flex;
	align-items: center;
	gap: 6px;
}

.citewp-aiso-kpi-card__head-main svg {
	flex-shrink: 0;
	color: var( --citewp-text-muted );
}
```

- [ ] **Step 3.4: Remove/scope Cite Score `__visual` and `__data` layout rules**

From the grep output in Step 3.1, find any rules scoped to `.citewp-aiso-cs-kpi-row` that apply side-by-side `__visual` + `__data` layout. Remove those rules (the new HTML no longer uses `__visual` or `__data` wrappers inside the Cite Score KPI row).

If `citewp-aiso-kpi-card__visual` and `citewp-aiso-kpi-card__data` rules exist only in a `.citewp-aiso-cs-kpi-row` scope block, delete the entire scope block. If they are in a shared block used elsewhere, add a scoped override instead:

```css
/* Cite Score KPI row — no orb/data layout (P57/P62 migration) */
.citewp-aiso-cs-kpi-row .citewp-aiso-kpi-card__visual,
.citewp-aiso-cs-kpi-row .citewp-aiso-kpi-card__data {
	display: none; /* removed in S37 — HTML no longer emits these elements */
}
```

Similarly, remove or scope any `.citewp-aiso-kpi-progress` rules that apply to the Cite Score KPI row if they are not shared with other surfaces:

```css
.citewp-aiso-cs-kpi-row .citewp-aiso-kpi-progress {
	display: none;
}
```

- [ ] **Step 3.5: Add schema tile CSS**

Add at the end of the KPI card section (after severity-tile rules — search `severity-tile` to find location):

```css
/* ── Schema Coverage tiles (Card 4, Cite Score page) ──────────────── */
.citewp-aiso-kpi-card__schema-tiles {
	display: flex;
	gap: 6px;
	margin-top: 8px;
	margin-bottom: 10px;
}

.citewp-aiso-kpi-card__schema-tile {
	flex: 1;
	border-radius: var( --radius-sm );
	padding: 5px 8px;
	text-align: center;
	border: 1px solid;
}

.citewp-aiso-kpi-card__schema-tile--confirmed {
	background: rgba( 0, 163, 42, 0.08 );
	border-color: rgba( 0, 163, 42, 0.25 );
}

.citewp-aiso-kpi-card__schema-tile--confirmed .citewp-aiso-kpi-card__schema-tile-count {
	color: var( --citewp-score-green );
}

.citewp-aiso-kpi-card__schema-tile--seo-plugin {
	background: rgba( 219, 166, 23, 0.08 );
	border-color: rgba( 219, 166, 23, 0.3 );
}

.citewp-aiso-kpi-card__schema-tile--seo-plugin .citewp-aiso-kpi-card__schema-tile-count {
	color: var( --citewp-score-yellow );
}

.citewp-aiso-kpi-card__schema-tile--none {
	background: rgba( 140, 27, 27, 0.08 );  /* --citewp-score-red #8C1B1B */
	border-color: rgba( 140, 27, 27, 0.25 );
}

.citewp-aiso-kpi-card__schema-tile--none .citewp-aiso-kpi-card__schema-tile-count {
	color: var( --citewp-score-red );  /* None = worst state = red, not orange */
}

.citewp-aiso-kpi-card__schema-tile-count {
	display: block;
	font-family: 'JetBrains Mono', monospace;
	font-size: 14px;
	font-weight: 700;
	line-height: 1;
	margin-bottom: 2px;
}

.citewp-aiso-kpi-card__schema-tile-label {
	display: block;
	font-family: Inter, -apple-system, sans-serif;
	font-size: 10px;
	font-weight: 600;
	color: var( --citewp-text-muted );
	text-transform: uppercase;
	letter-spacing: 0.04em;
}
```

- [ ] **Step 3.6: Verify no syntax errors**

PHP syntax check runs automatically. Also visually scan the CSS around your edits for unclosed braces.

- [ ] **Step 3.7: Commit and push**

```bash
git add admin/css/citewp-aiso-admin.css
git commit -m "feat: CSS — --4col grid, __head-main icon rule, schema tile styles, cs-kpi visual cleanup"
git push
```

---

## Task 4: Confirm per-post table anchor

**Files:**
- Read + possibly modify: `includes/Admin/Menu.php`

Cards 2, 3, and 4 all scroll to the per-post table on the Cite Score page. All four affordances must share the same anchor `id`.

- [ ] **Step 4.1: Find the per-post table element**

```bash
grep -n "id=.*table\|id=.*post\|id=.*score\|cs-post" includes/Admin/Menu.php
```

If a table or wrapper element already has an `id` attribute in `render_cite_score_panel()`, use that value in all scroll hrefs below (e.g. `#citewp-aiso-cs-post-table`).

If no `id` exists on the table, add one. Find the `<table` or outer wrapper `<div` that contains the per-post score rows in `render_cite_score_panel()` (below the KPI row) and add `id="citewp-aiso-cs-post-table"` to it.

- [ ] **Step 4.2: Commit if table anchor was added**

Only commit if you modified the file:

```bash
git add includes/Admin/Menu.php
git commit -m "feat: add id anchor to Cite Score per-post table for KPI card scroll links"
git push
```

> **Note:** Replace `#citewp-aiso-cs-post-table` throughout Tasks 5–8 with the actual anchor ID found/added in this step.

---

## Task 5: Restyle Card 1 — Top Crawler

**Files:**
- Modify: `includes/Admin/Menu.php` (within `render_cite_score_panel()`, ~L975–L1029)

Replace the Card 1 block entirely. The existing block has a 36px `__visual` orb + `__data` side-by-side layout. The new block moves the bot icon to the head row and flows content directly in `__body`.

- [ ] **Step 5.1: Replace Card 1 HTML**

Locate the comment `<!-- Card 1: Top Crawler -->` (~L974) and replace everything from the opening `<div class="citewp-aiso-kpi-card citewp-aiso-kpi-card--top-crawler">` through its closing `</div>` (~L1029) with:

```php
			<!-- Card 1: Top Crawler -->
			<div class="citewp-aiso-kpi-card citewp-aiso-kpi-card--top-crawler">
				<div class="citewp-aiso-kpi-card__head">
					<span class="citewp-aiso-kpi-card__head-main">
						<?php echo IconLibrary::icon( 'bot', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span class="citewp-aiso-kpi-card__title"><?php esc_html_e( 'Top Crawler', 'ai-search-optimizer' ); ?></span>
					</span>
					<span
						class="citewp-aiso-kpi-card__info"
						data-tooltip="<?php esc_attr_e( 'The AI bot that\'s visited your site most often in the last 7 days. A signal that your optimization work is being noticed.', 'ai-search-optimizer' ); ?>"
					>
						<?php echo IconLibrary::icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</span>
				</div>
				<div class="citewp-aiso-kpi-card__body">
					<?php if ( $top_crawler !== null ) : ?>
					<div class="citewp-aiso-kpi-card__value"><?php echo esc_html( $top_crawler['display_name'] ); ?></div>
					<div class="citewp-aiso-kpi-card__sub">
						<?php
						echo esc_html( sprintf(
							/* translators: %d: number of bot visits in last 7 days */
							__( '%d visits in last 7 days', 'ai-search-optimizer' ),
							(int) ( $top_crawler['visits'] ?? 0 )
						) );
						?>
					</div>
					<?php
					$tc_current = (int) ( $top_crawler['visits'] ?? 0 );
					$tc_prior   = (int) ( $top_crawler['prior_visits'] ?? 0 );
					$tc_delta   = $tc_current - $tc_prior;
					$tc_trend_class = 'citewp-aiso-kpi-card__trend--flat';
					if ( $tc_current > 0 || $tc_prior > 0 ) :
						$tc_trend_class = $tc_delta > 0 ? 'citewp-aiso-kpi-card__trend--up' : ( $tc_delta < 0 ? 'citewp-aiso-kpi-card__trend--down' : 'citewp-aiso-kpi-card__trend--flat' );
					?>
					<div class="citewp-aiso-kpi-card__trend <?php echo esc_attr( $tc_trend_class ); ?>">
						<?php if ( $tc_delta > 0 ) : ?>
							<?php echo esc_html( '↑' ); ?> +<?php echo esc_html( (string) $tc_delta ); ?> <span class="citewp-aiso-kpi-card__trend-suffix"><?php esc_html_e( 'vs. prior 7 days', 'ai-search-optimizer' ); ?></span>
						<?php elseif ( $tc_delta < 0 ) : ?>
							<?php echo esc_html( '↓' ); ?> <?php echo esc_html( (string) $tc_delta ); ?> <span class="citewp-aiso-kpi-card__trend-suffix"><?php esc_html_e( 'vs. prior 7 days', 'ai-search-optimizer' ); ?></span>
						<?php else : ?>
							<?php echo esc_html( '→' ); ?> <span class="citewp-aiso-kpi-card__trend-suffix"><?php esc_html_e( 'no change vs. prior 7 days', 'ai-search-optimizer' ); ?></span>
						<?php endif; ?>
					</div>
					<?php endif; ?>
					<?php else : ?>
					<div class="citewp-aiso-kpi-card__value">—</div>
					<div class="citewp-aiso-kpi-card__sub"><?php esc_html_e( 'No AI crawler visits yet', 'ai-search-optimizer' ); ?></div>
					<div class="citewp-aiso-kpi-card__trend citewp-aiso-kpi-card__trend--flat">→ <span class="citewp-aiso-kpi-card__trend-suffix"><?php esc_html_e( 'Visits typically begin within 24–72 hours of publishing.', 'ai-search-optimizer' ); ?></span></div>
					<?php endif; ?>
				</div>
				<div class="citewp-aiso-kpi-card__footer">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=citewp#crawler-logs' ) ); ?>" class="citewp-aiso-btn citewp-aiso-btn--outline"><?php esc_html_e( 'View Crawler Logs →', 'ai-search-optimizer' ); ?></a>
				</div>
			</div>
```

- [ ] **Step 5.2: Verify PHP syntax**

PostToolUse hook runs on save. Confirm no errors in debug.log.

- [ ] **Step 5.3: Commit and push**

```bash
git add includes/Admin/Menu.php
git commit -m "feat: Cite Score Card 1 — restyle Top Crawler to Dashboard KPI pattern (P57/P62)"
git push
```

---

## Task 6: Restyle Card 2 — Posts/Pages Optimized

**Files:**
- Modify: `includes/Admin/Menu.php` (~L1032–L1054)

- [ ] **Step 6.1: Replace Card 2 HTML**

Locate the comment `<!-- Card 2: Posts Optimized -->` (~L1031) and replace everything from the opening `<div class="citewp-aiso-kpi-card">` through its closing `</div>` (~L1054) with:

```php
			<!-- Card 2: Posts/Pages Optimized -->
			<div class="citewp-aiso-kpi-card">
				<div class="citewp-aiso-kpi-card__head">
					<span class="citewp-aiso-kpi-card__head-main">
						<?php echo IconLibrary::icon( 'check-circle', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span class="citewp-aiso-kpi-card__title"><?php esc_html_e( 'Posts/Pages Optimized', 'ai-search-optimizer' ); ?></span>
					</span>
				</div>
				<div class="citewp-aiso-kpi-card__body">
					<div class="citewp-aiso-kpi-card__value">
						<span class="citewp-aiso-kpi-card__value-main"><?php echo esc_html( (string) $posts_optimized ); ?></span><span class="citewp-aiso-kpi-card__value-denom"> / <?php echo esc_html( (string) $total_scored ); ?></span>
					</div>
					<div class="citewp-aiso-kpi-card__caption"><?php esc_html_e( 'posts & pages with Cite Score ≥ 50', 'ai-search-optimizer' ); ?></div>
					<div class="citewp-aiso-kpi-card__sub"><?php
					/* translators: %d: percentage of scored posts with Cite Score ≥ 50 */
					echo esc_html( sprintf( __( '%d%% of your scored content', 'ai-search-optimizer' ), absint( $pct_optimized ) ) );
					?></div>
					<div class="citewp-aiso-kpi-card__trend citewp-aiso-kpi-card__trend--flat">→ <span class="citewp-aiso-kpi-card__trend-suffix"><?php esc_html_e( 'based on current scores', 'ai-search-optimizer' ); ?></span></div>
				</div>
				<div class="citewp-aiso-kpi-card__footer">
					<a href="#citewp-aiso-cs-post-table" class="citewp-aiso-btn citewp-aiso-btn--outline"><?php esc_html_e( 'View All →', 'ai-search-optimizer' ); ?></a>
				</div>
			</div>
```

> Replace `#citewp-aiso-cs-post-table` with the actual anchor from Task 4 if different.

- [ ] **Step 6.2: Verify PHP syntax and commit**

```bash
git add includes/Admin/Menu.php
git commit -m "feat: Cite Score Card 2 — restyle Posts/Pages Optimized to Dashboard KPI pattern"
git push
```

---

## Task 7: Restyle Card 3 — Needs Attention (rename + restyle)

**Files:**
- Modify: `includes/Admin/Menu.php` (~L1056–L1078)

- [ ] **Step 7.1: Replace Card 3 HTML**

Locate the comment `<!-- Card 3: Issues Detected -->` (~L1056) and replace everything from the opening `<div class="citewp-aiso-kpi-card">` through its closing `</div>` (~L1078) with:

```php
			<!-- Card 3: Needs Attention -->
			<div class="citewp-aiso-kpi-card">
				<div class="citewp-aiso-kpi-card__head">
					<span class="citewp-aiso-kpi-card__head-main">
						<?php echo IconLibrary::icon( 'alert-triangle', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span class="citewp-aiso-kpi-card__title"><?php esc_html_e( 'Needs Attention', 'ai-search-optimizer' ); ?></span>
					</span>
					<a href="#citewp-aiso-cs-post-table" class="citewp-aiso-kpi-card__head-link"><?php esc_html_e( 'View All →', 'ai-search-optimizer' ); ?></a>
				</div>
				<div class="citewp-aiso-kpi-card__body">
					<div class="citewp-aiso-kpi-card__value citewp-aiso-kpi-score--<?php echo esc_attr( $issue_count > 0 ? ( $critical_count > 0 ? 'red' : 'orange' ) : 'green' ); ?>"><?php echo esc_html( number_format_i18n( $issue_count ) ); ?></div>
					<div class="citewp-aiso-kpi-card__caption"><?php $issue_count > 0 ? esc_html_e( 'posts need work', 'ai-search-optimizer' ) : esc_html_e( 'All posts are looking good', 'ai-search-optimizer' ); ?></div>
					<?php if ( $issue_count > 0 ) : ?>
					<div class="citewp-aiso-kpi-card__severity-tiles">
						<div class="citewp-aiso-kpi-card__severity-tile citewp-aiso-kpi-card__severity-tile--critical<?php echo $critical_count === 0 ? ' is-zero' : ''; ?>">
							<span class="citewp-aiso-kpi-card__severity-count"><?php echo esc_html( (string) $critical_count ); ?></span>
							<span class="citewp-aiso-kpi-card__severity-label"><?php esc_html_e( 'Critical', 'ai-search-optimizer' ); ?></span>
						</div>
						<div class="citewp-aiso-kpi-card__severity-tile citewp-aiso-kpi-card__severity-tile--minor<?php echo $minor_count === 0 ? ' is-zero' : ''; ?>">
							<span class="citewp-aiso-kpi-card__severity-count"><?php echo esc_html( (string) $minor_count ); ?></span>
							<span class="citewp-aiso-kpi-card__severity-label"><?php esc_html_e( 'Minor', 'ai-search-optimizer' ); ?></span>
						</div>
					</div>
					<?php endif; ?>
					<div class="citewp-aiso-kpi-card__trend citewp-aiso-kpi-card__trend--flat">→ <span class="citewp-aiso-kpi-card__trend-suffix"><?php esc_html_e( 'based on current scores', 'ai-search-optimizer' ); ?></span></div>
				</div>
			</div>
```

> Replace `#citewp-aiso-cs-post-table` with the actual anchor from Task 4 if different.
> Note: No `__footer` button on Card 3 — the head-right "View All →" link is the action affordance, matching the Dashboard Needs Attention card exactly.

- [ ] **Step 7.2: Commit and push**

```bash
git add includes/Admin/Menu.php
git commit -m "feat: Cite Score Card 3 — rename to Needs Attention, restyle to Dashboard KPI pattern"
git push
```

---

## Task 8: Update grid class and add Card 4 — Schema Coverage

**Files:**
- Modify: `includes/Admin/Menu.php` — grid class + data prep + Card 4 HTML

- [ ] **Step 8.1: Update grid class from `--3col` to `--4col`**

Find (in `render_cite_score_panel()`, ~L972):
```php
<div class="citewp-aiso-kpi-row citewp-aiso-kpi-row--3col citewp-aiso-cs-kpi-row">
```

Replace with:
```php
<div class="citewp-aiso-kpi-row citewp-aiso-kpi-row--4col citewp-aiso-cs-kpi-row">
```

- [ ] **Step 8.2: Wire `schema_coverage()` into data prep**

In `render_cite_score_panel()`, find the data prep block that calls `$dashboard_data->get_top_crawlers(1)` (~L764). Add the `schema_coverage()` call immediately after the existing data prep lines (before the loop over `$scored_ids`):

```php
$schema_coverage = $dashboard_data->schema_coverage();

// Denominator consistency check: schema_coverage() total should equal $total_scored.
// A mismatch means query paths diverged — investigate rather than ignore.
if ( defined( 'WP_DEBUG' ) && WP_DEBUG && isset( $schema_coverage['total'] ) && $schema_coverage['total'] !== $total_scored ) {
    _doing_it_wrong(
        'render_cite_score_panel',
        sprintf(
            'schema_coverage() total (%d) does not match $total_scored (%d). A post with a stored score is missing from one query path.',
            $schema_coverage['total'],
            $total_scored
        ),
        CITEWP_AISO_VERSION
    );
}
```

> `$total_scored` is set earlier in the function: `$total_scored = count( $scored_ids );`. Place this block after that line but before the HTML output section.

- [ ] **Step 8.3: Add Card 4 HTML after Card 3's closing `</div>`**

Immediately before the `</div><!-- .citewp-aiso-kpi-row -->` closing tag (~L1080), add:

```php
			<!-- Card 4: Schema Coverage -->
			<div class="citewp-aiso-kpi-card">
				<div class="citewp-aiso-kpi-card__head">
					<span class="citewp-aiso-kpi-card__head-main">
						<?php echo IconLibrary::icon( 'layers', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span class="citewp-aiso-kpi-card__title"><?php esc_html_e( 'Schema Coverage', 'ai-search-optimizer' ); ?></span>
					</span>
				</div>
				<div class="citewp-aiso-kpi-card__body">
					<?php if ( $schema_coverage['total'] > 0 ) : ?>
					<div class="citewp-aiso-kpi-card__value"><?php echo esc_html( (string) $schema_coverage['pct_confirmed'] ); ?><span class="citewp-aiso-kpi-card__value-denom">%</span></div>
					<div class="citewp-aiso-kpi-card__caption"><?php esc_html_e( 'posts with confirmed inline schema', 'ai-search-optimizer' ); ?></div>
					<div class="citewp-aiso-kpi-card__schema-tiles">
						<div class="citewp-aiso-kpi-card__schema-tile citewp-aiso-kpi-card__schema-tile--confirmed">
							<span class="citewp-aiso-kpi-card__schema-tile-count"><?php echo esc_html( (string) $schema_coverage['confirmed'] ); ?></span>
							<span class="citewp-aiso-kpi-card__schema-tile-label"><?php esc_html_e( 'Confirmed', 'ai-search-optimizer' ); ?></span>
						</div>
						<div class="citewp-aiso-kpi-card__schema-tile citewp-aiso-kpi-card__schema-tile--seo-plugin">
							<span class="citewp-aiso-kpi-card__schema-tile-count"><?php echo esc_html( (string) $schema_coverage['partial'] ); ?></span>
							<span class="citewp-aiso-kpi-card__schema-tile-label"><?php esc_html_e( 'SEO Plugin', 'ai-search-optimizer' ); ?></span>
						</div>
						<div class="citewp-aiso-kpi-card__schema-tile citewp-aiso-kpi-card__schema-tile--none">
							<span class="citewp-aiso-kpi-card__schema-tile-count"><?php echo esc_html( (string) $schema_coverage['none'] ); ?></span>
							<span class="citewp-aiso-kpi-card__schema-tile-label"><?php esc_html_e( 'None', 'ai-search-optimizer' ); ?></span>
						</div>
					</div>
					<?php else : ?>
					<div class="citewp-aiso-kpi-card__value">—</div>
					<div class="citewp-aiso-kpi-card__caption"><?php esc_html_e( 'Score your posts to see schema coverage', 'ai-search-optimizer' ); ?></div>
					<?php endif; ?>
					<div class="citewp-aiso-kpi-card__trend citewp-aiso-kpi-card__trend--flat">→ <span class="citewp-aiso-kpi-card__trend-suffix"><?php esc_html_e( 'based on current scores', 'ai-search-optimizer' ); ?></span></div>
				</div>
				<div class="citewp-aiso-kpi-card__footer">
					<a href="#citewp-aiso-cs-post-table" class="citewp-aiso-btn citewp-aiso-btn--outline"><?php esc_html_e( 'View Schema Gaps →', 'ai-search-optimizer' ); ?></a>
				</div>
			</div>
```

> Replace `#citewp-aiso-cs-post-table` with the actual anchor from Task 4 if different.

- [ ] **Step 8.4: Verify PHP syntax**

PostToolUse hook runs on save. Confirm no errors in debug.log.

- [ ] **Step 8.5: Commit and push**

```bash
git add includes/Admin/Menu.php
git commit -m "feat: Cite Score Card 4 — Schema Coverage (DashboardData::schema_coverage, P47/P49/X15)"
git push
```

---

## Task 9: X20 Spec-Compliance Audit

Run this checklist before handing to browser verify. Check each item against the spec at `docs/superpowers/specs/2026-05-21-cite-score-kpi-card-pass-design.md`.

- [ ] **Step 9.1: Typography — card titles**

```bash
grep -n "kpi-card__title" includes/Admin/Menu.php | grep -v "citewp-aiso-t2\|class=\"citewp-aiso-kpi-card__title\""
```

All 4 `citewp-aiso-kpi-card__title` spans must NOT have inline font/color styles. Typography comes from the shared `.citewp-aiso-t2` CSS block. If any card has `style="font-weight:..."` on the title, remove it.

- [ ] **Step 9.2: Button hierarchy — no primary-paper on KPI row**

```bash
grep -n "primary-paper" includes/Admin/Menu.php
```

The only `primary-paper` button in `render_cite_score_panel()` must be the "View AI Recommendations →" button below the KPI row. Zero `primary-paper` buttons inside `.citewp-aiso-cs-kpi-row`. If any appear, change to `--outline`.

- [ ] **Step 9.3: Icons are 16px in head, not 36px**

```bash
grep -n "icon.*36\|icon.*28\|__visual" includes/Admin/Menu.php
```

No `36` or `28` sizes inside the Cite Score KPI card blocks. No `__visual` elements in the Card 1–4 HTML. If any remain, remove them.

- [ ] **Step 9.4: Grid class is `--4col`, not `--3col`**

```bash
grep -n "cs-kpi-row\|kpi-row--" includes/Admin/Menu.php
```

The `.citewp-aiso-cs-kpi-row` div must use `kpi-row--4col`. If `--3col` still appears on this line, fix it.

- [ ] **Step 9.5: P65 — activity__heading not touched**

```bash
grep -n "activity__heading" admin/css/citewp-aiso-admin.css
```

The `.citewp-aiso-activity__heading` selector must use `color: var(--citewp-text-muted)` (Tier 3 per P65). If it was accidentally changed to `--citewp-text-primary`, revert it.

- [ ] **Step 9.6: P49 exclusion in schema_coverage()**

Confirm the WP_Query in `schema_coverage()` has the `relation => 'OR'` meta group:
```bash
grep -A 20 "public function schema_coverage" includes/Admin/DashboardData.php | grep "exclude_from_llms"
```
Must show `_citewp_aiso_exclude_from_llms` in the query. If absent, the method is missing the P49 exclusion guard — add it.

- [ ] **Step 9.7: Filter hooks present**

```bash
grep -n "citewp_aiso/data/schema_coverage\|citewp_aiso/data/scored_post_types" includes/Admin/DashboardData.php
```

Both filters must appear. If either is missing, add it to `schema_coverage()`.

- [ ] **Step 9.8: Commit audit results**

Even if no code changes were needed, commit a timestamp note:

```bash
git add includes/Admin/Menu.php includes/Admin/DashboardData.php admin/css/citewp-aiso-admin.css
git commit -m "chore: X20 spec-compliance audit pass — Cite Score KPI card pass S37"
git push
```

(If no files changed: `git commit --allow-empty -m "chore: X20 spec-compliance audit pass — no issues found S37"`)

---

## Task 10: Code-Reviewer Pass (X13 gate — runs before browser verify)

**This is a separate gate from the X20 spec audit.** X20 checks "does the output match the spec." The code-reviewer checks "is the PHP correct" — escaping, query correctness, method signatures, match() arm coverage, _doing_it_wrong usage.

Run `superpowers:requesting-code-review` against the diff of `includes/Admin/DashboardData.php` and `includes/Admin/Menu.php` only. Do not review CSS (presentation-only, no logic) or IconLibrary.php (path constant, trivial).

- [ ] **Step 10.1: Get the diff for review**

```bash
git diff HEAD~6 HEAD -- includes/Admin/DashboardData.php includes/Admin/Menu.php
```

(Adjust the commit count to span from before Task 2 to current HEAD — should cover the `schema_coverage()` method and all 4 card HTML changes.)

- [ ] **Step 10.2: Dispatch code-reviewer subagent**

Use `superpowers:requesting-code-review` with focus areas:

1. **`schema_coverage()` in DashboardData.php:**
   - WP_Query meta_query: is the P49 exclusion guard correct? (relation=AND outer, relation=OR inner, NOT EXISTS + != '1')
   - `match()` arms: does `default` correctly bucket `fail` AND any unexpected status values as `none`?
   - `_doing_it_wrong()` call: correct signature? (method name, message, version constant)
   - `apply_filters()` calls: correct hook name strings? Return value used correctly?
   - Repository instantiation inside loop: performance acceptable for ≤1000 posts?

2. **Card 4 HTML in Menu.php:**
   - All output correctly escaped (`esc_html()`, `esc_attr()`, `esc_url()`)?
   - `$schema_coverage` variable — is it in scope when Card 4 HTML runs? (Must be set in data prep block before the HTML output section)
   - Empty state: `$schema_coverage['total'] > 0` guard — correct? What if the array keys are missing (e.g. if the filter clobbers the return)?

3. **Cards 1–3 HTML:**
   - All `esc_html()` / `esc_attr()` / `phpcs:ignore` comments preserved from original?
   - `$tc_delta` calculation: no division-by-zero risk?

- [ ] **Step 10.3: Fix any issues the reviewer flags**

Apply fixes inline. Re-run PHP syntax check via PostToolUse hook. Commit fixes if any:

```bash
git add includes/Admin/DashboardData.php includes/Admin/Menu.php
git commit -m "fix: code-reviewer corrections — Cite Score KPI card pass S37"
git push
```

If no issues: proceed. Do not skip this task even if no fixes are needed — the review must run.

---

## Task 11: Browser Verify

- [ ] **Step 11.1: Open LocalWP site**

Navigate to `http://citewp-dev.local/wp-admin/admin.php?page=citewp#cite-score` in Chrome at 100% zoom (not 90% — per X18 validation contract).

- [ ] **Step 11.2: Verify the 4-card KPI row**

Confirm:
- 4 equal-width cards displayed in one row
- No 36px orb icons — each card head shows a small 16px inline icon left of the title
- Card titles use the same font/weight as Dashboard card titles (Tier 2: 600 14px Inter primary)
- Card 3 reads "Needs Attention" (not "Issues Detected")
- Card 4 shows Schema Coverage with percentage hero + 3-tile row (Confirmed / SEO Plugin / None)
- All 4 cards render correctly at 100% zoom without wrapping or overflow

- [ ] **Step 11.3: Verify button styles**

Confirm:
- Cards 1, 2, 4 have outline buttons at the bottom
- Card 3 has "View All →" as a head-right link (no bottom button)
- No blue primary-paper buttons in the KPI row
- The "View AI Recommendations →" primary-paper button below is unchanged

- [ ] **Step 11.4: Verify schema tiles (Card 4)**

Confirm:
- 3 tiles show: Confirmed (green), SEO Plugin (yellow), None (red)
- Tile counts are integers, not null/undefined
- Tile counts sum to total_scored (sanity check: should equal Card 2's Y denominator)

- [ ] **Step 11.5: Check debug.log**

```bash
cat "C:/Users/KingpinBWP/Local Sites/citewp-dev/app/public/wp-content/debug.log" | tail -30
```

No new PHP errors or `_doing_it_wrong` notices from this session's changes. If the denominator mismatch notice fires, investigate before claiming the task complete.

- [ ] **Step 11.6: Final push confirmation**

```bash
git log --oneline -8
```

Confirm all task commits are present. Push if any remain unpushed:
```bash
git push
```

---

## Self-Review

**Spec coverage check:**

| Spec requirement | Task |
|---|---|
| 4 cards restyled to Dashboard KPI pattern (P57) | Tasks 5–8 |
| 36px orbs shrunk to 16px inline icons (P62) | Tasks 5–7 |
| Card 3 renamed "Needs Attention" | Task 7 |
| Card 4 Schema Coverage added | Task 8 |
| DashboardData::schema_coverage() with P49 exclusion | Task 2 |
| X15 filter hooks on schema_coverage() | Task 2 |
| Denominator reconciliation assertion | Task 8 Step 8.2 |
| CSS --4col modifier | Task 3 |
| CSS __head-main rule | Task 3 |
| Schema tile CSS | Task 3 |
| IconLibrary layers icon | Task 1 |
| Per-post table anchor confirmed | Task 4 |
| P41 button hierarchy (all outline in KPI row) | Task 9 Step 9.2 |
| P65 activity__heading not touched | Task 9 Step 9.5 |
| Typography Tier 2 on all titles | Task 9 Step 9.1 |
| X20 spec-compliance audit | Task 9 |
| Code-reviewer pass (X13 gate) | Task 10 |
| X4 per-step commits | Each task |
| Browser verify at 100% zoom | Task 11 |

**No gaps found.**
