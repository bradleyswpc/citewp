#!/usr/bin/env bash
# php-syntax-check.sh
# PostToolUse hook on Edit|Write. Runs php -l on .php files. Non-blocking.

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

case "$FILE_PATH" in
    *.php) ;;
    *) exit 0 ;;
esac

[ -f "$FILE_PATH" ] || exit 0

PHP_BIN=""
for candidate in \
    "php" \
    "/c/Program Files/Local/resources/extraResources/lightning-services/php-8.2.30+10/bin/win64/php.exe" \
    "/c/Program Files (x86)/Local/resources/extraResources/lightning-services/php-8.2.30+10/bin/win64/php.exe"
do
    if command -v "$candidate" >/dev/null 2>&1 || [ -x "$candidate" ]; then
        PHP_BIN="$candidate"
        break
    fi
done

if [ -z "$PHP_BIN" ]; then
    exit 0
fi

LINT_OUTPUT="$("$PHP_BIN" -l "$FILE_PATH" 2>&1)"
LINT_EXIT=$?

if [ $LINT_EXIT -ne 0 ]; then
    cat >&2 <<INNER_EOF

------------------------------------------------------------------
  PHP SYNTAX ERROR in $FILE_PATH
------------------------------------------------------------------
$LINT_OUTPUT
------------------------------------------------------------------

INNER_EOF
fi

exit 0
