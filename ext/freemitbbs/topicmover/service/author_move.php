<?php

namespace freemitbbs\topicmover\service;

class author_move
{
	protected \phpbb\auth\auth $auth;
	protected \phpbb\config\config $config;
	protected \phpbb\db\driver\driver_interface $db;
	protected \phpbb\log\log_interface $log;
	protected string $phpbb_root_path;
	protected string $php_ext;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\log\log_interface $log,
		string $phpbb_root_path,
		string $php_ext
	)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->log = $log;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
	}

	public function topic(int $topic_id): ?array
	{
		if ($topic_id <= 0)
		{
			return null;
		}

		$sql = 'SELECT t.*, f.forum_name, f.forum_type, f.forum_status, f.forum_password
			FROM ' . TOPICS_TABLE . ' t
			INNER JOIN ' . FORUMS_TABLE . ' f
				ON f.forum_id = t.forum_id
			WHERE t.topic_id = ' . $topic_id;
		$result = $this->db->sql_query($sql);
		$topic = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $topic ?: null;
	}

	public function can_move(array $topic, int $user_id): bool
	{
		$forum_id = (int) ($topic['forum_id'] ?? 0);

		return $user_id > ANONYMOUS
			&& (int) ($topic['topic_poster'] ?? 0) === $user_id
			&& (int) ($topic['topic_visibility'] ?? ITEM_UNAPPROVED) === ITEM_APPROVED
			&& (int) ($topic['topic_moved_id'] ?? 0) === 0
			&& (int) ($topic['topic_status'] ?? ITEM_LOCKED) === ITEM_UNLOCKED
			&& (int) ($topic['topic_type'] ?? POST_GLOBAL) !== POST_GLOBAL
			&& $forum_id > 0
			&& (int) ($topic['forum_type'] ?? FORUM_CAT) === FORUM_POST
			&& (int) ($topic['forum_status'] ?? ITEM_LOCKED) === ITEM_UNLOCKED
			&& (string) ($topic['forum_password'] ?? '') === ''
			&& !$this->is_managed_forum($forum_id)
			&& $this->auth->acl_get('f_list', $forum_id)
			&& $this->auth->acl_get('f_read', $forum_id)
			&& $this->auth->acl_get('f_post', $forum_id);
	}

	public function destination_forums(array $topic): array
	{
		$source_forum_id = (int) ($topic['forum_id'] ?? 0);
		$topic_type = (int) ($topic['topic_type'] ?? POST_NORMAL);
		$rowset = $this->forum_rows();
		$eligible_forum_ids = [];

		foreach ($rowset as $row)
		{
			$forum_id = (int) $row['forum_id'];
			if ($forum_id !== $source_forum_id && $this->is_destination_allowed($row, $topic_type))
			{
				$eligible_forum_ids[$forum_id] = true;
			}
		}

		$visible_category_ids = [];
		foreach ($rowset as $row)
		{
			$forum_id = (int) $row['forum_id'];
			if ((int) $row['forum_type'] !== FORUM_CAT || !$this->auth->acl_get('f_list', $forum_id))
			{
				continue;
			}

			foreach ($rowset as $candidate)
			{
				$candidate_id = (int) $candidate['forum_id'];
				if (isset($eligible_forum_ids[$candidate_id])
					&& (int) $candidate['left_id'] > (int) $row['left_id']
					&& (int) $candidate['right_id'] < (int) $row['right_id'])
				{
					$visible_category_ids[$forum_id] = true;
					break;
				}
			}
		}

		$forums = [];
		foreach ($rowset as $row)
		{
			$forum_id = (int) $row['forum_id'];
			$is_category = (int) $row['forum_type'] === FORUM_CAT;
			if ((!$is_category && !isset($eligible_forum_ids[$forum_id]))
				|| ($is_category && !isset($visible_category_ids[$forum_id])))
			{
				continue;
			}

			$level = 0;
			foreach ($rowset as $ancestor)
			{
				$ancestor_id = (int) $ancestor['forum_id'];
				if (isset($visible_category_ids[$ancestor_id])
					&& (int) $ancestor['left_id'] < (int) $row['left_id']
					&& (int) $ancestor['right_id'] > (int) $row['right_id'])
				{
					$level++;
				}
			}

			$forums[] = [
				'forum_id' => $forum_id,
				'forum_name' => (string) $row['forum_name'],
				'is_category' => $is_category,
				'level' => $level,
			];
		}

		return $forums;
	}

	public function move(int $topic_id, int $destination_forum_id, int $user_id, string $user_ip): array
	{
		$this->db->sql_transaction('begin');

		try
		{
			$topic = $this->locked_topic($topic_id);
			if (!$topic || !$this->can_move($topic, $user_id))
			{
				throw new \RuntimeException('TOPICMOVER_AUTHOR_MOVE_NOT_ALLOWED');
			}

			$destination = $this->destination_forum($destination_forum_id);
			if (!$destination
				|| $destination_forum_id === (int) $topic['forum_id']
				|| !$this->is_destination_allowed($destination, (int) $topic['topic_type']))
			{
				throw new \InvalidArgumentException('TOPICMOVER_AUTHOR_MOVE_INVALID_DESTINATION');
			}

			if (!function_exists('move_topics'))
			{
				include_once($this->phpbb_root_path . 'includes/functions_admin.' . $this->php_ext);
			}

			$source_forum_id = (int) $topic['forum_id'];
			move_topics([$topic_id], $destination_forum_id, true);

			$this->log->add('mod', $user_id, $user_ip, 'LOG_TOPICMOVER_AUTHOR_MOVED', false, [
				'forum_id' => $destination_forum_id,
				'topic_id' => $topic_id,
				(string) $topic['forum_name'],
				(string) $destination['forum_name'],
				$source_forum_id,
				$destination_forum_id,
			]);

			$this->db->sql_transaction('commit');

			return [
				'topic_id' => $topic_id,
				'source_forum_id' => $source_forum_id,
				'destination_forum_id' => $destination_forum_id,
			];
		}
		catch (\Throwable $e)
		{
			$this->db->sql_transaction('rollback');
			throw $e;
		}
	}

	protected function locked_topic(int $topic_id): ?array
	{
		$sql = 'SELECT t.*, f.forum_name, f.forum_type, f.forum_status, f.forum_password
			FROM ' . TOPICS_TABLE . ' t
			INNER JOIN ' . FORUMS_TABLE . ' f
				ON f.forum_id = t.forum_id
			WHERE t.topic_id = ' . $topic_id . '
			FOR UPDATE';
		$result = $this->db->sql_query($sql);
		$topic = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $topic ?: null;
	}

	protected function destination_forum(int $forum_id): ?array
	{
		if ($forum_id <= 0)
		{
			return null;
		}

		$sql = 'SELECT forum_id, forum_name, forum_type, forum_status, forum_password, left_id, right_id
			FROM ' . FORUMS_TABLE . '
			WHERE forum_id = ' . $forum_id;
		$result = $this->db->sql_query($sql);
		$forum = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $forum ?: null;
	}

	protected function forum_rows(): array
	{
		$sql = 'SELECT forum_id, forum_name, forum_type, forum_status, forum_password, left_id, right_id
			FROM ' . FORUMS_TABLE . '
			WHERE ' . $this->db->sql_in_set('forum_type', [FORUM_CAT, FORUM_POST]) . '
			ORDER BY left_id ASC';
		$result = $this->db->sql_query($sql);
		$forums = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$forums[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $forums;
	}

	protected function is_destination_allowed(array $forum, int $topic_type): bool
	{
		$forum_id = (int) ($forum['forum_id'] ?? 0);
		if ($forum_id <= 0
			|| (int) ($forum['forum_type'] ?? FORUM_CAT) !== FORUM_POST
			|| (int) ($forum['forum_status'] ?? ITEM_LOCKED) !== ITEM_UNLOCKED
			|| (string) ($forum['forum_password'] ?? '') !== ''
			|| $this->is_managed_forum($forum_id)
			|| !$this->auth->acl_get('f_list', $forum_id)
			|| !$this->auth->acl_get('f_read', $forum_id)
			|| !$this->auth->acl_get('f_post', $forum_id)
			|| !$this->auth->acl_get('f_noapprove', $forum_id))
		{
			return false;
		}

		if ($topic_type === POST_STICKY && !$this->auth->acl_get('f_sticky', $forum_id))
		{
			return false;
		}

		return $topic_type !== POST_ANNOUNCE || $this->auth->acl_get('f_announce', $forum_id);
	}

	protected function is_managed_forum(int $forum_id): bool
	{
		$managed_forum_ids = [
			(int) ($this->config['freemitbbs_blog_forum_managed'] ?? 0),
			(int) ($this->config['newsscraper_digest_forum_managed'] ?? 0),
		];

		return $forum_id > 0 && in_array($forum_id, $managed_forum_ids, true);
	}
}
