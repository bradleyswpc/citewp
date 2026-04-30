# Admin Layout Refactor (P27 + P28 + P29) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the hybrid WP-submenus + top-horizontal-tabs layout with a single `add_menu_page` + left-rail navigation with hash-based section dispatch, and rebuild the Dashboard panel as a P27 single-column layout.

**Architecture:** `Menu.php` becomes the single WP render callback, renders the full page (wordmark header + left rail + all section panels). The `citewp_aiso/admin/nav` filter schema is extended with a `render` callable per item — registering a nav item also registers its panel, one filter call per extension. `Settings/Page.php` and `LogsPage.php` are stripped to pure panel renderers. `PageHeader.php` is deleted (wordmark inlined in `Menu.php`).

**Tech Stack:** PHP 8.0+, WordPress admin hooks, vanilla JS (hash dispatch + sessionStorage), WP admin CSS tokens.

---

## Visual Reference

`C:\Users\KingpinBWP\Desktop\CiteWP\Brain\design-reference\` contains 5 WP Rocket production screenshots showing the target left-rail pattern:

- `wp-rocket-dashboard-default.png`
- `wp-rocket-insights-default.png`
- `wp-rocket-addons-default.png`
- `wp-rocket-tutorials-default.png`
- `wp-rocket-our_plugins-default.png` ← note underscore in filename

**Use these for LEFT RAIL calibration only** — rail width, active-item highlight treatment (left-edge accent + tinted background), icon sizing, icon-to-label spacing, typography weight contrast (active vs inactive), and rail-to-content-column border/shadow separation. The visual hierarchy and rhythm should feel similar; exact pixel values will diverge because CiteWP uses Citrine `#E8D400` / Obsidian `#0C0C0D` / Plus Jakarta Sans where WP Rocket uses red / system font.

**Do NOT reference the content area.** WP Rocket's content uses a card-grid pattern which CiteWP explicitly rejects per P27. Content area design (single-column) follows `Brain/UI-DESIGN-SYSTEM.md`.

---

## File Map

| File | Action | Responsibility after this session |
|------|--------|-----------------------------------|
| `includes/Admin/Menu.php` | Modify | Single WP render callback `render_page()`; left rail + panels + inline JS; dashboard panel (P27 single-column) |
| `includes/Settings/Page.php` | Modify | Pure panel renderer (no `.wrap`, no nav header, no submenu registration) |
| `includes/Admin/LogsPage.php` | Modify | Pure panel renderer (no `.wrap`, no nav header); page detection updated to `SLUG_PARENT` |
| `admin/css/citewp-aiso-admin.css` | Modify | Add left rail layout + panel visibility + stat-row Dashboard styles |
| `includes/Admin/PageHeader.php` | Delete | Obsolete — wordmark inlined in `Menu.php`; all callers updated |

---

## Task 1 — Strip `Settings/Page.php` to a pure panel renderer

**Files:**
- Modify: `includes/Settings/Page.php`

This task removes the WP submenu registration, strips the `.wrap` page wrapper and `PageHeader` nav call from `render()`, updates the `handle_regenerate()` redirect to use `Menu::SLUG_PARENT` + `citewp_section=settings`, and updates the inline JS to preserve `#settings` as the outer hash while storing inner tab state in `localStorage`.

- [ ] **Step 1: Remove `add_submenu()` method and its `admin_menu` hook from `register()`**

In `register()`, remove the `add_action('admin_menu', ...)` line. Delete the `add_submenu()` method entirely (lines 25 + 30–39 of the current file).

`register()` after change:
```php
public function register(): void {
    add_action( 'admin_init', [ $this, 'register_settings' ] );
    add_action( 'admin_post_citewp_aiso_regenerate_llms', [ $this, 'handle_regenerate' ] );
}
```

- [ ] **Step 2: Remove `use` imports for `PageHeader` and `Menu` (re-add `Menu` with correct path)**

Current imports at top of file:
```php
use CiteWP\Aiso\Admin\Menu;
use CiteWP\Aiso\Admin\PageHeader;
use CiteWP\Aiso\Llms\Cache;
```

`PageHeader` is no longer called. `Menu` is still needed for `Menu::SLUG_PARENT` in `handle_regenerate()`. Remove `PageHeader` import only:
```php
use CiteWP\Aiso\Admin\Menu;
use CiteWP\Aiso\Llms\Cache;
```

- [ ] **Step 3: Strip `.wrap` wrapper and `PageHeader::render_nav()` from `render()`**

`render()` currently opens with:
```php
?>
<div class="wrap">

    <?php PageHeader::render_nav( self::SLUG ); ?>
```
and closes with:
```php
</div><!-- .wrap -->
```

Remove those three lines. The method now opens directly with the notices and settings tabs. The `.wrap` context is provided by `Menu::render_page()` outer wrapper.

- [ ] **Step 4: Update `handle_regenerate()` redirect to use parent slug + section param**

Replace the `wp_safe_redirect()` call:
```php
wp_safe_redirect(
    add_query_arg(
        [ 'page' => self::SLUG, 'regenerated' => '1' ],
        admin_url( 'admin.php' )
    )
);
```
With:
```php
wp_safe_redirect(
    add_query_arg(
        [ 'page' => Menu::SLUG_PARENT, 'regenerated' => '1', 'citewp_section' => 'settings' ],
        admin_url( 'admin.php' )
    )
);
```

- [ ] **Step 5: Update inline JS to preserve `#settings` outer hash and use `localStorage` for inner tab state**

Replace the existing `<script>` block at the bottom of `render()` with:
```javascript
<script>
(function () {
    var tabs   = document.querySelectorAll( '.citewp-aiso-tabs__btn' );
    var panels = document.querySelectorAll( '.citewp-aiso-tabs__panel' );
    var LS_KEY = 'citewp_aiso_settings_tab';

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

    // Restore from localStorage, fall back to first tab.
    var stored  = localStorage.getItem( LS_KEY );
    var initial = stored && document.getElementById( 'citewp-aiso-tab-' + stored )
        ? stored
        : <?php echo wp_json_encode( (string) $default_tab ); ?>;
    activate( initial );

    tabs.forEach( function ( btn ) {
        btn.addEventListener( 'click', function () {
            var slug = btn.dataset.tab;
            localStorage.setItem( LS_KEY, slug );
            activate( slug );
        } );
    } );
}() );
</script>
```

Key change: `history.replaceState` is removed. Tab state is persisted to `localStorage` instead of the URL hash, so the outer `#settings` hash remains intact when a user clicks a settings inner tab.

- [ ] **Step 6: Verify `public const SLUG` is still defined (do not remove it)**

`self::SLUG` is used in `register_settings()` (options group), `sanitize_core()`, etc. Keep it. It is no longer a WP admin menu slug but remains a valid PHP constant used internally.

- [ ] **Step 7: Commit**

```bash
git add includes/Settings/Page.php
git commit -m "refactor: strip submenu registration and page wrapper from Settings/Page — pure panel renderer"
```

---

## Task 2 — Strip `LogsPage.php` to a pure panel renderer

**Files:**
- Modify: `includes/Admin/LogsPage.php`

- [ ] **Step 1: Remove `use CiteWP\Aiso\Admin\PageHeader` import**

Remove the line:
```php
use CiteWP\Aiso\Admin\PageHeader;
```

- [ ] **Step 2: Update `maybe_init_table()` page detection**

Currently checks:
```php
if ( $page !== Menu::SLUG_LOGS ) {
    return;
}
```

Change to:
```php
if ( $page !== Menu::SLUG_PARENT ) {
    return;
}
```

All panels are always in the DOM when on the CiteWP page, so `WP_List_Table` must be prepared whenever `page=citewp` — not just for a specific (now removed) SLUG_LOGS submenu.

- [ ] **Step 3: Strip `.wrap` wrapper and `PageHeader::render_nav()` from `render()`**

Remove:
```php
<div class="wrap">

    <?php PageHeader::render_nav( Menu::SLUG_LOGS ); ?>

    <div class="citewp-aiso-page-body">
```
and its corresponding closing `</div><!-- .citewp-aiso-page-body -->` + `</div><!-- .wrap -->`.

The method now opens directly with the stats banner. `Menu::render_page()` provides the outer wrapper.

- [ ] **Step 4: Update list table form — change hidden `page` input and add `citewp_section` field**

Replace:
```php
<form method="get">
    <input type="hidden" name="page" value="<?php echo esc_attr( Menu::SLUG_LOGS ); ?>" />
    <?php $this->table->display(); ?>
</form>
```
With:
```php
<form method="get">
    <input type="hidden" name="page" value="<?php echo esc_attr( Menu::SLUG_PARENT ); ?>" />
    <input type="hidden" name="citewp_section" value="crawler-logs" />
    <?php $this->table->display(); ?>
</form>
```

The `citewp_section` field ensures the outer JS section resolver restores the Crawler Logs panel after filter form submission.

- [ ] **Step 5: Check `LogsTable.php` for any `SLUG_LOGS` references**

Run:
```bash
grep -n "SLUG_LOGS" "includes/Admin/LogsTable.php"
```

If any references exist, update them to `Menu::SLUG_PARENT`. (The pagination links `WP_List_Table` generates base on `$_SERVER['REQUEST_URI']` which will already be `admin.php?page=citewp`, so this is likely clean.)

- [ ] **Step 6: Commit**

```bash
git add includes/Admin/LogsPage.php
git commit -m "refactor: strip submenu registration and page wrapper from LogsPage — pure panel renderer"
```

---

## Task 3 — Rewrite `Menu.php` with single page render and left rail

**Files:**
- Modify: `includes/Admin/Menu.php`

This is the primary task. `Menu.php` gains `render_page()` (the WP render callback), `render_dashboard_panel()` (P27 single-column), and the `citewp_aiso/admin/nav` filter with extended render-callback schema. The two `add_submenu_page()` calls and the `$submenu` hack are removed.

- [ ] **Step 1: Update constants — remove `SLUG_LOGS` usage, keep constant defined**

`SLUG_LOGS` is no longer a WP menu slug. Keep it defined for now (LogsTable.php pagination may reference it indirectly). Add a comment marking it deprecated:

```php
public const SLUG_PARENT = 'citewp';
/** @deprecated No longer registered as a WP submenu slug. Kept for back-compat. */
public const SLUG_LOGS   = 'citewp-aiso-crawler-logs';
```

- [ ] **Step 2: Replace `add_menu()` — single `add_menu_page`, no submenus**

Replace the entire `add_menu()` method with:
```php
public function add_menu(): void {
    add_menu_page(
        __( 'CiteWP', 'ai-search-optimizer' ),
        __( 'CiteWP', 'ai-search-optimizer' ),
        'manage_options',
        self::SLUG_PARENT,
        [ $this, 'render_page' ],
        'dashicons-chart-line',
        81
    );
}
```

Removes: both `add_submenu_page()` calls, the `global $submenu` rename hack.

- [ ] **Step 3: Simplify `enqueue_assets()` — single screen hook**

Replace the screen detection block:
```php
$is_citewp_screen = (
    $hook === 'toplevel_page_' . self::SLUG_PARENT ||
    strpos( $hook, 'citewp_page_' ) === 0
);
```
With:
```php
$is_citewp_screen = ( $hook === 'toplevel_page_' . self::SLUG_PARENT );
```

No `citewp_page_*` hooks exist anymore — only one screen.

- [ ] **Step 4: Add `render_page()` — the single WP render callback**

Add this method after `enqueue_assets()`:

```php
public function render_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $defaults = [
        'dashboard' => [
            'label'  => __( 'Dashboard', 'ai-search-optimizer' ),
            'icon'   => 'dashicons-chart-line',
            'slug'   => 'dashboard',
            'render' => [ $this, 'render_dashboard_panel' ],
        ],
        'crawler-logs' => [
            'label'  => __( 'Crawler Logs', 'ai-search-optimizer' ),
            'icon'   => 'dashicons-list-view',
            'slug'   => 'crawler-logs',
            'render' => [ \CiteWP\Aiso\Plugin::instance()->module( 'admin_logs_page' ), 'render' ],
        ],
        'settings' => [
            'label'  => __( 'Settings', 'ai-search-optimizer' ),
            'icon'   => 'dashicons-admin-settings',
            'slug'   => 'settings',
            'render' => [ \CiteWP\Aiso\Plugin::instance()->module( 'settings_page' ), 'render' ],
        ],
        'pro' => [
            'label'    => __( 'Pro ↗', 'ai-search-optimizer' ),
            'icon'     => '',
            'slug'     => 'pro',
            'external' => true,
            'href'     => 'https://citewp.com',
        ],
    ];

    /**
     * Filters the CiteWP admin navigation items.
     *
     * Each item is an associative array with:
     *   label    (string)   Required. Nav label.
     *   icon     (string)   Required. Dashicon class (e.g. 'dashicons-chart-line'). Empty string for no icon.
     *   slug     (string)   Required. URL hash slug used for section routing (e.g. 'dashboard').
     *   render   (callable) For internal sections: a callable that outputs the panel HTML.
     *   external (bool)     Set true for external link-outs. Requires 'href'. No panel is rendered.
     *   href     (string)   URL for external items.
     *
     * Items with a 'render' callable register a panel in the main content area.
     * Items with 'external => true' render as link-outs in the rail only — no panel.
     * Registering a nav item with a 'render' callable is the only registration needed
     * (per X15 — FB28/FB30/FB34 register through this filter by appending an item with
     * their own render callable).
     *
     * @param array<string, array<string, mixed>> $items Navigation items keyed by an arbitrary identifier.
     */
    $items = apply_filters( 'citewp_aiso/admin/nav', $defaults );

    // Collect internal nav slugs for the JS resolver.
    $nav_slugs = [];
    foreach ( $items as $item ) {
        if ( ! empty( $item['slug'] ) && empty( $item['external'] ) ) {
            $nav_slugs[] = sanitize_key( $item['slug'] );
        }
    }

    $section_param  = sanitize_key( wp_unslash( $_GET['citewp_section']   ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display routing param; no data modification.
    $settings_saved = sanitize_key( wp_unslash( $_GET['settings-updated'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Standard WP options-saved flag; read-only.
    ?>
    <div class="wrap">

        <div class="citewp-aiso-header">
            <span class="citewp-aiso-header__wordmark">[CiteWP]</span>
        </div>

        <div class="citewp-aiso-page">

            <nav class="citewp-aiso-rail" aria-label="<?php esc_attr_e( 'CiteWP sections', 'ai-search-optimizer' ); ?>">
                <?php foreach ( $items as $item ) :
                    if ( ! isset( $item['label'], $item['slug'] ) ) {
                        continue;
                    }
                    $external = ! empty( $item['external'] );
                    $href     = $external
                        ? ( $item['href'] ?? '#' )
                        : '#' . sanitize_key( $item['slug'] );
                    $classes  = 'citewp-aiso-rail__item';
                    if ( $external ) {
                        $classes .= ' citewp-aiso-rail__item--external';
                    }
                ?>
                <a
                    href="<?php echo esc_url( $href ); ?>"
                    class="<?php echo esc_attr( $classes ); ?>"
                    data-panel="<?php echo esc_attr( $item['slug'] ); ?>"
                    <?php if ( $external ) : ?>
                        target="_blank"
                        rel="noopener noreferrer"
                    <?php endif; ?>
                ><?php if ( ! empty( $item['icon'] ) ) : ?><span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>" aria-hidden="true"></span><?php endif; ?><?php echo esc_html( $item['label'] ); ?></a>
                <?php endforeach; ?>
            </nav>

            <div class="citewp-aiso-main">
                <?php foreach ( $items as $item ) :
                    if ( empty( $item['render'] ) || ! is_callable( $item['render'] ) ) {
                        continue;
                    }
                ?>
                <div class="citewp-aiso-panel" data-panel="<?php echo esc_attr( $item['slug'] ); ?>">
                    <?php call_user_func( $item['render'] ); ?>
                </div>
                <?php endforeach; ?>
            </div><!-- .citewp-aiso-main -->

        </div><!-- .citewp-aiso-page -->

    </div><!-- .wrap -->

    <script>
    (function () {
        var navItems = document.querySelectorAll( '.citewp-aiso-rail__item[data-panel]' );
        var panels   = document.querySelectorAll( '.citewp-aiso-panel[data-panel]' );
        var known    = <?php echo wp_json_encode( array_values( $nav_slugs ) ); ?>;
        var SS_KEY   = 'citewp_aiso_section';

        function activate( slug ) {
            navItems.forEach( function ( item ) {
                var active = item.dataset.panel === slug;
                item.classList.toggle( 'citewp-aiso-rail__item--active', active );
                if ( active ) {
                    item.setAttribute( 'aria-current', 'page' );
                } else {
                    item.removeAttribute( 'aria-current' );
                }
            } );
            panels.forEach( function ( panel ) {
                var active = panel.dataset.panel === slug;
                panel.classList.toggle( 'citewp-aiso-panel--active', active );
            } );
            try { sessionStorage.setItem( SS_KEY, slug ); } catch ( e ) {}
        }

        function resolveSlug() {
            // 1. URL hash (explicit nav click, bookmark).
            var hash = location.hash.replace( '#', '' );
            if ( hash && known.indexOf( hash ) !== -1 ) { return hash; }

            // 2. citewp_section query param (settings regenerate redirect).
            var section = <?php echo wp_json_encode( $section_param ); ?>;
            if ( section && known.indexOf( section ) !== -1 ) { return section; }

            // 3. settings-updated flag (WP options.php save redirect).
            if ( <?php echo wp_json_encode( $settings_saved ); ?> !== '' ) { return 'settings'; }

            // 4. sessionStorage (restores section after pagination / filter submits).
            try {
                var stored = sessionStorage.getItem( SS_KEY );
                if ( stored && known.indexOf( stored ) !== -1 ) { return stored; }
            } catch ( e ) {}

            // 5. Default to first internal nav item.
            return known[0] || 'dashboard';
        }

        var initial = resolveSlug();
        if ( location.hash !== '#' + initial ) {
            history.replaceState( null, '', '#' + initial );
        }
        activate( initial );

        window.addEventListener( 'hashchange', function () {
            var hash = location.hash.replace( '#', '' );
            if ( known.indexOf( hash ) !== -1 ) { activate( hash ); }
        } );

        navItems.forEach( function ( item ) {
            if ( item.classList.contains( 'citewp-aiso-rail__item--external' ) ) { return; }
            item.addEventListener( 'click', function ( e ) {
                e.preventDefault();
                var slug = item.dataset.panel;
                history.pushState( null, '', '#' + slug );
                activate( slug );
            } );
        } );
    }() );
    </script>
    <?php
}
```

- [ ] **Step 5: Add `render_dashboard_panel()` — P27 single-column Dashboard**

Add this private method. Replaces the old `render_dashboard()` with a panel-level renderer (no `.wrap` wrapper) and single-column layout (stat row + needs-attention list, no 3-col card grid):

```php
private function render_dashboard_panel(): void {
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

    $logs_url = admin_url( 'admin.php?page=' . self::SLUG_PARENT . '#crawler-logs' );

    /**
     * Filters extra dashboard cards rendered after the built-in summary.
     *
     * Each card: title (string), value (string), description (string, optional),
     * link_url (string, optional), link_label (string, optional).
     *
     * @param array<int, array<string, string>> $cards
     */
    $extra_cards = apply_filters( 'citewp_aiso/dashboard/cards', [] );
    ?>
    <div class="citewp-aiso-page-body">

        <h2 class="citewp-aiso-panel__title"><?php esc_html_e( 'Dashboard', 'ai-search-optimizer' ); ?></h2>

        <!-- Inline stat row — P27 single-column (no card grid) -->
        <div class="citewp-aiso-stat-row">

            <div class="citewp-aiso-stat-row__item">
                <?php if ( $avg_score !== null ) : ?>
                    <svg class="citewp-aiso-gauge" width="80" height="44" viewBox="0 0 120 66" aria-hidden="true">
                        <path class="citewp-aiso-gauge__track" d="M 6,60 A 54,54 0 0,1 114,60" />
                        <path
                            class="citewp-aiso-gauge__fill citewp-aiso-gauge__fill--<?php echo esc_attr( $avg_grade ); ?>"
                            d="M 6,60 A 54,54 0 0,1 114,60"
                            stroke-dasharray="<?php echo esc_attr( (string) round( $circumference, 2 ) ); ?>"
                            stroke-dashoffset="<?php echo esc_attr( (string) round( $offset, 2 ) ); ?>"
                        />
                        <text x="60" y="56" class="citewp-aiso-gauge__score-text"><?php echo esc_html( (string) $avg_score ); ?></text>
                    </svg>
                    <span class="screen-reader-text"><?php echo esc_html( sprintf( __( 'Average Cite Score: %d out of 100', 'ai-search-optimizer' ), $avg_score ) ); ?></span>
                <?php else : ?>
                    <span class="citewp-aiso-stat-row__value citewp-aiso-stat-row__value--empty">—</span>
                <?php endif; ?>
                <span class="citewp-aiso-stat-row__label"><?php esc_html_e( 'Avg Cite Score', 'ai-search-optimizer' ); ?></span>
            </div>

            <div class="citewp-aiso-stat-row__item">
                <span class="citewp-aiso-stat-row__value"><?php echo esc_html( number_format_i18n( $trend['this_week'] ) ); ?></span>
                <span class="citewp-aiso-stat-row__label">
                    <?php esc_html_e( 'Bot visits (7d)', 'ai-search-optimizer' ); ?>
                    <?php
                    $diff = $trend['this_week'] - $trend['last_week'];
                    if ( $trend['last_week'] > 0 && $diff !== 0 ) :
                        $arrow = $diff > 0 ? '▲' : '▼';
                        echo ' ' . esc_html( $arrow . ' ' . number_format_i18n( abs( $diff ) ) );
                    endif;
                    ?>
                </span>
            </div>

        </div><!-- .citewp-aiso-stat-row -->

        <!-- Needs Attention -->
        <div class="citewp-aiso-section">
            <div class="citewp-aiso-section__header">
                <h3 class="citewp-aiso-section__title"><?php esc_html_e( 'Needs Attention', 'ai-search-optimizer' ); ?></h3>
                <p class="citewp-aiso-section__desc"><?php esc_html_e( 'Posts with the lowest Cite Scores.', 'ai-search-optimizer' ); ?></p>
            </div>
            <div class="citewp-aiso-section__body">
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
                    <div class="citewp-aiso-empty">
                        <p class="citewp-aiso-empty__title"><?php esc_html_e( 'No scored posts yet.', 'ai-search-optimizer' ); ?></p>
                        <p class="citewp-aiso-empty__desc"><?php esc_html_e( 'Open any post to trigger scoring.', 'ai-search-optimizer' ); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <p class="citewp-aiso-quick-actions">
            <a href="<?php echo esc_url( $logs_url ); ?>" class="button"><?php esc_html_e( 'View Crawler Logs', 'ai-search-optimizer' ); ?></a>
        </p>

        <!-- Extra cards from filter (Pro / extensions — appears only when filter returns items) -->
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

    </div><!-- .citewp-aiso-page-body -->
    <?php
}
```

- [ ] **Step 6: Delete the old `render_dashboard()` method**

Remove the entire `render_dashboard()` method (lines 75–225 in the current file). It is fully replaced by `render_page()` + `render_dashboard_panel()`.

- [ ] **Step 7: Verify `use` statement — `Repository` import still present, `use` for `Plugin` not needed (same namespace prefix used inline)**

`Menu.php` currently has only:
```php
use CiteWP\Aiso\Scoring\Repository;
```
`Repository::META_KEY_TOTAL` and `Repository::META_KEY_GRADE` are used in `render_dashboard_panel()`. Keep it. `\CiteWP\Aiso\Plugin` is referenced with a fully-qualified backslash so no `use` statement needed.

- [ ] **Step 8: Commit**

```bash
git add includes/Admin/Menu.php
git commit -m "feat: add single-page render with left rail + hash dispatch to Menu (P27/P28/P29)"
```

---

## Task 4 — Add left rail CSS to `admin/css/citewp-aiso-admin.css`

**Files:**
- Modify: `admin/css/citewp-aiso-admin.css`

Append the following block at the end of the file. Existing `.citewp-aiso-nav` / `.citewp-aiso-nav__item` styles are kept for backward compatibility (external filter consumers may reference them) but are no longer generated by plugin PHP.

- [ ] **Pre-step: Read all 5 reference images before writing any CSS**

Read each image in `C:\Users\KingpinBWP\Desktop\CiteWP\Brain\design-reference\`:
- `wp-rocket-dashboard-default.png`
- `wp-rocket-insights-default.png`
- `wp-rocket-addons-default.png`
- `wp-rocket-tutorials-default.png`
- `wp-rocket-our_plugins-default.png`

Calibrate the following from the screenshots, then adjust the CSS values in Step 1 before writing:

| Property | Spec default | Calibrate against screenshots |
|----------|-------------|-------------------------------|
| Rail width | 192px | Verify ~200px target; adjust if WP Rocket's visual rhythm suggests different |
| Rail item vertical padding | 10px top/bottom | Verify item height feels right relative to item count |
| Rail item horizontal padding | 16px (`--citewp-space-3`) | Verify left/right breathing room |
| Active item left-edge accent width | 3px | Verify vs WP Rocket accent thickness |
| Active item background tint | `#fffde6` (light Citrine) | Verify tint is visible but not heavy — WP Rocket uses a subtle grey tint |
| Icon size | 16px | Verify icons don't overwhelm label text |
| Icon-to-label gap | 8px (`--citewp-space-2`) | Verify spacing feels balanced |
| Rail-to-content separator | `border-right` on rail or `gap` only | Verify whether WP Rocket uses a hard border or relies on whitespace gap |
| Typography weight active vs inactive | 600 vs 400 | Verify this contrast reads clearly at 13px |

Goal: same feel, not pixel-exact copy. CiteWP's Citrine tint will look different from WP Rocket's grey — adjust tint opacity/value so the active state reads clearly against the white rail background.

- [ ] **Step 1: Append left rail layout + panel visibility block**

Append to end of `admin/css/citewp-aiso-admin.css`:

```css
/* =========================================================================
   Left Rail Page Layout — Session 13 (P27 / P28 / P29)
   ========================================================================= */

/* Outer flex container: left rail + main content */
.citewp-aiso-page {
    display: flex;
    gap: var(--citewp-space-4);
    margin-top: var(--citewp-space-4);
    align-items: flex-start;
}

/* Left navigation rail */
.citewp-aiso-rail {
    flex: 0 0 192px;
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    overflow: hidden;
}

.citewp-aiso-rail__item {
    display: flex;
    align-items: center;
    gap: var(--citewp-space-2);
    padding: 10px var(--citewp-space-3);
    font-size: var(--citewp-font-sm);
    font-weight: 400;
    color: #50575e;
    text-decoration: none;
    border-left: 3px solid transparent;
    border-bottom: 1px solid #f0f0f1;
    transition: color 0.1s ease, background 0.1s ease;
}

.citewp-aiso-rail__item:last-child {
    border-bottom: none;
}

.citewp-aiso-rail__item:hover,
.citewp-aiso-rail__item:focus {
    color: #1d2327;
    background: #f6f7f7;
    text-decoration: none;
}

.citewp-aiso-rail__item--active {
    border-left-color: var(--citewp-citrine);
    background: #fffde6;
    color: var(--citewp-obsidian);
    font-weight: 600;
}

.citewp-aiso-rail__item--external {
    color: #787c82;
    font-size: var(--citewp-font-xs);
    margin-top: var(--citewp-space-2);
}

.citewp-aiso-rail__item .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
    flex-shrink: 0;
    color: inherit;
}

/* Main content area */
.citewp-aiso-main {
    flex: 1;
    min-width: 0;
}

/* Section panels — hidden by default, shown when --active */
.citewp-aiso-panel {
    display: none;
}

.citewp-aiso-panel--active {
    display: block;
}

/* Panel title (h2 inside each panel) */
.citewp-aiso-panel__title {
    font-size: var(--citewp-font-xl);
    font-weight: 600;
    color: #1d2327;
    margin: 0 0 var(--citewp-space-4);
    padding: 0;
}

/* =========================================================================
   Single-Column Dashboard Stat Row — P27
   ========================================================================= */

.citewp-aiso-stat-row {
    display: flex;
    gap: var(--citewp-space-5);
    align-items: center;
    padding-bottom: var(--citewp-space-4);
    margin-bottom: var(--citewp-space-4);
    border-bottom: 1px solid #f0f0f1;
}

.citewp-aiso-stat-row__item {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: var(--citewp-space-1);
}

.citewp-aiso-stat-row__value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 28px;
    font-weight: 400;
    color: #1d2327;
    line-height: 1;
}

.citewp-aiso-stat-row__value--empty {
    color: #c3c4c7;
}

.citewp-aiso-stat-row__label {
    font-size: var(--citewp-font-xs);
    color: #787c82;
}

/* Gauge when used inside stat row (smaller viewBox, no extra margin) */
.citewp-aiso-stat-row__item .citewp-aiso-gauge {
    display: block;
    margin: 0;
}

/* Responsive: stack rail below content at narrow widths */
@media screen and (max-width: 782px) {
    .citewp-aiso-page {
        flex-direction: column;
    }
    .citewp-aiso-rail {
        flex: 0 0 auto;
        width: 100%;
    }
}
```

- [ ] **Step 2: Verify `.citewp-aiso-header` still shows wordmark only (no nav items)**

The existing `.citewp-aiso-header` CSS has `justify-content: space-between` (for wordmark + nav bar). After the refactor, the header only contains the wordmark span. No nav items are rendered inside it. No CSS change needed — `justify-content: space-between` with a single child is harmless.

- [ ] **Step 3: Commit**

```bash
git add admin/css/citewp-aiso-admin.css
git commit -m "feat: add left rail layout, panel visibility, and stat-row styles for admin refactor"
```

---

## Task 5 — Delete `PageHeader.php`

**Files:**
- Delete: `includes/Admin/PageHeader.php`

All callers (`LogsPage.php` — Task 2, `Settings/Page.php` — Task 1) have had their imports and `render_nav()` calls removed. `Menu.php` never imported it directly (same namespace). The wordmark is now inlined in `Menu::render_page()`.

- [ ] **Step 1: Confirm no remaining references**

```bash
grep -rn "PageHeader" includes/
```

Expected output: no matches. If any remain, trace and remove before deleting the file.

- [ ] **Step 2: Delete the file**

```bash
rm "includes/Admin/PageHeader.php"
```

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "chore: delete PageHeader — wordmark inlined in Menu::render_page()"
```

---

## Task 6 — Manual browser verification

**No code changes. Verification only.**

- [ ] **Step 1: Confirm WP admin left sidebar has ONE "CiteWP" item with no sub-items**

Navigate to `wp-admin/`. Verify: WP left sidebar shows "CiteWP" as a single item with no expansion arrow and no nested Crawler Logs / Settings sub-items.

- [ ] **Step 2: Verify initial load defaults to Dashboard section**

Navigate to `admin.php?page=citewp` (no hash). Verify: Dashboard panel is active, URL updates to `admin.php?page=citewp#dashboard`, Dashboard rail item has Citrine left border.

- [ ] **Step 3: Verify Dashboard panel is single-column (P27)**

Confirm: stat row (Avg Cite Score gauge + Bot visits count) is visible. No 3-column card grid. Needs Attention list below. "View Crawler Logs" quick action button at bottom.

- [ ] **Step 4: Verify left rail navigation between sections**

Click "Crawler Logs" in the rail. Verify: Crawler Logs panel becomes visible, URL updates to `#crawler-logs`, Dashboard panel hides. Click "Settings". Verify Settings panel with tabs appears. Click "Dashboard". Verify Dashboard reappears.

- [ ] **Step 5: Verify `Pro ↗` opens in a new tab**

Click "Pro ↗" in the rail. Verify: opens `https://citewp.com` in a new browser tab. Current CiteWP page stays open.

- [ ] **Step 6: Verify page refresh restores section**

While on `#crawler-logs`, press F5. Verify: page reloads and Crawler Logs panel is active (restored from URL hash).

- [ ] **Step 7: Verify Settings inner tabs still work**

Navigate to `#settings`. Click "Crawler Detection" tab. Verify it activates. Refresh page — verify the same tab is restored (from `localStorage`). Hash in URL should still be `#settings`, not `#crawler-detection`.

- [ ] **Step 8: Verify Settings save redirects back to Settings section**

Change any setting value. Click "Save Changes". Verify: page reloads, Settings panel is active (not Dashboard), success notice "Settings saved." is visible.

- [ ] **Step 9: Verify "Regenerate llms.txt" button redirects back to Settings**

Click "Regenerate now" in Settings → General tab. Verify: page reloads, Settings panel active, success notice "llms.txt cache cleared." visible.

- [ ] **Step 10: Verify Crawler Logs renders correctly**

Navigate to `#crawler-logs`. Verify: stats banner (Last 24h / Last 7d / Last 30d / Export CSV) visible. Table present (or empty-state notice if no logs). No PHP errors in debug.log.

- [ ] **Step 11: Verify Crawler Logs filter form keeps section**

If logs exist: apply a date filter. Verify: after form submit, Crawler Logs panel is still active (not Dashboard). The hidden `citewp_section=crawler-logs` field in the form handles this via the JS resolver.

- [ ] **Step 12: Verify no PHP errors in debug.log**

```bash
# In LocalWP terminal — check for new errors after each page visit
# Path: C:\Users\KingpinBWP\Local Sites\citewp-dev\logs\php\error.log
# OR: C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\debug.log
```

Verify: no new PHP errors or warnings from `CiteWP\Aiso\Admin\*` or `CiteWP\Aiso\Settings\*`.

- [ ] **Step 13: Run npm build (should be clean — no JS source changes)**

```bash
npm run build
```

Expected: exits 0. `build/index.js` may be identical if no `src/` files changed.

- [ ] **Step 14: Final commit if any debug fixes were needed; then push**

```bash
git push origin main
```

---

## Self-Review Checklist

### Spec Coverage

| Requirement | Task |
|-------------|------|
| Single `add_menu_page`, no `add_submenu_page` | Task 3 Step 2 |
| Left rail nav inside plugin page | Task 3 Step 4 + Task 4 |
| URL hash dispatch (`#dashboard`, `#crawler-logs`, `#settings`) | Task 3 Step 4 (JS) |
| JS `hashchange` handler | Task 3 Step 4 (JS) |
| All sections in DOM, `display:none` toggled by JS | Task 3 Step 4 (PHP panels) + Task 4 (CSS) |
| Default to `#dashboard` when hash absent | Task 3 Step 4 (JS `resolveSlug`) |
| Single-column Dashboard content (P27) | Task 3 Step 5 |
| Stat row replacing 3-col card grid | Task 3 Step 5 + Task 4 |
| Render-callback schema on `citewp_aiso/admin/nav` | Task 3 Step 4 (filter docblock) |
| FB28/FB30/FB34 extensibility via render callback | Task 3 Step 4 (filter docblock, pattern enforced by schema) |
| `citewp_aiso/dashboard/cards` filter preserved | Task 3 Step 5 |
| Settings inner tabs use `localStorage` (no hash conflict) | Task 1 Step 5 |
| Settings save restores Settings panel | Task 1 Step 4 + Task 3 Step 4 JS (settings-updated check) |
| Crawler Logs filter form restores section | Task 2 Step 4 + Task 3 Step 4 JS (citewp_section check) |
| `PageHeader.php` deleted | Task 5 |
| UI-DESIGN-SYSTEM.md left rail spec update | **Not in code tasks — update spec doc after browser verification** |

**UI-DESIGN-SYSTEM.md update** (run after Task 6 passes): The spec already documents the render-callback pattern (added pre-plan by the user). No additional update needed — confirmed spec is current.

### Known Limitation

WP_List_Table pagination links are generated by `get_pagenum_link()` based on `$_SERVER['REQUEST_URI']` and will NOT include the `citewp_section` query param. Clicking a pagination link goes to `admin.php?page=citewp&paged=N` with no hash. The JS resolver's sessionStorage fallback (step 4 in `resolveSlug()`) recovers this: the last active section is stored on each `activate()` call and restored on the next page load. This means pagination restores the section correctly as long as the user navigated to Crawler Logs via the rail (which sets sessionStorage) before paginating.
