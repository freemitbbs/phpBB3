<?php

namespace freemitbbs\hotforums\service;

class viewership
{
	private const DEFAULT_CACHE_SECONDS = 600;
	private const CACHE_KEY_PREFIX = '_freemitbbs_hotforums_viewership_';

	protected \phpbb\auth\auth $auth;
	protected ?\phpbb\cache\service $cache;
	protected ?\phpbb\config\config $config;
	protected \phpbb\content_visibility $content_visibility;
	protected \phpbb\db\driver\driver_interface $db;
	protected static ?array $ordered_forums_cache = null;
	protected static ?array $order_by_forum_id_cache = null;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\content_visibility $content_visibility,
		\phpbb\db\driver\driver_interface $db,
		?\phpbb\cache\service $cache = null,
		?\phpbb\config\config $config = null
	)
	{
		$this->auth = $auth;
		$this->content_visibility = $content_visibility;
		$this->db = $db;
		$this->cache = $cache;
		$this->config = $config;
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
		$cache_ttl = $this->get_cache_ttl();
		$cache_key = $this->build_cache_key($forum_ids, $visibility_sql);
		if ($cache_key !== '' && $cache_ttl > 0 && $this->cache !== null)
		{
			$cached_forums = $this->cache->get($cache_key);
			if (is_array($cached_forums))
			{
				self::$ordered_forums_cache = $this->normalise_ordered_forums($cached_forums);
				return self::$ordered_forums_cache;
			}
		}

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

		if ($cache_key !== '' && $cache_ttl > 0 && $this->cache !== null)
		{
			$this->cache->put($cache_key, self::$ordered_forums_cache, $cache_ttl);
		}

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

	protected function get_cache_ttl(): int
	{
		if ($this->cache === null)
		{
			return 0;
		}

		$ttl = $this->config !== null && isset($this->config['hotforums_viewership_cache_seconds'])
			? (int) $this->config['hotforums_viewership_cache_seconds']
			: self::DEFAULT_CACHE_SECONDS;

		return max(0, min(86400, $ttl));
	}

	protected function build_cache_key(array $forum_ids, string $visibility_sql): string
	{
		$payload = json_encode([
			'forum_ids' => array_values(array_map('intval', $forum_ids)),
			'visibility' => $visibility_sql,
		]);

		return $payload !== false ? self::CACHE_KEY_PREFIX . md5($payload) : '';
	}

	protected function normalise_ordered_forums(array $forums): array
	{
		$normalised = [];
		foreach ($forums as $row)
		{
			if (!is_array($row))
			{
				continue;
			}

			$forum_id = (int) ($row['forum_id'] ?? 0);
			if ($forum_id <= 0)
			{
				continue;
			}

			$row['forum_id'] = $forum_id;
			$row['forum_name'] = (string) ($row['forum_name'] ?? '');
			$row['left_id'] = (int) ($row['left_id'] ?? 0);
			$row['total_views'] = (int) ($row['total_views'] ?? 0);
			$normalised[] = $row;
		}

		return $normalised;
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
