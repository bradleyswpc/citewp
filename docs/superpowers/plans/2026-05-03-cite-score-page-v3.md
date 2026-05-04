# Cite Score Page v3 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the "coming soon" stub on the Cite Score admin page with a full v3 layout: page header strip with post picker, Donut Chart Panel, Line Chart Panel (empty state), and per-signal breakdown table.

**Architecture:** Two-file change — CSS first (Section 31 in `citewp-aiso-admin.css`) so classes exist before PHP references them; then a full rewrite of `render_cite_score_panel()` in `Menu.php`. No new files, no new PHP classes. Post context comes from `$_GET['post_id']` (sanitized `absint()`), defaulting to most-recently-scored post. Score data comes from `Repository::get()` which returns the `to_array()` array stored in post meta. Line Chart uses empty state per spec — no history storage layer exists yet. The SVG donut is generated inline in PHP; no chart library.

**Tech Stack:** PHP 8.0+, WordPress 6.5+, inline SVG, CSS custom properties. All tokens from P38, all button classes from P41.

---

## File Map

| File | Change |
|------|--------|
| `admin/css/citewp-aiso-admin.css` | Append Section 31: two-column layout, post picker, donut chart, line chart empty state, signal table |
| `includes/Admin/Menu.php` | Replace `render_cite_score_panel()` lines 709–721 with full 200-line implementation + add private `render_donut_svg()` helper below it |

---

## Task 1: CSS — Section 31

**Files:**
- Modify: `admin/css/citewp-aiso-admin.css` (append after last line)

- [ ] **Step 1: Append Section 31 to the CSS file**

Add the following block at the very end of `admin/css/citewp-aiso-admin.css`:

```css
/* === SECTION 31: Cite Score page (Session 19) === */

/* Post picker in page header right block */
.citewp-aiso-cs-picker {
  font: 500 13px/1 'Inter', system-ui, sans-serif;
  color: var(--citewp-obsidian);
  background: var(--citewp-paper);
  border: 1px solid var(--citewp-border);
  border-radius: var(--radius-md);
  padding: var(--sp-2) var(--sp-3);
  cursor: pointer;
}

/* Two-column layout: left (donut + history) | right (signals) */
.citewp-aiso-cs-body {
  display: grid;
  grid-template-columns: 1fr 1.5fr;
  gap: var(--sp-4);
  align-items: start;
}

.citewp-aiso-cs-left {
  display: flex;
  flex-direction: column;
  gap: var(--sp-4);
}

/* ── Donut Chart Panel ── */
.citewp-aiso-donut-panel {
  background: var(--citewp-paper);
  border: 1px solid var(--citewp-border);
  border-radius: var(--radius-lg);
  padding: var(--sp-5);
}

.citewp-aiso-donut-panel__chart {
  display: flex;
  justify-content: center;
  margin-bottom: var(--sp-4);
}

.citewp-aiso-donut-panel__cats {
  display: flex;
  flex-direction: column;
  gap: var(--sp-2);
}

.citewp-aiso-donut-panel__cat {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
}

.citewp-aiso-donut-panel__cat-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}

.citewp-aiso-donut-panel__cat-name {
  font: 500 13px/1 'Inter', system-ui, sans-serif;
  color: var(--citewp-obsidian);
  flex: 1;
}

.citewp-aiso-donut-panel__cat-score {
  font: 700 13px/1 'JetBrains Mono', monospace;
}

.citewp-aiso-donut-panel__cat-max {
  font: 400 12px/1 'Inter', system-ui, sans-serif;
  color: var(--citewp-text-muted);
}

.citewp-aiso-donut-panel__footer {
  margin-top: var(--sp-4);
  text-align: right;
}

.citewp-aiso-donut-panel__link {
  font: 700 12px/1 'Inter', system-ui, sans-serif;
  color: var(--citewp-tint-blue);
  text-decoration: none;
}

.citewp-aiso-donut-panel__link::after {
  content: ' →';
}

/* ── Line Chart Panel (history) ── */
.citewp-aiso-history-panel {
  background: var(--citewp-paper);
  border: 1px solid var(--citewp-border);
  border-radius: var(--radius-lg);
  padding: var(--sp-5);
}

.citewp-aiso-history-panel__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--sp-4);
}

.citewp-aiso-history-panel__title {
  font: 700 14px/1 'Inter', system-ui, sans-serif;
  color: var(--citewp-obsidian);
}

.citewp-aiso-history-panel__pills {
  display: flex;
  gap: var(--sp-1);
}

.citewp-aiso-history-pill {
  font: 700 11px/1 'Inter', system-ui, sans-serif;
  padding: 4px 10px;
  border-radius: 6px;
  border: none;
  cursor: pointer;
  background: var(--citewp-paper-tinted);
  color: var(--citewp-text-muted);
}

.citewp-aiso-history-pill.is-active {
  background: var(--citewp-obsidian);
  color: #fff;
}

.citewp-aiso-history-panel__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 120px;
  gap: var(--sp-2);
}

.citewp-aiso-history-panel__empty-line {
  width: 100%;
  height: 2px;
  border-top: 2px dashed var(--citewp-border);
  margin-bottom: var(--sp-2);
}

.citewp-aiso-history-panel__empty-text {
  font: 400 12px/1.5 'Inter', system-ui, sans-serif;
  color: var(--citewp-text-muted);
  text-align: center;
  max-width: 240px;
}

/* ── Signal breakdown (right column) ── */
.citewp-aiso-signals {
  background: var(--citewp-paper);
  border: 1px solid var(--citewp-border);
  border-radius: var(--radius-lg);
  overflow: hidden;
}

.citewp-aiso-signals__cat-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--sp-3) var(--sp-4);
  background: var(--citewp-paper-tinted);
  border-bottom: 1px solid var(--citewp-border);
}

.citewp-aiso-signals__cat-label {
  font: 700 12px/1 'Inter', system-ui, sans-serif;
  color: var(--citewp-obsidian);
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.citewp-aiso-signals__cat-score {
  font: 700 12px/1 'JetBrains Mono', monospace;
  color: var(--citewp-obsidian);
}

.citewp-aiso-signal-row {
  padding: var(--sp-3) var(--sp-4);
  border-bottom: 1px solid #eef2f7;
}

.citewp-aiso-signal-row:last-child {
  border-bottom: none;
}

.citewp-aiso-signal-row__top {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
}

.citewp-aiso-signal-row__label {
  font: 500 13px/1 'Inter', system-ui, sans-serif;
  color: var(--citewp-obsidian);
  flex: 1;
}

.citewp-aiso-signal-row__pts {
  font: 700 12px/1 'JetBrains Mono', monospace;
  color: var(--citewp-text-muted);
  white-space: nowrap;
}

.citewp-aiso-signal-badge {
  font: 700 10px/1 'Inter', system-ui, sans-serif;
  padding: 3px 7px;
  border-radius: var(--radius-sm);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  white-space: nowrap;
}

.citewp-aiso-signal-badge--pass {
  background: #dcfce7;
  color: var(--citewp-score-green);
}

.citewp-aiso-signal-badge--partial {
  background: #fef3c7;
  color: #b45309;
}

.citewp-aiso-signal-badge--fail {
  background: #fee2e2;
  color: var(--citewp-score-red);
}

.citewp-aiso-signal-row__msg {
  font: 400 12px/1.5 'Inter', system-ui, sans-serif;
  color: var(--citewp-text-muted);
  margin-top: var(--sp-1);
}

.citewp-aiso-signal-row__rec {
  font: 400 12px/1.5 'Inter', system-ui, sans-serif;
  color: var(--citewp-tint-blue);
  margin-top: 3px;
}

/* ── No-score empty state ── */
.citewp-aiso-cs-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: var(--sp-10) var(--sp-8);
  text-align: center;
  gap: var(--sp-3);
}

/* ── Responsive: stack columns below 900px ── */
@media (max-width: 900px) {
  .citewp-aiso-cs-body {
    grid-template-columns: 1fr;
  }
}
```

- [ ] **Step 2: Verify CSS appended cleanly**

Confirm the last line of the file is `}` closing the `@media` block.

- [ ] **Step 3: Commit CSS**

```
git add admin/css/citewp-aiso-admin.css
git commit -m "feat: Section 31 — Cite Score page CSS (donut panel, history panel, signal table)"
```

---

## Task 2: PHP — render_cite_score_panel() + render_donut_svg()

**Files:**
- Modify: `includes/Admin/Menu.php:709–721` (replace the stub + add private helper after it)

- [ ] **Step 1: Add `use` import for Repository at the top of Menu.php**

Near the top of `Menu.php`, find the existing `use` statements block and confirm `Scoring\Repository` is already imported. If not, add:

```php
use CiteWP\Aiso\Scoring\Repository;
```

Check by searching for `use CiteWP\Aiso\Scoring` in the file. It may already be present (ScoreMetaBox / EditorPanel imports it). If it exists, skip this step.

- [ ] **Step 2: Replace the cite-score stub with the full render method**

Replace everything from line 709 to line 721 (the entire `render_cite_score_panel()` method) with:

```php
private function render_cite_score_panel(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Resolve which post to show.
    $post_id = isset( $_GET['post_id'] ) ? absint( wp_unslash( $_GET['post_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display param; no data modification.

    if ( ! $post_id ) {
        // Default: most recently scored post.
        $recent = get_posts( [
            'meta_key'    => Repository::META_KEY_TIME,
            'orderby'     => 'meta_value',
            'order'       => 'DESC',
            'numberposts' => 1,
            'post_type'   => [ 'post', 'page' ],
            'post_status' => [ 'publish', 'draft' ],
        ] );
        $post_id = ! empty( $recent ) ? (int) $recent[0]->ID : 0;
    }

    // All scored posts for the picker dropdown.
    $scored_posts = get_posts( [
        'meta_key'     => Repository::META_KEY_TOTAL,
        'meta_compare' => 'EXISTS',
        'orderby'      => 'meta_value_num',
        'order'        => 'ASC',
        'numberposts'  => 100,
        'post_type'    => [ 'post', 'page' ],
        'post_status'  => [ 'publish', 'draft' ],
    ] );

    $score_data = $post_id ? ( new Repository() )->get( $post_id ) : null;
    $post       = $post_id ? get_post( $post_id ) : null;
    $base_url   = add_query_arg( [ 'page' => self::SLUG_PARENT ], admin_url( 'admin.php' ) ) . '#cite-score';

    ?>
    <!-- Page header strip -->
    <div class="citewp-aiso-page-header">
        <div class="citewp-aiso-page-header__left">
            <h2 class="citewp-aiso-page-header__title"><?php esc_html_e( 'Cite Score', 'ai-search-optimizer' ); ?></h2>
            <p class="citewp-aiso-page-header__desc"><?php esc_html_e( 'Optimize your content for AI citation.', 'ai-search-optimizer' ); ?></p>
        </div>
        <?php if ( ! empty( $scored_posts ) ) : ?>
        <div class="citewp-aiso-page-header__actions">
            <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
                <input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG_PARENT ); ?>">
                <select
                    name="post_id"
                    class="citewp-aiso-cs-picker"
                    onchange="this.form.submit()"
                    aria-label="<?php esc_attr_e( 'Select post', 'ai-search-optimizer' ); ?>"
                >
                    <?php foreach ( $scored_posts as $sp ) : ?>
                        <option value="<?php echo esc_attr( (string) $sp->ID ); ?>"<?php selected( $sp->ID, $post_id ); ?>>
                            <?php echo esc_html( get_the_title( $sp ) ?: __( '(no title)', 'ai-search-optimizer' ) ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <?php if ( ! $score_data || ! $post ) : ?>
    <!-- Empty state: no scored posts yet -->
    <div class="citewp-aiso-cs-empty">
        <div class="citewp-aiso-empty__icon"><?php echo IconLibrary::icon( 'gauge', 48 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?></div>
        <h3 class="citewp-aiso-empty__title"><?php esc_html_e( 'No scored content yet.', 'ai-search-optimizer' ); ?></h3>
        <p class="citewp-aiso-empty__text"><?php esc_html_e( 'Publish or save a post to generate your first Cite Score.', 'ai-search-optimizer' ); ?></p>
    </div>
    <?php return; endif;

    $total      = (int) $score_data['total'];
    $grade      = sanitize_key( $score_data['grade'] );
    $categories = $score_data['categories'];
    $signals    = $score_data['signals'];
    $edit_url   = get_edit_post_link( $post->ID );

    $grade_colors = [
        'green'  => 'var(--citewp-score-green)',
        'yellow' => 'var(--citewp-score-yellow)',
        'orange' => 'var(--citewp-score-orange)',
        'red'    => 'var(--citewp-score-red)',
    ];
    $score_color = $grade_colors[ $grade ] ?? 'var(--citewp-score-red)';

    // Category score-band colors (per-category grade).
    $cat_color = static function( int $score, int $max ): string {
        $pct = $max > 0 ? ( $score / $max ) * 100 : 0;
        if ( $pct >= 80 ) { return 'var(--citewp-score-green)'; }
        if ( $pct >= 60 ) { return 'var(--citewp-score-yellow)'; }
        if ( $pct >= 40 ) { return 'var(--citewp-score-orange)'; }
        return 'var(--citewp-score-red)';
    };
    ?>

    <!-- Two-column body -->
    <div class="citewp-aiso-cs-body">

        <!-- Left column: donut + history -->
        <div class="citewp-aiso-cs-left">

            <!-- Donut Chart Panel -->
            <div class="citewp-aiso-donut-panel">
                <div class="citewp-aiso-donut-panel__chart">
                    <?php $this->render_donut_svg( $total, $grade, $categories, $score_color, $cat_color ); ?>
                </div>
                <div class="citewp-aiso-donut-panel__cats">
                    <?php foreach ( $categories as $cat_key => $cat ) :
                        $c_score = (int) $cat['score'];
                        $c_max   = (int) $cat['max'];
                        $c_color = $cat_color( $c_score, $c_max );
                    ?>
                    <div class="citewp-aiso-donut-panel__cat">
                        <span class="citewp-aiso-donut-panel__cat-dot" style="background:<?php echo esc_attr( $c_color ); ?>"></span>
                        <span class="citewp-aiso-donut-panel__cat-name"><?php echo esc_html( $cat['label'] ); ?></span>
                        <span class="citewp-aiso-donut-panel__cat-score" style="color:<?php echo esc_attr( $c_color ); ?>"><?php echo esc_html( (string) $c_score ); ?></span>
                        <span class="citewp-aiso-donut-panel__cat-max">/ <?php echo esc_html( (string) $c_max ); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if ( $edit_url ) : ?>
                <div class="citewp-aiso-donut-panel__footer">
                    <a href="<?php echo esc_url( $edit_url ); ?>" class="citewp-aiso-donut-panel__link">
                        <?php esc_html_e( 'Improve this post', 'ai-search-optimizer' ); ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Score History Panel (empty state — history collection not yet implemented) -->
            <div class="citewp-aiso-history-panel">
                <div class="citewp-aiso-history-panel__head">
                    <span class="citewp-aiso-history-panel__title"><?php esc_html_e( 'Score History', 'ai-search-optimizer' ); ?></span>
                    <div class="citewp-aiso-history-panel__pills">
                        <button class="citewp-aiso-history-pill is-active" disabled>7D</button>
                        <button class="citewp-aiso-history-pill" disabled>30D</button>
                        <button class="citewp-aiso-history-pill" disabled>90D</button>
                    </div>
                </div>
                <div class="citewp-aiso-history-panel__empty">
                    <div class="citewp-aiso-history-panel__empty-line"></div>
                    <p class="citewp-aiso-history-panel__empty-text">
                        <?php esc_html_e( 'Not enough history yet. Scores appear after multiple saves.', 'ai-search-optimizer' ); ?>
                    </p>
                </div>
            </div>

        </div><!-- /.citewp-aiso-cs-left -->

        <!-- Right column: signal breakdown -->
        <div class="citewp-aiso-signals">
            <?php
            $groups = [
                'structure'  => __( 'Structure',   'ai-search-optimizer' ),
                'citability' => __( 'Citability',  'ai-search-optimizer' ),
                'authority'  => __( 'Authority',   'ai-search-optimizer' ),
            ];
            foreach ( $groups as $cat_key => $cat_label ) :
                $cat_signals = array_filter( $signals, static fn( $s ) => $s['category'] === $cat_key );
                if ( empty( $cat_signals ) ) { continue; }
                $cat_score = (int) $categories[ $cat_key ]['score'];
                $cat_max   = (int) $categories[ $cat_key ]['max'];
            ?>
            <div class="citewp-aiso-signals__cat-head">
                <span class="citewp-aiso-signals__cat-label"><?php echo esc_html( $cat_label ); ?></span>
                <span class="citewp-aiso-signals__cat-score"><?php echo esc_html( $cat_score . ' / ' . $cat_max ); ?></span>
            </div>
            <?php foreach ( $cat_signals as $sig ) :
                $status = sanitize_key( $sig['status'] );
                $badge_labels = [ 'pass' => 'Pass', 'partial' => 'Partial', 'fail' => 'Fail' ];
                $badge_label  = $badge_labels[ $status ] ?? $status;
            ?>
            <div class="citewp-aiso-signal-row">
                <div class="citewp-aiso-signal-row__top">
                    <span class="citewp-aiso-signal-row__label"><?php echo esc_html( $sig['label'] ); ?></span>
                    <span class="citewp-aiso-signal-row__pts"><?php echo esc_html( $sig['score'] . ' / ' . $sig['max'] ); ?></span>
                    <span class="citewp-aiso-signal-badge citewp-aiso-signal-badge--<?php echo esc_attr( $status ); ?>">
                        <?php echo esc_html( $badge_label ); ?>
                    </span>
                </div>
                <?php if ( ! empty( $sig['message'] ) ) : ?>
                <p class="citewp-aiso-signal-row__msg"><?php echo esc_html( $sig['message'] ); ?></p>
                <?php endif; ?>
                <?php if ( ! empty( $sig['recommendation'] ) && in_array( $status, [ 'partial', 'fail' ], true ) ) : ?>
                <p class="citewp-aiso-signal-row__rec"><?php echo esc_html( $sig['recommendation'] ); ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endforeach; ?>
        </div><!-- /.citewp-aiso-signals -->

    </div><!-- /.citewp-aiso-cs-body -->

    <!-- Pro Tip footer -->
    <div class="citewp-aiso-protip">
        <div class="citewp-aiso-protip__left">
            <div class="citewp-aiso-protip__orb"><?php echo IconLibrary::icon( 'zap', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- IconLibrary::icon() returns pre-escaped SVG ?></div>
            <div class="citewp-aiso-protip__content">
                <p class="citewp-aiso-protip__heading"><?php esc_html_e( 'Pro Tip', 'ai-search-optimizer' ); ?></p>
                <p class="citewp-aiso-protip__body"><?php esc_html_e( 'Adding a FAQ section to your post can boost your Structure score by up to 10 points.', 'ai-search-optimizer' ); ?></p>
            </div>
        </div>
        <a href="https://citewp.com/pro" target="_blank" rel="noopener noreferrer" class="citewp-aiso-btn citewp-aiso-btn--primary-paper">
            <?php esc_html_e( 'Connect Now →', 'ai-search-optimizer' ); ?>
        </a>
    </div>
    <?php
}
```

- [ ] **Step 3: Add `render_donut_svg()` private helper below `render_cite_score_panel()`**

Add this method immediately before the closing `}` of the `Menu` class (after `render_cite_score_panel()`):

```php
private function render_donut_svg(
    int $total,
    string $grade,
    array $categories,
    string $score_color,
    callable $cat_color
): void {
    unset( $grade ); // passed for future use (e.g. aria label); not yet used in SVG output
    // SVG dimensions and ring geometry.
    $cx     = 110;
    $cy     = 110;
    $r_inner = 52;   // inner ring (total score)
    $r_outer = 76;   // outer ring (categories)
    $sw_inner = 14;  // stroke-width inner
    $sw_outer = 10;  // stroke-width outer

    $circ_inner = 2 * M_PI * $r_inner;
    $circ_outer = 2 * M_PI * $r_outer;

    // Inner ring: single arc for total score. Starts at 12 o'clock (rotate -90deg).
    $inner_fill = round( ( $total / 100 ) * $circ_inner, 2 );
    $inner_gap  = round( $circ_inner - $inner_fill, 2 );

    // Outer ring: 3 sequential arc segments for Structure / Citability / Authority.
    // Each segment length proportional to its score / 100.
    $cat_order   = [ 'structure', 'citability', 'authority' ];
    $gap_px      = 4.0; // gap in px between outer segments
    $outer_segs  = [];
    $offset      = 0.0; // cumulative stroke-dashoffset from 12 o'clock
    foreach ( $cat_order as $key ) {
        $cat = $categories[ $key ];
        $len = round( ( (int) $cat['score'] / 100 ) * $circ_outer, 2 );
        $outer_segs[] = [
            'key'    => $key,
            'len'    => $len,
            'color'  => $cat_color( (int) $cat['score'], (int) $cat['max'] ),
            'offset' => round( $circ_outer - $offset, 2 ),
        ];
        $offset += $len + $gap_px;
    }

    // Rotation: -90deg so arc starts at top (12 o'clock).
    $rotate = 'rotate(-90 ' . $cx . ' ' . $cy . ')';
    ?>
    <svg viewBox="0 0 220 220" width="200" height="200" aria-hidden="true" focusable="false">
        <!-- Inner track (unfilled) -->
        <circle
            cx="<?php echo esc_attr( (string) $cx ); ?>"
            cy="<?php echo esc_attr( (string) $cy ); ?>"
            r="<?php echo esc_attr( (string) $r_inner ); ?>"
            fill="none"
            stroke="var(--citewp-paper-tinted)"
            stroke-width="<?php echo esc_attr( (string) $sw_inner ); ?>"
        />
        <!-- Inner arc (total score, Citrine) -->
        <circle
            cx="<?php echo esc_attr( (string) $cx ); ?>"
            cy="<?php echo esc_attr( (string) $cy ); ?>"
            r="<?php echo esc_attr( (string) $r_inner ); ?>"
            fill="none"
            stroke="var(--citewp-citrine)"
            stroke-width="<?php echo esc_attr( (string) $sw_inner ); ?>"
            stroke-linecap="round"
            stroke-dasharray="<?php echo esc_attr( $inner_fill . ' ' . $inner_gap ); ?>"
            transform="<?php echo esc_attr( $rotate ); ?>"
        />
        <!-- Outer track (unfilled) -->
        <circle
            cx="<?php echo esc_attr( (string) $cx ); ?>"
            cy="<?php echo esc_attr( (string) $cy ); ?>"
            r="<?php echo esc_attr( (string) $r_outer ); ?>"
            fill="none"
            stroke="var(--citewp-paper-tinted)"
            stroke-width="<?php echo esc_attr( (string) $sw_outer ); ?>"
        />
        <!-- Outer segments (categories) -->
        <?php foreach ( $outer_segs as $seg ) : ?>
        <circle
            cx="<?php echo esc_attr( (string) $cx ); ?>"
            cy="<?php echo esc_attr( (string) $cy ); ?>"
            r="<?php echo esc_attr( (string) $r_outer ); ?>"
            fill="none"
            stroke="<?php echo esc_attr( $seg['color'] ); ?>"
            stroke-width="<?php echo esc_attr( (string) $sw_outer ); ?>"
            stroke-linecap="round"
            stroke-dasharray="<?php echo esc_attr( $seg['len'] . ' ' . $circ_outer ); ?>"
            stroke-dashoffset="<?php echo esc_attr( (string) $seg['offset'] ); ?>"
            transform="<?php echo esc_attr( $rotate ); ?>"
        />
        <?php endforeach; ?>
        <!-- Center score value -->
        <text
            x="<?php echo esc_attr( (string) $cx ); ?>"
            y="<?php echo esc_attr( (string) ( $cy - 6 ) ); ?>"
            text-anchor="middle"
            dominant-baseline="middle"
            font-family="'JetBrains Mono', monospace"
            font-size="32"
            font-weight="800"
            fill="<?php echo esc_attr( $score_color ); ?>"
        ><?php echo esc_html( (string) $total ); ?></text>
        <!-- Center label -->
        <text
            x="<?php echo esc_attr( (string) $cx ); ?>"
            y="<?php echo esc_attr( (string) ( $cy + 18 ) ); ?>"
            text-anchor="middle"
            dominant-baseline="middle"
            font-family="'Inter', sans-serif"
            font-size="10"
            font-weight="700"
            fill="var(--citewp-text-muted)"
            letter-spacing="0.06em"
        >CITE SCORE</text>
        <!-- Screen reader text -->
    </svg>
    <span class="screen-reader-text">
        <?php
        /* translators: %1$d: score total, %2$s: grade band */
        printf(
            esc_html__( 'Cite Score: %1$d out of 100, %2$s band', 'ai-search-optimizer' ),
            $total,
            $grade
        );
        ?>
    </span>
    <?php
}
```

- [ ] **Step 4: Check for the Repository `use` statement in Menu.php**

Run:
```
grep -n "use CiteWP" includes/Admin/Menu.php
```

If `Scoring\Repository` is not listed, add it to the `use` block near the top of the file after the `namespace` declaration:

```php
use CiteWP\Aiso\Scoring\Repository;
```

- [ ] **Step 5: Verify PHP syntax**

If PHP CLI is available:
```
php -l includes/Admin/Menu.php
```
Expected: `No syntax errors detected`

If PHP CLI is unavailable (typical LocalWP shell), check LocalWP's `debug.log` after loading the Cite Score page.

- [ ] **Step 6: Commit PHP changes**

```
git add includes/Admin/Menu.php
git commit -m "feat: Cite Score page v3 — donut chart, history panel (empty state), signal table"
```

---

## Task 3: Smoke Test

**Files:** None (read-only verification)

- [ ] **Step 1: Open the Cite Score page**

Navigate to `http://citewp-dev.local/wp-admin/admin.php?page=citewp#cite-score`

Expected:
- Page header strip renders: "Cite Score" title + description + post picker `<select>`
- Left column: Donut Chart Panel with SVG donut (inner Citrine arc + outer 3-segment arc), category legend rows below, "Improve this post →" link
- Left column: Score History panel with "7D / 30D / 90D" pills (disabled) + empty state dashed line + message
- Right column: Signal table grouped into Structure / Citability / Authority sections, each signal showing label + score + Pass/Partial/Fail badge + message + recommendation (for fail/partial)

- [ ] **Step 2: Test post picker**

Change the `<select>` to a different post. Page should reload with that post's score data.

- [ ] **Step 3: Verify empty state**

Temporarily test with a post that has no score meta (or with `?post_id=999999`). Should show the "No scored content yet" empty state.

- [ ] **Step 4: Check PHP debug.log**

Open LocalWP → site → debug.log. Confirm no new PHP errors from the Cite Score panel render.

- [ ] **Step 5: Push**

```
git push
```

---

## Spec Coverage Check

| Requirement | Covered |
|-------------|---------|
| Page header strip | Task 2 Step 2 — `.citewp-aiso-page-header` |
| Post picker (defaults to most recent) | Task 2 Step 2 — `get_posts()` + `<select>` |
| Donut Chart Panel — two rings | Task 2 Step 3 — `render_donut_svg()` inner + outer circles |
| Donut Chart Panel — center value + label | Task 2 Step 3 — SVG `<text>` elements |
| Donut Chart Panel — category legend rows | Task 2 Step 2 — `__cats` HTML block |
| Donut Chart Panel — "View full breakdown →" (now "Improve this post") | Task 2 Step 2 — `__footer` link |
| Line Chart Panel — header + pills | Task 2 Step 2 — `__head` row |
| Line Chart Panel — empty state | Task 2 Step 2 — `__empty` block per spec |
| Signal breakdown — grouped by category | Task 2 Step 2 — foreach `$groups` |
| Signal breakdown — score / max / status badge | Task 2 Step 2 — `__pts` + `signal-badge` |
| Signal breakdown — message + recommendation | Task 2 Step 2 — `__msg` + `__rec` |
| Empty state (no scored posts) | Task 2 Step 2 — `if ( ! $score_data )` guard |
| Score-band coloring | Task 2 Step 2 — `$grade_colors` + `$cat_color` closure |
| Screen reader text for gauge | Task 2 Step 3 — `<span class="screen-reader-text">` |
| CSS — all layout + component classes | Task 1 Step 1 |
| Pro Tip footer | Task 2 Step 2 — `.citewp-aiso-protip` block after cs-body |
| Responsive stacking below 900px | Task 1 Step 1 — `@media` block |

All requirements covered. No placeholders.
