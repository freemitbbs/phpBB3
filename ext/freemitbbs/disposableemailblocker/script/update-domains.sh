#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SOURCE_URL="https://raw.githubusercontent.com/doodad-labs/disposable-email-domains/main/data/domains.txt"
TARGET_FILE="$ROOT_DIR/data/domains.txt"
TMP_FILE="$(mktemp)"

cleanup() {
	rm -f "$TMP_FILE"
}
trap cleanup EXIT

curl -fsSL "$SOURCE_URL" -o "$TMP_FILE"

line_count="$(wc -l < "$TMP_FILE" | tr -d '[:space:]')"
if [[ ! "$line_count" =~ ^[0-9]+$ || "$line_count" -lt 1000 ]]; then
	echo "Downloaded disposable domain list looks too small: ${line_count:-0} lines" >&2
	exit 1
fi

mv "$TMP_FILE" "$TARGET_FILE"
trap - EXIT

echo "Updated $TARGET_FILE with $line_count domains"
