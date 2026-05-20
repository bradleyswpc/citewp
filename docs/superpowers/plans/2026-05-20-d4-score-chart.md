# D4 — Cite Score Chart: Filter-Based Dataset Architecture Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Refactor `render_history_svg()` from two hardcoded series into a filter-driven dataset architecture, fix the 24h `get_visits_by_day()` off-by-one, and register the `citewp_aiso/dashboard/score_chart_datasets` filter.

**Architecture:** `get_visits_by_day()` is fixed to derive `$start_date` from the same `$cutoff_ts` as the SQL query. The call site builds two default datasets (Avg Score, Bot Visits), passes them through `apply_filters('citewp_aiso/dashboard/score_chart_datasets', ...)`, and passes the result to the refactored `render_history_svg($datasets, $days)`. The render builds its own UTC spine from `$days`, maps each dataset's points onto it by date, and renders count polylines first (underneath) then score paths on top.

**Tech Stack:** PHP 8.0+, WordPress 6.5+, inline SVG (no JS chart library), WordPress filter API.

**Design spec:** `docs/superpowers/specs/2026-05-20-d4-score-chart-design.md`

---

## File Map

| File | Change |
|------|--------|
| `includes/Admin/DashboardData.php` | Task 1: Fix `get_visits_by_day()` lines 356–376 |
| `admin/css/citewp-aiso-admin.css` | Task 2: Legend swatch → `--citewp-legend-color` (lines 2937–2944) |
| `includes/Admin/Menu.php` | Task 3: Default datasets + filter at call site (~line 866) |
| `includes/Admin/Menu.php` | Task 3: Update render call (~line 1304) |
| `includes/Admin/Menu.php` | Task 4: Replace `render_history_svg()` body (lines 1470–1611) |

---

## Task 1: Fix `get_visits_by_day()` 24h bug

**Files:**
- Modify: `includes/Admin/DashboardData.php:356–376`

**Root cause:** `$start_date` (zero-fill spine start) is computed as `$now - (($days-1) * DAY_IN_SECONDS)`. For `$days=1` this equals `$now - 0 = today`. The SQL `$cutoff` is `strtotime("-1 days")` = yesterday. The spine only contains today, so yesterday's fetched rows are silently discarded and the 24h chart shows empty.

- [ ] **Open** `includes/Admin/DashboardData.php` and locate lines 356–358:

```php
$now        = time();
$table_name = esc_sql( Schema::table( Schema::TABLE_CRAWLER_LOGS ) );
$cutoff     = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days", $now ) );
```

- [ ] **Replace** lines 356–358 with (extract `$cutoff_ts` so both `$cutoff` and `$start_date` derive from it):

```php
$now        = time();
$table_name = esc_sql( Schema::table( Schema::TABLE_CRAWLER_LOGS ) );
$cutoff_ts  = strtotime( "-{$days} days", $now );
$cutoff     = gmdate( 'Y-m-d H:i:s', $cutoff_ts );
```

- [ ] **Locate** line 376 (after the SQL query, inside the zero-fill setup):

```php
$start_date = gmdate( 'Y-m-d', $now - ( ( $days - 1 ) * DAY_IN_SECONDS ) );
```

- [ ] **Replace** line 376 with:

```php
$start_date = gmdate( 'Y-m-d', $cutoff_ts );
```

- [ ] **Verify** — check LocalWP `debug.log` for new PHP errors. No errors expected.

- [ ] **Commit and push:**

```bash
git add includes/Admin/DashboardData.php
git commit -m "fix: get_visits_by_day align start_date with SQL cutoff (24h/T3 bug)"
git push origin main
```

---

## Task 2: Update legend CSS to use `--citewp-legend-color` custom property

**Files:**
- Modify: `admin/css/citewp-aiso-admin.css:2937–2944`

The hardcoded `--score` and `--bv` modifier classes tie the CSS to specific dataset identities. Replace with a CSS custom property so any dataset can declare its swatch color inline.

- [ ] **Locate** lines 2937–2944 in `admin/css/citewp-aiso-admin.css`:

```css
.citewp-aiso-chart-legend__item::before {
  content: '';
  display: inline-block;
  width: 12px;
  height: 2px;
}
.citewp-aiso-chart-legend__item--score::before { background: var(--citewp-citrine); }
.citewp-aiso-chart-legend__item--bv::before    { background: var(--citewp-tint-blue); }
```

- [ ] **Replace** those 8 lines with:

```css
.citewp-aiso-chart-legend__item::before {
  content: '';
  display: inline-block;
  width: 12px;
  height: 2px;
  background: var(--citewp-legend-color, var(--citewp-border));
}
```

(Remove the two modifier rules — they are replaced by the inline `style` attribute set in Task 4.)

- [ ] **Commit and push:**

```bash
git add admin/css/citewp-aiso-admin.css
git commit -m "refactor: legend swatch color via --citewp-legend-color custom property"
git push origin main
```

---

## Task 3: Build default datasets + apply filter at call site

**Files:**
- Modify: `includes/Admin/Menu.php` (two locations: ~line 866 and ~line 1304)

**Location 1 — dataset construction (after the existing history fetches):**

- [ ] **Locate** lines 864–866 in `Menu.php`:

```php
$history           = ( new ScoreHistory() )->get_history( $history_range );
$bot_visits_by_day = ( new DashboardData() )->get_visits_by_day( $history_range, null );
$hist_avg          = ! empty( $history ) ? (int) round( array_sum( array_column( $history, 'avg' ) ) / count( $history ) ) : null;
```

- [ ] **After line 880** (after the `$hist_delta` calculation block ends), add:

```php
		// ── Chart datasets — filter-based extensibility (X15). ────────────────
		$score_lookup     = array_column( $history, 'avg', 'date' );
		$default_datasets = [
			[
				'label'   => __( 'Avg Score', 'ai-search-optimizer' ),
				'axis'    => 'score',
				'color'   => '--citewp-citrine',
				'width'   => 2.0,
				'opacity' => 1.0,
				'data'    => array_map(
					fn( $bv ) => [ 'date' => $bv['date'], 'value' => $score_lookup[ $bv['date'] ] ?? null ],
					$bot_visits_by_day
				),
			],
			[
				'label'   => __( 'Bot Visits', 'ai-search-optimizer' ),
				'axis'    => 'count',
				'color'   => '--citewp-tint-blue',
				'width'   => 1.5,
				'opacity' => 0.7,
				'data'    => array_map(
					fn( $bv ) => [ 'date' => $bv['date'], 'value' => $bv['sum'] ],
					$bot_visits_by_day
				),
			],
		];
		/**
		 * Filters the datasets rendered in the Cite Score Over Time chart.
		 *
		 * Each dataset must provide: label (string), axis ('score'|'count'), color (CSS custom property name),
		 * width (float stroke-width), opacity (float), data (array of {date: Y-m-d, value: float|int|null}).
		 * Count-axis nulls are coerced to 0 at render. Score-axis nulls produce line gaps.
		 * Right Y-axis max = max across ALL count-axis datasets combined.
		 *
		 * @param array<int, array{label: string, axis: string, color: string, width: float, opacity: float, data: array<int, array{date: string, value: float|int|null}>}> $datasets
		 * @param int $days Chart window in days.
		 */
		$chart_datasets = apply_filters( 'citewp_aiso/dashboard/score_chart_datasets', $default_datasets, $history_range );
```

**Location 2 — update the render call:**

- [ ] **Locate** the existing render call (~line 1304):

```php
<?php $this->render_history_svg( $history, $history_range, $bot_visits_by_day ); ?>
```

- [ ] **Replace** with:

```php
<?php $this->render_history_svg( $chart_datasets, $history_range ); ?>
```

- [ ] **Verify** — check `debug.log`. No PHP errors expected (render method is replaced in Task 4 — do Task 3 and Task 4 together if running inline; if subagent-driven, Task 4 must immediately follow Task 3 since the method signature mismatch will produce a fatal).

> **Note:** Tasks 3 and 4 must be committed atomically if the site is live — the signature mismatch between the new call site and the old `render_history_svg()` will fatal. Commit after Task 4 is complete.

- [ ] **Hold commit until Task 4 is complete.** (See Task 4 commit step.)

---

## Task 4: Refactor `render_history_svg()` to dataset-driven rendering

**Files:**
- Modify: `includes/Admin/Menu.php:1470–1611`

Replace the entire method (two stacked docblocks + body, lines 1470–1611) with the new dataset-driven implementation.

- [ ] **Delete lines 1470–1611** (the existing double-docblock + `render_history_svg()` body). Replace entirely with:

```php
	/**
	 * Renders the Cite Score Over Time SVG chart from a filtered dataset array.
	 *
	 * Datasets are produced by `citewp_aiso/dashboard/score_chart_datasets`. Each entry must have:
	 *   label (string), axis ('score'|'count'), color (CSS custom property name, e.g. '--citewp-citrine'),
	 *   width (float), opacity (float), data (array of {date: Y-m-d, value: float|int|null}).
	 * Score-axis nulls → line gaps. Count-axis nulls → coerced to 0. Right Y-axis max = max across all count datasets.
	 * Legend follows dataset array order (decoupled from paint order).
	 *
	 * @param array<int, array{label: string, axis: string, color: string, width: float, opacity: float, data: array<int, array{date: string, value: float|int|null}>}> $datasets
	 * @param int $days Chart window in days.
	 */
	private function render_history_svg( array $datasets, int $days ): void {
		// ── 1. Build UTC spine (same algorithm as get_visits_by_day — both must stay in sync). ──
		$now       = time();
		$cutoff_ts = strtotime( "-{$days} days", $now );
		$today     = gmdate( 'Y-m-d', $now );
		$spine     = [];
		$cursor    = gmdate( 'Y-m-d', $cutoff_ts );
		while ( $cursor <= $today ) {
			$spine[] = $cursor;
			$cursor  = gmdate( 'Y-m-d', strtotime( $cursor ) + DAY_IN_SECONDS );
		}
		$n = count( $spine );

		// ── 2. Partition datasets by axis. ──────────────────────────────────────────────
		$score_datasets = array_values( array_filter( $datasets, fn( $d ) => ( $d['axis'] ?? '' ) === 'score' ) );
		$count_datasets = array_values( array_filter( $datasets, fn( $d ) => ( $d['axis'] ?? '' ) === 'count' ) );

		// ── 3. Build per-dataset date→value lookup maps. ────────────────────────────────
		$score_maps = [];
		foreach ( $score_datasets as $i => $ds ) {
			$score_maps[ $i ] = array_column( $ds['data'], 'value', 'date' );
		}
		$count_maps = [];
		foreach ( $count_datasets as $i => $ds ) {
			$count_maps[ $i ] = array_column( $ds['data'], 'value', 'date' );
		}

		// ── 4. Compute right-axis max across ALL count datasets (post-coercion). ────────
		$max_bv = 1;
		foreach ( $count_datasets as $i => $ds ) {
			foreach ( $spine as $date ) {
				$v = $count_maps[ $i ][ $date ] ?? 0;
				$v = ( $v === null ) ? 0 : (int) $v;
				if ( $v > $max_bv ) {
					$max_bv = $v;
				}
			}
		}

		// ── 5. Empty-state: fires only when nothing to draw on either axis. ─────────────
		$has_score_data = false;
		foreach ( $score_datasets as $i => $ds ) {
			foreach ( $spine as $date ) {
				if ( ( $score_maps[ $i ][ $date ] ?? null ) !== null ) {
					$has_score_data = true;
					break 2;
				}
			}
		}
		$has_count_data = false;
		foreach ( $count_datasets as $i => $ds ) {
			foreach ( $spine as $date ) {
				$v = $count_maps[ $i ][ $date ] ?? 0;
				$v = ( $v === null ) ? 0 : (int) $v;
				if ( $v > 0 ) {
					$has_count_data = true;
					break 2;
				}
			}
		}

		if ( ! $has_score_data && ! $has_count_data ) {
			?>
			<div class="citewp-aiso-history-panel__empty">
				<svg viewBox="0 0 340 60" width="100%" height="60" aria-hidden="true">
					<line x1="0" y1="30" x2="340" y2="30" stroke="var(--citewp-border)" stroke-width="2" stroke-dasharray="6 4"/>
				</svg>
				<p class="citewp-aiso-history-panel__empty-text">
					<?php esc_html_e( 'Not enough history yet. Site Cite Score is recorded daily — check back tomorrow for your first data point.', 'ai-search-optimizer' ); ?>
				</p>
			</div>
			<?php
			return;
		}

		$w = 340;
		$h = 80;

		// ── Right Y-axis labels: compact K suffix for ≥ 1000. ──────────────────────────
		$fmt = static function ( int $v ): string {
			return $v >= 1000 ? round( $v / 1000, 1 ) . 'k' : (string) $v;
		};
		$bv_mid_val = (int) round( $max_bv / 2 );
		$bv_max_lbl = $fmt( $max_bv );
		$bv_mid_lbl = $fmt( $bv_mid_val );

		// ── X-axis label step. ──────────────────────────────────────────────────────────
		if ( $n <= 7 ) {
			$label_step = 1;
		} elseif ( $n <= 30 ) {
			$label_step = 5;
		} else {
			$label_step = 15;
		}
		?>
		<div class="citewp-aiso-chart-legend" aria-hidden="true">
			<?php foreach ( $datasets as $ds ) : ?>
				<span class="citewp-aiso-chart-legend__item"
					style="--citewp-legend-color: var(<?php echo esc_attr( $ds['color'] ); ?>)">
					<?php echo esc_html( $ds['label'] ); ?>
				</span>
			<?php endforeach; ?>
		</div>
		<div class="citewp-aiso-cs-history-wrap">
			<div class="citewp-aiso-cs-history-yaxis" aria-hidden="true">
				<span class="citewp-aiso-cs-history-yaxis__label" style="top:10%">100</span>
				<span class="citewp-aiso-cs-history-yaxis__label" style="top:30%">75</span>
				<span class="citewp-aiso-cs-history-yaxis__label" style="top:50%">50</span>
				<span class="citewp-aiso-cs-history-yaxis__label" style="top:70%">25</span>
				<span class="citewp-aiso-cs-history-yaxis__label" style="top:90%">0</span>
			</div>
			<svg viewBox="0 0 <?php echo esc_attr( (string) $w ); ?> <?php echo esc_attr( (string) $h ); ?>"
				width="100%" height="<?php echo esc_attr( (string) $h ); ?>" preserveAspectRatio="none" aria-hidden="true">
				<line x1="0" y1="8"  x2="340" y2="8"  stroke="var(--citewp-border)" stroke-width="0.5"/>
				<line x1="0" y1="24" x2="340" y2="24" stroke="var(--citewp-border)" stroke-width="0.5"/>
				<line x1="0" y1="40" x2="340" y2="40" stroke="var(--citewp-border)" stroke-width="0.5"/>
				<line x1="0" y1="56" x2="340" y2="56" stroke="var(--citewp-border)" stroke-width="0.5"/>
				<line x1="0" y1="72" x2="340" y2="72" stroke="var(--citewp-border)" stroke-width="0.5"/>
				<?php
				// ── Count-axis polylines: paint-order first (underneath). ─────────────
				foreach ( $count_datasets as $idx => $ds ) :
					$pts = [];
					foreach ( $spine as $si => $date ) {
						$v     = $count_maps[ $idx ][ $date ] ?? 0;
						$v     = ( $v === null ) ? 0 : (int) $v;
						$x     = $n > 1 ? (int) round( ( $si / ( $n - 1 ) ) * $w ) : (int) ( $w / 2 );
						$y     = (int) round( $h - ( (float) $v / (float) $max_bv ) * ( $h * 0.8 ) - $h * 0.1 );
						$pts[] = "{$x},{$y}";
					}
					$poly = implode( ' ', $pts );
					if ( ! empty( $poly ) ) :
				?>
				<polyline points="<?php echo esc_attr( $poly ); ?>" fill="none"
					stroke="var(<?php echo esc_attr( $ds['color'] ); ?>)"
					stroke-width="<?php echo esc_attr( (string) $ds['width'] ); ?>"
					stroke-opacity="<?php echo esc_attr( (string) $ds['opacity'] ); ?>"
					stroke-linejoin="round" stroke-linecap="round"/>
				<?php endif; endforeach; ?>
				<?php
				// ── Score-axis paths: paint-order second (on top). ───────────────────
				// All-null dataset → skip element entirely (no <path d="">).
				foreach ( $score_datasets as $idx => $ds ) :
					$path      = '';
					$prev_null = true;
					$any_point = false;
					foreach ( $spine as $si => $date ) {
						$v = $score_maps[ $idx ][ $date ] ?? null;
						if ( $v === null ) {
							$prev_null = true;
							continue;
						}
						$any_point = true;
						$x         = $n > 1 ? (int) round( ( $si / ( $n - 1 ) ) * $w ) : (int) ( $w / 2 );
						$y         = (int) round( $h - ( (float) $v / 100.0 ) * ( $h * 0.8 ) - $h * 0.1 );
						$path     .= ( $prev_null ? "M {$x} {$y}" : " L {$x} {$y}" );
						$prev_null  = false;
					}
					if ( $any_point ) :
				?>
				<path d="<?php echo esc_attr( $path ); ?>" fill="none"
					stroke="var(<?php echo esc_attr( $ds['color'] ); ?>)"
					stroke-width="<?php echo esc_attr( (string) $ds['width'] ); ?>"
					stroke-opacity="<?php echo esc_attr( (string) $ds['opacity'] ); ?>"
					stroke-linejoin="round" stroke-linecap="round"/>
				<?php endif; endforeach; ?>
			</svg>
			<div class="citewp-aiso-cs-history-yaxis citewp-aiso-cs-history-yaxis--right" aria-hidden="true">
				<span class="citewp-aiso-cs-history-yaxis__label" style="top:10%"><?php echo esc_html( $bv_max_lbl ); ?></span>
				<span class="citewp-aiso-cs-history-yaxis__label" style="top:50%"><?php echo esc_html( $bv_mid_lbl ); ?></span>
				<span class="citewp-aiso-cs-history-yaxis__label" style="top:90%">0</span>
			</div>
		</div>
		<div class="citewp-aiso-chart-xlabels">
			<?php
			foreach ( $spine as $si => $date ) :
				if ( $n > 1 && $si % $label_step !== 0 && $si !== $n - 1 ) {
					continue;
				}
				$left_pct  = $n > 1 ? round( $si / ( $n - 1 ) * 100, 2 ) : 50.0;
				$parts     = explode( '-', $date );
				$label_str = ltrim( $parts[1] ?? '', '0' ) . '/' . ltrim( $parts[2] ?? '', '0' );
			?>
				<span class="citewp-aiso-chart-xlabels__label" style="left:<?php echo esc_attr( $left_pct . '%' ); ?>">
					<?php echo esc_html( $label_str ); ?>
				</span>
			<?php endforeach; ?>
		</div>
		<?php
	}
```

- [ ] **Verify** — check `debug.log`. Load the Cite Score page in LocalWP. Chart must render with two visible series (citrine score line, teal bot visits polyline) and legend reading "Avg Score → Bot Visits."

- [ ] **Commit Tasks 3 + 4 together** (atomic — call site + render refactor must land in the same commit):

```bash
git add includes/Admin/Menu.php
git commit -m "feat: D4 — dataset-driven score chart, register citewp_aiso/dashboard/score_chart_datasets filter"
git push origin main
```

---

## Task 5: Browser verification

- [ ] Open LocalWP site (`http://citewp-dev.local/wp-admin/admin.php?page=citewp-aiso#cite-score`)

- [ ] **30-day view:** Both lines visible. Legend reads "Avg Score" then "Bot Visits". Left Y-axis 0–100. Right Y-axis shows bot-visit max. X-labels show M/D dates every 5 days.

- [ ] **7-day view:** Both lines visible. X-labels every 1 day (7 labels).

- [ ] **90-day view:** Both lines visible. X-labels every 15 days.

- [ ] **Empty-state check:** If no score history + no bot visits, chart shows the dashed-line placeholder with "Not enough history yet" text. (May need to temporarily filter out datasets to test; skip if environment has data on both axes.)

- [ ] **Crawler Logs — 24h filter:** Open `admin.php?page=citewp-aiso#crawler-logs`, set range to 24h. If bot visit data exists from the past 24h, the chart now shows a 2-point line (yesterday + today) instead of "No crawler activity." This is the T1/T3 fix verification. Confirm the spine has 2 dates.

- [ ] **Legend swatch colors:** Avg Score swatch = citrine (gold/yellow). Bot Visits swatch = teal/blue. No broken swatches (gray fallback color = bug).

- [ ] **No debug.log errors** from any of the above page loads.

---

## Task 6: Commit + push push verification note

All four commits should already be pushed. Verify:

```bash
git log --oneline -6
```

Expected output (newest first):
```
<hash> feat: D4 — dataset-driven score chart, register citewp_aiso/dashboard/score_chart_datasets filter
<hash> refactor: legend swatch color via --citewp-legend-color custom property
<hash> fix: get_visits_by_day align start_date with SQL cutoff (24h/T3 bug)
<hash> feat: D3 — swap col-a order: AI Insights first, Top Crawlers second
```

---

## Self-Review Notes

- **Spec coverage:** All three sections covered — S1 (filter registration, dataset shape, axis-max rule, null coercion) ✓ S2 (cutoff_ts fix, default dataset construction) ✓ S3 (spine, partition, lookup maps, coerce, empty-state, paint order, legend array-order, right-axis labels, x-labels) ✓
- **UTC consistency:** Both `get_visits_by_day()` fix and `render_history_svg()` spine use `gmdate()` ✓
- **Spine duplication:** Known — flag in SESSION-LOG. Both methods use `strtotime("-{$days} days", $now)` + `DAY_IN_SECONDS` walk. Extract `date_spine()` in a future session. ✓ flagged
- **All-null score path:** `$any_point` gate prevents `<path d="">` emission ✓
- **Legend order:** Iterates `$datasets` (array order), not paint order ✓
- **Null coercion:** `($v === null) ? 0 : (int) $v` in both `$max_bv` loop and polyline point loop ✓
- **Empty-state condition:** OR across axes (not AND of "all datasets empty") ✓
- **FB46(b):** No animation added; static SVG; noted in SESSION-LOG ✓
