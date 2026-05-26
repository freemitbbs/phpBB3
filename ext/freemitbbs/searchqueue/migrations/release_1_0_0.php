<?php

namespace freemitbbs\searchqueue\migrations;

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
				$this->table_prefix . 'searchqueue_posts' => [
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
				$this->table_prefix . 'searchqueue_posts',
			],
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['freemitbbs_searchqueue_enabled', '1']],
			['config.add', ['freemitbbs_searchqueue_batch_size', '25']],
			['config.add', ['freemitbbs_searchqueue_interval_seconds', '30']],
			['config.add', ['freemitbbs_searchqueue_version', '1.0.0']],
		];
	}
}
