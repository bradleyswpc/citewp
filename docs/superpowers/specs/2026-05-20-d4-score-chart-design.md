# D4 — Cite Score Over Time: Filter-Based Dataset Architecture

**Date:** 2026-05-20
**Session:** 35
**Status:** Shipped (Session 35) — single-series Avg Score + 24h/T3 data-layer fix

---

## Overview

D4 refactors `render_history_svg()` from a hardcoded two-series dual-axis chart into a filter-driven dataset architecture shipping with **one default series** (Avg Score). It also fixes the 24h bot-visits bug in the data layer.

**Scope reduction (mid-session):** The original D4 design included a Bot Visits overlay as a second default dataset. This was dropped: bot visits are already covered by the Bot Visits Over Time chart on the Crawler Logs page, and the bounded-score (0–100) vs. unbounded-count tension made shared geometry the wrong call. The filter hook and dataset architecture stay — future datasets can register via `citewp_aiso/dashboard/score_chart_datasets` without touching `Menu.php`.

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
    $default_datasets,   // array<int, dataset> — one entry (Avg Score)
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
| `'score'` | Left axis, fixed 0–100 | `<path>` with M/L (carry-forward) | Leading nulls = no point; post-measurement nulls carry forward last known value (plateau to next reading) |
| `'count'` | Right axis (future) | `<polyline>` (continuous) | Coerced to 0 at render |

**Note:** Count-axis rendering is not in the current render implementation (no default count dataset). The `axis='count'` discriminator is part of the dataset shape contract for future filter callbacks that extend the chart.

### Score-axis carry-forward

Score-axis datasets use carry-forward instead of gaps. Leading nulls (before the first non-null value) produce no point — the line begins at the first measured date. After the first measurement, any null date carries the last known value forward (plateau) until the next real reading. Result: one continuous `<path>` from the first measurement to the end of the spine.

### Default datasets

1. **Avg Score** — `axis: 'score'`, `--citewp-citrine`, width 2.0, opacity 1.0
   - Data: `ScoreHistory::get_history($days)` overlaid onto bot-visits date spine; absent dates → null

---

## Section 2: Data Layer

### `get_visits_by_day()` fix (DashboardData.php)

**Root cause:** `$start_date` (zero-fill spine start) was derived from `$now - (($days-1) * DAY_IN_SECONDS)`, while the SQL `$cutoff` used `strtotime("-{$days} days")`. For `$days=1` this made the spine start at today while the SQL fetched from yesterday, silently discarding yesterday's rows.

**Fix:** Extract a shared timestamp:

```php
$cutoff_ts  = strtotime( "-{$days} days", $now );
$cutoff     = gmdate( 'Y-m-d H:i:s', $cutoff_ts );   // SQL WHERE clause (unchanged semantics)
$today      = gmdate( 'Y-m-d', $now );
$start_date = gmdate( 'Y-m-d', $cutoff_ts );          // replaces -(days-1) formula
```

**Scope note:** `get_visits_by_day()` still serves the Bot Visits Over Time chart on the Crawler Logs page (`LogsPage.php:107`). The 24h fix is load-bearing for that chart regardless of D4 scope.

### Building default datasets (call site)

`$bot_visits_by_day = (new DashboardData())->get_visits_by_day($history_range, null)` is the canonical zero-filled date reference. The Avg Score dataset uses it as its date spine:

```php
$score_lookup     = array_column( $history, 'avg', 'date' );
$default_datasets = [
    [
        'label'   => __( 'Avg Score', 'ai-search-optimizer' ),
        'axis'    => 'score',
        'color'   => '--citewp-citrine',
        'width'   => 2.0,
        'opacity' => 1.0,
        'data'    => array_map(
            fn( $bv ) => [ 'date' => $bv['date'], 'value' => isset( $score_lookup[ $bv['date'] ] ) ? (float) $score_lookup[ $bv['date'] ] : null ],
            $bot_visits_by_day
        ),
    ],
];

$datasets = apply_filters( 'citewp_aiso/dashboard/score_chart_datasets', $default_datasets, $history_range );
```

---

## Section 3: Render (`render_history_svg()`)

### Signature

```php
private function render_history_svg( array $datasets, int $days ): void
```

### Internal steps

1. **Validate colors** — `preg_match('/^--[\w-]+$/', $d['color'])` on each dataset; strips unsafe entries.

2. **Build UTC spine** — `$cutoff_ts = strtotime("-{$days} days", time())`, walk `gmdate('Y-m-d', $cutoff_ts)` through `gmdate('Y-m-d')` today.

3. **Score-axis datasets + lookup maps** — `array_filter` on `axis === 'score'`; `array_column($ds['data'], 'value', 'date')` per dataset.

4. **Empty-state check** — fires when no score dataset has any non-null point. All-null = dashed empty state, return early.

5. **Render SVG elements:**
   - Grid lines (5 horizontal rules at y=8/24/40/56/72)
   - Score-axis `<path>` elements — one per score dataset; carry-forward algorithm (see Section 1). All-null dataset → element omitted.

6. **Left Y-axis labels** — 100/75/50/25/0 at top:10%/30%/50%/70%/90%.

7. **X-axis labels** — step=1 for ≤7d, step=5 for ≤30d, step=15 for >30d. M/D format.

8. **No legend** — single series; chart title is self-explanatory.

### What does not change

SVG viewport (340×80), grid line positions, label-step formula, X-label date format (M/D), UTC spine algorithm.

---

## Build Notes

- **UTC consistency**: Both `get_visits_by_day()` and the render's spine must use `gmdate()` throughout.
- **Spine duplication**: Both `get_visits_by_day()` and `render_history_svg()` compute the same walk-from-cutoff-to-today logic. Known duplication — fix one, must fix both.
- **FB46(b)**: N/A this session — chart is static SVG, no animation added.
- **All-null score path**: No-op — no element emitted.
- **Dual-line overlay dropped**: Bot visits redundant with Crawler Logs page chart. Bounded (0–100) score vs. unbounded count made shared Y-geometry the wrong abstraction. Can be re-added via filter if needed.
