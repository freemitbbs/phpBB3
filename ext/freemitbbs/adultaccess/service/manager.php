<?php

namespace freemitbbs\adultaccess\service;

use phpbb\config\config;
use phpbb\db\driver\driver_interface;

class manager
{
	private const GROUP_NAME = '18+ Opt-In';
	private const GROUP_DESC = 'Managed by freemitbbs/adultaccess';
	private const SOURCE_GROUPS = ['REGISTERED', 'REGISTERED_COPPA'];
	private const STAFF_GROUPS = ['ADMINISTRATORS', 'GLOBAL_MODERATORS'];
	private const ADULT_GROUP_FORUM_ROLE = 'ROLE_FORUM_STANDARD';
	private const STAFF_GROUP_FORUM_ROLES = [
		'ADMINISTRATORS' => 'ROLE_FORUM_FULL',
		'GLOBAL_MODERATORS' => 'ROLE_FORUM_STANDARD',
	];
	private const READ_OPTIONS = ['f_read'];
	private const ACCESS_OPTIONS = ['f_read', 'f_list', 'f_list_topics'];
	private const WRITE_OPTIONS = ['f_post', 'f_reply'];

	protected config $config;
	protected driver_interface $db;
	protected string $table_prefix;
	protected string $phpbb_root_path;
	protected string $php_ext;
	protected array $group_id_cache = [];
	protected array $role_id_cache = [];
	protected array $role_acl_rows_cache = [];
	protected array $auth_option_id_cache = [];
	protected array $role_option_setting_cache = [];

	public function __construct(config $config, driver_interface $db, string $table_prefix, string $phpbb_root_path, string $php_ext)
	{
		$this->config = $config;
		$this->db = $db;
		$this->table_prefix = $table_prefix;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
	}

	public function get_group_name(): string
	{
		return self::GROUP_NAME;
	}

	public function get_adult_group_id(): int
	{
		$configured_group_id = isset($this->config['freemitbbs_adult_group_id']) ? (int) $this->config['freemitbbs_adult_group_id'] : 0;
		if ($configured_group_id > 0 && $this->group_exists($configured_group_id))
		{
			return $configured_group_id;
		}

		$group_id = $this->find_managed_group_id();
		if ($group_id > 0)
		{
			$this->config->set('freemitbbs_adult_group_id', (string) $group_id);
		}

		return $group_id;
	}

	public function get_forum_ids(): array
	{
		return $this->parse_forum_ids((string) ($this->config['freemitbbs_adult_forum_ids'] ?? ''));
	}

	public function normalize_forum_id_list(string $value): string
	{
		$forum_ids = $this->parse_forum_ids($value);
		sort($forum_ids);

		return implode(',', $forum_ids);
	}

	public function parse_forum_ids(string $value): array
	{
		$trimmed = preg_replace('/\s+/', '', trim($value));
		if ($trimmed === '')
		{
			return [];
		}

		$forum_ids = [];
		foreach (explode(',', $trimmed) as $part)
		{
			$forum_id = (int) $part;
			if ($forum_id > 0)
			{
				$forum_ids[$forum_id] = $forum_id;
			}
		}

		return array_values($forum_ids);
	}

	public function filter_valid_post_forum_ids(array $forum_ids): array
	{
		$forum_ids = array_values(array_unique(array_map('intval', $forum_ids)));
		$forum_ids = array_values(array_filter($forum_ids, static function (int $forum_id): bool
		{
			return $forum_id > 0;
		}));

		if (empty($forum_ids))
		{
			return [];
		}

		$sql = 'SELECT forum_id
			FROM ' . FORUMS_TABLE . '
			WHERE ' . $this->db->sql_in_set('forum_id', $forum_ids) . '
				AND forum_type = ' . FORUM_POST;
		$result = $this->db->sql_query($sql);

		$valid_forum_ids = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$forum_id = (int) $row['forum_id'];
			$valid_forum_ids[$forum_id] = $forum_id;
		}
		$this->db->sql_freeresult($result);

		$filtered_ids = [];
		foreach ($forum_ids as $forum_id)
		{
			if (isset($valid_forum_ids[$forum_id]))
			{
				$filtered_ids[] = $forum_id;
			}
		}

		return $filtered_ids;
	}

	public function is_user_opted_in(int $user_id): bool
	{
		$group_id = $this->get_adult_group_id();
		if ($group_id <= 0 || $user_id <= 0)
		{
			return false;
		}

		$sql = 'SELECT 1 AS has_row
			FROM ' . USER_GROUP_TABLE . '
			WHERE group_id = ' . $group_id . '
				AND user_id = ' . $user_id . '
				AND user_pending = 0';
		$result = $this->db->sql_query_limit($sql, 1);
		$opted_in = (bool) $this->db->sql_fetchfield('has_row');
		$this->db->sql_freeresult($result);

		return $opted_in;
	}

	public function get_user_opt_in_time(int $user_id): int
	{
		$sql = 'SELECT user_adult_opt_in_time
			FROM ' . USERS_TABLE . '
			WHERE user_id = ' . $user_id;
		$result = $this->db->sql_query_limit($sql, 1);
		$opt_in_time = (int) $this->db->sql_fetchfield('user_adult_opt_in_time');
		$this->db->sql_freeresult($result);

		return $opt_in_time;
	}

	public function opt_in_user(int $user_id): void
	{
		$group_id = $this->get_adult_group_id();
		if ($group_id <= 0 || $user_id <= 0)
		{
			return;
		}

		$this->ensure_user_functions_loaded();

		if (!$this->is_user_opted_in($user_id))
		{
			$error = group_user_add($group_id, [$user_id], false, self::GROUP_NAME);
			if ($error !== false && $error !== 'GROUP_USERS_EXIST')
			{
				throw new \RuntimeException((string) $error);
			}
		}

		$sql = 'UPDATE ' . USERS_TABLE . '
			SET user_adult_opt_in_time = ' . time() . '
			WHERE user_id = ' . $user_id;
		$this->db->sql_query($sql);
	}

	public function opt_out_user(int $user_id): void
	{
		$group_id = $this->get_adult_group_id();
		if ($group_id <= 0 || $user_id <= 0)
		{
			return;
		}

		$this->ensure_user_functions_loaded();

		if ($this->is_user_opted_in($user_id))
		{
			$error = group_user_del($group_id, [$user_id], false, self::GROUP_NAME);
			if ($error !== false && $error !== 'NO_USER')
			{
				throw new \RuntimeException((string) $error);
			}
		}

		$this->cleanup_user_forum_state($user_id);
	}

	public function cleanup_user_forum_state(int $user_id): void
	{
		$forum_ids = $this->get_forum_ids();
		if ($user_id <= 0 || empty($forum_ids))
		{
			return;
		}

		$sql = 'DELETE FROM ' . FORUMS_WATCH_TABLE . '
			WHERE user_id = ' . $user_id . '
				AND ' . $this->db->sql_in_set('forum_id', $forum_ids);
		$this->db->sql_query($sql);

		if (defined('FORUMS_TRACK_TABLE'))
		{
			$sql = 'DELETE FROM ' . FORUMS_TRACK_TABLE . '
				WHERE user_id = ' . $user_id . '
					AND ' . $this->db->sql_in_set('forum_id', $forum_ids);
			$this->db->sql_query($sql);
		}

		$topic_ids = $this->get_topic_ids_for_forums($forum_ids);
		if (empty($topic_ids))
		{
			return;
		}

		foreach (array_chunk($topic_ids, 500) as $topic_id_chunk)
		{
			$sql = 'DELETE FROM ' . TOPICS_WATCH_TABLE . '
				WHERE user_id = ' . $user_id . '
					AND ' . $this->db->sql_in_set('topic_id', $topic_id_chunk);
			$this->db->sql_query($sql);

			$sql = 'DELETE FROM ' . BOOKMARKS_TABLE . '
				WHERE user_id = ' . $user_id . '
					AND ' . $this->db->sql_in_set('topic_id', $topic_id_chunk);
			$this->db->sql_query($sql);

			if (defined('TOPICS_TRACK_TABLE'))
			{
				$sql = 'DELETE FROM ' . TOPICS_TRACK_TABLE . '
					WHERE user_id = ' . $user_id . '
						AND ' . $this->db->sql_in_set('topic_id', $topic_id_chunk);
				$this->db->sql_query($sql);
			}
		}
	}

	public function sync_forum_permissions(array $old_forum_ids, array $new_forum_ids): array
	{
		$old_forum_ids = $this->normalize_forum_ids($old_forum_ids);
		$new_forum_ids = $this->normalize_forum_ids($new_forum_ids);

		$active_forum_ids = [];
		$skipped_forum_ids = [];
		$permissions_changed = false;

		foreach (array_values(array_diff($old_forum_ids, $new_forum_ids)) as $forum_id)
		{
			$permissions_changed = $this->restore_registered_access($forum_id) || $permissions_changed;
		}

		foreach ($new_forum_ids as $forum_id)
		{
			try
			{
				$this->gate_forum($forum_id);
				$permissions_changed = true;
				$active_forum_ids[] = $forum_id;
			}
			catch (\RuntimeException $e)
			{
				$skipped_forum_ids[$forum_id] = $e->getMessage();

				if (in_array($forum_id, $old_forum_ids, true))
				{
					$active_forum_ids[] = $forum_id;
				}
			}
		}

		$active_forum_ids = $this->normalize_forum_ids($active_forum_ids);

		if ($permissions_changed)
		{
			$this->clear_acl_cache();
		}

		return [
			'active_forum_ids' => $active_forum_ids,
			'skipped_forum_ids' => $skipped_forum_ids,
		];
	}

	public function get_forum_status_rows(array $forum_ids): array
	{
		$rows = [];
		$adult_group_id = $this->get_adult_group_id();
		$staff_group_ids = $this->get_group_ids(self::STAFF_GROUPS);

		foreach ($forum_ids as $forum_id)
		{
			$forum = $this->get_forum_row($forum_id);
			$acl_group_names = $this->get_forum_acl_group_names($forum_id);
			$bypass_names = $this->get_forum_access_bypass_names($forum_id);

			$other_names = [];
			foreach ($acl_group_names as $group_id => $group_name)
			{
				if ($adult_group_id > 0 && $group_id === $adult_group_id)
				{
					continue;
				}

				if (in_array($group_id, $staff_group_ids, true))
				{
					continue;
				}

				if (in_array($group_name, self::SOURCE_GROUPS, true))
				{
					continue;
				}

				$other_names[] = $group_name;
			}

			$rows[] = [
				'forum_id' => $forum_id,
				'forum_name' => $forum['forum_name'] ?: '',
				'exists' => (bool) $forum['forum_id'],
				'adult_group_has_access' => ($adult_group_id > 0) ? $this->has_group_forum_read_grant($forum_id, $adult_group_id) : false,
				'blocked_group_names' => implode(', ', $bypass_names),
				'other_group_names' => implode(', ', $other_names),
			];
		}

		return $rows;
	}

	protected function gate_forum(int $forum_id): void
	{
		$adult_group_id = $this->get_adult_group_id();
		if ($adult_group_id <= 0)
		{
			throw new \RuntimeException('ADULTACCESS_SKIP_GROUP_MISSING');
		}

		if (!$this->forum_exists($forum_id))
		{
			throw new \RuntimeException('ADULTACCESS_SKIP_FORUM_MISSING');
		}

		$this->run_acl_transaction(function () use ($forum_id, $adult_group_id): void
		{
			$source_group_id = $this->resolve_gate_source_group_id($forum_id, $adult_group_id);
			if ($source_group_id <= 0)
			{
				throw new \RuntimeException('ADULTACCESS_SKIP_NO_SOURCE');
			}

			$source_rows = $this->get_group_forum_acl_rows($forum_id, $source_group_id);
			if (empty($source_rows))
			{
				throw new \RuntimeException('ADULTACCESS_SKIP_NO_SOURCE');
			}

			$has_backup = $this->forum_has_acl_backup($forum_id);
			if ($source_group_id === $adult_group_id && !$has_backup)
			{
				throw new \RuntimeException('ADULTACCESS_SKIP_NO_SOURCE');
			}

			if ($has_backup)
			{
				$this->refresh_acl_backup_from_current_acl($forum_id, $adult_group_id);
			}
			else
			{
				$this->backup_forum_acl($forum_id);
			}

			$this->ensure_adult_group_forum_access($forum_id, $adult_group_id, $source_rows);
			$this->ensure_staff_forum_access($forum_id, $source_rows);
			$this->strip_non_opt_in_access($forum_id, $adult_group_id);
		});
	}

	protected function restore_registered_access(int $forum_id): bool
	{
		if (!$this->forum_exists($forum_id))
		{
			return false;
		}

		return (bool) $this->run_acl_transaction(function () use ($forum_id): bool
		{
			if (!$this->forum_has_acl_backup($forum_id))
			{
				return false;
			}

			$this->restore_forum_acl_backup($forum_id);
			return true;
		});
	}

	protected function resolve_gate_source_group_id(int $forum_id, int $adult_group_id): int
	{
		$source_group_ids = $this->get_group_ids(self::SOURCE_GROUPS);
		$source_group_ids[] = $adult_group_id;

		foreach ($source_group_ids as $group_id)
		{
			if ($group_id > 0 && $this->has_group_forum_read_grant($forum_id, $group_id))
			{
				return $group_id;
			}
		}

		return 0;
	}

	protected function replace_group_forum_acl_from_rows(int $forum_id, int $target_group_id, array $source_rows): void
	{
		$this->delete_group_forum_acl($forum_id, [$target_group_id]);

		$sql_ary = [];
		foreach ($source_rows as $row)
		{
			$sql_ary[] = [
				'group_id' => $target_group_id,
				'forum_id' => $forum_id,
				'auth_option_id' => (int) $row['auth_option_id'],
				'auth_role_id' => (int) $row['auth_role_id'],
				'auth_setting' => (int) $row['auth_setting'],
			];
		}

		if (!empty($sql_ary))
		{
			$this->db->sql_multi_insert(ACL_GROUPS_TABLE, $sql_ary);
		}
	}

	protected function ensure_adult_group_forum_access(int $forum_id, int $adult_group_id, array $source_rows): void
	{
		if ($this->acl_rows_effectively_grant_options($source_rows, $this->get_write_auth_option_ids()))
		{
			$this->replace_group_forum_acl_from_rows($forum_id, $adult_group_id, $source_rows);
			return;
		}

		$role_id = $this->get_role_id(self::ADULT_GROUP_FORUM_ROLE);
		if ($role_id > 0)
		{
			$this->replace_group_forum_acl_with_role($forum_id, $adult_group_id, $role_id);
			return;
		}

		$this->replace_group_forum_acl_from_rows($forum_id, $adult_group_id, $source_rows);
	}

	protected function ensure_staff_forum_access(int $forum_id, array $source_rows): void
	{
		foreach (self::STAFF_GROUP_FORUM_ROLES as $group_name => $role_name)
		{
			$group_ids = $this->get_group_ids([$group_name]);
			$group_id = $group_ids[0] ?? 0;
			if ($group_id <= 0)
			{
				continue;
			}

			$current_rows = $this->get_group_forum_acl_rows($forum_id, $group_id);
			if ($this->acl_rows_effectively_grant_options($current_rows, $this->get_write_auth_option_ids()))
			{
				continue;
			}

			$role_id = $this->get_role_id($role_name);
			if ($role_id > 0)
			{
				$this->replace_group_forum_acl_with_role($forum_id, $group_id, $role_id);
				continue;
			}

			$this->replace_group_forum_acl_from_rows($forum_id, $group_id, $source_rows);
		}
	}

	protected function replace_group_forum_acl_with_role(int $forum_id, int $group_id, int $role_id): void
	{
		$this->replace_group_forum_subject_acl($forum_id, $group_id, [[
			'group_id' => $group_id,
			'forum_id' => $forum_id,
			'auth_option_id' => 0,
			'auth_role_id' => $role_id,
			'auth_setting' => ACL_NEVER,
		]]);
	}

	protected function strip_non_opt_in_access(int $forum_id, int $adult_group_id): void
	{
		$staff_group_ids = $this->get_group_ids(self::STAFF_GROUPS);
		$access_option_ids = $this->get_access_auth_option_ids();
		$group_rows = $this->get_acl_group_rows_by_subject($forum_id);

		foreach ($group_rows as $group_id => $rows)
		{
			if ($group_id === $adult_group_id || in_array($group_id, $staff_group_ids, true))
			{
				continue;
			}

			if (!$this->acl_rows_effectively_grant_options($rows, $access_option_ids))
			{
				continue;
			}

			$replacement_rows = $this->remove_access_grants_from_acl_rows($rows, 'group_id', $group_id, $forum_id);
			$this->replace_group_forum_subject_acl($forum_id, $group_id, $replacement_rows);
		}

		$user_rows = $this->get_acl_user_rows_by_subject($forum_id);
		foreach ($user_rows as $user_id => $rows)
		{
			if (!$this->acl_rows_effectively_grant_options($rows, $access_option_ids))
			{
				continue;
			}

			$replacement_rows = $this->remove_access_grants_from_acl_rows($rows, 'user_id', $user_id, $forum_id);
			$this->replace_user_forum_subject_acl($forum_id, $user_id, $replacement_rows);
		}
	}

	protected function remove_access_grants_from_acl_rows(array $rows, string $subject_field, int $subject_id, int $forum_id): array
	{
		$replacement_rows = [];
		$access_option_ids = $this->get_access_auth_option_ids();

		foreach ($rows as $row)
		{
			foreach ($this->expand_acl_row($row, $subject_field, $subject_id, $forum_id) as $expanded_row)
			{
				if (in_array((int) $expanded_row['auth_option_id'], $access_option_ids, true) && (int) $expanded_row['auth_setting'] === ACL_YES)
				{
					continue;
				}

				$replacement_rows[] = $expanded_row;
			}
		}

		return $this->deduplicate_acl_rows($replacement_rows, $subject_field);
	}

	protected function merge_current_acl_rows_with_backup_access(array $current_rows, array $backup_rows, string $subject_field, int $subject_id, int $forum_id): array
	{
		$merged_rows = [];
		$access_option_ids = $this->get_access_auth_option_ids();

		foreach ($current_rows as $row)
		{
			foreach ($this->expand_acl_row($row, $subject_field, $subject_id, $forum_id) as $expanded_row)
			{
				if (!in_array((int) $expanded_row['auth_option_id'], $access_option_ids, true))
				{
					$merged_rows[] = $expanded_row;
				}
			}
		}

		foreach ($backup_rows as $row)
		{
			foreach ($this->expand_acl_row($row, $subject_field, $subject_id, $forum_id) as $expanded_row)
			{
				if (in_array((int) $expanded_row['auth_option_id'], $access_option_ids, true))
				{
					$merged_rows[] = $expanded_row;
				}
			}
		}

		return $this->deduplicate_acl_rows($merged_rows, $subject_field);
	}

	protected function merge_current_acl_rows_into_backup(array $current_rows, array $backup_rows, string $subject_field, int $subject_id, int $forum_id): array
	{
		$merged_rows = [];
		$current_access_rows_by_option = [];
		$backup_access_rows_by_option = [];
		$access_option_ids = $this->get_access_auth_option_ids();

		foreach ($current_rows as $row)
		{
			foreach ($this->expand_acl_row($row, $subject_field, $subject_id, $forum_id) as $expanded_row)
			{
				$auth_option_id = (int) $expanded_row['auth_option_id'];
				if (in_array($auth_option_id, $access_option_ids, true))
				{
					$current_access_rows_by_option[$auth_option_id][] = $expanded_row;
					continue;
				}

				$merged_rows[] = $expanded_row;
			}
		}

		foreach ($backup_rows as $row)
		{
			foreach ($this->expand_acl_row($row, $subject_field, $subject_id, $forum_id) as $expanded_row)
			{
				$auth_option_id = (int) $expanded_row['auth_option_id'];
				if (in_array($auth_option_id, $access_option_ids, true))
				{
					$backup_access_rows_by_option[$auth_option_id][] = $expanded_row;
				}
			}
		}

		foreach ($access_option_ids as $auth_option_id)
		{
			foreach ($current_access_rows_by_option[$auth_option_id] ?? ($backup_access_rows_by_option[$auth_option_id] ?? []) as $access_row)
			{
				$merged_rows[] = $access_row;
			}
		}

		return $this->deduplicate_acl_rows($merged_rows, $subject_field);
	}

	protected function expand_acl_row(array $row, string $subject_field, int $subject_id, int $forum_id): array
	{
		if ((int) $row['auth_role_id'] <= 0)
		{
			if ((int) $row['auth_option_id'] <= 0)
			{
				return [];
			}

			return [[
				$subject_field => $subject_id,
				'forum_id' => $forum_id,
				'auth_option_id' => (int) $row['auth_option_id'],
				'auth_role_id' => 0,
				'auth_setting' => (int) $row['auth_setting'],
			]];
		}

		$rows = [];
		foreach ($this->get_role_acl_rows((int) $row['auth_role_id']) as $role_row)
		{
			$rows[] = [
				$subject_field => $subject_id,
				'forum_id' => $forum_id,
				'auth_option_id' => (int) $role_row['auth_option_id'],
				'auth_role_id' => 0,
				'auth_setting' => (int) $role_row['auth_setting'],
			];
		}

		return $rows;
	}

	protected function deduplicate_acl_rows(array $rows, string $subject_field): array
	{
		$deduplicated_rows = [];
		$seen = [];

		foreach ($rows as $row)
		{
			$key = implode(':', [
				(int) $row[$subject_field],
				(int) $row['forum_id'],
				(int) $row['auth_option_id'],
				(int) $row['auth_role_id'],
				(int) $row['auth_setting'],
			]);

			if (isset($seen[$key]))
			{
				continue;
			}

			$seen[$key] = true;
			$deduplicated_rows[] = $row;
		}

		return $deduplicated_rows;
	}

	protected function backup_forum_acl(int $forum_id): void
	{
		if ($this->forum_has_acl_backup($forum_id))
		{
			return;
		}

		$this->insert_forum_acl_backup_set($forum_id);

		$group_backup_rows = [];
		foreach ($this->get_group_forum_acl_rows($forum_id) as $row)
		{
			$group_backup_rows[] = [
				'forum_id' => $forum_id,
				'group_id' => (int) $row['group_id'],
				'auth_option_id' => (int) $row['auth_option_id'],
				'auth_role_id' => (int) $row['auth_role_id'],
				'auth_setting' => (int) $row['auth_setting'],
			];
		}

		if (!empty($group_backup_rows))
		{
			$this->db->sql_multi_insert($this->acl_group_backups_table(), $group_backup_rows);
		}

		$user_backup_rows = [];
		foreach ($this->get_user_forum_acl_rows($forum_id) as $row)
		{
			$user_backup_rows[] = [
				'forum_id' => $forum_id,
				'user_id' => (int) $row['user_id'],
				'auth_option_id' => (int) $row['auth_option_id'],
				'auth_role_id' => (int) $row['auth_role_id'],
				'auth_setting' => (int) $row['auth_setting'],
			];
		}

		if (!empty($user_backup_rows))
		{
			$this->db->sql_multi_insert($this->acl_user_backups_table(), $user_backup_rows);
		}
	}

	protected function refresh_acl_backup_from_current_acl(int $forum_id, int $adult_group_id): void
	{
		if (!$this->forum_has_acl_backup($forum_id))
		{
			return;
		}

		$staff_group_ids = $this->get_group_ids(self::STAFF_GROUPS);
		$current_group_rows = $this->get_acl_group_rows_by_subject($forum_id);
		$backup_group_rows = $this->group_acl_rows_by_subject($this->get_backup_group_acl_rows($forum_id), 'group_id');
		$group_ids = array_values(array_unique(array_merge(array_keys($current_group_rows), array_keys($backup_group_rows))));
		$new_group_backup_rows = [];

		foreach ($group_ids as $group_id)
		{
			$group_id = (int) $group_id;
			if ($group_id === $adult_group_id || in_array($group_id, $staff_group_ids, true))
			{
				continue;
			}

			foreach ($this->merge_current_acl_rows_into_backup(
				$current_group_rows[$group_id] ?? [],
				$backup_group_rows[$group_id] ?? [],
				'group_id',
				$group_id,
				$forum_id
			) as $row)
			{
				$new_group_backup_rows[] = $row;
			}
		}

		$current_user_rows = $this->get_acl_user_rows_by_subject($forum_id);
		$backup_user_rows = $this->group_acl_rows_by_subject($this->get_backup_user_acl_rows($forum_id), 'user_id');
		$user_ids = array_values(array_unique(array_merge(array_keys($current_user_rows), array_keys($backup_user_rows))));
		$new_user_backup_rows = [];

		foreach ($user_ids as $user_id)
		{
			$user_id = (int) $user_id;
			foreach ($this->merge_current_acl_rows_into_backup(
				$current_user_rows[$user_id] ?? [],
				$backup_user_rows[$user_id] ?? [],
				'user_id',
				$user_id,
				$forum_id
			) as $row)
			{
				$new_user_backup_rows[] = $row;
			}
		}

		$this->replace_forum_acl_backup_rows($forum_id, $new_group_backup_rows, $new_user_backup_rows);
	}

	protected function replace_forum_acl_backup_rows(int $forum_id, array $group_rows, array $user_rows): void
	{
		$this->db->sql_query('DELETE FROM ' . $this->acl_group_backups_table() . '
			WHERE forum_id = ' . $forum_id);
		$this->db->sql_query('DELETE FROM ' . $this->acl_user_backups_table() . '
			WHERE forum_id = ' . $forum_id);

		if (!empty($group_rows))
		{
			$this->db->sql_multi_insert($this->acl_group_backups_table(), $group_rows);
		}

		if (!empty($user_rows))
		{
			$this->db->sql_multi_insert($this->acl_user_backups_table(), $user_rows);
		}
	}

	protected function insert_forum_acl_backup_set(int $forum_id): void
	{
		$this->db->sql_query('INSERT INTO ' . $this->acl_backup_sets_table() . ' ' . $this->db->sql_build_array('INSERT', [
			'forum_id' => $forum_id,
			'backed_up_time' => time(),
		]));
	}

	protected function restore_forum_acl_backup(int $forum_id): void
	{
		$staff_group_ids = $this->get_group_ids(self::STAFF_GROUPS);
		$adult_group_id = $this->get_adult_group_id();
		$this->refresh_acl_backup_from_current_acl($forum_id, $adult_group_id);

		$backup_group_rows = array_values(array_filter($this->get_backup_group_acl_rows($forum_id), static function (array $row) use ($staff_group_ids, $adult_group_id): bool
		{
			return (int) $row['group_id'] !== $adult_group_id && !in_array((int) $row['group_id'], $staff_group_ids, true);
		}));
		if ($adult_group_id > 0)
		{
			$this->delete_group_forum_acl($forum_id, [$adult_group_id]);
		}

		$current_group_rows = $this->get_acl_group_rows_by_subject($forum_id);
		foreach ($this->group_acl_rows_by_subject($backup_group_rows, 'group_id') as $group_id => $group_rows)
		{
			$replacement_rows = $this->merge_current_acl_rows_with_backup_access(
				$current_group_rows[$group_id] ?? [],
				$group_rows,
				'group_id',
				$group_id,
				$forum_id
			);
			$this->replace_group_forum_subject_acl($forum_id, $group_id, $replacement_rows);
		}

		$current_user_rows = $this->get_acl_user_rows_by_subject($forum_id);
		foreach ($this->group_acl_rows_by_subject($this->get_backup_user_acl_rows($forum_id), 'user_id') as $user_id => $user_rows)
		{
			$replacement_rows = $this->merge_current_acl_rows_with_backup_access(
				$current_user_rows[$user_id] ?? [],
				$user_rows,
				'user_id',
				$user_id,
				$forum_id
			);
			$this->replace_user_forum_subject_acl($forum_id, $user_id, $replacement_rows);
		}

		$this->delete_forum_acl_backup($forum_id);
	}

	protected function group_acl_rows_by_subject(array $rows, string $subject_field): array
	{
		$rows_by_subject = [];
		foreach ($rows as $row)
		{
			$rows_by_subject[(int) $row[$subject_field]][] = $row;
		}

		return $rows_by_subject;
	}

	protected function forum_has_acl_backup(int $forum_id): bool
	{
		$sql = 'SELECT 1 AS has_row
			FROM ' . $this->acl_backup_sets_table() . '
			WHERE forum_id = ' . $forum_id;
		$result = $this->db->sql_query_limit($sql, 1);
		$has_backup = (bool) $this->db->sql_fetchfield('has_row');
		$this->db->sql_freeresult($result);

		return $has_backup;
	}

	protected function get_backup_group_acl_rows(int $forum_id): array
	{
		$sql = 'SELECT group_id, forum_id, auth_option_id, auth_role_id, auth_setting
			FROM ' . $this->acl_group_backups_table() . '
			WHERE forum_id = ' . $forum_id . '
			ORDER BY backup_id ASC';
		$result = $this->db->sql_query($sql);

		$rows = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = [
				'group_id' => (int) $row['group_id'],
				'forum_id' => (int) $row['forum_id'],
				'auth_option_id' => (int) $row['auth_option_id'],
				'auth_role_id' => (int) $row['auth_role_id'],
				'auth_setting' => (int) $row['auth_setting'],
			];
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	protected function get_backup_user_acl_rows(int $forum_id): array
	{
		$sql = 'SELECT user_id, forum_id, auth_option_id, auth_role_id, auth_setting
			FROM ' . $this->acl_user_backups_table() . '
			WHERE forum_id = ' . $forum_id . '
			ORDER BY backup_id ASC';
		$result = $this->db->sql_query($sql);

		$rows = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = [
				'user_id' => (int) $row['user_id'],
				'forum_id' => (int) $row['forum_id'],
				'auth_option_id' => (int) $row['auth_option_id'],
				'auth_role_id' => (int) $row['auth_role_id'],
				'auth_setting' => (int) $row['auth_setting'],
			];
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	protected function delete_forum_acl_backup(int $forum_id): void
	{
		$this->db->sql_query('DELETE FROM ' . $this->acl_group_backups_table() . '
			WHERE forum_id = ' . $forum_id);
		$this->db->sql_query('DELETE FROM ' . $this->acl_user_backups_table() . '
			WHERE forum_id = ' . $forum_id);
		$this->db->sql_query('DELETE FROM ' . $this->acl_backup_sets_table() . '
			WHERE forum_id = ' . $forum_id);
	}

	protected function acl_backup_sets_table(): string
	{
		return $this->table_prefix . 'adultaccess_acl_backup_sets';
	}

	protected function acl_group_backups_table(): string
	{
		return $this->table_prefix . 'adultaccess_acl_group_backups';
	}

	protected function acl_user_backups_table(): string
	{
		return $this->table_prefix . 'adultaccess_acl_user_backups';
	}

	protected function has_group_forum_read_grant(int $forum_id, int $group_id): bool
	{
		return $this->acl_rows_effectively_grant_options($this->get_group_forum_acl_rows($forum_id, $group_id), $this->get_read_auth_option_ids());
	}

	protected function acl_rows_effectively_grant_options(array $rows, array $auth_option_ids): bool
	{
		foreach ($auth_option_ids as $auth_option_id)
		{
			if ($this->acl_rows_have_option_setting($rows, (int) $auth_option_id, ACL_NEVER))
			{
				continue;
			}

			if ($this->acl_rows_have_option_setting($rows, (int) $auth_option_id, ACL_YES))
			{
				return true;
			}
		}

		return false;
	}

	protected function acl_rows_have_option_setting(array $rows, int $auth_option_id, int $auth_setting): bool
	{
		foreach ($rows as $row)
		{
			if ($this->acl_row_has_option_setting($row, $auth_option_id, $auth_setting))
			{
				return true;
			}
		}

		return false;
	}

	protected function acl_row_has_option_setting(array $row, int $auth_option_id, int $auth_setting): bool
	{
		if ((int) $row['auth_option_id'] === $auth_option_id && (int) $row['auth_setting'] === $auth_setting)
		{
			return true;
		}

		if ((int) $row['auth_role_id'] > 0 && in_array($auth_option_id, $this->get_role_option_ids_by_setting((int) $row['auth_role_id'], $auth_setting), true))
		{
			return true;
		}

		return false;
	}

	protected function acl_row_grants_options(array $row, array $auth_option_ids): bool
	{
		if (empty($auth_option_ids))
		{
			return false;
		}

		if ((int) $row['auth_option_id'] > 0 && (int) $row['auth_setting'] === ACL_YES && in_array((int) $row['auth_option_id'], $auth_option_ids, true))
		{
			return true;
		}

		if ((int) $row['auth_role_id'] > 0)
		{
			return (bool) array_intersect($auth_option_ids, $this->get_role_option_ids_by_setting((int) $row['auth_role_id'], ACL_YES));
		}

		return false;
	}

	protected function get_read_auth_option_ids(): array
	{
		return $this->get_auth_option_ids(self::READ_OPTIONS);
	}

	protected function get_access_auth_option_ids(): array
	{
		return $this->get_auth_option_ids(self::ACCESS_OPTIONS);
	}

	protected function get_write_auth_option_ids(): array
	{
		return $this->get_auth_option_ids(self::WRITE_OPTIONS);
	}

	protected function get_auth_option_ids(array $auth_options): array
	{
		$auth_option_ids = [];
		foreach ($auth_options as $auth_option)
		{
			$auth_option_id = $this->get_auth_option_id($auth_option);
			if ($auth_option_id > 0)
			{
				$auth_option_ids[] = $auth_option_id;
			}
		}

		return $auth_option_ids;
	}

	protected function get_auth_option_id(string $auth_option): int
	{
		if (array_key_exists($auth_option, $this->auth_option_id_cache))
		{
			return $this->auth_option_id_cache[$auth_option];
		}

		$sql = 'SELECT auth_option_id
			FROM ' . ACL_OPTIONS_TABLE . "
			WHERE auth_option = '" . $this->db->sql_escape($auth_option) . "'";
		$result = $this->db->sql_query_limit($sql, 1);
		$auth_option_id = (int) $this->db->sql_fetchfield('auth_option_id');
		$this->db->sql_freeresult($result);

		$this->auth_option_id_cache[$auth_option] = $auth_option_id;

		return $auth_option_id;
	}

	protected function get_role_option_ids_by_setting(int $role_id, int $auth_setting): array
	{
		$cache_key = $role_id . ':' . $auth_setting;
		if (array_key_exists($cache_key, $this->role_option_setting_cache))
		{
			return $this->role_option_setting_cache[$cache_key];
		}

		$auth_option_ids = [];
		foreach ($this->get_role_acl_rows($role_id) as $row)
		{
			if ((int) $row['auth_setting'] === $auth_setting)
			{
				$auth_option_ids[] = (int) $row['auth_option_id'];
			}
		}

		$this->role_option_setting_cache[$cache_key] = $auth_option_ids;

		return $auth_option_ids;
	}

	protected function get_role_acl_rows(int $role_id): array
	{
		if (array_key_exists($role_id, $this->role_acl_rows_cache))
		{
			return $this->role_acl_rows_cache[$role_id];
		}

		$sql = 'SELECT auth_option_id, auth_setting
			FROM ' . ACL_ROLES_DATA_TABLE . '
			WHERE role_id = ' . $role_id . '
			ORDER BY auth_option_id ASC';
		$result = $this->db->sql_query($sql);

		$rows = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = [
				'auth_option_id' => (int) $row['auth_option_id'],
				'auth_setting' => (int) $row['auth_setting'],
			];
		}
		$this->db->sql_freeresult($result);

		$this->role_acl_rows_cache[$role_id] = $rows;

		return $rows;
	}

	protected function get_forum_access_bypass_names(int $forum_id): array
	{
		$adult_group_id = $this->get_adult_group_id();
		$staff_group_ids = $this->get_group_ids(self::STAFF_GROUPS);
		$access_option_ids = $this->get_access_auth_option_ids();
		$bypass_names = [];

		foreach ($this->get_acl_group_rows_by_subject($forum_id) as $group_id => $rows)
		{
			if (($adult_group_id > 0 && $group_id === $adult_group_id) || in_array($group_id, $staff_group_ids, true))
			{
				continue;
			}

			if ($this->acl_rows_effectively_grant_options($rows, $access_option_ids))
			{
				$bypass_names[] = $this->get_group_name_by_id($group_id);
			}
		}

		foreach ($this->get_acl_user_rows_by_subject($forum_id) as $user_id => $rows)
		{
			if ($this->acl_rows_effectively_grant_options($rows, $access_option_ids))
			{
				$username = $this->get_username_by_id($user_id);
				$bypass_names[] = $username !== '' ? 'user: ' . $username : 'user #' . $user_id;
			}
		}

		sort($bypass_names);

		return $bypass_names;
	}

	protected function get_acl_group_rows_by_subject(int $forum_id): array
	{
		$rows_by_group = [];
		foreach ($this->get_group_forum_acl_rows($forum_id) as $row)
		{
			$rows_by_group[(int) $row['group_id']][] = $row;
		}

		return $rows_by_group;
	}

	protected function get_acl_user_rows_by_subject(int $forum_id): array
	{
		$rows_by_user = [];
		foreach ($this->get_user_forum_acl_rows($forum_id) as $row)
		{
			$rows_by_user[(int) $row['user_id']][] = $row;
		}

		return $rows_by_user;
	}

	protected function replace_group_forum_subject_acl(int $forum_id, int $group_id, array $rows): void
	{
		$this->delete_group_forum_acl($forum_id, [$group_id]);

		$sql_ary = [];
		foreach ($rows as $row)
		{
			$sql_ary[] = [
				'group_id' => $group_id,
				'forum_id' => $forum_id,
				'auth_option_id' => (int) $row['auth_option_id'],
				'auth_role_id' => (int) $row['auth_role_id'],
				'auth_setting' => (int) $row['auth_setting'],
			];
		}

		if (!empty($sql_ary))
		{
			$this->db->sql_multi_insert(ACL_GROUPS_TABLE, $sql_ary);
		}
	}

	protected function replace_user_forum_subject_acl(int $forum_id, int $user_id, array $rows): void
	{
		$this->delete_user_forum_acl($forum_id, [$user_id]);

		$sql_ary = [];
		foreach ($rows as $row)
		{
			$sql_ary[] = [
				'user_id' => $user_id,
				'forum_id' => $forum_id,
				'auth_option_id' => (int) $row['auth_option_id'],
				'auth_role_id' => (int) $row['auth_role_id'],
				'auth_setting' => (int) $row['auth_setting'],
			];
		}

		if (!empty($sql_ary))
		{
			$this->db->sql_multi_insert(ACL_USERS_TABLE, $sql_ary);
		}
	}

	protected function delete_group_forum_acl(int $forum_id, array $group_ids): void
	{
		$group_ids = array_values(array_unique(array_filter(array_map('intval', $group_ids))));
		if (empty($group_ids))
		{
			return;
		}

		$sql = 'DELETE FROM ' . ACL_GROUPS_TABLE . '
			WHERE forum_id = ' . $forum_id . '
				AND ' . $this->db->sql_in_set('group_id', $group_ids);
		$this->db->sql_query($sql);
	}

	protected function delete_user_forum_acl(int $forum_id, array $user_ids): void
	{
		$user_ids = array_values(array_unique(array_filter(array_map('intval', $user_ids))));
		if (empty($user_ids))
		{
			return;
		}

		$sql = 'DELETE FROM ' . ACL_USERS_TABLE . '
			WHERE forum_id = ' . $forum_id . '
				AND ' . $this->db->sql_in_set('user_id', $user_ids);
		$this->db->sql_query($sql);
	}

	protected function get_group_forum_acl_rows(int $forum_id, int $group_id = 0): array
	{
		$sql = 'SELECT group_id, forum_id, auth_option_id, auth_role_id, auth_setting
			FROM ' . ACL_GROUPS_TABLE . '
			WHERE forum_id = ' . $forum_id;
		if ($group_id > 0)
		{
			$sql .= ' AND group_id = ' . $group_id;
		}
		$result = $this->db->sql_query($sql);

		$rows = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = [
				'group_id' => (int) $row['group_id'],
				'forum_id' => (int) $row['forum_id'],
				'auth_option_id' => (int) $row['auth_option_id'],
				'auth_role_id' => (int) $row['auth_role_id'],
				'auth_setting' => (int) $row['auth_setting'],
			];
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	protected function get_user_forum_acl_rows(int $forum_id): array
	{
		$sql = 'SELECT user_id, forum_id, auth_option_id, auth_role_id, auth_setting
			FROM ' . ACL_USERS_TABLE . '
			WHERE forum_id = ' . $forum_id;
		$result = $this->db->sql_query($sql);

		$rows = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = [
				'user_id' => (int) $row['user_id'],
				'forum_id' => (int) $row['forum_id'],
				'auth_option_id' => (int) $row['auth_option_id'],
				'auth_role_id' => (int) $row['auth_role_id'],
				'auth_setting' => (int) $row['auth_setting'],
			];
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	protected function get_forum_acl_group_names(int $forum_id): array
	{
		$sql = 'SELECT DISTINCT g.group_id, g.group_name
			FROM ' . ACL_GROUPS_TABLE . ' a
			INNER JOIN ' . GROUPS_TABLE . ' g
				ON g.group_id = a.group_id
			WHERE a.forum_id = ' . $forum_id . '
			ORDER BY g.group_name ASC';
		$result = $this->db->sql_query($sql);

		$group_names = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$group_names[(int) $row['group_id']] = (string) $row['group_name'];
		}
		$this->db->sql_freeresult($result);

		return $group_names;
	}

	protected function get_forum_row(int $forum_id): array
	{
		$sql = 'SELECT forum_id, forum_name
			FROM ' . FORUMS_TABLE . '
			WHERE forum_id = ' . $forum_id . '
				AND forum_type = ' . FORUM_POST;
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result) ?: [];
		$this->db->sql_freeresult($result);

		return [
			'forum_id' => (int) ($row['forum_id'] ?? 0),
			'forum_name' => (string) ($row['forum_name'] ?? ''),
		];
	}

	protected function forum_exists(int $forum_id): bool
	{
		return $this->get_forum_row($forum_id)['forum_id'] > 0;
	}

	protected function get_topic_ids_for_forums(array $forum_ids): array
	{
		$sql = 'SELECT topic_id
			FROM ' . TOPICS_TABLE . '
			WHERE ' . $this->db->sql_in_set('forum_id', $forum_ids);
		$result = $this->db->sql_query($sql);

		$topic_ids = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$topic_ids[] = (int) $row['topic_id'];
		}
		$this->db->sql_freeresult($result);

		return $topic_ids;
	}

	protected function normalize_forum_ids(array $forum_ids): array
	{
		$forum_ids = array_values(array_unique(array_filter(array_map('intval', $forum_ids), static function (int $forum_id): bool
		{
			return $forum_id > 0;
		})));
		sort($forum_ids);

		return $forum_ids;
	}

	protected function get_group_ids(array $group_names): array
	{
		$missing_names = [];
		foreach ($group_names as $group_name)
		{
			if (!array_key_exists($group_name, $this->group_id_cache))
			{
				$missing_names[] = $group_name;
			}
		}

		if (!empty($missing_names))
		{
			$sql = 'SELECT group_id, group_name
				FROM ' . GROUPS_TABLE . '
				WHERE ' . $this->db->sql_in_set('group_name', $missing_names);
			$result = $this->db->sql_query($sql);
			while ($row = $this->db->sql_fetchrow($result))
			{
				$this->group_id_cache[(string) $row['group_name']] = (int) $row['group_id'];
			}
			$this->db->sql_freeresult($result);

			foreach ($missing_names as $group_name)
			{
				$this->group_id_cache[$group_name] = $this->group_id_cache[$group_name] ?? 0;
			}
		}

		$group_ids = [];
		foreach ($group_names as $group_name)
		{
			$group_id = (int) ($this->group_id_cache[$group_name] ?? 0);
			if ($group_id > 0)
			{
				$group_ids[] = $group_id;
			}
		}

		return $group_ids;
	}

	protected function get_group_name_by_id(int $group_id): string
	{
		$sql = 'SELECT group_name
			FROM ' . GROUPS_TABLE . '
			WHERE group_id = ' . $group_id;
		$result = $this->db->sql_query_limit($sql, 1);
		$group_name = (string) $this->db->sql_fetchfield('group_name');
		$this->db->sql_freeresult($result);

		return $group_name !== '' ? $group_name : '#' . $group_id;
	}

	protected function get_username_by_id(int $user_id): string
	{
		$sql = 'SELECT username
			FROM ' . USERS_TABLE . '
			WHERE user_id = ' . $user_id;
		$result = $this->db->sql_query_limit($sql, 1);
		$username = (string) $this->db->sql_fetchfield('username');
		$this->db->sql_freeresult($result);

		return $username;
	}

	protected function get_role_id(string $role_name): int
	{
		if (array_key_exists($role_name, $this->role_id_cache))
		{
			return $this->role_id_cache[$role_name];
		}

		$sql = 'SELECT role_id
			FROM ' . ACL_ROLES_TABLE . "
			WHERE role_name = '" . $this->db->sql_escape($role_name) . "'
				AND role_type = 'f_'";
		$result = $this->db->sql_query_limit($sql, 1);
		$role_id = (int) $this->db->sql_fetchfield('role_id');
		$this->db->sql_freeresult($result);

		$this->role_id_cache[$role_name] = $role_id;

		return $role_id;
	}

	protected function group_exists(int $group_id): bool
	{
		$sql = 'SELECT 1 AS has_row
			FROM ' . GROUPS_TABLE . '
			WHERE group_id = ' . $group_id;
		$result = $this->db->sql_query_limit($sql, 1);
		$exists = (bool) $this->db->sql_fetchfield('has_row');
		$this->db->sql_freeresult($result);

		return $exists;
	}

	protected function run_acl_transaction(callable $callback)
	{
		$this->db->sql_transaction('begin');

		try
		{
			$result = $callback();
			$this->db->sql_transaction('commit');
			return $result;
		}
		catch (\Throwable $e)
		{
			$this->db->sql_transaction('rollback');
			throw $e;
		}
	}

	protected function find_managed_group_id(): int
	{
		$sql = 'SELECT group_id
			FROM ' . GROUPS_TABLE . "
			WHERE group_name = '" . $this->db->sql_escape(self::GROUP_NAME) . "'
				AND group_desc = '" . $this->db->sql_escape(self::GROUP_DESC) . "'
				AND group_type = " . GROUP_HIDDEN;
		$result = $this->db->sql_query_limit($sql, 1);
		$group_id = (int) $this->db->sql_fetchfield('group_id');
		$this->db->sql_freeresult($result);

		return $group_id;
	}

	protected function ensure_user_functions_loaded(): void
	{
		if (!function_exists('group_user_add') || !function_exists('group_user_del'))
		{
			include_once($this->phpbb_root_path . 'includes/functions_user.' . $this->php_ext);
		}
	}

	protected function clear_acl_cache(): void
	{
		if (!class_exists('auth_admin'))
		{
			include_once($this->phpbb_root_path . 'includes/acp/auth.' . $this->php_ext);
		}

		$auth_admin = new \auth_admin();
		$auth_admin->acl_clear_prefetch();
	}
}
