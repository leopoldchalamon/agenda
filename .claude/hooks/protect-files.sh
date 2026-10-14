#!/usr/bin/env bash
file=$(jq -r '.tool_input.file_path // empty')
rel=${file#"$CLAUDE_PROJECT_DIR"/}

case "$rel" in
  .env|composer.lock|symfony.lock|config/bundles.php)
    echo "$rel is protected. Use .env.local for local values; let composer/Flex manage lock files and bundles.php." >&2
    exit 2
    ;;
esac
exit 0
