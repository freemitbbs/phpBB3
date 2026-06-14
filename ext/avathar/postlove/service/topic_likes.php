<?php
/**
 *
 * Post Love extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Avathar.be
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\postlove\service;

use phpbb\cache\service as cache_service;
use phpbb\db\driver\driver_interface;

/**
 * Public service for querying aggregated like counts.
 *
 * Other extensions can consume this service via optional DI
 * (@?avathar.postlove.topic_likes) to display like counts
 * without querying the posts_likes table directly.
 */
class topic_likes
{
	private const CACHE_PREFIX = '_avathar_postlove_topic_like_count_';
	private const CACHE_SECONDS = 600;

	/** @var driver_interface */
	protected $db;

	/** @var cache_service */
	protected $cache;

	/** @var string */
	protected $likes_table;

	/**
	 * @param driver_interface $db
	 * @param cache_service    $cache
	 * @param string           $likes_table
	 */
	public function __construct(driver_interface $db, cache_service $cache, string $likes_table)
	{
		$this->db = $db;
		$this->cache = $cache;
		$this->likes_table = $likes_table;
	}

	public static function cache_key_for_topic(int $topic_id): string
	{
		return self::CACHE_PREFIX . max(0, $topic_id);
	}

	/**
	 * Get aggregated like counts for a set of topics.
	 *
	 * @param  array $topic_ids Array of topic IDs
	 * @return array Associative array [topic_id => like_count]
	 */
	public function get_topic_like_counts(array $topic_ids): array
	{
		$topic_ids = array_values(array_unique(array_filter(array_map('intval', $topic_ids), static function (int $topic_id): bool {
			return $topic_id > 0;
		})));
		if (empty($topic_ids))
		{
			return [];
		}

		$counts = [];
		$missing_topic_ids = [];
		foreach ($topic_ids as $topic_id)
		{
			$cached_count = $this->cache->get(self::cache_key_for_topic($topic_id));
			if ($cached_count !== false)
			{
				$counts[$topic_id] = max(0, (int) $cached_count);
				continue;
			}

			$missing_topic_ids[] = $topic_id;
		}

		if (empty($missing_topic_ids))
		{
			return $counts;
		}

		$sql = 'SELECT p.topic_id, COUNT(l.post_id) AS like_count
			FROM ' . POSTS_TABLE . ' p
			INNER JOIN ' . $this->likes_table . ' l ON (l.post_id = p.post_id)
			WHERE ' . $this->db->sql_in_set('p.topic_id', $missing_topic_ids) . '
			GROUP BY p.topic_id';
		$result = $this->db->sql_query($sql);

		$fetched_counts = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$fetched_counts[(int) $row['topic_id']] = max(0, (int) $row['like_count']);
		}
		$this->db->sql_freeresult($result);

		foreach ($missing_topic_ids as $topic_id)
		{
			$counts[$topic_id] = $fetched_counts[$topic_id] ?? 0;
			$this->cache->put(self::cache_key_for_topic($topic_id), $counts[$topic_id], self::CACHE_SECONDS);
		}

		return $counts;
	}
}
