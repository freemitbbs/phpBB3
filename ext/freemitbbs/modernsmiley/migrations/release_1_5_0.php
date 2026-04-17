<?php

namespace freemitbbs\modernsmiley\migrations;

class release_1_5_0 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\modernsmiley\migrations\release_1_4_0',
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'rebuild_acp_module']]],
			['config.update', ['modernsmiley_version', '1.5.0']],
		];
	}

	public function rebuild_acp_module(): bool
	{
		$this->remove_acp_modules();
		$this->ensure_acp_module();

		return true;
	}

	private function ensure_acp_module(): void
	{
		$parent = $this->get_parent_module_row();
		if (!$parent)
		{
			return;
		}

		$insert_at = (int) $parent['right_id'];
		$modules_table = $this->table_prefix . 'modules';

		$this->sql_query('begin');
		$this->sql_query('UPDATE ' . $modules_table . '
			SET left_id = left_id + 2
			WHERE module_class = \'acp\'
				AND left_id >= ' . $insert_at);
		$this->sql_query('UPDATE ' . $modules_table . '
			SET right_id = right_id + 2
			WHERE module_class = \'acp\'
				AND right_id >= ' . $insert_at);
		$this->sql_query('INSERT INTO ' . $modules_table . ' ' . $this->db->sql_build_array('INSERT', [
			'module_enabled' => 1,
			'module_display' => 1,
			'module_basename' => '\\freemitbbs\\modernsmiley\\acp\\acp_modernsmiley_module',
			'module_class' => 'acp',
			'parent_id' => (int) $parent['module_id'],
			'module_langname' => 'ACP_MODERNSMILEY',
			'module_mode' => 'main',
			'module_auth' => 'ext_freemitbbs/modernsmiley && acl_a_icons',
			'left_id' => $insert_at,
			'right_id' => $insert_at + 1,
		]));
		$this->sql_query('commit');
	}

	private function remove_acp_modules(): void
	{
		$rows = $this->get_target_module_rows();
		if (empty($rows))
		{
			return;
		}

		$modules_table = $this->table_prefix . 'modules';
		usort($rows, static function (array $left, array $right): int
		{
			return $right['left_id'] <=> $left['left_id'];
		});

		foreach ($rows as $row)
		{
			$module_id = (int) $row['module_id'];
			$left_id = (int) $row['left_id'];
			$right_id = (int) $row['right_id'];
			$diff = $right_id - $left_id + 1;

			$this->sql_query('DELETE FROM ' . $modules_table . '
				WHERE module_id = ' . $module_id);
			$this->sql_query('UPDATE ' . $modules_table . '
				SET right_id = right_id - ' . $diff . '
				WHERE module_class = \'acp\'
					AND left_id < ' . $left_id . '
					AND right_id > ' . $right_id);
			$this->sql_query('UPDATE ' . $modules_table . '
				SET left_id = left_id - ' . $diff . ',
					right_id = right_id - ' . $diff . '
				WHERE module_class = \'acp\'
					AND left_id > ' . $right_id);
		}
	}

	private function get_parent_module_row(): array
	{
		$sql = 'SELECT module_id, right_id
			FROM ' . $this->table_prefix . 'modules
			WHERE module_class = \'acp\'
				AND module_langname = \'ACP_MESSAGES\'
			ORDER BY left_id';
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result) ?: [];
		$this->db->sql_freeresult($result);

		return $row;
	}

	private function get_target_module_rows(): array
	{
		$sql = 'SELECT module_id, left_id, right_id
			FROM ' . $this->table_prefix . 'modules
			WHERE module_class = \'acp\'
				AND module_langname = \'ACP_MODERNSMILEY\'
				AND module_basename = \'\\\\freemitbbs\\\\modernsmiley\\\\acp\\\\acp_modernsmiley_module\'
				AND module_mode = \'main\'
			ORDER BY left_id, module_id';
		$result = $this->db->sql_query($sql);

		$rows = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = [
				'module_id' => (int) $row['module_id'],
				'left_id' => (int) $row['left_id'],
				'right_id' => (int) $row['right_id'],
			];
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}
}
