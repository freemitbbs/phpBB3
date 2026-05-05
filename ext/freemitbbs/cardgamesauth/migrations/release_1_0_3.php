<?php

namespace freemitbbs\cardgamesauth\migrations;

class release_1_0_3 extends \phpbb\db\migration\migration
{
	private const TESTER_GROUP_NAME = 'CARD_GAME_TESTERS';
	private const TESTER_GROUP_DESC = 'Users allowed to access card games while testing mode is enabled.';

	public static function depends_on()
	{
		return [
			'\freemitbbs\cardgamesauth\migrations\release_1_0_2',
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['cardgamesauth_version', '1.0.3']],
			['config.add', ['cardgamesauth_testing_mode', 0]],
			['config.add', ['cardgamesauth_tester_group_id', 0]],
			['custom', [[$this, 'ensure_tester_group']]],
		];
	}

	public function revert_data()
	{
		return [
			['custom', [[$this, 'remove_tester_group']]],
			['config.remove', ['cardgamesauth_tester_group_id']],
			['config.remove', ['cardgamesauth_testing_mode']],
		];
	}

	public function ensure_tester_group(): void
	{
		$group_id = $this->find_tester_group_id();
		if ($group_id <= 0)
		{
			$sql = 'INSERT INTO ' . GROUPS_TABLE . ' ' . $this->db->sql_build_array('INSERT', [
				'group_name' => self::TESTER_GROUP_NAME,
				'group_desc' => self::TESTER_GROUP_DESC,
				'group_desc_uid' => '',
				'group_desc_bitfield' => '',
				'group_type' => GROUP_HIDDEN,
				'group_colour' => '',
				'group_legend' => 0,
				'group_founder_manage' => 0,
				'group_skip_auth' => 0,
			]);
			$this->db->sql_query($sql);
			$group_id = (int) $this->db->sql_nextid();
		}

		if ($group_id > 0)
		{
			$this->config->set('cardgamesauth_tester_group_id', (string) $group_id);
		}
	}

	public function remove_tester_group(): void
	{
		$group_id = $this->find_tester_group_id();
		if ($group_id <= 0)
		{
			return;
		}

		$registered_group_id = $this->find_group_id_by_name('REGISTERED');
		if ($registered_group_id > 0)
		{
			$this->db->sql_query('UPDATE ' . USERS_TABLE . '
				SET group_id = ' . $registered_group_id . '
				WHERE group_id = ' . $group_id);
		}

		$this->db->sql_query('DELETE FROM ' . ACL_GROUPS_TABLE . '
			WHERE group_id = ' . $group_id);
		$this->db->sql_query('DELETE FROM ' . USER_GROUP_TABLE . '
			WHERE group_id = ' . $group_id);
		$this->db->sql_query('DELETE FROM ' . GROUPS_TABLE . '
			WHERE group_id = ' . $group_id);
	}

	protected function find_tester_group_id(): int
	{
		$config_group_id = (int) ($this->config['cardgamesauth_tester_group_id'] ?? 0);
		if ($config_group_id > 0)
		{
			$sql = 'SELECT group_id
				FROM ' . GROUPS_TABLE . '
				WHERE group_id = ' . $config_group_id . "
					AND group_name = '" . $this->db->sql_escape(self::TESTER_GROUP_NAME) . "'";
			$result = $this->db->sql_query_limit($sql, 1);
			$group_id = (int) $this->db->sql_fetchfield('group_id');
			$this->db->sql_freeresult($result);
			if ($group_id > 0)
			{
				return $group_id;
			}
		}

		$sql = 'SELECT group_id
			FROM ' . GROUPS_TABLE . "
			WHERE group_name = '" . $this->db->sql_escape(self::TESTER_GROUP_NAME) . "'";
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
