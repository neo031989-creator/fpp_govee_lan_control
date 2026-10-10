#!/bin/bash
set -e
PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"
chmod 755 "$PLUGIN_DIR/scripts/govee_lan.py" "$PLUGIN_DIR/scripts/govee_command.sh"
mkdir -p "$PLUGIN_DIR/config"
[ -f "$PLUGIN_DIR/config/devices.json" ] || printf '{"devices":{}}\n' > "$PLUGIN_DIR/config/devices.json"
