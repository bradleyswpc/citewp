# Session 17 — Crawler Logs + Settings v3 Polish Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the "light v3 styling" on Crawler Logs and Settings admin pages with the full component-spec-compliant v3 treatment per `UI-DESIGN-SYSTEM.md` (P40).

**Architecture:** Sequential delivery — Task 1 adds all new CSS classes with no PHP changes; Task 2 rewires Crawler Logs PHP to use them; Task 3 rewires Settings PHP to use them. Each task is independently testable and committed before the next begins. Shared CSS (`.citewp-aiso-page-header`) is written once in Task 1 and reused by both pages.

**Tech Stack:** PHP 8.0+, WordPress 6.5+, WP_List_Table, `admin/css/citewp-aiso-admin.css` (append-only for new sections), no new PHP files, no JS libraries.

---

## X20 — Per-Component Spec Compliance Checklists

These audits drive the BEFORE/AFTER framing in each task. Every ✗ BEFORE becomes a ✓ AFTER.

---

### Component 1 — Page Header Strip (both pages)

| Spec requirement | BEFORE | AFTER |
|---|---|---|
| White background | ✗ `.citewp-aiso-panel__title-row` (no explicit bg, inherits paper) | ✓ `var(--citewp-white)` on `.citewp-aiso-page-header` |
| Border-bottom 1px `--citewp-border` | ✗ None | ✓ `border-bottom: 1px solid var(--citewp-border)` |
| Padding 20px 24px | ✗ Uses panel title-row padding | ✓ `padding: var(--sp-5) var(--sp-6)` |
| Flex row, space-between | ✓ Exists on panel__title-row | ✓ Preserved on `.citewp-aiso-page-header` |
| Left: title Inter 700 20px | ✓ h2 exists but unsized | ✓ `font: 700 var(--fs-xl)/1.2 'Inter'...` |
| Left: desc Inter 400 13px | ✓ `.citewp-aiso-panel__subtitle` exists | ✓ `.citewp-aiso-page-header__desc` 13px |
| Right: optional action slot | ✓ Export CSV button already right-aligned | ✓ Preserved in `__right` flex container |
| Right (Logs): filter pills | ✗ Pills don't exist; filters are in `extra_tablenav()` | ✓ Pill chips in `__right`, `extra_tablenav()` suppressed |
| Right (Settings): text-tab nav | ✗ Tab nav is a separate row below the title | ✓ `.citewp-aiso-settings-tabnav` inside `__right` |
| No navy gradient | ✓ Panel title-row has no navy gradient | ✓ Preserved |

---

### Component 2 — Filter Pill Chips (Crawler Logs date range)

| Spec requirement | BEFORE | AFTER |
|---|---|---|
| Pill shape (border-radius 999px) | ✗ `<select>` dropdown via `extra_tablenav()` | ✓ `.citewp-aiso-filter-pill` with `border-radius: 999px` |
| Active: Obsidian fill + white text | ✗ N/A (select) | ✓ `.citewp-aiso-filter-pill--active`: `background: var(--citewp-obsidian)` |
| Inactive: paper-tinted + border | ✗ N/A (select) | ✓ `.citewp-aiso-filter-pill--inactive`: `background: var(--citewp-paper)` + border |
| `<a>` links with GET params | ✗ `<select>` + submit button | ✓ `<a href="?...citewp_aiso_range=X">` links |
| Options: All time / Last 24h / 7 days / 30 days | ✓ Exists in select | ✓ Same 4 options as pill links |
| Second filter (bot dropdown) | ✓ Exists in `extra_tablenav()` | ✓ Kept in `extra_tablenav()` — only date range pills move to header; bot select stays below table |

> **Note on bot filter:** The bot filter dropdown (a dynamic list of detected bots) stays in `extra_tablenav()` since it requires the `$distinct_bots` list populated by `prepare_items()`. Only the date range filter moves to the page header strip. `extra_tablenav()` is modified to suppress only the date range `<select>` — the bot dropdown remains.

---

### Component 3 — KPI Card v3 (Crawler Logs — 4 cards)

| Spec requirement | BEFORE | AFTER |
|---|---|---|
| Head row (orb + title side-by-side) | ✗ Orb and body are siblings, not a head row | ✓ `.citewp-aiso-kpi-card__head` wraps orb + head-title |
| Orb 32px colored background | ✓ Orb exists but only `--blue` variant | ✓ `--blue`, `--purple`, `--teal`, `--citrine` variants added |
| Title: Inter 700 11px uppercase | ✗ Title is inside `__body`, no uppercase | ✓ `.citewp-aiso-kpi-card__head-title` uppercase + letter-spacing |
| Value: JetBrains Mono 700 28px | ✓ `__value` class exists (verify size in CSS) | ✓ Preserved |
| Caption: Inter 400 12px muted | ✗ No caption element | ✓ `.citewp-aiso-kpi-card__caption` added |
| 4-column layout (4 cards) | ✗ 3-column layout (`--3col`) with 3 cards | ✓ `citewp-aiso-kpi-row--4col` + 4 new cards |
| Cards: Total Crawls / Unique Bots / Pages Crawled / Avg Frequency | ✗ 24h Visits / 7d Visits / 30d Visits | ✓ All 4 correct cards |
| New DB queries | ✗ Missing `unique_bots`, `pages_crawled`, `avg_freq` | ✓ Added in render() |

---

### Component 4 — WP_List_Table Paper Card Wrapper (Crawler Logs)

| Spec requirement | BEFORE | AFTER |
|---|---|---|
| Paper card container (white bg, border, border-radius) | ✗ `.citewp-aiso-table-wrap` — no paper card styling | ✓ `.citewp-aiso-logs-table-card` wrapper with white bg + border + radius |
| Full-width table inside container | ✓ Table is full-width | ✓ Preserved |
| Border 1px `--citewp-border` | ✗ None | ✓ Added to `.citewp-aiso-logs-table-card` |
| Border-radius `--radius-md` | ✗ None | ✓ Added |

---

### Component 5 — Pro Tip Footer (both pages)

| Spec requirement | BEFORE | AFTER |
|---|---|---|
| `.citewp-aiso-protip` present | ✗ Not rendered on Crawler Logs or Settings | ✓ Rendered at bottom of `.citewp-aiso-page-body` on both pages |
| CSS already exists (Section 17) | ✓ Defined in CSS from Dashboard session | ✓ Reused — no new CSS needed |
| Purple gradient background | ✓ CSS exists | ✓ Rendered |
| Orb + heading + body text | ✓ CSS exists | ✓ Page-specific copy added |

---

### Component 6 — Form Section Card (Settings)

| Spec requirement | BEFORE | AFTER |
|---|---|---|
| Paper container, overflow:hidden | ✗ `.citewp-aiso-section` — partial styling | ✓ `.citewp-aiso-fscard` white bg, border, border-radius, overflow:hidden |
| Section header strip (paper-tinted bg, Inter 700 14px) | ✗ `citewp-aiso-section__header` — different styling | ✓ `.citewp-aiso-fscard__header`: `var(--citewp-paper)` bg, 700 14px |
| Field rows: 16px 20px padding + border-bottom | ✗ `.citewp-aiso-section__body` with stacked layout | ✓ `.citewp-aiso-fscard__row` grid rows with `padding: var(--sp-4) var(--sp-5)` |
| Two-column layout: ~33% left / ~67% right | ✗ Stacked single-column layout | ✓ `grid-template-columns: 1fr 2fr` |
| Icon orb on left (32px, colored bg) | ✗ No orbs on Settings fields | ✓ `.citewp-aiso-fscard__orb` with `--orange`, `--blue`, `--teal`, `--gray` variants |
| Last row: no border-bottom | ✓ Implied by last-child | ✓ `.citewp-aiso-fscard__row:last-child { border-bottom: none }` |

---

### Component 7 — Settings Tab Nav (text-with-underline in header strip)

| Spec requirement | BEFORE | AFTER |
|---|---|---|
| Text tabs with bottom underline | ✗ `.citewp-aiso-tabs__btn` — pill/button style (separate nav row) | ✓ `.citewp-aiso-settings-tabnav__item` — text + underline only |
| Active underline: `#2563EB` (blue) | ✗ Active state uses different styling | ✓ `border-bottom: 2px solid #2563eb` on `--active` modifier |
| 32px item height | ✗ Not enforced | ✓ `height: 32px` on `.citewp-aiso-settings-tabnav__item` |
| In header strip right side | ✗ Separate nav row below header | ✓ Inside `.citewp-aiso-page-header__right` |
| localStorage persistence preserved | ✓ `citewp_aiso_settings_tab` key used | ✓ Key preserved; only JS selectors updated |
| `data-tab` attribute on buttons | ✓ Exists on `.citewp-aiso-tabs__btn` | ✓ Preserved on `.citewp-aiso-settings-tabnav__item` |
| Tab panel IDs unchanged | ✓ `citewp-aiso-tab-{slug}` | ✓ Panel IDs unchanged; only class changes |

---

### Component 8 — Primary Action Button (Settings Save Changes)

| Spec requirement | BEFORE | AFTER |
|---|---|---|
| `#2563EB` fill, white text | ✗ `submit_button(null, 'primary')` — WP admin theme color (not brand-locked) | ✓ Custom `<button class="citewp-aiso-btn--primary-action">` with `background: #2563eb` |
| Inter 700 14px | ✗ WP default button font | ✓ `font: 700 var(--fs-base)/1 'Inter'...` |
| Border-radius 6px | ✗ WP default | ✓ `border-radius: 6px` |
| Outside Form Section Cards, margin-top 24px | ✓ `.citewp-aiso-save-bar` already outside cards | ✓ Preserved |

---

## File-by-File Change Manifest

| File | Action | What changes |
|---|---|---|
| `admin/css/citewp-aiso-admin.css` | **Append** ~120 lines | Sections 20–25: page header strip, filter pills, KPI v3 additions, logs table card, form section card, settings tab nav, primary action button |
| `includes/Admin/LogsPage.php` | **Modify** `render()` | Replace `panel__title-row` + 3-col KPI row + `table-wrap`; add `unique_bots` / `pages_crawled` / `avg_freq` queries; add page header with filter pills; add 4-col KPI row with head/caption; add paper card table wrapper; add Pro Tip footer |
| `includes/Admin/LogsTable.php` | **Modify** `extra_tablenav()` | Remove date-range `<select>` block from top nav only; bot filter `<select>` stays |
| `includes/Settings/Page.php` | **Modify** `render()` | Replace `panel__title-row` + separate tab nav; add page header strip with tab nav on right; replace `citewp-aiso-section` cards with Form Section Card markup; replace `submit_button()` with primary action button; add Pro Tip footer; update inline JS selectors |

**Files NOT touched:** `includes/Scoring/Engine.php` (NO-TOUCH), `includes/Admin/Menu.php`, `includes/Admin/DashboardPage.php`, `includes/Admin/LogsTable.php` prepare_items/columns (query logic unchanged).

---

## Task 1 — CSS: Append Sections 20–25

**Files:**
- Modify: `admin/css/citewp-aiso-admin.css` (append after line ~1066, after Section 19)

- [ ] **Step 1: Verify current line count**

```powershell
(Get-Content "admin\css\citewp-aiso-admin.css").Count
```
Expected: ~1066–1100 lines. Confirms safe append point.

- [ ] **Step 2: Append new CSS sections**

Append this exact block to the end of `admin/css/citewp-aiso-admin.css`:

```css
/* === SECTION 20: Page Header Strip (P40 — all non-Dashboard admin pages) === */

.citewp-aiso-page-header {
  background: var(--citewp-white);
  border-bottom: 1px solid var(--citewp-border);
  padding: var(--sp-5) var(--sp-6);
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-4);
}

.citewp-aiso-page-header__left {
  flex: 1;
  min-width: 0;
}

.citewp-aiso-page-header__title {
  font: 700 var(--fs-xl)/1.2 'Inter', system-ui, -apple-system, sans-serif;
  color: var(--citewp-text-primary);
  margin: 0 0 var(--sp-1);
}

.citewp-aiso-page-header__desc {
  font: 400 var(--fs-sm)/1.4 'Inter', system-ui, -apple-system, sans-serif;
  color: var(--citewp-text-muted);
  margin: 0;
}

.citewp-aiso-page-header__right {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  flex-shrink: 0;
}

/* === SECTION 21: Filter pills (Crawler Logs date range) === */

.citewp-aiso-filter-pills {
  display: flex;
  align-items: center;
  gap: var(--sp-1);
}

.citewp-aiso-filter-pill {
  display: inline-flex;
  align-items: center;
  padding: 5px 14px;
  border-radius: 999px;
  font: 600 var(--fs-xs)/1 'Inter', system-ui, -apple-system, sans-serif;
  text-decoration: none;
  transition: background 0.12s, color 0.12s, border-color 0.12s;
  white-space: nowrap;
}

.citewp-aiso-filter-pill--active {
  background: var(--citewp-obsidian);
  color: var(--citewp-white);
  border: 1px solid var(--citewp-obsidian);
}

.citewp-aiso-filter-pill--inactive {
  background: var(--citewp-paper);
  color: var(--citewp-text-secondary);
  border: 1px solid var(--citewp-border);
}

.citewp-aiso-filter-pill--inactive:hover {
  border-color: #94a3b8;
  background: var(--citewp-paper-mid);
  color: var(--citewp-text-primary);
}

/* === SECTION 22: KPI card v3 additions — head row, caption, new orb variants, 4-col layout, table card === */

.citewp-aiso-kpi-row--4col {
  grid-template-columns: repeat(4, 1fr);
}

.citewp-aiso-kpi-card__head {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  margin-bottom: var(--sp-3);
}

.citewp-aiso-kpi-card__head-title {
  font: 700 11px/1 'Inter', system-ui, -apple-system, sans-serif;
  color: var(--citewp-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.citewp-aiso-kpi-card__caption {
  font: 400 var(--fs-xs)/1.4 'Inter', system-ui, -apple-system, sans-serif;
  color: var(--citewp-text-muted);
  margin-top: var(--sp-1);
}

/* New orb color variants */
.citewp-aiso-kpi-card__orb--purple {
  background: var(--citewp-purple-tint);
  color: var(--citewp-tint-purple);
}

.citewp-aiso-kpi-card__orb--teal {
  background: var(--citewp-teal-tint);
  color: var(--citewp-tint-teal);
}

.citewp-aiso-kpi-card__orb--citrine {
  background: rgba(232, 212, 0, 0.14);
  color: #9a7e00;
}

/* Logs table paper card wrapper */
.citewp-aiso-logs-table-card {
  background: var(--citewp-white);
  border: 1px solid var(--citewp-border);
  border-radius: var(--radius-md);
  overflow: hidden;
}

/* === SECTION 23: Form Section Card (Settings v3 — two-column) === */

.citewp-aiso-fscard {
  background: var(--citewp-white);
  border: 1px solid var(--citewp-border);
  border-radius: var(--radius-md);
  overflow: hidden;
  margin-bottom: var(--sp-4);
}

.citewp-aiso-fscard__header {
  background: var(--citewp-paper);
  border-bottom: 1px solid var(--citewp-border);
  padding: 10px var(--sp-5);
  font: 700 var(--fs-base)/1.2 'Inter', system-ui, -apple-system, sans-serif;
  color: var(--citewp-text-primary);
}

.citewp-aiso-fscard__row {
  display: grid;
  grid-template-columns: 1fr 2fr;
  gap: var(--sp-5);
  padding: var(--sp-4) var(--sp-5);
  border-bottom: 1px solid #eef2f7;
  align-items: start;
}

.citewp-aiso-fscard__row:last-child {
  border-bottom: none;
}

.citewp-aiso-fscard__left {
  display: flex;
  gap: 10px;
  align-items: flex-start;
}

.citewp-aiso-fscard__orb {
  width: 32px;
  height: 32px;
  border-radius: var(--radius-sm);
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  color: inherit;
}

.citewp-aiso-fscard__orb--orange { background: var(--citewp-orange-tint); color: var(--citewp-tint-orange); }
.citewp-aiso-fscard__orb--blue   { background: var(--citewp-blue-tint);   color: var(--citewp-tint-blue);   }
.citewp-aiso-fscard__orb--teal   { background: var(--citewp-teal-tint);   color: var(--citewp-tint-teal);   }
.citewp-aiso-fscard__orb--gray   { background: var(--citewp-paper-mid);   color: var(--citewp-text-muted);  }

.citewp-aiso-fscard__field-title {
  font: 700 var(--fs-sm)/1.3 'Inter', system-ui, -apple-system, sans-serif;
  color: var(--citewp-text-primary);
  margin-bottom: 2px;
}

.citewp-aiso-fscard__field-desc {
  font: 400 var(--fs-xs)/1.4 'Inter', system-ui, -apple-system, sans-serif;
  color: var(--citewp-text-muted);
}

.citewp-aiso-fscard__right {
  display: flex;
  flex-direction: column;
  gap: var(--sp-2);
  justify-content: flex-start;
  padding-top: 4px;
}

/* === SECTION 24: Settings tab nav — text-with-underline in page header strip === */

.citewp-aiso-settings-tabnav {
  display: flex;
  align-items: flex-end;
  gap: 0;
  height: 32px;
}

.citewp-aiso-settings-tabnav__item {
  display: inline-flex;
  align-items: center;
  height: 32px;
  padding: 0 var(--sp-3);
  font: 600 var(--fs-sm)/1 'Inter', system-ui, -apple-system, sans-serif;
  color: var(--citewp-text-muted);
  border-bottom: 2px solid transparent;
  border-top: none;
  border-left: none;
  border-right: none;
  background: transparent;
  cursor: pointer;
  white-space: nowrap;
  transition: color 0.12s, border-bottom-color 0.12s;
}

.citewp-aiso-settings-tabnav__item:hover {
  color: var(--citewp-text-primary);
}

.citewp-aiso-settings-tabnav__item--active {
  color: var(--citewp-text-primary);
  border-bottom-color: #2563eb;
}

/* === SECTION 25: Primary Action button (page-level save / submit) === */

.citewp-aiso-btn--primary-action {
  display: inline-flex;
  align-items: center;
  gap: var(--sp-1);
  padding: var(--sp-2) var(--sp-5);
  background: #2563eb;
  color: var(--citewp-white);
  border: 1px solid #2563eb;
  border-radius: 6px;
  font: 700 var(--fs-base)/1 'Inter', system-ui, -apple-system, sans-serif;
  cursor: pointer;
  text-decoration: none;
  transition: background 0.12s, border-color 0.12s;
}

.citewp-aiso-btn--primary-action:hover,
.citewp-aiso-btn--primary-action:focus {
  background: #1d4ed8;
  border-color: #1d4ed8;
  color: var(--citewp-white);
}

.citewp-aiso-btn--primary-action:focus-visible {
  outline: 2px solid #2563eb;
  outline-offset: 2px;
}
```

- [ ] **Step 3: Verify no existing classes were accidentally overridden**

Search for any existing use of these class names that might conflict:
```powershell
Select-String -Path "admin\css\citewp-aiso-admin.css" -Pattern "citewp-aiso-page-header|citewp-aiso-filter-pill|citewp-aiso-fscard|citewp-aiso-settings-tabnav|citewp-aiso-btn--primary-action|citewp-aiso-logs-table-card"
```
Expected: 0 matches (all new classes).

- [ ] **Step 4: Open browser and verify existing pages are unaffected**

Navigate to: `http://citewp-dev.local/wp-admin/admin.php?page=citewp-aiso`
- Dashboard should look identical to before
- No console errors

- [ ] **Step 5: Commit**

```bash
git add admin/css/citewp-aiso-admin.css
git commit -m "feat: add v3 page header strip, filter pills, KPI v3, FSCard, settings tab nav CSS (Sections 20-25)"
```

---

## Task 2 — Crawler Logs PHP

**Files:**
- Modify: `includes/Admin/LogsPage.php`
- Modify: `includes/Admin/LogsTable.php`

### Step-by-step

- [ ] **Step 1: Add new DB queries and compute avg_freq**

In `includes/Admin/LogsPage.php`, replace the existing query block (lines 53–58) with this expanded block:

BEFORE:
```php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin stats page; $table_name is esc_sql() of a hardcoded constant. Real-time data, intentionally uncached.
$total     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$count_24h = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE detected_at >= %s", gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ) ) );   // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$count_7d  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE detected_at >= %s", gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) ) ) );   // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$count_30d = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE detected_at >= %s", gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) ) ) );  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:enable
```

AFTER:
```php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin stats page; $table_name is esc_sql() of a hardcoded constant. Real-time data, intentionally uncached.
$total         = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$unique_bots   = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT bot_name) FROM {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$pages_crawled = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT request_uri) FROM {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$count_30d     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE detected_at >= %s", gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:enable

$avg_freq = $count_30d > 0 ? round( $count_30d / 30, 1 ) : 0.0;
```

- [ ] **Step 2: Add filter pill URL computation**

After the existing `$range_filter` validation block (currently ~line 61), add the base URL and range options for filter pills:

BEFORE (existing code after bot/range vars):
```php
$export_args = array_filter(
```

AFTER (insert before `$export_args`):
```php
// Whitelist range filter value.
if ( ! in_array( $range_filter, [ '24h', '7d', '30d' ], true ) ) {
    $range_filter = '';
}

// Base URL for date range filter pills.
$base_url = add_query_arg(
    [ 'page' => Menu::SLUG_PARENT, 'citewp_section' => 'crawler-logs' ],
    admin_url( 'admin.php' )
);

$range_options = [
    ''    => __( 'All time', 'ai-search-optimizer' ),
    '24h' => __( 'Last 24h', 'ai-search-optimizer' ),
    '7d'  => __( '7 days', 'ai-search-optimizer' ),
    '30d' => __( '30 days', 'ai-search-optimizer' ),
];

$export_args = array_filter(
```

- [ ] **Step 3: Replace the panel title-row HTML with the Page Header Strip**

BEFORE (lines 79–87):
```php
<div class="citewp-aiso-panel__title-row">
    <div>
        <h2><?php esc_html_e( 'Crawler Logs', 'ai-search-optimizer' ); ?></h2>
        <p class="citewp-aiso-panel__subtitle"><?php esc_html_e( 'AI crawler activity on your site.', 'ai-search-optimizer' ); ?></p>
    </div>
    <a href="<?php echo esc_url( $export_url ); ?>" class="citewp-aiso-btn citewp-aiso-btn--secondary">
        <?php esc_html_e( 'Export CSV', 'ai-search-optimizer' ); ?>
    </a>
</div>
```

AFTER:
```php
<div class="citewp-aiso-page-header">
    <div class="citewp-aiso-page-header__left">
        <h1 class="citewp-aiso-page-header__title"><?php esc_html_e( 'Crawler Logs', 'ai-search-optimizer' ); ?></h1>
        <p class="citewp-aiso-page-header__desc"><?php esc_html_e( 'AI crawler activity on your site.', 'ai-search-optimizer' ); ?></p>
    </div>
    <div class="citewp-aiso-page-header__right">
        <div class="citewp-aiso-filter-pills">
            <?php foreach ( $range_options as $value => $label ) :
                $is_active  = $range_filter === $value;
                $pill_url   = $value === ''
                    ? $base_url
                    : add_query_arg( 'citewp_aiso_range', $value, $base_url );
                $pill_class = $is_active
                    ? 'citewp-aiso-filter-pill citewp-aiso-filter-pill--active'
                    : 'citewp-aiso-filter-pill citewp-aiso-filter-pill--inactive';
            ?>
                <a href="<?php echo esc_url( $pill_url ); ?>" class="<?php echo esc_attr( $pill_class ); ?>">
                    <?php echo esc_html( $label ); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <a href="<?php echo esc_url( $export_url ); ?>" class="citewp-aiso-btn citewp-aiso-btn--secondary">
            <?php esc_html_e( 'Export CSV', 'ai-search-optimizer' ); ?>
        </a>
    </div>
</div>
```

- [ ] **Step 4: Replace the 3-col KPI row with the 4-col v3 KPI row**

BEFORE (lines 90–112 — entire 3-card KPI row):
```php
<div class="citewp-aiso-kpi-row citewp-aiso-kpi-row--3col">
    <div class="citewp-aiso-kpi-card">
        <div class="citewp-aiso-kpi-card__orb citewp-aiso-kpi-card__orb--blue"><?php echo IconLibrary::icon( 'search', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary returns trusted SVG markup. ?></div>
        <div class="citewp-aiso-kpi-card__body">
            <p class="citewp-aiso-kpi-card__title"><?php esc_html_e( '24h Visits', 'ai-search-optimizer' ); ?></p>
            <p class="citewp-aiso-kpi-card__value"><?php echo esc_html( number_format_i18n( $count_24h ) ); ?></p>
        </div>
    </div>
    <div class="citewp-aiso-kpi-card">
        <div class="citewp-aiso-kpi-card__orb citewp-aiso-kpi-card__orb--blue"><?php echo IconLibrary::icon( 'search', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary returns trusted SVG markup. ?></div>
        <div class="citewp-aiso-kpi-card__body">
            <p class="citewp-aiso-kpi-card__title"><?php esc_html_e( '7d Visits', 'ai-search-optimizer' ); ?></p>
            <p class="citewp-aiso-kpi-card__value"><?php echo esc_html( number_format_i18n( $count_7d ) ); ?></p>
        </div>
    </div>
    <div class="citewp-aiso-kpi-card">
        <div class="citewp-aiso-kpi-card__orb citewp-aiso-kpi-card__orb--blue"><?php echo IconLibrary::icon( 'search', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary returns trusted SVG markup. ?></div>
        <div class="citewp-aiso-kpi-card__body">
            <p class="citewp-aiso-kpi-card__title"><?php esc_html_e( '30d Visits', 'ai-search-optimizer' ); ?></p>
            <p class="citewp-aiso-kpi-card__value"><?php echo esc_html( number_format_i18n( $count_30d ) ); ?></p>
        </div>
    </div>
</div>
```

AFTER:
```php
<div class="citewp-aiso-kpi-row citewp-aiso-kpi-row--4col">

    <div class="citewp-aiso-kpi-card">
        <div class="citewp-aiso-kpi-card__head">
            <div class="citewp-aiso-kpi-card__orb citewp-aiso-kpi-card__orb--blue"><?php echo IconLibrary::icon( 'search', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary returns trusted SVG markup. ?></div>
            <span class="citewp-aiso-kpi-card__head-title"><?php esc_html_e( 'Total Crawls', 'ai-search-optimizer' ); ?></span>
        </div>
        <p class="citewp-aiso-kpi-card__value"><?php echo esc_html( number_format_i18n( $total ) ); ?></p>
        <p class="citewp-aiso-kpi-card__caption"><?php esc_html_e( 'All-time AI crawler visits', 'ai-search-optimizer' ); ?></p>
    </div>

    <div class="citewp-aiso-kpi-card">
        <div class="citewp-aiso-kpi-card__head">
            <div class="citewp-aiso-kpi-card__orb citewp-aiso-kpi-card__orb--purple"><?php echo IconLibrary::icon( 'bot', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary returns trusted SVG markup. ?></div>
            <span class="citewp-aiso-kpi-card__head-title"><?php esc_html_e( 'Unique Bots', 'ai-search-optimizer' ); ?></span>
        </div>
        <p class="citewp-aiso-kpi-card__value"><?php echo esc_html( number_format_i18n( $unique_bots ) ); ?></p>
        <p class="citewp-aiso-kpi-card__caption"><?php esc_html_e( 'Distinct AI engines detected', 'ai-search-optimizer' ); ?></p>
    </div>

    <div class="citewp-aiso-kpi-card">
        <div class="citewp-aiso-kpi-card__head">
            <div class="citewp-aiso-kpi-card__orb citewp-aiso-kpi-card__orb--teal"><?php echo IconLibrary::icon( 'eye', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary returns trusted SVG markup. ?></div>
            <span class="citewp-aiso-kpi-card__head-title"><?php esc_html_e( 'Pages Crawled', 'ai-search-optimizer' ); ?></span>
        </div>
        <p class="citewp-aiso-kpi-card__value"><?php echo esc_html( number_format_i18n( $pages_crawled ) ); ?></p>
        <p class="citewp-aiso-kpi-card__caption"><?php esc_html_e( 'Unique URLs visited', 'ai-search-optimizer' ); ?></p>
    </div>

    <div class="citewp-aiso-kpi-card">
        <div class="citewp-aiso-kpi-card__head">
            <div class="citewp-aiso-kpi-card__orb citewp-aiso-kpi-card__orb--citrine"><?php echo IconLibrary::icon( 'calendar', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary returns trusted SVG markup. ?></div>
            <span class="citewp-aiso-kpi-card__head-title"><?php esc_html_e( 'Avg Frequency', 'ai-search-optimizer' ); ?></span>
        </div>
        <p class="citewp-aiso-kpi-card__value"><?php echo esc_html( $avg_freq . '/day' ); ?></p>
        <p class="citewp-aiso-kpi-card__caption"><?php esc_html_e( '30-day average', 'ai-search-optimizer' ); ?></p>
    </div>

</div>
```

- [ ] **Step 5: Wrap the table in the paper card container and add Pro Tip footer**

BEFORE (lines 119–128 — the table wrap and closing body div):
```php
<?php elseif ( $this->table ) : ?>
    <div class="citewp-aiso-table-wrap">
        <form method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr( Menu::SLUG_PARENT ); ?>" />
            <input type="hidden" name="citewp_section" value="crawler-logs" />
            <?php $this->table->display(); ?>
        </form>
    </div>
<?php endif; ?>
</div><!-- .citewp-aiso-page-body -->
```

AFTER:
```php
<?php elseif ( $this->table ) : ?>
    <div class="citewp-aiso-logs-table-card citewp-aiso-table-wrap">
        <form method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr( Menu::SLUG_PARENT ); ?>" />
            <input type="hidden" name="citewp_section" value="crawler-logs" />
            <?php $this->table->display(); ?>
        </form>
    </div>
<?php endif; ?>

<div class="citewp-aiso-protip">
    <div class="citewp-aiso-protip__left">
        <div class="citewp-aiso-protip__orb"><?php echo IconLibrary::icon( 'sparkles', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
        <div class="citewp-aiso-protip__content">
            <p class="citewp-aiso-protip__heading"><?php esc_html_e( 'Pro Tip', 'ai-search-optimizer' ); ?></p>
            <p class="citewp-aiso-protip__body"><?php esc_html_e( 'Frequent AI crawler visits signal your content is being actively indexed. Upgrade to CiteWP Pro for 1-year log retention and advanced bot analytics.', 'ai-search-optimizer' ); ?></p>
        </div>
    </div>
</div>

</div><!-- .citewp-aiso-page-body -->
```

- [ ] **Step 6: Remove date range `<select>` from LogsTable::extra_tablenav()**

In `includes/Admin/LogsTable.php`, modify `extra_tablenav()` to remove only the date range select. The bot filter select stays.

BEFORE (lines 124–158, full method):
```php
protected function extra_tablenav( $which ): void {
    if ( $which !== 'top' ) {
        return;
    }

    $current_bot   = $this->validated_bot_filter();
    $current_range = $this->validated_range_filter();
    ?>
    <div class="alignleft actions">
        <label class="screen-reader-text" for="citewp_aiso_bot_filter">
            <?php esc_html_e( 'Filter by bot', 'ai-search-optimizer' ); ?>
        </label>
        <select id="citewp_aiso_bot_filter" name="citewp_aiso_bot">
            <option value=""><?php esc_html_e( 'All bots', 'ai-search-optimizer' ); ?></option>
            <?php foreach ( $this->distinct_bots as $bot ) : ?>
                <option value="<?php echo esc_attr( $bot ); ?>" <?php selected( $current_bot, $bot ); ?>>
                    <?php echo esc_html( $bot ); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label class="screen-reader-text" for="citewp_aiso_range_filter">
            <?php esc_html_e( 'Filter by date range', 'ai-search-optimizer' ); ?>
        </label>
        <select id="citewp_aiso_range_filter" name="citewp_aiso_range">
            <option value=""><?php esc_html_e( 'All time', 'ai-search-optimizer' ); ?></option>
            <option value="24h" <?php selected( $current_range, '24h' ); ?>><?php esc_html_e( 'Last 24 hours', 'ai-search-optimizer' ); ?></option>
            <option value="7d"  <?php selected( $current_range, '7d' );  ?>><?php esc_html_e( 'Last 7 days', 'ai-search-optimizer' ); ?></option>
            <option value="30d" <?php selected( $current_range, '30d' ); ?>><?php esc_html_e( 'Last 30 days', 'ai-search-optimizer' ); ?></option>
        </select>

        <?php submit_button( __( 'Filter', 'ai-search-optimizer' ), '', 'filter_action', false ); ?>
    </div>
    <?php
}
```

AFTER (keep bot filter, remove date range select and submit button — filter pills in header handle date range):
```php
protected function extra_tablenav( $which ): void {
    if ( $which !== 'top' ) {
        return;
    }

    $current_bot = $this->validated_bot_filter();

    if ( empty( $this->distinct_bots ) ) {
        return;
    }
    ?>
    <div class="alignleft actions">
        <label class="screen-reader-text" for="citewp_aiso_bot_filter">
            <?php esc_html_e( 'Filter by bot', 'ai-search-optimizer' ); ?>
        </label>
        <select id="citewp_aiso_bot_filter" name="citewp_aiso_bot">
            <option value=""><?php esc_html_e( 'All bots', 'ai-search-optimizer' ); ?></option>
            <?php foreach ( $this->distinct_bots as $bot ) : ?>
                <option value="<?php echo esc_attr( $bot ); ?>" <?php selected( $current_bot, $bot ); ?>>
                    <?php echo esc_html( $bot ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php submit_button( __( 'Filter', 'ai-search-optimizer' ), '', 'filter_action', false ); ?>
    </div>
    <?php
}
```

- [ ] **Step 7: Open browser and verify Crawler Logs page**

Navigate to: `http://citewp-dev.local/wp-admin/admin.php?page=citewp-aiso&citewp_section=crawler-logs`

Checklist:
- Page header strip renders: white bg, border-bottom, title + desc on left
- 4 filter pills render on right: "All time" active (obsidian fill), "Last 24h" / "7 days" / "30 days" inactive
- Clicking a pill filters the table (page reloads with `citewp_aiso_range=24h` etc.)
- Export CSV button still present on right
- 4-col KPI row: Total Crawls / Unique Bots / Pages Crawled / Avg Frequency
- KPI cards have head row (orb + uppercase title) + value + caption
- Table: paper card wrapper (border + border-radius)
- Bot filter dropdown still appears above table in `extra_tablenav` when bots exist
- Date range `<select>` no longer appears above table
- Pro Tip footer at bottom of page body
- No PHP errors in LocalWP `debug.log`
- No JS console errors

- [ ] **Step 8: Commit**

```bash
git add includes/Admin/LogsPage.php includes/Admin/LogsTable.php
git commit -m "feat: Crawler Logs v3 polish — page header strip, filter pills, 4-col KPI row, paper table card, Pro Tip footer"
```

---

## Task 3 — Settings PHP

**Files:**
- Modify: `includes/Settings/Page.php`

### Step-by-step

- [ ] **Step 1: Replace `panel__title-row` + separate tab nav with Page Header Strip containing the tab nav**

BEFORE (lines 139–171 — title-row div, notices, and tabs__nav div):
```php
<div class="citewp-aiso-panel__title-row">
    <div>
        <h2><?php esc_html_e( 'Settings', 'ai-search-optimizer' ); ?></h2>
        <p class="citewp-aiso-panel__subtitle"><?php esc_html_e( 'Configure your AI search optimization preferences.', 'ai-search-optimizer' ); ?></p>
    </div>
</div>

<?php if ( sanitize_key( wp_unslash( $_GET['regenerated'] ?? '' ) ) === '1' ) : // phpcs:ignore ... ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'llms.txt cache cleared. The next request will regenerate from scratch.', 'ai-search-optimizer' ); ?></p></div>
    <?php endif; ?>

    <?php if ( sanitize_key( wp_unslash( $_GET['settings-updated'] ?? '' ) ) !== '' ) : // phpcs:ignore ... ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'ai-search-optimizer' ); ?></p></div>
    <?php endif; ?>

    <div class="citewp-aiso-tabs" id="citewp-aiso-settings-tabs">

        <div class="citewp-aiso-tabs__nav" role="tablist">
            <?php foreach ( $tabs as $slug => $label ) :
                $btn_id   = 'citewp-aiso-tab-btn-' . esc_attr( $slug );
                $panel_id = 'citewp-aiso-tab-' . esc_attr( $slug );
            ?>
            <button
                type="button"
                id="<?php echo esc_attr( $btn_id ); ?>"
                class="citewp-aiso-tabs__btn"
                data-tab="<?php echo esc_attr( $slug ); ?>"
                role="tab"
                aria-controls="<?php echo esc_attr( $panel_id ); ?>"
                aria-selected="false"
            ><?php echo esc_html( $label ); ?></button>
            <?php endforeach; ?>
        </div>

        <form method="post" action="options.php" id="citewp-aiso-settings-form">
```

AFTER:
```php
<div class="citewp-aiso-page-header">
    <div class="citewp-aiso-page-header__left">
        <h1 class="citewp-aiso-page-header__title"><?php esc_html_e( 'Settings', 'ai-search-optimizer' ); ?></h1>
        <p class="citewp-aiso-page-header__desc"><?php esc_html_e( 'Configure your AI search optimization preferences.', 'ai-search-optimizer' ); ?></p>
    </div>
    <div class="citewp-aiso-page-header__right">
        <nav class="citewp-aiso-settings-tabnav" role="tablist">
            <?php foreach ( $tabs as $slug => $label ) :
                $btn_id   = 'citewp-aiso-tab-btn-' . esc_attr( $slug );
                $panel_id = 'citewp-aiso-tab-' . esc_attr( $slug );
            ?>
            <button
                type="button"
                id="<?php echo esc_attr( $btn_id ); ?>"
                class="citewp-aiso-settings-tabnav__item"
                data-tab="<?php echo esc_attr( $slug ); ?>"
                role="tab"
                aria-controls="<?php echo esc_attr( $panel_id ); ?>"
                aria-selected="false"
            ><?php echo esc_html( $label ); ?></button>
            <?php endforeach; ?>
        </nav>
    </div>
</div>

<?php if ( sanitize_key( wp_unslash( $_GET['regenerated'] ?? '' ) ) === '1' ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display flag set by this plugin after safe redirect; no data modification. ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'llms.txt cache cleared. The next request will regenerate from scratch.', 'ai-search-optimizer' ); ?></p></div>
    <?php endif; ?>

    <?php if ( sanitize_key( wp_unslash( $_GET['settings-updated'] ?? '' ) ) !== '' ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Standard WP options-saved flag; no data modification. ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'ai-search-optimizer' ); ?></p></div>
    <?php endif; ?>

    <div class="citewp-aiso-page-body" id="citewp-aiso-settings-tabs">

        <form method="post" action="options.php" id="citewp-aiso-settings-form">
```

- [ ] **Step 2: Replace General tab citewp-aiso-section card with Form Section Card**

BEFORE (lines 177–203 — General tab panel inner content):
```php
<div class="citewp-aiso-section">
    <div class="citewp-aiso-section__header">
        <h2 class="citewp-aiso-section__title"><?php esc_html_e( 'Maintenance', 'ai-search-optimizer' ); ?></h2>
        <p class="citewp-aiso-section__desc"><?php esc_html_e( 'Tools to reset or regenerate plugin data.', 'ai-search-optimizer' ); ?></p>
    </div>
    <div class="citewp-aiso-section__body">
        <div class="citewp-aiso-field citewp-aiso-field--stacked">
            <span class="citewp-aiso-field__label-text"><?php esc_html_e( 'Regenerate llms.txt', 'ai-search-optimizer' ); ?></span>
            <span class="citewp-aiso-field__label-desc"><?php esc_html_e( 'Clears the cache. The next request to /llms.txt rebuilds from scratch.', 'ai-search-optimizer' ); ?></span>
            <button
                type="submit"
                form="citewp-aiso-regenerate-form"
                class="button"
            ><?php esc_html_e( 'Regenerate now', 'ai-search-optimizer' ); ?></button>
        </div>
    </div>
</div>
```

AFTER:
```php
<div class="citewp-aiso-fscard">
    <div class="citewp-aiso-fscard__header"><?php esc_html_e( 'Maintenance', 'ai-search-optimizer' ); ?></div>
    <div class="citewp-aiso-fscard__row">
        <div class="citewp-aiso-fscard__left">
            <div class="citewp-aiso-fscard__orb citewp-aiso-fscard__orb--gray"><?php echo IconLibrary::icon( 'refresh-cw', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary returns trusted SVG markup. ?></div>
            <div>
                <p class="citewp-aiso-fscard__field-title"><?php esc_html_e( 'Regenerate llms.txt', 'ai-search-optimizer' ); ?></p>
                <p class="citewp-aiso-fscard__field-desc"><?php esc_html_e( 'Clears the cache. The next request to /llms.txt rebuilds from scratch.', 'ai-search-optimizer' ); ?></p>
            </div>
        </div>
        <div class="citewp-aiso-fscard__right">
            <button
                type="submit"
                form="citewp-aiso-regenerate-form"
                class="button"
            ><?php esc_html_e( 'Regenerate now', 'ai-search-optimizer' ); ?></button>
        </div>
    </div>
</div>
```

- [ ] **Step 3: Replace Crawler Detection tab section with Form Section Card**

BEFORE (the full `citewp-aiso-section` block inside the crawler-detection tab, lines 213–255):
```php
<div class="citewp-aiso-section">
    <div class="citewp-aiso-section__header">
        <h2 class="citewp-aiso-section__title"><?php esc_html_e( 'Crawler Detection', 'ai-search-optimizer' ); ?></h2>
        <p class="citewp-aiso-section__desc"><?php esc_html_e( 'Controls whether AI crawler visits are logged to your database.', 'ai-search-optimizer' ); ?></p>
    </div>
    <div class="citewp-aiso-section__body">
        <div class="citewp-aiso-field">
            <label class="citewp-aiso-field__label" for="citewp_aiso_enable_crawler_detection">
                <span class="citewp-aiso-field__label-text"><?php esc_html_e( 'Enable detection', 'ai-search-optimizer' ); ?></span>
                <span class="citewp-aiso-field__label-desc"><?php esc_html_e( 'Log AI crawler visits (GPTBot, ClaudeBot, PerplexityBot, and others).', 'ai-search-optimizer' ); ?></span>
            </label>
            <label class="citewp-aiso-toggle">
                <input
                    type="checkbox"
                    id="citewp_aiso_enable_crawler_detection"
                    name="<?php echo esc_attr( self::OPTION_CORE ); ?>[enable_crawler_detection]"
                    value="1"
                    <?php checked( ! empty( $core['enable_crawler_detection'] ) ); ?>
                />
                <span class="citewp-aiso-toggle__track" aria-hidden="true"></span>
            </label>
        </div>
        <div class="citewp-aiso-input-row">
            <label class="citewp-aiso-input-row__label" for="citewp_aiso_log_retention_days">
                <?php esc_html_e( 'Log retention (days)', 'ai-search-optimizer' ); ?>
            </label>
            <div class="citewp-aiso-input-row__field">
                <input
                    type="number"
                    id="citewp_aiso_log_retention_days"
                    name="<?php echo esc_attr( self::OPTION_CORE ); ?>[log_retention_days]"
                    class="small-text citewp-aiso-input--number"
                    value="<?php echo esc_attr( (string) ( $core['log_retention_days'] ?? 7 ) ); ?>"
                    min="1"
                    max="365"
                />
                <span class="description"><?php esc_html_e( 'Free tier: 7 days. Older logs pruned daily.', 'ai-search-optimizer' ); ?></span>
            </div>
        </div>
    </div>
</div>
```

AFTER:
```php
<div class="citewp-aiso-fscard">
    <div class="citewp-aiso-fscard__header"><?php esc_html_e( 'AI Crawler Detection', 'ai-search-optimizer' ); ?></div>

    <div class="citewp-aiso-fscard__row">
        <div class="citewp-aiso-fscard__left">
            <div class="citewp-aiso-fscard__orb citewp-aiso-fscard__orb--orange"><?php echo IconLibrary::icon( 'bot', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary returns trusted SVG markup. ?></div>
            <div>
                <p class="citewp-aiso-fscard__field-title"><?php esc_html_e( 'Enable detection', 'ai-search-optimizer' ); ?></p>
                <p class="citewp-aiso-fscard__field-desc"><?php esc_html_e( 'Log AI crawler visits (GPTBot, ClaudeBot, PerplexityBot, and others).', 'ai-search-optimizer' ); ?></p>
            </div>
        </div>
        <div class="citewp-aiso-fscard__right">
            <label class="citewp-aiso-toggle">
                <input
                    type="checkbox"
                    id="citewp_aiso_enable_crawler_detection"
                    name="<?php echo esc_attr( self::OPTION_CORE ); ?>[enable_crawler_detection]"
                    value="1"
                    <?php checked( ! empty( $core['enable_crawler_detection'] ) ); ?>
                />
                <span class="citewp-aiso-toggle__track" aria-hidden="true"></span>
            </label>
        </div>
    </div>

    <div class="citewp-aiso-fscard__row">
        <div class="citewp-aiso-fscard__left">
            <div class="citewp-aiso-fscard__orb citewp-aiso-fscard__orb--blue"><?php echo IconLibrary::icon( 'calendar', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary returns trusted SVG markup. ?></div>
            <div>
                <p class="citewp-aiso-fscard__field-title"><?php esc_html_e( 'Log retention (days)', 'ai-search-optimizer' ); ?></p>
                <p class="citewp-aiso-fscard__field-desc"><?php esc_html_e( 'Free tier: 7 days. Older logs pruned daily.', 'ai-search-optimizer' ); ?></p>
            </div>
        </div>
        <div class="citewp-aiso-fscard__right">
            <input
                type="number"
                id="citewp_aiso_log_retention_days"
                name="<?php echo esc_attr( self::OPTION_CORE ); ?>[log_retention_days]"
                class="small-text citewp-aiso-input--number"
                value="<?php echo esc_attr( (string) ( $core['log_retention_days'] ?? 7 ) ); ?>"
                min="1"
                max="365"
            />
        </div>
    </div>
</div>
```

- [ ] **Step 4: Replace llms.txt tab section with Form Section Card**

BEFORE (the full `citewp-aiso-section` inside llms-txt tab, lines 265–349):
```php
<div class="citewp-aiso-section">
    <div class="citewp-aiso-section__header">
        <h2 class="citewp-aiso-section__title"><?php esc_html_e( 'llms.txt Generation', 'ai-search-optimizer' ); ?></h2>
        <p class="citewp-aiso-section__desc">
            <?php esc_html_e( 'Your llms.txt:', 'ai-search-optimizer' ); ?>
            <a href="<?php echo esc_url( $llms_short ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( $llms_short ); ?></code></a>
            &nbsp;|&nbsp;
            <a href="<?php echo esc_url( $llms_full ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( $llms_full ); ?></code></a>
        </p>
    </div>
    <div class="citewp-aiso-section__body">
        [... all fields ...]
    </div>
</div>
```

AFTER:
```php
<p class="citewp-aiso-fscard__field-desc" style="margin-bottom: var(--sp-3);">
    <?php esc_html_e( 'Your llms.txt:', 'ai-search-optimizer' ); ?>
    <a href="<?php echo esc_url( $llms_short ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( $llms_short ); ?></code></a>
    &nbsp;|&nbsp;
    <a href="<?php echo esc_url( $llms_full ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( $llms_full ); ?></code></a>
</p>

<div class="citewp-aiso-fscard">
    <div class="citewp-aiso-fscard__header"><?php esc_html_e( 'llms.txt Generation', 'ai-search-optimizer' ); ?></div>

    <div class="citewp-aiso-fscard__row">
        <div class="citewp-aiso-fscard__left">
            <div class="citewp-aiso-fscard__orb citewp-aiso-fscard__orb--teal"><?php echo IconLibrary::icon( 'llms-txt', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary returns trusted SVG markup. ?></div>
            <div>
                <p class="citewp-aiso-fscard__field-title"><?php esc_html_e( 'Enable llms.txt', 'ai-search-optimizer' ); ?></p>
                <p class="citewp-aiso-fscard__field-desc"><?php esc_html_e( 'Serve dynamic llms.txt to AI engines.', 'ai-search-optimizer' ); ?></p>
            </div>
        </div>
        <div class="citewp-aiso-fscard__right">
            <label class="citewp-aiso-toggle">
                <input
                    type="checkbox"
                    id="citewp_aiso_llms_enabled"
                    name="<?php echo esc_attr( self::OPTION_LLMS ); ?>[enabled]"
                    value="1"
                    <?php checked( ! empty( $llms['enabled'] ) ); ?>
                />
                <span class="citewp-aiso-toggle__track" aria-hidden="true"></span>
            </label>
        </div>
    </div>

    <div class="citewp-aiso-fscard__row">
        <div class="citewp-aiso-fscard__left">
            <div class="citewp-aiso-fscard__orb citewp-aiso-fscard__orb--blue"><?php echo IconLibrary::icon( 'settings', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary returns trusted SVG markup. ?></div>
            <div>
                <p class="citewp-aiso-fscard__field-title"><?php esc_html_e( 'Minimum word count', 'ai-search-optimizer' ); ?></p>
                <p class="citewp-aiso-fscard__field-desc"><?php esc_html_e( 'Posts shorter than this are skipped. Pages always included.', 'ai-search-optimizer' ); ?></p>
            </div>
        </div>
        <div class="citewp-aiso-fscard__right">
            <input
                type="number"
                id="citewp_aiso_min_word_count"
                name="<?php echo esc_attr( self::OPTION_LLMS ); ?>[min_word_count]"
                class="small-text citewp-aiso-input--number"
                value="<?php echo esc_attr( (string) ( $llms['min_word_count'] ?? 500 ) ); ?>"
                min="0"
                max="10000"
                step="50"
            />
        </div>
    </div>

    <div class="citewp-aiso-fscard__row">
        <div class="citewp-aiso-fscard__left">
            <div class="citewp-aiso-fscard__orb citewp-aiso-fscard__orb--blue"><?php echo IconLibrary::icon( 'calendar', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary returns trusted SVG markup. ?></div>
            <div>
                <p class="citewp-aiso-fscard__field-title"><?php esc_html_e( 'Include posts from last (days)', 'ai-search-optimizer' ); ?></p>
                <p class="citewp-aiso-fscard__field-desc"><?php esc_html_e( 'Posts published before this window are excluded from llms.txt.', 'ai-search-optimizer' ); ?></p>
            </div>
        </div>
        <div class="citewp-aiso-fscard__right">
            <input
                type="number"
                id="citewp_aiso_recent_days"
                name="<?php echo esc_attr( self::OPTION_LLMS ); ?>[recent_days]"
                class="small-text citewp-aiso-input--number"
                value="<?php echo esc_attr( (string) ( $llms['recent_days'] ?? 90 ) ); ?>"
                min="1"
                max="3650"
            />
        </div>
    </div>

    <?php if ( ! empty( $public_types ) ) : ?>
    <div class="citewp-aiso-fscard__row">
        <div class="citewp-aiso-fscard__left">
            <div class="citewp-aiso-fscard__orb citewp-aiso-fscard__orb--gray"><?php echo IconLibrary::icon( 'settings', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary returns trusted SVG markup. ?></div>
            <div>
                <p class="citewp-aiso-fscard__field-title"><?php esc_html_e( 'Include custom post types', 'ai-search-optimizer' ); ?></p>
                <p class="citewp-aiso-fscard__field-desc"><?php esc_html_e( 'Selected types will appear alongside posts and pages in llms.txt.', 'ai-search-optimizer' ); ?></p>
            </div>
        </div>
        <div class="citewp-aiso-fscard__right">
            <?php foreach ( $public_types as $type ) : ?>
                <label class="citewp-aiso-cpt-label">
                    <input
                        type="checkbox"
                        name="<?php echo esc_attr( self::OPTION_LLMS ); ?>[extra_post_types][]"
                        value="<?php echo esc_attr( $type->name ); ?>"
                        <?php checked( in_array( $type->name, (array) ( $llms['extra_post_types'] ?? [] ), true ) ); ?>
                    />
                    <?php echo esc_html( $type->labels->name ); ?>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>
```

- [ ] **Step 5: Replace `submit_button()` with primary action button, update save-bar, add Pro Tip footer, close outer div**

BEFORE (lines 368–380 — save bar, regenerate form, closing divs):
```php
<div class="citewp-aiso-save-bar">
    <?php submit_button( null, 'primary', 'submit', false ); ?>
</div>

</form>

<!-- Regenerate form (outside main form to avoid nesting) -->
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="citewp-aiso-regenerate-form">
    <input type="hidden" name="action" value="citewp_aiso_regenerate_llms" />
    <?php wp_nonce_field( 'citewp_aiso_regenerate_llms' ); ?>
</form>

</div><!-- .citewp-aiso-tabs -->
```

AFTER:
```php
<div class="citewp-aiso-save-bar">
    <button type="submit" name="submit" class="citewp-aiso-btn--primary-action">
        <?php esc_html_e( 'Save Changes', 'ai-search-optimizer' ); ?>
    </button>
</div>

</form>

<!-- Regenerate form (outside main form to avoid nesting) -->
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="citewp-aiso-regenerate-form">
    <input type="hidden" name="action" value="citewp_aiso_regenerate_llms" />
    <?php wp_nonce_field( 'citewp_aiso_regenerate_llms' ); ?>
</form>

<div class="citewp-aiso-protip">
    <div class="citewp-aiso-protip__left">
        <div class="citewp-aiso-protip__orb"><?php echo IconLibrary::icon( 'sparkles', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
        <div class="citewp-aiso-protip__content">
            <p class="citewp-aiso-protip__heading"><?php esc_html_e( 'Pro Tip', 'ai-search-optimizer' ); ?></p>
            <p class="citewp-aiso-protip__body"><?php esc_html_e( 'Unlock advanced llms.txt customization, custom bot rules, and white-label reports with CiteWP Pro.', 'ai-search-optimizer' ); ?></p>
        </div>
    </div>
</div>

</div><!-- .citewp-aiso-page-body -->
```

- [ ] **Step 6: Update the inline JavaScript selectors**

BEFORE (the `<script>` block at lines 382–415):
```javascript
var tabs   = document.querySelectorAll( '.citewp-aiso-tabs__btn' );
var panels = document.querySelectorAll( '.citewp-aiso-tabs__panel' );
```
And inside `activate()`:
```javascript
btn.classList.toggle( 'citewp-aiso-tabs__btn--active', active );
```
And:
```javascript
panel.classList.toggle( 'citewp-aiso-tabs__panel--active', active );
```

AFTER (three targeted replacements in the script block):
```javascript
var tabs   = document.querySelectorAll( '.citewp-aiso-settings-tabnav__item' );
var panels = document.querySelectorAll( '.citewp-aiso-settings-tabpanel' );
```
And:
```javascript
btn.classList.toggle( 'citewp-aiso-settings-tabnav__item--active', active );
```
And:
```javascript
panel.classList.toggle( 'citewp-aiso-settings-tabpanel--active', active );
```

Also update the tab panel `display` toggling in CSS — add to Section 24:

In the CSS you just wrote (Section 24), also add:
```css
.citewp-aiso-settings-tabpanel { display: none; }
.citewp-aiso-settings-tabpanel--active { display: block; }
```

And update each tab panel's class in PHP from `citewp-aiso-tabs__panel` to `citewp-aiso-settings-tabpanel`:
- `id="citewp-aiso-tab-general"` div: `class="citewp-aiso-settings-tabpanel"`
- `id="citewp-aiso-tab-crawler-detection"` div: `class="citewp-aiso-settings-tabpanel"`
- `id="citewp-aiso-tab-llms-txt"` div: `class="citewp-aiso-settings-tabpanel"`
- Extra tabs loop: `class="citewp-aiso-settings-tabpanel"`

> **Note:** Panel IDs `citewp-aiso-tab-{slug}` do NOT change — the JS checks `panel.id === 'citewp-aiso-tab-' + slug` which still matches.

- [ ] **Step 7: Add `use` statement for IconLibrary in Settings/Page.php**

BEFORE (line 13, existing use statements):
```php
use CiteWP\Aiso\Admin\Menu;
use CiteWP\Aiso\Llms\Cache;
```

AFTER:
```php
use CiteWP\Aiso\Admin\IconLibrary;
use CiteWP\Aiso\Admin\Menu;
use CiteWP\Aiso\Llms\Cache;
```

- [ ] **Step 8: Open browser and verify Settings page**

Navigate to: `http://citewp-dev.local/wp-admin/admin.php?page=citewp-aiso&citewp_section=settings`

Checklist:
- Page header strip: white bg, border-bottom, "Settings" title + desc on left
- Tab nav on right of header strip: "General" / "Crawler Detection" / "llms.txt" as text-underline tabs
- Active tab has blue (`#2563eb`) underline
- Clicking a tab switches panels (JS localStorage still works — last tab remembered on reload)
- Form Section Cards render for each tab: paper bg, section header, two-column rows with icon orbs
- General tab: Regenerate row with `refresh-cw` gray orb
- Crawler Detection tab: Enable detection row (bot orange orb + toggle), Log retention row (calendar blue orb + input)
- llms.txt tab: Enable toggle (llms-txt teal orb), word count (settings blue orb), recent days (calendar blue orb), CPT checkboxes if any CPTs exist
- Save Changes button: blue (`#2563eb`) fill, white text, Inter 700
- Saving settings works (form posts to `options.php`, redirects back with "Settings saved." notice)
- Regenerate button works (submits regenerate form, redirects with "llms.txt cache cleared." notice)
- Pro Tip footer at bottom
- No PHP errors in LocalWP `debug.log`
- No JS console errors

- [ ] **Step 9: Commit**

```bash
git add includes/Settings/Page.php
git commit -m "feat: Settings v3 polish — page header strip with tab nav, Form Section Cards, blue Save button, Pro Tip footer"
```

---

## Subagent Prompt Templates

Use these if dispatching subagents (superpowers:subagent-driven-development). Each prompt is fully self-contained.

### Subagent A — CSS Sections 20–25

```
You are implementing Task 1 from the Session 17 CiteWP v3 CSS plan.

CONTEXT:
- Plugin path: includes/Admin/LogsPage.php (not touching), admin/css/citewp-aiso-admin.css (appending)
- CSS currently ends around line 1066-1100 with Section 19 (Score gauge)
- All new classes must be appended as new sections — do NOT modify existing sections

TASK:
Append the following exactly as-is to the end of admin/css/citewp-aiso-admin.css.
[paste the full CSS block from Task 1 Step 2 above]

DONE WHEN:
- File saved
- No existing class names appear before your appended block (verify with grep)
- git add + commit with message: "feat: add v3 page header strip, filter pills, KPI v3, FSCard, settings tab nav CSS (Sections 20-25)"
```

### Subagent B — Crawler Logs PHP (run after Subagent A is committed)

```
You are implementing Task 2 from the Session 17 CiteWP v3 plan.

CONTEXT:
- Plugin path: includes/Admin/LogsPage.php (232 lines), includes/Admin/LogsTable.php (240 lines)
- CSS Task 1 is already committed — new classes are available: .citewp-aiso-page-header, .citewp-aiso-filter-pill, .citewp-aiso-kpi-row--4col, .citewp-aiso-kpi-card__head, .citewp-aiso-kpi-card__caption, .citewp-aiso-logs-table-card, .citewp-aiso-protip (already existed)
- Available icons in IconLibrary: search, bot, eye, calendar, sparkles (verified from IconLibrary.php)
- DB table: wp_citewp_aiso_crawler_logs with columns: id, detected_at, bot_name, bot_vendor, request_uri, ip_address, user_agent

CHANGES (apply in order, with Read→Edit for each):

1. LogsPage.php queries: replace $total/$count_24h/$count_7d/$count_30d block with $total/$unique_bots/$pages_crawled/$count_30d + $avg_freq computation [exact code in plan Task 2 Step 1]
2. LogsPage.php: add $base_url and $range_options vars before $export_args [exact code in plan Task 2 Step 2]
3. LogsPage.php: replace citewp-aiso-panel__title-row HTML with citewp-aiso-page-header [exact code in plan Task 2 Step 3]
4. LogsPage.php: replace 3-col KPI row with 4-col v3 KPI row [exact code in plan Task 2 Step 4]
5. LogsPage.php: add citewp-aiso-logs-table-card wrapper + Pro Tip footer [exact code in plan Task 2 Step 5]
6. LogsTable.php: remove date range select from extra_tablenav(), keep bot select [exact code in plan Task 2 Step 6]

DONE WHEN:
- Browser test passes all items in plan Task 2 Step 7
- No errors in LocalWP debug.log
- git commit with message: "feat: Crawler Logs v3 polish — page header strip, filter pills, 4-col KPI row, paper table card, Pro Tip footer"
```

### Subagent C — Settings PHP (run after Subagent B is committed)

```
You are implementing Task 3 from the Session 17 CiteWP v3 plan.

CONTEXT:
- Plugin path: includes/Settings/Page.php (419 lines)
- CSS Task 1 is committed — new classes available: .citewp-aiso-page-header, .citewp-aiso-settings-tabnav, .citewp-aiso-settings-tabnav__item, .citewp-aiso-fscard (and sub-elements), .citewp-aiso-btn--primary-action, .citewp-aiso-protip
- Available icons: refresh-cw, bot, calendar, llms-txt, settings, sparkles (from IconLibrary.php)
- localStorage key 'citewp_aiso_settings_tab' must be preserved exactly
- Panel IDs (citewp-aiso-tab-{slug}) must NOT change — only classes change

CHANGES (apply in order, with Read→Edit for each):

1. Add `use CiteWP\Aiso\Admin\IconLibrary;` import [plan Task 3 Step 7]
2. Replace panel__title-row + separate tab nav with page header strip containing tabnav [plan Task 3 Step 1]
3. Replace General tab citewp-aiso-section with Form Section Card [plan Task 3 Step 2]
4. Replace Crawler Detection tab section with Form Section Card [plan Task 3 Step 3]
5. Replace llms.txt tab section with Form Section Card [plan Task 3 Step 4]
6. Replace submit_button() with primary action button + add Pro Tip footer [plan Task 3 Step 5]
7. Update inline JS selectors (3 replacements) + update all 4 panel class names [plan Task 3 Step 6]
8. Add tabpanel display CSS to Section 24 in admin/css/citewp-aiso-admin.css [plan Task 3 Step 6 note]

DONE WHEN:
- Browser test passes all items in plan Task 3 Step 8
- Tab switching works, localStorage remembered across reload
- Form saves correctly (test by changing a value, saving, reloading)
- No errors in LocalWP debug.log
- git commit with message: "feat: Settings v3 polish — page header strip with tab nav, Form Section Cards, blue Save button, Pro Tip footer"
```

---

## Spec Coverage Self-Review

| Requirement | Plan task |
|---|---|
| Page Header Strip on Crawler Logs (title, desc, filter pills, Export CSV) | Task 2 Steps 2–3 |
| 4-KPI row: Total Crawls / Unique Bots / Pages Crawled / Avg Frequency | Task 2 Steps 1, 4 |
| KPI card v3: head row + value + caption | Task 1 (CSS) + Task 2 Step 4 |
| Avg Frequency = count_30d / 30 | Task 2 Step 1 |
| Filter pills: Obsidian active, paper inactive, `<a>` links | Task 1 (CSS) + Task 2 Steps 2–3 |
| Suppress extra_tablenav() date range select | Task 2 Step 6 |
| Bot filter dropdown kept in extra_tablenav() | Task 2 Step 6 |
| Table in paper card wrapper | Task 1 (CSS) + Task 2 Step 5 |
| Pro Tip footer on Crawler Logs | Task 2 Step 5 |
| Page Header Strip on Settings (title, desc, tab nav on right) | Task 3 Steps 1, 7 |
| Settings tab nav: text-with-underline, active = `#2563eb` | Task 1 (CSS) + Task 3 Step 1 |
| localStorage `citewp_aiso_settings_tab` preserved | Task 3 Step 6 |
| Form Section Cards with two-column layout + icon orbs | Task 1 (CSS) + Task 3 Steps 2–4 |
| Blue Save Changes button (`#2563eb` fill) | Task 1 (CSS) + Task 3 Step 5 |
| Pro Tip footer on Settings | Task 3 Step 5 |
| X15: `citewp_aiso/settings/tabs` filter preserved | Not touched — filter is in the PHP logic, not the markup |
| Engine.php not touched | Not in any task |
| Dashboard page not touched | Not in any task |
| `declare(strict_types=1)` in all PHP | Existing, not changed |
| WP escaping on all output | All echo calls use `esc_html()` / `esc_url()` / `esc_attr()` |
| `current_user_can('manage_options')` gate | Already exists at top of both render() methods |
