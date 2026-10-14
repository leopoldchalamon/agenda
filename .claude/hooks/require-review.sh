#!/usr/bin/env bash
cd "$CLAUDE_PROJECT_DIR" || exit 0

git diff --cached --name-only | grep -q '\.php$' || exit 0

hash=$(git diff --cached | sha1sum | cut -d' ' -f1)
[ "$(cat .claude/.review-ok 2>/dev/null)" = "$hash" ] && exit 0

echo "Staged PHP changes not reviewed. Run the symfony-reviewer subagent, address findings, then retry the commit." >&2
exit 2
