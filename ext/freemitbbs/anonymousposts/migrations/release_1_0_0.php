<?php

namespace freemitbbs\anonymousposts\migrations;

class release_1_0_0 extends \phpbb\db\migration\migration
{
	public function update_schema()
	{
		return ['add_columns' => [
			$this->table_prefix . 'posts' => ['anonymous_user_id' => ['UINT', 0]],
		]];
	}

	public function revert_schema()
	{
		return ['drop_columns' => [
			$this->table_prefix . 'posts' => ['anonymous_user_id'],
		]];
	}

	public function update_data()
	{
		return [
			['config.add', ['anonymousposts_forum_ids', '']],
			['custom', [[$this, 'configure_forum']]],
		];
	}

	public function configure_forum(): void
	{
		$result = $this->db->sql_query('SELECT forum_id FROM ' . $this->table_prefix . "forums WHERE forum_name = '花间密语'");
		$ids = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$ids[] = (int) $row['forum_id'];
		}
		$this->db->sql_freeresult($result);
		$this->config->set('anonymousposts_forum_ids', implode(',', $ids));
	}
}
