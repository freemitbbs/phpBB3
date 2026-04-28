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
 * Service responsible for fetching and assigning recent active topics.
 * Uses per-request memoization for ACL checks.
 * Optional caching configurable via ACP (0 = disabled, 1-30 minutes).
 */
class recent_manager
{
	/** Setting the max limit */
	private const MAX_LIMIT = 50;

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

	/** @var array|null Cached readable forum IDs */
	private $readable_forum_ids = null;

	/** @var string|null Cached visibility SQL fragment */
	private $vis_sql_cache = null;

	/** @var string|null Cached viewtopic base URL */
	private $vt_base_cache = null;

	/** @var string|null Cached viewforum base URL */
	private $vf_base_cache = null;

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
	 * Display recent topics on index page.
	 */
	public function display_recent(data $event): void
	{
		if (empty($this->config['display_top_recent_index']))
		{
			return;
		}

		$active_limit = (int) ($this->config['tsrat_number'] ?? 0);
		$this->render_recent($active_limit);
		$this->assign_recent_vars($active_limit, 'index');
	}

	/**
	 * Display recent topics on portal page.
	 */
	public function display_recent_portal(data $event): void
	{
		if (empty($this->config['display_top_recent_portal']))
		{
			return;
		}

		$active_limit = (int) ($this->config['tsrat_numberp'] ?? $this->config['tsrat_number_portal'] ?? $this->config['tsrat_number'] ?? 0);

		$this->render_recent($active_limit);
		$this->assign_recent_vars($active_limit, 'portal');
	}

	/**
	 * Display recent topics on custom page.
	 */
	public function display_recent_custom(): void
	{
		if (empty($this->config['display_top_recent_custom']))
		{
			return;
		}

		$active_limit = (int) ($this->config['tsrat_numberc'] ?? 0);
		$this->render_recent($active_limit);
		$this->assign_recent_vars($active_limit, 'custom');
	}

	/**
	 * Core renderer: Fetch recent topics via optimized JOIN query.
	 * Implements optional caching based on ACP settings.
	 */
	private function render_recent(int $limit): void
	{
		$limit = max(0, min($limit, self::MAX_LIMIT));
		if ($limit === 0)
		{
			return;
		}

		$forum_ids = $this->get_readable_forum_ids();
		if (empty($forum_ids))
		{
			return;
		}

		// Check if caching is enabled
		$cache_time = (int) ($this->config['ts_recent_cache_time'] ?? 0);
		$use_cache = ($cache_time > 0);

		// Build cache key if caching is enabled
		$cache_key = '';
		if ($use_cache)
		{
			$vis_suffix = $this->get_vis_suffix();
			// crc32 used for cache key generation only (not security-sensitive)
			$forum_hash = crc32(implode(',', $forum_ids));
			$cache_key = '_ts_recent_' . $forum_hash . '_' . $limit . '_' . $vis_suffix;
			
			// Try to get from cache
			$cached_rows = $this->cache->get($cache_key);
			if ($cached_rows !== false && is_array($cached_rows))
			{
				$this->assign_recent_rows($cached_rows);
				return;
			}
		}

		// Single optimized query with forum name JOIN
		$sql = 'SELECT t.forum_id, t.topic_id, t.topic_title, t.topic_time, t.topic_views,
				t.topic_poster, t.topic_posts_approved, t.topic_first_poster_name,
				t.topic_first_poster_colour, t.topic_last_post_id, t.topic_last_poster_name,
				t.topic_last_poster_colour, t.topic_last_post_time, t.topic_last_poster_id,
				t.topic_status, t.topic_type,
				f.forum_name
			FROM ' . TOPICS_TABLE . ' t
			LEFT JOIN ' . FORUMS_TABLE . ' f ON f.forum_id = t.forum_id
			WHERE ' . $this->db->sql_in_set('t.forum_id', $forum_ids) . '
				AND ' . $this->get_vis_sql() . '
				AND t.topic_moved_id = 0
			ORDER BY t.topic_last_post_time DESC';

		$result = $this->db->sql_query_limit($sql, $limit);
		if (!$result)
		{
			return;
		}

		// Fetch all rows at once
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		if (empty($rows))
		{
			return;
		}

		// Store in cache if caching is enabled
		if ($use_cache && !empty($cache_key))
		{
			$ttl = $cache_time * 60; // Convert minutes to seconds
			$this->cache->put($cache_key, $rows, $ttl);
		}

		// Batch process all rows
		$this->assign_recent_rows($rows);
	}

	/**
	 * Build visibility fingerprint for cache keys.
	 * Based on user's approval and soft-delete permissions.
	 * 
	 * @return string Cache key suffix (e.g., 'U1S0')
	 */
	private function get_vis_suffix(): string
	{
		$u = $this->auth->acl_getf_global('m_approve') ? 'U1' : 'U0';
		$s = $this->auth->acl_getf_global('m_softdelete') ? 'S1' : 'S0';
		return $u . $s;
	}

	/**
	 * Batch assign recent topic rows to template with read/unread icons.
	 * Computes topic tracking per-user (never cached), then determines
	 * the prosilver icon CSS class for each topic.
	 * 
	 * @param array $rows Topic rows from database or cache
	 */
	private function assign_recent_rows(array $rows): void
	{
		$vt_base = $this->get_vt_base();
		$vf_base = $this->get_vf_base();

		// Compute read/unread tracking (per-user, not cached)
		$tracking = $this->get_topic_tracking_bulk($rows);
		$user_id = (int) $this->user->data['user_id'];
		$hot_threshold = (int) ($this->config['hot_threshold'] ?? 0);

		foreach ($rows as $row)
		{
			$forum_id = (int) $row['forum_id'];
			$topic_id = (int) $row['topic_id'];
			$last_post_id = (int) $row['topic_last_post_id'];
			$topic_poster = (int) $row['topic_poster'];
			$last_poster_id = (int) $row['topic_last_poster_id'];
			$last_post_time = (int) $row['topic_last_post_time'];

			$unread = isset($tracking[$topic_id]) && $last_post_time > $tracking[$topic_id];
			$icon_class = $this->get_topic_icon_class($row, $unread, $user_id, $hot_threshold);

			$this->template->assign_block_vars('recent_active', [
				'TOPIC_TITLE' => (string) $row['topic_title'],
				'TOPIC_TIME' => $this->user->format_date((int) $row['topic_time']),
				'TOPIC_VIEWS' => $this->number_helper->format_number((float) $row['topic_views']),
				'TOPIC_REPLIES' => $this->number_helper->format_number((float) $row['topic_posts_approved']),
				'USER_FULL_FIRST' => get_username_string('full', $topic_poster, $row['topic_first_poster_name'], $row['topic_first_poster_colour']),
				'USER_FULL_LAST' => get_username_string('full', $last_poster_id, $row['topic_last_poster_name'], $row['topic_last_poster_colour']),
				'TOPIC_LAST_POST_TIME' => $this->user->format_date($last_post_time),
				'U_FIRST_TOPIC' => append_sid($vt_base, 't=' . $topic_id),
				'U_LAST_TOPIC' => append_sid($vt_base, 'p=' . $last_post_id) . '#p' . $last_post_id,
				'FORUM_NAME' => (string) ($row['forum_name'] ?? ''),
				'FORUM_URL' => append_sid($vf_base, 'f=' . $forum_id),
				'TOPIC_IMG_STYLE' => $icon_class,
				'TOPIC_FOLDER_IMG_ALT' => $unread ? $this->user->lang('UNREAD_POSTS') : $this->user->lang('NO_UNREAD_POSTS'),
				'S_UNREAD_TOPIC' => $unread,
			]);
		}
	}

	/**
	 * Bulk-fetch topic tracking data with 2 queries max.
	 * Returns the effective mark time per topic_id. A topic is unread
	 * when topic_last_post_time > mark time.
	 *
	 * Uses user_lastmark (mark all forums read) as fallback, not user_regdate.
	 * Falls back to get_complete_topic_tracking() when DB tracking is disabled.
	 *
	 * @param array $rows Topic rows (must contain topic_id, forum_id)
	 * @return array<int, int> Map of topic_id => effective mark timestamp
	 */
	private function get_topic_tracking_bulk(array $rows): array
	{
		$user_id = (int) $this->user->data['user_id'];

		// Guests without cookie tracking: mark everything as read
		if ($user_id === ANONYMOUS && empty($this->config['load_anon_lastread']))
		{
			$tracking = [];
			foreach ($rows as $row)
			{
				$tracking[(int) $row['topic_id']] = PHP_INT_MAX;
			}
			return $tracking;
		}

		// Cookie-based tracking (guests with load_anon_lastread, or load_db_lastread disabled):
		// fall back to phpBB's built-in function which handles cookies
		if ($user_id === ANONYMOUS || empty($this->config['load_db_lastread']))
		{
			$forum_topics = [];
			foreach ($rows as $row)
			{
				$forum_topics[(int) $row['forum_id']][] = (int) $row['topic_id'];
			}

			$tracking = [];
			foreach ($forum_topics as $forum_id => $topic_ids)
			{
				$forum_tracking = get_complete_topic_tracking($forum_id, $topic_ids);
				foreach ($forum_tracking as $topic_id => $mark_time)
				{
					$tracking[(int) $topic_id] = (int) $mark_time;
				}
			}
			return $tracking;
		}

		// DB tracking: 2 bulk queries for all topics/forums at once
		$topic_ids = [];
		$forum_ids = [];
		foreach ($rows as $row)
		{
			$topic_ids[] = (int) $row['topic_id'];
			$forum_ids[(int) $row['forum_id']] = true;
		}
		$forum_ids = array_keys($forum_ids);

		// Query 1: Per-topic mark times
		$topic_marks = [];
		$sql = 'SELECT topic_id, mark_time
			FROM ' . TOPICS_TRACK_TABLE . '
			WHERE user_id = ' . $user_id . '
				AND ' . $this->db->sql_in_set('topic_id', $topic_ids);
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$topic_marks[(int) $row['topic_id']] = (int) $row['mark_time'];
		}
		$this->db->sql_freeresult($result);

		// Query 2: Per-forum mark times
		$forum_marks = [];
		$sql = 'SELECT forum_id, mark_time
			FROM ' . FORUMS_TRACK_TABLE . '
			WHERE user_id = ' . $user_id . '
				AND ' . $this->db->sql_in_set('forum_id', $forum_ids);
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$forum_marks[(int) $row['forum_id']] = (int) $row['mark_time'];
		}
		$this->db->sql_freeresult($result);

		// Fallback: user_lastmark = "mark all forums read" timestamp
		$user_lastmark = (int) $this->user->data['user_lastmark'];

		// Build effective mark time per topic
		$tracking = [];
		foreach ($rows as $row)
		{
			$tid = (int) $row['topic_id'];
			$fid = (int) $row['forum_id'];

			$mark = max(
				$topic_marks[$tid] ?? 0,
				$forum_marks[$fid] ?? 0,
				$user_lastmark
			);
			$tracking[$tid] = $mark;
		}

		return $tracking;
	}

	/**
	 * Determine prosilver icon CSS class for a topic.
	 * Replicates phpBB core topic_status() logic.
	 *
	 * @param array $row   Topic row with topic_status, topic_type, topic_posts_approved, topic_poster
	 * @param bool  $unread Whether the topic has unread posts
	 * @param int   $user_id Current user ID (for _mine suffix)
	 * @param int   $hot_threshold Config hot_threshold value
	 * @return string CSS class (e.g., 'topic_read', 'topic_unread_hot', 'global_unread')
	 */
	private function get_topic_icon_class(array $row, bool $unread, int $user_id, int $hot_threshold): string
	{
		$status = (int) ($row['topic_status'] ?? ITEM_UNLOCKED);
		$type = (int) ($row['topic_type'] ?? POST_NORMAL);
		$replies = max(0, (int) ($row['topic_posts_approved'] ?? 1) - 1);
		$read_status = $unread ? 'unread' : 'read';

		// Global announcement — overrides all
		if ($type === POST_GLOBAL)
		{
			return $status === ITEM_LOCKED
				? 'global_locked'
				: 'global_' . $read_status;
		}

		// Announcement — overrides all
		if ($type === POST_ANNOUNCE)
		{
			return $status === ITEM_LOCKED
				? 'announce_locked'
				: 'announce_' . $read_status;
		}

		// Sticky — uses base folder (locked/hot applies)
		if ($type === POST_STICKY)
		{
			if ($status === ITEM_LOCKED)
			{
				return 'sticky_' . $read_status . '_locked';
			}
			return 'sticky_' . $read_status;
		}

		// Normal topic
		$mine = ((int) ($row['topic_poster'] ?? 0) === $user_id) ? '_mine' : '';

		if ($status === ITEM_LOCKED)
		{
			return 'topic_' . $read_status . '_locked' . $mine;
		}

		if ($hot_threshold > 0 && $replies >= $hot_threshold)
		{
			return 'topic_' . $read_status . '_hot' . $mine;
		}

		return 'topic_' . $read_status . $mine;
	}

	/**
	 * Assign template vars for JS scrolling and limits (optimized).
	 * All config reads batched together, limits clamped to MAX_LIMIT.
	 */
	private function assign_recent_vars(int $active_limit = 0, string $scope = 'index'): void
	{
		// Batch config reads
		$config_values = [
			'ts_jsspeed' => (int) ($this->config['ts_jsspeed'] ?? 400),
			'ts_jsinterval' => (int) ($this->config['ts_jsinterval'] ?? 4000),
			'tsrat_numberp' => (int) ($this->config['tsrat_numberp'] ?? $this->config['tsrat_number_portal'] ?? 0),
			'tsrat_numberc' => (int) ($this->config['tsrat_numberc'] ?? 0),
			'ts_jsscroll_direction' => !empty($this->config['ts_jsscroll_direction']),
			'ts_jsscroll_pause' => !empty($this->config['ts_jsscroll_pause']),
			'ts_jsscroll_navigation' => !empty($this->config['ts_jsscroll_navigation']),
			's_ts_jsscroll' => !empty($this->config['ts_jsscroll']),
			'display_recent_index' => !empty($this->config['display_top_recent_index']),
			'display_recent_portal' => !empty($this->config['display_top_recent_portal']),
		];

		// Clamp all limits
		$active_limit_clamped = max(0, min($active_limit, self::MAX_LIMIT));
		$portal_limit = max(0, min($config_values['tsrat_numberp'], self::MAX_LIMIT));
		$custom_limit = max(0, min($config_values['tsrat_numberc'], self::MAX_LIMIT));

		$this->template->assign_vars([
			'JSSCROLL_SPEED' => $config_values['ts_jsspeed'],
			'JSSCROLL_INTERVAL' => $config_values['ts_jsinterval'],
			'TSRAT_NUMBER' => $active_limit_clamped,
			'TSRAT_NUMBERP' => $portal_limit,
			'TSRAT_NUMBERC' => $custom_limit,
			'TS_JSSCROLL_DIRECTION' => $config_values['ts_jsscroll_direction'],
			'TS_JSSCROLL_PAUSE' => $config_values['ts_jsscroll_pause'],
			'TS_JSSCROLL_NAVIGATION' => $config_values['ts_jsscroll_navigation'],
			'S_TS_JSSCROLL' => $config_values['s_ts_jsscroll'],
			'DISPLAY_RECENT_INDEX' => $config_values['display_recent_index'],
			'DISPLAY_RECENT_PORTAL' => $config_values['display_recent_portal'],
			'TS_SCOPE' => $scope,
		]);
	}

	/**
	 * Get cached visibility SQL fragment.
	 * Computed once per request for efficiency.
	 * 
	 * @return string Visibility SQL clause
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
	 * Get cached viewtopic base URL.
	 * Computed once per request for link building.
	 * 
	 * @return string Base URL for viewtopic.php
	 */
	private function get_vt_base(): string
	{
		if ($this->vt_base_cache === null)
		{
			$this->vt_base_cache = "{$this->root_path}viewtopic.{$this->php_ext}";
		}
		return $this->vt_base_cache;
	}

	/**
	 * Get cached viewforum base URL.
	 * Computed once per request for link building.
	 * 
	 * @return string Base URL for viewforum.php
	 */
	private function get_vf_base(): string
	{
		if ($this->vf_base_cache === null)
		{
			$this->vf_base_cache = "{$this->root_path}viewforum.{$this->php_ext}";
		}
		return $this->vf_base_cache;
	}

	/**
	 * Get list of forum IDs the current user can read.
	 * Includes forum_id 0 for global announcements. Cached per request.
	 * 
	 * @return array<int> Forum IDs with read permission
	 */
	private function get_readable_forum_ids(): array
	{
		if ($this->readable_forum_ids !== null)
		{
			return $this->readable_forum_ids;
		}

		$acl = $this->auth->acl_getf('f_read', true);
		$acl = is_array($acl) ? $acl : [];
		$acl[0] = true;

		$this->readable_forum_ids = array_keys($acl);
		return $this->readable_forum_ids;
	}
}
