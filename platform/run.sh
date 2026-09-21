#!/usr/bin/env bash
PORT=8080
if [ -n "$1" ]; then
    PORT=$1
fi
echo "============================================================"
echo "⚡ DREZZA Universal Catalog Matcher & Invoice Hub"
echo "🌐 Starting web server on http://localhost:$PORT"
echo "============================================================"
cd "$(dirname "$0")"
php -S 0.0.0.0:$PORT
