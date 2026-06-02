<?php

namespace freemitbbs\topicmover\migrations;

class release_1_0_3 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\topicmover\migrations\release_1_0_2',
		];
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'topicmover_considered_topics' => [
					'COLUMNS' => [
						'topic_id' => ['UINT:8', 0],
						'considered_time' => ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => ['topic_id'],
					'KEYS' => [
						'considered_time' => ['INDEX', 'considered_time'],
					],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'topicmover_considered_topics',
			],
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['topicmover_version', '1.0.3']],
		];
	}

	public function revert_data()
	{
		return [
			['config.update', ['topicmover_version', '1.0.2']],
		];
	}
}
