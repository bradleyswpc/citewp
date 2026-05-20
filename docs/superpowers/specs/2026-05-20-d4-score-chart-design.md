# D4 — Cite Score Over Time: Filter-Based Dataset Architecture

**Date:** 2026-05-20
**Session:** 35
**Status:** Approved — ready for writing-plans

---

## Overview

D4 refactors the existing dual-axis SVG chart (`render_history_svg()`) from hardcoded two-series rendering into a filter-driven dataset architecture. It also fixes the 24h bot-visits bug and aligns the render spine with the data layer. No visual regression on the existing two series.

**Files changed:**
- `includes/Admin/DashboardData.php` — `get_visits_by_day()` 24h/T3 fix
- `includes/Admin/Menu.php` — default dataset construction, filter registration, `render_history_svg()` refactor
- No CSS changes (SVG is static; FB46(b) N/A this session — no animation added)

---

## Section 1: Architecture + Filter Contract

### Filter registration

```php
$datasets = apply_filters(
    'citewp_aiso/dashboard/score_chart_datasets',
    $default_datasets,   // array<int, dataset>
    $days                // int — chart window in days
);
```

### Dataset shape

```php
[
    'label'   => string,          // legend label, i18n-ready
    'axis'    => 'score'|'count', // discriminator: Y-scale + rendering treatment
    'color'   => string,          // CSS custom property name, e.g. '--citewp-citrine'
    'width'   => float,           // SVG stroke-width
    'opacity' => float,           // SVG stroke-opacity
    'data'    => array<int, array{date: string, value: float|int|null}>,
                                  // date-stamped points; see null rules below
]
```

### Axis semantics

| `axis` | Y-scale | SVG element | Null handling |
|--------|---------|-------------|---------------|
| `'score'` | Left axis, fixed 0–100 | `<path>` with M/L (gap on null) | Preserved → line gap |
| `'count'` | Right axis, auto-scale 0–max | `<polyline>` (continuous) | Coerced to 0 at render |

### Right-axis max (load-bearing)

The right Y-axis max is computed from the maximum `value` across **all** count-axis datasets combined, after null coercion. This ensures a second or third count-axis series added via the filter is never clipped.

### Null coercion (defensive)

The render coerces any null `value` to `0` for every count-axis dataset at render time. Count datasets SHOULD arrive zero-filled, but correctness does not depend on it. Score-axis nulls are preserved (→ line gap).

### Default datasets

1. **Avg Score** — `axis: 'score'`, `--citewp-citrine`, width 2.0, opacity 1.0
   - Data: `ScoreHistory::get_history($days)` overlaid onto bot-visits date spine; absent dates → null
2. **Bot Visits** — `axis: 'count'`, `--citewp-tint-blue`, width 1.5, opacity 0.7
   - Data: `DashboardData::get_visits_by_day($days, null)` output mapped to `['date', 'value']`; zero-filled

---

## Section 2: Data Layer

### `get_visits_by_day()` fix (DashboardData.php)

**Root cause:** `$start_date` (zero-fill spine start) was derived from `$now - (($days-1) * DAY_IN_SECONDS)`, while the SQL `$cutoff` used `strtotime("-{$days} days")`. For `$days=1` this made the spine start at today while the SQL fetched from yesterday, silently discarding yesterday's rows. Off-by-one was invisible for `$days=7/30/90` because the spine still covered all SQL rows.

**Fix:** Extract a shared timestamp and derive both from it:

```php
$cutoff_ts  = strtotime( "-{$days} days", $now );
$cutoff     = gmdate( 'Y-m-d H:i:s', $cutoff_ts );   // SQL WHERE clause (unchanged semantics)
// ... SQL query unchanged ...
$today      = gmdate( 'Y-m-d', $now );
$start_date = gmdate( 'Y-m-d', $cutoff_ts );          // replaces -(days-1) formula
```

### Building default datasets (call site)

`$bot_visits_by_day = (new DashboardData())->get_visits_by_day($history_range, null)` remains the canonical zero-filled reference. Both default datasets are built against it before the filter call:

```php
$score_lookup = array_column( $history, 'avg', 'date' );

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

$datasets = apply_filters( 'citewp_aiso/dashboard/score_chart_datasets', $default_datasets, $history_range );
```

---

## Section 3: Render Refactor (`render_history_svg()`)

### Signature change

```php
// Before:
private function render_history_svg( array $history, int $days, array $bot_visits ): void

// After:
private function render_history_svg( array $datasets, int $days ): void
```

### Internal steps

1. **Build UTC spine** — `$cutoff_ts = strtotime("-{$days} days", time())`, walk `gmdate('Y-m-d', $cutoff_ts)` through `gmdate('Y-m-d')` today. All UTC. X-axis reference.

2. **Partition datasets** — split into `$score_datasets` and `$count_datasets` by `axis` key.

3. **Build per-dataset lookup maps** — convert each dataset's `data` array to `$date => value` map (one pass).

4. **Coerce count-axis nulls** — when resolving count values against the spine, treat null/missing → 0.

5. **Compute `$max_bv`** — max across all count-axis values after coercion, across all count datasets. Used for right Y-axis scale and polyline Y coordinates.

6. **Empty-state check** — fire the empty state only when: no score dataset has any non-null point AND no count dataset has any non-zero value. A chart with real bot visits and an all-null score dataset renders (count series only). An all-null score dataset does not emit a `<path>` element.

7. **Render SVG elements** (paint order):
   - Grid lines (5 horizontal rules, unchanged)
   - Count-axis `<polyline>` elements first (underneath) — one per count dataset, styled by dataset metadata
   - Score-axis `<path>` elements second (on top) — one per score dataset; before emitting, check whether any point in the dataset is non-null; if none, skip the `<path>` element entirely (no element emitted, not `<path d="">`)

8. **Legend** — one `<span>` per dataset in **dataset array order** (explicitly decoupled from paint order). Default: "Avg Score → Bot Visits." Filter-appended datasets appear in append order.

9. **Right Y-axis labels** — from `$max_bv` (K suffix for ≥1000, unchanged format).

10. **X-axis labels** — from spine array (unchanged label-step logic: step=1 for ≤7d, step=5 for ≤30d, step=15 for >30d).

### What does not change

SVG viewport (340×80), grid line positions, label-step formula, right-axis label format, X-label date format (M/D).

---

## Build Notes (for code-reviewer and implementation)

- **UTC consistency**: Both `get_visits_by_day()` and the render's spine must use `gmdate()` throughout. Site-local `date()` in the spine would reintroduce a date-boundary mismatch identical to the bug just fixed.
- **Spine duplication**: Both `get_visits_by_day()` and `render_history_svg()` compute the same walk-from-cutoff-to-today logic. Extract a shared `date_spine(int $days): array` helper if cheap at build time; if not, flag in SESSION-LOG as known duplication (fix one, must fix both).
- **FB46(b)**: N/A this session — chart is static SVG, no CSS animation added. Backlog item not silently skipped; see SESSION-LOG.
- **All-null score path**: Must be a no-op (no element emitted), not `<path d="">`.
- **Filter callbacks adding count datasets**: They supply date-stamped points for whatever dates they have; the render places them against the spine and zero-fills absent dates. No length alignment required.
