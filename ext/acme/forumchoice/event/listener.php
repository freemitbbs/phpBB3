<?php

namespace acme\forumchoice\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use phpbb\db\driver\driver_interface;
use phpbb\db\tools\tools_interface;
use phpbb\content_visibility;
use phpbb\user;
use phpbb\template\template;
use phpbb\controller\helper;
use phpbb\auth\auth;

class listener implements EventSubscriberInterface
{
	protected $db;
	protected $db_tools;
	protected $content_visibility;
	protected $user;
	protected $template;
	protected $helper;
	protected $auth;
	protected $table_prefix;
	protected $root_path;
	protected $php_ext;
	protected $favorite_table_available;

	public function __construct(
		driver_interface $db,
		tools_interface $db_tools,
		content_visibility $content_visibility,
		user $user,
		template $template,
		helper $helper,
		auth $auth,
		$table_prefix,
		$root_path,
		$php_ext
	)
	{
		$this->db = $db;
		$this->db_tools = $db_tools;
		$this->content_visibility = $content_visibility;
		$this->user = $user;
		$this->template = $template;
		$this->helper = $helper;
		$this->auth = $auth;
		$this->table_prefix = $table_prefix;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
		$this->favorite_table_available = null;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.viewforum_generate_page_after'	=> 'add_favorite_button',
			'core.index_modify_page_title'			=> 'show_favorites_on_index',
		];
	}

	/**
	 * Add a favorite/unfavorite button on the viewforum page
	 */
	public function add_favorite_button($event)
	{
		if ($this->user->data['user_id'] == ANONYMOUS)
		{
			return;
		}

		$this->user->add_lang_ext('acme/forumchoice', 'common');

		$forum_data = $event['forum_data'];
		$forum_id = (int) $forum_data['forum_id'];

		// Only show on actual post forums, not categories or links
		if ($forum_data['forum_type'] != FORUM_POST)
		{
			return;
		}

		if (!$this->favorite_table_exists())
		{
			return;
		}

		$is_favorite = $this->is_favorite_forum((int) $this->user->data['user_id'], $forum_id);

		$toggle_url = $this->build_toggle_url(
			$forum_id,
			append_sid($this->root_path . 'viewforum.' . $this->php_ext, 'f=' . $forum_id),
			$is_favorite ? 'remove' : 'add'
		);

		$this->template->assign_vars([
			'S_FORUM_FAVORITE'		=> $is_favorite,
			'U_FAVORITE_TOGGLE'		=> $toggle_url,
			'L_FAVORITE_ACTION'		=> $is_favorite ? $this->user->lang['REMOVE_FAVORITE'] : $this->user->lang['ADD_FAVORITE'],
		]);
	}

	/**
	 * Display favorited forums as a block at the top of the index page
	 */
	public function show_favorites_on_index($event)
	{
		if ($this->user->data['user_id'] == ANONYMOUS)
		{
			return;
		}

		$this->user->add_lang_ext('acme/forumchoice', 'common');

		if (!$this->favorite_table_exists())
		{
			return;
		}

		$table = $this->get_favorite_table();
		$index_url = append_sid($this->root_path . 'index.' . $this->php_ext);
		$sql = 'SELECT f.forum_id, f.forum_name, f.forum_desc, f.forum_desc_uid,
				f.forum_desc_bitfield, f.forum_desc_options, f.forum_type,
				f.forum_last_post_id, f.forum_last_post_subject, f.forum_last_post_time,
				f.forum_last_poster_id, f.forum_last_poster_name, f.forum_last_poster_colour,
				f.forum_topics_approved, f.forum_topics_unapproved, f.forum_topics_softdeleted,
				f.forum_posts_approved, f.forum_posts_unapproved, f.forum_posts_softdeleted,
				f.forum_status, f.forum_image, f.forum_link, f.forum_flags, f.forum_options
				FROM ' . $table . ' uf
			INNER JOIN ' . FORUMS_TABLE . ' f ON f.forum_id = uf.forum_id
			WHERE uf.user_id = ' . (int) $this->user->data['user_id'] . '
			ORDER BY f.left_id ASC';
		$result = $this->safe_sql_query($sql);
		if (!$result)
		{
			return;
		}

		$has_favorites = false;
		while ($row = $this->db->sql_fetchrow($result))
		{
			$forum_id = (int) $row['forum_id'];

			if (!$this->auth->acl_get('f_list', $forum_id))
			{
				continue;
			}

			$has_favorites = true;
			$row['forum_topics'] = $this->content_visibility->get_count('forum_topics', $row, $forum_id);
			$row['forum_posts'] = $this->content_visibility->get_count('forum_posts', $row, $forum_id);

			$last_post_subject = '';
			$last_post_time = '';
			$last_post_time_rfc3339 = '';
			$last_post_url = '';
			if ($row['forum_last_post_id'])
			{
				if ($this->auth->acl_gets('f_read', 'f_list_topics', $forum_id))
				{
					$last_post_subject = censor_text($row['forum_last_post_subject']);
				}
				$last_post_time = $this->user->format_date($row['forum_last_post_time']);
				$last_post_time_rfc3339 = gmdate(DATE_RFC3339, $row['forum_last_post_time']);
				$last_post_url = append_sid($this->root_path . 'viewtopic.' . $this->php_ext, 'p=' . $row['forum_last_post_id']) . '#p' . $row['forum_last_post_id'];
			}

			$this->template->assign_block_vars('favorite_forums', [
				'FORUM_ID'			=> $forum_id,
				'FORUM_NAME'		=> $row['forum_name'],
				'FORUM_DESC'		=> generate_text_for_display($row['forum_desc'], $row['forum_desc_uid'], $row['forum_desc_bitfield'], $row['forum_desc_options']),
				'FORUM_IMAGE'		=> ($row['forum_image']) ? '<img src="' . $this->root_path . $row['forum_image'] . '" alt="" />' : '',
				'TOPICS'			=> $row['forum_topics'],
				'POSTS'				=> $row['forum_posts'],
				'LAST_POST_SUBJECT'	=> truncate_string($last_post_subject, 30, 255, false, $this->user->lang['ELLIPSIS']),
				'LAST_POST_TIME'	=> $last_post_time,
				'LAST_POST_TIME_RFC3339' => $last_post_time_rfc3339,
				'LAST_POSTER_FULL'	=> get_username_string('full', $row['forum_last_poster_id'], $row['forum_last_poster_name'], $row['forum_last_poster_colour']),
				'L_FAVORITE_ACTION'	=> $this->user->lang['REMOVE_FAVORITE'],
				'U_FAVORITE_TOGGLE'	=> $this->build_toggle_url($forum_id, $index_url, 'remove'),
				'U_VIEWFORUM'		=> append_sid($this->root_path . 'viewforum.' . $this->php_ext, 'f=' . $forum_id),
				'U_LAST_POST'		=> $last_post_url,
				'S_LOCKED'			=> ($row['forum_status'] == ITEM_LOCKED),
			]);
		}
		$this->db->sql_freeresult($result);

		$this->template->assign_vars([
			'S_HAS_FAVORITE_FORUMS' => $has_favorites,
		]);
	}

	protected function build_toggle_url($forum_id, $redirect_url, $action)
	{
		return $this->helper->route('acme_forumchoice_toggle', [
			'forum_id'	=> (int) $forum_id,
			'action'	=> $action,
			'redirect'	=> $redirect_url,
			'hash'		=> generate_link_hash('favorite_forum_' . (int) $forum_id),
		]);
	}

	protected function favorite_table_exists()
	{
		if ($this->favorite_table_available === null)
		{
			$this->favorite_table_available = $this->db_tools->sql_table_exists($this->get_favorite_table());
		}

		return $this->favorite_table_available;
	}

	protected function get_favorite_table()
	{
		return $this->table_prefix . 'user_favorite_forums';
	}

	protected function is_favorite_forum($user_id, $forum_id)
	{
		$sql = 'SELECT forum_id FROM ' . $this->get_favorite_table() . '
			WHERE user_id = ' . (int) $user_id . '
				AND forum_id = ' . (int) $forum_id;
		$result = $this->safe_sql_query($sql);
		if (!$result)
		{
			return false;
		}

		$is_favorite = (bool) $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $is_favorite;
	}

	protected function safe_sql_query($sql)
	{
		$this->db->sql_return_on_error(true);
		$result = $this->db->sql_query($sql);
		$errored = $this->db->get_sql_error_triggered();
		$this->db->sql_return_on_error(false);

		return $errored ? false : $result;
	}
}
