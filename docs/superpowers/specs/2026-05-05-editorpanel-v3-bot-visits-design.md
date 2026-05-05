# EditorPanel v3 Polish + Per-Post Bot Visits Widget — Design Spec

**Date:** 2026-05-05  
**Session:** S23  
**Status:** Approved — ready for writing-plans

---

## Problem

`EditorPanel.php` is the primary CiteWP surface for ~50% of users (page builder, Classic Editor, Elementor backend). It currently uses v2 inline styles, no v3 tokens, text-only category rows (no bar fill), and no content unique to EditorPanel — score and schema suggestions are also in the Gutenberg sidebar. Against Rank Math's fully-mature meta box (SERP preview, focus keyword input, multi-tab checklist with status icons, link suggestions), EditorPanel reads as a lighter version of a tool the user already has.

S23 fixes both problems simultaneously: v3 token migration + adding Bot Visits as unique-to-CiteWP data. Bot Visits (AI crawler tracking) is the only content in EditorPanel that Rank Math cannot replicate. It's the differentiator that earns EditorPanel's screen real estate.

---

## Constraints (pre-settled)

- No `Engine.php` touch (A11)
- No new REST endpoints — PHP server-side render only
- No data layer changes — read-only consumer of existing `wp_citewp_aiso_crawler_logs` table
- `citewp_aiso/metabox/tabs` filter `$context` arg in scope (S12 carryover, unblocks FB30)
- No `LogsTable.php` URI filter addition this session

---

## Q1: Tab structure — **Option B settled**

Bot Visits renders **inline under General tab**, below Recalculate button. Not a third tab, not a below-tabs section.

**Rationale:** Bot Visits is the differentiator. Hiding it behind a tab click (Option A) means most users never discover it. Below-tabs (Option C) is architecturally incoherent. Inline under General creates the cause→effect narrative in one panel: score (prediction) + bot visits (measurement).

**$context arg:** Added to `citewp_aiso/metabox/tabs` filter call in S23 since `EditorPanel.php` is already being touched. Signature becomes `apply_filters('citewp_aiso/metabox/tabs', $tabs, $post, $context)` where `$context` is `'score'` (General) or `'schema'` (Schema tab). Unblocks FB30 (Cite Bridges tab) and future Pro tabs.

---

## Q2: Empty state — **Option A settled**

```
[bot icon]
No AI bot visits yet
Most bots discover new posts within 24–72 hours of publishing.
```

**Copy decision:** "Most bots" (not "Bots typically") — per X12 advisory language, "most" is load-bearing. "Typically" makes a claim about timing that's testable and wrong for low-authority sites.

**Why Option A over B/C:**
- B ("Monitoring active") implies active scheduling — product passively logs, doesn't schedule. Rejects for honesty reasons (Cluster 1 / burned early adopters). Not just gimmicky.
- C (ghost rows) would hardcode 3 specific bot names as canonical when the registry has 41 signatures. Structurally wrong.

**Empty state framing:** Score = prediction, Bot Visits = measurement, empty state copy = "come back." That value loop in two lines is content the score breakdown cannot provide.

**Tier disclosure in empty state:** Omit. The disclosure explains limits on data the user IS seeing. With zero data, there's nothing to limit. Empty state copy already sets expectations. Stacking two informational messages where one suffices is noise.

---

## Q3: Time window + cap — **settled**

**Time window:** 7-day query + "Last 7 days" label + tier disclosure footer (populated state only).

```sql
SELECT bot_signature, COUNT(*) as visits, MAX(created_at) as last_seen
FROM wp_citewp_aiso_crawler_logs
WHERE post_id = %d
  AND created_at > NOW() - INTERVAL 7 DAY
GROUP BY bot_signature
ORDER BY visits DESC
LIMIT 6
```

Fetch 6 to detect overflow; render top 5. If 6th row exists: show "and N more" plain text (no link — `LogsTable.php` has no URI filter).

**Why 7 days (not 30):** Free tier retention is 7 days (`citewp_aiso_daily_cleanup`). "Last 30 days" on a 7-day dataset is a silent label mismatch — erodes Cluster 1 trust. Option 1 (7-day label = honest about query) preferred over Option 2 (30-day label hides retention limit).

**Tier disclosure footer (populated state):**
> "Free tier shows 7 days of crawler activity. **Pro extends to 90 days.**"

Per P26: informational, not locked-state UI. Describes what exists at Pro tier, no CTA. This is a tier label, not an upsell, and appears on both Free (7-day) and Pro (90-day, with updated label) accounts.

**Cap:** Top 5 by visit count. "and N more" plain text if overflow.

---

## Q4 + Q5: Visual register + tab structure — **settled**

**Tab count (Q5):** 2 tabs (General / Schema). P31 verified correct. With Bot Visits inline under General, General tab is substantively dense: score + 3 category bars + Recalculate + Bot Visits section. Third tab would fragment content and hide the differentiator. Yoast classic meta box is also 2 tabs — P31's "mirroring Yoast/Rank Math" anchors at 2.

**Layout (Q4):** 2-column `CSS grid` inside General tab panel at meta box width (~870–1100px).

| Column | Width | Content |
|--------|-------|---------|
| Left | 45% | 64px Cite Score number + "/ 100" denom + "Cite Score" label · category bars (Structure / Citability / Authority with visual fill) · "Scored X ago" · Recalculate button |
| Right | 55% | Bot Visits section (header + table + tier footer or empty state) |

**Ratio note:** Default 45/55 favoring Bot Visits — Bot Visits table (4 columns: dot+name / visits / last seen / padding) benefits from every pixel; score bars don't need 480px horizontal. If browser verification shows Bot Visits visually overpowering the score, revert to 50/50. 50/50 communicates "equal priority"; 45/55 gives the table room to breathe.

**Column divider:** `border-left: 1px solid #e5e7eb` on right column, `padding-left: 24px`.

**Why 2-column beats stacked at meta box width:** Stacked layout on a ~960px canvas creates massive right-side whitespace — the exact failure mode S20 KPI cards hit. 2-column uses horizontal real estate and positions Bot Visits as a peer to the score, which is what the differentiation argument requires. Rank Math's own General tab uses horizontal sections at width (SERP preview + checklists side-by-side) — mirroring that structural choice with different content signals "different tool, same level of polish."

---

## Bot Visits Table — Populated State

**3-column table** (gained from wider canvas — not in original Q3 spec):

| Bot | Visits | Last seen |
|-----|--------|-----------|
| [dot] GPTBot | 12 | 2 hours ago |
| [dot] ClaudeBot | 4 | 1 day ago |
| [dot] PerplexityBot | 2 | 3 days ago |

`Last seen` = `human_time_diff(strtotime($row->last_seen), time())`. This column is a composition gain from the wider canvas: at sidebar width there's no room for it; at meta box width it adds real value ("GPTBot crawled 2 hours ago" vs. "CCBot crawled 5 days ago" without mental math). It's also structurally different from anything Rank Math's meta box shows — no time-series data anywhere in Rank Math's per-post panel.

**Colored dot per bot:** Derive color from `bot_signature` hash modulo palette (5–6 colors from v3 accent palette). Not hardcoded per-bot — registry has 41 signatures, any could appear.

**Section header:**
```
[navy icon block 20×20, Lucide robot SVG, Citrine stroke] "Bot Visits"  [last 7 days pill]
```
Full-width separator `border-top: 1px solid #e5e7eb` above header. Bold `font-weight: 700` title. Pill `background: #f3f4f6` right-justified.

---

## Bot Visits — Empty State (right column)

Vertically centered within the right column (`align-items: center` on column flex or `place-content: center` on grid cell). The left column height is set by score + bars + Recalculate; the empty state card floats centered in whatever height that establishes.

```
[36px circle icon, gray]
No AI bot visits yet
Most bots discover new posts within 24–72 hours of publishing.
```

No tier disclosure footer in empty state (omit-when-empty — see Q2 rationale).

---

## V3 Token Migration

All `inline_styles()` hardcoded hex values replace with v3 CSS custom properties (P38 palette). Key mappings:

| Current hardcoded | v3 token |
|---|---|
| `#1e2a3b` (tab active color) | `var(--citewp-navy)` |
| `#e8d400` (tab active border) | `var(--citewp-citrine)` |
| `#e5e7eb` (borders) | `var(--citewp-border)` |
| `#6b7280` (muted text) | `var(--citewp-text-muted)` |
| `#374151` (body text) | `var(--citewp-text-secondary)` |
| `#111827` (strong text) | `var(--citewp-text-primary)` |
| `#00A32A` (green score) | `var(--citewp-score-green)` |
| `#DBA617` (yellow score) | `var(--citewp-score-yellow)` |
| `#D63638` (orange score) | `var(--citewp-score-orange)` |
| `#8C1B1B` (red score) | `var(--citewp-score-red)` |
| `#9ca3af` (placeholder) | `var(--citewp-text-muted)` |

Score number stays 48px+ JetBrains Mono — correct already. Category rows gain visual bar fill (horizontal progress bars) — currently text-only fractions.

---

## Extensibility Hooks (X15 compliance)

`citewp_aiso/metabox/tabs` filter updated to include `$context` arg:
```php
apply_filters( 'citewp_aiso/metabox/tabs', $default_tabs, $post, 'score' )  // General tab
apply_filters( 'citewp_aiso/metabox/tabs', $default_tabs, $post, 'schema' ) // Schema tab
```

This unblocks:
- FB29 (schema generator expansion — Schema tab extensible)
- FB30 (Cite Bridges — can register as 'score' context tab when ready)

---

## Files to Touch

| File | Change |
|------|--------|
| `includes/Admin/EditorPanel.php` | Full rewrite: 2-column layout, Bot Visits query + render, v3 tokens, $context arg, category bar fill |
| `admin/css/citewp-aiso-admin.css` | New section: EditorPanel v3 styles (2-col grid, bot table, empty state, tier footer) |

No JS changes. No new files. No REST endpoints. No scoring logic.

---

## SESSION-LOG Notes (for S23 close)

- **Last seen column:** emerged from wider-canvas composition work — not in original Q3 spec. `MAX(created_at)` already available in any GROUP BY query; no schema change needed.
- **2-column layout:** identified as necessary only after noticing mockups were sidebar-width. The wider canvas revealed a structural problem (whitespace) and a structural gain (Bot Visits as peer, not subordinate). Log as composition gain, not layout choice.
- **UI-DESIGN-SYSTEM.md note (Brain consolidation):** "[A] mark is the brand identity glyph; section/widget icons in PHP surfaces use IconLibrary Lucide variants." Not S23 scope — add to next Brain consolidation session.

---

## Out of Scope (S23)

- llms.txt inclusion per-post widget (S24)
- FB29 new schema types
- FB30 Cite Bridges tab
- FB34 Global Meta defaults
- LogsTable.php URI filter
- `citewp_aiso/metabox/tabs` $context arg documentation in UI-DESIGN-SYSTEM.md (Brain consolidation)
