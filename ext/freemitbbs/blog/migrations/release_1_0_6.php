<?php

namespace freemitbbs\blog\migrations;

class release_1_0_6 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\blog\migrations\release_1_0_5',
		];
	}

	public function effectively_installed()
	{
		return $this->db_tools->sql_column_exists(USERS_TABLE, 'user_blog_header_attachment_id');
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				USERS_TABLE => [
					'user_blog_header_attachment_id' => ['UINT', 0],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns' => [
				USERS_TABLE => [
					'user_blog_header_attachment_id',
				],
			],
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['freemitbbs_blog_version', '1.0.6']],
		];
	}
}
