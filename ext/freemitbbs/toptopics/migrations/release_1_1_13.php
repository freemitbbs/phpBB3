<?php

namespace freemitbbs\toptopics\migrations;

class release_1_1_13 extends \phpbb\db\migration\migration
{
	private const BACKFILL_BATCH_SIZE = 300;
	private const PER_POST_CONTENT_CAP = 4000;

	public static function depends_on()
	{
		return [
			'\freemitbbs\toptopics\migrations\release_1_1_12',
		];
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'toptopics_post_quality' => [
					'COLUMNS' => [
						'post_id' => ['UINT:8', 0],
						'poster_id' => ['UINT:8', 0],
						'topic_id' => ['UINT:8', 0],
						'quality_length' => ['UINT:11', 0],
						'is_counted' => ['BOOL', 0],
						'computed_time' => ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => ['post_id'],
					'KEYS' => [
						'poster_counted' => ['INDEX', ['poster_id', 'is_counted']],
						'topic_id' => ['INDEX', 'topic_id'],
					],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'toptopics_post_quality',
			],
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'backfill_post_quality']]],
			['custom', [[$this, 'clear_materialized_reputation']]],
			['config.update', ['toptopics_version', '1.1.13']],
		];
	}

	public function backfill_post_quality($last_post_id = 0)
	{
		$last_post_id = (int) $last_post_id;
		$sql = 'SELECT p.post_id, p.poster_id, p.topic_id, p.post_text, p.post_visibility,
				t.topic_visibility, t.topic_type
			FROM ' . POSTS_TABLE . ' p
			INNER JOIN ' . TOPICS_TABLE . ' t
				ON t.topic_id = p.topic_id
			WHERE p.post_id > ' . $last_post_id . '
			ORDER BY p.post_id ASC';
		$result = $this->db->sql_query_limit($sql, self::BACKFILL_BATCH_SIZE);

		$rows = [];
		$post_ids = [];
		$computed_time = time();
		while ($row = $this->db->sql_fetchrow($result))
		{
			$post_id = (int) $row['post_id'];
			$poster_id = (int) $row['poster_id'];
			$is_countable_user = $poster_id > 0 && $poster_id !== ANONYMOUS;
			$is_counted = $is_countable_user
				&& (int) $row['post_visibility'] === ITEM_APPROVED
				&& (int) $row['topic_visibility'] === ITEM_APPROVED
				&& (int) $row['topic_type'] !== ITEM_MOVED;

			$rows[] = [
				'post_id' => $post_id,
				'poster_id' => $poster_id,
				'topic_id' => (int) $row['topic_id'],
				'quality_length' => $is_countable_user ? min(self::PER_POST_CONTENT_CAP, \freemitbbs\toptopics\service\quality_length::calculate((string) ($row['post_text'] ?? ''))) : 0,
				'is_counted' => $is_counted ? 1 : 0,
				'computed_time' => $computed_time,
			];
			$post_ids[] = $post_id;
			$last_post_id = $post_id;
		}
		$this->db->sql_freeresult($result);

		if (empty($rows))
		{
			return null;
		}

		$table = $this->table_prefix . 'toptopics_post_quality';
		$sql = 'DELETE FROM ' . $table . '
			WHERE ' . $this->db->sql_in_set('post_id', $post_ids);
		$this->db->sql_query($sql);

		$this->db->sql_multi_insert($table, $rows);

		return count($rows) < self::BACKFILL_BATCH_SIZE ? null : $last_post_id;
	}

	public function clear_materialized_reputation(): void
	{
		$sql = 'DELETE FROM ' . $this->table_prefix . 'toptopics_user_reputation';
		$this->db->sql_query($sql);
	}
}
