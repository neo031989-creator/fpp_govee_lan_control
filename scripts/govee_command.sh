#!/bin/bash
set -e
PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"
exec /usr/bin/python3 "$PLUGIN_DIR/scripts/govee_lan.py" "$@"
