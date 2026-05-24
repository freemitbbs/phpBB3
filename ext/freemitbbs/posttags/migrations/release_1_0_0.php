<?php

namespace freemitbbs\posttags\migrations;

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
				$this->table_prefix . 'posttags_tags' => [
					'COLUMNS' => [
						'tag_id' => ['UINT:8', null, 'auto_increment'],
						'tag_name' => ['VCHAR:100', ''],
						'tag_clean' => ['VCHAR:100', ''],
						'created_time' => ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => 'tag_id',
					'KEYS' => [
						'tag_clean' => ['UNIQUE', 'tag_clean'],
					],
				],
				$this->table_prefix . 'posttags_posts' => [
					'COLUMNS' => [
						'post_id' => ['UINT:8', 0],
						'tag_id' => ['UINT:8', 0],
						'tagged_time' => ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => ['post_id', 'tag_id'],
					'KEYS' => [
						'tag_id' => ['INDEX', 'tag_id'],
						'post_id' => ['INDEX', 'post_id'],
					],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'posttags_posts',
				$this->table_prefix . 'posttags_tags',
			],
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['freemitbbs_posttags_version', '1.0.0']],
		];
	}
}
