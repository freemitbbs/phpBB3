<?php

namespace freemitbbs\newmemberguard\controller;

class move_post
{
	protected \phpbb\auth\auth $auth;
	protected \phpbb\db\driver\driver_interface $db;
	protected \phpbb\language\language $language;
	protected \phpbb\log\log_interface $log;
	protected \phpbb\request\request_interface $request;
	protected \phpbb\user $user;
	protected \phpbb\controller\helper $helper;
	protected string $phpbb_root_path;
	protected string $php_ext;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\language\language $language,
		\phpbb\log\log_interface $log,
		\phpbb\request\request_interface $request,
		\phpbb\user $user,
		\phpbb\controller\helper $helper,
		string $phpbb_root_path,
		string $php_ext
	)
	{
		$this->auth = $auth;
		$this->db = $db;
		$this->language = $language;
		$this->log = $log;
		$this->request = $request;
		$this->user = $user;
		$this->helper = $helper;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
	}

	/**
	 * Move a post to a moderator-selected topic from the new-member approval panel.
	 */
	public function move($post_id)
	{
		$post_id = (int) $post_id;
		$this->language->add_lang(['posting', 'mcp']);

		if (!$this->request->is_set_post('move_post') || !check_form_key('freemitbbs_newmemberguard_move'))
		{
			trigger_error('FORM_INVALID');
		}

		$post = $this->post_row($post_id);
		if (!$post)
		{
			trigger_error('NO_POST');
		}

		$source_forum_id = (int) $post['forum_id'];
		if (!$this->auth->acl_get('m_approve', $source_forum_id) || !$this->auth->acl_get('m_', $source_forum_id))
		{
			send_status_line(403, 'Forbidden');
			trigger_error('NOT_AUTHORISED');
		}

		$target_topic_id = (int) $this->request->variable('manual_topic_id', 0);
		if ($target_topic_id <= 0)
		{
			$target_topic_id = (int) $this->request->variable('target_topic_id', 0);
		}

		if ($target_topic_id <= 0 || $target_topic_id === (int) $post['topic_id'])
		{
			trigger_error($this->language->lang('NEWMEMBERGUARD_INVALID_TARGET'));
		}

		$target = $this->topic_row($target_topic_id);
		if (!$target)
		{
			trigger_error($this->language->lang('NEWMEMBERGUARD_INVALID_TARGET'));
		}

		$target_forum_id = (int) $target['forum_id'];
		if (!$this->auth->acl_get('f_read', $target_forum_id) || !$this->auth->acl_get('m_', $target_forum_id))
		{
			send_status_line(403, 'Forbidden');
			trigger_error('NOT_AUTHORISED');
		}

		if (!function_exists('move_posts'))
		{
			include_once($this->phpbb_root_path . 'includes/functions_admin.' . $this->php_ext);
		}

		move_posts([$post_id], $target_topic_id, true);

		$this->log->add('mod', (int) $this->user->data['user_id'], $this->user->ip, 'LOG_MOVE', false, [
			'forum_id' => $target_forum_id,
			'topic_id' => $target_topic_id,
			(string) $this->forum_name($source_forum_id),
			(string) $this->forum_name($target_forum_id),
			$source_forum_id,
			$target_forum_id,
		]);

		redirect(append_sid("{$this->phpbb_root_path}mcp.{$this->php_ext}", 'i=queue&mode=unapproved_posts'));
	}

	protected function post_row(int $post_id): ?array
	{
		$sql = 'SELECT post_id, topic_id, forum_id
			FROM ' . POSTS_TABLE . '
			WHERE post_id = ' . $post_id;
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row ?: null;
	}

	protected function topic_row(int $topic_id): ?array
	{
		$sql = 'SELECT topic_id, forum_id, topic_visibility
			FROM ' . TOPICS_TABLE . '
			WHERE topic_id = ' . $topic_id;
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row ?: null;
	}

	protected function forum_name(int $forum_id): string
	{
		$sql = 'SELECT forum_name
			FROM ' . FORUMS_TABLE . '
			WHERE forum_id = ' . $forum_id;
		$result = $this->db->sql_query_limit($sql, 1);
		$name = (string) $this->db->sql_fetchfield('forum_name');
		$this->db->sql_freeresult($result);

		return $name;
	}
}
