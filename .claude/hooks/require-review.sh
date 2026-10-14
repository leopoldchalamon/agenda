#!/usr/bin/env bash
cmd=$(jq -r '.tool_input.command // empty')
grep -qE '(^|[;&|[:space:]])git[[:space:]]+commit\b' <<<"$cmd" || exit 0

if grep -qE '(^|[;&|[:space:]])git[[:space:]]+(add|rm|mv)\b|git[[:space:]]+commit[^;&|]*[[:space:]]-[a-zA-Z]*a' <<<"$cmd"; then
  echo "Stage files in a separate command before committing (no git add/rm/mv or commit -a in the same command), so the staged diff can be checked." >&2
  exit 2
fi

cd "$CLAUDE_PROJECT_DIR" || exit 0

git diff --cached --name-only | grep -q '\.php$' || exit 0

hash=$(git diff --cached | sha1sum | cut -d' ' -f1)
[ "$(cat .claude/.review-ok 2>/dev/null)" = "$hash" ] && exit 0

echo "Staged PHP changes not reviewed. Run the symfony-reviewer subagent, address findings, then retry the commit." >&2
exit 2
