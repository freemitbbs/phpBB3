<?php

namespace freemitbbs\newsscraper\migrations;

class release_1_0_1 extends \phpbb\db\migration\migration
{
	private const DIGEST_FORUM_NAME = '新闻摘要';
	private const DIGEST_FORUM_DESC = 'AI 生成的新闻摘要。';

	public static function depends_on()
	{
		return [
			'\freemitbbs\newsscraper\migrations\release_1_0_0',
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'ensure_digest_forum']]],
			['config.update', ['newsscraper_version', '1.0.1']],
		];
	}

	public function ensure_digest_forum(): void
	{
		$forum_id = $this->get_configured_forum_id();

		if ($forum_id > 0 && $this->forum_exists($forum_id) && !$this->is_managed_digest_forum($forum_id))
		{
			$forum_id = 0;
		}

		if ($forum_id <= 0 || !$this->forum_exists($forum_id))
		{
			$forum_id = $this->find_forum_by_name(self::DIGEST_FORUM_NAME);
		}

		if ($forum_id <= 0 || !$this->forum_exists($forum_id))
		{
			$forum_id = $this->create_digest_forum();
		}

		$this->normalise_digest_forum($forum_id);
		$this->set_config('newsscraper_digest_forum_id', (string) $forum_id);
		$this->set_config('newsscraper_digest_forum_managed', (string) $forum_id);
		$this->set_digest_forum_permissions($forum_id);
		$this->clear_acl_cache();
	}

	protected function get_configured_forum_id(): int
	{
		return (int) $this->get_config_value('newsscraper_digest_forum_id');
	}

	protected function get_config_value(string $config_name): string
	{
		$sql = 'SELECT config_value
			FROM ' . CONFIG_TABLE . "
			WHERE config_name = '" . $this->db->sql_escape($config_name) . "'";
		$result = $this->db->sql_query($sql);
		$config_value = (string) $this->db->sql_fetchfield('config_value');
		$this->db->sql_freeresult($result);

		return $config_value;
	}

	protected function is_managed_digest_forum(int $forum_id): bool
	{
		return $forum_id > 0
			&& (int) $this->get_config_value('newsscraper_digest_forum_managed') === $forum_id;
	}

	protected function forum_exists(int $forum_id): bool
	{
		$sql = 'SELECT forum_id
			FROM ' . FORUMS_TABLE . '
			WHERE forum_id = ' . (int) $forum_id . '
				AND forum_type = ' . FORUM_POST;
		$result = $this->db->sql_query_limit($sql, 1);
		$exists = (bool) $this->db->sql_fetchfield('forum_id');
		$this->db->sql_freeresult($result);

		return $exists;
	}

	protected function find_forum_by_name(string $forum_name): int
	{
		$sql = 'SELECT forum_id
			FROM ' . FORUMS_TABLE . "
			WHERE forum_name = '" . $this->db->sql_escape($forum_name) . "'
				AND forum_type = " . FORUM_POST;
		$result = $this->db->sql_query_limit($sql, 1);
		$forum_id = (int) $this->db->sql_fetchfield('forum_id');
		$this->db->sql_freeresult($result);

		return $forum_id;
	}

	protected function create_digest_forum(): int
	{
		$sql = 'SELECT MAX(right_id) AS right_id
			FROM ' . FORUMS_TABLE;
		$result = $this->db->sql_query($sql);
		$right_id = (int) $this->db->sql_fetchfield('right_id');
		$this->db->sql_freeresult($result);

		$forum_options = 1 << FORUM_OPTION_FEED_EXCLUDE;

		$sql_ary = [
			'parent_id' => 0,
			'left_id' => $right_id + 1,
			'right_id' => $right_id + 2,
			'forum_parents' => '',
			'forum_name' => self::DIGEST_FORUM_NAME,
			'forum_desc' => self::DIGEST_FORUM_DESC,
			'forum_desc_bitfield' => '',
			'forum_desc_options' => 7,
			'forum_desc_uid' => '',
			'forum_link' => '',
			'forum_password' => '',
			'forum_style' => 0,
			'forum_image' => '',
			'forum_rules' => '',
			'forum_rules_link' => '',
			'forum_rules_bitfield' => '',
			'forum_rules_options' => 7,
			'forum_rules_uid' => '',
			'forum_topics_per_page' => 0,
			'forum_type' => FORUM_POST,
			'forum_status' => ITEM_UNLOCKED,
			'forum_last_post_id' => 0,
			'forum_last_poster_id' => 0,
			'forum_last_post_subject' => '',
			'forum_last_post_time' => 0,
			'forum_last_poster_name' => '',
			'forum_last_poster_colour' => '',
			'forum_flags' => FORUM_FLAG_POST_REVIEW,
			'display_on_index' => 0,
			'enable_indexing' => 1,
			'enable_icons' => 0,
			'enable_prune' => 0,
			'prune_next' => 0,
			'prune_days' => 7,
			'prune_viewed' => 7,
			'prune_freq' => 1,
			'display_subforum_list' => 0,
			'display_subforum_limit' => 0,
			'forum_options' => $forum_options,
			'enable_shadow_prune' => 0,
			'prune_shadow_days' => 7,
			'prune_shadow_freq' => 1,
			'prune_shadow_next' => 0,
			'forum_posts_approved' => 0,
			'forum_posts_unapproved' => 0,
			'forum_posts_softdeleted' => 0,
			'forum_topics_approved' => 0,
			'forum_topics_unapproved' => 0,
			'forum_topics_softdeleted' => 0,
		];

		$this->db->sql_query('INSERT INTO ' . FORUMS_TABLE . ' ' . $this->db->sql_build_array('INSERT', $sql_ary));

		return (int) $this->db->sql_nextid();
	}

	protected function normalise_digest_forum(int $forum_id): void
	{
		$forum = $this->get_forum_row($forum_id);
		$feed_exclude = 1 << FORUM_OPTION_FEED_EXCLUDE;
		$forum_options = ((int) $forum['forum_options']) | $feed_exclude;
		$forum_flags = (((int) $forum['forum_flags']) | FORUM_FLAG_POST_REVIEW) & ~FORUM_FLAG_ACTIVE_TOPICS;

		$sql_ary = [
			'forum_name' => self::DIGEST_FORUM_NAME,
			'forum_desc' => self::DIGEST_FORUM_DESC,
			'forum_type' => FORUM_POST,
			'forum_status' => ITEM_UNLOCKED,
			'forum_flags' => $forum_flags,
			'display_on_index' => 0,
			'display_subforum_list' => 0,
			'enable_indexing' => 1,
			'enable_icons' => 0,
			'forum_options' => $forum_options,
		];

		$this->db->sql_query('UPDATE ' . FORUMS_TABLE . '
			SET ' . $this->db->sql_build_array('UPDATE', $sql_ary) . '
			WHERE forum_id = ' . (int) $forum_id);
	}

	protected function get_forum_row(int $forum_id): array
	{
		$sql = 'SELECT forum_id, forum_flags, forum_options
			FROM ' . FORUMS_TABLE . '
			WHERE forum_id = ' . (int) $forum_id;
		$result = $this->db->sql_query_limit($sql, 1);
		$forum = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $forum ?: [
			'forum_id' => 0,
			'forum_flags' => 0,
			'forum_options' => 0,
		];
	}

	protected function set_digest_forum_permissions(int $forum_id): void
	{
		$groups = $this->get_group_ids([
			'GUESTS',
			'REGISTERED',
			'REGISTERED_COPPA',
			'GLOBAL_MODERATORS',
			'ADMINISTRATORS',
			'BOTS',
			'NEWLY_REGISTERED',
		]);
		$roles = $this->get_role_ids([
			'ROLE_FORUM_READONLY',
			'ROLE_FORUM_FULL',
			'ROLE_FORUM_BOT',
		]);
		$f_list_id = $this->get_auth_option_id('f_list');

		$this->db->sql_query('DELETE FROM ' . ACL_GROUPS_TABLE . '
			WHERE forum_id = ' . (int) $forum_id);
		$this->db->sql_query('DELETE FROM ' . ACL_USERS_TABLE . '
			WHERE forum_id = ' . (int) $forum_id);

		$role_rows = [];
		$this->add_group_role($role_rows, $groups, $roles, 'GUESTS', ['ROLE_FORUM_READONLY'], $forum_id);
		$this->add_group_role($role_rows, $groups, $roles, 'REGISTERED', ['ROLE_FORUM_READONLY'], $forum_id);
		$this->add_group_role($role_rows, $groups, $roles, 'REGISTERED_COPPA', ['ROLE_FORUM_READONLY'], $forum_id);
		$this->add_group_role($role_rows, $groups, $roles, 'GLOBAL_MODERATORS', ['ROLE_FORUM_READONLY'], $forum_id);
		$this->add_group_role($role_rows, $groups, $roles, 'ADMINISTRATORS', ['ROLE_FORUM_FULL'], $forum_id);
		$this->add_group_role($role_rows, $groups, $roles, 'BOTS', ['ROLE_FORUM_BOT', 'ROLE_FORUM_READONLY'], $forum_id);
		$this->add_group_role($role_rows, $groups, $roles, 'NEWLY_REGISTERED', ['ROLE_FORUM_READONLY'], $forum_id);

		if (!empty($role_rows))
		{
			$this->db->sql_multi_insert(ACL_GROUPS_TABLE, $role_rows);
		}

		if ($f_list_id > 0 && !empty($role_rows))
		{
			$deny_rows = [];
			foreach ($role_rows as $row)
			{
				$deny_rows[] = [
					'group_id' => $row['group_id'],
					'forum_id' => $forum_id,
					'auth_option_id' => $f_list_id,
					'auth_role_id' => 0,
					'auth_setting' => ACL_NEVER,
				];
			}

			$this->db->sql_multi_insert(ACL_GROUPS_TABLE, $deny_rows);
		}
	}

	protected function add_group_role(array &$rows, array $groups, array $roles, string $group_name, array $role_names, int $forum_id): void
	{
		if (!isset($groups[$group_name]))
		{
			return;
		}

		foreach ($role_names as $role_name)
		{
			if (!isset($roles[$role_name]))
			{
				continue;
			}

			$rows[] = [
				'group_id' => $groups[$group_name],
				'forum_id' => $forum_id,
				'auth_option_id' => 0,
				'auth_role_id' => $roles[$role_name],
				'auth_setting' => 0,
			];
			return;
		}
	}

	protected function get_group_ids(array $group_names): array
	{
		$sql = 'SELECT group_id, group_name
			FROM ' . GROUPS_TABLE . '
			WHERE ' . $this->db->sql_in_set('group_name', $group_names);
		$result = $this->db->sql_query($sql);

		$groups = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$groups[$row['group_name']] = (int) $row['group_id'];
		}
		$this->db->sql_freeresult($result);

		return $groups;
	}

	protected function get_role_ids(array $role_names): array
	{
		$sql = 'SELECT role_id, role_name
			FROM ' . ACL_ROLES_TABLE . '
			WHERE ' . $this->db->sql_in_set('role_name', $role_names);
		$result = $this->db->sql_query($sql);

		$roles = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$roles[$row['role_name']] = (int) $row['role_id'];
		}
		$this->db->sql_freeresult($result);

		return $roles;
	}

	protected function get_auth_option_id(string $auth_option): int
	{
		$sql = 'SELECT auth_option_id
			FROM ' . ACL_OPTIONS_TABLE . "
			WHERE auth_option = '" . $this->db->sql_escape($auth_option) . "'";
		$result = $this->db->sql_query_limit($sql, 1);
		$auth_option_id = (int) $this->db->sql_fetchfield('auth_option_id');
		$this->db->sql_freeresult($result);

		return $auth_option_id;
	}

	protected function set_config(string $config_name, string $config_value): void
	{
		$sql = 'UPDATE ' . CONFIG_TABLE . "
			SET config_value = '" . $this->db->sql_escape($config_value) . "'
			WHERE config_name = '" . $this->db->sql_escape($config_name) . "'";
		$this->db->sql_query($sql);

		if ($this->db->sql_affectedrows() === 0)
		{
			$sql_ary = [
				'config_name' => $config_name,
				'config_value' => $config_value,
				'is_dynamic' => 0,
			];
			$this->db->sql_query('INSERT INTO ' . CONFIG_TABLE . ' ' . $this->db->sql_build_array('INSERT', $sql_ary));
		}
	}

	protected function clear_acl_cache(): void
	{
		global $auth, $cache;

		if (isset($auth) && is_object($auth) && method_exists($auth, 'acl_clear_prefetch'))
		{
			$auth->acl_clear_prefetch();
		}
		else
		{
			$this->db->sql_query('UPDATE ' . USERS_TABLE . "
				SET user_permissions = ''");
		}

		if (isset($cache) && is_object($cache) && method_exists($cache, 'destroy'))
		{
			$cache->destroy('_role_cache');
		}
	}
}
