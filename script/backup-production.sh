#!/bin/bash

set -euo pipefail

# --- CONFIGURATION ---
# Database name (found in IONOS control panel)
DB_NAME="dbs15523322"
# Path to your phpBB installation
FORUM_ROOT="$HOME/phpBB3"
# Local temporary backup directory
LOCAL_BACKUP_DIR="$HOME/backups_tmp"
# Backblaze B2 Bucket Name
B2_BUCKET="freemitbbs"
# Path to the rclone binary you downloaded earlier
RCLONE_PATH="$HOME/rclone"

DATE=$(date +%Y-%m-%d)
FORUM_PARENT=$(dirname "$FORUM_ROOT")
FORUM_NAME=$(basename "$FORUM_ROOT")

mkdir -p "$LOCAL_BACKUP_DIR"

echo "Starting phpBB backup for $DATE..."

# 1. Database Backup (Uses credentials from ~/.my.cnf)
echo "Dumping database..."
# Use --single-transaction to avoid locking tables for visitors
mysqldump "$DB_NAME" --single-transaction | gzip > "$LOCAL_BACKUP_DIR/db_$DATE.sql.gz"

# 2. Files Backup (Archive the entire board tree)
echo "Compressing site files..."
tar -czf "$LOCAL_BACKUP_DIR/files_$DATE.tar.gz" \
	-C "$FORUM_PARENT" "$FORUM_NAME"

# 3. Sync to Backblaze B2
# --fast-list is highly recommended by Backblaze to save on API call costs
echo "Uploading to Backblaze B2..."
"$RCLONE_PATH" copy "$LOCAL_BACKUP_DIR" "b2_remote:$B2_BUCKET" --fast-list

# 4. Cleanup local temp files older than 3 days
find "$LOCAL_BACKUP_DIR" -type f -mtime +3 -delete

# 5. Delete backups older than 30 days from Backblaze B2
# --min-age 30d identifies files older than 30 days
"$RCLONE_PATH" delete "b2_remote:$B2_BUCKET" --min-age 30d

echo "Backup complete!"
