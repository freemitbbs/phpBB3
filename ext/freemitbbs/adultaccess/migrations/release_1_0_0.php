<?php

namespace freemitbbs\adultaccess\migrations;

class release_1_0_0 extends \phpbb\db\migration\migration
{
	private const GROUP_NAME = '18+ Opt-In';
	private const GROUP_DESC = 'Managed by freemitbbs/adultaccess';

	public static function depends_on()
	{
		return [
			'\phpbb\db\migration\data\v33x\v3310',
		];
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'users' => [
					'user_adult_opt_in_time' => ['TIMESTAMP', 0],
				],
			],
			'add_tables' => [
				$this->table_prefix . 'adultaccess_acl_backup_sets' => [
					'COLUMNS' => [
						'forum_id' => ['UINT:8', 0],
						'backed_up_time' => ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => 'forum_id',
				],
				$this->table_prefix . 'adultaccess_acl_group_backups' => [
					'COLUMNS' => [
						'backup_id' => ['UINT', null, 'auto_increment'],
						'forum_id' => ['UINT:8', 0],
						'group_id' => ['UINT', 0],
						'auth_option_id' => ['UINT', 0],
						'auth_role_id' => ['UINT', 0],
						'auth_setting' => ['TINT:2', 0],
					],
					'PRIMARY_KEY' => 'backup_id',
					'KEYS' => [
						'forum_id' => ['INDEX', 'forum_id'],
						'group_forum' => ['INDEX', ['group_id', 'forum_id']],
					],
				],
				$this->table_prefix . 'adultaccess_acl_user_backups' => [
					'COLUMNS' => [
						'backup_id' => ['UINT', null, 'auto_increment'],
						'forum_id' => ['UINT:8', 0],
						'user_id' => ['UINT', 0],
						'auth_option_id' => ['UINT', 0],
						'auth_role_id' => ['UINT', 0],
						'auth_setting' => ['TINT:2', 0],
					],
					'PRIMARY_KEY' => 'backup_id',
					'KEYS' => [
						'forum_id' => ['INDEX', 'forum_id'],
						'user_forum' => ['INDEX', ['user_id', 'forum_id']],
					],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'adultaccess_acl_user_backups',
				$this->table_prefix . 'adultaccess_acl_group_backups',
				$this->table_prefix . 'adultaccess_acl_backup_sets',
			],
			'drop_columns' => [
				$this->table_prefix . 'users' => [
					'user_adult_opt_in_time',
				],
			],
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'remove_extension_modules']]],
			['config.add', ['freemitbbs_adultaccess_version', '1.0.3']],
			['config.add', ['freemitbbs_adult_group_id', 0]],
			['config.add', ['freemitbbs_adult_forum_ids', '']],
			['custom', [[$this, 'ensure_opt_in_group']]],
			['module.add', [
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_ADULTACCESS_GRP',
			]],
			['module.add', [
				'acp',
				'ACP_ADULTACCESS_GRP',
				[
					'module_langname' => 'ACP_ADULTACCESS',
					'module_basename' => '\freemitbbs\adultaccess\acp\acp_adultaccess_module',
					'module_mode' => 'main',
					'module_auth' => 'ext_freemitbbs/adultaccess && acl_a_board',
				],
			]],
			['module.add', [
				'ucp',
				'UCP_PREFS',
				[
					'module_langname' => 'UCP_ADULTACCESS',
					'module_basename' => '\freemitbbs\adultaccess\ucp\main_module',
					'module_mode' => 'settings',
					'module_auth' => 'ext_freemitbbs/adultaccess',
				],
			]],
		];
	}

	public function revert_data()
	{
		return [
			['custom', [[$this, 'restore_all_forum_permissions']]],
			['custom', [[$this, 'remove_extension_modules']]],
			['custom', [[$this, 'remove_opt_in_group']]],
			['config.remove', ['freemitbbs_adult_forum_ids']],
			['config.remove', ['freemitbbs_adult_group_id']],
			['config.remove', ['freemitbbs_adultaccess_version']],
			['custom', [[$this, 'clear_acl_cache']]],
		];
	}

	public function restore_all_forum_permissions(): void
	{
		$sql = 'SELECT forum_id
			FROM ' . $this->acl_backup_sets_table() . '
			ORDER BY forum_id ASC';
		$result = $this->db->sql_query($sql);

		$forum_ids = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$forum_ids[] = (int) $row['forum_id'];
		}
		$this->db->sql_freeresult($result);

		if (empty($forum_ids))
		{
			return;
		}

		$this->db->sql_transaction('begin');
		try
		{
			foreach ($forum_ids as $forum_id)
			{
				$this->restore_forum_acl_backup($forum_id);
			}
			$this->db->sql_transaction('commit');
		}
		catch (\Throwable $e)
		{
			$this->db->sql_transaction('rollback');
			throw $e;
		}
	}

	protected function restore_forum_acl_backup(int $forum_id): void
	{
		$backup_group_rows = $this->get_backup_group_acl_rows($forum_id);
		$backup_user_rows = $this->get_backup_user_acl_rows($forum_id);

		$this->db->sql_query('DELETE FROM ' . ACL_GROUPS_TABLE . '
			WHERE forum_id = ' . $forum_id);
		$this->db->sql_query('DELETE FROM ' . ACL_USERS_TABLE . '
			WHERE forum_id = ' . $forum_id);

		if (!empty($backup_group_rows))
		{
			$this->db->sql_multi_insert(ACL_GROUPS_TABLE, $backup_group_rows);
		}

		if (!empty($backup_user_rows))
		{
			$this->db->sql_multi_insert(ACL_USERS_TABLE, $backup_user_rows);
		}

		$this->delete_forum_acl_backup($forum_id);
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

	public function remove_extension_modules(): void
	{
		$this->remove_modules_by_langname('ucp', 'UCP_ADULTACCESS');
		$this->remove_modules_by_langname('acp', 'ACP_ADULTACCESS');
		$this->remove_modules_by_langname('acp', 'ACP_ADULTACCESS_GRP');
	}

	protected function remove_modules_by_langname(string $module_class, string $module_langname): void
	{
		global $phpbb_container;

		$sql = 'SELECT module_id
			FROM ' . MODULES_TABLE . "
			WHERE module_class = '" . $this->db->sql_escape($module_class) . "'
				AND module_langname = '" . $this->db->sql_escape($module_langname) . "'
			ORDER BY left_id DESC";
		$result = $this->db->sql_query($sql);

		$module_ids = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$module_ids[] = (int) $row['module_id'];
		}
		$this->db->sql_freeresult($result);

		if (empty($module_ids))
		{
			return;
		}

		$module_manager = $phpbb_container->get('module.manager');
		foreach ($module_ids as $module_id)
		{
			try
			{
				$module_manager->delete_module($module_id, $module_class);
			}
			catch (\phpbb\module\exception\module_exception $e)
			{
				$this->db->sql_query('DELETE FROM ' . MODULES_TABLE . '
					WHERE module_id = ' . $module_id . "
						AND module_class = '" . $this->db->sql_escape($module_class) . "'");
			}
		}

		$module_manager->remove_cache_file($module_class);
	}

	public function ensure_opt_in_group(): void
	{
		$group_id = $this->find_managed_group_id();
		if ($group_id <= 0)
		{
			$sql_ary = [
				'group_name' => self::GROUP_NAME,
				'group_desc' => self::GROUP_DESC,
				'group_desc_uid' => '',
				'group_desc_bitfield' => '',
				'group_type' => GROUP_HIDDEN,
				'group_colour' => '',
				'group_legend' => 0,
				'group_founder_manage' => 0,
				'group_skip_auth' => 0,
			];

			$sql = 'INSERT INTO ' . GROUPS_TABLE . ' ' . $this->db->sql_build_array('INSERT', $sql_ary);
			$this->db->sql_query($sql);
			$group_id = (int) $this->db->sql_nextid();
		}

		$this->config->set('freemitbbs_adult_group_id', (string) $group_id);
	}

	public function remove_opt_in_group(): void
	{
		$group_id = $this->find_managed_group_id();
		if ($group_id <= 0)
		{
			return;
		}

		$registered_group_id = $this->find_group_id_by_name('REGISTERED');
		if ($registered_group_id > 0)
		{
			$sql = 'UPDATE ' . USERS_TABLE . '
				SET group_id = ' . $registered_group_id . '
				WHERE group_id = ' . $group_id;
			$this->db->sql_query($sql);
		}

		$this->db->sql_query('DELETE FROM ' . ACL_GROUPS_TABLE . '
			WHERE group_id = ' . $group_id);
		$this->db->sql_query('DELETE FROM ' . USER_GROUP_TABLE . '
			WHERE group_id = ' . $group_id);
		$this->db->sql_query('DELETE FROM ' . GROUPS_TABLE . '
			WHERE group_id = ' . $group_id);
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

	protected function find_group_id_by_name(string $group_name): int
	{
		$sql = 'SELECT group_id
			FROM ' . GROUPS_TABLE . "
			WHERE group_name = '" . $this->db->sql_escape($group_name) . "'";
		$result = $this->db->sql_query_limit($sql, 1);
		$group_id = (int) $this->db->sql_fetchfield('group_id');
		$this->db->sql_freeresult($result);

		return $group_id;
	}

	public function clear_acl_cache(): void
	{
		if (!class_exists('auth_admin'))
		{
			include_once($this->phpbb_root_path . 'includes/acp/auth.' . $this->php_ext);
		}

		$auth_admin = new \auth_admin();
		$auth_admin->acl_clear_prefetch();
	}
}
