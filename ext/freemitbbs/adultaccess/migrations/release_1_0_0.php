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
					'module_mode' => ['main'],
					'module_auth' => 'ext_freemitbbs/adultaccess && acl_a_board',
				],
			]],
			['module.add', [
				'ucp',
				'UCP_PREFS',
				[
					'module_langname' => 'UCP_ADULTACCESS',
					'module_basename' => '\freemitbbs\adultaccess\ucp\main_module',
					'module_mode' => ['settings'],
					'module_auth' => 'ext_freemitbbs/adultaccess',
				],
			]],
		];
	}

	public function revert_data()
	{
		return [
			['custom', [[$this, 'remove_opt_in_group']]],
		];
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
}
