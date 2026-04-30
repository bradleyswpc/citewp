# Admin UI Polish — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Full WP Rocket IA polish pass on the three CiteWP admin pages (Dashboard, Settings, Crawler Logs) plus X15 extensibility filter-hook stubs on the WP Dashboard widget and post meta boxes.

**Architecture:** New `PageHeader` component renders the shared top nav across all three CiteWP admin pages, calling `apply_filters('citewp_aiso/admin/nav', $items)` for X15 extensibility. New `DashboardData` service extracts the four DB query methods from `DashboardWidget` so both the WP Dashboard widget and the new admin Dashboard page can share them. Settings page is rebuilt with a tabbed inner layout driven by `apply_filters('citewp_aiso/settings/tabs', $default_tabs)`. All admin-page styling moves to a single CSS file loaded only on CiteWP screens. WP Dashboard widget and post meta boxes receive filter-hook stubs only — no structural changes.

**Tech Stack:** PHP 8.0+, WordPress 6.5+, CSS3 custom properties, vanilla JS (no build step needed for the tab switcher), SVG for the score gauge, PowerShell `Invoke-WebRequest` to download font files from jsDelivr.

---

## File Structure

**Create:**
- `admin/css/citewp-aiso-admin.css` — @font-face declarations, design tokens, all UI component styles (tab nav, cards, toggles, gauge, badges, empty states, stats banner)
- `admin/fonts/plus-jakarta-sans-800.woff2` — self-hosted font, downloaded from jsDelivr
- `admin/fonts/fraunces-800-italic.woff2` — self-hosted font, downloaded from jsDelivr
- `admin/fonts/jetbrains-mono-400.woff2` — self-hosted font, downloaded from jsDelivr
- `includes/Admin/PageHeader.php` — `CiteWP\Aiso\Admin\PageHeader`, static `render_nav(string $current_page): void`, calls `apply_filters('citewp_aiso/admin/nav', $defaults)` (X15 hook #1)
- `includes/Admin/DashboardData.php` — `CiteWP\Aiso\Admin\DashboardData`, exposes four public methods extracted verbatim from `DashboardWidget`

**Modify:**
- `includes/Admin/Menu.php` — add `enqueue_assets()` on `admin_enqueue_scripts`; replace stub `render_dashboard()` with full card-grid page that calls `PageHeader::render_nav()` and `apply_filters('citewp_aiso/dashboard/cards', [])` (X15 hook #4)
- `includes/Settings/Page.php` — rebuild `render()` with tabbed layout, card sections, toggles, `apply_filters('citewp_aiso/settings/tabs', $default_tabs)` (X15 hook #2); remove `inline_styles()`
- `includes/Admin/LogsPage.php` — add `PageHeader::render_nav(Menu::SLUG_LOGS)` in `render()`; update HTML class names to match CSS file; remove `inline_styles()`
- `includes/Admin/DashboardWidget.php` — replace the four private data methods with delegation to `DashboardData`; add `apply_filters('citewp_aiso/dashboard/cards', [])` stub in `render()`
- `includes/Admin/ScoreMetaBox.php` — add `apply_filters('citewp_aiso/metabox/tabs', [])` stub at top of `render()` (X15 hook #3)
- `includes/Admin/SchemaMetaBox.php` — add `apply_filters('citewp_aiso/metabox/tabs', [])` stub at top of `render()`

---

## Dispatch Order

```
Phase 1 (parallel): Task 1 + Task 2
Phase 2 (parallel): Task 3 + Task 4
Phase 3 (parallel): Task 5 + Task 6 + Task 7
Phase 4 (parallel): Task 8 + Task 9
Phase 5 (sequential): Task 10
```

---

## X15 Extensibility Hooks (required by session protocol)

| Filter | Registered in | Purpose |
|--------|--------------|---------|
| `citewp_aiso/admin/nav` | `PageHeader::render_nav()` | Add/remove/reorder top nav items |
| `citewp_aiso/settings/tabs` | `Settings\Page::render()` | Add/remove/reorder settings inner tabs |
| `citewp_aiso/metabox/tabs` | `ScoreMetaBox::render()`, `SchemaMetaBox::render()` | Future Pro tab registration |
| `citewp_aiso/dashboard/cards` | `Menu::render_dashboard()` | Add extra dashboard cards (Pro/extensions) |

---

## Task 1: Create admin/css/citewp-aiso-admin.css

**Files:**
- Create: `admin/css/citewp-aiso-admin.css`

- [ ] **Step 1: Create the directory**

```bash
mkdir -p "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer\admin\css"
```

- [ ] **Step 2: Write the CSS file**

Use the Write tool to create `admin/css/citewp-aiso-admin.css` with the following content:

```css
/* ==========================================================================
   CiteWP AI Search Optimizer — Admin UI
   Loaded only on toplevel_page_citewp and citewp_page_* screens.
   ========================================================================== */

/* @font-face — self-hosted from admin/fonts/ -------------------------------- */

@font-face {
    font-family: 'Plus Jakarta Sans';
    src: url('../fonts/plus-jakarta-sans-800.woff2') format('woff2');
    font-weight: 800;
    font-style: normal;
    font-display: swap;
}

@font-face {
    font-family: 'JetBrains Mono';
    src: url('../fonts/jetbrains-mono-400.woff2') format('woff2');
    font-weight: 400;
    font-style: normal;
    font-display: swap;
}

/* Design Tokens ------------------------------------------------------------- */

:root {
    --citewp-citrine:      #E8D400;
    --citewp-citrine-text: #8A7800;
    --citewp-obsidian:     #0C0C0D;
    --citewp-space-1:      4px;
    --citewp-space-2:      8px;
    --citewp-space-3:      16px;
    --citewp-space-4:      24px;
    --citewp-space-5:      32px;
    --citewp-font-xl:      20px;
    --citewp-font-base:    14px;
    --citewp-font-sm:      13px;
    --citewp-font-xs:      12px;
}

/* Page Header + Top Nav ----------------------------------------------------- */

.citewp-aiso-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--citewp-space-3) 0 0;
    margin-bottom: 0;
    border-bottom: 1px solid #c3c4c7;
}

.citewp-aiso-header__wordmark {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: var(--citewp-font-xl);
    font-weight: 800;
    color: var(--citewp-obsidian);
    letter-spacing: -0.5px;
    line-height: 1;
}

.citewp-aiso-header__nav {
    display: flex;
    align-items: stretch;
    gap: 0;
}

.citewp-aiso-nav__item {
    display: inline-flex;
    align-items: center;
    padding: var(--citewp-space-2) var(--citewp-space-3);
    font-size: var(--citewp-font-sm);
    font-weight: 600;
    color: #50575e;
    text-decoration: none;
    border-bottom: 3px solid transparent;
    margin-bottom: -1px;
    transition: color 0.15s, border-color 0.15s;
}

.citewp-aiso-nav__item:hover,
.citewp-aiso-nav__item:focus {
    color: var(--citewp-obsidian);
    text-decoration: none;
}

.citewp-aiso-nav__item--active {
    color: var(--citewp-obsidian);
    border-bottom-color: var(--citewp-citrine);
}

/* Page Body ----------------------------------------------------------------- */

.citewp-aiso-page-body {
    margin-top: var(--citewp-space-4);
}

.citewp-aiso-page-title {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: var(--citewp-font-xl);
    font-weight: 800;
    color: var(--citewp-obsidian);
    margin: var(--citewp-space-4) 0 var(--citewp-space-3);
    line-height: 1.2;
}

/* Dashboard Card Grid ------------------------------------------------------- */

.citewp-aiso-card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: var(--citewp-space-3);
    margin-bottom: var(--citewp-space-4);
}

.citewp-aiso-card-grid--3col {
    grid-template-columns: repeat(3, 1fr);
}

.citewp-aiso-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: var(--citewp-space-4);
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.citewp-aiso-card__title {
    font-size: var(--citewp-font-xs);
    font-weight: 600;
    color: #50575e;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin: 0 0 var(--citewp-space-1);
}

.citewp-aiso-card__value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 32px;
    font-weight: 400;
    color: var(--citewp-obsidian);
    line-height: 1.1;
    margin: var(--citewp-space-1) 0;
}

.citewp-aiso-card__desc {
    font-size: var(--citewp-font-xs);
    color: #787c82;
    margin: 0;
}

.citewp-aiso-card__link {
    font-size: var(--citewp-font-xs);
    color: #2271b1;
    text-decoration: none;
}

.citewp-aiso-card__link:hover {
    text-decoration: underline;
}

/* Dashboard — Needs Attention card ----------------------------------------- */

.citewp-aiso-needs-attention__list {
    margin: 0;
    padding: 0;
    list-style: none;
}

.citewp-aiso-needs-attention__item {
    display: flex;
    align-items: center;
    gap: var(--citewp-space-2);
    padding: 4px 0;
    border-bottom: 1px solid #f0f0f1;
}

.citewp-aiso-needs-attention__item:last-child {
    border-bottom: none;
}

.citewp-aiso-needs-attention__title {
    font-size: var(--citewp-font-xs);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 140px;
    color: #2271b1;
    text-decoration: none;
}

.citewp-aiso-needs-attention__title:hover {
    text-decoration: underline;
}

.citewp-aiso-needs-attention__nodata {
    font-size: var(--citewp-font-xs);
    color: #787c82;
    margin: var(--citewp-space-2) 0 0;
}

/* Dashboard — Quick actions ------------------------------------------------- */

.citewp-aiso-quick-actions {
    display: flex;
    align-items: center;
    gap: var(--citewp-space-2);
    flex-wrap: wrap;
    margin-top: var(--citewp-space-3);
}

/* Settings Inner Tabs ------------------------------------------------------- */

.citewp-aiso-tabs {
    margin-top: var(--citewp-space-4);
}

.citewp-aiso-tabs__nav {
    display: flex;
    border-bottom: 1px solid #c3c4c7;
    margin-bottom: var(--citewp-space-4);
    gap: 0;
    flex-wrap: wrap;
}

.citewp-aiso-tabs__btn {
    padding: var(--citewp-space-2) var(--citewp-space-3);
    font-size: var(--citewp-font-sm);
    font-weight: 400;
    color: #50575e;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    margin-bottom: -1px;
    cursor: pointer;
    transition: color 0.15s, border-color 0.15s;
    line-height: 1.5;
}

.citewp-aiso-tabs__btn:hover {
    color: var(--citewp-obsidian);
}

.citewp-aiso-tabs__btn--active {
    font-weight: 600;
    color: var(--citewp-obsidian);
    border-bottom-color: var(--citewp-citrine);
}

.citewp-aiso-tabs__panel {
    display: none;
}

.citewp-aiso-tabs__panel--active {
    display: block;
}

/* Settings Section Cards ---------------------------------------------------- */

.citewp-aiso-section {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    margin-bottom: var(--citewp-space-3);
}

.citewp-aiso-section__header {
    padding: var(--citewp-space-3) var(--citewp-space-4);
    border-bottom: 1px solid #f0f0f1;
}

.citewp-aiso-section__title {
    font-size: var(--citewp-font-base);
    font-weight: 600;
    color: var(--citewp-obsidian);
    margin: 0;
}

.citewp-aiso-section__desc {
    font-size: var(--citewp-font-sm);
    color: #787c82;
    margin: var(--citewp-space-1) 0 0;
}

.citewp-aiso-section__body {
    padding: var(--citewp-space-3) var(--citewp-space-4);
}

/* Field Row (toggle + label) ------------------------------------------------ */

.citewp-aiso-field {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--citewp-space-4);
    padding: var(--citewp-space-2) 0;
    border-bottom: 1px solid #f0f0f1;
}

.citewp-aiso-field:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.citewp-aiso-field__label {
    flex: 1;
}

.citewp-aiso-field__label-text {
    font-size: var(--citewp-font-sm);
    font-weight: 600;
    color: var(--citewp-obsidian);
    display: block;
}

.citewp-aiso-field__label-desc {
    font-size: var(--citewp-font-xs);
    color: #787c82;
    display: block;
    margin-top: 2px;
}

/* Toggle Switch ------------------------------------------------------------- */

.citewp-aiso-toggle {
    position: relative;
    display: inline-flex;
    align-items: center;
    flex-shrink: 0;
}

.citewp-aiso-toggle input[type="checkbox"] {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
}

.citewp-aiso-toggle__track {
    width: 40px;
    height: 22px;
    background: #c3c4c7;
    border-radius: 11px;
    transition: background 0.2s;
    cursor: pointer;
    position: relative;
    display: block;
}

.citewp-aiso-toggle__track::after {
    content: '';
    position: absolute;
    top: 3px;
    left: 3px;
    width: 16px;
    height: 16px;
    background: #fff;
    border-radius: 50%;
    transition: transform 0.2s;
    box-shadow: 0 1px 2px rgba(0,0,0,.2);
}

.citewp-aiso-toggle input[type="checkbox"]:checked + .citewp-aiso-toggle__track {
    background: var(--citewp-citrine);
}

.citewp-aiso-toggle input[type="checkbox"]:checked + .citewp-aiso-toggle__track::after {
    transform: translateX(18px);
}

.citewp-aiso-toggle input[type="checkbox"]:focus-visible + .citewp-aiso-toggle__track {
    outline: 2px solid var(--wp-admin-theme-color, #2271b1);
    outline-offset: 2px;
}

/* Number Input -------------------------------------------------------------- */

.citewp-aiso-input-row {
    display: flex;
    align-items: center;
    gap: var(--citewp-space-2);
    padding: var(--citewp-space-2) 0;
    border-bottom: 1px solid #f0f0f1;
}

.citewp-aiso-input-row:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.citewp-aiso-input-row__label {
    font-size: var(--citewp-font-sm);
    font-weight: 600;
    color: var(--citewp-obsidian);
    flex: 1;
}

.citewp-aiso-input-row__field {
    display: flex;
    align-items: center;
    gap: var(--citewp-space-2);
}

.citewp-aiso-input--number {
    width: 80px;
    text-align: right;
}

/* Score Gauge (SVG semicircle) ---------------------------------------------- */

.citewp-aiso-gauge {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: var(--citewp-space-3) 0;
}

.citewp-aiso-gauge svg {
    overflow: visible;
}

.citewp-aiso-gauge__track {
    fill: none;
    stroke: #e5e7eb;
    stroke-width: 10;
    stroke-linecap: round;
}

.citewp-aiso-gauge__fill {
    fill: none;
    stroke-width: 10;
    stroke-linecap: round;
    transition: stroke-dashoffset 0.6s cubic-bezier(.4,0,.2,1);
}

.citewp-aiso-gauge__fill--green  { stroke: #46b450; }
.citewp-aiso-gauge__fill--yellow { stroke: var(--citewp-citrine); }
.citewp-aiso-gauge__fill--orange { stroke: #f56e28; }
.citewp-aiso-gauge__fill--red    { stroke: #dc3232; }

.citewp-aiso-gauge__score-text {
    font-family: 'JetBrains Mono', monospace;
    font-size: 32px;
    fill: var(--citewp-obsidian);
    text-anchor: middle;
    dominant-baseline: middle;
}

.citewp-aiso-gauge__label {
    font-size: var(--citewp-font-xs);
    color: #787c82;
    text-align: center;
    margin-top: var(--citewp-space-1);
}

/* Score Badges -------------------------------------------------------------- */

.citewp-aiso-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: var(--citewp-font-xs);
    font-weight: 600;
    line-height: 1.6;
    white-space: nowrap;
}

.citewp-aiso-badge--green  { background: #d7f0d7; color: #1a6b1a; }
.citewp-aiso-badge--yellow { background: #fef9c3; color: var(--citewp-citrine-text); }
.citewp-aiso-badge--orange { background: #fde8d3; color: #9a3412; }
.citewp-aiso-badge--red    { background: #fce8e8; color: #9b1c1c; }

/* Stats Banner (Logs page) -------------------------------------------------- */

.citewp-aiso-stats-banner {
    display: grid;
    grid-template-columns: repeat(3, auto) 1fr;
    align-items: center;
    gap: var(--citewp-space-3);
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: var(--citewp-space-3) var(--citewp-space-4);
    margin: var(--citewp-space-4) 0 var(--citewp-space-3);
    flex-wrap: wrap;
}

.citewp-aiso-stat {
    min-width: 100px;
}

.citewp-aiso-stat__label {
    display: block;
    font-size: var(--citewp-font-xs);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #787c82;
    margin-bottom: 2px;
}

.citewp-aiso-stat__value {
    display: block;
    font-family: 'JetBrains Mono', monospace;
    font-size: 22px;
    font-weight: 400;
    color: var(--citewp-obsidian);
    line-height: 1;
}

.citewp-aiso-stats-banner__export {
    text-align: right;
}

/* Empty State --------------------------------------------------------------- */

.citewp-aiso-empty {
    text-align: center;
    padding: var(--citewp-space-5) var(--citewp-space-4);
    color: #787c82;
}

.citewp-aiso-empty__title {
    font-size: var(--citewp-font-base);
    font-weight: 600;
    color: #50575e;
    margin: 0 0 var(--citewp-space-2);
}

.citewp-aiso-empty__desc {
    font-size: var(--citewp-font-sm);
    margin: 0;
}

/* Stacked field and input-row modifiers ------------------------------------- */

.citewp-aiso-field--stacked {
    flex-direction: column;
    align-items: flex-start;
}

.citewp-aiso-field--stacked .button {
    margin-top: var(--citewp-space-2);
}

.citewp-aiso-input-row--stacked {
    flex-direction: column;
    align-items: flex-start;
    gap: var(--citewp-space-2);
}

/* CPT checkboxes (llms.txt tab) --------------------------------------------- */

.citewp-aiso-cpt-label {
    display: block;
    margin-bottom: 4px;
    font-size: var(--citewp-font-sm);
}

/* Save bar ------------------------------------------------------------------ */

.citewp-aiso-save-bar {
    display: flex;
    align-items: center;
    gap: var(--citewp-space-3);
    margin-top: var(--citewp-space-4);
    padding-top: var(--citewp-space-3);
    border-top: 1px solid #c3c4c7;
}
```

- [ ] **Step 3: Commit**

```bash
git add admin/css/citewp-aiso-admin.css
git commit -m "feat: add admin CSS file with design tokens, components, and layout"
```

---

## Task 2: Download Font Files

**Files:**
- Create: `admin/fonts/plus-jakarta-sans-800.woff2`
- Create: `admin/fonts/fraunces-800-italic.woff2`
- Create: `admin/fonts/jetbrains-mono-400.woff2`

- [ ] **Step 1: Create the fonts directory**

```powershell
New-Item -ItemType Directory -Force -Path "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer\admin\fonts"
```

- [ ] **Step 2: Download Plus Jakarta Sans 800**

```powershell
Invoke-WebRequest `
  -Uri "https://cdn.jsdelivr.net/npm/@fontsource/plus-jakarta-sans@5.1.1/files/plus-jakarta-sans-latin-800-normal.woff2" `
  -OutFile "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer\admin\fonts\plus-jakarta-sans-800.woff2"
```

Expected: file exists, size > 10KB.

- [ ] **Step 3: Download JetBrains Mono 400**

```powershell
Invoke-WebRequest `
  -Uri "https://cdn.jsdelivr.net/npm/@fontsource/jetbrains-mono@5.1.0/files/jetbrains-mono-latin-400-normal.woff2" `
  -OutFile "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer\admin\fonts\jetbrains-mono-400.woff2"
```

Expected: file exists, size > 10KB.

- [ ] **Step 4: Verify both font files downloaded**

```powershell
Get-ChildItem "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer\admin\fonts\" | Select-Object Name, Length
```

Expected: two `.woff2` files (`plus-jakarta-sans-800.woff2`, `jetbrains-mono-400.woff2`), each > 10,000 bytes. If a download returned a 404 or tiny file (< 5KB), the jsDelivr URL changed — browse `https://cdn.jsdelivr.net/npm/@fontsource/{font-name}/` and find the correct woff2 filename in the `files/` listing, then re-download.

- [ ] **Step 5: Commit**

```bash
git add admin/fonts/
git commit -m "feat: add self-hosted woff2 font files for admin UI"
```

---

## Task 3: Create includes/Admin/PageHeader.php

**Files:**
- Create: `includes/Admin/PageHeader.php`

- [ ] **Step 1: Write the file**

Use the Write tool to create `includes/Admin/PageHeader.php`:

```php
<?php
/**
 * Shared admin page header with top navigation.
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Admin;

use CiteWP\Aiso\Settings\Page as SettingsPage;

defined( 'ABSPATH' ) || exit;

final class PageHeader {

	/**
	 * Render the top nav bar. Pass the current page's menu slug as $current_page.
	 */
	public static function render_nav( string $current_page ): void {
		$defaults = [
			'dashboard' => [
				'label' => __( 'Dashboard', 'ai-search-optimizer' ),
				'url'   => admin_url( 'admin.php?page=' . Menu::SLUG_PARENT ),
				'slug'  => Menu::SLUG_PARENT,
			],
			'settings' => [
				'label' => __( 'Settings', 'ai-search-optimizer' ),
				'url'   => admin_url( 'admin.php?page=' . SettingsPage::SLUG ),
				'slug'  => SettingsPage::SLUG,
			],
			'logs' => [
				'label' => __( 'Crawler Logs', 'ai-search-optimizer' ),
				'url'   => admin_url( 'admin.php?page=' . Menu::SLUG_LOGS ),
				'slug'  => Menu::SLUG_LOGS,
			],
			'pro' => [
				'label'    => __( 'Pro ↗', 'ai-search-optimizer' ),
				'url'      => 'https://citewp.com',
				'slug'     => 'pro',
				'external' => true,
			],
		];

		/**
		 * Filters the top navigation items for CiteWP admin pages.
		 *
		 * Each item is an associative array with keys: label (string), url (string),
		 * slug (string), external (bool, optional). The slug is compared against
		 * $current_page to apply the active state.
		 *
		 * @param array<string, array<string, mixed>> $items Navigation items keyed by an arbitrary identifier.
		 */
		$items = apply_filters( 'citewp_aiso/admin/nav', $defaults );

		?>
		<div class="citewp-aiso-header">
			<span class="citewp-aiso-header__wordmark">[CiteWP]</span>
			<nav class="citewp-aiso-header__nav" aria-label="<?php esc_attr_e( 'CiteWP navigation', 'ai-search-optimizer' ); ?>">
				<?php foreach ( $items as $item ) :
					if ( ! isset( $item['label'], $item['url'], $item['slug'] ) ) {
						continue;
					}
					$is_active = ( $current_page === $item['slug'] );
					$classes   = 'citewp-aiso-nav__item' . ( $is_active ? ' citewp-aiso-nav__item--active' : '' );
					$external  = ! empty( $item['external'] );
					?>
					<a
						href="<?php echo esc_url( $item['url'] ); ?>"
						class="<?php echo esc_attr( $classes ); ?>"
						<?php if ( $external ) : ?>
							target="_blank"
							rel="noopener noreferrer"
						<?php endif; ?>
						<?php if ( $is_active ) : ?>
							aria-current="page"
						<?php endif; ?>
					><?php echo esc_html( $item['label'] ); ?></a>
				<?php endforeach; ?>
			</nav>
		</div>
		<?php
	}
}
```

- [ ] **Step 2: Commit**

```bash
git add includes/Admin/PageHeader.php
git commit -m "feat: add PageHeader component with citewp_aiso/admin/nav filter (X15)"
```

---

## Task 4: Create includes/Admin/DashboardData.php

**Files:**
- Create: `includes/Admin/DashboardData.php`

The four methods below are extracted verbatim from `DashboardWidget.php` (lines 158–251), made `public` instead of `private`.

- [ ] **Step 1: Write the file**

Use the Write tool to create `includes/Admin/DashboardData.php`:

```php
<?php
/**
 * Shared data queries for CiteWP dashboard surfaces.
 *
 * Used by both the WP Dashboard widget (DashboardWidget) and the
 * plugin's own Dashboard admin page (Menu::render_dashboard).
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Admin;

use CiteWP\Aiso\Database\Schema;
use CiteWP\Aiso\Scoring\Repository;

defined( 'ABSPATH' ) || exit;

final class DashboardData {

	public function get_average_score(): ?int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin stat; real-time data, intentionally uncached.
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ROUND( AVG( CAST( pm.meta_value AS UNSIGNED ) ) )
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = %s
				   AND p.post_status = 'publish'
				   AND p.post_type IN ('post', 'page')",
				Repository::META_KEY_TOTAL
			)
		);

		return $result !== null ? (int) $result : null;
	}

	/**
	 * @return array<int, object>
	 */
	public function get_top_crawled_pages(): array {
		global $wpdb;

		$table = esc_sql( Schema::table( Schema::TABLE_CRAWLER_LOGS ) );
		$since = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; $table is esc_sql() of a hardcoded constant. Real-time widget data.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT request_uri, COUNT(*) AS visit_count
				 FROM {$table}
				 WHERE detected_at >= %s
				 GROUP BY request_uri
				 ORDER BY visit_count DESC
				 LIMIT 5",
				$since
			)
		);
		// phpcs:enable

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * @return array{this_week: int, last_week: int}
	 */
	public function get_visit_trend(): array {
		global $wpdb;

		$table        = esc_sql( Schema::table( Schema::TABLE_CRAWLER_LOGS ) );
		$now          = gmdate( 'Y-m-d H:i:s' );
		$seven_ago    = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
		$fourteen_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-14 days' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; $table is esc_sql() of a hardcoded constant. Real-time widget data.
		$this_week = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE detected_at >= %s AND detected_at < %s",
				$seven_ago,
				$now
			)
		);

		$last_week = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE detected_at >= %s AND detected_at < %s",
				$fourteen_ago,
				$seven_ago
			)
		);
		// phpcs:enable

		return [ 'this_week' => $this_week, 'last_week' => $last_week ];
	}

	/**
	 * @return \WP_Post[]
	 */
	public function get_lowest_scoring_posts(): array {
		$posts = get_posts(
			[
				'post_type'      => [ 'post', 'page' ],
				'post_status'    => 'publish',
				'posts_per_page' => 5,
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
				'meta_key'       => Repository::META_KEY_TOTAL, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Intentional; orderby meta_value_num requires meta_key.
			]
		);

		return is_array( $posts ) ? $posts : [];
	}
}
```

- [ ] **Step 2: Commit**

```bash
git add includes/Admin/DashboardData.php
git commit -m "feat: extract DashboardData service from DashboardWidget"
```

---

## Task 5: Update includes/Admin/Menu.php

**Files:**
- Modify: `includes/Admin/Menu.php`

Read the current file at `includes/Admin/Menu.php` before editing. The current file is 66 lines.

- [ ] **Step 1: Replace the entire file**

Use the Write tool (full replace — the file is short and changes touch nearly every method):

```php
<?php
/**
 * Admin menu registration.
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Admin;

use CiteWP\Aiso\Scoring\Repository;

defined( 'ABSPATH' ) || exit;

final class Menu {

	public const SLUG_PARENT = 'citewp';
	public const SLUG_LOGS   = 'citewp-aiso-crawler-logs';

	public function register(): void {
		add_action( 'admin_menu',             [ $this, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts',  [ $this, 'enqueue_assets' ] );
	}

	public function add_menu(): void {
		add_menu_page(
			__( 'CiteWP', 'ai-search-optimizer' ),
			__( 'CiteWP', 'ai-search-optimizer' ),
			'manage_options',
			self::SLUG_PARENT,
			[ $this, 'render_dashboard' ],
			'dashicons-chart-line',
			81
		);

		add_submenu_page(
			self::SLUG_PARENT,
			__( 'Crawler Logs', 'ai-search-optimizer' ),
			__( 'Crawler Logs', 'ai-search-optimizer' ),
			'manage_options',
			self::SLUG_LOGS,
			[ \CiteWP\Aiso\Plugin::instance()->module( 'admin_logs_page' ), 'render' ]
		);

		// Rename the auto-added duplicate "CiteWP" submenu to "Dashboard".
		global $submenu;
		if ( isset( $submenu[ self::SLUG_PARENT ][0][0] ) ) {
			$submenu[ self::SLUG_PARENT ][0][0] = __( 'Dashboard', 'ai-search-optimizer' );
		}
	}

	public function enqueue_assets( string $hook ): void {
		$is_citewp_screen = (
			$hook === 'toplevel_page_' . self::SLUG_PARENT ||
			strpos( $hook, 'citewp_page_' ) === 0
		);

		if ( ! $is_citewp_screen ) {
			return;
		}

		wp_enqueue_style(
			'citewp-aiso-admin',
			CITEWP_AISO_PLUGIN_URL . 'admin/css/citewp-aiso-admin.css',
			[],
			CITEWP_AISO_VERSION
		);
	}

	public function render_dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$data         = new DashboardData();
		$avg_score    = $data->get_average_score();
		$trend        = $data->get_visit_trend();
		$lowest_posts = $data->get_lowest_scoring_posts();

		$avg_grade = 'red';
		if ( $avg_score !== null ) {
			if ( $avg_score >= 80 ) {
				$avg_grade = 'green';
			} elseif ( $avg_score >= 60 ) {
				$avg_grade = 'yellow';
			} elseif ( $avg_score >= 40 ) {
				$avg_grade = 'orange';
			}
		}

		// Gauge arc: semicircle radius 54, circumference of half-circle = π * 54 ≈ 169.6.
		$circumference = M_PI * 54;
		$fill_pct      = $avg_score !== null ? max( 0, min( 100, $avg_score ) ) : 0;
		$offset        = $circumference - ( $fill_pct / 100 ) * $circumference;

		$settings_url = admin_url( 'admin.php?page=citewp-aiso-settings' );
		$logs_url     = admin_url( 'admin.php?page=' . self::SLUG_LOGS );

		/**
		 * Filters extra dashboard cards rendered after the built-in summary cards.
		 *
		 * Each card is an associative array with keys: title (string), value (string),
		 * description (string, optional), link_url (string, optional), link_label (string, optional).
		 * Return an empty array (default) to render no extra cards.
		 *
		 * @param array<int, array<string, string>> $cards Extra cards to render.
		 */
		$extra_cards = apply_filters( 'citewp_aiso/dashboard/cards', [] );
		?>
		<div class="wrap">

			<?php PageHeader::render_nav( self::SLUG_PARENT ); ?>

			<div class="citewp-aiso-page-body">

				<div class="citewp-aiso-card-grid citewp-aiso-card-grid--3col">

					<!-- Average Score Gauge -->
					<div class="citewp-aiso-card">
						<p class="citewp-aiso-card__title"><?php esc_html_e( 'Avg GEO Score', 'ai-search-optimizer' ); ?></p>
						<?php if ( $avg_score !== null ) : ?>
							<div class="citewp-aiso-gauge">
								<svg width="120" height="66" viewBox="0 0 120 66" aria-hidden="true">
									<path
										class="citewp-aiso-gauge__track"
										d="M 6,60 A 54,54 0 0,1 114,60"
									/>
									<path
										class="citewp-aiso-gauge__fill citewp-aiso-gauge__fill--<?php echo esc_attr( $avg_grade ); ?>"
										d="M 6,60 A 54,54 0 0,1 114,60"
										stroke-dasharray="<?php echo esc_attr( (string) round( $circumference, 2 ) ); ?>"
										stroke-dashoffset="<?php echo esc_attr( (string) round( $offset, 2 ) ); ?>"
									/>
									<text x="60" y="56" class="citewp-aiso-gauge__score-text"><?php echo esc_html( (string) $avg_score ); ?></text>
								</svg>
								<span class="citewp-aiso-gauge__label"><?php esc_html_e( 'across all scored posts', 'ai-search-optimizer' ); ?></span>
							</div>
						<?php else : ?>
							<div class="citewp-aiso-empty">
								<p class="citewp-aiso-empty__title"><?php esc_html_e( 'No scores yet', 'ai-search-optimizer' ); ?></p>
								<p class="citewp-aiso-empty__desc"><?php esc_html_e( 'Open any post to trigger scoring.', 'ai-search-optimizer' ); ?></p>
							</div>
						<?php endif; ?>
					</div>

					<!-- Bot Visit Trend -->
					<div class="citewp-aiso-card">
						<p class="citewp-aiso-card__title"><?php esc_html_e( 'Bot Visits (7d)', 'ai-search-optimizer' ); ?></p>
						<p class="citewp-aiso-card__value"><?php echo esc_html( number_format_i18n( $trend['this_week'] ) ); ?></p>
						<?php
						$diff = $trend['this_week'] - $trend['last_week'];
						if ( $trend['last_week'] > 0 && $diff !== 0 ) :
							$arrow = $diff > 0 ? '▲' : '▼';
						?>
						<p class="citewp-aiso-card__desc"><?php echo esc_html( $arrow . ' ' . number_format_i18n( abs( $diff ) ) . ' ' . __( 'vs. prior 7 days', 'ai-search-optimizer' ) ); ?></p>
						<?php else : ?>
						<p class="citewp-aiso-card__desc"><?php esc_html_e( 'vs. prior 7 days', 'ai-search-optimizer' ); ?></p>
						<?php endif; ?>
					</div>

					<!-- Lowest Scoring Posts -->
					<div class="citewp-aiso-card">
						<p class="citewp-aiso-card__title"><?php esc_html_e( 'Needs Attention', 'ai-search-optimizer' ); ?></p>
						<?php if ( ! empty( $lowest_posts ) ) : ?>
							<ul class="citewp-aiso-needs-attention__list">
								<?php foreach ( $lowest_posts as $post ) :
									$score    = (int) get_post_meta( $post->ID, Repository::META_KEY_TOTAL, true );
									$grade    = get_post_meta( $post->ID, Repository::META_KEY_GRADE, true );
									$grade    = is_string( $grade ) && in_array( $grade, [ 'red', 'orange', 'yellow', 'green' ], true ) ? $grade : 'red';
									$edit_url = get_edit_post_link( $post->ID );
								?>
								<li class="citewp-aiso-needs-attention__item">
									<span class="citewp-aiso-badge citewp-aiso-badge--<?php echo esc_attr( $grade ); ?>"><?php echo esc_html( (string) $score ); ?></span>
									<?php if ( $edit_url ) : ?>
										<a href="<?php echo esc_url( $edit_url ); ?>" class="citewp-aiso-needs-attention__title"><?php echo esc_html( get_the_title( $post ) ); ?></a>
									<?php else : ?>
										<span class="citewp-aiso-needs-attention__title"><?php echo esc_html( get_the_title( $post ) ); ?></span>
									<?php endif; ?>
								</li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<p class="citewp-aiso-needs-attention__nodata"><?php esc_html_e( 'No scored posts yet.', 'ai-search-optimizer' ); ?></p>
						<?php endif; ?>
					</div>

				</div>

				<!-- Extra cards from filter (Pro / extensions) -->
				<?php if ( ! empty( $extra_cards ) ) : ?>
				<div class="citewp-aiso-card-grid">
					<?php foreach ( $extra_cards as $card ) :
						if ( ! isset( $card['title'], $card['value'] ) ) {
							continue;
						}
					?>
					<div class="citewp-aiso-card">
						<p class="citewp-aiso-card__title"><?php echo esc_html( $card['title'] ); ?></p>
						<p class="citewp-aiso-card__value"><?php echo esc_html( $card['value'] ); ?></p>
						<?php if ( ! empty( $card['description'] ) ) : ?>
							<p class="citewp-aiso-card__desc"><?php echo esc_html( $card['description'] ); ?></p>
						<?php endif; ?>
						<?php if ( ! empty( $card['link_url'] ) && ! empty( $card['link_label'] ) ) : ?>
							<a href="<?php echo esc_url( $card['link_url'] ); ?>" class="citewp-aiso-card__link"><?php echo esc_html( $card['link_label'] ); ?></a>
						<?php endif; ?>
					</div>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>

				<!-- Quick actions -->
				<p class="citewp-aiso-quick-actions">
					<a href="<?php echo esc_url( $settings_url ); ?>" class="button"><?php esc_html_e( 'Settings', 'ai-search-optimizer' ); ?></a>
					<a href="<?php echo esc_url( $logs_url ); ?>" class="button"><?php esc_html_e( 'Crawler Logs', 'ai-search-optimizer' ); ?></a>
				</p>

			</div>
		</div>
		<?php
	}
}
```

- [ ] **Step 2: Commit**

```bash
git add includes/Admin/Menu.php
git commit -m "feat: rebuild Dashboard page with card grid and citewp_aiso/dashboard/cards filter (X15)"
```

---

## Task 6: Rebuild includes/Settings/Page.php

**Files:**
- Modify: `includes/Settings/Page.php`

Read the current file before editing. All existing sanitize callbacks, option constants, and the `handle_regenerate` handler are preserved — only `render()` and `inline_styles()` change.

- [ ] **Step 1: Replace the entire file**

Use the Write tool:

```php
<?php
/**
 * Settings page (admin UI for plugin configuration).
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Settings;

use CiteWP\Aiso\Admin\Menu;
use CiteWP\Aiso\Admin\PageHeader;
use CiteWP\Aiso\Llms\Cache;

defined( 'ABSPATH' ) || exit;

final class Page {

	public const SLUG        = 'citewp-aiso-settings';
	public const OPTION_LLMS = 'citewp_aiso_llms_settings';
	public const OPTION_CORE = 'citewp_aiso_settings';

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_submenu' ], 20 );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_post_citewp_aiso_regenerate_llms', [ $this, 'handle_regenerate' ] );
	}

	public function add_submenu(): void {
		add_submenu_page(
			Menu::SLUG_PARENT,
			__( 'CiteWP Settings', 'ai-search-optimizer' ),
			__( 'Settings', 'ai-search-optimizer' ),
			'manage_options',
			self::SLUG,
			[ $this, 'render' ]
		);
	}

	public function register_settings(): void {
		register_setting(
			'citewp_aiso_settings_group',
			self::OPTION_CORE,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_core' ],
				'default'           => [
					'enable_crawler_detection' => true,
					'log_retention_days'       => 7,
				],
			]
		);

		register_setting(
			'citewp_aiso_settings_group',
			self::OPTION_LLMS,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_llms' ],
				'default'           => [
					'enabled'          => true,
					'min_word_count'   => 500,
					'recent_days'      => 90,
					'extra_post_types' => [],
				],
			]
		);
	}

	/**
	 * @param array<string, mixed>|null $input
	 * @return array<string, mixed>
	 */
	public function sanitize_core( $input ): array {
		$input = is_array( $input ) ? $input : [];
		return [
			'enable_crawler_detection' => ! empty( $input['enable_crawler_detection'] ),
			'log_retention_days'       => max( 1, min( 365, (int) ( $input['log_retention_days'] ?? 7 ) ) ),
		];
	}

	/**
	 * @param array<string, mixed>|null $input
	 * @return array<string, mixed>
	 */
	public function sanitize_llms( $input ): array {
		$input = is_array( $input ) ? $input : [];

		$extra = [];
		if ( ! empty( $input['extra_post_types'] ) && is_array( $input['extra_post_types'] ) ) {
			$valid_types = array_keys( get_post_types( [ 'public' => true, 'show_ui' => true ] ) );
			$extra       = array_values( array_intersect( array_map( 'sanitize_key', $input['extra_post_types'] ), $valid_types ) );
		}

		return [
			'enabled'          => ! empty( $input['enabled'] ),
			'min_word_count'   => max( 0, min( 10000, (int) ( $input['min_word_count'] ?? 500 ) ) ),
			'recent_days'      => max( 1, min( 3650, (int) ( $input['recent_days'] ?? 90 ) ) ),
			'extra_post_types' => $extra,
		];
	}

	public function handle_regenerate(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'ai-search-optimizer' ) );
		}
		check_admin_referer( 'citewp_aiso_regenerate_llms' );

		( new Cache() )->flush();

		wp_safe_redirect(
			add_query_arg(
				[ 'page' => self::SLUG, 'regenerated' => '1' ],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$core         = get_option( self::OPTION_CORE, [] );
		$llms         = get_option( self::OPTION_LLMS, [] );
		$public_types = get_post_types( [ 'public' => true, 'show_ui' => true, '_builtin' => false ], 'objects' );
		$llms_short   = home_url( '/llms.txt' );
		$llms_full    = home_url( '/llms-full.txt' );

		/**
		 * Filters the inner tab definitions for the CiteWP Settings page.
		 *
		 * Array keys are tab slugs (used for URL hash and panel IDs).
		 * Array values are translated tab labels.
		 * Built-in slugs: general, crawler-detection, llms-txt, pro.
		 *
		 * @param array<string, string> $tabs Tab slug => label.
		 */
		$tabs = apply_filters(
			'citewp_aiso/settings/tabs',
			[
				'general'           => __( 'General', 'ai-search-optimizer' ),
				'crawler-detection' => __( 'Crawler Detection', 'ai-search-optimizer' ),
				'llms-txt'          => __( 'llms.txt', 'ai-search-optimizer' ),
			]
		);

		$default_tab = array_key_first( $tabs ) ?? 'general';
		?>
		<div class="wrap">

			<?php PageHeader::render_nav( self::SLUG ); ?>

			<?php if ( isset( $_GET['regenerated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display flag set by this plugin after safe redirect; no data modification. ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'llms.txt cache cleared. The next request will regenerate from scratch.', 'ai-search-optimizer' ); ?></p></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['settings-updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Standard WP options-saved flag; no data modification. ?>
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
					<?php settings_fields( 'citewp_aiso_settings_group' ); ?>

					<!-- General Tab -->
					<?php if ( isset( $tabs['general'] ) ) : ?>
					<div
						id="citewp-aiso-tab-general"
						class="citewp-aiso-tabs__panel"
						role="tabpanel"
						aria-labelledby="citewp-aiso-tab-btn-general"
					>
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
						<?php do_action( 'citewp_aiso/settings/panel/general' ); ?>
					</div>
					<?php endif; ?>

					<!-- Crawler Detection Tab -->
					<?php if ( isset( $tabs['crawler-detection'] ) ) : ?>
					<div
						id="citewp-aiso-tab-crawler-detection"
						class="citewp-aiso-tabs__panel"
						role="tabpanel"
						aria-labelledby="citewp-aiso-tab-btn-crawler-detection"
					>
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
						<?php do_action( 'citewp_aiso/settings/panel/crawler-detection' ); ?>
					</div>
					<?php endif; ?>

					<!-- llms.txt Tab -->
					<?php if ( isset( $tabs['llms-txt'] ) ) : ?>
					<div
						id="citewp-aiso-tab-llms-txt"
						class="citewp-aiso-tabs__panel"
						role="tabpanel"
						aria-labelledby="citewp-aiso-tab-btn-llms-txt"
					>
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
								<div class="citewp-aiso-field">
									<label class="citewp-aiso-field__label" for="citewp_aiso_llms_enabled">
										<span class="citewp-aiso-field__label-text"><?php esc_html_e( 'Enable llms.txt', 'ai-search-optimizer' ); ?></span>
										<span class="citewp-aiso-field__label-desc"><?php esc_html_e( 'Serve dynamic llms.txt to AI engines.', 'ai-search-optimizer' ); ?></span>
									</label>
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
								<div class="citewp-aiso-input-row">
									<label class="citewp-aiso-input-row__label" for="citewp_aiso_min_word_count">
										<?php esc_html_e( 'Minimum word count', 'ai-search-optimizer' ); ?>
									</label>
									<div class="citewp-aiso-input-row__field">
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
										<span class="description"><?php esc_html_e( 'Posts shorter than this are skipped. Pages always included.', 'ai-search-optimizer' ); ?></span>
									</div>
								</div>
								<div class="citewp-aiso-input-row">
									<label class="citewp-aiso-input-row__label" for="citewp_aiso_recent_days">
										<?php esc_html_e( 'Include posts from last (days)', 'ai-search-optimizer' ); ?>
									</label>
									<div class="citewp-aiso-input-row__field">
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
								<div class="citewp-aiso-input-row citewp-aiso-input-row--stacked">
									<span class="citewp-aiso-input-row__label"><?php esc_html_e( 'Include custom post types', 'ai-search-optimizer' ); ?></span>
									<div>
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
						</div>
						<?php do_action( 'citewp_aiso/settings/panel/llms-txt' ); ?>
					</div>
					<?php endif; ?>

					<!-- Extra tabs from filter (Pro registers here when citewp.com SaaS ships via citewp_aiso/settings/tabs) -->
					<?php foreach ( $tabs as $slug => $label ) :
						if ( in_array( $slug, [ 'general', 'crawler-detection', 'llms-txt' ], true ) ) {
							continue;
						}
						$panel_id = 'citewp-aiso-tab-' . esc_attr( $slug );
					?>
					<div
						id="<?php echo esc_attr( $panel_id ); ?>"
						class="citewp-aiso-tabs__panel"
						role="tabpanel"
						aria-labelledby="citewp-aiso-tab-btn-<?php echo esc_attr( $slug ); ?>"
					>
						<?php do_action( 'citewp_aiso/settings/panel/' . $slug ); ?>
					</div>
					<?php endforeach; ?>

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

		</div><!-- .wrap -->

		<script>
		(function () {
			var tabs   = document.querySelectorAll( '.citewp-aiso-tabs__btn' );
			var panels = document.querySelectorAll( '.citewp-aiso-tabs__panel' );

			function activate( slug ) {
				tabs.forEach( function ( btn ) {
					var active = btn.dataset.tab === slug;
					btn.classList.toggle( 'citewp-aiso-tabs__btn--active', active );
					btn.setAttribute( 'aria-selected', active ? 'true' : 'false' );
				} );
				panels.forEach( function ( panel ) {
					var active = panel.id === 'citewp-aiso-tab-' + slug;
					panel.classList.toggle( 'citewp-aiso-tabs__panel--active', active );
				} );
			}

			// Restore from URL hash.
			var hash    = location.hash.replace( '#', '' );
			var initial = hash && document.getElementById( 'citewp-aiso-tab-' + hash ) ? hash : <?php echo wp_json_encode( $default_tab ); ?>;
			activate( initial );

			tabs.forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					var slug = btn.dataset.tab;
					history.replaceState( null, '', '#' + slug );
					activate( slug );
				} );
			} );
		}());
		</script>
		<?php
	}
}
```

- [ ] **Step 2: Commit**

```bash
git add includes/Settings/Page.php
git commit -m "feat: rebuild Settings page with tabbed layout, cards, toggles, citewp_aiso/settings/tabs filter (X15)"
```

---

## Task 7: Update includes/Admin/LogsPage.php

**Files:**
- Modify: `includes/Admin/LogsPage.php`

Read the current file before editing. Changes: (1) add `PageHeader::render_nav()` at top of `render()`, (2) update HTML class names from `citewp-aiso-logs-banner/stat` to `citewp-aiso-stats-banner/stat` to match the CSS file, (3) remove `inline_styles()` method and its `add_action` registration.

- [ ] **Step 1: Remove the inline_styles action from register() and add PageHeader use declaration**

Read `includes/Admin/LogsPage.php`. Apply two Edit calls:

**Edit A** — remove the `admin_head` action. Use this exact old_string (anchored on the full register body as it exists in the file):

```php
	public function register(): void {
		add_action( 'admin_init',                    [ $this, 'maybe_init_table' ] );
		add_action( 'admin_head',                    [ $this, 'inline_styles' ] );
		add_action( 'admin_post_citewp_aiso_export_logs', [ $this, 'handle_csv_export' ] );
	}
```

new_string:

```php
	public function register(): void {
		add_action( 'admin_init',                    [ $this, 'maybe_init_table' ] );
		add_action( 'admin_post_citewp_aiso_export_logs', [ $this, 'handle_csv_export' ] );
	}
```

**Edit B** — add the PageHeader use declaration. old_string:

```php
use CiteWP\Aiso\Database\Schema;
```

new_string:

```php
use CiteWP\Aiso\Database\Schema;
use CiteWP\Aiso\Admin\PageHeader;
```

- [ ] **Step 2: Update render() — add PageHeader and update class names**

Read `includes/Admin/LogsPage.php`. Use the Edit tool anchored on the exact wrap-opening block as it currently exists in the file.

old_string:

```php
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'AI Crawler Logs', 'ai-search-optimizer' ); ?></h1>

			<div class="citewp-aiso-logs-banner">
				<div class="citewp-aiso-logs-stat">
					<span class="citewp-aiso-logs-stat__label"><?php esc_html_e( 'Last 24 hours', 'ai-search-optimizer' ); ?></span>
					<span class="citewp-aiso-logs-stat__value"><?php echo esc_html( number_format_i18n( $count_24h ) ); ?></span>
				</div>
				<div class="citewp-aiso-logs-stat">
					<span class="citewp-aiso-logs-stat__label"><?php esc_html_e( 'Last 7 days', 'ai-search-optimizer' ); ?></span>
					<span class="citewp-aiso-logs-stat__value"><?php echo esc_html( number_format_i18n( $count_7d ) ); ?></span>
				</div>
				<div class="citewp-aiso-logs-stat">
					<span class="citewp-aiso-logs-stat__label"><?php esc_html_e( 'Last 30 days', 'ai-search-optimizer' ); ?></span>
					<span class="citewp-aiso-logs-stat__value"><?php echo esc_html( number_format_i18n( $count_30d ) ); ?></span>
				</div>
				<div class="citewp-aiso-logs-banner__export">
					<a href="<?php echo esc_url( $export_url ); ?>" class="button">
						<?php esc_html_e( 'Export CSV', 'ai-search-optimizer' ); ?>
					</a>
				</div>
			</div>
```

new_string:

```php
		?>
		<div class="wrap">

			<?php PageHeader::render_nav( Menu::SLUG_LOGS ); ?>

			<div class="citewp-aiso-page-body">

			<div class="citewp-aiso-stats-banner">
				<div class="citewp-aiso-stat">
					<span class="citewp-aiso-stat__label"><?php esc_html_e( 'Last 24 hours', 'ai-search-optimizer' ); ?></span>
					<span class="citewp-aiso-stat__value"><?php echo esc_html( number_format_i18n( $count_24h ) ); ?></span>
				</div>
				<div class="citewp-aiso-stat">
					<span class="citewp-aiso-stat__label"><?php esc_html_e( 'Last 7 days', 'ai-search-optimizer' ); ?></span>
					<span class="citewp-aiso-stat__value"><?php echo esc_html( number_format_i18n( $count_7d ) ); ?></span>
				</div>
				<div class="citewp-aiso-stat">
					<span class="citewp-aiso-stat__label"><?php esc_html_e( 'Last 30 days', 'ai-search-optimizer' ); ?></span>
					<span class="citewp-aiso-stat__value"><?php echo esc_html( number_format_i18n( $count_30d ) ); ?></span>
				</div>
				<div class="citewp-aiso-stats-banner__export">
					<a href="<?php echo esc_url( $export_url ); ?>" class="button">
						<?php esc_html_e( 'Export CSV', 'ai-search-optimizer' ); ?>
					</a>
				</div>
			</div>
```

Also close the `citewp-aiso-page-body` div. Use a second Edit call anchored on the exact closing lines at the bottom of render() in the current file:

old_string:

```php
		<?php endif; ?>
		</div>
		<?php
	}
```

new_string:

```php
		<?php endif; ?>
			</div><!-- .citewp-aiso-page-body -->
		</div><!-- .wrap -->
		<?php
	}
```

- [ ] **Step 3: Remove the inline_styles() method entirely**

Read `includes/Admin/LogsPage.php`. Use the Edit tool with this exact old_string (the complete method as it exists in the file):

```php
	public function inline_styles(): void {
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, Menu::SLUG_LOGS ) === false ) {
			return;
		}
		?>
		<style>
			.citewp-aiso-logs-banner { display: flex; align-items: center; gap: 12px; margin: 16px 0; flex-wrap: wrap; }
			.citewp-aiso-logs-stat { background: #f9f9f9; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 16px; min-width: 110px; }
			.citewp-aiso-logs-stat__label { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; margin-bottom: 2px; }
			.citewp-aiso-logs-stat__value { display: block; font-size: 22px; font-weight: 700; color: #111827; line-height: 1; }
			.citewp-aiso-logs-banner__export { margin-left: auto; }
		</style>
		<?php
	}
```

new_string: *(empty string — delete the method entirely)*

- [ ] **Step 4: Verify the file parses**

```bash
php -l "includes/Admin/LogsPage.php"
```

Expected: `No syntax errors detected`.

- [ ] **Step 5: Commit**

```bash
git add includes/Admin/LogsPage.php
git commit -m "feat: add PageHeader nav to Logs page, migrate inline styles to CSS file"
```

---

## Task 8: Update includes/Admin/DashboardWidget.php

**Files:**
- Modify: `includes/Admin/DashboardWidget.php`

Read the current file before editing. Changes: replace the four private data methods with `DashboardData` delegation; add `apply_filters('citewp_aiso/dashboard/cards', [])` stub inside `render()` (no-op call, result unused, hook declared). The widget render HTML and inline_styles are unchanged.

- [ ] **Step 1: Add use statement for DashboardData**

Read `includes/Admin/DashboardWidget.php`. Use the Edit tool anchored on the existing use declarations:

old_string:

```php
use CiteWP\Aiso\Scoring\Repository;
```

new_string:

```php
use CiteWP\Aiso\Scoring\Repository;
use CiteWP\Aiso\Admin\DashboardData;
```

- [ ] **Step 2: Add the cards filter stub to render()**

Read `includes/Admin/DashboardWidget.php`. Use the Edit tool anchored on the last data-fetch line in render():

old_string:

```php
		$lowest_posts = $this->get_lowest_scoring_posts();
```

new_string:

```php
		$lowest_posts = $this->get_lowest_scoring_posts();

		/**
		 * Fires to allow registration of extra dashboard card data.
		 * Currently unused by the WP Dashboard widget — extension point for Pro.
		 *
		 * @param array<int, array<string, string>> $cards Extra cards (empty by default).
		 */
		apply_filters( 'citewp_aiso/dashboard/cards', [] );
```

- [ ] **Step 3: Replace the four private methods with DashboardData delegation** (continued below)

```php
		/**
		 * Fires to allow registration of extra dashboard card data.
		 * Currently unused by the WP Dashboard widget — extension point for Pro.
		 *
		 * @param array<int, array<string, string>> $cards Extra cards (empty by default).
		 */
		apply_filters( 'citewp_aiso/dashboard/cards', [] );
```

- [ ] **Step 3: Replace the four private methods with DashboardData delegation**

Read `includes/Admin/DashboardWidget.php`. Use the Edit tool with this exact old_string (the complete four private data methods as they exist in the current file — anchored from `private function get_average_score()` through the closing `}` of `get_lowest_scoring_posts()`):

old_string:

```php
	private function get_average_score(): ?int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin stat; real-time data, intentionally uncached.
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ROUND( AVG( CAST( pm.meta_value AS UNSIGNED ) ) )
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = %s
				   AND p.post_status = 'publish'
				   AND p.post_type IN ('post', 'page')",
				Repository::META_KEY_TOTAL
			)
		);

		return $result !== null ? (int) $result : null;
	}

	/**
	 * @return array<int, object>
	 */
	private function get_top_crawled_pages(): array {
		global $wpdb;

		$table = esc_sql( Schema::table( Schema::TABLE_CRAWLER_LOGS ) );
		$since = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; $table is esc_sql() of a hardcoded constant. Real-time widget data.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT request_uri, COUNT(*) AS visit_count
				 FROM {$table}
				 WHERE detected_at >= %s
				 GROUP BY request_uri
				 ORDER BY visit_count DESC
				 LIMIT 5",
				$since
			)
		);
		// phpcs:enable

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * @return array{this_week: int, last_week: int}
	 */
	private function get_visit_trend(): array {
		global $wpdb;

		$table        = esc_sql( Schema::table( Schema::TABLE_CRAWLER_LOGS ) );
		$now          = gmdate( 'Y-m-d H:i:s' );
		$seven_ago    = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
		$fourteen_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-14 days' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; $table is esc_sql() of a hardcoded constant. Real-time widget data.
		$this_week = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE detected_at >= %s AND detected_at < %s",
				$seven_ago,
				$now
			)
		);

		$last_week = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE detected_at >= %s AND detected_at < %s",
				$fourteen_ago,
				$seven_ago
			)
		);
		// phpcs:enable

		return [ 'this_week' => $this_week, 'last_week' => $last_week ];
	}

	/**
	 * @return \WP_Post[]
	 */
	private function get_lowest_scoring_posts(): array {
		$posts = get_posts(
			[
				'post_type'      => [ 'post', 'page' ],
				'post_status'    => 'publish',
				'posts_per_page' => 5,
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
				'meta_key'       => Repository::META_KEY_TOTAL, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Intentional; orderby meta_value_num requires meta_key.
			]
		);

		return is_array( $posts ) ? $posts : [];
	}
```

new_string:

```php
	private function get_average_score(): ?int {
		return ( new DashboardData() )->get_average_score();
	}

	/**
	 * @return array<int, object>
	 */
	private function get_top_crawled_pages(): array {
		return ( new DashboardData() )->get_top_crawled_pages();
	}

	/**
	 * @return array{this_week: int, last_week: int}
	 */
	private function get_visit_trend(): array {
		return ( new DashboardData() )->get_visit_trend();
	}

	/**
	 * @return \WP_Post[]
	 */
	private function get_lowest_scoring_posts(): array {
		return ( new DashboardData() )->get_lowest_scoring_posts();
	}
```

- [ ] **Step 4: Verify**

```bash
php -l "includes/Admin/DashboardWidget.php"
```

Expected: `No syntax errors detected`.

- [ ] **Step 5: Commit**

```bash
git add includes/Admin/DashboardWidget.php
git commit -m "refactor: delegate DashboardWidget data methods to DashboardData service"
```

---

## Task 9: Add citewp_aiso/metabox/tabs stubs

**Files:**
- Modify: `includes/Admin/ScoreMetaBox.php`
- Modify: `includes/Admin/SchemaMetaBox.php`

Read both files before editing. The change in each is identical: add a `apply_filters('citewp_aiso/metabox/tabs', [])` call at the top of the `render()` method, just after the `current_user_can` check.

- [ ] **Step 1: Read ScoreMetaBox.php — find the render() method**

Locate the `render()` method and the line that reads `if ( ! current_user_can( 'manage_options' ) ) { return; }`. Add immediately after it:

```php
		/**
		 * Filters the meta box tab definitions. Reserved for Pro tab registration.
		 * Return an array of [ 'slug' => 'Label' ] pairs to register additional tabs.
		 *
		 * @param array<string, string> $tabs Empty by default; Pro registers tabs here.
		 */
		apply_filters( 'citewp_aiso/metabox/tabs', [] );
```

- [ ] **Step 2: Apply the same edit to SchemaMetaBox.php**

Read `includes/Admin/SchemaMetaBox.php`. Locate its `render()` method and the `current_user_can` check. Add the identical `apply_filters` block immediately after it.

- [ ] **Step 3: Verify both files parse**

```bash
php -l "includes/Admin/ScoreMetaBox.php" && php -l "includes/Admin/SchemaMetaBox.php"
```

Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Commit**

```bash
git add includes/Admin/ScoreMetaBox.php includes/Admin/SchemaMetaBox.php
git commit -m "feat: add citewp_aiso/metabox/tabs filter stubs in ScoreMetaBox and SchemaMetaBox (X15)"
```

---

## Task 10: Build Verification and Smoke Test

**Files:** none new

- [ ] **Step 1: Run npm build**

```bash
npm run build
```

Expected: exits 0, no TypeScript errors, `build/index.js` updated.

- [ ] **Step 2: Check PHP syntax on all modified files**

```bash
php -l "includes/Admin/PageHeader.php" && php -l "includes/Admin/DashboardData.php" && php -l "includes/Admin/Menu.php" && php -l "includes/Settings/Page.php" && php -l "includes/Admin/LogsPage.php" && php -l "includes/Admin/DashboardWidget.php"
```

Expected: all `No syntax errors detected`.

- [ ] **Step 3: Browser verification checklist**

Open `http://citewp-dev.local/wp-admin/admin.php?page=citewp` in a browser and verify:

- [ ] CiteWP admin CSS loads (open DevTools → Network → filter `citewp-aiso-admin.css` → status 200)
- [ ] Top nav renders: Dashboard | Settings | Crawler Logs | Pro ↗
- [ ] "Dashboard" nav item has Citrine underline active state
- [ ] Dashboard card grid renders three cards (Avg Score gauge, Bot Visits, Needs Attention)
- [ ] Score gauge SVG renders (semicircle visible even if score is 0 or null)
- [ ] Empty state shows if no posts scored yet

Navigate to Settings (`?page=citewp-aiso-settings`):

- [ ] "Settings" nav item is active
- [ ] Four inner tab buttons render: General | Crawler Detection | llms.txt | Pro
- [ ] Clicking each tab shows its panel, hides others
- [ ] URL hash updates on tab click (e.g., `#crawler-detection`)
- [ ] Reloading with hash restores the correct active tab
- [ ] Crawler Detection: toggle renders (yellow/Citrine when on, grey when off)
- [ ] llms.txt: toggle renders; number inputs present
- [ ] Save button saves settings — notice "Settings saved." appears
- [ ] Regenerate button in General tab triggers cache clear and redirects with `?regenerated=1`
- [ ] Pro tab renders empty (no "Coming Soon" text)
- [ ] Font loads: Plus Jakarta Sans visible in wordmark (DevTools → Fonts)

Navigate to Crawler Logs (`?page=citewp-aiso-crawler-logs`):

- [ ] "Crawler Logs" nav item is active
- [ ] Stats banner renders with three stat cards + Export CSV button
- [ ] JetBrains Mono visible in stat values (monospace numeral)
- [ ] Empty state or table renders correctly

Check WP Dashboard (`/wp-admin/`):

- [ ] Dashboard widget still renders correctly (WP-native styles, no regressions)
- [ ] Widget data unchanged

Check post editor (any post):

- [ ] ScoreMetaBox still renders in Classic editor
- [ ] SchemaMetaBox still renders
- [ ] No PHP errors in debug.log

- [ ] **Step 4: Check debug.log**

Open LocalWP and check `wp-content/debug.log` for any new PHP errors introduced this session.

Expected: no new errors.

- [ ] **Step 5: Final commit and push**

```bash
git add -A
git status
git commit -m "chore: Session 12 final — admin UI polish, all X15 hooks registered"
git push origin main
```

---

## Extensibility Hooks Summary (X15 End-of-Session Verification)

Confirm all four hooks are present in the committed code before closing:

| Hook | File | Line (approx) | Status |
|------|------|----------------|--------|
| `citewp_aiso/admin/nav` | `includes/Admin/PageHeader.php` | in `render_nav()` | ☐ |
| `citewp_aiso/settings/tabs` | `includes/Settings/Page.php` | in `render()` | ☐ |
| `citewp_aiso/metabox/tabs` | `includes/Admin/ScoreMetaBox.php` | in `render()` | ☐ |
| `citewp_aiso/metabox/tabs` | `includes/Admin/SchemaMetaBox.php` | in `render()` | ☐ |
| `citewp_aiso/dashboard/cards` | `includes/Admin/Menu.php` | in `render_dashboard()` | ☐ |
| `citewp_aiso/dashboard/cards` | `includes/Admin/DashboardWidget.php` | stub in `render()` | ☐ |
