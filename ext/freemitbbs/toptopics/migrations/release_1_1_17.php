<?php

namespace freemitbbs\toptopics\migrations;

class release_1_1_17 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\toptopics\migrations\release_1_1_16',
		];
	}

	public function update_schema()
	{
		return [
			'add_index' => [
				$this->table_prefix . 'topics' => [
					'toptopics_recent_visible' => [
						'topic_visibility',
						'topic_last_post_time',
						'forum_id',
						'topic_time',
						'topic_type',
						'topic_id',
					],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_keys' => [
				$this->table_prefix . 'topics' => [
					'toptopics_recent_visible',
				],
			],
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['toptopics_version', '1.1.17']],
		];
	}
}
