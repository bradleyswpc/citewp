#!/usr/bin/env bash
# stop-checklist.sh
# Stop hook. Prints the end-of-session checklist. Non-blocking.

cat >&2 <<'INNER_EOF'

==================================================================
  END-OF-SESSION CHECKLIST  (Brain/00-CITEWP-MASTER.md)
==================================================================

  Mandatory before closing the session:

  [ ] 1. Verify deliverable works (manual feature test).

  [ ] 2. If JS changed:  npm run build
        PowerShell open at:
          C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer

  [ ] 3. Check LocalWP debug.log for new PHP errors.
        Path:
          C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\debug.log

  [ ] 4. Commit + push to GitHub.
        Per X4: per-step commits + pushes for any multi-step work.

  ---  STEPS MOST OFTEN SKIPPED  ---

  [ ] 5. Update SESSION-LOG.md (this folder).
        What shipped, what carried over, next session focus.

  [ ] 6. If a new decision was made:
        Append entry to Brain/DECISIONS.md (append-only).
        Path:
          C:\Users\KingpinBWP\Desktop\CiteWP\Brain\DECISIONS.md

  [ ] 7. If state changed:
        Update "Current State" in Brain/00-CITEWP-MASTER.md.
        Bump "Last updated" date at the top of the master file.
        Path:
          C:\Users\KingpinBWP\Desktop\CiteWP\Brain\00-CITEWP-MASTER.md

  [ ] 8. State carryover explicitly. No silent carryover.

==================================================================

INNER_EOF

exit 0
