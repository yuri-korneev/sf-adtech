#!/usr/bin/env bash
set -euo pipefail
if [ $# -ne 1 ]; then
  echo "Usage: $0 database/dumps/FILE.sql[.gz]" >&2
  exit 1
fi
file="$1"
if [[ "$file" == *.gz ]]; then
  gunzip -c "$file" | mysql -u root -psecret sfadtech
else
  mysql -u root -psecret sfadtech < "$file"
fi
echo "Restored from $file"
