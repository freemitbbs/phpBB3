<?php

namespace freemitbbs\toptopics\migrations;

class release_1_1_1 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\toptopics\migrations\release_1_1_0',
		];
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'posts_dislike_actions' => [
					'COLUMNS' => [
						'action_id' => ['UINT', null, 'auto_increment'],
						'post_id' => ['UINT:8', 0],
						'user_id' => ['UINT:8', 0],
						'action_time' => ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => 'action_id',
					'KEYS' => [
						'post_id' => ['INDEX', 'post_id'],
						'user_time' => ['INDEX', ['user_id', 'action_time']],
					],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'posts_dislike_actions',
			],
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'backfill_dislike_actions']]],
			['config.update', ['toptopics_version', '1.1.1']],
		];
	}

	public function backfill_dislike_actions(): void
	{
		$sql = 'INSERT INTO ' . $this->table_prefix . 'posts_dislike_actions (post_id, user_id, action_time)
			SELECT post_id, user_id, disliketime
			FROM ' . $this->table_prefix . 'posts_dislikes
			WHERE disliketime > 0';
		$this->db->sql_query($sql);
	}
}
