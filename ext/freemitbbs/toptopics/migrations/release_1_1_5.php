<?php

namespace freemitbbs\toptopics\migrations;

class release_1_1_5 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\toptopics\migrations\release_1_1_4',
		];
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'toptopics_topic_overrides' => [
					'COLUMNS' => [
						'topic_id' => ['UINT:8', 0],
						'override_state' => ['VCHAR:16', ''],
						'updated_by' => ['UINT:8', 0],
						'updated_time' => ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => ['topic_id'],
					'KEYS' => [
						'override_state' => ['INDEX', 'override_state'],
						'updated_by' => ['INDEX', 'updated_by'],
					],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'toptopics_topic_overrides',
			],
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['toptopics_manual_boost_multiplier', '2.0']],
			['config.add', ['toptopics_manual_demote_multiplier', '0.3']],
			['config.update', ['toptopics_version', '1.1.5']],
		];
	}
}
