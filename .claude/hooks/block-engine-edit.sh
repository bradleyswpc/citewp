#!/usr/bin/env bash
# block-engine-edit.sh
# PreToolUse hook on Edit|Write.
# Blocks edits to includes/Scoring/Engine.php unless .claude/.engine-edit-approved exists.
# Polarity: sentinel PRESENT = allowed, ABSENT = blocked (default).

set -u

INPUT_JSON="$(cat)"

FILE_PATH=""
if command -v python3 >/dev/null 2>&1; then
    FILE_PATH="$(printf '%s' "$INPUT_JSON" | python3 -c 'import json,sys; d=json.load(sys.stdin); print(d.get("tool_input",{}).get("file_path",""))' 2>/dev/null || true)"
elif command -v python >/dev/null 2>&1; then
    FILE_PATH="$(printf '%s' "$INPUT_JSON" | python -c 'import json,sys; d=json.load(sys.stdin); print(d.get("tool_input",{}).get("file_path",""))' 2>/dev/null || true)"
else
    FILE_PATH="$(printf '%s' "$INPUT_JSON" | grep -oE '"file_path"[[:space:]]*:[[:space:]]*"[^"]+"' | head -1 | sed -E 's/.*"file_path"[[:space:]]*:[[:space:]]*"([^"]+)".*/\1/')"
fi

if [ -z "$FILE_PATH" ]; then
    exit 0
fi

NORMALIZED="$(printf '%s' "$FILE_PATH" | tr '\\' '/')"

case "$NORMALIZED" in
    */includes/Scoring/Engine.php|includes/Scoring/Engine.php)
        ;;
    *)
        exit 0
        ;;
esac

SENTINEL=".claude/.engine-edit-approved"

if [ -f "$SENTINEL" ]; then
    echo "[Engine.php edit allowed - sentinel present at $SENTINEL]" >&2
    exit 0
fi

cat >&2 <<'INNER_EOF'

==================================================================
  BLOCKED: includes/Scoring/Engine.php is NO-TOUCH
==================================================================

The Cite Score engine is the heart of the product. Weights, thresholds,
signals, and category allocations cannot be modified without explicit
user approval (master file rule #4, DECISIONS.md A11).

The canonical specification is Brain/SCORING-RUBRIC.md.

If a change is genuinely needed AND approved by the user:

  1. Open PowerShell from:
     C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer

  2. Create the sentinel:
     New-Item -ItemType File -Path .claude/.engine-edit-approved

  3. Make the edit.

  4. Re-lock immediately after:
     Remove-Item .claude/.engine-edit-approved

The sentinel is gitignored - it never leaves this machine.

==================================================================

INNER_EOF
exit 2
