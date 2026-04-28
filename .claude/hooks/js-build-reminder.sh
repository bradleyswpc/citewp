#!/usr/bin/env bash
# js-build-reminder.sh
# PostToolUse hook on Edit|Write. Reminds to run npm run build for src/**/*.js changes.

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

[ -z "$FILE_PATH" ] && exit 0

NORMALIZED="$(printf '%s' "$FILE_PATH" | tr '\\' '/')"

case "$NORMALIZED" in
    */src/*.js|*/src/*.jsx|src/*.js|src/*.jsx)
        cat >&2 <<'INNER_EOF'

------------------------------------------------------------------
  REMINDER: JavaScript source changed
------------------------------------------------------------------

  Run the build before testing:

    PowerShell open at:
      C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer

    Command:
      npm run build

  The Gutenberg sidebar will not pick up changes until the build
  output in build/index.js is regenerated.

------------------------------------------------------------------

INNER_EOF
        ;;
    *) ;;
esac

exit 0
