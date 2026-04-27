<?php

namespace freemitbbs\toptopics\migrations;

class release_1_1_19 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\toptopics\migrations\release_1_1_18',
		];
	}

	public function update_schema()
	{
		return [
			'add_index' => [
				$this->table_prefix . 'topics' => [
					'toptopics_viewforum_sort' => [
						'forum_id',
						'topic_visibility',
						'topic_type',
						'topic_last_post_time',
						'topic_last_post_id',
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
					'toptopics_viewforum_sort',
				],
			],
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['toptopics_version', '1.1.19']],
		];
	}
}
