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

## Step 7 - Commit and push

From the plugin folder. Use a `feat:` / `fix:` / `refactor:` / `docs:` / `chore:` prefix.

```
PowerShell open at:
  C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer

git add -A
git commit -m "feat: <description>"
git push
```

Per X4 - push after every meaningful step in multi-step work, not just at session end.

## Step 8 - State carryover explicitly

End your final message with a "Carryover into next session" block. No silent carryover. If there is none, say "No carryover."
