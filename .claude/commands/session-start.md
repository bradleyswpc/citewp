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

- No new files in `Brain/` without explicit approval (5 active files only).
- `includes/Scoring/Engine.php` is NO-TOUCH - sentinel-gated by the `block-engine-edit` hook.
- Don't relitigate settled decisions in `DECISIONS.md`.
- Always specify where to open PowerShell from when giving commands.
