<?php

namespace freemitbbs\toptopics\migrations;

class release_1_1_28 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\toptopics\migrations\release_1_1_27',
		];
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'toptopics_user_reputation_queue' => [
					'COLUMNS' => [
						'user_id' => ['UINT:8', 0],
						'queued_time' => ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => ['user_id'],
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
				$this->table_prefix . 'toptopics_user_reputation_queue',
			],
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['toptopics_reputation_refresh_seconds', '300']],
			['config.add', ['toptopics_reputation_refresh_batch_size', '25']],
			['config.update', ['toptopics_version', '1.1.28']],
		];
	}
}
