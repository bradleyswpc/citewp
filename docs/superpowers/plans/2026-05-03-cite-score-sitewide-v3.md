# Cite Score Page v3 — Sitewide Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the first-pass per-post Cite Score detail view with a sitewide dashboard showing average score, category breakdown, AI recommendations, and a paginated post-level score table.

**Architecture:** PHP-rendered admin page (no React). Semi-circle SVG gauge as the hero element. Score history logged daily via WP-cron into WP options (365-entry cap). AI recommendations derive from the 3 most-failed signals across a sample of 50 scored posts, mapped to human-readable copy via `RecommendationMapper`. Paginated post table via `WP_Query` with GET params.

**Tech Stack:** PHP 8.0+, WordPress 6.5+, inline SVG (no chart library), `WP_Query`, WP-cron, WP options API

---

## File Map

**Brain repo (`C:\Users\KingpinBWP\Desktop\CiteWP\Brain\`):**
- Modify: `UI-DESIGN-SYSTEM.md` — amend Donut Chart Panel + 4 new component entries + Cite Score layout spec update + Last Updated line

**Plugin repo (`C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer\`):**
- Create: `includes/Scoring/ScoreHistory.php` — daily avg cron, WP options storage, 365-entry cap
- Modify: `includes/Plugin.php` — wire `ScoreHistory::register()` / `schedule()` / `unschedule()`
- Create: `includes/Admin/RecommendationMapper.php` — 17 signal IDs → copy map
- Modify: `includes/Admin/Menu.php` — replace `render_cite_score_panel()` + `render_donut_svg()` with sitewide dashboard + `render_gauge_svg()` + `render_history_svg()`; add two `use` statements
- Modify: `admin/css/citewp-aiso-admin.css` — replace Section 31 (lines 1841–end) with sitewide dashboard styles

---

### Task 1: Brain — Amend UI-DESIGN-SYSTEM.md

**Repo:** `C:\Users\KingpinBWP\Desktop\CiteWP\Brain`
**Files:** Modify `UI-DESIGN-SYSTEM.md`

- [ ] **Step 1: Amend "Donut Chart Panel" — add semi-circle variant sub-section**

In `UI-DESIGN-SYSTEM.md`, find the Donut Chart Panel's implementation note:

```markdown
**Implementation:** inline SVG, no chart library. Donut math: `circumference = 2πr`, `stroke-dasharray = score/100 * circumference`, `stroke-dashoffset` to rotate start point to 12 o'clock.
```

Replace with:

```markdown
**Implementation:** inline SVG, no chart library. Donut math: `circumference = 2πr`, `stroke-dasharray = score/100 * circumference`, `stroke-dashoffset` to rotate start point to 12 o'clock.

**Semi-circle variant (Cite Score sitewide dashboard):** The Cite Score admin page uses a semi-circle speedometer gauge instead of the full-circle donut. Full-circle donut remains in the Gutenberg sidebar per P22/P31.

- **viewBox:** `0 0 220 115`
- **Geometry:** cx=110, cy=100, r=80, stroke-width=14. Arc path: `M 30 100 A 80 80 0 0 0 190 100` (counter-clockwise, top arc)
- **Color ramp track:** `linearGradient` left→right: `--citewp-score-red` → `--citewp-score-orange` → `--citewp-score-yellow` → `--citewp-score-green`, applied to arc at 35% opacity. Decorative only — shows full 0–100 range.
- **Score fill arc:** Same path, `stroke-dasharray = "(score/100 × πr) (πr)"`, stroke = current score band color, full opacity.
- **Needle:** `<line>` from (110,100) to `(110 + 68×cos(θ), 100 − 68×sin(θ))` where `θ = 180° − (score/100 × 180°)` (math degrees). 4px dot at center. Color: `--citewp-obsidian`.
- **Score text:** JetBrains Mono 800 28px, centered at (110, 78), score band color.
- **Grade label:** Inter 700 10px uppercase, centered at (110, 94), `--citewp-text-muted`.
- **Screen reader:** `<span class="screen-reader-text">Average Cite Score: X out of 100, Band</span>`.
```

- [ ] **Step 2: Add "Score Breakdown Panel" component entry**

After the "Line Chart Panel" section, insert:

```markdown
### Score Breakdown Panel

A paper card showing the three scoring categories (Structure / Citability / Authority) as labeled progress-bar rows. Used in the left column of the Cite Score sitewide dashboard.

**Container spec:**
- Background: `--citewp-paper`, border 1px `--citewp-border`, border-radius 12px, `overflow: hidden`

**Header strip:**
- Background `--citewp-paper-tinted`, border-bottom 1px `--citewp-border`, padding `12px 16px`
- Text: Inter 700 11px uppercase, `letter-spacing: 0.06em`, `--citewp-obsidian`. Label: "SCORE BREAKDOWN"

**Rows (3 — Structure / Citability / Authority):**
- Padding: `12px 16px`, border-bottom 1px `--citewp-border` (last row none)
- Top line: flex row — category name (Inter 500 13px `--citewp-obsidian`) + score/max (JetBrains Mono 700 13px, score-band color of the category's pct). Format: "28 / 35"
- Progress bar: `margin-top: 6px`, 4px height, `border-radius: 999px`. Track: `--citewp-paper-tinted`. Fill: width = `(score/max × 100)%`, color = score-band of the category's pct.

**Score-band pct mapping for categories:** `pct ≥ 80` → green, `≥ 60` → yellow, `≥ 40` → orange, `< 40` → red.
```

- [ ] **Step 3: Add "AI Recommendations Panel" component entry**

After "Score Breakdown Panel", insert:

```markdown
### AI Recommendations Panel

A paper card showing exactly 3 actionable signal recommendations derived from the most-failed signals across all scored posts. Right column of the Cite Score sitewide dashboard.

**Container spec:**
- Background: `--citewp-paper`, border 1px `--citewp-border`, border-radius 12px, `overflow: hidden`

**Header strip:**
- Padding: `14px 20px`, border-bottom 1px `--citewp-border`
- Flex row: 16px Lucide sparkles icon (`--citewp-tint-purple`) + title "AI Recommendations" (Inter 800 14px `--citewp-obsidian`) + BETA badge (right)
- BETA badge: `padding: 3px 8px`, `border-radius: 999px`, background `rgba(124,58,237,0.12)`, color `--citewp-tint-purple`, Inter 800 10px uppercase, `letter-spacing: 0.08em`

**Rows (exactly 3):**
- Padding: `14px 20px`, border-bottom 1px `--citewp-border` (last row none)
- Signal label: Inter 700 13px `--citewp-obsidian`
- Affected count: Inter 400 11px `--citewp-text-muted`, `margin-top: 3px`. Example: "Failing on 8 of 12 sampled posts"
- Recommendation copy: Inter 400 12px `--citewp-text-muted`, `margin-top: 5px`, `line-height: 1.5`. 1–2 sentences.

**Fallback row (fewer than 3 failing signals):** label "Keep publishing", no affected count, copy "Your content is performing well. Keep publishing high-quality posts to maintain your score."
```

- [ ] **Step 4: Add "Post-Level Score Table" component entry**

After "AI Recommendations Panel", insert:

```markdown
### Post-Level Score Table

A paper card containing a server-side paginated and searchable table of all scored posts. Right column of the Cite Score sitewide dashboard, below the AI Recommendations Panel.

**Container spec:**
- Background: `--citewp-paper`, border 1px `--citewp-border`, border-radius 12px, `overflow: hidden`

**Controls row:**
- Padding: `12px 16px`, border-bottom 1px `--citewp-border`, flex row `justify-content: space-between`
- Search `<input type="search" name="css">`: Inter 400 13px, `padding: 6px 10px`, border 1px `--citewp-border`, `border-radius: 8px`, `min-width: 180px`
- Per-page `<select name="cspp">`: same border/padding, options: 10 / 20 / 50, auto-submits on change
- Both controls in separate `<form method="get">` elements that preserve sibling params via hidden inputs

**Table — 6 columns:**
1. Title — `<a>` to edit URL, Inter 500 13px
2. Type — "Post" / "Page", Inter 400 12px `--citewp-text-muted`
3. Score — JetBrains Mono 700 15px, score-band color
4. Grade — `.citewp-aiso-grade-badge--{red|orange|yellow|green}` flat badge, Inter 700 9px uppercase
5. Last Scored — `human_time_diff` format, Inter 400 12px `--citewp-text-muted`
6. Action — "Improve" outline button to edit URL

**Header row:** Inter 700 11px uppercase `--citewp-text-muted`, `letter-spacing: 0.04em`

**Pagination row:**
- Border-top 1px `--citewp-border`, padding `10px 16px`, flex row `justify-content: space-between`
- Left: "Showing X–Y of Z posts" (Inter 400 12px `--citewp-text-muted`)
- Right: Prev / Next outline buttons; disabled = `opacity: 0.4; pointer-events: none`
- GET params: `csp` (page number), `cspp` (per-page), `css` (search query)
```

- [ ] **Step 5: Update Cite Score layout in "Other page layouts (P40)"**

Find:
```markdown
   - **Cite Score:** 2-column — Donut Chart Panel + Line Chart Panel on the left; per-signal breakdown table on the right.
```

Replace with:
```markdown
   - **Cite Score (sitewide dashboard):** 2-column (`1fr / 1.4fr`) — Semi-circle Score Gauge + Score Breakdown Panel + Line Chart Panel stacked left; AI Recommendations Panel + Post-Level Score Table stacked right. Per-post signal breakdown lives in the Gutenberg sidebar (P22/P31) — the admin page is sitewide only.
```

- [ ] **Step 6: Update the "Last updated" header line**

Find the line beginning:
```markdown
> **Last updated:** 2026-05-03 (Session 19 — P41 button rewrite:
```

Prepend the P42 note so the line reads:
```markdown
> **Last updated:** 2026-05-03 (Session 19 — P42 Cite Score sitewide dashboard: semi-circle gauge spec added to Donut Chart Panel; Score Breakdown Panel, AI Recommendations Panel, Post-Level Score Table added to Component Library; Cite Score page layout updated. P41 button rewrite:
```

(Keep the rest of the existing P41 sentence unchanged after the colon.)

- [ ] **Step 7: Commit Brain repo**

```bash
cd "C:\Users\KingpinBWP\Desktop\CiteWP"
git add Brain/UI-DESIGN-SYSTEM.md
git commit -m "docs: Cite Score sitewide dashboard — semi-circle gauge + 4 new component specs (S19)"
git push
```

Expected: clean commit, no errors.

---

### Task 2: ScoreHistory — Daily Average Logging

**Files:**
- Create: `includes/Scoring/ScoreHistory.php`
- Modify: `includes/Plugin.php`

- [ ] **Step 1: Create `includes/Scoring/ScoreHistory.php`**

```php
<?php
/**
 * Logs a daily average Cite Score to WP options for the Score History chart.
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Scoring;

defined( 'ABSPATH' ) || exit;

final class ScoreHistory {

	public const OPTION_KEY   = 'citewp_aiso_score_history';
	public const CRON_HOOK    = 'citewp_aiso_daily_score_log';
	private const MAX_ENTRIES = 365;

	public function register(): void {
		add_action( self::CRON_HOOK, [ $this, 'log_daily_average' ] );
	}

	public function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( strtotime( 'tomorrow midnight' ), 'daily', self::CRON_HOOK );
		}
	}

	public function unschedule(): void {
		$ts = wp_next_scheduled( self::CRON_HOOK );
		if ( $ts ) {
			wp_unschedule_event( $ts, self::CRON_HOOK );
		}
	}

	public function log_daily_average(): void {
		$avg = $this->compute_current_average();
		if ( $avg === null ) {
			return;
		}

		$history = $this->get_raw_history();
		$today   = current_time( 'Y-m-d' );

		$updated = false;
		foreach ( $history as &$entry ) {
			if ( $entry['date'] === $today ) {
				$entry['avg'] = $avg;
				$updated      = true;
				break;
			}
		}
		unset( $entry );

		if ( ! $updated ) {
			$history[] = [ 'date' => $today, 'avg' => $avg ];
		}

		usort( $history, static fn( $a, $b ) => strcmp( $a['date'], $b['date'] ) );

		if ( count( $history ) > self::MAX_ENTRIES ) {
			$history = array_slice( $history, -self::MAX_ENTRIES );
		}

		update_option( self::OPTION_KEY, $history, false );
	}

	/**
	 * @return array<int, array{date: string, avg: float}>
	 */
	public function get_history( int $days = 30 ): array {
		$all    = $this->get_raw_history();
		$cutoff = gmdate( 'Y-m-d', (int) strtotime( "-{$days} days" ) );
		return array_values(
			array_filter( $all, static fn( $e ) => $e['date'] >= $cutoff )
		);
	}

	private function compute_current_average(): ?float {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$avg = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != '' AND meta_value > 0",
				Repository::META_KEY_TOTAL
			)
		);
		return $avg !== null ? (float) $avg : null;
	}

	/**
	 * @return array<int, array{date: string, avg: float}>
	 */
	private function get_raw_history(): array {
		$data = get_option( self::OPTION_KEY, [] );
		return is_array( $data ) ? $data : [];
	}
}
```

- [ ] **Step 2: Wire ScoreHistory into `includes/Plugin.php`**

The cron callback must be registered on every request (cron fires outside `is_admin()`). Add before the `if ( is_admin() )` block in `boot()`:

```php
		// Score history: cron callback registered on every request.
		$this->modules['score_history'] = new Scoring\ScoreHistory();
		$this->modules['score_history']->register();
```

In `activate()`, after `Llms\Router::flush_rewrite_rules_on_activation();`:

```php
		( new Scoring\ScoreHistory() )->schedule();
```

Replace `deactivate()` with:

```php
	public static function deactivate(): void {
		flush_rewrite_rules();
		( new Scoring\ScoreHistory() )->unschedule();
	}
```

- [ ] **Step 3: Verify — load wp-admin, check debug.log**

Open `http://citewp-dev.local/wp-admin/` in the browser.
Expected: no white screen, no new entries in `wp-content/debug.log`.

- [ ] **Step 4: Commit**

```bash
git add includes/Scoring/ScoreHistory.php includes/Plugin.php
git commit -m "feat: ScoreHistory — daily avg cron + WP options, 365-entry cap (S19)"
```

---

### Task 3: RecommendationMapper

**Files:**
- Create: `includes/Admin/RecommendationMapper.php`

- [ ] **Step 1: Create `includes/Admin/RecommendationMapper.php`**

```php
<?php
/**
 * Maps scoring signal IDs to human-readable recommendation copy.
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Admin;

defined( 'ABSPATH' ) || exit;

final class RecommendationMapper {

	/**
	 * @var array<string, array{label: string, copy: string, category: string}>
	 */
	private const MAP = [
		'faq_schema_or_qa'   => [
			'label'    => 'FAQ schema or Q&A pattern',
			'category' => 'structure',
			'copy'     => 'Add a FAQ section with schema markup. AI engines extract Q&A pairs directly from structured data.',
		],
		'heading_hierarchy'  => [
			'label'    => 'Heading hierarchy',
			'category' => 'structure',
			'copy'     => 'Use H2 and H3 headings in logical order. Well-structured headings help AI parse your content.',
		],
		'structured_blocks'  => [
			'label'    => 'Lists or tables',
			'category' => 'structure',
			'copy'     => 'Add bullet lists or data tables where appropriate. Structured content is easier for AI to extract.',
		],
		'answer_first'       => [
			'label'    => 'Answer-first format',
			'category' => 'structure',
			'copy'     => 'Start posts with a direct answer (50–160 words). AI engines favour content that answers the question upfront.',
		],
		'paragraph_chunks'   => [
			'label'    => 'Self-contained passages',
			'category' => 'structure',
			'copy'     => 'Keep paragraphs between 80–200 words. Self-contained chunks are easier for AI to extract as citations.',
		],
		'word_count'         => [
			'label'    => 'Word count',
			'category' => 'structure',
			'copy'     => 'Aim for at least 600 words. Longer, substantive posts are cited more often than thin content.',
		],
		'statistics'         => [
			'label'    => 'Statistics density',
			'category' => 'citability',
			'copy'     => 'Include specific statistics, percentages, or data points. Posts with concrete numbers are cited more often.',
		],
		'external_citations' => [
			'label'    => 'External citations',
			'category' => 'citability',
			'copy'     => 'Link to 2–3 authoritative external sources. Citations signal credibility to AI retrieval systems.',
		],
		'entities'           => [
			'label'    => 'Named entity density',
			'category' => 'citability',
			'copy'     => 'Mention specific people, places, organisations, and products. Entity-rich content performs better in AI retrieval.',
		],
		'non_promotional'    => [
			'label'    => 'Non-promotional tone',
			'category' => 'citability',
			'copy'     => 'Reduce promotional language. AI systems favour objective, informational content over sales copy.',
		],
		'freshness'          => [
			'label'    => 'Freshness',
			'category' => 'citability',
			'copy'     => 'Update older posts with recent information. Freshness signals help AI prefer newer, relevant content.',
		],
		'audience_use_case'  => [
			'label'    => 'Defined audience or use case',
			'category' => 'citability',
			'copy'     => 'Clearly define who the content is for. AI systems match content to specific user intents.',
		],
		'author_byline'      => [
			'label'    => 'Author byline & E-E-A-T',
			'category' => 'authority',
			'copy'     => 'Add an author bio with credentials. Authorship signals expertise and builds E-E-A-T trust with AI crawlers.',
		],
		'internal_links'     => [
			'label'    => 'Internal link density',
			'category' => 'authority',
			'copy'     => 'Link to 2–5 related posts on your site. Internal linking reinforces topical authority.',
		],
		'schema'             => [
			'label'    => 'Schema markup',
			'category' => 'authority',
			'copy'     => 'Add Article or HowTo schema to your post. Structured data helps AI understand and cite your content.',
		],
		'meta_description'   => [
			'label'    => 'Meta description',
			'category' => 'authority',
			'copy'     => 'Write a clear meta description. AI systems use meta descriptions to understand page intent.',
		],
		'featured_image'     => [
			'label'    => 'Featured image with alt',
			'category' => 'authority',
			'copy'     => 'Set a featured image with descriptive alt text. Rich media signals completeness and improves citation probability.',
		],
	];

	/**
	 * @return array{label: string, copy: string, category: string}|null
	 */
	public function get( string $signal_id ): ?array {
		return self::MAP[ $signal_id ] ?? null;
	}

	/**
	 * @param  string[]                                                        $signal_ids
	 * @return array<string, array{label: string, copy: string, category: string}>
	 */
	public function get_many( array $signal_ids ): array {
		$out = [];
		foreach ( $signal_ids as $id ) {
			if ( isset( self::MAP[ $id ] ) ) {
				$out[ $id ] = self::MAP[ $id ];
			}
		}
		return $out;
	}
}
```

- [ ] **Step 2: Verify — load wp-admin, check debug.log**

Open `http://citewp-dev.local/wp-admin/`.
Expected: no new errors (class is autoloaded by PSR-4 from `Admin/` namespace, not yet instantiated).

- [ ] **Step 3: Commit**

```bash
git add includes/Admin/RecommendationMapper.php
git commit -m "feat: RecommendationMapper — 17-signal recommendation copy map (S19)"
```

---

### Task 4: Delete First-Pass Code + Non-Breaking Stub

**Files:**
- Modify: `includes/Admin/Menu.php`
- Modify: `admin/css/citewp-aiso-admin.css`

- [ ] **Step 1: Replace render_cite_score_panel() + render_donut_svg() with a stub**

In `includes/Admin/Menu.php`, the two methods occupy lines 709–978 (line 979 is the class closing `}`). Replace everything from line 709 through line 978 with:

```php
	private function render_cite_score_panel(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="citewp-aiso-page-header">
			<div class="citewp-aiso-page-header__left">
				<h2 class="citewp-aiso-page-header__title"><?php esc_html_e( 'Cite Score', 'ai-search-optimizer' ); ?></h2>
				<p class="citewp-aiso-page-header__desc"><?php esc_html_e( 'Track and improve your site\'s AI citation potential.', 'ai-search-optimizer' ); ?></p>
			</div>
		</div>
		<?php
	}
```

The class closing `}` on line 979 must remain.

- [ ] **Step 2: Replace Section 31 CSS with a placeholder comment**

In `admin/css/citewp-aiso-admin.css`, find line 1841:
```css
/* === SECTION 31: Cite Score page (Session 19) === */
```

Delete from that line to the end of the file (line 2103). Replace with exactly:

```css
/* === SECTION 31: Cite Score sitewide dashboard (Session 19) — styles added in Task 5 === */
```

- [ ] **Step 3: Verify — no fatal errors**

Open `http://citewp-dev.local/wp-admin/admin.php?page=citewp#cite-score`.
Expected: Cite Score section shows page header strip only ("Cite Score" / description). No PHP fatal, no new debug.log entries.

- [ ] **Step 4: Commit**

```bash
git add includes/Admin/Menu.php admin/css/citewp-aiso-admin.css
git commit -m "refactor: remove first-pass per-post Cite Score code (S19)"
```

---

### Task 5: New CSS Section 31 — Sitewide Dashboard Styles

**Files:**
- Modify: `admin/css/citewp-aiso-admin.css`

- [ ] **Step 1: Replace the Section 31 placeholder with full CSS**

Find:
```css
/* === SECTION 31: Cite Score sitewide dashboard (Session 19) — styles added in Task 5 === */
```

Replace with:

```css
/* === SECTION 31: Cite Score sitewide dashboard (Session 19) === */

/* ── Two-column body ── */
.citewp-aiso-cs-body {
  display: grid;
  grid-template-columns: 1fr 1.4fr;
  gap: var(--sp-4);
  align-items: start;
}

.citewp-aiso-cs-left,
.citewp-aiso-cs-right {
  display: flex;
  flex-direction: column;
  gap: var(--sp-4);
}

/* ── Semi-circle Gauge Panel ── */
.citewp-aiso-gauge-panel {
  background: var(--citewp-paper);
  border: 1px solid var(--citewp-border);
  border-radius: var(--radius-lg);
  padding: var(--sp-5);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--sp-2);
}

.citewp-aiso-gauge-panel__meta {
  font: 400 12px/1 'Inter', system-ui, sans-serif;
  color: var(--citewp-text-muted);
  text-align: center;
}

/* ── Score Breakdown Panel ── */
.citewp-aiso-breakdown {
  background: var(--citewp-paper);
  border: 1px solid var(--citewp-border);
  border-radius: var(--radius-lg);
  overflow: hidden;
}

.citewp-aiso-breakdown__head {
  padding: var(--sp-3) var(--sp-4);
  background: var(--citewp-paper-tinted);
  border-bottom: 1px solid var(--citewp-border);
  font: 700 11px/1 'Inter', system-ui, sans-serif;
  color: var(--citewp-obsidian);
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.citewp-aiso-breakdown__row {
  padding: var(--sp-3) var(--sp-4);
  border-bottom: 1px solid var(--citewp-border);
}

.citewp-aiso-breakdown__row:last-child {
  border-bottom: none;
}

.citewp-aiso-breakdown__label-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--sp-2);
}

.citewp-aiso-breakdown__label {
  font: 500 13px/1 'Inter', system-ui, sans-serif;
  color: var(--citewp-obsidian);
}

.citewp-aiso-breakdown__score {
  font: 700 13px/1 'JetBrains Mono', monospace;
}

.citewp-aiso-breakdown__bar {
  height: 4px;
  background: var(--citewp-paper-tinted);
  border: 1px solid var(--citewp-border);
  border-radius: 999px;
  overflow: hidden;
}

.citewp-aiso-breakdown__fill {
  height: 100%;
  border-radius: 999px;
  transition: width 0.3s ease;
}

/* ── Score History Panel ── */
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
  margin-bottom: var(--sp-3);
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
  text-decoration: none;
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
  gap: var(--sp-2);
  padding: var(--sp-4) 0;
}

.citewp-aiso-history-panel__empty-text {
  font: 400 12px/1.5 'Inter', system-ui, sans-serif;
  color: var(--citewp-text-muted);
  text-align: center;
  max-width: 220px;
}

.citewp-aiso-history-panel__stats {
  display: flex;
  gap: var(--sp-6);
  margin-top: var(--sp-3);
}

.citewp-aiso-history-panel__stat-label {
  font: 700 11px/1 'Inter', system-ui, sans-serif;
  color: var(--citewp-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.citewp-aiso-history-panel__stat-value {
  font: 700 20px/1 'JetBrains Mono', monospace;
  color: var(--citewp-obsidian);
  margin-top: 4px;
}

/* ── AI Recommendations Panel ── */
.citewp-aiso-recs {
  background: var(--citewp-paper);
  border: 1px solid var(--citewp-border);
  border-radius: var(--radius-lg);
  overflow: hidden;
}

.citewp-aiso-recs__head {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  padding: var(--sp-4) var(--sp-5);
  border-bottom: 1px solid var(--citewp-border);
}

.citewp-aiso-recs__icon {
  color: var(--citewp-tint-purple);
  flex-shrink: 0;
}

.citewp-aiso-recs__title {
  font: 800 14px/1 'Inter', system-ui, sans-serif;
  color: var(--citewp-obsidian);
  flex: 1;
}

.citewp-aiso-recs__badge {
  font: 800 10px/1 'Inter', system-ui, sans-serif;
  padding: 3px 8px;
  border-radius: 999px;
  background: rgba(124, 58, 237, 0.12);
  color: var(--citewp-tint-purple);
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.citewp-aiso-recs__row {
  padding: var(--sp-4) var(--sp-5);
  border-bottom: 1px solid var(--citewp-border);
}

.citewp-aiso-recs__row:last-child {
  border-bottom: none;
}

.citewp-aiso-recs__label {
  font: 700 13px/1 'Inter', system-ui, sans-serif;
  color: var(--citewp-obsidian);
}

.citewp-aiso-recs__affected {
  font: 400 11px/1 'Inter', system-ui, sans-serif;
  color: var(--citewp-text-muted);
  margin-top: 3px;
}

.citewp-aiso-recs__copy {
  font: 400 12px/1.5 'Inter', system-ui, sans-serif;
  color: var(--citewp-text-muted);
  margin-top: 5px;
}

/* ── Post-Level Score Table ── */
.citewp-aiso-cs-table-wrap {
  background: var(--citewp-paper);
  border: 1px solid var(--citewp-border);
  border-radius: var(--radius-lg);
  overflow: hidden;
}

.citewp-aiso-cs-controls {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--sp-3) var(--sp-4);
  border-bottom: 1px solid var(--citewp-border);
  gap: var(--sp-2);
}

.citewp-aiso-cs-search,
.citewp-aiso-cs-perpage {
  font: 400 13px/1 'Inter', system-ui, sans-serif;
  color: var(--citewp-obsidian);
  background: var(--citewp-paper);
  border: 1px solid var(--citewp-border);
  border-radius: var(--radius-md);
  padding: 6px 10px;
}

.citewp-aiso-cs-search {
  min-width: 180px;
}

.citewp-aiso-cs-table {
  width: 100%;
  border-collapse: collapse;
}

.citewp-aiso-cs-table th {
  font: 700 11px/1 'Inter', system-ui, sans-serif;
  color: var(--citewp-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: var(--sp-2) var(--sp-3);
  border-bottom: 1px solid var(--citewp-border);
  text-align: left;
  white-space: nowrap;
}

.citewp-aiso-cs-table td {
  font: 400 13px/1.4 'Inter', system-ui, sans-serif;
  color: var(--citewp-obsidian);
  padding: var(--sp-3);
  border-bottom: 1px solid var(--citewp-border);
  vertical-align: middle;
}

.citewp-aiso-cs-table tr:last-child td {
  border-bottom: none;
}

.citewp-aiso-cs-table tr:nth-child(even) td {
  background: var(--citewp-paper-tinted);
}

.citewp-aiso-cs-table__score {
  font: 700 15px/1 'JetBrains Mono', monospace;
}

.citewp-aiso-cs-table__type,
.citewp-aiso-cs-table__time {
  font: 400 12px/1 'Inter', system-ui, sans-serif;
  color: var(--citewp-text-muted);
  white-space: nowrap;
}

.citewp-aiso-grade-badge {
  display: inline-block;
  font: 700 9px/1 'Inter', system-ui, sans-serif;
  padding: 3px 7px;
  border-radius: var(--radius-sm);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  white-space: nowrap;
}

.citewp-aiso-grade-badge--green  { background: #dcfce7; color: var(--citewp-score-green); }
.citewp-aiso-grade-badge--yellow { background: #fef3c7; color: #b45309; }
.citewp-aiso-grade-badge--orange { background: #fee2e2; color: var(--citewp-score-red); }
.citewp-aiso-grade-badge--red    { background: #fee2e2; color: var(--citewp-score-red); }

.citewp-aiso-cs-pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--sp-3) var(--sp-4);
  border-top: 1px solid var(--citewp-border);
}

.citewp-aiso-cs-pagination__info {
  font: 400 12px/1 'Inter', system-ui, sans-serif;
  color: var(--citewp-text-muted);
}

.citewp-aiso-cs-pagination__nav {
  display: flex;
  gap: var(--sp-2);
}

/* ── Empty state (no scored posts) ── */
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

- [ ] **Step 2: Verify CSS parses**

Open `http://citewp-dev.local/wp-admin/admin.php?page=citewp#cite-score`.
Open browser DevTools → Console.
Expected: no CSS parse errors. The stub PHP still renders (just header strip), but the new classes are available.

- [ ] **Step 3: Commit**

```bash
git add admin/css/citewp-aiso-admin.css
git commit -m "feat: Cite Score sitewide dashboard CSS — Section 31 (S19)"
```

---

### Task 6: New PHP — render_cite_score_panel() Sitewide Dashboard

**Files:**
- Modify: `includes/Admin/Menu.php`

This is the largest task. Replace the stub and add two private helper methods.

- [ ] **Step 1: Add two `use` statements at top of Menu.php**

The file currently has (lines 12–14):
```php
use CiteWP\Aiso\Admin\DashboardData;
use CiteWP\Aiso\Admin\IconLibrary;
use CiteWP\Aiso\Scoring\Repository;
```

Add after line 14:
```php
use CiteWP\Aiso\Admin\RecommendationMapper;
use CiteWP\Aiso\Scoring\ScoreHistory;
```

- [ ] **Step 2: Replace the stub `render_cite_score_panel()` with the full implementation**

Find:
```php
	private function render_cite_score_panel(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="citewp-aiso-page-header">
			<div class="citewp-aiso-page-header__left">
				<h2 class="citewp-aiso-page-header__title"><?php esc_html_e( 'Cite Score', 'ai-search-optimizer' ); ?></h2>
				<p class="citewp-aiso-page-header__desc"><?php esc_html_e( 'Track and improve your site\'s AI citation potential.', 'ai-search-optimizer' ); ?></p>
			</div>
		</div>
		<?php
	}
```

Replace with this full method (the two helper methods `render_gauge_svg` and `render_history_svg` are added in Steps 3–4 below; this step adds the main orchestrator only):

```php
	private function render_cite_score_panel(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// ── All scored post IDs ─────────────────────────────────────────
		$scored_ids   = get_posts( [
			'post_type'      => [ 'post', 'page' ],
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => 1000,
			'meta_key'       => Repository::META_KEY_TOTAL,
			'meta_compare'   => 'EXISTS',
			'no_found_rows'  => true,
			'fields'         => 'ids',
		] );
		$total_scored = count( $scored_ids );

		// ── Site-wide stats (sample first 50 for signal analysis) ───────
		$score_sum    = 0;
		$issue_count  = 0;
		$cat_sums     = [ 'structure' => 0, 'citability' => 0, 'authority' => 0 ];
		$signal_fails = [];
		$sample_cap   = 50;

		foreach ( $scored_ids as $i => $pid ) {
			$total      = (int) get_post_meta( (int) $pid, Repository::META_KEY_TOTAL, true );
			$score_sum += $total;
			if ( $total < 50 ) {
				++$issue_count;
			}
			if ( $i < $sample_cap ) {
				$data = ( new Repository() )->get( (int) $pid );
				if ( $data && isset( $data['categories'] ) ) {
					foreach ( array_keys( $cat_sums ) as $cat_key ) {
						if ( isset( $data['categories'][ $cat_key ]['score'] ) ) {
							$cat_sums[ $cat_key ] += (int) $data['categories'][ $cat_key ]['score'];
						}
					}
				}
				if ( $data && isset( $data['signals'] ) ) {
					foreach ( $data['signals'] as $sig ) {
						if ( in_array( $sig['status'], [ 'fail', 'partial' ], true ) ) {
							$signal_fails[ $sig['id'] ] = ( $signal_fails[ $sig['id'] ] ?? 0 ) + 1;
						}
					}
				}
			}
		}

		$avg_score = $total_scored > 0 ? (int) round( $score_sum / $total_scored ) : null;
		$avg_grade = 'empty';
		if ( $avg_score !== null ) {
			$avg_grade = match ( true ) {
				$avg_score >= 80 => 'green',
				$avg_score >= 60 => 'yellow',
				$avg_score >= 40 => 'orange',
				default          => 'red',
			};
		}

		$sample_n = min( $total_scored, $sample_cap );
		$cat_avgs = [];
		foreach ( $cat_sums as $cat_key => $sum ) {
			$cat_avgs[ $cat_key ] = $sample_n > 0 ? (int) round( $sum / $sample_n ) : 0;
		}

		// ── Top 3 failing signals → AI Recommendations ──────────────────
		arsort( $signal_fails );
		$top_signal_ids = array_slice( array_keys( $signal_fails ), 0, 3 );
		$mapper         = new RecommendationMapper();
		$top_recs       = $mapper->get_many( $top_signal_ids );
		$top_rec_ids    = array_keys( $top_recs );

		// Pad to exactly 3 rows.
		$recs_display = array_values( $top_recs );
		while ( count( $recs_display ) < 3 ) {
			$recs_display[] = [
				'label'    => __( 'Keep publishing', 'ai-search-optimizer' ),
				'copy'     => __( 'Your content is performing well. Keep publishing high-quality posts to maintain your score.', 'ai-search-optimizer' ),
				'category' => '',
			];
		}

		// ── Score History ────────────────────────────────────────────────
		$history_range = absint( $_GET['cs_range'] ?? 30 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$history_range = in_array( $history_range, [ 7, 30, 90 ], true ) ? $history_range : 30;
		$history       = ( new ScoreHistory() )->get_history( $history_range );
		$hist_avg      = ! empty( $history ) ? (int) round( array_sum( array_column( $history, 'avg' ) ) / count( $history ) ) : null;
		$hist_peak     = ! empty( $history ) ? (int) round( (float) max( array_column( $history, 'avg' ) ) ) : null;

		// ── Paginated post table ─────────────────────────────────────────
		$paged     = max( 1, absint( $_GET['csp'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per_page  = in_array( (int) ( $_GET['cspp'] ?? 20 ), [ 10, 20, 50 ], true ) ? (int) $_GET['cspp'] : 20; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search_q  = sanitize_text_field( wp_unslash( $_GET['css'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tbl_args  = [
			'post_type'      => [ 'post', 'page' ],
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'orderby'        => 'meta_value_num',
			'meta_key'       => Repository::META_KEY_TOTAL,
			'order'          => 'ASC',
			'meta_query'     => [ [ 'key' => Repository::META_KEY_TOTAL, 'compare' => 'EXISTS' ] ],
		];
		if ( $search_q !== '' ) {
			$tbl_args['s'] = $search_q;
		}
		$tbl_q       = new \WP_Query( $tbl_args );
		$total_pages = $tbl_q->max_num_pages;
		$first_item  = ( $paged - 1 ) * $per_page + 1;
		$last_item   = min( $paged * $per_page, $tbl_q->found_posts );

		// ── Category display metadata ────────────────────────────────────
		$cat_meta = [
			'structure'  => [ 'label' => __( 'Structure',  'ai-search-optimizer' ), 'max' => 35 ],
			'citability' => [ 'label' => __( 'Citability', 'ai-search-optimizer' ), 'max' => 40 ],
			'authority'  => [ 'label' => __( 'Authority',  'ai-search-optimizer' ), 'max' => 25 ],
		];

		$base_url   = admin_url( 'admin.php' );
		$base_q     = [ 'page' => self::SLUG_PARENT ];
		$band_color = static function ( string $grade ): string {
			return match ( $grade ) {
				'green'  => 'var(--citewp-score-green)',
				'yellow' => 'var(--citewp-score-yellow)',
				'orange' => 'var(--citewp-score-orange)',
				default  => 'var(--citewp-score-red)',
			};
		};
		?>

		<!-- Page header strip -->
		<div class="citewp-aiso-page-header">
			<div class="citewp-aiso-page-header__left">
				<h2 class="citewp-aiso-page-header__title"><?php esc_html_e( 'Cite Score', 'ai-search-optimizer' ); ?></h2>
				<p class="citewp-aiso-page-header__desc"><?php esc_html_e( 'Track and improve your site\'s AI citation potential.', 'ai-search-optimizer' ); ?></p>
			</div>
		</div>

		<?php if ( 0 === $total_scored ) : ?>
		<!-- Empty state -->
		<div class="citewp-aiso-cs-empty">
			<div class="citewp-aiso-empty__icon"><?php echo IconLibrary::icon( 'gauge', 48 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<h3 class="citewp-aiso-empty__title"><?php esc_html_e( 'No scored content yet.', 'ai-search-optimizer' ); ?></h3>
			<p class="citewp-aiso-empty__text"><?php esc_html_e( 'Open and save any post or page to generate your first Cite Score.', 'ai-search-optimizer' ); ?></p>
		</div>
		<?php else : ?>

		<!-- Two-column body -->
		<div class="citewp-aiso-cs-body">

			<!-- Left column -->
			<div class="citewp-aiso-cs-left">

				<!-- Semi-circle gauge -->
				<div class="citewp-aiso-gauge-panel">
					<?php $this->render_gauge_svg( $avg_score ?? 0, $avg_grade ); ?>
					<p class="citewp-aiso-gauge-panel__meta">
						<?php
						$published_total = (int) wp_count_posts( 'post' )->publish + (int) wp_count_posts( 'page' )->publish;
						printf(
							/* translators: %1$d: scored posts, %2$d: total published */
							esc_html__( '%1$d of %2$d posts scored', 'ai-search-optimizer' ),
							$total_scored,
							$published_total
						);
						?>
					</p>
				</div>

				<!-- Score breakdown -->
				<div class="citewp-aiso-breakdown">
					<div class="citewp-aiso-breakdown__head"><?php esc_html_e( 'Score Breakdown', 'ai-search-optimizer' ); ?></div>
					<?php foreach ( $cat_meta as $cat_key => $cat_info ) :
						$avg_cat   = $cat_avgs[ $cat_key ] ?? 0;
						$cat_max   = $cat_info['max'];
						$pct       = $cat_max > 0 ? ( $avg_cat / $cat_max ) * 100 : 0;
						$cat_grade = match ( true ) {
							$pct >= 80 => 'green',
							$pct >= 60 => 'yellow',
							$pct >= 40 => 'orange',
							default    => 'red',
						};
						$bar_color = $band_color( $cat_grade );
					?>
					<div class="citewp-aiso-breakdown__row">
						<div class="citewp-aiso-breakdown__label-row">
							<span class="citewp-aiso-breakdown__label"><?php echo esc_html( $cat_info['label'] ); ?></span>
							<span class="citewp-aiso-breakdown__score" style="color:<?php echo esc_attr( $bar_color ); ?>">
								<?php echo esc_html( $avg_cat . ' / ' . $cat_max ); ?>
							</span>
						</div>
						<div class="citewp-aiso-breakdown__bar">
							<div class="citewp-aiso-breakdown__fill" style="width:<?php echo esc_attr( round( $pct, 1 ) . '%' ); ?>;background:<?php echo esc_attr( $bar_color ); ?>"></div>
						</div>
					</div>
					<?php endforeach; ?>
				</div>

				<!-- Score history -->
				<div class="citewp-aiso-history-panel">
					<div class="citewp-aiso-history-panel__head">
						<span class="citewp-aiso-history-panel__title"><?php esc_html_e( 'Score History', 'ai-search-optimizer' ); ?></span>
						<div class="citewp-aiso-history-panel__pills">
							<?php foreach ( [ 7, 30, 90 ] as $days ) :
								$range_url = esc_url( add_query_arg( array_merge( $base_q, [ 'cs_range' => $days ] ), $base_url ) . '#cite-score' );
							?>
							<a href="<?php echo $range_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — esc_url already applied ?>"
							   class="citewp-aiso-history-pill<?php echo $days === $history_range ? ' is-active' : ''; ?>">
								<?php echo esc_html( $days . 'D' ); ?>
							</a>
							<?php endforeach; ?>
						</div>
					</div>
					<div class="citewp-aiso-history-panel__chart">
						<?php $this->render_history_svg( $history ); ?>
					</div>
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

			</div><!-- /.citewp-aiso-cs-left -->

			<!-- Right column -->
			<div class="citewp-aiso-cs-right">

				<!-- AI Recommendations -->
				<div class="citewp-aiso-recs">
					<div class="citewp-aiso-recs__head">
						<span class="citewp-aiso-recs__icon"><?php echo IconLibrary::icon( 'sparkles', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<span class="citewp-aiso-recs__title"><?php esc_html_e( 'AI Recommendations', 'ai-search-optimizer' ); ?></span>
						<span class="citewp-aiso-recs__badge"><?php esc_html_e( 'BETA', 'ai-search-optimizer' ); ?></span>
					</div>
					<?php foreach ( $recs_display as $idx => $rec ) :
						$rec_signal_id = $top_rec_ids[ $idx ] ?? '';
						$fail_count    = $signal_fails[ $rec_signal_id ] ?? 0;
					?>
					<div class="citewp-aiso-recs__row">
						<div class="citewp-aiso-recs__label"><?php echo esc_html( $rec['label'] ); ?></div>
						<?php if ( $fail_count > 0 ) : ?>
						<div class="citewp-aiso-recs__affected">
							<?php
							printf(
								/* translators: %1$d: fail count, %2$d: sample size */
								esc_html__( 'Failing on %1$d of %2$d sampled posts', 'ai-search-optimizer' ),
								$fail_count,
								$sample_n
							);
							?>
						</div>
						<?php endif; ?>
						<div class="citewp-aiso-recs__copy"><?php echo esc_html( $rec['copy'] ); ?></div>
					</div>
					<?php endforeach; ?>
				</div>

				<!-- Post-level table -->
				<div class="citewp-aiso-cs-table-wrap">
					<div class="citewp-aiso-cs-controls">
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
						<form method="get" action="<?php echo esc_url( $base_url ); ?>">
							<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG_PARENT ); ?>">
							<input type="hidden" name="css" value="<?php echo esc_attr( $search_q ); ?>">
							<select name="cspp" class="citewp-aiso-cs-perpage" onchange="this.form.submit()">
								<?php foreach ( [ 10, 20, 50 ] as $pp ) : ?>
								<option value="<?php echo esc_attr( (string) $pp ); ?>"<?php selected( $pp, $per_page ); ?>><?php echo esc_html( (string) $pp ); ?></option>
								<?php endforeach; ?>
							</select>
						</form>
					</div>

					<?php if ( $tbl_q->have_posts() ) : ?>
					<table class="citewp-aiso-cs-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Title',       'ai-search-optimizer' ); ?></th>
								<th><?php esc_html_e( 'Type',        'ai-search-optimizer' ); ?></th>
								<th><?php esc_html_e( 'Score',       'ai-search-optimizer' ); ?></th>
								<th><?php esc_html_e( 'Grade',       'ai-search-optimizer' ); ?></th>
								<th><?php esc_html_e( 'Last Scored', 'ai-search-optimizer' ); ?></th>
								<th><?php esc_html_e( 'Action',      'ai-search-optimizer' ); ?></th>
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
								$t_type_obj = get_post_type_object( (string) get_post_type() );
								$t_type     = $t_type_obj ? $t_type_obj->labels->singular_name : (string) get_post_type();
								$t_edit_url = get_edit_post_link( $t_id );
							?>
							<tr>
								<td>
									<?php if ( $t_edit_url ) : ?>
									<a href="<?php echo esc_url( $t_edit_url ); ?>"><?php echo esc_html( get_the_title() ?: __( '(no title)', 'ai-search-optimizer' ) ); ?></a>
									<?php else : ?>
									<?php echo esc_html( get_the_title() ?: __( '(no title)', 'ai-search-optimizer' ) ); ?>
									<?php endif; ?>
								</td>
								<td class="citewp-aiso-cs-table__type"><?php echo esc_html( $t_type ); ?></td>
								<td class="citewp-aiso-cs-table__score" style="color:<?php echo esc_attr( $band_color( $t_grade ) ); ?>"><?php echo esc_html( (string) $t_score ); ?></td>
								<td><span class="citewp-aiso-grade-badge citewp-aiso-grade-badge--<?php echo esc_attr( $t_grade ); ?>"><?php echo esc_html( ucfirst( $t_grade ) ); ?></span></td>
								<td class="citewp-aiso-cs-table__time"><?php echo esc_html( $t_time_ago ); ?></td>
								<td>
									<?php if ( $t_edit_url ) : ?>
									<a href="<?php echo esc_url( $t_edit_url ); ?>" class="citewp-aiso-btn citewp-aiso-btn--outline"><?php esc_html_e( 'Improve', 'ai-search-optimizer' ); ?></a>
									<?php endif; ?>
								</td>
							</tr>
							<?php endwhile; wp_reset_postdata(); ?>
						</tbody>
					</table>

					<div class="citewp-aiso-cs-pagination">
						<span class="citewp-aiso-cs-pagination__info">
							<?php
							printf(
								/* translators: %1$d: first, %2$d: last, %3$d: total */
								esc_html__( 'Showing %1$d–%2$d of %3$d posts', 'ai-search-optimizer' ),
								$first_item,
								$last_item,
								$tbl_q->found_posts
							);
							?>
						</span>
						<div class="citewp-aiso-cs-pagination__nav">
							<?php
							$prev_url = esc_url( add_query_arg( array_merge( $base_q, [ 'csp' => $paged - 1, 'cspp' => $per_page, 'css' => $search_q ] ), $base_url ) . '#cite-score' );
							$next_url = esc_url( add_query_arg( array_merge( $base_q, [ 'csp' => $paged + 1, 'cspp' => $per_page, 'css' => $search_q ] ), $base_url ) . '#cite-score' );
							?>
							<?php if ( $paged > 1 ) : ?>
							<a href="<?php echo $prev_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" class="citewp-aiso-btn citewp-aiso-btn--outline"><?php esc_html_e( '← Prev', 'ai-search-optimizer' ); ?></a>
							<?php else : ?>
							<span class="citewp-aiso-btn citewp-aiso-btn--outline" aria-disabled="true" style="opacity:0.4;pointer-events:none"><?php esc_html_e( '← Prev', 'ai-search-optimizer' ); ?></span>
							<?php endif; ?>
							<?php if ( $paged < $total_pages ) : ?>
							<a href="<?php echo $next_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" class="citewp-aiso-btn citewp-aiso-btn--outline"><?php esc_html_e( 'Next →', 'ai-search-optimizer' ); ?></a>
							<?php else : ?>
							<span class="citewp-aiso-btn citewp-aiso-btn--outline" aria-disabled="true" style="opacity:0.4;pointer-events:none"><?php esc_html_e( 'Next →', 'ai-search-optimizer' ); ?></span>
							<?php endif; ?>
						</div>
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

			</div><!-- /.citewp-aiso-cs-right -->

		</div><!-- /.citewp-aiso-cs-body -->

		<!-- Pro Tip footer -->
		<div class="citewp-aiso-protip">
			<div class="citewp-aiso-protip__left">
				<div class="citewp-aiso-protip__orb"><?php echo IconLibrary::icon( 'zap', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<div class="citewp-aiso-protip__content">
					<p class="citewp-aiso-protip__heading"><?php esc_html_e( 'Pro Tip', 'ai-search-optimizer' ); ?></p>
					<p class="citewp-aiso-protip__body"><?php esc_html_e( 'Adding FAQ schema to your top posts is the fastest way to raise your site-wide Cite Score.', 'ai-search-optimizer' ); ?></p>
				</div>
			</div>
			<a href="https://citewp.com/pro" target="_blank" rel="noopener noreferrer" class="citewp-aiso-btn citewp-aiso-btn--primary-paper">
				<?php esc_html_e( 'Learn More →', 'ai-search-optimizer' ); ?>
			</a>
		</div>

		<?php endif; // total_scored === 0
	}
```

- [ ] **Step 3: Add `render_gauge_svg()` after the closing `}` of `render_cite_score_panel()`**

```php
	private function render_gauge_svg( int $score, string $grade ): void {
		$cx      = 110;
		$cy      = 100;
		$r       = 80;
		$sw      = 14;
		$arc_len = round( M_PI * $r, 2 ); // ≈ 251.33

		$x_left   = $cx - $r; // 30
		$x_right  = $cx + $r; // 190
		$fill_len = round( ( $score / 100 ) * $arc_len, 2 );

		$angle_rad = ( 180.0 - ( $score / 100.0 ) * 180.0 ) * M_PI / 180.0;
		$needle_r  = 68;
		$needle_x  = round( $cx + $needle_r * cos( $angle_rad ), 2 );
		$needle_y  = round( $cy - $needle_r * sin( $angle_rad ), 2 );

		$score_colors = [
			'green'  => 'var(--citewp-score-green)',
			'yellow' => 'var(--citewp-score-yellow)',
			'orange' => 'var(--citewp-score-orange)',
			'red'    => 'var(--citewp-score-red)',
			'empty'  => 'var(--citewp-text-muted)',
		];
		$grade_labels = [
			'green'  => __( 'Good',       'ai-search-optimizer' ),
			'yellow' => __( 'Fair',       'ai-search-optimizer' ),
			'orange' => __( 'Needs Work', 'ai-search-optimizer' ),
			'red'    => __( 'Poor',       'ai-search-optimizer' ),
			'empty'  => __( 'No data',    'ai-search-optimizer' ),
		];
		$score_color = $score_colors[ $grade ] ?? 'var(--citewp-text-muted)';
		$grade_label = $grade_labels[ $grade ] ?? '';
		$arc_path    = 'M ' . $x_left . ' ' . $cy . ' A ' . $r . ' ' . $r . ' 0 0 0 ' . $x_right . ' ' . $cy;
		?>
		<svg viewBox="0 0 220 115" width="220" height="115" aria-hidden="true" focusable="false" style="display:block;margin:0 auto">
			<defs>
				<linearGradient id="citewp-gauge-ramp" x1="0%" y1="0%" x2="100%" y2="0%">
					<stop offset="0%"   stop-color="var(--citewp-score-red)"/>
					<stop offset="33%"  stop-color="var(--citewp-score-orange)"/>
					<stop offset="66%"  stop-color="var(--citewp-score-yellow)"/>
					<stop offset="100%" stop-color="var(--citewp-score-green)"/>
				</linearGradient>
			</defs>
			<path d="<?php echo esc_attr( $arc_path ); ?>" fill="none"
				stroke="url(#citewp-gauge-ramp)" stroke-width="<?php echo esc_attr( (string) $sw ); ?>"
				stroke-linecap="round" opacity="0.35"/>
			<path d="<?php echo esc_attr( $arc_path ); ?>" fill="none"
				stroke="<?php echo esc_attr( $score_color ); ?>" stroke-width="<?php echo esc_attr( (string) $sw ); ?>"
				stroke-linecap="round"
				stroke-dasharray="<?php echo esc_attr( $fill_len . ' ' . $arc_len ); ?>"
				stroke-dashoffset="0"/>
			<line
				x1="<?php echo esc_attr( (string) $cx ); ?>" y1="<?php echo esc_attr( (string) $cy ); ?>"
				x2="<?php echo esc_attr( (string) $needle_x ); ?>" y2="<?php echo esc_attr( (string) $needle_y ); ?>"
				stroke="var(--citewp-obsidian)" stroke-width="2.5" stroke-linecap="round"/>
			<circle cx="<?php echo esc_attr( (string) $cx ); ?>" cy="<?php echo esc_attr( (string) $cy ); ?>"
				r="5" fill="var(--citewp-obsidian)"/>
			<text x="<?php echo esc_attr( (string) $cx ); ?>" y="<?php echo esc_attr( (string) ( $cy - 22 ) ); ?>"
				text-anchor="middle" dominant-baseline="middle"
				font-family="'JetBrains Mono', monospace" font-size="28" font-weight="800"
				fill="<?php echo esc_attr( $score_color ); ?>"><?php echo esc_html( $score > 0 ? (string) $score : '—' ); ?></text>
			<text x="<?php echo esc_attr( (string) $cx ); ?>" y="<?php echo esc_attr( (string) ( $cy - 6 ) ); ?>"
				text-anchor="middle" dominant-baseline="middle"
				font-family="'Inter', sans-serif" font-size="10" font-weight="700"
				fill="var(--citewp-text-muted)" letter-spacing="0.06em"><?php echo esc_html( strtoupper( $grade_label ) ); ?></text>
		</svg>
		<span class="screen-reader-text">
			<?php
			printf(
				/* translators: %1$d: score, %2$s: grade band */
				esc_html__( 'Average Cite Score: %1$d out of 100, %2$s', 'ai-search-optimizer' ),
				$score,
				esc_html( $grade_label )
			);
			?>
		</span>
		<?php
	}
```

- [ ] **Step 4: Add `render_history_svg()` after `render_gauge_svg()`, before the class closing `}`**

```php
	/**
	 * @param array<int, array{date: string, avg: float}> $history
	 */
	private function render_history_svg( array $history ): void {
		if ( empty( $history ) ) {
			?>
			<div class="citewp-aiso-history-panel__empty">
				<svg viewBox="0 0 340 60" width="100%" height="60" aria-hidden="true">
					<line x1="0" y1="30" x2="340" y2="30" stroke="var(--citewp-border)" stroke-width="2" stroke-dasharray="6 4"/>
				</svg>
				<p class="citewp-aiso-history-panel__empty-text">
					<?php esc_html_e( 'Not enough history yet. Scores appear after the daily cron runs.', 'ai-search-optimizer' ); ?>
				</p>
			</div>
			<?php
			return;
		}

		$w      = 340;
		$h      = 80;
		$n      = count( $history );
		$scores = array_column( $history, 'avg' );
		$min_s  = (float) min( $scores );
		$max_s  = (float) max( $scores );
		$rng    = max( 1.0, $max_s - $min_s );

		$pts = [];
		foreach ( $history as $i => $entry ) {
			$x     = $n > 1 ? (int) round( ( $i / ( $n - 1 ) ) * $w ) : (int) ( $w / 2 );
			$y     = (int) round( $h - ( ( (float) $entry['avg'] - $min_s ) / $rng ) * ( $h * 0.8 ) - $h * 0.1 );
			$pts[] = [ 'x' => $x, 'y' => $y ];
		}

		$poly = implode( ' ', array_map( static fn( $p ) => "{$p['x']},{$p['y']}", $pts ) );
		$last = end( $pts );
		$frst = reset( $pts );
		$area = 'M ' . implode( ' L ', array_map( static fn( $p ) => "{$p['x']} {$p['y']}", $pts ) )
		        . " L {$last['x']} {$h} L {$frst['x']} {$h} Z";
		?>
		<svg viewBox="0 0 <?php echo esc_attr( (string) $w ); ?> <?php echo esc_attr( (string) $h ); ?>"
			width="100%" height="<?php echo esc_attr( (string) $h ); ?>" aria-hidden="true">
			<path d="<?php echo esc_attr( $area ); ?>" fill="rgba(232,212,0,0.08)"/>
			<polyline points="<?php echo esc_attr( $poly ); ?>" fill="none"
				stroke="var(--citewp-citrine)" stroke-width="2"
				stroke-linejoin="round" stroke-linecap="round"/>
			<?php foreach ( $pts as $pt ) : ?>
			<circle cx="<?php echo esc_attr( (string) $pt['x'] ); ?>" cy="<?php echo esc_attr( (string) $pt['y'] ); ?>"
				r="3" fill="var(--citewp-citrine)"/>
			<?php endforeach; ?>
		</svg>
		<?php
	}
```

- [ ] **Step 5: Verify full page renders**

Open `http://citewp-dev.local/wp-admin/admin.php?page=citewp#cite-score`.

Check each element:
- Page header strip shows "Cite Score" title and description
- If posts are scored: two-column body renders
  - Left: semi-circle SVG gauge (arc + needle + score number), Score Breakdown (3 progress bars), Score History (empty state dashed line)
  - Right: AI Recommendations (3 rows + BETA badge), Post-level table (6 columns, Prev/Next buttons)
- If no posts scored: centered empty-state card
- No PHP fatal errors in `debug.log`
- No JS console errors in browser

- [ ] **Step 6: Commit + push**

```bash
git add includes/Admin/Menu.php
git commit -m "feat: Cite Score sitewide dashboard — PHP render (gauge, breakdown, recs, table) (S19)"
git push
```

---

## Self-Review Checklist

**Spec coverage:**
- ✅ Part A — UI-DESIGN-SYSTEM.md: Donut Chart Panel amended, 4 new entries (Score Breakdown, AI Recommendations, Post-Level Score Table; semi-circle spec inside Donut Chart Panel counts as the 4th addition), Cite Score layout updated, Last Updated line amended
- ✅ Part B — ScoreHistory.php: daily cron (`citewp_aiso_daily_score_log`), WP options (`citewp_aiso_score_history`), 365-entry cap, schedule on activation, unschedule on deactivation
- ✅ Part C — render_cite_score_panel(): sitewide (not per-post), semi-circle gauge, score breakdown, history chart, AI recommendations, paginated post table, Pro Tip footer, empty state
- ✅ Part D — RecommendationMapper.php: all 17 signal IDs from Engine.php covered
- ✅ Page header strip (P40 non-Dashboard rule)
- ✅ Score Breakdown: 3 rows with progress bars
- ✅ AI Recommendations: exactly 3 rows, BETA badge, padded with fallback
- ✅ Post table: 6 columns, search (`css`), pagination (`csp`), per-page select (`cspp`)
- ✅ Score History: empty state until cron runs

**No placeholders:** All 17 signal IDs present in MAP. All CSS classes defined in Section 31. All PHP methods have complete code.

**Type consistency:**
- `ScoreHistory::get_history(int $days)` → `array<int, array{date: string, avg: float}>` — consumed by `render_history_svg(array $history)` ✅
- `RecommendationMapper::get_many(array $signal_ids)` → `array<string, array{label, copy, category}>` — consumed correctly, `$top_rec_ids = array_keys($top_recs)` used to get fail counts ✅
- `render_gauge_svg(int $score, string $grade)` called with `($avg_score ?? 0, $avg_grade)` ✅
