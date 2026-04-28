<?php
/**
 *
 * Top Stats extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 stoker - https://phpbb3bbcodes.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

declare(strict_types=1);

namespace stoker\topstats\service;

use phpbb\auth\auth;
use phpbb\cache\service as cache_interface;
use phpbb\config\config;
use phpbb\content_visibility;
use phpbb\db\driver\driver_interface;
use phpbb\event\data;
use phpbb\template\template;
use phpbb\user;
use stoker\topstats\helper\number_helper;

/**
 * Service responsible for fetching and assigning top statistics blocks.
 * Handles caching with smart TTLs based on data volatility.
 */
class topstats_manager
{
	/** @var int Cache TTL: 1 day (86400 seconds) */
	const CACHEDAY = 86400;
	/** @var int Cache TTL: 1 hour (3600 seconds) */
	const CACHEHOUR = 3600;
	/** @var int Cache TTL: 5 minutes (300 seconds) */
	const CACHEMIN = 300;
	/** @var int Cache TTL: 1 year (365 days for permanent caching) */
	const CACHEYEAR = 31536000;

	/** @var driver_interface */
	protected $db;
	
	/** @var template */
	protected $template;
	
	/** @var config */
	protected $config;
	
	/** @var user */
	protected $user;
	
	/** @var auth */
	protected $auth;
	
	/** @var content_visibility */
	protected $content_visibility;
	
	/** @var cache_interface */
	protected $cache;
	
	/** @var number_helper */
	protected $number_helper;
	
	/** @var string */
	protected $root_path;
	
	/** @var string */
	protected $php_ext;
	
	/** @var bool */
	protected $on_portal = false;
	
	/** @var string|null Cached visibility SQL fragment */
	private $vis_sql_cache = null;
	
	/** @var string|null Cached visibility suffix for cache keys */
	private $vis_suffix_cache = null;
	
	/** @var int|null Cached total post count */
	private $total_posts_cache = null;
	
	/** @var string|null Cached web root URL */
	private $web_root_cache = null;
	
	/** @var array|null Cached excluded user IDs */
	private $excluded_ids_cache = null;
	
	/** @var array|null Cached excluded forum IDs */
	private $excluded_forum_ids_cache = null;

	/**
	 * @param driver_interface  $db
	 * @param template          $template
	 * @param config            $config
	 * @param user              $user
	 * @param auth              $auth
	 * @param content_visibility $content_visibility
	 * @param cache_interface   $cache
	 * @param number_helper     $number_helper
	 * @param string            $phpbb_root_path
	 * @param string            $php_ext
	 */
	public function __construct(driver_interface $db, template $template, config $config, user $user, auth $auth, content_visibility $content_visibility, cache_interface $cache, number_helper $number_helper, string $phpbb_root_path, string $php_ext)
	{
		$this->db = $db;
		$this->template = $template;
		$this->config = $config;
		$this->user = $user;
		$this->auth = $auth;
		$this->content_visibility = $content_visibility;
		$this->cache = $cache;
		$this->number_helper = $number_helper;
		$this->root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
	}

	/**
	 * Get cached visibility SQL fragment.
	 * Computed once per request for efficiency.
	 * 
	 * @return string Visibility SQL clause for topic queries
	 */
	private function get_vis_sql(): string
	{
		if ($this->vis_sql_cache === null)
		{
			$this->vis_sql_cache = $this->content_visibility->get_visibility_sql('topic', 't.');
		}
		return $this->vis_sql_cache;
	}

	/**
	 * Get visibility suffix for cache keys.
	 * Based on user's approval and soft-delete permissions.
	 * 
	 * @return string Cache key suffix (e.g., 'U1S0')
	 */
	private function vis_suffix(): string
	{
		if ($this->vis_suffix_cache === null)
		{
			$u = $this->auth->acl_getf_global('m_approve') ? 'U1' : 'U0';
			$s = $this->auth->acl_getf_global('m_softdelete') ? 'S1' : 'S0';
			$this->vis_suffix_cache = $u . $s;
		}
		return $this->vis_suffix_cache;
	}

	/**
	 * Get total posts count (cached per request)
	 */
	private function get_total_posts(): int
	{
		if ($this->total_posts_cache === null)
		{
			$this->total_posts_cache = max(1, (int) ($this->config['num_posts'] ?? 1));
		}
		return $this->total_posts_cache;
	}

	/**
	 * Get web root URL with trailing slash.
	 * Cached per request for URL building.
	 * 
	 * @return string Board URL (e.g., 'https://example.com/')
	 */
	private function get_web_root(): string
	{
		if ($this->web_root_cache === null)
		{
			$this->web_root_cache = rtrim(generate_board_url(), '/') . '/';
		}
		return $this->web_root_cache;
	}

	/**
	 * Get list of user IDs to exclude from statistics.
	 * Parses comma-separated config value, cached per request.
	 * 
	 * @return array<int> Array of user IDs to exclude
	 */
	private function get_excluded_user_ids(): array
	{
		if ($this->excluded_ids_cache === null)
		{
			$excluded_ids = $this->config['topstats_excluded_users'] ?? '';
			if (empty($excluded_ids))
			{
				$this->excluded_ids_cache = [];
			}
			else
			{
				$this->excluded_ids_cache = array_map('intval', array_filter(array_map('trim', explode(',', $excluded_ids)), 'strlen'));
			}
		}
		return $this->excluded_ids_cache;
	}

	/**
	 * Get list of forum IDs to exclude from statistics.
	 * Parses comma-separated config value, cached per request.
	 * 
	 * @return array<int> Array of forum IDs to exclude
	 */
	private function get_excluded_forum_ids(): array
	{
		if ($this->excluded_forum_ids_cache === null)
		{
			$excluded_ids = $this->config['topstats_excluded_forums'] ?? '';
			if (empty($excluded_ids))
			{
				$this->excluded_forum_ids_cache = [];
			}
			else
			{
				$this->excluded_forum_ids_cache = array_map('intval', array_filter(array_map('trim', explode(',', $excluded_ids)), 'strlen'));
			}
		}
		return $this->excluded_forum_ids_cache;
	}
	
	/**
	 * Get current board time with timezone handling.
	 * Falls back to UTC if board timezone is invalid.
	 * 
	 * @return \DateTimeImmutable Current time in board timezone
	 */
	private function board_now(): \DateTimeImmutable
	{
		$tz_id = (string) ($this->config['board_timezone'] ?? 'UTC');
		try
		{
			$tz = new \DateTimeZone($tz_id);
		}
		catch (\Exception $e)
		{
			$tz = new \DateTimeZone('UTC');
		}
		return new \DateTimeImmutable('now', $tz);
	}

	/**
	 * Resolve active config limit key.
	 * Appends 'p' suffix when displaying on portal.
	 * 
	 * @param string $base_key Base config key (e.g., 'tsmvt_number')
	 * @return int Configured limit value
	 */
	private function pick(string $base_key): int
	{
		$key = $base_key . ($this->on_portal ? 'p' : '');
		return (int) ($this->config[$key] ?? 0);
	}

	/**
	 * Display all top stats on index or portal page
	 */
	public function display_topstats(data $event, bool $on_portal = false): void
	{
		$this->on_portal = $on_portal;

		// Pre-calculate forum list once
		$flist = array_unique(array_keys($this->auth->acl_getf('f_read', true)));
		$flist[] = 0;
		sort($flist, SORT_NUMERIC);

		$this->assign_most_viewed_topics($flist);
		$this->assign_most_replied_topics($flist);
		$this->assign_most_active_users();
		$this->assign_most_active_forums($flist);
		$this->assign_last_visited_bots();
		$this->assign_last_registered_users();
		$this->assign_top_posters_last_month();
		$this->assign_top_posters_this_month();

		// Compute month names using board timezone (not server timezone)
		$now = $this->board_now();
		$this_month_en = $now->format('F');
		$last_month_en = $now->modify('first day of last month')->format('F');
		$this_month_key = 'TS_MONTH_' . strtoupper($this_month_en);
		$last_month_key = 'TS_MONTH_' . strtoupper($last_month_en);
		$this_month_name = $this->user->lang($this_month_key);
		$last_month_name = $this->user->lang($last_month_key);
		if ($this_month_name === $this_month_key)
		{
			$this_month_name = $this_month_en;
		}
		if ($last_month_name === $last_month_key)
		{
			$last_month_name = $last_month_en;
		}

		// Batch assign template vars
		$this->template->assign_vars([
			'DISPLAY_TOP_STATS_INDEX' => !empty($this->config['display_top_stats_index']),
			'DISPLAY_TOP_STATS_PORTAL' => !empty($this->config['display_top_stats_portal']),
			'TS_THIS_MONTH_NAME' => $this_month_name,
			'TS_LAST_MONTH_NAME' => $last_month_name,
			'TSMVT_NUMBER' => $this->pick('tsmvt_number'),
			'TSMRT_NUMBER' => $this->pick('tsmrt_number'),
			'TSMAF_NUMBER' => $this->pick('tsmaf_number'),
			'TSMAU_NUMBER' => $this->pick('tsmau_number'),
			'TSLVB_NUMBER' => $this->pick('tslvb_number'),
			'TSLRU_NUMBER' => $this->pick('tslru_number'),
			'TSTTM_NUMBER' => $this->pick('tsttm_number'),
			'TSTLM_NUMBER' => $this->pick('tstlm_number'),
			'TSMVT_NUMBERP' => (int) ($this->config['tsmvt_numberp'] ?? 0),
			'TSMRT_NUMBERP' => (int) ($this->config['tsmrt_numberp'] ?? 0),
			'TSMAF_NUMBERP' => (int) ($this->config['tsmaf_numberp'] ?? 0),
			'TSMAU_NUMBERP' => (int) ($this->config['tsmau_numberp'] ?? 0),
			'TSLVB_NUMBERP' => (int) ($this->config['tslvb_numberp'] ?? 0),
			'TSLRU_NUMBERP' => (int) ($this->config['tslru_numberp'] ?? 0),
			'TSTTM_NUMBERP' => (int) ($this->config['tsttm_numberp'] ?? 0),
			'TSTLM_NUMBERP' => (int) ($this->config['tstlm_numberp'] ?? 0),
		]);
	}

	/**
	 * Display all top stats on custom page
	 */
	public function display_topstats_custom(): void
	{
		$this->on_portal = false;

		$keys = ['tsmvt_number', 'tsmrt_number', 'tsmau_number', 'tsmaf_number', 'tslvb_number', 'tslru_number', 'tsttm_number', 'tstlm_number'];

		// Backup and swap config values
		$backup = [];
		foreach ($keys as $k)
		{
			$backup[$k] = $this->config[$k] ?? null;
			$this->config[$k] = (int) ($this->config[$k . 'c'] ?? 0);
		}

		$flist = array_unique(array_keys($this->auth->acl_getf('f_read', true)));
		$flist[] = 0;
		sort($flist, SORT_NUMERIC);

		$this->assign_most_viewed_topics($flist);
		$this->assign_most_replied_topics($flist);
		$this->assign_most_active_users();
		$this->assign_most_active_forums($flist);
		$this->assign_last_visited_bots();
		$this->assign_last_registered_users();
		$this->assign_top_posters_last_month();
		$this->assign_top_posters_this_month();

		// Compute month names using board timezone (not server timezone)
		$now = $this->board_now();
		$this_month_en = $now->format('F');
		$last_month_en = $now->modify('first day of last month')->format('F');
		$this_month_key = 'TS_MONTH_' . strtoupper($this_month_en);
		$last_month_key = 'TS_MONTH_' . strtoupper($last_month_en);
		$this_month_name = $this->user->lang($this_month_key);
		$last_month_name = $this->user->lang($last_month_key);
		if ($this_month_name === $this_month_key)
		{
			$this_month_name = $this_month_en;
		}
		if ($last_month_name === $last_month_key)
		{
			$last_month_name = $last_month_en;
		}

		$this->template->assign_vars([
			'DISPLAY_TOP_STATS_CUSTOM' => !empty($this->config['display_top_stats_custom']),
			'TS_THIS_MONTH_NAME' => $this_month_name,
			'TS_LAST_MONTH_NAME' => $last_month_name,
			'TSMVT_NUMBER' => (int) ($this->config['tsmvt_numberc'] ?? 0),
			'TSMRT_NUMBER' => (int) ($this->config['tsmrt_numberc'] ?? 0),
			'TSMAF_NUMBER' => (int) ($this->config['tsmaf_numberc'] ?? 0),
			'TSMAU_NUMBER' => (int) ($this->config['tsmau_numberc'] ?? 0),
			'TSLVB_NUMBER' => (int) ($this->config['tslvb_numberc'] ?? 0),
			'TSLRU_NUMBER' => (int) ($this->config['tslru_numberc'] ?? 0),
			'TSTTM_NUMBER' => (int) ($this->config['tsttm_numberc'] ?? 0),
			'TSTLM_NUMBER' => (int) ($this->config['tstlm_numberc'] ?? 0),
		]);

		// Restore original config values
		foreach ($keys as $k)
		{
			if ($backup[$k] === null)
			{
				unset($this->config[$k]);
			}
			else
			{
				$this->config[$k] = $backup[$k];
			}
		}
	}

	/**
	 * Fetch and assign most viewed topics (optimized)
	 */
	private function assign_most_viewed_topics(array $flist): void
	{
		$number = $this->pick('tsmvt_number');
		if ($number <= 0 || empty($flist))
		{
			return;
		}

		// crc32 used for cache key generation only (not security-sensitive)
		$flist_hash = crc32(implode(',', $flist));
		$cache_key = '_ts_viewed_' . $flist_hash . '_' . $number . '_' . $this->vis_suffix();
		$most_viewed = $this->cache->get($cache_key);

		if ($most_viewed === false)
		{
			$sql = 'SELECT t.topic_id, t.forum_id, t.topic_title, t.topic_views, t.topic_time,
						t.topic_first_poster_name, t.topic_first_poster_colour, t.topic_poster
					FROM ' . TOPICS_TABLE . ' t
					WHERE ' . $this->db->sql_in_set('t.forum_id', $flist) . '
						AND ' . $this->get_vis_sql() . '
						AND t.topic_moved_id = 0
					ORDER BY t.topic_views DESC, t.topic_time DESC, t.topic_id DESC';

			$result = $this->db->sql_query_limit($sql, $number);
			$most_viewed = $this->db->sql_fetchrowset($result);
			$this->db->sql_freeresult($result);

			$this->cache->put($cache_key, $most_viewed, self::CACHEDAY);
		}

		$this->assign_topic_rows($most_viewed, 'most_viewed', 'topic_views', $number);
	}

	/**
	 * Fetch and assign most replied topics (optimized)
	 */
	private function assign_most_replied_topics(array $flist): void
	{
		$number = $this->pick('tsmrt_number');
		if ($number <= 0 || empty($flist))
		{
			return;
		}

		// crc32 used for cache key generation only (not security-sensitive)
		$flist_hash = crc32(implode(',', $flist));
		$cache_key = '_ts_replied_' . $flist_hash . '_' . $number . '_' . $this->vis_suffix();
		$most_replied = $this->cache->get($cache_key);

		if ($most_replied === false)
		{
			$sql = 'SELECT t.topic_id, t.forum_id, t.topic_title, (t.topic_posts_approved - 1) AS topic_replies,
						t.topic_time, t.topic_first_poster_name, t.topic_first_poster_colour, t.topic_poster
					FROM ' . TOPICS_TABLE . ' t
					WHERE ' . $this->db->sql_in_set('t.forum_id', $flist) . '
						AND ' . $this->get_vis_sql() . '
						AND t.topic_moved_id = 0
					ORDER BY t.topic_posts_approved DESC, t.topic_time DESC, t.topic_id DESC';

			$result = $this->db->sql_query_limit($sql, $number);
			$most_replied = $this->db->sql_fetchrowset($result);
			$this->db->sql_freeresult($result);

			$this->cache->put($cache_key, $most_replied, self::CACHEDAY);
		}

		$this->assign_topic_rows($most_replied, 'most_replied', 'topic_replies', $number);
	}

	/**
	 * Assign topic rows to template block.
	 * Generic helper for most viewed/replied topics to reduce code duplication.
	 * 
	 * @param array $rows Topic rows from database
	 * @param string $block_name Template block name
	 * @param string $stat_key Row key containing the statistic (views/replies)
	 * @param int $limit Maximum items to display
	 * @return void
	 */
	private function assign_topic_rows(array $rows, string $block_name, string $stat_key, int $limit): void
	{
		$rows = array_slice($rows, 0, $limit);
		$viewtopic_base = "{$this->root_path}viewtopic.{$this->php_ext}";

		foreach ($rows as $row)
		{
			$this->template->assign_block_vars($block_name, [
				'TOPIC_ID' => $row['topic_id'],
				'TOPIC_TITLE' => $row['topic_title'],
				strtoupper($stat_key) => $this->number_helper->format_number((float) ($row[$stat_key] ?? 0)),
				'TOPIC_TIME' => $this->user->format_date($row['topic_time']),
				'USER_FULL' => get_username_string('full', $row['topic_poster'], $row['topic_first_poster_name'], $row['topic_first_poster_colour']),
				'U_FIRST_TOPIC' => append_sid($viewtopic_base, 't=' . $row['topic_id']),
			]);
		}
	}

	/**
	 * Fetch and assign most active users (optimized)
	 */
	private function assign_most_active_users(): void
	{
		$number = $this->pick('tsmau_number');
		if ($number <= 0)
		{
			return;
		}

		$cache_key = '_ts_active_users_' . $number;
		$active_users = $this->cache->get($cache_key);

		if ($active_users === false)
		{
			$sql = 'SELECT user_id, username, user_posts, user_colour, user_regdate
					FROM ' . USERS_TABLE . '
					WHERE user_inactive_time = 0
						AND user_type NOT IN (' . USER_INACTIVE . ', ' . USER_IGNORE . ')
					ORDER BY user_posts DESC, user_id DESC';
			$result = $this->db->sql_query_limit($sql, $number);
			$active_users = $this->db->sql_fetchrowset($result);
			$this->db->sql_freeresult($result);
			$this->cache->put($cache_key, $active_users, self::CACHEDAY);
		}

		$active_users = array_slice($active_users, 0, $number);
		$total_posts = $this->get_total_posts();
		$search_base = "{$this->root_path}search.{$this->php_ext}";

		foreach ($active_users as $row)
		{
			$percent = ((float) $row['user_posts'] * 100.0) / $total_posts;
			$this->template->assign_block_vars('most_active_users', [
				'USER_FULL' => get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']),
				'USER_POST_SEARCH' => append_sid($search_base, 'author_id=' . $row['user_id'] . '&amp;sr=posts'),
				'USER_POST_PERCENT' => '(' . $this->number_helper->format_number($percent) . '%)',
				'USER_REG' => $this->user->format_date($row['user_regdate']),
				'USER_POSTS' => $this->number_helper->format_number((float) $row['user_posts']),
			]);
		}

		// Pad with NO_DATA
		for ($i = count($active_users); $i < $number; $i++)
		{
			$this->template->assign_block_vars('most_active_users', [
				'USER_FULL' => $this->user->lang('NO_DATA'),
				'USER_POST_SEARCH' => '',
				'USER_POST_PERCENT' => '',
				'USER_REG' => '',
				'USER_POSTS' => '',
			]);
		}
	}

	/**
	 * Fetch and assign most active forums (optimized)
	 */
	private function assign_most_active_forums(array $flist): void
	{
		$number = $this->pick('tsmaf_number');
		if ($number <= 0)
		{
			return;
		}

		// Remove forum_id 0 and sort
		$flist = array_values(array_filter($flist, static function($f) { return $f > 0; }));
		if (empty($flist))
		{
			return;
		}

		// crc32 used for cache key generation only (not security-sensitive)
		$flist_hash = crc32(implode(',', $flist));
		$cache_key = '_ts_active_forums_' . $flist_hash . '_' . $number;
		$active_forums = $this->cache->get($cache_key);

		if ($active_forums === false)
		{
			$sql = 'SELECT forum_id, forum_name, forum_posts_approved
					FROM ' . FORUMS_TABLE . '
					WHERE ' . $this->db->sql_in_set('forum_id', $flist) . '
						AND forum_type = ' . FORUM_POST . '
					ORDER BY forum_posts_approved DESC, forum_id ASC';
			$result = $this->db->sql_query_limit($sql, $number);
			$active_forums = $this->db->sql_fetchrowset($result);
			$this->db->sql_freeresult($result);
			$this->cache->put($cache_key, $active_forums, self::CACHEDAY);
		}

		$active_forums = array_slice($active_forums, 0, $number);
		$total_posts = $this->get_total_posts();
		$viewforum_base = "{$this->root_path}viewforum.{$this->php_ext}";

		foreach ($active_forums as $row)
		{
			$percent = ((float) $row['forum_posts_approved'] * 100.0) / $total_posts;
			$this->template->assign_block_vars('most_active_forums', [
				'FORUM_URL' => append_sid($viewforum_base, 'f=' . (int) $row['forum_id']),
				'FORUM_POST_PERCENT' => '(' . $this->number_helper->format_number($percent) . '%)',
				'FORUM_ID' => (int) $row['forum_id'],
				'FORUM_NAME' => $row['forum_name'],
				'FORUM_POSTS' => $this->number_helper->format_number((float) $row['forum_posts_approved']),
			]);
		}

		// Pad with NO_DATA
		for ($i = count($active_forums); $i < $number; $i++)
		{
			$this->template->assign_block_vars('most_active_forums', [
				'FORUM_URL' => '',
				'FORUM_POST_PERCENT' => '',
				'FORUM_ID' => 0,
				'FORUM_NAME' => $this->user->lang('NO_DATA'),
				'FORUM_POSTS' => '',
			]);
		}
	}

	/**
	 * Fetch and assign last visited bots (optimized)
	 */
	private function assign_last_visited_bots(): void
	{
		$number = $this->pick('tslvb_number');
		if ($number <= 0)
		{
			return;
		}

		$cache_key = '_ts_last_bots_' . $number;
		$last_bots = $this->cache->get($cache_key);

		if ($last_bots === false)
		{
			$sql = 'SELECT user_id, username, user_lastvisit, user_colour
					FROM ' . USERS_TABLE . '
					WHERE user_type = ' . USER_IGNORE . '
						AND user_id <> ' . ANONYMOUS . '
						AND user_lastvisit > 0
					ORDER BY user_lastvisit DESC';
			$result = $this->db->sql_query_limit($sql, $number);
			$last_bots = $this->db->sql_fetchrowset($result);
			$this->db->sql_freeresult($result);
			$this->cache->put($cache_key, $last_bots, self::CACHEMIN);
		}

		$last_bots = array_slice($last_bots, 0, $number);

		foreach ($last_bots as $bot)
		{
			$this->template->assign_block_vars('last_visited_bots', [
				'USER_FULL' => get_username_string('full', $bot['user_id'], $bot['username'], $bot['user_colour']),
				'USER_LAST_VISIT' => $this->user->format_date($bot['user_lastvisit']),
			]);
		}

		// Pad with NO_DATA
		for ($i = count($last_bots); $i < $number; $i++)
		{
			$this->template->assign_block_vars('last_visited_bots', [
				'USER_FULL' => $this->user->lang('NO_DATA'),
				'USER_LAST_VISIT' => '',
			]);
		}
	}

	/**
	 * Fetch and assign last registered users (optimized)
	 */
	private function assign_last_registered_users(): void
	{
		$number = $this->pick('tslru_number');
		if ($number <= 0)
		{
			return;
		}

		$cache_key = '_ts_last_users_' . $number;
		$last_users = $this->cache->get($cache_key);

		if ($last_users === false)
		{
			$sql = 'SELECT user_id, username, user_colour, user_regdate
					FROM ' . USERS_TABLE . '
					WHERE user_inactive_time = 0
						AND user_type NOT IN (' . USER_INACTIVE . ', ' . USER_IGNORE . ')
					ORDER BY user_regdate DESC';
			$result = $this->db->sql_query_limit($sql, $number);
			$last_users = $this->db->sql_fetchrowset($result);
			$this->db->sql_freeresult($result);
			$this->cache->put($cache_key, $last_users, self::CACHEMIN);
		}

		$last_users = array_slice($last_users, 0, $number);

		foreach ($last_users as $user)
		{
			$this->template->assign_block_vars('last_registered_user', [
				'USER_FULL' => get_username_string('full', $user['user_id'], $user['username'], $user['user_colour']),
				'USER_REGISTERED' => $this->user->format_date($user['user_regdate']),
			]);
		}

		// Pad with NO_DATA
		for ($i = count($last_users); $i < $number; $i++)
		{
			$this->template->assign_block_vars('last_registered_user', [
				'USER_FULL' => $this->user->lang('NO_DATA'),
				'USER_REGISTERED' => '',
			]);
		}
	}

	/**
	 * Calculate cache TTL for monthly post data based on ACP settings.
	 * 
	 * Supports flexible caching:
	 * - 0 = No cache (real-time, not recommended for large boards)
	 * - 1-8 = Cache for N hours
	 * - -1 = Cache until end of day (resets at midnight)
	 * 
	 * Past months always cache for 1 year since data won't change.
	 * 
	 * @param int $start_ts Start timestamp of the period
	 * @param int $end_ts End timestamp of the period
	 * @return int Cache TTL in seconds
	 */
	private function month_ttl(int $start_ts, int $end_ts): int
	{
		$now = $this->board_now();
		$now_ts = $now->getTimestamp();
		
		// If the period has ended, data is complete - cache for 1 year
		if ($end_ts <= $now_ts)
		{
			return self::CACHEYEAR;
		}
		
		// Current month - use admin-configured cache duration
		$cache_hours = (int) ($this->config['ts_topposter_cache_time'] ?? -1);
		
		// Special value: -1 = rest of day (cache until midnight)
		if ($cache_hours === -1)
		{
			$end_of_day = $now->setTime(23, 59, 59)->getTimestamp();
			$seconds_until_midnight = max(300, $end_of_day - $now_ts); // Minimum 5 minutes
			return $seconds_until_midnight;
		}
		
		// 0 = no cache (real-time)
		if ($cache_hours === 0)
		{
			return 1; // phpBB cache requires TTL > 0, so use 1 second
		}
		
		// 1-8 hours: convert to seconds
		return $cache_hours * self::CACHEHOUR;
	}

	/**
	 * Core method: Fetch and assign top posters for a time period (optimized).
	 * 
	 * OPTIMIZATIONS:
	 * - Uses post_id range to reduce scan size
	 * - Fetches more results than needed to handle user exclusions
	 * - Applies user exclusions in PHP for better cache sharing
	 * - Applies forum exclusions in SQL (can't be done post-cache)
	 * - Smart cache TTL (until end of day for current month)
	 */
	private function assign_top_posters_period(int $number, string $start_str, string $end_str, string $cache_key, string $block_name = 'top_posters_selected', bool $pad_missing = true): void
	{
		if ($number <= 0)
		{
			return;
		}

		$tz = $this->board_now()->getTimezone();
		$start = (new \DateTimeImmutable($start_str, $tz))->getTimestamp();
		$end = (new \DateTimeImmutable($end_str, $tz))->getTimestamp();

		// Forum exclusions must be applied in SQL (can't filter post-cache)
		$excluded_forum_ids = $this->get_excluded_forum_ids();
		$forum_sql = '';
		$forum_suffix = '';
		if (!empty($excluded_forum_ids))
		{
			$forum_sql = ' AND ' . $this->db->sql_in_set('p.forum_id', $excluded_forum_ids, true);
			$forum_suffix = '_xf' . crc32(implode(',', $excluded_forum_ids));
		}
		
		// Cache key includes forum exclusions (applied in SQL) but not user exclusions (applied in PHP)
		$cache_key_full = $cache_key . '_' . $number . $forum_suffix . '_v10';

		// Per-request memoization
		static $tp_memo = [];
		$cached_data = $tp_memo[$cache_key_full] ?? $this->cache->get($cache_key_full);

		if ($cached_data === false)
		{
			// OPTIMIZATION: Get post_id range for the date range first
			// This dramatically reduces the rows MySQL needs to scan
			$sql_range = 'SELECT MIN(post_id) as min_id, MAX(post_id) as max_id
				FROM ' . POSTS_TABLE . '
				WHERE post_time >= ' . (int) $start . '
					AND post_time < ' . (int) $end . '
				LIMIT 1';
			$result = $this->db->sql_query($sql_range);
			$range = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);

			if (empty($range['min_id']))
			{
				// No posts in this period
				$cached_data = [
					'top_posters' => [],
					'total_posts' => 0,
				];
			}
			else
			{
				// OPTIMIZATION: Fetch 3x the needed results to account for potential user exclusions
				// This avoids needing separate cache entries for different user exclusion combos
				$fetch_limit = $number * 3;

				// OPTIMIZED QUERY: Use post_id range to reduce scan + forum exclusions in SQL
				$sql_top = 'SELECT p.poster_id, COUNT(*) AS cnt
					FROM ' . POSTS_TABLE . ' p
					WHERE p.post_id >= ' . (int) $range['min_id'] . '
						AND p.post_id <= ' . (int) $range['max_id'] . '
						AND p.post_time >= ' . (int) $start . '
						AND p.post_time < ' . (int) $end . '
						AND p.post_visibility = ' . ITEM_APPROVED
						. $forum_sql . '
					GROUP BY p.poster_id
					ORDER BY cnt DESC';

				$result = $this->db->sql_query_limit($sql_top, $fetch_limit);
				$poster_counts = [];
				$user_ids = [];
				
				while ($r = $this->db->sql_fetchrow($result))
				{
					$uid = (int) $r['poster_id'];
					$poster_counts[$uid] = (int) $r['cnt'];
					$user_ids[] = $uid;
				}
				$this->db->sql_freeresult($result);

				// Phase 2: Bulk fetch user data
				$top_posters = [];
				if (!empty($user_ids))
				{
					$sql_users = 'SELECT u.user_id, u.username, u.user_colour, u.user_avatar,
									u.user_avatar_type, u.user_avatar_width, u.user_avatar_height
								FROM ' . USERS_TABLE . ' u
								WHERE ' . $this->db->sql_in_set('u.user_id', $user_ids);
					$result = $this->db->sql_query($sql_users);
					$user_data = $this->db->sql_fetchrowset($result);
					$this->db->sql_freeresult($result);

					// Index by user_id for O(1) lookup
					$user_map = [];
					foreach ($user_data as $user)
					{
						$user_map[(int) $user['user_id']] = $user;
					}

					// Merge data maintaining rank order
					foreach ($user_ids as $uid)
					{
						if (isset($user_map[$uid]))
						{
							$top_posters[] = $user_map[$uid] + ['total_posts' => $poster_counts[$uid]];
						}
					}
				}

				// Get total posts for percentage (excludes forums but not users for consistent totals)
				$sql_total = 'SELECT COUNT(*) AS total
					FROM ' . POSTS_TABLE . ' p
					WHERE p.post_id >= ' . (int) $range['min_id'] . '
						AND p.post_id <= ' . (int) $range['max_id'] . '
						AND p.post_time >= ' . (int) $start . '
						AND p.post_time < ' . (int) $end . '
						AND p.post_visibility = ' . ITEM_APPROVED
						. $forum_sql;
				$result = $this->db->sql_query($sql_total);
				$total_row = $this->db->sql_fetchrow($result);
				$this->db->sql_freeresult($result);

				$cached_data = [
					'top_posters' => $top_posters,
					'total_posts' => (int) ($total_row['total'] ?? 0),
				];
			}

			// Smart cache TTL: until end of day for current month, 1 year for past months
			$ttl = $this->month_ttl($start, $end);
			$this->cache->put($cache_key_full, $cached_data, $ttl);
		}

		$tp_memo[$cache_key_full] = $cached_data;

		// OPTIMIZATION: Apply user exclusions in PHP after fetching from cache
		// (Forum exclusions already applied in SQL above)
		$excluded_user_ids = $this->get_excluded_user_ids();
		
		$filtered_posters = [];
		$excluded_post_count = 0;
		
		foreach ($cached_data['top_posters'] as $poster)
		{
			$uid = (int) $poster['user_id'];
			
			// Skip excluded users
			if (in_array($uid, $excluded_user_ids, true))
			{
				$excluded_post_count += $poster['total_posts'];
				continue;
			}
			
			$filtered_posters[] = $poster;
			
			// Stop when we have enough
			if (count($filtered_posters) >= $number)
			{
				break;
			}
		}

		// Adjust total posts if users were excluded
		$total_posts = max(1, (int) $cached_data['total_posts'] - $excluded_post_count);

		// Assign to template
		foreach ($filtered_posters as $poster)
		{
			$percent = ($poster['total_posts'] * 100) / $total_posts;
			$this->template->assign_block_vars($block_name, [
				'TOTAL_POSTS' => (int) $poster['total_posts'],
				'PERCENTAGE' => '(' . $this->number_helper->format_number($percent) . '%)',
				'USER_FULL' => get_username_string('full', (int) $poster['user_id'], $poster['username'], $poster['user_colour']),
				'USER_AVATAR' => $this->render_avatar($poster),
			]);
		}

		// Pad with NO_DATA if needed
		if ($pad_missing)
		{
			for ($i = count($filtered_posters); $i < $number; $i++)
			{
				$this->template->assign_block_vars($block_name, [
					'TOTAL_POSTS' => '',
					'PERCENTAGE' => '',
					'USER_FULL' => $this->user->lang('NO_DATA'),
					'USER_AVATAR' => '',
				]);
			}
		}
	}

	/**
	 * Render user avatar HTML.
	 * Handles gallery avatars with proper path construction and escaping.
	 * 
	 * @param array $poster User data including avatar fields
	 * @return string Avatar HTML img tag
	 */
	private function render_avatar(array $poster): string
	{
		$avatar_name = (string) ($poster['user_avatar'] ?? '');
		if (empty($avatar_name))
		{
			return $this->get_default_avatar();
		}

		$avatar_type_raw = $poster['user_avatar_type'] ?? '';
		$avatar_width = (int) ($poster['user_avatar_width'] ?? 0);
		$avatar_height = (int) ($poster['user_avatar_height'] ?? 0);
		$username = $poster['username'] ?? '';

		$is_gallery = ($avatar_type_raw === 'avatar.driver.local') || 
			((is_numeric($avatar_type_raw) && (int) $avatar_type_raw === (defined('AVATAR_GALLERY') ? AVATAR_GALLERY : -1)));

		if (!$is_gallery)
		{
			$avatar_html = phpbb_get_avatar([
				'avatar' => $avatar_name,
				'avatar_type' => $avatar_type_raw,
				'avatar_width' => $avatar_width,
				'avatar_height' => $avatar_height,
			], $username);

			return $avatar_html ?: $this->get_default_avatar();
		}

		// Gallery avatar handling
		if ($avatar_width === 0 || $avatar_height === 0)
		{
			$avatar_width = (int) ($this->config['avatar_gallery_width'] ?? $this->config['avatar_max_width'] ?? 80);
			$avatar_height = (int) ($this->config['avatar_gallery_height'] ?? $this->config['avatar_max_height'] ?? 80);
		}

		$gallery_path = trim((string) ($this->config['avatar_gallery_path'] ?? 'images/avatars/gallery'), '/');
		$clean = str_replace('\\', '/', $avatar_name);
		$parts = array_filter(explode('/', $clean), static function ($p) {
			return $p !== '' && $p !== '.' && $p !== '..';
		});
		$encoded_name = implode('/', array_map('rawurlencode', $parts));

		$src = $this->get_web_root() . $gallery_path . '/' . $encoded_name;
		$w = $avatar_width > 0 ? $avatar_width : 80;
		$h = $avatar_height > 0 ? $avatar_height : 80;

		// Using phpBB's utf8_htmlspecialchars() for proper escaping
		return '<img src="' . utf8_htmlspecialchars($src) . '"' . ' alt="' . utf8_htmlspecialchars($username) . '"' . ' width="' . (int) $w . '" height="' . (int) $h . '" loading="lazy">';
	}

	/**
	 * Get default avatar HTML when user has no avatar.
	 * Cached statically for performance.
	 * 
	 * @return string Default avatar HTML img tag
	 */
	private function get_default_avatar(): string
	{
		static $default_avatar = null;
		if ($default_avatar === null)
		{
			$src = $this->get_web_root() . 'ext/stoker/topstats/styles/prosilver/theme/images/default_avatar.png';
			$default_avatar = '<img src="' . utf8_htmlspecialchars($src) . '" alt="" width="80" height="80" loading="lazy">';
		}
		return $default_avatar;
	}

	private function assign_top_posters_this_month(): void
	{
		$n = $this->pick('tsttm_number');
		if ($n <= 0) return;

		$now = $this->board_now();
		$start_str = $now->modify('first day of this month 00:00:00')->format('Y-m-d H:i:s');
		$end_str = $now->modify('first day of next month 00:00:00')->format('Y-m-d H:i:s');
		$month_key = $now->format('Y-m');

		$this->assign_top_posters_period($n, $start_str, $end_str, '_ts_tp_' . $month_key, 'top_posters_this_month');
	}

	private function assign_top_posters_last_month(): void
	{
		$n = $this->pick('tstlm_number');
		if ($n <= 0) return;

		$now = $this->board_now();
		$start_dt = $now->modify('first day of last month 00:00:00');
		$end_dt = $now->modify('first day of this month 00:00:00');
		$start_str = $start_dt->format('Y-m-d H:i:s');
		$end_str = $end_dt->format('Y-m-d H:i:s');
		$month_key = $start_dt->format('Y-m');

		$this->assign_top_posters_period($n, $start_str, $end_str, '_ts_tp_' . $month_key, 'top_posters_last_month');
	}

	public function assign_top_posters_this_month_custom(int $limit): void
	{
		if ($limit <= 0) return;

		$now = $this->board_now();
		$start_str = $now->modify('first day of this month 00:00:00')->format('Y-m-d H:i:s');
		$end_str = $now->modify('first day of next month 00:00:00')->format('Y-m-d H:i:s');
		$month_key = $now->format('Y-m');

		$this->assign_top_posters_period($limit, $start_str, $end_str, '_ts_tp_' . $month_key, 'top_posters_this_month', false);
	}

	public function assign_top_posters_last_month_custom(int $limit): void
	{
		if ($limit <= 0) return;

		$now = $this->board_now();
		$start_dt = $now->modify('first day of last month 00:00:00');
		$end_dt = $now->modify('first day of this month 00:00:00');
		$start_str = $start_dt->format('Y-m-d H:i:s');
		$end_str = $end_dt->format('Y-m-d H:i:s');
		$month_key = $start_dt->format('Y-m');

		$this->assign_top_posters_period($limit, $start_str, $end_str, '_ts_tp_' . $month_key, 'top_posters_last_month', false);
	}

	public function assign_top_posters_for_month_custom(string $ym, int $limit, string $block_name = 'top_posters_selected', bool $pad_missing = false): void
	{
		if (!preg_match('#^\d{4}-\d{2}$#', $ym) || $limit <= 0)
		{
			return;
		}

		$now = $this->board_now();
		$tz = $now->getTimezone();

		try
		{
			$start_dt = new \DateTimeImmutable($ym . '-01 00:00:00', $tz);
		}
		catch (\Exception $e)
		{
			return;
		}
		
		$end_dt = $start_dt->modify('first day of next month 00:00:00');
		$start_str = $start_dt->format('Y-m-d H:i:s');
		$end_str = $end_dt->format('Y-m-d H:i:s');

		$this->assign_top_posters_period($limit, $start_str, $end_str, '_ts_tp_' . $ym, $block_name, $pad_missing);
	}
}
