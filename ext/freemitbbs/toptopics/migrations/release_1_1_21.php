<?php

namespace freemitbbs\toptopics\migrations;

class release_1_1_21 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\toptopics\migrations\release_1_1_20',
		];
	}

	public function effectively_installed()
	{
		return $this->db_tools->sql_column_exists(USERS_TABLE, 'user_home_topic_hide_forums');
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				USERS_TABLE => [
					'user_home_topic_hide_forums' => ['VCHAR:1024', ''],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns' => [
				USERS_TABLE => [
					'user_home_topic_hide_forums',
				],
			],
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['toptopics_version', '1.1.21']],
		];
	}
}
