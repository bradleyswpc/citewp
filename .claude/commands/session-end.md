---
description: Run the end-of-session protocol (verify deliverable, build, debug.log, SESSION-LOG, DECISIONS, master file, commit, push).
---

# Session End

Execute the end-of-session protocol from `Brain/00-CITEWP-MASTER.md`. Walk these in order. Do not skip the bookkeeping steps just because the code works - the bookkeeping is what keeps future sessions sane.

## Step 1 - Verify the deliverable manually

State what was supposed to ship this session. Confirm it works. If it doesn't, stop here and fix it before continuing.

## Step 2 - If any JS changed, build it

```
PowerShell open at:
  C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer

npm run build
```

Must compile clean. Pre-existing warnings are fine (the 3 chartLine icon warnings from the sidebar are known); new warnings need investigation.

## Step 3 - Check debug.log for new PHP errors

```
C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\debug.log
```

If new errors landed during this session, address them or call them out as known carryover.

## Step 4 - Update SESSION-LOG.md

Add a new entry above the previous one (newest first) in `SESSION-LOG.md` of this plugin folder. Include:

- Session number and title
- Date
- Deliverable
- Shipped (file list with one-line per change)
- Modified (file list with one-line per change)
- Decisions made (if any - these also go in DECISIONS.md)
- Verified (manual test results)
- Carryover into next session (explicit, no silent carryover)
- Next session focus

## Step 5 - If a new decision was made, append to DECISIONS.md

```
C:\Users\KingpinBWP\Desktop\CiteWP\Brain\DECISIONS.md
```

Append-only. Use the existing table format. ID prefix matches the category (A / P / R / S / X). Number sequentially within the category.

## Step 6 - If state changed, update the master file

```
C:\Users\KingpinBWP\Desktop\CiteWP\Brain\00-CITEWP-MASTER.md
```

Two updates:

1. Bump the "Last updated" date at the top.
2. Update the "Current State" section - add this session under "Shipped", remove from carryover, set next session focus.

Do not rewrite sections that didn't change.

## Step 7 - Verify cross-reference integrity AND outcome accuracy (X16)

Before committing, run two checks. This is mandatory per X16 — it catches phantom decision references and outcome drift before they propagate into future sessions.

### 7a - Cross-reference integrity check

For every P-number, X-number, A-number, R-number, S-number you ADDED or REFERENCED in this session's edits to DECISIONS.md, FEATURE-BACKLOG.md, master file, or SESSION-LOG.md, verify the actual decision row exists in DECISIONS.md.

Quick grep pattern (run from `Desktop\CiteWP\Brain\`):

```
PowerShell open at:
  C:\Users\KingpinBWP\Desktop\CiteWP\Brain

# List every P/X/A/R/S reference across canonical files
Select-String -Path "*.md","..\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer\SESSION-LOG.md" -Pattern "\b[PXARS]\d+\b" | Select-Object -ExpandProperty Matches | Select-Object -ExpandProperty Value | Sort-Object -Unique
```

For each reference:
- If the row exists in DECISIONS.md → pass
- If the row does NOT exist → either (a) write the row now, or (b) remove the reference from the file that contains it

Phantom references — where future-tense decision drafts ("rejected separately as P25") get committed to other files before the actual row is written — are forbidden. Either the row exists or the reference doesn't.

### 7b - Outcome accuracy check

Review every outcome statement added to master file `Current state` line and SESSION-LOG.md outcomes during this session. For each:

- "Approved" / "Live" / "Shipped" / "Deployed" / "Submitted" claims — verify external evidence:
  - "Approved" only if you have the approval email in hand
  - "Live on WP.org" only if SVN commit verified (not just GitHub push)
  - "Shipped" only if the deliverable is actually merged to `main` and pushed
  - "Submitted" only if the WP.org submission page or equivalent confirms it
- If the claim was anticipatory ("about to submit", "will be live"), correct it to reflect actual state at session close

Two real failures motivated this rule (see X16 in DECISIONS.md). Skipping this check is the failure mode.

## Step 8 - Commit and push

From the plugin folder. Use a `feat:` / `fix:` / `refactor:` / `docs:` / `chore:` prefix.

```
PowerShell open at:
  C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer

git add -A
git commit -m "feat: <description>"
git push
```

Per X4 - push after every meaningful step in multi-step work, not just at session end.

## Step 9 - State carryover explicitly

End your final message with a "Carryover into next session" block. No silent carryover. If there is none, say "No carryover."
