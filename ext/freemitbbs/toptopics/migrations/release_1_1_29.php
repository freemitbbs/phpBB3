<?php

namespace freemitbbs\toptopics\migrations;

class release_1_1_29 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\toptopics\migrations\release_1_1_28',
		];
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'toptopics_post_quality_queue' => [
					'COLUMNS' => [
						'post_id' => ['UINT:8', 0],
						'queued_time' => ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => ['post_id'],
					'KEYS' => [
						'queued_time' => ['INDEX', 'queued_time'],
					],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'toptopics_post_quality_queue',
			],
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['toptopics_version', '1.1.29']],
		];
	}
}
