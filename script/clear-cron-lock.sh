#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
PHP_BIN="${PHP_BIN:-php8.4-cli}"
FORCE=0
DRY_RUN=0

usage() {
    cat <<'USAGE'
Usage:
  script/clear-cron-lock.sh [--force] [--dry-run] [--root /path/to/phpBB] [--php php8.4-cli]

Clears phpBB's DB-backed cron lock in <table_prefix>config.config_name = 'cron_lock'.

By default this script refuses to clear a lock newer than 3600 seconds, matching
phpBB's own cron lock timeout. Use --force only after verifying no cron process
is still running.
USAGE
}

while [ "$#" -gt 0 ]; do
    case "$1" in
        --force)
            FORCE=1
            shift
            ;;
        --dry-run)
            DRY_RUN=1
            shift
            ;;
        --root)
            ROOT_DIR="${2:?missing path after --root}"
            shift 2
            ;;
        --php)
            PHP_BIN="${2:?missing binary after --php}"
            shift 2
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "Unknown argument: $1" >&2
            usage >&2
            exit 2
            ;;
    esac
done

ROOT_DIR="$(cd "$ROOT_DIR" && pwd)"
CONFIG_FILE="$ROOT_DIR/config.php"

if [ ! -f "$CONFIG_FILE" ]; then
    echo "config.php not found at: $CONFIG_FILE" >&2
    exit 1
fi

if ! command -v "$PHP_BIN" >/dev/null 2>&1; then
    echo "PHP binary not found: $PHP_BIN" >&2
    echo "Try: PHP_BIN=php script/clear-cron-lock.sh" >&2
    exit 1
fi

"$PHP_BIN" -d display_errors=stderr -r '
$config_file = $argv[1];
$force = (int) $argv[2];
$dry_run = (int) $argv[3];

require $config_file;

$required = ["dbhost", "dbport", "dbname", "dbuser", "dbpasswd", "table_prefix"];
foreach ($required as $name) {
    if (!isset($$name)) {
        fwrite(STDERR, "Missing $" . $name . " in config.php\n");
        exit(1);
    }
}

$socket = null;
$port = null;
$host = $dbhost !== "" ? $dbhost : "localhost";

if ($dbport !== "") {
    if (is_numeric($dbport)) {
        $port = (int) $dbport;
    } else {
        $socket = $dbport;
    }
}

$mysqli = @new mysqli($host, $dbuser, $dbpasswd, $dbname, $port ?: null, $socket ?: null);
if ($mysqli->connect_errno) {
    fwrite(STDERR, "DB connect failed: " . $mysqli->connect_error . "\n");
    exit(1);
}

$table = preg_replace("/[^A-Za-z0-9_]/", "", $table_prefix) . "config";
$result = $mysqli->query("SELECT config_value FROM `$table` WHERE config_name = '\''cron_lock'\'' LIMIT 1");
if (!$result) {
    fwrite(STDERR, "Unable to read cron_lock: " . $mysqli->error . "\n");
    exit(1);
}

$row = $result->fetch_assoc();
if (!$row) {
    echo "cron_lock row does not exist; nothing to clear.\n";
    exit(0);
}

$value = (string) $row["config_value"];
if ($value === "" || $value === "0") {
    echo "cron_lock is already clear.\n";
    exit(0);
}

$parts = explode(" ", $value, 2);
$lock_time = ctype_digit($parts[0] ?? "") ? (int) $parts[0] : 0;
$age = $lock_time > 0 ? time() - $lock_time : null;

echo "Current cron_lock: " . $value . "\n";
if ($lock_time > 0) {
    echo "Lock time: " . date("Y-m-d H:i:s T", $lock_time) . "\n";
    echo "Lock age: " . $age . " seconds\n";
}

if (!$force && $lock_time > 0 && $age < 3600) {
    fwrite(STDERR, "Refusing to clear a recent lock. Verify cron is not running, then rerun with --force.\n");
    exit(3);
}

if ($dry_run) {
    echo "Dry run: would clear cron_lock.\n";
    exit(0);
}

$stmt = $mysqli->prepare("UPDATE `$table` SET config_value = '\''0'\'' WHERE config_name = '\''cron_lock'\''");
if (!$stmt || !$stmt->execute()) {
    fwrite(STDERR, "Unable to clear cron_lock: " . $mysqli->error . "\n");
    exit(1);
}

echo "cron_lock cleared.\n";
' "$CONFIG_FILE" "$FORCE" "$DRY_RUN"
