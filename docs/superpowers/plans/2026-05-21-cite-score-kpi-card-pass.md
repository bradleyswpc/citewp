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
- **`__kpi-progress` CSS MUST be kept.** Card 2 reinstates the progress bar. Do NOT scope out or suppress `.citewp-aiso-kpi-progress` for `.citewp-aiso-cs-kpi-row`. Only `__visual` and `__data` orb-layout rules are suppressed.
- **No flat trend rows on Cards 2, 3, 4.** The "→ based on current scores" filler trend has been removed from Cards 2, 3, and 4. Card 1's real ↑/↓/→ delta trend stays. Equal card height comes from body density (secondary stats + progress bar on Card 2; tile rows on Cards 3/4), not from filler rows.
- **Footer baseline:** each card's `__footer` uses `margin-top: auto` so buttons pin to the card bottom in flex-column context. Do not use `flex:1` on any trend row for this purpose.
- **Full-width buttons:** footer buttons in Cards 1, 2, 4 use `display: block; width: 100%; text-align: center` for visual conformity across the row.

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

- [ ] **Step 3.4: Suppress Cite Score `__visual` and `__data` layout rules only — NOT `__kpi-progress`**

From the grep output in Step 3.1, find any rules scoped to `.citewp-aiso-cs-kpi-row` that apply side-by-side `__visual` + `__data` layout. Remove those rules only — the new HTML no longer uses `__visual` or `__data` wrappers.

If `citewp-aiso-kpi-card__visual` and `citewp-aiso-kpi-card__data` rules exist only in a `.citewp-aiso-cs-kpi-row` scope block, delete the entire scope block. If they are in a shared block, add a scoped suppression:

```css
/* Cite Score KPI row — no orb/data layout (P57/P62 migration) */
.citewp-aiso-cs-kpi-row .citewp-aiso-kpi-card__visual,
.citewp-aiso-cs-kpi-row .citewp-aiso-kpi-card__data {
	display: none; /* removed in S37 — HTML no longer emits these elements */
}
```

**Do NOT suppress `.citewp-aiso-kpi-progress` here.** Card 2 reinstates the progress bar — the CSS must remain active for `.citewp-aiso-cs-kpi-row`.

- [ ] **Step 3.5: Add footer baseline + full-width button rules**

Add in the Cite Score KPI row overrides block (near the `__body` flex-column overrides):

```css
/* Cite Score KPI row — footer pins to card bottom, buttons full-width */
.citewp-aiso-cs-kpi-row .citewp-aiso-kpi-card__footer {
	margin-top: auto;
}

.citewp-aiso-cs-kpi-row .citewp-aiso-kpi-card__footer .citewp-aiso-btn {
	display: block;
	width: 100%;
	text-align: center;
	box-sizing: border-box;
}
```

- [ ] **Step 3.6: Add Card 1 top-page ellipsis rule**

Add in the Cite Score KPI row overrides block:

```css
/* Card 1 — top-page sub line clamped to prevent height overflow */
.citewp-aiso-kpi-card__sub--top-page {
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	max-width: 100%;
}
```

- [ ] **Step 3.8: Add schema tile CSS**

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

- [ ] **Step 3.9: Verify no syntax errors**

PHP syntax check runs automatically. Also visually scan the CSS around your edits for unclosed braces.

- [ ] **Step 3.10: Commit and push**

```bash
git add admin/css/citewp-aiso-admin.css
git commit -m "feat: CSS — --4col grid, __head-main, schema tiles, footer baseline, full-width buttons, cs-kpi visual cleanup"
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
- Modify: `includes/Admin/DashboardData.php` (if `get_unique_bot_count()` is missing)
- Modify: `includes/Admin/Menu.php` (within `render_cite_score_panel()`, ~L975–L1029)

Replace the Card 1 block entirely. The existing block has a 36px `__visual` orb + `__data` side-by-side layout. The new block moves the bot icon to the head row, adds two stacked secondary stats, and keeps the real ↑/↓/→ trend.

- [ ] **Step 5.0: Verify/add data methods**

Check whether `get_unique_bot_count()` and the 1-result variant of `get_top_crawled_pages()` are available:

```bash
grep -n "get_unique_bot_count\|get_top_crawled_pages" includes/Admin/DashboardData.php
```

**`get_top_crawled_pages()`** — should exist from S33. Note its signature (likely takes a `$cutoff` timestamp and `$limit`). You will call it with a 7-day cutoff and limit 1.

**`get_unique_bot_count()`** — if absent, add this method to `DashboardData.php` after `get_top_crawled_pages()`:

```php
/**
 * Returns the count of distinct AI bot user-agents seen in the given window.
 *
 * @param int $cutoff Unix timestamp — only log rows after this time are counted.
 * @return int
 */
public function get_unique_bot_count( int $cutoff ): int {
    global $wpdb;
    $table = \CiteWP\Aiso\Database\Schema::table( 'crawler_logs' );
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin-only, no persistent cache needed; $table is esc_sql of a constant.
    return (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(DISTINCT bot_slug) FROM {$table} WHERE created_at > %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is sanitised above
            gmdate( 'Y-m-d H:i:s', $cutoff )
        )
    );
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
}
```

If `get_top_crawled_pages()` requires a parameter format you cannot determine from the method signature, use the existing `get_top_crawlers(1)` pattern for the top-page title as a fallback — note which approach you used in the commit message.

In `render_cite_score_panel()` data prep (near the `$top_crawler` call), add:

```php
$cutoff_7d          = strtotime( '-7 days' );
$top_page_rows      = $dashboard_data->get_top_crawled_pages( $cutoff_7d, 1 );
$top_page_title     = ! empty( $top_page_rows ) ? ( $top_page_rows[0]['title'] ?? $top_page_rows[0]['uri'] ?? '' ) : '';
$unique_bot_count   = $dashboard_data->get_unique_bot_count( $cutoff_7d );
```

> Check the actual keys returned by `get_top_crawled_pages()` — look at the method and use the correct key for the resolved page title.

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
					<?php if ( ! empty( $top_page_title ) ) : ?>
					<div class="citewp-aiso-kpi-card__sub citewp-aiso-kpi-card__sub--top-page">
						<?php
						echo esc_html( sprintf(
							/* translators: %s: resolved page title */
							__( 'Top page: %s', 'ai-search-optimizer' ),
							$top_page_title
						) );
						?>
					</div>
					<?php endif; ?>
					<?php if ( $unique_bot_count > 0 ) : ?>
					<div class="citewp-aiso-kpi-card__sub">
						<?php
						echo esc_html( sprintf(
							/* translators: %d: number of distinct AI bots detected in last 7 days */
							__( '%d AI bots detected this week.', 'ai-search-optimizer' ),
							$unique_bot_count
						) );
						?>
					</div>
					<?php endif; ?>
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
					<div class="citewp-aiso-kpi-progress">
						<div class="citewp-aiso-kpi-progress__bar" style="width: <?php echo absint( $pct_optimized ); ?>%"></div>
					</div>
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

- [x] **Step 9.7a: Height gate outcome — Card 1 top-page line — KEPT**

Browser verify: all 4 cards rendered at exactly 273px each (CSS height, 100% zoom, Playwright measurement). Top-page line and bot-count line both stay. No code change required.

- [ ] **Step 9.7b: `__kpi-progress` NOT suppressed for `.citewp-aiso-cs-kpi-row`**

```bash
grep -n "kpi-progress" admin/css/citewp-aiso-admin.css
```

Confirm `.citewp-aiso-kpi-progress` rules are NOT inside any `.citewp-aiso-cs-kpi-row` block with `display: none` or `visibility: hidden`. Card 2 reinstates the progress bar — suppressing it here is a bug.

- [ ] **Step 9.7c: No flat trend rows on Cards 2, 3, or 4**

```bash
grep -n "trend--flat\|based on current scores" includes/Admin/Menu.php
```

Flat `→ based on current scores` trend rows must be absent from Cards 2, 3, and 4. Only Card 1's real `$tc_trend_class` trend block is allowed. If any flat trend row remains in Cards 2–4, remove it and re-commit the affected task.

- [ ] **Step 9.7d: Footer `margin-top: auto` rule present**

```bash
grep -n "margin-top.*auto" admin/css/citewp-aiso-admin.css
```

The rule `.citewp-aiso-cs-kpi-row .citewp-aiso-kpi-card__footer { margin-top: auto; }` must be present. If absent, add it per Task 3 Step 3.5 and re-commit the CSS.

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

- [ ] **Step 11.2a: Height gate — measure Card 1 vs tile-card heights**

At 100% zoom, visually compare the rendered height of Card 1 (Top Crawler) against Card 3 (Needs Attention) and Card 4 (Schema Coverage).

- **KEPT:** Card 1 ≤ tile-card height → both secondary stat lines stay. Record "KEPT" in Step 9.7a.
- **CUT:** Card 1 > tile-card height → remove the `$top_page_title` conditional block from Card 1 in `includes/Admin/Menu.php` (keep only the `$unique_bot_count` block). Commit:

```bash
git add includes/Admin/Menu.php
git commit -m "fix: Card 1 height gate — remove top-page line, keep bot-count line"
git push
```

Record "CUT — top-page line removed" in Step 9.7a.

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
| Card 1 secondary stats: top-page (height-gated) + bot count | Task 5 Step 5.0 + Task 5.1 |
| get_unique_bot_count() method | Task 5 Step 5.0 |
| Card 1 top-page ellipsis CSS | Task 3 Step 3.6 |
| __kpi-progress bar on Card 2 (reinstated) | Task 6 Step 6.1 + Task 9 Step 9.7b |
| No flat trend rows on Cards 2, 3, 4 | Tasks 6/7/8 + Task 9 Step 9.7c |
| Footer margin-top: auto (buttons pin to bottom) | Task 3 Step 3.5 + Task 9 Step 9.7d |
| Full-width outline buttons on Cards 1, 2, 4 | Task 3 Step 3.5 |
| Height gate outcome documented (KEPT or CUT) | Task 9 Step 9.7a + Task 11 Step 11.2a |
| X20 spec-compliance audit | Task 9 |
| Code-reviewer pass (X13 gate) | Task 10 |
| X4 per-step commits | Each task |
| Browser verify at 100% zoom | Task 11 |

**No gaps found.**

---

## S37 Follow-Up Amendment Tasks

### Task A-diag: Diagnosis (complete — no implementation needed)

Root cause confirmed via Playwright computed styles: Card 2 HTML emits `.citewp-aiso-kpi-progress__bar`; the CSS targets `.citewp-aiso-kpi-progress__fill`. The `__bar` element receives no height or background, so the bar renders invisible despite `width: 50%` inline style. Fix is in Task B: rename inner element class in Menu.php.

---

### Task B: Fix progress bar — correct class name + score-band color

**Files:**
- Modify: `includes/Admin/Menu.php` (Card 2 HTML block, data prep)
- Modify: `admin/css/citewp-aiso-admin.css` (score-band modifier rules)

- [ ] **Step B.1: Add `$optimized_grade` to data prep**

In `render_cite_score_panel()`, find `$pct_optimized` (already computed ~L826). Immediately after it, add:

```php
$optimized_grade = match ( true ) {
    $pct_optimized >= 80 => 'green',
    $pct_optimized >= 60 => 'yellow',
    $pct_optimized >= 40 => 'orange',
    default              => 'red',
};
```

- [ ] **Step B.2: Fix Card 2 progress bar HTML**

In Card 2 HTML, find:

```php
					<div class="citewp-aiso-kpi-progress">
						<div class="citewp-aiso-kpi-progress__bar" style="width: <?php echo absint( $pct_optimized ); ?>%"></div>
					</div>
```

Replace with:

```php
					<div class="citewp-aiso-kpi-progress citewp-aiso-kpi-progress--<?php echo esc_attr( $optimized_grade ); ?>">
						<div class="citewp-aiso-kpi-progress__fill" style="width: <?php echo absint( $pct_optimized ); ?>%"></div>
					</div>
```

- [ ] **Step B.3: Add score-band modifier rules to CSS**

In `admin/css/citewp-aiso-admin.css`, find the `.citewp-aiso-kpi-progress__fill` rule (~L2192) and add the modifier overrides immediately after it:

```css
/* Score-band colored fill — P44 thresholds applied to pct_optimized */
.citewp-aiso-kpi-progress--green  .citewp-aiso-kpi-progress__fill { background: var( --citewp-score-green ); }
.citewp-aiso-kpi-progress--yellow .citewp-aiso-kpi-progress__fill { background: var( --citewp-score-yellow ); }
.citewp-aiso-kpi-progress--orange .citewp-aiso-kpi-progress__fill { background: var( --citewp-score-orange ); }
.citewp-aiso-kpi-progress--red    .citewp-aiso-kpi-progress__fill { background: var( --citewp-score-red ); }
```

Do NOT remove or change the base `.citewp-aiso-kpi-progress__fill { background: var(--citewp-score-green); }` — Dashboard KPI bars use the element without a modifier and rely on it as a fallback.

- [ ] **Step B.4: Verify PHP syntax + commit**

```bash
git add includes/Admin/Menu.php admin/css/citewp-aiso-admin.css
git commit -m "fix: Card 2 progress bar — __bar→__fill, score-band modifier class (P44)"
git push
```

---

### Task C: Head-icon tints (decorative, P38/P62)

**Files:**
- Modify: `includes/Admin/Menu.php` (add modifier classes to Cards 2, 3, 4)
- Modify: `admin/css/citewp-aiso-admin.css` (per-card icon tint overrides)

- [ ] **Step C.1: Add modifier classes to Cards 2, 3, 4 in Menu.php**

Find the opening div of each card and add the modifier class:

Card 2 (`<!-- Card 2: Posts/Pages Optimized -->`):
```php
<div class="citewp-aiso-kpi-card citewp-aiso-kpi-card--optimized">
```

Card 3 (`<!-- Card 3: Needs Attention -->`):
```php
<div class="citewp-aiso-kpi-card citewp-aiso-kpi-card--needs-attention">
```

Card 4 (`<!-- Card 4: Schema Coverage -->`):
```php
<div class="citewp-aiso-kpi-card citewp-aiso-kpi-card--schema-coverage">
```

Card 1 already has `citewp-aiso-kpi-card--top-crawler` — no change needed.

- [ ] **Step C.2: Add icon tint CSS overrides**

Find the `.citewp-aiso-kpi-card__head-main svg` rule (within the `__head-main` block added in Task 3 Step 3.3) and add per-card overrides immediately after it:

```css
/* Per-card decorative icon tints (P38 — wallpaper level, no semantic encoding) */
.citewp-aiso-kpi-card--top-crawler    .citewp-aiso-kpi-card__head-main svg { color: var( --citewp-tint-teal ); }
.citewp-aiso-kpi-card--optimized      .citewp-aiso-kpi-card__head-main svg { color: var( --citewp-score-green ); }
.citewp-aiso-kpi-card--needs-attention .citewp-aiso-kpi-card__head-main svg { color: var( --citewp-score-orange ); }
.citewp-aiso-kpi-card--schema-coverage .citewp-aiso-kpi-card__head-main svg { color: var( --citewp-tint-purple ); }
```

> **Kill-switch:** If browser-verify shows the row feels visually busy, revert Task C only — remove these four rules from CSS and the three modifier classes from Cards 2/3/4 HTML. Task B is independent and must stay.

- [ ] **Step C.3: Verify + commit**

```bash
git add includes/Admin/Menu.php admin/css/citewp-aiso-admin.css
git commit -m "feat: head-icon tints per card (decorative P38/P62 — kill-switch see spec)"
git push
```

---

### Task D: X20 Audit (follow-up assertions)

- [ ] **Step D.1: Progress bar visible**

```bash
# Playwright / browser inspect on Card 2:
# .citewp-aiso-kpi-progress__fill computed height > 0, background != transparent
```

Confirm `__fill` (not `__bar`) is in DOM, height = 4px (inherits from track), background = score-band token matching `$pct_optimized` grade.

- [ ] **Step D.2: Score-band token correct**

At current `$pct_optimized = 50%` → grade should be `yellow` (≥40, <60). Confirm modifier class is `citewp-aiso-kpi-progress--yellow` and fill background computes to `var(--citewp-score-yellow)`.

- [ ] **Step D.3: Icon tints don't collide with functional colors**

Card 3 value digit uses `citewp-aiso-kpi-score--red` or `--orange` (functional). Card 4 tiles use score-band tokens (functional). Confirm the icon tint overrides (decorative) target `__head-main svg` only — no bleed onto value, caption, or tile elements.

- [ ] **Step D.4: Commit audit**

```bash
git commit --allow-empty -m "chore: X20 follow-up audit — progress bar + icon tints S37"
git push
```

---

### Task E: Browser Verify (follow-up)

- [ ] **Step E.1: Card 2 progress bar visible**

Navigate to `http://citewp-dev.local/wp-admin/admin.php?page=citewp#cite-score` at 100% zoom. Card 2 must show a colored bar below "50% of your scored content". At 50%, the bar should be yellow.

- [ ] **Step E.2: Card heights still equal**

All 4 cards must still render at equal height. Adding the progress bar should not increase Card 2's height above the others (bar fits within existing body height via `overflow: hidden` on the track).

- [ ] **Step E.3: Icon tints (if Task C kept)**

Each card head icon shows its assigned tint: teal (Card 1), green (Card 2), orange (Card 3), purple (Card 4). Confirm tints are subtle — if visually busy, apply kill-switch.

- [ ] **Step E.4: debug.log clean**

No new PHP errors from the amendment.

- [ ] **Step E.5: Final push**

```bash
git log --oneline -5
git push
```
