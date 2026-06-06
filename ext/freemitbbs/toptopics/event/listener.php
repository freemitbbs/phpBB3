<?php

namespace freemitbbs\toptopics\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	private const USER_OPTION_HIDE_FORUM_SUMMARY = 20;
	private const USER_OPTION_HIDE_TOPIC_LIST_MEDIA_PREVIEWS = 21;
	private const USER_OPTION_HIDE_ENHANCED_TOPIC_LIST_VIEW = 22;
	private const DEFAULT_PER_FORUM_TOPIC_LIMIT = 3;
	private const BALANCED_TOPIC_FETCH_MULTIPLIER = 5;
	private const INDEX_CATEGORY_FORUM_CANDIDATE_MULTIPLIER = 2;
	private const DEFAULT_CANDIDATE_POOL_LIMIT = 2000;
	private const DEFAULT_POST_COLLAPSE_DISLIKE_THRESHOLD = 5;
	private const DUPLICATE_POST_WINDOW_SECONDS = 60;
	private const DUPLICATE_POST_LOCK_TIMEOUT_SECONDS = 0.5;
	private const INLINE_PREVIEW_MAX_IMAGES = 8;
	private const INLINE_PREVIEW_SERVER_RENDER_LIMIT = 5;
	private const REPUTATION_TIER_STEADY = 100;
	private const REPUTATION_TIER_TRUSTED = 500;
	private const REPUTATION_TIER_ELITE = 2000;
	private const REPUTATION_TIER_LEGEND = 5000;

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
	protected ?\freemitbbs\toptopics\controller\inline_preview $inline_preview;
	protected $topic_likes;
	protected $collapsible_operator;
	protected string $dislikes_table;
	protected string $likes_table;
	protected string $dislike_history_table;
	protected string $topic_overrides_table;
	protected string $user_reputation_table;
	protected string $root_path;
	protected string $php_ext;

	protected array $post_dislike_counts = [];
	protected array $post_net_dislike_scores = [];
	protected array $post_dislikers = [];
	protected array $user_disliked_posts = [];
	protected array $user_reputation_scores = [];
	protected array $local_viewtopic_link_titles = [
		'p' => [],
		't' => [],
	];
	protected array $inline_preview_visible_server_render_counts = [];
	protected ?int $current_user_reputation = null;
	protected array $index_category_blocks = [];
	protected ?array $index_summary_topics = null;
	protected ?array $index_summary_topic_ids = null;
	protected ?int $index_category_candidate_limit = null;
	protected ?array $index_excluded_forum_id_map = null;
	protected ?array $index_category_excluded_forum_id_map = null;
	protected ?array $user_home_excluded_forum_id_map = null;
	protected ?array $selectable_home_forum_id_map = null;
	protected ?array $index_recenttopics_topic_id_map = null;
	protected ?array $index_forum_viewership_order = null;
	protected ?array $foe_user_id_map = null;
	protected ?string $duplicate_post_lock_name = null;

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
		string $php_ext,
		?\freemitbbs\toptopics\controller\inline_preview $inline_preview = null,
		$topic_likes = null
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
		$this->inline_preview = $inline_preview;
		$this->topic_likes = $topic_likes;
		$this->collapsible_operator = $collapsible_operator;
		$this->dislikes_table = $dislikes_table;
		$this->likes_table = $this->derive_likes_table($dislikes_table);
		$this->dislike_history_table = $dislike_history_table;
		$this->topic_overrides_table = $topic_overrides_table;
		$this->user_reputation_table = $user_reputation_table;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	protected function derive_likes_table(string $dislikes_table): string
	{
		$dislikes_suffix = 'posts_dislikes';
		if (substr($dislikes_table, -strlen($dislikes_suffix)) === $dislikes_suffix)
		{
			return substr($dislikes_table, 0, -strlen($dislikes_suffix)) . 'posts_likes';
		}

		return preg_replace('/dislikes$/', 'likes', $dislikes_table) ?: $dislikes_table;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.user_setup' => 'load_language_on_setup',
			'core.permissions' => 'add_permissions',
			'vse.topicpreview.display_topic_preview' => 'add_full_post_image_candidates_to_topic_preview',
			'core.ucp_prefs_personal_data' => 'ucp_prefs_personal_data',
			'core.ucp_prefs_personal_update_data' => 'ucp_prefs_personal_update_data',
			'core.ucp_prefs_view_data' => 'ucp_prefs_view_data',
			'core.ucp_prefs_view_update_data' => 'ucp_prefs_view_update_data',
			'core.memberlist_view_profile' => 'user_profile_reaction_records',
			'core.viewforum_get_topic_ids_data' => 'viewforum_exclude_foe_topics',
			'core.viewforum_get_announcement_topic_ids_data' => 'viewforum_exclude_foe_topics',
			'imcger.recenttopicsng.sql_pull_topics_list' => 'recenttopics_exclude_first_post_disliked_topics',
			'imcger.recenttopicsng.sql_pull_topics_data' => 'recenttopics_exclude_first_post_disliked_topics',
			'imcger.recenttopicsng.modify_topics_list' => 'recenttopics_filter_first_post_disliked_topics',
			'imcger.recenttopicsng.modify_tpl_ary' => 'recenttopics_fade_first_post_disliked_topic',
			'core.viewforum_modify_topics_data' => 'viewforum_score_first_post_disliked_topics',
			'core.viewforum_modify_topicrow' => 'viewforum_fade_first_post_disliked_topic',
			'core.viewtopic_modify_post_data' => 'prefetch_dislikes',
			'core.viewtopic_modify_post_row' => 'modify_post_row',
			'core.viewtopic_modify_page_title' => 'viewtopic_admin_override',
			'core.display_forums_before' => 'index_category_blocks',
			'core.display_forums_modify_category_template_vars' => 'display_forums_modify_category_template_vars',
			'core.report_post_auth' => 'report_post_auth',
			'core.index_modify_page_title' => 'index_page_summary',
			'core.viewforum_modify_page_title' => 'forum_page_summary',
			'core.posting_modify_submit_post_before' => 'guard_duplicate_post_before',
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
		$classic_topic_list_view = $this->user_hides_enhanced_topic_list_view();
		$this->template->assign_vars([
			'S_TOPTOPICS_ENHANCED_TOPIC_LIST_VIEW' => !$classic_topic_list_view,
			'S_TOPTOPICS_CLASSIC_TOPIC_LIST_VIEW' => $classic_topic_list_view,
		]);
	}

	public function add_permissions($event)
	{
		$permissions = $event['permissions'];
		$permissions['u_toptopics_dislike'] = ['lang' => 'ACL_U_TOPTOPICS_DISLIKE', 'cat' => 'misc'];
		$event['permissions'] = $permissions;
	}

	public function add_full_post_image_candidates_to_topic_preview($event): void
	{
		$row = $event['row'];
		$block = $event['block'];
		$image_urls = $this->extract_inline_preview_image_urls((string) ($row['first_post_text'] ?? ''));

		if (empty($image_urls))
		{
			return;
		}

		$hidden_images = $this->build_hidden_inline_preview_images_html($image_urls);
		if ($hidden_images === '')
		{
			return;
		}

		$block['TOPIC_PREVIEW_FIRST_POST'] = (string) ($block['TOPIC_PREVIEW_FIRST_POST'] ?? '') . $hidden_images;
		$event['block'] = $block;
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
		$data['toptopics_show_topic_list_media_previews'] = !empty($event['submit'])
			? (bool) $this->request->variable('toptopics_show_topic_list_media_previews', true)
			: !$this->user_hides_topic_list_media_previews();
		$data['toptopics_show_enhanced_topic_list_view'] = !empty($event['submit'])
			? (bool) $this->request->variable('toptopics_show_enhanced_topic_list_view', true)
			: !$this->user_hides_enhanced_topic_list_view();
		$this->template->assign_vars([
			'S_TOPTOPICS_SHOW_TOPIC_LIST_MEDIA_PREVIEWS' => $data['toptopics_show_topic_list_media_previews'],
			'S_TOPTOPICS_TOPIC_LIST_MEDIA_PREVIEWS_DISABLED' => !$data['toptopics_show_topic_list_media_previews'],
			'S_TOPTOPICS_SHOW_ENHANCED_TOPIC_LIST_VIEW' => $data['toptopics_show_enhanced_topic_list_view'],
			'S_TOPTOPICS_ENHANCED_TOPIC_LIST_VIEW_DISABLED' => !$data['toptopics_show_enhanced_topic_list_view'],
		]);

		if ($this->has_user_home_forum_exclusion_column())
		{
			$selected_forum_ids = !empty($event['submit'])
				? $this->request->variable('user_home_topic_hide_forums', [0])
				: $this->get_user_home_excluded_forum_ids();
			$data['user_home_topic_hide_forums'] = $this->normalise_forum_ids($selected_forum_ids);
			$this->assign_home_topic_forum_exclusion_template_vars($data['user_home_topic_hide_forums']);
		}
		$event['data'] = $data;
	}

	public function ucp_prefs_view_update_data($event): void
	{
		$data = $event['data'];
		$sql_ary = $event['sql_ary'];
		$sql_ary['user_options'] = phpbb_optionset(
			self::USER_OPTION_HIDE_TOPIC_LIST_MEDIA_PREVIEWS,
			!(bool) ($data['toptopics_show_topic_list_media_previews'] ?? true),
			(int) $sql_ary['user_options']
		);
		$sql_ary['user_options'] = phpbb_optionset(
			self::USER_OPTION_HIDE_ENHANCED_TOPIC_LIST_VIEW,
			!(bool) ($data['toptopics_show_enhanced_topic_list_view'] ?? true),
			(int) $sql_ary['user_options']
		);

		if ($this->has_user_home_forum_exclusion_column())
		{
			$sql_ary['user_home_topic_hide_forums'] = $this->format_forum_id_csv(
				$this->filter_selectable_home_forum_ids($this->request->variable('user_home_topic_hide_forums', [0]))
			);
		}
		$event['sql_ary'] = $sql_ary;
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
		$this->prefetch_local_viewtopic_link_titles((array) ($event['rowset'] ?? []));

		$post_list = array_map('intval', (array) ($event['post_list'] ?? []));
		if (empty($post_list))
		{
			return;
		}

		foreach ($post_list as $post_id)
		{
			$this->post_dislike_counts[$post_id] = 0;
			$this->post_net_dislike_scores[$post_id] = 0;
			$this->post_dislikers[$post_id] = [];
		}

		$sql = 'SELECT pd.post_id, pd.user_id, u.username
			FROM ' . $this->dislikes_table . ' pd
			LEFT JOIN ' . USERS_TABLE . ' u
				ON u.user_id = pd.user_id
			WHERE ' . $this->db->sql_in_set('pd.post_id', $post_list) . '
			ORDER BY pd.disliketime ASC, pd.user_id ASC';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$post_id = (int) $row['post_id'];
			$user_id = (int) $row['user_id'];
			$username = (string) ($row['username'] ?? '');
			$this->post_dislike_counts[$post_id]++;
			$this->post_net_dislike_scores[$post_id]++;
			if ($username !== '')
			{
				$this->post_dislikers[$post_id][$user_id] = $username;
			}
		}
		$this->db->sql_freeresult($result);

		$sql = 'SELECT post_id, COUNT(user_id) AS like_count
			FROM ' . $this->likes_table . '
			WHERE ' . $this->db->sql_in_set('post_id', $post_list) . '
			GROUP BY post_id';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$post_id = (int) $row['post_id'];
			$this->post_net_dislike_scores[$post_id] = ($this->post_net_dislike_scores[$post_id] ?? 0) - (int) $row['like_count'];
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
		$net_dislike_score = $this->post_net_dislike_scores[$post_id] ?? 0;
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
		$post_row['POST_DISLIKERS'] = $this->get_post_dislikers_title($post_id, $dislike_count);
		$post_row['POST_DISLIKE_ACTION'] = $action;
		$post_row['POST_DISLIKE_URL'] = $this->build_dislike_url($post_id, $current_user_disliked);
		$post_row['POST_DISLIKE_DISABLED'] = $disabled;
		$post_row['POST_DISLIKE_FADE_CLASS'] = $this->get_post_dislike_fade_class($net_dislike_score);
		if ($this->should_collapse_post_for_dislikes($post_id, $net_dislike_score, $post_row))
		{
			$post_row['S_IGNORE_POST'] = true;
			$post_row['S_POST_HIDDEN'] = true;
			$post_row['L_IGNORE_POST'] = $this->language->lang(
				'TOPTOPICS_POST_COLLAPSED',
				$net_dislike_score,
				$this->get_post_collapse_dislike_threshold()
			);
		}
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
		if (!empty($post_row['MESSAGE']))
		{
			$message = (string) $post_row['MESSAGE'];
			$message = $this->rewrite_unparsed_media_embed_links($message);
			$post_row['MESSAGE'] = $this->rewrite_local_viewtopic_link_texts($message);
		}

		$event['post_row'] = $post_row;
	}

	protected function rewrite_unparsed_media_embed_links(string $message): string
	{
		if ($message === '' || stripos($message, '[media]') === false)
		{
			return $message;
		}

		$rewritten = preg_replace_callback(
			'#\[media\]\s*<a\b[^>]*\bhref=(["\'])(.*?)\1[^>]*>.*?</a>\s*\[/media\]#is',
			function ($match) {
				$params = $this->extract_youtube_embed_params((string) ($match[2] ?? ''));

				return $params === null ? (string) $match[0] : $this->build_youtube_embed_html($params);
			},
			$message
		);

		return $rewritten ?? $message;
	}

	protected function extract_youtube_embed_params(string $url): ?array
	{
		$url = trim(htmlspecialchars_decode($url, ENT_QUOTES | ENT_HTML5));
		if ($url === '')
		{
			return null;
		}

		$parts = parse_url($url);
		if ($parts === false || empty($parts['host']))
		{
			return null;
		}

		$host = strtolower((string) $parts['host']);
		$path = (string) ($parts['path'] ?? '');
		$query = [];
		parse_str((string) ($parts['query'] ?? ''), $query);
		$id = '';

		if ($host === 'youtu.be' || $host === 'www.youtu.be' || $host === 'm.youtu.be')
		{
			$id = strtok(ltrim($path, '/'), '/') ?: '';
		}
		else if (preg_match('#(?:^|\.)youtube\.com$#i', $host))
		{
			if ($path === '/watch')
			{
				$id = (string) ($query['v'] ?? '');
			}
			else if (preg_match('#^/(?:embed|shorts|live|v)/([-A-Za-z0-9_]+)#', $path, $id_match))
			{
				$id = (string) $id_match[1];
			}
		}

		if (!preg_match('/^[-A-Za-z0-9_]{6,}$/', $id))
		{
			return null;
		}

		$params = ['id' => $id];
		if (!empty($query['list']) && preg_match('/^[-A-Za-z0-9_]+$/', (string) $query['list']))
		{
			$params['list'] = (string) $query['list'];
		}
		if (!empty($query['t']) && preg_match('/^\d[\dhms]*$/', (string) $query['t']))
		{
			$params['t'] = (string) $query['t'];
		}

		return $params;
	}

	protected function build_youtube_embed_html(array $params): string
	{
		$id = $this->escape_attr((string) $params['id']);
		$src = 'https://www.youtube-nocookie.com/embed/' . rawurlencode((string) $params['id']);
		$separator = '?';
		if (!empty($params['list']))
		{
			$src .= $separator . 'list=' . rawurlencode((string) $params['list']);
			$separator = '&';
		}
		if (!empty($params['t']))
		{
			$src .= $separator . 'start=' . rawurlencode((string) $params['t']);
		}

		return '<span data-s9e-mediaembed="youtube" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe referrerpolicy="origin" allowfullscreen="" loading="lazy" scrolling="no" style="background:url(https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg) 50% 50% / cover;border:0;height:100%;left:0;position:absolute;width:100%" src="' . $this->escape_attr($src) . '"></iframe></span></span>';
	}

	protected function prefetch_local_viewtopic_link_titles(array $rowset): void
	{
		$this->local_viewtopic_link_titles = [
			'p' => [],
			't' => [],
		];

		if (empty($rowset))
		{
			return;
		}

		$post_ids = [];
		$topic_ids = [];
		$pattern = '#(?:(?:https?:)?//[^\s<>"\'\]]+|(?:\./|\.\./|/)?viewtopic\.' . preg_quote($this->php_ext, '#') . '\?[^\s<>"\'\]]+)#i';
		foreach ($rowset as $row)
		{
			$post_text = (string) ($row['post_text'] ?? '');
			if ($post_text === '' || stripos($post_text, 'viewtopic.') === false)
			{
				continue;
			}

			if (!preg_match_all($pattern, $post_text, $matches))
			{
				continue;
			}

			foreach ($matches[0] as $url)
			{
				$ref = $this->extract_local_viewtopic_link_ref((string) $url);
				if ($ref === null)
				{
					continue;
				}

				if (!empty($ref['post_id']))
				{
					$post_ids[(int) $ref['post_id']] = (int) $ref['post_id'];
				}
				else if (!empty($ref['topic_id']))
				{
					$topic_ids[(int) $ref['topic_id']] = (int) $ref['topic_id'];
				}
			}
		}

		$this->load_local_viewtopic_post_titles(array_values($post_ids));
		$this->load_local_viewtopic_topic_titles(array_values($topic_ids));
	}

	protected function load_local_viewtopic_post_titles(array $post_ids): void
	{
		$post_ids = array_values(array_unique(array_map('intval', $post_ids)));
		if (empty($post_ids))
		{
			return;
		}

		$sql = 'SELECT p.post_id, p.post_visibility, t.topic_id, t.forum_id, t.topic_title, t.topic_visibility
			FROM ' . POSTS_TABLE . ' p
			INNER JOIN ' . TOPICS_TABLE . ' t
				ON t.topic_id = p.topic_id
			WHERE ' . $this->db->sql_in_set('p.post_id', $post_ids);
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$post_id = (int) $row['post_id'];
			$topic_id = (int) $row['topic_id'];
			$forum_id = (int) $row['forum_id'];
			if ((int) $row['post_visibility'] !== ITEM_APPROVED
				|| (int) $row['topic_visibility'] !== ITEM_APPROVED
				|| !$this->auth->acl_get('f_read', $forum_id))
			{
				continue;
			}

			$title = $this->format_local_viewtopic_link_title((string) $row['topic_title']);
			if ($title !== '')
			{
				$this->local_viewtopic_link_titles['p'][$post_id] = $title;
				$this->local_viewtopic_link_titles['t'][$topic_id] = $title;
			}
		}
		$this->db->sql_freeresult($result);
	}

	protected function load_local_viewtopic_topic_titles(array $topic_ids): void
	{
		$topic_ids = array_values(array_unique(array_map('intval', $topic_ids)));
		$missing_topic_ids = [];
		foreach ($topic_ids as $topic_id)
		{
			if ($topic_id > 0 && empty($this->local_viewtopic_link_titles['t'][$topic_id]))
			{
				$missing_topic_ids[] = $topic_id;
			}
		}

		if (empty($missing_topic_ids))
		{
			return;
		}

		$sql = 'SELECT topic_id, forum_id, topic_title, topic_visibility
			FROM ' . TOPICS_TABLE . '
			WHERE ' . $this->db->sql_in_set('topic_id', $missing_topic_ids);
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$topic_id = (int) $row['topic_id'];
			$forum_id = (int) $row['forum_id'];
			if ((int) $row['topic_visibility'] !== ITEM_APPROVED
				|| !$this->auth->acl_get('f_read', $forum_id))
			{
				continue;
			}

			$title = $this->format_local_viewtopic_link_title((string) $row['topic_title']);
			if ($title !== '')
			{
				$this->local_viewtopic_link_titles['t'][$topic_id] = $title;
			}
		}
		$this->db->sql_freeresult($result);
	}

	protected function rewrite_local_viewtopic_link_texts(string $message): string
	{
		if ($message === ''
			|| stripos($message, 'viewtopic.') === false
			|| (empty($this->local_viewtopic_link_titles['p']) && empty($this->local_viewtopic_link_titles['t'])))
		{
			return $message;
		}

		$rewritten = preg_replace_callback('#<a\b(?=[^>]*\bhref=)([^>]*)>(.*?)</a>#is', function ($matches)
		{
			$attributes = (string) $matches[1];
			$inner_html = (string) $matches[2];
			if (!preg_match('#\bhref\s*=\s*(["\'])(.*?)\1#is', $attributes, $href_match))
			{
				return $matches[0];
			}

			$href_ref = $this->extract_local_viewtopic_link_ref((string) $href_match[2]);
			if ($href_ref === null)
			{
				return $matches[0];
			}

			$title = $this->get_local_viewtopic_link_title($href_ref);
			if ($title === '' || !$this->is_bare_local_viewtopic_link_text($inner_html, $href_ref))
			{
				return $matches[0];
			}

			return '<a' . $attributes . '>' . $title . '</a>';
		}, $message);

		return $rewritten === null ? $message : $rewritten;
	}

	protected function is_bare_local_viewtopic_link_text(string $inner_html, array $href_ref): bool
	{
		if (preg_match('#<(?:img|svg|i)\b#i', $inner_html))
		{
			return false;
		}

		$visible_text = trim($this->decode_display_text(strip_tags($inner_html)));
		if ($visible_text === '')
		{
			return false;
		}

		$text_ref = $this->extract_local_viewtopic_link_ref($visible_text);
		return $text_ref !== null && $this->same_local_viewtopic_link_ref($href_ref, $text_ref);
	}

	protected function same_local_viewtopic_link_ref(array $left, array $right): bool
	{
		$left_post_id = (int) ($left['post_id'] ?? 0);
		$right_post_id = (int) ($right['post_id'] ?? 0);
		if ($left_post_id > 0 || $right_post_id > 0)
		{
			return $left_post_id > 0 && $left_post_id === $right_post_id;
		}

		$left_topic_id = (int) ($left['topic_id'] ?? 0);
		$right_topic_id = (int) ($right['topic_id'] ?? 0);
		return $left_topic_id > 0 && $left_topic_id === $right_topic_id;
	}

	protected function get_local_viewtopic_link_title(array $ref): string
	{
		$post_id = (int) ($ref['post_id'] ?? 0);
		if ($post_id > 0)
		{
			return $this->local_viewtopic_link_titles['p'][$post_id] ?? '';
		}

		$topic_id = (int) ($ref['topic_id'] ?? 0);
		return $topic_id > 0 ? ($this->local_viewtopic_link_titles['t'][$topic_id] ?? '') : '';
	}

	protected function format_local_viewtopic_link_title(string $title): string
	{
		$title = function_exists('censor_text') ? censor_text($title) : $title;

		return $this->escape_display_text($title);
	}

	protected function extract_local_viewtopic_link_ref(string $url): ?array
	{
		$url = trim($this->decode_display_text($url));
		$url = trim($url, "\"' \t\n\r\0\x0B");
		if ($url === '' || stripos($url, 'viewtopic.') === false)
		{
			return null;
		}

		$parts = parse_url($url);
		if ($parts === false || empty($parts['path']))
		{
			return null;
		}

		$path = str_replace('\\', '/', (string) $parts['path']);
		$expected_script = 'viewtopic.' . strtolower($this->php_ext);
		if (strtolower(basename($path)) !== $expected_script)
		{
			return null;
		}

		$host = (string) ($parts['host'] ?? '');
		if ($host !== '' && !$this->is_local_board_host($host))
		{
			return null;
		}

		$params = [];
		parse_str((string) ($parts['query'] ?? ''), $params);
		$post_id = (int) ($params['p'] ?? 0);
		if ($post_id > 0)
		{
			return ['post_id' => $post_id];
		}

		$topic_id = (int) ($params['t'] ?? 0);
		return $topic_id > 0 ? ['topic_id' => $topic_id] : null;
	}

	protected function is_local_board_host(string $host): bool
	{
		$host = $this->normalize_board_host($host);
		if ($host === '')
		{
			return true;
		}

		$local_hosts = [
			$this->request->server('HTTP_HOST', ''),
			$this->request->server('SERVER_NAME', ''),
			(string) ($this->config['server_name'] ?? ''),
			'freemitbbs.com',
			'www.freemitbbs.com',
			'themitbbs.com',
			'www.themitbbs.com',
		];
		foreach ($local_hosts as $local_host)
		{
			if ($host === $this->normalize_board_host((string) $local_host))
			{
				return true;
			}
		}

		return false;
	}

	protected function normalize_board_host(string $host): string
	{
		$host = strtolower(trim($host));
		if ($host === '')
		{
			return '';
		}

		if ($host[0] === '[')
		{
			$end = strpos($host, ']');
			return $end === false ? $host : substr($host, 1, $end - 1);
		}

		$host = preg_replace('#:\d+$#', '', $host) ?? $host;
		return strpos($host, 'www.') === 0 ? substr($host, 4) : $host;
	}

	protected function get_post_dislikers_title(int $post_id, int $dislike_count): string
	{
		$dislikers = $this->post_dislikers[$post_id] ?? [];
		if (!empty($dislikers))
		{
			return $this->escape_attr($this->language->lang('TOPTOPICS_DISLIKED_BY') . implode(', ', $dislikers));
		}

		return $this->escape_attr($this->language->lang('TOPTOPICS_DISLIKES_COUNT', $dislike_count));
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
		$balanced_scope_map = [];
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
				$scope_id = 'category_' . (int) $category_id;
				if ($this->should_limit_topics_per_forum($topic_forum_ids))
				{
					$balanced_scope_map[$scope_id] = [
						'forum_ids' => $topic_forum_ids,
						'limit' => $category_candidate_limit,
						'per_forum_candidate_limit' => $this->get_index_category_per_forum_candidate_limit($category_candidate_limit),
						'per_forum_result_limit' => $category_candidate_limit,
					];
				}
				else
				{
					$scope_map[$scope_id] = [
						'forum_ids' => $topic_forum_ids,
						'limit' => $this->get_balanced_topic_fetch_limit($category_candidate_limit, $topic_forum_ids),
					];
				}
			}
		}

		$scope_topics = !empty($scope_map)
			? $this->ranker->get_topics_for_scopes($scope_map)
			: [];
		if (!empty($balanced_scope_map))
		{
			$scope_topics += $this->ranker->get_topics_for_balanced_forum_scopes($balanced_scope_map);
		}
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
			$category_scope_topics = !empty($category_topic_forum_ids[$category_id])
				? $this->get_index_category_scope_topics($scope_topics, (int) $category_id, $category_topic_forum_ids[$category_id])
				: [];
			$topics = !empty($category_topic_forum_ids[$category_id])
				? $this->filter_index_category_topics($category_scope_topics, $category_topic_forum_ids[$category_id])
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

		$topicpreview = $this->get_topicpreview_context();
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

	public function recenttopics_exclude_first_post_disliked_topics($event): void
	{
		$sql_array = $event['sql_array'] ?? null;
		if (!is_array($sql_array) || empty($sql_array['WHERE']))
		{
			return;
		}

		$exclusion_sql = $this->build_first_post_disliked_topic_exclusion_sql('t');
		if ($exclusion_sql === '')
		{
			return;
		}

		$sql_array['WHERE'] .= $exclusion_sql;
		$event['sql_array'] = $sql_array;
	}

	public function recenttopics_filter_first_post_disliked_topics($event): void
	{
		$topic_list = $event['topic_list'] ?? [];
		$rowset = $event['rowset'] ?? [];
		if (!is_array($topic_list) || !is_array($rowset))
		{
			return;
		}

		$topic_ids = [];
		foreach ($topic_list as $topic_id)
		{
			$topic_id = (int) $topic_id;
			if ($topic_id > 0)
			{
				$topic_ids[$topic_id] = true;
			}
		}

		foreach ($rowset as $row)
		{
			$topic_id = is_array($row) ? (int) ($row['topic_id'] ?? 0) : 0;
			if ($topic_id > 0)
			{
				$topic_ids[$topic_id] = true;
			}
		}

		$first_post_net_dislike_scores = $this->get_first_post_net_dislike_score_map(array_keys($topic_ids));
		$excluded_topic_ids = $this->filter_first_post_net_dislike_scores_at_threshold($first_post_net_dislike_scores);
		if (empty($excluded_topic_ids))
		{
			foreach ($rowset as &$row)
			{
				if (is_array($row))
				{
					$topic_id = (int) ($row['topic_id'] ?? 0);
					$row['TOPTOPICS_FIRST_POST_NET_DISLIKE_SCORE'] = $first_post_net_dislike_scores[$topic_id] ?? 0;
				}
			}
			unset($row);

			$rowset = $this->add_topic_like_counts($rowset);
			$event['rowset'] = $this->add_inline_topic_previews($rowset, $topic_list, false);
			return;
		}

		$filtered_topic_list = array_values(array_filter($topic_list, static function ($topic_id) use ($excluded_topic_ids) {
			return !isset($excluded_topic_ids[(int) $topic_id]);
		}));
		$event['topic_list'] = $filtered_topic_list;

		$filtered_rowset = [];
		foreach ($rowset as $row)
		{
			$topic_id = is_array($row) ? (int) ($row['topic_id'] ?? 0) : 0;
			if ($topic_id > 0 && isset($excluded_topic_ids[$topic_id]))
			{
				continue;
			}

			if (is_array($row))
			{
				$row['TOPTOPICS_FIRST_POST_NET_DISLIKE_SCORE'] = $first_post_net_dislike_scores[$topic_id] ?? 0;
			}
			$filtered_rowset[] = $row;
		}
		$filtered_rowset = $this->add_topic_like_counts($filtered_rowset);
		$event['rowset'] = $this->add_inline_topic_previews($filtered_rowset, $filtered_topic_list, false);
	}

	public function recenttopics_fade_first_post_disliked_topic($event): void
	{
		$row = $event['row'] ?? [];
		$tpl_ary = $event['tpl_ary'] ?? [];
		if (!is_array($row) || !is_array($tpl_ary))
		{
			return;
		}

		$tpl_ary['TOPTOPICS_TOPIC_DISLIKE_FADE_CLASS'] = $this->get_topic_dislike_fade_class(
			(int) ($row['TOPTOPICS_FIRST_POST_NET_DISLIKE_SCORE'] ?? 0)
		);
		$tpl_ary['TOPIC_LIKE_COUNT'] = $this->get_topic_like_count_from_row($row);
		$tpl_ary = $this->copy_inline_topic_preview_vars($tpl_ary, $row, true);
		$event['tpl_ary'] = $tpl_ary;
	}

	public function viewforum_score_first_post_disliked_topics($event): void
	{
		$rowset = $event['rowset'] ?? [];
		if (!is_array($rowset) || empty($rowset))
		{
			return;
		}

		$topic_ids = [];
		$topic_list = $event['topic_list'] ?? [];
		if (is_array($topic_list))
		{
			foreach ($topic_list as $topic_id)
			{
				$topic_id = (int) $topic_id;
				if ($topic_id > 0)
				{
					$topic_ids[$topic_id] = true;
				}
			}
		}

		foreach ($rowset as $row)
		{
			$topic_id = is_array($row) ? (int) ($row['topic_id'] ?? 0) : 0;
			if ($topic_id > 0)
			{
				$topic_ids[$topic_id] = true;
			}
		}

		$first_post_net_dislike_scores = $this->get_first_post_net_dislike_score_map(array_keys($topic_ids));

		foreach ($rowset as &$row)
		{
			if (is_array($row))
			{
				$topic_id = (int) ($row['topic_id'] ?? 0);
				$row['TOPTOPICS_FIRST_POST_NET_DISLIKE_SCORE'] = $first_post_net_dislike_scores[$topic_id] ?? 0;
			}
		}
		unset($row);

		$event['rowset'] = $this->add_inline_topic_previews($rowset, $topic_list, false);
	}

	public function viewforum_fade_first_post_disliked_topic($event): void
	{
		$row = $event['row'] ?? [];
		$topic_row = $event['topic_row'] ?? [];
		if (!is_array($row) || !is_array($topic_row))
		{
			return;
		}

		$topic_row['TOPTOPICS_TOPIC_DISLIKE_FADE_CLASS'] = $this->get_topic_dislike_fade_class(
			(int) ($row['TOPTOPICS_FIRST_POST_NET_DISLIKE_SCORE'] ?? 0)
		);
		$topic_row = $this->copy_inline_topic_preview_vars($topic_row, $row, true);
		$event['topic_row'] = $topic_row;
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
		$this->release_duplicate_post_lock();

		$data = $event['data'] ?? [];
		$post_id = (int) ($data['post_id'] ?? 0);
		if ($post_id > 0)
		{
			try
			{
				$this->reputation->queue_post_quality_sync($post_id);
			}
			catch (\Throwable $e)
			{
				error_log('TopTopics post quality queue failed after submit: post_id=' . $post_id . ' error=' . $e->getMessage());
			}
		}
	}

	public function guard_duplicate_post_before($event): void
	{
		$mode = (string) ($event['mode'] ?? '');
		if (!in_array($mode, ['post', 'reply', 'quote'], true))
		{
			return;
		}

		$data = $event['data'] ?? [];
		$message_md5 = (string) ($data['message_md5'] ?? '');
		$forum_id = (int) ($data['forum_id'] ?? 0);
		if ($message_md5 === '' || $forum_id <= 0)
		{
			return;
		}

		$topic_id = (int) ($data['topic_id'] ?? 0);
		if ($mode !== 'post' && $topic_id <= 0)
		{
			return;
		}

		$post_data = $event['post_data'] ?? [];
		$subject = (string) ($post_data['post_subject'] ?? $data['topic_title'] ?? '');
		$post_author_name = (string) ($event['post_author_name'] ?? '');
		$duplicate_since = time() - self::DUPLICATE_POST_WINDOW_SECONDS;

		$this->acquire_duplicate_post_lock($this->duplicate_post_fingerprint($mode, $forum_id, $topic_id, $message_md5, $subject, $post_author_name));

		$duplicate = $this->find_recent_duplicate_post($mode, $forum_id, $topic_id, $message_md5, $subject, $post_author_name, $duplicate_since);
		if (empty($duplicate))
		{
			return;
		}

		$this->release_duplicate_post_lock();
		redirect($this->duplicate_post_url((int) $duplicate['post_id'], (int) $duplicate['topic_id']));
	}

	protected function find_recent_duplicate_post(string $mode, int $forum_id, int $topic_id, string $message_md5, string $subject, string $post_author_name, int $duplicate_since): array
	{
		$poster_id = (int) $this->user->data['user_id'];
		$where = [
			'p.poster_id = ' . $poster_id,
			'p.forum_id = ' . $forum_id,
			"p.post_checksum = '" . $this->db->sql_escape($message_md5) . "'",
			'p.post_time >= ' . $duplicate_since,
			'p.post_visibility <> ' . ITEM_DELETED,
		];

		if ($poster_id === ANONYMOUS)
		{
			$where[] = "p.poster_ip = '" . $this->db->sql_escape((string) $this->user->ip) . "'";
			if ($post_author_name !== '')
			{
				$where[] = "p.post_username = '" . $this->db->sql_escape($post_author_name) . "'";
			}
		}

		if ($mode === 'post')
		{
			$from_sql = POSTS_TABLE . ' p
				INNER JOIN ' . TOPICS_TABLE . ' t
					ON t.topic_id = p.topic_id
						AND t.topic_first_post_id = p.post_id
						AND t.topic_moved_id = 0';
			$where[] = "p.post_subject = '" . $this->db->sql_escape($subject) . "'";
		}
		else
		{
			$from_sql = POSTS_TABLE . ' p';
			$where[] = 'p.topic_id = ' . $topic_id;
		}

		$sql = 'SELECT p.post_id, p.topic_id
			FROM ' . $from_sql . '
			WHERE ' . implode('
				AND ', $where) . '
			ORDER BY p.post_time DESC, p.post_id DESC';
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return is_array($row) ? $row : [];
	}

	protected function acquire_duplicate_post_lock(string $fingerprint): void
	{
		if (!$this->supports_duplicate_post_lock())
		{
			return;
		}

		$lock_name = 'freemitbbs_post_' . substr(hash('sha256', $fingerprint), 0, 48);
		$sql = "SELECT GET_LOCK('" . $this->db->sql_escape($lock_name) . "', " . self::DUPLICATE_POST_LOCK_TIMEOUT_SECONDS . ') AS lock_acquired';
		$result = $this->db->sql_query($sql);
		$acquired = (int) $this->db->sql_fetchfield('lock_acquired');
		$this->db->sql_freeresult($result);

		if ($acquired === 1)
		{
			$this->duplicate_post_lock_name = $lock_name;
		}
	}

	protected function release_duplicate_post_lock(): void
	{
		if ($this->duplicate_post_lock_name === null || !$this->supports_duplicate_post_lock())
		{
			$this->duplicate_post_lock_name = null;
			return;
		}

		$lock_name = $this->duplicate_post_lock_name;
		$this->duplicate_post_lock_name = null;

		$sql = "SELECT RELEASE_LOCK('" . $this->db->sql_escape($lock_name) . "')";
		$result = $this->db->sql_query($sql);
		$this->db->sql_freeresult($result);
	}

	protected function supports_duplicate_post_lock(): bool
	{
		$sql_layer = $this->db->get_sql_layer();

		return $sql_layer === 'mysqli' || strpos($sql_layer, 'mysql') === 0;
	}

	protected function duplicate_post_fingerprint(string $mode, int $forum_id, int $topic_id, string $message_md5, string $subject, string $post_author_name): string
	{
		$actor = (int) $this->user->data['user_id'];
		if ($actor === ANONYMOUS)
		{
			$actor = 'guest:' . (string) $this->user->ip . ':' . $post_author_name;
		}

		return implode('|', [
			$mode,
			$forum_id,
			$topic_id,
			$message_md5,
			$subject,
			$actor,
		]);
	}

	protected function duplicate_post_url(int $post_id, int $topic_id): string
	{
		if ($post_id > 0)
		{
			return append_sid($this->root_path . 'viewtopic.' . $this->php_ext, 'p=' . $post_id) . '#p' . $post_id;
		}

		if ($topic_id > 0)
		{
			return append_sid($this->root_path . 'viewtopic.' . $this->php_ext, 't=' . $topic_id);
		}

		return append_sid($this->root_path . 'index.' . $this->php_ext);
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

	protected function should_collapse_post_for_dislikes(int $post_id, int $net_dislike_score, array $post_row): bool
	{
		$threshold = $this->get_post_collapse_dislike_threshold();
		if ($threshold <= 0 || $net_dislike_score < $threshold)
		{
			return false;
		}

		if (!empty($post_row['S_POST_DELETED']) || !empty($post_row['S_IGNORE_POST']))
		{
			return false;
		}

		return !$this->is_explicitly_showing_post($post_id);
	}

	protected function get_post_collapse_dislike_threshold(): int
	{
		return max(0, (int) ($this->config['toptopics_post_collapse_dislike_threshold'] ?? self::DEFAULT_POST_COLLAPSE_DISLIKE_THRESHOLD));
	}

	protected function get_post_dislike_fade_class(int $net_dislike_score): string
	{
		$level = $this->get_post_dislike_fade_level($net_dislike_score);
		return $level > 0 ? 'toptopics-dislike-fade toptopics-dislike-fade-level-' . $level : '';
	}

	protected function get_topic_dislike_fade_class(int $net_dislike_score): string
	{
		$level = $this->get_post_dislike_fade_level($net_dislike_score);
		return $level > 0 ? 'toptopics-topic-dislike-fade toptopics-dislike-fade-level-' . $level : '';
	}

	protected function get_post_dislike_fade_level(int $net_dislike_score): int
	{
		$threshold = $this->get_post_collapse_dislike_threshold();
		if ($threshold <= 0 || $net_dislike_score <= 1)
		{
			return 0;
		}

		if ($threshold <= 1 || $net_dislike_score >= $threshold)
		{
			return 4;
		}

		$visible_range = max(1, $threshold - 1);
		return max(1, min(4, (int) ceil((($net_dislike_score - 1) / $visible_range) * 4)));
	}

	protected function is_explicitly_showing_post(int $post_id): bool
	{
		return $this->request->variable('view', '') === 'show'
			&& $this->request->variable('p', 0) === $post_id;
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

	protected function user_hides_topic_list_media_previews(): bool
	{
		return phpbb_optionget(self::USER_OPTION_HIDE_TOPIC_LIST_MEDIA_PREVIEWS, (int) $this->user->data['user_options']);
	}

	protected function user_hides_enhanced_topic_list_view(): bool
	{
		return phpbb_optionget(self::USER_OPTION_HIDE_ENHANCED_TOPIC_LIST_VIEW, (int) $this->user->data['user_options']);
	}

	protected function has_user_home_forum_exclusion_column(): bool
	{
		return version_compare((string) ($this->config['toptopics_version'] ?? '0.0.0'), '1.1.21', '>=');
	}

	protected function get_user_home_excluded_forum_ids(): array
	{
		$forum_ids = array_keys($this->get_user_home_excluded_forum_id_map());
		sort($forum_ids);

		return $forum_ids;
	}

	protected function get_user_home_excluded_forum_id_map(): array
	{
		if ($this->user_home_excluded_forum_id_map !== null)
		{
			return $this->user_home_excluded_forum_id_map;
		}

		$configured_ids = (string) ($this->user->data['user_home_topic_hide_forums'] ?? '');
		if ($this->has_user_home_forum_exclusion_column())
		{
			$user_id = $this->get_current_user_id();
			if ($user_id > 0)
			{
				$sql = 'SELECT user_home_topic_hide_forums
					FROM ' . USERS_TABLE . '
					WHERE user_id = ' . $user_id;
				$result = $this->db->sql_query($sql);
				$configured_ids = (string) $this->db->sql_fetchfield('user_home_topic_hide_forums');
				$this->db->sql_freeresult($result);
			}
		}

		$this->user_home_excluded_forum_id_map = $this->parse_forum_id_map($configured_ids);
		return $this->user_home_excluded_forum_id_map;
	}

	protected function get_current_user_id(): int
	{
		$user_id = (int) ($this->user->data['user_id'] ?? 0);
		if ($user_id > 0)
		{
			return $user_id;
		}

		return (int) $this->request->variable('u', ANONYMOUS);
	}

	protected function format_forum_id_csv(array $forum_ids): string
	{
		return implode(',', $this->normalise_forum_ids($forum_ids));
	}

	protected function filter_selectable_home_forum_ids(array $forum_ids): array
	{
		$selectable_forums = $this->get_selectable_home_forum_id_map();
		if (empty($selectable_forums))
		{
			return [];
		}

		$filtered_forum_ids = [];
		foreach ($this->normalise_forum_ids($forum_ids) as $forum_id)
		{
			if (isset($selectable_forums[$forum_id]))
			{
				$filtered_forum_ids[] = $forum_id;
			}
		}

		return $filtered_forum_ids;
	}

	protected function get_selectable_home_forum_id_map(): array
	{
		if ($this->selectable_home_forum_id_map !== null)
		{
			return $this->selectable_home_forum_id_map;
		}

		$this->selectable_home_forum_id_map = [];
		$forum_list_ary = $this->auth->acl_getf('f_list');
		foreach ($this->auth->acl_getf('f_read') as $forum_id => $allowed)
		{
			if (!empty($allowed['f_read']) && !empty($forum_list_ary[$forum_id]['f_list']))
			{
				$this->selectable_home_forum_id_map[(int) $forum_id] = true;
			}
		}

		return $this->selectable_home_forum_id_map;
	}

	protected function assign_home_topic_forum_exclusion_template_vars(array $selected_forum_ids): void
	{
		$selectable_forums = $this->get_selectable_home_forum_id_map();
		if (empty($selectable_forums))
		{
			return;
		}

		$sql = 'SELECT forum_id, forum_name
			FROM ' . FORUMS_TABLE . '
			WHERE forum_type = ' . FORUM_POST . '
				AND ' . $this->db->sql_in_set('forum_id', array_keys($selectable_forums)) . '
			ORDER BY left_id';
		$result = $this->db->sql_query($sql);

		$selected_forum_map = array_fill_keys($this->normalise_forum_ids($selected_forum_ids), true);
		$has_forum_options = false;
		$forum_checkbox_html = '';
		while ($row = $this->db->sql_fetchrow($result))
		{
			$forum_id = (int) ($row['forum_id'] ?? 0);
			if ($forum_id <= 0)
			{
				continue;
			}

			$has_forum_options = true;
			$forum_checkbox_html .= '<label><input type="checkbox" name="user_home_topic_hide_forums[]" value="' . $forum_id . '"'
				. (isset($selected_forum_map[$forum_id]) ? ' checked="checked"' : '')
				. ' /> ' . htmlspecialchars((string) ($row['forum_name'] ?? ''), ENT_COMPAT, 'UTF-8') . '</label><br />';
		}
		$this->db->sql_freeresult($result);

		$this->template->assign_vars([
			'S_TOPTOPICS_HOME_FORUM_EXCLUSIONS' => $has_forum_options,
			'S_TOPTOPICS_HOME_FORUM_EXCLUSION_OPTIONS' => $forum_checkbox_html,
		]);
	}

	protected function get_index_summary_topic_id_map(): array
	{
		if ($this->index_summary_topic_ids === null)
		{
			$this->get_index_summary_topics();
		}

		return $this->index_summary_topic_ids ?? [];
	}

	public function get_index_summary_topic_ids_for_dedupe(): array
	{
		$topic_ids = array_keys($this->get_index_summary_topic_id_map());
		$topic_ids = array_values(array_unique(array_filter(array_map('intval', $topic_ids), static function ($topic_id) {
			return $topic_id > 0;
		})));
		sort($topic_ids);

		return $topic_ids;
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

	protected function get_index_category_scope_topics(array $scope_topics, int $category_id, array $forum_ids): array
	{
		$forum_ids = $this->normalise_forum_ids($forum_ids);
		if (empty($forum_ids))
		{
			return [];
		}

		return $scope_topics['category_' . $category_id] ?? [];
	}

	protected function get_index_category_candidate_topics(array $forum_ids): array
	{
		$forum_ids = $this->normalise_forum_ids($forum_ids);
		if (empty($forum_ids))
		{
			return [];
		}

		$candidate_limit = $this->get_index_category_candidate_limit();
		if (!$this->should_limit_topics_per_forum($forum_ids))
		{
			return $this->ranker->get_topics($forum_ids, $this->get_balanced_topic_fetch_limit($candidate_limit, $forum_ids));
		}

		$scope_id = '__index_category_fallback';
		$topics_by_scope = $this->ranker->get_topics_for_balanced_forum_scopes([
			$scope_id => [
				'forum_ids' => $forum_ids,
				'limit' => $candidate_limit,
				'per_forum_candidate_limit' => $this->get_index_category_per_forum_candidate_limit($candidate_limit),
				'per_forum_result_limit' => $candidate_limit,
			],
		]);

		return $topics_by_scope[$scope_id] ?? [];
	}

	protected function get_index_category_per_forum_candidate_limit(int $candidate_limit): int
	{
		$candidate_limit = max(1, $candidate_limit);
		$configured_candidate_limit = isset($this->config['toptopics_candidate_pool_limit'])
			? (int) $this->config['toptopics_candidate_pool_limit']
			: self::DEFAULT_CANDIDATE_POOL_LIMIT;
		$configured_candidate_limit = max(50, min(20000, $configured_candidate_limit));

		return min(
			$configured_candidate_limit,
			max(
				$candidate_limit,
				$candidate_limit * self::INDEX_CATEGORY_FORUM_CANDIDATE_MULTIPLIER,
				$this->get_per_forum_topic_limit() * self::BALANCED_TOPIC_FETCH_MULTIPLIER
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
		return $this->exclude_forum_ids_by_map(
			$forum_ids,
			$this->get_index_excluded_forum_id_map() + $this->get_user_home_excluded_forum_id_map()
		);
	}

	protected function exclude_index_category_forum_ids(array $forum_ids): array
	{
		return $this->exclude_forum_ids_by_map(
			$forum_ids,
			$this->get_index_category_excluded_forum_id_map() + $this->get_user_home_excluded_forum_id_map()
		);
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
			|| (!method_exists($recenttopicsng_functions, 'get_displayed_index_topic_ids_for_dedupe')
				&& !method_exists($recenttopicsng_functions, 'get_index_topic_ids_for_dedupe')))
		{
			return $this->index_recenttopics_topic_id_map;
		}

		$topic_ids = [];
		foreach (['rtng_topics', 'rtng_junban_topics'] as $tpl_loopname)
		{
			try
			{
				if (method_exists($recenttopicsng_functions, 'get_displayed_index_topic_ids_for_dedupe')
					&& (!method_exists($recenttopicsng_functions, 'has_displayed_index_topic_ids_for_dedupe')
						|| $recenttopicsng_functions->has_displayed_index_topic_ids_for_dedupe($tpl_loopname)))
				{
					$loop_topic_ids = $recenttopicsng_functions->get_displayed_index_topic_ids_for_dedupe($tpl_loopname);
				}
				else
				{
					$loop_topic_ids = $recenttopicsng_functions->get_index_topic_ids_for_dedupe($tpl_loopname);
				}

				if (is_array($loop_topic_ids))
				{
					$topic_ids = array_merge($topic_ids, $loop_topic_ids);
				}
			}
			catch (\Throwable $exception)
			{
				continue;
			}
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
			$cache = method_exists($phpbb_container, 'has') && $phpbb_container->has('cache')
				? $phpbb_container->get('cache')
				: null;
			$viewership = new $class($this->auth, $phpbb_container->get('content.visibility'), $this->db, $cache, $this->config);
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
			? $this->filter_index_category_topics($this->get_index_category_candidate_topics($topic_forum_ids), $topic_forum_ids)
			: [];
		$topicpreview = $this->get_topicpreview_context();
		$topics = $this->decorate_topics($topics, $topicpreview['enabled'], $topicpreview);

		return [
			'forum_menu_html' => $this->build_category_forum_menu_html($forums, $category_id),
			'rows_html' => $this->build_category_rows_html($topics),
		];
	}

	protected function build_category_rows_html(array $topics): string
	{
		$topics = $this->exclude_topics_present_in_index_summary($topics);
		$enhanced_topic_list_view = !$this->user_hides_enhanced_topic_list_view();

		if (empty($topics))
		{
			return '<li class="row bg1 toptopics-category-empty' . ($enhanced_topic_list_view ? ' toptopics-enhanced-topic-list-row' : '') . '">'
				. '<dl class="row-item">'
				. '<dt><div class="list-inner">' . $this->escape_text($this->language->lang('TOPTOPICS_CATEGORY_EMPTY')) . '</div></dt>'
				. (!$enhanced_topic_list_view ? '<dd class="posts">&nbsp;</dd><dd class="views">&nbsp;</dd>' : '')
				. '<dd class="lastpost">&nbsp;</dd>'
				. '</dl>'
				. '</li>';
		}

		$html = '';
		foreach ($topics as $index => $topic)
		{
			$row_class = ($index % 2 === 0) ? 'bg1' : 'bg2';
			$topic_fade_class = $this->escape_attr($this->get_topic_dislike_fade_class((int) ($topic['first_post_net_dislike_score'] ?? 0)));
			$topic_url = append_sid($this->root_path . 'viewtopic.' . $this->php_ext, 'f=' . (int) $topic['forum_id'] . '&t=' . (int) $topic['topic_id']);
			$topic_title = $this->escape_display_text(censor_text((string) $topic['topic_title']));

			$unread_icon = '';
			if (!empty($topic['unread_topic']) && !$this->user->data['is_bot'])
			{
				$unread_icon = '<a class="unread" href="' . $topic['u_newest_post'] . '">'
					. '<i class="icon fa-file fa-fw icon-red icon-md" aria-hidden="true"></i>'
					. '<span class="sr-only">' . $this->escape_text($this->language->lang('NEW_POST')) . '</span>'
					. '</a>';
			}

			$html .= '<li class="row ' . $row_class . ' toptopics-category-row' . ($enhanced_topic_list_view ? ' toptopics-enhanced-topic-list-row' : '') . '">'
				. '<dl class="row-item ' . $this->escape_attr((string) ($topic['topic_img_style'] ?? '')) . '">'
				. '<dt title="' . $this->escape_attr((string) ($topic['topic_folder_img_alt'] ?? '')) . '">'
				. ((!empty($topic['unread_topic']) && !$this->user->data['is_bot']) ? '<a href="' . $topic['u_newest_post'] . '" class="row-item-link"></a>' : '')
				. '<div class="list-inner">'
				. $unread_icon
				. '<a href="' . $topic_url . '" class="topictitle' . ($topic_fade_class !== '' ? ' ' . $topic_fade_class : '') . '">' . $topic_title . '</a>'
				. $this->build_inline_topic_preview_html($topic, $topic_url, $topic_fade_class)
				. (!$enhanced_topic_list_view ? '<br>' : '')
				. $this->build_mobile_topic_author_html($topic)
				. $this->build_mobile_lastpost_html($topic)
				. $this->build_mobile_stats_html($topic)
				. (!$enhanced_topic_list_view ? $this->build_classic_topic_author_html($topic) : '')
				. '</div>'
				. '</dt>'
				. ($enhanced_topic_list_view
					? $this->build_lastpost_column_html($topic)
					: $this->build_topic_stats_columns_html($topic) . $this->build_classic_lastpost_column_html($topic, true))
				. '</dl>'
				. '</li>';
		}

		return $html;
	}

	protected function refresh_reputation_for_post_ids(array $post_ids): void
	{
		$this->reputation->refresh_post_contexts($post_ids);
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

		if ($score < self::REPUTATION_TIER_STEADY)
		{
			return ['class' => 'fresh', 'lang' => 'TOPTOPICS_REPUTATION_TIER_NEUTRAL'];
		}

		if ($score < self::REPUTATION_TIER_TRUSTED)
		{
			return ['class' => 'steady', 'lang' => 'TOPTOPICS_REPUTATION_TIER_POSITIVE'];
		}

		if ($score < self::REPUTATION_TIER_ELITE)
		{
			return ['class' => 'regular', 'lang' => 'TOPTOPICS_REPUTATION_TIER_TRUSTED'];
		}

		if ($score < self::REPUTATION_TIER_LEGEND)
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

		if ($score < self::REPUTATION_TIER_STEADY)
		{
			return max(8, (int) round(($score / self::REPUTATION_TIER_STEADY) * 100));
		}

		if ($score < self::REPUTATION_TIER_TRUSTED)
		{
			return (int) round((($score - self::REPUTATION_TIER_STEADY) / (self::REPUTATION_TIER_TRUSTED - self::REPUTATION_TIER_STEADY)) * 100);
		}

		if ($score < self::REPUTATION_TIER_ELITE)
		{
			return (int) round((($score - self::REPUTATION_TIER_TRUSTED) / (self::REPUTATION_TIER_ELITE - self::REPUTATION_TIER_TRUSTED)) * 100);
		}

		if ($score < self::REPUTATION_TIER_LEGEND)
		{
			return (int) round((($score - self::REPUTATION_TIER_ELITE) / (self::REPUTATION_TIER_LEGEND - self::REPUTATION_TIER_ELITE)) * 100);
		}

		return 100;
	}

	protected function escape_text(string $text): string
	{
		return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	protected function escape_display_text(string $text): string
	{
		return $this->escape_text($this->decode_display_text($text));
	}

	protected function decode_display_text(string $text): string
	{
		return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}

	protected function escape_attr(string $text): string
	{
		return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	protected function extract_inline_preview_image_urls(string $post_text): array
	{
		if ($post_text === '')
		{
			return [];
		}

		$post_text = $this->remove_inline_preview_quoted_content($post_text);
		$image_urls = [];
		$seen = [];

		if (preg_match_all('#<IMG\b[^>]*\bsrc=(["\'])(.*?)\1#is', $post_text, $matches, PREG_SET_ORDER))
		{
			foreach ($matches as $match)
			{
				$this->add_inline_preview_image_url($image_urls, $seen, $match[2]);
				if (count($image_urls) >= self::INLINE_PREVIEW_MAX_IMAGES)
				{
					return $image_urls;
				}
			}
		}

		if (preg_match_all('#\[img(?:=[^\]]*)?\](.*?)\[/img\]#is', $post_text, $matches, PREG_SET_ORDER))
		{
			foreach ($matches as $match)
			{
				$this->add_inline_preview_image_url($image_urls, $seen, $match[1]);
				if (count($image_urls) >= self::INLINE_PREVIEW_MAX_IMAGES)
				{
					break;
				}
			}
		}

		return $image_urls;
	}

	protected function remove_inline_preview_quoted_content(string $post_text): string
	{
		foreach ([
			'#<QUOTE\b[^>]*>.*?</QUOTE>#si',
			'#\[quote(?:=[^\]]*)?\].*?\[/quote\]#si',
		] as $pattern)
		{
			do
			{
				$previous = $post_text;
				$post_text = preg_replace($pattern, '', $post_text);
				if ($post_text === null)
				{
					return $previous;
				}
			}
			while ($post_text !== $previous);
		}

		return $post_text;
	}

	protected function add_inline_preview_image_url(array &$image_urls, array &$seen, string $url): void
	{
		$url = $this->normalize_inline_preview_image_url($url);
		if ($url === ''
			|| isset($seen[$url])
			|| !$this->is_allowed_inline_preview_image_url($url)
			|| $this->is_ignored_inline_preview_image_url($url))
		{
			return;
		}

		$seen[$url] = true;
		$image_urls[] = $url;
	}

	protected function normalize_inline_preview_image_url(string $url): string
	{
		$url = trim(htmlspecialchars_decode(strip_tags($url), ENT_QUOTES | ENT_HTML5));
		$url = trim($url, "\"' \t\n\r\0\x0B");

		if ($url === '' || preg_match('#\s#', $url))
		{
			return '';
		}

		return $url;
	}

	protected function is_allowed_inline_preview_image_url(string $url): bool
	{
		return (bool) preg_match('#^(?:https?:)?//#i', $url)
			|| strpos($url, '/') === 0
			|| strpos($url, './') === 0
			|| strpos($url, '../') === 0;
	}

	protected function is_ignored_inline_preview_image_url(string $url): bool
	{
		return (bool) preg_match('#(?:^https?://fonts\.gstatic\.com/s/e/notoemoji/|/(?:images/)?smilies/)#i', $url);
	}

	protected function build_hidden_inline_preview_images_html(array $image_urls): string
	{
		if (empty($image_urls))
		{
			return '';
		}

		$html = '<span class="toptopics-preview-image-candidates" hidden="hidden" aria-hidden="true">';
		foreach ($image_urls as $url)
		{
			$html .= '<img data-src="' . $this->escape_attr($url) . '" alt="" hidden="hidden" />';
		}
		$html .= '</span>';

		return $html;
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

		return get_topic_list_username_string('full', $last_poster_id, $last_poster_name, $last_poster_colour);
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
		$like_count = $this->get_topic_like_count_from_row($topic);

		$html = '<div class="responsive-show toptopics-mobile-stats" style="display: none;">';
		foreach ($stats as $label => $value)
		{
			$html .= '<span class="toptopics-mobile-stat">'
				. $this->escape_text($label)
				. $this->escape_text($this->language->lang('COLON'))
				. ' <strong>' . $value . '</strong></span>';
		}
		if ($like_count > 0)
		{
			$html .= $this->build_topic_like_stat_html($like_count, 'toptopics-mobile-stat');
		}
		$html .= '</div>';

		return $html;
	}

	protected function build_lastpost_stats_html(array $topic): string
	{
		$stats = [
			$this->language->lang('REPLIES') => (int) ($topic['replies'] ?? 0),
			$this->language->lang('VIEWS') => (int) ($topic['views'] ?? 0),
		];
		$like_count = $this->get_topic_like_count_from_row($topic);

		$html = '<span class="toptopics-lastpost-stats">';
		foreach ($stats as $label => $value)
		{
			$html .= '<span>'
				. $this->escape_text($label)
				. $this->escape_text($this->language->lang('COLON'))
				. ' <strong>' . $value . '</strong></span>';
		}
		if ($like_count > 0)
		{
			$html .= $this->build_topic_like_stat_html($like_count);
		}
		$html .= '</span>';

		return $html;
	}

	protected function build_topic_like_stat_html(int $like_count, string $extra_class = ''): string
	{
		if ($like_count <= 0)
		{
			return '';
		}

		return '<span class="toptopics-topic-like-stat' . ($extra_class !== '' ? ' ' . $this->escape_attr($extra_class) : '') . '" title="'
			. $this->escape_attr($this->language->lang('LIKED_BY'))
			. '"><i class="liked" aria-hidden="true"></i><strong>' . $like_count . '</strong></span>';
	}

	protected function build_topic_stats_columns_html(array $topic): string
	{
		return '<dd class="posts">' . (int) ($topic['replies'] ?? 0) . ' <dfn>'
			. $this->escape_text($this->language->lang('REPLIES'))
			. '</dfn></dd><dd class="views">' . (int) ($topic['views'] ?? 0) . ' <dfn>'
			. $this->escape_text($this->language->lang('VIEWS'))
			. '</dfn></dd>';
	}

	protected function build_lastpost_forum_html(array $topic): string
	{
		$forum_id = (int) ($topic['forum_id'] ?? 0);
		$forum_name = (string) ($topic['forum_name'] ?? '');
		if ($forum_id <= 0 || $forum_name === '')
		{
			return '';
		}

		$forum_url = append_sid($this->root_path . 'viewforum.' . $this->php_ext, 'f=' . $forum_id);

		return '<span class="toptopics-lastpost-forum">'
			. '<span class="toptopics-info-label">' . $this->escape_text($this->language->lang('TOPTOPICS_FORUM_LABEL')) . '</span>'
			. '<span class="toptopics-info-content"><a href="' . $forum_url . '">' . $this->escape_text($forum_name) . '</a></span>'
			. '</span>';
	}

	protected function build_mobile_topic_author_html(array $topic): string
	{
		return '<div class="responsive-show toptopics-mobile-meta" style="display: none;">'
			. $this->build_topic_author_line_html($topic, true)
			. '</div>';
	}

	protected function build_mobile_lastpost_html(array $topic): string
	{
		return '<div class="responsive-show toptopics-mobile-lastpost" style="display: none;">'
			. $this->escape_text($this->language->lang('TOPTOPICS_LATEST')) . ' '
			. $this->escape_text($this->language->lang('POST_BY_AUTHOR')) . ' '
			. $this->get_last_post_author_full($topic)
			. ' &laquo; <a href="' . $this->build_last_post_url($topic) . '" title="'
			. $this->escape_attr($this->language->lang('GOTO_LAST_POST')) . '">'
			. $this->escape_text($this->get_last_post_time_text($topic))
			. '</a></div>';
	}

	protected function build_topic_author_line_html(array $topic, bool $include_forum_name = false): string
	{
		return $this->escape_text($this->language->lang('TOPTOPICS_TOPIC_LABEL')) . ' '
			. $this->build_topic_author_detail_html($topic, $include_forum_name);
	}

	protected function build_topic_author_detail_html(array $topic, bool $include_forum_name = false): string
	{
		$html = $this->escape_text($this->language->lang('POST_BY_AUTHOR')) . ' '
			. get_topic_list_username_string(
				'full',
				(int) ($topic['topic_poster'] ?? 0),
				(string) ($topic['topic_first_poster_name'] ?? ''),
				(string) ($topic['topic_first_poster_colour'] ?? '')
			)
			. ' &raquo; ' . $this->escape_text($this->user->format_date((int) ($topic['topic_time'] ?? 0)));

		$forum_id = (int) ($topic['forum_id'] ?? 0);
		$forum_name = (string) ($topic['forum_name'] ?? '');
		if ($include_forum_name && $forum_id > 0 && $forum_name !== '')
		{
			$forum_url = append_sid($this->root_path . 'viewforum.' . $this->php_ext, 'f=' . $forum_id);
			$html .= ' &raquo; <a href="' . $forum_url . '" class="toptopics-category-badge">' . $this->escape_text($forum_name) . '</a>';
		}

		return $html;
	}

	protected function build_classic_topic_author_html(array $topic): string
	{
		return '<div class="responsive-hide left-box">'
			. $this->build_topic_author_detail_html($topic, true)
			. '</div>';
	}

	protected function build_lastpost_column_html(array $topic): string
	{
		$topic_fade_class = $this->escape_attr($this->get_topic_dislike_fade_class((int) ($topic['first_post_net_dislike_score'] ?? 0)));
		$html = '<dd class="lastpost"><span>'
			. $this->build_lastpost_forum_html($topic)
			. '<span class="topic-poster toptopics-lastpost-topic-author' . ($topic_fade_class !== '' ? ' ' . $topic_fade_class : '') . '">'
			. '<span class="toptopics-info-label">' . $this->escape_text($this->language->lang('TOPTOPICS_TOPIC_LABEL')) . '</span>'
			. '<span class="toptopics-info-content">' . $this->build_topic_author_detail_html($topic, false) . '</span>'
			. '</span>'
			. '<span class="toptopics-lastpost-latest"><span class="toptopics-info-label"><dfn>'
			. $this->escape_text($this->language->lang('TOPTOPICS_LATEST'))
			. ' </dfn>'
			. $this->escape_text($this->language->lang('TOPTOPICS_LATEST'))
			. '</span><span class="toptopics-info-content">'
			. $this->escape_text($this->language->lang('POST_BY_AUTHOR'))
			. ' '
			. $this->get_last_post_author_full($topic);

		$last_post_url = $this->build_last_post_url($topic);
		$html .= ' &raquo; <time datetime="'
			. $this->escape_attr($this->get_last_post_time_rfc3339($topic))
			. '">'
			. $this->escape_text($this->get_last_post_time_text($topic))
			. '</time>';
		if (!$this->user->data['is_bot'] && $last_post_url !== '')
		{
			$html .= ' <a href="' . $last_post_url . '" title="'
				. $this->escape_attr($this->language->lang('GOTO_LAST_POST'))
				. '"><i class="icon fa-external-link-square fa-fw icon-lightgray icon-md" aria-hidden="true"></i>'
				. '<span class="sr-only">' . $this->escape_text($this->language->lang('VIEW_LATEST_POST')) . '</span></a>';
		}

		$html .= '</span></span>'
			. $this->build_lastpost_stats_html($topic)
			. '</span></dd>';

		return $html;
	}

	protected function build_classic_lastpost_column_html(array $topic, bool $include_forum_name = false): string
	{
		$last_post_url = $this->build_last_post_url($topic);
		$html = '<dd class="lastpost"><span><dfn>'
			. $this->escape_text($this->language->lang('LAST_POST'))
			. ' </dfn>'
			. $this->escape_text($this->language->lang('POST_BY_AUTHOR'))
			. ' '
			. $this->get_last_post_author_full($topic);

		if (!$this->user->data['is_bot'] && $last_post_url !== '')
		{
			$html .= ' <a href="' . $last_post_url . '" title="'
				. $this->escape_attr($this->language->lang('GOTO_LAST_POST'))
				. '"><i class="icon fa-external-link-square fa-fw icon-lightgray icon-md" aria-hidden="true"></i>'
				. '<span class="sr-only">' . $this->escape_text($this->language->lang('VIEW_LATEST_POST')) . '</span></a>';
		}

		$html .= '<br><time datetime="'
			. $this->escape_attr($this->get_last_post_time_rfc3339($topic))
			. '">'
			. $this->escape_text($this->get_last_post_time_text($topic))
			. '</time>';

		$forum_id = (int) ($topic['forum_id'] ?? 0);
		$forum_name = (string) ($topic['forum_name'] ?? '');
		if ($include_forum_name && $forum_id > 0 && $forum_name !== '')
		{
			$forum_url = append_sid($this->root_path . 'viewforum.' . $this->php_ext, 'f=' . $forum_id);
			$html .= '<br>' . $this->escape_text($this->language->lang('POSTED'))
				. ' ' . $this->escape_text($this->language->lang('IN'))
				. ' <a href="' . $forum_url . '">' . $this->escape_text($forum_name) . '</a>';
		}

		return $html . '</span></dd>';
	}

	protected function build_inline_topic_preview_html(array $topic, string $topic_url, string $topic_fade_class = ''): string
	{
		if ($this->user_hides_enhanced_topic_list_view())
		{
			return '';
		}

		if (!empty($topic['S_TOPTOPICS_INLINE_SERVER_PREVIEW']) && !empty($topic['TOPTOPICS_INLINE_PREVIEW_HTML']))
		{
			return (string) $topic['TOPTOPICS_INLINE_PREVIEW_HTML'];
		}

		if (empty($topic['S_TOPTOPICS_INLINE_LAZY_PREVIEW'])
			|| empty($topic['U_TOPTOPICS_INLINE_PREVIEW']))
		{
			return '';
		}

		return '<div class="toptopics-inline-preview toptopics-inline-preview-lazy'
			. ($topic_fade_class !== '' ? ' ' . $topic_fade_class : '')
				. '" data-toptopics-inline-preview-url="' . $this->escape_attr((string) $topic['U_TOPTOPICS_INLINE_PREVIEW']) . '"'
				. ' data-toptopics-inline-preview-batch-url="' . $this->escape_attr((string) ($topic['U_TOPTOPICS_INLINE_PREVIEW_BATCH'] ?? '')) . '"'
				. ' data-toptopics-inline-preview-topic-id="' . (int) ($topic['TOPTOPICS_INLINE_PREVIEW_TOPIC_ID'] ?? 0) . '"'
				. ' data-toptopics-topic-url="' . $this->escape_attr($this->decode_html_url($topic_url)) . '"'
				. ' data-toptopics-inline-media-preview="' . (!empty($topic['S_TOPTOPICS_INLINE_MEDIA_PREVIEW']) ? '1' : '0') . '"'
				. ' aria-busy="true"></div>';
	}

	protected function build_inline_preview_topic_url(array $topic): string
	{
		return $this->decode_html_url(append_sid(
			$this->root_path . 'viewtopic.' . $this->php_ext,
			'f=' . (int) ($topic['forum_id'] ?? 0) . '&t=' . (int) ($topic['topic_id'] ?? 0)
		));
	}

	protected function decode_html_url(string $url): string
	{
		return html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}

	protected function build_server_inline_topic_preview_html(array $preview, string $topic_url, string $topic_fade_class, bool $media_enabled): string
	{
		if ((int) ($preview['status'] ?? 0) !== 200)
		{
			return '';
		}

		$plain_text = $this->normalize_server_inline_preview_plain_text((string) ($preview['plain_text'] ?? ''));
		$image_urls = $this->get_server_inline_preview_image_urls($preview);
		if ($media_enabled && !empty($image_urls))
		{
			return $this->build_server_inline_mixed_preview_html(
				$plain_text,
				$this->build_server_inline_image_preview_html($image_urls, $topic_url, $topic_fade_class),
				$topic_fade_class
			);
		}

		$rendered_html = trim((string) ($preview['rendered_html'] ?? ''));
		if ($media_enabled && $rendered_html !== '')
		{
			return '<div class="' . $this->build_server_inline_preview_class('toptopics-inline-preview toptopics-inline-preview-text toptopics-inline-preview-rich', $topic_fade_class) . '">'
				. $rendered_html
				. '</div>';
		}

		if ($media_enabled && !empty($preview['media_url']))
		{
			$media_html = $this->build_server_inline_media_preview_html($preview, $topic_url, $topic_fade_class);
			if ($media_html !== '')
			{
				return $this->build_server_inline_mixed_preview_html($plain_text, $media_html, $topic_fade_class);
			}
		}

		return $this->build_server_inline_text_preview_html($plain_text, $topic_fade_class);
	}

	protected function get_server_inline_preview_image_urls(array $preview): array
	{
		$image_urls = $preview['image_urls'] ?? [];
		if (empty($image_urls) && !empty($preview['media_urls']))
		{
			$image_urls = $preview['media_urls'];
		}

		if (!is_array($image_urls))
		{
			return [];
		}

		$urls = [];
		$seen = [];
		foreach ($image_urls as $url)
		{
			$url = (string) $url;
			if ($url === ''
				|| isset($seen[$url])
				|| !$this->is_allowed_inline_preview_image_url($url)
				|| $this->is_ignored_inline_preview_image_url($url))
			{
				continue;
			}

			$seen[$url] = true;
			$urls[] = $url;
			if (count($urls) >= self::INLINE_PREVIEW_MAX_IMAGES)
			{
				break;
			}
		}

		return $urls;
	}

	protected function normalize_server_inline_preview_plain_text(string $plain_text): string
	{
		$plain_text = trim($plain_text);
		if ($plain_text === '')
		{
			return '';
		}

		$normalized = preg_replace('/\s+/u', "\xE3\x80\x80", $plain_text);
		if ($normalized !== null)
		{
			return trim($normalized, "\xE3\x80\x80 \t\n\r\0\x0B");
		}

		$normalized = preg_replace('/\s+/', ' ', $plain_text);
		return trim((string) $normalized);
	}

	protected function build_server_inline_preview_class(string $base_class, string $topic_fade_class): string
	{
		return $this->escape_attr($base_class . ($topic_fade_class !== '' ? ' ' . $topic_fade_class : ''));
	}

	protected function build_server_inline_text_preview_html(string $plain_text, string $topic_fade_class): string
	{
		if ($plain_text === '')
		{
			return '';
		}

		return '<div class="' . $this->build_server_inline_preview_class('toptopics-inline-preview toptopics-inline-preview-text', $topic_fade_class) . '">'
			. $this->escape_text($plain_text)
			. '</div>';
	}

	protected function build_server_inline_mixed_preview_html(string $plain_text, string $media_html, string $topic_fade_class): string
	{
		if ($media_html === '')
		{
			return $this->build_server_inline_text_preview_html($plain_text, $topic_fade_class);
		}

		if ($plain_text === '')
		{
			return $media_html;
		}

		return '<div class="' . $this->build_server_inline_preview_class('toptopics-inline-preview toptopics-inline-preview-mixed', $topic_fade_class) . '">'
			. '<div class="toptopics-inline-preview-text toptopics-inline-preview-mixed-text">' . $this->escape_text($plain_text) . '</div>'
			. '<div class="toptopics-inline-preview-mixed-media">' . $media_html . '</div>'
			. '</div>';
	}

	protected function build_server_inline_image_preview_html(array $image_urls, string $topic_url, string $topic_fade_class): string
	{
		if (empty($image_urls))
		{
			return '';
		}

		$image_count = count($image_urls);
		$html = '<div class="' . $this->build_server_inline_preview_class(
			'toptopics-inline-preview toptopics-inline-preview-image toptopics-inline-preview-carousel '
				. ($image_count === 1 ? 'toptopics-inline-preview-single-image' : 'toptopics-inline-preview-multi-image'),
			$topic_fade_class
		) . '" data-toptopics-carousel-index="0">';
		$html .= '<div class="toptopics-inline-preview-carousel-track">';
		foreach ($image_urls as $index => $url)
		{
			$html .= '<a class="toptopics-inline-preview-carousel-slide' . ($index === 0 ? ' toptopics-inline-preview-carousel-slide-active' : '') . '"'
				. ' href="' . $this->escape_attr($topic_url) . '"'
				. ' aria-hidden="' . ($index === 0 ? 'false' : 'true') . '">';
			$html .= '<img '
				. ($index === 0 ? 'src="' : 'data-toptopics-src="') . $this->escape_attr($url) . '"'
				. ' alt="" loading="lazy">';
			$html .= '</a>';
		}
		$html .= '</div>';

		if ($image_count > 1)
		{
			$html .= '<div class="toptopics-inline-preview-carousel-controls">'
				. '<button type="button" class="toptopics-inline-preview-carousel-button toptopics-inline-preview-carousel-button-prev" data-toptopics-carousel-step="-1" title="Previous image" aria-label="Previous image">&#8249;</button>'
				. '<span class="toptopics-inline-preview-carousel-count">1 / ' . $image_count . '</span>'
				. '<button type="button" class="toptopics-inline-preview-carousel-button toptopics-inline-preview-carousel-button-next" data-toptopics-carousel-step="1" title="Next image" aria-label="Next image">&#8250;</button>'
				. '</div>';
		}

		return $html . '</div>';
	}

	protected function build_server_inline_media_preview_html(array $preview, string $topic_url, string $topic_fade_class): string
	{
		$media_type = (string) ($preview['media_type'] ?? '');
		$media_url = (string) ($preview['media_url'] ?? '');
		$media_id = (string) ($preview['media_id'] ?? '');
		if ($media_url === '')
		{
			return '';
		}

		if ($media_type === 'video')
		{
			return $this->build_server_inline_structured_media_preview_html(
				'<video src="' . $this->escape_attr($media_url) . '" preload="metadata" controls playsinline="playsinline" height="220"></video>',
				'',
				$topic_fade_class
			);
		}

		if ($media_type === 'youtube' && preg_match('/^[A-Za-z0-9_-]{11}$/', $media_id))
		{
			$thumb_url = 'https://i.ytimg.com/vi/' . rawurlencode($media_id) . '/mqdefault.jpg';
			return $this->build_server_inline_structured_media_preview_html(
				'<a class="toptopics-inline-preview-youtube-thumb" href="' . $this->escape_attr($topic_url) . '" aria-label="YouTube video">'
					. '<img src="' . $this->escape_attr($thumb_url) . '" alt="" loading="lazy">'
					. '<span class="toptopics-inline-preview-youtube-play" aria-hidden="true"></span>'
					. '</a>',
				' toptopics-inline-preview-media-frame-youtube',
				$topic_fade_class
			);
		}

		if ($media_type === 'bilibili' && preg_match('/^BV[0-9A-Za-z]+$/', $media_id))
		{
			return $this->build_server_inline_structured_media_preview_html(
				'<iframe src="https://player.bilibili.com/player.html?bvid=' . rawurlencode($media_id) . '&amp;autoplay=0" loading="lazy" frameborder="0" scrolling="no" allowfullscreen="allowfullscreen" title="Bilibili video" width="640" height="360" data-s9e-mediaembed="bilibili"></iframe>',
				' toptopics-inline-preview-media-frame-youtube',
				$topic_fade_class
			);
		}

		if ($media_type === 'tiktok' && preg_match('/^\d{6,}$/', $media_id))
		{
			return $this->build_server_inline_structured_media_preview_html(
				'<iframe src="https://www.tiktok.com/embed/' . rawurlencode($media_id) . '" loading="lazy" frameborder="0" scrolling="no" allowfullscreen="allowfullscreen" title="TikTok video" width="340" height="700" data-s9e-mediaembed="tiktok"></iframe>',
				' toptopics-inline-preview-media-frame-tiktok',
				$topic_fade_class
			);
		}

		if ($media_type === 'tweet')
		{
			$tweet_id = $media_id !== '' ? $media_id : $this->extract_tweet_id_from_url($media_url);
			if ($tweet_id !== '')
			{
				return $this->build_server_inline_structured_media_preview_html(
					'<iframe src="https://platform.twitter.com/embed/Tweet.html?id=' . rawurlencode($tweet_id) . '&amp;conversation=none&amp;cards=hidden" loading="lazy" frameborder="0" scrolling="no" title="Tweet" width="550" height="350" data-s9e-mediaembed="twitter"></iframe>',
					'',
					$topic_fade_class
				);
			}
		}

		return '';
	}

	protected function build_server_inline_structured_media_preview_html(string $media_html, string $frame_extra_class, string $topic_fade_class): string
	{
		return '<div class="' . $this->build_server_inline_preview_class('toptopics-inline-preview toptopics-inline-preview-media-box', $topic_fade_class) . '">'
			. '<div class="' . $this->escape_attr('toptopics-inline-preview-media-frame' . $frame_extra_class) . '">'
			. $media_html
			. '</div>'
			. '</div>';
	}

	protected function extract_tweet_id_from_url(string $url): string
	{
		if (preg_match('#/status(?:es)?/(\d+)#i', $url, $match))
		{
			return (string) $match[1];
		}

		return '';
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
				'TOPIC_TITLE' => $this->escape_display_text(censor_text($topic['topic_title'])),
				'TOPIC_IMG_STYLE' => $topic['topic_img_style'],
				'TOPIC_FOLDER_IMG_ALT' => $topic['topic_folder_img_alt'],
				'FORUM_NAME' => $topic['forum_name'],
				'USERNAME_FULL' => get_topic_list_username_string('full', (int) $topic['topic_poster'], $topic['topic_first_poster_name'], $topic['topic_first_poster_colour']),
				'POST_TIME' => $this->user->format_date((int) $topic['topic_time']),
				'U_LAST_POST' => $this->build_last_post_url($topic),
				'LAST_POST_AUTHOR_FULL' => $this->get_last_post_author_full($topic),
				'LAST_POST_TIME' => $this->get_last_post_time_text($topic),
				'LAST_POST_TIME_RFC3339' => $this->get_last_post_time_rfc3339($topic),
				'REPLIES' => (int) $topic['replies'],
				'VIEWS' => (int) $topic['views'],
				'TOPIC_LIKE_COUNT' => $this->get_topic_like_count_from_row($topic),
				'LIKES' => (int) $topic['like_count'],
				'DISLIKES' => (int) $topic['dislike_count'],
				'FLAGS' => (int) $topic['flag_count'],
				'TOPIC_DISLIKE_FADE_CLASS' => $this->get_topic_dislike_fade_class((int) ($topic['first_post_net_dislike_score'] ?? 0)),
				'S_UNREAD_TOPIC' => !empty($topic['unread_topic']),
			];
			$topic_row = $this->copy_inline_topic_preview_vars($topic_row, $topic);

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
			if ($topic_poster > 0 && isset($foe_user_id_map[$topic_poster]))
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

		return is_array($topics) ? $this->exclude_first_post_disliked_topics(array_values($topics)) : [];
	}

	protected function exclude_first_post_disliked_topics(array $topics): array
	{
		if (empty($topics) || $this->get_post_collapse_dislike_threshold() <= 0)
		{
			return array_values($topics);
		}

		$topic_ids = [];
		foreach ($topics as $topic)
		{
			$topic_id = is_array($topic) ? (int) ($topic['topic_id'] ?? 0) : 0;
			if ($topic_id > 0)
			{
				$topic_ids[$topic_id] = true;
			}
		}

		$excluded_topic_ids = $this->get_first_post_disliked_topic_id_map(array_keys($topic_ids));
		if (empty($excluded_topic_ids))
		{
			return array_values($topics);
		}

		$filtered_topics = [];
		foreach ($topics as $topic)
		{
			$topic_id = is_array($topic) ? (int) ($topic['topic_id'] ?? 0) : 0;
			if ($topic_id > 0 && isset($excluded_topic_ids[$topic_id]))
			{
				continue;
			}

			$filtered_topics[] = $topic;
		}

		return $filtered_topics;
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
			. $this->db->sql_in_set($topic_alias . '.topic_poster', $foe_user_ids, true);
	}

	protected function build_first_post_disliked_topic_exclusion_sql(string $topic_alias = 't'): string
	{
		$threshold = $this->get_post_collapse_dislike_threshold();
		if ($threshold <= 0)
		{
			return '';
		}

		return ' AND ((
			SELECT COUNT(ttfpd.user_id)
			FROM ' . $this->dislikes_table . ' ttfpd
			WHERE ttfpd.post_id = ' . $topic_alias . '.topic_first_post_id
		) - (
			SELECT COUNT(ttfpl.user_id)
			FROM ' . $this->likes_table . ' ttfpl
			WHERE ttfpl.post_id = ' . $topic_alias . '.topic_first_post_id
		)) < ' . $threshold;
	}

	protected function get_first_post_disliked_topic_id_map(array $topic_ids): array
	{
		return $this->filter_first_post_net_dislike_scores_at_threshold(
			$this->get_first_post_net_dislike_score_map($topic_ids)
		);
	}

	protected function get_first_post_net_dislike_score_map(array $topic_ids): array
	{
		$topic_ids = array_values(array_unique(array_filter(array_map('intval', $topic_ids), static function ($topic_id) {
			return $topic_id > 0;
		})));
		if (empty($topic_ids))
		{
			return [];
		}

		$sql = 'SELECT t.topic_id,
				(SELECT COUNT(ttfpd.user_id)
					FROM ' . $this->dislikes_table . ' ttfpd
					WHERE ttfpd.post_id = t.topic_first_post_id) AS first_post_dislike_count,
				(SELECT COUNT(ttfpl.user_id)
					FROM ' . $this->likes_table . ' ttfpl
					WHERE ttfpl.post_id = t.topic_first_post_id) AS first_post_like_count
			FROM ' . TOPICS_TABLE . ' t
			WHERE ' . $this->db->sql_in_set('t.topic_id', $topic_ids);
		$result = $this->db->sql_query($sql);
		$net_dislike_scores = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$topic_id = (int) ($row['topic_id'] ?? 0);
			$net_dislike_score = (int) ($row['first_post_dislike_count'] ?? 0) - (int) ($row['first_post_like_count'] ?? 0);
			if ($topic_id > 0 && $net_dislike_score > 0)
			{
				$net_dislike_scores[$topic_id] = $net_dislike_score;
			}
		}
		$this->db->sql_freeresult($result);

		return $net_dislike_scores;
	}

	protected function filter_first_post_net_dislike_scores_at_threshold(array $net_dislike_scores): array
	{
		$threshold = $this->get_post_collapse_dislike_threshold();
		if ($threshold <= 0 || empty($net_dislike_scores))
		{
			return [];
		}

		$disliked_topic_ids = [];
		foreach ($net_dislike_scores as $topic_id => $net_dislike_score)
		{
			if ((int) $net_dislike_score >= $threshold)
			{
				$disliked_topic_ids[(int) $topic_id] = true;
			}
		}

		return $disliked_topic_ids;
	}

	protected function add_topic_like_counts(array $topics): array
	{
		if (empty($topics))
		{
			return $topics;
		}

		$topic_ids = [];
		$needs_lookup = false;
		foreach ($topics as $topic)
		{
			if (!is_array($topic))
			{
				continue;
			}

			$topic_id = (int) ($topic['topic_id'] ?? $topic['TOPIC_ID'] ?? 0);
			if ($topic_id > 0)
			{
				$topic_ids[$topic_id] = true;
				if (!array_key_exists('TOPIC_LIKE_COUNT', $topic)
					&& !array_key_exists('topic_like_count', $topic)
					&& !array_key_exists('like_count', $topic))
				{
					$needs_lookup = true;
				}
			}
		}

		if (empty($topic_ids))
		{
			return $topics;
		}

		$like_counts = [];
		if ($needs_lookup && $this->topic_likes !== null && method_exists($this->topic_likes, 'get_topic_like_counts'))
		{
			$like_counts = $this->topic_likes->get_topic_like_counts(array_keys($topic_ids));
		}

		foreach ($topics as &$topic)
		{
			if (!is_array($topic))
			{
				continue;
			}

			$topic_id = (int) ($topic['topic_id'] ?? $topic['TOPIC_ID'] ?? 0);
			$topic['TOPIC_LIKE_COUNT'] = $topic_id > 0
				? (int) ($like_counts[$topic_id] ?? $topic['TOPIC_LIKE_COUNT'] ?? $topic['topic_like_count'] ?? $topic['like_count'] ?? 0)
				: 0;
		}
		unset($topic);

		return $topics;
	}

	protected function get_topic_like_count_from_row(array $row): int
	{
		return (int) ($row['TOPIC_LIKE_COUNT'] ?? $row['topic_like_count'] ?? $row['like_count'] ?? 0);
	}

	protected function add_inline_topic_previews(array $topics, array $topic_order = [], bool $server_render = true): array
	{
		if (empty($topics))
		{
			return $topics;
		}

		if ($this->user_hides_enhanced_topic_list_view())
		{
			return $topics;
		}

		$topicpreview = $this->get_topicpreview_context();
		if (!$topicpreview['enabled'])
		{
			return $topics;
		}

		$topic_id_map = [];
		foreach ($topics as &$topic)
		{
			if (!is_array($topic))
			{
				continue;
			}

			$topic_id = (int) ($topic['topic_id'] ?? 0);
			$topic = array_merge($topic, $this->build_inline_topic_preview_vars($topic_id, $topicpreview['media_enabled']));
			if ($topic_id > 0)
			{
				$topic_id_map[$topic_id] = true;
			}
		}
		unset($topic);

		$server_preview_topic_ids = $server_render ? $this->get_server_inline_preview_topic_ids($topics, $topic_order, $topic_id_map) : [];
		if (!empty($server_preview_topic_ids) && $this->inline_preview !== null)
		{
			$server_preview_topic_id_map = array_fill_keys($server_preview_topic_ids, true);
			$previews = $this->inline_preview->previews_for_topic_ids($server_preview_topic_ids);
			foreach ($topics as &$topic)
			{
				if (!is_array($topic))
				{
					continue;
				}

				$topic_id = (int) ($topic['topic_id'] ?? 0);
				if ($topic_id <= 0 || !isset($server_preview_topic_id_map[$topic_id]))
				{
					continue;
				}

				$topic['S_TOPTOPICS_INLINE_LAZY_PREVIEW'] = false;
				if (empty($previews[$topic_id]))
				{
					continue;
				}

				$topic_url = $this->build_inline_preview_topic_url($topic);
				$preview_html = $this->build_server_inline_topic_preview_html(
					$previews[$topic_id],
					$topic_url,
					$this->get_topic_dislike_fade_class((int) ($topic['TOPTOPICS_FIRST_POST_NET_DISLIKE_SCORE'] ?? $topic['first_post_net_dislike_score'] ?? 0)),
					$topicpreview['media_enabled']
				);
				if ($preview_html === '')
				{
					continue;
				}

				$topic['S_TOPTOPICS_INLINE_SERVER_PREVIEW'] = true;
				$topic['TOPTOPICS_INLINE_PREVIEW_HTML'] = $preview_html;
			}
			unset($topic);
		}

		return $topics;
	}

	protected function get_server_inline_preview_topic_ids(array $topics, array $topic_order, array $topic_id_map): array
	{
		if ($this->inline_preview === null)
		{
			return [];
		}

		$topic_ids = [];
		$seen = [];
		$ordered_ids = !empty($topic_order) ? $topic_order : array_map(static function ($topic) {
			return is_array($topic) ? (int) ($topic['topic_id'] ?? 0) : 0;
		}, $topics);

		foreach ($ordered_ids as $topic_id)
		{
			$topic_id = (int) $topic_id;
			if ($topic_id <= 0 || isset($seen[$topic_id]) || !isset($topic_id_map[$topic_id]))
			{
				continue;
			}

			$seen[$topic_id] = true;
			$topic_ids[] = $topic_id;
			if (count($topic_ids) >= self::INLINE_PREVIEW_SERVER_RENDER_LIMIT)
			{
				break;
			}
		}

		return $topic_ids;
	}

	protected function build_inline_topic_preview_vars(int $topic_id, bool $media_enabled = true): array
	{
		if ($topic_id <= 0)
		{
			return $this->empty_inline_topic_preview_vars();
		}

		return [
			'S_TOPTOPICS_INLINE_LAZY_PREVIEW' => true,
			'S_TOPTOPICS_INLINE_IMAGE_PREVIEW' => false,
			'S_TOPTOPICS_INLINE_EXCERPT_PREVIEW' => false,
			'S_TOPTOPICS_INLINE_RICH_PREVIEW' => false,
			'S_TOPTOPICS_INLINE_MEDIA_PREVIEW' => $media_enabled,
			'S_TOPTOPICS_INLINE_SERVER_PREVIEW' => false,
			'U_TOPTOPICS_INLINE_PREVIEW' => $this->helper->route('freemitbbs_toptopics_inline_preview', ['topic' => $topic_id]),
			'U_TOPTOPICS_INLINE_PREVIEW_BATCH' => $this->helper->route('freemitbbs_toptopics_inline_preview_batch'),
			'TOPTOPICS_INLINE_PREVIEW_TOPIC_ID' => $topic_id,
			'TOPTOPICS_INLINE_IMAGE_URL' => '',
			'TOPTOPICS_INLINE_EXCERPT' => '',
			'TOPTOPICS_INLINE_PREVIEW_HTML' => '',
		];
	}

	protected function empty_inline_topic_preview_vars(): array
	{
		return [
			'S_TOPTOPICS_INLINE_LAZY_PREVIEW' => false,
			'S_TOPTOPICS_INLINE_IMAGE_PREVIEW' => false,
			'S_TOPTOPICS_INLINE_EXCERPT_PREVIEW' => false,
			'S_TOPTOPICS_INLINE_RICH_PREVIEW' => false,
			'S_TOPTOPICS_INLINE_MEDIA_PREVIEW' => false,
			'S_TOPTOPICS_INLINE_SERVER_PREVIEW' => false,
			'U_TOPTOPICS_INLINE_PREVIEW' => '',
			'U_TOPTOPICS_INLINE_PREVIEW_BATCH' => '',
			'TOPTOPICS_INLINE_PREVIEW_TOPIC_ID' => 0,
			'TOPTOPICS_INLINE_IMAGE_URL' => '',
			'TOPTOPICS_INLINE_EXCERPT' => '',
			'TOPTOPICS_INLINE_PREVIEW_HTML' => '',
		];
	}

	protected function copy_inline_topic_preview_vars(array $target, array $source, bool $server_render_visible = false): array
	{
		foreach (array_keys($this->empty_inline_topic_preview_vars()) as $key)
		{
			if (array_key_exists($key, $source))
			{
				$target[$key] = $source[$key];
			}
		}

		if ($server_render_visible)
		{
			$target = $this->server_render_visible_inline_topic_preview($target, $source);
		}

		return $target;
	}

	protected function server_render_visible_inline_topic_preview(array $target, array $source): array
	{
		$topic_id = (int) ($target['TOPTOPICS_INLINE_PREVIEW_TOPIC_ID'] ?? $source['topic_id'] ?? 0);
		if ($topic_id <= 0)
		{
			return $target;
		}

		$surface_key = $this->get_visible_inline_preview_surface_key($source, $target);
		if (!empty($target['S_TOPTOPICS_INLINE_SERVER_PREVIEW']))
		{
			$this->increment_visible_inline_preview_count($surface_key);
			return $target;
		}

		if (empty($target['S_TOPTOPICS_INLINE_LAZY_PREVIEW']))
		{
			$topicpreview = $this->get_topicpreview_context();
			if (!$topicpreview['enabled'])
			{
				return $target;
			}

			$target = array_merge(
				$target,
				$this->build_inline_topic_preview_vars($topic_id, $topicpreview['media_enabled'])
			);
		}

		if ($this->inline_preview === null
			|| $this->get_visible_inline_preview_count($surface_key) >= self::INLINE_PREVIEW_SERVER_RENDER_LIMIT
			|| empty($target['S_TOPTOPICS_INLINE_LAZY_PREVIEW']))
		{
			return $target;
		}

		$this->increment_visible_inline_preview_count($surface_key);
		$target['S_TOPTOPICS_INLINE_LAZY_PREVIEW'] = false;
		$previews = $this->inline_preview->previews_for_topic_ids([$topic_id]);
		if (empty($previews[$topic_id]))
		{
			return $target;
		}

		$topic_url = (string) ($target['U_VIEW_TOPIC'] ?? $target['U_TOPIC'] ?? '');
		if ($topic_url === '')
		{
			$topic_url = $this->build_inline_preview_topic_url($source);
		}
		else
		{
			$topic_url = $this->decode_html_url($topic_url);
		}

		$preview_html = $this->build_server_inline_topic_preview_html(
			$previews[$topic_id],
			$topic_url,
			(string) ($target['TOPTOPICS_TOPIC_DISLIKE_FADE_CLASS'] ?? $target['TOPIC_DISLIKE_FADE_CLASS'] ?? ''),
			!empty($target['S_TOPTOPICS_INLINE_MEDIA_PREVIEW'])
		);
		if ($preview_html === '')
		{
			return $target;
		}

		$target['S_TOPTOPICS_INLINE_SERVER_PREVIEW'] = true;
		$target['TOPTOPICS_INLINE_PREVIEW_HTML'] = $preview_html;

		return $target;
	}

	protected function get_visible_inline_preview_surface_key(array $source, array $target): string
	{
		$page_name = (string) ($this->user->page['page_name'] ?? '');
		$forum_id = (int) ($source['forum_id'] ?? $target['FORUM_ID'] ?? 0);

		if ($page_name === 'index.php')
		{
			return $forum_id === 2 ? 'index:rtng-junban' : 'index:rtng-default';
		}

		if ($page_name === 'viewforum.php')
		{
			return 'viewforum:' . $forum_id;
		}

		return ($page_name !== '' ? $page_name : 'page') . ':' . $forum_id;
	}

	protected function get_visible_inline_preview_count(string $surface_key): int
	{
		return (int) ($this->inline_preview_visible_server_render_counts[$surface_key] ?? 0);
	}

	protected function increment_visible_inline_preview_count(string $surface_key): void
	{
		$this->inline_preview_visible_server_render_counts[$surface_key] = $this->get_visible_inline_preview_count($surface_key) + 1;
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

	protected function get_topicpreview_context(): array
	{
		$enabled = !empty($this->config['topic_preview_limit'])
			&& !empty($this->user->data['user_topic_preview'])
			&& !$this->user_hides_enhanced_topic_list_view();

		return [
			'enabled' => $enabled,
			'media_enabled' => !$this->user_hides_topic_list_media_previews(),
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

		$topics = $this->add_topic_like_counts($topics);
		$topics = $this->add_topic_tracking($topics);
		$topics = $this->add_topic_display_state($topics);
		if ($with_previews)
		{
			$topics = $this->add_inline_topic_previews($topics);
		}

		return $topics;
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
