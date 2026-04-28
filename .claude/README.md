# .claude/ - Project Rules for AI Search Optimizer

This folder configures Claude Code for the plugin. The contents are split into committed project rules (everyone gets these on a fresh clone) and gitignored machine-local state.

## What's in here

| File | Purpose | Committed? |
|---|---|---|
| `settings.json` | Hook registrations. Claude Code reads this on session start. | Yes |
| `settings.local.json` | Machine-local permission grants. | No (gitignored) |
| `.engine-edit-approved` | Sentinel file - its presence unlocks edits to `Engine.php`. | No (gitignored) |
| `hooks/` | Shell scripts the hooks call. Run by Claude Code on Edit/Write/Stop events. | Yes |
| `commands/` | Slash commands. Markdown files with frontmatter. Invoked manually. | Yes |
| `README.md` | This file. | Yes |

## Hooks

Four hooks fire automatically. Defined in `settings.json`, implemented in `hooks/`.

### `block-engine-edit.sh` - PreToolUse on Edit/Write
Blocks any edit to `includes/Scoring/Engine.php` unless the sentinel file `.claude/.engine-edit-approved` exists. Master file rule #4 / DECISIONS.md A11.

### `php-syntax-check.sh` - PostToolUse on Edit/Write
After any `*.php` file is touched, runs `php -l` on it and prints a warning to stderr if syntax fails. Non-blocking. Skips silently if `php` is not on PATH.

### `js-build-reminder.sh` - PostToolUse on Edit/Write
After any `src/**/*.js` or `*.jsx` file is touched, prints a reminder to run `npm run build` from the plugin folder.

### `stop-checklist.sh` - Stop
At the end of every agent turn, prints the end-of-session checklist with the most-skipped steps highlighted (SESSION-LOG, DECISIONS, master file updates, push). Non-blocking - informational.

## Slash commands

Type these in Claude Code:

- `/session-start` - Read master, last SESSION-LOG entry, DECISIONS. State session # + deliverable. Stop. Wait for direction.
- `/session-end` - Verify deliverable, build, debug.log, update SESSION-LOG, update DECISIONS if needed, update master if state changed, commit, push, state carryover.
- `/smoke-test` - Run the 10-step Session 7.5 smoke test (bot detection, llms.txt, Cite Score, sidebar, post column, REST, Crawler Logs, Dashboard, Settings, uninstall). Per X6, the uninstall test does NOT use the live source folder.

## NO-TOUCH - Engine.php

`includes/Scoring/Engine.php` is locked by default. The `block-engine-edit.sh` hook enforces this.

**Polarity (read this carefully):**

- Sentinel file `.claude/.engine-edit-approved` PRESENT = edits ALLOWED.
- Sentinel file ABSENT = edits BLOCKED. (Default.)

The sentinel is gitignored, so it never leaves this machine. Default state on a fresh clone is "absent" - Engine.php is locked.

### To unlock for an approved edit

PowerShell open at:
```
C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer
```

Create the sentinel:
```powershell
New-Item -ItemType File -Path .claude/.engine-edit-approved
```

Make the edit through Claude Code.

### Re-lock immediately after

```powershell
Remove-Item .claude/.engine-edit-approved
```

Don't leave the sentinel in place "for convenience." It exists to be the conscious step - the friction is the feature.

## Gitignore pattern

The plugin's `.gitignore` is configured so that `.claude/` is ignored by default but the four committable items are explicitly un-ignored:

```
.claude/*
!.claude/hooks/
!.claude/commands/
!.claude/settings.json
!.claude/README.md
```

That keeps `settings.local.json` and `.engine-edit-approved` out of git while shipping the project rules.

## Where to open PowerShell

User preference: every command in this project specifies the working directory before the command itself. Example:

```
PowerShell open at:
  C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer

npm run build
```

Hooks and commands in this folder follow that convention.
