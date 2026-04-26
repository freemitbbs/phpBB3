<?php

namespace freemitbbs\toptopics\migrations;

class release_1_1_18 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\toptopics\migrations\release_1_1_17',
		];
	}

	public function update_schema()
	{
		return [
			'add_index' => [
				$this->table_prefix . 'posts' => [
					'toptopics_topic_reply_author' => [
						'topic_id',
						'post_visibility',
						'poster_id',
						'post_id',
						'post_username',
					],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_keys' => [
				$this->table_prefix . 'posts' => [
					'toptopics_topic_reply_author',
				],
			],
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['toptopics_version', '1.1.18']],
		];
	}
}
