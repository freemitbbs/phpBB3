#!/usr/bin/env bash

set -euo pipefail

# Edit this block for your test restore.
BASE_PHPBB_ARCHIVE="/Users/hyang/Downloads/phpBB-3.3.15.zip"
MANDARIN_LANGUAGE_PACK="/Users/hyang/Downloads/mandarin_chinese_simplified_script_25_04_0.zip"
DB_DUMP_FILE="/Users/hyang/Downloads/phpBB3/db_2026-04-16.sql.gz"
FILES_ARCHIVE="/Users/hyang/Downloads/phpBB3/files_2026-04-16.tar.gz"
TARGET_DIR="/Users/hyang/Downloads/phpBB3/.tmp/test-board-live"

TEST_DB_HOST="localhost"
TEST_DB_PORT=""
TEST_DB_SOCKET="/tmp/phpbb-mysql.sock"
TEST_DB_NAME="phpbb_test_restore"
TEST_DB_USER="root"
TEST_DB_PASSWORD=""
TEST_DB_CHARSET="utf8mb4"
TEST_DB_COLLATION="utf8mb4_unicode_ci"

LOCAL_SERVER_NAME="127.0.0.1"
LOCAL_SERVER_PORT="8090"
LOCAL_SCRIPT_PATH="/"
LOCAL_COOKIE_DOMAIN=""
LOCAL_COOKIE_SECURE="0"

DROP_AND_RECREATE_DB="yes"
RESET_TARGET_DIR="yes"

MYSQL_BIN="${MYSQL_BIN:-$(command -v mysql || true)}"
GZIP_BIN="${GZIP_BIN:-$(command -v gzip || true)}"
TAR_BIN="${TAR_BIN:-$(command -v tar || true)}"
UNZIP_BIN="${UNZIP_BIN:-$(command -v unzip || true)}"
PERL_BIN="${PERL_BIN:-$(command -v perl || true)}"

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
		echo "Required file not found: $path" >&2
		exit 1
	fi
}

mysql_exec() {
	local database="${1:-}"
	shift || true

	local -a args

	args=("$MYSQL_BIN")

	if [[ -n "$TEST_DB_SOCKET" ]]; then
		args+=("--socket=$TEST_DB_SOCKET")
	else
		args+=("--host=$TEST_DB_HOST")
		if [[ -n "$TEST_DB_PORT" ]]; then
			args+=("--port=$TEST_DB_PORT")
		fi
	fi

	args+=("-u$TEST_DB_USER")
	if [[ -n "$TEST_DB_PASSWORD" ]]; then
		args+=("-p$TEST_DB_PASSWORD")
	fi

	if [[ -n "$database" ]]; then
		args+=("$database")
	fi

	"$MYSQL_BIN" "${args[@]}" "$@"
}

mysql_quote_ident() {
	local ident="$1"
	ident="${ident//\`/\`\`}"
	printf '`%s`' "$ident"
}

db_dump_stream() {
	case "$DB_DUMP_FILE" in
		*.sql)
			cat "$DB_DUMP_FILE"
			;;
		*.sql.gz|*.gz)
			"$GZIP_BIN" -dc "$DB_DUMP_FILE"
			;;
		*)
			echo "Unsupported DB dump format: $DB_DUMP_FILE" >&2
			echo "Expected .sql or .sql.gz" >&2
			exit 1
			;;
	esac
}

strip_database_statements() {
	"$PERL_BIN" -ne '
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

restore_database() {
	echo "Restoring database dump into $TEST_DB_NAME"
	db_dump_stream | strip_database_statements | mysql_exec "$TEST_DB_NAME"
}

prepare_target_dir() {
	if [[ "$RESET_TARGET_DIR" == "yes" && -d "$TARGET_DIR" ]]; then
		echo "Removing existing target directory: $TARGET_DIR"
		rm -rf "$TARGET_DIR"
	fi

	mkdir -p "$TARGET_DIR"
}

copy_extracted_tree() {
	local source_dir="$1"
	local extracted_root="$source_dir"
	local child_count=0
	local first_child=""
	local entry

	while IFS= read -r entry; do
		child_count=$((child_count + 1))
		if [[ "$child_count" -eq 1 ]]; then
			first_child="$entry"
		fi
	done < <(find "$source_dir" -mindepth 1 -maxdepth 1 | sort)

	if [[ "$child_count" -eq 1 && -d "$first_child" ]]; then
		extracted_root="$first_child"
	fi

	cp -R "$extracted_root"/. "$TARGET_DIR"/
}

restore_files() {
	local base_tmp_dir
	local language_tmp_dir

	base_tmp_dir="$(mktemp -d "${TMPDIR:-/tmp}/restore-base.XXXXXX")"
	language_tmp_dir="$(mktemp -d "${TMPDIR:-/tmp}/restore-lang.XXXXXX")"
	trap 'rm -rf "$base_tmp_dir" "$language_tmp_dir"' RETURN

	echo "Extracting base phpBB archive into $TARGET_DIR"
	case "$BASE_PHPBB_ARCHIVE" in
		*.zip)
			"$UNZIP_BIN" -q "$BASE_PHPBB_ARCHIVE" -d "$base_tmp_dir"
			;;
		*.tar.gz|*.tgz)
			"$TAR_BIN" -xzf "$BASE_PHPBB_ARCHIVE" -C "$base_tmp_dir"
			;;
		*)
			echo "Unsupported base archive format: $BASE_PHPBB_ARCHIVE" >&2
			exit 1
			;;
	esac
	copy_extracted_tree "$base_tmp_dir"

	echo "Extracting board files into $TARGET_DIR"
	"$TAR_BIN" -xzf "$FILES_ARCHIVE" -C "$TARGET_DIR"

	echo "Installing Mandarin Chinese language pack into $TARGET_DIR"
	case "$MANDARIN_LANGUAGE_PACK" in
		*.zip)
			"$UNZIP_BIN" -q "$MANDARIN_LANGUAGE_PACK" -d "$language_tmp_dir"
			;;
		*.tar.gz|*.tgz)
			"$TAR_BIN" -xzf "$MANDARIN_LANGUAGE_PACK" -C "$language_tmp_dir"
			;;
		*)
			echo "Unsupported language pack format: $MANDARIN_LANGUAGE_PACK" >&2
			exit 1
			;;
	esac
	copy_extracted_tree "$language_tmp_dir"

	rm -rf "$TARGET_DIR/install"
	trap - RETURN
	rm -rf "$base_tmp_dir" "$language_tmp_dir"
}

rewrite_config_php() {
	local config_file="$TARGET_DIR/config.php"

	if [[ ! -f "$config_file" ]]; then
		echo "Extracted config.php not found: $config_file" >&2
		exit 1
	fi

	echo "Rewriting extracted config.php for test database"
	TEST_DB_HOST="$TEST_DB_HOST" \
	TEST_DB_PORT="$TEST_DB_PORT" \
	TEST_DB_NAME="$TEST_DB_NAME" \
	TEST_DB_USER="$TEST_DB_USER" \
	TEST_DB_PASSWORD="$TEST_DB_PASSWORD" \
	TEST_DB_SOCKET="$TEST_DB_SOCKET" \
	"$PERL_BIN" -0pi -e '
		my $dbhost = $ENV{TEST_DB_HOST};
		my $dbport = $ENV{TEST_DB_PORT};
		my $dbname = $ENV{TEST_DB_NAME};
		my $dbuser = $ENV{TEST_DB_USER};
		my $dbpass = $ENV{TEST_DB_PASSWORD};
		my $dbsocket = $ENV{TEST_DB_SOCKET};

		$dbport = $dbsocket if defined($dbsocket) && $dbsocket ne q{};

		s/^\$dbhost = .*?;$/\$dbhost = '\''$dbhost'\'';/m;
		s/^\$dbport = .*?;$/\$dbport = '\''$dbport'\'';/m;
		s/^\$dbname = .*?;$/\$dbname = '\''$dbname'\'';/m;
		s/^\$dbuser = .*?;$/\$dbuser = '\''$dbuser'\'';/m;
		s/^\$dbpasswd = .*?;$/\$dbpasswd = '\''$dbpass'\'';/m;
	' "$config_file"
}

localize_board_config() {
	echo "Updating board config for local test host"
	mysql_exec "$TEST_DB_NAME" -e "
		UPDATE phpbb_config SET config_value = '$(printf "%s" "$LOCAL_SERVER_NAME" | sed "s/'/''/g")' WHERE config_name = 'server_name';
		UPDATE phpbb_config SET config_value = '$(printf "%s" "$LOCAL_SERVER_PORT" | sed "s/'/''/g")' WHERE config_name = 'server_port';
		UPDATE phpbb_config SET config_value = '$(printf "%s" "$LOCAL_SCRIPT_PATH" | sed "s/'/''/g")' WHERE config_name = 'script_path';
		UPDATE phpbb_config SET config_value = '$(printf "%s" "$LOCAL_COOKIE_DOMAIN" | sed "s/'/''/g")' WHERE config_name = 'cookie_domain';
		UPDATE phpbb_config SET config_value = '$(printf "%s" "$LOCAL_COOKIE_SECURE" | sed "s/'/''/g")' WHERE config_name = 'cookie_secure';
	"
}

purge_phpbb_cache() {
	echo "Purging restored board cache"
	(
		cd "$TARGET_DIR"
		php bin/phpbbcli.php cache:purge >/dev/null
	)
}

main() {
	require_bin "mysql" "$MYSQL_BIN"
	require_bin "gzip" "$GZIP_BIN"
	require_bin "tar" "$TAR_BIN"
	require_bin "unzip" "$UNZIP_BIN"
	require_bin "perl" "$PERL_BIN"
	require_file "$BASE_PHPBB_ARCHIVE"
	require_file "$MANDARIN_LANGUAGE_PACK"
	require_file "$DB_DUMP_FILE"
	require_file "$FILES_ARCHIVE"

	create_database
	restore_database
	prepare_target_dir
	restore_files
	rewrite_config_php
	localize_board_config
	purge_phpbb_cache

	echo "Test board restore complete."
	echo "Board dir: $TARGET_DIR"
	echo "Database: $TEST_DB_NAME"
}

main "$@"
