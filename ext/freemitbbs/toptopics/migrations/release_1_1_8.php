<?php

namespace freemitbbs\toptopics\migrations;

class release_1_1_8 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\toptopics\migrations\release_1_1_7',
		];
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'toptopics_user_reputation' => [
					'likes_received' => ['UINT:10', 0],
					'dislikes_received' => ['UINT:10', 0],
					'open_flags_received' => ['UINT:10', 0],
					'content_length_total' => ['UINT:11', 0],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns' => [
				$this->table_prefix . 'toptopics_user_reputation' => [
					'likes_received',
					'dislikes_received',
					'open_flags_received',
					'content_length_total',
				],
			],
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'clear_materialized_reputation']]],
			['config.remove', ['toptopics_reputation_cache_seconds']],
			['config.update', ['toptopics_version', '1.1.8']],
		];
	}

	public function clear_materialized_reputation(): void
	{
		$sql = 'DELETE FROM ' . $this->table_prefix . 'toptopics_user_reputation';
		$this->db->sql_query($sql);
	}
}
