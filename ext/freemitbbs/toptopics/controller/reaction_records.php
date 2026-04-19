<?php

namespace freemitbbs\toptopics\controller;

class reaction_records
{
	protected \phpbb\auth\auth $auth;
	protected \phpbb\content_visibility $content_visibility;
	protected \phpbb\db\driver\driver_interface $db;
	protected \phpbb\controller\helper $helper;
	protected \phpbb\language\language $language;
	protected \phpbb\pagination $pagination;
	protected \phpbb\template\template $template;
	protected \phpbb\user $user;
	protected \phpbb\user_loader $user_loader;
	protected string $likes_table;
	protected string $dislikes_table;
	protected string $root_path;
	protected string $php_ext;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\content_visibility $content_visibility,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\controller\helper $helper,
		\phpbb\language\language $language,
		\phpbb\pagination $pagination,
		\phpbb\template\template $template,
		\phpbb\user $user,
		\phpbb\user_loader $user_loader,
		string $likes_table,
		string $dislikes_table,
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
		$this->template = $template;
		$this->user = $user;
		$this->user_loader = $user_loader;
		$this->likes_table = $likes_table;
		$this->dislikes_table = $dislikes_table;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	public function base(int $page = 1)
	{
		if ((int) $this->user->data['user_id'] === ANONYMOUS)
		{
			login_box($this->helper->route('freemitbbs_toptopics_reaction_records'));
		}

		$this->language->add_lang('toptopics', 'freemitbbs/toptopics');

		$limit = 50;
		$page = max(1, $page);
		$start = ($page - 1) * $limit;
		$forum_ids = $this->get_readable_forum_ids();

		$total = 0;
		$rows = [];
		if ($forum_ids)
		{
			$total = $this->count_records($forum_ids);
			$rows = $total > 0 ? $this->get_records($forum_ids, $limit, $start) : [];
		}

		$this->assign_records($rows);

		$this->pagination->generate_template_pagination([
			'routes' => [
				'freemitbbs_toptopics_reaction_records',
				'freemitbbs_toptopics_reaction_records_page',
			],
		], 'pagination', 'page', $total, $limit, $start);

		$this->template->assign_vars([
			'PAGE_NUMBER' => $this->pagination->on_page($total, $limit, $start),
		]);

		return $this->helper->render(
			'@freemitbbs_toptopics/reaction_records.html',
			$this->language->lang('TOPTOPICS_REACTION_RECORDS')
		);
	}

	protected function get_readable_forum_ids(): array
	{
		$forum_ids = [];
		foreach ($this->auth->acl_getf('f_read') as $forum_id => $allowed)
		{
			if (!empty($allowed['f_read']))
			{
				$forum_ids[] = (int) $forum_id;
			}
		}

		return array_values(array_unique($forum_ids));
	}

	protected function count_records(array $forum_ids): int
	{
		return $this->count_table_records($this->likes_table, 'pl', 'liketime', $forum_ids)
			+ $this->count_table_records($this->dislikes_table, 'pd', 'disliketime', $forum_ids);
	}

	protected function count_table_records(string $table, string $alias, string $time_column, array $forum_ids): int
	{
		$sql = 'SELECT COUNT(*) AS record_count
			FROM ' . $table . ' ' . $alias . '
			INNER JOIN ' . POSTS_TABLE . ' p
				ON p.post_id = ' . $alias . '.post_id
			INNER JOIN ' . TOPICS_TABLE . ' t
				ON t.topic_id = p.topic_id
			WHERE ' . $this->build_record_where($alias, $time_column, $forum_ids);
		$result = $this->db->sql_query($sql);
		$count = (int) $this->db->sql_fetchfield('record_count');
		$this->db->sql_freeresult($result);

		return $count;
	}

	protected function get_records(array $forum_ids, int $limit, int $start): array
	{
		$like_where = $this->build_record_where('pl', 'liketime', $forum_ids);
		$dislike_where = $this->build_record_where('pd', 'disliketime', $forum_ids);

		$sql = "SELECT reaction_type, action_time, actor_user_id, post_id, topic_id, forum_id, post_subject, topic_title
			FROM (
				SELECT 'like' AS reaction_type, pl.liketime AS action_time, pl.user_id AS actor_user_id,
					p.post_id, p.topic_id, p.forum_id, p.post_subject, t.topic_title
				FROM " . $this->likes_table . ' pl
				INNER JOIN ' . POSTS_TABLE . ' p
					ON p.post_id = pl.post_id
				INNER JOIN ' . TOPICS_TABLE . ' t
					ON t.topic_id = p.topic_id
				WHERE ' . $like_where . "
				UNION ALL
				SELECT 'dislike' AS reaction_type, pd.disliketime AS action_time, pd.user_id AS actor_user_id,
					p.post_id, p.topic_id, p.forum_id, p.post_subject, t.topic_title
				FROM " . $this->dislikes_table . ' pd
				INNER JOIN ' . POSTS_TABLE . ' p
					ON p.post_id = pd.post_id
				INNER JOIN ' . TOPICS_TABLE . ' t
					ON t.topic_id = p.topic_id
				WHERE ' . $dislike_where . '
			) reaction_records
			ORDER BY action_time DESC, post_id DESC';

		$result = $this->db->sql_query_limit($sql, $limit, $start);
		$rows = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	protected function build_record_where(string $alias, string $time_column, array $forum_ids): string
	{
		$user_id = (int) $this->user->data['user_id'];

		return 'p.poster_id = ' . $user_id . '
			AND ' . $alias . '.user_id > 0
			AND ' . $alias . '.' . $time_column . ' > 0
			AND ' . $this->db->sql_in_set('p.forum_id', $forum_ids) . '
			AND ' . $this->content_visibility->get_forums_visibility_sql('post', $forum_ids, 'p.') . '
			AND ' . $this->content_visibility->get_forums_visibility_sql('topic', $forum_ids, 't.') . '
			AND t.topic_type <> ' . ITEM_MOVED;
	}

	protected function assign_records(array $rows): void
	{
		$actor_ids = [];
		foreach ($rows as $row)
		{
			$actor_ids[] = (int) $row['actor_user_id'];
		}

		if ($actor_ids)
		{
			$this->user_loader->load_users(array_values(array_unique($actor_ids)));
		}

		foreach ($rows as $row)
		{
			$reaction_type = (string) $row['reaction_type'];
			$reaction_label = $this->language->lang(
				$reaction_type === 'dislike' ? 'TOPTOPICS_REACTION_DISLIKE' : 'TOPTOPICS_REACTION_LIKE'
			);
			$post_link = '<a href="' . $this->build_post_url($row) . '">' . $this->escape(censor_text($row['post_subject'] ?: $row['topic_title'])) . '</a>';
			$topic_link = '<a href="' . $this->build_topic_url($row) . '" class="topictitle">' . $this->escape(censor_text($row['topic_title'])) . '</a>';

			$this->template->assign_block_vars('reaction_records', [
				'REACTION_CLASS' => $reaction_type === 'dislike' ? 'toptopics-reaction-dislike' : 'toptopics-reaction-like',
				'LINE' => $this->language->lang(
					'TOPTOPICS_RECEIVED_REACTION_LINE',
					$this->user->format_date((int) $row['action_time']),
					$this->user_loader->get_username((int) $row['actor_user_id'], 'full'),
					$reaction_label,
					$post_link,
					$topic_link
				),
			]);
		}
	}

	protected function build_post_url(array $row): string
	{
		return append_sid(
			$this->root_path . 'viewtopic.' . $this->php_ext,
			'f=' . (int) $row['forum_id'] . '&amp;t=' . (int) $row['topic_id'] . '&amp;p=' . (int) $row['post_id'] . '#p' . (int) $row['post_id']
		);
	}

	protected function build_topic_url(array $row): string
	{
		return append_sid(
			$this->root_path . 'viewtopic.' . $this->php_ext,
			'f=' . (int) $row['forum_id'] . '&amp;t=' . (int) $row['topic_id']
		);
	}

	protected function escape(string $text): string
	{
		return htmlspecialchars($text, ENT_COMPAT, 'UTF-8');
	}
}
