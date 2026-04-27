<?php
/**
 *
 * Recent Topics NG. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2022, IMC, https://github.com/IMC-GER / LukeWCS, https://github.com/LukeWCS
 * @copyright (c) 2017, Sajaki, https://www.avathar.be
 * @copyright (c) 2015, PayBas
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 * Based on the original NV Recent Topics by Joas Schilling (nickvergessen)
 */

namespace imcger\recenttopicsng\core;

class rtng_functions
{
	public const CACHE_RTNG_ENABLED_FORUM_IDS = '_imcger_recenttopicsng_enabled_forum_ids';
	private const CACHE_SECONDS = 86400;

	private array $user_setting;
	private int $topics_start;
	private int $topics_per_page;
	private int $topics_page_number;
	private ?array $toptopics_index_topic_ids;
	private ?array $index_topic_ids_for_dedupe;
	private array $displayed_topic_ids_for_dedupe;
	private ?array $forum_id_list;
	private ?array $prepared_index_topic_list_for_display;
	private ?array $foe_user_id_map;

	public function __construct
	(
		protected \phpbb\auth\auth $auth,
		protected \phpbb\cache\service $cache,
		protected \phpbb\config\config $config,
		protected \phpbb\language\language $language,
		protected \phpbb\content_visibility $content_visibility,
		protected \phpbb\db\driver\driver_interface $db,
		protected \phpbb\event\dispatcher_interface $dispatcher,
		protected \phpbb\pagination $pagination,
		protected \phpbb\request\request_interface $request,
		protected \phpbb\template\template $template,
		protected \phpbb\user $user,
		protected \imcger\recenttopicsng\controller\controller_common $ctrl_common,
		protected $root_path,
		protected $phpEx,
		protected $toptopics_ranker = null,
		protected $toptopics_listener = null,
	)
	{
		$this->topics_start			= 0;
		$this->topics_per_page		= 0;
		$this->topics_page_number	= 0;
		$this->toptopics_index_topic_ids = null;
		$this->index_topic_ids_for_dedupe = null;
		$this->displayed_topic_ids_for_dedupe = [];
		$this->forum_id_list = null;
		$this->prepared_index_topic_list_for_display = null;
		$this->foe_user_id_map = null;
	}

	/**
	 * Set number of recent topics per page
	 */
	public function set_topics_per_page(int $topics_number): bool
	{
		if ($topics_number > 0)
		{
			$this->topics_per_page = $topics_number;
			return true;
		}

		return false;
	}

	/**
	 * Set the number of pages for recent topics
	 */
	public function set_topics_page_number(int $page_number): bool
	{
		if ($page_number > 0)
		{
			$this->topics_page_number = $page_number;
			return true;
		}

		return false;
	}

	/**
	 * Display recent topics
	 */
	public function display_recent_topics(string $tpl_loopname = 'rtng_topics'): void
	{
		$this->displayed_topic_ids_for_dedupe[$tpl_loopname] = [];
		$this->user_setting = $this->ctrl_common->get_user_setting();

		// can view rtng ?
		if (!($this->user_setting['user_rtng_enable'] && $this->auth->acl_get('u_rtng_view')))
		{
			return;
		}

		if (!function_exists('topic_status'))
		{
			include($this->root_path . 'includes/functions_display.' . $this->phpEx);
		}

		$rtng_start = $this->request->variable($tpl_loopname . '_start', 0);

		$excluded_topics = $this->get_effective_excluded_topics($tpl_loopname, [], $rtng_start);

		$min_topic_level = $this->config['rtng_min_topic_level'];

		$this->language->add_lang('rtng_common', 'imcger/recenttopicsng');
		$this->template->assign_vars([
				'S_RTNG_LOCATION_TOP'	 => $this->user_setting['user_rtng_location'] == 'RTNG_TOP',
				'S_RTNG_LOCATION_BOTTOM' => $this->user_setting['user_rtng_location'] == 'RTNG_BOTTOM',
				'S_RTNG_LOCATION_SIDE'	 => $this->user_setting['user_rtng_location'] == 'RTNG_SIDE',
				strtoupper($tpl_loopname) . '_DISPLAY' => true,
			]
		);

		$forum_id_list = $this->getforumlist();

		// No forums to display
		if (count($forum_id_list) == 0)
		{
			return;
		}

		// When not 0, they set by page controller
		if (!$this->topics_per_page)
		{
			$this->topics_per_page = $this->user_setting['user_rtng_index_topics_qty'];
		}

		// When not 0, they set by page controller
		if (!$this->topics_page_number)
		{
			$this->topics_page_number = $this->user_setting['user_rtng_index_page_qty'];
		}

		// limit number of pages to be shown
		// compute as product of topics per page and max number of pages.
		if ((int) $this->config['rtng_all_topics'] == 0)
		{
			$total_topics_limit = $this->topics_per_page * $this->topics_page_number;
		}
		else
		{
			$sql_array = $this->get_allowed_topics_sql($excluded_topics, $min_topic_level, $forum_id_list);
			$count_sql_array = $sql_array;
			$count_sql_array['SELECT'] = 'COUNT(t.topic_id) as topic_count';
			unset($count_sql_array['ORDER_BY']);
			unset($count_sql_array['LEFT_JOIN']);
			$sql = $this->db->sql_build_query('SELECT', $count_sql_array);

			$result = $this->db->sql_query($sql);
			$total_topics_limit = (int) $this->db->sql_fetchfield('topic_count');
			$this->db->sql_freeresult($result);
		}

		// These variables are defined in the gettopiclist() function.
		$obtain_icons = false;
		$forums		  = [];
		$topic_list	  = [];
		$topic_rows	  = [];

		// When 0 there are no topics to display
		if ($total_topics_limit < 1)
		{
			return;
		}

		$topic_list_cache_key = $this->build_topic_list_cache_key($tpl_loopname, $rtng_start, $total_topics_limit, $excluded_topics, $forum_id_list);
		$prepared_topic_list = $this->get_prepared_index_topic_list_for_display($topic_list_cache_key);
		if ($prepared_topic_list !== null)
		{
			$obtain_icons = (bool) $prepared_topic_list['obtain_icons'];
			$forums = $prepared_topic_list['forums'];
			$topic_list = $prepared_topic_list['topic_list'];
			$topic_rows = $prepared_topic_list['topic_rows'];
			$topics_count = (int) $prepared_topic_list['topics_count'];
			$this->topics_start = (int) $prepared_topic_list['topics_start'];
		}
		else
		{
			$topics_count = $this->gettopiclist($obtain_icons, $forums, $rtng_start, $total_topics_limit, $excluded_topics, $forum_id_list, $topic_list, $topic_rows);
		}

		// Return if there are no topics available to display.
		if (count($topic_list) == 0)
		{
			return;
		}

		// Grab icons
		$icons = [];
		if ($obtain_icons)
		{
			$icons = $this->cache->obtain_icons();
		}

		// Get the topic tracking data
		$topic_tracking_info = [];
		foreach ($forums as $forum_id => $forum)
		{
			if ($this->config['load_db_lastread'] && $this->user->data['is_registered'] && !$this->config['rtng_load_first_unrd_post'])
			{
				$topic_tracking_info[$forum_id] = get_topic_tracking($forum_id, $forum['topic_list'], $forum['rowset'], [$forum_id => $forum['mark_time']]);
			}
			else if ($this->config['load_anon_lastread'] || ($this->user->data['is_registered'] && !$this->config['load_db_lastread']))
			{
				$topic_tracking_info[$forum_id] = get_complete_topic_tracking($forum_id, $forum['topic_list']);
			}
		}

		$this->template->assign_vars([
				'RTNG_TOPICS_COUNT'		 => $this->language->lang('RTNG_TOPICS_COUNT', (int) $topics_count),
				'RTNG_SORT_START_TIME'	 => $this->user_setting['user_rtng_sort_start_time'],
			]
		);

		$this->fill_template($icons, $tpl_loopname, $topic_tracking_info, $topics_count, $topic_list, $topic_rows);
	}

	public function get_index_topic_ids_for_dedupe(string $tpl_loopname = 'rtng_topics', array $additional_excluded_topic_ids = []): array
	{
		if ($this->has_displayed_index_topic_ids_for_dedupe($tpl_loopname))
		{
			return $this->get_displayed_index_topic_ids_for_dedupe($tpl_loopname);
		}

		if ($this->index_topic_ids_for_dedupe !== null && empty($additional_excluded_topic_ids))
		{
			return $this->index_topic_ids_for_dedupe;
		}

		$this->index_topic_ids_for_dedupe = [];
		$this->user_setting = $this->ctrl_common->get_user_setting();

		if (!($this->user_setting['user_rtng_enable'] && $this->auth->acl_get('u_rtng_view')))
		{
			return $this->index_topic_ids_for_dedupe;
		}

		if (!in_array((string) $this->user_setting['user_rtng_location'], ['RTNG_TOP', 'RTNG_BOTTOM', 'RTNG_SIDE'], true))
		{
			return $this->index_topic_ids_for_dedupe;
		}

		$forum_id_list = $this->getforumlist();
		if (empty($forum_id_list))
		{
			return $this->index_topic_ids_for_dedupe;
		}

		$this->topics_per_page = max(1, (int) $this->user_setting['user_rtng_index_topics_qty']);
		$this->topics_page_number = max(1, (int) $this->user_setting['user_rtng_index_page_qty']);
		$rtng_start = $this->request->variable($tpl_loopname . '_start', 0);
		$excluded_topics = $this->get_effective_excluded_topics($tpl_loopname, $additional_excluded_topic_ids, $rtng_start);
		$min_topic_level = (int) $this->config['rtng_min_topic_level'];

		if ((int) $this->config['rtng_all_topics'] == 0)
		{
			$total_topics_limit = $this->topics_per_page * $this->topics_page_number;
		}
		else
		{
			$sql_array = $this->get_allowed_topics_sql($excluded_topics, $min_topic_level, $forum_id_list);
			$count_sql_array = $sql_array;
			$count_sql_array['SELECT'] = 'COUNT(t.topic_id) as topic_count';
			unset($count_sql_array['ORDER_BY']);
			unset($count_sql_array['LEFT_JOIN']);
			$sql = $this->db->sql_build_query('SELECT', $count_sql_array);

			$result = $this->db->sql_query($sql);
			$total_topics_limit = (int) $this->db->sql_fetchfield('topic_count');
			$this->db->sql_freeresult($result);
		}

		if ($total_topics_limit < 1)
		{
			return $this->index_topic_ids_for_dedupe;
		}

		$obtain_icons = false;
		$forums = [];
		$topic_list = [];
		$topic_rows = [];
		$topics_count = $this->gettopiclist($obtain_icons, $forums, $rtng_start, $total_topics_limit, $excluded_topics, $forum_id_list, $topic_list, $topic_rows);
		$display_topic_list = $topic_list;

		if (empty($additional_excluded_topic_ids))
		{
			$this->prepared_index_topic_list_for_display = [
				'key' => $this->build_topic_list_cache_key($tpl_loopname, $rtng_start, $total_topics_limit, $excluded_topics, $forum_id_list),
				'obtain_icons' => $obtain_icons,
				'forums' => $forums,
				'topic_list' => $display_topic_list,
				'topic_rows' => $topic_rows,
				'topics_count' => $topics_count,
				'topics_start' => $this->topics_start,
			];
		}

		$topic_list = array_values(array_unique(array_filter(array_map('intval', $topic_list), static function ($topic_id) {
			return $topic_id > 0;
		})));
		sort($topic_list);

		if (empty($additional_excluded_topic_ids))
		{
			$this->index_topic_ids_for_dedupe = $topic_list;
		}

		return $topic_list;
	}

	public function get_displayed_index_topic_ids_for_dedupe(string $tpl_loopname = 'rtng_topics'): array
	{
		return $this->normalise_topic_ids(array_keys($this->displayed_topic_ids_for_dedupe[$tpl_loopname] ?? []));
	}

	public function has_displayed_index_topic_ids_for_dedupe(string $tpl_loopname = 'rtng_topics'): bool
	{
		return array_key_exists($tpl_loopname, $this->displayed_topic_ids_for_dedupe);
	}

	/**
	 * Get the forums we take our topics from
	 */
	private function getforumlist(): array
	{
		if ($this->forum_id_list !== null)
		{
			return $this->forum_id_list;
		}

		// Get the allowed forums
		$forum_ary = [];
		$forum_read_ary = $this->auth->acl_getf('f_read');

		foreach ($forum_read_ary as $forum_id => $allowed)
		{
			if ($allowed['f_read'])
			{
				$forum_ary[] = (int) $forum_id;
			}
		}

		$forum_ids = array_diff($forum_ary, $this->user->get_passworded_forums());

		if (count($forum_ids) > 1)
		{
			$enabled_forum_map = array_fill_keys($this->get_rtng_enabled_forum_ids(), true);
			$forum_ids_disp = array_values(array_filter(array_map('intval', $forum_ids), static function ($forum_id) use ($enabled_forum_map) {
				return isset($enabled_forum_map[$forum_id]);
			}));
			$this->forum_id_list = $forum_ids_disp;
			return $this->forum_id_list;
		}
		else
		{
			$this->forum_id_list = [];
			return $this->forum_id_list;
		}
	}

	private function get_rtng_enabled_forum_ids(): array
	{
		$cached = $this->cache->get(self::CACHE_RTNG_ENABLED_FORUM_IDS);
		if (is_array($cached))
		{
			return $this->normalise_topic_ids($cached);
		}

		$sql_array = [
			'SELECT' => 'forum_id',
			'FROM' => [FORUMS_TABLE => ''],
			'WHERE' => 'forum_rtng_disp = 1',
		];
		$sql = $this->db->sql_build_query('SELECT', $sql_array);
		$result = $this->db->sql_query($sql);

		$forum_ids = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$forum_ids[] = (int) ($row['forum_id'] ?? 0);
		}
		$this->db->sql_freeresult($result);

		$forum_ids = $this->normalise_topic_ids($forum_ids);
		$this->cache->put(self::CACHE_RTNG_ENABLED_FORUM_IDS, $forum_ids, self::CACHE_SECONDS);

		return $forum_ids;
	}

	private function build_topic_list_cache_key(string $tpl_loopname, int $rtng_start, int $total_topics_limit, array $excluded_topics, array $forum_id_list): string
	{
		$excluded_topics = $this->normalise_topic_ids($excluded_topics);
		$forum_id_list = $this->normalise_topic_ids($forum_id_list);

		return md5(json_encode([
			'tpl_loopname' => $tpl_loopname,
			'rtng_start' => $rtng_start,
			'total_topics_limit' => $total_topics_limit,
			'topics_per_page' => $this->topics_per_page,
			'topics_page_number' => $this->topics_page_number,
			'rtng_all_topics' => (int) $this->config['rtng_all_topics'],
			'rtng_min_topic_level' => (int) $this->config['rtng_min_topic_level'],
			'user_rtng_unread_only' => !empty($this->user_setting['user_rtng_unread_only']),
			'user_rtng_sort_start_time' => !empty($this->user_setting['user_rtng_sort_start_time']),
			'excluded_topics' => $excluded_topics,
			'forum_id_list' => $forum_id_list,
		]));
	}

	private function get_prepared_index_topic_list_for_display(string $topic_list_cache_key): ?array
	{
		if (!is_array($this->prepared_index_topic_list_for_display)
			|| ($this->prepared_index_topic_list_for_display['key'] ?? '') !== $topic_list_cache_key
			|| !isset($this->prepared_index_topic_list_for_display['forums'])
			|| !is_array($this->prepared_index_topic_list_for_display['forums'])
			|| !isset($this->prepared_index_topic_list_for_display['topic_list'])
			|| !is_array($this->prepared_index_topic_list_for_display['topic_list'])
			|| !isset($this->prepared_index_topic_list_for_display['topic_rows'])
			|| !is_array($this->prepared_index_topic_list_for_display['topic_rows'])
			|| !isset($this->prepared_index_topic_list_for_display['obtain_icons'])
			|| !isset($this->prepared_index_topic_list_for_display['topics_count'])
			|| !isset($this->prepared_index_topic_list_for_display['topics_start']))
		{
			return null;
		}

		return $this->prepared_index_topic_list_for_display;
	}

	/**
	 * Get the topic list
	 */
	private function gettopiclist(bool &$obtain_icons, array &$forums, int $rtng_start, int $total_topics_limit, array $excluded_topics, array $forum_id_list, array &$topic_list, array &$topic_rows): int
	{
		$topics_count	 = 0;
		$min_topic_level = $this->config['rtng_min_topic_level'];

		// Either use the phpBB core function to get unread topics, or the custom function for default behavior
		if ($this->user_setting['user_rtng_unread_only'] && $this->user->data['is_registered'])
		{
			// Get unread topics
			$sql_extra	   = ' AND ' . $this->db->sql_in_set('t.topic_id', $excluded_topics, true);
			$sql_extra	  .= ' AND ' . $this->content_visibility->get_forums_visibility_sql('topic', $forum_id_list, $table_alias = 't.');
			$sql_extra	  .= ' AND t.topic_status <> ' . ITEM_MOVED;
			$sql_extra	  .= $this->build_non_foe_topic_poster_sql('t');
			$unread_topics = get_unread_topics(false, $sql_extra, '', $total_topics_limit);

			$total_topics_limit = min($total_topics_limit, count($unread_topics));
			$rtng_start = $this->validate_start($rtng_start, $this->topics_per_page, $total_topics_limit);

			$topic_list = array_slice(array_keys($unread_topics), $rtng_start, $this->topics_per_page);
		}
		else
		{
			// Get allowed topics
			$sql_array = $this->get_allowed_topics_sql($excluded_topics, $min_topic_level, $forum_id_list);
			$sql = $this->db->sql_build_query('SELECT', $sql_array);

			if ((int) $this->config['rtng_all_topics'] == 0)
			{
				$result = $this->db->sql_query_limit($sql, $total_topics_limit);
				if ($result == false)
				{
					return 0;
				}

				$rows = $this->db->sql_fetchrowset($result);
				$this->db->sql_freeresult($result);

				$topics_count = count($rows);
				$total_topics_limit = min($total_topics_limit, $topics_count);
				$rtng_start = $this->validate_start($rtng_start, $this->topics_per_page, $total_topics_limit);

				$this->collect_topic_list_rows(
					array_slice($rows, $rtng_start, $this->topics_per_page),
					$obtain_icons,
					$forums,
					$topic_list,
					$topic_rows
				);
			}
			else
			{
				$count_sql_array = $sql_array;
				$count_sql_array['SELECT'] = 'COUNT(t.topic_id) as topic_count';
				unset($count_sql_array['ORDER_BY']);
				unset($count_sql_array['LEFT_JOIN']);
				$count_sql = $this->db->sql_build_query('SELECT', $count_sql_array);

				$result = $this->db->sql_query($count_sql);
				$topics_count = (int) $this->db->sql_fetchfield('topic_count');
				$this->db->sql_freeresult($result);

				$total_topics_limit = min($total_topics_limit, $topics_count);
				$rtng_start = $this->validate_start($rtng_start, $this->topics_per_page, $total_topics_limit);
				$result = $this->db->sql_query_limit($sql, $this->topics_per_page, $rtng_start);

				if ($result == false)
				{
					return 0;
				}

				$rows = $this->db->sql_fetchrowset($result);
				$this->db->sql_freeresult($result);

				$this->collect_topic_list_rows($rows, $obtain_icons, $forums, $topic_list, $topic_rows);
			}
		}

		// Set start of pagination
		$this->topics_start = $rtng_start;

		// Return number of total topics counts to display
		return $total_topics_limit;
	}

	private function collect_topic_list_rows(array $rows, bool &$obtain_icons, array &$forums, array &$topic_list, array &$topic_rows): void
	{
		foreach ($rows as $row)
		{
			$topic_id = (int) ($row['topic_id'] ?? 0);
			$forum_id = (int) ($row['forum_id'] ?? 0);
			if ($topic_id <= 0 || $forum_id <= 0)
			{
				continue;
			}

			$topic_list[] = $topic_id;
			$topic_rows[] = $row;

			if (!isset($forums[$forum_id]) && $this->user->data['is_registered'] && $this->config['load_db_lastread'])
			{
				$forums[$forum_id]['mark_time'] = $row['f_mark_time'];
			}
			$forums[$forum_id]['topic_list'][] = $topic_id;
			$forums[$forum_id]['rowset'][$topic_id] = $row;

			if (!empty($row['icon_id']))
			{
				$obtain_icons = true;
			}
		}
	}

	/**
	 * Custom function to get allowed topics
	 * Used for anon access or when unread topics is not requested
	 */
	private function get_allowed_topics_sql(array $excluded_topics, int $min_topic_level, array $forum_id_list): array
	{
		$sort_topics = $this->user_setting['user_rtng_sort_start_time'] ? 'topic_time' : 'topic_last_post_time';

		// Get the allowed topics
		$sql_array = [
			'SELECT'    => 't.*, f.forum_name, tp.topic_posted, tt.mark_time, ft.mark_time as f_mark_time, t.' . $sort_topics . ' as sortcr ',
			'FROM'      => [TOPICS_TABLE => 't'],
			'LEFT_JOIN' => [
				[
					'FROM' => [FORUMS_TABLE => 'f', ],
					'ON'   => 'f.forum_id = t.forum_id',
				],
				[
					'FROM' => [TOPICS_TRACK_TABLE => 'tt', ],
					'ON'   => 'tt.topic_id = t.topic_id AND tt.user_id = ' . (int) $this->user->data['user_id'],
				],
				[
					'FROM' => [FORUMS_TRACK_TABLE => 'ft', ],
					'ON'   => 'ft.forum_id = t.forum_id AND ft.user_id = ' . (int) $this->user->data['user_id'],
				],
				[
					'FROM' => [TOPICS_POSTED_TABLE => 'tp', ],
					'ON' => 'tp.topic_id = t.topic_id AND tp.user_id = ' . (int) $this->user->data['user_id'],
				],
			],
			'WHERE'     => $this->db->sql_in_set('t.topic_id', $excluded_topics, true) . '
					AND t.topic_status <> ' . ITEM_MOVED . '
					AND ' . $this->content_visibility->get_forums_visibility_sql('topic', $forum_id_list, $table_alias = 't.')
					. $this->build_non_foe_topic_poster_sql('t'),
			'ORDER_BY'  => 't.' . $sort_topics . ' DESC',
		];

		// Check if we want all topics, or only stickies/announcements/globals
		if ($min_topic_level > 0)
		{
			$sql_array['WHERE'] .= ' AND t.topic_type >= ' . (int) $min_topic_level;
		}

		if ($this->config['rtng_parents'])
		{
			$sql_array['SELECT'] .= ', f.parent_id, f.forum_parents, f.left_id, f.right_id';
		}

		/**
		 * Event to modify the SQL query before the allowed topics list data is retrieved
		 *
		 * @event imcger.recenttopicsng.sql_pull_topics_list
		 * @var	array	sql_array	The SQL array
		 * @since 1.0.0
		 */
		$vars = ['sql_array'];
		extract($this->dispatcher->trigger_event('imcger.recenttopicsng.sql_pull_topics_list', compact($vars)));

		return $sql_array;
	}

	private function get_effective_excluded_topics(string $tpl_loopname, array $additional_excluded_topic_ids = [], int $rtng_start = 0): array
	{
		$excluded_topic_map = $this->parse_topic_id_csv_to_map((string) ($this->config['rtng_anti_topics'] ?? ''));

		foreach ($additional_excluded_topic_ids as $topic_id)
		{
			$topic_id = (int) $topic_id;
			if ($topic_id > 0)
			{
				$excluded_topic_map[$topic_id] = true;
			}
		}

		if (empty($additional_excluded_topic_ids) && $this->should_exclude_index_toptopics_from_rtng($tpl_loopname, $rtng_start))
		{
			foreach ($this->get_index_toptopics_topic_ids() as $topic_id)
			{
				$excluded_topic_map[$topic_id] = true;
			}
		}

		$excluded_topics = array_keys($excluded_topic_map);
		sort($excluded_topics);

		// Keep RTNG's historical behavior when no IDs are excluded.
		return !empty($excluded_topics) ? $excluded_topics : [0];
	}

	private function should_exclude_index_toptopics_from_rtng(string $tpl_loopname, int $rtng_start): bool
	{
		if ($tpl_loopname !== 'rtng_topics')
		{
			return false;
		}

		if ($rtng_start > 0)
		{
			return false;
		}

		if (((string) ($this->user->page['page_name'] ?? '')) !== 'index.php')
		{
			return false;
		}

		return ($this->toptopics_listener && method_exists($this->toptopics_listener, 'get_index_summary_topic_ids_for_dedupe'))
			|| ($this->toptopics_ranker && method_exists($this->toptopics_ranker, 'get_topics'));
	}

	private function get_index_toptopics_topic_ids(): array
	{
		if ($this->toptopics_index_topic_ids !== null)
		{
			return $this->toptopics_index_topic_ids;
		}

		$this->toptopics_index_topic_ids = [];
		if ($this->toptopics_listener && method_exists($this->toptopics_listener, 'get_index_summary_topic_ids_for_dedupe'))
		{
			try
			{
				$topic_ids = $this->toptopics_listener->get_index_summary_topic_ids_for_dedupe();
				if (is_array($topic_ids))
				{
					foreach ($topic_ids as $topic_id)
					{
						$topic_id = (int) $topic_id;
						if ($topic_id > 0)
						{
							$this->toptopics_index_topic_ids[$topic_id] = true;
						}
					}

					$this->toptopics_index_topic_ids = array_keys($this->toptopics_index_topic_ids);
					sort($this->toptopics_index_topic_ids);
					return $this->toptopics_index_topic_ids;
				}
			}
			catch (\Throwable $exception)
			{
				$this->toptopics_index_topic_ids = [];
			}
		}

		if (!$this->toptopics_ranker || !method_exists($this->toptopics_ranker, 'get_topics'))
		{
			return $this->toptopics_index_topic_ids;
		}

		$index_limit = isset($this->config['toptopics_index_limit']) ? max(0, (int) $this->config['toptopics_index_limit']) : 0;
		if ($index_limit <= 0)
		{
			return $this->toptopics_index_topic_ids;
		}

		$forum_ids = $this->get_toptopics_index_forum_ids();
		if (empty($forum_ids))
		{
			return $this->toptopics_index_topic_ids;
		}

		$topics = $this->toptopics_ranker->get_topics($forum_ids, $index_limit);
		$topic_id_map = [];
		$foe_user_id_map = $this->get_current_user_foe_id_map();
		foreach ($topics as $topic)
		{
			$topic_id = (int) ($topic['topic_id'] ?? 0);
			$topic_poster = (int) ($topic['topic_poster'] ?? 0);
			if ($topic_id <= 0)
			{
				continue;
			}

			if (!empty($foe_user_id_map) && isset($foe_user_id_map[$topic_poster]))
			{
				continue;
			}

			$topic_id_map[$topic_id] = true;
		}

		$this->toptopics_index_topic_ids = array_keys($topic_id_map);
		sort($this->toptopics_index_topic_ids);
		return $this->toptopics_index_topic_ids;
	}

	private function get_toptopics_index_forum_ids(): array
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
		$excluded_forum_map = $this->parse_topic_id_csv_to_map((string) ($this->config['toptopics_index_excluded_forum_ids'] ?? ''));
		if (!empty($excluded_forum_map))
		{
			$forum_ids = array_values(array_filter($forum_ids, static function ($forum_id) use ($excluded_forum_map) {
				return !isset($excluded_forum_map[$forum_id]);
			}));
		}

		sort($forum_ids);
		return $forum_ids;
	}

	private function parse_topic_id_csv_to_map(string $csv): array
	{
		$csv = preg_replace('/\s+/', '', trim($csv));
		if ($csv === '')
		{
			return [];
		}

		$id_map = [];
		foreach (explode(',', $csv) as $part)
		{
			$id = (int) $part;
			if ($id > 0)
			{
				$id_map[$id] = true;
			}
		}

		return $id_map;
	}

	private function normalise_topic_ids(array $topic_ids): array
	{
		$topic_ids = array_values(array_unique(array_filter(array_map('intval', $topic_ids), static function ($topic_id) {
			return $topic_id > 0;
		})));
		sort($topic_ids);

		return $topic_ids;
	}

	private function build_non_foe_topic_poster_sql(string $topic_alias = 't'): string
	{
		$foe_user_id_map = $this->get_current_user_foe_id_map();
		if (empty($foe_user_id_map))
		{
			return '';
		}

		$foe_user_ids = array_map('intval', array_keys($foe_user_id_map));
		if (empty($foe_user_ids))
		{
			return '';
		}

		return ' AND '
			. $this->db->sql_in_set($topic_alias . '.topic_poster', $foe_user_ids, true)
			. ' AND '
			. $this->db->sql_in_set($topic_alias . '.topic_last_poster_id', $foe_user_ids, true);
	}

	private function get_current_user_foe_id_map(): array
	{
		if ($this->foe_user_id_map !== null)
		{
			return $this->foe_user_id_map;
		}

		$this->foe_user_id_map = [];
		$current_user_id = (int) ($this->user->data['user_id'] ?? ANONYMOUS);
		if ($current_user_id === ANONYMOUS)
		{
			return $this->foe_user_id_map;
		}

		$sql = 'SELECT zebra_id
			FROM ' . ZEBRA_TABLE . '
			WHERE user_id = ' . $current_user_id . '
				AND foe = 1';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$foe_user_id = (int) ($row['zebra_id'] ?? 0);
			if ($foe_user_id > 0)
			{
				$this->foe_user_id_map[$foe_user_id] = true;
			}
		}
		$this->db->sql_freeresult($result);

		return $this->foe_user_id_map;
	}

	/**
	 * Get username details for placing into templates.
	 */
	private function getusernamestrings(array $row): array
	{
		$topic_author				= get_username_string('username', $row['topic_poster'], $row['topic_first_poster_name'], $row['topic_first_poster_colour']);
		$topic_author_color			= get_username_string('colour', $row['topic_poster'], $row['topic_first_poster_name'], $row['topic_first_poster_colour']);
		$topic_author_full			= get_username_string('full', $row['topic_poster'], $row['topic_first_poster_name'], $row['topic_first_poster_colour']);
		$u_topic_author				= get_username_string('profile', $row['topic_poster'], $row['topic_first_poster_name'], $row['topic_first_poster_colour']);
		$last_post_author			= get_username_string('username', $row['topic_last_poster_id'], $row['topic_last_poster_name'], $row['topic_last_poster_colour']);
		$last_post_author_colour	= get_username_string('colour', $row['topic_last_poster_id'], $row['topic_last_poster_name'], $row['topic_last_poster_colour']);
		$last_post_author_full		= get_username_string('full', $row['topic_last_poster_id'], $row['topic_last_poster_name'], $row['topic_last_poster_colour']);
		$u_last_post_author			= get_username_string('profile', $row['topic_last_poster_id'], $row['topic_last_poster_name'], $row['topic_last_poster_colour']);
		return [$topic_author, $topic_author_color, $topic_author_full, $u_topic_author, $last_post_author, $last_post_author_colour, $last_post_author_full, $u_last_post_author];
	}

	/**
	 * Pull the data of the requested
	 */
	private function get_topics_sql(array $topic_list): array
	{
		$sort_topics = $this->user_setting['user_rtng_sort_start_time'] ? 'topic_time' : 'topic_last_post_time';

		$sql_array = [
			'SELECT'    => 't.*, f.forum_name, tp.topic_posted',
			'FROM'      => [TOPICS_TABLE => 't', ],
			'LEFT_JOIN' => [
				[
					'FROM' => [FORUMS_TABLE => 'f', ],
					'ON'   => 'f.forum_id = t.forum_id',
				],
				[
					'FROM' => [TOPICS_POSTED_TABLE => 'tp', ],
					'ON' => 'tp.topic_id = t.topic_id AND tp.user_id = ' . (int) $this->user->data['user_id'],
				],
				],
				'WHERE'     => $this->db->sql_in_set('t.topic_id', $topic_list)
					. $this->build_non_foe_topic_poster_sql('t'),
				'ORDER_BY'  => 't.' . $sort_topics . ' DESC',
			];

		if ($this->config['rtng_parents'])
		{
			$sql_array['SELECT'] .= ', f.parent_id, f.forum_parents, f.left_id, f.right_id';
		}

		/**
		 * Event to modify the SQL query before the topics data is retrieved
		 *
		 * @event imcger.recenttopicsng.sql_pull_topics_data
		 * @var	array	sql_array	The SQL array
		 * @since 1.0.0
		 */
		$vars = ['sql_array'];
		extract($this->dispatcher->trigger_event('imcger.recenttopicsng.sql_pull_topics_data', compact($vars)));

		$sql    = $this->db->sql_build_query('SELECT', $sql_array);
		$result = $this->db->sql_query($sql);

		$rowset = $this->db->sql_fetchrowset($result);

		$this->db->sql_freeresult($result);
		return $rowset;
	}

	/**
	 * Set template vars
	 */
	private function fill_template(array $icons, string $tpl_loopname, array $topic_tracking_info, int $topics_count, array $topic_list, array $preloaded_rowset = []): void
	{
		$rowset = $this->can_use_preloaded_topic_rowset($preloaded_rowset)
			? $preloaded_rowset
			: $this->get_topics_sql($topic_list);
		$topic_icons = [];

		// if topics returned by DB
		if (count($rowset))
		{
			/**
			 * Event to modify the topics list data before we start the display loop
			 *
			 * @event imcger.recenttopicsng.modify_topics_list
			 * @var	array	topic_list	Array of all the topic IDs
			 * @var	array	rowset		The full topics list array
			 * @since 1.0.0
			 */
			$vars = ['topic_list', 'rowset'];
			extract($this->dispatcher->trigger_event('imcger.recenttopicsng.modify_topics_list', compact($vars)));

			foreach ($rowset as $row)
			{
				$first_unread	= [];
				$topic_id		= $row['topic_id'];
				$forum_id		= $row['forum_id'];
				$unread_topic	= false;
				$replies		= $this->content_visibility->get_count('topic_posts', $row, $forum_id) - 1;
				$s_type_switch_test = ($row['topic_type'] == POST_ANNOUNCE || $row['topic_type'] == POST_GLOBAL) ? 1 : 0;
				$disp_topic_title	= $this->user_setting['user_rtng_disp_last_post'] ? 'last_post' : 'first_post';

				if ($this->user->data['is_registered'] && $this->config['load_db_lastread'] && $this->config['rtng_load_first_unrd_post'])
				{
					// Get author, posttime, id and title of first unread post in topic
					$sql_array = [
						'SELECT'	=> 'p.poster_id, u.username, u.user_colour, p.post_id, p.post_subject, p.post_time',
						'FROM'		=> [POSTS_TABLE => 'p',	],
						'LEFT_JOIN' => [
							[
								'FROM' => [TOPICS_TABLE => 't', ],
								'ON'   => "t.topic_id = p.topic_id",
							],
							[
								'FROM' => [TOPICS_TRACK_TABLE => 'tt', ],
								'ON'   => "tt.user_id = {$this->user->data['user_id']}
										AND tt.topic_id = p.topic_id",
							],
							[
								'FROM' => [FORUMS_TRACK_TABLE => 'ft', ],
								'ON'   => "ft.user_id = {$this->user->data['user_id']}
										AND ft.forum_id = t.forum_id",
							],
							[
								'FROM' => [USERS_TABLE => 'u', ],
								'ON'   => 'u.user_id = p.poster_id',
							],
						],
						'WHERE'		=> "p.topic_id = $topic_id
									AND p.post_time > COALESCE(tt.mark_time, ft.mark_time, {$this->user->data['user_lastmark']}, 0)
									AND p.forum_id = $forum_id",
						'ORDER_BY'	=> 'p.post_time ASC, p.post_id ASC',
					];

					$sql = $this->db->sql_build_query('SELECT', $sql_array);
					$result = $this->db->sql_query_limit($sql, 1);
					$first_unread = $this->db->sql_fetchrow($result);
					$this->db->sql_freeresult($result);

					$unread_topic = !empty($first_unread['post_id']);

					if ($this->user_setting['user_rtng_disp_first_unrd_post'] && $unread_topic)
					{
						$disp_topic_title = 'first_unread_post';

						$first_unread_post_author		= get_username_string('username', $first_unread['poster_id'], $first_unread['username'], $first_unread['user_colour']);
						$first_unread_post_author_color	= get_username_string('colour', $first_unread['poster_id'], $first_unread['username'], $first_unread['user_colour']);
						$first_unread_post_author_full	= get_username_string('full', $first_unread['poster_id'], $first_unread['username'], $first_unread['user_colour']);
						$first_unread_post_time			= $this->user->format_date($first_unread['post_time']);
						$u_first_unread_post_author		= get_username_string('profile', $first_unread['poster_id'], $first_unread['username'], $first_unread['user_colour']);
					}
				}
				else
				{
					$unread_topic = isset($topic_tracking_info[$forum_id][$row['topic_id']]) && ($row['topic_last_post_time'] > $topic_tracking_info[$forum_id][$row['topic_id']]);
				}

				$row['topic_first_unread_post_id']		 = $first_unread['post_id'] ?? '';
				$row['topic_first_unread_poster_id']	 = $first_unread['poster_id'] ?? '';
				$row['topic_first_unread_poster_name']	 = $first_unread['username'] ?? '';
				$row['topic_first_unread_poster_colour'] = $first_unread['user_colour'] ?? '';
				$row['topic_first_unread_post_subject']	 = $first_unread['post_subject'] ?? '';
				$row['topic_first_unread_post_time']	 = $first_unread['post_time'] ?? '';

				$view_topic_url				= append_sid("{$this->root_path}viewtopic.$this->phpEx", 't=' . $topic_id);
				$view_last_post_url			= append_sid("{$this->root_path}viewtopic.$this->phpEx", 'p=' . $row['topic_last_post_id'] . '#p' . $row['topic_last_post_id']);
				$view_first_unread_post_url	= !empty($first_unread['post_id']) ? append_sid("{$this->root_path}viewtopic.$this->phpEx", 'p=' . $first_unread['post_id'] . '#p' . $first_unread['post_id']) : '';
				$view_report_url			= append_sid("{$this->root_path}mcp.$this->phpEx", 'i=reports&amp;mode=reports&amp;t=' . $topic_id, true, $this->user->session_id);
				$view_forum_url				= append_sid("{$this->root_path}viewforum.$this->phpEx", 'f=' . $forum_id);
				$topic_unapproved			= ($row['topic_visibility'] == ITEM_UNAPPROVED && $this->auth->acl_get('m_approve', $forum_id));
				$posts_unapproved			= ($row['topic_visibility'] == ITEM_APPROVED && $row['topic_posts_unapproved'] && $this->auth->acl_get('m_approve', $forum_id));
				$u_mcp_queue				= ($topic_unapproved || $posts_unapproved) ? append_sid("{$this->root_path}mcp.$this->phpEx", 'i=queue&amp;mode=' . ($topic_unapproved ? 'approve_details' : 'unapproved_posts') . "&amp;t=$topic_id", true, $this->user->session_id) : '';
				$s_type_switch				= ($row['topic_type'] == POST_ANNOUNCE || $row['topic_type'] == POST_GLOBAL) ? 1 : 0;

				if (!empty($icons[$row['icon_id']]))
				{
					$topic_icons[] = $topic_id;
				}

				if ($this->user_setting['user_rtng_unread_only'] && $this->user->data['is_registered'])
				{
					$unread_topic = true;
				}

				// Get folder img, topic status/type related information
				$folder_img = $folder_alt = $topic_type = '';
				topic_status($row, $replies, $unread_topic, $folder_img, $folder_alt, $topic_type);

				list($topic_author, $topic_author_color, $topic_author_full, $u_topic_author, $last_post_author, $last_post_author_colour, $last_post_author_full, $u_last_post_author) = $this->getusernamestrings($row);

				//load language
				$this->language->add_lang('rtng_common', 'imcger/recenttopicsng');
				$tpl_ary = [
					'FORUM_ID'							=> $forum_id,
					'TOPIC_ID'							=> $topic_id,
					'TOPIC_AUTHOR'						=> $topic_author,
					'TOPIC_AUTHOR_COLOUR'				=> $topic_author_color,
					'TOPIC_AUTHOR_FULL'					=> $topic_author_full,
					'U_TOPIC_AUTHOR'					=> $u_topic_author,
					'FIRST_POST_TIME'					=> $this->user->format_date($row['topic_time']),
					'FIRST_UNREAD_POST_AUTHOR'			=> $first_unread_post_author ?? '',
					'FIRST_UNREAD_POST_AUTHOR_COLOUR'	=> $first_unread_post_author_color ?? '',
					'FIRST_UNREAD_POST_AUTHOR_FULL'		=> $first_unread_post_author_full ?? '',
					'U_FIRST_UNREAD_POST_AUTHOR'		=> $u_first_unread_post_author ?? '',
					'FIRST_UNREAD_POST_SUBJECT'			=> censor_text($row['topic_first_unread_post_subject']),
					'FIRST_UNREAD_POST_TIME'			=> $first_unread_post_time ?? '',
					'LAST_POST_SUBJECT'					=> censor_text($row['topic_last_post_subject']),
					'LAST_POST_TIME'					=> $this->user->format_date($row['topic_last_post_time']),
					'LAST_VIEW_TIME'					=> $this->user->format_date($row['topic_last_view_time']),
					'LAST_POST_AUTHOR'					=> $last_post_author,
					'LAST_POST_AUTHOR_COLOUR'			=> $last_post_author_colour,
					'LAST_POST_AUTHOR_FULL'				=> $last_post_author_full,
					'U_LAST_POST_AUTHOR'				=> $u_last_post_author,
					'REPLIES'							=> $replies,
					'VIEWS'								=> $row['topic_views'],
					'TOPIC_TITLE'						=> censor_text($row['topic_title']),
					'FORUM_NAME'						=> $row['forum_name'],
					'TOPIC_TYPE'						=> $topic_type,
					'TOPIC_IMG_STYLE'					=> $folder_img,
					'TOPIC_FOLDER_IMG'			=> $this->user->img($folder_img, $folder_alt),
					'TOPIC_FOLDER_IMG_ALT'		=> $this->language->lang($folder_alt),
					'TOPIC_ICON_IMG'			=> (!empty($icons[$row['icon_id']])) ? $icons[$row['icon_id']]['img'] : '',
					'TOPIC_ICON_IMG_WIDTH'		=> (!empty($icons[$row['icon_id']])) ? $icons[$row['icon_id']]['width'] : '',
					'TOPIC_ICON_IMG_HEIGHT'		=> (!empty($icons[$row['icon_id']])) ? $icons[$row['icon_id']]['height'] : '',
					'ATTACH_ICON_IMG'			=> ($this->auth->acl_get('u_download') && $this->auth->acl_get('f_download', $forum_id) && $row['topic_attachment']) ? $this->user->img('icon_topic_attach', $this->language->lang('TOTAL_ATTACHMENTS')) : '',
					'UNAPPROVED_IMG'			=> ($topic_unapproved || $posts_unapproved) ? $this->user->img('icon_topic_unapproved', $topic_unapproved ? 'TOPIC_UNAPPROVED' : 'POSTS_UNAPPROVED') : '',
					'REPORTED_IMG'				=> ($row['topic_reported'] && $this->auth->acl_get('m_report', $forum_id)) ? $this->user->img('icon_topic_reported', 'TOPIC_REPORTED') : '',
					'S_HAS_POLL'				=> (bool) $row['poll_start'],
					'S_TOPIC_TYPE'				=> $row['topic_type'],
					'S_UNREAD_TOPIC'			=> $unread_topic,
					'S_DISP_FIRST_UNREAD_POST'	=> $disp_topic_title == 'first_unread_post',
					'S_DISP_LAST_POST'			=> $disp_topic_title == 'last_post',
					'S_DISP_FIRST_POST'			=> $disp_topic_title == 'first_post',
					'S_TOPIC_REPORTED'			=> $row['topic_reported'] && $this->auth->acl_get('m_report', $forum_id),
					'S_TOPIC_UNAPPROVED'		=> $topic_unapproved,
					'S_POSTS_UNAPPROVED'		=> $posts_unapproved,
					'S_POST_ANNOUNCE'			=> $row['topic_type'] == POST_ANNOUNCE,
					'S_POST_GLOBAL'				=> $row['topic_type'] == POST_GLOBAL,
					'S_POST_STICKY'				=> $row['topic_type'] == POST_STICKY,
					'S_TOPIC_LOCKED'			=> $row['topic_status'] == ITEM_LOCKED,
					'S_TOPIC_MOVED'				=> $row['topic_status'] == ITEM_MOVED,
					'S_TOPIC_TYPE_SWITCH'		=> ($s_type_switch == $s_type_switch_test) ? -1 : $s_type_switch_test,
					'U_NEWEST_POST'				=> $view_topic_url . '&amp;view=unread#unread',
					'U_LAST_POST'				=> $view_last_post_url,
					'U_FIRST_UNREAD_POST'		=> $view_first_unread_post_url,
					'U_VIEW_TOPIC'				=> $view_topic_url,
					'U_VIEW_FORUM'				=> $view_forum_url,
					'U_MCP_REPORT'				=> $view_report_url,
					'U_MCP_QUEUE'				=> $u_mcp_queue,
				];

				/**
				 * Modify the topic data before it is assigned to the template
				 *
				 * @event imcger.recenttopicsng.modify_tpl_ary
				 * @var	string  disp_topic_title	Post in Topic title. first, last or first unread post
				 * @var	array	row					Array with topic data
				 * @var	array	tpl_ary				Template block array with topic data
				 * @since 1.0.0
				 * @changed 1.1.0 Variables added. $disp_topic_title and properties of the first unread post in $row
				 */
				$vars = ['disp_topic_title', 'row', 'tpl_ary'];
				extract($this->dispatcher->trigger_event('imcger.recenttopicsng.modify_tpl_ary', compact($vars)));

				$this->template->assign_block_vars($tpl_loopname, $tpl_ary);
				$this->record_displayed_topic_id_for_dedupe($tpl_loopname, (int) ($tpl_ary['TOPIC_ID'] ?? $topic_id));
				$this->pagination->generate_template_pagination($view_topic_url, $tpl_loopname . '.pagination', 'start', $replies + 1, $this->config['posts_per_page'], 1, true, true);

				if ($this->config['rtng_parents'])
				{
					$forum_parents = get_forum_parents($row);
					foreach ($forum_parents as $parent_id => $data)
					{
						$this->template->assign_block_vars(
							$tpl_loopname . '.parent_forums', [
								'FORUM_ID'		=> $parent_id,
								'FORUM_NAME'	=> $data[0],
								'U_VIEW_FORUM'	=> append_sid("{$this->root_path}viewforum.$this->phpEx", 'f=' . $parent_id),
							]
						);
					}
				}
			} // end rowsset

			// Get URL-parameters for pagination
			$url_params		= explode('&', $this->user->page['query_string']);
			$append_params	= [];

			foreach ($url_params as $param)
			{
				if (!$param)
				{
					continue;
				}

				if (strpos($param, '=') === false)
				{
					// Fix MSSTI Advanced BBCode MOD
					$append_params[$param] = '1';
					continue;
				}

				list($name, $value) = explode('=', $param);

				if ($name != $tpl_loopname . '_start')
				{
					$append_params[$name] = $value;
				}
			}

			$pagination_url = append_sid($this->root_path . $this->user->page['page_name'], $append_params);
			$this->pagination->generate_template_pagination($pagination_url, 'pagination',
				$tpl_loopname . '_start', $topics_count, $this->topics_per_page, $this->topics_start);

			$this->template->assign_vars([
				'S_RTNG_TOPIC_ICONS' => (bool) $topic_icons,
			]);
		} // topics found
	}

	private function can_use_preloaded_topic_rowset(array $rowset): bool
	{
		if (empty($rowset) || $this->dispatcher->hasListeners('imcger.recenttopicsng.sql_pull_topics_data'))
		{
			return false;
		}

		$required_fields = [
			'forum_id',
			'forum_name',
			'icon_id',
			'poll_start',
			'topic_attachment',
			'topic_first_poster_colour',
			'topic_first_poster_name',
			'topic_id',
			'topic_last_post_id',
			'topic_last_post_subject',
			'topic_last_post_time',
			'topic_last_poster_colour',
			'topic_last_poster_id',
			'topic_last_poster_name',
			'topic_last_view_time',
			'topic_poster',
			'topic_posts_unapproved',
			'topic_reported',
			'topic_status',
			'topic_time',
			'topic_title',
			'topic_type',
			'topic_views',
			'topic_visibility',
		];

		foreach ($rowset as $row)
		{
			foreach ($required_fields as $field)
			{
				if (!array_key_exists($field, $row))
				{
					return false;
				}
			}
		}

		return true;
	}

	private function record_displayed_topic_id_for_dedupe(string $tpl_loopname, int $topic_id): void
	{
		if ($topic_id > 0)
		{
			$this->displayed_topic_ids_for_dedupe[$tpl_loopname][$topic_id] = true;
		}
	}

	public function validate_start($start, $per_page, $num_items)
	{
		$start = $start >= $num_items ? $num_items - 1 : $start;
		$start = intdiv($start, $per_page) * $per_page;
		$start = max(0, $start);

		return $start;
	}
}
