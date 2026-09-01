#!/usr/bin/env bash
# The repo is canonical. Push it into the live skill directory.
set -e
rsync -a --delete "$(dirname "$0")/plugins/agentready/skills/agentready/" "$HOME/.claude/skills/agentready/"
echo "Synced to ~/.claude/skills/agentready"
