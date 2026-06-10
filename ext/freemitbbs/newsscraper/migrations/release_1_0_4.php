<?php

namespace freemitbbs\newsscraper\migrations;

class release_1_0_4 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\newsscraper\migrations\release_1_0_3',
		];
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'newsscraper_discussions' => [
					'COLUMNS' => [
						'digest_topic_id' => ['UINT:8', 0],
						'discussion_topic_id' => ['UINT:8', 0],
						'discussion_post_id' => ['UINT:8', 0],
						'forum_id' => ['UINT:8', 0],
						'created_time' => ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => ['digest_topic_id', 'discussion_topic_id'],
					'KEYS' => [
						'digest_time' => ['INDEX', ['digest_topic_id', 'created_time']],
						'discussion_topic' => ['INDEX', 'discussion_topic_id'],
						'discussion_post' => ['INDEX', 'discussion_post_id'],
						'forum_time' => ['INDEX', ['forum_id', 'created_time']],
					],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'newsscraper_discussions',
			],
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'backfill_discussions']]],
			['config.update', ['newsscraper_version', '1.0.4']],
		];
	}

	public function backfill_discussions(): void
	{
		$digest_forum_id = (int) $this->get_config_value('newsscraper_digest_forum_id');
		if ($digest_forum_id <= 0)
		{
			return;
		}

		$first_digest_time = $this->first_digest_topic_time($digest_forum_id);
		if ($first_digest_time <= 0)
		{
			return;
		}

		$digest_topics = [];
		$seen_pairs = [];
		$sql = 'SELECT p.post_id, p.topic_id, p.forum_id, p.post_text, p.post_time
			FROM ' . POSTS_TABLE . ' p
			INNER JOIN ' . TOPICS_TABLE . ' t
				ON t.topic_id = p.topic_id
			WHERE p.post_visibility = ' . ITEM_APPROVED . '
				AND t.topic_visibility = ' . ITEM_APPROVED . '
				AND p.forum_id <> ' . $digest_forum_id . '
				AND p.post_time >= ' . $first_digest_time . '
				AND p.post_text ' . $this->db->sql_like_expression($this->db->get_any_char() . 'viewtopic.php?t=' . $this->db->get_any_char()) . '
			ORDER BY p.post_time ASC, p.post_id ASC';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			if (!preg_match_all('#viewtopic\.php\?t=(\d+)#', (string) $row['post_text'], $matches))
			{
				continue;
			}

			foreach (array_unique(array_map('intval', $matches[1])) as $digest_topic_id)
			{
				$pair_key = $digest_topic_id . ':' . (int) $row['topic_id'];
				if ($digest_topic_id <= 0 || isset($seen_pairs[$pair_key]))
				{
					continue;
				}
				$seen_pairs[$pair_key] = true;

				if (!$this->digest_topic_exists($digest_topic_id, $digest_forum_id, $digest_topics))
				{
					continue;
				}

				$this->insert_discussion_row(
					$digest_topic_id,
					(int) $row['topic_id'],
					(int) $row['post_id'],
					(int) $row['forum_id'],
					(int) $row['post_time']
				);
			}
		}
		$this->db->sql_freeresult($result);
	}

	protected function get_config_value(string $config_name): string
	{
		$sql = 'SELECT config_value
			FROM ' . CONFIG_TABLE . "
			WHERE config_name = '" . $this->db->sql_escape($config_name) . "'";
		$result = $this->db->sql_query($sql);
		$config_value = (string) $this->db->sql_fetchfield('config_value');
		$this->db->sql_freeresult($result);

		return $config_value;
	}

	protected function first_digest_topic_time(int $digest_forum_id): int
	{
		$sql = 'SELECT MIN(topic_time) AS first_topic_time
			FROM ' . TOPICS_TABLE . '
			WHERE forum_id = ' . $digest_forum_id . '
				AND topic_visibility = ' . ITEM_APPROVED;
		$result = $this->db->sql_query($sql);
		$first_topic_time = (int) $this->db->sql_fetchfield('first_topic_time');
		$this->db->sql_freeresult($result);

		return $first_topic_time;
	}

	protected function digest_topic_exists(int $topic_id, int $digest_forum_id, array &$digest_topics): bool
	{
		if (isset($digest_topics[$topic_id]))
		{
			return $digest_topics[$topic_id];
		}

		$sql = 'SELECT topic_id
			FROM ' . TOPICS_TABLE . '
			WHERE topic_id = ' . $topic_id . '
				AND forum_id = ' . $digest_forum_id . '
				AND topic_visibility = ' . ITEM_APPROVED;
		$result = $this->db->sql_query_limit($sql, 1);
		$digest_topics[$topic_id] = (bool) $this->db->sql_fetchfield('topic_id');
		$this->db->sql_freeresult($result);

		return $digest_topics[$topic_id];
	}

	protected function insert_discussion_row(int $digest_topic_id, int $discussion_topic_id, int $discussion_post_id, int $forum_id, int $created_time): void
	{
		$sql_ary = [
			'digest_topic_id' => $digest_topic_id,
			'discussion_topic_id' => $discussion_topic_id,
			'discussion_post_id' => $discussion_post_id,
			'forum_id' => $forum_id,
			'created_time' => $created_time,
		];

		$this->db->sql_return_on_error(true);
		try
		{
			$this->db->sql_query('INSERT INTO ' . $this->table_prefix . 'newsscraper_discussions ' . $this->db->sql_build_array('INSERT', $sql_ary));
		}
		finally
		{
			$this->db->sql_return_on_error(false);
		}
	}
}
