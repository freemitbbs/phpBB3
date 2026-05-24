<?php

namespace freemitbbs\posttags\controller;

class search
{
	private const PAGE_SIZE = 25;

	protected \phpbb\auth\auth $auth;
	protected \phpbb\content_visibility $content_visibility;
	protected \phpbb\db\driver\driver_interface $db;
	protected \phpbb\controller\helper $helper;
	protected \phpbb\language\language $language;
	protected \phpbb\pagination $pagination;
	protected \phpbb\request\request_interface $request;
	protected \phpbb\template\template $template;
	protected \phpbb\user $user;
	protected \freemitbbs\posttags\service\manager $manager;
	protected string $root_path;
	protected string $php_ext;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\content_visibility $content_visibility,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\controller\helper $helper,
		\phpbb\language\language $language,
		\phpbb\pagination $pagination,
		\phpbb\request\request_interface $request,
		\phpbb\template\template $template,
		\phpbb\user $user,
		\freemitbbs\posttags\service\manager $manager,
		string $root_path,
		string $php_ext
	)
	{
		$this->auth = $auth;
		$this->content_visibility = $content_visibility;
		$this->db = $db;
		$this->helper = $helper;
		$this->language = $language;
		$this->pagination = $pagination;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->manager = $manager;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	public function by_tag(string $tag)
	{
		$this->language->add_lang('common', 'freemitbbs/posttags');

		$tag_name = $this->manager->clean_tag($tag);
		if ($tag_name === '')
		{
			throw new \phpbb\exception\http_exception(404, 'NOT_FOUND');
		}

		$tag_row = $this->manager->get_tag($tag_name);
		if ($tag_row)
		{
			$tag_name = (string) $tag_row['tag_name'];
		}

		$forum_ids = $this->readable_forum_ids();
		$total = ($tag_row && !empty($forum_ids)) ? $this->count_tag_posts((int) $tag_row['tag_id'], $forum_ids) : 0;
		$start = max(0, $this->request->variable('start', 0));
		$start = $this->pagination->validate_start($start, self::PAGE_SIZE, $total);

		if ($tag_row && $total > 0)
		{
			$this->assign_results((int) $tag_row['tag_id'], $forum_ids, self::PAGE_SIZE, $start);
		}

		$route = $this->helper->route('freemitbbs_posttags_search', ['tag' => $tag_name]);
		$this->pagination->generate_template_pagination($route, 'pagination', 'start', $total, self::PAGE_SIZE, $start);

		$page_title = $this->language->lang('POSTTAGS_SEARCH_TITLE', $tag_name);
		$this->template->assign_vars([
			'POSTTAGS_SEARCH_TAG' => $tag_name,
			'POSTTAGS_SEARCH_TITLE' => $page_title,
			'U_POSTTAGS_SEARCH' => $route,
			'S_POSTTAGS_HAS_RESULTS' => $total > 0,
			'PAGE_NUMBER' => $this->pagination->on_page($total, self::PAGE_SIZE, $start),
		]);

		return $this->helper->render('@freemitbbs_posttags/posttags_search.html', $page_title);
	}

	protected function readable_forum_ids(): array
	{
		return array_values(array_map('intval', array_keys($this->auth->acl_getf('f_read', true))));
	}

	protected function count_tag_posts(int $tag_id, array $forum_ids): int
	{
		$sql = 'SELECT COUNT(p.post_id) AS total
			FROM ' . $this->manager->get_post_tags_table() . ' pt
			JOIN ' . POSTS_TABLE . ' p
				ON p.post_id = pt.post_id
			JOIN ' . TOPICS_TABLE . ' t
				ON t.topic_id = p.topic_id
			WHERE pt.tag_id = ' . $tag_id . '
				AND ' . $this->db->sql_in_set('p.forum_id', $forum_ids) . '
				AND ' . $this->content_visibility->get_forums_visibility_sql('post', $forum_ids, 'p.') . '
				AND ' . $this->content_visibility->get_forums_visibility_sql('topic', $forum_ids, 't.');
		$result = $this->db->sql_query($sql);
		$total = (int) $this->db->sql_fetchfield('total');
		$this->db->sql_freeresult($result);

		return $total;
	}

	protected function assign_results(int $tag_id, array $forum_ids, int $limit, int $start): void
	{
		$sql = 'SELECT p.post_id, p.topic_id, p.forum_id, p.poster_id, p.post_subject, p.post_username, p.post_time,
				t.topic_title,
				f.forum_name,
				u.username, u.user_colour
			FROM ' . $this->manager->get_post_tags_table() . ' pt
			JOIN ' . POSTS_TABLE . ' p
				ON p.post_id = pt.post_id
			JOIN ' . TOPICS_TABLE . ' t
				ON t.topic_id = p.topic_id
			JOIN ' . FORUMS_TABLE . ' f
				ON f.forum_id = p.forum_id
			LEFT JOIN ' . USERS_TABLE . ' u
				ON u.user_id = p.poster_id
			WHERE pt.tag_id = ' . $tag_id . '
				AND ' . $this->db->sql_in_set('p.forum_id', $forum_ids) . '
				AND ' . $this->content_visibility->get_forums_visibility_sql('post', $forum_ids, 'p.') . '
				AND ' . $this->content_visibility->get_forums_visibility_sql('topic', $forum_ids, 't.') . '
			ORDER BY p.post_time DESC, p.post_id DESC';
		$result = $this->db->sql_query_limit($sql, $limit, $start);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$post_id = (int) $row['post_id'];
			$topic_id = (int) $row['topic_id'];
			$forum_id = (int) $row['forum_id'];
			$poster_id = (int) $row['poster_id'];
			$post_subject = censor_text((string) ($row['post_subject'] ?: $row['topic_title']));

			$this->template->assign_block_vars('posttags_results', [
				'POST_ID' => $post_id,
				'POST_SUBJECT' => $post_subject,
				'POST_DATE' => $this->user->format_date((int) $row['post_time']),
				'POST_DATE_RFC3339' => gmdate(DATE_RFC3339, (int) $row['post_time']),
				'POST_AUTHOR_FULL' => get_username_string('full', $poster_id, (string) ($row['username'] ?? ''), (string) ($row['user_colour'] ?? ''), (string) $row['post_username']),
				'TOPIC_TITLE' => censor_text((string) $row['topic_title']),
				'FORUM_NAME' => (string) $row['forum_name'],
				'U_POST' => append_sid($this->root_path . 'viewtopic.' . $this->php_ext, 'p=' . $post_id) . '#p' . $post_id,
				'U_TOPIC' => append_sid($this->root_path . 'viewtopic.' . $this->php_ext, 't=' . $topic_id),
				'U_FORUM' => append_sid($this->root_path . 'viewforum.' . $this->php_ext, 'f=' . $forum_id),
			]);
		}
		$this->db->sql_freeresult($result);
	}
}
