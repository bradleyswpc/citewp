# EditorPanel v3 Polish + Bot Visits Widget Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rewrite `EditorPanel.php` to add a 2-column General tab (score left / Bot Visits right), migrate all hardcoded hex values to v3 CSS tokens, add category bar fill, add the `$context` arg to the tabs filter, and render per-post AI crawler activity from `wp_citewp_aiso_crawler_logs`.

**Architecture:** All styles move from the `inline_styles()` PHP method to a new section in `admin/css/citewp-aiso-admin.css`, enqueued on `post.php`/`post-new.php` via a new `enqueue_styles()` method on `EditorPanel`. The General tab panel becomes a CSS grid (45% left / 55% right) — 45/55 favors the Bot Visits table which needs horizontal room; if browser verification shows the Bot Visits column visually overpowering the score, revert to 50/50 (see Task 5 Step 2). Bot Visits data comes from a direct `$wpdb` query — no new REST endpoints, no schema changes.

**Tech Stack:** PHP 8.0+, WordPress 6.5+, `$wpdb->prepare()`, `human_time_diff()`, plain CSS grid (no JS framework).

---

## File Map

| File | Action | What changes |
|------|--------|-------------|
| `includes/Admin/EditorPanel.php` | Modify | Add `enqueue_styles()`, remove `inline_styles()`, split filter call, add bar fill, add Bot Visits methods, rewrite `render_general_tab()` |
| `admin/css/citewp-aiso-admin.css` | Modify | Append EditorPanel v3 CSS section (tokens + all EditorPanel styles) |

No new files. No REST endpoints. No JS build step. No `Engine.php` touch.

---

## Task 1: Migrate EditorPanel styles to admin CSS + add enqueue

**Purpose:** Move all EditorPanel `<style>` output from `inline_styles()` into `admin/css/citewp-aiso-admin.css` where it belongs, enqueue the CSS on post edit screens, and delete the `inline_styles()` method. After this task the meta box will look exactly as before (tabs work, score displays) but styles come from the CSS file with v3 tokens.

**Files:**
- Modify: `admin/css/citewp-aiso-admin.css` (append after line 2430)
- Modify: `includes/Admin/EditorPanel.php` (lines 12–16: register, lines 347–387: inline_styles)

- [ ] **Step 1: Append the EditorPanel v3 CSS section to admin.css**

Open `admin/css/citewp-aiso-admin.css`. After the last line (line 2430, `.citewp-aiso-cs-recs-btn { ... }`), append:

```css

/* =============================================================================
   EditorPanel v3 — meta box (post.php / post-new.php)
   Token definitions are scoped here; not shared with Gutenberg sidebar.
   ============================================================================= */

#citewp_aiso_editor_panel {
	--citewp-navy: #1e2a3b;
	--citewp-citrine: #e8d400;
	--citewp-border: #e5e7eb;
	--citewp-text-muted: #9ca3af;
	--citewp-text-secondary: #374151;
	--citewp-text-primary: #111827;
	--citewp-score-green: #00A32A;
	--citewp-score-yellow: #DBA617;
	--citewp-score-orange: #D63638;
	--citewp-score-red: #8C1B1B;
}

/* --- Tabs ------------------------------------------------------------------ */
#citewp_aiso_editor_panel .citewp-aiso-ep__tabs {
	display: flex;
	gap: 0;
	border-bottom: 2px solid var(--citewp-border);
	margin-bottom: 12px;
}
#citewp_aiso_editor_panel .citewp-aiso-ep__tab {
	background: none;
	border: none;
	border-bottom: 2px solid transparent;
	margin-bottom: -2px;
	padding: 8px 16px;
	font-size: 13px;
	font-weight: 600;
	color: var(--citewp-text-muted);
	cursor: pointer;
}
#citewp_aiso_editor_panel .citewp-aiso-ep__tab.is-active {
	color: var(--citewp-navy);
	border-bottom-color: var(--citewp-citrine);
}
#citewp_aiso_editor_panel .citewp-aiso-ep__panel { display: none; }
#citewp_aiso_editor_panel .citewp-aiso-ep__panel.is-active { display: block; }

/* --- 2-column General tab grid --------------------------------------------- */
#citewp_aiso_editor_panel .citewp-aiso-ep-columns {
	display: grid;
	grid-template-columns: 45% 55%;
	min-height: 160px;
}
#citewp_aiso_editor_panel .citewp-aiso-ep-col-left {
	padding-right: 24px;
}
#citewp_aiso_editor_panel .citewp-aiso-ep-col-right {
	border-left: 1px solid var(--citewp-border);
	padding-left: 24px;
	display: flex;
	flex-direction: column;
}

/* --- Score badge ----------------------------------------------------------- */
#citewp_aiso_editor_panel .citewp-aiso-mb-score {
	display: flex;
	align-items: baseline;
	gap: 6px;
	margin-bottom: 10px;
}
#citewp_aiso_editor_panel .citewp-aiso-mb-badge {
	font-size: 48px;
	font-weight: 800;
	line-height: 1;
	font-family: 'JetBrains Mono', monospace;
}
#citewp_aiso_editor_panel .citewp-aiso-mb-badge--green  { color: var(--citewp-score-green); }
#citewp_aiso_editor_panel .citewp-aiso-mb-badge--yellow { color: var(--citewp-score-yellow); }
#citewp_aiso_editor_panel .citewp-aiso-mb-badge--orange { color: var(--citewp-score-orange); }
#citewp_aiso_editor_panel .citewp-aiso-mb-badge--red    { color: var(--citewp-score-red); }
#citewp_aiso_editor_panel .citewp-aiso-mb-total-label {
	font-size: 13px;
	color: var(--citewp-text-muted);
}

/* --- Category rows with bar fill ------------------------------------------ */
#citewp_aiso_editor_panel .citewp-aiso-mb-categories {
	border-top: 1px solid var(--citewp-border);
	padding-top: 8px;
	margin-bottom: 8px;
}
#citewp_aiso_editor_panel .citewp-aiso-mb-cat-row {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 3px 0;
	font-size: 12px;
}
#citewp_aiso_editor_panel .citewp-aiso-mb-cat-label {
	color: var(--citewp-text-secondary);
	width: 72px;
	flex-shrink: 0;
}
#citewp_aiso_editor_panel .citewp-aiso-mb-cat-bar-wrap {
	flex: 1;
	height: 4px;
	background: var(--citewp-border);
	border-radius: 2px;
	overflow: hidden;
}
#citewp_aiso_editor_panel .citewp-aiso-mb-cat-bar-fill {
	height: 100%;
	background: var(--citewp-navy);
	border-radius: 2px;
	transition: width 0.3s ease;
}
#citewp_aiso_editor_panel .citewp-aiso-mb-cat-score {
	color: var(--citewp-text-primary);
	font-weight: 600;
	font-variant-numeric: tabular-nums;
	width: 36px;
	text-align: right;
	flex-shrink: 0;
}

/* --- Scored time, empty, action ------------------------------------------- */
#citewp_aiso_editor_panel .citewp-aiso-mb-time {
	font-size: 11px;
	color: var(--citewp-text-muted);
	margin: 0 0 8px;
}
#citewp_aiso_editor_panel .citewp-aiso-mb-empty {
	font-size: 12px;
	color: var(--citewp-text-muted);
	margin: 0 0 8px;
}
#citewp_aiso_editor_panel .citewp-aiso-mb-action { margin: 8px 0 0; }
#citewp_aiso_editor_panel .citewp-aiso-recalc-error {
	font-size: 12px;
	color: var(--citewp-score-orange);
	margin: 4px 0 0;
}

/* --- Schema tab ------------------------------------------------------------ */
#citewp_aiso_editor_panel .citewp-aiso-mb-schema-row { margin-bottom: 10px; }
#citewp_aiso_editor_panel .citewp-aiso-mb-schema-label {
	display: block;
	font-size: 12px;
	font-weight: 600;
	color: var(--citewp-text-secondary);
	margin-bottom: 4px;
}
#citewp_aiso_editor_panel .citewp-aiso-mb-detected {
	font-size: 12px;
	color: var(--citewp-score-green);
	font-weight: 600;
}
#citewp_aiso_editor_panel .citewp-aiso-mb-empty-note {
	font-size: 12px;
	color: var(--citewp-text-muted);
}
#citewp_aiso_editor_panel .citewp-aiso-copy-btn { font-size: 12px; }
#citewp_aiso_editor_panel .citewp-aiso-mb-copy-note {
	display: none;
	font-size: 11px;
	color: var(--citewp-text-muted);
	margin-top: 6px;
	line-height: 1.5;
}
#citewp_aiso_editor_panel .citewp-aiso-mb-other-types {
	font-size: 11px;
	color: var(--citewp-text-muted);
	margin: 8px 0 0;
	border-top: 1px solid var(--citewp-border);
	padding-top: 8px;
}

/* --- Bot Visits section ---------------------------------------------------- */
#citewp_aiso_editor_panel .citewp-aiso-bv {
	flex: 1;
	display: flex;
	flex-direction: column;
}
#citewp_aiso_editor_panel .citewp-aiso-bv__header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	border-top: 1px solid var(--citewp-border);
	padding-top: 10px;
	margin-bottom: 10px;
}
#citewp_aiso_editor_panel .citewp-aiso-bv__title-wrap {
	display: flex;
	align-items: center;
	gap: 6px;
}
#citewp_aiso_editor_panel .citewp-aiso-bv__icon {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 20px;
	height: 20px;
	background: var(--citewp-navy);
	border-radius: 3px;
	flex-shrink: 0;
}
#citewp_aiso_editor_panel .citewp-aiso-bv__icon svg {
	width: 12px;
	height: 12px;
}
#citewp_aiso_editor_panel .citewp-aiso-bv__title {
	font-size: 13px;
	font-weight: 700;
	color: var(--citewp-text-primary);
}
#citewp_aiso_editor_panel .citewp-aiso-bv__pill {
	font-size: 11px;
	color: var(--citewp-text-muted);
	background: #f3f4f6;
	padding: 2px 8px;
	border-radius: 10px;
}

/* --- Bot Visits table ------------------------------------------------------ */
#citewp_aiso_editor_panel .citewp-aiso-bv__table {
	width: 100%;
	border-collapse: collapse;
	font-size: 12px;
	flex: 1;
}
#citewp_aiso_editor_panel .citewp-aiso-bv__table th {
	text-align: left;
	font-weight: 600;
	color: var(--citewp-text-muted);
	font-size: 11px;
	padding: 0 0 6px;
	border-bottom: 1px solid var(--citewp-border);
}
#citewp_aiso_editor_panel .citewp-aiso-bv__table th:last-child,
#citewp_aiso_editor_panel .citewp-aiso-bv__table td:last-child { text-align: right; }
#citewp_aiso_editor_panel .citewp-aiso-bv__table td {
	padding: 5px 0;
	color: var(--citewp-text-secondary);
	border-bottom: 1px solid var(--citewp-border);
}
#citewp_aiso_editor_panel .citewp-aiso-bv__table tr:last-child td { border-bottom: none; }
#citewp_aiso_editor_panel .citewp-aiso-bv__bot-cell {
	display: flex;
	align-items: center;
	gap: 6px;
}
#citewp_aiso_editor_panel .citewp-aiso-bv__dot {
	width: 8px;
	height: 8px;
	border-radius: 50%;
	flex-shrink: 0;
}
#citewp_aiso_editor_panel .citewp-aiso-bv__visits { font-variant-numeric: tabular-nums; }
#citewp_aiso_editor_panel .citewp-aiso-bv__overflow {
	font-size: 11px;
	color: var(--citewp-text-muted);
	margin: 6px 0 0;
}
#citewp_aiso_editor_panel .citewp-aiso-bv__footer {
	font-size: 11px;
	color: var(--citewp-text-muted);
	margin: 8px 0 0;
	padding-top: 8px;
	border-top: 1px solid var(--citewp-border);
}
#citewp_aiso_editor_panel .citewp-aiso-bv__footer strong {
	font-weight: 600;
	color: var(--citewp-text-secondary);
}

/* --- Bot Visits empty state ------------------------------------------------ */
#citewp_aiso_editor_panel .citewp-aiso-bv__empty {
	flex: 1;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	text-align: center;
	padding: 16px 0;
}
#citewp_aiso_editor_panel .citewp-aiso-bv__empty-icon {
	width: 36px;
	height: 36px;
	border-radius: 50%;
	background: #f3f4f6;
	display: flex;
	align-items: center;
	justify-content: center;
	margin-bottom: 8px;
}
#citewp_aiso_editor_panel .citewp-aiso-bv__empty-icon svg {
	width: 18px;
	height: 18px;
	color: var(--citewp-text-muted);
}
#citewp_aiso_editor_panel .citewp-aiso-bv__empty-title {
	font-size: 12px;
	font-weight: 600;
	color: var(--citewp-text-primary);
	margin: 0 0 4px;
}
#citewp_aiso_editor_panel .citewp-aiso-bv__empty-desc {
	font-size: 11px;
	color: var(--citewp-text-muted);
	margin: 0;
	line-height: 1.5;
	max-width: 220px;
}
```

- [ ] **Step 2: Update `EditorPanel::register()` — add enqueue, remove inline_styles hook**

In `includes/Admin/EditorPanel.php`, replace the `register()` method (lines 12–15):

```php
// OLD:
public function register(): void {
    add_action( 'add_meta_boxes', [ $this, 'register_meta_box' ], 20 );
    add_action( 'admin_head',     [ $this, 'inline_styles' ] );
}
```

With:

```php
public function register(): void {
    add_action( 'add_meta_boxes',        [ $this, 'register_meta_box' ], 20 );
    add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );
}

public function enqueue_styles( string $hook ): void {
    if ( $hook !== 'post.php' && $hook !== 'post-new.php' ) {
        return;
    }
    wp_enqueue_style(
        'citewp-aiso-editor-panel',
        CITEWP_AISO_PLUGIN_URL . 'admin/css/citewp-aiso-admin.css',
        [],
        CITEWP_AISO_VERSION
    );
}
```

- [ ] **Step 3: Delete `inline_styles()` method**

Remove the entire `inline_styles()` method (lines 347–387 in the original file — from `public function inline_styles(): void {` to the closing `}`).

- [ ] **Step 4: Verify the CSS enqueue works**

Open LocalWP, navigate to any post edit page in Classic Editor (disable Gutenberg plugin if needed, or use a non-Gutenberg post type). Inspect the page source and confirm `citewp-aiso-admin.css` loads as a stylesheet `<link>` tag. Confirm the tabs still render and switch correctly. Confirm the score badge still shows with correct color.

- [ ] **Step 5: Commit**

```bash
git add includes/Admin/EditorPanel.php admin/css/citewp-aiso-admin.css
git commit -m "refactor: migrate EditorPanel inline styles to admin CSS with v3 tokens"
```

---

## Task 2: Add `$context` arg to the tabs filter

**Purpose:** Split the single `apply_filters('citewp_aiso/metabox/tabs', ...)` call into two contextual calls — one for the score/General tab area (`'score'`) and one for the schema tab area (`'schema'`). This unblocks FB30 (Cite Bridges) and FB29 (Schema expansion) by giving external code a way to add tabs into a specific context. No visible behavior change.

**Files:**
- Modify: `includes/Admin/EditorPanel.php` (the `render()` method, lines 32–101)

- [ ] **Step 1: Replace the single filter call in `render()` with two contextual calls**

In `render()`, find the `$default_tabs` array definition and the single `apply_filters` call (around lines 38–57). Replace them:

```php
// OLD — delete these lines:
$default_tabs = [
    [
        'slug'   => 'general',
        'label'  => __( 'General', 'ai-search-optimizer' ),
        'render' => [ $this, 'render_general_tab' ],
    ],
    [
        'slug'   => 'schema',
        'label'  => __( 'Schema', 'ai-search-optimizer' ),
        'render' => [ $this, 'render_schema_tab' ],
    ],
];

/**
 * Filters the EditorPanel tab definitions. Reserved for Pro tab registration.
 *
 * @param array<int, array{slug: string, label: string, render: callable}> $tabs
 * @param \WP_Post $post
 */
$tabs = apply_filters( 'citewp_aiso/metabox/tabs', $default_tabs, $post );
```

Replace with:

```php
/**
 * Filters EditorPanel tabs for the score (General) context.
 * Add tabs here to appear alongside or after the General tab.
 *
 * @param array<int, array{slug: string, label: string, render: callable}> $tabs
 * @param \WP_Post $post
 * @param string   $context Always 'score' for this call.
 */
$score_tabs = apply_filters(
    'citewp_aiso/metabox/tabs',
    [
        [
            'slug'   => 'general',
            'label'  => __( 'General', 'ai-search-optimizer' ),
            'render' => [ $this, 'render_general_tab' ],
        ],
    ],
    $post,
    'score'
);

/**
 * Filters EditorPanel tabs for the schema context.
 * Add tabs here to appear alongside or after the Schema tab.
 *
 * @param array<int, array{slug: string, label: string, render: callable}> $tabs
 * @param \WP_Post $post
 * @param string   $context Always 'schema' for this call.
 */
$schema_tabs = apply_filters(
    'citewp_aiso/metabox/tabs',
    [
        [
            'slug'   => 'schema',
            'label'  => __( 'Schema', 'ai-search-optimizer' ),
            'render' => [ $this, 'render_schema_tab' ],
        ],
    ],
    $post,
    'schema'
);

$tabs = array_merge( $score_tabs, $schema_tabs );
```

- [ ] **Step 2: Verify no behavior change**

Reload a post edit page. General and Schema tabs should still render and switch identically to before.

- [ ] **Step 3: Commit**

```bash
git add includes/Admin/EditorPanel.php
git commit -m "feat: add \$context arg to citewp_aiso/metabox/tabs filter (score/schema contexts)"
```

---

## Task 3: Add Bot Visits private methods

**Purpose:** Add the two private methods that power the Bot Visits widget: `query_bot_visits()` runs the SQL and handles the overflow count, `bot_dot_color()` maps a bot signature string to a consistent accent color. Neither method has any UI side effect — this task has no visible output.

**Files:**
- Modify: `includes/Admin/EditorPanel.php` (add `use` statement + two private methods before the closing `}`)

- [ ] **Step 1: Add the `Database\Schema` import**

At the top of `EditorPanel.php`, add to the existing `use` block (after line 7 `use CiteWP\Aiso\Schema\Generator;`):

```php
use CiteWP\Aiso\Database\Schema;
```

- [ ] **Step 2: Add `query_bot_visits()` as a private method**

Add this method inside the `EditorPanel` class, before the closing `}` of the class:

```php
/**
 * @return array{rows: list<object>, n_more: int}
 */
private function query_bot_visits( int $post_id ): array {
    global $wpdb;

    $table = Schema::table( 'citewp_aiso_crawler_logs' );

    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT bot_signature, COUNT(*) AS visits, MAX(created_at) AS last_seen
             FROM {$table}
             WHERE post_id = %d
               AND created_at > NOW() - INTERVAL 7 DAY
             GROUP BY bot_signature
             ORDER BY visits DESC
             LIMIT 6",
            $post_id
        )
    );

    if ( null === $rows ) {
        return [ 'rows' => [], 'n_more' => 0 ];
    }

    $n_more = 0;
    if ( count( $rows ) === 6 ) {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total  = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT bot_signature)
                 FROM {$table}
                 WHERE post_id = %d
                   AND created_at > NOW() - INTERVAL 7 DAY",
                $post_id
            )
        );
        $n_more = max( 0, $total - 5 );
        $rows   = array_slice( $rows, 0, 5 );
    }

    return [ 'rows' => $rows, 'n_more' => $n_more ];
}
```

- [ ] **Step 3: Add `bot_dot_color()` as a private method**

Add this method immediately after `query_bot_visits()`:

```php
private function bot_dot_color( string $sig ): string {
    $palette = [ '#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6' ];
    return $palette[ abs( crc32( $sig ) ) % count( $palette ) ];
}
```

- [ ] **Step 4: Verify no PHP errors**

Check LocalWP's `debug.log` (at `app/public/wp-content/debug.log`) — no new errors should appear. The methods are private and not yet called.

- [ ] **Step 5: Commit**

```bash
git add includes/Admin/EditorPanel.php
git commit -m "feat: add query_bot_visits and bot_dot_color helpers to EditorPanel"
```

---

## Task 4: Add `render_bot_visits()` private method

**Purpose:** Add the method that renders the Bot Visits right column — populated state (table + tier footer) or empty state (centered card). The icon is the Lucide `bot` SVG inline; the header icon block is navy 20×20 with citrine stroke per the spec.

**Files:**
- Modify: `includes/Admin/EditorPanel.php` (add private method before closing `}`)

- [ ] **Step 1: Add `render_bot_visits()` as a private method**

Add this method after `bot_dot_color()`, before the class closing `}`:

```php
private function render_bot_visits( \WP_Post $post ): void {
    $result   = $this->query_bot_visits( $post->ID );
    $rows     = $result['rows'];
    $n_more   = $result['n_more'];
    $has_data = ! empty( $rows );

    // Lucide bot SVG — citrine stroke for the icon block, muted for empty state
    $bot_svg_citrine = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#e8d400" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/></svg>';
    $bot_svg_muted   = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/></svg>';
    ?>
    <div class="citewp-aiso-bv">
        <div class="citewp-aiso-bv__header">
            <div class="citewp-aiso-bv__title-wrap">
                <span class="citewp-aiso-bv__icon">
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo $bot_svg_citrine;
                    ?>
                </span>
                <span class="citewp-aiso-bv__title"><?php esc_html_e( 'Bot Visits', 'ai-search-optimizer' ); ?></span>
            </div>
            <span class="citewp-aiso-bv__pill"><?php esc_html_e( 'Last 7 days', 'ai-search-optimizer' ); ?></span>
        </div>

        <?php if ( $has_data ) : ?>

            <table class="citewp-aiso-bv__table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Bot', 'ai-search-optimizer' ); ?></th>
                        <th><?php esc_html_e( 'Visits', 'ai-search-optimizer' ); ?></th>
                        <th><?php esc_html_e( 'Last seen', 'ai-search-optimizer' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $rows as $row ) : ?>
                        <?php
                        $dot_color = $this->bot_dot_color( (string) $row->bot_signature );
                        $last_seen = sprintf(
                            /* translators: %s: human-readable time difference, e.g. "2 hours" */
                            __( '%s ago', 'ai-search-optimizer' ),
                            human_time_diff( (int) strtotime( (string) $row->last_seen ), time() )
                        );
                        ?>
                        <tr>
                            <td>
                                <div class="citewp-aiso-bv__bot-cell">
                                    <span class="citewp-aiso-bv__dot"
                                          style="background:<?php echo esc_attr( $dot_color ); ?>;"
                                          aria-hidden="true"></span>
                                    <?php echo esc_html( (string) $row->bot_signature ); ?>
                                </div>
                            </td>
                            <td class="citewp-aiso-bv__visits"><?php echo esc_html( (string) $row->visits ); ?></td>
                            <td><?php echo esc_html( $last_seen ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ( $n_more > 0 ) : ?>
                <p class="citewp-aiso-bv__overflow">
                    <?php
                    printf(
                        /* translators: %d: number of additional bot signatures */
                        esc_html__( 'and %d more', 'ai-search-optimizer' ),
                        (int) $n_more
                    );
                    ?>
                </p>
            <?php endif; ?>

            <p class="citewp-aiso-bv__footer">
                <?php
                echo wp_kses(
                    __( 'Free tier shows 7 days of crawler activity. <strong>Pro extends to 90 days.</strong>', 'ai-search-optimizer' ),
                    [ 'strong' => [] ]
                );
                ?>
            </p>

        <?php else : ?>

            <div class="citewp-aiso-bv__empty">
                <div class="citewp-aiso-bv__empty-icon">
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo $bot_svg_muted;
                    ?>
                </div>
                <p class="citewp-aiso-bv__empty-title">
                    <?php esc_html_e( 'No AI bot visits yet', 'ai-search-optimizer' ); ?>
                </p>
                <p class="citewp-aiso-bv__empty-desc">
                    <?php esc_html_e( 'Most bots discover new posts within 24–72 hours of publishing.', 'ai-search-optimizer' ); ?>
                </p>
            </div>

        <?php endif; ?>
    </div>
    <?php
}
```

- [ ] **Step 2: Commit**

```bash
git add includes/Admin/EditorPanel.php
git commit -m "feat: add render_bot_visits method — populated table + empty state"
```

---

## Task 5: Rewrite `render_general_tab()` — 2-column layout + category bar fill

**Purpose:** Replace the single-column layout with a CSS grid (45% left / 55% right). Left column keeps the existing score badge, category rows (now with visual bar fill), "Scored X ago" text, and Recalculate button. Right column calls `render_bot_visits()`. The JS recalculate rebuild is updated to include bar fill markup. This is the final visible change — after this task the panel matches the approved design.

**Files:**
- Modify: `includes/Admin/EditorPanel.php` (replace `render_general_tab()` entirely)

- [ ] **Step 1: Replace the entire `render_general_tab()` method**

Delete the existing `render_general_tab()` method (lines 103–231 in the original file) and replace with:

```php
public function render_general_tab( \WP_Post $post ): void {
    $repo       = new Repository();
    $data       = $repo->get( $post->ID );
    $total      = isset( $data['total'] ) ? (int) $data['total'] : null;
    $grade      = isset( $data['grade'] ) && is_string( $data['grade'] ) ? $data['grade'] : 'red';
    $categories = isset( $data['categories'] ) && is_array( $data['categories'] ) ? $data['categories'] : [];
    $scored_at  = get_post_meta( $post->ID, Repository::META_KEY_TIME, true );
    $nonce      = wp_create_nonce( 'wp_rest' );
    $recalc_url = rest_url( 'citewp/aiso/v1/score/' . $post->ID . '/recalculate' );
    $content_id = 'citewp-ep-general-' . $post->ID;
    ?>
    <div class="citewp-aiso-ep-general" id="<?php echo esc_attr( $content_id ); ?>">
        <div class="citewp-aiso-ep-columns">

            <div class="citewp-aiso-ep-col-left">
                <div class="citewp-aiso-mb-content">
                    <?php if ( $total !== null ) : ?>
                        <div class="citewp-aiso-mb-score">
                            <span class="citewp-aiso-mb-badge citewp-aiso-mb-badge--<?php echo esc_attr( $grade ); ?>">
                                <?php echo esc_html( (string) $total ); ?>
                            </span>
                            <span class="citewp-aiso-mb-total-label"><?php esc_html_e( '/ 100', 'ai-search-optimizer' ); ?></span>
                        </div>
                        <?php if ( ! empty( $categories ) ) : ?>
                            <div class="citewp-aiso-mb-categories">
                                <?php foreach ( $categories as $cat ) : ?>
                                    <?php
                                    if ( ! is_array( $cat ) ) {
                                        continue;
                                    }
                                    $score = (int) ( $cat['score'] ?? 0 );
                                    $max   = (int) ( $cat['max'] ?? 0 );
                                    $pct   = $max > 0 ? (int) round( ( $score / $max ) * 100 ) : 0;
                                    ?>
                                    <div class="citewp-aiso-mb-cat-row">
                                        <span class="citewp-aiso-mb-cat-label">
                                            <?php echo esc_html( (string) ( $cat['label'] ?? '' ) ); ?>
                                        </span>
                                        <div class="citewp-aiso-mb-cat-bar-wrap">
                                            <div class="citewp-aiso-mb-cat-bar-fill"
                                                 style="width:<?php echo esc_attr( (string) $pct ); ?>%"></div>
                                        </div>
                                        <span class="citewp-aiso-mb-cat-score">
                                            <?php echo esc_html( $score . '/' . $max ); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php
                        $ts = $scored_at && is_string( $scored_at ) ? strtotime( $scored_at ) : false;
                        if ( false !== $ts ) :
                        ?>
                            <p class="citewp-aiso-mb-time">
                                <?php
                                printf(
                                    /* translators: %s: human-readable time difference */
                                    esc_html__( 'Scored %s ago', 'ai-search-optimizer' ),
                                    esc_html( human_time_diff( $ts, time() ) )
                                );
                                ?>
                            </p>
                        <?php endif; ?>
                    <?php else : ?>
                        <p class="citewp-aiso-mb-empty">
                            <?php esc_html_e( 'Score not yet calculated.', 'ai-search-optimizer' ); ?>
                        </p>
                    <?php endif; ?>
                </div>
                <p class="citewp-aiso-mb-action">
                    <button type="button"
                            class="button button-secondary citewp-aiso-recalc-btn"
                            data-nonce="<?php echo esc_attr( $nonce ); ?>"
                            data-url="<?php echo esc_url( $recalc_url ); ?>">
                        <?php esc_html_e( 'Recalculate', 'ai-search-optimizer' ); ?>
                    </button>
                </p>
                <p class="citewp-aiso-recalc-error" style="display:none;">
                    <?php esc_html_e( 'Recalculation failed — please try again.', 'ai-search-optimizer' ); ?>
                </p>
            </div>

            <div class="citewp-aiso-ep-col-right">
                <?php $this->render_bot_visits( $post ); ?>
            </div>

        </div>
    </div>
    <script>
    (function() {
        function esc(s) {
            return String(s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
        var wrap   = document.getElementById( <?php echo wp_json_encode( $content_id ); ?> );
        if ( ! wrap ) { return; }
        var btn    = wrap.querySelector( '.citewp-aiso-recalc-btn' );
        var errEl  = wrap.querySelector( '.citewp-aiso-recalc-error' );
        var contEl = wrap.querySelector( '.citewp-aiso-mb-content' );
        if ( ! btn || ! errEl ) { return; }

        btn.addEventListener( 'click', function() {
            var origText = btn.textContent;
            btn.disabled    = true;
            btn.textContent = <?php echo wp_json_encode( __( 'Recalculating…', 'ai-search-optimizer' ) ); ?>;
            errEl.style.display = 'none';

            fetch( btn.dataset.url, {
                method: 'POST',
                headers: { 'X-WP-Nonce': btn.dataset.nonce, 'Content-Type': 'application/json' }
            } )
            .then( function( r ) { return r.ok ? r.json() : Promise.reject( r.status ); } )
            .then( function( data ) {
                var grade = data.grade || 'red';
                var total = data.total || 0;
                var cats  = data.categories || {};
                var html  = '<div class="citewp-aiso-mb-score">'
                          + '<span class="citewp-aiso-mb-badge citewp-aiso-mb-badge--' + esc(grade) + '">' + esc(String(total)) + '</span>'
                          + '<span class="citewp-aiso-mb-total-label"> / 100</span>'
                          + '</div>';
                if ( cats.structure ) {
                    html += '<div class="citewp-aiso-mb-categories">';
                    [ 'structure', 'citability', 'authority' ].forEach( function( k ) {
                        if ( cats[ k ] ) {
                            var score = cats[k].score ?? 0;
                            var max   = cats[k].max   ?? 0;
                            var pct   = max ? Math.round( ( score / max ) * 100 ) : 0;
                            html += '<div class="citewp-aiso-mb-cat-row">'
                                  + '<span class="citewp-aiso-mb-cat-label">' + esc( cats[k].label ?? '' ) + '</span>'
                                  + '<div class="citewp-aiso-mb-cat-bar-wrap"><div class="citewp-aiso-mb-cat-bar-fill" style="width:' + pct + '%"></div></div>'
                                  + '<span class="citewp-aiso-mb-cat-score">' + esc( String(score) ) + '/' + esc( String(max) ) + '</span>'
                                  + '</div>';
                        }
                    } );
                    html += '</div>';
                }
                html += '<p class="citewp-aiso-mb-time">' + <?php echo wp_json_encode( __( 'Scored just now', 'ai-search-optimizer' ) ); ?> + '</p>';
                if ( contEl ) { contEl.innerHTML = html; }
                btn.disabled    = false;
                btn.textContent = origText;
            } )
            .catch( function( status ) {
                btn.disabled    = false;
                btn.textContent = origText;
                if ( status === 403 ) {
                    errEl.textContent = <?php echo wp_json_encode( __( 'Session expired — please reload the page.', 'ai-search-optimizer' ) ); ?>;
                }
                errEl.style.display = 'block';
            } );
        } );
    })();
    </script>
    <?php
}
```

- [ ] **Step 2: Verify in browser — golden path + column ratio check**

Open a scored post in Classic Editor (non-Gutenberg). Confirm:
- 2-column layout renders at meta box width
- Left column: score number (JetBrains Mono, grade-colored), category bars with horizontal fill, "Scored X ago", Recalculate button
- Right column: Bot Visits section (populated table OR empty state depending on data in wp_citewp_aiso_crawler_logs)
- Column divider line visible between columns
- "Bot Visits" header with navy icon block + citrine SVG + "Last 7 days" pill

**Column ratio check (design decision):** The grid uses `45% 55%` (favoring Bot Visits). If the Bot Visits column visually overpowers the score — the score feels cramped or subordinate — change `grid-template-columns: 45% 55%` to `grid-template-columns: 50% 50%` in `admin/css/citewp-aiso-admin.css`. 50/50 communicates equal priority; only revert if 45/55 creates imbalance at the actual meta box width.

- [ ] **Step 3: Verify Recalculate JS**

Click Recalculate. After response:
- Score badge updates to new value/grade
- Category rows re-render with bar fill (bars should fill to the correct percentage)
- "Scored just now" appears
- No JS errors in browser console

- [ ] **Step 4: Verify empty state**

Open a post that has never been crawled (new draft, or clear test data). Confirm the right column shows:
- Gray circle icon
- "No AI bot visits yet"
- "Most bots discover new posts within 24–72 hours of publishing."
- No tier disclosure footer (correct — omit when empty)

- [ ] **Step 5: Check debug.log**

```
# In LocalWP terminal or via file browser:
# Check: app/public/wp-content/debug.log
# Expected: no new PHP errors or warnings
```

- [ ] **Step 6: Commit**

```bash
git add includes/Admin/EditorPanel.php
git commit -m "feat: EditorPanel General tab — 2-column layout, bar fill, Bot Visits widget"
```

---

## Task 6: Push + session close

- [ ] **Step 1: Final smoke test checklist**

Verify each of these before pushing:
- [ ] Classic Editor post: 2-column renders, tabs switch, Recalculate works
- [ ] Schema tab: Article copy button and FAQPage button still work
- [ ] `debug.log`: no new PHP errors
- [ ] Post list column: Cite Score column still shows (unrelated to this session, regression check)
- [ ] `/llms.txt`: still renders (regression check)

- [ ] **Step 2: Push**

```bash
git push origin main
```

- [ ] **Step 3: Update SESSION-LOG.md**

Record in `SESSION-LOG.md`:
- **S23 Delivered:** EditorPanel v3 — 2-column layout (45/55), v3 token migration, category bar fill, Bot Visits widget (populated + empty state), `$context` arg added to `citewp_aiso/metabox/tabs` filter (score/schema contexts)
- **Composition gain (SESSION-LOG note):** "Last seen" column emerged from wider-canvas composition work — not in original Q3 spec. `MAX(created_at)` already in GROUP BY; no schema change needed. Wider canvas revealed a data dimension the sidebar mockup couldn't surface.
- **Carryover:** None from S23. S24 focus: llms.txt per-post widget.
- **DECISIONS.md:** No new decisions required (all settled in brainstorming).

---

## Self-Review

**Spec coverage check:**

| Spec requirement | Task that covers it |
|---|---|
| v3 token migration — all inline hex → custom properties | Task 1 (CSS section) |
| Enqueue CSS on post.php/post-new.php | Task 1 (enqueue_styles method) |
| Remove inline_styles() | Task 1 (Step 3) |
| $context arg on tabs filter — 'score' + 'schema' | Task 2 |
| Category bar fill (visual progress bars) | Task 5 (render_general_tab rewrite) |
| Bar fill in JS recalculate rebuild | Task 5 (render_general_tab JS) |
| query_bot_visits — LIMIT 6 overflow detection, COUNT fallback | Task 3 |
| bot_dot_color — hash mod palette | Task 3 |
| Bot Visits header: navy icon block + citrine SVG + pill | Task 4 (render_bot_visits) |
| Populated state: 3-column table (Bot / Visits / Last seen) | Task 4 |
| "Last seen" via human_time_diff + " ago" | Task 4 |
| Colored dots via bot_dot_color() | Task 4 |
| Overflow: "and N more" plain text | Task 4 |
| Tier disclosure footer (populated state only) | Task 4 |
| Empty state: icon + "No AI bot visits yet" + copy | Task 4 |
| Empty state centered in right column | Task 1 CSS (.citewp-aiso-bv__empty flex centering) |
| No tier disclosure in empty state | Task 4 (footer only in $has_data branch) |
| 2-column General tab, 45/55 grid | Task 5 (render_general_tab rewrite) |
| Column divider border-left | Task 1 CSS (.citewp-aiso-ep-col-right) |
| 2 tabs total confirmed (no third tab) | Task 2 (only two filter calls) |
| Schema::table() for table name | Task 3 (query_bot_visits uses Schema::table()) |
| use CiteWP\Aiso\Database\Schema import | Task 3 (Step 1) |

All spec requirements covered. No gaps.
