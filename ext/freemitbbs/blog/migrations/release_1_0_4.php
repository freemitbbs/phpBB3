<?php

namespace freemitbbs\blog\migrations;

class release_1_0_4 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\blog\migrations\release_1_0_3',
		];
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'blog_topics' => [
					'is_draft' => ['BOOL', 0],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns' => [
				$this->table_prefix . 'blog_topics' => [
					'is_draft',
				],
			],
		];
	}

	public function update_data()
	{
		return [
			['permission.remove', ['m_blog_manage', true]],
			['permission.remove', ['m_blog_manage', false]],
			['config.update', ['freemitbbs_blog_version', '1.0.4']],
		];
	}
}
