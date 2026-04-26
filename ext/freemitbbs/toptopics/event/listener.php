<?php

namespace freemitbbs\toptopics\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	private const USER_OPTION_HIDE_FORUM_SUMMARY = 20;
	private const USER_OPTION_SHOW_MOBILE_TOPIC_STATS = 21;
	private const DEFAULT_PER_FORUM_TOPIC_LIMIT = 3;
	private const BALANCED_TOPIC_FETCH_MULTIPLIER = 5;
	private const DEFAULT_CANDIDATE_POOL_LIMIT = 2000;

	protected \phpbb\auth\auth $auth;
	protected \phpbb\config\config $config;
	protected \phpbb\db\driver\driver_interface $db;
	protected \phpbb\request\request_interface $request;
	protected \phpbb\template\template $template;
	protected \phpbb\user $user;
	protected \phpbb\language\language $language;
	protected \phpbb\controller\helper $helper;
	protected \phpbb\event\dispatcher_interface $dispatcher;
	protected \freemitbbs\toptopics\service\ranker $ranker;
	protected \freemitbbs\toptopics\service\cache_invalidator $cache_invalidator;
	protected \freemitbbs\toptopics\service\reputation $reputation;
	protected $recenttopicsng_functions = null;
	protected $topicpreview_data;
	protected $topicpreview_renderer;
	protected $collapsible_operator;
	protected string $dislikes_table;
	protected string $dislike_history_table;
	protected string $topic_overrides_table;
	protected string $user_reputation_table;
	protected string $root_path;
	protected string $php_ext;

	protected array $post_dislike_counts = [];
	protected array $user_disliked_posts = [];
	protected array $user_reputation_scores = [];
	protected ?int $current_user_reputation = null;
	protected array $index_category_blocks = [];
	protected ?array $index_summary_topics = null;
	protected ?array $index_summary_topic_ids = null;
	protected ?int $index_category_candidate_limit = null;
	protected ?array $index_excluded_forum_id_map = null;
	protected ?array $index_category_excluded_forum_id_map = null;
	protected ?array $index_recenttopics_topic_id_map = null;
	protected ?array $index_forum_viewership_order = null;
	protected ?array $foe_user_id_map = null;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\request\request_interface $request,
		\phpbb\template\template $template,
		\phpbb\user $user,
		\phpbb\language\language $language,
		\phpbb\controller\helper $helper,
		\phpbb\event\dispatcher_interface $dispatcher,
		\freemitbbs\toptopics\service\ranker $ranker,
		\freemitbbs\toptopics\service\cache_invalidator $cache_invalidator,
		\freemitbbs\toptopics\service\reputation $reputation,
		$topicpreview_data,
		$topicpreview_renderer,
		$collapsible_operator,
		string $dislikes_table,
		string $dislike_history_table,
		string $topic_overrides_table,
		string $user_reputation_table,
		string $root_path,
		string $php_ext
	)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->language = $language;
		$this->helper = $helper;
		$this->dispatcher = $dispatcher;
		$this->ranker = $ranker;
		$this->cache_invalidator = $cache_invalidator;
		$this->reputation = $reputation;
		$this->topicpreview_data = $topicpreview_data;
		$this->topicpreview_renderer = $topicpreview_renderer;
		$this->collapsible_operator = $collapsible_operator;
		$this->dislikes_table = $dislikes_table;
		$this->dislike_history_table = $dislike_history_table;
		$this->topic_overrides_table = $topic_overrides_table;
		$this->user_reputation_table = $user_reputation_table;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	public static function getSubscribedEvents()
	{
				return [
					'core.user_setup' => 'load_language_on_setup',
					'core.permissions' => 'add_permissions',
					'core.ucp_prefs_personal_data' => 'ucp_prefs_personal_data',
					'core.ucp_prefs_personal_update_data' => 'ucp_prefs_personal_update_data',
					'core.ucp_prefs_view_data' => 'ucp_prefs_view_data',
					'core.ucp_prefs_view_update_data' => 'ucp_prefs_view_update_data',
					'core.page_header_after' => 'assign_page_template_vars',
						'core.memberlist_view_profile' => 'user_profile_reaction_records',
					'core.viewforum_get_topic_ids_data' => 'viewforum_exclude_foe_topics',
					'core.viewforum_get_announcement_topic_ids_data' => 'viewforum_exclude_foe_topics',
					'core.viewtopic_modify_post_data' => 'prefetch_dislikes',
					'core.viewtopic_modify_post_row' => 'modify_post_row',
					'core.viewtopic_modify_page_title' => 'viewtopic_admin_override',
				'core.display_forums_before' => 'index_category_blocks',
				'core.display_forums_modify_category_template_vars' => 'display_forums_modify_category_template_vars',
				'core.report_post_auth' => 'report_post_auth',
				'core.index_modify_page_title' => 'index_page_summary',
				'core.viewforum_modify_page_title' => 'forum_page_summary',
				'core.submit_post_end' => 'submit_post_end',
				'core.set_post_visibility_after' => 'post_visibility_after',
				'core.set_topic_visibility_after' => 'topic_visibility_after',
				'core.notification_manager_add_notifications' => 'report_notification_added',
				'core.add_log' => 'report_log_added',
				'core.delete_posts_after' => 'clean_posts_after',
				'core.delete_user_after' => 'clean_users_after',
			];
		}

	public function load_language_on_setup($event)
	{
		$this->language->add_lang('toptopics', 'freemitbbs/toptopics');
	}

	public function add_permissions($event)
	{
		$permissions = $event['permissions'];
		$permissions['u_toptopics_dislike'] = ['lang' => 'ACL_U_TOPTOPICS_DISLIKE', 'cat' => 'misc'];
		$event['permissions'] = $permissions;
	}

	public function ucp_prefs_personal_data($event): void
	{
		$data = $event['data'];
		$data['toptopics_show_forum_summary'] = $this->request->variable(
			'toptopics_show_forum_summary',
			!$this->user_hides_forum_summary()
		);
		$event['data'] = $data;

		$this->template->assign_var('S_TOPTOPICS_SHOW_FORUM_SUMMARY', $data['toptopics_show_forum_summary']);
	}

	public function ucp_prefs_personal_update_data($event): void
	{
		$data = $event['data'];
		$sql_ary = $event['sql_ary'];
		$sql_ary['user_options'] = phpbb_optionset(
			self::USER_OPTION_HIDE_FORUM_SUMMARY,
			!(bool) ($data['toptopics_show_forum_summary'] ?? true),
			(int) $sql_ary['user_options']
		);
		$event['sql_ary'] = $sql_ary;
	}

	public function ucp_prefs_view_data($event): void
	{
		$data = $event['data'];
		$data['toptopics_show_mobile_topic_stats'] = $this->request->variable(
			'toptopics_show_mobile_topic_stats',
			$this->user_shows_mobile_topic_stats()
		);
		$event['data'] = $data;

		$this->template->assign_var('S_TOPTOPICS_SHOW_MOBILE_TOPIC_STATS', $data['toptopics_show_mobile_topic_stats']);
	}

	public function ucp_prefs_view_update_data($event): void
	{
		$data = $event['data'];
		$sql_ary = $event['sql_ary'];
		$sql_ary['user_options'] = phpbb_optionset(
			self::USER_OPTION_SHOW_MOBILE_TOPIC_STATS,
			(bool) ($data['toptopics_show_mobile_topic_stats'] ?? false),
			(int) $sql_ary['user_options']
		);
		$event['sql_ary'] = $sql_ary;
	}

	public function assign_page_template_vars($event): void
	{
		$this->template->assign_var('S_TOPTOPICS_SHOW_MOBILE_TOPIC_STATS', $this->user_shows_mobile_topic_stats());
	}

	public function user_profile_reaction_records($event): void
	{
		$current_user_id = (int) $this->user->data['user_id'];
		$profile_user_id = (int) ($event['member']['user_id'] ?? 0);
		if ($profile_user_id > 0 && $profile_user_id !== ANONYMOUS)
		{
			$this->template->assign_vars([
				'S_TOPTOPICS_PROFILE_REPUTATION' => true,
				'TOPTOPICS_PROFILE_REPUTATION' => $this->format_reputation($this->reputation->get_score($profile_user_id)),
			]);
		}

		if ($current_user_id === ANONYMOUS || $current_user_id !== $profile_user_id)
		{
			return;
		}

		$this->template->assign_vars([
			'S_TOPTOPICS_REACTION_RECORDS' => true,
			'U_TOPTOPICS_REACTION_RECORDS' => $this->helper->route('freemitbbs_toptopics_reaction_records'),
		]);
	}

	public function prefetch_dislikes($event)
	{
		$post_list = array_map('intval', $event['post_list']);
		if (empty($post_list))
		{
			return;
		}

		$sql = 'SELECT post_id, COUNT(user_id) AS dislike_count
			FROM ' . $this->dislikes_table . '
			WHERE ' . $this->db->sql_in_set('post_id', $post_list) . '
			GROUP BY post_id';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$this->post_dislike_counts[(int) $row['post_id']] = (int) $row['dislike_count'];
		}
		$this->db->sql_freeresult($result);

		$user_ids = [];
		foreach (($event['rowset'] ?? []) as $row)
		{
			$user_id = (int) ($row['user_id'] ?? ANONYMOUS);
			if ($user_id !== ANONYMOUS)
			{
				$user_ids[] = $user_id;
			}
		}

		if ((int) $this->user->data['user_id'] !== ANONYMOUS)
		{
			$user_ids[] = (int) $this->user->data['user_id'];
		}

		$this->user_reputation_scores = $this->reputation->get_scores($user_ids);
		if ((int) $this->user->data['user_id'] !== ANONYMOUS)
		{
			$this->current_user_reputation = $this->user_reputation_scores[(int) $this->user->data['user_id']] ?? 0;
		}

		if ($this->user->data['user_id'] == ANONYMOUS)
		{
			return;
		}

		$sql = 'SELECT post_id
			FROM ' . $this->dislikes_table . '
			WHERE user_id = ' . (int) $this->user->data['user_id'] . '
				AND ' . $this->db->sql_in_set('post_id', $post_list);
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$this->user_disliked_posts[(int) $row['post_id']] = true;
		}
		$this->db->sql_freeresult($result);
	}

	public function modify_post_row($event)
	{
		$post_id = (int) $event['row']['post_id'];
		$poster_id = isset($event['poster_id']) ? (int) $event['poster_id'] : (int) ($event['row']['user_id'] ?? ANONYMOUS);
		$current_user_id = (int) $this->user->data['user_id'];
		$dislike_count = $this->post_dislike_counts[$post_id] ?? 0;
		$current_user_disliked = !empty($this->user_disliked_posts[$post_id]);

		$disabled = false;
		$action = $current_user_disliked ? $this->language->lang('CLICK_TO_UNDISLIKE') : $this->language->lang('CLICK_TO_DISLIKE');

		if ($current_user_id == ANONYMOUS || !$this->auth->acl_get('u_toptopics_dislike'))
		{
			$disabled = true;
			$action = $this->language->lang('LOGIN_TO_DISLIKE_POST');
		}
		else if ($poster_id === $current_user_id)
		{
			$disabled = true;
			$action = $this->language->lang('CANT_DISLIKE_OWN_POST');
		}
		else if ((int) $this->user->data['user_posts'] < (int) $this->config['toptopics_downvote_min_posts'])
		{
			$disabled = true;
			$action = $this->language->lang('TOPTOPICS_MIN_POSTS_TO_DISLIKE', (int) $this->config['toptopics_downvote_min_posts']);
		}
		else if ($this->reputation->get_required_dislike_score() > 0
			&& $this->get_current_user_reputation() < $this->reputation->get_required_dislike_score())
		{
			$disabled = true;
			$action = $this->language->lang(
				'TOPTOPICS_MIN_REPUTATION_TO_DISLIKE',
				$this->format_reputation($this->reputation->get_required_dislike_score()),
				$this->format_reputation($this->get_current_user_reputation())
			);
		}

		$post_row = $event['post_row'];
		$post_row['POST_DISLIKE_CLASS'] = $current_user_disliked ? 'toptopics-disliked' : 'toptopics-dislike';
		$post_row['POST_DISLIKE_COUNT'] = $dislike_count;
		$post_row['POST_DISLIKERS'] = $this->language->lang('TOPTOPICS_DISLIKES_COUNT', $dislike_count);
		$post_row['POST_DISLIKE_ACTION'] = $action;
		$post_row['POST_DISLIKE_URL'] = $this->build_dislike_url($post_id, $current_user_disliked);
		$post_row['POST_DISLIKE_DISABLED'] = $disabled;
		if ($poster_id !== ANONYMOUS)
		{
			$reputation_score = $this->user_reputation_scores[$poster_id] ?? 0;
			$reputation_tier = $this->get_reputation_tier($reputation_score);
			$post_row['S_TOPTOPICS_USER_REPUTATION'] = true;
			$post_row['USER_REPUTATION'] = $this->format_reputation($reputation_score);
			$post_row['USER_REPUTATION_CLASS'] = $reputation_tier['class'];
			$post_row['USER_REPUTATION_TIER'] = $this->language->lang($reputation_tier['lang']);
			$post_row['USER_REPUTATION_TOOLTIP'] = $this->language->lang('TOPTOPICS_REPUTATION_TOOLTIP', $post_row['USER_REPUTATION'], $post_row['USER_REPUTATION_TIER']);
			$post_row['USER_REPUTATION_PROGRESS'] = $this->get_reputation_progress($reputation_score);
		}

		$forum_id = (int) ($event['row']['forum_id'] ?? 0);
		if (!empty($post_row['U_REPORT']) && !$this->can_current_user_report($forum_id))
		{
			$post_row['U_REPORT'] = '';
		}

		$event['post_row'] = $post_row;
	}

	public function report_post_auth($event): void
	{
		$forum_id = (int) ($event['report_data']['forum_id'] ?? 0);
		if ($forum_id <= 0 || $this->can_current_user_report($forum_id))
		{
			return;
		}

		throw new \phpbb\report\exception\report_permission_denied_exception($this->language->lang(
			'TOPTOPICS_MIN_REPUTATION_TO_REPORT',
			$this->format_reputation($this->reputation->get_required_report_score()),
			$this->format_reputation($this->get_current_user_reputation())
		));
	}

	public function viewtopic_admin_override($event): void
	{
		$topic_id = (int) ($event['topic_data']['topic_id'] ?? 0);
		$forum_id = (int) ($event['forum_id'] ?? 0);

		if (!$topic_id
			|| $this->user->data['is_bot']
			|| !$this->auth->acl_get('a_board')
			|| !$this->auth->acl_get('f_read', $forum_id))
		{
			return;
		}

		$current_state = $this->get_topic_override_state($topic_id);

		$this->template->assign_vars([
			'S_TOPTOPICS_ADMIN_OVERRIDE' => true,
			'TOPTOPICS_OVERRIDE_CURRENT' => $this->language->lang('TOPTOPICS_OVERRIDE_CURRENT_' . strtoupper($current_state ?: 'NORMAL')),
			'U_TOPTOPICS_OVERRIDE_NORMAL' => $this->build_override_url($topic_id, 'normal'),
			'U_TOPTOPICS_OVERRIDE_BOOST' => $this->build_override_url($topic_id, 'boost'),
			'U_TOPTOPICS_OVERRIDE_DEMOTE' => $this->build_override_url($topic_id, 'demote'),
			'U_TOPTOPICS_OVERRIDE_KILL' => $this->build_override_url($topic_id, 'kill'),
			'S_TOPTOPICS_OVERRIDE_NORMAL' => $current_state === '',
			'S_TOPTOPICS_OVERRIDE_BOOST' => $current_state === 'boost',
			'S_TOPTOPICS_OVERRIDE_DEMOTE' => $current_state === 'demote',
			'S_TOPTOPICS_OVERRIDE_KILL' => $current_state === 'kill',
		]);
	}

	public function index_category_blocks($event): void
	{
		$root_data = $event['root_data'] ?? [];
		if ((int) ($root_data['forum_id'] ?? -1) !== 0)
		{
			return;
		}

		$this->index_category_blocks = [];

		$forum_rows = $event['forum_rows'] ?? [];
		if (empty($forum_rows))
		{
			return;
		}

		$category_rows = [];
		foreach ($forum_rows as $forum_id => $row)
		{
			$forum_id = (int) $forum_id;
			if ((int) ($row['forum_type'] ?? FORUM_POST) === FORUM_CAT
				&& (int) ($row['parent_id'] ?? 0) === 0)
			{
				$category_rows[$forum_id] = $row;
				$this->index_category_blocks[$forum_id] = [];
			}
		}

		if (empty($category_rows))
		{
			return;
		}

		$topicpreview = $this->get_topicpreview_context();
		if ($topicpreview['enabled'])
		{
			$this->assign_topicpreview_template_vars($topicpreview);
		}

		$category_forums = [];
		foreach ($forum_rows as $row)
		{
			$parent_id = (int) ($row['parent_id'] ?? 0);
			if ((int) ($row['forum_type'] ?? FORUM_POST) !== FORUM_CAT && isset($category_rows[$parent_id]))
			{
				$category_forums[$parent_id][] = $row;
			}
		}

		if (empty($category_forums))
		{
			return;
		}

		$scope_map = [];
		$summary_scope_id = '__index_summary';
		$summary_forum_ids = $this->exclude_index_forum_ids($this->get_readable_forum_ids());
		$summary_display_limit = max(0, (int) $this->config['toptopics_index_limit']);
		if (!empty($summary_forum_ids))
		{
			$scope_map[$summary_scope_id] = [
				'forum_ids' => $summary_forum_ids,
				'limit' => $this->get_balanced_topic_fetch_limit($summary_display_limit, $summary_forum_ids),
			];
		}

		$category_topic_forum_ids = [];
		$category_candidate_limit = $this->get_index_category_candidate_limit();
		foreach ($category_forums as $category_id => $forums)
		{
			$topic_forum_ids = [];
			foreach ($forums as $forum)
			{
				if ((int) ($forum['forum_type'] ?? FORUM_POST) === FORUM_POST)
				{
					$topic_forum_ids[] = (int) $forum['forum_id'];
				}
			}
			$topic_forum_ids = $this->exclude_index_category_forum_ids($topic_forum_ids);

			$category_topic_forum_ids[$category_id] = $topic_forum_ids;
			if (!empty($topic_forum_ids))
			{
				$scope_map['category_' . $category_id] = [
					'forum_ids' => $topic_forum_ids,
					'limit' => $this->get_balanced_topic_fetch_limit($category_candidate_limit, $topic_forum_ids),
				];
			}
		}

		$scope_topics = !empty($scope_map)
			? $this->ranker->get_topics_for_scopes($scope_map)
			: [];
		$this->index_summary_topics = $this->apply_topic_list_limits(
			$this->modify_topic_list(
				$this->exclude_foe_authored_topics($scope_topics[$summary_scope_id] ?? []),
				'index_summary'
			),
			$summary_display_limit,
			$summary_forum_ids
		);
		$this->index_summary_topic_ids = [];
		foreach ($this->index_summary_topics as $topic)
		{
			$topic_id = (int) ($topic['topic_id'] ?? 0);
			if ($topic_id > 0)
			{
				$this->index_summary_topic_ids[$topic_id] = true;
			}
		}

		$all_topics = [];
		foreach ($category_forums as $category_id => $forums)
		{
			$topics = !empty($category_topic_forum_ids[$category_id])
				? $this->filter_index_category_topics($scope_topics['category_' . $category_id] ?? [], $category_topic_forum_ids[$category_id])
				: [];

			$this->index_category_blocks[$category_id] = [
				'forum_menu_html' => $this->build_category_forum_menu_html($forums, $category_id),
				'topic_ids' => array_map('intval', array_column($topics, 'topic_id')),
				'rows_html' => '',
			];

			foreach ($topics as $topic)
			{
				$all_topics[(int) $topic['topic_id']] = $topic;
			}
		}

		if (empty($all_topics))
		{
			foreach ($this->index_category_blocks as $category_id => $block)
			{
				$this->index_category_blocks[$category_id]['rows_html'] = $this->build_category_rows_html([]);
				unset($this->index_category_blocks[$category_id]['topic_ids']);
			}
			return;
		}

		$decorated_topics = $this->decorate_topics(array_values($all_topics), $topicpreview['enabled'], $topicpreview);
		$decorated_by_id = [];
		foreach ($decorated_topics as $topic)
		{
			$decorated_by_id[(int) $topic['topic_id']] = $topic;
		}

		foreach ($this->index_category_blocks as $category_id => $block)
		{
			$category_topics = [];
			foreach ($block['topic_ids'] as $topic_id)
			{
				if (isset($decorated_by_id[$topic_id]))
				{
					$category_topics[] = $decorated_by_id[$topic_id];
				}
			}

				$this->index_category_blocks[$category_id]['rows_html'] = $this->build_category_rows_html($category_topics);
				unset($this->index_category_blocks[$category_id]['topic_ids']);
			}
		}

	public function viewforum_exclude_foe_topics($event): void
	{
		$sql_ary = $event['sql_ary'] ?? null;
		if (!is_array($sql_ary) || empty($sql_ary['WHERE']))
		{
			return;
		}

		$non_foe_topic_sql = $this->build_non_foe_topic_sql('t');
		if ($non_foe_topic_sql === '')
		{
			return;
		}

		$sql_ary['WHERE'] .= $non_foe_topic_sql;
		$event['sql_ary'] = $sql_ary;
	}

	public function display_forums_modify_category_template_vars($event): void
	{
		$category_id = (int) (($event['row']['forum_id'] ?? 0));
		if (!isset($this->index_category_blocks[$category_id]))
		{
			$this->index_category_blocks[$category_id] = $this->build_index_category_block($category_id);
		}

		if (empty($this->index_category_blocks[$category_id]))
		{
			return;
		}

		$cat_row = $event['cat_row'];
		$cat_row['S_TOPTOPICS_CATEGORY_BLOCK'] = true;
		$cat_row['TOPTOPICS_CATEGORY_FORUM_MENU'] = $this->index_category_blocks[$category_id]['forum_menu_html'];
		$cat_row['TOPTOPICS_CATEGORY_ROWS_HTML'] = $this->index_category_blocks[$category_id]['rows_html'];
		$event['cat_row'] = $cat_row;
	}

	public function index_page_summary($event)
	{
		if ($this->user->data['is_bot'])
		{
			return;
		}

		$topics = $this->get_index_summary_topics();
		$this->template->assign_var(
			'S_TOPTOPICS_INDEX_BELOW',
			isset($this->config['postlove_summary_position']) ? (bool) $this->config['postlove_summary_position'] : true
		);
		$this->assign_summary(
			$topics,
			'S_TOPTOPICS_INDEX_ITEMS',
			$this->language->lang('TOPTOPICS_INDEX_TITLE'),
			true,
			'toptopics_index',
			$this->exclude_index_forum_ids($this->get_readable_forum_ids()),
			max(0, (int) $this->config['toptopics_index_limit'])
		);
	}

	public function forum_page_summary($event)
	{
		if ((int) ($event['start'] ?? 0) > 0)
		{
			return;
		}

		if ($this->user->data['is_bot'] || $this->user_hides_forum_summary())
		{
			return;
		}

		$forum_id = (int) $event['forum_id'];
		$forum_ids = [$forum_id];
		$forum_read_ary = $this->auth->acl_getf('f_read');
		$forum_list_ary = $this->auth->acl_getf('f_list');
		$include_forum_name = false;

		if ((int) $event['forum_data']['left_id'] !== (int) $event['forum_data']['right_id'] - 1)
		{
			$include_forum_name = true;
			$sql = 'SELECT forum_id
				FROM ' . FORUMS_TABLE . '
				WHERE parent_id = ' . $forum_id;
			$result = $this->db->sql_query($sql);
			while ($row = $this->db->sql_fetchrow($result))
			{
				$child_forum_id = (int) $row['forum_id'];
				if (!empty($forum_read_ary[$child_forum_id]['f_read']) && !empty($forum_list_ary[$child_forum_id]['f_list']))
				{
					$forum_ids[] = $child_forum_id;
				}
			}
			$this->db->sql_freeresult($result);
		}

		$display_limit = max(0, (int) $this->config['toptopics_forum_limit']);
		$topics = $this->ranker->get_topics($forum_ids, $this->get_balanced_topic_fetch_limit($display_limit, $forum_ids));
		$this->assign_summary($topics, 'S_TOPTOPICS_FORUM_ITEMS', $this->language->lang('TOPTOPICS_FORUM_TITLE'), $include_forum_name, 'toptopics_forum_' . $forum_id, $forum_ids, $display_limit);
	}

	public function submit_post_end($event): void
	{
		$data = $event['data'] ?? [];
		$post_id = (int) ($data['post_id'] ?? 0);
		if ($post_id > 0)
		{
			$this->reputation->sync_post($post_id);
		}
	}

	public function clean_posts_after($event)
	{
		$sql = 'DELETE FROM ' . $this->dislikes_table . '
			WHERE ' . $this->db->sql_in_set('post_id', array_map('intval', $event['post_ids']));
		$this->db->sql_query($sql);

		$sql = 'DELETE FROM ' . $this->dislike_history_table . '
			WHERE ' . $this->db->sql_in_set('post_id', array_map('intval', $event['post_ids']));
		$this->db->sql_query($sql);

		$this->reputation->remove_posts($event['post_ids'] ?? [], $event['poster_ids'] ?? []);
		$this->invalidate_rank_cache_for_forums($event['forum_ids'] ?? []);
	}

	public function clean_users_after($event)
	{
		$sql = 'DELETE FROM ' . $this->dislikes_table . '
			WHERE ' . $this->db->sql_in_set('user_id', array_map('intval', $event['user_ids']));
		$this->db->sql_query($sql);

		$sql = 'DELETE FROM ' . $this->dislike_history_table . '
			WHERE ' . $this->db->sql_in_set('user_id', array_map('intval', $event['user_ids']));
		$this->db->sql_query($sql);

		$sql = 'DELETE FROM ' . $this->user_reputation_table . '
			WHERE ' . $this->db->sql_in_set('user_id', array_map('intval', $event['user_ids']));
		$this->db->sql_query($sql);

		$this->invalidate_all_rank_cache();
	}

	public function post_visibility_after($event): void
	{
		$forum_id = (int) ($event['forum_id'] ?? 0);
		if ($forum_id > 0)
		{
			$this->invalidate_rank_cache_for_forums([$forum_id]);
			$this->ranker->clear_materialized_scopes_for_forums([$forum_id]);
		}
		else
		{
			$this->invalidate_all_rank_cache();
		}

		$this->sync_reputation_for_visibility_event($event);
	}

	public function topic_visibility_after($event): void
	{
		$forum_id = (int) ($event['forum_id'] ?? 0);
		if ($forum_id > 0)
		{
			$this->invalidate_rank_cache_for_forums([$forum_id]);
			$this->ranker->clear_materialized_scopes_for_forums([$forum_id]);
			$this->sync_reputation_for_topic_id((int) ($event['topic_id'] ?? 0));
			return;
		}

		$this->invalidate_all_rank_cache();
		$this->sync_reputation_for_topic_id((int) ($event['topic_id'] ?? 0));
	}

	public function report_notification_added($event): void
	{
		if (($event['notification_type_name'] ?? '') === 'notification.type.report_post')
		{
			$data = $event['data'] ?? [];
			$forum_id = (int) ($data['forum_id'] ?? 0);
			if ($forum_id > 0)
			{
				$this->invalidate_rank_cache_for_forums([$forum_id]);
			}
			else
			{
				$this->invalidate_all_rank_cache();
			}

			$post_id = (int) ($data['post_id'] ?? 0);
			if ($post_id > 0)
			{
				$this->refresh_reputation_for_post_ids([$post_id]);
			}
		}
	}

	public function report_log_added($event): void
	{
		if (($event['mode'] ?? '') !== 'mod')
		{
			return;
		}

		if (in_array((string) ($event['log_operation'] ?? ''), ['LOG_REPORT_CLOSED', 'LOG_REPORT_DELETED'], true))
		{
			$sql_ary = $event['sql_ary'] ?? [];
			$forum_id = (int) ($sql_ary['forum_id'] ?? 0);
			if ($forum_id > 0)
			{
				$this->invalidate_rank_cache_for_forums([$forum_id]);
			}
			else
			{
				$this->invalidate_all_rank_cache();
			}

			$post_id = (int) ($sql_ary['post_id'] ?? 0);
			if ($post_id > 0)
			{
				$this->refresh_reputation_for_post_ids([$post_id]);
			}
		}
	}

	protected function build_dislike_url(int $post_id, bool $current_user_disliked): string
	{
		return $this->helper->route('freemitbbs_toptopics_vote', [
			'action' => $current_user_disliked ? 'remove' : 'add',
			'post' => $post_id,
			'hash' => generate_link_hash('toptopics_dislike_' . $post_id),
		]);
	}

	protected function build_override_url(int $topic_id, string $state): string
	{
		return $this->helper->route('freemitbbs_toptopics_override', [
			'topic' => $topic_id,
			'state' => $state,
			'hash' => generate_link_hash('toptopics_override_' . $topic_id . '_' . $state),
		]);
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

		return array_values(array_unique($forum_ids));
	}

	protected function get_index_summary_topics(): array
	{
		if ($this->index_summary_topics !== null)
		{
			return $this->index_summary_topics;
		}

		if ($this->user->data['is_bot'])
		{
			$this->index_summary_topics = [];
			$this->index_summary_topic_ids = [];
			return [];
		}

		$forum_ids = $this->exclude_index_forum_ids($this->get_readable_forum_ids());
		$display_limit = max(0, (int) $this->config['toptopics_index_limit']);
		$this->index_summary_topics = $this->apply_topic_list_limits(
			$this->modify_topic_list(
				$this->exclude_foe_authored_topics(
					$this->ranker->get_topics($forum_ids, $this->get_balanced_topic_fetch_limit($display_limit, $forum_ids))
				),
				'index_summary'
			),
			$display_limit,
			$forum_ids
		);
		$this->index_summary_topic_ids = [];

		foreach ($this->index_summary_topics as $topic)
		{
			$topic_id = (int) ($topic['topic_id'] ?? 0);
			if ($topic_id > 0)
			{
				$this->index_summary_topic_ids[$topic_id] = true;
			}
		}

		return $this->index_summary_topics;
	}

	protected function user_hides_forum_summary(): bool
	{
		return phpbb_optionget(self::USER_OPTION_HIDE_FORUM_SUMMARY, (int) $this->user->data['user_options']);
	}

	protected function user_shows_mobile_topic_stats(): bool
	{
		return phpbb_optionget(self::USER_OPTION_SHOW_MOBILE_TOPIC_STATS, (int) $this->user->data['user_options']);
	}

	protected function get_index_summary_topic_id_map(): array
	{
		if ($this->index_summary_topic_ids === null)
		{
			$this->get_index_summary_topics();
		}

		return $this->index_summary_topic_ids ?? [];
	}

	protected function get_index_category_candidate_limit(): int
	{
		if ($this->index_category_candidate_limit === null)
		{
			$this->index_category_candidate_limit = max(0, (int) $this->config['toptopics_forum_limit'])
				+ max(0, (int) $this->config['toptopics_index_limit']);
		}

		return $this->index_category_candidate_limit;
	}

	protected function get_balanced_topic_fetch_limit(int $display_limit, array $forum_ids): int
	{
		$display_limit = max(0, $display_limit);
		if ($display_limit <= 0)
		{
			return 0;
		}

		$forum_ids = $this->normalise_forum_ids($forum_ids);
		if (!$this->should_limit_topics_per_forum($forum_ids))
		{
			return $display_limit;
		}

		$configured_candidate_limit = isset($this->config['toptopics_candidate_pool_limit'])
			? (int) $this->config['toptopics_candidate_pool_limit']
			: self::DEFAULT_CANDIDATE_POOL_LIMIT;
		$configured_candidate_limit = max(50, min(20000, $configured_candidate_limit));

		return max(
			$display_limit,
			min(
				$configured_candidate_limit,
				max(
					$display_limit,
					$display_limit * self::BALANCED_TOPIC_FETCH_MULTIPLIER,
					count($forum_ids) * $this->get_per_forum_topic_limit()
				)
			)
		);
	}

	protected function apply_topic_list_limits(array $topics, int $display_limit, array $forum_ids): array
	{
		$display_limit = max(0, $display_limit);
		if ($display_limit <= 0 || empty($topics))
		{
			return [];
		}

		$topics = $this->limit_topics_per_forum($topics, $forum_ids);
		if (count($topics) > $display_limit)
		{
			return array_slice($topics, 0, $display_limit);
		}

		return $topics;
	}

	protected function limit_topics_per_forum(array $topics, array $forum_ids): array
	{
		$forum_ids = $this->normalise_forum_ids($forum_ids);
		if (!$this->should_limit_topics_per_forum($forum_ids))
		{
			return array_values($topics);
		}

		$per_forum_limit = $this->get_per_forum_topic_limit();
		$forum_counts = [];
		$limited_topics = [];
		foreach ($topics as $topic)
		{
			$forum_id = (int) ($topic['forum_id'] ?? 0);
			if ($forum_id > 0 && in_array($forum_id, $forum_ids, true))
			{
				$forum_counts[$forum_id] = (int) ($forum_counts[$forum_id] ?? 0);
				if ($forum_counts[$forum_id] >= $per_forum_limit)
				{
					continue;
				}
				$forum_counts[$forum_id]++;
			}

			$limited_topics[] = $topic;
		}

		return $limited_topics;
	}

	protected function should_limit_topics_per_forum(array $forum_ids): bool
	{
		return $this->get_per_forum_topic_limit() > 0
			&& count($this->normalise_forum_ids($forum_ids)) > 1;
	}

	protected function get_per_forum_topic_limit(): int
	{
		$value = isset($this->config['toptopics_per_forum_limit'])
			? (int) $this->config['toptopics_per_forum_limit']
			: self::DEFAULT_PER_FORUM_TOPIC_LIMIT;

		return max(0, min(100, $value));
	}

	protected function normalise_forum_ids(array $forum_ids): array
	{
		$forum_ids = array_values(array_unique(array_filter(array_map('intval', $forum_ids), static function ($forum_id) {
			return $forum_id > 0;
		})));
		sort($forum_ids);

		return $forum_ids;
	}

	protected function exclude_index_forum_ids(array $forum_ids): array
	{
		return $this->exclude_forum_ids_by_map($forum_ids, $this->get_index_excluded_forum_id_map());
	}

	protected function exclude_index_category_forum_ids(array $forum_ids): array
	{
		return $this->exclude_forum_ids_by_map($forum_ids, $this->get_index_category_excluded_forum_id_map());
	}

	protected function get_index_excluded_forum_id_map(): array
	{
		if ($this->index_excluded_forum_id_map !== null)
		{
			return $this->index_excluded_forum_id_map;
		}

		$this->index_excluded_forum_id_map = $this->parse_forum_id_map((string) ($this->config['toptopics_index_excluded_forum_ids'] ?? ''));
		return $this->index_excluded_forum_id_map;
	}

	protected function get_index_category_excluded_forum_id_map(): array
	{
		if ($this->index_category_excluded_forum_id_map !== null)
		{
			return $this->index_category_excluded_forum_id_map;
		}

		$this->index_category_excluded_forum_id_map = $this->parse_forum_id_map((string) ($this->config['toptopics_index_category_excluded_forum_ids'] ?? ''));
		return $this->index_category_excluded_forum_id_map;
	}

	protected function parse_forum_id_map(string $configured_ids): array
	{
		$configured_ids = preg_replace('/\s+/', '', trim($configured_ids));
		if ($configured_ids === '')
		{
			return [];
		}

		$forum_ids = [];
		foreach (explode(',', $configured_ids) as $part)
		{
			$forum_id = (int) $part;
			if ($forum_id > 0)
			{
				$forum_ids[$forum_id] = true;
			}
		}

		return $forum_ids;
	}

	protected function exclude_forum_ids_by_map(array $forum_ids, array $excluded_forum_ids): array
	{
		$forum_ids = array_values(array_unique(array_filter(array_map('intval', $forum_ids), static function ($forum_id) {
			return $forum_id > 0;
		})));
		if (empty($forum_ids))
		{
			return [];
		}

		if (empty($excluded_forum_ids))
		{
			sort($forum_ids);
			return $forum_ids;
		}

		$filtered_forum_ids = [];
		foreach ($forum_ids as $forum_id)
		{
			if (!isset($excluded_forum_ids[$forum_id]))
			{
				$filtered_forum_ids[] = $forum_id;
			}
		}

		sort($filtered_forum_ids);
		return $filtered_forum_ids;
	}

	protected function filter_index_category_topics(array $topics, array $forum_ids): array
	{
		$forum_limit = max(0, (int) $this->config['toptopics_forum_limit']);
		if ($forum_limit <= 0 || empty($topics))
		{
			return [];
		}

		$filtered_topics = $this->exclude_foe_authored_topics($topics);
		$filtered_topics = $this->exclude_topics_present_in_index_summary($filtered_topics);
		$filtered_topics = $this->modify_topic_list($filtered_topics, 'index_category');

		return $this->apply_topic_list_limits($filtered_topics, $forum_limit, $forum_ids);
	}

	protected function exclude_topics_present_in_index_summary(array $topics): array
	{
		if (empty($topics))
		{
			return [];
		}

		$excluded_topic_ids = $this->get_index_summary_topic_id_map();
		$recenttopics_topic_ids = $this->get_index_recenttopics_topic_id_map();
		if (!empty($recenttopics_topic_ids))
		{
			$excluded_topic_ids += $recenttopics_topic_ids;
		}

		if (empty($excluded_topic_ids))
		{
			return array_values($topics);
		}

		$filtered_topics = [];
		foreach ($topics as $topic)
		{
			$topic_id = (int) ($topic['topic_id'] ?? 0);
			if ($topic_id > 0 && isset($excluded_topic_ids[$topic_id]))
			{
				continue;
			}

			$filtered_topics[] = $topic;
		}

		return $filtered_topics;
	}

	protected function get_index_recenttopics_topic_id_map(): array
	{
		if ($this->index_recenttopics_topic_id_map !== null)
		{
			return $this->index_recenttopics_topic_id_map;
		}

		$this->index_recenttopics_topic_id_map = [];
		$recenttopicsng_functions = $this->get_recenttopicsng_functions_service();
		if (empty($recenttopicsng_functions)
			|| !method_exists($recenttopicsng_functions, 'get_index_topic_ids_for_dedupe'))
		{
			return $this->index_recenttopics_topic_id_map;
		}

		try
		{
			$topic_ids = $recenttopicsng_functions->get_index_topic_ids_for_dedupe(
				'rtng_topics',
				array_keys($this->get_index_summary_topic_id_map())
			);
		}
		catch (\Throwable $exception)
		{
			return $this->index_recenttopics_topic_id_map;
		}

		if (!is_array($topic_ids))
		{
			return $this->index_recenttopics_topic_id_map;
		}

		foreach ($topic_ids as $topic_id)
		{
			$topic_id = (int) $topic_id;
			if ($topic_id > 0)
			{
				$this->index_recenttopics_topic_id_map[$topic_id] = true;
			}
		}

		return $this->index_recenttopics_topic_id_map;
	}

	protected function get_recenttopicsng_functions_service()
	{
		if ($this->recenttopicsng_functions !== null)
		{
			return $this->recenttopicsng_functions;
		}

		global $phpbb_container;
		if (empty($phpbb_container)
			|| !method_exists($phpbb_container, 'has')
			|| !$phpbb_container->has('imcger.recenttopicsng.functions'))
		{
			return null;
		}

		try
		{
			$this->recenttopicsng_functions = $phpbb_container->get('imcger.recenttopicsng.functions');
		}
		catch (\Throwable $exception)
		{
			return null;
		}

		return $this->recenttopicsng_functions;
	}

	protected function get_topic_override_state(int $topic_id): string
	{
		$sql = 'SELECT override_state
			FROM ' . $this->topic_overrides_table . '
			WHERE topic_id = ' . $topic_id;
		$result = $this->db->sql_query_limit($sql, 1);
		$state = (string) $this->db->sql_fetchfield('override_state');
		$this->db->sql_freeresult($result);

		return in_array($state, ['boost', 'demote', 'kill'], true) ? $state : '';
	}

	protected function build_category_forum_menu_html(array $forums, int $category_id): string
	{
		$forums = $this->sort_category_forums_by_viewership($forums);
		$html = '';
		foreach ($forums as $forum)
		{
			$url = append_sid($this->root_path . 'viewforum.' . $this->php_ext, 'f=' . (int) $forum['forum_id']);
			$name = $this->escape_text(censor_text((string) ($forum['forum_name'] ?? '')));
			$html .= '<a href="' . $url . '" class="toptopics-category-forum-link">' . $name . '</a>';
		}

		if (count($forums) > 1)
		{
			$more_url = append_sid($this->root_path . 'viewforum.' . $this->php_ext, 'f=' . $category_id);
			$html .= '<a href="' . $more_url . '" class="toptopics-category-forum-link toptopics-category-forum-more" aria-label="'
				. $this->escape_attr($this->language->lang('TOPTOPICS_CATEGORY_MORE'))
				. '">'
				. $this->escape_text($this->language->lang('TOPTOPICS_CATEGORY_MORE'))
				. '</a>';
		}

		return $html;
	}

	protected function sort_category_forums_by_viewership(array $forums): array
	{
		if (count($forums) < 2)
		{
			return $forums;
		}

		$forum_order = $this->get_forum_viewership_order();
		if (empty($forum_order))
		{
			return $forums;
		}

		usort($forums, static function (array $left, array $right) use ($forum_order): int {
			$left_rank = $forum_order[(int) ($left['forum_id'] ?? 0)] ?? PHP_INT_MAX;
			$right_rank = $forum_order[(int) ($right['forum_id'] ?? 0)] ?? PHP_INT_MAX;
			if ($left_rank !== $right_rank)
			{
				return $left_rank <=> $right_rank;
			}

			$left_order = (int) ($left['left_id'] ?? 0);
			$right_order = (int) ($right['left_id'] ?? 0);
			if ($left_order !== $right_order)
			{
				return $left_order <=> $right_order;
			}

			return (int) ($left['forum_id'] ?? 0) <=> (int) ($right['forum_id'] ?? 0);
		});

		return $forums;
	}

	protected function get_forum_viewership_order(): array
	{
		if ($this->index_forum_viewership_order !== null)
		{
			return $this->index_forum_viewership_order;
		}

		$this->index_forum_viewership_order = [];
		$class = '\\freemitbbs\\hotforums\\service\\viewership';
		if (!class_exists($class))
		{
			return $this->index_forum_viewership_order;
		}

		global $phpbb_container;
		if (empty($phpbb_container)
			|| !method_exists($phpbb_container, 'get'))
		{
			return $this->index_forum_viewership_order;
		}

		try
		{
			$viewership = new $class($this->auth, $phpbb_container->get('content.visibility'), $this->db);
			$this->index_forum_viewership_order = $viewership->get_order_by_forum_id();
		}
		catch (\Throwable $exception)
		{
			$this->index_forum_viewership_order = [];
		}

		return $this->index_forum_viewership_order;
	}

	protected function build_index_category_block(int $category_id): array
	{
		if ($category_id <= 0)
		{
			return [];
		}

		$forum_read_ary = $this->auth->acl_getf('f_read');
		$forum_list_ary = $this->auth->acl_getf('f_list');
		$forums = [];
		$topic_forum_ids = [];

		$sql = 'SELECT forum_id, forum_name, forum_type, left_id
			FROM ' . FORUMS_TABLE . '
			WHERE parent_id = ' . $category_id . '
			ORDER BY left_id ASC';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$forum_id = (int) $row['forum_id'];
			if (empty($forum_list_ary[$forum_id]['f_list']))
			{
				continue;
			}

			$forums[] = $row;
			if ((int) $row['forum_type'] === FORUM_POST && !empty($forum_read_ary[$forum_id]['f_read']))
			{
				$topic_forum_ids[] = $forum_id;
			}
		}
		$this->db->sql_freeresult($result);

		if (empty($forums))
		{
			return [];
		}

		$topic_forum_ids = $this->exclude_index_category_forum_ids($topic_forum_ids);
		$topics = !empty($topic_forum_ids)
			? $this->filter_index_category_topics(
				$this->ranker->get_topics($topic_forum_ids, $this->get_balanced_topic_fetch_limit($this->get_index_category_candidate_limit(), $topic_forum_ids)),
				$topic_forum_ids
			)
			: [];
		$topicpreview = $this->get_topicpreview_context();
		if ($topicpreview['enabled'])
		{
			$this->assign_topicpreview_template_vars($topicpreview);
		}
		$topics = $this->decorate_topics($topics, $topicpreview['enabled'], $topicpreview);

		return [
			'forum_menu_html' => $this->build_category_forum_menu_html($forums, $category_id),
			'rows_html' => $this->build_category_rows_html($topics),
		];
	}

	protected function build_category_rows_html(array $topics): string
	{
		$topics = $this->exclude_topics_present_in_index_summary($topics);

		if (empty($topics))
		{
			return '<li class="row bg1 toptopics-category-empty">'
				. '<dl class="row-item">'
				. '<dt><div class="list-inner">' . $this->escape_text($this->language->lang('TOPTOPICS_CATEGORY_EMPTY')) . '</div></dt>'
				. '<dd class="topics">&nbsp;</dd>'
				. '<dd class="posts">&nbsp;</dd>'
				. '<dd class="lastpost">&nbsp;</dd>'
				. '</dl>'
				. '</li>';
		}

		$html = '';
		foreach ($topics as $index => $topic)
		{
			$row_class = ($index % 2 === 0) ? 'bg1' : 'bg2';
			$topic_url = append_sid($this->root_path . 'viewtopic.' . $this->php_ext, 'f=' . (int) $topic['forum_id'] . '&t=' . (int) $topic['topic_id']);
			$forum_url = append_sid($this->root_path . 'viewforum.' . $this->php_ext, 'f=' . (int) $topic['forum_id']);
			$topic_title = $this->escape_text(censor_text((string) $topic['topic_title']));
			$forum_name = $this->escape_text((string) $topic['forum_name']);
			$meta = $this->escape_text($this->language->lang('POST_BY_AUTHOR')) . ' '
				. get_username_string('full', (int) $topic['topic_poster'], $topic['topic_first_poster_name'], $topic['topic_first_poster_colour'])
				. ' &bull; ' . $this->user->format_date((int) $topic['topic_time'])
				. ' &bull; <a href="' . $forum_url . '" class="toptopics-category-badge">' . $forum_name . '</a>';

			$unread_icon = '';
			if (!empty($topic['unread_topic']) && !$this->user->data['is_bot'])
			{
				$unread_icon = '<a class="unread" href="' . $topic['u_newest_post'] . '">'
					. '<i class="icon fa-file fa-fw icon-red icon-md" aria-hidden="true"></i>'
					. '<span class="sr-only">' . $this->escape_text($this->language->lang('NEW_POST')) . '</span>'
					. '</a>';
			}

				$html .= '<li class="row ' . $row_class . ' toptopics-category-row">'
					. '<dl class="row-item ' . $this->escape_attr((string) ($topic['topic_img_style'] ?? '')) . '">'
					. '<dt title="' . $this->escape_attr((string) ($topic['topic_folder_img_alt'] ?? '')) . '">'
					. ((!empty($topic['unread_topic']) && !$this->user->data['is_bot']) ? '<a href="' . $topic['u_newest_post'] . '" class="row-item-link"></a>' : '')
					. '<div class="list-inner">'
					. $unread_icon
					. '<a href="' . $topic_url . '" class="topictitle">' . $topic_title . '</a>'
					. '<div class="toptopics-meta">' . $meta . '</div>'
					. $this->build_mobile_stats_html($topic)
					. $this->build_mobile_lastpost_html($topic)
					. '</div>'
					. '</dt>'
					. '<dd class="topics">' . (int) ($topic['replies'] ?? 0) . ' <dfn>' . $this->escape_text($this->language->lang('REPLIES')) . '</dfn></dd>'
					. '<dd class="posts">' . (int) ($topic['views'] ?? 0) . ' <dfn>' . $this->escape_text($this->language->lang('VIEWS')) . '</dfn></dd>'
					. $this->build_lastpost_column_html($topic)
					. '</dl>'
					. $this->build_topic_preview_html($topic)
					. '</li>';
		}

		return $html;
	}

	protected function build_topic_preview_html(array $topic): string
	{
		$topic_id = (int) ($topic['topic_id'] ?? 0);
		if ($topic_id <= 0 || !$this->is_topic_preview_enabled())
		{
			return '';
		}

		return '<div class="topic_preview_content" data-topic-preview-id="' . $topic_id . '" style="display: none;"></div>';
	}

	protected function refresh_reputation_for_post_ids(array $post_ids): void
	{
		$post_ids = array_values(array_unique(array_filter(array_map('intval', $post_ids))));
		if (empty($post_ids))
		{
			return;
		}

		$sql = 'SELECT DISTINCT poster_id
			FROM ' . POSTS_TABLE . '
			WHERE ' . $this->db->sql_in_set('post_id', $post_ids);
		$result = $this->db->sql_query($sql);
		$user_ids = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$user_ids[] = (int) $row['poster_id'];
		}
		$this->db->sql_freeresult($result);

		$this->reputation->refresh_users($user_ids);
	}

	protected function sync_reputation_for_visibility_event($event): void
	{
		$post_ids = $event['post_id'] ?? [];
		if (is_array($post_ids) && !empty($post_ids))
		{
			$this->reputation->sync_posts($post_ids);
			return;
		}

		if ((int) $post_ids > 0)
		{
			$this->reputation->sync_post((int) $post_ids);
			return;
		}

		$this->sync_reputation_for_topic_id((int) ($event['topic_id'] ?? 0));
	}

	protected function sync_reputation_for_topic_id(int $topic_id): void
	{
		if ($topic_id <= 0)
		{
			return;
		}

		$this->reputation->sync_topic($topic_id);
	}

	protected function can_current_user_report(int $forum_id): bool
	{
		if ($forum_id <= 0)
		{
			return false;
		}

		if ($this->user->data['user_id'] == ANONYMOUS)
		{
			return false;
		}

		if ($this->auth->acl_get('a_board') || $this->auth->acl_get('m_report', $forum_id))
		{
			return true;
		}

		$required_score = $this->reputation->get_required_report_score();
		if ($required_score <= 0)
		{
			return true;
		}

		return $this->get_current_user_reputation() >= $required_score;
	}

	protected function get_current_user_reputation(): int
	{
		if ($this->current_user_reputation === null)
		{
			$this->current_user_reputation = $this->reputation->get_score((int) $this->user->data['user_id']);
		}

		return $this->current_user_reputation;
	}

	protected function format_reputation(int $score): string
	{
		return (string) $score;
	}

	protected function get_reputation_tier(int $score): array
	{
		if ($score < 0)
		{
			return ['class' => 'disputed', 'lang' => 'TOPTOPICS_REPUTATION_TIER_NEGATIVE'];
		}

		if ($score < 50)
		{
			return ['class' => 'fresh', 'lang' => 'TOPTOPICS_REPUTATION_TIER_NEUTRAL'];
		}

		if ($score < 150)
		{
			return ['class' => 'steady', 'lang' => 'TOPTOPICS_REPUTATION_TIER_POSITIVE'];
		}

		if ($score < 400)
		{
			return ['class' => 'regular', 'lang' => 'TOPTOPICS_REPUTATION_TIER_TRUSTED'];
		}

		if ($score < 1000)
		{
			return ['class' => 'pillar', 'lang' => 'TOPTOPICS_REPUTATION_TIER_ELITE'];
		}

		return ['class' => 'legend', 'lang' => 'TOPTOPICS_REPUTATION_TIER_LEGEND'];
	}

	protected function get_reputation_progress(int $score): int
	{
		if ($score < 0)
		{
			return 100;
		}

		if ($score < 50)
		{
			return max(8, (int) round(($score / 50) * 100));
		}

		if ($score < 150)
		{
			return (int) round((($score - 50) / 100) * 100);
		}

		if ($score < 400)
		{
			return (int) round((($score - 150) / 250) * 100);
		}

		if ($score < 1000)
		{
			return (int) round((($score - 400) / 600) * 100);
		}

		return 100;
	}

	protected function escape_text(string $text): string
	{
		return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	protected function escape_attr(string $text): string
	{
		return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	protected function build_last_post_url(array $topic): string
	{
		$last_post_id = (int) ($topic['topic_last_post_id'] ?? 0);
		if ($last_post_id > 0)
		{
			return append_sid($this->root_path . 'viewtopic.' . $this->php_ext, 'p=' . $last_post_id) . '#p' . $last_post_id;
		}

		return append_sid(
			$this->root_path . 'viewtopic.' . $this->php_ext,
			'f=' . (int) ($topic['forum_id'] ?? 0) . '&t=' . (int) ($topic['topic_id'] ?? 0)
		);
	}

	protected function get_last_post_author_full(array $topic): string
	{
		$last_poster_id = (int) ($topic['topic_last_poster_id'] ?? 0);
		$last_poster_name = (string) ($topic['topic_last_poster_name'] ?? '');
		$last_poster_colour = (string) ($topic['topic_last_poster_colour'] ?? '');

		if ($last_poster_name === '')
		{
			$last_poster_id = (int) ($topic['topic_poster'] ?? 0);
			$last_poster_name = (string) ($topic['topic_first_poster_name'] ?? '');
			$last_poster_colour = (string) ($topic['topic_first_poster_colour'] ?? '');
		}

		return get_username_string('full', $last_poster_id, $last_poster_name, $last_poster_colour);
	}

	protected function get_last_post_time_text(array $topic): string
	{
		return $this->user->format_date((int) ($topic['topic_last_post_time'] ?? $topic['topic_time'] ?? 0));
	}

	protected function get_last_post_time_rfc3339(array $topic): string
	{
		return gmdate(DATE_RFC3339, (int) ($topic['topic_last_post_time'] ?? $topic['topic_time'] ?? 0));
	}

	protected function build_mobile_stats_html(array $topic): string
	{
		$stats = [
			$this->language->lang('REPLIES') => (int) ($topic['replies'] ?? 0),
			$this->language->lang('VIEWS') => (int) ($topic['views'] ?? 0),
		];

		$html = '<div class="responsive-show toptopics-mobile-stats" style="display: none;">';
		foreach ($stats as $label => $value)
		{
			$html .= '<span class="toptopics-mobile-stat">'
				. $this->escape_text($label)
				. $this->escape_text($this->language->lang('COLON'))
				. ' <strong>' . $value . '</strong></span>';
		}
		$html .= '</div>';

		return $html;
	}

	protected function build_mobile_lastpost_html(array $topic): string
	{
		return '<div class="responsive-show toptopics-mobile-lastpost" style="display: none;">'
			. $this->escape_text($this->language->lang('LAST_POST')) . ' '
				. $this->escape_text($this->language->lang('POST_BY_AUTHOR')) . ' '
				. $this->get_last_post_author_full($topic)
				. ' &laquo; <a href="' . $this->build_last_post_url($topic) . '" title="'
				. $this->escape_attr($this->language->lang('GOTO_LAST_POST')) . '">'
				. $this->escape_text($this->get_last_post_time_text($topic))
				. '</a></div>';
	}

	protected function build_lastpost_column_html(array $topic): string
	{
		$html = '<dd class="lastpost"><span><dfn>'
			. $this->escape_text($this->language->lang('LAST_POST'))
			. ' </dfn>'
			. $this->escape_text($this->language->lang('POST_BY_AUTHOR'))
			. ' '
			. $this->get_last_post_author_full($topic);

		$last_post_url = $this->build_last_post_url($topic);
		if (!$this->user->data['is_bot'] && $last_post_url !== '')
		{
			$html .= ' <a href="' . $last_post_url . '" title="'
				. $this->escape_attr($this->language->lang('GOTO_LAST_POST'))
				. '"><i class="icon fa-external-link-square fa-fw icon-lightgray icon-md" aria-hidden="true"></i>'
				. '<span class="sr-only">' . $this->escape_text($this->language->lang('VIEW_LATEST_POST')) . '</span></a>';
		}

		$html .= '<br /><time datetime="'
			. $this->escape_attr($this->get_last_post_time_rfc3339($topic))
			. '">'
			. $this->escape_text($this->get_last_post_time_text($topic))
			. '</time></span></dd>';

		return $html;
	}

	protected function assign_summary(array $topics, string $template_flag, string $title, bool $include_forum_name, string $collapse_id, ?array $forum_ids = null, ?int $display_limit = null): void
	{
		$topics = $this->modify_topic_list($this->exclude_foe_authored_topics($topics), $collapse_id);
		if ($forum_ids !== null && $display_limit !== null)
		{
			$topics = $this->apply_topic_list_limits($topics, $display_limit, $forum_ids);
		}

		if (empty($topics))
		{
			return;
		}

		$topicpreview = $this->get_topicpreview_context();
		$topics = $this->decorate_topics($topics, true, $topicpreview);
		$this->assign_topicpreview_template_vars($topicpreview);

		$collapsible = !empty($this->collapsible_operator);
		$hidden = $collapsible ? (bool) $this->collapsible_operator->is_collapsed($collapse_id) : false;
		$collapse_url = $collapsible ? $this->collapsible_operator->get_collapsible_link($collapse_id) : '';

		$this->template->assign_vars([
			$template_flag => true,
			'TOPTOPICS_BLOCK_TITLE' => $title,
			'S_TOPTOPICS_INCLUDE_FORUM_NAME' => $include_forum_name,
			'S_TOPTOPICS_COLLAPSIBLE' => $collapsible,
			'S_TOPTOPICS_HIDDEN' => $hidden,
			'U_TOPTOPICS_COLLAPSE_URL' => $collapse_url,
			'TOPTOPICS_BLOCK_ID' => $collapse_id,
		]);

		foreach ($topics as $topic)
		{
			$topic_row = [
				'U_TOPIC' => append_sid($this->root_path . 'viewtopic.' . $this->php_ext, 'f=' . (int) $topic['forum_id'] . '&t=' . (int) $topic['topic_id']),
				'U_FORUM' => append_sid($this->root_path . 'viewforum.' . $this->php_ext, 'f=' . (int) $topic['forum_id']),
				'U_NEWEST_POST' => $topic['u_newest_post'],
				'TOPIC_TITLE' => censor_text($topic['topic_title']),
				'TOPIC_IMG_STYLE' => $topic['topic_img_style'],
				'TOPIC_FOLDER_IMG_ALT' => $topic['topic_folder_img_alt'],
				'FORUM_NAME' => $topic['forum_name'],
				'USERNAME_FULL' => get_username_string('full', (int) $topic['topic_poster'], $topic['topic_first_poster_name'], $topic['topic_first_poster_colour']),
					'POST_TIME' => $this->user->format_date((int) $topic['topic_time']),
					'U_LAST_POST' => $this->build_last_post_url($topic),
					'LAST_POST_AUTHOR_FULL' => $this->get_last_post_author_full($topic),
					'LAST_POST_TIME' => $this->get_last_post_time_text($topic),
					'LAST_POST_TIME_RFC3339' => $this->get_last_post_time_rfc3339($topic),
					'REPLIES' => (int) $topic['replies'],
					'VIEWS' => (int) $topic['views'],
					'LIKES' => (int) $topic['like_count'],
				'DISLIKES' => (int) $topic['dislike_count'],
				'FLAGS' => (int) $topic['flag_count'],
				'S_UNREAD_TOPIC' => !empty($topic['unread_topic']),
			];

			$topic_id = (int) ($topic['topic_id'] ?? 0);
			if ($topic_id > 0 && $this->is_topic_preview_enabled())
			{
				$topic_row['TOPIC_PREVIEW_TOPIC_ID'] = $topic_id;
			}

			$this->template->assign_block_vars('top_topics', $topic_row);
		}
	}

	protected function exclude_foe_authored_topics(array $topics): array
	{
		if (empty($topics))
		{
			return [];
		}

		$foe_user_id_map = $this->get_current_user_foe_id_map();
		if (empty($foe_user_id_map))
		{
			return array_values($topics);
		}

		$filtered_topics = [];
		foreach ($topics as $topic)
		{
			$topic_poster = (int) ($topic['topic_poster'] ?? 0);
			$topic_last_poster = (int) ($topic['topic_last_poster_id'] ?? 0);
			if (($topic_poster > 0 && isset($foe_user_id_map[$topic_poster]))
				|| ($topic_last_poster > 0 && isset($foe_user_id_map[$topic_last_poster])))
			{
				continue;
			}

			$filtered_topics[] = $topic;
		}

		return $filtered_topics;
	}

	protected function modify_topic_list(array $topics, string $context): array
	{
		if (empty($topics))
		{
			return [];
		}

		$vars = ['topics', 'context'];
		extract($this->dispatcher->trigger_event('freemitbbs.toptopics.modify_topic_list', compact($vars)));

		return is_array($topics) ? array_values($topics) : [];
	}

	protected function get_current_user_foe_id_map(): array
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

	protected function build_non_foe_topic_sql(string $topic_alias = 't'): string
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

	protected function assign_topicpreview_template_vars(array $topicpreview): void
	{
		$this->template->assign_vars([
			'S_TOPTOPICS_TOPICPREVIEW' => $topicpreview['enabled'],
			'TOPTOPICS_PREVIEW_THEME' => $topicpreview['theme'],
			'TOPTOPICS_PREVIEW_DELAY' => $topicpreview['delay'],
			'TOPTOPICS_PREVIEW_DRIFT' => $topicpreview['drift'],
			'TOPTOPICS_PREVIEW_WIDTH' => $topicpreview['width'],
		]);
	}

	protected function add_topic_tracking(array $topics): array
	{
		if (empty($topics))
		{
			return $topics;
		}

		if (!function_exists('get_complete_topic_tracking'))
		{
			include_once($this->root_path . 'includes/functions_display.' . $this->php_ext);
		}

		$topic_ids_by_forum = [];
		foreach ($topics as $topic)
		{
			$topic_ids_by_forum[(int) $topic['forum_id']][] = (int) $topic['topic_id'];
		}

		$topic_tracking_info = [];
		if ($this->config['load_anon_lastread'] || $this->user->data['is_registered'])
		{
			foreach ($topic_ids_by_forum as $forum_id => $topic_ids)
			{
				$topic_tracking_info[$forum_id] = get_complete_topic_tracking($forum_id, array_values(array_unique($topic_ids)));
			}
		}

		foreach ($topics as &$topic)
		{
			$forum_id = (int) $topic['forum_id'];
			$topic_id = (int) $topic['topic_id'];
			$topic_last_post_time = (int) ($topic['topic_last_post_time'] ?? $topic['topic_time']);

			$topic['unread_topic'] = isset($topic_tracking_info[$forum_id][$topic_id])
				&& $topic_last_post_time > (int) $topic_tracking_info[$forum_id][$topic_id];
			$topic['u_newest_post'] = append_sid(
				$this->root_path . 'viewtopic.' . $this->php_ext,
				'f=' . $forum_id . '&t=' . $topic_id
			) . '&amp;view=unread#unread';
		}
		unset($topic);

		return $topics;
	}

	protected function add_topic_display_state(array $topics): array
	{
		if (empty($topics))
		{
			return $topics;
		}

		if (!function_exists('topic_status'))
		{
			include_once($this->root_path . 'includes/functions_display.' . $this->php_ext);
		}

		foreach ($topics as &$topic)
		{
			$folder_img = '';
			$folder_alt = '';
			$topic_type = '';
			$topic_status_row = [
				'topic_status' => (int) ($topic['topic_status'] ?? ITEM_UNLOCKED),
				'topic_type' => (int) ($topic['topic_type'] ?? POST_NORMAL),
				'topic_posted' => false,
				'poll_start' => (int) ($topic['poll_start'] ?? 0),
			];

			topic_status($topic_status_row, (int) ($topic['replies'] ?? 0), !empty($topic['unread_topic']), $folder_img, $folder_alt, $topic_type);

			$topic['topic_img_style'] = $folder_img;
			$topic['topic_folder_img_alt'] = $this->language->lang($folder_alt);
		}
		unset($topic);

		return $topics;
	}

	protected function add_topic_previews(array $topics, array $topicpreview): array
	{
		if (empty($topics)
			|| empty($this->topicpreview_data)
			|| empty($this->topicpreview_renderer)
			|| !$topicpreview['enabled']
			|| !method_exists($this->topicpreview_data, 'modify_sql')
			|| !method_exists($this->topicpreview_renderer, 'render_text'))
		{
			return $topics;
		}

		$topic_ids = array_values(array_unique(array_map(static function ($topic) {
			return (int) $topic['topic_id'];
		}, $topics)));

		if (empty($topic_ids))
		{
			return $topics;
		}

		$sql_array = [
			'SELECT' => 't.topic_id, t.forum_id, t.topic_first_post_id, t.topic_last_post_id, t.topic_attachment, t.topic_poster, t.topic_last_poster_id',
			'FROM' => [
				TOPICS_TABLE => 't',
			],
			'WHERE' => $this->db->sql_in_set('t.topic_id', $topic_ids),
		];
		$sql_array = $this->topicpreview_data->modify_sql($sql_array, 'SELECT');

		$sql = $this->db->sql_build_query('SELECT', $sql_array);
		$result = $this->db->sql_query($sql);

		$preview_rows = [];
		$preview_rowset = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$topic_id = (int) $row['topic_id'];
			$preview_rows[$topic_id] = $row;
			$preview_rowset[] = $row;
		}
		$this->db->sql_freeresult($result);

		if (empty($preview_rows))
		{
			return $topics;
		}

		$attachments = [];
		if ($topicpreview['attachments_enabled']
			&& method_exists($this->topicpreview_data, 'get_attachments_for_topics'))
		{
			$attachments = $this->topicpreview_data->get_attachments_for_topics($preview_rowset);
		}

		foreach ($topics as &$topic)
		{
			$topic_id = (int) $topic['topic_id'];
			if (isset($preview_rows[$topic_id]))
			{
				$topic['topic_preview'] = $this->build_topic_preview_vars($preview_rows[$topic_id], $attachments, $topicpreview);
			}
		}
		unset($topic);

		return $topics;
	}

	protected function build_topic_preview_vars(array $row, array $attachments, array $topicpreview): array
	{
		$first_post_id = (int) ($row['topic_first_post_id'] ?? 0);
		$last_post_id = (int) ($row['topic_last_post_id'] ?? 0);

		return [
			'TOPIC_PREVIEW_FIRST_POST' => $this->render_topic_preview_text(
				(string) ($row['first_post_text'] ?? ''),
				$first_post_id,
				(int) ($row['forum_id'] ?? 0),
				$attachments[$first_post_id] ?? [],
				$topicpreview
			),
			'TOPIC_PREVIEW_LAST_POST' => $last_post_id !== $first_post_id
				? $this->render_topic_preview_text(
					(string) ($row['last_post_text'] ?? ''),
					$last_post_id,
					(int) ($row['forum_id'] ?? 0),
					$attachments[$last_post_id] ?? [],
					$topicpreview
				)
				: '',
			'TOPIC_PREVIEW_FIRST_AVATAR' => $this->build_topic_preview_avatar($row, 'fp', $topicpreview),
			'TOPIC_PREVIEW_LAST_AVATAR' => $last_post_id !== $first_post_id
				? $this->build_topic_preview_avatar($row, 'lp', $topicpreview)
				: '',
		];
	}

	protected function render_topic_preview_text(string $text, int $post_id, int $forum_id, array $attachments, array $topicpreview): string
	{
		if (!$post_id || $text === '')
		{
			return '';
		}

		return censor_text($this->topicpreview_renderer->render_text(
			$text,
			$topicpreview['limit'],
			$topicpreview['strip_bbcodes'],
			$topicpreview['rich_text'],
			(bool) $topicpreview['theme'],
			$attachments,
			$forum_id
		));
	}

	protected function build_topic_preview_avatar(array $row, string $prefix, array $topicpreview): string
	{
		if (!$topicpreview['avatars_enabled'])
		{
			return '';
		}

		$avatar_data = [
			'user_avatar' => $row[$prefix . '_user_avatar'] ?? '',
			'user_avatar_type' => $row[$prefix . '_user_avatar_type'] ?? '',
			'user_avatar_width' => $row[$prefix . '_user_avatar_width'] ?? '',
			'user_avatar_height' => $row[$prefix . '_user_avatar_height'] ?? '',
			'username' => $row[$prefix . '_username'] ?? '',
			'user_id' => $row[$prefix . '_user_id'] ?? 0,
		];

		if (!empty($avatar_data['user_avatar']) && function_exists('phpbb_get_user_avatar'))
		{
			$avatar = phpbb_get_user_avatar($avatar_data, 'USER_AVATAR', false, true);
			if ($avatar)
			{
				return $avatar;
			}
		}

		return 'no-avatar';
	}

	protected function get_topicpreview_context(): array
	{
		$theme = 'light';
		$style_theme = $this->user->style['topic_preview_theme'] ?? '';
		if ($style_theme && file_exists($this->root_path . 'ext/vse/topicpreview/styles/all/theme/' . $style_theme . '.css'))
		{
			$theme = $style_theme;
		}

		$rich_text = !empty($this->config['topic_preview_rich_text']);
		$enabled = !empty($this->topicpreview_data)
			&& !empty($this->topicpreview_renderer)
			&& !empty($this->config['topic_preview_limit'])
			&& !empty($this->user->data['user_topic_preview']);

		return [
			'enabled' => $enabled,
			'theme' => $theme,
			'limit' => (int) ($this->config['topic_preview_limit'] ?? 0),
			'strip_bbcodes' => (string) ($this->config['topic_preview_strip_bbcodes'] ?? ''),
			'rich_text' => $rich_text,
			'attachments_enabled' => $rich_text
				&& !empty($this->config['topic_preview_rich_attachments'])
				&& !empty($this->config['allow_attachments']),
			'avatars_enabled' => !empty($this->config['topic_preview_avatars'])
				&& !empty($this->config['allow_avatar'])
				&& $this->user->optionget('viewavatars'),
			'delay' => max(300, (int) ($this->config['topic_preview_delay'] ?? 1000)),
			'drift' => (int) ($this->config['topic_preview_drift'] ?? 15),
			'width' => !empty($this->config['topic_preview_width']) ? (int) $this->config['topic_preview_width'] : 360,
		];
	}

	protected function increment_rank_cache_generation(): void
	{
		$this->invalidate_all_rank_cache();
	}

	protected function decorate_topics(array $topics, bool $with_previews, ?array $topicpreview = null): array
	{
		if (empty($topics))
		{
			return $topics;
		}

		$topics = $this->add_topic_tracking($topics);
		$topics = $this->add_topic_display_state($topics);

		return $topics;
	}

	protected function build_topic_preview_url(array $topic): string
	{
		$topic_id = (int) ($topic['topic_id'] ?? 0);
		if ($topic_id <= 0 || !$this->is_topic_preview_enabled())
		{
			return '';
		}

		return append_sid($this->root_path . 'app.php/topicpreview/' . $topic_id);
	}

	protected function is_topic_preview_enabled(): bool
	{
		$topicpreview = $this->get_topicpreview_context();
		return !empty($topicpreview['enabled']);
	}

	protected function invalidate_rank_cache_for_forums(array $forum_ids): void
	{
		$this->ranker->invalidate_forums($forum_ids);
	}

	protected function invalidate_all_rank_cache(): void
	{
		$this->ranker->invalidate_all();
	}
}
