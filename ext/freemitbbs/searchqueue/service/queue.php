<?php

namespace freemitbbs\searchqueue\service;

class queue
{
	private const NATIVE_SEARCH_CLASS = 'phpbb\search\fulltext_native';

	protected \phpbb\auth\auth $auth;
	protected \phpbb\config\config $config;
	protected \phpbb\db\driver\driver_interface $db;
	protected \phpbb\user $user;
	protected \phpbb\event\dispatcher_interface $dispatcher;
	protected string $queue_table;
	protected string $root_path;
	protected string $php_ext;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\user $user,
		\phpbb\event\dispatcher_interface $dispatcher,
		string $queue_table,
		string $root_path,
		string $php_ext
	)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->user = $user;
		$this->dispatcher = $dispatcher;
		$this->queue_table = $queue_table;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	public function should_defer_submit_index(bool $update_search_index, array $data): bool
	{
		if (!$update_search_index || empty($data['enable_indexing']))
		{
			return false;
		}

		return $this->can_process();
	}

	public function can_process(): bool
	{
		return $this->is_enabled()
			&& $this->is_native_search_active()
			&& !empty($this->config['fulltext_native_load_upd'])
			&& class_exists((string) $this->config['search_type']);
	}

	public function is_enabled(): bool
	{
		return !empty($this->config['freemitbbs_searchqueue_enabled']);
	}

	public function queue_post(int $post_id): bool
	{
		return $this->queue_posts([$post_id]);
	}

	public function queue_posts(array $post_ids): bool
	{
		$post_ids = $this->normalize_post_ids($post_ids);
		if (empty($post_ids))
		{
			return true;
		}

		$rows = [];
		$queued_time = time();
		foreach ($post_ids as $post_id)
		{
			$rows[] = [
				'post_id' => (int) $post_id,
				'queued_time' => $queued_time,
			];
		}

		$success = true;
		$this->db->sql_return_on_error(true);
		try
		{
			$sql = 'DELETE FROM ' . $this->queue_table . '
				WHERE ' . $this->db->sql_in_set('post_id', $post_ids);
			$success = ($this->db->sql_query($sql) !== false) && $success;
			$success = ($this->db->sql_multi_insert($this->queue_table, $rows) !== false) && $success;
			$success = !$this->db->get_sql_error_triggered() && $success;
		}
		finally
		{
			$this->db->sql_return_on_error(false);
		}

		return $success;
	}

	public function has_queued_posts(): bool
	{
		$this->db->sql_return_on_error(true);
		try
		{
			$sql = 'SELECT post_id
				FROM ' . $this->queue_table;
			$result = $this->db->sql_query_limit($sql, 1);
			if ($result === false)
			{
				return false;
			}

			$row = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);

			return !empty($row);
		}
		finally
		{
			$this->db->sql_return_on_error(false);
		}
	}

	public function process_queued_posts(int $batch_size): int
	{
		if (!$this->can_process())
		{
			return 0;
		}

		$post_ids = $this->get_queued_post_ids($batch_size);
		if (empty($post_ids))
		{
			return 0;
		}

		$search = $this->create_search_backend();
		if ($search === null)
		{
			return 0;
		}

		$post_rows = $this->get_post_rows($post_ids);
		$handled_post_ids = [];
		$indexed = 0;

		foreach ($post_ids as $post_id)
		{
			if (empty($post_rows[$post_id]) || empty($post_rows[$post_id]['enable_indexing']))
			{
				$handled_post_ids[] = $post_id;
				continue;
			}

			$row = $post_rows[$post_id];
			$message = (string) $row['post_text'];
			$subject = (string) $row['post_subject'];

			try
			{
				// "edit" syncs search_wordmatch to the current saved post state for both new and changed posts.
				$search->index('edit', $post_id, $message, $subject, (int) $row['poster_id'], (int) $row['forum_id']);
				$handled_post_ids[] = $post_id;
				$indexed++;
			}
			catch (\Throwable $e)
			{
				// Leave this post queued so a later cron run can retry it.
			}
		}

		$this->delete_queued_posts($handled_post_ids);

		return $indexed;
	}

	public function delete_queued_posts(array $post_ids): void
	{
		$post_ids = $this->normalize_post_ids($post_ids);
		if (empty($post_ids))
		{
			return;
		}

		$this->db->sql_return_on_error(true);
		try
		{
			$sql = 'DELETE FROM ' . $this->queue_table . '
				WHERE ' . $this->db->sql_in_set('post_id', $post_ids);
			$this->db->sql_query($sql);
		}
		finally
		{
			$this->db->sql_return_on_error(false);
		}
	}

	protected function get_queued_post_ids(int $limit): array
	{
		$limit = max(1, min(500, $limit));
		$post_ids = [];

		$this->db->sql_return_on_error(true);
		try
		{
			$sql = 'SELECT post_id
				FROM ' . $this->queue_table . '
				ORDER BY queued_time ASC, post_id ASC';
			$result = $this->db->sql_query_limit($sql, $limit);
			if ($result === false)
			{
				return [];
			}

			while ($row = $this->db->sql_fetchrow($result))
			{
				$post_ids[] = (int) $row['post_id'];
			}
			$this->db->sql_freeresult($result);
		}
		finally
		{
			$this->db->sql_return_on_error(false);
		}

		return $this->normalize_post_ids($post_ids);
	}

	protected function get_post_rows(array $post_ids): array
	{
		$post_ids = $this->normalize_post_ids($post_ids);
		if (empty($post_ids))
		{
			return [];
		}

		$rows = [];
		$sql = 'SELECT p.post_id, p.post_subject, p.post_text, p.poster_id, p.forum_id, f.enable_indexing
			FROM ' . POSTS_TABLE . ' p
			LEFT JOIN ' . FORUMS_TABLE . ' f
				ON f.forum_id = p.forum_id
			WHERE ' . $this->db->sql_in_set('p.post_id', $post_ids);
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[(int) $row['post_id']] = $row;
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	protected function create_search_backend(): ?object
	{
		$search_type = (string) $this->config['search_type'];
		if (!$this->is_native_search_active() || !class_exists($search_type))
		{
			return null;
		}

		$error = false;
		$search = new $search_type($error, $this->root_path, $this->php_ext, $this->auth, $this->config, $this->db, $this->user, $this->dispatcher);

		return $error ? null : $search;
	}

	protected function is_native_search_active(): bool
	{
		return ltrim((string) ($this->config['search_type'] ?? ''), '\\') === self::NATIVE_SEARCH_CLASS;
	}

	protected function normalize_post_ids(array $post_ids): array
	{
		$normalized = [];
		foreach ($post_ids as $post_id)
		{
			$post_id = (int) $post_id;
			if ($post_id > 0)
			{
				$normalized[$post_id] = $post_id;
			}
		}

		return array_values($normalized);
	}
}
