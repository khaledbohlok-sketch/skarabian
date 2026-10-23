#!/bin/sh
# Preview the built site: http://127.0.0.1:8090/ (English) and /ar/ (Arabic)
cd "$(dirname "$0")/.." && python3 -m http.server 8090 --bind 127.0.0.1
