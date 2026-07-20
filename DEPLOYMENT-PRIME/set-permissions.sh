#!/usr/bin/env bash
# LOKA — set permissions on Hostinger / Linux document root
# Usage: ./set-permissions.sh /home/lokaloka/htdocs/lokastage.dictr2.cloud

set -euo pipefail

ROOT="${1:-}"
if [[ -z "$ROOT" || ! -d "$ROOT" ]]; then
  echo "Usage: $0 /absolute/path/to/document-root"
  exit 1
fi

ROOT="$(cd "$ROOT" && pwd)"
echo "Setting permissions under: $ROOT"

# Baseline
find "$ROOT" -type d -exec chmod 755 {} +
find "$ROOT" -type f -exec chmod 644 {} +

# Writable app paths
for d in cache logs uploads uploads/observations uploads/gas_vouchers; do
  if [[ -d "$ROOT/$d" ]]; then
    chmod 775 "$ROOT/$d"
    find "$ROOT/$d" -type d -exec chmod 775 {} +
    find "$ROOT/$d" -type f -exec chmod 664 {} + 2>/dev/null || true
  else
    mkdir -p "$ROOT/$d"
    chmod 775 "$ROOT/$d"
  fi
done

# Keep .env private
if [[ -f "$ROOT/.env" ]]; then
  chmod 640 "$ROOT/.env"
fi

# Ensure log dir exists for cron redirects
mkdir -p "$ROOT/logs"
chmod 775 "$ROOT/logs"
touch "$ROOT/logs/.gitkeep" 2>/dev/null || true

# CLI cron scripts executable (optional; php can run them without +x)
if [[ -d "$ROOT/cron" ]]; then
  find "$ROOT/cron" -type f -name '*.php' -exec chmod 644 {} +
fi

echo "Done."
echo "Owner tip: if PHP-FPM runs as your account user, chown -R lokaloka:lokaloka \"$ROOT\""
echo "           (adjust user/group to match Hostinger panel)."
