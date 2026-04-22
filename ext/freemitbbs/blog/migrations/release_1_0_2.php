<?php

namespace freemitbbs\blog\migrations;

class release_1_0_2 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\blog\migrations\release_1_0_1',
		];
	}

	public function effectively_installed()
	{
		return !$this->db_tools->sql_table_exists($this->table_prefix . 'blog_entries');
	}

	public function update_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'blog_entries',
			],
		];
	}

	public function revert_schema()
	{
		return [];
	}
}
