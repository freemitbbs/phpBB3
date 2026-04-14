<?php

namespace freemitbbs\toptopics\controller;

use Symfony\Component\HttpFoundation\RedirectResponse;

class moderation
{
	protected \phpbb\auth\auth $auth;
	protected \phpbb\config\config $config;
	protected \phpbb\db\driver\driver_interface $db;
	protected \phpbb\controller\helper $helper;
	protected \phpbb\request\request_interface $request;
	protected \phpbb\user $user;
	protected \phpbb\language\language $language;
	protected \freemitbbs\toptopics\service\ranker $ranker;
	protected string $topic_overrides_table;
	protected string $root_path;
	protected string $php_ext;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\controller\helper $helper,
		\phpbb\request\request_interface $request,
		\phpbb\user $user,
		\phpbb\language\language $language,
		\freemitbbs\toptopics\service\ranker $ranker,
		string $topic_overrides_table,
		string $root_path,
		string $php_ext
	)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->helper = $helper;
		$this->request = $request;
		$this->user = $user;
		$this->language = $language;
		$this->ranker = $ranker;
		$this->topic_overrides_table = $topic_overrides_table;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	public function topic_override($topic, $state): RedirectResponse
	{
		$topic_id = (int) $topic;
		$state = (string) $state;
		$topic_row = $this->get_topic_row($topic_id);
		$redirect_url = $this->build_topic_url($topic_id, (int) ($topic_row['forum_id'] ?? 0));

		if (!$topic_row
			|| !$this->auth->acl_get('a_board')
			|| !$this->auth->acl_get('f_read', (int) $topic_row['forum_id'])
			|| !in_array($state, ['normal', 'boost', 'demote', 'kill'], true)
			|| !check_link_hash($this->request->variable('hash', ''), 'toptopics_override_' . $topic_id . '_' . $state))
		{
			return new RedirectResponse($redirect_url);
		}

		if ($state === 'normal')
		{
			$sql = 'DELETE FROM ' . $this->topic_overrides_table . '
				WHERE topic_id = ' . $topic_id;
			$this->db->sql_query($sql);
		}
		else
		{
			$sql = 'DELETE FROM ' . $this->topic_overrides_table . '
				WHERE topic_id = ' . $topic_id;
			$this->db->sql_query($sql);

			$sql = 'INSERT INTO ' . $this->topic_overrides_table . ' ' .
				$this->db->sql_build_array('INSERT', [
					'topic_id' => $topic_id,
					'override_state' => $state,
					'updated_by' => (int) $this->user->data['user_id'],
					'updated_time' => time(),
				]);
			$this->db->sql_query($sql);
		}

		$this->ranker->invalidate_forums([(int) $topic_row['forum_id']]);
		$this->ranker->clear_materialized_scopes_for_forums([(int) $topic_row['forum_id']]);

		return new RedirectResponse($redirect_url);
	}

	protected function get_topic_row(int $topic_id): array|false
	{
		$sql = 'SELECT topic_id, forum_id
			FROM ' . TOPICS_TABLE . '
			WHERE topic_id = ' . $topic_id;
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row;
	}

	protected function build_topic_url(int $topic_id, int $forum_id): string
	{
		return append_sid(
			$this->root_path . 'viewtopic.' . $this->php_ext,
			'f=' . $forum_id . '&t=' . $topic_id
		);
	}
}
