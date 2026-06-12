<?php

namespace freemitbbs\hotforums\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	private const INDEX_COLLAPSE_ID = 'freemitbbs_hotforums_index';

	protected \phpbb\auth\auth $auth;
	protected \phpbb\cache\service $cache;
	protected \phpbb\config\config $config;
	protected \phpbb\content_visibility $content_visibility;
	protected \phpbb\db\driver\driver_interface $db;
	protected \phpbb\template\template $template;
	protected \phpbb\user $user;
	protected $collapsible_operator;
	protected string $root_path;
	protected string $php_ext;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\cache\service $cache,
		\phpbb\config\config $config,
		\phpbb\content_visibility $content_visibility,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\template\template $template,
		\phpbb\user $user,
		string $root_path,
		string $php_ext,
		$collapsible_operator = null
	)
	{
		$this->auth = $auth;
		$this->cache = $cache;
		$this->config = $config;
		$this->content_visibility = $content_visibility;
		$this->db = $db;
		$this->template = $template;
		$this->user = $user;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
		$this->collapsible_operator = $collapsible_operator;
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

		$viewership = $this->get_viewership_service();
		if ($viewership !== null)
		{
			$has_rows = false;
			foreach ($viewership->get_top_forums($limit) as $row)
			{
				$has_rows = true;
				$this->template->assign_block_vars('hot_forums', [
					'FORUM_ID' => (int) $row['forum_id'],
					'FORUM_NAME' => (string) $row['forum_name'],
					'TOTAL_VIEWS' => (int) $row['total_views'],
					'U_VIEWFORUM' => append_sid($this->root_path . 'viewforum.' . $this->php_ext, 'f=' . (int) $row['forum_id']),
				]);
			}

			$this->assign_hotforums_template_state($has_rows);
			return;
		}

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

		$this->assign_hotforums_template_state($has_rows);
	}

	protected function assign_hotforums_template_state(bool $has_rows): void
	{
		$template_vars = [
			'S_HAS_HOT_FORUMS' => $has_rows,
			'S_HOTFORUMS_COLLAPSIBLE' => false,
		];

		if ($has_rows && $this->has_collapsible_categories())
		{
			$hidden = (bool) $this->collapsible_operator->is_collapsed(self::INDEX_COLLAPSE_ID);
			$template_vars = array_merge($template_vars, [
				'S_HOTFORUMS_COLLAPSIBLE' => true,
				'S_HOTFORUMS_HIDDEN' => $hidden,
				'HOTFORUMS_BLOCK_ID' => self::INDEX_COLLAPSE_ID,
				'U_HOTFORUMS_COLLAPSE_URL' => $this->collapsible_operator->get_collapsible_link(self::INDEX_COLLAPSE_ID),
				'HOTFORUMS_COLLAPSE_HIDDEN_DATA' => $hidden ? '1' : '',
				'HOTFORUMS_COLLAPSE_TITLE' => $this->collapse_button_title($hidden),
				'HOTFORUMS_COLLAPSE_ALT_TITLE' => $this->collapse_button_title(!$hidden),
				'HOTFORUMS_COLLAPSE_ICON' => $hidden ? 'fa-plus-square' : 'fa-minus-square',
			]);
		}

		$this->template->assign_vars($template_vars);
	}

	protected function has_collapsible_categories(): bool
	{
		return $this->collapsible_operator
			&& method_exists($this->collapsible_operator, 'is_collapsed')
			&& method_exists($this->collapsible_operator, 'get_collapsible_link');
	}

	protected function collapse_button_title(bool $hidden): string
	{
		return (string) $this->user->lang('COLLAPSIBLE_CATEGORIES_TITLE', $hidden ? 1 : 0);
	}

	protected function get_viewership_service(): ?object
	{
		$class = '\\freemitbbs\\hotforums\\service\\viewership';
		if (!class_exists($class))
		{
			return null;
		}

		return new $class($this->auth, $this->content_visibility, $this->db, $this->cache, $this->config);
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
