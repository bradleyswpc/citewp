---
description: Run the start-of-session protocol (read Brain master, last SESSION-LOG entry, DECISIONS, then state session # + deliverable).
---

# Session Start

Execute the start-of-session protocol exactly as defined in `Brain/00-CITEWP-MASTER.md`. Do not skip steps. Do not summarize what you read until you've read everything.

## Step 1 - Read the master brief in full

Read `C:\Users\KingpinBWP\Desktop\CiteWP\Brain\00-CITEWP-MASTER.md` top to bottom. Do not skim. The "Horizon" section constrains what we build today even if today's work is in Phase 1.5 - read it.

## Step 2 - Read the last SESSION-LOG entry

Read the most recent entry only from `SESSION-LOG.md` in this plugin folder. You need:

- The previous session's deliverable
- Any carryover into the new session
- The next session's intended deliverable

## Step 3 - Check DECISIONS.md for relevant settled decisions

Read `C:\Users\KingpinBWP\Desktop\CiteWP\Brain\DECISIONS.md`. Identify any decisions (Architecture / Product / Pricing / Strategic / Process) that touch today's work. Do not relitigate them - `DECISIONS.md` is append-only.

## Step 4 - Confirm what you found

Report exactly four things, one line each:

1. Current session number we're starting
2. Deliverable from the previous session
3. Carryover into this session
4. Next session's intended deliverable per the master file

## Step 5 - Stop

Do not propose work. Do not propose new files. Do not start coding. Wait for the user to tell you what we're doing today.

## Hard rules in effect (from master file)

- No new files in `Brain/` without explicit approval (6 active files: master, decisions, scoring-rubric, competitors, ui-design-system, feature-backlog).
- `includes/Scoring/Engine.php` is NO-TOUCH - sentinel-gated by the `block-engine-edit` hook.
- Don't relitigate settled decisions in `DECISIONS.md`.
- Always specify where to open PowerShell from when giving commands.

---

## Backlog Scan (added 2026-04-29 per X15)

After reading the master file, DECISIONS.md, and SESSION-LOG.md, ALSO read `Brain\FEATURE-BACKLOG.md`.

For the session's stated deliverable, perform this scan before plan-writing begins:

1. Identify which UI surface(s) the session's deliverable touches:
   - Admin pages (left rail, nav, individual page IA)
   - Meta boxes (per-post tabs, render output)
   - Settings (tab structure, sections)
   - Dashboard widget (cards, layout)
   - Per-post column / list table
   - REST API surface
   - Block editor sidebar surfaces

2. Cross-reference the surfaces against `Brain\FEATURE-BACKLOG.md` candidates. Identify all P-numbers (P28–P35 and any added later) whose target surface overlaps the session's surface.

3. Surface to user with this format BEFORE writing the plan:

   > "Session [N] deliverable touches: [list of surfaces].
   >
   > Backlog candidates targeting these surfaces:
   > - P28 (Cite Audit) — needs admin nav rail slot, settings tab
   > - P30 (Cite Bridges) — needs meta box tab slot
   > - [etc., only candidates relevant to this session]
   >
   > Confirm Session [N]'s build will:
   > (a) Reserve space for these via filter hooks per X15 (`apply_filters( 'citewp_aiso/...', ... )`)
   > (b) Explicitly defer them with a documented limitation
   >
   > Items neither reserved nor deferred become technical debt requiring future refactor."

4. User confirms reservations or deferrals before plan-writing begins.

5. The plan (`superpowers:writing-plans` output) MUST include an explicit "Extensibility Hooks" section listing every filter registration point added to accommodate confirmed-reserved backlog candidates.

6. End-of-session verification (`superpowers:verification-before-completion` step) MUST confirm the filter hooks were actually added in code, not just specified in the plan.

**Skipping the backlog scan is a session-start protocol violation.** The backlog exists to prevent paint-into-corner architectural decisions. Bypassing the scan defeats the purpose; X15 compliance assumes the scan ran.

If `Brain\FEATURE-BACKLOG.md` does not exist for any reason, the session must stop and resolve that before continuing — the file is canonical.
