#!/usr/bin/env bash

set -euo pipefail

# Edit this block for your test database.
DUMP_FILE="/absolute/path/to/phpbb-backup.sql.gz"
TEST_DB_HOST="127.0.0.1"
TEST_DB_PORT="3306"
TEST_DB_SOCKET=""
TEST_DB_NAME="phpbb_test_restore"
TEST_DB_USER="root"
TEST_DB_PASSWORD=""
TEST_DB_CHARSET="utf8mb4"
TEST_DB_COLLATION="utf8mb4_unicode_ci"
DROP_AND_RECREATE_DB="yes"

MYSQL_BIN="${MYSQL_BIN:-$(command -v mysql || true)}"
GZIP_BIN="${GZIP_BIN:-$(command -v gzip || true)}"

require_bin() {
	local name="$1"
	local path="$2"

	if [[ -z "$path" ]]; then
		echo "Missing required binary: $name" >&2
		exit 1
	fi
}

require_file() {
	local path="$1"

	if [[ ! -f "$path" ]]; then
		echo "Dump file not found: $path" >&2
		exit 1
	fi
}

mysql_args() {
	local -a args

	args=()
	if [[ -n "$TEST_DB_SOCKET" ]]; then
		args+=("--socket=$TEST_DB_SOCKET")
	else
		args+=("--host=$TEST_DB_HOST")
		if [[ -n "$TEST_DB_PORT" ]]; then
			args+=("--port=$TEST_DB_PORT")
		fi
	fi

	args+=("-u$TEST_DB_USER")
	printf '%s\n' "${args[@]}"
}

mysql_exec() {
	local database="${1:-}"
	shift || true

	local -a args
	mapfile -t args < <(mysql_args)

	if [[ -n "$database" ]]; then
		args+=("$database")
	fi

	MYSQL_PWD="$TEST_DB_PASSWORD" "$MYSQL_BIN" "${args[@]}" "$@"
}

mysql_quote_ident() {
	local ident="$1"
	ident="${ident//\`/\`\`}"
	printf '`%s`' "$ident"
}

dump_stream() {
	case "$DUMP_FILE" in
		*.sql)
			cat "$DUMP_FILE"
			;;
		*.sql.gz|*.gz)
			"$GZIP_BIN" -dc "$DUMP_FILE"
			;;
		*)
			echo "Unsupported dump format: $DUMP_FILE" >&2
			echo "Expected .sql or .sql.gz" >&2
			exit 1
			;;
	esac
}

strip_database_statements() {
	perl -ne '
		next if /^\s*CREATE\s+DATABASE\b/i;
		next if /^\s*DROP\s+DATABASE\b/i;
		next if /^\s*USE\b/i;
		print;
	'
}

create_database() {
	local db_quoted
	db_quoted="$(mysql_quote_ident "$TEST_DB_NAME")"

	if [[ "$DROP_AND_RECREATE_DB" == "yes" ]]; then
		echo "Dropping existing test database if present: $TEST_DB_NAME"
		mysql_exec "" -e "DROP DATABASE IF EXISTS $db_quoted"
	fi

	echo "Creating test database: $TEST_DB_NAME"
	mysql_exec "" -e "CREATE DATABASE IF NOT EXISTS $db_quoted CHARACTER SET $TEST_DB_CHARSET COLLATE $TEST_DB_COLLATION"
}

restore_dump() {
	echo "Restoring $DUMP_FILE into $TEST_DB_NAME"
	dump_stream | strip_database_statements | mysql_exec "$TEST_DB_NAME"
}

main() {
	require_bin "mysql" "$MYSQL_BIN"
	require_bin "gzip" "$GZIP_BIN"
	require_file "$DUMP_FILE"

	create_database
	restore_dump

	echo "Restore complete."
	echo "Host: $TEST_DB_HOST"
	echo "Database: $TEST_DB_NAME"
}

main "$@"
