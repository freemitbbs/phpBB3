<?php

namespace freemitbbs\hotforums\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	protected \phpbb\auth\auth $auth;
	protected \phpbb\config\config $config;
	protected \phpbb\content_visibility $content_visibility;
	protected \phpbb\db\driver\driver_interface $db;
	protected \phpbb\template\template $template;
	protected \phpbb\user $user;
	protected string $root_path;
	protected string $php_ext;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\config\config $config,
		\phpbb\content_visibility $content_visibility,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\template\template $template,
		\phpbb\user $user,
		string $root_path,
		string $php_ext
	)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->content_visibility = $content_visibility;
		$this->db = $db;
		$this->template = $template;
		$this->user = $user;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.index_modify_page_title' => 'show_hotforums_on_index',
		];
	}

	public function show_hotforums_on_index($event): void
	{
		$this->user->add_lang_ext('freemitbbs/hotforums', 'common');

		$limit = isset($this->config['hotforums_index_limit']) ? (int) $this->config['hotforums_index_limit'] : 8;
		$limit = max(1, min(100, $limit));

		$forum_ids = $this->get_readable_forum_ids();
		if (empty($forum_ids))
		{
			return;
		}

		$visibility_sql = $this->content_visibility->get_forums_visibility_sql('topic', $forum_ids, 't.');
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

		$result = $this->db->sql_query_limit($sql, $limit);
		$has_rows = false;
		while ($row = $this->db->sql_fetchrow($result))
		{
			$total_views = (int) ($row['total_views'] ?? 0);
			if ($total_views <= 0)
			{
				continue;
			}

			$has_rows = true;
			$this->template->assign_block_vars('hot_forums', [
				'FORUM_ID' => (int) $row['forum_id'],
				'FORUM_NAME' => (string) $row['forum_name'],
				'TOTAL_VIEWS' => $total_views,
				'U_VIEWFORUM' => append_sid($this->root_path . 'viewforum.' . $this->php_ext, 'f=' . (int) $row['forum_id']),
			]);
		}
		$this->db->sql_freeresult($result);

		$this->template->assign_var('S_HAS_HOT_FORUMS', $has_rows);
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
