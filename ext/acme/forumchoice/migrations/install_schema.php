<?php

namespace acme\forumchoice\migrations;

class install_schema extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\phpbb\db\migration\data\v320\v320'];
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'user_favorite_forums' => [
					'COLUMNS' => [
						'user_id'  => ['UINT', 0],
						'forum_id' => ['UINT', 0],
					],
					'PRIMARY_KEY' => ['user_id', 'forum_id'],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'user_favorite_forums',
			],
		];
	}
}
