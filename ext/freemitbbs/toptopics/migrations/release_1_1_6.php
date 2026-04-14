<?php

namespace freemitbbs\toptopics\migrations;

class release_1_1_6 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\toptopics\migrations\release_1_1_5',
		];
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'toptopics_user_reputation' => [
					'COLUMNS' => [
						'user_id' => ['UINT:8', 0],
						'reputation_score' => ['PDECIMAL:12', 0],
						'computed_time' => ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => ['user_id'],
					'KEYS' => [
						'computed_time' => ['INDEX', 'computed_time'],
					],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'toptopics_user_reputation',
			],
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['toptopics_reputation_cache_seconds', '3600']],
			['config.add', ['toptopics_min_reputation_dislike', '10']],
			['config.add', ['toptopics_min_reputation_report', '50']],
			['config.update', ['toptopics_version', '1.1.6']],
		];
	}
}
