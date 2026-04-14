<?php

namespace freemitbbs\toptopics\migrations;

class release_1_1_9 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\toptopics\migrations\release_1_1_8',
		];
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'toptopics_scope_snapshots' => [
					'COLUMNS' => [
						'scope_key' => ['VCHAR:64', ''],
						'forum_ids_json' => ['TEXT_UNI', ''],
						'topic_limit' => ['UINT:4', 0],
						'options_hash' => ['VCHAR:32', ''],
						'generation_hash' => ['VCHAR:32', ''],
						'topics_json' => ['MTEXT_UNI', ''],
						'updated_time' => ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => ['scope_key'],
					'KEYS' => [
						'updated_time' => ['INDEX', 'updated_time'],
					],
				],
				$this->table_prefix . 'toptopics_scope_forums' => [
					'COLUMNS' => [
						'scope_key' => ['VCHAR:64', ''],
						'forum_id' => ['UINT:8', 0],
					],
					'PRIMARY_KEY' => ['scope_key', 'forum_id'],
					'KEYS' => [
						'forum_id' => ['INDEX', 'forum_id'],
					],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'toptopics_scope_snapshots',
				$this->table_prefix . 'toptopics_scope_forums',
			],
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['toptopics_version', '1.1.9']],
		];
	}
}
