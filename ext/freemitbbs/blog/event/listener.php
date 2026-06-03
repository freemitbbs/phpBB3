<?php

namespace freemitbbs\blog\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	private const NICKNAME_PROFILE_FIELD_IDENT = 'nick_name';

	protected \phpbb\auth\auth $auth;
	protected \phpbb\config\config $config;
	protected \phpbb\controller\helper $helper;
	protected \phpbb\db\driver\driver_interface $db;
	protected \phpbb\language\language $language;
	protected \phpbb\request\request_interface $request;
	protected \phpbb\template\template $template;
	protected \phpbb\user $user;
	protected string $blog_topics_table;
	protected array $blog_comments_disabled_topic_cache = [];
	protected array $blog_profile_link_cache = [];
	protected ?bool $nickname_profile_field_exists = null;
	protected array $nickname_cache = [];

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\config\config $config,
		\phpbb\controller\helper $helper,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\language\language $language,
		\phpbb\request\request_interface $request,
		\phpbb\template\template $template,
		\phpbb\user $user,
		string $table_prefix
	)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->helper = $helper;
		$this->db = $db;
		$this->language = $language;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->blog_topics_table = $table_prefix . 'blog_topics';
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.user_setup' => 'load_language',
			'core.modify_username_string' => 'append_profile_nickname_to_username',
			'core.page_header' => 'assign_header_links',
			'core.permissions' => 'add_permissions',
			'core.viewtopic_modify_post_row' => 'add_send_to_blog_button',
			'core.viewtopic_modify_forum_id' => 'redirect_blog_viewtopic',
			'core.viewforum_modify_page_title' => 'redirect_blog_viewforum',
			'core.memberlist_view_profile' => 'add_profile_blog_link',
			'core.ucp_display_module_before' => 'load_ucp_language',
			'core.modify_posting_auth' => 'block_disabled_blog_comments',
			'core.viewtopic_modify_quick_reply_template_vars' => 'disable_blog_quick_reply',
			'core.viewtopic_modify_page_title' => 'disable_blog_reply_buttons',
			'core.posting_modify_template_vars' => 'customise_blog_posting_title',
			'core.posting_modify_submit_post_before' => 'publish_blog_draft_before_submit',
			'core.submit_post_end' => 'redirect_blog_after_submit',
			'core.delete_post_after' => 'delete_blog_topic_metadata_after_post_delete',
			'core.delete_topics_before_query' => 'delete_blog_topic_metadata_before_topic_delete',
			'core.search_backend_search_after' => 'filter_reposted_blog_search_results',
			'vse.similartopics.modify_rowset' => 'filter_reposted_blog_similar_topics',
			'imcger.recenttopicsng.modify_topics_list' => 'filter_reposted_blog_recent_topics',
			'freemitbbs.toptopics.modify_topic_list' => 'filter_reposted_blog_top_topics',
		];
	}

	public function load_language(): void
	{
		$this->language->add_lang('common', 'freemitbbs/blog');
	}

	public function load_ucp_language(): void
	{
		$this->language->add_lang('common', 'freemitbbs/blog');
	}

	public function append_profile_nickname_to_username($event): void
	{
		if (\function_exists('topic_list_username_nickname_suppression') && \topic_list_username_nickname_suppression())
		{
			return;
		}

		$mode = (string) ($event['mode'] ?? '');
		$user_id = (int) ($event['user_id'] ?? 0);
		if (!in_array($mode, ['full', 'no_profile'], true) || $user_id <= 0 || $user_id === ANONYMOUS)
		{
			return;
		}

		$nickname = $this->profile_nickname($user_id);
		if ($nickname === '')
		{
			return;
		}

		$username = trim(html_entity_decode(strip_tags((string) ($event['username'] ?? '')), ENT_QUOTES, 'UTF-8'));
		if ($username !== '' && utf8_clean_string($username) === utf8_clean_string($nickname))
		{
			return;
		}

		$suffix = '（' . htmlspecialchars(censor_text($nickname), ENT_QUOTES, 'UTF-8') . '）';
		$username_string = (string) ($event['username_string'] ?? '');
		if ($username_string === '' || strpos($username_string, $suffix) !== false)
		{
			return;
		}

		if (preg_match('#</(?:a|span)>$#', $username_string, $match, PREG_OFFSET_CAPTURE))
		{
			$position = (int) $match[0][1];
			$username_string = substr($username_string, 0, $position) . $suffix . substr($username_string, $position);
		}
		else
		{
			$username_string .= $suffix;
		}

		$event['username_string'] = $username_string;
	}

	public function assign_header_links(): void
	{
		$user_id = (int) $this->user->data['user_id'];
		$can_create = $user_id !== ANONYMOUS && $this->auth->acl_get('u_blog_create');

		$this->template->assign_vars([
			'S_FREEMITBBS_BLOG_NAV' => true,
			'S_BLOG_CAN_CREATE' => $can_create,
			'U_BLOG_INDEX' => $this->helper->route('freemitbbs_blog_index'),
			'U_BLOG_MANAGE' => $can_create ? $this->helper->route('freemitbbs_blog_manage') : '',
			'U_BLOG_NEW' => $can_create ? $this->helper->route('freemitbbs_blog_new') : '',
		]);
	}

	public function add_permissions($event): void
	{
		$permissions = $event['permissions'];
		$permissions['u_blog_create'] = ['lang' => 'ACL_U_BLOG_CREATE', 'cat' => 'misc'];
		$event['permissions'] = $permissions;
	}

	public function add_send_to_blog_button($event): void
	{
		$this->remove_nickname_from_post_profile($event);

		$post_row = $event['post_row'];

		if ($this->blog_comments_disabled_for_topic(
			(int) ($event['row']['forum_id'] ?? 0),
			(int) ($event['row']['topic_id'] ?? 0),
			(int) ($event['row']['topic_poster'] ?? 0)
		))
		{
			$post_row['U_QUOTE'] = '';
		}

		$current_user_id = (int) $this->user->data['user_id'];
		$row = $event['row'];
		$post_id = (int) ($row['post_id'] ?? 0);
		$poster_id = (int) ($event['poster_id'] ?? $row['poster_id'] ?? $row['user_id'] ?? ANONYMOUS);

		if ($this->has_public_blog_entries($poster_id))
		{
			$post_row = $this->append_postrow_blog_profile_link(
				$post_row,
				$this->helper->route('freemitbbs_blog_user', ['user_id' => $poster_id])
			);
		}

		if ($this->is_blog_forum((int) ($row['forum_id'] ?? 0)))
		{
			$event['post_row'] = $post_row;
			return;
		}

		if ($current_user_id === ANONYMOUS
			|| $current_user_id !== $poster_id
			|| !$post_id
			|| !$this->auth->acl_get('u_blog_create')
			|| !empty($this->user->data['is_bot']))
		{
			$event['post_row'] = $post_row;
			return;
		}

		$post_row['S_BLOG_CAN_SEND'] = true;
		$post_row['U_BLOG_SEND'] = $this->helper->route('freemitbbs_blog_send_post', ['post_id' => $post_id]);
		$post_row['BLOG_SEND_HASH'] = generate_link_hash('freemitbbs_blog_send_' . $post_id);
		$event['post_row'] = $post_row;
	}

	protected function remove_nickname_from_post_profile($event): void
	{
		$cp_row = $event['cp_row'] ?? [];
		if (empty($cp_row) || !is_array($cp_row))
		{
			return;
		}

		if (!empty($cp_row['blockrow']) && is_array($cp_row['blockrow']))
		{
			$cp_row['blockrow'] = array_values(array_filter($cp_row['blockrow'], static function (array $field_data): bool {
				return ($field_data['PROFILE_FIELD_IDENT'] ?? '') !== self::NICKNAME_PROFILE_FIELD_IDENT;
			}));
		}

		if (!empty($cp_row['row']) && is_array($cp_row['row']))
		{
			foreach (array_keys($cp_row['row']) as $key)
			{
				if ($key === 'S_PROFILE_NICK_NAME' || strpos($key, 'PROFILE_NICK_NAME_') === 0)
				{
					unset($cp_row['row'][$key]);
				}
			}
		}

		$event['cp_row'] = $cp_row;
	}

	public function redirect_blog_viewtopic($event): void
	{
		$topic_data = $event['topic_data'] ?? [];
		$forum_id = (int) ($event['forum_id'] ?? $topic_data['forum_id'] ?? 0);
		$topic_id = (int) ($topic_data['topic_id'] ?? 0);

		if ($topic_id > 0 && $this->is_blog_forum($forum_id))
		{
			redirect($this->helper->route('freemitbbs_blog_entry', ['entry_id' => $topic_id]));
		}
	}

	public function redirect_blog_viewforum($event): void
	{
		if ($this->is_blog_forum((int) ($event['forum_id'] ?? 0)))
		{
			redirect($this->helper->route('freemitbbs_blog_index'));
		}
	}

	public function block_disabled_blog_comments($event): void
	{
		$mode = (string) ($event['mode'] ?? '');
		if (($mode !== 'reply' && $mode !== 'quote')
			|| !$this->is_blog_forum((int) ($event['forum_id'] ?? 0)))
		{
			return;
		}

		$post_data = $event['post_data'] ?? [];
		$topic_poster = (int) ($post_data['topic_poster'] ?? 0);
		if ($topic_poster <= 0)
		{
			$topic_poster = $this->topic_poster_id((int) ($event['topic_id'] ?? 0));
		}

		if ($topic_poster <= 0 || $this->blog_comments_enabled_for_user($topic_poster))
		{
			return;
		}

		$error = $event['error'] ?? [];
		$error[] = $this->language->lang('BLOG_COMMENTS_DISABLED');
		$event['error'] = $error;
		$event['is_authed'] = false;
	}

	public function disable_blog_quick_reply($event): void
	{
		$topic_data = $event['topic_data'] ?? [];
		if (!$this->blog_comments_disabled_for_topic(
			(int) ($topic_data['forum_id'] ?? 0),
			(int) ($topic_data['topic_id'] ?? 0),
			(int) ($topic_data['topic_poster'] ?? 0)
		))
		{
			return;
		}

		$tpl_ary = $event['tpl_ary'];
		$tpl_ary['S_QUICK_REPLY'] = false;
		$event['tpl_ary'] = $tpl_ary;
	}

	public function disable_blog_reply_buttons($event): void
	{
		$topic_data = $event['topic_data'] ?? [];
		if (!$this->blog_comments_disabled_for_topic(
			(int) ($event['forum_id'] ?? 0),
			(int) ($topic_data['topic_id'] ?? 0),
			(int) ($topic_data['topic_poster'] ?? 0)
		))
		{
			return;
		}

		$this->template->assign_vars([
			'S_DISPLAY_REPLY_INFO' => false,
			'U_POST_REPLY_TOPIC' => '',
		]);
	}

	public function filter_reposted_blog_search_results($event): void
	{
		if ((int) ($event['topic_id'] ?? 0) > 0)
		{
			return;
		}

		$id_ary = $event['id_ary'] ?? [];
		if (empty($id_ary) || !is_array($id_ary))
		{
			return;
		}

		if (($event['show_results'] ?? '') === 'topics')
		{
			$event['id_ary'] = $this->filter_reposted_topic_ids($id_ary);
			return;
		}

		$event['id_ary'] = $this->filter_reposted_first_post_ids($id_ary);
	}

	public function filter_reposted_blog_similar_topics($event): void
	{
		$rowset = $event['rowset'] ?? [];
		if (empty($rowset) || !is_array($rowset))
		{
			return;
		}

		$extra_excluded_topic_ids = [];
		$current_topic_id = $this->request->variable('t', 0);
		if ($current_topic_id <= 0)
		{
			$current_topic_id = $this->topic_id_for_post($this->request->variable('p', 0));
		}

		$source_topic_id = $this->source_topic_id_for_blog_topic($current_topic_id);
		if ($source_topic_id > 0)
		{
			$extra_excluded_topic_ids[$source_topic_id] = true;
		}

		$event['rowset'] = $this->filter_reposted_topic_rowset($rowset, $extra_excluded_topic_ids);
	}

	public function filter_reposted_blog_recent_topics($event): void
	{
		$rowset = $event['rowset'] ?? [];
		if (empty($rowset) || !is_array($rowset))
		{
			return;
		}

		$rowset = $this->filter_reposted_topic_rowset($rowset);
		$event['rowset'] = $rowset;
		$event['topic_list'] = $this->topic_ids_from_rowset($rowset);
	}

	public function filter_reposted_blog_top_topics($event): void
	{
		$topics = $event['topics'] ?? [];
		if (empty($topics) || !is_array($topics))
		{
			return;
		}

		$event['topics'] = array_values($this->filter_reposted_topic_rowset($topics));
	}

	public function add_profile_blog_link($event): void
	{
		$member = $event['member'];
		$user_id = (int) ($member['user_id'] ?? ANONYMOUS);
		if ($user_id === ANONYMOUS)
		{
			return;
		}

		if (!$this->has_public_blog_entries($user_id))
		{
			return;
		}

		$blog_url = $this->helper->route('freemitbbs_blog_user', ['user_id' => $user_id]);
		$member['user_posts'] = $this->append_blog_profile_link((string) (($member['user_posts'] ?? 0) ?: 0), $blog_url, true);
		$event['member'] = $member;
	}

	protected function append_postrow_blog_profile_link(array $post_row, string $blog_url): array
	{
		$post_count = (string) ($post_row['POSTER_POSTS'] ?? '');
		if ($post_count === '')
		{
			return $post_row;
		}

		$search_url = (string) ($post_row['U_SEARCH'] ?? '');
		if ($search_url !== '')
		{
			$post_count = '<a href="' . $this->html_attribute($search_url) . '">' . $post_count . '</a>';
			$post_row['U_SEARCH'] = '';
		}

		$post_row['POSTER_POSTS'] = $this->append_blog_profile_link($post_count, $blog_url, false);

		return $post_row;
	}

	protected function append_blog_profile_link(string $value, string $blog_url, bool $strong): string
	{
		$link = '<a href="' . $this->html_attribute($blog_url) . '">' . htmlspecialchars($this->language->lang('BLOG'), ENT_QUOTES, 'UTF-8') . '</a>';
		if ($strong)
		{
			$link = '<strong>' . $link . '</strong>';
		}

		return $value . ' | ' . $link;
	}

	protected function html_attribute(string $value): string
	{
		return htmlspecialchars(htmlspecialchars_decode($value, ENT_QUOTES), ENT_QUOTES, 'UTF-8');
	}

	public function customise_blog_posting_title($event): void
	{
		if (!$this->is_blog_forum((int) $event['forum_id']))
		{
			return;
		}

		$mode = (string) $event['mode'];
		$page_title = '';
		$page_explain = '';
		if ($mode === 'post')
		{
			$page_title = $this->language->lang('BLOG_NEW_POST');
		}
		else if ($mode === 'edit')
		{
			$post_data = $event['post_data'];
			if ((int) ($post_data['topic_first_post_id'] ?? 0) !== (int) $event['post_id'])
			{
				return;
			}

			if ($this->is_blog_draft((int) $event['topic_id']))
			{
				$page_title = $this->language->lang('BLOG_EDIT_DRAFT');
				$page_explain = $this->language->lang('BLOG_EDIT_DRAFT_EXPLAIN');
			}
			else
			{
				$page_title = $this->language->lang('BLOG_EDIT_POST');
			}
		}

		if ($page_title === '')
		{
			return;
		}

		$page_data = $event['page_data'];
		$page_data['L_POST_A'] = $page_title;
		$page_data['S_FREEMITBBS_BLOG_POSTING'] = true;
		$page_data['S_DELETE_ALLOWED'] = false;
		$page_data['S_SOFTDELETE_ALLOWED'] = false;
		$page_data['BLOG_POSTING_LABEL'] = $page_title;
		$page_data['BLOG_POSTING_EXPLAIN'] = $page_explain;
		$event['page_data'] = $page_data;
		$event['page_title'] = $page_title;
	}

	public function publish_blog_draft_before_submit($event): void
	{
		// Draft/published state is controlled from the blog manage list, not by editor submit.
	}

	public function redirect_blog_after_submit($event): void
	{
		$data = $event['data'];
		if (!$this->is_blog_forum((int) ($data['forum_id'] ?? 0)))
		{
			return;
		}

		$topic_id = (int) ($data['topic_id'] ?? 0);
		if ($topic_id > 0 && (int) ($event['post_visibility'] ?? ITEM_UNAPPROVED) === ITEM_APPROVED)
		{
			$event['url'] = $this->helper->route('freemitbbs_blog_entry', ['entry_id' => $topic_id]);
		}
	}

	public function delete_blog_topic_metadata_after_post_delete($event): void
	{
		if (!$this->is_blog_forum((int) ($event['forum_id'] ?? 0)))
		{
			return;
		}

		$data = $event['data'] ?? [];
		$post_mode = (string) ($event['post_mode'] ?? '');
		$post_id = (int) ($event['post_id'] ?? 0);
		$first_post_id = (int) ($data['topic_first_post_id'] ?? 0);
		if ($post_mode !== 'delete_topic' && ($first_post_id <= 0 || $post_id !== $first_post_id))
		{
			return;
		}

		$this->delete_blog_topic_metadata([(int) ($event['topic_id'] ?? 0)]);
	}

	public function delete_blog_topic_metadata_before_topic_delete($event): void
	{
		$this->delete_blog_topic_metadata($event['topic_ids'] ?? []);
	}

	protected function is_blog_forum(int $forum_id): bool
	{
		return $forum_id > 0 && $forum_id === (int) ($this->config['freemitbbs_blog_forum_id'] ?? 0);
	}

	protected function blog_forum_id(): int
	{
		return (int) ($this->config['freemitbbs_blog_forum_id'] ?? 0);
	}

	protected function is_blog_draft(int $topic_id): bool
	{
		if ($topic_id <= 0)
		{
			return false;
		}

		$sql = 'SELECT is_draft
			FROM ' . $this->blog_topics_table . '
			WHERE topic_id = ' . $topic_id;
		$result = $this->db->sql_query_limit($sql, 1);
		$is_draft = (bool) $this->db->sql_fetchfield('is_draft');
		$this->db->sql_freeresult($result);

		return $is_draft;
	}

	protected function delete_blog_topic_metadata(array $topic_ids): void
	{
		$topic_ids = $this->normalise_ids($topic_ids);
		if (empty($topic_ids))
		{
			return;
		}

		$sql = 'DELETE FROM ' . $this->blog_topics_table . '
			WHERE ' . $this->db->sql_in_set('topic_id', $topic_ids);
		$this->db->sql_query($sql);
	}

	protected function topic_poster_id(int $topic_id): int
	{
		if ($topic_id <= 0)
		{
			return 0;
		}

		$sql = 'SELECT topic_poster
			FROM ' . TOPICS_TABLE . '
			WHERE topic_id = ' . $topic_id;
		$result = $this->db->sql_query_limit($sql, 1);
		$topic_poster = (int) $this->db->sql_fetchfield('topic_poster');
		$this->db->sql_freeresult($result);

		return $topic_poster;
	}

	protected function blog_comments_enabled_for_user(int $user_id): bool
	{
		if ($user_id <= 0)
		{
			return true;
		}

		$sql = 'SELECT user_blog_comments_enabled
			FROM ' . USERS_TABLE . '
			WHERE user_id = ' . $user_id;
		$result = $this->db->sql_query_limit($sql, 1);
		$enabled = $this->db->sql_fetchfield('user_blog_comments_enabled');
		$this->db->sql_freeresult($result);

		return $enabled === false || (bool) $enabled;
	}

	protected function blog_comments_disabled_for_topic(int $forum_id, int $topic_id, int $topic_poster = 0): bool
	{
		if (!$this->is_blog_forum($forum_id) || $topic_id <= 0)
		{
			return false;
		}

		if (isset($this->blog_comments_disabled_topic_cache[$topic_id]))
		{
			return $this->blog_comments_disabled_topic_cache[$topic_id];
		}

		if ($topic_poster <= 0)
		{
			$topic_poster = $this->topic_poster_id($topic_id);
		}

		$this->blog_comments_disabled_topic_cache[$topic_id] = $topic_poster > 0
			&& !$this->blog_comments_enabled_for_user($topic_poster);

		return $this->blog_comments_disabled_topic_cache[$topic_id];
	}

	protected function profile_nickname(int $user_id): string
	{
		if (array_key_exists($user_id, $this->nickname_cache))
		{
			return $this->nickname_cache[$user_id];
		}

		$this->nickname_cache[$user_id] = '';
		if (!$this->nickname_profile_field_exists())
		{
			return '';
		}

		$sql = 'SELECT pf_nick_name
			FROM ' . PROFILE_FIELDS_DATA_TABLE . '
			WHERE user_id = ' . (int) $user_id;
		$result = $this->db->sql_query_limit($sql, 1);
		$nickname = (string) $this->db->sql_fetchfield('pf_nick_name');
		$this->db->sql_freeresult($result);

		$this->nickname_cache[$user_id] = trim(html_entity_decode(strip_tags($nickname), ENT_QUOTES, 'UTF-8'));

		return $this->nickname_cache[$user_id];
	}

	protected function nickname_profile_field_exists(): bool
	{
		if ($this->nickname_profile_field_exists !== null)
		{
			return $this->nickname_profile_field_exists;
		}

		$sql = 'SELECT field_id
			FROM ' . PROFILE_FIELDS_TABLE . "
			WHERE field_ident = 'nick_name'";
		$result = $this->db->sql_query_limit($sql, 1);
		$this->nickname_profile_field_exists = (bool) $this->db->sql_fetchfield('field_id');
		$this->db->sql_freeresult($result);

		return $this->nickname_profile_field_exists;
	}

	protected function has_public_blog_entries(int $user_id): bool
	{
		if ($user_id <= 0 || $user_id === ANONYMOUS)
		{
			return false;
		}

		if (isset($this->blog_profile_link_cache[$user_id]))
		{
			return $this->blog_profile_link_cache[$user_id];
		}

		$forum_id = (int) ($this->config['freemitbbs_blog_forum_id'] ?? 0);
		if ($forum_id <= 0 || !$this->auth->acl_get('f_read', $forum_id))
		{
			$this->blog_profile_link_cache[$user_id] = false;
			return false;
		}

		$sql = 'SELECT 1 AS blog_exists
			FROM ' . TOPICS_TABLE . ' t
			LEFT JOIN ' . $this->blog_topics_table . ' bt
				ON bt.topic_id = t.topic_id
			WHERE t.forum_id = ' . $forum_id . '
				AND t.topic_poster = ' . $user_id . '
				AND t.topic_moved_id = 0
				AND t.topic_visibility = ' . ITEM_APPROVED . '
				AND (bt.is_draft IS NULL OR bt.is_draft = 0)';
		$result = $this->db->sql_query_limit($sql, 1);
		$has_entries = (bool) $this->db->sql_fetchfield('blog_exists');
		$this->db->sql_freeresult($result);

		$this->blog_profile_link_cache[$user_id] = $has_entries;
		return $has_entries;
	}

	protected function filter_reposted_topic_rowset(array $rowset, array $extra_excluded_topic_ids = []): array
	{
		$topic_ids = $this->topic_ids_from_rowset($rowset);
		$excluded_topic_ids = $this->blog_topic_id_map($topic_ids);
		foreach ($extra_excluded_topic_ids as $topic_id => $unused)
		{
			$topic_id = (int) $topic_id;
			if ($topic_id > 0)
			{
				$excluded_topic_ids[$topic_id] = true;
			}
		}

		$filtered = [];
		foreach ($rowset as $key => $row)
		{
			$topic_id = (int) ($row['topic_id'] ?? 0);
			if ($topic_id > 0 && isset($excluded_topic_ids[$topic_id]))
			{
				continue;
			}

			$filtered[$key] = $row;
		}

		return $filtered;
	}

	protected function filter_reposted_topic_ids(array $topic_ids): array
	{
		$topic_ids = $this->normalise_ids($topic_ids);
		$excluded_topic_ids = $this->blog_topic_id_map($topic_ids);
		if (empty($excluded_topic_ids))
		{
			return $topic_ids;
		}

		$filtered = [];
		foreach ($topic_ids as $topic_id)
		{
			if (!isset($excluded_topic_ids[$topic_id]))
			{
				$filtered[] = $topic_id;
			}
		}

		return $filtered;
	}

	protected function filter_reposted_first_post_ids(array $post_ids): array
	{
		$post_ids = $this->normalise_ids($post_ids);
		$blog_forum_id = $this->blog_forum_id();
		if (empty($post_ids))
		{
			return [];
		}
		if ($blog_forum_id <= 0)
		{
			return $post_ids;
		}

		$sql = 'SELECT p.post_id
			FROM ' . POSTS_TABLE . ' p
			INNER JOIN ' . TOPICS_TABLE . ' t
				ON t.topic_id = p.topic_id
			INNER JOIN ' . $this->blog_topics_table . ' bt
				ON bt.topic_id = t.topic_id
			WHERE ' . $this->db->sql_in_set('p.post_id', $post_ids) . '
				AND t.forum_id = ' . $blog_forum_id . '
				AND ' . $this->reposted_blog_sql('bt');
		$result = $this->db->sql_query($sql);
		$excluded_post_ids = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$excluded_post_ids[(int) $row['post_id']] = true;
		}
		$this->db->sql_freeresult($result);

		if (empty($excluded_post_ids))
		{
			return $post_ids;
		}

		$filtered = [];
		foreach ($post_ids as $post_id)
		{
			if (!isset($excluded_post_ids[$post_id]))
			{
				$filtered[] = $post_id;
			}
		}

		return $filtered;
	}

	protected function blog_topic_id_map(array $topic_ids): array
	{
		$topic_ids = $this->normalise_ids($topic_ids);
		$blog_forum_id = $this->blog_forum_id();
		if (empty($topic_ids) || $blog_forum_id <= 0)
		{
			return [];
		}

		$sql = 'SELECT t.topic_id
			FROM ' . TOPICS_TABLE . ' t
			INNER JOIN ' . $this->blog_topics_table . ' bt
				ON bt.topic_id = t.topic_id
			WHERE ' . $this->db->sql_in_set('t.topic_id', $topic_ids) . '
				AND t.forum_id = ' . $blog_forum_id . '
				AND ' . $this->reposted_blog_sql('bt');
		$result = $this->db->sql_query($sql);
		$topic_id_map = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$topic_id_map[(int) $row['topic_id']] = true;
		}
		$this->db->sql_freeresult($result);

		return $topic_id_map;
	}

	protected function source_topic_id_for_blog_topic(int $topic_id): int
	{
		if ($topic_id <= 0)
		{
			return 0;
		}

		$sql = 'SELECT bt.source_topic_id
			FROM ' . $this->blog_topics_table . ' bt
			WHERE bt.topic_id = ' . $topic_id . '
				AND ' . $this->reposted_blog_sql('bt');
		$result = $this->db->sql_query_limit($sql, 1);
		$source_topic_id = (int) $this->db->sql_fetchfield('source_topic_id');
		$this->db->sql_freeresult($result);

		return $source_topic_id;
	}

	protected function topic_id_for_post(int $post_id): int
	{
		if ($post_id <= 0)
		{
			return 0;
		}

		$sql = 'SELECT topic_id
			FROM ' . POSTS_TABLE . '
			WHERE post_id = ' . $post_id;
		$result = $this->db->sql_query_limit($sql, 1);
		$topic_id = (int) $this->db->sql_fetchfield('topic_id');
		$this->db->sql_freeresult($result);

		return $topic_id;
	}

	protected function topic_ids_from_rowset(array $rowset): array
	{
		$topic_ids = [];
		foreach ($rowset as $row)
		{
			$topic_id = (int) ($row['topic_id'] ?? 0);
			if ($topic_id > 0)
			{
				$topic_ids[] = $topic_id;
			}
		}

		return $this->normalise_ids($topic_ids);
	}

	protected function normalise_ids(array $ids): array
	{
		$normalised = [];
		foreach ($ids as $id)
		{
			$id = (int) $id;
			if ($id > 0)
			{
				$normalised[$id] = true;
			}
		}

		return array_keys($normalised);
	}

	protected function reposted_blog_sql(string $alias): string
	{
		return '(' . $alias . '.source_topic_id > 0 OR ' . $alias . '.source_post_id > 0)';
	}
}
