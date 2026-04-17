<?php

namespace freemitbbs\modernsmiley\migrations;

use freemitbbs\modernsmiley\service\mapper;

class release_1_0_0 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\phpbb\db\migration\data\v33x\v3310',
		];
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'modern_smilies' => [
					'COLUMNS' => [
						'smiley_url' => ['VCHAR:50', ''],
						'emoji_seq' => ['VCHAR:191', ''],
					],
					'PRIMARY_KEY' => 'smiley_url',
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'modern_smilies',
			],
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['modernsmiley_version', '1.0.0']],
			['custom', [[$this, 'seed_default_mappings']]],
			['custom', [[$this, 'ensure_acp_module']]],
		];
	}

	public function revert_data()
	{
		return [
			['custom', [[$this, 'remove_acp_modules']]],
		];
	}

	public function seed_default_mappings(): void
	{
		$sql = 'SELECT DISTINCT smiley_url
			FROM ' . SMILIES_TABLE;
		$result = $this->db->sql_query($sql);

		$sql_ary = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$smiley_url = (string) $row['smiley_url'];
			if (isset(mapper::DEFAULT_URL_MAPPINGS[$smiley_url]))
			{
				$sql_ary[$smiley_url] = [
					'smiley_url' => $smiley_url,
					'emoji_seq' => mapper::DEFAULT_URL_MAPPINGS[$smiley_url],
				];
			}
		}
		$this->db->sql_freeresult($result);

		if (!empty($sql_ary))
		{
			$this->db->sql_multi_insert($this->table_prefix . 'modern_smilies', array_values($sql_ary));
		}
	}

	public function ensure_acp_module(): bool
	{
		$this->delete_duplicate_module_rows();

		if ($this->find_existing_module_row())
		{
			return true;
		}

		$parent = $this->get_parent_module_row();
		if (!$parent)
		{
			return true;
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

		$this->delete_duplicate_module_rows();

		return true;
	}

	public function remove_acp_modules(): bool
	{
		$this->delete_duplicate_module_rows();

		$rows = $this->get_target_module_rows();
		if (empty($rows))
		{
			return true;
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

		return true;
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

	private function find_existing_module_row(): array
	{
		$rows = $this->get_target_module_rows();

		return $rows[0] ?? [];
	}

	private function get_target_module_rows(): array
	{
		$sql = 'SELECT module_id, parent_id, left_id, right_id
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
				'parent_id' => (int) $row['parent_id'],
				'left_id' => (int) $row['left_id'],
				'right_id' => (int) $row['right_id'],
			];
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	private function delete_duplicate_module_rows(): void
	{
		$rows = $this->get_target_module_rows();
		if (count($rows) < 2)
		{
			return;
		}

		$seen = [];
		$delete_ids = [];
		foreach ($rows as $row)
		{
			$key = implode(':', [$row['parent_id'], $row['left_id'], $row['right_id']]);
			if (isset($seen[$key]))
			{
				$delete_ids[] = $row['module_id'];
				continue;
			}

			$seen[$key] = true;
		}

		if (empty($delete_ids))
		{
			return;
		}

		$this->sql_query('DELETE FROM ' . $this->table_prefix . 'modules
			WHERE ' . $this->db->sql_in_set('module_id', $delete_ids));
	}
}
