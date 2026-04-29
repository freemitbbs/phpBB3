<?php

namespace freemitbbs\adultaccess\migrations;

class release_1_0_1 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\adultaccess\migrations\release_1_0_0',
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'repair_ucp_nested_set']]],
			['config.update', ['freemitbbs_adultaccess_version', '1.0.4']],
		];
	}

	public function repair_ucp_nested_set(): void
	{
		$modules_table = $this->table_prefix . 'modules';
		$sql = 'SELECT module_id, parent_id, left_id, right_id, module_langname
			FROM ' . $modules_table . "
			WHERE module_class = 'ucp'
			ORDER BY left_id ASC, module_id ASC";
		$result = $this->db->sql_query($sql);

		$rows = [];
		$children = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$module_id = (int) $row['module_id'];
			$parent_id = (int) $row['parent_id'];

			$rows[$module_id] = [
				'module_id' => $module_id,
				'parent_id' => $parent_id,
				'left_id' => (int) $row['left_id'],
				'right_id' => (int) $row['right_id'],
				'module_langname' => (string) $row['module_langname'],
			];
			$children[$parent_id][] = $module_id;
		}
		$this->db->sql_freeresult($result);

		if (empty($rows) || empty($children[0]))
		{
			return;
		}

		foreach ($children as $parent_id => $child_ids)
		{
			$children[$parent_id] = $this->sort_ucp_children($parent_id, $child_ids, $rows);
		}

		$updates = [];
		$next_id = 1;
		foreach ($children[0] as $module_id)
		{
			$this->assign_ucp_nested_set($module_id, $rows, $children, $updates, $next_id);
		}

		foreach ($updates as $module_id => $update)
		{
			if ($update['left_id'] === $rows[$module_id]['left_id'] && $update['right_id'] === $rows[$module_id]['right_id'])
			{
				continue;
			}

			$sql = 'UPDATE ' . $modules_table . '
				SET ' . $this->db->sql_build_array('UPDATE', $update) . '
				WHERE module_id = ' . $module_id;
			$this->db->sql_query($sql);
		}
	}

	protected function sort_ucp_children(int $parent_id, array $child_ids, array $rows): array
	{
		$preferred_prefs_order = [
			'UCP_PREFS_PERSONAL' => 10,
			'UCP_PREFS_POST' => 20,
			'UCP_PREFS_VIEW' => 30,
			'UCP_NOTIFICATION_OPTIONS' => 40,
			'UCP_REACTIONS_SETTINGS' => 50,
			'UCP_ADULTACCESS' => 60,
		];

		$is_prefs_parent = isset($rows[$parent_id]) && $rows[$parent_id]['module_langname'] === 'UCP_PREFS';

		usort($child_ids, static function ($left_id, $right_id) use ($rows, $preferred_prefs_order, $is_prefs_parent) {
			$left = $rows[$left_id];
			$right = $rows[$right_id];

			$left_order = $is_prefs_parent
				? ($preferred_prefs_order[$left['module_langname']] ?? (1000 + $left['left_id']))
				: $left['left_id'];
			$right_order = $is_prefs_parent
				? ($preferred_prefs_order[$right['module_langname']] ?? (1000 + $right['left_id']))
				: $right['left_id'];

			if ($left_order === $right_order)
			{
				return $left['module_id'] <=> $right['module_id'];
			}

			return $left_order <=> $right_order;
		});

		return $child_ids;
	}

	protected function assign_ucp_nested_set(int $module_id, array $rows, array $children, array &$updates, int &$next_id): void
	{
		if (!isset($rows[$module_id]))
		{
			return;
		}

		$left_id = $next_id++;
		foreach ($children[$module_id] ?? [] as $child_id)
		{
			$this->assign_ucp_nested_set($child_id, $rows, $children, $updates, $next_id);
		}
		$right_id = $next_id++;

		$updates[$module_id] = [
			'left_id' => $left_id,
			'right_id' => $right_id,
		];
	}
}
