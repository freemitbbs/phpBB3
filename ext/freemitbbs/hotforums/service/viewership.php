<?php

namespace freemitbbs\hotforums\service;

class viewership
{
	protected \phpbb\auth\auth $auth;
	protected \phpbb\content_visibility $content_visibility;
	protected \phpbb\db\driver\driver_interface $db;
	protected static ?array $ordered_forums_cache = null;
	protected static ?array $order_by_forum_id_cache = null;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\content_visibility $content_visibility,
		\phpbb\db\driver\driver_interface $db
	)
	{
		$this->auth = $auth;
		$this->content_visibility = $content_visibility;
		$this->db = $db;
	}

	public function get_ordered_forums(): array
	{
		if (self::$ordered_forums_cache !== null)
		{
			return self::$ordered_forums_cache;
		}

		$forum_ids = $this->get_readable_forum_ids();
		if (empty($forum_ids))
		{
			self::$ordered_forums_cache = [];
			return self::$ordered_forums_cache;
		}

		$visibility_sql = $this->content_visibility->get_forums_visibility_sql('topic', $forum_ids, 't.');
		$sql = 'SELECT f.forum_id, f.forum_name, f.left_id, COALESCE(SUM(t.topic_views), 0) AS total_views
			FROM ' . FORUMS_TABLE . ' f
			LEFT JOIN ' . TOPICS_TABLE . ' t
				ON t.forum_id = f.forum_id
				AND t.topic_type <> ' . ITEM_MOVED . '
				AND ' . $visibility_sql . '
			WHERE f.forum_type = ' . FORUM_POST . '
				AND ' . $this->db->sql_in_set('f.forum_id', $forum_ids) . '
			GROUP BY f.forum_id, f.forum_name, f.left_id
			ORDER BY total_views DESC, f.left_id ASC';
		$result = $this->db->sql_query($sql);

		self::$ordered_forums_cache = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$row['forum_id'] = (int) $row['forum_id'];
			$row['left_id'] = (int) $row['left_id'];
			$row['total_views'] = (int) ($row['total_views'] ?? 0);
			self::$ordered_forums_cache[] = $row;
		}
		$this->db->sql_freeresult($result);

		return self::$ordered_forums_cache;
	}

	public function get_top_forums(int $limit): array
	{
		$limit = max(1, $limit);
		$forums = [];
		foreach ($this->get_ordered_forums() as $forum)
		{
			if ((int) ($forum['total_views'] ?? 0) <= 0)
			{
				continue;
			}

			$forums[] = $forum;
			if (count($forums) >= $limit)
			{
				break;
			}
		}

		return $forums;
	}

	public function get_order_by_forum_id(): array
	{
		if (self::$order_by_forum_id_cache !== null)
		{
			return self::$order_by_forum_id_cache;
		}

		self::$order_by_forum_id_cache = [];
		foreach ($this->get_ordered_forums() as $rank => $forum)
		{
			self::$order_by_forum_id_cache[(int) $forum['forum_id']] = $rank;
		}

		return self::$order_by_forum_id_cache;
	}

	protected function get_readable_forum_ids(): array
	{
		$forum_ids = [];
		$forum_list_ary = $this->auth->acl_getf('f_list');
		foreach ($this->auth->acl_getf('f_read') as $forum_id => $allowed)
		{
			if (!empty($allowed['f_read']) && !empty($forum_list_ary[$forum_id]['f_list']))
			{
				$forum_ids[] = (int) $forum_id;
			}
		}

		$forum_ids = array_values(array_unique($forum_ids));
		sort($forum_ids);

		return $forum_ids;
	}
}
